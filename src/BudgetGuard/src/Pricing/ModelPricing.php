<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\BudgetGuard\Pricing;

use PromptPHP\Intercept\BudgetGuard\ValueObjects\ModelRate;

final class ModelPricing
{
    /**
     * Get the built-in pricing table.
     *
     * Rates are in US dollars per million tokens, which is how providers publish them.
     *
     * Keys are matched against the model of the step, first exactly, then as the longest key
     * that the model starts with, then as the longest key the model contains. A dated or
     * versioned model id therefore resolves to its family: `claude-sonnet-4-5-20250929`
     * matches `claude-sonnet-4-5`, and `us.anthropic.claude-3-5-haiku-v1:0` matches
     * `claude-3-5-haiku`.
     *
     * Published pricing changes faster than this package releases, so treat these rates as a
     * starting point. Confirm them against your provider's pricing page and override the ones
     * that matter to you through `model_pricing`.
     *
     * @return array<string, array<string, float>>
     */
    public static function rates(): array
    {
        return [
            // Anthropic.
            'claude-opus-4'     => ['input' => 15.00, 'output' => 75.00, 'cache_read' => 1.50, 'cache_write' => 18.75],
            'claude-sonnet-4'   => ['input' => 3.00, 'output' => 15.00, 'cache_read' => 0.30, 'cache_write' => 3.75],
            'claude-haiku-4'    => ['input' => 1.00, 'output' => 5.00, 'cache_read' => 0.10, 'cache_write' => 1.25],
            'claude-3-opus'     => ['input' => 15.00, 'output' => 75.00, 'cache_read' => 1.50, 'cache_write' => 18.75],
            'claude-3-5-sonnet' => ['input' => 3.00, 'output' => 15.00, 'cache_read' => 0.30, 'cache_write' => 3.75],
            'claude-3-7-sonnet' => ['input' => 3.00, 'output' => 15.00, 'cache_read' => 0.30, 'cache_write' => 3.75],
            'claude-3-5-haiku'  => ['input' => 0.80, 'output' => 4.00, 'cache_read' => 0.08, 'cache_write' => 1.00],
            'claude-3-haiku'    => ['input' => 0.25, 'output' => 1.25, 'cache_read' => 0.03, 'cache_write' => 0.30],

            // OpenAI.
            'gpt-5'        => ['input' => 1.25, 'output' => 10.00, 'cache_read' => 0.125],
            'gpt-5-mini'   => ['input' => 0.25, 'output' => 2.00, 'cache_read' => 0.025],
            'gpt-5-nano'   => ['input' => 0.05, 'output' => 0.40, 'cache_read' => 0.005],
            'gpt-4.1'      => ['input' => 2.00, 'output' => 8.00, 'cache_read' => 0.50],
            'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60, 'cache_read' => 0.10],
            'gpt-4.1-nano' => ['input' => 0.10, 'output' => 0.40, 'cache_read' => 0.025],
            'gpt-4o'       => ['input' => 2.50, 'output' => 10.00, 'cache_read' => 1.25],
            'gpt-4o-mini'  => ['input' => 0.15, 'output' => 0.60, 'cache_read' => 0.075],
            'o3'           => ['input' => 2.00, 'output' => 8.00, 'cache_read' => 0.50],
            'o3-mini'      => ['input' => 1.10, 'output' => 4.40, 'cache_read' => 0.55],
            'o4-mini'      => ['input' => 1.10, 'output' => 4.40, 'cache_read' => 0.275],

            // Google.
            'gemini-2.5-pro'        => ['input' => 1.25, 'output' => 10.00, 'cache_read' => 0.31],
            'gemini-2.5-flash'      => ['input' => 0.30, 'output' => 2.50, 'cache_read' => 0.075],
            'gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40, 'cache_read' => 0.025],
            'gemini-2.0-flash'      => ['input' => 0.10, 'output' => 0.40, 'cache_read' => 0.025],

            // xAI.
            'grok-4'      => ['input' => 3.00, 'output' => 15.00, 'cache_read' => 0.75],
            'grok-3'      => ['input' => 3.00, 'output' => 15.00, 'cache_read' => 0.75],
            'grok-3-mini' => ['input' => 0.30, 'output' => 0.50, 'cache_read' => 0.075],

            // DeepSeek.
            'deepseek-chat'     => ['input' => 0.27, 'output' => 1.10, 'cache_read' => 0.07],
            'deepseek-reasoner' => ['input' => 0.55, 'output' => 2.19, 'cache_read' => 0.14],

            // Mistral.
            'mistral-large' => ['input' => 2.00, 'output' => 6.00],
            'mistral-small' => ['input' => 0.10, 'output' => 0.30],
        ];
    }

    /**
     * Resolve the rate for a model from a pricing table.
     *
     * @param string                                  $model The model of the step.
     * @param array<string, array<string, float|int>> $rates The pricing table to read.
     *
     * @return ModelRate|null The rate, or null when the table prices no matching model.
     */
    public static function resolve(string $model, array $rates): ?ModelRate
    {
        $model = self::normalise($model);

        if ($model === '') {
            return null;
        }

        $matched = null;
        $length  = 0;

        foreach ($rates as $key => $entry) {
            $candidate = self::normalise((string) $key);

            if ($candidate === '') {
                continue;
            }

            if ($candidate === $model) {
                return ModelRate::fromArray($entry);
            }

            if (mb_strlen($candidate) <= $length) {
                continue;
            }

            if (str_starts_with($model, $candidate) || str_contains($model, $candidate)) {
                $matched = $entry;
                $length  = mb_strlen($candidate);
            }
        }

        return $matched === null ? null : ModelRate::fromArray($matched);
    }

    /**
     * Normalise a model id or pricing key for matching.
     *
     * Deployment ids carry gateway prefixes and region prefixes that the published model name
     * does not, such as `bedrock/us.anthropic.claude-3-5-haiku-20241022-v1:0`. Dropping those
     * leaves the family name the pricing table is keyed by.
     *
     * @param string $model The model id to normalise.
     */
    private static function normalise(string $model): string
    {
        $model = mb_strtolower(trim($model));

        if (($slash = mb_strrpos($model, '/')) !== false) {
            $model = mb_substr($model, $slash + 1);
        }

        return str_replace('_', '-', $model);
    }
}
