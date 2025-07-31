<?php

declare(strict_types=1);

namespace Denosys\Routing\Pool;

use Denosys\Routing\TrieNode;

class TrieNodePool
{
    private array $availableNodes = [];
    private int $poolSize = 0;
    private int $maxPoolSize = 1000;
    private int $totalCreated = 0;
    private int $totalReused = 0;
    private static ?self $instance = null;
    
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }
    
    public function __construct(int $maxPoolSize = 1000)
    {
        $this->maxPoolSize = $maxPoolSize;
    }
    
    public function acquire(): TrieNode
    {
        if ($this->poolSize > 0) {
            $this->poolSize--;
            $this->totalReused++;
            $node = array_pop($this->availableNodes);
            $this->resetNode($node);
            return $node;
        }
        
        $this->totalCreated++;
        return new TrieNode();
    }
    
    public function release(TrieNode $node): void
    {
        if ($this->poolSize >= $this->maxPoolSize) {
            return; // Pool is full, let GC handle it
        }
        
        // Clean up the node before pooling
        $this->resetNode($node);
        
        $this->availableNodes[] = $node;
        $this->poolSize++;
    }
    
    public function releaseMany(array $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof TrieNode) {
                $this->release($node);
            }
        }
    }
    
    private function resetNode(TrieNode $node): void
    {
        // TrieNode properties are public, so we can reset them directly
        $node->route = null;
        $node->children = [];
        $node->paramName = null;
        $node->constraint = null;
        $node->isOptional = false;
        $node->isWildcard = false;
    }
    
    public function preAllocate(int $count): void
    {
        for ($i = 0; $i < $count && $this->poolSize < $this->maxPoolSize; $i++) {
            $this->availableNodes[] = new TrieNode();
            $this->poolSize++;
            $this->totalCreated++;
        }
    }
    
    public function clear(): void
    {
        $this->availableNodes = [];
        $this->poolSize = 0;
    }
    
    public function getStats(): array
    {
        return [
            'pool_size' => $this->poolSize,
            'max_pool_size' => $this->maxPoolSize,
            'total_created' => $this->totalCreated,
            'total_reused' => $this->totalReused,
            'reuse_ratio' => $this->totalCreated > 0 ? $this->totalReused / $this->totalCreated : 0,
            'memory_saved_estimate' => $this->totalReused * 1024 // Rough estimate
        ];
    }
    
    public function warmup(): void
    {
        // Pre-allocate 50% of max pool size
        $this->preAllocate((int)($this->maxPoolSize * 0.5));
    }
}
