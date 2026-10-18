<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\BudgetGuard;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\Contracts\RemembersConversations;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\PendingStep;
use PromptPHP\Intercept\BudgetGuard\Defaults\BudgetGuardDefaults;
use PromptPHP\Intercept\BudgetGuard\Enums\ActionTypes;
use PromptPHP\Intercept\BudgetGuard\Enums\BudgetTypes;
use PromptPHP\Intercept\BudgetGuard\Enums\UnknownModelActions;
use PromptPHP\Intercept\BudgetGuard\Exceptions\BudgetGuardException;
use PromptPHP\Intercept\BudgetGuard\Pricing\ModelPricing;
use PromptPHP\Intercept\BudgetGuard\Support\SpendStore;
use PromptPHP\Intercept\BudgetGuard\ValueObjects\BudgetBreach;
use PromptPHP\Intercept\BudgetGuard\ValueObjects\CostEstimate;
use PromptPHP\Intercept\BudgetGuard\ValueObjects\ModelRate;
use PromptPHP\Intercept\Support\Concerns\InspectsPendingSteps;
use PromptPHP\Intercept\Support\InterceptConfig;

class BudgetGuard
{
    use InspectsPendingSteps;

    /**
     * The number of characters a token costs when no custom estimator is supplied.
     *
     * A rough average for English prose. Supply `tokenEstimator` for anything more precise, such
     * as a real tokenizer for the model in use.
     */
    protected const CHARS_PER_TOKEN = 4;

    /**
     * The characters assumed to describe one tool definition, when a step offers any.
     */
    protected const TOOL_OVERHEAD_CHARS = 200;

    /**
     * The action to take when a request would exceed its budget.
     */
    protected ActionTypes $action = ActionTypes::BLOCK;

    /**
     * The maximum cost of a single run, in US dollars. Null disables this budget.
     */
    protected ?float $maxCostPerRequest;

    /**
     * The maximum cost attributable to a session, in US dollars. Null disables this budget.
     */
    protected ?float $maxCostPerSession;

    /**
     * The maximum cost attributable to a user per day, in US dollars. Null disables this budget.
     */
    protected ?float $maxCostPerUserPerDay;

    /**
     * How many hours a session's spend is tracked for, from its first recorded request.
     */
    protected int $sessionWindowHours;

    /**
     * The output tokens assumed for a step's response, since it is not known until the provider
     * replies.
     */
    protected int $assumedOutputTokens;

    /**
     * Custom or overriding pricing, keyed the same way as the built-in table.
     *
     * @var array<string, array<string, float>>
     */
    protected array $modelPricing;

    /**
     * What to do when no pricing is known for the model a step runs on.
     */
    protected UnknownModelActions $unknownModelAction = UnknownModelActions::ALLOW;

    /**
     * The cache store spend is recorded in. Null uses the default store.
     */
    protected ?string $cacheStore;

    /**
     * Whether to include a short prompt preview in logs.
     */
    protected bool $logPreview = false;

    /**
     * Custom callback for handling a budget breach.
     */
    protected ?Closure $callback;

    /**
     * Resolves the key a session's spend is tracked under.
     *
     * Receives the generation step and returns a stable identifier, or null when the step
     * cannot be attributed to a session. Defaults to the agent's own conversation ID, for an
     * agent that remembers its conversations; otherwise the session budget never matches and is
     * silently skipped.
     */
    protected Closure $sessionKeyResolver;

    /**
     * Resolves the key a user's daily spend is tracked under.
     *
     * Receives the generation step and returns a stable identifier, or null when the step
     * cannot be attributed to a user. Defaults to the agent's conversation participant, falling
     * back to the authenticated user; otherwise the daily budget never matches and is silently
     * skipped.
     */
    protected Closure $userKeyResolver;

    /**
     * Estimates the input tokens a step will send to the provider.
     *
     * Receives the generation step and returns the estimated input token count. Defaults to a
     * character count over the step's full message history, divided by `CHARS_PER_TOKEN`.
     * Supply your own for an exact count from the provider's own tokenizer.
     */
    protected Closure $tokenEstimator;

    /**
     * The spend store backing the session and daily budgets.
     */
    protected SpendStore $spendStore;

