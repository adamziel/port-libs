<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BlobMerge;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\MergeIndexEntry;
use PortLibs\Gitoxide\MergeIndexFile;
use PortLibs\Gitoxide\MergeWorktreeWriter;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;
use PortLibs\Gitoxide\TreeMerge;
use PortLibs\Gitoxide\TreeMergeResult;

$oid = static fn (string $hex): string => str_repeat($hex, 40);
$objectStore = static function (): array {
    $objects = [];
    $write = static function (GitObject $object) use (&$objects): string {
        $oid = $object->oid();
        $objects[$oid] = $object;

        return $oid;
    };
    $read = static function (string $oid) use (&$objects): GitObject {
        if (!isset($objects[$oid])) {
            throw new RuntimeException("Fixture object not found: {$oid}");
        }

        return $objects[$oid];
    };
    $blobEntry = static fn (string $filename, string $content, string $mode = '100644'): TreeEntry => new TreeEntry($mode, $filename, $write(new GitObject('blob', $content)));
    $treeEntry = static fn (string $filename, Tree $tree): TreeEntry => new TreeEntry('40000', $filename, $write($tree->toObject()));

    return [$read, $write, $blobEntry, $treeEntry];
};
$recursiveConflict = static function () use ($objectStore): array {
    [$read, $write, $blobEntry, $treeEntry] = $objectStore();
    $wpContent = static fn (TreeEntry $metadata, TreeEntry $theme): Tree => new Tree([
        $metadata,
        $treeEntry('themes', new Tree([
            $treeEntry('acme', new Tree([$theme])),
        ])),
    ]);
    $base = new Tree([
        $treeEntry('wp-content', $wpContent(
            $blobEntry('post.meta', "title: Demo\nslug: demo\nstatus: draft\n"),
            $blobEntry('theme.json', "{\n  \"color\": \"base\"\n}\n"),
        )),
    ]);
    $ours = new Tree([
        $treeEntry('wp-content', $wpContent(
            $blobEntry('post.meta', "title: Demo Import\nslug: demo\nstatus: draft\n"),
            $blobEntry('theme.json', "{\n  \"color\": \"blue\"\n}\n"),
        )),
    ]);
    $theirs = new Tree([
        $treeEntry('wp-content', $wpContent(
            $blobEntry('post.meta', "title: Demo\nslug: demo\nstatus: publish\n"),
            $blobEntry('theme.json', "{\n  \"color\": \"green\"\n}\n"),
        )),
    ]);

    return [TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write, BlobMerge::STYLE_DIFF3), $read];
};

