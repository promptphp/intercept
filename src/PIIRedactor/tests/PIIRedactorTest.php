<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use PromptPHP\Intercept\PIIRedactor\Exceptions\PIIRedactorException;
use PromptPHP\Intercept\PIIRedactor\PIIRedactor;
use PromptPHP\Intercept\PIIRedactor\Tests\Fixtures\PIIRedactorTestAgent;
use PromptPHP\Intercept\PIIRedactor\Tests\Fixtures\PIIRedactorTestProvider;
use PromptPHP\Intercept\PIIRedactor\ValueObjects\RedactionResult;
use PromptPHP\Intercept\Support\ApprovalDecisionLedger;

afterEach(function (): void {
    Mockery::close();
});

/**
 * Build a generation step with the given history.
 *
 * @param array<int, Message> $messages
 */
function makePIIRedactorStep(array $messages, int $number = 0): PendingStep
{
    return new PendingStep(
        number: $number,
        isFinalStep: false,
        provider: 'test-provider',
        model: 'test-model',
        instructions: 'You are a support agent.',
        messages: $messages,
        tools: [],
        schema: null,
        options: new TextGenerationOptions(agent: new PIIRedactorTestAgent),
        invocationId: 'inv_1',
    );
}

/**
 * Build the first step of a new turn, which ends with the prompt.
 */
function makePIIRedactorAgentPrompt(string $prompt): PendingStep
{
    return makePIIRedactorStep([new UserMessage($prompt)]);
}

/**
 * Get the prompt text a step sends to the provider.
 */
function piiStepPrompt(PendingStep $step): string
{
    $messages = array_values(array_filter($step->messages, fn (Message $message): bool => $message instanceof UserMessage));

    return (string) $messages[count($messages) - 1]->content;
}

/**
 * Build a prompt resuming a paused run, which always carries empty prompt text.
 */
function makePIIRedactorResumedPrompt(Decisions $approvalDecisions): AgentPrompt
{
    return new AgentPrompt(
        agent: new PIIRedactorTestAgent,
        prompt: '',
        attachments: [],
        provider: new PIIRedactorTestProvider,
        model: 'test-model',
        invocationId: 'inv_1',
        approvalDecisions: $approvalDecisions,
    );
}

/**
 * Build the first step of a resumed run, which ends with the tool results of the decisions.
 *
 * @param array<int, ToolResult> $results
 */
function makePIIRedactorResumedStep(array $results): PendingStep
{
    $calls = array_map(
        fn (ToolResult $result): ToolCall => new ToolCall($result->id, $result->name, ['recipient' => 'team@example.org']),
        $results,
    );

    return makePIIRedactorStep([
        new UserMessage('Send the summary.'),
        new AssistantMessage('', collect($calls)),
        new ToolResultMessage(collect($results)),
    ]);
}

it('allows safe prompts to continue through the pipeline', function (): void {
    $redactor = new PIIRedactor;

    $prompt = makePIIRedactorAgentPrompt('Summarise this support ticket.');

    $receivedPrompt = null;

    $result = $redactor->handle($prompt, function (PendingStep $step) use (&$receivedPrompt): string {
        $receivedPrompt = $step;

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
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Email [EMAIL_1] about this.');
});

it('redacts phone numbers by default', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Call me on 07123456789.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Call me on [PHONE_1].');
});

it('redacts ip addresses by default', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('The login came from 192.168.1.10.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('The login came from [IP_ADDRESS_1].');
});

it('redacts MAC addresses by default', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('The router MAC is 00:1A:2B:3C:4D:5E'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('The router MAC is [MAC_ADDRESS_1]');
});

it('redacts URLs by default', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Look at http://example.com, check the page https://example.com, visit the site at www.example.com, or https://127.0.0.1:8080, or view the example.com/dashboard.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Look at [URL_1], check the page [URL_2], visit the site at [URL_3], or [URL_4], or view the [URL_5].');
});

it('redacts http URLs with fragments', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('See http://example.com/docs#section-1 for details.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('See [URL_1] for details.');
});

it('redacts URLs with ports', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Dev server at http://localhost:8080/api is ready.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Dev server at [URL_1] is ready.');
});

it('strips trailing punctuation from scheme URLs', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Visit https://example.com, it is great.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Visit [URL_1], it is great.');
});

it('redacts bare domains that start with www.', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Go to www.example.com today.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Go to [URL_1] today.');
});

it('redacts bare domains that include a path', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Repo at github.com/org/repo/pull/123.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Repo at [URL_1].');
});

