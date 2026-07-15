<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Laravel\Ai\Prompts\AgentPrompt;
use PromptPHP\Intercept\PIIRedactor\PIIRedactor;

it('redacts MAC addresses correctly', function () {
    // Boot an empty Laravel container and mock the config service
    $container = Container::getInstance();
    $container->bind('config', function () {
        return new class {
            public function get(string $key, mixed $default = null): mixed {
                return $default;
            }
        };
    });

    // Mock of AgentPrompt
    $mockPrompt = Mockery::mock(AgentPrompt::class);

    $reflection = new ReflectionClass(AgentPrompt::class);
    $property = $reflection->getProperty('prompt');
    $property->setAccessible(true);
    $property->setValue($mockPrompt, 'My router MAC is 00:1A:2B:3C:4D:5E and server is 192.168.1.1');

    $resultText = '';
    $mockPrompt->shouldReceive('revise')
        ->once()
        ->andReturnUsing(function ($revisedText) use (&$resultText, $mockPrompt) {
            $resultText = $revisedText;
            return $mockPrompt;
        });

    $redactor = new PIIRedactor(logDetections: false);

    $redactor->handle($mockPrompt, function ($nextPrompt) {
        return $nextPrompt;
    });

    expect($resultText)->toContain('[MAC_ADDRESS_1]');
    expect($resultText)->not->toContain('00:1A:2B:3C:4D:5E');
    expect($resultText)->toContain('[IP_ADDRESS_1]');
});