<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePDO;

$sqlitePdoNativeAvailable = static fn (): bool => in_array('sqlite', PDO::getAvailableDrivers(), true);

$withScratchDir = static function (string $prefix, callable $callback): mixed {
    $scratchRoot = __DIR__ . '/.sqlite-pdo-tmp';
    if (!is_dir($scratchRoot) && !mkdir($scratchRoot)) {
        throw new RuntimeException("Unable to create temporary test root: {$scratchRoot}");
    }
    chmod($scratchRoot, 0700);
    $dir = $scratchRoot . '/' . $prefix . '-' . bin2hex(random_bytes(6));
    if (!mkdir($dir) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create temporary test directory: {$dir}");
    }
    chmod($dir, 0700);

    try {
        return $callback($dir);
    } finally {
        foreach (glob($dir . '/*.sqlite') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
        if (is_dir($scratchRoot) && (scandir($scratchRoot) ?: ['.', '..']) === ['.', '..']) {
            rmdir($scratchRoot);
        }
    }
};

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

$exercise = static function (string $class, string $storage, int $errmode, ?string $path) use ($warningMessages, $resultKind): array {
    $warnings = [];
    set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
        $warnings[] = ['errno' => $errno, 'message' => $message];

        return true;
    });

    try {
        $dsn = $storage === 'memory' ? 'sqlite::memory:' : 'sqlite:' . $path;
        $pdo = new $class($dsn, null, null, [
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
        $afterConnectionFailure = [
            'result_kind' => $resultKind($connectionResult),
            'exception' => $connectionException instanceof PDOException,
            'exception_error_info' => $connectionException?->errorInfo ?? null,
            'exception_message' => $connectionException?->getMessage(),
            'pdo_error_code' => $pdo->errorCode(),
            'pdo_error_info' => $pdo->errorInfo(),
            'warning_messages' => $warningMessages($warnings),
        ];

        $rowsAfterConnectionSuccess = $pdo->query('SELECT setting_id, key_name FROM app_settings ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC);
        $afterConnectionSuccess = [
            'pdo_error_code' => $pdo->errorCode(),
            'pdo_error_info' => $pdo->errorInfo(),
            'rows' => $rowsAfterConnectionSuccess,
        ];

        $statement = $pdo->prepare('INSERT INTO app_settings (key_name) VALUES (?)');
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Expected prepared statement');
        }
        $statementResult = null;
        $statementException = null;
        try {
            $statementResult = $statement->execute(['ignored', 'surplus']);
        } catch (PDOException $exception) {
            $statementException = $exception;
        }
        $afterStatementFailure = [
            'result' => $statementResult,
            'exception' => $statementException instanceof PDOException,
            'exception_error_info' => $statementException?->errorInfo ?? null,
            'exception_message' => $statementException?->getMessage(),
            'pdo_error_code' => $pdo->errorCode(),
            'pdo_error_info' => $pdo->errorInfo(),
            'statement_error_code' => $statement->errorCode(),
            'statement_error_info' => $statement->errorInfo(),
            'warning_messages' => $warningMessages($warnings),
            'file_unchanged' => $path === null || hash('sha256', (string) $before) === hash('sha256', (string) file_get_contents($path)),
        ];

        $statementSuccess = $statement->execute(['kept']);
        $afterStatementSuccess = [
            'result' => $statementSuccess,
            'pdo_error_code' => $pdo->errorCode(),
            'pdo_error_info' => $pdo->errorInfo(),
            'statement_error_code' => $statement->errorCode(),
            'statement_error_info' => $statement->errorInfo(),
            'rows' => $pdo->query('SELECT setting_id, key_name FROM app_settings ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC),
        ];

        $reopenedRows = null;
        if ($path !== null) {
            $reopened = new $class('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => $errmode,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $reopenedRows = $reopened->query('SELECT setting_id, key_name FROM app_settings ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            'connection_failure' => $afterConnectionFailure,
            'connection_success' => $afterConnectionSuccess,
            'statement_failure' => $afterStatementFailure,
            'statement_success' => $afterStatementSuccess,
            'reopened_rows' => $reopenedRows,
        ];
    } finally {
        restore_error_handler();
    }
};

$modes = [
    'silent' => PDO::ERRMODE_SILENT,
    'warning' => PDO::ERRMODE_WARNING,
    'exception' => PDO::ERRMODE_EXCEPTION,
];

$expectedWarnings = [
    'connection' => 'PDO::prepare(): SQLSTATE[HY000]: General error: 1 no such column: missing_key',
    'statement' => 'PDOStatement::execute(): SQLSTATE[HY000]: General error: 25 column index out of range',
];
$expectedRowsAfterConnection = [['setting_id' => 1, 'key_name' => 'initial']];
$expectedRowsAfterStatement = [
    ['setting_id' => 1, 'key_name' => 'initial'],
    ['setting_id' => 2, 'key_name' => 'kept'],
];

$assertMatrixCase = static function (
    TestRunner $t,
    string $storage,
    string $modeName,
    int $errmode,
) use ($sqlitePdoNativeAvailable, $withScratchDir, $exercise, $expectedWarnings, $expectedRowsAfterConnection, $expectedRowsAfterStatement): void {
    $withScratchDir("sqlite-pdo-error-matrix-{$storage}-{$modeName}", static function (string $dir) use ($t, $storage, $modeName, $errmode, $expectedWarnings, $expectedRowsAfterConnection, $expectedRowsAfterStatement, $exercise, $sqlitePdoNativeAvailable): void {
        $polyfillPath = $storage === 'file' ? $dir . "/polyfill-{$modeName}.sqlite" : null;
        $nativePath = $storage === 'file' ? $dir . "/native-{$modeName}.sqlite" : null;
        $polyfill = $exercise(SQLitePDO::class, $storage, $errmode, $polyfillPath);
        $native = $sqlitePdoNativeAvailable() ? $exercise(PDO::class, $storage, $errmode, $nativePath) : null;

        $expectedConnectionResult = $modeName === 'exception' ? null : false;
        $expectedConnectionException = $modeName === 'exception';
        $expectedStatementResult = $modeName === 'exception' ? null : false;
        $expectedStatementException = $modeName === 'exception';
        $expectedWarningMessages = $modeName === 'warning' ? [$expectedWarnings['connection']] : [];
        $expectedStatementWarningMessages = $modeName === 'warning'
            ? [$expectedWarnings['connection'], $expectedWarnings['statement']]
            : [];

        $t->same($expectedConnectionResult, $polyfill['connection_failure']['result_kind'], "{$storage} {$modeName} connection result");
        $t->same($expectedConnectionException, $polyfill['connection_failure']['exception'], "{$storage} {$modeName} connection exception flag");
        if ($expectedConnectionException) {
            $t->contains('no such column: missing_key', $polyfill['connection_failure']['exception_message'] ?? '', "{$storage} {$modeName} connection exception message");
            $t->same(['HY000', 1, 'no such column: missing_key'], $polyfill['connection_failure']['exception_error_info'], "{$storage} {$modeName} connection exception errorInfo");
        } else {
            $t->same(null, $polyfill['connection_failure']['exception_error_info'], "{$storage} {$modeName} connection exception errorInfo");
        }
        $t->same('HY000', $polyfill['connection_failure']['pdo_error_code'], "{$storage} {$modeName} connection errorCode");
        $t->same(['HY000', 1, 'no such column: missing_key'], $polyfill['connection_failure']['pdo_error_info'], "{$storage} {$modeName} connection errorInfo");
        $t->same($expectedWarningMessages, $polyfill['connection_failure']['warning_messages'], "{$storage} {$modeName} connection warning messages");
        $t->same('00000', $polyfill['connection_success']['pdo_error_code'], "{$storage} {$modeName} connection success code");
        $t->same(['00000', null, null], $polyfill['connection_success']['pdo_error_info'], "{$storage} {$modeName} connection success errorInfo");
        $t->same($expectedRowsAfterConnection, $polyfill['connection_success']['rows'], "{$storage} {$modeName} rows after connection success");

        $t->same($expectedStatementResult, $polyfill['statement_failure']['result'], "{$storage} {$modeName} statement result");
        $t->same($expectedStatementException, $polyfill['statement_failure']['exception'], "{$storage} {$modeName} statement exception flag");
        if ($expectedStatementException) {
            $t->contains('column index out of range', $polyfill['statement_failure']['exception_message'] ?? '', "{$storage} {$modeName} statement exception message");
            $t->same(['HY000', 25, 'column index out of range'], $polyfill['statement_failure']['exception_error_info'], "{$storage} {$modeName} statement exception errorInfo");
        } else {
            $t->same(null, $polyfill['statement_failure']['exception_error_info'], "{$storage} {$modeName} statement exception errorInfo");
        }
        $t->same('00000', $polyfill['statement_failure']['pdo_error_code'], "{$storage} {$modeName} statement connection errorCode");
        $t->same(['00000', null, null], $polyfill['statement_failure']['pdo_error_info'], "{$storage} {$modeName} statement connection errorInfo");
        $t->same('HY000', $polyfill['statement_failure']['statement_error_code'], "{$storage} {$modeName} statement errorCode");
        $t->same(['HY000', 25, 'column index out of range'], $polyfill['statement_failure']['statement_error_info'], "{$storage} {$modeName} statement errorInfo");
        $t->same($expectedStatementWarningMessages, $polyfill['statement_failure']['warning_messages'], "{$storage} {$modeName} statement warning messages");
        $t->same(true, $polyfill['statement_failure']['file_unchanged'], "{$storage} {$modeName} unchanged after failures");
        $t->same(true, $polyfill['statement_success']['result'], "{$storage} {$modeName} statement success result");
        $t->same(['00000', null, null], $polyfill['statement_success']['pdo_error_info'], "{$storage} {$modeName} final connection errorInfo");
        $t->same(['00000', null, null], $polyfill['statement_success']['statement_error_info'], "{$storage} {$modeName} final statement errorInfo");
        $t->same($expectedRowsAfterStatement, $polyfill['statement_success']['rows'], "{$storage} {$modeName} final rows");
        if ($storage === 'file') {
            $t->same($expectedRowsAfterStatement, $polyfill['reopened_rows'], "{$storage} {$modeName} reopened rows");
        } else {
            $t->same(null, $polyfill['reopened_rows'], "{$storage} {$modeName} no reopened memory rows");
        }

        if ($native !== null) {
            $t->same($native['connection_failure']['result_kind'], $polyfill['connection_failure']['result_kind'], "{$storage} {$modeName} native connection result");
            $t->same($native['connection_failure']['exception'], $polyfill['connection_failure']['exception'], "{$storage} {$modeName} native connection exception flag");
            $t->same($native['connection_failure']['exception_error_info'], $polyfill['connection_failure']['exception_error_info'], "{$storage} {$modeName} native connection exception info");
            $t->same($native['connection_failure']['pdo_error_code'], $polyfill['connection_failure']['pdo_error_code'], "{$storage} {$modeName} native connection code");
            $t->same($native['connection_failure']['pdo_error_info'], $polyfill['connection_failure']['pdo_error_info'], "{$storage} {$modeName} native connection info");
            $t->same($native['connection_failure']['warning_messages'], $polyfill['connection_failure']['warning_messages'], "{$storage} {$modeName} native connection warnings");
            $t->same($native['connection_success'], $polyfill['connection_success'], "{$storage} {$modeName} native connection success");
            $t->same($native['statement_failure']['result'], $polyfill['statement_failure']['result'], "{$storage} {$modeName} native statement result");
            $t->same($native['statement_failure']['exception'], $polyfill['statement_failure']['exception'], "{$storage} {$modeName} native statement exception flag");
            $t->same($native['statement_failure']['exception_error_info'], $polyfill['statement_failure']['exception_error_info'], "{$storage} {$modeName} native statement exception info");
            $t->same($native['statement_failure']['pdo_error_info'], $polyfill['statement_failure']['pdo_error_info'], "{$storage} {$modeName} native statement pdo info");
            $t->same($native['statement_failure']['statement_error_info'], $polyfill['statement_failure']['statement_error_info'], "{$storage} {$modeName} native statement info");
            $t->same($native['statement_failure']['warning_messages'], $polyfill['statement_failure']['warning_messages'], "{$storage} {$modeName} native statement warnings");
            $t->same($native['statement_failure']['file_unchanged'], $polyfill['statement_failure']['file_unchanged'], "{$storage} {$modeName} native unchanged flag");
            $t->same($native['statement_success'], $polyfill['statement_success'], "{$storage} {$modeName} native final statement success");
            $t->same($native['reopened_rows'], $polyfill['reopened_rows'], "{$storage} {$modeName} native reopened rows");
        }
    });
};

$tests = [];
foreach (['memory', 'file'] as $storage) {
    foreach ($modes as $modeName => $errmode) {
        $tests["SQLitePDO native error matrix {$storage} {$modeName}"] = static fn (TestRunner $t): mixed => $assertMatrixCase($t, $storage, $modeName, $errmode);
    }
}

return $tests;
