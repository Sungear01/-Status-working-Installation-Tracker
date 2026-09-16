<?php

declare(strict_types=1);

namespace App\Controllers;

use RuntimeException;

/** Development-only controller used to preview the 500 recovery screen. */
final class ErrorPreviewController
{
    public function show(): void
    {
        throw new RuntimeException('Error page preview');
    }
}
