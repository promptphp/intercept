<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor;

use Closure;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Ai\Prompts\AgentPrompt;
use PromptPHP\Intercept\PIIRedactor\Defaults\PIIRedactorDefaults;
use PromptPHP\Intercept\PIIRedactor\Detectors\Contracts\Detector;
use PromptPHP\Intercept\PIIRedactor\Detectors\RegexDetector;
use PromptPHP\Intercept\PIIRedactor\Enums\ActionTypes;
use PromptPHP\Intercept\PIIRedactor\Enums\EntityTypes;
use PromptPHP\Intercept\PIIRedactor\Exceptions\PIIRedactorException;
use PromptPHP\Intercept\PIIRedactor\ValueObjects\Detection;
use PromptPHP\Intercept\PIIRedactor\ValueObjects\RedactionResult;
use PromptPHP\Intercept\Support\InterceptConfig;

class PIIRedactor
{
    /**
     * The PII entities to detect.
     *
     * @var array<int, string>
     */
    protected array $entities;

    /**
     * The entities that should always block the prompt.
     *
     * @var array<int, string>
     */
    protected array $blockEntities;

    /**
     * The allowed email addresses.
     *
     * @var array<int, string>
     */
    protected array $allowedEmails;

    /**
     * The allowed email domains.
     *
     * @var array<int, string>
     */
    protected array $allowedDomains;

    /**
     * The action to take when PII is detected.
     */
    protected ActionTypes $action = ActionTypes::REDACT;

    /**
     * The replacement format for redacted values.
     */
    protected string $replacementFormat = '[{{TYPE}}_{{INDEX}}]';

    /**
     * The character to use when masking values.
     */
    protected string $maskCharacter = '*';

    /**
     * Whether to log detections.
     */
    protected bool $logDetections = true;

    /**
     * Whether to include a short prompt preview in logs.
     */
    protected bool $logPreview = false;

    /**
     * Custom callback for handling detected PII.
     */
    protected ?Closure $callback;

    /**
     * The configured detectors.
     *
     * @var array<int, Detector>
     */
    protected array $detectors;

    /**
     * Create a new PII Redactor instance.
     *
     * @param array<int, string>|null   $entities          PII entities to detect.
     * @param string|null               $action            What to do: 'redact', 'mask', 'block', or 'log'.
     * @param Closure|null              $callback          Custom handler for detected PII.
     * @param array<int, string>|null   $blockEntities     Entities that should always block.
     * @param array<int, string>|null   $allowedEmails     Email addresses to ignore.
     * @param array<int, string>|null   $allowedDomains    Email domains to ignore.
     * @param string|null               $replacementFormat Replacement format for redaction.
     * @param string|null               $maskCharacter     Character used for masking.
     * @param bool|null                 $logDetections     Whether to log detections.
     * @param bool|null                 $logPreview        Whether to log a short prompt preview.
     * @param array<int, Detector>|null $detectors         Additional custom detectors.
     */
    public function __construct(
        ?array $entities = null,
        ?string $action = null,
        ?Closure $callback = null,
        ?array $blockEntities = null,
        ?array $allowedEmails = null,
        ?array $allowedDomains = null,
        ?string $replacementFormat = null,
        ?string $maskCharacter = null,
        ?bool $logDetections = null,
        ?bool $logPreview = null,
        ?array $detectors = null,
    ) {
        $config = InterceptConfig::middleware('pii_redactor', PIIRedactorDefaults::values());

        $entities ??= $config['entities'];
        $action ??= $config['action'];
        $blockEntities ??= $config['block_entities'];
        $allowedEmails ??= $config['allowed_emails'];
        $allowedDomains ??= $config['allowed_domains'];
        $replacementFormat ??= $config['replacement_format'];
        $maskCharacter ??= $config['mask_character'];
        $logDetections ??= $config['log_detections'];
        $logPreview ??= $config['log_preview'];

        $this->validateAction($action);
        $this->validateEntities($entities);
        $this->validateEntities($blockEntities);
        $this->validateDetectors($detectors ?? []);

        $this->entities          = $entities;
        $this->action            = ActionTypes::from($action);
        $this->callback          = $callback;
        $this->blockEntities     = $blockEntities;
        $this->allowedEmails     = array_map('strtolower', $allowedEmails);
        $this->allowedDomains    = array_map('strtolower', $allowedDomains);
        $this->replacementFormat = $replacementFormat;
        $this->maskCharacter     = mb_substr($maskCharacter, 0, 1) ?: '*';
        $this->logDetections     = $logDetections;
        $this->logPreview        = $logPreview;
        $this->detectors         = [
            ...$this->defaultDetectors(),
            ...($detectors ?? []),
        ];
    }

