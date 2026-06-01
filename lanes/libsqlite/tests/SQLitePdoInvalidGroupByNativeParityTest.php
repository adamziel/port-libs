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
    'SQLitePDO invalid GROUP BY columns fail like native prepare query and preserve file image' => static function (TestRunner $t) use ($sqlitePdoNativeAvailable, $withScratchDir, $open): void {
        $cases = [
            'query group by missing column' => [
                'query',
                'SELECT key_name FROM app_settings GROUP BY missing_key',
                ['HY000', 1, 'no such column: missing_key'],
                'query',
            ],
            'prepared group by missing column' => [
                'prepare',
                'SELECT key_name FROM app_settings GROUP BY missing_key',
                ['HY000', 1, 'no such column: missing_key'],
                'prepare',
            ],
            'prepared aggregate group by missing column' => [
                'prepare',
                'SELECT key_name, count(*) AS rows FROM app_settings GROUP BY missing_key',
                ['HY000', 1, 'no such column: missing_key'],
                'prepare',
            ],
        ];
        $initialRows = [
            ['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'kept'],
            ['setting_id' => 2, 'key_name' => 'beta', 'key_value' => 'kept-too'],
        ];

        $exercise = static function (string $class, string $path) use ($cases, $open): array {
            $pdo = $open($class, $path);
            $pdo->exec('CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)');
            $pdo->exec("INSERT INTO app_settings (key_name, key_value) VALUES ('alpha', 'kept')");
            $pdo->exec("INSERT INTO app_settings (key_name, key_value) VALUES ('beta', 'kept-too')");
            $before = (string) file_get_contents($path);
            $errors = [];

            foreach ($cases as $name => [$operation, $sql, $expectedErrorInfo, $expectedPhase]) {
                $statement = null;
                $phase = $expectedPhase;
                try {
                    if ($operation === 'query') {
                        $pdo->query($sql);
                    } else {
                        $statement = $pdo->prepare($sql);
                        $phase = 'execute';
                        if (!$statement instanceof PDOStatement) {
                            throw new RuntimeException('Expected prepared statement');
                        }
                        $statement->execute();
                    }
                    throw new RuntimeException("Expected PDOException for {$name}");
                } catch (PDOException $exception) {
                    $connectionErrorInfo = $pdo->errorInfo();
                    $statementErrorInfo = $statement instanceof PDOStatement ? $statement->errorInfo() : null;
                    $rowsAfterSuccess = $pdo->query('SELECT setting_id, key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC);
                    $errors[$name] = [
                        'phase' => $phase,
                        'message' => $exception->getMessage(),
                        'exception_error_info' => $exception->errorInfo ?? null,
                        'connection_error_info' => $connectionErrorInfo,
                        'statement_error_info' => $statementErrorInfo,
                        'statement_was_created' => $statement instanceof PDOStatement,
                        'file_unchanged' => hash('sha256', $before) === hash('sha256', (string) file_get_contents($path)),
                        'rows_after_success' => $rowsAfterSuccess,
                        'success_error_info' => $pdo->errorInfo(),
                        'expected_error_info' => $expectedErrorInfo,
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

        $withScratchDir('sqlite-pdo-invalid-group-by-column', static function (string $dir) use ($t, $cases, $exercise, $initialRows, $sqlitePdoNativeAvailable): void {
            $polyfill = $exercise(SQLitePDO::class, $dir . '/polyfill.sqlite');
            $native = $sqlitePdoNativeAvailable() ? $exercise(PDO::class, $dir . '/native.sqlite') : null;

            foreach ($cases as $name => [, , $expectedErrorInfo, $expectedPhase]) {
                $error = $polyfill['errors'][$name];
                $t->same($expectedPhase, $error['phase']);
                $t->contains($expectedErrorInfo[2], $error['message']);
                $t->same($expectedErrorInfo, $error['exception_error_info']);
                $t->same($expectedErrorInfo, $error['connection_error_info']);
                $t->same(null, $error['statement_error_info']);
                $t->same(false, $error['statement_was_created']);
                $t->same(true, $error['file_unchanged']);
                $t->same($initialRows, $error['rows_after_success']);
                $t->same(['00000', null, null], $error['success_error_info']);

                if ($native !== null) {
                    $t->same($native['errors'][$name]['phase'], $error['phase']);
                    $t->same($native['errors'][$name]['exception_error_info'], $error['exception_error_info']);
                    $t->same($native['errors'][$name]['connection_error_info'], $error['connection_error_info']);
                    $t->same($native['errors'][$name]['statement_was_created'], $error['statement_was_created']);
                    $t->same($native['errors'][$name]['rows_after_success'], $error['rows_after_success']);
                }
            }

            $t->same($initialRows, $polyfill['rows']);
            $t->same(true, $polyfill['file_unchanged']);
        });
    },
];
