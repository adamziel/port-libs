<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$dir = sys_get_temp_dir() . '/quadrable-wp-init-length-' . bin2hex(random_bytes(6));

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
    $firstInit = QuadbStore::initCommandOutput($dir);
    $repo = QuadbStore::open($dir);
    $lengthBeforeImport = QuadbStore::lengthCommandOutput($dir);

    $repo->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );
    $lengthAfterImport = QuadbStore::lengthCommandOutput($dir);
    $secondInit = QuadbStore::initCommandOutput($dir);

    echo json_encode([
        'scenario' => 'bootstrap a WordPress snapshot store with upstream-shaped quadb init and length output',
        'firstInitStdout' => rtrim($firstInit['stdout'], "\r\n"),
        'firstInitStderr' => rtrim($firstInit['stderr'], "\r\n"),
        'secondInitStdout' => rtrim($secondInit['stdout'], "\r\n"),
        'secondInitStderr' => rtrim($secondInit['stderr'], "\r\n"),
        'lengthBeforeImport' => $lengthBeforeImport,
        'lengthAfterImport' => $lengthAfterImport,
        'statusAfterImport' => rtrim($repo->statusText(), "\r\n"),
        'exportedRecords' => count(array_filter(explode("\n", trim($repo->exportLines('|'))))),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($dir);
}
