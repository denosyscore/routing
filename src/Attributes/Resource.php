<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Resource
{
    public function __construct(
        public readonly string $name,
        public readonly array $only = [],
        public readonly array $except = [],
        public readonly array $middleware = [],
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getOnly(): array
    {
        return $this->only;
    }

    public function getExcept(): array
    {
        return $this->except;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getDefaultActions(): array
    {
        return [
            'index' => ['GET', ''],
            'create' => ['GET', '/create'],
            'store' => ['POST', ''],
            'show' => ['GET', '/{id}'],
            'edit' => ['GET', '/{id}/edit'],
            'update' => ['PUT', '/{id}'],
            'delete' => ['DELETE', '/{id}']
        ];
    }

    public function getActions(): array
    {
        $actions = $this->getDefaultActions();

        if (!empty($this->only)) {
            $actions = array_intersect_key($actions, array_flip($this->only));
        }

        if (!empty($this->except)) {
            $actions = array_diff_key($actions, array_flip($this->except));
        }

        $result = [];
        foreach ($actions as $actionName => [$method, $path]) {
            $result[$actionName] = [
                'method' => $method,
                'path' => $path
            ];
        }

        return $result;
    }
}
