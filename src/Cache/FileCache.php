<?php

declare(strict_types=1);

namespace Denosys\Routing\Cache;

class FileCache implements CacheInterface
{
    private ?array $data = null;
    private bool $loaded = false;

    public function __construct(
        private readonly string $cacheFile
    ) {
    }

    public function get(string $key): mixed
    {
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
        $this->loadCache();
        return isset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
        $this->loaded = true;
        $this->persistCache();
    }

    private function loadCache(): void
    {
        if ($this->loaded) {
            return;
        }

        if (file_exists($this->cacheFile)) {
            $contents = file_get_contents($this->cacheFile);
            $decoded = $contents !== false ? json_decode($contents, true) : null;
            $this->data = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($this->data)) {
            $this->data = [];
        }

        $this->loaded = true;
    }

    private function persistCache(): void
    {
        if (!$this->loaded) {
            return;
        }

        file_put_contents($this->cacheFile, json_encode($this->data), LOCK_EX);
    }
}
