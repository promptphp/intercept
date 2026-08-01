<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\ToolApprovalGuard;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\TextResponse;
use PromptPHP\Intercept\InjectionGuard\Defaults\InjectionGuardDefaults;
use PromptPHP\Intercept\PIIRedactor\Detectors\Contracts\Detector;
use PromptPHP\Intercept\PIIRedactor\Detectors\DefaultDetectors;
use PromptPHP\Intercept\PIIRedactor\Enums\EntityTypes;
use PromptPHP\Intercept\Support\Concerns\ScansApprovalDecisions;
use PromptPHP\Intercept\Support\InterceptConfig;
use PromptPHP\Intercept\Support\ValueObjects\ApprovalDecisionSegment;
use PromptPHP\Intercept\ToolApprovalGuard\Defaults\ToolApprovalGuardDefaults;
use PromptPHP\Intercept\ToolApprovalGuard\Enums\ActionTypes;
use PromptPHP\Intercept\ToolApprovalGuard\Enums\FindingTypes;
use PromptPHP\Intercept\ToolApprovalGuard\Exceptions\ToolApprovalGuardException;
use PromptPHP\Intercept\ToolApprovalGuard\ValueObjects\ApprovalFinding;

class ToolApprovalGuard
{
    use ScansApprovalDecisions;

    /**
     * The tools that may be proposed. An empty list permits every tool.
     *
     * @var array<int, string>
     */
    protected array $allowedTools;

    /**
     * The tools that may never be proposed.
     *
     * @var array<int, string>
     */
    protected array $deniedTools;

    /**
     * The PII entities to detect in proposed tool arguments.
     *
     * @var array<int, string>
     */
    protected array $entities;

    /**
     * The entities that always block, regardless of the configured action.
     *
     * @var array<int, string>
     */
    protected array $blockEntities;

    /**
     * The action to take when a proposed tool call is flagged.
     */
    protected ActionTypes $action = ActionTypes::BLOCK;

    /**
     * Whether to scan proposed arguments for personal and secret-like data.
     */
    protected bool $scanPii = true;

    /**
     * Whether to scan proposed arguments for prompt injection patterns.
     */
    protected bool $scanInjection = true;

    /**
     * Whether to include a short argument preview in logs.
     */
    protected bool $logPreview = false;

    /**
     * Custom callback for handling findings.
     */
    protected ?Closure $callback;

    /**
     * The configured detectors.
     *
     * @var array<int, Detector>
     */
    protected array $detectors;

    /**
     * The injection patterns used to scan proposed arguments.
     *
     * @var array<int, string>
     */
    protected array $patterns;

    /**
     * Create a new Tool Approval Guard instance.
     *
     * @param string|null               $action        What to do: 'block' or 'log'.
     * @param array<int, string>|null   $allowedTools  Tools that may be proposed. Empty permits all.
     * @param array<int, string>|null   $deniedTools   Tools that may never be proposed.
     * @param Closure|null              $callback      Custom handler for findings.
     * @param bool|null                 $scanPii       Whether to scan arguments for PII and secrets.
     * @param bool|null                 $scanInjection Whether to scan arguments for injection patterns.
     * @param array<int, string>|null   $entities      PII entities to detect.
     * @param array<int, string>|null   $blockEntities Entities that always block.
     * @param bool|null                 $logPreview    Whether to log a short argument preview.
     * @param array<int, Detector>|null $detectors     Additional custom detectors.
     * @param array<int, string>|null   $patterns      Additional custom injection patterns.
     */
    public function __construct(
        ?string $action = null,
        ?array $allowedTools = null,
        ?array $deniedTools = null,
        ?Closure $callback = null,
        ?bool $scanPii = null,
        ?bool $scanInjection = null,
        ?array $entities = null,
        ?array $blockEntities = null,
        ?bool $logPreview = null,
        ?array $detectors = null,
        ?array $patterns = null,
    ) {
        $config = InterceptConfig::middleware('tool_approval_guard', ToolApprovalGuardDefaults::values());

        $action ??= $config['action'];
        $allowedTools ??= $config['allowed_tools'];
        $deniedTools ??= $config['denied_tools'];
        $scanPii ??= $config['scan_pii'];
        $scanInjection ??= $config['scan_injection'];
        $entities ??= $config['entities'];
        $blockEntities ??= $config['block_entities'];
        $logPreview ??= $config['log_preview'];

        $this->validateAction($action);
        $this->validateEntities($entities);
        $this->validateEntities($blockEntities);
        $this->validateDetectors($detectors ?? []);

        $this->action        = ActionTypes::from($action);
        $this->allowedTools  = $allowedTools;
        $this->deniedTools   = $deniedTools;
        $this->callback      = $callback;
        $this->scanPii       = $scanPii;
        $this->scanInjection = $scanInjection;
        $this->entities      = $entities;
        $this->blockEntities = $blockEntities;
        $this->logPreview    = $logPreview;

        $this->detectors = [
            ...DefaultDetectors::all(),
            ...($detectors ?? []),
        ];

        $this->patterns = [
            ...InjectionGuardDefaults::patterns(),
            ...($patterns ?? []),
        ];
    }

