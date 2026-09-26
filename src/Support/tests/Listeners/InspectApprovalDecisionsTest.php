<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laravel\Ai\AiManager;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use Laravel\Ai\Gateway\FakeTextGateway;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use PromptPHP\Intercept\Support\ApprovalDecisionLedger;
use PromptPHP\Intercept\Support\Listeners\InspectApprovalDecisions;
use PromptPHP\Intercept\Support\Tests\Fixtures\MiddlewareTestAgent;
use PromptPHP\Intercept\Support\Tests\Fixtures\RecordingApprovableTool;
use PromptPHP\Intercept\Support\Tests\Fixtures\RecordingDecisionInspector;

beforeEach(function (): void {
    RecordingDecisionInspector::$inspected = [];
    RecordingApprovableTool::$executions   = [];
});

/**
 * Build a prompt for the given agent, optionally resuming a paused run.
 */
function makeListenerPrompt(MiddlewareTestAgent $agent, ?Decisions $decisions = null): AgentPrompt
{
    return new AgentPrompt(
        agent: $agent,
        prompt: $decisions === null ? 'Hello.' : '',
        attachments: [],
        provider: resolve(AiManager::class)->textProvider(),
        model: 'test-model',
        invocationId: 'inv_1',
        approvalDecisions: $decisions,
    );
}

/**
 * Answer every step of the default provider with the given responses.
 *
 * The fake sits on the provider, not on the agent, because the SDK does not apply approval
 * decisions for an agent that is faked.
 *
 * @param array<int, mixed> $responses
 */
function useScriptedTextGateway(array $responses): void
{
    resolve(AiManager::class)->textProvider()->useTextGateway(new FakeTextGateway($responses));
}

/**
 * Build a conversation history that waits on one tool call.
 *
 * @return array<int, mixed>
 */
function pausedHistory(): array
{
    return [
        new UserMessage('Send the report.'),
        new AssistantMessage('', collect([new ToolCall('call_1', 'send_report', ['to' => 'ops'])])),
    ];
}

it('registers the listener for the prompting and streaming events', function (): void {
    expect(Event::hasListeners(PromptingAgent::class))->toBeTrue();
    expect(Event::hasListeners(StreamingAgent::class))->toBeTrue();
});

it('registers the ledger as a scoped singleton', function (): void {
    expect(resolve(ApprovalDecisionLedger::class))->toBe(resolve(ApprovalDecisionLedger::class));
});

it('inspects the decisions of a resumed prompt and records the run', function (string $event): void {
    $agent  = new MiddlewareTestAgent(pipeline: [new RecordingDecisionInspector]);
    $prompt = makeListenerPrompt($agent, Decisions::from(['call_1' => Decision::approve()]));

    Event::dispatch(new $event('inv_1', $prompt));

    expect(RecordingDecisionInspector::$inspected)->toBe([$prompt]);
    expect(resolve(ApprovalDecisionLedger::class)->wasInspected('inv_1'))->toBeTrue();
})->with([
    'prompting' => PromptingAgent::class,
    'streaming' => StreamingAgent::class,
]);

it('resolves middleware given as a class name', function (): void {
    $agent = new MiddlewareTestAgent(pipeline: [RecordingDecisionInspector::class]);

    (new InspectApprovalDecisions(new ApprovalDecisionLedger))
        ->handle(new PromptingAgent('inv_1', makeListenerPrompt($agent, Decisions::from(['call_1' => Decision::approve()]))));

    expect(RecordingDecisionInspector::$inspected)->toHaveCount(1);
});

it('ignores prompts that do not resume a paused run', function (): void {
    $ledger = new ApprovalDecisionLedger;
    $agent  = new MiddlewareTestAgent(pipeline: [new RecordingDecisionInspector]);

    (new InspectApprovalDecisions($ledger))->handle(new PromptingAgent('inv_1', makeListenerPrompt($agent)));

    expect(RecordingDecisionInspector::$inspected)->toBe([]);
    expect($ledger->wasInspected('inv_1'))->toBeFalse();
});

it('stops a resumed run before the SDK executes an approved tool call', function (): void {
    useScriptedTextGateway(['Done.']);

    $agent = new MiddlewareTestAgent(
        pipeline: [new RecordingDecisionInspector(blocks: true)],
        messages: pausedHistory(),
        tools: [new RecordingApprovableTool],
    );

    expect(fn () => $agent->prompt(Decisions::from(['call_1' => Decision::edit(['to' => 'attacker'])])))
        ->toThrow(RuntimeException::class, 'Blocked by the decision inspector.');

    expect(RecordingDecisionInspector::$inspected)->toHaveCount(1);
    expect(RecordingApprovableTool::$executions)->toBe([]);
});

it('stops a streamed resumed run before the SDK executes an approved tool call', function (): void {
    useScriptedTextGateway(['Done.']);

    $agent = new MiddlewareTestAgent(
        pipeline: [new RecordingDecisionInspector(blocks: true)],
        messages: pausedHistory(),
        tools: [new RecordingApprovableTool],
    );

    expect(fn () => iterator_to_array($agent->stream(Decisions::from(['call_1' => Decision::approve()]))))
        ->toThrow(RuntimeException::class, 'Blocked by the decision inspector.');

    expect(RecordingApprovableTool::$executions)->toBe([]);
});

it('lets a resumed run execute the approved tool call when nothing blocks it', function (): void {
    useScriptedTextGateway(['Done.']);

    $agent = new MiddlewareTestAgent(
        pipeline: [new RecordingDecisionInspector],
        messages: pausedHistory(),
        tools: [new RecordingApprovableTool],
    );

    $agent->prompt(Decisions::from(['call_1' => Decision::edit(['to' => 'finance'])]));

    expect(RecordingApprovableTool::$executions)->toBe([['to' => 'finance']]);
});
