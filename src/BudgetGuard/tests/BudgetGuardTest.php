<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use PromptPHP\Intercept\BudgetGuard\BudgetGuard;
use PromptPHP\Intercept\BudgetGuard\Enums\BudgetTypes;
use PromptPHP\Intercept\BudgetGuard\Exceptions\BudgetGuardException;
use PromptPHP\Intercept\BudgetGuard\Tests\Fixtures\BudgetGuardTestAgent;
use PromptPHP\Intercept\BudgetGuard\Tests\Fixtures\BudgetGuardTestConversationalAgent;
use PromptPHP\Intercept\BudgetGuard\ValueObjects\BudgetBreach;
use PromptPHP\Intercept\BudgetGuard\ValueObjects\CostEstimate;

afterEach(function (): void {
    Mockery::close();
});

/**
 * Build a generation step for the budget guard tests.
 *
 * @param array<int, mixed> $messages
 * @param array<int, mixed> $tools
 */
function makeBudgetStep(
    array $messages = [],
    ?object $agent = null,
    ?TextUsage $usage = null,
    string $model = 'gpt-4o',
    array $tools = [],
    int $number = 0,
): PendingStep {
    return new PendingStep(
        number: $number,
        isFinalStep: false,
        provider: 'test-provider',
        model: $model,
        instructions: 'You are a support agent.',
        messages: $messages === [] ? [new UserMessage('Hello there, can you help me today?')] : $messages,
        tools: $tools,
        schema: null,
        options: new TextGenerationOptions(agent: $agent ?? new BudgetGuardTestAgent),
        usage: $usage ?? new TextUsage,
        invocationId: 'inv_1',
    );
}

/**
 * Build a step response with the given usage.
 */
function respondBudget(?TextUsage $usage = null, string $text = 'All done.'): StepResponse
{
    return new StepResponse($text, [], FinishReason::Stop, $usage ?? new TextUsage(100, 50), new Meta('test-provider', 'gpt-4o'));
}

/**
 * Run the guard on a step and resolve the step response, capturing the step actually sent on.
 */
function guardBudgetStep(BudgetGuard $guard, PendingStep $step, StepResponse $response, ?PendingStep &$receivedStep = null): ?StepResponse
{
    return $guard->handle($step, function (PendingStep $sent) use (&$receivedStep, $response): StepResult {
        $receivedStep = $sent;

        return new StepResult($response);
    })->response();
}

it('lets a request within every configured budget through untouched', function (): void {
    $guard = new BudgetGuard(maxCostPerRequest: 10.00);

    $step     = makeBudgetStep();
    $response = respondBudget();

    expect(guardBudgetStep($guard, $step, $response))->toBe($response);
});

it('blocks a request projected to exceed the per-request budget', function (): void {
    Log::shouldReceive('warning')->once();

    $guard = new BudgetGuard(maxCostPerRequest: 0.0001, assumedOutputTokens: 1000);

    $step     = makeBudgetStep();
    $response = respondBudget();

    expect(fn () => guardBudgetStep($guard, $step, $response))
        ->toThrow(BudgetGuardException::class);
});

it('includes the cost already accrued by earlier steps in the request projection', function (): void {
    Log::shouldReceive('warning')->once();

    // A cheap new step, but a run that already burned through most of the budget.
    $guard = new BudgetGuard(maxCostPerRequest: 0.01, assumedOutputTokens: 10);

    $accrued  = new TextUsage(inputTokens: 1_000_000, outputTokens: 1_000_000);
    $step     = makeBudgetStep(messages: [new UserMessage('ok')], usage: $accrued, number: 1);
    $response = respondBudget();

    expect(fn () => guardBudgetStep($guard, $step, $response))
        ->toThrow(BudgetGuardException::class);
});

it('logs instead of blocking when the action is log', function (): void {
    Log::shouldReceive('warning')->once();

    $guard = new BudgetGuard(action: 'log', maxCostPerRequest: 0.0001);

    $step     = makeBudgetStep();
    $response = respondBudget();

    expect(guardBudgetStep($guard, $step, $response))->toBe($response);
});

it('truncates the newest user message to fit the per-request budget', function (): void {
    Log::shouldReceive('warning')->once();

    $guard = new BudgetGuard(action: 'truncate', maxCostPerRequest: 0.001, assumedOutputTokens: 10);

    $longMessage = new UserMessage(str_repeat('This is a very long support ticket. ', 50));
    $step        = makeBudgetStep(messages: [$longMessage]);
    $response    = respondBudget();

    $receivedStep = null;

    guardBudgetStep($guard, $step, $response, $receivedStep);

    expect($receivedStep)->not->toBeNull()
        ->and(mb_strlen((string) $receivedStep->messages[0]->content))
        ->toBeLessThan(mb_strlen((string) $longMessage->content));
});

