<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\Detectors;

use PromptPHP\Intercept\PIIRedactor\Detectors\Contracts\Detector;
use PromptPHP\Intercept\PIIRedactor\Enums\EntityTypes;

/**
 * Class DefaultDetectors.
 *
 * The built-in PII detectors, shared by every Intercept middleware that needs to scan
 * text for structured sensitive values.
 */
final class DefaultDetectors
{
    /**
     * Get the default detectors.
     *
     * @return array<int, Detector>
     */
    public static function all(): array
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
                '/\b(?:\d[ -]*?){13,19}\b/',
                1.0,
                fn (string $value): bool => self::passesLuhn($value),
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
            // Pattern 1: URLs with scheme (http:// or https://).
            new RegexDetector(
                EntityTypes::URL->value,
                '~\bhttps?://[^\s<>"{}|\\^`\[\]]+(?<![.,;:!])~i',
                1.0,
                function (string $url): bool {
                    $url = rtrim($url, '.,;:!'); // trim trailing punctuation swallowed by regex.

                    $parsed = parse_url($url);

                    return $parsed !== false
                        && isset($parsed['scheme'], $parsed['host'])
                        && in_array($parsed['scheme'], ['http', 'https'], true);
                }
            ),
            // Pattern 2: URLs without scheme (www.example.com or example.com/path)
            // More conservative to avoid false positives.
            new RegexDetector(
                EntityTypes::URL->value,
                '~\b(?<![a-zA-Z0-9@])[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+(?:/[^\s<>"{}|\\^`\[\]]+)?(?<![.,;:!])~i',
                0.9,
                function (string $url): bool {
                    // Bare domains in prose are not URLs.
                    if (! str_contains($url, '/') && ! str_starts_with($url, 'www.')) {
                        return false;
                    }

                    $url = rtrim($url, '.,;:!');

                    // Prepend scheme so parse_url can validate the host.
                    $parsed = parse_url('http://'.$url);

                    return $parsed !== false
                        && isset($parsed['host'])
                        && str_contains($parsed['host'], '.');
                }
            ),
        ];
    }

    /**
     * Determine whether a credit card-like value passes the Luhn check.
     *
     * A run of digits only counts as a card number if it checksums, which keeps order
     * numbers, reference codes, and other long digit strings from being flagged.
     *
     * @param string $value The value to check.
     */
    private static function passesLuhn(string $value): bool
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
