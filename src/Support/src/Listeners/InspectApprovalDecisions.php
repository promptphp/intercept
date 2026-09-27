<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support\Listeners;

use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Events\PromptingAgent;
use PromptPHP\Intercept\Support\ApprovalDecisionLedger;
use PromptPHP\Intercept\Support\Contracts\InspectsApprovalDecisions;

/**
 * Runs the agent's Intercept middleware on approval decisions before the SDK applies them.
 *
 * The SDK dispatches the prompting and streaming events before it resumes a paused run, and
 * an exception thrown here stops the run before any approved or edited tool call executes.
 */
final class InspectApprovalDecisions
{
    /**
     * Create a new listener instance.
     *
     * @param ApprovalDecisionLedger $ledger The record of inspected runs.
     */
    public function __construct(private readonly ApprovalDecisionLedger $ledger)
    {
        //
    }

    /**
     * Handle the prompting or streaming event.
     *
     * @param PromptingAgent $event The event dispatched before the run starts.
     */
    public function handle(PromptingAgent $event): void
    {
        $prompt = $event->prompt;

        if (! $prompt->hasApprovalDecisions() || ! $prompt->agent instanceof HasMiddleware) {
            return;
        }

        foreach ($prompt->agent->middleware() as $middleware) {
            if (is_string($middleware)) {
                $middleware = resolve($middleware);
            }

            if ($middleware instanceof InspectsApprovalDecisions) {
                $middleware->inspectApprovalDecisions($prompt);
            }
        }

        $this->ledger->markInspected($prompt->invocationId ?? $event->invocationId);
    }
}
