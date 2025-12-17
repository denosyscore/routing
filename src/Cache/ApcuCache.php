<?php

declare(strict_types=1);

namespace Denosys\Routing\Cache;

class ApcuCache implements CacheInterface
{
    public function __construct(
        private readonly string $prefix = 'denosys_routing_',
        private readonly int $ttl = 3600
    ) {
    }

    public function get(string $key): mixed
    {
        if (!function_exists('apcu_fetch')) {
            return null;
        }

        $fullKey = $this->prefix . $key;
        $value = apcu_fetch($fullKey, $success);

        return $success ? $value : null;
    }

    public function set(string $key, mixed $value): void
    {
        if (!function_exists('apcu_store')) {
            return;
        }

        $fullKey = $this->prefix . $key;
        apcu_store($fullKey, $value, $this->ttl);
    }

    public function has(string $key): bool
    {
        if (!function_exists('apcu_exists')) {
            return false;
        }

        $fullKey = $this->prefix . $key;
        return apcu_exists($fullKey);
    }

    public function clear(): void
    {
        if (!function_exists('apcu_cache_info') || !function_exists('apcu_delete')) {
            return;
        }

        // Clear all keys with our prefix
        $cache = apcu_cache_info();

        if (!isset($cache['cache_list'])) {
            return;
        }

        foreach ($cache['cache_list'] as $entry) {
            if (isset($entry['info']) && str_starts_with($entry['info'], $this->prefix)) {
                apcu_delete($entry['info']);
            }
        }
    }
}
