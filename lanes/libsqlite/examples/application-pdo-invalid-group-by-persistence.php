<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePDO;

$path = tempnam(sys_get_temp_dir(), 'port-libsqlite-pdo-group-by-');
if ($path === false) {
    throw new RuntimeException('Unable to allocate temporary SQLitePDO file');
}

$result = null;
try {
    $pdo = new SQLitePDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)');
    $pdo->exec("INSERT INTO app_settings (key_name, key_value) VALUES ('alpha', 'kept')");
    $pdo->exec("INSERT INTO app_settings (key_name, key_value) VALUES ('beta', 'kept-too')");
    $before = (string) file_get_contents($path);

    try {
        $pdo->prepare('SELECT key_name FROM app_settings GROUP BY missing_key');
        throw new RuntimeException('Expected invalid GROUP BY column failure');
    } catch (PDOException $exception) {
        $afterFailure = [
            'exception_error_info' => $exception->errorInfo ?? null,
            'connection_error_info' => $pdo->errorInfo(),
            'file_unchanged' => hash('sha256', $before) === hash('sha256', (string) file_get_contents($path)),
        ];
    }

    $rowsAfterSuccess = $pdo->query('SELECT setting_id, key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll();
    $reopened = new SQLitePDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $result = [
        'scenario' => 'application-pdo-invalid-group-by-persistence',
        'applicationUse' => 'Reject invalid grouped application reports with native-style PDO errorInfo while keeping the file-backed SQLite image available for later successful reads.',
        'afterFailure' => $afterFailure,
        'afterSuccess' => [
            'connection_error_info' => $pdo->errorInfo(),
            'rows' => $rowsAfterSuccess,
        ],
        'reopenedRows' => $reopened->query('SELECT setting_id, key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll(),
    ];
} finally {
    if (is_file($path)) {
        unlink($path);
    }
}

if (($argv[1] ?? null) === '--self-test') {
    $expectedRows = [
        ['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'kept'],
        ['setting_id' => 2, 'key_name' => 'beta', 'key_value' => 'kept-too'],
    ];
    $expectedErrorInfo = ['HY000', 1, 'no such column: missing_key'];
    $passed = $result !== null
        && $result['afterFailure']['exception_error_info'] === $expectedErrorInfo
        && $result['afterFailure']['connection_error_info'] === $expectedErrorInfo
        && $result['afterFailure']['file_unchanged'] === true
        && $result['afterSuccess']['connection_error_info'] === ['00000', null, null]
        && $result['afterSuccess']['rows'] === $expectedRows
        && $result['reopenedRows'] === $expectedRows;

    if (!$passed) {
        fwrite(STDERR, "application-pdo-invalid-group-by-persistence self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pdo-invalid-group-by-persistence self-test passed\n");
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