    /**
     * Create a new Budget Guard instance.
     *
     * @param string|null                              $action               What to do: 'block', 'log', or 'truncate'.
     * @param float|null                               $maxCostPerRequest    Maximum cost of a single run, in US dollars.
     * @param float|null                               $maxCostPerSession    Maximum cost attributable to a session, in US dollars.
     * @param float|null                               $maxCostPerUserPerDay Maximum cost attributable to a user per day, in US dollars.
     * @param int|null                                 $sessionWindowHours   Hours a session's spend is tracked for.
     * @param int|null                                 $assumedOutputTokens  Output tokens assumed before the provider replies.
     * @param array<string, array<string, float>>|null $modelPricing         Custom or overriding pricing, merged over the built-in table.
     * @param string|null                              $unknownModelAction   What to do for an unpriced model: 'allow' or 'block'.
     * @param string|null                              $cacheStore           The cache store spend is recorded in.
     * @param bool|null                                $logPreview           Whether to include a short prompt preview in logs.
     * @param Closure|null                             $callback             Custom handler for a budget breach.
     * @param Closure|null                             $sessionKeyResolver   Resolves the session budget key from the step.
     * @param Closure|null                             $userKeyResolver      Resolves the daily budget key from the step.
     * @param Closure|null                             $tokenEstimator       Estimates the input tokens a step will send.
     */
    public function __construct(
        ?string $action = null,
        ?float $maxCostPerRequest = null,
        ?float $maxCostPerSession = null,
        ?float $maxCostPerUserPerDay = null,
        ?int $sessionWindowHours = null,
        ?int $assumedOutputTokens = null,
        ?array $modelPricing = null,
        ?string $unknownModelAction = null,
        ?string $cacheStore = null,
        ?bool $logPreview = null,
        ?Closure $callback = null,
        ?Closure $sessionKeyResolver = null,
        ?Closure $userKeyResolver = null,
        ?Closure $tokenEstimator = null,
    ) {
        $config = InterceptConfig::middleware('budget_guard', BudgetGuardDefaults::values());

        $action ??= $config['action'];
        $maxCostPerRequest ??= $config['max_cost_per_request'];
        $maxCostPerSession ??= $config['max_cost_per_session'];
        $maxCostPerUserPerDay ??= $config['max_cost_per_user_per_day'];
        $sessionWindowHours ??= $config['session_window_hours'];
        $assumedOutputTokens ??= $config['assumed_output_tokens'];
        $modelPricing ??= $config['model_pricing'];
        $unknownModelAction ??= $config['unknown_model_action'];
        $cacheStore ??= $config['cache_store'];
        $logPreview ??= $config['log_preview'];

        $this->validateAction($action);
        $this->validateUnknownModelAction($unknownModelAction);
        $this->validatePositive('sessionWindowHours', $sessionWindowHours);
        $this->validatePositive('assumedOutputTokens', $assumedOutputTokens);

        $this->action               = ActionTypes::from($action);
        $this->maxCostPerRequest    = $maxCostPerRequest;
        $this->maxCostPerSession    = $maxCostPerSession;
        $this->maxCostPerUserPerDay = $maxCostPerUserPerDay;
        $this->sessionWindowHours   = $sessionWindowHours;
        $this->assumedOutputTokens  = $assumedOutputTokens;
        $this->modelPricing         = $modelPricing;
        $this->unknownModelAction   = UnknownModelActions::from($unknownModelAction);
        $this->cacheStore           = $cacheStore;
        $this->logPreview           = $logPreview;
        $this->callback             = $callback;

        $this->sessionKeyResolver = $sessionKeyResolver ?? $this->defaultSessionKey(...);
        $this->userKeyResolver    = $userKeyResolver ?? $this->defaultUserKey(...);
        $this->tokenEstimator     = $tokenEstimator ?? $this->estimateInputTokens(...);

        $this->spendStore = new SpendStore($this->cacheStore);
    }

