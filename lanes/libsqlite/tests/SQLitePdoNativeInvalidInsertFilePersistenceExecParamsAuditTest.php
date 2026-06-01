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
    'SQLitePDO omitted INSERT execute parameters match native file-backed null binding' => static function (TestRunner $t) use ($sqlitePdoNativeAvailable, $withScratchDir, $open): void {
        if (!$sqlitePdoNativeAvailable()) {
            return;
        }

        $exercise = static function (string $class, string $path, bool $includeExecParams) use ($open): array {
            $pdo = $open($class, $path);
            $pdo->exec('CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)');

            $first = $pdo->prepare('INSERT INTO app_settings (key_name, key_value) VALUES (?, ?)');
            $first->execute(['prepared-first']);
            $oneKeyed = $pdo->prepare('INSERT INTO app_settings (key_name, key_value) VALUES (?, ?)');
            $oneKeyed->execute([1 => 'prepared-second-slot']);
            $empty = $pdo->prepare('INSERT INTO app_settings (key_name, key_value) VALUES (?, ?)');
            $empty->execute([]);

            if ($includeExecParams) {
                $pdo->exec('INSERT INTO app_settings (key_name, key_value) VALUES (?, ?)', ['exec-first']);
                $pdo->exec('INSERT INTO app_settings (key_name, key_value) VALUES (:key_name, :key_value)', ['key_name' => 'exec-named']);
            }

            $reopened = $open($class, $path);

            return $reopened->query('SELECT key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC);
        };

        $withScratchDir('sqlite-pdo-native-null-params', static function (string $dir) use ($t, $exercise): void {
            $native = $exercise(PDO::class, $dir . '/native.sqlite', false);
            $polyfill = $exercise(SQLitePDO::class, $dir . '/polyfill.sqlite', true);

            $t->same($native, array_slice($polyfill, 0, 3));
            $t->same(
                [
                    ['key_name' => 'prepared-first', 'key_value' => null],
                    ['key_name' => null, 'key_value' => 'prepared-second-slot'],
                    ['key_name' => null, 'key_value' => null],
                    ['key_name' => 'exec-first', 'key_value' => null],
                    ['key_name' => 'exec-named', 'key_value' => null],
                ],
                $polyfill,
            );
        });
    },

    'SQLitePDO parameterized invalid INSERT paths keep file image unchanged' => static function (TestRunner $t) use ($sqlitePdoNativeAvailable, $withScratchDir, $open): void {
        $cases = [
            'exec invalid target column' => [
                'exec',
                'INSERT INTO app_settings (missing_key) VALUES (?)',
                ['ignored'],
                ['HY000', 1, 'table app_settings has no column named missing_key'],
                'connection',
            ],
            'exec invalid scalar column' => [
                'exec',
                'INSERT INTO app_settings (key_name) VALUES (missing_column)',
                [],
                ['HY000', 1, 'no such column: missing_column'],
                'connection',
            ],
            'exec surplus positional parameter' => [
                'exec',
                'INSERT INTO app_settings (key_name) VALUES (?)',
                ['ignored', 'surplus'],
                ['HY000', 25, 'column index out of range'],
                'connection',
            ],
            'exec invalid numbered parameter' => [
                'exec',
                'INSERT INTO app_settings (key_name) VALUES (?0)',
                ['ignored'],
                ['HY000', 1, 'variable number must be between ?1 and ?32766'],
                'connection',
            ],
            'prepared sparse surplus parameter' => [
                'prepared',
                'INSERT INTO app_settings (key_name, key_value) VALUES (?, ?)',
                [0 => 'ignored', 2 => 'surplus'],
                ['HY000', 25, 'column index out of range'],
                'statement',
            ],
        ];

        $exercise = static function (string $class, string $path, bool $includeExecCases) use ($cases, $open): array {
            $pdo = $open($class, $path);
            $pdo->exec('CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)');
            $pdo->exec("INSERT INTO app_settings (key_name, key_value) VALUES ('initial', 'kept')");
            $before = (string) file_get_contents($path);
            $errors = [];

            foreach ($cases as $name => [$operation, $sql, $parameters, $expectedErrorInfo, $scope]) {
                if (!$includeExecCases && $operation === 'exec') {
                    continue;
                }
                $statement = null;
                try {
                    if ($operation === 'exec') {
                        $pdo->exec($sql, $parameters);
                    } else {
                        $statement = $pdo->prepare($sql);
                        if (!$statement instanceof PDOStatement) {
                            throw new RuntimeException('Expected prepared statement');
                        }
                        $statement->execute($parameters);
                    }
                    throw new RuntimeException("Expected PDOException for {$name}");
                } catch (PDOException $exception) {
                    $errors[$name] = [
                        'message' => $exception->getMessage(),
                        'exception_error_info' => $exception->errorInfo ?? null,
                        'connection_error_info' => $pdo->errorInfo(),
                        'statement_error_info' => $statement instanceof PDOStatement ? $statement->errorInfo() : null,
                        'file_unchanged' => hash('sha256', $before) === hash('sha256', (string) file_get_contents($path)),
                        'rows_after_success' => $pdo->query('SELECT key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC),
                        'success_error_info' => $pdo->errorInfo(),
                        'expected_error_info' => $expectedErrorInfo,
                        'scope' => $scope,
                    ];
                }
            }

            $reopened = $open($class, $path);

            return [
                'errors' => $errors,
                'rows' => $reopened->query('SELECT key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC),
                'file_unchanged' => hash('sha256', $before) === hash('sha256', (string) file_get_contents($path)),
            ];
        };

        $withScratchDir('sqlite-pdo-invalid-insert-exec-params', static function (string $dir) use ($t, $exercise, $cases, $sqlitePdoNativeAvailable): void {
            $polyfill = $exercise(SQLitePDO::class, $dir . '/polyfill.sqlite', true);
            $native = $sqlitePdoNativeAvailable() ? $exercise(PDO::class, $dir . '/native.sqlite', false) : null;

            foreach ($cases as $name => [, , , $expectedErrorInfo, $scope]) {
                $error = $polyfill['errors'][$name];
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
                $t->same([['key_name' => 'initial', 'key_value' => 'kept']], $error['rows_after_success']);
                $t->same(['00000', null, null], $error['success_error_info']);
            }

            if ($native !== null) {
                $t->same(
                    $native['errors']['prepared sparse surplus parameter']['exception_error_info'],
                    $polyfill['errors']['prepared sparse surplus parameter']['exception_error_info'],
                );
                $t->same(
                    $native['errors']['prepared sparse surplus parameter']['statement_error_info'],
                    $polyfill['errors']['prepared sparse surplus parameter']['statement_error_info'],
                );
            }

            $t->same([['key_name' => 'initial', 'key_value' => 'kept']], $polyfill['rows']);
            $t->same(true, $polyfill['file_unchanged']);
        });
    },

    'SQLitePDO misspelled INSERT column example throws and keeps table empty' => static function (TestRunner $t) use ($withScratchDir, $open): void {
        $withScratchDir('sqlite-pdo-misspelled-insert-column', static function (string $dir) use ($t, $open): void {
            $path = $dir . '/polyfill.sqlite';
            $sqlite = $open(SQLitePDO::class, $path);
            $sqlite->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');

            $exception = null;
            try {
                $sqlite->exec("INSERT INTO test (namedd) VALUES ('Janet')");
            } catch (PDOException $caught) {
                $exception = $caught;
            }

            $t->true($exception instanceof PDOException, 'Misspelled INSERT column should throw');
            $t->contains('table test has no column named namedd', $exception->getMessage());
            $t->same(['HY000', 1, 'table test has no column named namedd'], $exception->errorInfo ?? null);
            $t->same(['HY000', 1, 'table test has no column named namedd'], $sqlite->errorInfo());
            $t->same([], $sqlite->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC));

            $reopened = $open(SQLitePDO::class, $path);
            $t->same([], $reopened->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC));
        });
    },
];
