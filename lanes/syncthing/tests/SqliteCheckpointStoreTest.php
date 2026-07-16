<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoScanResult;
use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanCheckpoint;
use PortLibs\Syncthing\FolderScanCheckpointConflictException;
use PortLibs\Syncthing\FolderScanProgress;
use PortLibs\Syncthing\FolderScanService;
use PortLibs\Syncthing\SqliteCheckpointStore;
use PortLibs\Syncthing\VersionVector;

return [
    'sqlite checkpoint store persists snapshots across connections with FileInfo metadata' => static function (TestRunner $t): void {
        $root = syncthing_sqlite_checkpoint_root();
        try {
            $db = $root . '/checkpoints.sqlite';
            $store = SqliteCheckpointStore::open($db);
            $file = new FileInfo(
                name: 'wp-content/uploads/2026/05/hero.jpg',
                modifiedS: 1779499200,
                modifiedNs: 44,
                version: VersionVector::fromCounters([[42, 9]]),
                localFlags: FileInfo::FLAG_LOCAL_MUST_RESCAN,
                size: 8,
                blocksHash: hash('sha256', 'blocks'),
                previousBlocksHash: hash('sha256', 'previous'),
                permissions: 0644,
                rawBlockSize: 4,
                sequence: 21,
                blocks: [new Block(0, 4, hash('sha256', 'abcd'))],
                unixOwnerName: 'www-data',
                unixGroupName: 'www-data',
                unixUid: 33,
                unixGid: 33,
                modifiedBy: 42,
                xattrs: ['user.wp-caption' => 'Hero'],
            );
            $checkpoint = FolderScanCheckpoint::fromResult(
                'wordpress-media',
                new FileInfoScanResult([$file], resumeSubs: ['wp-content/uploads/2026/05']),
            );

            $saved = $store->save($checkpoint, expectedRevision: 0, now: 1000, ttlSeconds: 60);
            $reloaded = SqliteCheckpointStore::open($db)->load('wordpress-media', 1001);
            $loadedFile = $reloaded?->checkpoint->currentFile($file->name);

            $t->same(1, $saved->revision);
            $t->same(1060, $saved->expiresAt);
            $t->same(1, $reloaded?->revision);
            $t->same($file->blocksHash, $loadedFile?->blocksHash);
            $t->same(9, $loadedFile?->version->counter(42));
            $t->same(FileInfo::FLAG_LOCAL_MUST_RESCAN, $loadedFile?->localFlags);
            $t->same('Hero', $loadedFile?->xattrs['user.wp-caption'] ?? null);
        } finally {
            syncthing_sqlite_checkpoint_rm($root);
        }
    },
    'sqlite checkpoint store rejects stale revisions and expires rows before reuse' => static function (TestRunner $t): void {
        $root = syncthing_sqlite_checkpoint_root();
        try {
            $db = $root . '/checkpoints.sqlite';
            $firstStore = SqliteCheckpointStore::open($db);
            $secondStore = SqliteCheckpointStore::open($db);
            $checkpoint = new FolderScanCheckpoint('wordpress-media');

            $first = $firstStore->save($checkpoint, expectedRevision: 0, now: 1100, ttlSeconds: 10);
            $second = $firstStore->save($checkpoint, expectedRevision: $first->revision, now: 1101, ttlSeconds: 10);

            $t->same(2, $second->revision);
            $t->throws(
                FolderScanCheckpointConflictException::class,
                static fn () => $secondStore->save($checkpoint, expectedRevision: $first->revision, now: 1102, ttlSeconds: 10),
            );
            $t->same(null, $firstStore->load('wordpress-media', 1111));
            $t->same(1, $firstStore->save($checkpoint, expectedRevision: 0, now: 1112)->revision);
        } finally {
            syncthing_sqlite_checkpoint_rm($root);
        }
    },
    'sqlite checkpoint store merges results and lists unexpired folders in stable order' => static function (TestRunner $t): void {
        $root = syncthing_sqlite_checkpoint_root();
        try {
            $store = SqliteCheckpointStore::open($root . '/checkpoints.sqlite');
            $dir = new FileInfo(name: 'wp-content/uploads/2026/05', type: FileInfo::TYPE_DIRECTORY);
            $hero = new FileInfo(name: 'wp-content/uploads/2026/05/hero.jpg', size: 8);
            $thumb = new FileInfo(name: 'wp-content/uploads/2026/05/thumb.jpg', size: 5);
            $post = new FileInfo(name: 'wp-content/post.html', size: 11);

            $media = $store->mergeResult(
                'wordpress-media',
                new FileInfoScanResult([$dir, $hero], cancelled: true, cancelledAt: $thumb->name, resumeSubs: ['wp-content/uploads/2026/05']),
                expectedRevision: 0,
                now: 1200,
                ttlSeconds: 40,
            );
            $store->mergeResult(
                'wordpress-content',
                new FileInfoScanResult([$post]),
                expectedRevision: 0,
                now: 1200,
                ttlSeconds: 10,
            );
            $merged = $store->mergeResult(
                'wordpress-media',
                new FileInfoScanResult([$thumb]),
                expectedRevision: $media->revision,
                now: 1201,
                ttlSeconds: 40,
            );
            $snapshots = $store->snapshots(1201);

            $t->same(2, $merged->revision);
            $t->same('complete', $merged->checkpoint->state());
            $t->same([$dir->name, $hero->name, $thumb->name], $merged->checkpoint->completedPaths());
            $t->same(['wordpress-content', 'wordpress-media'], array_map(static fn ($snapshot): string => $snapshot->folderId(), $snapshots));
            $t->same(1, $store->forgetExpired(1210));
            $t->same(['wordpress-media'], array_map(static fn ($snapshot): string => $snapshot->folderId(), $store->snapshots(1210)));
        } finally {
            syncthing_sqlite_checkpoint_rm($root);
        }
    },
    'folder scan service resumes through a sqlite checkpoint store' => static function (TestRunner $t): void {
        $root = syncthing_sqlite_checkpoint_root();
        try {
            $scanRoot = $root . '/site';
            $db = $root . '/checkpoints.sqlite';
            $dir = 'wp-content/uploads/2026/05';
            syncthing_sqlite_checkpoint_write($scanRoot, $dir . '/hero.jpg', 'abcdefgh');
            syncthing_sqlite_checkpoint_write($scanRoot, $dir . '/thumb.jpg', '12345');

            $service = new FolderScanService(
                'wordpress-media',
                new FileInfoScanner($scanRoot),
                SqliteCheckpointStore::open($db),
                ttlSeconds: 60,
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
                now: 1300,
            );
            $resumed = (new FolderScanService(
                'wordpress-media',
                new FileInfoScanner($scanRoot),
                SqliteCheckpointStore::open($db),
                ttlSeconds: 60,
            ))->scan(hashBlocks: true, blockSize: 4, now: 1315);

            $t->same('cancelled', $first->checkpoint->state());
            $t->same(2, $resumed->revision);
            $t->same('complete', $resumed->checkpoint->state());
            $t->same([$dir, $dir . '/hero.jpg', $dir . '/thumb.jpg'], $resumed->checkpoint->completedPaths());
            $t->same(hash('sha256', '1234'), $resumed->checkpoint->currentFile($dir . '/thumb.jpg')?->blocks[0]->hashHex);
        } finally {
            syncthing_sqlite_checkpoint_rm($root);
        }
    },
    'sqlite checkpoint store rejects unsafe table names and malformed payload rows' => static function (TestRunner $t): void {
        $root = syncthing_sqlite_checkpoint_root();
        try {
            $t->throws(
                InvalidArgumentException::class,
                static fn () => SqliteCheckpointStore::inMemory('bad-name;drop'),
            );

            $db = $root . '/checkpoints.sqlite';
            $pdo = new PDO('sqlite:' . $db);
            new SqliteCheckpointStore($pdo);
            $pdo->prepare(
                'INSERT INTO ' . SqliteCheckpointStore::DEFAULT_TABLE
                . ' (folder_id, revision, updated_at, expires_at, payload) VALUES (?, ?, ?, ?, ?)',
            )->execute([
                'wordpress-media',
                1,
                1400,
                null,
                json_encode(['schema' => 999], JSON_THROW_ON_ERROR),
            ]);

            $t->throws(
                RuntimeException::class,
                static fn () => SqliteCheckpointStore::open($db)->load('wordpress-media', 1401),
            );
        } finally {
            syncthing_sqlite_checkpoint_rm($root);
        }
    },
];

function syncthing_sqlite_checkpoint_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-sqlite-checkpoint-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create SQLite checkpoint test root');
    }

    return $root;
}

function syncthing_sqlite_checkpoint_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create SQLite checkpoint test directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write SQLite checkpoint test file');
    }
}

function syncthing_sqlite_checkpoint_rm(string $path): void
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
        syncthing_sqlite_checkpoint_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
