<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;

class AttributeRouteScanner
{
    public function scanClass(string $className): array
    {
        if (!class_exists($className)) {
            throw new InvalidArgumentException("Controller class {$className} does not exist");
        }

        $reflectionClass = new ReflectionClass($className);
        $routes = [];

        // Get class-level attributes
        $classRouteGroup = $this->getRouteGroupFromClass($reflectionClass);
        $classMiddleware = $this->getMiddlewareFromClass($reflectionClass);
        $classResource = $this->getResourceFromClass($reflectionClass);

        // If class has Resource attribute, generate resource routes
        if ($classResource) {
            $routes = array_merge($routes, $this->generateResourceRoutes($className, $classResource));
        }

        // Scan individual methods for route attributes
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodRoutes = $this->scanMethod($className, $method, $classRouteGroup, $classMiddleware);
            $routes = array_merge($routes, $methodRoutes);
        }

        return $routes;
    }

    public function scanClasses(array $classNames): array
    {
        $allRoutes = [];
        
        foreach ($classNames as $className) {
            $routes = $this->scanClass($className);
            $allRoutes = array_merge($allRoutes, $routes);
        }

        return $allRoutes;
    }

    public function scanDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException("Directory {$directory} does not exist");
        }

        $allRoutes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $classes = $this->extractClassesFromFile($file->getPathname());
                foreach ($classes as $className) {
                    if ($this->isControllerClass($className)) {
                        $routes = $this->scanClass($className);
                        $allRoutes = array_merge($allRoutes, $routes);
                    }
                }
            }
        }

        return $allRoutes;
    }

    private function extractClassesFromFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $classes = [];
        
        // Extract namespace
        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]) . '\\';
        }
        
        // Extract class names
        if (preg_match_all('/class\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/i', $content, $matches)) {
            foreach ($matches[1] as $className) {
                $fullClassName = $namespace . $className;
                if (class_exists($fullClassName)) {
                    $classes[] = $fullClassName;
                }
            }
        }
        
        return $classes;
    }

    private function isControllerClass(string $className): bool
    {
        $reflectionClass = new ReflectionClass($className);
        
        // Check if class has any route attributes
        if (!empty($reflectionClass->getAttributes(RouteGroup::class)) ||
            !empty($reflectionClass->getAttributes(Resource::class)) ||
            !empty($reflectionClass->getAttributes(ApiResource::class))) {
            return true;
        }
        
        // Check if any methods have route attributes
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes() as $attribute) {
                $attributeInstance = $attribute->newInstance();
                if ($attributeInstance instanceof Route) {
                    return true;
                }
            }
        }
        
        return false;
    }

    private function scanMethod(string $className, ReflectionMethod $method, ?RouteGroup $classRouteGroup, array $classMiddleware): array
    {
        $routes = [];
        $methodAttributes = $method->getAttributes();

        foreach ($methodAttributes as $attribute) {
            $attributeInstance = $attribute->newInstance();

            if ($attributeInstance instanceof Route) {
                $routes[] = $this->createRouteFromAttribute(
                    $className,
                    $method->getName(),
                    $attributeInstance,
                    $classRouteGroup,
                    $classMiddleware,
                    $this->getMiddlewareFromMethod($method)
                );
            }
        }

        return $routes;
    }

    private function getRouteGroupFromClass(ReflectionClass $class): ?RouteGroup
    {
        $attributes = $class->getAttributes(RouteGroup::class);
        return !empty($attributes) ? $attributes[0]->newInstance() : null;
    }

    private function getMiddlewareFromClass(ReflectionClass $class): array
    {
        $middleware = [];
        $attributes = $class->getAttributes(Middleware::class);
        
        foreach ($attributes as $attribute) {
            $middlewareInstance = $attribute->newInstance();
            $middleware = array_merge($middleware, $middlewareInstance->getMiddleware());
        }

        return $middleware;
    }

    private function getMiddlewareFromMethod(ReflectionMethod $method): array
    {
        $middleware = [];
        $middlewareExcept = [];
        $attributes = $method->getAttributes(Middleware::class);
        
        foreach ($attributes as $attribute) {
            $middlewareInstance = $attribute->newInstance();
            $middleware = array_merge($middleware, $middlewareInstance->getMiddleware());
            if (!empty($middlewareInstance->getExcept())) {
                $middlewareExcept = array_merge($middlewareExcept, $middlewareInstance->getExcept());
            }
        }

        return ['middleware' => $middleware, 'except' => $middlewareExcept];
    }

    private function getResourceFromClass(ReflectionClass $class): ?Resource
    {
        $attributes = $class->getAttributes(Resource::class);
        if (!empty($attributes)) {
            return $attributes[0]->newInstance();
        }

        $attributes = $class->getAttributes(ApiResource::class);
        return !empty($attributes) ? $attributes[0]->newInstance() : null;
    }

    private function generateResourceRoutes(string $className, Resource $resource): array
    {
        $routes = [];
        $actions = $resource->getActions();

        foreach ($actions as $actionName => $actionConfig) {
            $path = '/' . $resource->getName() . $actionConfig['path'];
            
            $methods = [$actionConfig['method']];
            if ($actionConfig['method'] === 'GET') {
                $methods[] = 'HEAD';
            }
            
            $routes[] = [
                'methods' => $methods,
                'path' => $path,
                'name' => $resource->getName() . '.' . $actionName,
                'action' => [$className, $actionName],
                'middleware' => $resource->getMiddleware(),
                'where' => [],
                'middlewareExcept' => []
            ];
        }

        return $routes;
    }

    private function createRouteFromAttribute(
        string $className,
        string $methodName,
        Route $routeAttribute,
        ?RouteGroup $classRouteGroup,
        array $classMiddleware,
        array $methodMiddleware
    ): array {
        // Build path
        $path = $routeAttribute->getPath();
        if ($classRouteGroup) {
            $path = rtrim($classRouteGroup->getPrefix(), '/') . '/' . ltrim($path, '/');
        }

        // Build name
        $name = $routeAttribute->getName();
        if ($classRouteGroup && $classRouteGroup->getName() && $name) {
            $name = $classRouteGroup->getName() . '.' . $name;
        }

        // Combine middleware
        $middleware = array_merge(
            $classMiddleware,
            $classRouteGroup ? $classRouteGroup->getMiddleware() : [],
            $routeAttribute->getMiddleware(),
            $methodMiddleware['middleware'] ?? []
        );

        return [
            'methods' => $routeAttribute->getMethods(),
            'path' => $path,
            'name' => $name,
            'action' => [$className, $methodName],
            'middleware' => array_unique($middleware),
            'where' => $routeAttribute->getWhere(),
            'middlewareExcept' => $methodMiddleware['except'] ?? []
        ];
    }
}
