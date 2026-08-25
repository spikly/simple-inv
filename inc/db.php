<?php

/**
 * Lazily created shared PDO connection.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = require __DIR__ . '/../config/user.config.php';
        $db = $config['db'];

        $pdo = new PDO(
            'mysql:host=' . $db['host'] . ';dbname=' . $db['database'] . ';charset=' . $db['charset'],
            $db['username'],
            $db['password'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    return $pdo;
}

/**
 * Prepare and execute a statement.
 */
function dbRun(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

/**
 * Every matching row, or an empty array.
 */
function dbAll(string $sql, array $params = []): array
{
    return dbRun($sql, $params)->fetchAll();
}

/**
 * The first matching row, or false.
 */
function dbRow(string $sql, array $params = [])
{
    return dbRun($sql, $params)->fetch();
}

/**
 * Run an INSERT and return the new row's id.
 */
function dbInsert(string $sql, array $params = []): string
{
    dbRun($sql, $params);

    return db()->lastInsertId();
}
