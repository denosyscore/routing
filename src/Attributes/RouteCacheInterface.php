<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

interface RouteCacheInterface
{
    public function cacheRoutes(array $routes, string $cacheFilePath): bool;
    
    public function loadCachedRoutes(string $cacheFilePath): ?array;
    
    public function isCacheValid(string $cacheFilePath, array $sourceFiles = []): bool;
    
    public function clearCache(string $cacheFilePath): bool;
}