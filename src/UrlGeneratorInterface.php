<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Psr\Http\Message\ServerRequestInterface;
use DateTimeInterface;
use DateInterval;
use BackedEnum;

interface UrlGeneratorInterface
{
    public function route(BackedEnum|string $name, array $parameters = [], bool $absolute = true): string;
    public function hasRoute(BackedEnum|string $name): bool;
    public function setBaseUrl(string $baseUrl): static;
    public function getBaseUrl(): string;
    public function setAssetUrl(string $assetUrl): static;
    public function getAssetUrl(): string;
    public function setSecure(bool $secure = true): static;
    public function setRequest(?ServerRequestInterface $request): static;
    public function getRequest(): ?ServerRequestInterface;
    public function current(bool $includeQuery = true): string;
    public function previous(?string $fallback = null): string;
    public function to(string $path, array $query = [], bool $secure = false): string;
    public function asset(string $path, ?string $version = null, bool $secure = false, bool $absolute = true): string;
    public function full(): string;
    public function signedRoute(BackedEnum|string $name, array $parameters = [], DateTimeInterface|DateInterval|int|null $expiration = null, bool $absolute = true): string;
    public function hasValidSignature(string $url): bool;
    public function setKeyResolver(?callable $keyResolver): static;
    public function isValidUrl(string $url): bool;
    public function setIntendedUrl(string $url): static;
    public function getIntendedUrl(): ?string;
    public function setPreviousUrl(string $url): static;
    public function getPreviousUrl(): ?string;
}
