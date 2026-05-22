<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BlobMerge;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\MergeIndexEntry;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;
use PortLibs\Gitoxide\TreeMerge;

$oid = static fn (string $hex): string => str_repeat($hex, 40);
$entry = static fn (string $filename, string $oid, string $mode = '100644'): TreeEntry => new TreeEntry($mode, $filename, $oid);
$names = static fn (Tree $tree): array => array_map(static fn (TreeEntry $entry): string => $entry->filename, $tree->entries);
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

return [
    'merges independent flat WordPress tree changes' => static function (TestRunner $t) use ($oid, $entry, $names): void {
        $base = new Tree([
            $entry('index.php', $oid('1')),
            $entry('wp-config.php', $oid('2')),
            $entry('wp-content', $oid('3'), '40000'),
        ]);
        $ours = new Tree([
            $entry('index.php', $oid('1')),
            $entry('wp-config.php', $oid('2')),
            $entry('wp-content', $oid('4'), '40000'),
        ]);
        $theirs = new Tree([
            $entry('.wp-env.json', $oid('5')),
            $entry('index.php', $oid('1')),
            $entry('wp-config.php', $oid('2')),
            $entry('wp-content', $oid('3'), '40000'),
        ]);

        $result = TreeMerge::mergeFlat($base, $ours, $theirs);

        $t->true($result->isClean());
        $t->same(['.wp-env.json', 'index.php', 'wp-config.php', 'wp-content'], $names($result->tree));
        $t->same($oid('4'), $result->tree->entryNamed('wp-content', true)?->oid);
        $t->same($oid('5'), $result->tree->entryNamed('.wp-env.json', false)?->oid);
    },
    'reports modify modify conflicts and keeps base entry in result tree' => static function (TestRunner $t) use ($oid, $entry): void {
        $base = new Tree([$entry('theme.json', $oid('1'))]);
        $ours = new Tree([$entry('theme.json', $oid('2'))]);
        $theirs = new Tree([$entry('theme.json', $oid('3'))]);

        $result = TreeMerge::mergeFlat($base, $ours, $theirs);

        $t->same(false, $result->isClean());
        $t->same(1, count($result->conflicts));
        $t->same('modify-modify', $result->conflicts[0]->reason);
        $t->same($oid('1'), $result->tree->entryNamed('theme.json')?->oid);
    },
    'handles delete delete as a clean removal' => static function (TestRunner $t) use ($oid, $entry): void {
        $base = new Tree([$entry('wp-content/plugins/old.php', $oid('1'))]);
        $ours = new Tree([]);
        $theirs = new Tree([]);

        $result = TreeMerge::mergeFlat($base, $ours, $theirs);

        $t->true($result->isClean());
        $t->same([], $result->tree->entries);
    },
    'reports delete modify conflicts with staged sides' => static function (TestRunner $t) use ($oid, $entry): void {
        $base = new Tree([$entry('wp-content/plugins/acme.php', $oid('1'))]);
        $ours = new Tree([]);
        $theirs = new Tree([$entry('wp-content/plugins/acme.php', $oid('2'))]);

        $result = TreeMerge::mergeFlat($base, $ours, $theirs);

        $t->same('delete-modify', $result->conflicts[0]->reason);
        $t->same($oid('1'), $result->conflicts[0]->base?->oid);
        $t->same(null, $result->conflicts[0]->ours);
        $t->same($oid('2'), $result->conflicts[0]->theirs?->oid);
        $t->same($oid('1'), $result->tree->entryNamed('wp-content/plugins/acme.php')?->oid);
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'oid' => $oid('1')],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'oid' => $oid('2')],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => ['stage' => $entry->stage, 'side' => $entry->side(), 'oid' => $entry->oid],
            $result->indexEntries(),
        ));
    },
    'resolves identical add add entries but reports divergent additions' => static function (TestRunner $t) use ($oid, $entry): void {
        $same = TreeMerge::mergeFlat(
            new Tree([]),
            new Tree([$entry('wp-content/mu-plugins/bootstrap.php', $oid('1'))]),
            new Tree([$entry('wp-content/mu-plugins/bootstrap.php', $oid('1'))]),
        );
        $different = TreeMerge::mergeFlat(
            new Tree([]),
            new Tree([$entry('wp-content/mu-plugins/bootstrap.php', $oid('1'))]),
            new Tree([$entry('wp-content/mu-plugins/bootstrap.php', $oid('2'))]),
        );

        $t->true($same->isClean());
        $t->same($oid('1'), $same->tree->entryNamed('wp-content/mu-plugins/bootstrap.php')?->oid);
        $t->same('add-add', $different->conflicts[0]->reason);
        $t->same([], $different->tree->entries);
    },
    'duplicate flat tree names are rejected' => static function (TestRunner $t) use ($oid, $entry): void {
        $tree = new Tree([$entry('wp-config.php', $oid('1')), $entry('wp-config.php', $oid('2'))]);

        $t->throws(InvalidArgumentException::class, static fn () => TreeMerge::mergeFlat(new Tree([]), $tree, new Tree([])));
    },
    'recursive tree merge combines independent nested blob edits' => static function (TestRunner $t) use ($objectStore): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $baseContent = "title: Demo\nslug: demo\nstatus: draft\n";
        $ourContent = "title: Demo Import\nslug: demo\nstatus: draft\n";
        $theirContent = "title: Demo\nslug: demo\nstatus: publish\n";
        $base = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('post.meta', $baseContent)]))]);
        $ours = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('post.meta', $ourContent)]))]);
        $theirs = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('post.meta', $theirContent)]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $mergedContentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
        $mergedPost = $read($mergedContentTree->entryNamed('post.meta')?->oid ?? '');

        $t->true($result->isClean());
        $t->same("title: Demo Import\nslug: demo\nstatus: publish\n", $mergedPost->body);
        $t->same([], $result->indexEntries());
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'recursive tree merge records content conflicts with full paths and marker blobs' => static function (TestRunner $t) use ($objectStore): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([
            $treeEntry('wp-content', new Tree([
                $treeEntry('themes', new Tree([
                    $treeEntry('acme', new Tree([
                        $blobEntry('theme.json', "{\n  \"color\": \"base\"\n}\n"),
                    ])),
                ])),
            ])),
        ]);
        $ours = new Tree([
            $treeEntry('wp-content', new Tree([
                $treeEntry('themes', new Tree([
                    $treeEntry('acme', new Tree([
                        $blobEntry('theme.json', "{\n  \"color\": \"blue\"\n}\n"),
                    ])),
                ])),
            ])),
        ]);
        $theirs = new Tree([
            $treeEntry('wp-content', new Tree([
                $treeEntry('themes', new Tree([
                    $treeEntry('acme', new Tree([
                        $blobEntry('theme.json', "{\n  \"color\": \"green\"\n}\n"),
                    ])),
                ])),
            ])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write, BlobMerge::STYLE_DIFF3);
        $contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
        $themesTree = Tree::fromObject($read($contentTree->entryNamed('themes', true)?->oid ?? ''));
        $themeTree = Tree::fromObject($read($themesTree->entryNamed('acme', true)?->oid ?? ''));
        $mergedThemeJson = $read($themeTree->entryNamed('theme.json')?->oid ?? '');

        $t->same(false, $result->isClean());
        $t->same(1, count($result->conflicts));
        $t->same('content-conflict', $result->conflicts[0]->reason);
        $t->same('wp-content/themes/acme/theme.json', $result->conflicts[0]->path);
        $t->contains('<<<<<<< ours/wp-content/themes/acme/theme.json', $mergedThemeJson->body);
        $t->contains('||||||| base/wp-content/themes/acme/theme.json', $mergedThemeJson->body);
        $t->contains('>>>>>>> theirs/wp-content/themes/acme/theme.json', $mergedThemeJson->body);
        $t->same([
            MergeIndexEntry::STAGE_ANCESTOR,
            MergeIndexEntry::STAGE_OURS,
            MergeIndexEntry::STAGE_THEIRS,
        ], array_map(static fn (MergeIndexEntry $entry): int => $entry->stage, $result->indexEntries()));
        $t->same([
            'ancestor',
            'ours',
            'theirs',
        ], array_map(static fn (MergeIndexEntry $entry): string => $entry->side(), $result->indexEntries()));
        $t->same([
            'wp-content/themes/acme/theme.json',
        ], array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read)));
        $t->same($mergedThemeJson->oid(), $result->worktreeConflictFiles($read)[0]->oid);
        $t->contains('<<<<<<< ours/wp-content/themes/acme/theme.json', $result->worktreeConflictFiles($read)[0]->content);
    },
];
