<?php

declare(strict_types=1);

namespace G\Router\Tests\Fixtures\Controller;

use G\Router\Attribute\{Group, Route};

#[Group('/users', name: 'users.')]
final class UserController
{
    #[Route('', name: 'index')]
    public function index(): void
    {
    }

    #[Route('/{id:i}', name: 'show')]
    #[Route('/{id:i}/profile', name: 'profile')]
    public function show(): void
    {
    }

    #[Route('/{id:i}', methods: ['PUT', 'PATCH'])]
    public function update(): void
    {
    }

    public function helper(): void
    {
    }
}
