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
        ],
    ],
];