it('does not redact bare domains in prose without a path or www prefix', function (): void {
    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('I love github.com and use it daily.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    // No PII detected → no log, prompt unchanged
    expect(piiStepPrompt($forwardedPrompt))->toBe('I love github.com and use it daily.');
});

it('does not redact bare domains at end of sentence', function (): void {
    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('My favourite site is laravel.com.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('My favourite site is laravel.com.');
});

it('does not redact bare domains inside parentheses', function (): void {
    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('See docs (example.com) for more.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('See docs (example.com) for more.');
});

it('does not redact malformed scheme URLs without a host', function (): void {
    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Broken link: http://?foo=bar.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Broken link: http://?foo=bar.');
});

it('does not redact scheme-only fragments', function (): void {
    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Type http:// here.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Type http:// here.');
});

it('prefers the full scheme URL over an overlapping bare domain', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Check https://example.com/path for updates.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    // Must be a single [URL_1] covering the full scheme URL, not two separate redactions
    expect(piiStepPrompt($forwardedPrompt))->toBe('Check [URL_1] for updates.');
});

it('redacts URLs alongside emails and phone numbers', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com or visit https://example.com/help or call 07123456789.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Email [EMAIL_1] or visit [URL_1] or call [PHONE_1].');
});

it('blocks credit cards by default', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    expect(fn () => $redactor->handle(
        makePIIRedactorAgentPrompt('My card is 4111 1111 1111 1111.'),
        fn (PendingStep $step) => $step,
    ))->toThrow(PIIRedactorException::class);
});

it('blocks api keys by default', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    expect(fn () => $redactor->handle(
        makePIIRedactorAgentPrompt('Use sk-abcdefghijklmnopqrstuvwxyz1234567890ABCDE for this request.'),
        fn (PendingStep $step) => $step,
    ))->toThrow(PIIRedactorException::class);
});

it('blocks bearer tokens by default', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    expect(fn () => $redactor->handle(
        makePIIRedactorAgentPrompt('Authorization: Bearer abcdefghijklmnopqrstuvwxyz1234567890'),
        fn (PendingStep $step) => $step,
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
            expect($context['provider'])->toBe('test-provider');
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
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect($result)->toBe('continued');
    expect(piiStepPrompt($forwardedPrompt))->toBe('Email victor@example.com.');
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
        fn (PendingStep $step) => 'continued',
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
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toContain('v*****@example.com');
    expect(piiStepPrompt($forwardedPrompt))->toContain('*******6789');
});

it('uses config values when constructor values are not provided', function (): void {
    config()->set('intercept.middleware.pii_redactor.action', 'mask');
    config()->set('intercept.middleware.pii_redactor.block_entities', []);

    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Email v*****@example.com.');
});

it('allows constructor values to override config values', function (): void {
    config()->set('intercept.middleware.pii_redactor.action', 'log');

    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor(
        action: 'block',
    );

    expect(fn () => $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com.'),
        fn (PendingStep $step) => $step,
    ))->toThrow(PIIRedactorException::class);
});

it('falls back to internal defaults when config section is missing', function (): void {
    config()->set('intercept.middleware.pii_redactor', null);

    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwardedPrompt = null;

    $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Email [EMAIL_1].');
});

