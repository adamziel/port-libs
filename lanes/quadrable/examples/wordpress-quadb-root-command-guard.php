<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$missingDir = sys_get_temp_dir() . '/quadrable-wp-missing-root-' . bin2hex(random_bytes(6));
$storeDir = sys_get_temp_dir() . '/quadrable-wp-root-guard-' . bin2hex(random_bytes(6));

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
    $missingRoot = QuadbStore::rootCommandOutput($missingDir);

    if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
        throw new RuntimeException('unable to create WordPress snapshot store directory');
    }

    $emptyRoot = QuadbStore::rootCommandOutput($storeDir);
    $repo = QuadbStore::open($storeDir);
    $repo->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );
    $populatedRoot = QuadbStore::rootCommandOutput($storeDir);

    echo json_encode([
        'scenario' => 'guard WordPress snapshot root checks against missing stores while allowing empty precreated stores',
        'missingExitCode' => $missingRoot['exitCode'],
        'missingStderr' => rtrim($missingRoot['stderr'], "\r\n"),
        'missingCreatedDirectory' => is_dir($missingDir),
        'emptyRootStdout' => rtrim($emptyRoot['stdout'], "\r\n"),
        'populatedRootMatchesStore' => $populatedRoot['stdout'] === $repo->rootText(),
        'populatedRecordCount' => count(array_filter(explode("\n", trim($repo->exportLines('|'))))),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($missingDir);
    $cleanup($storeDir);
}