return [
    'writes unmerged blob stages to a git index v2 file' => static function (TestRunner $t) use ($oid): void {
        $entries = [
            new MergeIndexEntry('wp-content/themes/acme/theme.json', MergeIndexEntry::STAGE_THEIRS, '100644', $oid('3')),
            new MergeIndexEntry('wp-content/themes/acme/theme.json', MergeIndexEntry::STAGE_ANCESTOR, '100644', $oid('1')),
            new MergeIndexEntry('wp-content/themes/acme/theme.json', MergeIndexEntry::STAGE_OURS, '100755', $oid('2')),
        ];
        $dir = sys_get_temp_dir() . '/port-libs-git-index-' . bin2hex(random_bytes(4));
        $path = $dir . '/.git/index';

        $checksum = MergeIndexFile::write($path, $entries);
        $bytes = (string) file_get_contents($path);
        $parsed = MergeIndexFile::entriesFromBytes($bytes);

        $t->same('DIRC', substr($bytes, 0, 4));
        $t->same(2, unpack('N', substr($bytes, 4, 4))[1]);
        $t->same(3, unpack('N', substr($bytes, 8, 4))[1]);
        $t->same(hash('sha1', substr($bytes, 0, -20)), $checksum);
        $t->same([
            MergeIndexEntry::STAGE_ANCESTOR,
            MergeIndexEntry::STAGE_OURS,
            MergeIndexEntry::STAGE_THEIRS,
        ], array_map(static fn (MergeIndexEntry $entry): int => $entry->stage, $parsed));
        $t->same([
            '100644',
            '100755',
            '100644',
        ], array_map(static fn (MergeIndexEntry $entry): string => $entry->mode, $parsed));
        $t->same([
            $oid('1'),
            $oid('2'),
            $oid('3'),
        ], array_map(static fn (MergeIndexEntry $entry): string => $entry->oid, $parsed));
    },
    'rejects tree stages that a git index cannot store' => static function (TestRunner $t) use ($oid): void {
        $entries = [new MergeIndexEntry('wp-content/cache', MergeIndexEntry::STAGE_OURS, '40000', $oid('4'))];

        $t->throws(RuntimeException::class, static fn () => MergeIndexFile::bytesFor($entries));
    },
    'writes relocated directory file conflicts to git index entries' => static function (TestRunner $t) use ($objectStore): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([
            $treeEntry('wp-content', new Tree([])),
        ]);
        $ours = new Tree([
            $treeEntry('wp-content', new Tree([
                $treeEntry('cache', new Tree([
                    $blobEntry('index.php', "<?php\n"),
                    $treeEntry('nested', new Tree([
                        $blobEntry('asset.txt', "cached asset\n"),
                    ])),
                ])),
            ])),
        ]);
        $theirs = new Tree([
            $treeEntry('wp-content', new Tree([
                $blobEntry('cache', "legacy cache file\n"),
            ])),
        ]);
        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $dir = sys_get_temp_dir() . '/port-libs-expanded-index-' . bin2hex(random_bytes(4));

        $expanded = MergeIndexFile::entriesForResult($result, $read);
        MergeIndexFile::writeResult($dir . '/.git/index', $result, $read);
        $parsed = MergeIndexFile::entriesFromBytes((string) file_get_contents($dir . '/.git/index'));

        $t->same([
            ['path' => 'wp-content/cache~B', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'kind' => 'blob'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => ['path' => $entry->path, 'stage' => $entry->stage, 'kind' => (new TreeEntry($entry->mode, basename($entry->path), $entry->oid))->kind()],
            $expanded,
        ));
        $t->same(
            array_map(static fn (MergeIndexEntry $entry): array => [$entry->path, $entry->stage, $entry->oid], $expanded),
            array_map(static fn (MergeIndexEntry $entry): array => [$entry->path, $entry->stage, $entry->oid], $parsed),
        );
    },
    'writes merged recursive worktree files including marker blobs' => static function (TestRunner $t) use ($recursiveConflict): void {
        [$result, $read] = $recursiveConflict();
        $worktree = sys_get_temp_dir() . '/port-libs-worktree-' . bin2hex(random_bytes(4));

        $files = MergeWorktreeWriter::writeMergedTree($result, $worktree, $read);

        $t->same([
            'wp-content/post.meta',
            'wp-content/themes/acme/theme.json',
        ], array_map(static fn ($file): string => $file->path, $files));
        $t->same("title: Demo Import\nslug: demo\nstatus: publish\n", (string) file_get_contents($worktree . '/wp-content/post.meta'));
        $themeJson = (string) file_get_contents($worktree . '/wp-content/themes/acme/theme.json');
        $t->contains('<<<<<<< ours/wp-content/themes/acme/theme.json', $themeJson);
        $t->contains('||||||| base/wp-content/themes/acme/theme.json', $themeJson);
        $t->contains('>>>>>>> theirs/wp-content/themes/acme/theme.json', $themeJson);
        $t->same('0644', substr(sprintf('%o', fileperms($worktree . '/wp-content/post.meta')), -4));
    },
    'checkout merged tree removes stale paths but preserves git metadata' => static function (TestRunner $t) use ($objectStore): void {
        [$read, , $blobEntry, $treeEntry] = $objectStore();
        $result = new TreeMergeResult(new Tree([
            $treeEntry('wp-content', new Tree([
                $blobEntry('new.php', "<?php\nreturn 'new';\n"),
            ])),
        ]), []);
        $worktree = sys_get_temp_dir() . '/port-libs-clean-worktree-' . bin2hex(random_bytes(4));
        mkdir($worktree . '/.git', 0777, true);
        mkdir($worktree . '/wp-content/cache', 0777, true);
        file_put_contents($worktree . '/.git/config', "[core]\n");
        file_put_contents($worktree . '/wp-content/old.php', "<?php\nreturn 'old';\n");
        file_put_contents($worktree . '/wp-content/cache/transient.txt', "cached\n");

        $files = MergeWorktreeWriter::checkoutMergedTree($result, $worktree, $read);

        $t->same(['wp-content/new.php'], array_map(static fn ($file): string => $file->path, $files));
        $t->same(true, is_file($worktree . '/.git/config'));
        $t->same(false, file_exists($worktree . '/wp-content/old.php'));
        $t->same(false, file_exists($worktree . '/wp-content/cache'));
        $t->same("<?php\nreturn 'new';\n", (string) file_get_contents($worktree . '/wp-content/new.php'));
    },
    'checkout merged tree replaces file and directory blockers' => static function (TestRunner $t) use ($objectStore): void {
        [$read, , $blobEntry, $treeEntry] = $objectStore();
        $result = new TreeMergeResult(new Tree([
            $treeEntry('wp-content', new Tree([
                $blobEntry('cache', "cache file\n"),
                $treeEntry('plugins', new Tree([
                    $blobEntry('acme.php', "<?php\n"),
                ])),
            ])),
        ]), []);
        $worktree = sys_get_temp_dir() . '/port-libs-blocker-worktree-' . bin2hex(random_bytes(4));
        mkdir($worktree . '/wp-content/cache', 0777, true);
        file_put_contents($worktree . '/wp-content/cache/index.php', "<?php\n");
        file_put_contents($worktree . '/wp-content/plugins', "legacy plugin file\n");

        MergeWorktreeWriter::checkoutMergedTree($result, $worktree, $read);

        $t->same("cache file\n", (string) file_get_contents($worktree . '/wp-content/cache'));
        $t->same(true, is_dir($worktree . '/wp-content/plugins'));
        $t->same("<?php\n", (string) file_get_contents($worktree . '/wp-content/plugins/acme.php'));
    },
    'writes only content conflict files when requested' => static function (TestRunner $t) use ($recursiveConflict): void {
        [$result, $read] = $recursiveConflict();
        $worktree = sys_get_temp_dir() . '/port-libs-conflict-worktree-' . bin2hex(random_bytes(4));

        $files = MergeWorktreeWriter::writeConflictFiles($result, $worktree, $read);

        $t->same(['wp-content/themes/acme/theme.json'], array_map(static fn ($file): string => $file->path, $files));
        $t->same(false, is_file($worktree . '/wp-content/post.meta'));
        $t->contains('<<<<<<< ours/wp-content/themes/acme/theme.json', (string) file_get_contents($worktree . '/wp-content/themes/acme/theme.json'));
    },
    'rejects unsafe worktree paths' => static function (TestRunner $t) use ($objectStore): void {
        [$read, $write] = $objectStore();
        $oid = $write(new GitObject('blob', 'do not write outside worktree'));
        $result = new TreeMergeResult(new Tree([new TreeEntry('100644', '../evil.php', $oid)]), []);
        $worktree = sys_get_temp_dir() . '/port-libs-unsafe-worktree-' . bin2hex(random_bytes(4));

        $t->throws(InvalidArgumentException::class, static fn () => MergeWorktreeWriter::writeMergedTree($result, $worktree, $read));
    },
];
