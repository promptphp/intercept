<?php

use Illuminate\Support\ServiceProvider;

test('Intercept provider class extends base Laravel service provider class')
    ->expect('PromptPHP\Intercept\Support\InterceptServiceProvider')
    ->classes()
    ->toExtend(ServiceProvider::class);

