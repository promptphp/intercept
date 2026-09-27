<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Tests\Fixtures;

use Closure;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\PendingStep;

/**
 * A middleware that records the content of every message each step sends.
 */
final class RecordingStepMessages
{
    /**
     * The message contents, one list per step.
     *
     * @var array<int, array<int, string|null>>
     */
    public static array $contents = [];

    /**
     * Record the step and pass it on.
     *
     * @param PendingStep $step The generation step.
     * @param Closure     $next The next middleware in the pipeline.
     */
    public function handle(PendingStep $step, Closure $next): mixed
    {
        self::$contents[] = array_map(fn (Message $message): ?string => $message->content, $step->messages);

        return $next($step);
    }
}
