<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanCheckpoint;
use PortLibs\Syncthing\FolderScanCheckpointConflictException;
use PortLibs\Syncthing\FolderScanCheckpointStore;
use PortLibs\Syncthing\FolderScanProgress;
use PortLibs\Syncthing\FolderScanScheduler;
use PortLibs\Syncthing\FolderScanService;

return [
    'scan folders publishes every registered folder with independent revisions and expiry' => static function (TestRunner $t): void {
        $mediaRoot = syncthing_folder_scan_scheduler_root();
        $contentRoot = syncthing_folder_scan_scheduler_root();
        try {
            syncthing_folder_scan_scheduler_write($mediaRoot, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            syncthing_folder_scan_scheduler_write($contentRoot, 'wp-content/themes/twentytwentyseven/style.css', 'body{}');

            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder(
                'wordpress-media',
                new FolderScanService('wordpress-media', new FileInfoScanner($mediaRoot), new FolderScanCheckpointStore(), ttlSeconds: 60),
            );
            $scheduler->addFolder(
                'wordpress-content',
                new FolderScanService('wordpress-content', new FileInfoScanner($contentRoot), new FolderScanCheckpointStore(), ttlSeconds: 120),
            );

            $result = $scheduler->scanFolders(
                [
                    'wordpress-media' => ['wp-content/uploads/2026/05/'],
                    'wordpress-content' => ['wp-content/themes/twentytwentyseven'],
                ],
                hashBlocks: true,
                blockSize: 4,
                now: 1000,
            );
            $status = $result->toRestStatus();

            $t->true($result->successful());
            $t->same(['wordpress-media', 'wordpress-content'], $scheduler->runningFolderIds());
            $t->same(1, $result->snapshot('wordpress-media')?->revision);
            $t->same(1060, $result->snapshot('wordpress-media')?->expiresAt);
            $t->same(1, $result->snapshot('wordpress-content')?->revision);
            $t->same(1120, $result->snapshot('wordpress-content')?->expiresAt);
            $t->same(
                ['wp-content/uploads/2026/05', 'wp-content/uploads/2026/05/hero.jpg'],
                $result->snapshot('wordpress-media')?->checkpoint->completedPaths(),
            );
            $t->same(
                ['wp-content/themes/twentytwentyseven', 'wp-content/themes/twentytwentyseven/style.css'],
                $result->snapshot('wordpress-content')?->checkpoint->completedPaths(),
            );
            $t->same(hash('sha256', 'abcd'), $result->snapshot('wordpress-media')?->checkpoint->currentFile('wp-content/uploads/2026/05/hero.jpg')?->blocks[0]->hashHex);
            $t->same(true, $status['successful']);
            $t->same(2, $status['folderCount']);
            $t->same(0, $status['errorCount']);
            $t->same('complete', $status['folders']['wordpress-media']['state']);
        } finally {
            syncthing_folder_scan_scheduler_rm($mediaRoot);
            syncthing_folder_scan_scheduler_rm($contentRoot);
        }
    },
    'scan folders records paused errors and continues running folders' => static function (TestRunner $t): void {
        $mediaRoot = syncthing_folder_scan_scheduler_root();
        $contentRoot = syncthing_folder_scan_scheduler_root();
        try {
            syncthing_folder_scan_scheduler_write($mediaRoot, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            syncthing_folder_scan_scheduler_write($contentRoot, 'wp-content/plugins/local-first/plugin.php', '<?php');

            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', new FolderScanService('wordpress-media', new FileInfoScanner($mediaRoot), new FolderScanCheckpointStore()));
            $scheduler->addFolder('wordpress-content', new FolderScanService('wordpress-content', new FileInfoScanner($contentRoot), new FolderScanCheckpointStore()), running: false);

            $result = $scheduler->scanFolders(hashBlocks: true, blockSize: 4, now: 1200);

            $t->true(!$result->successful());
            $t->same(1, $result->snapshot('wordpress-media')?->revision);
            $t->same(null, $result->snapshot('wordpress-content'));
            $t->contains(FolderScanScheduler::ERR_FOLDER_PAUSED, $result->error('wordpress-content')?->getMessage() ?? '');
            $t->same(['wordpress-media' => 1], array_map(static fn ($snapshot): int => $snapshot->revision, $result->snapshots()));
            $t->same(['wordpress-content' => 'folder paused: wordpress-content'], $result->errorMessages());
            $t->throws(RuntimeException::class, static fn () => $scheduler->scanFolder('notexisting'));

            $scheduler->resumeFolder('wordpress-content');
            $second = $scheduler->scanFolder('wordpress-content', hashBlocks: true, blockSize: 4, now: 1210);
            $t->same(1, $second->revision);
            $t->same(false, $scheduler->isPaused('wordpress-content'));
        } finally {
            syncthing_folder_scan_scheduler_rm($mediaRoot);
            syncthing_folder_scan_scheduler_rm($contentRoot);
        }
    },
    'scan folder subdirs accepts empty and slash-normalized upstream subdir forms' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_scheduler_root();
        try {
            syncthing_folder_scan_scheduler_write($root, 'foo/index.html', '<main></main>');

            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-site', new FolderScanService('wordpress-site', new FileInfoScanner($root), new FolderScanCheckpointStore()));

            $snapshot = $scheduler->scanFolderSubdirs('wordpress-site', ['baz/', 'foo', ''], hashBlocks: true, blockSize: 4, now: 1300);

            $t->same(1, $snapshot->revision);
            $t->same('complete', $snapshot->checkpoint->state());
            $t->same(['foo', 'foo/index.html'], $snapshot->checkpoint->completedPaths());
            $t->same([], $snapshot->checkpoint->scanErrors());
        } finally {
            syncthing_folder_scan_scheduler_rm($root);
        }
    },
    'stale publish in one folder is returned as an error while neighboring folders commit' => static function (TestRunner $t): void {
        $mediaRoot = syncthing_folder_scan_scheduler_root();
        $contentRoot = syncthing_folder_scan_scheduler_root();
        try {
            syncthing_folder_scan_scheduler_write($mediaRoot, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            syncthing_folder_scan_scheduler_write($contentRoot, 'wp-content/mu-plugins/sync.php', '<?php');

            $mediaStore = new FolderScanCheckpointStore();
            $contentStore = new FolderScanCheckpointStore();
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', new FolderScanService('wordpress-media', new FileInfoScanner($mediaRoot), $mediaStore, ttlSeconds: 60));
            $scheduler->addFolder('wordpress-content', new FolderScanService('wordpress-content', new FileInfoScanner($contentRoot), $contentStore, ttlSeconds: 90));
            $injected = false;

            $result = $scheduler->scanFolders(
                hashBlocks: true,
                blockSize: 4,
                progressLogger: static function (FolderScanProgress $progress) use ($mediaStore, &$injected): void {
                    if ($injected || $progress->folder !== 'wordpress-media') {
                        return;
                    }
                    $injected = true;
                    $mediaStore->save(new FolderScanCheckpoint('wordpress-media'), expectedRevision: 0, now: 1400, ttlSeconds: 60);
                },
                now: 1400,
            );

            $t->true(!$result->successful());
            $t->true($result->error('wordpress-media') instanceof FolderScanCheckpointConflictException);
            $t->same(null, $result->snapshot('wordpress-media'));
            $t->same(1, $mediaStore->load('wordpress-media', 1400)?->revision);
            $t->same('idle', $mediaStore->load('wordpress-media', 1400)?->checkpoint->state());
            $t->same(1, $result->snapshot('wordpress-content')?->revision);
            $t->same(1490, $result->snapshot('wordpress-content')?->expiresAt);
            $t->same(['wordpress-media' => 'Folder scan checkpoint revision conflict for wordpress-media: expected 0, actual 1'], $result->errorMessages());
            $t->same('complete', $contentStore->load('wordpress-content', 1400)?->checkpoint->state());
        } finally {
            syncthing_folder_scan_scheduler_rm($mediaRoot);
            syncthing_folder_scan_scheduler_rm($contentRoot);
        }
    },
    'delayed scan timing resets next scan and only publishes checkpoints when due' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_scheduler_root();
        try {
            syncthing_folder_scan_scheduler_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            $service = new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore(), ttlSeconds: 60);
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', $service);

            $t->true($scheduler->delayScan('wordpress-media', 30, 1000));
            $t->same([
                'folder' => 'wordpress-media',
                'requestedAt' => 1000,
                'delaySeconds' => 30,
                'effectiveDelaySeconds' => 30,
                'scheduledAt' => 1030,
                'remainingSeconds' => 25,
                'due' => false,
            ], $scheduler->scheduledScanStatus('wordpress-media', 1005));
            $t->same(['wordpress-media'], array_keys($scheduler->scheduledScanStatuses(1005)));
            $t->same([], $scheduler->dueDelayedFolderIds(1029));

            $early = $scheduler->scanDueDelayedFolders(hashBlocks: true, blockSize: 4, now: 1029);
            $t->true($early->successful());
            $t->same([], $early->snapshots());
            $t->same(null, $service->checkpoint(1029));

            $due = $scheduler->scanDueDelayedFolders(hashBlocks: true, blockSize: 4, now: 1030);
            $t->true($due->successful());
            $t->same(1, $due->snapshot('wordpress-media')?->revision);
            $t->same(1090, $due->snapshot('wordpress-media')?->expiresAt);
            $t->same(hash('sha256', 'abcd'), $due->snapshot('wordpress-media')?->checkpoint->currentFile('wp-content/uploads/2026/05/hero.jpg')?->blocks[0]->hashHex);
            $t->same(null, $scheduler->scheduledScanStatus('wordpress-media', 1030));

            $t->true($scheduler->delayScan('wordpress-media', -5, 1040));
            $t->same(['wordpress-media'], $scheduler->dueDelayedFolderIds(1040));
            $t->same(1040, $scheduler->scheduledScanStatus('wordpress-media', 1040)['scheduledAt'] ?? null);
            $scheduler->pauseFolder('wordpress-media');
            $t->same(null, $scheduler->scheduledScanStatus('wordpress-media', 1041));
            $t->true(!$scheduler->delayScan('missing-folder', 10, 1041));
        } finally {
            syncthing_folder_scan_scheduler_rm($root);
        }
    },
];

function syncthing_folder_scan_scheduler_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-folder-scan-scheduler-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary folder scan scheduler root');
    }

    return $root;
}

function syncthing_folder_scan_scheduler_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create folder scan scheduler test directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write folder scan scheduler test file');
    }
}

function syncthing_folder_scan_scheduler_rm(string $path): void
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
        syncthing_folder_scan_scheduler_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
