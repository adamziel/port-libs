<?php

declare(strict_types=1);

use PortLibs\Quadrable\HashTree;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$missingDir = sys_get_temp_dir() . '/quadrable-wp-get-missing-' . bin2hex(random_bytes(6));
$storeDir = sys_get_temp_dir() . '/quadrable-wp-get-guard-' . bin2hex(random_bytes(6));

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
    $noArgs = QuadbStore::noArgumentCommandOutput();
    $missingStore = QuadbStore::getCommandOutput($missingDir, 'wp_options:siteurl');

    if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
        throw new RuntimeException('unable to create WordPress snapshot store directory');
    }

    $missingOptionBeforeImport = QuadbStore::getCommandOutput($storeDir, 'wp_options:siteurl');
    $repo = QuadbStore::open($storeDir);
    $emptyStoreBootstrapped = $repo->rootText() === '0x' . HashTree::EMPTY_HASH . "\n";

    $repo->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );

    $siteUrl = QuadbStore::getCommandOutput($storeDir, 'wp_options:siteurl');
    $home = QuadbStore::getCommandOutput($storeDir, 'wp_options:home');

    echo json_encode([
        'scenario' => 'guard WordPress option reads with upstream-shaped quadb no-arg and get command output',
        'noArgumentExitCode' => $noArgs['exitCode'],
        'noArgumentStderr' => $noArgs['stderr'],
        'noArgumentShowsUsage' => str_contains($noArgs['stdout'], 'quadb [options] get [--int] [--] <key>'),
        'missingStoreExitCode' => $missingStore['exitCode'],
        'missingStoreCreatedDirectory' => is_dir($missingDir),
        'missingOptionBeforeImport' => rtrim($missingOptionBeforeImport['stderr'], "\r\n"),
        'emptyStoreBootstrapped' => $emptyStoreBootstrapped,
        'siteUrlStdout' => rtrim($siteUrl['stdout'], "\r\n"),
        'siteUrlExitCode' => $siteUrl['exitCode'],
        'homeMissingStderr' => rtrim($home['stderr'], "\r\n"),
        'homeMissingExitCode' => $home['exitCode'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($missingDir);
    $cleanup($storeDir);
}
