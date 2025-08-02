<?php

declare(strict_types=1);

namespace Denosys\Routing;

interface UrlGeneratorInterface
{
    public function route(string $name, array $parameters = []): string;
    public function hasRoute(string $name): bool;
    public function setBaseUrl(string $baseUrl): void;
    public function getBaseUrl(): string;
}
