<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Intercept AI Agent Middleware Collection
    |--------------------------------------------------------------------------
    |
    | This file contains the global configuration for the Intercept AI Agent
    | middleware collection.
    |
    | Each middleware ships with internal defaults, so sections can be omitted
    | safely. Values defined here are used as global defaults and can still be
    | overridden per agent when creating the middleware instance.
    |
    */

    'middleware' => [
        /*
        |--------------------------------------------------------------------------
        | Injection Guard Middleware
        |--------------------------------------------------------------------------
        |
        | The Injection Guard middleware detects common prompt injection attempts
        | before a prompt is sent to the AI provider.
        |
        */
        'injection_guard' => [

            /*
             * The action to take when a possible prompt injection attempt is detected.
             * Supported values: 'block', 'log', 'warn', 'sanitize'.
             */
            'action' => 'block',

            /**
             * Additional regex patterns used to detect prompt injection attempts.
             * Set merge_patterns to false if you want to use only the patterns listed here.
             */
            'patterns' => [
                // Add your custom regex patterns here.
            ],

            /**
             * Determines whether custom patterns should be merged with the built-in
             * prompt injection patterns. If false, only the custom patterns above will be used.
             */
            'merge_patterns' => true,

            /**
             * Determines whether the prompt should be normalised before scanning.
             * Helps catch simple obfuscation.
             */
            'normalise_prompt' => true,

            /**
             * Determines whether a short preview of the prompt should be included
             * when logging injection detections. Prompts may contain sensitive user data.
             */
            'log_prompt_preview' => false,

            /**
             * Whether to scan the tool approval decisions carried by a resumed run.
             *
             * When a paused run is resumed, the prompt text is empty and the only new content
             * is what a human supplied while resolving the pending tool calls: edited tool
             * arguments and rejection results.
             *
             * Resumed prompts cannot be rewritten, so 'sanitize' and 'warn' degrade to logging
             * on this path. The 'block' action still stops the run.
             */
            'scan_approval_decisions' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | PII Redactor Middleware
        |--------------------------------------------------------------------------
        |
        | The PII Redactor middleware redacts sensitive information from prompts
        | before they are sent to the AI provider.
        |
        */
        'pii_redactor' => [

            /*
             * The action to take when a PII is detected.
             * Supported values: 'redact', 'log', 'mask', 'block'.
             */
            'action' => 'redact',

            /**
             * The entities that should be detected.
             */
            'entities' => [
                'email',
                'phone',
                'credit_card',
                'ip_address',
                'api_key',
                'bearer_token',
                'mac_address',
                'url',
            ],

            /**
             * The entities that should always block the prompt.
             */
            'block_entities' => [
                'credit_card',
                'api_key',
                'bearer_token',
            ],

            /**
             * The allowed email addresses.
             */
            'allowed_emails' => [],

            /**
             * The allowed email domains.
             */
            'allowed_domains' => [],

            /**
             * The replacement format for redacted values.
             */
            'replacement_format' => '[{{TYPE}}_{{INDEX}}]',

            /**
             * The character to use when masking values.
             */
            'mask_character' => '*',

            /**
             * Whether to log detections.
             */
            'log_detections' => true,

            /**
             * Whether to include a short prompt preview in logs.
             */
            'log_preview' => false,

            /**
             * Whether to scan the tool approval decisions carried by a resumed run.
             *
             * When a paused run is resumed, the prompt text is empty and the only new content
             * is what a human supplied while resolving the pending tool calls: edited tool
             * arguments and rejection results.
             *
             * Resumed prompts cannot be rewritten, so 'redact' and 'mask' degrade to logging
             * on this path. Blocked entities and the 'block' action still stop the run.
             */
            'scan_approval_decisions' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Tool Approval Guard Middleware
        |--------------------------------------------------------------------------
        |
        | The Tool Approval Guard middleware inspects the tool calls an agent proposes
        | while pausing for human approval, before they are surfaced for review.
        |
        | Unlike the other middleware, this one acts on the response, because the tool
        | calls it guards are proposed by the model rather than supplied by the user.
        |
        */
        'tool_approval_guard' => [

            /*
             * The action to take when a proposed tool call is flagged.
             * Supported values: 'block', 'log'.
             *
             * There is no mutating action. A proposed tool call is part of the paused turn
             * the provider recorded, so rewriting it would desynchronise the resumed run.
             *
             * 'block_entities' below stops the run whatever this is set to, so an observe-only
             * rollout needs 'log' here and an empty 'block_entities'.
             */
            'action' => 'block',

            /**
             * The tools that may be proposed. An empty list permits every tool.
             */
            'allowed_tools' => [],

            /**
             * The tools that may never be proposed.
             */
            'denied_tools' => [],

            /**
             * Whether to scan proposed arguments for secret-like data.
             * A key or a card number in an outbound argument is a strong exfiltration signal.
             */
            'scan_pii' => true,

            /**
             * Whether to scan proposed arguments for prompt injection patterns.
             *
             * Off by default: prose written for a human reader routinely matches the patterns.
             * Enable it when an argument feeds another model or agent rather than a person.
             */
            'scan_injection' => false,

            /**
             * The entities that should be detected in proposed arguments.
             *
             * Narrower than the PII Redactor list by design. In a proposed argument an email
             * address is usually the tool's own parameter, not an exfiltration signal. Adding
             * 'email', 'phone', 'url', 'ip_address' or 'mac_address' will flag legitimate calls.
             */
            'entities' => [
                'credit_card',
                'api_key',
                'bearer_token',
            ],

            /**
             * The entities that should always block, regardless of the action above.
             */
            'block_entities' => [
                'credit_card',
                'api_key',
                'bearer_token',
            ],

            /**
             * Whether to include a short argument preview in logs.
             * Matched values are always logged as hashes regardless of this setting.
             */
            'log_preview' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Budget Guard Middleware
        |--------------------------------------------------------------------------
        |
        | The Budget Guard middleware estimates the cost of a generation step before
        | the provider is called, and enforces per-request, per-session, and
        | per-user-per-day spending budgets.
        |
        */
        'budget_guard' => [

            /*
             * The action to take when a request would exceed its budget.
             * Supported values: 'block', 'log', 'truncate'.
             *
             * 'truncate' only applies to the per-request budget: shrinking the current prompt
             * cannot undo spend already recorded against a session or a user's day, so a
             * session or daily breach degrades to 'block'.
             */
            'action' => 'block',

            /**
             * The maximum cost of a single run, in US dollars, including the cost already
             * accrued by its earlier steps. Null disables this budget.
             */
            'max_cost_per_request' => null,

            /**
             * The maximum cost attributable to a session, in US dollars, within the session
             * window below. Null disables this budget.
             *
             * Sessions are only tracked when a key can be resolved for the step. By default
             * that is the agent's own conversation ID, for an agent that remembers its
             * conversations. Supply a custom `sessionKeyResolver` to the middleware for
             * anything else.
             */
            'max_cost_per_session' => null,

            /**
             * The maximum cost attributable to a user per calendar day, in US dollars.
             * Null disables this budget.
             *
             * By default the user is the agent's conversation participant, falling back to the
             * authenticated user. Supply a custom `userKeyResolver` to the middleware for
             * anything else.
             */
            'max_cost_per_user_per_day' => null,

            /**
             * How many hours a session's spend is tracked for, from the first request that
             * spends against it.
             */
            'session_window_hours' => 24,

            /**
             * The output tokens assumed for a step's response, since the real count is not
             * known until the provider replies. Used only to estimate cost before the call.
             */
            'assumed_output_tokens' => 1000,

            /**
             * Custom pricing, merged over the built-in pricing table and keyed the same way:
             * a model family name mapped to 'input', 'output', and optional 'cache_read' and
             * 'cache_write' rates, in US dollars per million tokens. Entries here override a
             * built-in rate for the same key.
             */
            'model_pricing' => [
                // 'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            ],

            /**
             * What to do when no pricing is known for the model a step runs on.
             * Supported values: 'allow', 'block'.
             *
             * Pricing changes faster than this package releases, so an unpriced model is
             * expected rather than exceptional. 'allow' keeps a model upgrade from taking an
             * application down; 'block' guarantees a budget is never bypassed by one.
             */
            'unknown_model_action' => 'allow',

            /**
             * The cache store session and daily spend is recorded in. Null uses the default
             * store configured for the application.
             */
            'cache_store' => null,

            /**
             * Whether to include a short prompt preview in logs.
             */
            'log_preview' => false,
        ],
    ],
];
