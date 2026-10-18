<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\BudgetGuard\Tests\Fixtures;

use Illuminate\Broadcasting\Channel;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\AgentInput;
use Laravel\Ai\Contracts\RemembersConversations as RemembersConversationsContract;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\QueuedAgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use RuntimeException;

/**
 * A test agent that remembers conversations, so the default session and user key resolvers
 * have something to resolve.
 */
final class BudgetGuardTestConversationalAgent implements Agent, RemembersConversationsContract
{
    use RemembersConversations;

    /**
     * {@inheritDoc}
     */
    public function instructions(): string
    {
        return 'You are a test agent.';
    }

    /**
     * {@inheritDoc}
     */
    public function prompt(
        AgentInput|UserMessage|Decisions|string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): AgentResponse {
        throw new RuntimeException('Not used in this test.');
    }

    /**
     * {@inheritDoc}
     */
    public function stream(
        AgentInput|UserMessage|Decisions|string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): StreamableAgentResponse {
        throw new RuntimeException('Not used in this test.');
    }

    /**
     * {@inheritDoc}
     */
    public function queue(
        AgentInput|UserMessage|Decisions|string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null
    ): QueuedAgentResponse {
        throw new RuntimeException('Not used in this test.');
    }

    /**
     * {@inheritDoc}
     */
    public function broadcast(
        AgentInput|UserMessage|Decisions|string $prompt,
        Channel|array $channels,
        array $attachments = [],
        bool $now = false,
        Lab|array|string|null $provider = null,
        ?string $model = null
    ): StreamableAgentResponse {
        throw new RuntimeException('Not used in this test.');
    }

    /**
     * {@inheritDoc}
     */
    public function broadcastNow(
        AgentInput|UserMessage|Decisions|string $prompt,
        Channel|array $channels,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null
    ): StreamableAgentResponse {
        throw new RuntimeException('Not used in this test.');
    }

    /**
     * {@inheritDoc}
     */
    public function broadcastOnQueue(
        AgentInput|UserMessage|Decisions|string $prompt,
        Channel|array $channels,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null
    ): QueuedAgentResponse {
        throw new RuntimeException('Not used in this test.');
    }
}
