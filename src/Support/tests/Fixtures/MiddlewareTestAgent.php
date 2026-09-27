<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support\Tests\Fixtures;

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\HasMiddleware;

/**
 * A conversational test agent whose middleware, history, and tools the test controls.
 */
class MiddlewareTestAgent extends AnonymousAgent implements HasMiddleware
{
    /**
     * Create a new test agent.
     *
     * @param string            $instructions The agent instructions.
     * @param iterable<mixed>   $messages     The conversation history.
     * @param iterable<mixed>   $tools        The agent tools.
     * @param array<int, mixed> $pipeline     The agent middleware.
     */
    public function __construct(
        string $instructions = 'You are a test agent.',
        iterable $messages = [],
        iterable $tools = [],
        public array $pipeline = [],
    ) {
        parent::__construct($instructions, $messages, $tools);
    }

    /**
     * {@inheritDoc}
     */
    public function middleware(): array
    {
        return $this->pipeline;
    }
}
