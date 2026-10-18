<?php

declare(strict_types=1);

use PromptPHP\Intercept\BudgetGuard\Pricing\ModelPricing;
use PromptPHP\Intercept\BudgetGuard\ValueObjects\ModelRate;

it('matches a model exactly', function (): void {
    $rate = ModelPricing::resolve('gpt-4o-mini', ModelPricing::rates());

    expect($rate)->toBeInstanceOf(ModelRate::class)
        ->and($rate->input)->toBe(0.15)
        ->and($rate->output)->toBe(0.60);
});

it('matches a dated or versioned model id to its family by longest prefix', function (): void {
    $rate = ModelPricing::resolve('claude-sonnet-4-5-20250929', ModelPricing::rates());

    expect($rate)->toBeInstanceOf(ModelRate::class)
        ->and($rate->input)->toBe(3.00)
        ->and($rate->output)->toBe(15.00);
});

it('prefers the longer, more specific match over a shorter prefix', function (): void {
    $rate = ModelPricing::resolve('gpt-4o-mini-2024-07-18', ModelPricing::rates());

    expect($rate->input)->toBe(0.15)
        ->and($rate->output)->toBe(0.60);
});

it('strips a gateway or region prefix before matching', function (): void {
    $rate = ModelPricing::resolve('bedrock/us.anthropic.claude-3-5-haiku-20241022-v1:0', ModelPricing::rates());

    expect($rate)->toBeInstanceOf(ModelRate::class)
        ->and($rate->input)->toBe(0.80);
});

it('returns null for a model nothing prices', function (): void {
    expect(ModelPricing::resolve('some-brand-new-model', ModelPricing::rates()))->toBeNull();
});

it('lets a custom pricing table override a built-in rate', function (): void {
    $rate = ModelPricing::resolve('gpt-4o', [
        ...ModelPricing::rates(),
        'gpt-4o' => ['input' => 1.00, 'output' => 2.00],
    ]);

    expect($rate->input)->toBe(1.00)
        ->and($rate->output)->toBe(2.00);
});
