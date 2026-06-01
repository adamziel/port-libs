<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePDO;

$sqlitePdoNativeAvailable = static fn (): bool => in_array('sqlite', PDO::getAvailableDrivers(), true);

$withScratchDirs = static function (string $prefix, callable $callback): mixed {
    $scratchRoot = __DIR__ . '/.sqlite-pdo-tmp';
    if (!is_dir($scratchRoot) && !mkdir($scratchRoot)) {
        throw new RuntimeException("Unable to create temporary test root: {$scratchRoot}");
    }
    chmod($scratchRoot, 0700);
    $root = $scratchRoot . '/' . $prefix . '-' . bin2hex(random_bytes(6));
    $first = $root . '/first';
    $second = $root . '/second';
    if (!mkdir($first, 0700, true) && !is_dir($first)) {
        throw new RuntimeException("Unable to create first temporary test directory: {$first}");
    }
    if (!mkdir($second, 0700, true) && !is_dir($second)) {
        throw new RuntimeException("Unable to create second temporary test directory: {$second}");
    }

    try {
        return $callback($first, $second);
    } finally {
        foreach ([$first, $second] as $dir) {
            foreach (glob($dir . '/*.sqlite') ?: [] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
        if (is_dir($root)) {
            rmdir($root);
        }
        if (is_dir($scratchRoot) && (scandir($scratchRoot) ?: ['.', '..']) === ['.', '..']) {
            rmdir($scratchRoot);
        }
    }
};

$openRelative = static function (string $class): PDO {
    return new $class('sqlite:relative.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
};

return [
    'SQLitePDO relative file bad-column errors keep subsequent writes on original file like native' => static function (TestRunner $t) use ($sqlitePdoNativeAvailable, $withScratchDirs, $openRelative): void {
        $expectedRows = [
            ['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'kept'],
            ['setting_id' => 2, 'key_name' => 'beta', 'key_value' => 'kept-too'],
        ];
        $expectedErrorInfo = ['HY000', 1, 'no such column: missing_key'];

        $exercise = static function (string $class, string $first, string $second) use ($openRelative): array {
            $cwd = getcwd();
            if ($cwd === false) {
                throw new RuntimeException('Unable to read current working directory');
            }

            try {
                foreach ([$first . '/relative.sqlite', $second . '/relative.sqlite'] as $path) {
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
                chdir($first);
                $pdo = $openRelative($class);
                $pdo->exec('CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)');
                $pdo->exec("INSERT INTO app_settings (key_name, key_value) VALUES ('alpha', 'kept')");
                $firstPath = $first . '/relative.sqlite';
                $beforeFailure = (string) file_get_contents($firstPath);

                chdir($second);
                $statement = null;
                $phase = 'prepare';
                try {
                    $statement = $pdo->prepare('SELECT key_name FROM app_settings WHERE missing_key = 1');
                    $phase = 'execute';
                    if (!$statement instanceof PDOStatement) {
                        throw new RuntimeException('Expected prepared statement');
                    }
                    $statement->execute();
                    throw new RuntimeException('Expected missing-column PDOException');
                } catch (PDOException $exception) {
                    $afterBadColumn = [
                        'phase' => $phase,
                        'message' => $exception->getMessage(),
                        'exception_error_info' => $exception->errorInfo ?? null,
                        'connection_error_info' => $pdo->errorInfo(),
                        'statement_error_info' => $statement instanceof PDOStatement ? $statement->errorInfo() : null,
                        'statement_was_created' => $statement instanceof PDOStatement,
                        'original_file_unchanged' => hash('sha256', $beforeFailure) === hash('sha256', (string) file_get_contents($firstPath)),
                        'second_file_exists_after_failure' => is_file($second . '/relative.sqlite'),
                    ];
                }

                $pdo->exec("INSERT INTO app_settings (key_name, key_value) VALUES ('beta', 'kept-too')");
                $rowsAfterSuccess = $pdo->query('SELECT setting_id, key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC);
                $afterSuccess = [
                    'connection_error_info' => $pdo->errorInfo(),
                    'rows' => $rowsAfterSuccess,
                    'second_file_exists_after_success' => is_file($second . '/relative.sqlite'),
                ];
                unset($pdo);

                chdir($first);
                $reopened = $openRelative($class);
                $reopenedRows = $reopened->query('SELECT setting_id, key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC);
                unset($reopened);

                return [
                    'after_bad_column' => $afterBadColumn,
                    'after_success' => $afterSuccess,
                    'reopened_rows' => $reopenedRows,
                    'first_file_exists' => is_file($firstPath),
                    'second_file_exists' => is_file($second . '/relative.sqlite'),
                ];
            } finally {
                chdir($cwd);
            }
        };

        $withScratchDirs('sqlite-pdo-relative-bad-column', static function (string $first, string $second) use ($t, $sqlitePdoNativeAvailable, $exercise, $expectedRows, $expectedErrorInfo): void {
            $polyfill = $exercise(SQLitePDO::class, $first, $second);
            $native = $sqlitePdoNativeAvailable() ? $exercise(PDO::class, $first, $second) : null;

            $error = $polyfill['after_bad_column'];
            $t->same('prepare', $error['phase']);
            $t->contains($expectedErrorInfo[2], $error['message']);
            $t->same($expectedErrorInfo, $error['exception_error_info']);
            $t->same($expectedErrorInfo, $error['connection_error_info']);
            $t->same(null, $error['statement_error_info']);
            $t->same(false, $error['statement_was_created']);
            $t->same(true, $error['original_file_unchanged']);
            $t->same(false, $error['second_file_exists_after_failure']);
            $t->same(['00000', null, null], $polyfill['after_success']['connection_error_info']);
            $t->same($expectedRows, $polyfill['after_success']['rows']);
            $t->same(false, $polyfill['after_success']['second_file_exists_after_success']);
            $t->same($expectedRows, $polyfill['reopened_rows']);
            $t->same(true, $polyfill['first_file_exists']);
            $t->same(false, $polyfill['second_file_exists']);

            if ($native !== null) {
                $t->same($native['after_bad_column']['phase'], $error['phase']);
                $t->same($native['after_bad_column']['exception_error_info'], $error['exception_error_info']);
                $t->same($native['after_bad_column']['connection_error_info'], $error['connection_error_info']);
                $t->same($native['after_bad_column']['statement_was_created'], $error['statement_was_created']);
                $t->same($native['after_success']['connection_error_info'], $polyfill['after_success']['connection_error_info']);
                $t->same($native['after_success']['rows'], $polyfill['after_success']['rows']);
                $t->same($native['after_success']['second_file_exists_after_success'], $polyfill['after_success']['second_file_exists_after_success']);
                $t->same($native['reopened_rows'], $polyfill['reopened_rows']);
                $t->same($native['second_file_exists'], $polyfill['second_file_exists']);
            }
        });
    },
];
