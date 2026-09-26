<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support\Concerns;

use Closure;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;

/**
 * Trait InspectsPendingSteps.
 *
 * Reads the generation step the Laravel AI SDK hands to agent middleware.
 *
 * Agent middleware wraps each step of a run, not the prompt. The prompt is the newest user
 * message in the step history, and a rewrite of that history applies to one step only, so
 * a middleware that changes what the provider sees must apply the change on every step.
 */
trait InspectsPendingSteps
{
    /**
     * Get the agent that runs the step.
     *
     * @param PendingStep $step The step to read.
     */
    protected function agentFor(PendingStep $step): ?Agent
    {
        return $step->options?->agent;
    }

    /**
     * Determine whether the step sends a new prompt to the provider.
     *
     * Only the first step of a new turn ends with the user message. Later steps end with
     * the model's own turn or with tool results.
     *
     * @param PendingStep $step The step to read.
     */
    protected function startsNewTurn(PendingStep $step): bool
    {
        return $this->lastMessage($step) instanceof UserMessage;
    }

    /**
     * Determine whether the step resumes a run that paused for tool approval.
     *
     * The SDK applies the approval decisions before the first step, so that step ends with
     * the tool results the decisions produced.
     *
     * @param PendingStep $step The step to read.
     */
    protected function resumesFromApproval(PendingStep $step): bool
    {
        return $step->isFirstStep() && $this->lastMessage($step) instanceof ToolResultMessage;
    }

    /**
     * Get the newest user message in the step history.
     *
     * @param PendingStep $step The step to read.
     */
    protected function latestUserMessage(PendingStep $step): ?UserMessage
    {
        foreach (array_reverse($step->messages) as $message) {
            if ($message instanceof UserMessage) {
                return $message;
            }
        }

        return null;
    }

    /**
     * Rewrite user messages in the step history.
     *
     * The callback receives the content of each user message and returns the new content.
     * Attachments are kept. A step whose content does not change is returned unchanged.
     *
     * @param PendingStep                   $step       The step to rewrite.
     * @param Closure(string, bool): string $rewrite    The rewrite. The second argument is true for the newest user message.
     * @param bool                          $latestOnly Whether to rewrite only the newest user message.
     */
    protected function mapUserMessages(PendingStep $step, Closure $rewrite, bool $latestOnly = false): PendingStep
    {
        $latest  = $this->latestUserMessage($step);
        $changed = false;

        $messages = array_map(function (Message $message) use ($rewrite, $latest, $latestOnly, &$changed): Message {
            if (! $message instanceof UserMessage || ($latestOnly && $message !== $latest)) {
                return $message;
            }

            $content   = (string) $message->content;
            $rewritten = $rewrite($content, $message === $latest);

            if ($rewritten === $content) {
                return $message;
            }

            $changed = true;

            return new UserMessage($rewritten, $message->attachments);
        }, $step->messages);

        return $changed ? $step->withMessages($messages) : $step;
    }

    /**
     * Build the log context that identifies the step.
     *
     * @param PendingStep $step The step to describe.
     *
     * @return array{agent: class-string|null, provider: string, model: string, step: int, invocation_id: string|null}
     */
    protected function stepLogContext(PendingStep $step): array
    {
        $agent = $this->agentFor($step);

        return [
            'agent'         => $agent !== null ? $agent::class : null,
            'provider'      => $step->provider,
            'model'         => $step->model,
            'step'          => $step->number,
            'invocation_id' => $step->invocationId,
        ];
    }

    /**
     * Get the last message in the step history.
     *
     * @param PendingStep $step The step to read.
     */
    private function lastMessage(PendingStep $step): ?Message
    {
        return $step->messages === [] ? null : $step->messages[array_key_last($step->messages)];
    }
}
