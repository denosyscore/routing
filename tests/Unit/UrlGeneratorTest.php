<?php

use Denosys\Routing\Router;
use Denosys\Routing\UrlGenerator;
use Denosys\Routing\Exceptions\RouteNotFoundException;
use Laminas\Diactoros\ServerRequest;


enum RouteNameType: string
{
    case DOWNLOAD = 'download';
    case PAYMENT = 'payment.process';
    case USER_PROFILE = 'user.profile';
}

describe('UrlGenerator', function () {
    
    beforeEach(function () {
        $this->router = new Router();
        $this->urlGenerator = new UrlGenerator($this->router->getRouteCollection());
    });

    describe('Basic Route Generation', function () {
        
        it('can generate URL for named route without parameters', function () {
            $this->router->get('/users', fn() => 'users')->name('users.index');
            
            $url = $this->urlGenerator->route('users.index');
            
            expect($url)->toBe('/users');
        });

        it('can generate URL for named route with single parameter', function () {
            $this->router->get('/users/{id}', fn($id) => "user $id")->name('users.show');
            
            $url = $this->urlGenerator->route('users.show', ['id' => 123]);
            
            expect($url)->toBe('/users/123');
        });

        it('can generate URL for named route with multiple parameters', function () {
            $this->router->get('/users/{userId}/posts/{postId}', fn($userId, $postId) => 'post')
                         ->name('users.posts.show');
            
            $url = $this->urlGenerator->route('users.posts.show', [
                'userId' => 123,
                'postId' => 456
            ]);
            
            expect($url)->toBe('/users/123/posts/456');
        });

        it('throws exception for non-existent route name', function () {
            expect(fn() => $this->urlGenerator->route('non.existent'))
                ->toThrow(RouteNotFoundException::class, "Route [non.existent] not found");
        });

        it('can check if route exists', function () {
            $this->router->get('/users', fn() => 'users')->name('users.index');
            
            expect($this->urlGenerator->hasRoute('users.index'))->toBe(true);
            expect($this->urlGenerator->hasRoute('non.existent'))->toBe(false);
        });

        it('can generate URL with base URL', function () {
            $this->urlGenerator->setBaseUrl('https://example.com');
            $this->router->get('/users/{id}', fn($id) => "user $id")->name('users.show');
            
            $url = $this->urlGenerator->route('users.show', ['id' => 123]);
            
            expect($url)->toBe('https://example.com/users/123');
        });

        it('can generate relative URL when absolute is false', function () {
            $this->urlGenerator->setBaseUrl('https://example.com');
            $this->router->get('/users/{id}', fn($id) => "user $id")->name('users.show');
            
            $url = $this->urlGenerator->route('users.show', ['id' => 123], false);
            
            expect($url)->toBe('/users/123');
        });

        it('throws exception when required parameter is missing', function () {
            $this->router->get('/users/{id}', fn($id) => "user $id")->name('users.show');

            expect(fn() => $this->urlGenerator->route('users.show'))
                ->toThrow(InvalidArgumentException::class, "Missing required parameter [id] for route [users.show]");
        });

        it('handles special characters in parameters', function () {
            $this->router->get('/search/{query}', fn($query) => 'search')->name('search');
            
            $url = $this->urlGenerator->route('search', ['query' => 'hello world!']);
            
            expect($url)->toBe('/search/hello%20world%21');
        });
        
        it('can generate URL using BackedEnum for route name', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            
            $url = $this->urlGenerator->route(RouteNameType::DOWNLOAD, ['file' => 'document.pdf']);
            
            expect($url)->toBe('/download/document.pdf');
        });
        
        it('can check if route exists using BackedEnum', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            
            expect($this->urlGenerator->hasRoute(RouteNameType::DOWNLOAD))->toBe(true);
            expect($this->urlGenerator->hasRoute(RouteNameType::PAYMENT))->toBe(false);
        });
    });

    describe('Configuration Methods', function () {
        
        it('can set and get base URL', function () {
            $this->urlGenerator->setBaseUrl('https://example.com');
            
            expect($this->urlGenerator->getBaseUrl())->toBe('https://example.com');
        });

        it('can set and get asset URL', function () {
            $this->urlGenerator->setAssetUrl('https://cdn.example.com');
            
            expect($this->urlGenerator->getAssetUrl())->toBe('https://cdn.example.com');
        });

        it('falls back to base URL when no asset URL set', function () {
            $this->urlGenerator->setBaseUrl('https://example.com');
            
            expect($this->urlGenerator->getAssetUrl())->toBe('https://example.com');
        });

        it('can set secure mode', function () {
            $this->urlGenerator->setBaseUrl('http://example.com');
            $this->urlGenerator->setSecure(true);
            
            $url = $this->urlGenerator->to('/admin');
            
            expect($url)->toBe('https://example.com/admin');
        });
    });

    describe('Request Management', function () {
        
        it('can set and get request', function () {
            $request = new ServerRequest([], [], 'https://example.com/test', 'GET');
            
            $this->urlGenerator->setRequest($request);
            
            expect($this->urlGenerator->getRequest())->toBe($request);
        });
    });

    describe('Route Groups and Naming', function () {
        
        it('can generate URLs for routes in groups with name prefix', function () {
            $this->router->group('/api', function($group) {
                $group->name('api')->group('/v1', function($v1) {
                    $v1->get('/users', fn() => 'users')->name('users.index');
                    $v1->get('/users/{id}', fn($id) => "user $id")->name('users.show');
                });
            });

            $url = $this->urlGenerator->route('api.users.index');
            expect($url)->toBe('/api/v1/users');

            $url = $this->urlGenerator->route('api.users.show', ['id' => 123]);
            expect($url)->toBe('/api/v1/users/123');
        });
    });

    describe('Current and Previous URL Methods', function () {
        
        it('can get the current URL for the request', function () {
            $request = new ServerRequest([], [], 'https://example.com/users/123?tab=profile', 'GET');
            $this->urlGenerator->setRequest($request);
            
            $currentUrl = $this->urlGenerator->current();
            
            expect($currentUrl)->toBe('https://example.com/users/123?tab=profile');
        });

        it('can get the current URL without query string', function () {
            $request = new ServerRequest([], [], 'https://example.com/users/123?tab=profile', 'GET');
            $this->urlGenerator->setRequest($request);
            
            $currentUrl = $this->urlGenerator->current(false);
            
            expect($currentUrl)->toBe('https://example.com/users/123');
        });

        it('can get the previous URL from referer header', function () {
            $request = new ServerRequest(
                [], [], 
                'https://example.com/users/123', 
                'GET',
                'php://memory',
                ['Referer' => 'https://example.com/dashboard']
            );
            $this->urlGenerator->setRequest($request);
            
            $previousUrl = $this->urlGenerator->previous();
            
            expect($previousUrl)->toBe('https://example.com/dashboard');
        });

        it('can get previous URL from stored property', function () {
            $this->urlGenerator->setPreviousUrl('https://example.com/stored');
            
            $previousUrl = $this->urlGenerator->getPreviousUrl();
            
            expect($previousUrl)->toBe('https://example.com/stored');
        });

        it('returns fallback URL when no previous URL exists', function () {
            $request = new ServerRequest([], [], 'https://example.com/users/123', 'GET');
            $this->urlGenerator->setRequest($request);
            
            $previousUrl = $this->urlGenerator->previous('/dashboard');
            
            expect($previousUrl)->toBe('/dashboard');
        });

        it('throws exception when trying to get current URL without request', function () {
            expect(fn() => $this->urlGenerator->current())
                ->toThrow(RuntimeException::class, 'No request set. Call setRequest() first.');
        });
    });

    describe('URL Generation', function () {
        
        it('can generate absolute URL to given path', function () {
            $this->urlGenerator->setBaseUrl('https://example.com');
            
            $url = $this->urlGenerator->to('/admin/dashboard');
            
            expect($url)->toBe('https://example.com/admin/dashboard');
        });

        it('can generate absolute URL with query parameters', function () {
            $this->urlGenerator->setBaseUrl('https://example.com');
            
            $url = $this->urlGenerator->to('/search', ['q' => 'hello world', 'sort' => 'date']);
            
            expect($url)->toContain('https://example.com/search?');
            expect($url)->toContain('q=hello+world');
            expect($url)->toContain('sort=date');
        });

        it('can generate secure URLs', function () {
            $this->urlGenerator->setBaseUrl('http://example.com');
            
            $url = $this->urlGenerator->to('/admin', [], true);
            
            expect($url)->toBe('https://example.com/admin');
        });
        
        it('throws exception for invalid URL paths', function () {
            $this->urlGenerator->setBaseUrl('ht://[invalid');
            
            expect(fn() => $this->urlGenerator->to('/admin'))
                ->toThrow(InvalidArgumentException::class, 'Invalid URL format:');
        });
        
        it('validates URLs with query parameters', function () {
            $this->urlGenerator->setBaseUrl('https://example.com');
            
            $url = $this->urlGenerator->to('/search', ['q' => 'test', 'page' => 1]);
            
            expect($url)->toStartWith('https://example.com/search?');
        });
        
        it('can validate URLs using public method', function () {
            expect($this->urlGenerator->isValidUrl('https://example.com/path'))->toBe(true);
            expect($this->urlGenerator->isValidUrl('?query=value'))->toBe(true);
            expect($this->urlGenerator->isValidUrl('#fragment'))->toBe(true);
            expect($this->urlGenerator->isValidUrl('invalid://url with spaces'))->toBe(false);
            expect($this->urlGenerator->isValidUrl('ht://[invalid/admin'))->toBe(false);
            expect($this->urlGenerator->isValidUrl('http://[invalid-host'))->toBe(false);
        });
        
        it('supports mailto URI scheme validation', function () {
            expect($this->urlGenerator->isValidUrl('mailto:user@example.com'))->toBe(true);
            expect($this->urlGenerator->isValidUrl('mailto:user@example.com?subject=Hello'))->toBe(true);
            expect($this->urlGenerator->isValidUrl('MAILTO:User@Example.COM'))->toBe(true);
            expect($this->urlGenerator->isValidUrl('mailto:'))->toBe(false);
            expect($this->urlGenerator->isValidUrl('mailto:anything'))->toBe(true);
        });
        
        it('supports tel URI scheme validation', function () {
            expect($this->urlGenerator->isValidUrl('tel:+1234567890'))->toBe(true);
            expect($this->urlGenerator->isValidUrl('tel:123-456-7890'))->toBe(true);
            expect($this->urlGenerator->isValidUrl('tel:(123) 456-7890'))->toBe(true);
            expect($this->urlGenerator->isValidUrl('TEL:123.456.7890'))->toBe(true);
            expect($this->urlGenerator->isValidUrl('tel:'))->toBe(false);
            expect($this->urlGenerator->isValidUrl('tel:anything'))->toBe(true);
        });
        
        it('supports sms URI scheme validation', function () {
            expect($this->urlGenerator->isValidUrl('sms:+1234567890'))->toBe(true);
            expect($this->urlGenerator->isValidUrl('sms:123-456-7890?body=Hello'))->toBe(true);
            expect($this->urlGenerator->isValidUrl('SMS:(123) 456-7890'))->toBe(true);
            expect($this->urlGenerator->isValidUrl('sms:'))->toBe(false);
            expect($this->urlGenerator->isValidUrl('sms:anything'))->toBe(true);
        });
    });

    describe('Asset URL Generation', function () {
        
        it('can generate URL to application asset', function () {
            $this->urlGenerator->setBaseUrl('https://example.com');
            
            $url = $this->urlGenerator->asset('/css/app.css');
            
            expect($url)->toBe('https://example.com/css/app.css');
        });

        it('can generate asset URL with version parameter', function () {
            $this->urlGenerator->setBaseUrl('https://example.com');
            
            $url = $this->urlGenerator->asset('/css/app.css', '1.2.3');
            
            expect($url)->toBe('https://example.com/css/app.css?v=1.2.3');
        });

        it('handles asset paths without leading slash', function () {
            $this->urlGenerator->setBaseUrl('https://example.com');
            
            $url = $this->urlGenerator->asset('js/app.js');
            
            expect($url)->toBe('https://example.com/js/app.js');
        });

        it('can use CDN URL for assets', function () {
            $this->urlGenerator->setAssetUrl('https://cdn.example.com');
            
            $url = $this->urlGenerator->asset('/css/app.css');
            
            expect($url)->toBe('https://cdn.example.com/css/app.css');
        });

        it('can generate secure asset URLs', function () {
            $this->urlGenerator->setAssetUrl('http://cdn.example.com');
            
            $url = $this->urlGenerator->asset('/css/app.css', null, true);
            
            expect($url)->toBe('https://cdn.example.com/css/app.css');
        });

        it('can generate relative asset URLs when absolute is false', function () {
            $this->urlGenerator->setAssetUrl('https://cdn.example.com');
            
            $url = $this->urlGenerator->asset('/css/app.css', null, false, false);
            
            expect($url)->toBe('/css/app.css');
        });
        
        it('throws exception for invalid asset URLs', function () {
            $this->urlGenerator->setAssetUrl('ht://[invalid');
            
            expect(fn() => $this->urlGenerator->asset('/css/app.css'))
                ->toThrow(InvalidArgumentException::class, 'Invalid URL format:');
        });
    });

    describe('Signed URL Generation', function () {
        
        it('can create signed route URL for named route', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            $this->urlGenerator->setKeyResolver(fn() => 'secret-key');
            
            $url = $this->urlGenerator->signedRoute('download', ['file' => 'document.pdf']);
            
            expect($url)->toStartWith('/download/document.pdf?signature=');
            expect($url)->toContain('signature=');
        });

        it('can create temporary signed route URL', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            $this->urlGenerator->setKeyResolver(fn() => 'secret-key');
            
            $expiresAt = time() + 3600;
            $url = $this->urlGenerator->signedRoute('download', ['file' => 'document.pdf'], $expiresAt);
            
            expect($url)->toStartWith('/download/document.pdf?expires=');
            expect($url)->toContain('signature=');
            expect($url)->toContain("expires=$expiresAt");
        });

        it('can verify signed URL signature', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            $this->urlGenerator->setKeyResolver(fn() => 'secret-key');
            
            $url = $this->urlGenerator->signedRoute('download', ['file' => 'document.pdf']);
            $isValid = $this->urlGenerator->hasValidSignature($url);
            
            expect($isValid)->toBe(true);
        });

        it('can use custom signing key', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            $this->urlGenerator->setKeyResolver(fn() => 'my-custom-key');
            
            $url = $this->urlGenerator->signedRoute('download', ['file' => 'document.pdf']);
            $isValid = $this->urlGenerator->hasValidSignature($url);
            
            expect($isValid)->toBe(true);
        });

        it('rejects expired temporary signed URLs', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            $this->urlGenerator->setKeyResolver(fn() => 'secret-key');
            
            $expiresAt = time() - 3600; // 1 hour ago (expired)
            $url = $this->urlGenerator->signedRoute('download', ['file' => 'document.pdf'], $expiresAt);
            
            $isValid = $this->urlGenerator->hasValidSignature($url);
            
            expect($isValid)->toBe(false);
        });

        it('rejects URLs with invalid signatures', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            $this->urlGenerator->setKeyResolver(fn() => 'secret-key');
            
            $url = $this->urlGenerator->signedRoute('download', ['file' => 'document.pdf']);
            $tamperedUrl = str_replace('document.pdf', 'other-file.pdf', $url);
            
            $isValid = $this->urlGenerator->hasValidSignature($tamperedUrl);
            
            expect($isValid)->toBe(false);
        });

        it('can create signed URL with DateTime expiration', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            $this->urlGenerator->setKeyResolver(fn() => 'secret-key');
            
            $expiresAt = new \DateTime('+1 hour');
            $url = $this->urlGenerator->signedRoute('download', ['file' => 'document.pdf'], $expiresAt);
            
            expect($url)->toStartWith('/download/document.pdf?expires=');
            expect($url)->toContain('signature=');
            expect($url)->toContain('expires=' . $expiresAt->getTimestamp());
        });

        it('can create signed URL with DateInterval expiration', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            $this->urlGenerator->setKeyResolver(fn() => 'secret-key');
            
            $interval = new \DateInterval('PT1H'); // 1 hour
            $url = $this->urlGenerator->signedRoute('download', ['file' => 'document.pdf'], $interval);
            
            expect($url)->toStartWith('/download/document.pdf?expires=');
            expect($url)->toContain('signature=');
        });
        
        it('can use key resolver with different keys', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            $this->urlGenerator->setKeyResolver(fn() => 'downloads-secret');
            
            $url = $this->urlGenerator->signedRoute('download', ['file' => 'document.pdf']);
            $isValid = $this->urlGenerator->hasValidSignature($url);
            
            expect($isValid)->toBe(true);
        });
        
        it('can use BackedEnum for route name', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            $this->urlGenerator->setKeyResolver(fn() => 'secret-key');
            
            $url = $this->urlGenerator->signedRoute(RouteNameType::DOWNLOAD, ['file' => 'document.pdf']);
            $isValid = $this->urlGenerator->hasValidSignature($url);
            
            expect($isValid)->toBe(true);
            expect($url)->toStartWith('/download/document.pdf?signature=');
        });
        
        it('can use callable key resolver', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            
            // Set up a key resolver that returns a single key
            $keyResolver = function (): string {
                return 'resolver-provided-key';
            };
            
            $this->urlGenerator->setKeyResolver($keyResolver);
            
            $url = $this->urlGenerator->signedRoute('download', ['file' => 'document.pdf']);
            $isValid = $this->urlGenerator->hasValidSignature($url);
            
            expect($isValid)->toBe(true);
            expect($url)->toStartWith('/download/document.pdf?signature=');
        });
        
        it('throws exception when no key resolver is set', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            
            expect(fn() => $this->urlGenerator->signedRoute('download', ['file' => 'document.pdf']))
                ->toThrow(RuntimeException::class, 'No key resolver set. Call setKeyResolver() first.');
        });
        
        it('throws exception when using reserved parameter "signature"', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            $this->urlGenerator->setKeyResolver(fn() => 'secret-key');
            
            expect(fn() => $this->urlGenerator->signedRoute('download', ['file' => 'document.pdf', 'signature' => 'malicious']))
                ->toThrow(InvalidArgumentException::class, "Parameter 'signature' is reserved for signed URLs and cannot be used in route parameters");
        });
        
        it('throws exception when using reserved parameter "expires"', function () {
            $this->router->get('/download/{file}', fn($file) => "download $file")->name('download');
            $this->urlGenerator->setKeyResolver(fn() => 'secret-key');
            
            expect(fn() => $this->urlGenerator->signedRoute('download', ['file' => 'document.pdf', 'expires' => 12345]))
                ->toThrow(InvalidArgumentException::class, "Parameter 'expires' is reserved for signed URLs and cannot be used in route parameters");
        });
    });

    describe('Session-Based URL Storage', function () {
        
        it('can store and retrieve intended URL', function () {
            $this->urlGenerator->setIntendedUrl('https://example.com/dashboard');
            
            $intendedUrl = $this->urlGenerator->getIntendedUrl();
            
            expect($intendedUrl)->toBe('https://example.com/dashboard');
        });

        it('can store and retrieve previous URL', function () {
            $this->urlGenerator->setPreviousUrl('https://example.com/previous');
            
            $previousUrl = $this->urlGenerator->getPreviousUrl();
            
            expect($previousUrl)->toBe('https://example.com/previous');
        });
    });

    describe('Full URL Method', function () {
        
        it('can get full URL from request', function () {
            $request = new ServerRequest([], [], 'https://example.com/users/123?tab=profile', 'GET');
            $this->urlGenerator->setRequest($request);
            
            $fullUrl = $this->urlGenerator->full();
            
            expect($fullUrl)->toBe('https://example.com/users/123?tab=profile');
        });

        it('throws exception when trying to get full URL without request', function () {
            expect(fn() => $this->urlGenerator->full())
                ->toThrow(RuntimeException::class, 'No request set. Call setRequest() first.');
        });
    });

    describe('Global Helper Function', function () {
        
        it('can use global route helper function', function () {
            $this->router->get('/users/{id}', fn($id) => "user $id")->name('users.show');
            
            // Set up global router for helper function
            $GLOBALS['router'] = $this->router;
            
            $url = route('users.show', ['id' => 123]);
            
            expect($url)->toBe('/users/123');
        });
    });

    describe('Edge Cases', function () {
        
        it('handles routes with optional parameters', function () {
            $this->router->get('/posts/{slug?}', fn($slug = null) => 'posts')->name('posts.show');
            
            // With parameter
            $url = $this->urlGenerator->route('posts.show', ['slug' => 'my-post']);
            expect($url)->toBe('/posts/my-post');
            
            // Without parameter
            $url = $this->urlGenerator->route('posts.show');
            expect($url)->toBe('/posts');
        });
    });
});