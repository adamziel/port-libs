<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanProgress;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-error-cancel-' . bin2hex(random_bytes(6));
$dir = 'wp-content/uploads/2026/05';

try {
    wordpress_scanner_error_cancel_write($root, $dir . '/hero.jpg', 'abcdefgh');
    wordpress_scanner_error_cancel_write($root, $dir . '/thumb.jpg', '12345');
    wordpress_scanner_error_cancel_write($root, $dir . '/metadata-error.jpg', 'metadata read fails');
    wordpress_scanner_error_cancel_write($root, $dir . '/private-cache/export.zip', 'private export');

    $errors = [];
    $failureEvents = [];
    $progress = [];
    $stopAfterFirstHash = false;

    $scanner = new FileInfoScanner(
        $root,
        scanXattrs: true,
        xattrLister: static function (string $path): array {
            if (basename($path) === 'metadata-error.jpg') {
                throw new RuntimeException('host xattr metadata is temporarily unavailable');
            }

            return [];
        },
        directoryLister: static function (string $path) use ($root, $dir): array {
            if ($path === $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir . '/private-cache')) {
                throw new RuntimeException('permission denied');
            }

            return wordpress_scanner_error_cancel_entries($path);
        },
    );

    $files = $scanner->walk(
        [$dir],
        hashBlocks: true,
        blockSize: 4,
        progressLogger: static function (FolderScanProgress $event) use (&$progress, &$stopAfterFirstHash): void {
            $progress[] = $event->toArray();
            $stopAfterFirstHash = true;
        },
        folder: 'wordpress-media',
        errorLogger: static function (string $path, Throwable $error, string $phase) use (&$errors): void {
            $errors[] = [
                'path' => $path,
                'phase' => $phase,
                'error' => $error->getMessage(),
            ];
        },
        shouldCancel: static function (?string $path) use (&$stopAfterFirstHash): bool {
            return $stopAfterFirstHash && $path !== null;
        },
        failureLogger: static function (string $type, array $data) use (&$failureEvents): void {
            $failureEvents[] = ['type' => $type, 'data' => $data];
        },
    );

    echo json_encode([
        'folder' => 'wordpress-media',
        'files' => array_map(
            static fn (FileInfo $file): array => [
                'name' => $file->name,
                'type' => $file->type,
                'bytes' => $file->size,
                'blocks' => count($file->blocks),
            ],
            $files,
        ),
        'scanProgress' => $progress,
        'scanErrors' => $errors,
        'walkFailureEvents' => $failureEvents,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_error_cancel_rm($root);
}

function wordpress_scanner_error_cancel_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner error/cancel example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner error/cancel example file');
    }
}

/**
 * @return list<string>
 */
function wordpress_scanner_error_cancel_entries(string $path): array
{
    $entries = scandir($path);
    if (!is_array($entries)) {
        throw new RuntimeException('Failed to list scanner error/cancel example directory');
    }

    return array_values(array_filter(
        $entries,
        static fn (string $entry): bool => $entry !== '.' && $entry !== '..',
    ));
}

function wordpress_scanner_error_cancel_rm(string $path): void
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
        wordpress_scanner_error_cancel_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
