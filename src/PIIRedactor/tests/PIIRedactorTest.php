<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Prompts\AgentPrompt;
use PromptPHP\Intercept\PIIRedactor\Exceptions\PIIRedactorException;
use PromptPHP\Intercept\PIIRedactor\PIIRedactor;
use PromptPHP\Intercept\PIIRedactor\Tests\Fixtures\PIIRedactorTestAgent;
use PromptPHP\Intercept\PIIRedactor\Tests\Fixtures\PIIRedactorTestProvider;
use PromptPHP\Intercept\PIIRedactor\ValueObjects\RedactionResult;

afterEach(function (): void {
    Mockery::close();
});

function makePIIRedactorAgentPrompt(string $prompt): AgentPrompt
{
    return new AgentPrompt(
        agent: new PIIRedactorTestAgent,
        prompt: $prompt,
        attachments: [],
        provider: new PIIRedactorTestProvider,
        model: 'test-model',
    );
}

it('allows safe prompts to continue through the pipeline', function (): void {
    $redactor = new PIIRedactor;

    $prompt = makePIIRedactorAgentPrompt('Summarise this support ticket.');

    $receivedPrompt = null;

    $result = $redactor->handle($prompt, function (AgentPrompt $prompt) use (&$receivedPrompt): string {
        $receivedPrompt = $prompt;

        return 'next-called';
    });

    expect($result)->toBe('next-called');
    expect($receivedPrompt)->toBe($prompt);
});

it('redacts email addresses by default', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com about this.'),
        function (AgentPrompt $prompt) use (&$forwardedPrompt): string {
            $forwardedPrompt = $prompt;

            return 'continued';
        },
    );

    expect($forwardedPrompt->prompt)->toBe('Email [EMAIL_1] about this.');
});

it('redacts phone numbers by default', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Call me on 07123456789.'),
        function (AgentPrompt $prompt) use (&$forwardedPrompt): string {
            $forwardedPrompt = $prompt;

            return 'continued';
        },
    );

    expect($forwardedPrompt->prompt)->toBe('Call me on [PHONE_1].');
});

it('redacts ip addresses by default', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('The login came from 192.168.1.10.'),
        function (AgentPrompt $prompt) use (&$forwardedPrompt): string {
            $forwardedPrompt = $prompt;

            return 'continued';
        },
    );

    expect($forwardedPrompt->prompt)->toBe('The login came from [IP_ADDRESS_1].');
});

it('redacts MAC addresses by default', function () : void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('The router MAC is 00:1A:2B:3C:4D:5E'),
        function (AgentPrompt $prompt) use (&$forwardedPrompt): string {
            $forwardedPrompt = $prompt;

            return 'continued';
        },
    );

    expect($forwardedPrompt->prompt)->toBe('The router MAC is [MAC_ADDRESS_1]');
});

it('blocks credit cards by default', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    expect(fn () => $redactor->handle(
        makePIIRedactorAgentPrompt('My card is 4111 1111 1111 1111.'),
        fn (AgentPrompt $prompt) => $prompt,
    ))->toThrow(PIIRedactorException::class);
});

it('blocks api keys by default', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    expect(fn () => $redactor->handle(
        makePIIRedactorAgentPrompt('Use sk-abcdefghijklmnopqrstuvwxyz1234567890ABCDE for this request.'),
        fn (AgentPrompt $prompt) => $prompt,
    ))->toThrow(PIIRedactorException::class);
});

it('blocks bearer tokens by default', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    expect(fn () => $redactor->handle(
        makePIIRedactorAgentPrompt('Authorization: Bearer abcdefghijklmnopqrstuvwxyz1234567890'),
        fn (AgentPrompt $prompt) => $prompt,
    ))->toThrow(PIIRedactorException::class);
});

it('logs safely and continues unchanged when action is log', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->with('PII detected in agent prompt.', Mockery::on(function (array $context): bool {
            expect($context)->toHaveKeys([
                'agent',
                'provider',
                'model',
                'entities',
                'value_hashes',
                'prompt_hash',
                'timestamp',
            ]);

            expect($context['agent'])->toBe(PIIRedactorTestAgent::class);
            expect($context['provider'])->toBe(PIIRedactorTestProvider::class);
            expect($context['model'])->toBe('test-model');
            expect($context['entities'])->toBe(['email' => 1]);
            expect($context)->not->toHaveKey('prompt_preview');

            return true;
        }));

    $redactor = new PIIRedactor(
        action: 'log',
        blockEntities: [],
    );

    $forwardedPrompt = null;

    $result = $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com.'),
        function (AgentPrompt $prompt) use (&$forwardedPrompt): string {
            $forwardedPrompt = $prompt;

            return 'continued';
        },
    );

    expect($result)->toBe('continued');
    expect($forwardedPrompt->prompt)->toBe('Email victor@example.com.');
});

it('can include a prompt preview in logs when enabled', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->with('PII detected in agent prompt.', Mockery::on(function (array $context): bool {
            expect($context)->toHaveKey('prompt_preview');
            expect($context['prompt_preview'])->toContain('victor@example.com');

            return true;
        }));

    $redactor = new PIIRedactor(
        logPreview: true,
    );

    $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com.'),
        fn (AgentPrompt $prompt) => 'continued',
    );
});

