<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\Detectors;

use InvalidArgumentException;
use PromptPHP\Intercept\PIIRedactor\Detectors\Contracts\Detector;
use PromptPHP\Intercept\PIIRedactor\ValueObjects\Detection;

final readonly class RegexDetector implements Detector
{
    /**
     * Create a new regex detector.
     *
     * @param string $type       The detector entity type.
     * @param string $pattern    The regex pattern to use for detection.
     * @param float  $confidence The confidence level of the detection (default: 1.0).
     */
    public function __construct(
        protected string $type,
        protected string $pattern,
        protected float $confidence = 1.0,
    ) {
        //
    }

    /**
     * Get the detector entity type.
     *
     * @return string The detector entity type.
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Detect sensitive values in the given text.
     *
     * @param string $text The text to analyze for sensitive values.
     *
     * @return array<int, Detection>
     */
    public function detect(string $text): array
    {
        $result = preg_match_all(
            $this->pattern,
            $text,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        if ($result === false) {
            throw new InvalidArgumentException("Invalid PII detector regex pattern [{$this->pattern}].");
        }

        if ($result === 0) {
            return [];
        }

        return array_map(
            fn (array $match): Detection => new Detection(
                type: $this->type,
                value: $match[0],
                start: $match[1],
                length: strlen($match[0]),
                confidence: $this->confidence,
            ),
            $matches[0],
        );
    }
}
