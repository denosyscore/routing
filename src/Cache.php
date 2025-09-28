<?php

declare(strict_types=1);

namespace Denosys\Routing;

class Cache
{
    private ?string $cacheFile;
    private ?array $data = null;
    private bool $loaded = false;
    private string $cacheKey;

    public function __construct(?string $cacheFile = null)
    {
        $this->cacheFile = $cacheFile;
        $this->cacheKey = 'denosys_routing_' . md5($cacheFile ?? '');
    }

    public function get(string $key): mixed
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $this->loadCache();
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->loadCache();
        $this->data[$key] = $value;
        $this->persistCache();
    }

    public function has(string $key): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $this->loadCache();
        return isset($this->data[$key]);
    }

    public function isEnabled(): bool
    {
        return $this->cacheFile !== null;
    }

    public function getCacheFile(): ?string
    {
        return $this->cacheFile;
    }

    private function loadCache(): void
    {
        if ($this->loaded) {
            return;
        }

        if (function_exists('apcu_exists') && apcu_exists($this->cacheKey)) {
            $this->data = apcu_fetch($this->cacheKey) ?: [];
            $this->loaded = true;
            return;
        }

        if ($this->cacheFile && file_exists($this->cacheFile)) {
            $contents = file_get_contents($this->cacheFile);
            if ($contents !== false) {
                $this->data = unserialize($contents) ?: [];
            }
        }

        if (!is_array($this->data)) {
            $this->data = [];
        }
        
        $this->loaded = true;
    }

    private function persistCache(): void
    {
        if (!$this->cacheFile || !$this->loaded) {
            return;
        }

        if (function_exists('apcu_store')) {
            apcu_store($this->cacheKey, $this->data, 3600);
        }

        file_put_contents($this->cacheFile, serialize($this->data), LOCK_EX);
    }
}
