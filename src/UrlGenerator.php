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
        $routeName = $name instanceof BackedEnum ? $name->value : $name;
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
        $routeName = $name instanceof BackedEnum ? $name->value : $name;
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
        if (!$this->request) {
            throw new RuntimeException('No request set. Call setRequest() first.');
        }
        
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
        $url = $this->formatUrl($this->baseUrl . $path);
        
        if (!empty($query)) {
            $queryString = $this->buildQueryString($query);
            $url .= '?' . $queryString;
        }
        
        if (($secure || $this->secure) && str_starts_with($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }
        
        $this->validateUrl($url);
        return $url;
    }

    public function asset(string $path, ?string $version = null, bool $secure = false, bool $absolute = true): string
    {
        $path = '/' . ltrim($path, '/');
        $baseUrl = $absolute ? $this->getAssetUrl() : '';
        $url = $baseUrl . $path;
        
        $url = $this->formatUrl($url);
        
        if ($version !== null) {
            $url .= '?v=' . $version;
        }
        
        if (($secure || $this->secure) && str_starts_with($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }
        
        $this->validateUrl($url);
        return $url;
    }

    public function full(): string
    {
        if (!$this->request) {
            throw new RuntimeException('No request set. Call setRequest() first.');
        }
        
        return (string) $this->request->getUri();
    }

    public function signedRoute(BackedEnum|string $name, array $parameters = [], DateTimeInterface|DateInterval|int|null $expiration = null, bool $absolute = true): string
    {
        $this->preventUseOfReservedParameters($parameters);

        $routeName = $name instanceof BackedEnum ? $name->value : $name;
        $url = $this->route($routeName, $parameters, $absolute);
        $signingKey = $this->getSigningKey();
        
        $separator = str_contains($url, '?') ? '&' : '?';
        
        if ($expiration !== null) {
            $expirationTimestamp = $this->resolveExpiration($expiration);
            $urlWithExpiry = $url . $separator . 'expires=' . $expirationTimestamp;
            $signature = $this->generateSignature($urlWithExpiry, $signingKey);
            $finalUrl = $urlWithExpiry . '&signature=' . $signature;
        } else {
            $signature = $this->generateSignature($url, $signingKey);
            $finalUrl = $url . $separator . 'signature=' . $signature;
        }
        
        $this->validateUrl($finalUrl);
        return $this->formatUrl($finalUrl);
    }

    public function hasValidSignature(string $url): bool
    {
        $urlParts = parse_url($url);
        parse_str($urlParts['query'] ?? '', $queryParams);
        
        if (!isset($queryParams['signature'])) {
            return false;
        }
        
        $providedSignature = $queryParams['signature'];
        unset($queryParams['signature']);
        
        if (isset($queryParams['expires'])) {
            if (time() > (int) $queryParams['expires']) {
                return false;
            }
        }
        
        $cleanUrl = '';
        if (isset($urlParts['scheme']) && isset($urlParts['host'])) {
            $cleanUrl = $urlParts['scheme'] . '://' . $urlParts['host'];
        }
        $cleanUrl .= $urlParts['path'] ?? '';
        
        if (!empty($queryParams)) {
            $cleanUrl .= '?' . $this->buildQueryString($queryParams);
        }
        
        $signingKey = $this->getSigningKey();
        $expectedSignature = $this->generateSignature($cleanUrl, $signingKey);
        
        return hash_equals($expectedSignature, $providedSignature);
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
}