    /**
     * Handle a generation step and enforce the configured budgets before the provider is called.
     *
     * This runs on every step of a run, not only the first, because a multi-step tool-calling
     * loop keeps spending with every round trip. Session and daily spend is recorded from the
     * step's own actual usage once the provider replies, so the next step, and the next run, see
     * an accurate running total.
     *
     * @param PendingStep $step The generation step.
     * @param Closure     $next The next middleware in the pipeline.
     */
    public function handle(PendingStep $step, Closure $next): StepResult
    {
        $rate = ModelPricing::resolve($step->model, [...ModelPricing::rates(), ...$this->modelPricing]);

        if ($rate === null) {
            return $this->handleUnknownModel($step, $next);
        }

        $estimate = $this->estimate($step, $rate);
        $breach   = $this->firstBreach($step, $estimate);

        if ($breach === null) {
            return $this->proceed($step, $next, $rate);
        }

        $this->log($step, $estimate, $breach, blocked: $this->action !== ActionTypes::LOG);

        if ($this->callback !== null) {
            ($this->callback)($step, $breach, $estimate);

            return $this->proceed($step, $next, $rate);
        }

        if ($this->action === ActionTypes::TRUNCATE && $breach->type === BudgetTypes::REQUEST) {
            $truncated = $this->truncate($step, $estimate, $rate);

            if ($truncated !== null) {
                return $this->proceed($truncated, $next, $rate);
            }
        }

        if ($this->action !== ActionTypes::LOG) {
            $this->block($breach);
        }

        return $this->proceed($step, $next, $rate);
    }

    /**
     * Handle a step whose model carries no known pricing.
     *
     * @param PendingStep $step The generation step.
     * @param Closure     $next The next middleware in the pipeline.
     */
    protected function handleUnknownModel(PendingStep $step, Closure $next): StepResult
    {
        Log::warning('Budget Guard has no pricing for the requested model.', [
            ...$this->stepLogContext($step),
            'unknown_model_action' => $this->unknownModelAction->value,
        ]);

        if ($this->unknownModelAction === UnknownModelActions::BLOCK) {
            throw new BudgetGuardException(sprintf(
                'Budget Guard has no pricing for model [%s] and unknown_model_action is "block".',
                $step->model,
            ));
        }

        return $next($step);
    }

    /**
     * Run the next middleware and record the step's actual spend once it resolves.
     *
     * @param PendingStep $step The generation step.
     * @param Closure     $next The next middleware in the pipeline.
     * @param ModelRate   $rate The rate the step's model is priced at.
     */
    protected function proceed(PendingStep $step, Closure $next, ModelRate $rate): StepResult
    {
        $result = $next($step);

        if ($result instanceof StepResponse) {
            $result = new StepResult($result);
        }

        return $result->then(
            fn (StepResponse $response) => $this->recordSpend($step, $response, $rate),
        );
    }

    /**
     * Estimate the cost of running a step.
     *
     * @param PendingStep $step The generation step.
     * @param ModelRate   $rate The rate the step's model is priced at.
     */
    protected function estimate(PendingStep $step, ModelRate $rate): CostEstimate
    {
        $inputTokens = max(0, ($this->tokenEstimator)($step));

        return new CostEstimate(
            inputTokens: $inputTokens,
            outputTokens: $this->assumedOutputTokens,
            cost: $rate->cost($inputTokens, $this->assumedOutputTokens),
            accrued: $rate->costOfUsage($step->usage),
        );
    }

    /**
     * Find the first budget a step's estimate would exceed.
     *
     * Checked in order: the request's own ceiling, then the session, then the user's daily
     * ceiling. Each is independent: a run can breach more than one, but only the first found is
     * reported, since one failing action is enough to stop or log the step.
     *
     * @param PendingStep  $step     The generation step.
     * @param CostEstimate $estimate The step's cost estimate.
     */
    protected function firstBreach(PendingStep $step, CostEstimate $estimate): ?BudgetBreach
    {
        if ($this->maxCostPerRequest !== null && $estimate->projected() > $this->maxCostPerRequest) {
            return new BudgetBreach(
                type: BudgetTypes::REQUEST,
                limit: $this->maxCostPerRequest,
                projected: $estimate->projected(),
                spent: $estimate->accrued,
            );
        }

        $windowSeconds = $this->sessionWindowHours * 3600;

        if (
            $this->maxCostPerSession !== null
            && ($key = $this->resolveKey($this->sessionKeyResolver, $step)) !== null
            && ($breach = $this->windowedBreach(BudgetTypes::SESSION, "budget_guard:session:{$key}", $this->maxCostPerSession, $estimate->cost, $windowSeconds)) !== null
        ) {
            return $breach;
        }

        if (
            $this->maxCostPerUserPerDay !== null
            && ($key = $this->resolveKey($this->userKeyResolver, $step)) !== null
            && ($breach = $this->windowedBreach(BudgetTypes::USER_DAILY, 'budget_guard:daily:'.now()->format('Y-m-d').":{$key}", $this->maxCostPerUserPerDay, $estimate->cost, 86400)) !== null
        ) {
            return $breach;
        }

        return null;
    }

