<?php
$databaseConfig = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'dbname' => 'minishop',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];

$pdo = null;

try {
    $pdo = new PDO(
        'mysql:host=' . $databaseConfig['host'] . ';port=' . $databaseConfig['port'] . ';dbname=' . $databaseConfig['dbname'] . ';charset=' . $databaseConfig['charset'],
        $databaseConfig['username'],
        $databaseConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    $pdo = null;
}
