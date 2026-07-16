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

$open = static function (string $class, string $path, int $errmode): PDO {
    return new $class('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => $errmode,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
};

return [
    'SQLitePDO invalid DDL matches native prepare exec error state and preserves DML rows' => static function (TestRunner $t) use ($sqlitePdoNativeAvailable, $withScratchDir, $open): void {
        $cases = [
            'exec duplicate table' => [
                'exec',
                'CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT)',
                ['HY000', 1, 'table app_settings already exists'],
                'exec',
            ],
            'exec duplicate table case-insensitive' => [
                'exec',
                'CREATE TABLE APP_SETTINGS (setting_id INTEGER PRIMARY KEY, key_name TEXT)',
                ['HY000', 1, 'table APP_SETTINGS already exists'],
                'exec',
            ],
            'prepare duplicate table' => [
                'prepare',
                'CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT)',
                ['HY000', 1, 'table app_settings already exists'],
                'prepare',
            ],
            'exec malformed ddl token' => [
                'exec',
                'CREATE TABLE invalid_ddl (123bad TEXT)',
                ['HY000', 1, 'unrecognized token: "123bad"'],
                'exec',
            ],
            'prepare malformed ddl token' => [
                'prepare',
                'CREATE TABLE invalid_ddl (123bad TEXT)',
                ['HY000', 1, 'unrecognized token: "123bad"'],
                'prepare',
            ],
            'prepare empty ddl column list' => [
                'prepare',
                'CREATE TABLE empty_ddl ()',
                ['HY000', 1, 'near ")": syntax error'],
                'prepare',
            ],
            'exec incomplete ddl input' => [
                'exec',
                'CREATE TABLE incomplete_ddl (setting_id INTEGER',
                ['HY000', 1, 'incomplete input'],
                'exec',
            ],
        ];
        $initialRows = [['setting_id' => 1, 'key_name' => 'initial']];

        $exercise = static function (string $class, string $path) use ($cases, $open): array {
            $pdo = $open($class, $path, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT)');
            $pdo->exec("INSERT INTO app_settings (key_name) VALUES ('initial')");
            $before = (string) file_get_contents($path);
            $errors = [];

            foreach ($cases as $name => [$operation, $sql, $expectedErrorInfo, $expectedPhase]) {
                $statement = null;
                $phase = $expectedPhase;
                try {
                    if ($operation === 'prepare') {
                        $phase = 'prepare';
                        $statement = $pdo->prepare($sql);
                        $phase = 'execute';
                        if (!$statement instanceof PDOStatement) {
                            throw new RuntimeException("Expected prepared statement for {$name}");
                        }
                        $statement->execute();
                    } else {
                        $pdo->exec($sql);
                    }
                    throw new RuntimeException("Expected PDOException for {$name}");
                } catch (PDOException $exception) {
                    $connectionErrorInfo = $pdo->errorInfo();
                    $statementErrorInfo = $statement instanceof PDOStatement ? $statement->errorInfo() : null;
                    $rowsAfterConnectionSuccess = $pdo
                        ->query('SELECT setting_id, key_name FROM app_settings ORDER BY setting_id')
                        ->fetchAll(PDO::FETCH_ASSOC);
                    $errors[$name] = [
                        'phase' => $phase,
                        'message' => $exception->getMessage(),
                        'exception_error_info' => $exception->errorInfo ?? null,
                        'connection_error_info' => $connectionErrorInfo,
                        'statement_was_created' => $statement instanceof PDOStatement,
                        'statement_error_info' => $statementErrorInfo,
                        'file_unchanged' => hash('sha256', $before) === hash('sha256', (string) file_get_contents($path)),
                        'rows_after_connection_success' => $rowsAfterConnectionSuccess,
                        'success_error_info' => $pdo->errorInfo(),
                        'expected_error_info' => $expectedErrorInfo,
                    ];
                }
            }

            $reopened = $open($class, $path, PDO::ERRMODE_EXCEPTION);

            return [
                'errors' => $errors,
                'rows' => $reopened->query('SELECT setting_id, key_name FROM app_settings ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC),
                'file_unchanged' => hash('sha256', $before) === hash('sha256', (string) file_get_contents($path)),
            ];
        };

        $withScratchDir('sqlite-pdo-ddl-exception-state', static function (string $dir) use ($t, $cases, $exercise, $initialRows, $sqlitePdoNativeAvailable): void {
            $polyfill = $exercise(SQLitePDO::class, $dir . '/polyfill.sqlite');
            $native = $sqlitePdoNativeAvailable() ? $exercise(PDO::class, $dir . '/native.sqlite') : null;

            foreach ($cases as $name => [, , $expectedErrorInfo, $expectedPhase]) {
                $error = $polyfill['errors'][$name];
                $t->same($expectedPhase, $error['phase']);
                $t->contains($expectedErrorInfo[2], $error['message']);
                $t->same($expectedErrorInfo, $error['exception_error_info']);
                $t->same($expectedErrorInfo, $error['connection_error_info']);
                $t->same(false, $error['statement_was_created']);
                $t->same(null, $error['statement_error_info']);
                $t->same(true, $error['file_unchanged']);
                $t->same($initialRows, $error['rows_after_connection_success']);
                $t->same(['00000', null, null], $error['success_error_info']);

                if ($native !== null) {
                    $t->same($native['errors'][$name]['phase'], $error['phase']);
                    $t->same($native['errors'][$name]['exception_error_info'], $error['exception_error_info']);
                    $t->same($native['errors'][$name]['connection_error_info'], $error['connection_error_info']);
                    $t->same($native['errors'][$name]['statement_was_created'], $error['statement_was_created']);
                    $t->same($native['errors'][$name]['rows_after_connection_success'], $error['rows_after_connection_success']);
                    $t->same($native['errors'][$name]['success_error_info'], $error['success_error_info']);
                }
            }

            $t->same($initialRows, $polyfill['rows']);
            $t->same(true, $polyfill['file_unchanged']);
        });
    },

    'SQLitePDO silent DDL failures return false and later DML clears the connection error' => static function (TestRunner $t) use ($sqlitePdoNativeAvailable, $withScratchDir, $open): void {
        $cases = [
            'silent exec duplicate table' => [
                'exec',
                'CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT)',
                ['HY000', 1, 'table app_settings already exists'],
            ],
            'silent prepare duplicate table' => [
                'prepare',
                'CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT)',
                ['HY000', 1, 'table app_settings already exists'],
            ],
            'silent prepare malformed ddl token' => [
                'prepare',
                'CREATE TABLE invalid_ddl (123bad TEXT)',
                ['HY000', 1, 'unrecognized token: "123bad"'],
            ],
            'silent exec incomplete ddl input' => [
                'exec',
                'CREATE TABLE incomplete_ddl (setting_id INTEGER',
                ['HY000', 1, 'incomplete input'],
            ],
        ];

        $exercise = static function (string $class, string $path) use ($cases, $open): array {
            $pdo = $open($class, $path, PDO::ERRMODE_SILENT);
            $pdo->exec('CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT)');
            $pdo->exec("INSERT INTO app_settings (key_name) VALUES ('initial')");
            $errors = [];
            $caseNumber = 0;

            foreach ($cases as $name => $case) {
                [$operation, $sql, $expectedErrorInfo] = $case;
                $beforeFailure = (string) file_get_contents($path);
                $result = $operation === 'prepare' ? $pdo->prepare($sql) : $pdo->exec($sql);
                $errorCode = $pdo->errorCode();
                $errorInfo = $pdo->errorInfo();
                $fileUnchangedAfterFailure = hash('sha256', $beforeFailure) === hash('sha256', (string) file_get_contents($path));
                $rowsAfterConnectionSuccess = $pdo
                    ->query('SELECT setting_id, key_name FROM app_settings ORDER BY setting_id')
                    ->fetchAll(PDO::FETCH_ASSOC);
                $dmlResult = $pdo->exec(
                    "INSERT INTO app_settings (key_name) VALUES ('after-ddl-error-" . $caseNumber . "')",
                );
                $errors[$name] = [
                    'result_is_false' => $result === false,
                    'error_code' => $errorCode,
                    'error_info' => $errorInfo,
                    'file_unchanged_after_failure' => $fileUnchangedAfterFailure,
                    'rows_after_connection_success' => $rowsAfterConnectionSuccess,
                    'dml_result' => $dmlResult,
                    'success_error_info' => $pdo->errorInfo(),
                    'expected_error_info' => $expectedErrorInfo,
                ];
                $caseNumber++;
            }

            $reopened = $open($class, $path, PDO::ERRMODE_SILENT);

            return [
                'errors' => $errors,
                'rows' => $reopened->query('SELECT setting_id, key_name FROM app_settings ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC),
            ];
        };

        $withScratchDir('sqlite-pdo-ddl-silent-state', static function (string $dir) use ($t, $cases, $exercise, $sqlitePdoNativeAvailable): void {
            $polyfill = $exercise(SQLitePDO::class, $dir . '/polyfill.sqlite');
            $native = $sqlitePdoNativeAvailable() ? $exercise(PDO::class, $dir . '/native.sqlite') : null;

            $caseNumber = 0;
            foreach ($cases as $name => [, , $expectedErrorInfo]) {
                $error = $polyfill['errors'][$name];
                $t->same(true, $error['result_is_false']);
                $t->same('HY000', $error['error_code']);
                $t->same($expectedErrorInfo, $error['error_info']);
                $t->same(true, $error['file_unchanged_after_failure']);
                $t->same($caseNumber + 1, count($error['rows_after_connection_success']));
                $t->same(1, $error['dml_result']);
                $t->same(['00000', null, null], $error['success_error_info']);

                if ($native !== null) {
                    $t->same($native['errors'][$name]['result_is_false'], $error['result_is_false']);
                    $t->same($native['errors'][$name]['error_code'], $error['error_code']);
                    $t->same($native['errors'][$name]['error_info'], $error['error_info']);
                    $t->same($native['errors'][$name]['rows_after_connection_success'], $error['rows_after_connection_success']);
                    $t->same($native['errors'][$name]['success_error_info'], $error['success_error_info']);
                }
                $caseNumber++;
            }

            $t->same(
                ['initial', 'after-ddl-error-0', 'after-ddl-error-1', 'after-ddl-error-2', 'after-ddl-error-3'],
                array_column($polyfill['rows'], 'key_name'),
            );
            if ($native !== null) {
                $t->same($native['rows'], $polyfill['rows']);
            }
        });
    },
];
