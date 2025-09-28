<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

use RuntimeException;

class RouteCache implements RouteCacheInterface
{
    public function cacheRoutes(array $routes, string $cacheFilePath): bool
    {
        $cacheDir = dirname($cacheFilePath);
        if (!is_dir($cacheDir)) {
            // Recursively check if we can create the directory path
            $pathParts = explode('/', trim($cacheDir, '/'));
            $currentPath = '';
            
            foreach ($pathParts as $part) {
                $currentPath .= '/' . $part;
                
                if (!is_dir($currentPath)) {
                    $parentPath = dirname($currentPath);
                    
                    // Check if parent exists and is writable
                    if (!is_dir($parentPath) || !is_writable($parentPath)) {
                        throw new RuntimeException("Failed to create cache directory: {$cacheDir}");
                    }
                }
            }
            
            if (!mkdir($cacheDir, 0755, true)) {
                throw new RuntimeException("Failed to create cache directory: {$cacheDir}");
            }
        }

        $cacheData = [
            'timestamp' => time(),
            'routes' => $routes,
            'php_version' => PHP_VERSION,
            'generator' => 'DenosysCore Routing v' . $this->getVersion()
        ];

        $cacheContent = "<?php\n\n// Auto-generated route cache file\n// Generated at: " . date('Y-m-d H:i:s') . "\n\nreturn " . var_export($cacheData, true) . ";\n";

        $result = file_put_contents($cacheFilePath, $cacheContent, LOCK_EX);
        
        if ($result === false) {
            throw new RuntimeException("Failed to write route cache to: {$cacheFilePath}");
        }

        return true;
    }

    public function loadCachedRoutes(string $cacheFilePath): ?array
    {
        if (!file_exists($cacheFilePath) || !is_readable($cacheFilePath)) {
            return null;
        }

        try {
            $cacheData = include $cacheFilePath;
            
            if (!is_array($cacheData) || !isset($cacheData['routes']) || !isset($cacheData['timestamp'])) {
                return null;
            }

            return $cacheData['routes'];
        } catch (\Throwable $e) {
            // Cache file is corrupted or invalid
            return null;
        }
    }

    public function isCacheValid(string $cacheFilePath, array $sourceFiles = []): bool
    {
        if (!file_exists($cacheFilePath)) {
            return false;
        }

        $cacheTime = filemtime($cacheFilePath);
        if ($cacheTime === false) {
            return false;
        }

        // Check if any source files are newer than cache
        foreach ($sourceFiles as $sourceFile) {
            if (!file_exists($sourceFile)) {
                continue;
            }

            $sourceTime = filemtime($sourceFile);
            if ($sourceTime !== false && $sourceTime > $cacheTime) {
                return false;
            }
        }

        return true;
    }

    public function clearCache(string $cacheFilePath): bool
    {
        if (!file_exists($cacheFilePath)) {
            return true;
        }

        return unlink($cacheFilePath);
    }

    private function getVersion(): string
    {
        // In a real implementation, this could read from composer.json or a version file
        return '1.0.0';
    }
}
