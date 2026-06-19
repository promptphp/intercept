<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\ValueObjects;

final readonly class RedactionResult
{
    /**
     * Create a new redaction result.
     *
     * @param string                $text       The redacted text.
     * @param array<int, Detection> $detections The list of detections found in the original text.
     */
    public function __construct(
        public string $text,
        public array $detections,
    ) {
        //
    }

    /**
     * Determine whether the result contains detections.
     */
    public function hasDetections(): bool
    {
        return $this->detections !== [];
    }
}
