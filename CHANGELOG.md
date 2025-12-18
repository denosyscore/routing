# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.0-alpha.2] – 2025-12-18

### Added

-   **PSR-15 Support**: `Dispatcher` now implements `RequestHandlerInterface` for direct use as PSR-15 handler
-   **Named Middleware Groups**: New `MiddlewareRegistry` class for managing middleware aliases and groups
    -   `aliasMiddleware('auth', AuthMiddleware::class)` - register single middleware aliases
    -   `middlewareGroup('web', ['session', 'csrf', 'cookies'])` - register middleware groups
    -   `prependMiddlewareToGroup()` / `appendMiddlewareToGroup()` - modify existing groups
    -   Nested groups support (groups can reference other groups)
    -   Circular reference protection
-   **Global Middleware**: New `$router->use()` method for application-wide middleware
    -   `use(LoggingMiddleware::class)` - register middleware that runs on every request
    -   Applied at dispatch time (works for routes defined before or after the `use()` call)
    -   Executes before route-specific middleware (outermost layer)
    -   Supports strings, arrays, and middleware instances
-   **Middleware Exclusion**: New `withoutMiddleware()` method for routes
    -   `$route->withoutMiddleware('auth')` - exclude specific middleware from a route
    -   Works with inherited group middleware
    -   Supports aliases and class names (resolved before filtering)
-   **New Cache Architecture**: `FileCache`, `ApcuCache`, `NullCache` implementations with `CacheInterface`
-   **Contracts Namespace**: Route interfaces following Interface Segregation Principle
    -   `RouteInfoInterface`, `RouteMatcherInterface`, `RouteNamingInterface`
    -   `RouteMiddlewareInterface`, `RouteConstraintInterface`
    -   `HostMatchingInterface`, `PortMatchingInterface`, `SchemeMatchingInterface`
-   **Strategies Namespace**: Pattern/host/port/scheme matching strategies
    -   `PatternMatchingStrategyInterface`, `HostMatchingStrategyInterface`
    -   `PortMatchingStrategyInterface`, `SchemeMatchingStrategyInterface`
    -   Default implementations for each strategy
-   **Factories Namespace**: `CacheFactory`, `RouteFactory` for object creation
-   **AttributeRouteLoader**: Convenient loader for scanning and registering attribute-based routes
-   **Priority Enum**: Handler and parameter resolver priority management

### Changed

-   Moved PSR-7 implementations (`laminas/laminas-diactoros`, `slim/psr7`, `nyholm/psr7-server`) from `require` to `require-dev`
-   Added `suggest` section for recommended PSR-7 implementations
-   Users can now choose their preferred PSR-7 implementation (reduced dependency footprint)
-   Handler resolver chain now uses priority-based sorting
-   Middleware resolution happens at dispatch time (lazy) for flexibility
-   Branch alias updated to `0.5.x-dev`

### Fixed

-   README example for accessing routes from collection now uses `reset()` instead of numeric index

## [0.4.0] – 2025-08-18

### Added

-   Constraint enforcement tests covering all constraint types
-   Strict validation for route parameter constraints (whereNumber, whereAlpha, whereAlphaNumeric, whereIn)
-   Enhanced regex constraint handling with proper delimiter escaping
-   404 NotFoundException thrown for constraint violations instead of fallback behavior

### Changed

-   **BREAKING**: Route constraints now strictly enforce validation rules
-   **BREAKING**: Routes with `whereNumber('id')` will now throw 404 for non-numeric input instead of matching
-   **BREAKING**: Routes with `whereAlpha('name')` will now throw 404 for non-alphabetic input instead of matching
-   **BREAKING**: Routes with `whereIn('status', ['active', 'inactive'])` will now throw 404 for invalid values instead of matching
-   Extracted common URL generation logic into shared helper methods in UrlGenerator
-   Improved RouteTrie constraint matching logic to prioritize constrained nodes
-   Enhanced TrieNode regex constraint handling with proper forward slash escaping

