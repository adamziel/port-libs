<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$missingDir = sys_get_temp_dir() . '/quadrable-wp-import-int-missing-' . bin2hex(random_bytes(6));
$storeDir = sys_get_temp_dir() . '/quadrable-wp-import-int-guard-' . bin2hex(random_bytes(6));

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
    $missingImport = QuadbStore::importIntegerCommandOutput(
        $missingDir,
        "1,wp_options:siteurl=https://example.test\n"
    );
    $missingExport = QuadbStore::exportIntegerCommandOutput($missingDir);

    if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
        throw new RuntimeException('unable to create WordPress snapshot store directory');
    }

    $emptyExport = QuadbStore::exportIntegerCommandOutput($storeDir);
    $numericPrefixImport = QuadbStore::importIntegerCommandOutput(
        $storeDir,
        "1x,wp_options:siteurl=https://example.test\n"
        . "20,wp_posts:1=Published post\n"
    );
    $populatedExport = QuadbStore::exportIntegerCommandOutput($storeDir);
    $nonnumericImport = QuadbStore::importIntegerCommandOutput($storeDir, "abc,value\n");
    $negativeImport = QuadbStore::importIntegerCommandOutput($storeDir, "-1,value\n");
    $tooLargeImport = QuadbStore::importIntegerCommandOutput($storeDir, "2147483648,value\n");

    echo json_encode([
        'scenario' => 'guard WordPress integer snapshot import/export with upstream-shaped quadb --int output',
        'missingImportExitCode' => $missingImport['exitCode'],
        'missingExportExitCode' => $missingExport['exitCode'],
        'missingStoreCreatedDirectory' => is_dir($missingDir),
        'emptyExportStdout' => $emptyExport['stdout'],
        'numericPrefixImportExitCode' => $numericPrefixImport['exitCode'],
        'populatedExportStdout' => $populatedExport['stdout'],
        'nonnumericImportStderr' => rtrim($nonnumericImport['stderr'], "\r\n"),
        'negativeImportStderr' => rtrim($negativeImport['stderr'], "\r\n"),
        'tooLargeImportStderr' => rtrim($tooLargeImport['stderr'], "\r\n"),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($missingDir);
    $cleanup($storeDir);
}
