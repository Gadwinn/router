<?php

declare(strict_types=1);

namespace G\Router\Tests\Fixtures\Controller;

use G\Router\Attribute\Route;

abstract class AbstractController
{
    #[Route('/abstract')]
    public function inherited(): void
    {
    }
}
