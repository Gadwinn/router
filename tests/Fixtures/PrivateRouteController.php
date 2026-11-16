<?php

declare(strict_types=1);

namespace G\Router\Tests\Fixtures;

use G\Router\Attribute\Route;

final class PrivateRouteController
{
    #[Route('/private')]
    private function hidden(): void // @phpstan-ignore method.unused
    {
    }
}
