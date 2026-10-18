<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\BudgetGuard\Defaults;

final class BudgetGuardDefaults
{
    /**
     * Get the default Budget Guard config.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return [
            'action'                    => 'block',
            'max_cost_per_request'      => null,
            'max_cost_per_session'      => null,
            'max_cost_per_user_per_day' => null,
            'session_window_hours'      => 24,
            'assumed_output_tokens'     => 1000,
            'model_pricing'             => [],
            'unknown_model_action'      => 'allow',
            'cache_store'               => null,
            'log_preview'               => false,
        ];
    }
}
