<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\Detectors\Contracts;

use PromptPHP\Intercept\PIIRedactor\ValueObjects\Detection;

interface Detector
{
    /**
     * Get the detector entity type.
     */
    public function type(): string;

    /**
     * Detect sensitive values in the given text.
     *
     * @param string $text The text to analyze for sensitive values.
     *
     * @return array<int, Detection>
     */
    public function detect(string $text): array;
}
