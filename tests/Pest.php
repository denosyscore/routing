<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// Force error reporting in test environment
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Register a custom error handler that ensures errors are visible
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        echo "\n\033[31m[ERROR]\033[0m $message\n";
        echo "  File: $file:$line\n\n";
    }
    return false; // Continue with normal error handler
});

// Register shutdown function to catch fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        echo "\n\033[31m[FATAL ERROR]\033[0m {$error['message']}\n";
        echo "  File: {$error['file']}:{$error['line']}\n\n";
    }
});

pest()->extend(Tests\TestCase::class)->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeResponse', function () {
    return $this->toBeInstanceOf(\Psr\Http\Message\ResponseInterface::class);
});

expect()->extend('toBeRoute', function () {
    return $this->toBeInstanceOf(\Denosys\Routing\RouteInterface::class);
});

expect()->extend('toBeRouteGroup', function () {
    return $this->toBeInstanceOf(\Denosys\Routing\RouteGroupInterface::class);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createRequest(string $method = 'GET', string $path = '/', array $headers = []): \Psr\Http\Message\ServerRequestInterface
{
    return new \Laminas\Diactoros\ServerRequest([], [], $path, $method, 'php://memory', $headers);
}

function createJsonResponse(array $data, int $status = 200): \Psr\Http\Message\ResponseInterface
{
    return new \Laminas\Diactoros\Response\JsonResponse($data, $status);
}

function route(string $name, array $parameters = []): string
{
    static $urlGenerator;
    static $lastRouter;

    if (!isset($GLOBALS['router'])) {
        throw new RuntimeException('Global router not set. Set $GLOBALS[\'router\'] before using route() helper.');
    }

    // Recreate UrlGenerator if router changed
    if ($lastRouter !== $GLOBALS['router']) {
        $urlGenerator = new \Denosys\Routing\UrlGenerator($GLOBALS['router']->getRouteCollection());
        $lastRouter = $GLOBALS['router'];
    }

    return $urlGenerator->route($name, $parameters);
}
