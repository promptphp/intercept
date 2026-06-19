<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\ValueObjects;

final readonly class Detection
{
    /**
     * Create a new detection value object.
     * 
     * @param string $type       The type of the detected PII (e.g., "email").
     * @param string $value      The detected PII value.
     * @param int    $start      The start offset of the detected PII.
     * @param int    $length     The length of the detected PII.
     * @param float  $confidence The confidence level of the detection.
     */
    public function __construct(
        public string $type,
        public string $value,
        public int $start,
        public int $length,
        public float $confidence = 1.0,
    ) {
        //
    }

    /**
     * Get the detection end offset.
     * 
     * @return int
     */
    public function end(): int
    {
        return $this->start + $this->length;
    }
}