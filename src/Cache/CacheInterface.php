<?php

declare(strict_types=1);

namespace Denosys\Routing\Cache;

interface CacheInterface
{
    /**
     * Retrieve an item from the cache by key.
     */
    public function get(string $key): mixed;

    /**
     * Store an item in the cache.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Determine if an item exists in the cache.
     */
    public function has(string $key): bool;

    /**
     * Remove all items from the cache.
     */
    public function clear(): void;
}
