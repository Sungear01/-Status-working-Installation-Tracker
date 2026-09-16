<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $viewFile = base_path('resources/views/' . $view . '.php');
        if (!is_file($viewFile)) {
            throw new RuntimeException("View not found: {$view}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();
        if ($layout === '') {
            echo $content;
            return;
        }
        require base_path('resources/views/' . $layout . '.php');
    }
}

