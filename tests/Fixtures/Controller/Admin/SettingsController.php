<?php

declare(strict_types=1);

namespace G\Router\Tests\Fixtures\Controller\Admin;

use G\Router\Attribute\{Group, Route};

#[Group('/admin/settings')]
final class SettingsController
{
    #[Route('/security', methods: 'POST', name: 'admin.security')]
    public function security(): void
    {
    }
}
