<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\Tests\Fixtures;

use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\TextGenerationLoop;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use RuntimeException;

final class PIIRedactorTestProvider implements TextProvider
{
    /**
     * {@inheritDoc}
     */
    public function prompt(AgentPrompt $prompt): AgentResponse
    {
        throw new RuntimeException('Not used in this test.');
    }

    /**
     * {@inheritDoc}
     */
    public function stream(AgentPrompt $prompt): StreamableAgentResponse
    {
        throw new RuntimeException('Not used in this test.');
    }

    /**
     * {@inheritDoc}
     */
    public function useTextGateway(StepTextGateway $gateway): self
    {
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function textGenerationLoop(): TextGenerationLoop
    {
        throw new RuntimeException('Not used in this test.');
    }

    /**
     * {@inheritDoc}
     */
    public function defaultTextModel(): string
    {
        return 'test-model';
    }

    /**
     * {@inheritDoc}
     */
    public function cheapestTextModel(): string
    {
        return 'test-cheapest-model';
    }

    /**
     * {@inheritDoc}
     */
    public function smartestTextModel(): string
    {
        return 'test-smartest-model';
    }

    // ---- Provider interface ----

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'test-provider';
    }

    /**
     * {@inheritDoc}
     */
    public function driver(): string
    {
        return 'test';
    }

    /**
     * {@inheritDoc}
     */
    public function providerCredentials(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function additionalConfiguration(): array
    {
        return [];
    }
}
