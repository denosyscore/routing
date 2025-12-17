<?php

declare(strict_types=1);

namespace Denosys\Routing\Cache;

/**
 * Null Object pattern implementation for disabled caching.
 */
class NullCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        return null;
    }

    public function set(string $key, mixed $value): void
    {
        // No-op
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function clear(): void
    {
        // No-op
    }
}
