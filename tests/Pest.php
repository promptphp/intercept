<?php

declare(strict_types=1);

use PromptPHP\Intercept\Tests\TestCase;

uses(TestCase::class)->in(
    ...glob(__DIR__.'/../src/*/tests') ?: [],
);
