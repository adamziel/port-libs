<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullDbUpdater;
use PortLibs\Syncthing\VersionVector;

return [
    'dbUpdaterRoutine batches pulled updates fsyncs changed dirs and emits last received file' => static function (TestRunner $t): void {
        $updates = [];
        $fsyncs = [];
        $received = [];

        $updater = new PullDbUpdater(
            updateLocalsFromPulling: static function (array $files) use (&$updates): ?Throwable {
                $updates[] = array_map(
                    static fn (FileInfo $file): array => [$file->name, $file->sequence, $file->deleted],
                    $files,
                );

                return null;
            },
            syncDirectory: static function (string $dir) use (&$fsyncs): ?Throwable {
                $fsyncs[] = $dir;

                return null;
            },
            receivedFile: static function (string $name, bool $deleted) use (&$received): void {
                $received[] = [$name, $deleted];
            },
        );

        $updater->append(syncthing_pull_db_file('wp-content/uploads/2026/hero.jpg', sequence: 81), PullDbUpdater::DB_UPDATE_HANDLE_FILE);
        $updater->append(syncthing_pull_db_file('wp-content/uploads/2026/hero-alt.txt', sequence: 82), PullDbUpdater::DB_UPDATE_SHORTCUT_FILE);
        $updater->append(syncthing_pull_db_file('wp-content/uploads/2026/old.jpg', sequence: 83, deleted: true), PullDbUpdater::DB_UPDATE_DELETE_FILE);
        $updater->append(syncthing_pull_db_file('wp-content/uploads/2026/gallery', sequence: 84, type: FileInfo::TYPE_DIRECTORY), PullDbUpdater::DB_UPDATE_HANDLE_DIR);

        $t->same(4, $updater->close());
        $t->same(4, $updater->changedCount());
        $t->same([
            'wp-content/uploads/2026',
            'wp-content/uploads/2026/gallery',
        ], $fsyncs);
        $t->same($fsyncs, $updater->fsyncedDirectories());
        $t->same([
            [
                ['wp-content/uploads/2026/hero.jpg', 0, false],
                ['wp-content/uploads/2026/hero-alt.txt', 0, false],
                ['wp-content/uploads/2026/old.jpg', 0, true],
                ['wp-content/uploads/2026/gallery', 0, false],
            ],
        ], $updates);
        $t->same([['wp-content/uploads/2026/old.jpg', true]], $received);
        $t->same([
            ['name' => 'wp-content/uploads/2026/old.jpg', 'deleted' => true],
        ], $updater->receivedFiles());
    },
    'updateLocalsFromPulling emits remote change events after local batch update' => static function (TestRunner $t): void {
        $order = [];
        $remoteChanges = [];

        $updater = new PullDbUpdater(
            updateLocalsFromPulling: static function (array $files) use (&$order): ?Throwable {
                $order[] = 'update:' . implode(',', array_map(static fn (FileInfo $file): string => $file->name, $files));

                return null;
            },
            syncDirectory: static function (string $dir) use (&$order): ?Throwable {
                $order[] = 'fsync:' . $dir;

                return null;
            },
            receivedFile: static function (string $name, bool $deleted) use (&$order): void {
                $order[] = 'received:' . $name . ':' . ($deleted ? 'deleted' : 'modified');
            },
            remoteChangeDetected: static function (array $event) use (&$order, &$remoteChanges): void {
                $remoteChanges[] = $event;
                $order[] = 'remote:' . $event['action'] . ':' . $event['type'] . ':' . $event['path'];
            },
            folderId: 'wordpress-media',
            folderLabel: 'WordPress Media',
        );

        $updater->append(syncthing_pull_db_file('wp-content/uploads/2026/hero.jpg', sequence: 91), PullDbUpdater::DB_UPDATE_HANDLE_FILE);
        $updater->append(syncthing_pull_db_file('wp-content/uploads/2026/old.jpg', sequence: 92, deleted: true), PullDbUpdater::DB_UPDATE_DELETE_FILE);
        $updater->append(syncthing_pull_db_file('wp-content/uploads/2026/gallery', sequence: 93, type: FileInfo::TYPE_DIRECTORY), PullDbUpdater::DB_UPDATE_HANDLE_DIR);
        $updater->append(syncthing_pull_db_file('wp-content/uploads/current', sequence: 94, type: FileInfo::TYPE_SYMLINK), PullDbUpdater::DB_UPDATE_HANDLE_SYMLINK);
        $updater->append(syncthing_pull_db_file('wp-content/uploads/private.jpg', sequence: 95, flags: FileInfo::FLAG_LOCAL_IGNORED), PullDbUpdater::DB_UPDATE_INVALIDATE);

        $t->same(5, $updater->close());
        $t->same([
            'fsync:wp-content/uploads/2026',
            'fsync:wp-content/uploads/2026/gallery',
            'update:wp-content/uploads/2026/hero.jpg,wp-content/uploads/2026/old.jpg,wp-content/uploads/2026/gallery,wp-content/uploads/current,wp-content/uploads/private.jpg',
            'remote:modified:file:' . str_replace('/', DIRECTORY_SEPARATOR, 'wp-content/uploads/2026/hero.jpg'),
            'remote:deleted:file:' . str_replace('/', DIRECTORY_SEPARATOR, 'wp-content/uploads/2026/old.jpg'),
            'remote:modified:dir:' . str_replace('/', DIRECTORY_SEPARATOR, 'wp-content/uploads/2026/gallery'),
            'remote:modified:symlink:' . str_replace('/', DIRECTORY_SEPARATOR, 'wp-content/uploads/current'),
            'received:wp-content/uploads/2026/old.jpg:deleted',
        ], $order);
        $t->same($remoteChanges, $updater->remoteChangeEvents());
        $t->same([
            [
                'folder' => 'wordpress-media',
                'folderID' => 'wordpress-media',
                'label' => 'WordPress Media',
                'action' => 'modified',
                'type' => 'file',
                'path' => str_replace('/', DIRECTORY_SEPARATOR, 'wp-content/uploads/2026/hero.jpg'),
                'modifiedBy' => '202',
            ],
            [
                'folder' => 'wordpress-media',
                'folderID' => 'wordpress-media',
                'label' => 'WordPress Media',
                'action' => 'deleted',
                'type' => 'file',
                'path' => str_replace('/', DIRECTORY_SEPARATOR, 'wp-content/uploads/2026/old.jpg'),
                'modifiedBy' => '202',
            ],
            [
                'folder' => 'wordpress-media',
                'folderID' => 'wordpress-media',
                'label' => 'WordPress Media',
                'action' => 'modified',
                'type' => 'dir',
                'path' => str_replace('/', DIRECTORY_SEPARATOR, 'wp-content/uploads/2026/gallery'),
                'modifiedBy' => '202',
            ],
            [
                'folder' => 'wordpress-media',
                'folderID' => 'wordpress-media',
                'label' => 'WordPress Media',
                'action' => 'modified',
                'type' => 'symlink',
                'path' => str_replace('/', DIRECTORY_SEPARATOR, 'wp-content/uploads/current'),
                'modifiedBy' => '202',
            ],
        ], $remoteChanges);
    },
    'dbUpdaterRoutine flushes at upstream file count limit and emits one received file per batch' => static function (TestRunner $t): void {
        $updater = new PullDbUpdater(disableFsync: true);

        for ($i = 0; $i < 1000; $i++) {
            $updater->append(syncthing_pull_db_file(sprintf('wp-content/uploads/batch/%04d.jpg', $i), sequence: $i + 1), PullDbUpdater::DB_UPDATE_HANDLE_FILE);
        }

        $t->same(1, count($updater->updateBatches()));
        $t->same(1000, count($updater->updateBatches()[0]));
        $t->same([
            ['name' => 'wp-content/uploads/batch/0999.jpg', 'deleted' => false],
        ], $updater->receivedFiles());

        $updater->append(syncthing_pull_db_file('wp-content/uploads/batch/1000.jpg', sequence: 1001), PullDbUpdater::DB_UPDATE_HANDLE_FILE);
        $t->same(1001, $updater->close());
        $t->same(2, count($updater->updateBatches()));
        $t->same(1, count($updater->updateBatches()[1]));
        $t->same([
            ['name' => 'wp-content/uploads/batch/0999.jpg', 'deleted' => false],
            ['name' => 'wp-content/uploads/batch/1000.jpg', 'deleted' => false],
        ], $updater->receivedFiles());
        $t->same([], $updater->fsyncedDirectories());
    },
    'dbUpdaterRoutine timer tick flushes partial batch before close' => static function (TestRunner $t): void {
        $updater = new PullDbUpdater(disableFsync: true);
        $updater->append(syncthing_pull_db_file('wp-content/uploads/2026/tick-a.jpg', sequence: 11), PullDbUpdater::DB_UPDATE_HANDLE_FILE);

        $t->same(null, $updater->tick());
        $t->same(1, count($updater->updateBatches()));
        $t->same([
            ['name' => 'wp-content/uploads/2026/tick-a.jpg', 'deleted' => false],
        ], $updater->receivedFiles());

        $updater->append(syncthing_pull_db_file('wp-content/uploads/2026/tick-b.jpg', sequence: 12), PullDbUpdater::DB_UPDATE_HANDLE_FILE);
        $t->same(2, $updater->close());
        $t->same(2, count($updater->updateBatches()));
    },
    'dbUpdaterRoutine skips received file events for invalid and shortcut metadata updates' => static function (TestRunner $t): void {
        $updater = new PullDbUpdater(disableFsync: true);

        $updater->append(
            syncthing_pull_db_file('wp-content/uploads/2026/ignored.jpg', flags: FileInfo::FLAG_LOCAL_IGNORED),
            PullDbUpdater::DB_UPDATE_DELETE_FILE,
        );
        $updater->append(
            syncthing_pull_db_file('wp-content/uploads/2026/metadata-only.jpg'),
            PullDbUpdater::DB_UPDATE_SHORTCUT_FILE,
        );
        $updater->append(
            syncthing_pull_db_file('wp-content/uploads/2026/link', type: FileInfo::TYPE_SYMLINK),
            PullDbUpdater::DB_UPDATE_HANDLE_SYMLINK,
        );

        $t->same(3, $updater->close());
        $t->same([], $updater->receivedFiles());
        $t->same(1, count($updater->updateBatches()));
        $t->throws(InvalidArgumentException::class, static fn () => $updater->append(
            syncthing_pull_db_file('wp-content/uploads/2026/bad.jpg'),
            'dbUpdateUnsupported',
        ));
    },
];

function syncthing_pull_db_file(
    string $name,
    int $sequence = 1,
    bool $deleted = false,
    int $type = FileInfo::TYPE_FILE,
    int $flags = 0,
): FileInfo {
    $bytes = 'pulled wordpress media database update ' . $name;
    $blocks = [];
    $size = $deleted || $type !== FileInfo::TYPE_FILE ? 0 : strlen($bytes);
    if ($size > 0) {
        $blocks[] = new Block(0, $size, hash('sha256', $bytes));
    }

    return new FileInfo(
        name: $name,
        modifiedS: 1_700_002_000 + $sequence,
        version: VersionVector::fromCounters([202 => $sequence]),
        deleted: $deleted,
        localFlags: $flags,
        size: $size,
        type: $type,
        permissions: $type === FileInfo::TYPE_DIRECTORY ? 0755 : 0644,
        rawBlockSize: max(1, $size),
        sequence: $sequence,
        symlinkTarget: $type === FileInfo::TYPE_SYMLINK ? 'target' : '',
        blocks: $blocks,
        modifiedBy: 202,
    );
}
