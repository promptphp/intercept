<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support\Concerns;

use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use PromptPHP\Intercept\Support\ValueObjects\ApprovalDecisionSegment;

/**
 * Trait ScansApprovalDecisions.
 *
 * Extracts the scannable text carried by both ends of a tool approval cycle.
 *
 * When an agent pauses for approval, the model proposes tool calls whose arguments may have
 * been shaped by content Intercept never sees, such as tool results or retrieved documents.
 * When the run is resumed, the prompt text is empty and the only new content is whatever a
 * human supplied while resolving those calls: edited tool arguments and rejection results.
 *
 * Both reach the AI provider unscanned unless a middleware inspects them here.
 */
trait ScansApprovalDecisions
{
    /**
     * Extract the scannable text segments from a set of pending tool approvals.
     *
     * These are the tool calls the model proposed, before any human has resolved them.
     * The segment's tool call ID is the pending approval ID, so a caller can map a finding
     * back to the approval and its tool name.
     *
     * @param Collection<int, PendingApproval>|null $pendingApprovals The proposed tool calls.
     *
     * @return array<int, ApprovalDecisionSegment>
     */
    protected function pendingApprovalSegments(?Collection $pendingApprovals): array
    {
        if ($pendingApprovals === null) {
            return [];
        }

        $segments = [];

        foreach ($pendingApprovals as $approval) {
            $segments = [
                ...$segments,
                ...$this->segmentsForArguments($approval->id, $approval->arguments),
            ];
        }

        return $segments;
    }

    /**
     * Extract the scannable text segments from a set of tool approval decisions.
     *
     * @param Decisions|null $decisions The decisions resolving a paused run.
     *
     * @return array<int, ApprovalDecisionSegment>
     */
    protected function approvalDecisionSegments(?Decisions $decisions): array
    {
        if ($decisions === null) {
            return [];
        }

        $segments = [];

        foreach ($decisions->all() as $toolCallId => $decision) {
            $segments = [
                ...$segments,
                ...$this->segmentsForDecision((string) $toolCallId, $decision),
            ];
        }

        return $segments;
    }

    /**
     * Extract the scannable text segments from a single approval decision.
     *
     * Approved decisions carry no operator input, so they contribute nothing to scan.
     *
     * @param string   $toolCallId The ID of the tool call the decision resolves.
     * @param Decision $decision   The decision to extract text from.
     *
     * @return array<int, ApprovalDecisionSegment>
     */
    protected function segmentsForDecision(string $toolCallId, Decision $decision): array
    {
        if ($decision->isEdited()) {
            return $this->segmentsForArguments($toolCallId, $decision->arguments ?? []);
        }

        if ($decision->isRejected() && $this->scannableValue($decision->result) !== null) {
            return [
                new ApprovalDecisionSegment(
                    toolCallId: $toolCallId,
                    field: 'result',
                    text: (string) $decision->result,
                ),
            ];
        }

        return [];
    }

    /**
     * Flatten edited tool arguments into dot-pathed segments.
     *
     * @param string                  $toolCallId The ID of the tool call the decision resolves.
     * @param array<array-key, mixed> $arguments  The edited tool call arguments.
     * @param string                  $path       The dot path accumulated so far.
     *
     * @return array<int, ApprovalDecisionSegment>
     */
    protected function segmentsForArguments(string $toolCallId, array $arguments, string $path = 'arguments'): array
    {
        $segments = [];

        foreach ($arguments as $key => $value) {
            $field = $path.'.'.$key;

            if (is_array($value)) {
                $segments = [
                    ...$segments,
                    ...$this->segmentsForArguments($toolCallId, $value, $field),
                ];

                continue;
            }

            $text = $this->scannableValue($value);

            if ($text === null) {
                continue;
            }

            $segments[] = new ApprovalDecisionSegment(
                toolCallId: $toolCallId,
                field: $field,
                text: $text,
            );
        }

        return $segments;
    }

    /**
     * Resolve a decision value into scannable text.
     *
     * Integers are scanned because a hand-edited argument can carry an unquoted card or
     * account number. Booleans, floats, and null cannot meaningfully carry a detectable
     * value, and blank strings have nothing to detect.
     *
     * @param mixed $value The value to resolve.
     *
     * @return string|null The scannable text, or null when there is nothing to scan.
     */
    protected function scannableValue(mixed $value): ?string
    {
        if (is_string($value)) {
            return trim($value) === '' ? null : $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return null;
    }
}
