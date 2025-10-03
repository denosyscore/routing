<?php

declare(strict_types=1);

use Denosys\Routing\CachedRouteMatcher;
use Denosys\Routing\Cache;
use Denosys\Routing\RouteManager;
use Denosys\Routing\Route;
use Denosys\Routing\RouteHandlerResolver;

it('can create CachedRouteMatcher', function () {
    $manager = new RouteManager();
    $cache = new Cache();
    $cachedMatcher = new CachedRouteMatcher($manager, $cache);

    expect($cachedMatcher)->toBeInstanceOf(CachedRouteMatcher::class);
});

it('can find route through cached matcher', function () {
    $manager = new RouteManager();
    $cachedMatcher = new CachedRouteMatcher($manager);

    $resolver = new RouteHandlerResolver();
    $route = new Route(['GET'], '/test', fn() => 'test', $resolver);
    $cachedMatcher->addRoute('GET', '/test', $route);

    $result = $cachedMatcher->findRoute('GET', '/test');

    expect($result)->not->toBeNull();
});

it('uses cache for repeated lookups', function () {
    // Use in-memory cache to avoid serialization issues with closures
    $cache = new Cache(); // No file path = in-memory only
    $manager = new RouteManager();
    $cachedMatcher = new CachedRouteMatcher($manager, $cache);

    $resolver = new RouteHandlerResolver();
    $route = new Route(['GET'], '/users/{id}', fn() => 'user', $resolver);
    $cachedMatcher->addRoute('GET', '/users/{id}', $route);

    // First call - will cache in memory
    $result1 = $cachedMatcher->findRoute('GET', '/users/123');
    expect($result1)->not->toBeNull();

    // Second call - should use in-memory cache
    $result2 = $cachedMatcher->findRoute('GET', '/users/123');
    expect($result2)->toBe($result1);
});

it('caches null results to avoid repeated lookups', function () {
    $cache = new Cache();
    $manager = new RouteManager();
    $cachedMatcher = new CachedRouteMatcher($manager, $cache);

    // First call for non-existent route
    $result1 = $cachedMatcher->findRoute('GET', '/non-existent');
    expect($result1)->toBeNull();

    // Second call should still be null (but cached)
    $result2 = $cachedMatcher->findRoute('GET', '/non-existent');
    expect($result2)->toBeNull();
});

it('works without cache when cache is null', function () {
    $manager = new RouteManager();
    $cachedMatcher = new CachedRouteMatcher($manager, null);

    $resolver = new RouteHandlerResolver();
    $route = new Route(['GET'], '/test', fn() => 'test', $resolver);
    $cachedMatcher->addRoute('GET', '/test', $route);

    $result = $cachedMatcher->findRoute('GET', '/test');
    expect($result)->not->toBeNull();
});

it('delegates addRoute to underlying manager', function () {
    $manager = new RouteManager();
    $cachedMatcher = new CachedRouteMatcher($manager);

    $resolver = new RouteHandlerResolver();
    $route = new Route(['POST'], '/users', fn() => 'create', $resolver);

    $cachedMatcher->addRoute('POST', '/users', $route);

    // Verify route was added by trying to find it
    $result = $cachedMatcher->findRoute('POST', '/users');
    expect($result)->not->toBeNull();
});

it('can find all matching routes', function () {
    $manager = new RouteManager();
    $cachedMatcher = new CachedRouteMatcher($manager);

    $resolver = new RouteHandlerResolver();
    $route1 = new Route(['GET'], '/api/{version}/users', fn() => 'v1', $resolver);
    $route2 = new Route(['GET'], '/api/v2/users', fn() => 'v2', $resolver);

    $cachedMatcher->addRoute('GET', '/api/{version}/users', $route1);
    $cachedMatcher->addRoute('GET', '/api/v2/users', $route2);

    $results = $cachedMatcher->findAllRoutes('GET', '/api/v2/users');
    expect($results)->toHaveCount(2);
});

it('implements RouteManagerInterface', function () {
    $manager = new RouteManager();
    $cachedMatcher = new CachedRouteMatcher($manager);

    expect($cachedMatcher)->toBeInstanceOf(\Denosys\Routing\RouteManagerInterface::class);
});
