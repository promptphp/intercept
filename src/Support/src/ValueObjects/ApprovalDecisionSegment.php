<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support\ValueObjects;

final readonly class ApprovalDecisionSegment
{
    /**
     * Create a new approval decision segment.
     *
     * @param string $toolCallId The ID of the tool call the decision resolves.
     * @param string $field      The dot path of the value within the decision.
     * @param string $text       The scannable text carried by the decision.
     */
    public function __construct(
        public string $toolCallId,
        public string $field,
        public string $text,
    ) {
        //
    }
}
