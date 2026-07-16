<?php

declare(strict_types=1);

use PortLibs\Syncthing\ActiveDownload;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FolderErrorTracker;
use PortLibs\Syncthing\ProgressEmitter;
use PortLibs\Syncthing\PullFinisher;
use PortLibs\Syncthing\PullJobQueue;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-folder-errors-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $bytes = str_repeat('wordpress media block needing repair ', 6000);
    $blocks = (new BlockList())->fromBytes($bytes, BlockList::MIN_BLOCK_SIZE);
    $file = new FileInfo(
        name: 'wp-content/uploads/2026/needs-retry.jpg',
        modifiedS: 1_780_210_000,
        version: VersionVector::fromCounters([202 => 8]),
        size: strlen($bytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        sequence: 78,
        blocks: $blocks,
        modifiedBy: 202,
    );

    $queue = new PullJobQueue();
    $queue->push($file->name, $file->size, $file->modifiedS * 1_000_000_000);
    $queue->pop();

    $emitter = new ProgressEmitter();
    $emitter->register(new ActiveDownload('wordpress-media', $file, [], availableUpdated: 1, created: 1));

    $logged = [];
    $folderErrors = new FolderErrorTracker(
        'wordpress-media',
        static function (string $type, array $data) use (&$logged): void {
            $logged[] = ['type' => $type, 'data' => $data];
        },
    );
    $folderErrors->startPull();
    $folderErrors->addScanError('wp-content/uploads/2026/.stignore', 'scan: permission denied');
    $folderErrors->startPullerIteration();

    $temporary = new PullTemporaryFile($file, $root);
    $temporary->fail('peer response hash mismatch');

    $finisher = new PullFinisher(
        $queue,
        $emitter,
        folderId: 'wordpress-media',
        folderErrors: $folderErrors,
    );

    $finish = $finisher->finish($temporary);
    $pull = $folderErrors->completePull(changed: 0);

    echo json_encode([
        'pullInSync' => $pull->success,
        'finisherHandled' => $finish->handled,
        'queueProgress' => $queue->progressCount(),
        'progressEmitterRegistrations' => $emitter->registeredCount(),
        'persistentErrors' => $folderErrors->errors(),
        'folderErrorsEvent' => $pull->folderErrorsEvent,
        'loggedEvents' => $logged,
        'tempFileRetainedForRetry' => file_exists($temporary->tempPath()),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($root);
    }
}
