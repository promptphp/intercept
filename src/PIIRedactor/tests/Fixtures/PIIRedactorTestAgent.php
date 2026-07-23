<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\Tests\Fixtures;

use Illuminate\Broadcasting\Channel;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\QueuedAgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use RuntimeException;

final class PIIRedactorTestAgent implements Agent
{
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
        Decisions|string $prompt,
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
        Decisions|string $prompt,
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
        Decisions|string $prompt,
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
        Decisions|string $prompt,
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
        Decisions|string $prompt,
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
        Decisions|string $prompt,
        Channel|array $channels,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null
    ): QueuedAgentResponse {
        throw new RuntimeException('Not used in this test.');
    }
}
