<?php

declare(strict_types=1);

namespace Denosys\Routing;

class CacheBuilder
{
    public function buildRouteCache(RouteCollectionInterface $routeCollection, string $cacheFile): void
    {
        $routeManager = new RouteManager();
        $routeCache = [];

        /** @var RouteInterface $route */
        foreach ($routeCollection->all() as $route) {
            foreach ($route->getMethods() as $method) {
                $routeManager->addRoute($method, $route->getPattern(), $route);
            }
        }

        $commonPaths = $this->getCommonPaths($routeCollection);

        foreach ($commonPaths as $method => $paths) {
            foreach ($paths as $path) {
                $result = $routeManager->findRoute($method, $path);

                if ($result !== null) {
                    [$matchedRoute, $params] = $result;
                    $routeCache["route_{$method}_{$path}"] = $this->extractRouteData($matchedRoute, $params);
                }
            }
        }

        $this->writePHPCache($cacheFile, $routeCache);
    }

    private function extractRouteData(RouteInterface $route, array $params = []): array
    {
        return [
            'pattern' => $route->getPattern(),
            'methods' => $route->getMethods(),
            'handler' => $this->serializeHandler($route->getHandler()),
            'name' => $route->getName(),
            'constraints' => $route->getConstraints(),
            'params' => $params,
        ];
    }

    private function serializeHandler($handler): array|string|null
    {
        if (is_string($handler) || is_array($handler)) {
            return $handler;
        }

        // For closures or objects, you might need special handling
        if ($handler instanceof \Closure) {
            // You can't cache closures with var_export
            return '__closure__';
        }

        return null;
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
        $path = preg_replace('/\{[^}]+}/', '123', $pattern);
        $examples[] = $path;

        $path = preg_replace('/\{[^}]+}/', 'example', $pattern);
        $examples[] = $path;

        return array_unique($examples);
    }
}
