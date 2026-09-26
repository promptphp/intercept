<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support\Contracts;

use Laravel\Ai\Prompts\AgentPrompt;

/**
 * A middleware that inspects tool approval decisions before the SDK applies them.
 *
 * The SDK applies the decisions of a resumed run before the first step, so approved and
 * edited tool calls run before any agent middleware sees the step. Intercept calls this
 * method from a listener on the prompt event, which the SDK dispatches before it applies
 * the decisions.
 */
interface InspectsApprovalDecisions
{
    /**
     * Inspect the approval decisions carried by a resumed prompt.
     *
     * Throw an exception to stop the run before any decision is applied.
     *
     * @param AgentPrompt $prompt The prompt that resumes the paused run.
     */
    public function inspectApprovalDecisions(AgentPrompt $prompt): void;
}