it('degrades to blocking when truncation leaves no room for a prompt', function (): void {
    Log::shouldReceive('warning')->once();

    // The accrued cost alone already exceeds the budget, so no amount of truncation helps.
    $guard = new BudgetGuard(action: 'truncate', maxCostPerRequest: 0.0001, assumedOutputTokens: 1000);

    $accrued  = new TextUsage(inputTokens: 1_000_000, outputTokens: 1_000_000);
    $step     = makeBudgetStep(usage: $accrued, number: 1);
    $response = respondBudget();

    expect(fn () => guardBudgetStep($guard, $step, $response))
        ->toThrow(BudgetGuardException::class);
});

it('lets an unpriced model through by default and logs the gap', function (): void {
    Log::shouldReceive('warning')->once();

    $guard = new BudgetGuard(maxCostPerRequest: 0.01);

    $step     = makeBudgetStep(model: 'some-future-model-nobody-has-priced-yet');
    $response = respondBudget();

    expect(guardBudgetStep($guard, $step, $response))->toBe($response);
});

it('blocks an unpriced model when unknown_model_action is block', function (): void {
    Log::shouldReceive('warning')->once();

    $guard = new BudgetGuard(maxCostPerRequest: 0.01, unknownModelAction: 'block');

    $step     = makeBudgetStep(model: 'some-future-model-nobody-has-priced-yet');
    $response = respondBudget();

    expect(fn () => guardBudgetStep($guard, $step, $response))
        ->toThrow(BudgetGuardException::class);
});

it('delegates to a custom callback and lets the run continue', function (): void {
    Log::shouldReceive('warning')->once();

    $seen = null;

    $guard = new BudgetGuard(
        maxCostPerRequest: 0.0001,
        callback: function (PendingStep $step, BudgetBreach $breach, CostEstimate $estimate) use (&$seen): void {
            $seen = $breach;
        },
    );

    $step     = makeBudgetStep();
    $response = respondBudget();

    expect(guardBudgetStep($guard, $step, $response))->toBe($response)
        ->and($seen)->toBeInstanceOf(BudgetBreach::class)
        ->and($seen->type)->toBe(BudgetTypes::REQUEST);
});

it('tracks session spend across runs for a conversational agent and blocks once the budget is spent', function (): void {
    $agent = new BudgetGuardTestConversationalAgent;
    $agent->continue('conversation-'.Str::random());

    $guard = new BudgetGuard(maxCostPerSession: 0.001, assumedOutputTokens: 1);

    $usage = new TextUsage(inputTokens: 1000, outputTokens: 1000);

    // First run spends against the session...
    $first = guardBudgetStep(
        $guard,
        makeBudgetStep(agent: $agent, usage: new TextUsage),
        respondBudget($usage),
    );

    expect($first)->not->toBeNull();

    Log::shouldReceive('warning')->once();

    // The second run's own estimate, on top of what the first already spent, breaches the budget...
    expect(fn () => guardBudgetStep(
        $guard,
        makeBudgetStep(agent: $agent, usage: new TextUsage),
        respondBudget($usage),
    ))->toThrow(BudgetGuardException::class);
});

it('tracks daily spend for the conversation participant and blocks once the budget is spent', function (): void {
    $agent = new BudgetGuardTestConversationalAgent;
    $agent->forUser((object) ['id' => 'user-'.Str::random()]);

    $guard = new BudgetGuard(maxCostPerUserPerDay: 0.001, assumedOutputTokens: 1);

    $usage = new TextUsage(inputTokens: 1000, outputTokens: 1000);

    $first = guardBudgetStep(
        $guard,
        makeBudgetStep(agent: $agent, usage: new TextUsage),
        respondBudget($usage),
    );

    expect($first)->not->toBeNull();

    Log::shouldReceive('warning')->once();

    expect(fn () => guardBudgetStep(
        $guard,
        makeBudgetStep(agent: $agent, usage: new TextUsage),
        respondBudget($usage),
    ))->toThrow(BudgetGuardException::class);
});

it('skips the session budget silently when no session key can be resolved', function (): void {
    // The default agent does not remember conversations, so there is no session to attribute spend to.
    $guard = new BudgetGuard(maxCostPerSession: 0.00000001);

    $step     = makeBudgetStep();
    $response = respondBudget();

    expect(guardBudgetStep($guard, $step, $response))->toBe($response);
});

it('rejects an unsupported action', function (): void {
    expect(fn () => new BudgetGuard(action: 'redact'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an unsupported unknown model action', function (): void {
    expect(fn () => new BudgetGuard(unknownModelAction: 'warn'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a non positive session window', function (): void {
    expect(fn () => new BudgetGuard(sessionWindowHours: 0))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a non positive assumed output token count', function (): void {
    expect(fn () => new BudgetGuard(assumedOutputTokens: -1))
        ->toThrow(InvalidArgumentException::class);
});
