<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\Detectors;

use Closure;
use InvalidArgumentException;
use PromptPHP\Intercept\PIIRedactor\Detectors\Contracts\Detector;
use PromptPHP\Intercept\PIIRedactor\ValueObjects\Detection;

final readonly class RegexDetector implements Detector
{
    /**
     * Create a new regex detector.
     *
     * @param string   $type       The detector entity type.
     * @param string   $pattern    The regex pattern to use for detection.
     * @param float    $confidence The confidence level of the detection (default: 1.0).
     * @param ?Closure $validator  A optional closure to validate detected values.
     */
    public function __construct(
        protected string $type,
        protected string $pattern,
        protected float $confidence = 1.0,
        protected ?Closure $validator = null,
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
     * @return array<int, Detection> An array of Detection objects representing the detected sensitive values.
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

        $detections = [];

        foreach ($matches[0] as $match) {
            $value = $match[0];

            if ($this->validator !== null && ! ($this->validator)($value)) {
                continue;
            }

            $detections[] = new Detection(
                type: $this->type,
                value: $value,
                start: $match[1],
                length: strlen($value),
                confidence: $this->confidence,
            );
        }

        return $detections;
    }
}
