<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support;

/**
 * Records the runs whose approval decisions were inspected before they were applied.
 *
 * The first step of a resumed run checks the ledger. When the listener already inspected
 * the decisions, the step does not scan the same content again.
 */
final class ApprovalDecisionLedger
{
    /**
     * The invocation IDs of the inspected runs.
     *
     * @var array<string, true>
     */
    private array $inspected = [];

    /**
     * Record that the decisions of a run were inspected.
     *
     * @param string|null $invocationId The invocation ID of the run.
     */
    public function markInspected(?string $invocationId): void
    {
        if ($invocationId !== null) {
            $this->inspected[$invocationId] = true;
        }
    }

    /**
     * Determine whether the decisions of a run were inspected.
     *
     * @param string|null $invocationId The invocation ID of the run.
     */
    public function wasInspected(?string $invocationId): bool
    {
        return $invocationId !== null && isset($this->inspected[$invocationId]);
    }
}
