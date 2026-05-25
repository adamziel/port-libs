<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$missingDir = sys_get_temp_dir() . '/quadrable-wp-import-export-missing-' . bin2hex(random_bytes(6));
$storeDir = sys_get_temp_dir() . '/quadrable-wp-import-export-' . bin2hex(random_bytes(6));

$cleanup = static function (string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dir);
};

try {
    $missingImport = QuadbStore::importCommandOutput(
        $missingDir,
        "wp_options:siteurl=https://example.test\n",
        '='
    );
    $missingExport = QuadbStore::exportCommandOutput($missingDir, '=');

    if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
        throw new RuntimeException('unable to create WordPress snapshot store directory');
    }

    $emptyExport = QuadbStore::exportCommandOutput($storeDir, '=');
    $import = QuadbStore::importCommandOutput(
        $storeDir,
        "wp_options:siteurl=https://example.test\n"
        . "wp_posts:1=Published post\n",
        '='
    );
    $export = QuadbStore::exportCommandOutput($storeDir, '=');
    $badLine = QuadbStore::importCommandOutput($storeDir, "missing separator\n", '=');
    $emptySeparator = QuadbStore::importCommandOutput(
        $storeDir,
        "wp_options:home=https://example.test\n",
        ''
    );

    echo json_encode([
        'scenario' => 'round trip WordPress snapshot rows with upstream-shaped quadb import and export output',
        'missingImportExitCode' => $missingImport['exitCode'],
        'missingExportExitCode' => $missingExport['exitCode'],
        'missingStoreCreatedDirectory' => is_dir($missingDir),
        'emptyExportStdout' => $emptyExport['stdout'],
        'importStreamsEmpty' => $import['stdout'] === '' && $import['stderr'] === '',
        'exportedRows' => array_values(array_filter(explode("\n", trim($export['stdout'])))),
        'badLineStderr' => rtrim($badLine['stderr'], "\r\n"),
        'emptySeparatorStderr' => rtrim($emptySeparator['stderr'], "\r\n"),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($missingDir);
    $cleanup($storeDir);
}
