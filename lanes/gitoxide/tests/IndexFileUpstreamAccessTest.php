<?php

declare(strict_types=1);

use PortLibs\Gitoxide\IndexCacheTree;
use PortLibs\Gitoxide\IndexEntry;
use PortLibs\Gitoxide\IndexFile;

$emptyBlob = 'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391';
$treeOid = '496d6428b9cf92981dc9495211e6e1120fb6f2ba';
$entry = static function (
    string $path,
    int $stage = IndexEntry::STAGE_NORMAL,
    string $mode = IndexEntry::MODE_FILE,
    ?string $oid = null,
    bool $skipWorktree = false,
    bool $assumeValid = false,
) use ($emptyBlob): IndexEntry {
    return new IndexEntry($path, $stage, $mode, $oid ?? $emptyBlob, $skipWorktree, $assumeValid);
};
$pathsOf = static fn (array $entries): array => array_map(static fn (IndexEntry $entry): string => $entry->path, $entries);
$stagesOf = static fn (array $entries): array => array_map(static fn (IndexEntry $entry): int => $entry->stage, $entries);
$withChecksum = static function (string $payload): string {
    $checksum = hex2bin(hash('sha1', $payload));
    if ($checksum === false) {
        throw new RuntimeException('Unable to build index checksum fixture');
    }

    return $payload . $checksum;
};
$withExtension = static function (string $indexBytes, string $signature, string $payload) use ($withChecksum): string {
    if (strlen($signature) !== 4) {
        throw new RuntimeException('Index extension signatures must be four bytes');
    }

    $withoutChecksum = substr($indexBytes, 0, -20);

    return $withChecksum($withoutChecksum . $signature . pack('N', strlen($payload)) . $payload);
};
$throwsMessage = static function (TestRunner $t, string $messageNeedle, callable $callback): void {
    try {
        $callback();
    } catch (Throwable $throwable) {
        $t->contains($messageNeedle, $throwable->getMessage());

        return;
    }

    throw new RuntimeException('Expected exception was not thrown');
};
$checkPrefix = static function (TestRunner $t, array $entries, string $prefix, array $expected) use ($pathsOf): void {
    $actual = IndexFile::prefixedEntries($entries, $prefix);
    $t->same($expected, $actual === null ? null : $pathsOf($actual), "prefix {$prefix}");
};
$moreFileEntries = static fn (): array => IndexFile::sortEntries(array_map($entry, [
    'a',
    'b',
    'c',
    'd/a',
    'd/b',
    'd/c',
    'd/last/123',
    'd/last/34',
    'd/last/6',
    'x',
]));
$conflictingFileEntries = static fn (): array => IndexFile::sortEntries([
    $entry('file', IndexEntry::STAGE_ANCESTOR),
    $entry('file', IndexEntry::STAGE_OURS),
    $entry('file', IndexEntry::STAGE_THEIRS),
]);

