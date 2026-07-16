<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePDO;

$path = tempnam(sys_get_temp_dir(), 'port-libsqlite-pdo-dml-audit-');
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
    $beforeInvalid = (string) file_get_contents($path);

    try {
        $pdo->exec("UPDATE app_settings SET key_value = missing_value || '-suffix' WHERE key_name = 'alpha'");
        throw new RuntimeException('Expected invalid UPDATE expression failure');
    } catch (PDOException $exception) {
        $invalidUpdateExpression = [
            'exception_error_info' => $exception->errorInfo ?? null,
            'connection_error_info' => $pdo->errorInfo(),
            'file_unchanged' => hash('sha256', $beforeInvalid) === hash('sha256', (string) file_get_contents($path)),
        ];
    }

    try {
        $pdo->exec("INSERT OR IGNORE INTO app_settings (missing_key) VALUES ('ignored')");
        throw new RuntimeException('Expected invalid INSERT OR IGNORE target-column failure');
    } catch (PDOException $exception) {
        $invalidConflictInsertTarget = [
            'exception_error_info' => $exception->errorInfo ?? null,
            'connection_error_info' => $pdo->errorInfo(),
            'file_unchanged' => hash('sha256', $beforeInvalid) === hash('sha256', (string) file_get_contents($path)),
        ];
    }

    $statement = $pdo->prepare('DELETE FROM app_settings WHERE key_name = ?');
    try {
        $statement->execute(['alpha', 'surplus']);
        throw new RuntimeException('Expected surplus DELETE parameter failure');
    } catch (PDOException $exception) {
        $surplusDeleteParameter = [
            'exception_error_info' => $exception->errorInfo ?? null,
            'connection_error_info' => $pdo->errorInfo(),
            'statement_error_info' => $statement->errorInfo(),
            'file_unchanged' => hash('sha256', $beforeInvalid) === hash('sha256', (string) file_get_contents($path)),
        ];
    }

    try {
        $pdo->exec('UPDATE app_settings SET key_value = ? WHERE key_name = ?', ['changed', 'alpha', 'surplus']);
        throw new RuntimeException('Expected surplus UPDATE exec-parameter failure');
    } catch (PDOException $exception) {
        $surplusUpdateExecParameter = [
            'exception_error_info' => $exception->errorInfo ?? null,
            'connection_error_info' => $pdo->errorInfo(),
            'file_unchanged' => hash('sha256', $beforeInvalid) === hash('sha256', (string) file_get_contents($path)),
        ];
    }

    $conflictUpdate = $pdo->prepare('UPDATE OR REPLACE app_settings SET key_value = ? WHERE key_name = ?');
    $conflictUpdateResult = $conflictUpdate->execute(['changed-after-audit', 'alpha']);

    $result = [
        'scenario' => 'application-pdo-invalid-dml-native-parity-audit',
        'applicationUse' => 'Audit invalid SQLitePDO UPDATE and DELETE paths for application settings files: native-style errors are reported before any file-backed row mutation.',
        'invalidUpdateExpression' => $invalidUpdateExpression,
        'invalidConflictInsertTarget' => $invalidConflictInsertTarget,
        'surplusDeleteParameter' => $surplusDeleteParameter,
        'surplusUpdateExecParameter' => $surplusUpdateExecParameter,
        'conflictUpdateResult' => $conflictUpdateResult,
        'conflictUpdateRowCount' => $conflictUpdate->rowCount(),
        'conflictUpdateErrorInfo' => $conflictUpdate->errorInfo(),
        'rowsAfterAudit' => $pdo->query('SELECT key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll(),
    ];
} finally {
    if (is_file($path)) {
        unlink($path);
    }
}

if (($argv[1] ?? null) === '--self-test') {
    $passed = $result !== null
        && $result['invalidUpdateExpression']['exception_error_info'] === ['HY000', 1, 'no such column: missing_value']
        && $result['invalidUpdateExpression']['connection_error_info'] === ['HY000', 1, 'no such column: missing_value']
        && $result['invalidUpdateExpression']['file_unchanged'] === true
        && $result['invalidConflictInsertTarget']['exception_error_info'] === ['HY000', 1, 'table app_settings has no column named missing_key']
        && $result['invalidConflictInsertTarget']['connection_error_info'] === ['HY000', 1, 'table app_settings has no column named missing_key']
        && $result['invalidConflictInsertTarget']['file_unchanged'] === true
        && $result['surplusDeleteParameter']['exception_error_info'] === ['HY000', 25, 'column index out of range']
        && $result['surplusDeleteParameter']['connection_error_info'] === ['00000', null, null]
        && $result['surplusDeleteParameter']['statement_error_info'] === ['HY000', 25, 'column index out of range']
        && $result['surplusDeleteParameter']['file_unchanged'] === true
        && $result['surplusUpdateExecParameter']['exception_error_info'] === ['HY000', 25, 'column index out of range']
        && $result['surplusUpdateExecParameter']['connection_error_info'] === ['HY000', 25, 'column index out of range']
        && $result['surplusUpdateExecParameter']['file_unchanged'] === true
        && $result['conflictUpdateResult'] === true
        && $result['conflictUpdateRowCount'] === 1
        && $result['conflictUpdateErrorInfo'] === ['00000', null, null]
        && $result['rowsAfterAudit'] === [
            ['key_name' => 'alpha', 'key_value' => 'changed-after-audit'],
            ['key_name' => 'beta', 'key_value' => 'kept-too'],
        ];

    if (!$passed) {
        fwrite(STDERR, "application-pdo-invalid-dml-native-parity-audit self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pdo-invalid-dml-native-parity-audit self-test passed\n");
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
