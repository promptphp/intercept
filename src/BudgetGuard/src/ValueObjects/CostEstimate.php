<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\BudgetGuard\ValueObjects;

final readonly class CostEstimate
{
    /**
     * Create a new cost estimate for a single generation step.
     *
     * @param int   $inputTokens  The estimated input tokens of the step.
     * @param int   $outputTokens The assumed output tokens of the step.
     * @param float $cost         The estimated cost of the step, in US dollars.
     * @param float $accrued      The cost the completed steps of the run already spent, in US dollars.
     */
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
        public float $cost,
        public float $accrued = 0.0,
    ) {
        //
    }

    /**
     * Get the projected cost of the whole run, in US dollars.
     */
    public function projected(): float
    {
        return $this->accrued + $this->cost;
    }

    /**
     * Describe the estimate for logging.
     *
     * @return array<string, float|int>
     */
    public function toArray(): array
    {
        return [
            'input_tokens'  => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'step_cost'     => round($this->cost, 6),
            'accrued_cost'  => round($this->accrued, 6),
            'run_cost'      => round($this->projected(), 6),
        ];
    }
}