it('ignores allowed email addresses', function (): void {
    $redactor = new PIIRedactor(
        allowedEmails: [
            'support@example.com',
        ],
    );

    $prompt = makePIIRedactorAgentPrompt('Email support@example.com.');

    $receivedPrompt = null;

    $result = $redactor->handle($prompt, function (PendingStep $step) use (&$receivedPrompt): string {
        $receivedPrompt = $step;

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

    $result = $redactor->handle($prompt, function (PendingStep $step) use (&$receivedPrompt): string {
        $receivedPrompt = $step;

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
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Email <EMAIL:1>.');
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
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect(piiStepPrompt($forwardedPrompt))->toBe('Email [EMAIL_1] or call 07123456789.');
});

it('does not call the next middleware when blocking', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $nextWasCalled = false;

    try {
        $redactor->handle(
            makePIIRedactorAgentPrompt('My card is 4111 1111 1111 1111.'),
            function (PendingStep $step) use (&$nextWasCalled): void {
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
        callback: function (PendingStep $step, Closure $next, RedactionResult $result): mixed {
            expect($result->hasDetections())->toBeTrue();
            expect($result->detections[0]->type)->toBe('email');
            expect($result->detections[0]->value)->toBe('victor@example.com');

            return $next(
                $step->withMessages([new UserMessage('Custom callback handled PII.')])
            );
        },
    );

    $forwardedPrompt = null;

    $result = $redactor->handle(
        makePIIRedactorAgentPrompt('Email victor@example.com.'),
        function (PendingStep $step) use (&$forwardedPrompt): string {
            $forwardedPrompt = $step;

            return 'continued';
        },
    );

    expect($result)->toBe('continued');
    expect(piiStepPrompt($forwardedPrompt))->toStartWith('Custom callback handled PII.');
});

it('throws an exception for unsupported actions', function (): void {
    expect(fn () => new PIIRedactor(action: 'unknown'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported PII redactor action');
});

it('throws an exception for unsupported entities', function (): void {
    expect(fn () => new PIIRedactor(entities: ['passport']))
        ->toThrow(InvalidArgumentException::class, 'Unsupported PII entity');
});

it('allows resumed runs with clean approval decisions to continue', function (): void {
    $redactor = new PIIRedactor;

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::edit(['subject' => 'Quarterly summary']),
    ]));

    expect(fn () => $redactor->inspectApprovalDecisions($prompt))->not->toThrow(Throwable::class);
});

it('detects PII in edited tool arguments on a resumed run', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'PII detected in tool approval decisions.'
                && $context['source'] === 'approval_decisions'
                && $context['entities'] === ['email' => 1]
                && $context['segments'][0]['tool_call_id'] === 'call_1'
                && $context['segments'][0]['field'] === 'arguments.recipient';
        });

    $redactor = new PIIRedactor;

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::edit(['recipient' => 'victor@example.com']),
    ]));

    expect(fn () => $redactor->inspectApprovalDecisions($prompt))->not->toThrow(Throwable::class);
});

it('detects PII in rejection results on a resumed run', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $context['segments'][0]['field'] === 'result');

    $redactor = new PIIRedactor;

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::reject('Cancelled, email victor@example.com instead.'),
    ]));

    expect(fn () => $redactor->inspectApprovalDecisions($prompt))->not->toThrow(Throwable::class);
});

it('blocks high risk entities found in approval decisions', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::edit(['token' => 'sk-abcdefghijklmnopqrstuvwxyz123456']),
    ]));

    expect(fn () => $redactor->inspectApprovalDecisions($prompt))->toThrow(PIIRedactorException::class);
});

it('blocks an unquoted card number in edited tool arguments', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::edit(['card' => 4111111111111111]),
    ]));

    expect(fn () => $redactor->inspectApprovalDecisions($prompt))
        ->toThrow(PIIRedactorException::class);
});

it('degrades redact to logging on a resumed run', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => ($context['degraded_from'] ?? null) === 'redact');

    $redactor = new PIIRedactor;

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::edit(['recipient' => 'victor@example.com']),
    ]));

    expect(fn () => $redactor->inspectApprovalDecisions($prompt))->not->toThrow(Throwable::class);
});

it('degrades mask to logging on a resumed run', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => ($context['degraded_from'] ?? null) === 'mask');

    $redactor = new PIIRedactor(action: 'mask');

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::edit(['recipient' => 'victor@example.com']),
    ]));

    expect(fn () => $redactor->inspectApprovalDecisions($prompt))->not->toThrow(Throwable::class);
});

it('does not report a degraded action when the run is blocked', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => ! array_key_exists('degraded_from', $context));

    $redactor = new PIIRedactor;

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::edit(['token' => 'sk-abcdefghijklmnopqrstuvwxyz123456']),
    ]));

    expect(fn () => $redactor->inspectApprovalDecisions($prompt))
        ->toThrow(PIIRedactorException::class);
});

it('blocks approval decision detections when the action is block', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor(action: 'block');

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::edit(['recipient' => 'victor@example.com']),
    ]));

    expect(fn () => $redactor->inspectApprovalDecisions($prompt))
        ->toThrow(PIIRedactorException::class);
});

it('skips approval decision scanning when disabled', function (): void {
    Log::shouldReceive('warning')->never();

    $redactor = new PIIRedactor(scanApprovalDecisions: false);

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::edit(['token' => 'sk-abcdefghijklmnopqrstuvwxyz123456']),
    ]));

    expect(fn () => $redactor->inspectApprovalDecisions($prompt))->not->toThrow(Throwable::class);
});

it('passes approval decision detections to a custom callback', function (): void {
    Log::shouldReceive('warning')->once();

    $received = null;

    $redactor = new PIIRedactor(
        callback: function (AgentPrompt $prompt, ?Closure $next, RedactionResult $result) use (&$received): void {
            expect($next)->toBeNull();

            $received = $result;
        },
    );

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::edit(['token' => 'sk-abcdefghijklmnopqrstuvwxyz123456']),
    ]));

    $redactor->inspectApprovalDecisions($prompt);
    expect($received->detections)->toHaveCount(1);
    expect($received->detections[0]->type)->toBe('api_key');
});

