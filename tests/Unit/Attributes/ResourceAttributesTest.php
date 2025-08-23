<?php

declare(strict_types=1);

use Denosys\Routing\Attributes\Resource;
use Denosys\Routing\Attributes\ApiResource;

it('can create Resource attribute with default actions', function () {
    $resource = new Resource('users');
    
    expect($resource->getName())->toBe('users')
        ->and($resource->getOnly())->toBe([])
        ->and($resource->getExcept())->toBe([])
        ->and($resource->getMiddleware())->toBe([])
        ->and($resource->getDefaultActions())->toBe([
            'index' => ['GET', ''],
            'create' => ['GET', '/create'],
            'store' => ['POST', ''],
            'show' => ['GET', '/{id}'],
            'edit' => ['GET', '/{id}/edit'],
            'update' => ['PUT', '/{id}'],
            'delete' => ['DELETE', '/{id}']
        ]);
});

it('can create Resource attribute with only specific actions', function () {
    $resource = new Resource('users', only: ['index', 'show']);
    
    expect($resource->getName())->toBe('users')
        ->and($resource->getOnly())->toBe(['index', 'show'])
        ->and($resource->getExcept())->toBe([]);
});

it('can create Resource attribute excluding specific actions', function () {
    $resource = new Resource('users', except: ['create', 'edit']);
    
    expect($resource->getName())->toBe('users')
        ->and($resource->getOnly())->toBe([])
        ->and($resource->getExcept())->toBe(['create', 'edit']);
});

it('can create Resource attribute with middleware', function () {
    $resource = new Resource('users', middleware: ['auth']);
    
    expect($resource->getName())->toBe('users')
        ->and($resource->getMiddleware())->toBe(['auth']);
});

it('can create ApiResource attribute', function () {
    $apiResource = new ApiResource('products');
    
    expect($apiResource->getName())->toBe('products')
        ->and($apiResource->getDefaultActions())->toBe([
            'index' => ['GET', ''],
            'store' => ['POST', ''],
            'show' => ['GET', '/{id}'],
            'update' => ['PUT', '/{id}'],
            'delete' => ['DELETE', '/{id}']
        ]);
});

it('can create ApiResource attribute with constraints', function () {
    $apiResource = new ApiResource('products', only: ['index', 'show'], middleware: ['api']);
    
    expect($apiResource->getName())->toBe('products')
        ->and($apiResource->getOnly())->toBe(['index', 'show'])
        ->and($apiResource->getMiddleware())->toBe(['api']);
});

it('Resource generates correct action methods', function () {
    $resource = new Resource('users');
    $actions = $resource->getActions();
    
    expect($actions)->toHaveCount(7)
        ->and($actions['index']['method'])->toBe('GET')
        ->and($actions['index']['path'])->toBe('')
        ->and($actions['show']['method'])->toBe('GET')
        ->and($actions['show']['path'])->toBe('/{id}')
        ->and($actions['store']['method'])->toBe('POST')
        ->and($actions['update']['method'])->toBe('PUT');
});

it('ApiResource generates correct action methods', function () {
    $apiResource = new ApiResource('products');
    $actions = $apiResource->getActions();
    
    expect($actions)->toHaveCount(5)
        ->and($actions)->not()->toHaveKey('create')
        ->and($actions)->not()->toHaveKey('edit')
        ->and($actions['index']['method'])->toBe('GET')
        ->and($actions['store']['method'])->toBe('POST');
});

it('Resource respects only constraint', function () {
    $resource = new Resource('users', only: ['index', 'show']);
    $actions = $resource->getActions();
    
    expect($actions)->toHaveCount(2)
        ->and($actions)->toHaveKey('index')
        ->and($actions)->toHaveKey('show')
        ->and($actions)->not()->toHaveKey('store');
});

it('Resource respects except constraint', function () {
    $resource = new Resource('users', except: ['create', 'edit']);
    $actions = $resource->getActions();
    
    expect($actions)->toHaveCount(5)
        ->and($actions)->not()->toHaveKey('create')
        ->and($actions)->not()->toHaveKey('edit')
        ->and($actions)->toHaveKey('index')
        ->and($actions)->toHaveKey('store');
});
