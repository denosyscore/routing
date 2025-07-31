<?php

declare(strict_types=1);

namespace Denosys\Routing;

class Cache
{
    private ?string $cacheFile;
    private ?array $data = null;
    private bool $loaded = false;

    public function __construct(?string $cacheFile = null)
    {
        $this->cacheFile = $cacheFile;
    }

    public function get(string $key): mixed
    {
        if (!$this->cacheFile || !file_exists($this->cacheFile)) {
            return null;
        }

        $this->loadCache();
        return $this->data[$key] ?? null;
    }

    public function has(string $key): bool
    {
        if (!$this->cacheFile || !file_exists($this->cacheFile)) {
            return false;
        }

        $this->loadCache();
        return isset($this->data[$key]);
    }

    public function isEnabled(): bool
    {
        return $this->cacheFile !== null && file_exists($this->cacheFile);
    }

    public function getCacheFile(): ?string
    {
        return $this->cacheFile;
    }

    private function loadCache(): void
    {
        if ($this->loaded || !$this->cacheFile || !file_exists($this->cacheFile)) {
            return;
        }

        $this->data = include $this->cacheFile;
        
        if (!is_array($this->data)) {
            $this->data = [];
        }
        
        $this->loaded = true;
    }
}
