<?php

declare(strict_types=1);

namespace Denosys\Routing\Factories;

use Denosys\Routing\Cache\CacheInterface;
use Denosys\Routing\Cache\FileCache;
use Denosys\Routing\Cache\ApcuCache;
use Denosys\Routing\Cache\NullCache;

/**
 * Factory for creating cache instances.
 * Provides centralized cache creation with support for multiple cache types.
 */
class CacheFactory
{
    /**
     * Create a file-based cache.
     */
    public function createFileCache(string $cacheFile): CacheInterface
    {
        return new FileCache($cacheFile);
    }

    /**
     * Create an APCu-based cache.
     *
     * @throws \RuntimeException If APCu extension is not available
     */
    public function createApcuCache(string $prefix = 'route_'): CacheInterface
    {
        if (!extension_loaded('apcu')) {
            throw new \RuntimeException('APCu extension is not available');
        }

        return new ApcuCache($prefix);
    }

    /**
     * Create a null cache (no-op cache).
     */
    public function createNullCache(): CacheInterface
    {
        return new NullCache();
    }

    /**
     * Create cache from configuration array.
     *
     * @param array $config Configuration with 'type' and type-specific options
     * @throws \InvalidArgumentException If cache type is invalid
     */
    public function createFromConfig(array $config): CacheInterface
    {
        $type = $config['type'] ?? 'null';

        return match ($type) {
            'file' => $this->createFileCache($config['path'] ?? throw new \InvalidArgumentException('File cache requires "path" in config')),
            'apcu' => $this->createApcuCache($config['prefix'] ?? 'route_'),
            'null' => $this->createNullCache(),
            default => throw new \InvalidArgumentException("Invalid cache type: {$type}")
        };
    }

    /**
     * Create cache with automatic type detection based on environment.
     *
     * @param string|null $fallbackPath Path for file cache if APCu is not available
     */
    public function createAuto(?string $fallbackPath = null): CacheInterface
    {
        // Prefer APCu if available
        if (extension_loaded('apcu')) {
            return $this->createApcuCache();
        }

        // Fall back to file cache if path is provided
        if ($fallbackPath !== null) {
            return $this->createFileCache($fallbackPath);
        }

        // Otherwise use null cache
        return $this->createNullCache();
    }
}
