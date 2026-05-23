<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanProgress;
use PortLibs\Syncthing\FolderScanService;
use PortLibs\Syncthing\WordPressOptionCheckpointStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-folder-scan-option-store-' . bin2hex(random_bytes(6));
$options = [];
$dir = 'wp-content/uploads/2026/05';

try {
    wordpress_folder_scan_option_store_write($root, $dir . '/hero.jpg', 'abcdefgh');
    wordpress_folder_scan_option_store_write($root, $dir . '/thumb.jpg', '12345');

    $store = wordpress_folder_scan_option_store($options);
    $service = new FolderScanService(
        'wordpress-media',
        new FileInfoScanner($root),
        $store,
        ttlSeconds: 3600,
    );

    $cancelAfterFirstHash = false;
    $first = $service->scan(
        [$dir],
        hashBlocks: true,
        blockSize: 4,
        progressLogger: static function (FolderScanProgress $progress) use (&$cancelAfterFirstHash): void {
            $cancelAfterFirstHash = true;
        },
        shouldCancel: static function (?string $path) use (&$cancelAfterFirstHash): bool {
            return $cancelAfterFirstHash && $path !== null;
        },
        now: 2000,
    );

    $laterRequest = new FolderScanService(
        'wordpress-media',
        new FileInfoScanner($root),
        wordpress_folder_scan_option_store($options),
        ttlSeconds: 3600,
    );
    $resumed = $laterRequest->scan(hashBlocks: true, blockSize: 4, now: 2015);
    $optionName = $store->optionName('wordpress-media');

    echo json_encode([
        'route' => '/wp-json/local-first/v1/folder-scan/wordpress-media',
        'storage' => [
            'kind' => 'wp_option',
            'optionName' => $optionName,
            'storedRevision' => $options[$optionName]['revision'] ?? null,
            'compareAndSwapRevision' => $resumed->revision,
        ],
        'firstStatus' => $first->toRestStatus(),
        'resumedStatus' => $resumed->toRestStatus(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_folder_scan_option_store_rm($root);
}

function wordpress_folder_scan_option_store(array &$options): WordPressOptionCheckpointStore
{
    return new WordPressOptionCheckpointStore(
        WordPressOptionCheckpointStore::DEFAULT_OPTION_PREFIX,
        static function (string $key) use (&$options): mixed {
            return $options[$key] ?? null;
        },
        static function (string $key, mixed $value) use (&$options): bool {
            $options[$key] = $value;
            return true;
        },
        static function (string $key) use (&$options): bool {
            unset($options[$key]);
            return true;
        },
    );
}

function wordpress_folder_scan_option_store_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create folder scan option-store example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write folder scan option-store example file');
    }
}

function wordpress_folder_scan_option_store_rm(string $path): void
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
        wordpress_folder_scan_option_store_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
