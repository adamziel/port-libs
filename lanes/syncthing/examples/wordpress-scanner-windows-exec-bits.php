<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-windows-exec-' . bin2hex(random_bytes(6));
$name = 'wp-content/plugins/local-first-sync/build/index.php';
$bytes = '<?php echo "plugin asset";';
$path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);

try {
    wordpress_scanner_windows_exec_write($path, $bytes, 1_700_006_150, 0644);

    $current = new FileInfo(
        name: $name,
        modifiedS: 1_700_006_150,
        size: strlen($bytes),
        type: FileInfo::TYPE_FILE,
        permissions: 0755,
    );

    $posixScanner = new FileInfoScanner($root, platformFamily: 'Linux');
    $posixChanged = $posixScanner->walk([$name], currentFiles: [$current]);

    $windowsScanner = new FileInfoScanner($root, platformFamily: 'Windows');
    $windowsScanned = $windowsScanner->scan($name, currentFile: $current);
    $windowsChanged = $windowsScanner->walk([$name], currentFiles: [$current]);

    echo json_encode([
        'path' => $name,
        'diskPermissions' => sprintf('0%03o', 0644),
        'currentIndexPermissions' => sprintf('0%03o', $current->permissions & 0777),
        'posixPermissionChangeItems' => count($posixChanged),
        'windowsPermissionChangeItems' => count($windowsChanged),
        'windowsAdvertisedPermissions' => sprintf('0%03o', $windowsScanned->permissions & 0777),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_windows_exec_rm($root);
}

function wordpress_scanner_windows_exec_write(string $path, string $bytes, int $mtime, int $mode): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner Windows exec example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner Windows exec example file');
    }
    chmod($path, $mode);
    touch($path, $mtime);
    clearstatcache(true, $path);
}

function wordpress_scanner_windows_exec_rm(string $path): void
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
        wordpress_scanner_windows_exec_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
