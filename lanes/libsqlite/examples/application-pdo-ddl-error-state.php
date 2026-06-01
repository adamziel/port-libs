<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePDO;

$path = tempnam(sys_get_temp_dir(), 'port-libsqlite-pdo-ddl-error-');
if ($path === false) {
    throw new RuntimeException('Unable to allocate temporary SQLitePDO file');
}

$result = null;
try {
    $pdo = new SQLitePDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT)');
    $pdo->exec("INSERT INTO app_settings (key_name) VALUES ('initial')");
    $beforeFailures = (string) file_get_contents($path);

    try {
        $pdo->exec('CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT)');
        throw new RuntimeException('Expected duplicate DDL failure');
    } catch (PDOException $exception) {
        $duplicateFailure = [
            'exception_error_info' => $exception->errorInfo ?? null,
            'connection_error_info' => $pdo->errorInfo(),
        ];
    }

    try {
        $pdo->prepare('CREATE TABLE invalid_ddl (123bad TEXT)');
        throw new RuntimeException('Expected malformed DDL failure');
    } catch (PDOException $exception) {
        $malformedFailure = [
            'exception_error_info' => $exception->errorInfo ?? null,
            'connection_error_info' => $pdo->errorInfo(),
        ];
    }

    $fileUnchangedAfterFailures = hash('sha256', $beforeFailures) === hash('sha256', (string) file_get_contents($path));
    $dmlResult = $pdo->exec("INSERT INTO app_settings (key_name) VALUES ('after-ddl-error')");

    $result = [
        'scenario' => 'application-pdo-ddl-error-state',
        'applicationUse' => 'Reject invalid SQLitePDO DDL without mutating a file-backed application settings table, then allow the next DML write to clear the connection error.',
        'duplicateFailure' => $duplicateFailure,
        'malformedFailure' => $malformedFailure,
        'fileUnchangedAfterFailures' => $fileUnchangedAfterFailures,
        'dmlResult' => $dmlResult,
        'connectionAfterDml' => $pdo->errorInfo(),
        'rows' => $pdo->query('SELECT setting_id, key_name FROM app_settings ORDER BY setting_id')->fetchAll(),
    ];
} finally {
    if (is_file($path)) {
        unlink($path);
    }
}

if (($argv[1] ?? null) === '--self-test') {
    $passed = $result !== null
        && $result['duplicateFailure']['exception_error_info'] === ['HY000', 1, 'table app_settings already exists']
        && $result['duplicateFailure']['connection_error_info'] === ['HY000', 1, 'table app_settings already exists']
        && $result['malformedFailure']['exception_error_info'] === ['HY000', 1, 'unrecognized token: "123bad"']
        && $result['malformedFailure']['connection_error_info'] === ['HY000', 1, 'unrecognized token: "123bad"']
        && $result['fileUnchangedAfterFailures'] === true
        && $result['dmlResult'] === 1
        && $result['connectionAfterDml'] === ['00000', null, null]
        && array_column($result['rows'], 'key_name') === ['initial', 'after-ddl-error'];

    if (!$passed) {
        fwrite(STDERR, "application-pdo-ddl-error-state self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pdo-ddl-error-state self-test passed\n");
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
