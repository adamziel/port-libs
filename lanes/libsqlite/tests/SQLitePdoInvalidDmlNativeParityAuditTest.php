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

$open = static function (string $class, string $path): PDO {
    return new $class('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
};

return [
    'SQLitePDO invalid UPDATE and DELETE DML match native prepare exec and parameter error parity' => static function (TestRunner $t) use ($sqlitePdoNativeAvailable, $withScratchDir, $open): void {
        if (!$sqlitePdoNativeAvailable()) {
            return;
        }

        $cases = [
            'prepare update expression missing column' => [
                'prepare',
                "UPDATE app_settings SET key_value = missing_value || '-suffix' WHERE key_name = ?",
                ['alpha'],
                ['HY000', 1, 'no such column: missing_value'],
                'prepare',
                'connection',
                true,
            ],
            'exec update expression missing column' => [
                'exec',
                "UPDATE app_settings SET key_value = missing_value || '-suffix' WHERE key_name = 'alpha'",
                [],
                ['HY000', 1, 'no such column: missing_value'],
                'exec',
                'connection',
                true,
            ],
            'prepare insert expression missing column' => [
                'prepare',
                "INSERT INTO app_settings (key_name) VALUES (missing_value || '-suffix')",
                [],
                ['HY000', 1, 'no such column: missing_value'],
                'prepare',
                'connection',
                true,
            ],
            'exec insert expression missing column' => [
                'exec',
                "INSERT INTO app_settings (key_name) VALUES (missing_value || '-suffix')",
                [],
                ['HY000', 1, 'no such column: missing_value'],
                'exec',
                'connection',
                true,
            ],
            'exec insert invalid target column' => [
                'exec',
                "INSERT INTO app_settings (missing_key) VALUES ('Janet')",
                [],
                ['HY000', 1, 'table app_settings has no column named missing_key'],
                'exec',
                'connection',
                true,
            ],
            'prepare update invalid numbered parameter expression' => [
                'prepare',
                'UPDATE app_settings SET key_value = ?0 + 1 WHERE key_name = ?',
                ['alpha'],
                ['HY000', 1, 'variable number must be between ?1 and ?32766'],
                'prepare',
                'connection',
                true,
            ],
            'exec update invalid numbered parameter expression' => [
                'exec',
                "UPDATE app_settings SET key_value = ?0 + 1 WHERE key_name = 'alpha'",
                [],
                ['HY000', 1, 'variable number must be between ?1 and ?32766'],
                'exec',
                'connection',
                true,
            ],
            'prepare delete complex predicate missing column' => [
                'prepare',
                "DELETE FROM app_settings WHERE missing_key || '-suffix' = ?",
                ['alpha-suffix'],
                ['HY000', 1, 'no such column: missing_key'],
                'prepare',
                'connection',
                true,
            ],
            'exec delete complex predicate missing column' => [
                'exec',
                "DELETE FROM app_settings WHERE missing_key || '-suffix' = 'alpha-suffix'",
                [],
                ['HY000', 1, 'no such column: missing_key'],
                'exec',
                'connection',
                true,
            ],
            'prepared update surplus parameter' => [
                'prepared-execute',
                'UPDATE app_settings SET key_value = ? WHERE key_name = ?',
                ['changed', 'alpha', 'surplus'],
                ['HY000', 25, 'column index out of range'],
                'execute',
                'statement',
                true,
            ],
            'prepared delete surplus parameter' => [
                'prepared-execute',
                'DELETE FROM app_settings WHERE key_name = ?',
                ['alpha', 'surplus'],
                ['HY000', 25, 'column index out of range'],
                'execute',
                'statement',
                true,
            ],
            'exec update surplus parameter helper' => [
                'exec-params',
                'UPDATE app_settings SET key_value = ? WHERE key_name = ?',
                ['changed', 'alpha', 'surplus'],
                ['HY000', 25, 'column index out of range'],
                'exec',
                'connection',
                false,
            ],
            'exec delete surplus parameter helper' => [
                'exec-params',
                'DELETE FROM app_settings WHERE key_name = ?',
                ['alpha', 'surplus'],
                ['HY000', 25, 'column index out of range'],
                'exec',
                'connection',
                false,
            ],
        ];

        $initialRows = [
            ['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'kept'],
            ['setting_id' => 2, 'key_name' => 'beta', 'key_value' => 'kept-too'],
        ];

        $exercise = static function (string $class, string $path, bool $includePolyfillExecParams) use ($cases, $open): array {
            $pdo = $open($class, $path);
            $pdo->exec('CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)');
            $pdo->exec("INSERT INTO app_settings (key_name, key_value) VALUES ('alpha', 'kept')");
            $pdo->exec("INSERT INTO app_settings (key_name, key_value) VALUES ('beta', 'kept-too')");
            $before = (string) file_get_contents($path);
            $errors = [];

            foreach ($cases as $name => [$operation, $sql, $parameters, $expectedErrorInfo, $expectedPhase, $scope, $nativeComparable]) {
                if (!$includePolyfillExecParams && !$nativeComparable) {
                    continue;
                }
                $statement = null;
                $phase = $operation === 'prepared-execute' ? 'prepare' : $expectedPhase;
                try {
                    if ($operation === 'exec') {
                        $pdo->exec($sql);
                    } elseif ($operation === 'exec-params') {
                        $pdo->exec($sql, $parameters);
                    } else {
                        $statement = $pdo->prepare($sql);
                        $phase = 'execute';
                        $statement->execute($parameters);
                    }
                    throw new RuntimeException("Expected PDOException for {$name}");
                } catch (PDOException $exception) {
                    $after = (string) file_get_contents($path);
                    $connectionErrorInfo = $pdo->errorInfo();
                    $statementErrorInfo = $statement instanceof PDOStatement ? $statement->errorInfo() : null;
                    $rowsAfterSuccess = $pdo->query('SELECT setting_id, key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC);
                    $errors[$name] = [
                        'phase' => $phase,
                        'message' => $exception->getMessage(),
                        'exception_error_info' => $exception->errorInfo ?? null,
                        'connection_error_info' => $connectionErrorInfo,
                        'statement_error_info' => $statementErrorInfo,
                        'file_unchanged' => hash('sha256', $before) === hash('sha256', $after),
                        'rows_after_success' => $rowsAfterSuccess,
                        'success_error_info' => $pdo->errorInfo(),
                        'expected_error_info' => $expectedErrorInfo,
                        'scope' => $scope,
                    ];
                }
            }

            $reopened = $open($class, $path);

            return [
                'errors' => $errors,
                'rows' => $reopened->query('SELECT setting_id, key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC),
                'file_unchanged' => hash('sha256', $before) === hash('sha256', (string) file_get_contents($path)),
            ];
        };

        $withScratchDir('sqlite-pdo-invalid-dml-parity', static function (string $dir) use ($t, $cases, $exercise, $initialRows): void {
            $native = $exercise(PDO::class, $dir . '/native.sqlite', false);
            $polyfill = $exercise(SQLitePDO::class, $dir . '/polyfill.sqlite', true);

            foreach ($cases as $name => [, , , $expectedErrorInfo, $expectedPhase, $scope, $nativeComparable]) {
                $error = $polyfill['errors'][$name];
                $t->same($expectedPhase, $error['phase']);
                $t->contains($expectedErrorInfo[2], $error['message']);
                $t->same($expectedErrorInfo, $error['exception_error_info']);
                if ($scope === 'connection') {
                    $t->same($expectedErrorInfo, $error['connection_error_info']);
                    $t->same(null, $error['statement_error_info']);
                } else {
                    $t->same(['00000', null, null], $error['connection_error_info']);
                    $t->same($expectedErrorInfo, $error['statement_error_info']);
                }
                $t->same(true, $error['file_unchanged']);
                $t->same($initialRows, $error['rows_after_success']);
                $t->same(['00000', null, null], $error['success_error_info']);

                if ($nativeComparable) {
                    $t->same($native['errors'][$name]['phase'], $error['phase']);
                    $t->same($native['errors'][$name]['exception_error_info'], $error['exception_error_info']);
                    $t->same($native['errors'][$name]['connection_error_info'], $error['connection_error_info']);
                    $t->same($native['errors'][$name]['statement_error_info'], $error['statement_error_info']);
                    $t->same($native['errors'][$name]['rows_after_success'], $error['rows_after_success']);
                }
            }

            $t->same($initialRows, $polyfill['rows']);
            $t->same(true, $polyfill['file_unchanged']);
        });
    },
];
