<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\BudgetGuard\ValueObjects;

use InvalidArgumentException;
use Laravel\Ai\Responses\Data\TextUsage;

final readonly class ModelRate
{
    /**
     * The number of tokens the rates are quoted per.
     */
    public const PER_TOKENS = 1_000_000;

    /**
     * Create a new model rate.
     *
     * All rates are in US dollars per million tokens, matching how providers publish them.
     *
     * @param float      $input      The rate for input tokens.
     * @param float      $output     The rate for output tokens.
     * @param float|null $cacheRead  The rate for input tokens read from a prompt cache. Falls back to the input rate.
     * @param float|null $cacheWrite The rate for input tokens written to a prompt cache. Falls back to the input rate.
     */
    public function __construct(
        public float $input,
        public float $output,
        public ?float $cacheRead = null,
        public ?float $cacheWrite = null,
    ) {
        //
    }

    /**
     * Create a rate from a pricing table entry.
     *
     * @param array<string, mixed> $entry The entry to read.
     */
    public static function fromArray(array $entry): self
    {
        foreach (['input', 'output'] as $key) {
            if (! isset($entry[$key]) || ! is_numeric($entry[$key])) {
                throw new InvalidArgumentException("Model pricing entries require a numeric [{$key}] rate.");
            }
        }

        foreach (['cache_read', 'cache_write'] as $key) {
            if (isset($entry[$key]) && ! is_numeric($entry[$key])) {
                throw new InvalidArgumentException("The model pricing [{$key}] rate must be numeric.");
            }
        }

        return new self(
            input: (float) $entry['input'],
            output: (float) $entry['output'],
            cacheRead: isset($entry['cache_read']) ? (float) $entry['cache_read'] : null,
            cacheWrite: isset($entry['cache_write']) ? (float) $entry['cache_write'] : null,
        );
    }

    /**
     * Get the cost in US dollars of the given token counts.
     *
     * @param int $inputTokens  The number of input tokens.
     * @param int $outputTokens The number of output tokens.
     */
    public function cost(int $inputTokens, int $outputTokens): float
    {
        return ($inputTokens * $this->input + $outputTokens * $this->output) / self::PER_TOKENS;
    }

    /**
     * Get the cost in US dollars of the usage a provider reported.
     *
     * Cached input tokens are priced at their own rates when the provider reports them and the
     * pricing table carries them, because a cache read is typically a fraction of the input rate
     * and a cache write a premium over it.
     *
     * @param TextUsage $usage The usage to price.
     */
    public function costOfUsage(TextUsage $usage): float
    {
        $cacheRead  = $usage->cacheReadInputTokens ?? 0;
        $cacheWrite = $usage->cacheWriteInputTokens ?? 0;

        $cost = $usage->uncachedInputTokens() * $this->input
            + $cacheRead * ($this->cacheRead ?? $this->input)
            + $cacheWrite * ($this->cacheWrite ?? $this->input)
            + $usage->outputTokens * $this->output;

        return $cost / self::PER_TOKENS;
    }
}
