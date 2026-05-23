<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanProgress;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-resume-' . bin2hex(random_bytes(6));
$dir = 'wp-content/uploads/2026/05';

try {
    wordpress_scanner_resume_write($root, $dir . '/hero.jpg', 'abcdefgh');
    wordpress_scanner_resume_write($root, $dir . '/thumb.jpg', '12345');

    $scanner = new FileInfoScanner($root);
    $progress = [];
    $stopAfterFirstHash = false;

    $checkpoint = $scanner->walkWithCheckpoint(
        [$dir],
        hashBlocks: true,
        blockSize: 4,
        progressLogger: static function (FolderScanProgress $event) use (&$progress, &$stopAfterFirstHash): void {
            $progress[] = $event->toArray();
            $stopAfterFirstHash = true;
        },
        folder: 'wordpress-media',
        shouldCancel: static function (?string $path) use (&$stopAfterFirstHash): bool {
            return $stopAfterFirstHash && $path !== null;
        },
    );

    $resumeProgress = [];
    $resumed = $scanner->walkWithCheckpoint(
        $checkpoint->resumeSubs,
        hashBlocks: true,
        blockSize: 4,
        currentFiles: $checkpoint->resumeCurrentFiles(),
        progressLogger: static function (FolderScanProgress $event) use (&$resumeProgress): void {
            $resumeProgress[] = $event->toArray();
        },
        folder: 'wordpress-media',
    );

    echo json_encode([
        'folder' => 'wordpress-media',
        'cancelledAt' => $checkpoint->cancelledAt,
        'completedBeforeCancel' => $checkpoint->completedPaths(),
        'checkpointProgress' => $progress,
        'resumeSubs' => $checkpoint->resumeSubs,
        'resumedItems' => array_map(
            static fn (FileInfo $file): array => [
                'name' => $file->name,
                'bytes' => $file->size,
                'blocks' => count($file->blocks),
            ],
            $resumed->files,
        ),
        'resumeProgress' => $resumeProgress,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_resume_rm($root);
}

function wordpress_scanner_resume_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner resume example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner resume example file');
    }
}

function wordpress_scanner_resume_rm(string $path): void
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
        wordpress_scanner_resume_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
