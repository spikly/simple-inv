<?php

/** Lazily created shared PDO connection. */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . config('db.host')
                . ';dbname=' . config('db.database')
                . ';charset=' . config('db.charset', 'utf8mb4'),
            config('db.username'),
            config('db.password'),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    return $pdo;
}

function dbRun(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

/** Every matching row, or an empty array. */
function dbAll(string $sql, array $params = []): array
{
    return dbRun($sql, $params)->fetchAll();
}

/** The first matching row, or false. */
function dbRow(string $sql, array $params = [])
{
    return dbRun($sql, $params)->fetch();
}

/** The first column of the first row, or $default when there is no row. */
function dbValue(string $sql, array $params = [], $default = null)
{
    $value = dbRun($sql, $params)->fetchColumn();

    return ($value === false) ? $default : $value;
}

function dbInsert(string $sql, array $params = []): string
{
    dbRun($sql, $params);

    return db()->lastInsertId();
}

/** Run $work inside a transaction, rolling back if it throws. */
function dbTransaction(callable $work)
{
    db()->beginTransaction();

    try {
        $result = $work();
        db()->commit();
    } catch (\Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $e;
    }

    return $result;
}
