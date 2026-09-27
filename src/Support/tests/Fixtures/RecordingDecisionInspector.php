<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support\Tests\Fixtures;

use Closure;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Prompts\AgentPrompt;
use PromptPHP\Intercept\Support\Contracts\InspectsApprovalDecisions;
use RuntimeException;

/**
 * A middleware that records the resumed prompts it inspects and can stop the run.
 */
final class RecordingDecisionInspector implements InspectsApprovalDecisions
{
    /**
     * The resumed prompts inspected by any instance.
     *
     * @var array<int, AgentPrompt>
     */
    public static array $inspected = [];

    /**
     * Create a new inspector.
     *
     * @param bool $blocks Whether the inspector stops the run.
     */
    public function __construct(private readonly bool $blocks = false)
    {
        //
    }

    /**
     * Pass the step through unchanged.
     *
     * @param PendingStep $step The generation step.
     * @param Closure     $next The next middleware in the pipeline.
     */
    public function handle(PendingStep $step, Closure $next): mixed
    {
        return $next($step);
    }

    /**
     * {@inheritDoc}
     */
    public function inspectApprovalDecisions(AgentPrompt $prompt): void
    {
        self::$inspected[] = $prompt;

        if ($this->blocks) {
            throw new RuntimeException('Blocked by the decision inspector.');
        }
    }
}
