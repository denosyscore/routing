<?php

declare(strict_types=1);

use Denosys\Routing\MiddlewareRegistry;
use Denosys\Routing\MiddlewareRegistryInterface;

beforeEach(function () {
    $this->registry = new MiddlewareRegistry();
});

describe('MiddlewareRegistry', function () {
    describe('Aliases', function () {
        it('can register a middleware alias', function () {
            $this->registry->alias('auth', 'App\Middleware\AuthMiddleware');

            expect($this->registry->hasAlias('auth'))->toBeTrue();
            expect($this->registry->getAlias('auth'))->toBe('App\Middleware\AuthMiddleware');
        });

        it('can register multiple aliases at once', function () {
            $this->registry->aliases([
                'auth' => 'App\Middleware\AuthMiddleware',
                'throttle' => 'App\Middleware\ThrottleMiddleware',
            ]);

            expect($this->registry->hasAlias('auth'))->toBeTrue();
            expect($this->registry->hasAlias('throttle'))->toBeTrue();
        });

        it('returns null for non-existent alias', function () {
            expect($this->registry->getAlias('nonexistent'))->toBeNull();
            expect($this->registry->hasAlias('nonexistent'))->toBeFalse();
        });

        it('can remove an alias', function () {
            $this->registry->alias('auth', 'App\Middleware\AuthMiddleware');
            $this->registry->removeAlias('auth');

            expect($this->registry->hasAlias('auth'))->toBeFalse();
        });

        it('can get all aliases', function () {
            $this->registry->aliases([
                'auth' => 'App\Middleware\AuthMiddleware',
                'throttle' => 'App\Middleware\ThrottleMiddleware',
            ]);

            $aliases = $this->registry->getAliases();

            expect($aliases)->toHaveCount(2);
            expect($aliases['auth'])->toBe('App\Middleware\AuthMiddleware');
        });
    });

    describe('Groups', function () {
        it('can register a middleware group', function () {
            $this->registry->group('web', ['session', 'csrf', 'cookies']);

            expect($this->registry->hasGroup('web'))->toBeTrue();
            expect($this->registry->getGroup('web'))->toBe(['session', 'csrf', 'cookies']);
        });

        it('returns null for non-existent group', function () {
            expect($this->registry->getGroup('nonexistent'))->toBeNull();
            expect($this->registry->hasGroup('nonexistent'))->toBeFalse();
        });

        it('can prepend middleware to a group', function () {
            $this->registry->group('web', ['csrf', 'cookies']);
            $this->registry->prependToGroup('web', 'session');

            expect($this->registry->getGroup('web'))->toBe(['session', 'csrf', 'cookies']);
        });

        it('can prepend multiple middleware to a group', function () {
            $this->registry->group('web', ['cookies']);
            $this->registry->prependToGroup('web', ['session', 'csrf']);

            expect($this->registry->getGroup('web'))->toBe(['session', 'csrf', 'cookies']);
        });

        it('can append middleware to a group', function () {
            $this->registry->group('web', ['session', 'csrf']);
            $this->registry->appendToGroup('web', 'cookies');

            expect($this->registry->getGroup('web'))->toBe(['session', 'csrf', 'cookies']);
        });

        it('can append multiple middleware to a group', function () {
            $this->registry->group('web', ['session']);
            $this->registry->appendToGroup('web', ['csrf', 'cookies']);

            expect($this->registry->getGroup('web'))->toBe(['session', 'csrf', 'cookies']);
        });

        it('creates group when prepending to non-existent group', function () {
            $this->registry->prependToGroup('web', 'session');

            expect($this->registry->hasGroup('web'))->toBeTrue();
            expect($this->registry->getGroup('web'))->toBe(['session']);
        });

        it('creates group when appending to non-existent group', function () {
            $this->registry->appendToGroup('web', 'session');

            expect($this->registry->hasGroup('web'))->toBeTrue();
            expect($this->registry->getGroup('web'))->toBe(['session']);
        });

        it('can remove a group', function () {
            $this->registry->group('web', ['session', 'csrf']);
            $this->registry->removeGroup('web');

            expect($this->registry->hasGroup('web'))->toBeFalse();
        });

        it('can get all groups', function () {
            $this->registry->group('web', ['session', 'csrf']);
            $this->registry->group('api', ['throttle', 'auth']);

            $groups = $this->registry->getGroups();

            expect($groups)->toHaveCount(2);
            expect($groups['web'])->toBe(['session', 'csrf']);
            expect($groups['api'])->toBe(['throttle', 'auth']);
        });
    });

    describe('Resolution', function () {
        it('resolves a direct class name unchanged', function () {
            $resolved = $this->registry->resolve('App\Middleware\AuthMiddleware');

            expect($resolved)->toBe(['App\Middleware\AuthMiddleware']);
        });

        it('resolves an alias to its class', function () {
            $this->registry->alias('auth', 'App\Middleware\AuthMiddleware');

            $resolved = $this->registry->resolve('auth');

            expect($resolved)->toBe(['App\Middleware\AuthMiddleware']);
        });

        it('resolves a group to its middleware', function () {
            $this->registry->group('web', [
                'App\Middleware\SessionMiddleware',
                'App\Middleware\CsrfMiddleware',
            ]);

            $resolved = $this->registry->resolve('web');

            expect($resolved)->toBe([
                'App\Middleware\SessionMiddleware',
                'App\Middleware\CsrfMiddleware',
            ]);
        });

        it('resolves a group with aliases', function () {
            $this->registry->alias('session', 'App\Middleware\SessionMiddleware');
            $this->registry->alias('csrf', 'App\Middleware\CsrfMiddleware');
            $this->registry->group('web', ['session', 'csrf']);

            $resolved = $this->registry->resolve('web');

            expect($resolved)->toBe([
                'App\Middleware\SessionMiddleware',
                'App\Middleware\CsrfMiddleware',
            ]);
        });

        it('resolves nested groups', function () {
            $this->registry->group('web', ['session', 'csrf']);
            $this->registry->group('admin', ['web', 'admin-auth']);
            $this->registry->alias('session', 'App\Middleware\SessionMiddleware');
            $this->registry->alias('csrf', 'App\Middleware\CsrfMiddleware');
            $this->registry->alias('admin-auth', 'App\Middleware\AdminAuthMiddleware');

            $resolved = $this->registry->resolve('admin');

            expect($resolved)->toBe([
                'App\Middleware\SessionMiddleware',
                'App\Middleware\CsrfMiddleware',
                'App\Middleware\AdminAuthMiddleware',
            ]);
        });

        it('resolves an array of middleware', function () {
            $this->registry->alias('auth', 'App\Middleware\AuthMiddleware');
            $this->registry->alias('throttle', 'App\Middleware\ThrottleMiddleware');

            $resolved = $this->registry->resolve(['auth', 'throttle']);

            expect($resolved)->toBe([
                'App\Middleware\AuthMiddleware',
                'App\Middleware\ThrottleMiddleware',
            ]);
        });

        it('resolves mixed array of aliases, groups, and classes', function () {
            $this->registry->alias('auth', 'App\Middleware\AuthMiddleware');
            $this->registry->group('web', ['session', 'csrf']);
            $this->registry->alias('session', 'App\Middleware\SessionMiddleware');
            $this->registry->alias('csrf', 'App\Middleware\CsrfMiddleware');

            $resolved = $this->registry->resolve([
                'auth',
                'web',
                'App\Middleware\CustomMiddleware',
            ]);

            expect($resolved)->toBe([
                'App\Middleware\AuthMiddleware',
                'App\Middleware\SessionMiddleware',
                'App\Middleware\CsrfMiddleware',
                'App\Middleware\CustomMiddleware',
            ]);
        });

        it('handles circular references gracefully', function () {
            $this->registry->group('a', ['b']);
            $this->registry->group('b', ['a', 'c']);
            $this->registry->alias('c', 'App\Middleware\CMiddleware');

            $resolved = $this->registry->resolve('a');

            // Should resolve 'b', skip circular 'a', resolve 'c'
            expect($resolved)->toBe(['App\Middleware\CMiddleware']);
        });
    });

    describe('Clearing', function () {
        it('can clear all aliases and groups', function () {
            $this->registry->alias('auth', 'App\Middleware\AuthMiddleware');
            $this->registry->group('web', ['session', 'csrf']);

            $this->registry->clear();

            expect($this->registry->getAliases())->toBe([]);
            expect($this->registry->getGroups())->toBe([]);
        });
    });

    describe('Fluent Interface', function () {
        it('returns self for all setter methods', function () {
            $result = $this->registry
                ->alias('auth', 'App\Middleware\AuthMiddleware')
                ->aliases(['throttle' => 'App\Middleware\ThrottleMiddleware'])
                ->group('web', ['auth', 'throttle'])
                ->prependToGroup('web', 'session')
                ->appendToGroup('web', 'cookies')
                ->removeAlias('throttle')
                ->removeGroup('web')
                ->clear();

            expect($result)->toBeInstanceOf(MiddlewareRegistry::class);
        });
    });

    describe('Interface', function () {
        it('implements MiddlewareRegistryInterface', function () {
            expect($this->registry)->toBeInstanceOf(MiddlewareRegistryInterface::class);
        });
    });
});