it('includes segment previews in approval decision logs when enabled', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $context['segments'][0]['preview'] === 'victor@example.com');

    $redactor = new PIIRedactor(logPreview: true);

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::edit(['recipient' => 'victor@example.com']),
    ]));

    $redactor->inspectApprovalDecisions($prompt);
});

it('reports detections across multiple approval decisions', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $context['entities'] === ['email' => 1, 'ip_address' => 1]
                && count($context['segments']) === 2;
        });

    $redactor = new PIIRedactor;

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::edit(['recipient' => 'victor@example.com']),
        'call_2' => Decision::reject('Blocked at 192.168.1.1.'),
    ]));

    expect(fn () => $redactor->inspectApprovalDecisions($prompt))->not->toThrow(Throwable::class);
});

it('keeps approval decision scanning enabled when an older published config omits the key', function (): void {
    config()->set('intercept.middleware.pii_redactor', [
        'action'      => 'redact',
        'log_preview' => false,
    ]);

    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $prompt = makePIIRedactorResumedPrompt(Decisions::from([
        'call_1' => Decision::edit(['recipient' => 'victor@example.com']),
    ]));

    expect(fn () => $redactor->inspectApprovalDecisions($prompt))->not->toThrow(Throwable::class);
});

it('redacts every user message in the step history', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $step = makePIIRedactorStep([
        new UserMessage('My email is victor@example.com.'),
        new AssistantMessage('Thanks.'),
        new UserMessage('Call me on 07123456789.'),
    ]);

    $forwarded = null;

    $redactor->handle($step, function (PendingStep $step) use (&$forwarded): string {
        $forwarded = $step;

        return 'continued';
    });

    expect($forwarded->messages[0]->content)->toBe('My email is [EMAIL_1].');
    expect($forwarded->messages[1]->content)->toBe('Thanks.');
    expect($forwarded->messages[2]->content)->toBe('Call me on [PHONE_1].');
});

it('repeats the redaction on a later step without logging again', function (): void {
    Log::shouldReceive('warning')->never();

    $redactor = new PIIRedactor;

    $step = makePIIRedactorStep([
        new UserMessage('Email victor@example.com about this.'),
        new AssistantMessage('', collect([new ToolCall('call_1', 'lookup', [])])),
        new ToolResultMessage(collect([new ToolResult('call_1', 'lookup', [], 'Found.')])),
    ], number: 1);

    $forwarded = null;

    $redactor->handle($step, function (PendingStep $step) use (&$forwarded): string {
        $forwarded = $step;

        return 'continued';
    });

    expect(piiStepPrompt($forwarded))->toBe('Email [EMAIL_1] about this.');
});

it('keeps prompt attachments when it redacts the prompt', function (): void {
    Log::shouldReceive('warning')->once();

    $redactor = new PIIRedactor;

    $forwarded = null;

    $redactor->handle(makePIIRedactorStep([new UserMessage('Email victor@example.com.', ['attachment'])]), function (PendingStep $step) use (&$forwarded): string {
        $forwarded = $step;

        return 'continued';
    });

    $message = $forwarded->messages[0];

    expect($message)->toBeInstanceOf(UserMessage::class);
    expect($message instanceof UserMessage ? $message->attachments->all() : null)->toBe(['attachment']);
});

it('blocks a high risk entity in edited arguments on the first step of a resumed run', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $context['source'] === 'approval_decisions'
            && $context['step'] === 0
            && $context['segments'][0]['field'] === 'arguments.token');

    $redactor = new PIIRedactor;

    $step = makePIIRedactorResumedStep([
        new ToolResult('call_1', 'send', ['token' => 'sk-abcdefghijklmnopqrstuvwxyz123456'], 'Sent.'),
    ]);

    expect(fn () => $redactor->handle($step, fn (): string => 'next-called'))
        ->toThrow(PIIRedactorException::class);
});

it('skips the resumed step scan when the listener already inspected the decisions', function (): void {
    Log::shouldReceive('warning')->never();

    resolve(ApprovalDecisionLedger::class)->markInspected('inv_1');

    $redactor = new PIIRedactor;

    $step = makePIIRedactorResumedStep([
        new ToolResult('call_1', 'send', ['token' => 'sk-abcdefghijklmnopqrstuvwxyz123456'], 'Sent.'),
    ]);

    expect($redactor->handle($step, fn (): string => 'next-called'))->toBe('next-called');
});
