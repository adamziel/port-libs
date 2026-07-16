<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FolderIndexState;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream sqlite TestNeed local and remote need lists' => static function (TestRunner $t): void {
        $state = new FolderIndexState();
        $base = VersionVector::fromCounters([1 => 1]);
        $newer = VersionVector::fromCounters([1 => 1, 42 => 2]);

        $state->update('local', [
            syncthing_folder_index_state_file('test1', 1, 128, $base),
            syncthing_folder_index_state_file('test2', 2, 256, $base),
            syncthing_folder_index_state_file('test3', 3, 384, $newer),
        ]);
        $state->update('remote-42', [
            syncthing_folder_index_state_file('test2', 100, 256, $newer),
            syncthing_folder_index_state_file('test3', 101, 384, $base),
            syncthing_folder_index_state_file('test4', 102, 512, $newer),
        ]);

        $t->same(['test2', 'test4'], syncthing_folder_index_state_names($state->neededFiles('local')));
        $t->same(['test1', 'test3'], syncthing_folder_index_state_names($state->neededFiles('remote-42')));
        $t->same(2, $state->countNeed('local')->files);
        $t->same(768, $state->countNeed('local')->bytes);
        $t->same(2, $state->countNeed('remote-42')->files);
        $t->same(512, $state->countNeed('remote-42')->bytes);
        $t->same(4, $state->countGlobal()->files);
        $t->same(1280, $state->countGlobal()->bytes);
    },
    'maps upstream deleted and ignored need boundaries' => static function (TestRunner $t): void {
        $base = VersionVector::fromCounters([1 => 1]);
        $remoteDelete = VersionVector::fromCounters([1 => 1, 42 => 2]);

        $deletedNeeded = new FolderIndexState();
        $deletedNeeded->update('local', [
            syncthing_folder_index_state_file('wp-content/uploads/hero.jpg', 1, 2048, $base),
        ]);
        $deletedNeeded->update('remote-42', [
            syncthing_folder_index_state_file('wp-content/uploads/hero.jpg', 2, 0, $remoteDelete, deleted: true),
        ]);

        $t->same(['wp-content/uploads/hero.jpg'], syncthing_folder_index_state_names($deletedNeeded->neededFiles('local')));
        $t->same(1, $deletedNeeded->countNeed('local')->deleted);
        $t->same(0, $deletedNeeded->countNeed('local')->bytes);

        $ignoredLocal = new FolderIndexState();
        $ignoredLocal->update('remote-42', [
            syncthing_folder_index_state_file('wp-content/uploads/ignored.jpg', 3, 1024, $base),
        ]);
        $ignoredLocal->update('local', [
            syncthing_folder_index_state_file(
                'wp-content/uploads/ignored.jpg',
                4,
                1024,
                $base,
                flags: FileInfo::FLAG_LOCAL_IGNORED,
            ),
        ]);

        $t->same([], $ignoredLocal->neededFiles('local'));
        $t->same(0, $ignoredLocal->countNeed('local')->files);

        $missingLocalDelete = new FolderIndexState();
        $missingLocalDelete->update('remote-42', [
            syncthing_folder_index_state_file('wp-content/uploads/missing.jpg', 5, 0, $remoteDelete, deleted: true),
        ]);
        $t->same([], $missingLocalDelete->neededFiles('local'));
        $t->same(0, $missingLocalDelete->countNeed('local')->deleted);

        $missingRemoteDelete = new FolderIndexState();
        $missingRemoteDelete->update('local', [
            syncthing_folder_index_state_file('wp-content/uploads/local-delete.jpg', 6, 0, $remoteDelete, deleted: true),
        ]);
        $t->same([], $missingRemoteDelete->neededFiles('remote-42'));
        $t->same(0, $missingRemoteDelete->countNeed('remote-42')->deleted);
    },
    'preserves remote need metadata across a full index reset' => static function (TestRunner $t): void {
        $state = new FolderIndexState();
        $base = VersionVector::fromCounters([1 => 1]);
        $deleted = VersionVector::fromCounters([1 => 1, 2 => 2]);
        $readded = VersionVector::fromCounters([1 => 3, 2 => 2]);

        $state->update('remote-1', [
            syncthing_folder_index_state_file('wp-content/uploads/foo.jpg', 1, 10, $base),
        ], reset: true);
        $state->update('remote-2', [
            syncthing_folder_index_state_file('wp-content/uploads/foo.jpg', 1, 10, $base),
        ], reset: true);
        $state->update('remote-1', [
            syncthing_folder_index_state_file('wp-content/uploads/foo.jpg', 2, 0, $deleted, deleted: true),
        ]);
        $state->update('remote-2', [
            syncthing_folder_index_state_file('wp-content/uploads/foo.jpg', 2, 0, $deleted, deleted: true),
        ]);
        $state->update('remote-1', [
            syncthing_folder_index_state_file('wp-content/uploads/foo.jpg', 3, 20, $readded),
        ]);

        $t->same(['wp-content/uploads/foo.jpg'], syncthing_folder_index_state_names($state->neededFiles('remote-2')));
        $t->same(1, $state->countNeed('remote-2')->files);
        $t->same(20, $state->countNeed('remote-2')->bytes);

        $state->update('remote-1', [
            syncthing_folder_index_state_file('wp-content/uploads/foo.jpg', 3, 20, $readded),
        ], reset: true);

        $t->same(['wp-content/uploads/foo.jpg'], syncthing_folder_index_state_names($state->neededFiles('remote-2')));
        $t->same(1, $state->countNeed('remote-2')->files);
        $t->same(20, $state->globalFile('wp-content/uploads/foo.jpg')?->size);
    },
    'maps upstream remote directory symlink and alphabetic pagination need' => static function (TestRunner $t): void {
        $state = new FolderIndexState();
        $version = VersionVector::fromCounters([42 => 1]);
        $state->update('remote-42', [
            syncthing_folder_index_state_file('wp-content/uploads/sym', 100, 12, $version, type: FileInfo::TYPE_SYMLINK),
            syncthing_folder_index_state_file('wp-content/uploads/dir', 101, 128, $version, type: FileInfo::TYPE_DIRECTORY),
            syncthing_folder_index_state_file('wp-content/uploads/a.jpg', 102, 1, $version),
            syncthing_folder_index_state_file('wp-content/uploads/b.jpg', 103, 1, $version),
            syncthing_folder_index_state_file('wp-content/uploads/c.jpg', 104, 1, $version),
        ]);

        $need = $state->countNeed('local');
        $t->same(3, $need->files);
        $t->same(1, $need->directories);
        $t->same(1, $need->symlinks);
        $t->same([
            'wp-content/uploads/a.jpg',
            'wp-content/uploads/b.jpg',
        ], syncthing_folder_index_state_names($state->neededFiles('local', limit: 2)));
        $t->same([
            'wp-content/uploads/c.jpg',
            'wp-content/uploads/dir',
            'wp-content/uploads/sym',
        ], syncthing_folder_index_state_names($state->neededFiles('local', limit: 3, offset: 2)));
    },
    'maps upstream needed-file pull orders from sqlite folderdb' => static function (TestRunner $t): void {
        $state = new FolderIndexState();
        $version = VersionVector::fromCounters([42 => 1]);
        $state->update('remote-42', [
            syncthing_folder_index_state_file('wp-content/uploads/2026/large-new.jpg', 500, 5000, $version),
            syncthing_folder_index_state_file('wp-content/uploads/2026/medium-mid.jpg', 300, 3000, $version),
            syncthing_folder_index_state_file('wp-content/uploads/2026/small-old.jpg', 100, 1000, $version),
        ]);

        $t->same([
            'wp-content/uploads/2026/large-new.jpg',
            'wp-content/uploads/2026/medium-mid.jpg',
            'wp-content/uploads/2026/small-old.jpg',
        ], syncthing_folder_index_state_names($state->neededFiles('local', order: FolderIndexState::PULL_ORDER_ALPHABETIC)));
        $t->same([
            'wp-content/uploads/2026/small-old.jpg',
            'wp-content/uploads/2026/medium-mid.jpg',
            'wp-content/uploads/2026/large-new.jpg',
        ], syncthing_folder_index_state_names($state->neededFiles('local', order: FolderIndexState::PULL_ORDER_SMALLEST_FIRST)));
        $t->same([
            'wp-content/uploads/2026/large-new.jpg',
            'wp-content/uploads/2026/medium-mid.jpg',
            'wp-content/uploads/2026/small-old.jpg',
        ], syncthing_folder_index_state_names($state->neededFiles('local', order: FolderIndexState::PULL_ORDER_LARGEST_FIRST)));
        $t->same([
            'wp-content/uploads/2026/small-old.jpg',
            'wp-content/uploads/2026/medium-mid.jpg',
            'wp-content/uploads/2026/large-new.jpg',
        ], syncthing_folder_index_state_names($state->neededFiles('local', order: FolderIndexState::PULL_ORDER_OLDEST_FIRST)));
        $t->same([
            'wp-content/uploads/2026/large-new.jpg',
            'wp-content/uploads/2026/medium-mid.jpg',
            'wp-content/uploads/2026/small-old.jpg',
        ], syncthing_folder_index_state_names($state->neededFiles('local', order: FolderIndexState::PULL_ORDER_NEWEST_FIRST)));
        $t->same([
            'wp-content/uploads/2026/medium-mid.jpg',
        ], syncthing_folder_index_state_names($state->neededFiles(
            'local',
            limit: 1,
            offset: 1,
            order: FolderIndexState::PULL_ORDER_SMALLEST_FIRST,
        )));
    },
    'maps pull order text fallback and deterministic random test hook' => static function (TestRunner $t): void {
        $state = new FolderIndexState();
        $version = VersionVector::fromCounters([42 => 1]);
        $state->update('remote-42', [
            syncthing_folder_index_state_file('wp-content/uploads/2026/a.jpg', 1, 100, $version),
            syncthing_folder_index_state_file('wp-content/uploads/2026/b.jpg', 2, 200, $version),
            syncthing_folder_index_state_file('wp-content/uploads/2026/c.jpg', 3, 300, $version),
        ]);

        $t->same(FolderIndexState::PULL_ORDER_NEWEST_FIRST, FolderIndexState::pullOrderFromText('newestFirst'));
        $t->same(FolderIndexState::PULL_ORDER_RANDOM, FolderIndexState::pullOrderFromText('unsupported-upstream-defaults-to-random'));

        $randomized = $state->neededFiles(
            'local',
            order: FolderIndexState::PULL_ORDER_RANDOM,
            randomize: static fn (array $files): array => [$files[2], $files[0], $files[1]],
        );
        $t->same([
            'wp-content/uploads/2026/c.jpg',
            'wp-content/uploads/2026/a.jpg',
            'wp-content/uploads/2026/b.jpg',
        ], syncthing_folder_index_state_names($randomized));
        $t->throws(
            UnexpectedValueException::class,
            static fn () => $state->neededFiles(
                'local',
                order: FolderIndexState::PULL_ORDER_RANDOM,
                randomize: static fn (array $files): array => ['not-a-file'],
            ),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $state->neededFiles('local', order: 'unsupported-direct-api-order'),
        );
    },
    'maps upstream global availability and drop recalculation' => static function (TestRunner $t): void {
        $state = new FolderIndexState();
        $base = VersionVector::fromCounters([1 => 1]);
        $remoteWin = VersionVector::fromCounters([1 => 1, 42 => 2]);

        $state->update('local', [
            syncthing_folder_index_state_file('wp-content/uploads/drop-recalc.jpg', 1, 128, $base),
        ]);
        $state->update('remote-b', [
            syncthing_folder_index_state_file('wp-content/uploads/drop-recalc.jpg', 2, 256, $remoteWin),
        ]);
        $state->update('remote-a', [
            syncthing_folder_index_state_file('wp-content/uploads/drop-recalc.jpg', 3, 256, $remoteWin),
        ]);
        $state->update('remote-old', [
            syncthing_folder_index_state_file('wp-content/uploads/drop-recalc.jpg', 4, 128, $base),
        ]);

        $t->same(['remote-a', 'remote-b'], $state->globalAvailability('wp-content/uploads/drop-recalc.jpg'));
        $t->same(256, $state->globalFile('wp-content/uploads/drop-recalc.jpg')?->size);

        $state->dropFilesNamed('remote-b', ['wp-content/uploads/drop-recalc.jpg']);
        $t->same(['remote-a'], $state->globalAvailability('wp-content/uploads/drop-recalc.jpg'));

        $state->dropAllFiles('remote-a');
        $t->same(128, $state->globalFile('wp-content/uploads/drop-recalc.jpg')?->size);
        $t->same(['remote-old'], $state->globalAvailability('wp-content/uploads/drop-recalc.jpg'));

        $state->dropAllFiles('remote-old');
        $t->same([], $state->globalAvailability('wp-content/uploads/drop-recalc.jpg'));
    },
    'maps upstream AllGlobalFilesPrefix subtree selection' => static function (TestRunner $t): void {
        $state = new FolderIndexState();
        $base = VersionVector::fromCounters([1 => 1]);
        $remote = VersionVector::fromCounters([1 => 1, 42 => 2]);
        $blockSize = 128 << 10;

        $state->update('local', [
            syncthing_folder_index_state_file('test1', 1, $blockSize, $base),
            syncthing_folder_index_state_file('test2', 2, 128, $base, type: FileInfo::TYPE_DIRECTORY),
            syncthing_folder_index_state_file('test2/a', 3, 2 * $blockSize, $base),
            syncthing_folder_index_state_file('test2/b', 4, 3 * $blockSize, $base),
        ]);
        $state->update('remote-42', [
            syncthing_folder_index_state_file('test3', 101, 3 * $blockSize, $remote),
            syncthing_folder_index_state_file('test4', 102, 4 * $blockSize, $remote),
            syncthing_folder_index_state_file('test1', 103, 5 * $blockSize, $remote),
        ]);

        $t->same(['test2', 'test2/a', 'test2/b'], syncthing_folder_index_state_names($state->globalFilesPrefix('test2')));
        $t->same(['test1', 'test2', 'test2/a', 'test2/b', 'test3', 'test4'], syncthing_folder_index_state_names($state->globalFilesPrefix('')));
        $t->same([], $state->globalFilesPrefix('test5'));
    },
    'maps upstream DropDevice no-op and global recalculation' => static function (TestRunner $t): void {
        $state = new FolderIndexState();
        $base = VersionVector::fromCounters([1 => 1]);
        $remoteWin = VersionVector::fromCounters([1 => 1, 42 => 2]);

        $state->update('local', [
            syncthing_folder_index_state_file('wp-content/uploads/2026/hero.jpg', 1, 128, $base),
        ]);
        $state->update('playground-peer', [
            syncthing_folder_index_state_file('wp-content/uploads/2026/hero.jpg', 2, 256, $remoteWin),
            syncthing_folder_index_state_file('wp-content/uploads/2026/peer-only.jpg', 3, 512, $remoteWin),
        ]);
        $state->update('backup-peer', [
            syncthing_folder_index_state_file('wp-content/uploads/2026/backup.jpg', 4, 1024, $remoteWin),
        ]);

        $t->same(256, $state->globalFile('wp-content/uploads/2026/hero.jpg')?->size);
        $t->same(['wp-content/uploads/2026/backup.jpg', 'wp-content/uploads/2026/hero.jpg', 'wp-content/uploads/2026/peer-only.jpg'], syncthing_folder_index_state_names($state->neededFiles('local')));

        $state->dropDevice('playground-peer');

        $t->same(null, $state->deviceFile('playground-peer', 'wp-content/uploads/2026/hero.jpg'));
        $t->same(128, $state->globalFile('wp-content/uploads/2026/hero.jpg')?->size);
        $t->same(null, $state->globalFile('wp-content/uploads/2026/peer-only.jpg'));
        $t->same(['wp-content/uploads/2026/backup.jpg'], syncthing_folder_index_state_names($state->neededFiles('local')));

        $state->dropDevice('missing-peer');
        $t->same(['wp-content/uploads/2026/backup.jpg'], syncthing_folder_index_state_names($state->neededFiles('local')));
        $t->throws(\LogicException::class, static fn () => $state->dropDevice('local'));
    },
];

function syncthing_folder_index_state_file(
    string $name,
    int $sequence,
    int $size,
    VersionVector $version,
    bool $deleted = false,
    int $flags = 0,
    int $type = FileInfo::TYPE_FILE,
): FileInfo {
    return new FileInfo(
        name: $name,
        modifiedS: 1_700_004_000 + $sequence,
        modifiedNs: $sequence,
        version: $version,
        deleted: $deleted,
        localFlags: $flags,
        size: $deleted ? 0 : $size,
        type: $type,
        permissions: 0644,
        rawBlockSize: $type === FileInfo::TYPE_FILE && !$deleted ? max(1, $size) : 0,
        sequence: $sequence,
        symlinkTarget: $type === FileInfo::TYPE_SYMLINK ? 'target' : '',
        modifiedBy: 42,
    );
}

/**
 * @param list<FileInfo> $files
 *
 * @return list<string>
 */
function syncthing_folder_index_state_names(array $files): array
{
    return array_map(static fn (FileInfo $file): string => $file->name, $files);
}
