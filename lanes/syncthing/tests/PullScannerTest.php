<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullFinalizationResult;
use PortLibs\Syncthing\PullFinisher;
use PortLibs\Syncthing\PullJobQueue;
use PortLibs\Syncthing\PullScanner;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\VersionVector;

return [
    'pullScannerRoutine aggregates duplicate finalization paths until close' => static function (TestRunner $t): void {
        $scheduled = [];
        $scanner = new PullScanner(
            static function (array $paths) use (&$scheduled): ?Throwable {
                $scheduled[] = $paths;
                return null;
            },
            folderId: 'wordpress-media',
        );
        $finalization = new PullFinalizationResult(
            closed: true,
            finalized: false,
            error: 'checking existing file: file modified but not rescanned; will try again later',
            tempName: RequestServer::temporaryName('wp-content/uploads/2026/local-edit.jpg'),
            finalName: 'wp-content/uploads/2026/local-edit.jpg',
            scanNames: [
                'wp-content/uploads/2026/local-edit.jpg',
                'wp-content/uploads/2026/local-edit.jpg',
            ],
        );

        $scanner->queueFinalization($finalization);
        $scanner->queueFile('wp-content/uploads/2026/local-edit.jpg', 'explicit-repeat');

        $t->same('wordpress-media', $scanner->folderId());
        $t->same([], $scheduled);
        $t->same(1, $scanner->pendingCount());
        $t->same(['wp-content/uploads/2026/local-edit.jpg'], $scanner->pendingPaths());
        $t->same([
            [
                'path' => 'wp-content/uploads/2026/local-edit.jpg',
                'type' => PullScanner::TYPE_FILE,
                'sources' => ['explicit-repeat', 'finalization'],
            ],
        ], $scanner->pendingItems());

        $result = $scanner->close();
        $again = $scanner->close();

        $t->true($result->scheduled);
        $t->same(null, $result->error);
        $t->same(['wp-content/uploads/2026/local-edit.jpg'], $result->paths);
        $t->same([['wp-content/uploads/2026/local-edit.jpg']], $scheduled);
        $t->same([], $scanner->pendingPaths());
        $t->same(1, count($scanner->scheduledResults()));
        $t->true($again->alreadyClosed);
        $t->throws(LogicException::class, static fn () => $scanner->queueFile('wp-content/uploads/2026/too-late.jpg'));
    },
    'pullScannerRoutine keeps file and directory scan scheduling separate before one scan batch' => static function (TestRunner $t): void {
        $scheduled = [];
        $scanner = new PullScanner(static function (array $paths) use (&$scheduled): void {
            $scheduled[] = $paths;
        });
        $deletedMedia = new FileInfo(
            name: 'wp-content/uploads/2026/deleted-media.jpg',
            deleted: true,
            type: FileInfo::TYPE_FILE,
        );
        $deletedGallery = new FileInfo(
            name: 'wp-content/uploads/2026/stale-gallery',
            deleted: true,
            type: FileInfo::TYPE_DIRECTORY,
        );

        $scanner->queueDeletion($deletedMedia);
        $scanner->queueDeletion($deletedGallery);
        $scanner->queueDirectory('wp-content/uploads/2026/stale-gallery/local-crops', 'directory-child');
        $scanner->queueFile('wp-content/uploads/2026/deleted-media.jpg', 'duplicate-delete');

        $t->same([
            PullScanner::TYPE_FILE => 1,
            PullScanner::TYPE_DIRECTORY => 2,
            PullScanner::TYPE_UNKNOWN => 0,
            PullScanner::TYPE_MIXED => 0,
        ], $scanner->pendingCountsByType());
        $t->same([
            [
                'path' => 'wp-content/uploads/2026/deleted-media.jpg',
                'type' => PullScanner::TYPE_FILE,
                'sources' => ['deletion', 'duplicate-delete'],
            ],
            [
                'path' => 'wp-content/uploads/2026/stale-gallery',
                'type' => PullScanner::TYPE_DIRECTORY,
                'sources' => ['deletion'],
            ],
            [
                'path' => 'wp-content/uploads/2026/stale-gallery/local-crops',
                'type' => PullScanner::TYPE_DIRECTORY,
                'sources' => ['directory-child'],
            ],
        ], $scanner->pendingItems());

        $result = $scanner->close();

        $t->true($result->scheduled);
        $t->same([
            'wp-content/uploads/2026/deleted-media.jpg',
            'wp-content/uploads/2026/stale-gallery',
            'wp-content/uploads/2026/stale-gallery/local-crops',
        ], $result->paths);
        $t->same([$result->paths], $scheduled);
    },
    'finisher queues failed finalization scan names without scheduling during pull' => static function (TestRunner $t): void {
        $root = syncthing_pull_scanner_root();
        try {
            $name = 'wp-content/uploads/2026/editor-local-crop.jpg';
            $scannedBytes = str_repeat('scanned crop ', 5000);
            $localBytes = str_repeat('local editor crop ', 5100);
            $remoteBytes = str_repeat('remote playground crop ', 5200);
            $current = syncthing_pull_scanner_file($name, $scannedBytes, sequence: 81);
            $remote = syncthing_pull_scanner_file(
                $name,
                $remoteBytes,
                sequence: 82,
                version: VersionVector::fromCounters([202 => 1]),
                modifiedBy: 202,
            );
            $currentPath = syncthing_pull_scanner_write_current_file($root, $current, $localBytes);
            touch($currentPath, $current->modifiedS + 5);

            $scheduled = [];
            $scanner = new PullScanner(static function (array $paths) use (&$scheduled): void {
                $scheduled[] = $paths;
            });
            $temporary = new PullTemporaryFile($remote, $root, currentFile: $current);
            $temporary->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledBeforeLocalEditCheck');

            $queue = syncthing_pull_scanner_started_queue($remote->name);
            $finisher = new PullFinisher($queue, folderId: 'wordpress-media', pullScanner: $scanner);
            $finish = $finisher->finish($temporary);

            $t->true($finish->handled);
            $t->true(!$finish->finalization->finalized);
            $t->same('checking existing file: file modified but not rescanned; will try again later', $finish->finalization->error);
            $t->same([$name], $finish->finalization->scanNames);
            $t->same([$name], $scanner->pendingPaths());
            $t->same([], $scheduled);
            $t->same(0, $queue->progressCount());
            $t->true(file_exists($temporary->tempPath()));
            $t->same($localBytes, (string) file_get_contents($currentPath));

            $scanResult = $scanner->close();

            $t->true($scanResult->scheduled);
            $t->same([$name], $scanResult->paths);
            $t->same([[$name]], $scheduled);
        } finally {
            syncthing_pull_scanner_rm($root);
        }
    },
    'pullScannerRoutine captures schedule callback failures and remains closed' => static function (TestRunner $t): void {
        $scanner = new PullScanner(static function (): RuntimeException {
            return new RuntimeException('scan service paused during WordPress import');
        });
        $scanner->queueFile('wp-content/uploads/2026/retry-scan.jpg');

        $result = $scanner->close();

        $t->true(!$result->scheduled);
        $t->same('scan service paused during WordPress import', $result->error);
        $t->same(['wp-content/uploads/2026/retry-scan.jpg'], $result->paths);
        $t->same([], $scanner->pendingPaths());
        $t->throws(LogicException::class, static fn () => $scanner->queueDirectory('wp-content/uploads/2026/retry-dir'));
    },
];

function syncthing_pull_scanner_file(
    string $name,
    string $bytes,
    int $sequence,
    ?VersionVector $version = null,
    int $modifiedBy = 101,
): FileInfo {
    $blocks = (new BlockList())->fromBytes($bytes, BlockList::MIN_BLOCK_SIZE);

    return new FileInfo(
        name: $name,
        modifiedS: 1_700_004_000 + $sequence,
        version: $version ?? VersionVector::fromCounters([101 => $sequence]),
        size: strlen($bytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        sequence: $sequence,
        blocks: $blocks,
        modifiedBy: $modifiedBy,
    );
}

function syncthing_pull_scanner_started_queue(string $name): PullJobQueue
{
    $queue = new PullJobQueue();
    $queue->push($name);
    $queue->pop();

    return $queue;
}

function syncthing_pull_scanner_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-pull-scanner-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary pull scanner root');
    }

    return $root;
}

function syncthing_pull_scanner_write_current_file(string $root, FileInfo $file, string $bytes): string
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file->name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create current file parent directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write current file');
    }
    chmod($path, $file->permissions & 0777);
    touch($path, $file->modifiedS);

    return $path;
}

function syncthing_pull_scanner_rm(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}
