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
            |--------------------------------------------------------------------------
            | Action
            |--------------------------------------------------------------------------
            |
            | The action to take when a possible prompt injection attempt is detected.
            |
            | Supported values:
            | - block: stop the prompt and throw an exception.
            | - log: log the detection and continue.
            | - warn: prepend a security warning and continue.
            | - sanitize: remove the matched injection content and continue.
            |
            */

            'action' => 'block',

            /*
            |--------------------------------------------------------------------------
            | Custom Patterns
            |--------------------------------------------------------------------------
            |
            | Additional regex patterns used to detect prompt injection attempts.
            |
            | By default, these patterns are merged with the built-in detection
            | patterns. Set merge_patterns to false if you want to use only the
            | patterns listed here.
            |
            */

            'patterns' => [
                // Add your custom regex patterns here.
            ],

            /*
            |--------------------------------------------------------------------------
            | Merge Patterns
            |--------------------------------------------------------------------------
            |
            | Determines whether custom patterns should be merged with the built-in
            | prompt injection patterns.
            |
            | If false, only the custom patterns above will be used.
            |
            */

            'merge_patterns' => true,

            /*
            |--------------------------------------------------------------------------
            | Prompt Normalisation
            |--------------------------------------------------------------------------
            |
            | Determines whether the prompt should be normalised before scanning.
            |
            | Normalisation helps catch simple obfuscation by decoding URL-encoded
            | content, decoding HTML entities, removing zero-width characters, and
            | collapsing repeated whitespace.
            |
            */

            'normalise_prompt' => true,

            /*
            |--------------------------------------------------------------------------
            | Log Prompt Preview
            |--------------------------------------------------------------------------
            |
            | Determines whether a short preview of the prompt should be included
            | when logging injection detections.
            |
            | This is disabled by default because prompts may contain sensitive user
            | data. The middleware still logs a prompt hash even when previews are
            | disabled.
            |
            */
            
            'log_prompt_preview' => false,
        ],
    ],
];