### Fixed

-   Route constraints now properly enforce validation instead of falling back to unconstrained matches
-   Regex constraints with forward slashes now work correctly (e.g., file extension patterns)
-   Constraint violation edge cases now properly return 404 responses

### Security

-   Route parameter validation is now strictly enforced, preventing potential security issues from invalid input matching

## [0.3.0] – 2025-08-03

### Added

-   BackedEnum support for route names across the entire URL generation system
-   Callable key resolver pattern for secure and flexible signed URL key management
-   Consistent $absolute parameter support across all URL generation methods (route(), to(), asset(), signedRoute())
-   full() method to get complete URL from PSR-7 request
-   Comprehensive URL validation with isValidUrl() method supporting mailto, tel, and sms URI schemes
-   Reserved parameter protection preventing override of 'signature' and 'expires' parameters in signed URLs
-   Flexible expiration type support: DateTimeInterface|DateInterval|int|null for signed URLs
-   Simplified UrlGenerator architecture removing over-engineered container-based services

### Fixed

-   Trailing slash removal across all URL generation methods for clean, consistent URLs
-   Optional parameter handling for routes like /posts/{slug?} now correctly removes trailing slashes
-   URI scheme validation now properly supports modern web standards

### Changed

-   Complete UrlGenerator architectural overhaul to self-contained design
-   Removed complex service dependencies in favor of simple properties and methods
-   Simplified signed URL generation with callable key resolver instead of parameter-based keys
-   Enhanced type safety with BackedEnum integration throughout the system
-   Removed backwards compatibility code for cleaner, more maintainable codebase

### Security

-   Added validation to prevent use of reserved parameters ('signature', 'expires') in signed URL generation
-   Improved signed URL security with callable key resolver pattern

## [0.2.0] – 2025-08-01

### Added

-   Complete comprehensive test suite with 177 tests and 5,099 assertions
-   Added missing `head()` HTTP method support in `HasRouteMethods` trait
-   Enhanced middleware system with advanced priority handling and scoping
-   Added performance benchmarking tests with memory usage monitoring
-   Comprehensive integration tests for real-world API scenarios
-   Added support for conditional middleware with `middlewareWhen()` and `middlewareUnless()`
-   Enhanced route constraint system with `whereIn()`, `whereNumber()`, `whereAlpha()`, and `whereAlphaNumeric()`
-   Added comprehensive edge case handling for malformed URIs and special characters

### Fixed

-   Fixed string response handling to return plain text instead of JSON-encoded strings
-   Fixed HEAD method auto-addition for GET routes
-   Fixed handler resolution exceptions to be thrown at appropriate times
-   Fixed middleware execution order and scoping issues in nested groups
-   Fixed route parameter constraint validation
-   Fixed trailing slash handling in route matching
-   Fixed middleware leakage prevention between routes and groups
-   Fixed boolean and null response type conversion
-   Fixed route identifier generation for uniqueness
-   Fixed memory management and cleanup after requests

### Changed

-   Improved response type handling with consistent string output
-   Enhanced middleware pipeline with better priority management
-   Optimized route matching performance for large route collections
-   Updated test configuration to use correct source directory (`src` instead of `app`)
-   Improved error handling for non-existent controller classes
-   Enhanced route group prefix handling with better slash normalization

### Performance

-   Optimized route registration to handle 10,000+ routes efficiently
-   Improved dispatch performance to handle 100,000+ requests per second
-   Reduced memory footprint for large route collections
-   Enhanced middleware execution performance with minimal overhead

## [0.1.1] – 2025‑07‑26

### Changed

-   Enhanced error handling in `RouteHandlerResolver` for invalid handlers
-   Removed custom constructor from `InvalidHandlerException` for simplicity

## [0.1.0] - 2024-05-27

### Added

-   Initial release