return [
    'upstream access entry lookup stages and ranges' => static function (TestRunner $t) use (
        $entry,
        $conflictingFileEntries,
    ): void {
        $entries = IndexFile::sortEntries([
            $entry('D/B'),
            $entry('FILE_X'),
            $entry('X'),
            $entry('file_X'),
            $entry('file_x'),
            $entry('x', IndexEntry::STAGE_NORMAL, IndexEntry::MODE_SYMLINK),
        ]);

        foreach ($entries as $index => $entry) {
            $path = $entry->path;
            $t->same($entry, IndexFile::entryByPath($entries, $path));
            $t->same($entry, IndexFile::entryByPathAndStage($entries, $path, IndexEntry::STAGE_NORMAL));
            $t->same($index, IndexFile::entryIndexByPathAndStage($entries, $path, IndexEntry::STAGE_NORMAL));
        }

        $conflicts = $conflictingFileEntries();
        foreach ([IndexEntry::STAGE_ANCESTOR, IndexEntry::STAGE_OURS, IndexEntry::STAGE_THEIRS] as $stage) {
            $t->same($stage, IndexFile::entryByPathAndStage($conflicts, 'file', $stage)?->stage);
        }

        $t->same(IndexEntry::STAGE_OURS, IndexFile::entryByPath($conflicts, 'file')?->stage);
        $t->same([0, 3], IndexFile::entryRange($conflicts, 'file'));
        $t->same(null, IndexFile::entryRange($conflicts, 'foo'));
        $t->same($conflicts, IndexFile::prefixedEntries($conflicts, 'fil'));
        $t->same($conflicts, IndexFile::prefixedEntries($conflicts, 'file'));
        $t->same($conflicts, IndexFile::prefixedEntries($conflicts, ''));
        $t->same([0, 3], IndexFile::prefixedEntriesRange($conflicts, ''));
        $t->same(null, IndexFile::prefixedEntriesRange($conflicts, 'foo'));
    },
    'upstream access prefixes sorting and removal' => static function (TestRunner $t) use (
        $entry,
        $moreFileEntries,
        $conflictingFileEntries,
        $pathsOf,
        $checkPrefix,
    ): void {
        $clashingNames = IndexFile::sortEntries([
            $entry('FILE_X'),
            $entry('file_X'),
            $entry('file_x'),
            $entry('other'),
        ]);
        $t->same([1, 3], IndexFile::prefixedEntriesRange($clashingNames, 'file'));

        $removed = $conflictingFileEntries();
        $removed = IndexFile::removeEntries($removed, static fn (int $index): bool => $index === 0);
        $t->same(2, count($removed));
        $removed = IndexFile::removeEntries($removed, static fn (int $index): bool => $index === 0);
        $t->same(1, count($removed));
        $removed = IndexFile::removeEntries($removed, static fn (int $index): bool => $index === 0);
        $t->same(0, count($removed));
        $called = false;
        IndexFile::removeEntries($removed, static function () use (&$called): bool {
            $called = true;

            return true;
        });
        $t->same(false, $called);

        $removedAt = $conflictingFileEntries();
        $removedAt = IndexFile::removeEntryAtIndex($removedAt, 0);
        $t->same(2, count($removedAt));
        $removedAt = IndexFile::removeEntryAtIndex($removedAt, 0);
        $t->same(1, count($removedAt));
        $removedAt = IndexFile::removeEntryAtIndex($removedAt, 0);
        $t->same(0, count($removedAt));

        $entries = $moreFileEntries();
        $validEntries = count($entries);
        $newEntry = $entry('an initially incorrectly ordered entry');
        $unsorted = $entries;
        $unsorted[] = $newEntry;

        foreach ($entries as $index => $existing) {
            $t->same(
                $index,
                IndexFile::entryIndexByPathAndStageBounded(
                    $unsorted,
                    $existing->path,
                    IndexEntry::STAGE_NORMAL,
                    $validEntries,
                ),
            );
        }
        $t->same(null, IndexFile::entryByPathAndStage($unsorted, $newEntry->path, IndexEntry::STAGE_NORMAL));

        $sorted = IndexFile::sortEntries($unsorted);
        $t->same($newEntry->path, IndexFile::entryByPathAndStage($sorted, $newEntry->path, IndexEntry::STAGE_NORMAL)?->path);

        $checkPrefix($t, $sorted, 'd', ['d/a', 'd/b', 'd/c', 'd/last/123', 'd/last/34', 'd/last/6']);
        $checkPrefix($t, $sorted, 'd/', ['d/a', 'd/b', 'd/c', 'd/last/123', 'd/last/34', 'd/last/6']);
        $checkPrefix($t, $sorted, 'd/last', ['d/last/123', 'd/last/34', 'd/last/6']);
        $checkPrefix($t, $sorted, 'd/last/', ['d/last/123', 'd/last/34', 'd/last/6']);
        $checkPrefix($t, $sorted, 'd/las', ['d/last/123', 'd/last/34', 'd/last/6']);
        $checkPrefix($t, $sorted, 'd/last/123', ['d/last/123']);
        $checkPrefix($t, $sorted, 'd/last/34', ['d/last/34']);
        $checkPrefix($t, $sorted, 'd/last/6', ['d/last/6']);
        $checkPrefix($t, $sorted, 'x', ['x']);
        $checkPrefix($t, $sorted, 'a', ['a', 'an initially incorrectly ordered entry']);
        $checkPrefix($t, $sorted, 'an', ['an initially incorrectly ordered entry']);
        $checkPrefix($t, $sorted, 'an initially incorrectly ordered entry', ['an initially incorrectly ordered entry']);
        $checkPrefix($t, $sorted, 'b', ['b']);
        $checkPrefix($t, $sorted, 'c', ['c']);
        $t->same([0, 11], IndexFile::prefixedEntriesRange($sorted, ''));
        $t->same(null, IndexFile::prefixedEntriesRange($sorted, 'foo'));
        $t->same(
            ['a', 'an initially incorrectly ordered entry', 'b', 'c', 'd/a', 'd/b', 'd/c', 'd/last/123', 'd/last/34', 'd/last/6', 'x'],
            $pathsOf($sorted),
        );
    },
    'upstream access directory detection' => static function (TestRunner $t) use ($entry): void {
        $allKinds = IndexFile::sortEntries([
            $entry('a'),
            $entry('d/a'),
            $entry('sub', IndexEntry::STAGE_NORMAL, IndexEntry::MODE_COMMIT),
            $entry('sub-worktree', IndexEntry::STAGE_NORMAL, IndexEntry::MODE_COMMIT),
        ]);

        $t->same('d/a', IndexFile::entryClosestToDirectoryOrDirectory($allKinds, 'd')?->path);
        $t->same('sub', IndexFile::entryClosestToDirectoryOrDirectory($allKinds, 'sub')?->path);
        $t->same('sub-worktree', IndexFile::entryClosestToDirectoryOrDirectory($allKinds, 'sub-worktree')?->path);
        $t->same(null, IndexFile::entryClosestToDirectoryOrDirectory($allKinds, 'a'));

        $t->same(true, IndexFile::pathIsDirectory($allKinds, 'sub-worktree'));
        $t->same(true, IndexFile::pathIsDirectory($allKinds, 'd'));
        $t->same(true, IndexFile::pathIsDirectory($allKinds, 'sub'));
        $t->same(false, IndexFile::pathIsDirectory($allKinds, 'su'));
        $t->same(false, IndexFile::pathIsDirectory($allKinds, 'a'));

        $realistic = IndexFile::sortEntries([
            $entry('tests/snapshots/porcelain/basic.snap'),
            $entry('tests/snapshots/porcelain/conflict.snap'),
            $entry('tests/tools/check.sh'),
            $entry('tests/utilities.sh'),
        ]);

        $t->same(true, IndexFile::pathIsDirectory($realistic, 'tests'));
        $t->same(true, IndexFile::pathIsDirectory($realistic, 'tests/snapshots'));
        $t->same(true, IndexFile::pathIsDirectory($realistic, 'tests/snapshots/porcelain'));
        $t->same(true, IndexFile::pathIsDirectory($realistic, 'tests/tools'));
        $t->same(false, IndexFile::pathIsDirectory($realistic, 'nonexistent'));
        $t->same(false, IndexFile::pathIsDirectory($realistic, 'z'));
        $t->same(false, IndexFile::pathIsDirectory($realistic, 'test'));
        $t->same(false, IndexFile::pathIsDirectory($realistic, 'tests/utilities.sh'));
        $t->same(false, IndexFile::pathIsDirectory($realistic, ''));
    },
    'upstream read version header checksum and safe extensions' => static function (TestRunner $t) use (
        $entry,
        $emptyBlob,
        $treeOid,
        $pathsOf,
        $withChecksum,
        $withExtension,
        $throwsMessage,
    ): void {
        $emptyBytes = IndexFile::bytesFor([]);
        $t->same(2, IndexFile::versionFromBytes($emptyBytes));
        $t->same([], IndexFile::entriesFromBytes($emptyBytes));
        $t->same(hash('sha1', substr($emptyBytes, 0, -20)), IndexFile::checksum($emptyBytes));

        $single = [$entry('a')];
        $tree = new IndexCacheTree('', $treeOid, 1);
        $withTreeAndOptional = $withExtension(IndexFile::bytesFor($single, $tree), 'EOIE', pack('N', 12));
        $parsedSingle = IndexFile::entriesFromBytes($withTreeAndOptional);
        $t->same(2, IndexFile::versionFromBytes($withTreeAndOptional));
        $t->same(1, count($parsedSingle));
        $t->same($emptyBlob, $parsedSingle[0]->oid);
        $t->same('a', $parsedSingle[0]->path);
        $t->same($treeOid, IndexFile::cacheTreeFromBytes($withTreeAndOptional)?->oid);

        $moreEntries = IndexFile::sortEntries(array_map($entry, ['a', 'b', 'c', 'd/a', 'd/b', 'd/c']));
        $moreTree = new IndexCacheTree('', $treeOid, 6, [
            new IndexCacheTree('d', '765b32c65d38f04c4f287abda055818ec0f26912', 3),
        ]);
        $moreBytes = IndexFile::bytesFor($moreEntries, $moreTree);
        $t->same(2, IndexFile::versionFromBytes($moreBytes));
        $t->same($pathsOf($moreEntries), $pathsOf(IndexFile::entriesFromBytes($moreBytes)));
        $parsedMoreTree = IndexFile::cacheTreeFromBytes($moreBytes);
        $t->same(6, $parsedMoreTree?->numEntries);
        $t->same(3, $parsedMoreTree?->childNamed('d')?->numEntries);

        $longPath = str_repeat('a', 4096) . 'q';
        $longEntries = IndexFile::sortEntries([
            $entry($longPath),
            $entry('b'),
            $entry('c'),
            $entry('d/a'),
            $entry('d/b'),
            $entry('d/c'),
            $entry('e'),
            $entry('f'),
            $entry('g'),
        ]);
        $parsedLong = IndexFile::entriesFromBytes(IndexFile::bytesFor($longEntries));
        $t->same(9, count($parsedLong));
        $t->same($longPath, $parsedLong[0]->path);

        $optionalUnknown = $withExtension(IndexFile::bytesFor($single), 'ZZZZ', 'optional payload');
        $t->same(['a'], $pathsOf(IndexFile::entriesFromBytes($optionalUnknown)));

        $mandatoryUnknown = $withExtension(IndexFile::bytesFor($single), 'sdir', 'mandatory payload');
        $throwsMessage(
            $t,
            'Unsupported mandatory index extension',
            static fn () => IndexFile::entriesFromBytes($mandatoryUnknown),
        );

        $throwsMessage(
            $t,
            'missing the DIRC signature',
            static fn () => IndexFile::entriesFromBytes('NOPE' . substr($emptyBytes, 4)),
        );
        $unsupportedVersion = $withChecksum('DIRC' . pack('N2', 5, 0));
        $throwsMessage(
            $t,
            'Unsupported index version: 5',
            static fn () => IndexFile::entriesFromBytes($unsupportedVersion),
        );
        $badChecksum = substr($emptyBytes, 0, -1) . (substr($emptyBytes, -1) === "\0" ? "\1" : "\0");
        $throwsMessage(
            $t,
            'Index checksum mismatch',
            static fn () => IndexFile::entriesFromBytes($badChecksum),
        );
    },
    'upstream read conflict and sparse entry flags' => static function (TestRunner $t) use (
        $entry,
        $conflictingFileEntries,
        $pathsOf,
        $stagesOf,
    ): void {
        $conflicts = IndexFile::entriesFromBytes(IndexFile::bytesFor($conflictingFileEntries()));
        $t->same(3, count($conflicts));
        $t->same([IndexEntry::STAGE_ANCESTOR, IndexEntry::STAGE_OURS, IndexEntry::STAGE_THEIRS], $stagesOf($conflicts));

        $nonSparse = IndexFile::entriesFromBytes(IndexFile::bytesFor([
            $entry('a'),
            $entry('c1/c2/file'),
            $entry('c1/c3/file', skipWorktree: true),
            $entry('d/a', skipWorktree: true),
        ]));
        $t->same(['a', 'c1/c2/file', 'c1/c3/file', 'd/a'], $pathsOf($nonSparse));
        foreach ($nonSparse as $parsedEntry) {
            $shouldSkip = str_starts_with($parsedEntry->path, 'd') || str_starts_with($parsedEntry->path, 'c1/c3');
            $t->same(IndexEntry::MODE_FILE, $parsedEntry->mode);
            $t->same($shouldSkip, $parsedEntry->skipWorktree);
        }

        $cone = IndexFile::entriesFromBytes(IndexFile::bytesFor([
            $entry('a'),
            $entry('c1/c2/file'),
            $entry('c1/c3', mode: IndexEntry::MODE_DIR, skipWorktree: true),
            $entry('d', mode: IndexEntry::MODE_DIR, skipWorktree: true),
        ]));
        foreach ($cone as $parsedEntry) {
            if (str_starts_with($parsedEntry->path, 'c1/c3') || str_starts_with($parsedEntry->path, 'd')) {
                $t->same(IndexEntry::MODE_DIR, $parsedEntry->mode);
                $t->same(true, $parsedEntry->skipWorktree);
            } else {
                $t->same(IndexEntry::MODE_FILE, $parsedEntry->mode);
                $t->same(false, $parsedEntry->skipWorktree);
            }
        }

        $nonCone = IndexFile::entriesFromBytes(IndexFile::bytesFor([
            $entry('c1/c2/file'),
            $entry('c1/c3/file', skipWorktree: true),
            $entry('d/a', skipWorktree: true),
        ]));
        foreach ($nonCone as $parsedEntry) {
            $t->same(str_starts_with($parsedEntry->path, 'c1/c2') ? false : true, $parsedEntry->skipWorktree);
            $t->same(IndexEntry::MODE_FILE, $parsedEntry->mode);
        }
    },
    'upstream write and init roundtrips' => static function (TestRunner $t) use (
        $entry,
        $moreFileEntries,
        $conflictingFileEntries,
        $pathsOf,
    ): void {
        $roundtripSets = [
            [],
            $moreFileEntries(),
            $conflictingFileEntries(),
            [
                $entry('a'),
                $entry('c1/c3', mode: IndexEntry::MODE_DIR, skipWorktree: true),
                $entry('d', mode: IndexEntry::MODE_DIR, skipWorktree: true),
            ],
        ];
        foreach ($roundtripSets as $entries) {
            $bytes = IndexFile::bytesFor($entries);
            $parsed = IndexFile::entriesFromBytes($bytes);
            $rewritten = IndexFile::bytesFor($parsed);

            $t->same($bytes, $rewritten);
            $t->same($pathsOf(IndexFile::sortEntries($entries)), $pathsOf($parsed));
        }

        $empty = IndexFile::bytesFor([]);
        $t->same(2, IndexFile::versionFromBytes($empty));
        $t->same(0, count(IndexFile::entriesFromBytes($empty)));

        $upgraded = IndexFile::bytesFor([$entry('a', skipWorktree: true)]);
        $t->same(3, IndexFile::versionFromBytes($upgraded));

        $directory = sys_get_temp_dir() . '/port-libs-index-' . getmypid();
        $path = $directory . '/index';
        try {
            $entries = [$entry('a'), $entry('d/a')];
            $checksum = IndexFile::write($path, $entries);
            $bytes = (string) file_get_contents($path);

            $t->same(true, is_file($path));
            $t->same(hash('sha1', substr($bytes, 0, -20)), $checksum);
            $t->same(['a', 'd/a'], $pathsOf(IndexFile::entriesFromBytes($bytes)));
            $t->same(2, IndexFile::versionFromBytes($bytes));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    },
];
