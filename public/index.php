<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/bootstrap.php';

use App\Core\Router;

$router = new Router();
require base_path('routes/web.php');
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

