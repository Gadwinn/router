<?php

declare(strict_types=1);

namespace G\Router\Tests\Fixtures\Controller;

use G\Router\Attribute\Route;

final class HomeController
{
    #[Route('/', name: 'home', options: ['layout' => 'wide'])]
    public function __invoke(): void
    {
    }
}
