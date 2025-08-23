<?php

declare(strict_types=1);

namespace Denosys\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ApiResource extends Resource
{
    public function getDefaultActions(): array
    {
        return [
            'index' => ['GET', ''],
            'store' => ['POST', ''],
            'show' => ['GET', '/{id}'],
            'update' => ['PUT', '/{id}'],
            'delete' => ['DELETE', '/{id}']
        ];
    }
}
