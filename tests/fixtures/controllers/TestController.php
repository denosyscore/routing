<?php

declare(strict_types=1);

namespace Tests\Fixtures\Controllers;

use Denosys\Routing\Attributes\Get;
use Denosys\Routing\Attributes\Post;

class TestController
{
    #[Get('/test', name: 'test.index')]
    public function index()
    {
        return ['message' => 'Test route'];
    }

    #[Post('/test', name: 'test.store')]
    public function store()
    {
        return ['message' => 'Test stored'];
    }
}
