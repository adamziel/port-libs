<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePDO;

$path = tempnam(sys_get_temp_dir(), 'port-libsqlite-pdo-insert-audit-');
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
    $pdo->exec("INSERT INTO app_settings (key_name, key_value) VALUES ('initial', 'kept')");
    $pdo->exec('INSERT INTO app_settings (key_name, key_value) VALUES (?, ?)', ['omitted-value']);
    $beforeInvalid = (string) file_get_contents($path);

    try {
        $pdo->exec('INSERT INTO app_settings (missing_key) VALUES (?)', ['ignored']);
        throw new RuntimeException('Expected invalid target-column failure');
    } catch (PDOException $exception) {
        $invalidTargetColumn = [
            'exception_error_info' => $exception->errorInfo ?? null,
            'connection_error_info' => $pdo->errorInfo(),
            'file_unchanged' => hash('sha256', $beforeInvalid) === hash('sha256', (string) file_get_contents($path)),
        ];
    }

    $rowsAfterInvalidTarget = $pdo->query('SELECT key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll();

    $statement = $pdo->prepare('INSERT INTO app_settings (key_name, key_value) VALUES (?, ?)');
    try {
        $statement->execute([0 => 'ignored', 2 => 'surplus']);
        throw new RuntimeException('Expected sparse surplus parameter failure');
    } catch (PDOException $exception) {
        $sparseSurplus = [
            'exception_error_info' => $exception->errorInfo ?? null,
            'connection_error_info' => $pdo->errorInfo(),
            'statement_error_info' => $statement->errorInfo(),
            'file_unchanged' => hash('sha256', $beforeInvalid) === hash('sha256', (string) file_get_contents($path)),
        ];
    }

    $result = [
        'scenario' => 'application-pdo-invalid-insert-exec-params-audit',
        'applicationUse' => 'Audit parameterized SQLitePDO INSERT behavior for application settings files: omitted values persist as NULL, while invalid target columns and surplus execute parameters keep the file image unchanged.',
        'rowsAfterInvalidTarget' => $rowsAfterInvalidTarget,
        'invalidTargetColumn' => $invalidTargetColumn,
        'sparseSurplus' => $sparseSurplus,
        'rowsAfterAudit' => $pdo->query('SELECT key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll(),
    ];
} finally {
    if (is_file($path)) {
        unlink($path);
    }
}

if (($argv[1] ?? null) === '--self-test') {
    $passed = $result !== null
        && $result['invalidTargetColumn']['exception_error_info'] === ['HY000', 1, 'table app_settings has no column named missing_key']
        && $result['invalidTargetColumn']['connection_error_info'] === ['HY000', 1, 'table app_settings has no column named missing_key']
        && $result['invalidTargetColumn']['file_unchanged'] === true
        && $result['sparseSurplus']['exception_error_info'] === ['HY000', 25, 'column index out of range']
        && $result['sparseSurplus']['connection_error_info'] === ['00000', null, null]
        && $result['sparseSurplus']['statement_error_info'] === ['HY000', 25, 'column index out of range']
        && $result['sparseSurplus']['file_unchanged'] === true
        && $result['rowsAfterAudit'] === [
            ['key_name' => 'initial', 'key_value' => 'kept'],
            ['key_name' => 'omitted-value', 'key_value' => null],
        ];

    if (!$passed) {
        fwrite(STDERR, "application-pdo-invalid-insert-exec-params-audit self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pdo-invalid-insert-exec-params-audit self-test passed\n");
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
