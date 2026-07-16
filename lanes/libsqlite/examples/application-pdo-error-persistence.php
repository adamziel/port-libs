<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePDO;

$path = tempnam(sys_get_temp_dir(), 'port-libsqlite-pdo-error-');
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
    $statement = $pdo->prepare('INSERT INTO app_settings (key_name) VALUES (?)');
    $before = (string) file_get_contents($path);

    try {
        $statement->execute(['ignored', 'surplus']);
        throw new RuntimeException('Expected surplus parameter failure');
    } catch (PDOException $exception) {
        $afterFailure = [
            'exception_error_info' => $exception->errorInfo ?? null,
            'connection_error_info' => $pdo->errorInfo(),
            'statement_error_info' => $statement->errorInfo(),
            'file_unchanged' => hash('sha256', $before) === hash('sha256', (string) file_get_contents($path)),
        ];
    }

    $rowsAfterConnectionSuccess = $pdo->query('SELECT * FROM app_settings ORDER BY setting_id')->fetchAll();
    $afterConnectionSuccess = [
        'connection_error_info' => $pdo->errorInfo(),
        'statement_error_info' => $statement->errorInfo(),
        'rows' => $rowsAfterConnectionSuccess,
    ];

    $statement->execute(['kept']);
    $result = [
        'scenario' => 'application-pdo-error-persistence',
        'applicationUse' => 'Keep SQLitePDO statement execution errors scoped to the prepared statement while preserving the file-backed database image for later successful application writes.',
        'afterFailure' => $afterFailure,
        'afterConnectionSuccess' => $afterConnectionSuccess,
        'afterStatementSuccess' => [
            'connection_error_info' => $pdo->errorInfo(),
            'statement_error_info' => $statement->errorInfo(),
            'rows' => $pdo->query('SELECT * FROM app_settings ORDER BY setting_id')->fetchAll(),
        ],
    ];
} finally {
    if (is_file($path)) {
        unlink($path);
    }
}

if (($argv[1] ?? null) === '--self-test') {
    $expectedErrorInfo = ['HY000', 25, 'column index out of range'];
    $passed = $result !== null
        && $result['afterFailure']['exception_error_info'] === $expectedErrorInfo
        && $result['afterFailure']['connection_error_info'] === ['00000', null, null]
        && $result['afterFailure']['statement_error_info'] === $expectedErrorInfo
        && $result['afterFailure']['file_unchanged'] === true
        && $result['afterConnectionSuccess']['connection_error_info'] === ['00000', null, null]
        && $result['afterConnectionSuccess']['statement_error_info'] === $expectedErrorInfo
        && $result['afterStatementSuccess']['statement_error_info'] === ['00000', null, null]
        && array_column($result['afterStatementSuccess']['rows'], 'key_name') === ['initial', 'kept'];

    if (!$passed) {
        fwrite(STDERR, "application-pdo-error-persistence self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pdo-error-persistence self-test passed\n");
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
