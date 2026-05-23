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
use PortLibs\Syncthing\VersionVector;
use PortLibs\Syncthing\WordPressOptionCheckpointStore;

return [
    'wordpress option store persists checkpoint payloads with FileInfo metadata' => static function (TestRunner $t): void {
        $options = [];
        $store = syncthing_wordpress_option_checkpoint_store($options);
        $file = new FileInfo(
            name: 'wp-content/uploads/2026/05/hero.jpg',
            modifiedS: 1779499200,
            modifiedNs: 12,
            version: VersionVector::fromCounters([[42, 7]]),
            localFlags: FileInfo::FLAG_LOCAL_MUST_RESCAN,
            size: 8,
            blocksHash: hash('sha256', 'blocks'),
            previousBlocksHash: hash('sha256', 'previous'),
            permissions: 0644,
            rawBlockSize: 4,
            sequence: 17,
            blocks: [new Block(0, 4, hash('sha256', 'abcd'))],
            unixOwnerName: 'www-data',
            unixGroupName: 'www-data',
            unixUid: 33,
            unixGid: 33,
            modifiedBy: 42,
            xattrs: ['user.wp-alt' => 'Hero'],
        );
        $checkpoint = FolderScanCheckpoint::fromResult(
            'wordpress-media',
            new FileInfoScanResult([$file], resumeSubs: ['wp-content/uploads/2026/05']),
        );

        $saved = $store->save($checkpoint, expectedRevision: 0, now: 500, ttlSeconds: 60);
        $reloaded = syncthing_wordpress_option_checkpoint_store($options)->load('wordpress-media', 501);
        $loadedFile = $reloaded?->checkpoint->currentFile($file->name);

        $t->same(1, $saved->revision);
        $t->same(560, $saved->expiresAt);
        $t->true(isset($options[$store->optionName('wordpress-media')]));
        $t->same(1, $reloaded?->revision);
        $t->same($file->blocksHash, $loadedFile?->blocksHash);
        $t->same(FileInfo::FLAG_LOCAL_MUST_RESCAN, $loadedFile?->localFlags);
        $t->same(7, $loadedFile?->version->counter(42));
        $t->same('Hero', $loadedFile?->xattrs['user.wp-alt'] ?? null);
    },
    'wordpress option store rejects stale revisions and compare-and-swap conflicts' => static function (TestRunner $t): void {
        $options = [];
        $injectConcurrentWrite = false;
        $compareAndSwap = static function (string $key, mixed $value, ?int $expectedRevision) use (&$options, &$injectConcurrentWrite): bool {
            if ($injectConcurrentWrite && isset($options[$key]) && is_array($options[$key])) {
                $options[$key]['revision'] = 2;
                $injectConcurrentWrite = false;
            }

            $actualRevision = isset($options[$key]) && is_array($options[$key])
                ? ($options[$key]['revision'] ?? 0)
                : 0;
            if ($expectedRevision !== null && $expectedRevision !== $actualRevision) {
                return false;
            }

            $options[$key] = $value;
            return true;
        };
        $store = syncthing_wordpress_option_checkpoint_store($options, $compareAndSwap);
        $checkpoint = new FolderScanCheckpoint('wordpress-media');
        $first = $store->save($checkpoint, expectedRevision: 0, now: 600, ttlSeconds: 60);

        $t->throws(
            FolderScanCheckpointConflictException::class,
            static fn () => $store->save($checkpoint, expectedRevision: 0, now: 601, ttlSeconds: 60),
        );

        $injectConcurrentWrite = true;
        $t->throws(
            FolderScanCheckpointConflictException::class,
            static fn () => $store->save($checkpoint, expectedRevision: $first->revision, now: 602, ttlSeconds: 60),
        );
        $t->same(2, $store->load('wordpress-media', 602)?->revision);
    },
    'wordpress option store expires snapshots and deletes stale options before reuse' => static function (TestRunner $t): void {
        $options = [];
        $store = syncthing_wordpress_option_checkpoint_store($options);
        $key = $store->optionName('wordpress-media');

        $store->save(new FolderScanCheckpoint('wordpress-media'), expectedRevision: 0, now: 700, ttlSeconds: 10);

        $t->true(isset($options[$key]));
        $t->true($store->load('wordpress-media', 709) !== null);
        $t->same(null, $store->load('wordpress-media', 710));
        $t->true(!isset($options[$key]));
        $t->same(1, $store->save(new FolderScanCheckpoint('wordpress-media'), expectedRevision: 0, now: 711)->revision);
    },
    'folder scan service resumes through a wordpress option checkpoint store' => static function (TestRunner $t): void {
        $root = syncthing_wordpress_option_checkpoint_root();
        try {
            $options = [];
            $dir = 'wp-content/uploads/2026/05';
            syncthing_wordpress_option_checkpoint_write($root, $dir . '/hero.jpg', 'abcdefgh');
            syncthing_wordpress_option_checkpoint_write($root, $dir . '/thumb.jpg', '12345');

            $store = syncthing_wordpress_option_checkpoint_store($options);
            $service = new FolderScanService('wordpress-media', new FileInfoScanner($root), $store, ttlSeconds: 60);
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
                now: 800,
            );
            $reloadedService = new FolderScanService(
                'wordpress-media',
                new FileInfoScanner($root),
                syncthing_wordpress_option_checkpoint_store($options),
                ttlSeconds: 60,
            );
            $resumed = $reloadedService->scan(hashBlocks: true, blockSize: 4, now: 815);

            $t->same('cancelled', $first->checkpoint->state());
            $t->same(2, $resumed->revision);
            $t->same('complete', $resumed->checkpoint->state());
            $t->same([$dir, $dir . '/hero.jpg', $dir . '/thumb.jpg'], $resumed->checkpoint->completedPaths());
            $t->same(hash('sha256', '1234'), $resumed->checkpoint->currentFile($dir . '/thumb.jpg')?->blocks[0]->hashHex);
        } finally {
            syncthing_wordpress_option_checkpoint_rm($root);
        }
    },
    'wordpress option store hashes unsafe folder IDs and rejects malformed payloads' => static function (TestRunner $t): void {
        $options = [];
        $store = syncthing_wordpress_option_checkpoint_store($options);
        $unsafeFolderId = '../wp-content/uploads';
        $unsafeKey = $store->optionName($unsafeFolderId);

        $t->same(
            WordPressOptionCheckpointStore::DEFAULT_OPTION_PREFIX . hash('sha256', $unsafeFolderId),
            $unsafeKey,
        );

        $options[$store->optionName('wordpress-media')] = ['schema' => 999];
        $t->throws(
            RuntimeException::class,
            static fn () => $store->load('wordpress-media', 900),
        );
    },
];

function syncthing_wordpress_option_checkpoint_store(
    array &$options,
    ?callable $compareAndSwap = null,
): WordPressOptionCheckpointStore {
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
        $compareAndSwap,
    );
}

function syncthing_wordpress_option_checkpoint_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-wordpress-option-checkpoint-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create WordPress option checkpoint test root');
    }

    return $root;
}

function syncthing_wordpress_option_checkpoint_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create WordPress option checkpoint test directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write WordPress option checkpoint test file');
    }
}

function syncthing_wordpress_option_checkpoint_rm(string $path): void
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
        syncthing_wordpress_option_checkpoint_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
