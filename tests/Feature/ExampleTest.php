<?php

use Denosys\Routing\Router;
use Laminas\Diactoros\ServerRequest;

it('returns a successful response', function () {
    $router = new Router();
    $router->get('/', fn() => 'Hello World');
    
    $request = new ServerRequest([], [], '/', 'GET');
    $response = $router->dispatch($request);

    expect($response->getStatusCode())->toBe(200);
    expect((string) $response->getBody())->toBe('Hello World');
});
