<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support\Tests\Fixtures;

use Laravel\Ai\Approvals\Decisions;
use PromptPHP\Intercept\Support\Concerns\ScansApprovalDecisions;
use PromptPHP\Intercept\Support\ValueObjects\ApprovalDecisionSegment;

/**
 * Test host exposing the ScansApprovalDecisions trait's protected API.
 */
final class ApprovalDecisionScanner
{
    use ScansApprovalDecisions;

    /**
     * Extract the scannable text segments from a set of tool approval decisions.
     *
     * @param Decisions|null $decisions The decisions resolving a paused run.
     *
     * @return array<int, ApprovalDecisionSegment>
     */
    public function segments(?Decisions $decisions): array
    {
        return $this->approvalDecisionSegments($decisions);
    }
}
