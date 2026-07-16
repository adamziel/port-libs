<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$missingDir = sys_get_temp_dir() . '/quadrable-wp-putdel-missing-' . bin2hex(random_bytes(6));
$storeDir = sys_get_temp_dir() . '/quadrable-wp-putdel-guard-' . bin2hex(random_bytes(6));

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
    $missingPut = QuadbStore::putCommandOutput(
        $missingDir,
        'wp_options:siteurl',
        'https://example.test'
    );
    $missingDelete = QuadbStore::deleteCommandOutput($missingDir, 'wp_options:siteurl');

    if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
        throw new RuntimeException('unable to create WordPress snapshot store directory');
    }

    $firstPut = QuadbStore::putCommandOutput(
        $storeDir,
        'wp_options:siteurl',
        'https://example.test'
    );
    $overwrite = QuadbStore::putCommandOutput(
        $storeDir,
        'wp_options:siteurl',
        'https://preview.example.test'
    );
    $afterOverwrite = QuadbStore::getCommandOutput($storeDir, 'wp_options:siteurl');
    $missingDeleteInStore = QuadbStore::deleteCommandOutput($storeDir, 'wp_options:home');
    $deleteSiteUrl = QuadbStore::deleteCommandOutput($storeDir, 'wp_options:siteurl');
    $afterDelete = QuadbStore::getCommandOutput($storeDir, 'wp_options:siteurl');

    echo json_encode([
        'scenario' => 'guard WordPress snapshot writes with upstream-shaped quadb put and del command output',
        'missingPutExitCode' => $missingPut['exitCode'],
        'missingDeleteExitCode' => $missingDelete['exitCode'],
        'missingStoreCreatedDirectory' => is_dir($missingDir),
        'firstPutStreamsEmpty' => $firstPut['stdout'] === '' && $firstPut['stderr'] === '',
        'overwriteStreamsEmpty' => $overwrite['stdout'] === '' && $overwrite['stderr'] === '',
        'siteUrlAfterOverwrite' => rtrim($afterOverwrite['stdout'], "\r\n"),
        'missingDeleteInStoreExitCode' => $missingDeleteInStore['exitCode'],
        'deleteSiteUrlExitCode' => $deleteSiteUrl['exitCode'],
        'siteUrlAfterDeleteStderr' => rtrim($afterDelete['stderr'], "\r\n"),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($missingDir);
    $cleanup($storeDir);
}
