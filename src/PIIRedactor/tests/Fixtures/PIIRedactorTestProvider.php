<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\Tests\Fixtures;

use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use RuntimeException;

final class PIIRedactorTestProvider implements TextProvider
{
    public function prompt(AgentPrompt $prompt): AgentResponse
    {
        throw new RuntimeException('Not used in this test.');
    }

    public function stream(AgentPrompt $prompt): StreamableAgentResponse
    {
        throw new RuntimeException('Not used in this test.');
    }

    public function textGateway(): TextGateway
    {
        throw new RuntimeException('Not used in this test.');
    }

    public function useTextGateway(TextGateway $gateway): self
    {
        return $this;
    }

    public function defaultTextModel(): string
    {
        return 'test-model';
    }

    public function cheapestTextModel(): string
    {
        return 'test-cheapest-model';
    }

    public function smartestTextModel(): string
    {
        return 'test-smartest-model';
    }
}
