<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\Trait;

/**
 * Trait for request-scoped API response caching
 *
 * Reusability: 100%
 * Usage: Add to API client classes to enable caching
 */
trait CachableApiTrait
{
    /**
     * Request-scoped cache storage
     *
     * @var array<string, mixed>
     */
    private array $apiResponseCache = [];

    /**
     * Cache TTL storage
     *
     * @var array<string, int>
     */
    private array $apiResponseCacheTtl = [];

    /**
     * Cache timestamps
     *
     * @var array<string, int>
     */
    private array $apiResponseCacheTimestamps = [];

    /**
     * @inheritDoc
     */
    public function cacheApiResponse(string $key, mixed $data, ?int $ttl = null): void
    {
        $this->apiResponseCache[$key] = $data;
        $this->apiResponseCacheTimestamps[$key] = time();

        if ($ttl !== null) {
            $this->apiResponseCacheTtl[$key] = $ttl;
        }
    }

    /**
     * @inheritDoc
     */
    public function getCachedResponse(string $key): mixed
    {
        if (!$this->hasCachedResponse($key)) {
            return null;
        }

        // Check TTL if set
        if (isset($this->apiResponseCacheTtl[$key])) {
            $age = time() - $this->apiResponseCacheTimestamps[$key];
            if ($age > $this->apiResponseCacheTtl[$key]) {
                $this->invalidateCache($key);
                return null;
            }
        }

        return $this->apiResponseCache[$key];
    }

    /**
     * @inheritDoc
     */
    public function hasCachedResponse(string $key): bool
    {
        return isset($this->apiResponseCache[$key]);
    }

    /**
     * @inheritDoc
     */
    public function invalidateCache(string $key): void
    {
        unset(
            $this->apiResponseCache[$key],
            $this->apiResponseCacheTtl[$key],
            $this->apiResponseCacheTimestamps[$key]
        );
    }

    /**
     * @inheritDoc
     */
    public function clearCache(): void
    {
        $this->apiResponseCache = [];
        $this->apiResponseCacheTtl = [];
        $this->apiResponseCacheTimestamps = [];
    }

    /**
     * Get or cache API response
     *
     * Helper method to simplify cache-or-fetch pattern
     *
     * @param string $key Cache key
     * @param callable $fetcher Callback to fetch data if not cached
     * @param int|null $ttl Cache TTL in seconds
     * @return mixed Cached or fetched data
     */
    protected function getCachedOrFetch(string $key, callable $fetcher, ?int $ttl = null): mixed
    {
        $cached = $this->getCachedResponse($key);
        if ($cached !== null) {
            return $cached;
        }

        $data = $fetcher();
        $this->cacheApiResponse($key, $data, $ttl);

        return $data;
    }
}
