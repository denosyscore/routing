<?php

declare(strict_types=1);

use Laminas\Diactoros\Uri;
use Denosys\Routing\Router;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;

describe('Host, Port and Scheme Routing', function () {

    beforeEach(function () {
        $this->router = new Router();
    });

    describe('Host Conditions', function () {

        it('matches routes with exact host', function () {
            $this->router->group('/api', function($g) {
                $g->host('api.example.com');
                $g->get('/users', fn() => 'api users');
            });

            $this->router->group('/admin', function($g) {
                $g->host('admin.example.com');
                $g->get('/users', fn() => 'admin users');
            });

            // Request to api.example.com
            $request = (new ServerRequest([], [], '/api/users', 'GET'))
                ->withHeader('Host', 'api.example.com');

            /** @var ResponseInterface $response */
            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('api users');

            // Request to admin.example.com
            $request = (new ServerRequest([], [], '/admin/users', 'GET'))
                ->withHeader('Host', 'admin.example.com');

            /** @var ResponseInterface $response */
            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('admin users');
        });

        it('supports wildcard host matching', function () {
            $this->router->group('/tenant', function($g) {
                $g->host('{subdomain}.example.com');
                $g->get('/dashboard', fn($subdomain) => "Tenant: $subdomain");
            });

            $request = (new ServerRequest([], [], '/tenant/dashboard', 'GET'))
                ->withHeader('Host', 'acme.example.com');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('Tenant: acme');
        });

        it('ignores port when matching host', function () {
            $this->router->group('/api', function($g) {
                $g->host('api.example.com');
                $g->get('/users', fn() => 'api users');
            });

            // Request with port should still match
            $request = (new ServerRequest([], [], '/api/users', 'GET'))
                ->withHeader('Host', 'api.example.com:8080');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('api users');
        });

        it('supports multiple host parameters', function () {
            $this->router->group('/app', function($g) {
                $g->host('{subdomain}.{region}.example.com');
                $g->get('/info', fn($subdomain, $region) => "$subdomain in $region");
            });

            $request = (new ServerRequest([], [], '/app/info', 'GET'))
                ->withHeader('Host', 'api.us-west.example.com');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('api in us-west');
        });

        it('applies host constraints with where clause', function () {
            $this->router->group('/app', function($g) {
                $g->host('{tenant}.example.com')
                  ->whereHost('tenant', '[a-z]+'); // Only lowercase
                $g->get('/dashboard', fn() => 'dashboard');
            });

            // Valid tenant
            $request = (new ServerRequest([], [], '/app/dashboard', 'GET'))
                ->withHeader('Host', 'acme.example.com');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('dashboard');

            // Invalid tenant (contains numbers)
            $request = (new ServerRequest([], [], '/app/dashboard', 'GET'))
                ->withHeader('Host', 'acme123.example.com');

            expect(fn() => $this->router->dispatch($request))
                ->toThrow(\Denosys\Routing\Exceptions\NotFoundException::class);
        });
    });

    describe('Port Conditions', function () {

        it('matches routes with specific port', function () {
            $this->router->group('/api', function($g) {
                $g->port(8080);
                $g->get('/users', fn() => 'api on 8080');
            });

            $this->router->group('/api', function($g) {
                $g->port(9090);
                $g->get('/users', fn() => 'api on 9090');
            });

            // Request to port 8080
            $request = (new ServerRequest([], [], '/api/users', 'GET'))
                ->withHeader('Host', 'example.com:8080');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('api on 8080');

            // Request to port 9090
            $request = (new ServerRequest([], [], '/api/users', 'GET'))
                ->withHeader('Host', 'example.com:9090');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('api on 9090');
        });

        it('matches routes with wildcard port', function () {
            $this->router->group('/api', function($g) {
                $g->port('{port}');
                $g->get('/info', fn($port) => "Port: $port");
            });

            $request = (new ServerRequest([], [], '/api/info', 'GET'))
                ->withHeader('Host', 'example.com:3000');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('Port: 3000');
        });

        it('matches default ports (80 for http, 443 for https)', function () {
            $this->router->group('/api', function($g) {
                $g->port(80);
                $g->get('/users', fn() => 'default http port');
            });

            // Request without explicit port (defaults to 80 for http)
            $uri = (new Uri())->withScheme('http')->withHost('example.com')->withPath('/api/users');
            $request = (new ServerRequest([], [], $uri, 'GET'))
                ->withHeader('Host', 'example.com');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('default http port');
        });

        it('applies port constraints', function () {
            $this->router->group('/api', function($g) {
                $g->port('{port}')
                  ->wherePort('port', '808[0-9]'); // Only 8080-8089
                $g->get('/users', fn() => 'restricted port');
            });

            // Valid port
            $request = (new ServerRequest([], [], '/api/users', 'GET'))
                ->withHeader('Host', 'example.com:8085');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('restricted port');

            // Invalid port
            $request = (new ServerRequest([], [], '/api/users', 'GET'))
                ->withHeader('Host', 'example.com:9000');

            expect(fn() => $this->router->dispatch($request))
                ->toThrow(\Denosys\Routing\Exceptions\NotFoundException::class);
        });

        it('supports port ranges', function () {
            $this->router->group('/api', function($g) {
                $g->portIn([8080, 8081, 8082]);
                $g->get('/users', fn() => 'port in range');
            });

            // Valid port
            $request = (new ServerRequest([], [], '/api/users', 'GET'))
                ->withHeader('Host', 'example.com:8081');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('port in range');

            // Invalid port
            $request = (new ServerRequest([], [], '/api/users', 'GET'))
                ->withHeader('Host', 'example.com:8090');

            expect(fn() => $this->router->dispatch($request))
                ->toThrow(\Denosys\Routing\Exceptions\NotFoundException::class);
        });
    });

    describe('Scheme Conditions', function () {

        it('matches routes with https scheme only', function () {
            $this->router->group('/secure', function($g) {
                $g->scheme('https');
                $g->get('/data', fn() => 'secure data');
            });

            // HTTPS request
            $uri = (new Uri())->withScheme('https')->withHost('example.com')->withPath('/secure/data');
            $request = new ServerRequest([], [], $uri, 'GET');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('secure data');

            // HTTP request should fail
            $uri = (new Uri())->withScheme('http')->withHost('example.com')->withPath('/secure/data');
            $request = new ServerRequest([], [], $uri, 'GET');

            expect(fn() => $this->router->dispatch($request))
                ->toThrow(\Denosys\Routing\Exceptions\NotFoundException::class);
        });

        it('matches routes with http scheme only', function () {
            $this->router->group('/public', function($g) {
                $g->scheme('http');
                $g->get('/info', fn() => 'public info');
            });

            // HTTP request
            $uri = (new Uri())->withScheme('http')->withHost('example.com')->withPath('/public/info');
            $request = new ServerRequest([], [], $uri, 'GET');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('public info');
        });

        it('supports multiple schemes', function () {
            $this->router->group('/api', function($g) {
                $g->scheme(['http', 'https']);
                $g->get('/users', fn() => 'both schemes');
            });

            // HTTP request
            $uri = (new Uri())->withScheme('http')->withHost('example.com')->withPath('/api/users');
            $request = new ServerRequest([], [], $uri, 'GET');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('both schemes');

            // HTTPS request
            $uri = (new Uri())->withScheme('https')->withHost('example.com')->withPath('/api/users');
            $request = new ServerRequest([], [], $uri, 'GET');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('both schemes');
        });

        it('captures scheme as parameter', function () {
            $this->router->group('/api', function($g) {
                $g->scheme('{scheme}');
                $g->get('/info', fn($scheme) => "Scheme: $scheme");
            });

            $uri = (new Uri())->withScheme('https')->withHost('example.com')->withPath('/api/info');
            $request = new ServerRequest([], [], $uri, 'GET');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('Scheme: https');
        });
    });

    describe('Combined Conditions', function () {

        it('combines host, port and scheme conditions', function () {
            $this->router->group('/api', function($g) {
                $g->host('api.example.com')
                  ->port(8080)
                  ->scheme('https');
                $g->get('/secure', fn() => 'secure api');
            });

            // Matching request
            $uri = (new Uri())
                ->withScheme('https')
                ->withHost('api.example.com')
                ->withPort(8080)
                ->withPath('/api/secure');

            $request = (new ServerRequest([], [], $uri, 'GET'))
                ->withHeader('Host', 'api.example.com:8080');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('secure api');

            // Wrong host
            $uri = (new Uri())
                ->withScheme('https')
                ->withHost('wrong.example.com')
                ->withPort(8080)
                ->withPath('/api/secure');

            $request = (new ServerRequest([], [], $uri, 'GET'))
                ->withHeader('Host', 'wrong.example.com:8080');

            expect(fn() => $this->router->dispatch($request))
                ->toThrow(\Denosys\Routing\Exceptions\NotFoundException::class);
        });

        it('inherits conditions from parent groups', function () {
            $this->router->group('', function($parent) {
                $parent->host('api.example.com')
                       ->scheme('https');

                $parent->group('/v1', function($g) {
                    $g->port(8080);
                    $g->get('/users', fn() => 'v1 users');
                });

                $parent->group('/v2', function($g) {
                    $g->port(9090);
                    $g->get('/users', fn() => 'v2 users');
                });
            });

            // V1 with port 8080
            $uri = (new Uri())
                ->withScheme('https')
                ->withHost('api.example.com')
                ->withPort(8080)
                ->withPath('/v1/users');

            $request = (new ServerRequest([], [], $uri, 'GET'))
                ->withHeader('Host', 'api.example.com:8080');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('v1 users');
        });

        it('supports secure() helper method for https', function () {
            $this->router->group('/admin', function($g) {
                $g->secure(); // Shorthand for ->scheme('https')
                $g->get('/dashboard', fn() => 'secure dashboard');
            });

            $uri = (new Uri())->withScheme('https')->withHost('example.com')->withPath('/admin/dashboard');
            $request = new ServerRequest([], [], $uri, 'GET');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('secure dashboard');
        });

        it('can mix wildcard and exact conditions', function () {
            $this->router->group('/app', function($g) {
                $g->host('{tenant}.example.com')
                  ->port(8080)
                  ->scheme('https');
                $g->get('/info', fn($tenant) => "Tenant: $tenant on secure 8080");
            });

            $uri = (new Uri())
                ->withScheme('https')
                ->withHost('acme.example.com')
                ->withPort(8080)
                ->withPath('/app/info');

            $request = (new ServerRequest([], [], $uri, 'GET'))
                ->withHeader('Host', 'acme.example.com:8080');

            $response = $this->router->dispatch($request);
            expect((string) $response->getBody())->toBe('Tenant: acme on secure 8080');
        });
    });
});
