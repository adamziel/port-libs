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

return [
    'SQLitePDO silent mode returns false for native prepare query and exec errors' => static function (TestRunner $t) use ($sqlitePdoNativeAvailable, $withScratchDir): void {
        $cases = [
            'prepare missing table' => ['prepare', 'SELECT * FROM missing_table', 'no such table: missing_table'],
            'prepare unknown target column' => ['prepare', 'INSERT INTO test (namedd) VALUES (?)', 'table test has no column named namedd'],
            'prepare unknown select column' => ['prepare', 'SELECT namedd FROM test', 'no such column: namedd'],
            'query missing table' => ['query', 'SELECT * FROM missing_table', 'no such table: missing_table'],
            'exec missing table' => ['exec', "INSERT INTO missing_table (name) VALUES ('Other')", 'no such table: missing_table'],
            'exec unknown target column' => ['exec', "INSERT INTO test (namedd) VALUES ('Other')", 'table test has no column named namedd'],
        ];

        $exercise = static function (string $class, string $path) use ($cases): array {
            $pdo = new $class('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
            $pdo->exec("INSERT INTO test (name) VALUES ('Janet')");
            $before = (string) file_get_contents($path);
            $errors = [];

            foreach ($cases as $name => [$operation, $sql]) {
                $result = match ($operation) {
                    'prepare' => $pdo->prepare($sql),
                    'query' => $pdo->query($sql),
                    'exec' => $pdo->exec($sql),
                };
                $after = (string) file_get_contents($path);
                $errorCode = $pdo->errorCode();
                $errorInfo = $pdo->errorInfo();
                $rowsAfterSuccess = $pdo->query('SELECT * FROM test ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
                $errors[$name] = [
                    'result_is_false' => $result === false,
                    'statement_was_created' => $result instanceof PDOStatement,
                    'error_code' => $errorCode,
                    'error_info' => $errorInfo,
                    'file_unchanged' => hash('sha256', $before) === hash('sha256', $after),
                    'rows_after_success' => $rowsAfterSuccess,
                    'success_error_info' => $pdo->errorInfo(),
                ];
            }

            $reopened = new $class('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            return [
                'errors' => $errors,
                'rows' => $reopened->query('SELECT * FROM test ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
                'file_unchanged' => hash('sha256', $before) === hash('sha256', (string) file_get_contents($path)),
            ];
        };

        $withScratchDir('sqlite-pdo-false-errors', static function (string $dir) use ($t, $exercise, $cases, $sqlitePdoNativeAvailable): void {
            $polyfill = $exercise(SQLitePDO::class, $dir . '/polyfill.sqlite');
            $native = $sqlitePdoNativeAvailable() ? $exercise(PDO::class, $dir . '/native.sqlite') : null;

            foreach ($cases as $name => [, , $message]) {
                $t->same(true, $polyfill['errors'][$name]['result_is_false']);
                $t->same(false, $polyfill['errors'][$name]['statement_was_created']);
                $t->same('HY000', $polyfill['errors'][$name]['error_code']);
                $t->same(['HY000', 1, $message], $polyfill['errors'][$name]['error_info']);
                $t->same(true, $polyfill['errors'][$name]['file_unchanged']);
                $t->same([['id' => 1, 'name' => 'Janet']], $polyfill['errors'][$name]['rows_after_success']);
                $t->same(['00000', null, null], $polyfill['errors'][$name]['success_error_info']);

                if ($native !== null) {
                    $t->same($native['errors'][$name], $polyfill['errors'][$name]);
                }
            }

            $t->same([['id' => 1, 'name' => 'Janet']], $polyfill['rows']);
            $t->same(true, $polyfill['file_unchanged']);
        });
    },

    'SQLitePDO silent statement execute errors return false without connection error state' => static function (TestRunner $t) use ($sqlitePdoNativeAvailable, $withScratchDir): void {
        $exercise = static function (string $class, string $path): array {
            $pdo = new $class('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
            $pdo->exec("INSERT INTO test (name) VALUES ('Janet')");
            $statement = $pdo->prepare('INSERT INTO test (name) VALUES (?)');
            if (!$statement instanceof PDOStatement) {
                throw new RuntimeException('Expected prepared statement');
            }
            $before = (string) file_get_contents($path);

            $failureResult = $statement->execute(['Ignored', 'surplus']);
            $afterFailure = [
                'execute_result' => $failureResult,
                'pdo_error_code' => $pdo->errorCode(),
                'pdo_error_info' => $pdo->errorInfo(),
                'statement_error_code' => $statement->errorCode(),
                'statement_error_info' => $statement->errorInfo(),
                'file_unchanged' => hash('sha256', $before) === hash('sha256', (string) file_get_contents($path)),
            ];

            $rowsAfterConnectionSuccess = $pdo->query('SELECT * FROM test ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $afterConnectionSuccess = [
                'pdo_error_info' => $pdo->errorInfo(),
                'statement_error_info' => $statement->errorInfo(),
                'rows' => $rowsAfterConnectionSuccess,
                'file_unchanged' => hash('sha256', $before) === hash('sha256', (string) file_get_contents($path)),
            ];

            $successResult = $statement->execute(['Kept']);
            $afterStatementSuccess = [
                'execute_result' => $successResult,
                'pdo_error_info' => $pdo->errorInfo(),
                'statement_error_info' => $statement->errorInfo(),
                'rows' => $pdo->query('SELECT * FROM test ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
            ];

            return [
                'after_failure' => $afterFailure,
                'after_connection_success' => $afterConnectionSuccess,
                'after_statement_success' => $afterStatementSuccess,
            ];
        };

        $withScratchDir('sqlite-pdo-false-statement-errors', static function (string $dir) use ($t, $exercise, $sqlitePdoNativeAvailable): void {
            $polyfill = $exercise(SQLitePDO::class, $dir . '/polyfill.sqlite');
            $native = $sqlitePdoNativeAvailable() ? $exercise(PDO::class, $dir . '/native.sqlite') : null;
            $expectedErrorInfo = ['HY000', 25, 'column index out of range'];

            $t->same(false, $polyfill['after_failure']['execute_result']);
            $t->same('00000', $polyfill['after_failure']['pdo_error_code']);
            $t->same(['00000', null, null], $polyfill['after_failure']['pdo_error_info']);
            $t->same('HY000', $polyfill['after_failure']['statement_error_code']);
            $t->same($expectedErrorInfo, $polyfill['after_failure']['statement_error_info']);
            $t->same(true, $polyfill['after_failure']['file_unchanged']);
            $t->same(['00000', null, null], $polyfill['after_connection_success']['pdo_error_info']);
            $t->same($expectedErrorInfo, $polyfill['after_connection_success']['statement_error_info']);
            $t->same([['id' => 1, 'name' => 'Janet']], $polyfill['after_connection_success']['rows']);
            $t->same(true, $polyfill['after_connection_success']['file_unchanged']);
            $t->same(true, $polyfill['after_statement_success']['execute_result']);
            $t->same(['00000', null, null], $polyfill['after_statement_success']['pdo_error_info']);
            $t->same(['00000', null, null], $polyfill['after_statement_success']['statement_error_info']);
            $t->same([['id' => 1, 'name' => 'Janet'], ['id' => 2, 'name' => 'Kept']], $polyfill['after_statement_success']['rows']);

            if ($native !== null) {
                $t->same($native, $polyfill);
            }
        });
    },
];
