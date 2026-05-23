<?php

declare(strict_types=1);

use PortLibs\Syncthing\ActiveDownload;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\ProgressEmitter;
use PortLibs\Syncthing\PullDbUpdater;
use PortLibs\Syncthing\PullFinisher;
use PortLibs\Syncthing\PullJobQueue;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-finisher-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $bytes = str_repeat('wordpress finished media ', 9000);
    $blocks = (new BlockList())->fromBytes($bytes, BlockList::MIN_BLOCK_SIZE);
    $file = new FileInfo(
        name: 'wp-content/uploads/2026/finished-hero.jpg',
        modifiedS: 1_780_200_000,
        version: VersionVector::fromCounters([202 => 7]),
        size: strlen($bytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: 0644,
        sequence: 77,
        blocks: $blocks,
        modifiedBy: 202,
    );

    $queue = new PullJobQueue();
    $queue->push($file->name, $file->size, $file->modifiedS * 1_000_000_000);
    $queue->pop();

    $emitter = new ProgressEmitter();
    $emitter->register(new ActiveDownload('wordpress-media', $file, [0], availableUpdated: 1, created: 1));

    $dbUpdater = new PullDbUpdater(disableFsync: true);
    $events = [];
    $finisher = new PullFinisher(
        $queue,
        $emitter,
        $dbUpdater,
        folderId: 'wordpress-media',
        itemFinished: static function (array $event) use (&$events): void {
            $events[] = $event;
        },
    );

    $temporary = new PullTemporaryFile($file, $root);
    foreach ($file->blocks as $block) {
        $temporary->writeBlock(
            $block,
            substr($bytes, $block->offset, $block->size),
            source: 'pulledFromPlaygroundPeer',
        );
    }

    $finish = $finisher->finish($temporary, pullTotal: count($file->blocks));
    $changed = $dbUpdater->close();

    echo json_encode([
        'handledByFinisher' => $finish->handled,
        'itemFinished' => $events,
        'queue' => $queue->jobs(1, 10),
        'progressEmitterRegistrations' => $emitter->registeredCount(),
        'dbChangedJobs' => $changed,
        'dbUpdateBatches' => count($dbUpdater->updateBatches()),
        'pullErrors' => $finisher->pullErrors(),
        'blockStats' => $finisher->blockStats(),
        'finalSha256' => hash_file('sha256', $temporary->finalPath()),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
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
