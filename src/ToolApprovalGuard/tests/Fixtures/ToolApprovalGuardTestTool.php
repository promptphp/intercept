<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\ToolApprovalGuard\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * A named tool whose approval rule the test controls.
 */
final class ToolApprovalGuardTestTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * Create a new test tool.
     *
     * @param string $toolName The name the model uses to call the tool.
     */
    public function __construct(private readonly string $toolName)
    {
        //
    }

    /**
     * Get the name of the tool.
     */
    public function name(): string
    {
        return $this->toolName;
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string
    {
        return 'A test tool.';
    }

    /**
     * {@inheritDoc}
     */
    public function handle(Request $request): string
    {
        return 'done';
    }

    /**
     * {@inheritDoc}
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
