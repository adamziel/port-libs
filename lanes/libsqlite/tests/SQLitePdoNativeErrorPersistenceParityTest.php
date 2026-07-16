<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePDO;

$sqlitePdoNativeAvailable = static fn (): bool => in_array('sqlite', PDO::getAvailableDrivers(), true);

return [
    'SQLitePDO connection failures expose native PDOException errorInfo and clear after success' => static function (TestRunner $t) use ($sqlitePdoNativeAvailable): void {
        if (!$sqlitePdoNativeAvailable()) {
            return;
        }

        $exercise = static function (string $class): array {
            $pdo = new $class('sqlite::memory:', null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
            $pdo->exec("INSERT INTO test (name) VALUES ('Janet')");

            try {
                $pdo->prepare('SELECT * FROM missing_table');
                throw new RuntimeException('Expected PDOException for missing table prepare');
            } catch (PDOException $exception) {
                $afterFailure = [
                    'exception_message' => $exception->getMessage(),
                    'exception_code' => $exception->getCode(),
                    'exception_error_info' => $exception->errorInfo ?? null,
                    'pdo_error_code' => $pdo->errorCode(),
                    'pdo_error_info' => $pdo->errorInfo(),
                ];
            }

            $statement = $pdo->prepare('SELECT name FROM test WHERE id = ?');
            $afterPrepareSuccess = [
                'pdo_error_code' => $pdo->errorCode(),
                'pdo_error_info' => $pdo->errorInfo(),
                'statement_error_code' => $statement->errorCode(),
                'statement_error_info' => $statement->errorInfo(),
            ];
            $statement->execute([1]);

            return [
                'after_failure' => $afterFailure,
                'after_prepare_success' => $afterPrepareSuccess,
                'rows' => $statement->fetchAll(),
            ];
        };

        $native = $exercise(PDO::class);
        $polyfill = $exercise(SQLitePDO::class);

        $t->contains('no such table: missing_table', $polyfill['after_failure']['exception_message']);
        $t->same($native['after_failure']['exception_code'], $polyfill['after_failure']['exception_code']);
        $t->same($native['after_failure']['exception_error_info'], $polyfill['after_failure']['exception_error_info']);
        $t->same($native['after_failure']['pdo_error_code'], $polyfill['after_failure']['pdo_error_code']);
        $t->same($native['after_failure']['pdo_error_info'], $polyfill['after_failure']['pdo_error_info']);
        $t->same($native['after_prepare_success']['pdo_error_code'], $polyfill['after_prepare_success']['pdo_error_code']);
        $t->same($native['after_prepare_success']['pdo_error_info'], $polyfill['after_prepare_success']['pdo_error_info']);
        $t->same($native['after_prepare_success']['statement_error_code'], $polyfill['after_prepare_success']['statement_error_code']);
        $t->same($native['after_prepare_success']['statement_error_info'], $polyfill['after_prepare_success']['statement_error_info']);
        $t->same($native['rows'], $polyfill['rows']);
    },

    'SQLitePDO statement execute errors persist on statement without clobbering connection' => static function (TestRunner $t) use ($sqlitePdoNativeAvailable): void {
        if (!$sqlitePdoNativeAvailable()) {
            return;
        }

        $scratchRoot = __DIR__ . '/.sqlite-pdo-tmp';
        if (!is_dir($scratchRoot) && !mkdir($scratchRoot)) {
            throw new RuntimeException("Unable to create temporary test root: {$scratchRoot}");
        }
        chmod($scratchRoot, 0700);
        $dir = $scratchRoot . '/sqlite-pdo-error-persistence-' . bin2hex(random_bytes(6));
        if (!mkdir($dir) && !is_dir($dir)) {
            throw new RuntimeException("Unable to create temporary test directory: {$dir}");
        }
        chmod($dir, 0700);

        $exercise = static function (string $class, string $path): array {
            $pdo = new $class('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
            $pdo->exec("INSERT INTO test (name) VALUES ('Janet')");
            $statement = $pdo->prepare('INSERT INTO test (name) VALUES (?)');
            $before = (string) file_get_contents($path);

            try {
                $statement->execute(['Ignored', 'surplus']);
                throw new RuntimeException('Expected PDOException for surplus execute parameter');
            } catch (PDOException $exception) {
                $afterFailure = [
                    'exception_message' => $exception->getMessage(),
                    'exception_code' => $exception->getCode(),
                    'exception_error_info' => $exception->errorInfo ?? null,
                    'pdo_error_code' => $pdo->errorCode(),
                    'pdo_error_info' => $pdo->errorInfo(),
                    'statement_error_code' => $statement->errorCode(),
                    'statement_error_info' => $statement->errorInfo(),
                    'file_unchanged' => hash('sha256', $before) === hash('sha256', (string) file_get_contents($path)),
                ];
            }

            $rowsAfterConnectionSuccess = $pdo->query('SELECT * FROM test ORDER BY id')->fetchAll();
            $afterConnectionSuccess = [
                'pdo_error_code' => $pdo->errorCode(),
                'pdo_error_info' => $pdo->errorInfo(),
                'statement_error_code' => $statement->errorCode(),
                'statement_error_info' => $statement->errorInfo(),
                'file_unchanged' => hash('sha256', $before) === hash('sha256', (string) file_get_contents($path)),
            ];

            $statement->execute(['Kept']);
            $afterStatementSuccess = [
                'pdo_error_code' => $pdo->errorCode(),
                'pdo_error_info' => $pdo->errorInfo(),
                'statement_error_code' => $statement->errorCode(),
                'statement_error_info' => $statement->errorInfo(),
                'rows' => $pdo->query('SELECT * FROM test ORDER BY id')->fetchAll(),
            ];

            return [
                'after_failure' => $afterFailure,
                'rows_after_connection_success' => $rowsAfterConnectionSuccess,
                'after_connection_success' => $afterConnectionSuccess,
                'after_statement_success' => $afterStatementSuccess,
            ];
        };

        $polyfillPath = $dir . '/polyfill.sqlite';
        $nativePath = $dir . '/native.sqlite';

        try {
            $native = $exercise(PDO::class, $nativePath);
            $polyfill = $exercise(SQLitePDO::class, $polyfillPath);

            $t->contains('column index out of range', $polyfill['after_failure']['exception_message']);
            $t->same($native['after_failure']['exception_code'], $polyfill['after_failure']['exception_code']);
            $t->same($native['after_failure']['exception_error_info'], $polyfill['after_failure']['exception_error_info']);
            $t->same($native['after_failure']['pdo_error_code'], $polyfill['after_failure']['pdo_error_code']);
            $t->same($native['after_failure']['pdo_error_info'], $polyfill['after_failure']['pdo_error_info']);
            $t->same($native['after_failure']['statement_error_code'], $polyfill['after_failure']['statement_error_code']);
            $t->same($native['after_failure']['statement_error_info'], $polyfill['after_failure']['statement_error_info']);
            $t->same(true, $polyfill['after_failure']['file_unchanged']);

            $t->same($native['rows_after_connection_success'], $polyfill['rows_after_connection_success']);
            $t->same($native['after_connection_success']['pdo_error_code'], $polyfill['after_connection_success']['pdo_error_code']);
            $t->same($native['after_connection_success']['pdo_error_info'], $polyfill['after_connection_success']['pdo_error_info']);
            $t->same($native['after_connection_success']['statement_error_code'], $polyfill['after_connection_success']['statement_error_code']);
            $t->same($native['after_connection_success']['statement_error_info'], $polyfill['after_connection_success']['statement_error_info']);
            $t->same(true, $polyfill['after_connection_success']['file_unchanged']);

            $t->same($native['after_statement_success']['pdo_error_code'], $polyfill['after_statement_success']['pdo_error_code']);
            $t->same($native['after_statement_success']['pdo_error_info'], $polyfill['after_statement_success']['pdo_error_info']);
            $t->same($native['after_statement_success']['statement_error_code'], $polyfill['after_statement_success']['statement_error_code']);
            $t->same($native['after_statement_success']['statement_error_info'], $polyfill['after_statement_success']['statement_error_info']);
            $t->same($native['after_statement_success']['rows'], $polyfill['after_statement_success']['rows']);
        } finally {
            foreach ([$polyfillPath, $nativePath] as $path) {
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
    },
];
