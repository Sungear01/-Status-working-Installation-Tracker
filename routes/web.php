<?php

declare(strict_types=1);

use App\Controllers\AdminLogController;
use App\Controllers\AdminRoleController;
use App\Controllers\AdminUserController;
use App\Controllers\AuthController;
use App\Controllers\CompanyController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\ErrorPreviewController;
use App\Controllers\ForgotPasswordController;
use App\Controllers\PasswordController;
use App\Controllers\PasswordResetController;
use App\Controllers\ProfileController;
use App\Controllers\NotificationController;
use App\Controllers\SessionController;

$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/forgot-password', [ForgotPasswordController::class, 'show']);
$router->post('/forgot-password', [ForgotPasswordController::class, 'request']);
$router->get('/reset-password', [PasswordResetController::class, 'show']);
$router->post('/reset-password', [PasswordResetController::class, 'update']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);
$router->get('/session-status', [SessionController::class, 'status']);
$router->get('/change-password', [PasswordController::class, 'edit'], ['auth']);
$router->post('/change-password', [PasswordController::class, 'update'], ['auth']);
$router->get('/profile', [ProfileController::class, 'edit'], ['auth']);
$router->post('/profile', [ProfileController::class, 'update'], ['auth']);
$router->get('/notifications', [NotificationController::class, 'index'], ['auth']);

$router->get('/dashboard', [DashboardController::class, 'index'], ['view_dashboard']);
$router->get('/customers', [CustomerController::class, 'index'], ['view_customer']);
$router->get('/customers/export', [CustomerController::class, 'export'], ['export_customer']);
$router->post('/customers', [CustomerController::class, 'store'], ['create_customer']);
$router->get('/customers/{id}', [CustomerController::class, 'show'], ['view_customer']);
$router->post('/customers/{id}', [CustomerController::class, 'update'], ['edit_customer']);
$router->post('/customers/{id}/delete', [CustomerController::class, 'destroy'], ['delete_customer']);

$router->post('/companies', [CompanyController::class, 'store'], ['manage_companies']);
$router->post('/companies/{id}', [CompanyController::class, 'update'], ['manage_companies']);
$router->post('/companies/{id}/delete', [CompanyController::class, 'destroy'], ['manage_companies']);

$router->get('/admin/users', [AdminUserController::class, 'index'], ['manage_users']);
$router->post('/admin/users', [AdminUserController::class, 'store'], ['manage_users']);
$router->post('/admin/users/{id}', [AdminUserController::class, 'update'], ['manage_users']);
$router->post('/admin/users/{id}/toggle', [AdminUserController::class, 'toggle'], ['manage_users']);
$router->post('/admin/users/{id}/reset-password', [AdminUserController::class, 'resetPassword'], ['manage_users']);
$router->post('/admin/users/{id}/send-reset-link', [AdminUserController::class, 'sendResetLink'], ['manage_users']);
$router->post('/admin/users/{id}/unlock', [AdminUserController::class, 'unlock'], ['manage_users']);
$router->get('/admin/roles', [AdminRoleController::class, 'index'], ['manage_users']);
$router->post('/admin/roles/{id}', [AdminRoleController::class, 'update'], ['manage_users']);
$router->get('/admin/logs', [AdminLogController::class, 'index'], ['view_activity_log']);

// Development-only preview. Never expose this route when APP_DEBUG=false.
if (config('app.debug')) {
    $router->get('/error-preview', [ErrorPreviewController::class, 'show']);
}
