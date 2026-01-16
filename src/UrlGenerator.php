<?php

declare(strict_types=1);

namespace Denosys\Routing;

use InvalidArgumentException;
use RuntimeException;
use Psr\Http\Message\ServerRequestInterface;
use Denosys\Routing\Exceptions\RouteNotFoundException;
use DateTimeInterface;
use DateInterval;
use BackedEnum;

class UrlGenerator implements UrlGeneratorInterface
{
    protected string $baseUrl = '';
    protected string $assetUrl = '';
    protected bool $secure = false;
    protected ?ServerRequestInterface $request = null;
    protected $keyResolver = null;
    protected ?string $intendedUrl = null;
    protected ?string $previousUrl = null;

    public function __construct(
        protected RouteCollectionInterface $routeCollection
    ) {
    }

    public function route(BackedEnum|string $name, array $parameters = [], bool $absolute = true): string
    {
        $routeName = $this->resolveRouteName($name);
        $route = $this->routeCollection->findByName($routeName);
        
        if (!$route) {
            throw new RouteNotFoundException($routeName);
        }

        $pattern = $route->getPattern();
        $requiredParams = $this->extractRequiredParameters($pattern);
        
        foreach ($requiredParams as $param) {
            if (!isset($parameters[$param])) {
                throw new InvalidArgumentException(
                    "Missing required parameter [{$param}] for route [{$routeName}]"
                );
            }
        }

        $url = $this->replaceParameters($pattern, $parameters);
        $url = $this->encodeUrl($url);
        
        $finalUrl = $absolute ? $this->baseUrl . $url : $url;
        $this->validateUrl($finalUrl);
        return $this->formatUrl($finalUrl);
    }

    public function hasRoute(BackedEnum|string $name): bool
    {
        $routeName = $this->resolveRouteName($name);
        return $this->routeCollection->findByName($routeName) !== null;
    }

    public function setBaseUrl(string $baseUrl): static
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        return $this;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setAssetUrl(string $assetUrl): static
    {
        $this->assetUrl = rtrim($assetUrl, '/');
        return $this;
    }

    public function getAssetUrl(): string
    {
        return $this->assetUrl ?: $this->baseUrl;
    }

    public function setSecure(bool $secure = true): static
    {
        $this->secure = $secure;
        return $this;
    }

    public function setRequest(?ServerRequestInterface $request): static
    {
        $this->request = $request;
        return $this;
    }

    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    public function current(bool $includeQuery = true): string
    {
        $this->requiresRequest();
        
        $uri = $this->request->getUri();
        $url = (string) $uri->withFragment('');
        
        if (!$includeQuery) {
            $url = (string) $uri->withQuery('')->withFragment('');
        }
        
        return $url;
    }

    public function previous(?string $fallback = null): string
    {
        if ($this->previousUrl) {
            return $this->previousUrl;
        }
        
        if ($this->request) {
            $referer = $this->request->getHeaderLine('Referer');
            if ($referer) {
                return $referer;
            }
        }
        
        return $fallback ?: $this->current();
    }

    public function to(string $path, array $query = [], bool $secure = false): string
    {
        $path = '/' . ltrim($path, '/');
        $url = $this->buildUrl($path, $query, $secure, absolute: true);
        
        $this->validateUrl($url);
        return $url;
    }

    public function asset(string $path, ?string $version = null, bool $secure = false, bool $absolute = true): string
    {
        $path = '/' . ltrim($path, '/');
        $query = $version !== null ? ['v' => $version] : [];
        
        if ($absolute) {
            $assetUrl = $this->getAssetUrl() . $path;
            $assetUrl = $this->formatUrl($assetUrl);
            
            if (!empty($query)) {
                $queryString = $this->buildQueryString($query);
                $assetUrl .= '?' . $queryString;
            }
            
            if (($secure || $this->secure) && str_starts_with($assetUrl, 'http://')) {
                $assetUrl = 'https://' . substr($assetUrl, 7);
            }
            
            $this->validateUrl($assetUrl);
            return $assetUrl;
        }
        
        $url = $this->buildUrl($path, $query, $secure, absolute: false);
        $this->validateUrl($url);
        return $url;
    }

    public function full(): string
    {
        $this->requiresRequest();
        
        return (string) $this->request->getUri();
    }

