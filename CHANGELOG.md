# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] – 2025-08-03

### Added

- BackedEnum support for route names across the entire URL generation system
- Callable key resolver pattern for secure and flexible signed URL key management
- Consistent $absolute parameter support across all URL generation methods (route(), to(), asset(), signedRoute())
- full() method to get complete URL from PSR-7 request
- Comprehensive URL validation with isValidUrl() method supporting mailto, tel, and sms URI schemes
- Reserved parameter protection preventing override of 'signature' and 'expires' parameters in signed URLs
- Flexible expiration type support: DateTimeInterface|DateInterval|int|null for signed URLs
- Simplified UrlGenerator architecture removing over-engineered container-based services

### Fixed

- Trailing slash removal across all URL generation methods for clean, consistent URLs
- Optional parameter handling for routes like /posts/{slug?} now correctly removes trailing slashes
- URI scheme validation now properly supports modern web standards

### Changed

- Complete UrlGenerator architectural overhaul to self-contained design
- Removed complex service dependencies in favor of simple properties and methods
- Simplified signed URL generation with callable key resolver instead of parameter-based keys
- Enhanced type safety with BackedEnum integration throughout the system
- Removed backwards compatibility code for cleaner, more maintainable codebase

### Security

- Added validation to prevent use of reserved parameters ('signature', 'expires') in signed URL generation
- Improved signed URL security with callable key resolver pattern

## [0.2.0] – 2025-08-01

### Added

- Complete comprehensive test suite with 177 tests and 5,099 assertions
- Added missing `head()` HTTP method support in `HasRouteMethods` trait
- Enhanced middleware system with advanced priority handling and scoping
- Added performance benchmarking tests with memory usage monitoring
- Comprehensive integration tests for real-world API scenarios
- Added support for conditional middleware with `middlewareWhen()` and `middlewareUnless()`
- Enhanced route constraint system with `whereIn()`, `whereNumber()`, `whereAlpha()`, and `whereAlphaNumeric()`
- Added comprehensive edge case handling for malformed URIs and special characters

### Fixed

- Fixed string response handling to return plain text instead of JSON-encoded strings
- Fixed HEAD method auto-addition for GET routes
- Fixed handler resolution exceptions to be thrown at appropriate times
- Fixed middleware execution order and scoping issues in nested groups
- Fixed route parameter constraint validation
- Fixed trailing slash handling in route matching
- Fixed middleware leakage prevention between routes and groups
- Fixed boolean and null response type conversion
- Fixed route identifier generation for uniqueness
- Fixed memory management and cleanup after requests

### Changed

- Improved response type handling with consistent string output
- Enhanced middleware pipeline with better priority management
- Optimized route matching performance for large route collections
- Updated test configuration to use correct source directory (`src` instead of `app`)
- Improved error handling for non-existent controller classes
- Enhanced route group prefix handling with better slash normalization

### Performance

- Optimized route registration to handle 10,000+ routes efficiently
- Improved dispatch performance to handle 100,000+ requests per second
- Reduced memory footprint for large route collections
- Enhanced middleware execution performance with minimal overhead

## [0.1.1] – 2025‑07‑26

### Changed

- Enhanced error handling in `RouteHandlerResolver` for invalid handlers  
- Removed custom constructor from `InvalidHandlerException` for simplicity


## [0.1.0] - 2024-05-27

### Added

- Initial release
