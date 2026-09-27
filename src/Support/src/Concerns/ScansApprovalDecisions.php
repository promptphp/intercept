<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support\Concerns;

use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
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
 *
 * The SDK applies the decisions before the first step of the resumed run. The decisions
 * themselves are read from the resumed prompt, and the tool results they produced are read
 * from the first step, as a second check when the prompt was not inspected.
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
     * Extract the scannable text segments from the first step of a resumed run.
     *
     * The step ends with the tool results that the decisions produced. A denied result
     * carries the rejection text. An edited call carries arguments that differ from the
     * call the model proposed in the preceding assistant message.
     *
     * @param PendingStep $step The first step of the resumed run.
     *
     * @return array<int, ApprovalDecisionSegment>
     */
    protected function resumedApprovalSegments(PendingStep $step): array
    {
        $messages = array_values($step->messages);
        $last     = $messages[count($messages) - 1] ?? null;

        if (! $last instanceof ToolResultMessage) {
            return [];
        }

        $previous = $messages[count($messages) - 2] ?? null;

        $proposed = $previous instanceof AssistantMessage
            ? $previous->toolCalls->keyBy(fn (ToolCall $call): string => $call->id)
            : collect();

        $segments = [];

        foreach ($last->toolResults as $result) {
            /** @var ToolResult $result */
            $proposedCall = $proposed->get($result->id);

            if ($result->denied) {
                $text = $this->scannableValue($result->result);

                if ($text !== null) {
                    $segments[] = new ApprovalDecisionSegment(
                        toolCallId: $result->id,
                        field: 'result',
                        text: $text,
                    );
                }

                continue;
            }

            if ($proposedCall === null || $this->sortedArguments($result->arguments) !== $this->sortedArguments($proposedCall->arguments)) {
                $segments = [
                    ...$segments,
                    ...$this->segmentsForArguments($result->id, $result->arguments),
                ];
            }
        }

        return $segments;
    }

    /**
     * Build the log context that identifies a resumed prompt.
     *
     * The keys match the step log context, so both approval paths log the same shape.
     *
     * @param AgentPrompt $prompt The prompt that resumes the paused run.
     *
     * @return array{agent: class-string, provider: string, model: string, step: null, invocation_id: string|null}
     */
    protected function approvalPromptLogContext(AgentPrompt $prompt): array
    {
        return [
            'agent'         => $prompt->agent::class,
            'provider'      => $prompt->provider()->name(),
            'model'         => $prompt->model,
            'step'          => null,
            'invocation_id' => $prompt->invocationId,
        ];
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
     * Sort the keys of tool arguments at every depth.
     *
     * Two argument sets with the same values in a different key order then compare as equal,
     * so a provider that reorders keys does not mark an unedited call as edited.
     *
     * @param array<array-key, mixed> $arguments The arguments to sort.
     *
     * @return array<array-key, mixed>
     */
    protected function sortedArguments(array $arguments): array
    {
        ksort($arguments);

        return array_map(
            fn (mixed $value): mixed => is_array($value) ? $this->sortedArguments($value) : $value,
            $arguments,
        );
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