it('masks detected values when action is mask', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor(
        action: 'mask',
        blockEntities: [],
    );

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com or call 07123456789.'),
        function (AgentPrompt $prompt) use (&$forwardedPrompt): string {
            $forwardedPrompt = $prompt;

            return 'continued';
        },
    );

    expect($forwardedPrompt->prompt)->toContain('v*****@example.com');
    expect($forwardedPrompt->prompt)->toContain('*******6789');
});

it('uses config values when constructor values are not provided', function (): void {
    config()->set('intercept.middleware.pii_redactor.action', 'mask');
    config()->set('intercept.middleware.pii_redactor.block_entities', []);

    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com.'),
        function (AgentPrompt $prompt) use (&$forwardedPrompt): string {
            $forwardedPrompt = $prompt;

            return 'continued';
        },
    );

    expect($forwardedPrompt->prompt)->toBe('Email v*****@example.com.');
});

it('allows constructor values to override config values', function (): void {
    config()->set('intercept.middleware.pii_redactor.action', 'log');

    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor(
        action: 'block',
    );

    expect(fn () => $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com.'),
        fn (AgentPrompt $prompt) => $prompt,
    ))->toThrow(PIIRedactorException::class);
});

it('falls back to internal defaults when config section is missing', function (): void {
    config()->set('intercept.middleware.pii_redactor', null);

    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com.'),
        function (AgentPrompt $prompt) use (&$forwardedPrompt): string {
            $forwardedPrompt = $prompt;

            return 'continued';
        },
    );

    expect($forwardedPrompt->prompt)->toBe('Email [EMAIL_1].');
});

it('ignores allowed email addresses', function (): void {
    $redactor = new PIIRedactor(
        allowedEmails: [
            'support@example.com',
        ],
    );

    $prompt = makePIIRedactorAgentPrompt('Email support@example.com.');

    $receivedPrompt = null;

    $result = $redactor->handle($prompt, function (AgentPrompt $prompt) use (&$receivedPrompt): string {
        $receivedPrompt = $prompt;

        return 'continued';
    });

    expect($result)->toBe('continued');
    expect($receivedPrompt)->toBe($prompt);
});

it('ignores allowed email domains', function (): void {
    $redactor = new PIIRedactor(
        allowedDomains: [
            'example.com',
        ],
    );

    $prompt = makePIIRedactorAgentPrompt('Email support@example.com.');

    $receivedPrompt = null;

    $result = $redactor->handle($prompt, function (AgentPrompt $prompt) use (&$receivedPrompt): string {
        $receivedPrompt = $prompt;

        return 'continued';
    });

    expect($result)->toBe('continued');
    expect($receivedPrompt)->toBe($prompt);
});

it('supports custom replacement formats', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor(
        replacementFormat: '<{{TYPE}}:{{INDEX}}>',
    );

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com.'),
        function (AgentPrompt $prompt) use (&$forwardedPrompt): string {
            $forwardedPrompt = $prompt;

            return 'continued';
        },
    );

    expect($forwardedPrompt->prompt)->toBe('Email <EMAIL:1>.');
});

it('only detects enabled entities', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor(
        entities: [
            'email',
        ],
    );

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com or call 07123456789.'),
        function (AgentPrompt $prompt) use (&$forwardedPrompt): string {
            $forwardedPrompt = $prompt;

            return 'continued';
        },
    );

    expect($forwardedPrompt->prompt)->toBe('Email [EMAIL_1] or call 07123456789.');
});

it('does not call the next middleware when blocking', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $nextWasCalled = false;

    try {
        $redactor->handle(
            makePIIRedactorAgentPrompt('My card is 4111 1111 1111 1111.'),
            function (AgentPrompt $prompt) use (&$nextWasCalled): void {
                $nextWasCalled = true;
            },
        );
    } catch (PIIRedactorException) {
        //
    }

    expect($nextWasCalled)->toBeFalse();
});

it('passes detection results to a custom callback', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor(
        callback: function (AgentPrompt $prompt, Closure $next, RedactionResult $result): mixed {
            expect($result->hasDetections())->toBeTrue();
            expect($result->detections[0]->type)->toBe('email');
            expect($result->detections[0]->value)->toBe('victor@example.com');

            return $next(
                $prompt->prepend('Custom callback handled PII.')
            );
        },
    );

    $forwardedPrompt = null;

    $result = $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com.'),
        function (AgentPrompt $prompt) use (&$forwardedPrompt): string {
            $forwardedPrompt = $prompt;

            return 'continued';
        },
    );

    expect($result)->toBe('continued');
    expect($forwardedPrompt->prompt)->toStartWith('Custom callback handled PII.');
});

it('throws an exception for unsupported actions', function (): void {
    expect(fn () => new PIIRedactor(action: 'unknown'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported PII redactor action');
});

it('throws an exception for unsupported entities', function (): void {
    expect(fn () => new PIIRedactor(entities: ['passport']))
        ->toThrow(InvalidArgumentException::class, 'Unsupported PII entity');
});
