<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\ToolApprovalGuard\ValueObjects;

use PromptPHP\Intercept\ToolApprovalGuard\Enums\FindingTypes;

final readonly class ApprovalFinding
{
    /**
     * Create a new approval finding.
     *
     * @param string       $toolCallId The ID of the pending approval the finding belongs to.
     * @param string       $tool       The name of the proposed tool.
     * @param FindingTypes $type       Why the proposed call was flagged.
     * @param string|null  $field      The dot path of the offending argument, if any.
     * @param string|null  $detail     The entity type or matched pattern, if any.
     * @param string|null  $value      The matched value. Never logged or surfaced in cleartext.
     */
    public function __construct(
        public string $toolCallId,
        public string $tool,
        public FindingTypes $type,
        public ?string $field = null,
        public ?string $detail = null,
        public ?string $value = null,
    ) {
        //
    }

    /**
     * Get a safe reference to where the finding was made.
     *
     * Names the tool call and field but never the matched value, so it is safe to put in an
     * exception message or a log line.
     */
    public function reference(): string
    {
        return $this->field === null
            ? sprintf('%s: %s', $this->toolCallId, $this->tool)
            : sprintf('%s: %s.%s', $this->toolCallId, $this->tool, $this->field);
    }
}
