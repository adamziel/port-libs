<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePDO;

$warningMessages = static fn (array $warnings): array => array_map(
    static fn (array $warning): string => $warning['message'],
    $warnings,
);

$resultKind = static function (mixed $result): mixed {
    if ($result instanceof PDOStatement) {
        return 'statement';
    }
    if ($result === false) {
        return false;
    }
    if ($result === null) {
        return null;
    }

    return get_debug_type($result);
};

$exercise = static function (string $storage, int $errmode, ?string $path) use ($warningMessages, $resultKind): array {
    $warnings = [];
    set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
        $warnings[] = ['errno' => $errno, 'message' => $message];

        return true;
    });

    try {
        $pdo = new SQLitePDO($storage === 'memory' ? 'sqlite::memory:' : 'sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => $errmode,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT)');
        $pdo->exec("INSERT INTO app_settings (key_name) VALUES ('initial')");
        $before = $path !== null ? (string) file_get_contents($path) : null;

        $connectionResult = null;
        $connectionException = null;
        try {
            $connectionResult = $pdo->prepare('SELECT missing_key FROM app_settings');
        } catch (PDOException $exception) {
            $connectionException = $exception;
        }
        $connectionFailure = [
            'result' => $resultKind($connectionResult),
            'exception_error_info' => $connectionException?->errorInfo ?? null,
            'pdo_error_info' => $pdo->errorInfo(),
            'warnings' => $warningMessages($warnings),
        ];

        $rowsAfterConnectionSuccess = $pdo->query('SELECT setting_id, key_name FROM app_settings ORDER BY setting_id')->fetchAll();
        $statement = $pdo->prepare('INSERT INTO app_settings (key_name) VALUES (?)');
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Expected SQLitePDO prepared statement');
        }

        $statementResult = null;
        $statementException = null;
        try {
            $statementResult = $statement->execute(['ignored', 'surplus']);
        } catch (PDOException $exception) {
            $statementException = $exception;
        }
        $statementFailure = [
            'result' => $statementResult,
            'exception_error_info' => $statementException?->errorInfo ?? null,
            'pdo_error_info' => $pdo->errorInfo(),
            'statement_error_info' => $statement->errorInfo(),
            'warnings' => $warningMessages($warnings),
            'unchanged_after_failure' => $path === null || hash('sha256', (string) $before) === hash('sha256', (string) file_get_contents($path)),
        ];

        $statement->execute(['kept']);
        $rowsAfterStatementSuccess = $pdo->query('SELECT setting_id, key_name FROM app_settings ORDER BY setting_id')->fetchAll();
        $reopenedRows = null;
        if ($path !== null) {
            $reopened = new SQLitePDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => $errmode,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $reopenedRows = $reopened->query('SELECT setting_id, key_name FROM app_settings ORDER BY setting_id')->fetchAll();
        }

        return [
            'connectionFailure' => $connectionFailure,
            'connectionSuccessRows' => $rowsAfterConnectionSuccess,
            'statementFailure' => $statementFailure,
            'statementSuccessRows' => $rowsAfterStatementSuccess,
            'reopenedRows' => $reopenedRows,
        ];
    } finally {
        restore_error_handler();
    }
};

$path = tempnam(sys_get_temp_dir(), 'port-libsqlite-pdo-matrix-');
if ($path === false) {
    throw new RuntimeException('Unable to allocate temporary SQLitePDO file');
}

$result = null;
try {
    $modes = [
        'silent' => PDO::ERRMODE_SILENT,
        'warning' => PDO::ERRMODE_WARNING,
        'exception' => PDO::ERRMODE_EXCEPTION,
    ];
    $matrix = [];
    foreach (['memory', 'file'] as $storage) {
        foreach ($modes as $mode => $errmode) {
            $matrix[$storage][$mode] = $exercise($storage, $errmode, $storage === 'file' ? $path : null);
            if ($storage === 'file') {
                unlink($path);
                $path = tempnam(sys_get_temp_dir(), 'port-libsqlite-pdo-matrix-');
                if ($path === false) {
                    throw new RuntimeException('Unable to allocate temporary SQLitePDO file');
                }
            }
        }
    }

    $result = [
        'scenario' => 'application-pdo-native-error-matrix',
        'applicationUse' => 'Verify SQLitePDO file-backed and in-memory application settings writes keep native PDO error-state behavior across silent, warning, and exception modes.',
        'matrix' => $matrix,
    ];
} finally {
    if (is_file($path)) {
        unlink($path);
    }
}

if (($argv[1] ?? null) === '--self-test') {
    $expectedConnectionError = ['HY000', 1, 'no such column: missing_key'];
    $expectedStatementError = ['HY000', 25, 'column index out of range'];
    $expectedConnectionWarning = 'PDO::prepare(): SQLSTATE[HY000]: General error: 1 no such column: missing_key';
    $expectedStatementWarning = 'PDOStatement::execute(): SQLSTATE[HY000]: General error: 25 column index out of range';
    $expectedInitialRows = [['setting_id' => 1, 'key_name' => 'initial']];
    $expectedFinalRows = [
        ['setting_id' => 1, 'key_name' => 'initial'],
        ['setting_id' => 2, 'key_name' => 'kept'],
    ];

    $passed = $result !== null;
    foreach (['memory', 'file'] as $storage) {
        foreach (['silent', 'warning', 'exception'] as $mode) {
            $case = $result['matrix'][$storage][$mode] ?? null;
            $expectedConnectionResult = $mode === 'exception' ? null : false;
            $expectedStatementResult = $mode === 'exception' ? null : false;
            $expectedConnectionException = $mode === 'exception' ? $expectedConnectionError : null;
            $expectedStatementException = $mode === 'exception' ? $expectedStatementError : null;
            $expectedConnectionWarnings = $mode === 'warning' ? [$expectedConnectionWarning] : [];
            $expectedStatementWarnings = $mode === 'warning' ? [$expectedConnectionWarning, $expectedStatementWarning] : [];

            $passed = $passed
                && is_array($case)
                && $case['connectionFailure']['result'] === $expectedConnectionResult
                && $case['connectionFailure']['exception_error_info'] === $expectedConnectionException
                && $case['connectionFailure']['pdo_error_info'] === $expectedConnectionError
                && $case['connectionFailure']['warnings'] === $expectedConnectionWarnings
                && $case['connectionSuccessRows'] === $expectedInitialRows
                && $case['statementFailure']['result'] === $expectedStatementResult
                && $case['statementFailure']['exception_error_info'] === $expectedStatementException
                && $case['statementFailure']['pdo_error_info'] === ['00000', null, null]
                && $case['statementFailure']['statement_error_info'] === $expectedStatementError
                && $case['statementFailure']['warnings'] === $expectedStatementWarnings
                && $case['statementFailure']['unchanged_after_failure'] === true
                && $case['statementSuccessRows'] === $expectedFinalRows
                && ($storage === 'memory' ? $case['reopenedRows'] === null : $case['reopenedRows'] === $expectedFinalRows);
        }
    }

    if (!$passed) {
        fwrite(STDERR, "application-pdo-native-error-matrix self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pdo-native-error-matrix self-test passed\n");
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