    /**
     * Handle the incoming prompt.
     *
     * @param AgentPrompt $prompt The agent being prompted.
     * @param Closure     $next   The next middleware in the pipeline.
     */
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $result = $this->detect($prompt->prompt);

        if (! $result->hasDetections()) {
            return $next($prompt);
        }

        if ($this->logDetections || $this->action === ActionTypes::LOG) {
            $this->log($prompt, $result);
        }

        if ($this->callback !== null) {
            return ($this->callback)($prompt, $next, $result);
        }

        if ($this->hasBlockedEntity($result) || $this->action === ActionTypes::BLOCK) {
            $this->block();
        }

        return match ($this->action) {
            ActionTypes::LOG  => $next($prompt),
            ActionTypes::MASK => $next($prompt->revise($this->mask($prompt->prompt, $result->detections)->text)),
            default           => $next($prompt->revise($this->redact($prompt->prompt, $result->detections)->text)),
        };
    }

    /**
     * Detect PII in the given text.
     *
     * @param string $text The text to scan for PII.
     *
     * @return RedactionResult The result of the detection, including any found PII.
     */
    protected function detect(string $text): RedactionResult
    {
        $detections = [];

        foreach ($this->detectors as $detector) {
            if (! in_array($detector->type(), $this->entities, true)) {
                continue;
            }

            foreach ($detector->detect($text) as $detection) {
                if (! $this->shouldKeepDetection($detection)) {
                    continue;
                }

                $detections[] = $detection;
            }
        }

        return new RedactionResult(
            text: $text,
            detections: $this->removeOverlaps($detections),
        );
    }

    /**
     * Redact detected PII.
     *
     * @param string                $text       The text to scan for PII.
     * @param array<int, Detection> $detections The detected PII instances.
     *
     * @return RedactionResult The result of the redaction, including the modified text and any found PII
     */
    protected function redact(string $text, array $detections): RedactionResult
    {
        $replacements = $this->buildReplacements(
            $detections,
            fn (Detection $detection, int $index): string => $this->formatReplacement($detection, $index),
        );

        return new RedactionResult(
            text: $this->applyReplacements($text, $replacements),
            detections: $detections,
        );
    }

    /**
     * Mask detected PII.
     *
     * @param string                $text       The text to scan for PII.
     * @param array<int, Detection> $detections The detected PII instances.
     *
     * @return RedactionResult The result of the masking, including the modified text and any found PII
     */
    protected function mask(string $text, array $detections): RedactionResult
    {
        $replacements = $this->buildReplacements(
            $detections,
            fn (Detection $detection): string => $this->maskValue($detection),
        );

        return new RedactionResult(
            text: $this->applyReplacements($text, $replacements),
            detections: $detections,
        );
    }

    /**
     * Block the prompt.
     *
     *
     * @throws PIIRedactorException Always throws an exception to block the prompt.
     */
    protected function block(): never
    {
        throw new PIIRedactorException;
    }

    /**
     * Log detected PII safely.
     *
     * @param AgentPrompt     $prompt The agent being prompted.
     * @param RedactionResult $result The result of the detection, including any found PII.
     */
    protected function log(AgentPrompt $prompt, RedactionResult $result): void
    {
        $context = [
            'agent'        => $prompt->agent::class,
            'provider'     => $prompt->provider()::class,
            'model'        => $prompt->model,
            'entities'     => $this->summariseEntities($result->detections),
            'value_hashes' => array_map(
                fn (Detection $detection): string => hash('sha256', $detection->value),
                $result->detections,
            ),
            'prompt_hash' => hash('sha256', $prompt->prompt),
            'timestamp'   => now()->toIso8601String(),
        ];

        if ($this->logPreview) {
            $context['prompt_preview'] = str($prompt->prompt)->limit(300)->toString();
        }

        Log::warning('PII detected in agent prompt.', $context);
    }

    /**
     * Determine whether a detection should be kept.
     *
     * @param Detection $detection The detection to evaluate.
     *
     * @return bool True if the detection should be kept; false otherwise.
     */
    protected function shouldKeepDetection(Detection $detection): bool
    {
        if ($detection->type === EntityTypes::CREDIT_CARD->value) {
            return $this->passesLuhn($detection->value);
        }

        if ($detection->type !== EntityTypes::EMAIL->value) {
            return true;
        }

        $email = strtolower($detection->value);

        if (in_array($email, $this->allowedEmails, true)) {
            return false;
        }

        $domain = substr(strrchr($email, '@') ?: '', 1);

        return ! in_array($domain, $this->allowedDomains, true);
    }

    /**
     * Determine whether the result contains a blocked entity.
     *
     * @param RedactionResult $result The result of the detection, including any found PII.
     *
     * @return bool True if a blocked entity is found; false otherwise.
     */
    protected function hasBlockedEntity(RedactionResult $result): bool
    {
        foreach ($result->detections as $detection) {
            if (in_array($detection->type, $this->blockEntities, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the default detectors.
     *
     * @return array<int, Detector>
     */
    protected function defaultDetectors(): array
    {
        return [
            new RegexDetector(
                EntityTypes::API_KEY->value,
                '/\b(?:sk-[A-Za-z0-9]{20,}|pk_[A-Za-z0-9]{20,}|ghp_[A-Za-z0-9]{20,}|xox[baprs]-[A-Za-z0-9-]{10,})\b/'
            ),
            new RegexDetector(
                EntityTypes::BEARER_TOKEN->value,
                '/\bBearer\s+[A-Za-z0-9._~+\/=-]{20,}\b/i'
            ),
            new RegexDetector(
                EntityTypes::CREDIT_CARD->value,
                '/\b(?:\d[ -]*?){13,19}\b/'
            ),
            new RegexDetector(
                EntityTypes::EMAIL->value,
                '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i'
            ),
            new RegexDetector(
                EntityTypes::IP_ADDRESS->value,
                '/\b(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)\b/'
            ),
            new RegexDetector(
                EntityTypes::PHONE->value,
                '/(?<!\d)(?:\+?\d{1,3}[\s.-]?)?(?:\(?\d{2,5}\)?[\s.-]?)?\d{3,4}[\s.-]?\d{3,4}(?!\d)/'
            ),
            new RegexDetector(
                EntityTypes::MAC_ADDRESS->value,
                '/\b(?:[0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}\b/'
            ),
            new RegexDetector(
                EntityTypes::URL->value,
                '/\b(?:https?:\/\/|www\.)[a-zA-Z0-9+&@#\/%?=~_|!:,.;]*[a-zA-Z0-9+&@#\/%=~_|]|\b(?<![a-zA-Z0-9@])(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(?:\/[a-zA-Z0-9+&@#\/%?=~_|!:,.;]*[a-zA-Z0-9+&@#\/%=~_|])?\b/'
            ),
        ];
    }

    /**
     * Remove overlapping detections.
     *
     * @param array<int, Detection> $detections Thre list of detections to process.
     *
     * @return array<int, Detection>
     */
    protected function removeOverlaps(array $detections): array
    {
        usort($detections, function (Detection $a, Detection $b): int {
            return $a->start <=> $b->start
                ?: $this->priority($b->type) <=> $this->priority($a->type)
                ?: $b->length <=> $a->length;
        });

        $selected = [];

        foreach ($detections as $detection) {
            foreach ($selected as $existing) {
                if ($this->overlaps($detection, $existing)) {
                    continue 2;
                }
            }

            $selected[] = $detection;
        }

        return $selected;
    }

    /**
     * Determine whether two detections overlap.
     *
     * @param Detection $a The first detection.
     * @param Detection $b The second detection.
     *
     * @return bool True if the detections overlap; false otherwise.
     */
    protected function overlaps(Detection $a, Detection $b): bool
    {
        return $a->start < $b->end() && $b->start < $a->end();
    }

    /**
     * Get entity priority for overlap resolution. The higher the number, the higher the priority.
     *
     * @param string $type The entity type.
     *
     * @return int The priority of the entity type.
     */
    protected function priority(string $type): int
    {
        return match ($type) {
            EntityTypes::API_KEY->value      => 60,
            EntityTypes::BEARER_TOKEN->value => 50,
            EntityTypes::CREDIT_CARD->value  => 40,
            EntityTypes::EMAIL->value        => 30,
            EntityTypes::IP_ADDRESS->value   => 20,
            EntityTypes::PHONE->value        => 10,
            EntityTypes::MAC_ADDRESS->value  => 5,
            default                          => 0,
        };
    }

    /**
     * Build replacements for detections.
     *
     * @param array<int, Detection> $detections The list of detections to process.
     * @param Closure               $resolver   The resolver function for creating replacements.
     *
     * @return array<int, array{start: int, length: int, replacement: string}>
     */
    protected function buildReplacements(array $detections, Closure $resolver): array
    {
        $counts       = [];
        $replacements = $detections;

        usort(
            $replacements,
            fn (Detection $a, Detection $b): int => $a->start <=> $b->start,
        );

        return array_map(function (Detection $detection) use (&$counts, $resolver): array {
            $counts[$detection->type] = ($counts[$detection->type] ?? 0) + 1;

            return [
                'start'       => $detection->start,
                'length'      => $detection->length,
                'replacement' => $resolver($detection, $counts[$detection->type]),
            ];
        }, $replacements);
    }

    /**
     * Apply replacements to text.
     *
     * @param string                                                          $text         The text to apply replacements to.
     * @param array<int, array{start: int, length: int, replacement: string}> $replacements The list of replacements to apply.
     *
     * @return string The text with replacements applied.
     */
    protected function applyReplacements(string $text, array $replacements): string
    {
        usort(
            $replacements,
            fn (array $a, array $b): int => $b['start'] <=> $a['start'],
        );

        foreach ($replacements as $replacement) {
            $text = substr_replace(
                $text,
                $replacement['replacement'],
                $replacement['start'],
                $replacement['length'],
            );
        }

        return $text;
    }

    /**
     * Format a redaction replacement.
     *
     * @param Detection $detection The detection to format.
     * @param int       $index     The index of the detection for this type.
     *
     * @return string The formatted replacement string.
     */
    protected function formatReplacement(Detection $detection, int $index): string
    {
        return str_replace(
            ['{{TYPE}}', '{{type}}', '{{INDEX}}', '{{index}}'],
            [strtoupper($detection->type), $detection->type, (string) $index, (string) $index],
            $this->replacementFormat,
        );
    }

    /**
     * Mask a detected value.
     *
     * @param Detection $detection The detection to mask.
     *
     * @return string The masked value.
     */
    protected function maskValue(Detection $detection): string
    {
        return match ($detection->type) {
            EntityTypes::EMAIL->value      => $this->maskEmail($detection->value),
            EntityTypes::IP_ADDRESS->value => preg_replace('/\.\d+$/', '.'.$this->maskCharacter, $detection->value) ?? $detection->value,
            default                        => $this->maskGeneric($detection->value),
        };
    }

    /**
     * Mask an email address.
     *
     * @param string $email The email address to mask.
     *
     * @return string The masked email address.
     */
    protected function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1)
            .str_repeat($this->maskCharacter, max(3, mb_strlen($local) - 1))
            .'@'
            .$domain;
    }

    /**
     * Mask a generic value.
     *
     * @param string $value The value to mask.
     *
     * @return string The masked value.
     */
    protected function maskGeneric(string $value): string
    {
        $visible = mb_substr($value, -4);
        $length  = max(6, mb_strlen($value) - mb_strlen($visible));

        return str_repeat($this->maskCharacter, $length).$visible;
    }

    /**
     * Summarise detected entities.
     *
     * @param array<int, Detection> $detections The list of detections to summarise.
     *
     * @return array<string, int>
     */
    protected function summariseEntities(array $detections): array
    {
        $summary = [];

        foreach ($detections as $detection) {
            $summary[$detection->type] = ($summary[$detection->type] ?? 0) + 1;
        }

        ksort($summary);

        return $summary;
    }

    /**
     * Validate the provided action.
     *
     * @param string $action The action to validate.
     *
     * @throws InvalidArgumentException If the action is not supported.
     */
    protected function validateAction(string $action): void
    {
        if (! in_array($action, array_column(ActionTypes::cases(), 'value'), true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unsupported PII redactor action: %s. Must be one of: %s.',
                    $action,
                    implode(', ', array_column(ActionTypes::cases(), 'value')),
                )
            );
        }
    }

    /**
     * Validate the provided entities.
     *
     * @param array<int, string> $entities The list of entities to validate.
     *
     * @throws InvalidArgumentException If any entity is not supported.
     */
    protected function validateEntities(array $entities): void
    {
        $supported = array_column(EntityTypes::cases(), 'value');

        foreach ($entities as $entity) {
            if (! in_array($entity, $supported, true)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Unsupported PII entity: %s. Must be one of: %s.',
                        $entity,
                        implode(', ', $supported),
                    )
                );
            }
        }
    }

    /**
     * Validate the provided detectors.
     *
     * @param array<int, mixed> $detectors The list of detectors to validate.
     *
     * @throws InvalidArgumentException If any detector does not implement the Detector contract.
     */
    protected function validateDetectors(array $detectors): void
    {
        foreach ($detectors as $detector) {
            if (! $detector instanceof Detector) {
                throw new InvalidArgumentException('Custom PII detectors must implement the Detector contract.');
            }
        }
    }

    /**
     * Determine whether a credit card-like value passes the Luhn check.
     *
     * @param string $value The value to check.
     *
     * @return bool True if the value passes the Luhn check; false otherwise.
     */
    protected function passesLuhn(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) < 13 || strlen($digits) > 19) {
            return false;
        }

        $sum       = 0;
        $alternate = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $number = (int) $digits[$i];

            if ($alternate) {
                $number *= 2;

                if ($number > 9) {
                    $number -= 9;
                }
            }

            $sum += $number;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }
}
