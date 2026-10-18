<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\BudgetGuard\ValueObjects;

use PromptPHP\Intercept\BudgetGuard\Enums\BudgetTypes;

final readonly class BudgetBreach
{
    /**
     * Create a new budget breach.
     *
     * @param BudgetTypes $type       The budget that would be exceeded.
     * @param float       $limit      The configured limit, in US dollars.
     * @param float       $projected  The projected spend against that limit, in US dollars.
     * @param float       $spent      The spend already recorded against that limit, in US dollars.
     * @param int|null    $retryAfter The seconds until the budget window resets, when it is windowed.
     */
    public function __construct(
        public BudgetTypes $type,
        public float $limit,
        public float $projected,
        public float $spent = 0.0,
        public ?int $retryAfter = null,
    ) {
        //
    }

    /**
     * Get the amount by which the limit would be exceeded, in US dollars.
     */
    public function overspend(): float
    {
        return $this->projected - $this->limit;
    }

    /**
     * Get a safe reference to the breach.
     *
     * Names the budget and the amounts, but never the key the spend was recorded against, so it
     * is safe to put in an exception message or a log line.
     */
    public function reference(): string
    {
        return sprintf('%s: $%s of $%s', $this->type->value, number_format($this->projected, 4), number_format($this->limit, 4));
    }

    /**
     * Describe the breach for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'budget'      => $this->type->value,
            'limit'       => round($this->limit, 6),
            'spent'       => round($this->spent, 6),
            'projected'   => round($this->projected, 6),
            'overspend'   => round($this->overspend(), 6),
            'retry_after' => $this->retryAfter,
        ];
    }
}
