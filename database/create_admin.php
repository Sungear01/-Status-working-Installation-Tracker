<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/bootstrap.php';

use App\Core\Database;

if (PHP_SAPI !== 'cli' || $argc < 4) {
    fwrite(STDERR, "Usage: php database/create_admin.php <username> <name> <password>\n");
    exit(1);
}

[$script, $username, $name, $password] = $argv;
if (!preg_match('/^[A-Za-z0-9._-]{3,100}$/', $username) || strlen($password) < 12) {
    fwrite(STDERR, "Username is invalid or password is shorter than 12 characters.\n");
    exit(1);
}

$pdo = Database::connection();
$roleId = $pdo->query("SELECT id FROM roles WHERE slug = 'admin' LIMIT 1")->fetchColumn();
if (!$roleId) {
    fwrite(STDERR, "Run database/schema.sql first.\n");
    exit(1);
}
$statement = $pdo->prepare('INSERT INTO users (role_id, username, name, password, must_change_password) VALUES (:role_id, :username, :name, :password, 1)');
$statement->execute([
    'role_id' => $roleId,
    'username' => $username,
    'name' => $name,
    'password' => password_hash($password, PASSWORD_DEFAULT),
]);
fwrite(STDOUT, "Admin created successfully.\n");