    public function signedRoute(
        BackedEnum|string $name, 
        array $parameters = [], 
        DateTimeInterface|DateInterval|int|null $expiration = null, 
        bool $absolute = true
    ): string {
        $this->preventUseOfReservedParameters($parameters);

        $routeName = $this->resolveRouteName($name);
        $url = $this->route($routeName, $parameters, $absolute);
        $signingKey = $this->getSigningKey();
        
        // Add expiration if provided
        if ($expiration !== null) {
            $expirationTimestamp = $this->resolveExpiration($expiration);
            $separator = str_contains($url, '?') ? '&' : '?';
            $url = $url . $separator . 'expires=' . $expirationTimestamp;
        }
        
        // Normalize the URL before signing
        $normalizedUrl = $this->normalizeUrlForSigning($url);
        $signature = $this->generateSignature($normalizedUrl, $signingKey);
        
        // Add signature to the original URL (not the normalized one)
        $separator = str_contains($url, '?') ? '&' : '?';
        $finalUrl = $url . $separator . 'signature=' . urlencode($signature);
        
        $this->validateUrl($finalUrl);
        return $this->formatUrl($finalUrl);
    }

    public function hasValidSignature(string $url): bool
    {
        // Quick check for signature parameter existence
        if (!str_contains($url, 'signature=')) {
            return false;
        }
        
        // Parse to check expiration
        $urlParts = parse_url($url);
        parse_str($urlParts['query'] ?? '', $queryParams);
        
        if (!isset($queryParams['signature'])) {
            return false;
        }
        
        // Check expiration first (before expensive signature calculation)
        if (isset($queryParams['expires'])) {
            if (time() > (int) $queryParams['expires']) {
                return false;
            }
        }
        
        // Store the provided signature
        $providedSignature = $queryParams['signature'];
        
        // Normalize the URL for signature verification
        // This will automatically remove the signature parameter
        $normalizedUrl = $this->normalizeUrlForSigning($url);
        
        // Generate expected signature from normalized URL
        $signingKey = $this->getSigningKey();
        $expectedSignature = $this->generateSignature($normalizedUrl, $signingKey);
        
        // Use timing-safe comparison
        return hash_equals($expectedSignature, $providedSignature);
    }

    public function temporarySignedRoute(
        BackedEnum|string $name, 
        DateTimeInterface|DateInterval|int $expiration, 
        array $parameters = [], 
        bool $absolute = true
    ): string {
        // This is just a convenience method that enforces expiration
        return $this->signedRoute($name, $parameters, $expiration, $absolute);
    }

    public function hasValidSignatureWhileIgnoringExpiry(string $url): bool
    {
        // Parse the URL to get components
        $urlParts = parse_url($url);
        parse_str($urlParts['query'] ?? '', $queryParams);
        
        if (!isset($queryParams['signature'])) {
            return false;
        }
        
        // Store the provided signature
        $providedSignature = $queryParams['signature'];
        
        // Normalize the URL for signature verification
        $normalizedUrl = $this->normalizeUrlForSigning($url);
        
        // Generate expected signature from normalized URL
        $signingKey = $this->getSigningKey();
        $expectedSignature = $this->generateSignature($normalizedUrl, $signingKey);
        
        // Use timing-safe comparison
        return hash_equals($expectedSignature, $providedSignature);
    }

    public function hasCorrectSignature(string $url): bool
    {
        // Alias for backward compatibility or semantic clarity
        return $this->hasValidSignatureWhileIgnoringExpiry($url);
    }

    public function signatureHasNotExpired(string $url): bool
    {
        $urlParts = parse_url($url);
        parse_str($urlParts['query'] ?? '', $queryParams);
        
        if (!isset($queryParams['expires'])) {
            // No expiration means it never expires
            return true;
        }
        
        return time() <= (int) $queryParams['expires'];
    }

    protected function normalizeUrlForSigning(string $url): string
    {
        $parts = parse_url($url);
        $normalized = '';
        
        if (isset($parts['scheme'], $parts['host'])) {
            $normalized = $parts['scheme'] . '://' . $parts['host'];
            if (isset($parts['port'])) {
                $normalized .= ':' . $parts['port'];
            }
        }
        
        if (isset($parts['path'])) {
            $segments = explode('/', $parts['path']);
            $encodedSegments = array_map(function($segment) {
                return $segment === '' ? $segment : rawurlencode(rawurldecode($segment));
            }, $segments);
            $normalized .= implode('/', $encodedSegments);
        }
        
        if (isset($parts['query'])) {
            parse_str($parts['query'], $params);
            unset($params['signature']); // Remove signature if present
            
            if (!empty($params)) {
                ksort($params);
                $normalized .= '?' . $this->buildQueryString($params);
            }
        }
        
        return $this->formatUrl($normalized);
    }

    public function setKeyResolver(?callable $keyResolver): static
    {
        $this->keyResolver = $keyResolver;
        return $this;
    }

    protected function getSigningKey(): string
    {
        if ($this->keyResolver === null) {
            throw new RuntimeException('No key resolver set. Call setKeyResolver() first.');
        }
        
        return ($this->keyResolver)();
    }