    /**
     * Check a windowed budget against the spend already recorded for its key.
     *
     * @param BudgetTypes $type          The budget being checked.
     * @param string      $key           The cache key the spend is recorded under.
     * @param float       $limit         The configured limit, in US dollars.
     * @param float       $stepCost      The estimated cost of the step about to run.
     * @param int         $windowSeconds The width of the budget's window, in seconds.
     */
    protected function windowedBreach(BudgetTypes $type, string $key, float $limit, float $stepCost, int $windowSeconds): ?BudgetBreach
    {
        $spent     = $this->spendStore->spent($key);
        $projected = $spent + $stepCost;

        if ($projected <= $limit) {
            return null;
        }

        return new BudgetBreach(
            type: $type,
            limit: $limit,
            projected: $projected,
            spent: $spent,
            retryAfter: $this->spendStore->availableIn($key),
        );
    }

    /**
     * Resolve a key from a resolver, guarding against a user-supplied callback that throws.
     *
     * @param Closure     $resolver The resolver to call.
     * @param PendingStep $step     The generation step to resolve a key for.
     */
    protected function resolveKey(Closure $resolver, PendingStep $step): ?string
    {
        $key = $resolver($step);

        return $key === null || $key === '' ? null : $key;
    }

    /**
     * Shrink the newest user message of a step until its estimated cost fits the request budget.
     *
     * Returns null when no prompt short enough would still fit, or when the budget leaves no
     * room for the output alone, so the caller can fall back to blocking.
     *
     * @param PendingStep  $step     The generation step to truncate.
     * @param CostEstimate $estimate The step's cost estimate.
     * @param ModelRate    $rate     The rate the step's model is priced at.
     */
    protected function truncate(PendingStep $step, CostEstimate $estimate, ModelRate $rate): ?PendingStep
    {
        if ($this->maxCostPerRequest === null) {
            return null;
        }

        $outputCost     = $rate->cost(0, $this->assumedOutputTokens);
        $remainingInput = $this->maxCostPerRequest - $estimate->accrued - $outputCost;

        if ($remainingInput <= 0 || $rate->input <= 0) {
            return null;
        }

        $allowedInputTokens = (int) floor($remainingInput * ModelRate::PER_TOKENS / $rate->input);
        $allowedChars       = $allowedInputTokens * self::CHARS_PER_TOKEN;

        $latest = $this->latestUserMessage($step);

        if ($latest === null) {
            return null;
        }

        $otherChars       = $this->historyChars($step) - mb_strlen((string) $latest->content) - $this->toolChars($step);
        $allowedForLatest = $allowedChars - $otherChars;

        if ($allowedForLatest < 20) {
            return null;
        }

        return $this->mapUserMessages(
            $step,
            fn (string $content): string => Str::limit($content, $allowedForLatest, preserveWords: true),
            latestOnly: true,
        );
    }

    /**
     * Estimate the input tokens a step will send to the provider.
     *
     * Counts the characters of the full message history that is resent with every step,
     * including tool call arguments and tool results, plus a flat allowance per declared tool,
     * and converts the total using `CHARS_PER_TOKEN`.
     *
     * @param PendingStep $step The generation step.
     */
    protected function estimateInputTokens(PendingStep $step): int
    {
        $chars = $this->historyChars($step) + $this->toolChars($step);

        return (int) ceil($chars / self::CHARS_PER_TOKEN);
    }

    /**
     * Count the characters of a step's instructions and message history.
     *
     * @param PendingStep $step The generation step.
     */
    protected function historyChars(PendingStep $step): int
    {
        $chars = mb_strlen((string) $step->instructions);

        foreach ($step->messages as $message) {
            $chars += $this->messageChars($message);
        }

        return $chars;
    }

    /**
     * Count the characters of a single message, including any tool calls or results it carries.
     *
     * @param Message $message The message to measure.
     */
    protected function messageChars(Message $message): int
    {
        $chars = mb_strlen((string) $message->content);

        if ($message instanceof AssistantMessage) {
            foreach ($message->toolCalls as $call) {
                $chars += mb_strlen($call->name) + mb_strlen((string) json_encode($call->arguments));
            }
        }

        if ($message instanceof ToolResultMessage) {
            foreach ($message->toolResults as $result) {
                $chars += mb_strlen((string) json_encode($result->result));
            }
        }

        return $chars;
    }

    /**
     * Estimate the characters spent describing a step's declared tools.
     *
     * @param PendingStep $step The generation step.
     */
    protected function toolChars(PendingStep $step): int
    {
        return count($step->tools) * self::TOOL_OVERHEAD_CHARS;
    }

