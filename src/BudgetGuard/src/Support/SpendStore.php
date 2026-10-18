<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\BudgetGuard\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\InteractsWithTime;

/**
 * Records spend against a budget key in the cache.
 *
 * Session and daily budgets span runs, so they cannot live on the middleware instance or in a
 * per-request ledger. Dollars are tracked as integer micro-dollars, because the cache drivers
 * that support atomic increments (database, Redis, Memcached) do not all support atomic
 * increments of a float. The windowed keys follow the same timer-key pattern as Laravel's own
 * `RateLimiter`, so a window's expiry can be read back for a `Retry-After` header.
 */
final class SpendStore
{
    use InteractsWithTime;

    /**
     * The number of micro-dollars in one US dollar.
     */
    private const MICRO_DOLLARS = 1_000_000;

    /**
     * Create a new spend store.
     *
     * @param string|null $store The cache store to use. Null uses the default store.
     */
    public function __construct(private readonly ?string $store = null)
    {
        //
    }

    /**
     * Get the spend recorded against a key, in US dollars.
     *
     * @param string $key The budget key to read.
     */
    public function spent(string $key): float
    {
        return ((int) $this->cache()->get($key, 0)) / self::MICRO_DOLLARS;
    }

    /**
     * Record spend against a key and get the new total, in US dollars.
     *
     * The window starts on the key's first write and lasts `$windowSeconds`, so a daily budget
     * resets a day after the request that first spends against it rather than at midnight.
     * A null window keeps the key forever.
     *
     * @param string   $key           The budget key to record against.
     * @param float    $amount        The amount to add, in US dollars.
     * @param int|null $windowSeconds The number of seconds the key lives for.
     */
    public function recordSpend(string $key, float $amount, ?int $windowSeconds = null): float
    {
        $cache  = $this->cache();
        $micros = (int) round($amount * self::MICRO_DOLLARS);

        if ($windowSeconds !== null) {
            $cache->add("{$key}:expires_at", $this->availableAt($windowSeconds), $windowSeconds);
        }

        $added = $cache->add($key, 0, $windowSeconds);
        $total = (int) $cache->increment($key, $micros);

        // The key may have expired between the add above and the increment, in which case the
        // increment recreated it without a TTL. Reinstating one here keeps the window honest.
        if (! $added && $total === $micros && $windowSeconds !== null) {
            $cache->put($key, $total, $windowSeconds);
        }

        return $total / self::MICRO_DOLLARS;
    }

    /**
     * Get the seconds until a windowed key resets, or null when it carries no window.
     *
     * @param string $key The budget key to read.
     */
    public function availableIn(string $key): ?int
    {
        $expiresAt = $this->cache()->get("{$key}:expires_at");

        if ($expiresAt === null) {
            return null;
        }

        return max(0, (int) $expiresAt - $this->currentTime());
    }

    /**
     * Resolve the cache repository.
     */
    private function cache(): Repository
    {
        return Cache::store($this->store);
    }
}
