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
            'action'                  => 'block',
            'patterns'                => [],
            'merge_patterns'          => true,
            'normalise_prompt'        => true,
            'log_prompt_preview'      => false,
            'scan_approval_decisions' => true,
        ];
    }

    /**
     * Get the built-in prompt injection patterns.
     *
     * These strings are surfaced in log context and passed to custom callbacks as
     * `$detection['pattern']`, so they form part of the observable API.
     *
     * @return array<int, string>
     */
    public static function patterns(): array
    {
        return [
            '/ignore\s+(?:(?:all|the)\s+)?(?:(?:previous|prior|earlier)\s+)?(?:instructions|prompts|directives)/i',
            '/disregard\s+(?:(?:all|the)\s+)?(?:(?:previous|prior|earlier)\s+)?(?:instructions|prompts|directives)/i',
            '/forget\s+(?:(?:all|the)\s+)?(?:(?:previous|prior|earlier)\s+)?(?:instructions|prompts|directives)/i',
            '/(?:do\s+not|don\'t)\s+(?:follow|obey)\s+(?:(?:the|any)\s+)?(?:previous|prior|earlier|original)\s+(?:instructions|prompts|directives|rules)/i',
            '/system(?:\s+prompt)?\s*[:=]/i',
            '/new\s+(?:instructions|prompt|directive)\s*[:=]/i',
            '/you\s+(?:are|will)\s+now/i',
            '/pretend\s+(?:you\s+are|to\s+be)/i',
            '/act\s+(?:as|like)\s+(?:an?|the)/i',
            '/from\s+now\s+on/i',
            '/your\s+(?:new|current)\s+(?:role|task|purpose)/i',
            '/override\s+(?:the\s+)?system\s+prompt/i',
            '/(?:reveal|show|display|print|expose)\s+(?:your|the)\s+(?:hidden\s+)?(?:system\s+prompt|instructions|prompts|directives)/i',
            '/(?:repeat|recite|reproduce)\s+(?:(?:the\s+)?system\s+prompt|(?:the\s+)?(?:instructions|prompt|directives)\s+you\s+were\s+given)/i',
            '/(?:bypass|circumvent|disable|evade|remove)\s+(?:(?:all|any|the|your)\s+)?(?:(?:safety|security|content)\s+)?(?:guardrails|filters|policies|rules|restrictions|safeguards)/i',
            '/(?:enable|enter|activate|switch\s+to)\s+(?:jailbreak|developer|debug|unrestricted)\s+mode/i',
            '/follow\s+(?:my|these|the\s+following)\s+(?:instructions|prompt|directives)\s+instead/i',
            '/(?:\[\s*(?:system|developer)\s*\]|<\|(?:system|developer)\|>)/i',
        ];
    }
}