    /**
     * Resolve the default session key: the agent's own conversation ID.
     *
     * @param PendingStep $step The generation step.
     */
    protected function defaultSessionKey(PendingStep $step): ?string
    {
        $agent = $this->agentFor($step);

        return $agent instanceof RemembersConversations ? $agent->currentConversation() : null;
    }

    /**
     * Resolve the default user key: the agent's conversation participant, or the authenticated user.
     *
     * @param PendingStep $step The generation step.
     */
    protected function defaultUserKey(PendingStep $step): ?string
    {
        $agent = $this->agentFor($step);

        if ($agent instanceof RemembersConversations && ($participant = $agent->conversationParticipant()) !== null) {
            return Conversation::participantType($participant).':'.Conversation::participantKey($participant);
        }

        return Auth::hasUser() ? (string) Auth::id() : null;
    }

    /**
     * Record the actual spend of a resolved step against the windowed budgets.
     *
     * @param PendingStep  $step     The generation step.
     * @param StepResponse $response The response of the step.
     * @param ModelRate    $rate     The rate the step's model is priced at.
     */
    protected function recordSpend(PendingStep $step, StepResponse $response, ModelRate $rate): void
    {
        $cost = $rate->costOfUsage($response->usage);

        if ($cost <= 0.0) {
            return;
        }

        if (($key = $this->resolveKey($this->sessionKeyResolver, $step)) !== null) {
            $this->spendStore->recordSpend("budget_guard:session:{$key}", $cost, $this->sessionWindowHours * 3600);
        }

        if (($key = $this->resolveKey($this->userKeyResolver, $step)) !== null) {
            $this->spendStore->recordSpend('budget_guard:daily:'.now()->format('Y-m-d').":{$key}", $cost, 86400);
        }
    }

    /**
     * Block the run before the provider is called.
     *
     * The message names the breached budget and the dollar amounts, but never the session or
     * user key spend was tracked against, so it stays safe to log and to surface.
     *
     * @param BudgetBreach $breach The breach that caused the block.
     *
     * @throws BudgetGuardException
     */
    protected function block(BudgetBreach $breach): never
    {
        throw new BudgetGuardException(
            sprintf('Request would exceed its configured budget [%s].', $breach->reference()),
            retryAfter: $breach->retryAfter,
        );
    }

    /**
     * Log a budget breach safely.
     *
     * @param PendingStep  $step     The generation step.
     * @param CostEstimate $estimate The step's cost estimate.
     * @param BudgetBreach $breach   The breach found.
     * @param bool         $blocked  Whether the run is being stopped.
     */
    protected function log(PendingStep $step, CostEstimate $estimate, BudgetBreach $breach, bool $blocked): void
    {
        $context = [
            ...$this->stepLogContext($step),
            'estimate'  => $estimate->toArray(),
            'breach'    => $breach->toArray(),
            'action'    => $this->action->value,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($this->logPreview && ($latest = $this->latestUserMessage($step)) !== null) {
            $context['preview'] = str($latest->content)->limit(300)->toString();
        }

        Log::warning(
            $blocked ? 'Budget Guard blocked a request that would exceed its budget.' : 'Budget Guard logged a request that would exceed its budget.',
            $context,
        );
    }

    /**
     * Validate the provided action.
     *
     * @param string $action The action to validate.
     */
    protected function validateAction(string $action): void
    {
        if (! in_array($action, array_column(ActionTypes::cases(), 'value'), true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unsupported budget guard action: %s. Must be one of: %s.',
                    $action,
                    implode(', ', array_column(ActionTypes::cases(), 'value')),
                )
            );
        }
    }

    /**
     * Validate the provided unknown model action.
     *
     * @param string $action The action to validate.
     */
    protected function validateUnknownModelAction(string $action): void
    {
        if (! in_array($action, array_column(UnknownModelActions::cases(), 'value'), true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unsupported unknown model action: %s. Must be one of: %s.',
                    $action,
                    implode(', ', array_column(UnknownModelActions::cases(), 'value')),
                )
            );
        }
    }

    /**
     * Validate that a numeric option is positive.
     *
     * @param string $name  The option name, for the exception message.
     * @param int    $value The value to validate.
     */
    protected function validatePositive(string $name, int $value): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("The budget guard [{$name}] option must be greater than zero.");
        }
    }
}