    protected function resolveExpiration(DateTimeInterface|DateInterval|int $expiration): int
    {
        if ($expiration instanceof DateTimeInterface) {
            return $expiration->getTimestamp();
        }
        
        if ($expiration instanceof DateInterval) {
            $now = new \DateTime();
            return $now->add($expiration)->getTimestamp();
        }
        
        return $expiration;
    }

    protected function preventUseOfReservedParameters(array $parameters): void
    {
        $reservedParameters = ['signature', 'expires'];

        foreach ($reservedParameters as $reserved) {
            if (array_key_exists($reserved, $parameters)) {
                throw new InvalidArgumentException(
                    "Parameter '{$reserved}' is reserved for signed URLs and cannot be used in route parameters"
                );
            }
        }
    }

    public function setIntendedUrl(string $url): static
    {
        $this->intendedUrl = $url;
        return $this;
    }

    public function getIntendedUrl(): ?string
    {
        return $this->intendedUrl;
    }

    public function setPreviousUrl(string $url): static
    {
        $this->previousUrl = $url;
        return $this;
    }

    public function getPreviousUrl(): ?string
    {
        return $this->previousUrl;
    }

    protected function extractRequiredParameters(string $pattern): array
    {
        preg_match_all('/\{([^}?]+)\}/', $pattern, $matches);
        return $matches[1] ?? [];
    }

    protected function replaceParameters(string $pattern, array $parameters): string
    {
        foreach ($parameters as $key => $value) {
            $pattern = str_replace('{' . $key . '}', (string) $value, $pattern);
            $pattern = str_replace('{' . $key . '?}', (string) $value, $pattern);
        }
        
        // Handle optional parameters that weren't provided
        $pattern = preg_replace('/\/\{[^}]*\?\}/', '', $pattern);
        
        return $pattern;
    }

    protected function encodeUrl(string $url): string
    {
        $segments = explode('/', $url);

        $encodedSegments = array_map(function ($segment) {
            if ($segment === '') {
                return $segment;
            }
            return rawurlencode($segment);
        }, $segments);
        
        return implode('/', $encodedSegments);
    }

    protected function generateSignature(string $url, string $key): string
    {
        return hash_hmac('sha256', $url, $key);
    }

    protected function buildQueryString(array $query): string
    {
        $parts = [];
        
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $parts[] = urlencode((string) $key) . '=' . urlencode((string) $item);
                }
            } else {
                $parts[] = urlencode((string) $key) . '=' . urlencode((string) $value);
            }
        }
        
        return implode('&', $parts);
    }

    public function isValidUrl(string $url): bool
    {
        if (preg_match('/^(mailto|tel|sms):/i', $url)) {
            return !empty(explode(':', $url, 2)[1]);
        }
        
        if (str_starts_with($url, '/') || str_starts_with($url, '?') || str_starts_with($url, '#')) {
            return true;
        }
        
        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            // Additional check for common invalid cases
            return !str_contains($url, ' ') && !str_contains($url, '[') && !str_contains($url, ']');
        }
        
        return false;
    }

    protected function validateUrl(string $url): void
    {
        if (!$this->isValidUrl($url)) {
            throw new InvalidArgumentException("Invalid URL format: {$url}");
        }
    }

    protected function formatUrl(string $url): string
    {
        if (strlen($url) > 1) {
            $url = rtrim($url, '/');
        }
        return $url;
    }

    protected function resolveRouteName(BackedEnum|string $name): string
    {
        return $name instanceof BackedEnum ? $name->value : $name;
    }

    protected function requiresRequest(): void
    {
        if (!$this->request) {
            throw new RuntimeException('No request set. Call setRequest() first.');
        }
    }

    protected function buildUrl(string $path, array $query = [], bool $secure = false, bool $absolute = true): string
    {
        $url = $absolute ? $this->baseUrl . $path : $path;
        $url = $this->formatUrl($url);

        if (!empty($query)) {
            $queryString = $this->buildQueryString($query);
            $url .= '?' . $queryString;
        }

        if (($secure || $this->secure) && str_starts_with($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }

        return $url;
    }

    public function debugSignature(string $url): array
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $queryParams);
        
        $normalizedUrl = $this->normalizeUrlForSigning($url);
        $signingKey = $this->getSigningKey();
        $expectedSignature = $this->generateSignature($normalizedUrl, $signingKey);
        
        return [
            'original_url' => $url,
            'normalized_url' => $normalizedUrl,
            'provided_signature' => $queryParams['signature'] ?? null,
            'expected_signature' => $expectedSignature,
            'signatures_match' => hash_equals($expectedSignature, $queryParams['signature'] ?? ''),
            'expires' => $queryParams['expires'] ?? null,
            'is_expired' => isset($queryParams['expires']) ? time() > (int)$queryParams['expires'] : false,
        ];
    }
}
