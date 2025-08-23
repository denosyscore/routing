<?php

declare(strict_types=1);

use Denosys\Routing\Attributes\Get;
use Denosys\Routing\Attributes\Post;
use Denosys\Routing\Attributes\Put;
use Denosys\Routing\Attributes\Delete;
use Denosys\Routing\Attributes\Patch;
use Denosys\Routing\Attributes\Options;
use Denosys\Routing\Attributes\Any;
use Denosys\Routing\Attributes\RouteMatch;

it('can create Get attribute with path', function () {
    $get = new Get('/users');
    
    expect($get->getPath())->toBe('/users')
        ->and($get->getMethods())->toBe(['GET', 'HEAD'])
        ->and($get->getName())->toBeNull()
        ->and($get->getWhere())->toBe([])
        ->and($get->getMiddleware())->toBe([]);
});

it('can create Get attribute with all parameters', function () {
    $get = new Get(
        path: '/users/{id}',
        name: 'users.show',
        where: ['id' => '\d+'],
        middleware: ['auth']
    );
    
    expect($get->getPath())->toBe('/users/{id}')
        ->and($get->getMethods())->toBe(['GET', 'HEAD'])
        ->and($get->getName())->toBe('users.show')
        ->and($get->getWhere())->toBe(['id' => '\d+'])
        ->and($get->getMiddleware())->toBe(['auth']);
});

it('can create Post attribute', function () {
    $post = new Post('/users', 'users.store', middleware: ['auth']);
    
    expect($post->getPath())->toBe('/users')
        ->and($post->getMethods())->toBe(['POST'])
        ->and($post->getName())->toBe('users.store')
        ->and($post->getMiddleware())->toBe(['auth']);
});

it('can create Put attribute', function () {
    $put = new Put('/users/{id}', where: ['id' => '\d+']);
    
    expect($put->getPath())->toBe('/users/{id}')
        ->and($put->getMethods())->toBe(['PUT'])
        ->and($put->getWhere())->toBe(['id' => '\d+']);
});

it('can create Delete attribute', function () {
    $delete = new Delete('/users/{id}');
    
    expect($delete->getPath())->toBe('/users/{id}')
        ->and($delete->getMethods())->toBe(['DELETE']);
});

it('can create Patch attribute', function () {
    $patch = new Patch('/users/{id}');
    
    expect($patch->getPath())->toBe('/users/{id}')
        ->and($patch->getMethods())->toBe(['PATCH']);
});

it('can create Options attribute', function () {
    $options = new Options('/users');
    
    expect($options->getPath())->toBe('/users')
        ->and($options->getMethods())->toBe(['OPTIONS']);
});

it('can create Any attribute', function () {
    $any = new Any('/webhook');
    
    expect($any->getPath())->toBe('/webhook')
        ->and($any->getMethods())->toBe(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS']);
});

it('can create RouteMatch attribute with single method', function () {
    $matchRoute = new RouteMatch(['POST'], '/search');
    
    expect($matchRoute->getPath())->toBe('/search')
        ->and($matchRoute->getMethods())->toBe(['POST']);
});

it('can create RouteMatch attribute with multiple methods', function () {
    $matchRoute = new RouteMatch(['GET', 'POST'], '/search');
    
    expect($matchRoute->getPath())->toBe('/search')
        ->and($matchRoute->getMethods())->toBe(['GET', 'POST']);
});
