<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-ignoreperms-' . bin2hex(random_bytes(6));
$name = 'wp-content/uploads/2026/05/noisy-host-media.jpg';
$path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);

try {
    wordpress_scanner_ignoreperms_write($path, 'wordpress media bytes', 1_700_006_300, 0644);

    $strictScanner = new FileInfoScanner($root);
    $current = $strictScanner->walk([$name])[0];

    chmod($path, 0600);
    clearstatcache(true, $path);

    $strictPermissionChange = $strictScanner->walk([$name], currentFiles: [$current]);
    $ignorePermsScanner = new FileInfoScanner($root, ignorePerms: true);
    $ignoredPermissionChange = $ignorePermsScanner->walk([$name], currentFiles: [$current]);
    $windowBase = $ignorePermsScanner->scan($name);

    touch($path, 1_700_006_301);
    clearstatcache(true, $path);

    $strictTimeChange = $ignorePermsScanner->walk([$name], currentFiles: [$windowBase]);
    $windowScanner = new FileInfoScanner($root, ignorePerms: true, modTimeWindowNs: 2_000_000_000);
    $windowedTimeChange = $windowScanner->walk([$name], currentFiles: [$windowBase]);

    echo json_encode([
        'path' => $name,
        'strictPermissionChangeItems' => count($strictPermissionChange),
        'ignorePermsPermissionChangeItems' => count($ignoredPermissionChange),
        'strictOneSecondMtimeChangeItems' => count($strictTimeChange),
        'windowedOneSecondMtimeChangeItems' => count($windowedTimeChange),
        'noPermissionsAdvertised' => $windowBase->noPermissions,
        'retainedPermissions' => sprintf('0%03o', $windowBase->permissions & 0777),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_ignoreperms_rm($root);
}

function wordpress_scanner_ignoreperms_write(string $path, string $bytes, int $mtime, int $mode): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner ignore-perms example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner ignore-perms example file');
    }
    chmod($path, $mode);
    touch($path, $mtime);
    clearstatcache(true, $path);
}

function wordpress_scanner_ignoreperms_rm(string $path): void
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        wordpress_scanner_ignoreperms_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
