<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePDO;

$root = sys_get_temp_dir() . '/port-libsqlite-pdo-relative-' . bin2hex(random_bytes(6));
$first = $root . '/first';
$second = $root . '/second';
$cwd = getcwd();
if ($cwd === false) {
    throw new RuntimeException('Unable to read current working directory');
}
if (!mkdir($first, 0700, true) && !is_dir($first)) {
    throw new RuntimeException("Unable to create first temporary directory: {$first}");
}
if (!mkdir($second, 0700, true) && !is_dir($second)) {
    throw new RuntimeException("Unable to create second temporary directory: {$second}");
}

$result = null;
try {
    chdir($first);
    $pdo = new SQLitePDO('sqlite:app-relative.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE app_settings (setting_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)');
    $pdo->exec("INSERT INTO app_settings (key_name, key_value) VALUES ('alpha', 'kept')");
    $firstPath = $first . '/app-relative.sqlite';
    $beforeFailure = (string) file_get_contents($firstPath);

    chdir($second);
    try {
        $pdo->prepare('SELECT key_name FROM app_settings WHERE missing_key = 1');
        throw new RuntimeException('Expected missing-column failure');
    } catch (PDOException $exception) {
        $afterFailure = [
            'exception_error_info' => $exception->errorInfo ?? null,
            'connection_error_info' => $pdo->errorInfo(),
            'original_file_unchanged' => hash('sha256', $beforeFailure) === hash('sha256', (string) file_get_contents($firstPath)),
            'stray_relative_file_exists' => is_file($second . '/app-relative.sqlite'),
        ];
    }

    $pdo->exec("INSERT INTO app_settings (key_name, key_value) VALUES ('beta', 'kept-too')");
    $rowsAfterSuccess = $pdo->query('SELECT setting_id, key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll();
    unset($pdo);

    chdir($first);
    $reopened = new SQLitePDO('sqlite:app-relative.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $result = [
        'scenario' => 'application-pdo-relative-file-bad-column',
        'applicationUse' => 'Keep relative file-backed SQLitePDO connections anchored to the originally opened file after native-style bad-column errors and later writes.',
        'afterFailure' => $afterFailure,
        'afterSuccess' => [
            'connection_error_info' => $reopened->errorInfo(),
            'rows' => $rowsAfterSuccess,
            'reopened_rows' => $reopened->query('SELECT setting_id, key_name, key_value FROM app_settings ORDER BY setting_id')->fetchAll(),
            'stray_relative_file_exists' => is_file($second . '/app-relative.sqlite'),
        ],
    ];
} finally {
    chdir($cwd);
    foreach ([$first . '/app-relative.sqlite', $second . '/app-relative.sqlite'] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    if (is_dir($first)) {
        rmdir($first);
    }
    if (is_dir($second)) {
        rmdir($second);
    }
    if (is_dir($root)) {
        rmdir($root);
    }
}

if (($argv[1] ?? null) === '--self-test') {
    $expectedRows = [
        ['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'kept'],
        ['setting_id' => 2, 'key_name' => 'beta', 'key_value' => 'kept-too'],
    ];
    $expectedErrorInfo = ['HY000', 1, 'no such column: missing_key'];
    $passed = $result !== null
        && $result['afterFailure']['exception_error_info'] === $expectedErrorInfo
        && $result['afterFailure']['connection_error_info'] === $expectedErrorInfo
        && $result['afterFailure']['original_file_unchanged'] === true
        && $result['afterFailure']['stray_relative_file_exists'] === false
        && $result['afterSuccess']['connection_error_info'] === ['00000', null, null]
        && $result['afterSuccess']['rows'] === $expectedRows
        && $result['afterSuccess']['reopened_rows'] === $expectedRows
        && $result['afterSuccess']['stray_relative_file_exists'] === false;

    if (!$passed) {
        fwrite(STDERR, "application-pdo-relative-file-bad-column self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pdo-relative-file-bad-column self-test passed\n");
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
