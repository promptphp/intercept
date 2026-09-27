<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * A tool that needs approval and records every execution.
 */
final class RecordingApprovableTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * The arguments of every execution of any instance.
     *
     * @var array<int, array<string, mixed>>
     */
    public static array $executions = [];

    /**
     * Get the name of the tool.
     */
    public function name(): string
    {
        return 'send_report';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string
    {
        return 'Send a report.';
    }

    /**
     * {@inheritDoc}
     */
    public function handle(Request $request): string
    {
        self::$executions[] = $request->all();

        return 'Sent.';
    }

    /**
     * {@inheritDoc}
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
