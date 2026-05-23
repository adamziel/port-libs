<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanEventCollector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-checkpoint-events-' . bin2hex(random_bytes(6));
$dir = 'wp-content/uploads/2026/05';
$blockedDir = $dir . '/private-cache';

try {
    wordpress_scanner_checkpoint_events_write($root, $dir . '/after.jpg', 'after!');
    wordpress_scanner_checkpoint_events_write($root, $dir . '/hero.jpg', 'hero');
    wordpress_scanner_checkpoint_events_write($root, $blockedDir . '/draft.zip', 'private export');

    $collector = new FolderScanEventCollector('wordpress-media');
    $scanner = new FileInfoScanner(
        $root,
        directoryLister: static function (string $path) use ($root, $blockedDir): array {
            if ($path === wordpress_scanner_checkpoint_events_path($root, $blockedDir)) {
                throw new RuntimeException('permission denied');
            }

            return wordpress_scanner_checkpoint_events_entries($path);
        },
    );

    $result = $scanner->walkWithCheckpoint(
        [$dir],
        hashBlocks: true,
        blockSize: 4,
        folder: 'wordpress-media',
        eventCollector: $collector,
    );

    echo json_encode([
        'folder' => 'wordpress-media',
        'completed' => array_map(
            static fn (FileInfo $file): array => [
                'name' => $file->name,
                'type' => $file->type,
                'bytes' => $file->size,
                'blocks' => count($file->blocks),
            ],
            $result->files,
        ),
        'resume' => $result->toArray(),
        'scanEvents' => $result->scanEvents(),
        'scanErrors' => $result->scanErrors(),
        'failureEvents' => $result->failureEvents(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_checkpoint_events_rm($root);
}

function wordpress_scanner_checkpoint_events_path(string $root, string $name): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
}

function wordpress_scanner_checkpoint_events_write(string $root, string $name, string $bytes): void
{
    $path = wordpress_scanner_checkpoint_events_path($root, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner checkpoint event example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner checkpoint event example file');
    }
}

/**
 * @return list<string>
 */
function wordpress_scanner_checkpoint_events_entries(string $path): array
{
    $entries = scandir($path);
    if (!is_array($entries)) {
        throw new RuntimeException('Failed to list scanner checkpoint event example directory');
    }

    return array_values(array_filter(
        $entries,
        static fn (string $entry): bool => $entry !== '.' && $entry !== '..',
    ));
}

function wordpress_scanner_checkpoint_events_rm(string $path): void
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
        wordpress_scanner_checkpoint_events_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
