<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\InjectionGuard\Defaults;

final class InjectionGuardDefaults
{
    /**
     * Get the default Injection Guard config.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return [
            'action'             => 'block',
            'patterns'           => [],
            'merge_patterns'     => true,
            'normalise_prompt'   => true,
            'log_prompt_preview' => false,
        ];
    }
}