    /**
     * Handle the outgoing prompt and inspect any tool calls proposed for approval.
     *
     * This middleware acts on the response rather than the prompt, because the tool calls it
     * guards are proposed by the model. Runs that do not pause for approval are untouched.
     *
     * @param AgentPrompt $prompt The agent being prompted.
     * @param Closure     $next   The next middleware in the pipeline.
     */
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $response = $next($prompt);

        if ($response instanceof StreamableAgentResponse) {
            // A streamed response has not produced its approvals yet. The completion hook fires
            // once they are known, but the caller has already received the streamed text by
            // then, so blocking is no longer possible and the action degrades to logging.
            return $response->then(
                fn (TextResponse $streamed): mixed => $this->inspect($prompt, $streamed, blocking: false),
            );
        }

        if ($response instanceof TextResponse) {
            return $this->inspect($prompt, $response, blocking: true);
        }

        return $response;
    }

    /**
     * Inspect the tool calls a response has proposed for approval.
     *
     * @param AgentPrompt  $prompt   The agent being prompted.
     * @param TextResponse $response The response carrying the pending approvals.
     * @param bool         $blocking Whether the run can still be stopped.
     */
    protected function inspect(AgentPrompt $prompt, TextResponse $response, bool $blocking): mixed
    {
        if (! $response->hasPendingApprovals()) {
            return $response;
        }

        $findings = $this->findingsFor($response->pendingApprovals);

        if ($findings === []) {
            return $response;
        }

        $shouldBlock = $blocking && (
            $this->action === ActionTypes::BLOCK || $this->hasBlockedEntity($findings)
        );

        $this->log($prompt, $findings, $shouldBlock, $blocking);

        if ($this->callback !== null) {
            return ($this->callback)($prompt, $response, $findings);
        }

        if ($shouldBlock) {
            $this->block($findings);
        }

        return $response;
    }

    /**
     * Collect the findings for a set of pending approvals.
     *
     * @param Collection<int, PendingApproval> $pendingApprovals
     *
     * @return array<int, ApprovalFinding>
     */
    protected function findingsFor($pendingApprovals): array
    {
        $tools = [];

        foreach ($pendingApprovals as $approval) {
            $tools[$approval->id] = $approval->tool;
        }

        $findings = [];

        foreach ($pendingApprovals as $approval) {
            if (! $this->toolIsPermitted($approval->tool)) {
                $findings[] = new ApprovalFinding(
                    toolCallId: $approval->id,
                    tool: $approval->tool,
                    type: FindingTypes::DENIED_TOOL,
                );
            }
        }

        foreach ($this->pendingApprovalSegments($pendingApprovals) as $segment) {
            $tool = $tools[$segment->toolCallId] ?? '';

            $findings = [
                ...$findings,
                ...$this->findingsForSegment($segment, $tool),
            ];
        }

        return $findings;
    }

    /**
     * Collect the findings for a single proposed argument.
     *
     * @param ApprovalDecisionSegment $segment The proposed argument to scan.
     * @param string                  $tool    The name of the proposed tool.
     *
     * @return array<int, ApprovalFinding>
     */
    protected function findingsForSegment(ApprovalDecisionSegment $segment, string $tool): array
    {
        $findings = [];

        if ($this->scanPii) {
            foreach ($this->detectors as $detector) {
                if (! in_array($detector->type(), $this->entities, true)) {
                    continue;
                }

                foreach ($detector->detect($segment->text) as $detection) {
                    $findings[] = new ApprovalFinding(
                        toolCallId: $segment->toolCallId,
                        tool: $tool,
                        type: FindingTypes::PII,
                        field: $segment->field,
                        detail: $detection->type,
                        value: $detection->value,
                    );
                }
            }
        }

        if ($this->scanInjection && ($pattern = $this->matchingPattern($segment->text)) !== null) {
            $findings[] = new ApprovalFinding(
                toolCallId: $segment->toolCallId,
                tool: $tool,
                type: FindingTypes::INJECTION,
                field: $segment->field,
                detail: $pattern,
                value: $segment->text,
            );
        }

        return $findings;
    }

    /**
     * Determine whether a tool may be proposed.
     *
     * @param string $tool The name of the proposed tool.
     */
    protected function toolIsPermitted(string $tool): bool
    {
        if (in_array($tool, $this->deniedTools, true)) {
            return false;
        }

        return $this->allowedTools === [] || in_array($tool, $this->allowedTools, true);
    }

    /**
     * Get the first injection pattern the given text matches.
     *
     * @param string $text The text to scan.
     *
     * @return string|null The matching pattern, or null when the text is clean.
     */
    protected function matchingPattern(string $text): ?string
    {
        foreach ($this->patterns as $pattern) {
            $result = preg_match($pattern, $text);

            if ($result === false) {
                throw new InvalidArgumentException("Invalid prompt injection regex pattern [{$pattern}].");
            }

            if ($result === 1) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * Determine whether the findings include a high-risk entity.
     *
     * @param array<int, ApprovalFinding> $findings The findings to evaluate.
     */
    protected function hasBlockedEntity(array $findings): bool
    {
        foreach ($findings as $finding) {
            if ($finding->type === FindingTypes::PII && in_array($finding->detail, $this->blockEntities, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Block the run before the approval is surfaced.
     *
     * The message names the offending tool calls and fields, but never the matched values,
     * so it stays safe to log and to surface.
     *
     * @param array<int, ApprovalFinding> $findings The findings that caused the block.
     *
     * @throws ToolApprovalGuardException
     */
    protected function block(array $findings): never
    {
        throw new ToolApprovalGuardException(
            sprintf(
                'Unsafe tool call proposed for approval [%s].',
                implode(', ', array_unique(array_map(
                    fn (ApprovalFinding $finding): string => $finding->reference(),
                    $findings,
                ))),
            )
        );
    }

    /**
     * Log the findings safely.
     *
     * @param AgentPrompt                 $prompt      The agent being prompted.
     * @param array<int, ApprovalFinding> $findings    The findings to log.
     * @param bool                        $shouldBlock Whether the run is being stopped.
     * @param bool                        $blocking    Whether the run could have been stopped.
     */
    protected function log(AgentPrompt $prompt, array $findings, bool $shouldBlock, bool $blocking): void
    {
        $context = [
            'agent'    => $prompt->agent::class,
            'provider' => $prompt->provider()::class,
            'model'    => $prompt->model,
            'source'   => 'pending_approvals',
            'findings' => array_map(
                fn (ApprovalFinding $finding): array => $this->describe($finding),
                $findings,
            ),
            'timestamp' => now()->toIso8601String(),
        ];

        if (! $blocking && $this->action === ActionTypes::BLOCK) {
            $context['degraded_from'] = ActionTypes::BLOCK->value;
        }

        Log::warning(
            $shouldBlock
                ? 'Unsafe tool call proposed for approval.'
                : 'Suspicious tool call proposed for approval.',
            $context,
        );
    }

    /**
     * Describe a finding for logging.
     *
     * The matched value is only ever recorded as a hash, and the argument preview is gated
     * behind the log preview option.
     *
     * @param ApprovalFinding $finding The finding to describe.
     *
     * @return array<string, mixed>
     */
    protected function describe(ApprovalFinding $finding): array
    {
        $described = [
            'tool_call_id' => $finding->toolCallId,
            'tool'         => $finding->tool,
            'type'         => $finding->type->value,
            'field'        => $finding->field,
            'detail'       => $finding->detail,
        ];

        if ($finding->value !== null) {
            $described['value_hash'] = hash('sha256', $finding->value);

            if ($this->logPreview) {
                $described['preview'] = str($finding->value)->limit(300)->toString();
            }
        }

        return $described;
    }

    /**
     * Validate the provided action.
     *
     * @param string $action The action to validate.
     */
    protected function validateAction(string $action): void
    {
        if (! in_array($action, array_column(ActionTypes::cases(), 'value'), true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unsupported tool approval guard action: %s. Must be one of: %s.',
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
     */
    protected function validateDetectors(array $detectors): void
    {
        foreach ($detectors as $detector) {
            if (! $detector instanceof Detector) {
                throw new InvalidArgumentException('Custom PII detectors must implement the Detector contract.');
            }
        }
    }
}
