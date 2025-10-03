<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Denosys\Routing\Attributes\AttributeRouteScanner;

class Router implements RouterInterface
{
    use HasRouteMethods;

    public static array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

    protected Dispatcher $dispatcher;

    protected array $pendingMiddleware = [];

    protected ?string $cachePath = null;

    public function __construct(
        protected ?ContainerInterface $container = null,
        protected ?RouteCollectionInterface $routeCollection = null,
        protected ?RouteHandlerResolverInterface $routeHandlerResolver = null,
        protected ?RouteManagerInterface $routeManager = null
    ) {
        $this->routeHandlerResolver = $routeHandlerResolver ?? new RouteHandlerResolver($this->container);
        $this->routeCollection = $routeCollection ?? new RouteCollection($this->routeHandlerResolver);
        $this->routeManager = $routeManager ?? new RouteManager();
        $this->dispatcher = new Dispatcher(
            routeCollection: $this->routeCollection,
            routeManager: $this->routeManager,
            container: $this->container
        );
    }

    public function addRoute(string|array $methods, string $pattern, Closure|array|string $handler): RouteInterface
    {
        $route = $this->routeCollection->add($methods, $pattern, $handler);

        foreach ($this->pendingMiddleware as $middlewareItem) {
            $route->middleware($middlewareItem);
        }

        $this->pendingMiddleware = [];

        return $route;
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {   
        return $this->dispatcher->dispatch($request);
    }

    public function group(string $prefix, Closure $callback): RouteGroupInterface
    {
        $routeGroup = new RouteGroup($prefix, $this, $this->container);

        foreach ($this->pendingMiddleware as $middlewareItem) {
            $routeGroup->addGroupMiddleware($middlewareItem);
        }

        $this->pendingMiddleware = [];

        $callback($routeGroup);

        $routeGroup->markCallbackFinished();

        return $routeGroup;
    }

    public function middleware(string|array $middleware): static
    {
        $middlewares = is_array($middleware) ? $middleware : [$middleware];
        foreach ($middlewares as $mw) {
            $this->pendingMiddleware[] = $mw;
        }
        return $this;
    }

    public function getRouteCollection(): RouteCollectionInterface
    {
        return $this->routeCollection;
    }

    public function setCachePath(string $cachePath): static
    {
        $this->cachePath = $cachePath;
        return $this;
    }

    public function enableRouteCache(?string $cacheFile = null): static
    {
        $file = $cacheFile ?? $this->cachePath;

        if (!$file) {
            throw new \InvalidArgumentException('Cache file path must be provided');
        }

        // Wrap the current route manager with caching decorator
        $cache = new Cache($file);
        $cachedManager = new CachedRouteMatcher($this->routeManager, $cache);

        // Update the dispatcher with the cached manager
        $this->dispatcher = new Dispatcher(
            routeCollection: $this->routeCollection,
            routeManager: $cachedManager,
            container: $this->container
        );

        return $this;
    }

    public function getCachePath(): ?string
    {
        return $this->cachePath;
    }

    public function loadAttributeRoutes(array $controllerClasses, ?string $cacheFilePath = null): static
    {
        $cacheFile = $cacheFilePath ?? $this->cachePath;
        
        if ($cacheFile && file_exists($cacheFile)) {
            return $this->loadAttributeRoutesFromCache($cacheFile);
        }
        
        $scanner = new AttributeRouteScanner();
        
        foreach ($controllerClasses as $controllerClass) {
            $routes = $scanner->scanClass($controllerClass);
            $this->registerRoutesFromData($routes);
        }
        
        return $this;
    }

    private function registerRoutesFromData(array $routes): void
    {
        foreach ($routes as $routeData) {
            $route = $this->addRoute(
                $routeData['methods'],
                $routeData['path'],
                $routeData['action']
            );
            
            if ($routeData['name']) {
                $route->name($routeData['name']);
            }
            
            foreach ($routeData['where'] as $param => $pattern) {
                $route->where($param, $pattern);
            }
            
            foreach ($routeData['middleware'] as $middleware) {
                $route->middleware($middleware);
            }
        }
    }

    public function loadAttributeRoutesFromDirectory(string $directory, ?string $cacheFilePath = null): static
    {
        $cacheFile = $cacheFilePath ?? $this->cachePath;
        
        if ($cacheFile && file_exists($cacheFile)) {
            return $this->loadAttributeRoutesFromCache($cacheFile);
        }
        
        $scanner = new AttributeRouteScanner();
        $routes = $scanner->scanDirectory($directory);
        $this->registerRoutesFromData($routes);
        
        return $this;
    }

    public function loadAttributeRoutesFromCache(string $cacheFilePath): static
    {
        $scanner = new AttributeRouteScanner();
        $routes = $scanner->loadCachedRoutes($cacheFilePath);
        
        if ($routes === null) {
            throw new \RuntimeException("Failed to load routes from cache file: {$cacheFilePath}");
        }
        
        $this->registerRoutesFromData($routes);
        
        return $this;
    }

    public function cacheAttributeRoutes(array $controllerClasses, ?string $cacheFilePath = null): static
    {
        $cacheFile = $cacheFilePath ?? $this->cachePath;
        
        if (!$cacheFile) {
            throw new \InvalidArgumentException('Cache file path must be provided either as parameter or via setCachePath()');
        }
        
        $scanner = new AttributeRouteScanner();
        $allRoutes = [];
        
        foreach ($controllerClasses as $controllerClass) {
            $routes = $scanner->scanClass($controllerClass);
            $allRoutes = array_merge($allRoutes, $routes);
        }
        
        $scanner->cacheRoutes($allRoutes, $cacheFile);
        
        return $this;
    }

    public function cacheAttributeRoutesFromDirectory(string $directory, ?string $cacheFilePath = null): static
    {
        $cacheFile = $cacheFilePath ?? $this->cachePath;
        
        if (!$cacheFile) {
            throw new \InvalidArgumentException('Cache file path must be provided either as parameter or via setCachePath()');
        }
        
        $scanner = new AttributeRouteScanner();
        $routes = $scanner->scanDirectory($directory);
        $scanner->cacheRoutes($routes, $cacheFile);
        
        return $this;
    }

    public function clearAttributeRoutesCache(?string $cacheFilePath = null): static
    {
        $cacheFile = $cacheFilePath ?? $this->cachePath;
        
        if (!$cacheFile) {
            throw new \InvalidArgumentException('Cache file path must be provided either as parameter or via setCachePath()');
        }
        
        $scanner = new AttributeRouteScanner();
        $scanner->clearCache($cacheFile);
        
        return $this;
    }

    public function getUrlGenerator(string $baseUrl = ''): UrlGeneratorInterface
    {
        $urlGenerator = new UrlGenerator($this->routeCollection);
        
        if ($baseUrl) {
            $urlGenerator->setBaseUrl($baseUrl);
        }
        
        return $urlGenerator;
    }
}
