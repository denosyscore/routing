<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Denosys\Routing\Middleware\MiddlewareManager;

class CacheBuilder
{
    public function buildRouteCache(RouteCollectionInterface $routeCollection, string $cacheFile): void
    {
        $routeTrie = new RouteTrie();
        $routeCache = [];

        foreach ($routeCollection->all() as $route) {
            foreach ($route->getMethods() as $method) {
                $routeTrie->addRoute($method, $route->getPattern(), $route);
            }
        }

        $commonPaths = $this->getCommonPaths($routeCollection);
        
        foreach ($commonPaths as $method => $paths) {
            foreach ($paths as $path) {
                $result = $routeTrie->findRoute($method, $path);
                if ($result !== null) {
                    $routeCache["route_{$method}_{$path}"] = $result;
                }
            }
        }

        $this->writePHPCache($cacheFile, $routeCache);
    }

    public function buildMiddlewareCache(array $middlewareList, ?MiddlewareManager $middlewareManager, string $cacheFile): void
    {
        $middlewareCache = [];
        $manager = $middlewareManager ?? new MiddlewareManager();

        foreach ($middlewareList as $middleware) {
            try {
                // Only cache simple middleware strings without parameters
                if (is_string($middleware) && !str_contains($middleware, ':')) {
                    $resolved = $manager->resolve($middleware);
                    $middlewareCache["middleware_" . md5($middleware)] = $resolved;
                }
            } catch (\Exception $e) {
                // Skip middleware that can't be resolved during cache build
                continue;
            }
        }

        $this->writePHPCache($cacheFile, $middlewareCache);
    }

    private function writePHPCache(string $cacheFile, array $data): void
    {
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $export = var_export($data, true);
        $content = "<?php\n\nreturn {$export};\n";
        
        file_put_contents($cacheFile, $content, LOCK_EX);
    }

    private function getCommonPaths(RouteCollectionInterface $routeCollection): array
    {
        $paths = [];
        
        foreach ($routeCollection->all() as $route) {
            foreach ($route->getMethods() as $method) {
                $pattern = $route->getPattern();
                
                if (!isset($paths[$method])) {
                    $paths[$method] = [];
                }
                
                $paths[$method][] = $pattern;
                
                // For patterns with parameters, generate some example paths
                if (str_contains($pattern, '{')) {
                    $examplePaths = $this->generateExamplePaths($pattern);
                    $paths[$method] = array_merge($paths[$method], $examplePaths);
                }
            }
        }

        return $paths;
    }

    private function generateExamplePaths(string $pattern): array
    {
        $examples = [];
        
        // Replace parameters with example values
        $path = preg_replace('/\{[^}]+\}/', '123', $pattern);
        $examples[] = $path;
        
        $path = preg_replace('/\{[^}]+\}/', 'example', $pattern);
        $examples[] = $path;
        
        return array_unique($examples);
    }
}
