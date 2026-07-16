<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$missingDir = sys_get_temp_dir() . '/quadrable-wp-int-rw-missing-' . bin2hex(random_bytes(6));
$storeDir = sys_get_temp_dir() . '/quadrable-wp-int-rw-guard-' . bin2hex(random_bytes(6));

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
    $missingPut = QuadbStore::putIntegerCommandOutput(
        $missingDir,
        '1',
        'wp_options:siteurl=https://example.test'
    );
    $missingGet = QuadbStore::getIntegerCommandOutput($missingDir, '1');

    if (!mkdir($storeDir, 0755, true) && !is_dir($storeDir)) {
        throw new RuntimeException('unable to create WordPress snapshot store directory');
    }

    $badPutKey = QuadbStore::putIntegerCommandOutput($storeDir, 'abc', 'ignored');
    $badGetKey = QuadbStore::getIntegerCommandOutput($storeDir, '-1');
    $badDeleteKey = QuadbStore::deleteIntegerCommandOutput($storeDir, '2147483648');
    $numericPrefixPut = QuadbStore::putIntegerCommandOutput(
        $storeDir,
        '1x',
        'wp_options:siteurl=https://example.test'
    );
    $siteUrl = QuadbStore::getIntegerCommandOutput($storeDir, '1');
    $missingDelete = QuadbStore::deleteIntegerCommandOutput($storeDir, '2');
    $deleteSiteUrl = QuadbStore::deleteIntegerCommandOutput($storeDir, '1');
    $afterDelete = QuadbStore::getIntegerCommandOutput($storeDir, '1');

    echo json_encode([
        'scenario' => 'guard WordPress integer snapshot row reads and writes with upstream-shaped quadb get/put/del --int output',
        'missingPutExitCode' => $missingPut['exitCode'],
        'missingGetExitCode' => $missingGet['exitCode'],
        'missingStoreCreatedDirectory' => is_dir($missingDir),
        'badPutKeyStderr' => rtrim($badPutKey['stderr'], "\r\n"),
        'badGetKeyStderr' => rtrim($badGetKey['stderr'], "\r\n"),
        'badDeleteKeyStderr' => rtrim($badDeleteKey['stderr'], "\r\n"),
        'numericPrefixPutExitCode' => $numericPrefixPut['exitCode'],
        'siteUrlStdout' => rtrim($siteUrl['stdout'], "\r\n"),
        'missingDeleteExitCode' => $missingDelete['exitCode'],
        'deleteSiteUrlExitCode' => $deleteSiteUrl['exitCode'],
        'siteUrlAfterDeleteStderr' => rtrim($afterDelete['stderr'], "\r\n"),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($missingDir);
    $cleanup($storeDir);
}
