<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePDO;

$path = tempnam(sys_get_temp_dir(), 'port-libsqlite-pdo-silent-');
if ($path === false) {
    throw new RuntimeException('Unable to allocate temporary SQLitePDO file');
}

$result = null;
try {
    $pdo = new SQLitePDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT)');
    $pdo->exec("INSERT INTO app_settings (key_name) VALUES ('initial')");
    $statement = $pdo->prepare('INSERT INTO app_settings (key_name) VALUES (?)');
    if (!$statement instanceof PDOStatement) {
        throw new RuntimeException('Expected prepared statement');
    }
    $before = (string) file_get_contents($path);

    $prepareFailure = $pdo->prepare('SELECT * FROM missing_settings');
    $prepareFailureInfo = $pdo->errorInfo();
    $rowsAfterPrepareFailure = $pdo->query('SELECT * FROM app_settings ORDER BY setting_id')->fetchAll();

    $execFailure = $pdo->exec("INSERT INTO app_settings (missing_key) VALUES ('ignored')");
    $execFailureInfo = $pdo->errorInfo();
    $rowsAfterExecFailure = $pdo->query('SELECT * FROM app_settings ORDER BY setting_id')->fetchAll();

    $statementFailure = $statement->execute(['ignored', 'surplus']);
    $statementFailureInfo = $statement->errorInfo();
    $connectionAfterStatementFailure = $pdo->errorInfo();
    $fileUnchangedAfterFailures = hash('sha256', $before) === hash('sha256', (string) file_get_contents($path));
    $rowsAfterStatementFailure = $pdo->query('SELECT * FROM app_settings ORDER BY setting_id')->fetchAll();

    $statementSuccess = $statement->execute(['kept']);
    $result = [
        'scenario' => 'application-pdo-silent-error-parity',
        'applicationUse' => 'Keep silent-mode SQLitePDO failures as strict false returns without mutating a file-backed application settings database.',
        'prepareFailure' => [
            'result_is_false' => $prepareFailure === false,
            'error_info' => $prepareFailureInfo,
            'rows_after_success' => $rowsAfterPrepareFailure,
        ],
        'execFailure' => [
            'result_is_false' => $execFailure === false,
            'error_info' => $execFailureInfo,
            'rows_after_success' => $rowsAfterExecFailure,
        ],
        'statementFailure' => [
            'result_is_false' => $statementFailure === false,
            'statement_error_info' => $statementFailureInfo,
            'connection_error_info' => $connectionAfterStatementFailure,
            'rows_after_success' => $rowsAfterStatementFailure,
        ],
        'statementSuccess' => [
            'result' => $statementSuccess,
            'statement_error_info' => $statement->errorInfo(),
            'rows' => $pdo->query('SELECT * FROM app_settings ORDER BY setting_id')->fetchAll(),
        ],
        'fileUnchangedAfterFailures' => $fileUnchangedAfterFailures,
    ];
} finally {
    if (is_file($path)) {
        unlink($path);
    }
}

if (($argv[1] ?? null) === '--self-test') {
    $passed = $result !== null
        && $result['prepareFailure']['result_is_false'] === true
        && $result['prepareFailure']['error_info'] === ['HY000', 1, 'no such table: missing_settings']
        && $result['execFailure']['result_is_false'] === true
        && $result['execFailure']['error_info'] === ['HY000', 1, 'table app_settings has no column named missing_key']
        && $result['statementFailure']['result_is_false'] === true
        && $result['statementFailure']['statement_error_info'] === ['HY000', 25, 'column index out of range']
        && $result['statementFailure']['connection_error_info'] === ['00000', null, null]
        && $result['statementSuccess']['result'] === true
        && $result['statementSuccess']['statement_error_info'] === ['00000', null, null]
        && array_column($result['statementSuccess']['rows'], 'key_name') === ['initial', 'kept']
        && $result['fileUnchangedAfterFailures'] === true;

    if (!$passed) {
        fwrite(STDERR, "application-pdo-silent-error-parity self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pdo-silent-error-parity self-test passed\n");
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
