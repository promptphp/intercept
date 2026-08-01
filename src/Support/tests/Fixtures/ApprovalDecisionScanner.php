<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support\Tests\Fixtures;

use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
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

    /**
     * Extract the scannable text segments from a set of pending tool approvals.
     *
     * @param Collection<int, PendingApproval>|null $pendingApprovals The proposed tool calls.
     *
     * @return array<int, ApprovalDecisionSegment>
     */
    public function pendingSegments(?Collection $pendingApprovals): array
    {
        return $this->pendingApprovalSegments($pendingApprovals);
    }
}
