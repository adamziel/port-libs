<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BlobMerge;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\MergeIndexFile;
use PortLibs\Gitoxide\MergeIndexEntry;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;
use PortLibs\Gitoxide\TreeMerge;
use PortLibs\Gitoxide\TreeMergeResult;

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
    'reports exact rename delete conflicts with staged sides' => static function (TestRunner $t) use ($oid, $entry, $names): void {
        $base = new Tree([$entry('old.php', $oid('1'))]);
        $ours = new Tree([$entry('new.php', $oid('1'))]);
        $theirs = new Tree([]);

        $result = TreeMerge::mergeFlat($base, $ours, $theirs);

        $t->same(false, $result->isClean());
        $t->same(1, count($result->conflicts));
        $t->same('rename-delete', $result->conflicts[0]->reason);
        $t->same('old.php', $result->conflicts[0]->path);
        $t->same($oid('1'), $result->conflicts[0]->base?->oid);
        $t->same('new.php', $result->conflicts[0]->ours?->filename);
        $t->same(null, $result->conflicts[0]->theirs);
        $t->same(['old.php'], $names($result->tree));
        $t->same($oid('1'), $result->tree->entryNamed('old.php')?->oid);
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'oid' => $oid('1')],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'oid' => $oid('1')],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => ['stage' => $entry->stage, 'side' => $entry->side(), 'oid' => $entry->oid],
            $result->indexEntries(),
        ));
    },
    'reports exact rename rename conflicts and keeps base path' => static function (TestRunner $t) use ($oid, $entry, $names): void {
        $base = new Tree([$entry('old.php', $oid('1'))]);
        $ours = new Tree([$entry('ours.php', $oid('1'))]);
        $theirs = new Tree([$entry('theirs.php', $oid('1'))]);

        $result = TreeMerge::mergeFlat($base, $ours, $theirs);

        $t->same(false, $result->isClean());
        $t->same(1, count($result->conflicts));
        $t->same('rename-rename', $result->conflicts[0]->reason);
        $t->same('old.php', $result->conflicts[0]->path);
        $t->same('ours.php', $result->conflicts[0]->ours?->filename);
        $t->same('theirs.php', $result->conflicts[0]->theirs?->filename);
        $t->same(['old.php'], $names($result->tree));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'oid' => $oid('1')],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'oid' => $oid('1')],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'oid' => $oid('1')],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => ['stage' => $entry->stage, 'side' => $entry->side(), 'oid' => $entry->oid],
            $result->indexEntries(),
        ));
    },
    'does not infer ambiguous exact renames from duplicate objects' => static function (TestRunner $t) use ($oid, $entry, $names): void {
        $base = new Tree([
            $entry('old-a.php', $oid('1')),
            $entry('old-b.php', $oid('1')),
        ]);
        $ours = new Tree([$entry('new.php', $oid('1'))]);
        $theirs = new Tree([]);

        $result = TreeMerge::mergeFlat($base, $ours, $theirs);

        $t->true($result->isClean());
        $t->same(['new.php'], $names($result->tree));
        $t->same([], $result->indexEntries());
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
    'reports directory file conflicts before generic add add conflicts' => static function (TestRunner $t) use ($oid, $entry): void {
        $result = TreeMerge::mergeFlat(
            new Tree([]),
            new Tree([$entry('wp-content/cache', $oid('1'), '40000')]),
            new Tree([$entry('wp-content/cache', $oid('2'))]),
        );

        $t->same('directory-file', $result->conflicts[0]->reason);
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
    'maps upstream gix-merge tree-baseline big-file-merge fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $baseContent = "original\n1\n2\n3\n4\n5\n";
        $ourContent = implode("\n", range(1, 37)) . "\n";
        $theirContent = "1\n2\n3\n4\n5\n6\n";
        $base = new Tree([$treeEntry('a', new Tree([$blobEntry('x.f', $baseContent)]))]);
        $ours = new Tree([$treeEntry('a', new Tree([$blobEntry('x.f', $ourContent)]))]);
        $theirs = new Tree([$treeEntry('a', new Tree([$blobEntry('x.f', $theirContent)]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write, BlobMerge::STYLE_MERGE, 100);
        $aTree = Tree::fromObject($read($result->tree->entryNamed('a', true)?->oid ?? ''));
        $mergedFile = $read($aTree->entryNamed('x.f')?->oid ?? '');
        $indexEntries = $result->indexEntries();
        $worktreeFiles = $result->worktreeConflictFiles($read);

        $t->same(false, $result->isClean());
        $t->same(['a'], $names($result->tree));
        $t->same($ourContent, $mergedFile->body);
        $t->same([
            ['path' => 'a/x.f', 'reason' => 'content-conflict', 'base' => 'x.f', 'ours' => 'x.f', 'theirs' => 'x.f'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'a/x.f'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'a/x.f'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'a/x.f'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => ['stage' => $entry->stage, 'side' => $entry->side(), 'path' => $entry->path],
            $indexEntries,
        ));
        $t->same([$baseContent, $ourContent, $theirContent], array_map(
            static fn (MergeIndexEntry $entry): string => $read($entry->oid)->body,
            $indexEntries,
        ));
        $t->same([
            ['path' => 'a/x.f', 'content' => $ourContent],
        ], array_map(
            static fn ($file): array => ['path' => $file->path, 'content' => $file->content],
            $worktreeFiles,
        ));
    },
    'maps upstream gix-merge tree-baseline simple side-1-3-without-conflict fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $base = new Tree([
            $blobEntry('numbers', "1\n2\n3\n4\n5\n"),
            $blobEntry('greeting', "hello\n"),
            $blobEntry('whatever', "foo\n"),
        ]);
        $ours = new Tree([
            $blobEntry('numbers', "1\n2\n3\n4\n5\n6\n"),
            $blobEntry('greeting', "hi\n"),
            $blobEntry('whatever', "bar\n"),
        ]);
        $theirs = new Tree([
            $blobEntry('sequence', "1\n2\n3\n4\n5\n"),
            $blobEntry('greeting', "hello\n"),
            $blobEntry('whatever', "foo\n"),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);

        $t->true($result->isClean());
        $t->same(['greeting', 'sequence', 'whatever'], $names($result->tree));
        $t->same("hi\n", $read($result->tree->entryNamed('greeting')?->oid ?? '')->body);
        $t->same("1\n2\n3\n4\n5\n6\n", $read($result->tree->entryNamed('sequence')?->oid ?? '')->body);
        $t->same("bar\n", $read($result->tree->entryNamed('whatever')?->oid ?? '')->body);
        $t->same([], $result->indexEntries());
        $t->same([], $result->worktreeConflictFiles($read));

        $reverse = TreeMerge::mergeRecursive($base, $theirs, $ours, $read, $write);

        $t->true($reverse->isClean());
        $t->same(['greeting', 'sequence', 'whatever'], $names($reverse->tree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($reverse->tree->entryNamed('sequence')?->oid ?? '')->body);
        $t->same([], $reverse->indexEntries());
    },
    'maps upstream gix-merge tree-baseline simple fast-forward no-change and unrelated fixture shapes' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $main = new Tree([
            $blobEntry('numbers', "1\n2\n3\n4\n5\n"),
            $blobEntry('greeting', "hello\n"),
            $blobEntry('whatever', "foo\n"),
        ]);
        $side1 = new Tree([
            $blobEntry('numbers', "1\n2\n3\n4\n5\n6\n"),
            $blobEntry('greeting', "hi\n"),
            $blobEntry('whatever', "bar\n"),
        ]);
        $unrelated = new Tree([
            $blobEntry('something-else', ''),
        ]);

        $fastForward = TreeMerge::mergeRecursive($main, $side1, $main, $read, $write);
        $fastForwardReverse = TreeMerge::mergeRecursive($main, $main, $side1, $read, $write);
        $noChange = TreeMerge::mergeRecursive($main, $main, $main, $read, $write);
        $unrelatedMerge = TreeMerge::mergeRecursive(new Tree([]), $side1, $unrelated, $read, $write);
        $unrelatedDiff3 = TreeMerge::mergeRecursive(new Tree([]), $side1, $unrelated, $read, $write, BlobMerge::STYLE_DIFF3);

        $t->true($fastForward->isClean());
        $t->same(['greeting', 'numbers', 'whatever'], $names($fastForward->tree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($fastForward->tree->entryNamed('numbers')?->oid ?? '')->body);
        $t->same("hi\n", $read($fastForward->tree->entryNamed('greeting')?->oid ?? '')->body);
        $t->same("bar\n", $read($fastForward->tree->entryNamed('whatever')?->oid ?? '')->body);
        $t->same([], $fastForward->indexEntries());
        $t->same(['greeting', 'numbers', 'whatever'], $names($fastForwardReverse->tree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($fastForwardReverse->tree->entryNamed('numbers')?->oid ?? '')->body);
        $t->same([], $fastForwardReverse->indexEntries());

        $t->true($noChange->isClean());
        $t->same(['greeting', 'numbers', 'whatever'], $names($noChange->tree));
        $t->same("1\n2\n3\n4\n5\n", $read($noChange->tree->entryNamed('numbers')?->oid ?? '')->body);
        $t->same([], $noChange->indexEntries());

        $t->true($unrelatedMerge->isClean());
        $t->same(['greeting', 'numbers', 'something-else', 'whatever'], $names($unrelatedMerge->tree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($unrelatedMerge->tree->entryNamed('numbers')?->oid ?? '')->body);
        $t->same('', $read($unrelatedMerge->tree->entryNamed('something-else')?->oid ?? '')->body);
        $t->same([], $unrelatedMerge->indexEntries());
        $t->same([], $unrelatedMerge->worktreeConflictFiles($read));
        $t->true($unrelatedDiff3->isClean());
        $t->same(['greeting', 'numbers', 'something-else', 'whatever'], $names($unrelatedDiff3->tree));
        $t->same([], $unrelatedDiff3->indexEntries());
    },
    'maps upstream gix-merge tree-baseline simple single-content-conflict fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $base = new Tree([
            $blobEntry('numbers', "1\n2\n3\n4\n5\n"),
            $blobEntry('greeting', "hello\n"),
            $blobEntry('whatever', "foo\n"),
        ]);
        $ours = new Tree([
            $blobEntry('numbers', "1\n2\n3\n4\n5\n6\n"),
            $blobEntry('greeting', "hi\n"),
            $blobEntry('whatever', "bar\n"),
        ]);
        $theirs = new Tree([
            $blobEntry('numbers', "0\n1\n2\n3\n4\n5\n"),
            $blobEntry('greeting', "yo\n"),
            $blobEntry('whatever', "foo\n"),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $mergedGreeting = $read($result->tree->entryNamed('greeting')?->oid ?? '');

        $t->same(false, $result->isClean());
        $t->same(['greeting', 'numbers', 'whatever'], $names($result->tree));
        $t->contains('<<<<<<< ours/greeting', $mergedGreeting->body);
        $t->contains("hi\n", $mergedGreeting->body);
        $t->contains("yo\n", $mergedGreeting->body);
        $t->same("0\n1\n2\n3\n4\n5\n6\n", $read($result->tree->entryNamed('numbers')?->oid ?? '')->body);
        $t->same("bar\n", $read($result->tree->entryNamed('whatever')?->oid ?? '')->body);
        $t->same([
            ['path' => 'greeting', 'reason' => 'content-conflict', 'base' => 'greeting', 'ours' => 'greeting', 'theirs' => 'greeting'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'greeting', 'body' => "hello\n"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'greeting', 'body' => "hi\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'greeting', 'body' => "yo\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same(['greeting'], array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read)));

        $diff3 = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write, BlobMerge::STYLE_DIFF3);
        $diff3Greeting = $read($diff3->tree->entryNamed('greeting')?->oid ?? '');

        $t->same(false, $diff3->isClean());
        $t->contains('||||||| base/greeting', $diff3Greeting->body);
        $t->same("0\n1\n2\n3\n4\n5\n6\n", $read($diff3->tree->entryNamed('numbers')?->oid ?? '')->body);
    },
    'maps upstream gix-merge tree-baseline simple side-1-2-various-conflicts fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([
            $blobEntry('numbers', "1\n2\n3\n4\n5\n"),
            $blobEntry('greeting', "hello\n"),
            $blobEntry('whatever', "foo\n"),
        ]);
        $ours = new Tree([
            $blobEntry('numbers', "1\n2\n3\n4\n5\n6\n"),
            $blobEntry('greeting', "hi\n"),
            $blobEntry('whatever', "bar\n"),
        ]);
        $theirs = new Tree([
            $blobEntry('numbers', "0\n1\n2\n3\n4\n5\n"),
            $blobEntry('greeting', "yo\n"),
            $treeEntry('whatever', new Tree([$blobEntry('empty', '')])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $mergedGreeting = $read($result->tree->entryNamed('greeting')?->oid ?? '');
        $whateverTree = Tree::fromObject($read($result->tree->entryNamed('whatever', true)?->oid ?? ''));

        $t->same(false, $result->isClean());
        $t->same(['greeting', 'numbers', 'whatever', 'whatever~A'], $names($result->tree));
        $t->same(['empty'], $names($whateverTree));
        $t->contains('<<<<<<< ours/greeting', $mergedGreeting->body);
        $t->contains("hi\n", $mergedGreeting->body);
        $t->contains("yo\n", $mergedGreeting->body);
        $t->same("0\n1\n2\n3\n4\n5\n6\n", $read($result->tree->entryNamed('numbers')?->oid ?? '')->body);
        $t->same("bar\n", $read($result->tree->entryNamed('whatever~A')?->oid ?? '')->body);
        $t->same([
            ['path' => 'greeting', 'reason' => 'content-conflict', 'base' => 'greeting', 'ours' => 'greeting', 'theirs' => 'greeting'],
            ['path' => 'whatever~A', 'reason' => 'delete-modify', 'base' => 'whatever~A', 'ours' => 'whatever~A', 'theirs' => null],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'greeting', 'body' => "hello\n"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'greeting', 'body' => "hi\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'greeting', 'body' => "yo\n"],
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'whatever~A', 'body' => "foo\n"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'whatever~A', 'body' => "bar\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same(['greeting'], array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read)));

        $diff3 = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write, BlobMerge::STYLE_DIFF3);
        $diff3Greeting = $read($diff3->tree->entryNamed('greeting')?->oid ?? '');

        $t->same(false, $diff3->isClean());
        $t->contains('||||||| base/greeting', $diff3Greeting->body);
        $t->same("0\n1\n2\n3\n4\n5\n6\n", $read($diff3->tree->entryNamed('numbers')?->oid ?? '')->body);
        $t->same("bar\n", $read($diff3->tree->entryNamed('whatever~A')?->oid ?? '')->body);
    },
    'maps upstream gix-merge tree-baseline simple directory-file resolve-tree fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([
            $blobEntry('numbers', "1\n2\n3\n4\n5\n"),
            $blobEntry('greeting', "hello\n"),
            $blobEntry('whatever', "foo\n"),
        ]);
        $side1 = new Tree([
            $blobEntry('numbers', "1\n2\n3\n4\n5\n6\n"),
            $blobEntry('greeting', "hi\n"),
            $blobEntry('whatever', "bar\n"),
        ]);
        $side2 = new Tree([
            $blobEntry('numbers', "0\n1\n2\n3\n4\n5\n"),
            $blobEntry('greeting', "yo\n"),
            $treeEntry('whatever', new Tree([$blobEntry('empty', '')])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $side1, $side2, $read, $write);
        $ancestorResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
        $oursResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
        $reverse = TreeMerge::mergeRecursive($base, $side2, $side1, $read, $write);
        $reverseAncestorResolved = $reverse->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
        $reverseTheirsResolved = $reverse->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_THEIRS);

        $t->same(['greeting', 'numbers', 'whatever'], $names($ancestorResolved->tree));
        $t->same('blob', $ancestorResolved->tree->entryNamed('whatever')?->kind());
        $t->same("foo\n", $read($ancestorResolved->tree->entryNamed('whatever')?->oid ?? '')->body);
        $t->same(['greeting'], array_map(static fn ($conflict): string => $conflict->path, $ancestorResolved->conflicts));
        $t->contains('<<<<<<< ours/greeting', $read($ancestorResolved->tree->entryNamed('greeting')?->oid ?? '')->body);
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'greeting'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'greeting'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'greeting'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => ['stage' => $entry->stage, 'side' => $entry->side(), 'path' => $entry->path],
            $ancestorResolved->indexEntries(),
        ));

        $t->same(['greeting', 'numbers', 'whatever'], $names($oursResolved->tree));
        $t->same('blob', $oursResolved->tree->entryNamed('whatever')?->kind());
        $t->same("bar\n", $read($oursResolved->tree->entryNamed('whatever')?->oid ?? '')->body);
        $t->same(['greeting'], array_map(static fn ($conflict): string => $conflict->path, $oursResolved->conflicts));

        $reverseDirectory = Tree::fromObject($read($reverse->tree->entryNamed('whatever', true)?->oid ?? ''));
        $t->same(['empty'], $names($reverseDirectory));
        $t->same(['greeting', 'numbers', 'whatever'], $names($reverseAncestorResolved->tree));
        $t->same('blob', $reverseAncestorResolved->tree->entryNamed('whatever')?->kind());
        $t->same("foo\n", $read($reverseAncestorResolved->tree->entryNamed('whatever')?->oid ?? '')->body);
        $t->same(['greeting', 'numbers', 'whatever'], $names($reverseTheirsResolved->tree));
        $t->same('blob', $reverseTheirsResolved->tree->entryNamed('whatever')?->kind());
        $t->same("bar\n", $read($reverseTheirsResolved->tree->entryNamed('whatever')?->oid ?? '')->body);
        $t->same(['greeting'], array_map(static fn ($conflict): string => $conflict->path, $reverseTheirsResolved->conflicts));
    },
    'maps upstream gix-merge tree-baseline simple tweak1-side2 fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $renamedNumbers = 'Αυτά μου φαίνονται κινέζικα';
        $base = new Tree([
            $blobEntry('numbers', "1\n2\n3\n4\n5\n"),
            $blobEntry('greeting', "hello\n"),
            $blobEntry('whatever', "foo\n"),
        ]);
        $ours = new Tree([
            $blobEntry($renamedNumbers, "zero\n1\n2\n3\n4\n5\n6\n"),
            $blobEntry('greeting', "hi\n"),
            $blobEntry('whatever', "bar\n"),
        ]);
        $theirs = new Tree([
            $blobEntry('numbers', "0\n1\n2\n3\n4\n5\n"),
            $blobEntry('greeting', "yo\n"),
            $treeEntry('whatever', new Tree([$blobEntry('empty', '')])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $mergedGreeting = $read($result->tree->entryNamed('greeting')?->oid ?? '');
        $mergedRenamedNumbers = $read($result->tree->entryNamed($renamedNumbers)?->oid ?? '');
        $whateverTree = Tree::fromObject($read($result->tree->entryNamed('whatever', true)?->oid ?? ''));
        $conflicts = array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        );
        usort($conflicts, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));
        $worktreePaths = array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read));
        sort($worktreePaths, SORT_STRING);

        $t->same(false, $result->isClean());
        $t->same(['greeting', 'whatever', 'whatever~A', $renamedNumbers], $names($result->tree));
        $t->same(['empty'], $names($whateverTree));
        $t->contains('<<<<<<< ours/greeting', $mergedGreeting->body);
        $t->contains('<<<<<<< ours/' . $renamedNumbers, $mergedRenamedNumbers->body);
        $t->contains("zero\n", $mergedRenamedNumbers->body);
        $t->contains("0\n", $mergedRenamedNumbers->body);
        $t->same("bar\n", $read($result->tree->entryNamed('whatever~A')?->oid ?? '')->body);
        $t->same([
            ['path' => 'greeting', 'reason' => 'content-conflict', 'base' => 'greeting', 'ours' => 'greeting', 'theirs' => 'greeting'],
            ['path' => 'whatever~A', 'reason' => 'delete-modify', 'base' => 'whatever~A', 'ours' => 'whatever~A', 'theirs' => null],
            ['path' => $renamedNumbers, 'reason' => 'content-conflict', 'base' => $renamedNumbers, 'ours' => $renamedNumbers, 'theirs' => $renamedNumbers],
        ], $conflicts);
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'greeting', 'body' => "hello\n"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'greeting', 'body' => "hi\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'greeting', 'body' => "yo\n"],
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'whatever~A', 'body' => "foo\n"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'whatever~A', 'body' => "bar\n"],
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => $renamedNumbers, 'body' => "1\n2\n3\n4\n5\n"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => $renamedNumbers, 'body' => "zero\n1\n2\n3\n4\n5\n6\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => $renamedNumbers, 'body' => "0\n1\n2\n3\n4\n5\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same(['greeting', $renamedNumbers], $worktreePaths);

        $diff3 = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write, BlobMerge::STYLE_DIFF3);
        $diff3RenamedNumbers = $read($diff3->tree->entryNamed($renamedNumbers)?->oid ?? '');

        $t->same(false, $diff3->isClean());
        $t->contains('||||||| base/' . $renamedNumbers, $diff3RenamedNumbers->body);
        $t->same("bar\n", $read($diff3->tree->entryNamed('whatever~A')?->oid ?? '')->body);
    },
    'recursive tree merge reports nested exact rename delete conflicts' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('old.php', "<?php\nreturn 'base';\n")]))]);
        $ours = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('new.php', "<?php\nreturn 'base';\n")]))]);
        $theirs = new Tree([$treeEntry('wp-content', new Tree([]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));

        $t->same(false, $result->isClean());
        $t->same(1, count($result->conflicts));
        $t->same('rename-delete', $result->conflicts[0]->reason);
        $t->same('wp-content/old.php', $result->conflicts[0]->path);
        $t->same('new.php', $result->conflicts[0]->ours?->filename);
        $t->same(null, $result->conflicts[0]->theirs);
        $t->same(['old.php'], $names($contentTree));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'wp-content/old.php'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'wp-content/old.php'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => ['stage' => $entry->stage, 'side' => $entry->side(), 'path' => $entry->path],
            $result->indexEntries(),
        ));
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'recursive tree merge reports similar rename delete conflicts' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $baseContent = "name: old-plugin\nversion: 1.0\nrequires: 6.5\nstatus: active\nentry: bootstrap.php\n";
        $ourContent = "name: new-plugin\nversion: 1.1\nrequires: 6.5\nstatus: active\nentry: bootstrap.php\n";
        $base = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('old-plugin.php', $baseContent)]))]);
        $ours = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('new-plugin.php', $ourContent)]))]);
        $theirs = new Tree([$treeEntry('wp-content', new Tree([]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));

        $t->same(false, $result->isClean());
        $t->same('rename-delete', $result->conflicts[0]->reason);
        $t->same('wp-content/old-plugin.php', $result->conflicts[0]->path);
        $t->same('new-plugin.php', $result->conflicts[0]->ours?->filename);
        $t->same(null, $result->conflicts[0]->theirs);
        $t->same(['old-plugin.php'], $names($contentTree));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'wp-content/old-plugin.php'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'wp-content/old-plugin.php'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => ['stage' => $entry->stage, 'side' => $entry->side(), 'path' => $entry->path],
            $result->indexEntries(),
        ));
    },
    'recursive tree merge reports similar rename modify conflicts' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $baseContent = "name: old-plugin\nversion: 1.0\nrequires: 6.5\nstatus: active\nentry: bootstrap.php\n";
        $ourContent = "name: new-plugin\nversion: 1.1\nrequires: 6.5\nstatus: active\nentry: bootstrap.php\n";
        $theirContent = "name: old-plugin\nversion: 1.0\nrequires: 6.6\nstatus: paused\nentry: bootstrap.php\n";
        $base = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('old-plugin.php', $baseContent)]))]);
        $ours = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('new-plugin.php', $ourContent)]))]);
        $theirs = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('old-plugin.php', $theirContent)]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
        $mergedPlugin = $read($contentTree->entryNamed('new-plugin.php')?->oid ?? '');

        $t->same(false, $result->isClean());
        $t->same(['new-plugin.php'], $names($contentTree));
        $t->same('content-conflict', $result->conflicts[0]->reason);
        $t->same('wp-content/new-plugin.php', $result->conflicts[0]->path);
        $t->same('new-plugin.php', $result->conflicts[0]->ours?->filename);
        $t->same('new-plugin.php', $result->conflicts[0]->theirs?->filename);
        $t->contains('<<<<<<< ours/wp-content/new-plugin.php', $mergedPlugin->body);
        $t->contains('>>>>>>> theirs/wp-content/new-plugin.php', $mergedPlugin->body);
        $t->same([
            MergeIndexEntry::STAGE_ANCESTOR,
            MergeIndexEntry::STAGE_OURS,
            MergeIndexEntry::STAGE_THEIRS,
        ], array_map(static fn (MergeIndexEntry $entry): int => $entry->stage, $result->indexEntries()));
        $t->same([
            'wp-content/new-plugin.php',
            'wp-content/new-plugin.php',
            'wp-content/new-plugin.php',
        ], array_map(static fn (MergeIndexEntry $entry): string => $entry->path, $result->indexEntries()));
    },
    'recursive tree merge cleanly merges same target similar renames' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $baseContent = "name: old-plugin\nversion: 1.0\nrequires: 6.5\nstatus: active\nentry: bootstrap.php\n";
        $ourContent = "name: new-plugin\nversion: 1.0\nrequires: 6.5\nstatus: active\nentry: bootstrap.php\n";
        $theirContent = "name: old-plugin\nversion: 1.0\nrequires: 6.5\nstatus: network-active\nentry: bootstrap.php\n";
        $base = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('old-plugin.php', $baseContent)]))]);
        $ours = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('new-plugin.php', $ourContent)]))]);
        $theirs = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('new-plugin.php', $theirContent)]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
        $mergedPlugin = $read($contentTree->entryNamed('new-plugin.php')?->oid ?? '');

        $t->true($result->isClean());
        $t->same(['new-plugin.php'], $names($contentTree));
        $t->same("name: new-plugin\nversion: 1.0\nrequires: 6.5\nstatus: network-active\nentry: bootstrap.php\n", $mergedPlugin->body);
        $t->same([], $result->indexEntries());
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'recursive tree merge conflicts same target similar rename edits at renamed path' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $baseContent = "name: old-plugin\nversion: 1.0\nrequires: 6.5\nstatus: active\nentry: bootstrap.php\n";
        $ourContent = "name: new-plugin\nversion: 1.1\nrequires: 6.5\nstatus: active\nentry: bootstrap.php\n";
        $theirContent = "name: new-plugin\nversion: 1.2\nrequires: 6.5\nstatus: active\nentry: bootstrap.php\n";
        $base = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('old-plugin.php', $baseContent)]))]);
        $ours = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('new-plugin.php', $ourContent)]))]);
        $theirs = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('new-plugin.php', $theirContent)]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write, BlobMerge::STYLE_DIFF3);
        $contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
        $mergedPlugin = $read($contentTree->entryNamed('new-plugin.php')?->oid ?? '');

        $t->same(false, $result->isClean());
        $t->same(['new-plugin.php'], $names($contentTree));
        $t->same('content-conflict', $result->conflicts[0]->reason);
        $t->same('wp-content/new-plugin.php', $result->conflicts[0]->path);
        $t->contains('<<<<<<< ours/wp-content/new-plugin.php', $mergedPlugin->body);
        $t->contains('||||||| base/wp-content/new-plugin.php', $mergedPlugin->body);
        $t->contains('>>>>>>> theirs/wp-content/new-plugin.php', $mergedPlugin->body);
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'path' => 'wp-content/new-plugin.php'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'path' => 'wp-content/new-plugin.php'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'path' => 'wp-content/new-plugin.php'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => ['stage' => $entry->stage, 'path' => $entry->path],
            $result->indexEntries(),
        ));
        $t->same(['wp-content/new-plugin.php'], array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read)));
    },
    'recursive tree merge applies directory rename to clean modified old directory' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $basePlugin = "Plugin: Acme\nVersion: 1.0\nRequires: 6.5\nStatus: active\n";
        $ourPlugin = "Plugin: Acme Pro\nVersion: 1.0\nRequires: 6.5\nStatus: active\n";
        $baseReadme = "Acme plugin\nStable tag: 1.0\n";
        $theirReadme = "Acme plugin\nStable tag: 1.1\n";
        $base = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme', new Tree([
            $blobEntry('acme.php', $basePlugin),
            $blobEntry('readme.txt', $baseReadme),
        ]))]))]))]);
        $ours = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme-pro', new Tree([
            $blobEntry('acme.php', $ourPlugin),
            $blobEntry('readme.txt', $baseReadme),
        ]))]))]))]);
        $theirs = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme', new Tree([
            $blobEntry('acme.php', $basePlugin),
            $blobEntry('readme.txt', $theirReadme),
        ]))]))]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
        $pluginsTree = Tree::fromObject($read($contentTree->entryNamed('plugins', true)?->oid ?? ''));
        $pluginTree = Tree::fromObject($read($pluginsTree->entryNamed('acme-pro', true)?->oid ?? ''));

        $t->true($result->isClean());
        $t->same(['acme-pro'], $names($pluginsTree));
        $t->same($ourPlugin, $read($pluginTree->entryNamed('acme.php')?->oid ?? '')->body);
        $t->same($theirReadme, $read($pluginTree->entryNamed('readme.txt')?->oid ?? '')->body);
        $t->same([], $result->indexEntries());
    },
    'recursive tree merge detects directory rename when plugin entry file is renamed' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $basePlugin = "Plugin: Acme\nVersion: 1.0\nRequires: 6.5\nStatus: active\n";
        $ourPlugin = "Plugin: Acme Pro\nVersion: 1.0\nRequires: 6.5\nStatus: active\n";
        $baseReadme = "Acme plugin\nStable tag: 1.0\n";
        $theirReadme = "Acme plugin\nStable tag: 1.1\n";
        $base = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme', new Tree([
            $blobEntry('acme.php', $basePlugin),
            $blobEntry('readme.txt', $baseReadme),
        ]))]))]))]);
        $ours = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme-pro', new Tree([
            $blobEntry('acme-pro.php', $ourPlugin),
            $blobEntry('readme.txt', $baseReadme),
        ]))]))]))]);
        $theirs = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme', new Tree([
            $blobEntry('acme.php', $basePlugin),
            $blobEntry('readme.txt', $theirReadme),
        ]))]))]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
        $pluginsTree = Tree::fromObject($read($contentTree->entryNamed('plugins', true)?->oid ?? ''));
        $pluginTree = Tree::fromObject($read($pluginsTree->entryNamed('acme-pro', true)?->oid ?? ''));

        $t->true($result->isClean());
        $t->same(['acme-pro'], $names($pluginsTree));
        $t->same(['acme-pro.php', 'readme.txt'], $names($pluginTree));
        $t->same($ourPlugin, $read($pluginTree->entryNamed('acme-pro.php')?->oid ?? '')->body);
        $t->same($theirReadme, $read($pluginTree->entryNamed('readme.txt')?->oid ?? '')->body);
        $t->same([], $result->indexEntries());
    },
    'recursive tree merge chooses strict best directory rename candidate' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $basePlugin = "Plugin: Acme\nVersion: 1.0\nRequires: 6.5\nStatus: active\nEntry: acme.php\n";
        $ourPlugin = "Plugin: Acme Pro\nVersion: 1.1\nRequires: 6.5\nStatus: active\nEntry: acme-pro.php\n";
        $litePlugin = "Plugin: Acme Lite\nVersion: 0.1\nRequires: 6.5\nStatus: inactive\nEntry: acme-lite.php\n";
        $baseReadme = "Acme plugin\nStable tag: 1.0\n";
        $theirReadme = "Acme plugin\nStable tag: 1.2\n";
        $api = "<?php\nreturn 'api';\n";
        $admin = "<?php\nreturn 'admin';\n";
        $style = ".acme { color: #135e96; }\n";
        $basePluginTree = new Tree([
            $blobEntry('acme.php', $basePlugin),
            $blobEntry('readme.txt', $baseReadme),
            $treeEntry('assets', new Tree([$blobEntry('style.css', $style)])),
            $treeEntry('includes', new Tree([
                $blobEntry('admin.php', $admin),
                $blobEntry('api.php', $api),
            ])),
        ]);
        $ours = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([
            $treeEntry('acme-lite', new Tree([
                $blobEntry('acme-lite.php', $litePlugin),
                $blobEntry('readme.txt', $baseReadme),
                $treeEntry('assets', new Tree([$blobEntry('style.css', $style)])),
                $treeEntry('includes', new Tree([$blobEntry('api.php', $api)])),
            ])),
            $treeEntry('acme-pro', new Tree([
                $blobEntry('acme-pro.php', $ourPlugin),
                $blobEntry('readme.txt', $baseReadme),
                $treeEntry('assets', new Tree([$blobEntry('style.css', $style)])),
                $treeEntry('includes', new Tree([
                    $blobEntry('admin.php', $admin),
                    $blobEntry('api.php', $api),
                ])),
            ])),
        ]))]))]);
        $base = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme', $basePluginTree)]))]))]);
        $theirs = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme', new Tree([
            $blobEntry('acme.php', $basePlugin),
            $blobEntry('readme.txt', $theirReadme),
            $treeEntry('assets', new Tree([$blobEntry('style.css', $style)])),
            $treeEntry('includes', new Tree([
                $blobEntry('admin.php', $admin),
                $blobEntry('api.php', $api),
            ])),
        ]))]))]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
        $pluginsTree = Tree::fromObject($read($contentTree->entryNamed('plugins', true)?->oid ?? ''));
        $proTree = Tree::fromObject($read($pluginsTree->entryNamed('acme-pro', true)?->oid ?? ''));
        $liteTree = Tree::fromObject($read($pluginsTree->entryNamed('acme-lite', true)?->oid ?? ''));

        $t->true($result->isClean());
        $t->same(['acme-lite', 'acme-pro'], $names($pluginsTree));
        $t->same(['acme-pro.php', 'assets', 'includes', 'readme.txt'], $names($proTree));
        $t->same($ourPlugin, $read($proTree->entryNamed('acme-pro.php')?->oid ?? '')->body);
        $t->same($theirReadme, $read($proTree->entryNamed('readme.txt')?->oid ?? '')->body);
        $t->same($litePlugin, $read($liteTree->entryNamed('acme-lite.php')?->oid ?? '')->body);
        $t->same([], $result->indexEntries());
    },
    'recursive tree merge reports directory rename target collisions' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $basePlugin = "Plugin: Acme\nVersion: 1.0\nRequires: 6.5\nStatus: active\nEntry: acme.php\n";
        $ourPlugin = "Plugin: Acme Pro\nVersion: 1.1\nRequires: 6.5\nStatus: active\nEntry: acme-pro.php\n";
        $targetPlugin = "Plugin: Different Acme Pro\nVersion: 2.0\nRequires: 6.5\nStatus: active\n";
        $baseReadme = "Acme plugin\nStable tag: 1.0\n";
        $theirReadme = "Acme plugin\nStable tag: 1.2\n";
        $api = "<?php\nreturn 'api';\n";
        $admin = "<?php\nreturn 'admin';\n";
        $style = ".acme { color: #135e96; }\n";
        $basePluginTree = new Tree([
            $blobEntry('acme.php', $basePlugin),
            $blobEntry('readme.txt', $baseReadme),
            $treeEntry('assets', new Tree([$blobEntry('style.css', $style)])),
            $treeEntry('includes', new Tree([
                $blobEntry('admin.php', $admin),
                $blobEntry('api.php', $api),
            ])),
        ]);
        $base = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme', $basePluginTree)]))]))]);
        $ours = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme-pro', new Tree([
            $blobEntry('acme-pro.php', $ourPlugin),
            $blobEntry('readme.txt', $baseReadme),
            $treeEntry('assets', new Tree([$blobEntry('style.css', $style)])),
            $treeEntry('includes', new Tree([
                $blobEntry('admin.php', $admin),
                $blobEntry('api.php', $api),
            ])),
        ]))]))]))]);
        $theirs = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([
            $treeEntry('acme', new Tree([
                $blobEntry('acme.php', $basePlugin),
                $blobEntry('readme.txt', $theirReadme),
                $treeEntry('assets', new Tree([$blobEntry('style.css', $style)])),
                $treeEntry('includes', new Tree([
                    $blobEntry('admin.php', $admin),
                    $blobEntry('api.php', $api),
                ])),
            ])),
            $treeEntry('acme-pro', new Tree([$blobEntry('acme.php', $targetPlugin)])),
        ]))]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
        $pluginsTree = Tree::fromObject($read($contentTree->entryNamed('plugins', true)?->oid ?? ''));

        $t->same(false, $result->isClean());
        $t->same(['acme'], $names($pluginsTree));
        $t->same([
            ['path' => 'wp-content/plugins/acme', 'reason' => 'rename-modify'],
            ['path' => 'wp-content/plugins/acme-pro', 'reason' => 'rename-target-add'],
        ], array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'wp-content/plugins/acme'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'wp-content/plugins/acme'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'wp-content/plugins/acme'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'wp-content/plugins/acme-pro'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'wp-content/plugins/acme-pro'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => ['stage' => $entry->stage, 'side' => $entry->side(), 'path' => $entry->path],
            $result->indexEntries(),
        ));
    },
    'maps upstream gix-merge tree-baseline non-tree-to-tree fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([$blobEntry('a', "original\n1\n2\n3\n4\n5\n")]);
        $ours = new Tree([$blobEntry('a', "1\n2\n3\n4\n5\n6\n")]);
        $theirs = new Tree([
            $treeEntry('a', new Tree([
                $blobEntry('d', ''),
                $blobEntry('e', ''),
                $treeEntry('sub', new Tree([
                    $blobEntry('b', ''),
                    $blobEntry('c', ''),
                ])),
            ])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $aTree = Tree::fromObject($read($result->tree->entryNamed('a', true)?->oid ?? ''));
        $subTree = Tree::fromObject($read($aTree->entryNamed('sub', true)?->oid ?? ''));
        $relocated = $result->tree->entryNamed('a~A');

        $t->same(false, $result->isClean());
        $t->same(['a', 'a~A'], $names($result->tree));
        $t->same(['d', 'e', 'sub'], $names($aTree));
        $t->same(['b', 'c'], $names($subTree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($relocated?->oid ?? '')->body);
        $t->same([
            ['path' => 'a~A', 'reason' => 'delete-modify', 'base' => 'a~A', 'ours' => 'a~A', 'theirs' => null],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'a~A', 'body' => "original\n1\n2\n3\n4\n5\n"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'a~A', 'body' => "1\n2\n3\n4\n5\n6\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same([
            ['path' => 'a~A', 'stage' => MergeIndexEntry::STAGE_ANCESTOR, 'body' => "original\n1\n2\n3\n4\n5\n"],
            ['path' => 'a~A', 'stage' => MergeIndexEntry::STAGE_OURS, 'body' => "1\n2\n3\n4\n5\n6\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'path' => $entry->path,
                'stage' => $entry->stage,
                'body' => $read($entry->oid)->body,
            ],
            MergeIndexFile::entriesForResult($result, $read),
        ));
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'maps upstream gix-merge tree-baseline non-tree-to-tree-with-rename fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([$blobEntry('a', "original\n1\n2\n3\n4\n5\n")]);
        $ours = new Tree([$blobEntry('a', "1\n2\n3\n4\n5\n6\n")]);
        $theirs = new Tree([
            $treeEntry('a', new Tree([
                $blobEntry('d', ''),
                $blobEntry('e', ''),
                $treeEntry('sub', new Tree([
                    $blobEntry('b', "original\n1\n2\n3\n4\n5\n"),
                    $blobEntry('c', ''),
                ])),
            ])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $aTree = Tree::fromObject($read($result->tree->entryNamed('a', true)?->oid ?? ''));
        $subTree = Tree::fromObject($read($aTree->entryNamed('sub', true)?->oid ?? ''));

        $t->true($result->isClean());
        $t->same(['a'], $names($result->tree));
        $t->same(['d', 'e', 'sub'], $names($aTree));
        $t->same(['b', 'c'], $names($subTree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($subTree->entryNamed('b')?->oid ?? '')->body);
        $t->same([], $result->conflicts);
        $t->same([], $result->indexEntries());
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'maps upstream gix-merge tree-baseline tree-to-non-tree fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([
            $treeEntry('a', new Tree([
                $blobEntry('d', ''),
                $blobEntry('e', ''),
                $treeEntry('sub', new Tree([
                    $blobEntry('b', "original\n1\n2\n3\n4\n5\n"),
                    $blobEntry('c', ''),
                ])),
            ])),
        ]);
        $ours = new Tree([
            $treeEntry('a', new Tree([
                $blobEntry('d', ''),
                $blobEntry('e', ''),
                $treeEntry('sub', new Tree([
                    $blobEntry('b', "1\n2\n3\n4\n5\n6\n"),
                    $blobEntry('c', ''),
                ])),
            ])),
        ]);
        $theirs = new Tree([$blobEntry('a', "new file\n")]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $aTree = Tree::fromObject($read($result->tree->entryNamed('a', true)?->oid ?? ''));
        $subTree = Tree::fromObject($read($aTree->entryNamed('sub', true)?->oid ?? ''));
        $relocated = $result->tree->entryNamed('a~B');

        $t->same(false, $result->isClean());
        $t->same(['a', 'a~B'], $names($result->tree));
        $t->same(['sub'], $names($aTree));
        $t->same(['b'], $names($subTree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($subTree->entryNamed('b')?->oid ?? '')->body);
        $t->same("new file\n", $read($relocated?->oid ?? '')->body);
        $t->same([
            ['path' => 'a/sub/b', 'reason' => 'delete-modify', 'base' => 'b', 'ours' => 'b', 'theirs' => null],
            ['path' => 'a~B', 'reason' => 'directory-file', 'base' => null, 'ours' => null, 'theirs' => 'a~B'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'a/sub/b', 'body' => "original\n1\n2\n3\n4\n5\n"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'a/sub/b', 'body' => "1\n2\n3\n4\n5\n6\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'a~B', 'body' => "new file\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same([
            ['path' => 'a/sub/b', 'stage' => MergeIndexEntry::STAGE_ANCESTOR, 'body' => "original\n1\n2\n3\n4\n5\n"],
            ['path' => 'a/sub/b', 'stage' => MergeIndexEntry::STAGE_OURS, 'body' => "1\n2\n3\n4\n5\n6\n"],
            ['path' => 'a~B', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'body' => "new file\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'path' => $entry->path,
                'stage' => $entry->stage,
                'body' => $read($entry->oid)->body,
            ],
            MergeIndexFile::entriesForResult($result, $read),
        ));
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'maps upstream gix-merge tree-baseline tree-to-non-tree-with-rename fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([
            $treeEntry('a', new Tree([
                $blobEntry('d', ''),
                $blobEntry('e', ''),
                $treeEntry('sub', new Tree([
                    $blobEntry('b', "original\n1\n2\n3\n4\n5\n"),
                    $blobEntry('c', ''),
                ])),
            ])),
        ]);
        $ours = new Tree([
            $treeEntry('a', new Tree([
                $blobEntry('d', ''),
                $blobEntry('e', ''),
                $treeEntry('sub', new Tree([
                    $blobEntry('b', "1\n2\n3\n4\n5\n6\n"),
                    $blobEntry('c', ''),
                ])),
            ])),
        ]);
        $theirs = new Tree([$blobEntry('a', '')]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $aTree = Tree::fromObject($read($result->tree->entryNamed('a', true)?->oid ?? ''));
        $subTree = Tree::fromObject($read($aTree->entryNamed('sub', true)?->oid ?? ''));
        $relocated = $result->tree->entryNamed('a~B');

        $t->same(false, $result->isClean());
        $t->same(['a', 'a~B'], $names($result->tree));
        $t->same(['sub'], $names($aTree));
        $t->same(['b'], $names($subTree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($subTree->entryNamed('b')?->oid ?? '')->body);
        $t->same('', $read($relocated?->oid ?? '')->body);
        $t->same([
            ['path' => 'a/sub/b', 'reason' => 'delete-modify', 'base' => 'b', 'ours' => 'b', 'theirs' => null],
            ['path' => 'a~B', 'reason' => 'directory-file', 'base' => null, 'ours' => null, 'theirs' => 'a~B'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'a/sub/b', 'body' => "original\n1\n2\n3\n4\n5\n"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'a/sub/b', 'body' => "1\n2\n3\n4\n5\n6\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'a~B', 'body' => ''],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same([
            ['path' => 'a/sub/b', 'stage' => MergeIndexEntry::STAGE_ANCESTOR, 'body' => "original\n1\n2\n3\n4\n5\n"],
            ['path' => 'a/sub/b', 'stage' => MergeIndexEntry::STAGE_OURS, 'body' => "1\n2\n3\n4\n5\n6\n"],
            ['path' => 'a~B', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'body' => ''],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'path' => $entry->path,
                'stage' => $entry->stage,
                'body' => $read($entry->oid)->body,
            ],
            MergeIndexFile::entriesForResult($result, $read),
        ));
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'maps upstream gix-merge tree-baseline rename-delete fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([
            $blobEntry('foo', "1\n2\n3\n4\n5\n"),
            $treeEntry('olddir', new Tree([
                $blobEntry('a', "a\n"),
                $blobEntry('b', "b\n"),
                $blobEntry('c', "c\n"),
            ])),
        ]);
        $ours = new Tree([
            $blobEntry('foo', "1\n2\n3\n4\n5\n6\n"),
            $treeEntry('newdir', new Tree([
                $blobEntry('a', "a\n"),
                $blobEntry('b', "b\n"),
                $blobEntry('c', "c\n"),
            ])),
        ]);
        $theirs = new Tree([
            $treeEntry('olddir', new Tree([
                $blobEntry('a', "a\n"),
                $blobEntry('bar', "1\n2\n3\n4\n5 six\n"),
                $blobEntry('b', "b\n"),
                $blobEntry('c', "c\n"),
            ])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $newdir = Tree::fromObject($read($result->tree->entryNamed('newdir', true)?->oid ?? ''));
        $mergedBar = $read($newdir->entryNamed('bar')?->oid ?? '');

        $t->same(false, $result->isClean());
        $t->same(['newdir'], $names($result->tree));
        $t->same(['a', 'b', 'bar', 'c'], $names($newdir));
        $t->same(null, $result->tree->entryNamed('foo'));
        $t->contains('<<<<<<< ours/newdir/bar', $mergedBar->body);
        $t->contains("5\n6\n", $mergedBar->body);
        $t->contains("5 six\n", $mergedBar->body);
        $t->same([
            ['path' => 'newdir/bar', 'reason' => 'content-conflict', 'base' => 'foo', 'ours' => 'foo', 'theirs' => 'bar'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'newdir/bar', 'body' => "1\n2\n3\n4\n5\n"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'newdir/bar', 'body' => "1\n2\n3\n4\n5\n6\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'newdir/bar', 'body' => "1\n2\n3\n4\n5 six\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same(['newdir/bar'], array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read)));
    },
    'maps upstream gix-merge tree-baseline rename-add fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $base = new Tree([$blobEntry('foo', "original\n1\n2\n3\n4\n5\n")]);
        $ours = new Tree([
            $blobEntry('foo', "1\n2\n3\n4\n5\n"),
            $blobEntry('bar', "different file\n"),
        ]);
        $theirs = new Tree([$blobEntry('bar', "original\n1\n2\n3\n4\n5\n6\n")]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);

        $t->same(false, $result->isClean());
        $t->same(['foo'], $names($result->tree));
        $t->same([
            ['path' => 'foo', 'reason' => 'rename-modify'],
            ['path' => 'bar', 'reason' => 'rename-target-add'],
        ], array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'bar'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'bar'],
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'foo'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'foo'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'foo'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => ['stage' => $entry->stage, 'side' => $entry->side(), 'path' => $entry->path],
            $result->indexEntries(),
        ));
    },
    'maps upstream gix-merge tree-baseline rename-add-exe-bit-conflict fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $base = new Tree([
            $blobEntry('a', '', '100755'),
            $blobEntry('b', ''),
        ]);
        $ours = new Tree([
            $blobEntry('a', ''),
            $blobEntry('b', ''),
        ]);
        $theirs = new Tree([
            $blobEntry('a', '', '100755'),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $a = $result->tree->entryNamed('a');

        $t->true($result->isClean());
        $t->same(['a'], $names($result->tree));
        $t->same('blob', $a?->kind());
        $t->same('', $read($a?->oid ?? '')->body);
        $t->same([], $result->indexEntries());
    },
    'maps upstream gix-merge tree-baseline remove-executable-mode fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $base = new Tree([$blobEntry('w', '', '100755')]);
        $ours = new Tree([$blobEntry('w', '', '100644')]);
        $theirs = new Tree([$blobEntry('w', "1\n2\n3\n4\n5\n", '100755')]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $w = $result->tree->entryNamed('w');

        $t->true($result->isClean());
        $t->same(['w'], $names($result->tree));
        $t->same('100644', $w?->mode);
        $t->same('blob', $w?->kind());
        $t->same("1\n2\n3\n4\n5\n", $read($w?->oid ?? '')->body);
        $t->same([], $result->conflicts);
        $t->same([], $result->indexEntries());
    },
    'maps upstream gix-merge tree-baseline added-file-changed-content-and-mode fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([$treeEntry('a', new Tree([$blobEntry('x.f', "original\n1\n2\n3\n4\n5\n")]))]);
        $ours = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', "original\n1\n2\n3\n4\n5\n")])),
            $blobEntry('new', "1\n2\n3\n4\n5\n"),
        ]);
        $theirs = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', "original\n1\n2\n3\n4\n5\n")])),
            $blobEntry('new', "original\n1\n2\n3\n4\n5\n6\n", '100755'),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $new = $result->tree->entryNamed('new');
        $newBody = $read($new?->oid ?? '')->body;

        $t->same(false, $result->isClean());
        $t->same(['a', 'new'], $names($result->tree));
        $t->same('100755', $new?->mode);
        $t->contains('<<<<<<< ours/new', $newBody);
        $t->contains("1\n2\n3\n4\n5\n", $newBody);
        $t->contains("original\n1\n2\n3\n4\n5\n6\n", $newBody);
        $t->same([
            ['path' => 'new', 'reason' => 'add-add', 'base' => null, 'oursMode' => '100644', 'theirsMode' => '100755'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'oursMode' => $conflict->ours?->mode,
                'theirsMode' => $conflict->theirs?->mode,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'new', 'mode' => '100644', 'body' => "1\n2\n3\n4\n5\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'new', 'mode' => '100755', 'body' => "original\n1\n2\n3\n4\n5\n6\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'mode' => $entry->mode,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same(['new'], array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read)));
    },
    'maps upstream gix-merge tree-baseline renamed-symlink-with-conflict fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $linkEntry = static fn (string $filename, string $target): TreeEntry => new TreeEntry('120000', $filename, $write(new GitObject('blob', $target)));
        $base = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', "original\n1\n2\n3\n4\n5\n")])),
            $linkEntry('link', 'a/x.f'),
        ]);
        $ours = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', "1\n2\n3\n4\n5\n")])),
            $linkEntry('link-renamed', 'a/x.f'),
        ]);
        $theirs = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', "original\n1\n2\n3\n4\n5\n6\n")])),
            $linkEntry('link-different', 'a/x.f'),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $aTree = Tree::fromObject($read($result->tree->entryNamed('a', true)?->oid ?? ''));

        $t->same(false, $result->isClean());
        $t->same(['a', 'link-different', 'link-renamed'], $names($result->tree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($aTree->entryNamed('x.f')?->oid ?? '')->body);
        $t->same('a/x.f', $read($result->tree->entryNamed('link-renamed')?->oid ?? '')->body);
        $t->same('a/x.f', $read($result->tree->entryNamed('link-different')?->oid ?? '')->body);
        $t->same([
            ['path' => 'link', 'reason' => 'rename-rename', 'base' => 'link', 'ours' => null, 'theirs' => null],
            ['path' => 'link-renamed', 'reason' => 'rename-rename', 'base' => null, 'ours' => 'link-renamed', 'theirs' => null],
            ['path' => 'link-different', 'reason' => 'rename-rename', 'base' => null, 'ours' => null, 'theirs' => 'link-different'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'link', 'kind' => 'link', 'body' => 'a/x.f'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'link-different', 'kind' => 'link', 'body' => 'a/x.f'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'link-renamed', 'kind' => 'link', 'body' => 'a/x.f'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'kind' => (new TreeEntry($entry->mode, basename($entry->path), $entry->oid))->kind(),
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same([], $result->worktreeConflictFiles($read));

        $ancestorResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
        $ancestorATree = Tree::fromObject($read($ancestorResolved->tree->entryNamed('a', true)?->oid ?? ''));

        $t->true($ancestorResolved->isClean());
        $t->same(['a', 'link'], $names($ancestorResolved->tree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($ancestorATree->entryNamed('x.f')?->oid ?? '')->body);
        $t->same('a/x.f', $read($ancestorResolved->tree->entryNamed('link')?->oid ?? '')->body);
        $t->same([], $ancestorResolved->indexEntries());

        $oursResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
        $oursATree = Tree::fromObject($read($oursResolved->tree->entryNamed('a', true)?->oid ?? ''));

        $t->true($oursResolved->isClean());
        $t->same(['a', 'link-renamed'], $names($oursResolved->tree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($oursATree->entryNamed('x.f')?->oid ?? '')->body);
        $t->same('a/x.f', $read($oursResolved->tree->entryNamed('link-renamed')?->oid ?? '')->body);
        $t->same([], $oursResolved->indexEntries());

        $theirsResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_THEIRS);
        $theirsATree = Tree::fromObject($read($theirsResolved->tree->entryNamed('a', true)?->oid ?? ''));

        $t->true($theirsResolved->isClean());
        $t->same(['a', 'link-different'], $names($theirsResolved->tree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($theirsATree->entryNamed('x.f')?->oid ?? '')->body);
        $t->same('a/x.f', $read($theirsResolved->tree->entryNamed('link-different')?->oid ?? '')->body);
        $t->same([], $theirsResolved->indexEntries());
    },
    'maps upstream gix-merge tree-baseline rename-add-symlink fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $linkEntry = static fn (string $filename, string $target): TreeEntry => new TreeEntry('120000', $filename, $write(new GitObject('blob', $target)));
        $base = new Tree([$blobEntry('foo', "original\n1\n2\n3\n4\n5\n")]);
        $ours = new Tree([
            $blobEntry('foo', "1\n2\n3\n4\n5\n"),
            $linkEntry('bar', 'foo'),
        ]);
        $theirs = new Tree([$blobEntry('bar', "original\n1\n2\n3\n4\n5\n6\n")]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);

        $t->same(false, $result->isClean());
        $t->same(['foo'], $names($result->tree));
        $t->same([
            ['path' => 'foo', 'reason' => 'rename-modify'],
            ['path' => 'bar', 'reason' => 'rename-target-add'],
        ], array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'bar', 'kind' => 'link'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'bar', 'kind' => 'blob'],
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'foo', 'kind' => 'blob'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'foo', 'kind' => 'blob'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'foo', 'kind' => 'blob'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'kind' => (new TreeEntry($entry->mode, basename($entry->path), $entry->oid))->kind(),
            ],
            $result->indexEntries(),
        ));
    },
    'maps upstream gix-merge tree-baseline rename-add-symlink resolve-tree fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $linkEntry = static fn (string $filename, string $target): TreeEntry => new TreeEntry('120000', $filename, $write(new GitObject('blob', $target)));
        $base = new Tree([$blobEntry('foo', "original\n1\n2\n3\n4\n5\n")]);
        $ours = new Tree([
            $blobEntry('foo', "1\n2\n3\n4\n5\n"),
            $linkEntry('bar', 'foo'),
        ]);
        $theirs = new Tree([$blobEntry('bar', "original\n1\n2\n3\n4\n5\n6\n")]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $ancestorResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
        $oursResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
        $theirsResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_THEIRS);
        $oursBar = $oursResolved->tree->entryNamed('bar');
        $theirsBar = $theirsResolved->tree->entryNamed('bar');

        $t->true($ancestorResolved->isClean());
        $t->same([], $names($ancestorResolved->tree));
        $t->same([], $ancestorResolved->indexEntries());

        $t->true($oursResolved->isClean());
        $t->same(['bar'], $names($oursResolved->tree));
        $t->same('link', $oursBar?->kind());
        $t->same('foo', $read($oursBar?->oid ?? '')->body);
        $t->same([], $oursResolved->indexEntries());

        $t->true($theirsResolved->isClean());
        $t->same(['bar'], $names($theirsResolved->tree));
        $t->same('blob', $theirsBar?->kind());
        $t->same("original\n1\n2\n3\n4\n5\n6\n", $read($theirsBar?->oid ?? '')->body);
        $t->same([], $theirsResolved->indexEntries());
    },
    'maps upstream gix-merge tree-baseline rename-add-same-symlink fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $linkEntry = static fn (string $filename, string $target): TreeEntry => new TreeEntry('120000', $filename, $write(new GitObject('blob', $target)));
        $base = new Tree([
            $blobEntry('target', ''),
            $linkEntry('link', 'target'),
        ]);
        $ours = new Tree([
            $linkEntry('link-new', 'target'),
            $blobEntry('target', ''),
        ]);
        $theirs = new Tree([
            $linkEntry('link', 'target'),
            $linkEntry('link-new', 'target'),
            $blobEntry('target', ''),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $linkNew = $result->tree->entryNamed('link-new');

        $t->true($result->isClean());
        $t->same(['link-new', 'target'], $names($result->tree));
        $t->same(null, $result->tree->entryNamed('link'));
        $t->same('link', $linkNew?->kind());
        $t->same('target', $read($linkNew?->oid ?? '')->body);
        $t->same([], $result->conflicts);
        $t->same([], $result->indexEntries());
    },
    'maps upstream gix-merge tree-baseline symlink-modification fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $linkEntry = static fn (string $filename, string $target): TreeEntry => new TreeEntry('120000', $filename, $write(new GitObject('blob', $target)));
        $base = new Tree([
            $blobEntry('a', ''),
            $blobEntry('b', ''),
            $linkEntry('link', 'o'),
            $blobEntry('o', ''),
        ]);
        $ours = new Tree([
            $blobEntry('a', ''),
            $blobEntry('b', ''),
            $linkEntry('link', 'a'),
            $blobEntry('o', ''),
        ]);
        $theirs = new Tree([
            $blobEntry('a', ''),
            $blobEntry('b', ''),
            $linkEntry('link', 'b'),
            $blobEntry('o', ''),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $link = $result->tree->entryNamed('link');

        $t->same(false, $result->isClean());
        $t->same(['a', 'b', 'link', 'o'], $names($result->tree));
        $t->same('link', $link?->kind());
        $t->same('a', $read($link?->oid ?? '')->body);
        $t->same([
            ['path' => 'link', 'reason' => 'content-conflict', 'base' => 'link', 'ours' => 'link', 'theirs' => 'link'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'link', 'kind' => 'link', 'body' => 'o'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'link', 'kind' => 'link', 'body' => 'a'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'link', 'kind' => 'link', 'body' => 'b'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'kind' => (new TreeEntry($entry->mode, basename($entry->path), $entry->oid))->kind(),
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'maps upstream gix-merge tree-baseline symlink-addition fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $linkEntry = static fn (string $filename, string $target): TreeEntry => new TreeEntry('120000', $filename, $write(new GitObject('blob', $target)));
        $base = new Tree([
            $blobEntry('a', ''),
            $blobEntry('b', ''),
        ]);
        $ours = new Tree([
            $blobEntry('a', ''),
            $blobEntry('b', ''),
            $linkEntry('link', 'a'),
        ]);
        $theirs = new Tree([
            $blobEntry('a', ''),
            $blobEntry('b', ''),
            $linkEntry('link', 'b'),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $link = $result->tree->entryNamed('link');

        $t->same(false, $result->isClean());
        $t->same(['a', 'b', 'link'], $names($result->tree));
        $t->same('link', $link?->kind());
        $t->same('a', $read($link?->oid ?? '')->body);
        $t->same([
            ['path' => 'link', 'reason' => 'add-add', 'base' => null, 'ours' => 'link', 'theirs' => 'link'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'link', 'kind' => 'link', 'body' => 'a'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'link', 'kind' => 'link', 'body' => 'b'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'kind' => (new TreeEntry($entry->mode, basename($entry->path), $entry->oid))->kind(),
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'maps upstream gix-merge tree-baseline type-change-to-symlink fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $linkEntry = static fn (string $filename, string $target): TreeEntry => new TreeEntry('120000', $filename, $write(new GitObject('blob', $target)));
        $base = new Tree([
            $blobEntry('a', ''),
            $blobEntry('b', ''),
            $blobEntry('link', ''),
        ]);
        $ours = new Tree([
            $blobEntry('a', ''),
            $blobEntry('b', ''),
            $linkEntry('link', 'a'),
        ]);
        $theirs = new Tree([
            $blobEntry('a', ''),
            $blobEntry('b', ''),
            $linkEntry('link', 'b'),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $link = $result->tree->entryNamed('link');

        $t->same(false, $result->isClean());
        $t->same(['a', 'b', 'link'], $names($result->tree));
        $t->same('link', $link?->kind());
        $t->same('a', $read($link?->oid ?? '')->body);
        $t->same([
            ['path' => 'link', 'reason' => 'content-conflict', 'baseKind' => 'blob', 'oursKind' => 'link', 'theirsKind' => 'link'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'baseKind' => $conflict->base?->kind(),
                'oursKind' => $conflict->ours?->kind(),
                'theirsKind' => $conflict->theirs?->kind(),
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'link', 'kind' => 'blob', 'body' => ''],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'link', 'kind' => 'link', 'body' => 'a'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'link', 'kind' => 'link', 'body' => 'b'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'kind' => (new TreeEntry($entry->mode, basename($entry->path), $entry->oid))->kind(),
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'maps upstream gix-merge tree-baseline type-change-and-renamed fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $linkEntry = static fn (string $filename, string $target): TreeEntry => new TreeEntry('120000', $filename, $write(new GitObject('blob', $target)));
        $base = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', '')])),
            $linkEntry('link', 'a/x.f'),
        ]);
        $ours = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', '')])),
            $blobEntry('link', "not-link\n"),
        ]);
        $theirs = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', '')])),
            $linkEntry('link-renamed', 'a/x.f'),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $link = $result->tree->entryNamed('link');
        $linkRenamed = $result->tree->entryNamed('link-renamed');

        $t->same(false, $result->isClean());
        $t->same(['a', 'link', 'link-renamed'], $names($result->tree));
        $t->same('blob', $link?->kind());
        $t->same("not-link\n", $read($link?->oid ?? '')->body);
        $t->same('link', $linkRenamed?->kind());
        $t->same('a/x.f', $read($linkRenamed?->oid ?? '')->body);
        $t->same([
            ['path' => 'link-renamed', 'reason' => 'delete-modify', 'base' => 'link', 'ours' => null, 'theirs' => 'link-renamed'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'link-renamed', 'kind' => 'link', 'body' => 'a/x.f'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'link-renamed', 'kind' => 'link', 'body' => 'a/x.f'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'kind' => (new TreeEntry($entry->mode, basename($entry->path), $entry->oid))->kind(),
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'maps upstream gix-merge tree-baseline type-change-and-renamed resolve-tree fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $linkEntry = static fn (string $filename, string $target): TreeEntry => new TreeEntry('120000', $filename, $write(new GitObject('blob', $target)));
        $base = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', '')])),
            $linkEntry('link', 'a/x.f'),
        ]);
        $ours = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', '')])),
            $blobEntry('link', "not-link\n"),
        ]);
        $theirs = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', '')])),
            $linkEntry('link-renamed', 'a/x.f'),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $ancestorResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
        $oursResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
        $theirsResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_THEIRS);
        $reverse = TreeMerge::mergeRecursive($base, $theirs, $ours, $read, $write);
        $reverseOursResolved = $reverse->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);

        $t->true($ancestorResolved->isClean());
        $t->same(['a', 'link'], $names($ancestorResolved->tree));
        $t->same('link', $ancestorResolved->tree->entryNamed('link')?->kind());
        $t->same('a/x.f', $read($ancestorResolved->tree->entryNamed('link')?->oid ?? '')->body);
        $t->same([], $ancestorResolved->indexEntries());

        $t->true($oursResolved->isClean());
        $t->same(['a', 'link'], $names($oursResolved->tree));
        $t->same('blob', $oursResolved->tree->entryNamed('link')?->kind());
        $t->same("not-link\n", $read($oursResolved->tree->entryNamed('link')?->oid ?? '')->body);
        $t->same([], $oursResolved->indexEntries());

        $t->true($theirsResolved->isClean());
        $t->same(['a', 'link-renamed'], $names($theirsResolved->tree));
        $t->same('link', $theirsResolved->tree->entryNamed('link-renamed')?->kind());
        $t->same('a/x.f', $read($theirsResolved->tree->entryNamed('link-renamed')?->oid ?? '')->body);
        $t->same([], $theirsResolved->indexEntries());

        $t->true($reverseOursResolved->isClean());
        $t->same(['a', 'link-renamed'], $names($reverseOursResolved->tree));
        $t->same('link', $reverseOursResolved->tree->entryNamed('link-renamed')?->kind());
        $t->same('a/x.f', $read($reverseOursResolved->tree->entryNamed('link-renamed')?->oid ?? '')->body);
        $t->same([], $reverseOursResolved->indexEntries());
    },
    'maps upstream gix-merge tree-baseline rename-and-modification fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', "original\n1\n2\n3\n4\n5\n")])),
        ]);
        $ours = new Tree([
            $blobEntry('x.f', "original\n1\n2\n3\n4\n5\n"),
        ]);
        $theirs = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', "1\n2\n3\n4\n5\n6\n")])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $x = $result->tree->entryNamed('x.f');

        $t->true($result->isClean());
        $t->same(['x.f'], $names($result->tree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($x?->oid ?? '')->body);
        $t->same([], $result->conflicts);
        $t->same([], $result->indexEntries());
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'maps upstream gix-merge tree-baseline no-merge-base fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $base = new Tree([]);
        $ours = new Tree([$blobEntry('content', "A\n")]);
        $theirs = new Tree([$blobEntry('content', "B\n")]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $content = $read($result->tree->entryNamed('content')?->oid ?? '');

        $t->same(false, $result->isClean());
        $t->same(['content'], $names($result->tree));
        $t->contains('<<<<<<< ours/content', $content->body);
        $t->contains("A\n", $content->body);
        $t->contains("B\n", $content->body);
        $t->same([
            ['path' => 'content', 'reason' => 'add-add', 'base' => null, 'ours' => 'content', 'theirs' => 'content'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'content', 'body' => "A\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'content', 'body' => "B\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same(['content'], array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read)));
    },
    'maps upstream gix-merge tree-baseline multiple-merge-bases fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $mergeBaseAncestor = new Tree([$blobEntry('content', "1\n2\n3\n4\n5\n")]);
        $firstMergeBase = new Tree([$blobEntry('content', "0\n1\n2\n3\n4\n5\n")]);
        $secondMergeBase = new Tree([$blobEntry('content', "1\n2\n3\n4\n5\n6\n")]);
        $ours = new Tree([$blobEntry('content', "0\n1\n2\n3\n4\n5\nA\n")]);
        $theirs = new Tree([$blobEntry('renamed', "0\n2\n3\n4\n5\nsix\n")]);

        $result = TreeMerge::mergeRecursiveWithVirtualBase(
            $mergeBaseAncestor,
            [$firstMergeBase, $secondMergeBase],
            $ours,
            $theirs,
            $read,
            $write,
        );
        $renamed = $read($result->tree->entryNamed('renamed')?->oid ?? '');

        $t->same(false, $result->isClean());
        $t->same(['renamed'], $names($result->tree));
        $t->contains('<<<<<<< ours/renamed', $renamed->body);
        $t->same("0\n2\n3\n4\n5\n<<<<<<< ours/renamed\nA\n=======\nsix\n>>>>>>> theirs/renamed\n", $renamed->body);
        $t->same([
            ['path' => 'renamed', 'reason' => 'content-conflict', 'base' => 'renamed', 'ours' => 'renamed', 'theirs' => 'renamed'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'renamed', 'body' => "0\n1\n2\n3\n4\n5\n6\n"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'renamed', 'body' => "0\n1\n2\n3\n4\n5\nA\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'renamed', 'body' => "0\n2\n3\n4\n5\nsix\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same(['renamed'], array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read)));
    },
    'maps upstream gix-merge tree-baseline change-and-delete fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $baseFile = "original\n1\n2\n3\n4\n5\n";
        $ourFile = "1\n2\n3\n4\n5\n6\n";
        $base = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', $baseFile)])),
            $blobEntry('link', 'a/x.f', '120000'),
        ]);
        $ours = new Tree([
            $treeEntry('a', new Tree([$blobEntry('x.f', $ourFile)])),
            $blobEntry('link', "not-link\n"),
        ]);
        $theirs = new Tree([]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $aTree = Tree::fromObject($read($result->tree->entryNamed('a', true)?->oid ?? ''));
        $link = $result->tree->entryNamed('link');

        $t->same(false, $result->isClean());
        $t->same(['a', 'link'], $names($result->tree));
        $t->same($ourFile, $read($aTree->entryNamed('x.f')?->oid ?? '')->body);
        $t->same('100644', $link?->mode);
        $t->same("not-link\n", $read($link?->oid ?? '')->body);
        $t->same([
            ['path' => 'a', 'reason' => 'delete-modify', 'base' => 'tree', 'ours' => 'tree', 'theirs' => null],
            ['path' => 'link', 'reason' => 'delete-modify', 'base' => 'link', 'ours' => 'blob', 'theirs' => null],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->kind(),
                'ours' => $conflict->ours?->kind(),
                'theirs' => $conflict->theirs?->kind(),
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'a/x.f', 'kind' => 'blob', 'body' => $baseFile],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'a/x.f', 'kind' => 'blob', 'body' => $ourFile],
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'link', 'kind' => 'link', 'body' => 'a/x.f'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'link', 'kind' => 'blob', 'body' => "not-link\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'kind' => (new TreeEntry($entry->mode, basename($entry->path), $entry->oid))->kind(),
                'body' => $read($entry->oid)->body,
            ],
            MergeIndexFile::entriesForResult($result, $read),
        ));
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'maps upstream gix-merge tree-baseline submodule-both-modify fixture shape' => static function (TestRunner $t) use ($entry, $names): void {
        $baseOid = 'e835c0c403c8e494c0ca98f3d25d0b8464c18d38';
        $ourOid = '64466ebdff775ad618d9cc993cf52840e0af528c';
        $theirOid = 'ea6eb701e03c2497915c25a851f3da8f8e362ca0';
        $base = new Tree([$entry('sub', $baseOid, '160000')]);
        $ours = new Tree([$entry('sub', $ourOid, '160000')]);
        $theirs = new Tree([$entry('sub', $theirOid, '160000')]);

        $result = TreeMerge::mergeFlat($base, $ours, $theirs);
        $sub = $result->tree->entryNamed('sub');

        $t->same(false, $result->isClean());
        $t->same(['sub'], $names($result->tree));
        $t->same('commit', $sub?->kind());
        $t->same($baseOid, $sub?->oid);
        $t->same([
            ['path' => 'sub', 'reason' => 'modify-modify', 'base' => $baseOid, 'ours' => $ourOid, 'theirs' => $theirOid],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->oid,
                'ours' => $conflict->ours?->oid,
                'theirs' => $conflict->theirs?->oid,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'sub', 'kind' => 'commit', 'oid' => $baseOid],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'sub', 'kind' => 'commit', 'oid' => $ourOid],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'sub', 'kind' => 'commit', 'oid' => $theirOid],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'kind' => (new TreeEntry($entry->mode, basename($entry->path), $entry->oid))->kind(),
                'oid' => $entry->oid,
            ],
            $result->indexEntries(),
        ));
        $t->same([], $result->worktreeConflictFiles(static fn (string $oid): GitObject => throw new RuntimeException("No object read expected for {$oid}")));
    },
    'maps upstream gix-merge tree-baseline same-rename-different-mode fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([$treeEntry('a', new Tree([
            $blobEntry('w', ''),
            $blobEntry('x.f', "original\n1\n2\n3\n4\n5\n"),
        ]))]);
        $ours = new Tree([$treeEntry('a-renamed', new Tree([
            $blobEntry('w', '', '100755'),
            $blobEntry('x.f', "1\n2\n3\n4\n5\n", '100755'),
        ]))]);
        $theirs = new Tree([$treeEntry('a-renamed', new Tree([
            $blobEntry('w', ''),
            $blobEntry('x.f', "original\n1\n2\n3\n4\n5\n6\n"),
        ]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $renamed = Tree::fromObject($read($result->tree->entryNamed('a-renamed', true)?->oid ?? ''));
        $w = $renamed->entryNamed('w');
        $x = $renamed->entryNamed('x.f');

        $t->same(false, $result->isClean());
        $t->same(['a-renamed'], $names($result->tree));
        $t->same(['w', 'x.f'], $names($renamed));
        $t->same('100755', $w?->mode);
        $t->same('', $read($w?->oid ?? '')->body);
        $t->same('100755', $x?->mode);
        $t->same("1\n2\n3\n4\n5\n6\n", $read($x?->oid ?? '')->body);
        $t->same([
            ['path' => 'a-renamed/w', 'reason' => 'mode-change', 'base' => null, 'ours' => '100755', 'theirs' => '100644'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->mode,
                'ours' => $conflict->ours?->mode,
                'theirs' => $conflict->theirs?->mode,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'a-renamed/w', 'mode' => '100755'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'a-renamed/w', 'mode' => '100644'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'mode' => $entry->mode,
            ],
            $result->indexEntries(),
        ));
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'maps upstream gix-merge tree-baseline both-modify-union-attr fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([
            $blobEntry('.gitattributes', "a/* merge=union\n"),
            $treeEntry('a', new Tree([$blobEntry('x.f', "original\n1\n2\n3\n4\n5\n")])),
        ]);
        $ours = new Tree([
            $blobEntry('.gitattributes', "a/* merge=union\n"),
            $treeEntry('a', new Tree([$blobEntry('x.f', "A\n1\n2\n3\n4\n5\n6\n")])),
        ]);
        $theirs = new Tree([
            $blobEntry('.gitattributes', "a/* merge=union\n"),
            $treeEntry('a', new Tree([$blobEntry('x.f', "B\n1\n2\n3\n4\n5\n7\n")])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write, BlobMerge::STYLE_DIFF3);
        $a = Tree::fromObject($read($result->tree->entryNamed('a', true)?->oid ?? ''));
        $x = $a->entryNamed('x.f');

        $t->same(true, $result->isClean());
        $t->same(['.gitattributes', 'a'], $names($result->tree));
        $t->same("A\nB\n1\n2\n3\n4\n5\n6\n7\n", $read($x?->oid ?? '')->body);
        $t->same([], $result->conflicts);
        $t->same([], $result->indexEntries());
    },
    'maps upstream gix-merge tree-baseline both-modify-binary fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([$treeEntry('a', new Tree([$blobEntry('x.f', "\0 binary")]))]);
        $ours = new Tree([$treeEntry('a', new Tree([$blobEntry('x.f', "\0 A")]))]);
        $theirs = new Tree([$treeEntry('a', new Tree([$blobEntry('x.f', "\0 B")]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $a = Tree::fromObject($read($result->tree->entryNamed('a', true)?->oid ?? ''));
        $x = $a->entryNamed('x.f');

        $t->same(false, $result->isClean());
        $t->same(['a'], $names($result->tree));
        $t->same("\0 A", $read($x?->oid ?? '')->body);
        $t->same([
            ['path' => 'a/x.f', 'reason' => 'content-conflict', 'base' => 'x.f', 'ours' => 'x.f', 'theirs' => 'x.f'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'a/x.f', 'body' => "\0 binary"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'a/x.f', 'body' => "\0 A"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'a/x.f', 'body' => "\0 B"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same([
            ['path' => 'a/x.f', 'body' => "\0 A"],
        ], array_map(
            static fn ($file): array => ['path' => $file->path, 'body' => $file->content],
            $result->worktreeConflictFiles($read),
        ));
    },
    'maps upstream gix-merge tree-baseline both-modify-file-with-binary-attr fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([$treeEntry('a', new Tree([$blobEntry('x.f', "not binary\n")]))]);
        $ours = new Tree([$treeEntry('a', new Tree([$blobEntry('x.f', "A binary\n")]))]);
        $theirs = new Tree([$treeEntry('a', new Tree([$blobEntry('x.f', "B binary\n")]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $a = Tree::fromObject($read($result->tree->entryNamed('a', true)?->oid ?? ''));
        $x = $a->entryNamed('x.f');
        $mergedBody = $read($x?->oid ?? '')->body;

        $t->same(false, $result->isClean());
        $t->same(['a'], $names($result->tree));
        $t->same(['x.f'], $names($a));
        $t->contains('<<<<<<< ours/a/x.f', $mergedBody);
        $t->contains("A binary\n", $mergedBody);
        $t->contains("B binary\n", $mergedBody);
        $t->same([
            ['path' => 'a/x.f', 'reason' => 'content-conflict', 'base' => 'x.f', 'ours' => 'x.f', 'theirs' => 'x.f'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'a/x.f', 'body' => "not binary\n"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'a/x.f', 'body' => "A binary\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'a/x.f', 'body' => "B binary\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same([
            ['path' => 'a/x.f', 'body' => $mergedBody],
        ], array_map(
            static fn ($file): array => ['path' => $file->path, 'body' => $file->content],
            $result->worktreeConflictFiles($read),
        ));
    },
    'maps upstream gix-merge tree-baseline super-1 fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $lines = static fn (array $lines): string => implode("\n", $lines) . "\n";
        $base = new Tree([
            $blobEntry('one', $lines(range(11, 19))),
            $blobEntry('three', $lines(range(31, 39))),
            $blobEntry('five', $lines(range(51, 59))),
        ]);
        $ours = new Tree([
            $blobEntry('two', $lines(range(10, 19))),
            $blobEntry('four', $lines([...range(31, 39), 40])),
            $blobEntry('six', $lines(range(51, 59))),
        ]);
        $theirs = new Tree([
            $blobEntry('six', $lines([...range(11, 19), 20])),
            $blobEntry('two', $lines([...range(31, 39), 'forty'])),
            $blobEntry('four', $lines([...range(51, 59), 60])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $merged = [];
        foreach (['four', 'six', 'two'] as $path) {
            $merged[$path] = $read($result->tree->entryNamed($path)?->oid ?? '')->body;
        }
        $conflicts = array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        );
        usort($conflicts, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));
        $worktreePaths = array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read));
        sort($worktreePaths, SORT_STRING);

        $t->same(false, $result->isClean());
        $t->same(['four', 'six', 'two'], $names($result->tree));
        $t->contains('<<<<<<< ours/four', $merged['four']);
        $t->contains("31\n32\n33\n34\n35\n36\n37\n38\n39\n40\n", $merged['four']);
        $t->contains("51\n52\n53\n54\n55\n56\n57\n58\n59\n60\n", $merged['four']);
        $t->contains('<<<<<<< ours/six', $merged['six']);
        $t->contains("51\n52\n53\n54\n55\n56\n57\n58\n59\n", $merged['six']);
        $t->contains("11\n12\n13\n14\n15\n16\n17\n18\n19\n20\n", $merged['six']);
        $t->contains('<<<<<<< ours/two', $merged['two']);
        $t->contains("10\n11\n12\n13\n14\n15\n16\n17\n18\n19\n", $merged['two']);
        $t->contains("31\n32\n33\n34\n35\n36\n37\n38\n39\nforty\n", $merged['two']);
        $t->same([
            ['path' => 'four', 'reason' => 'content-conflict', 'base' => 'four', 'ours' => 'four', 'theirs' => 'four'],
            ['path' => 'six', 'reason' => 'content-conflict', 'base' => 'six', 'ours' => 'six', 'theirs' => 'six'],
            ['path' => 'two', 'reason' => 'content-conflict', 'base' => 'two', 'ours' => 'two', 'theirs' => 'two'],
        ], $conflicts);
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'four', 'body' => $lines(range(51, 59))],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'four', 'body' => $lines([...range(31, 39), 40])],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'four', 'body' => $lines([...range(51, 59), 60])],
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'six', 'body' => $lines(range(11, 19))],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'six', 'body' => $lines(range(51, 59))],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'six', 'body' => $lines([...range(11, 19), 20])],
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'two', 'body' => $lines(range(31, 39))],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'two', 'body' => $lines(range(10, 19))],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'two', 'body' => $lines([...range(31, 39), 'forty'])],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same(['four', 'six', 'two'], $worktreePaths);
    },
    'maps upstream gix-merge tree-baseline super-2 fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([
            $blobEntry('foo', "1\n2\n3\n4\n5\n"),
            $treeEntry('olddir', new Tree([
                $blobEntry('a', "a\n"),
                $blobEntry('b', "b\n"),
                $blobEntry('c', "c\n"),
            ])),
        ]);
        $ours = new Tree([
            $treeEntry('newdir', new Tree([
                $blobEntry('a', "a\n"),
                $blobEntry('b', "b\n"),
                $treeEntry('bar', new Tree([
                    $blobEntry('file', ''),
                ])),
                $blobEntry('c', "c\n"),
            ])),
        ]);
        $theirs = new Tree([
            $treeEntry('olddir', new Tree([
                $blobEntry('a', "a\n"),
                $blobEntry('bar', "1\n2\n3\n4\n5\n6\n"),
                $blobEntry('b', "b\n"),
                $blobEntry('c', "c\n"),
            ])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $newdir = Tree::fromObject($read($result->tree->entryNamed('newdir', true)?->oid ?? ''));
        $bar = Tree::fromObject($read($newdir->entryNamed('bar', true)?->oid ?? ''));

        $t->same(false, $result->isClean());
        $t->same(['newdir'], $names($result->tree));
        $t->same(['a', 'b', 'bar', 'bar~B', 'c'], $names($newdir));
        $t->same(['file'], $names($bar));
        $t->same('', $read($bar->entryNamed('file')?->oid ?? '')->body);
        $t->same("1\n2\n3\n4\n5\n6\n", $read($newdir->entryNamed('bar~B')?->oid ?? '')->body);
        $t->same([
            ['path' => 'newdir/bar~B', 'reason' => 'directory-file', 'base' => null, 'ours' => null, 'theirs' => 'bar~B'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'newdir/bar~B', 'body' => "1\n2\n3\n4\n5\n6\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same([], $result->worktreeConflictFiles($read));
    },
    'maps upstream gix-merge tree-baseline super-2 resolve-tree fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $changedFoo = "1\n2\n3\n4\n5\n6\n";
        $base = new Tree([
            $blobEntry('foo', "1\n2\n3\n4\n5\n"),
            $treeEntry('olddir', new Tree([
                $blobEntry('a', "a\n"),
                $blobEntry('b', "b\n"),
                $blobEntry('c', "c\n"),
            ])),
        ]);
        $ours = new Tree([
            $treeEntry('newdir', new Tree([
                $blobEntry('a', "a\n"),
                $blobEntry('b', "b\n"),
                $treeEntry('bar', new Tree([
                    $blobEntry('file', ''),
                ])),
                $blobEntry('c', "c\n"),
            ])),
        ]);
        $theirs = new Tree([
            $treeEntry('olddir', new Tree([
                $blobEntry('a', "a\n"),
                $blobEntry('bar', $changedFoo),
                $blobEntry('b', "b\n"),
                $blobEntry('c', "c\n"),
            ])),
        ]);
        $bodyAt = static function (Tree $tree, string $path) use ($read): ?string {
            $entry = $tree->entryNamed($path);

            return $entry === null ? null : $read($entry->oid)->body;
        };
        $newdirIn = static function (Tree $tree) use ($read): Tree {
            return Tree::fromObject($read($tree->entryNamed('newdir', true)?->oid ?? ''));
        };

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $ancestorResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
        $oursResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
        $reverse = TreeMerge::mergeRecursive($base, $theirs, $ours, $read, $write);
        $reverseAncestorResolved = $reverse->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
        $reverseOursResolved = $reverse->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);

        foreach ([$ancestorResolved, $reverseAncestorResolved] as $resolved) {
            $newdir = $newdirIn($resolved->tree);
            $bar = Tree::fromObject($read($newdir->entryNamed('bar', true)?->oid ?? ''));

            $t->true($resolved->isClean());
            $t->same(['foo', 'newdir'], $names($resolved->tree));
            $t->same($changedFoo, $bodyAt($resolved->tree, 'foo'));
            $t->same(['a', 'b', 'bar', 'c'], $names($newdir));
            $t->same(['file'], $names($bar));
            $t->same([], $resolved->indexEntries());
        }

        $oursNewdir = $newdirIn($oursResolved->tree);
        $oursBar = Tree::fromObject($read($oursNewdir->entryNamed('bar', true)?->oid ?? ''));
        $reverseOursNewdir = $newdirIn($reverseOursResolved->tree);

        $t->true($oursResolved->isClean());
        $t->same(['newdir'], $names($oursResolved->tree));
        $t->same(['a', 'b', 'bar', 'c'], $names($oursNewdir));
        $t->same(['file'], $names($oursBar));
        $t->same([], $oursResolved->indexEntries());
        $t->true($reverseOursResolved->isClean());
        $t->same(['newdir'], $names($reverseOursResolved->tree));
        $t->same(['a', 'b', 'bar', 'c'], $names($reverseOursNewdir));
        $t->same('blob', $reverseOursNewdir->entryNamed('bar')?->kind());
        $t->same($changedFoo, $bodyAt($reverseOursNewdir, 'bar'));
        $t->same([], $reverseOursResolved->indexEntries());
    },
    'maps upstream gix-merge tree-baseline rename-within-rename fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $baseContent = "original\n1\n2\n3\n4\n5\n";
        $ourContent = "1\n2\n3\n4\n5\n";
        $theirContent = "original\n1\n2\n3\n4\n5\n6\n";
        $directory = static fn (string $content): Tree => new Tree([
            $treeEntry('sub', new Tree([
                $blobEntry('y.f', $content),
                $blobEntry('z', ''),
            ])),
            $blobEntry('w', ''),
            $blobEntry('x.f', $content),
        ]);
        $base = new Tree([$treeEntry('a', $directory($baseContent))]);
        $ours = new Tree([$treeEntry('a-renamed', $directory($ourContent))]);
        $theirs = new Tree([$treeEntry('a', new Tree([
            $treeEntry('sub-renamed', new Tree([
                $blobEntry('y.f', $theirContent),
                $blobEntry('z', ''),
            ])),
            $blobEntry('w', ''),
            $blobEntry('x.f', $theirContent),
        ]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $renamed = Tree::fromObject($read($result->tree->entryNamed('a-renamed', true)?->oid ?? ''));
        $sourceSub = Tree::fromObject($read($renamed->entryNamed('sub', true)?->oid ?? ''));
        $targetSub = Tree::fromObject($read($renamed->entryNamed('sub-renamed', true)?->oid ?? ''));
        $expanded = MergeIndexFile::entriesForResult($result, $read);

        $t->same(false, $result->isClean());
        $t->same(['a-renamed'], $names($result->tree));
        $t->same(['sub', 'sub-renamed', 'w', 'x.f'], $names($renamed));
        $t->same(['y.f', 'z'], $names($sourceSub));
        $t->same(['y.f', 'z'], $names($targetSub));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($sourceSub->entryNamed('y.f')?->oid ?? '')->body);
        $t->same("1\n2\n3\n4\n5\n6\n", $read($targetSub->entryNamed('y.f')?->oid ?? '')->body);
        $t->same("1\n2\n3\n4\n5\n6\n", $read($renamed->entryNamed('x.f')?->oid ?? '')->body);
        $t->same([
            ['path' => 'a-renamed/sub', 'reason' => 'nested-directory-rename', 'base' => 'sub', 'ours' => 'sub', 'theirs' => 'sub-renamed'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['path' => 'a-renamed/sub-renamed/y.f', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'body' => $theirContent],
            ['path' => 'a-renamed/sub-renamed/z', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'body' => ''],
            ['path' => 'a-renamed/sub/y.f', 'stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'body' => $baseContent],
            ['path' => 'a-renamed/sub/y.f', 'stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'body' => $ourContent],
            ['path' => 'a-renamed/sub/z', 'stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'body' => ''],
            ['path' => 'a-renamed/sub/z', 'stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'body' => ''],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'path' => $entry->path,
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'body' => $read($entry->oid)->body,
            ],
            $expanded,
        ));
        $t->same([], $result->worktreeConflictFiles($read));

        $ancestorResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
        $ancestorBase = Tree::fromObject($read($ancestorResolved->tree->entryNamed('a', true)?->oid ?? ''));
        $ancestorBaseSub = Tree::fromObject($read($ancestorBase->entryNamed('sub', true)?->oid ?? ''));
        $ancestorRenamed = Tree::fromObject($read($ancestorResolved->tree->entryNamed('a-renamed', true)?->oid ?? ''));

        $t->true($ancestorResolved->isClean());
        $t->same(['a', 'a-renamed'], $names($ancestorResolved->tree));
        $t->same(['sub'], $names($ancestorBase));
        $t->same(['y.f', 'z'], $names($ancestorBaseSub));
        $t->same(['w', 'x.f'], $names($ancestorRenamed));
        $t->same($baseContent, $read($ancestorBaseSub->entryNamed('y.f')?->oid ?? '')->body);
        $t->same("1\n2\n3\n4\n5\n6\n", $read($ancestorRenamed->entryNamed('x.f')?->oid ?? '')->body);
        $t->same([], $ancestorResolved->indexEntries());

        $oursResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS, TreeMergeResult::RESOLVE_OURS);
        $oursRenamed = Tree::fromObject($read($oursResolved->tree->entryNamed('a-renamed', true)?->oid ?? ''));
        $oursSub = Tree::fromObject($read($oursRenamed->entryNamed('sub', true)?->oid ?? ''));

        $t->true($oursResolved->isClean());
        $t->same(['a-renamed'], $names($oursResolved->tree));
        $t->same(['sub', 'w', 'x.f'], $names($oursRenamed));
        $t->same(['y.f', 'z'], $names($oursSub));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($oursSub->entryNamed('y.f')?->oid ?? '')->body);
        $t->same("1\n2\n3\n4\n5\n6\n", $read($oursRenamed->entryNamed('x.f')?->oid ?? '')->body);
        $t->same([], $oursResolved->indexEntries());

        $reverse = TreeMerge::mergeRecursive($base, $theirs, $ours, $read, $write);
        $reverseAncestorResolved = $reverse->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
        $reverseAncestorBase = Tree::fromObject($read($reverseAncestorResolved->tree->entryNamed('a', true)?->oid ?? ''));
        $reverseAncestorRenamed = Tree::fromObject($read($reverseAncestorResolved->tree->entryNamed('a-renamed', true)?->oid ?? ''));
        $reverseOursResolved = $reverse->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS, TreeMergeResult::RESOLVE_OURS);
        $reverseOursRenamed = Tree::fromObject($read($reverseOursResolved->tree->entryNamed('a-renamed', true)?->oid ?? ''));
        $reverseOursSub = Tree::fromObject($read($reverseOursRenamed->entryNamed('sub-renamed', true)?->oid ?? ''));

        $t->true($reverseAncestorResolved->isClean());
        $t->same(['a', 'a-renamed'], $names($reverseAncestorResolved->tree));
        $t->same(['sub'], $names($reverseAncestorBase));
        $t->same(['w', 'x.f'], $names($reverseAncestorRenamed));
        $t->same([], $reverseAncestorResolved->indexEntries());
        $t->true($reverseOursResolved->isClean());
        $t->same(['a-renamed'], $names($reverseOursResolved->tree));
        $t->same(['sub-renamed', 'w', 'x.f'], $names($reverseOursRenamed));
        $t->same(['y.f', 'z'], $names($reverseOursSub));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($reverseOursSub->entryNamed('y.f')?->oid ?? '')->body);
        $t->same("1\n2\n3\n4\n5\n6\n", $read($reverseOursRenamed->entryNamed('x.f')?->oid ?? '')->body);
        $t->same([], $reverseOursResolved->indexEntries());
    },
    'maps upstream gix-merge tree-baseline rename-within-rename-2 fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $baseContent = "original\n1\n2\n3\n4\n5\n";
        $ourContent = "1\n2\n3\n4\n5\n";
        $theirContent = "original\n1\n2\n3\n4\n5\n6\n";
        $mergedContent = "1\n2\n3\n4\n5\n6\n";
        $base = new Tree([
            $treeEntry('a', new Tree([
                $treeEntry('sub', new Tree([
                    $blobEntry('y.f', $baseContent),
                    $blobEntry('z', ''),
                ])),
                $blobEntry('w', ''),
                $blobEntry('x.f', $baseContent),
            ])),
        ]);
        $ours = new Tree([
            $treeEntry('a-renamed', new Tree([
                $treeEntry('sub-renamed', new Tree([
                    $blobEntry('y.f', $ourContent),
                    $blobEntry('z', ''),
                ])),
                $blobEntry('w', ''),
                $blobEntry('x.f', $ourContent),
            ])),
        ]);
        $theirs = new Tree([
            $treeEntry('a', new Tree([
                $treeEntry('sub-renamed', new Tree([
                    $blobEntry('y.f', $theirContent),
                    $blobEntry('z', ''),
                ])),
                $blobEntry('w', ''),
                $blobEntry('x.f', $theirContent),
            ])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $renamed = Tree::fromObject($read($result->tree->entryNamed('a-renamed', true)?->oid ?? ''));
        $targetSub = Tree::fromObject($read($renamed->entryNamed('sub-renamed', true)?->oid ?? ''));
        $mergedY = $read($targetSub->entryNamed('y.f')?->oid ?? '');
        $expanded = MergeIndexFile::entriesForResult($result, $read);

        $t->same(false, $result->isClean());
        $t->same(['a-renamed'], $names($result->tree));
        $t->same(['sub-renamed', 'w', 'x.f'], $names($renamed));
        $t->same("1\n2\n3\n4\n5\n<<<<<<< ours/a-renamed/sub-renamed/y.f\n=======\n6\n>>>>>>> theirs/a-renamed/sub-renamed/y.f\n", $mergedY->body);
        $t->same($mergedContent, $read($renamed->entryNamed('x.f')?->oid ?? '')->body);
        $t->same('', $read($targetSub->entryNamed('z')?->oid ?? '')->body);
        $t->same([
            ['path' => 'a-renamed/sub-renamed/y.f', 'reason' => 'content-conflict', 'base' => null, 'ours' => 'y.f', 'theirs' => 'y.f'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'a-renamed/sub-renamed/y.f', 'body' => $ourContent],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'a-renamed/sub-renamed/y.f', 'body' => $mergedContent],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $expanded,
        ));
        $t->same(['a-renamed/sub-renamed/y.f'], array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read)));

        $reverse = TreeMerge::mergeRecursive($base, $theirs, $ours, $read, $write);
        $reverseRenamed = Tree::fromObject($read($reverse->tree->entryNamed('a-renamed', true)?->oid ?? ''));
        $reverseSub = Tree::fromObject($read($reverseRenamed->entryNamed('sub-renamed', true)?->oid ?? ''));

        $t->true($reverse->isClean());
        $t->same(['a-renamed'], $names($reverse->tree));
        $t->same(['sub-renamed', 'w', 'x.f'], $names($reverseRenamed));
        $t->same($mergedContent, $read($reverseSub->entryNamed('y.f')?->oid ?? '')->body);
        $t->same([], $reverse->conflicts);
        $t->same([], $reverse->indexEntries());
    },
    'maps upstream gix-merge tree-baseline conflicting-rename fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $baseContent = "original\n1\n2\n3\n4\n5\n";
        $ourContent = "1\n2\n3\n4\n5\n";
        $theirContent = "original\n1\n2\n3\n4\n5\n6\n";
        $directory = static fn (string $content): Tree => new Tree([
            $treeEntry('sub', new Tree([
                $blobEntry('y.f', $content),
                $blobEntry('z', ''),
            ])),
            $blobEntry('w', ''),
            $blobEntry('x.f', $content),
        ]);
        $base = new Tree([$treeEntry('a', $directory($baseContent))]);
        $ours = new Tree([$treeEntry('a-renamed', $directory($ourContent))]);
        $theirs = new Tree([$treeEntry('a-different', $directory($theirContent))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $aTree = Tree::fromObject($read($result->tree->entryNamed('a', true)?->oid ?? ''));
        $expanded = MergeIndexFile::entriesForResult($result, $read);

        $t->same(false, $result->isClean());
        $t->same(['a'], $names($result->tree));
        $t->same(['sub', 'w', 'x.f'], $names($aTree));
        $t->same([
            ['path' => 'a', 'reason' => 'rename-rename', 'base' => 'a', 'ours' => 'a-renamed', 'theirs' => 'a-different'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'a', 'kind' => 'tree'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'a', 'kind' => 'tree'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'a', 'kind' => 'tree'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'kind' => (new TreeEntry($entry->mode, basename($entry->path), $entry->oid))->kind(),
            ],
            $result->indexEntries(),
        ));
        $t->same([
            ['path' => 'a-different/sub/y.f', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'body' => $theirContent],
            ['path' => 'a-different/sub/z', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'body' => ''],
            ['path' => 'a-different/w', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'body' => ''],
            ['path' => 'a-different/x.f', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'body' => $theirContent],
            ['path' => 'a-renamed/sub/y.f', 'stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'body' => $ourContent],
            ['path' => 'a-renamed/sub/z', 'stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'body' => ''],
            ['path' => 'a-renamed/w', 'stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'body' => ''],
            ['path' => 'a-renamed/x.f', 'stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'body' => $ourContent],
            ['path' => 'a/sub/y.f', 'stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'body' => $baseContent],
            ['path' => 'a/sub/z', 'stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'body' => ''],
            ['path' => 'a/w', 'stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'body' => ''],
            ['path' => 'a/x.f', 'stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'body' => $baseContent],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'path' => $entry->path,
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'body' => $read($entry->oid)->body,
            ],
            $expanded,
        ));

        $oursResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS, TreeMergeResult::RESOLVE_OURS);
        $oursResolvedTree = Tree::fromObject($read($oursResolved->tree->entryNamed('a-renamed', true)?->oid ?? ''));
        $oursResolvedSub = Tree::fromObject($read($oursResolvedTree->entryNamed('sub', true)?->oid ?? ''));

        $t->true($oursResolved->isClean());
        $t->same(['a-renamed'], $names($oursResolved->tree));
        $t->same(['sub', 'w', 'x.f'], $names($oursResolvedTree));
        $t->same($mergedContent = "1\n2\n3\n4\n5\n6\n", $read($oursResolvedSub->entryNamed('y.f')?->oid ?? '')->body);
        $t->same($mergedContent, $read($oursResolvedTree->entryNamed('x.f')?->oid ?? '')->body);
        $t->same([], $oursResolved->indexEntries());

        $reverse = TreeMerge::mergeRecursive($base, $theirs, $ours, $read, $write);
        $reverseResolved = $reverse->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS, TreeMergeResult::RESOLVE_OURS);
        $reverseResolvedTree = Tree::fromObject($read($reverseResolved->tree->entryNamed('a-different', true)?->oid ?? ''));

        $t->true($reverseResolved->isClean());
        $t->same(['a-different'], $names($reverseResolved->tree));
        $t->same($mergedContent, $read($reverseResolvedTree->entryNamed('x.f')?->oid ?? '')->body);
    },
    'maps upstream gix-merge tree-baseline conflicting-rename-2 fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $baseContent = "original\n1\n2\n3\n4\n5\n";
        $ourContent = "1\n2\n3\n4\n5\n";
        $theirContent = "original\n1\n2\n3\n4\n5\n6\n";
        $mergedContent = "1\n2\n3\n4\n5\n6\n";
        $base = new Tree([
            $treeEntry('a', new Tree([
                $treeEntry('sub', new Tree([
                    $blobEntry('y.f', $baseContent),
                    $blobEntry('z', ''),
                ])),
                $blobEntry('w', ''),
                $blobEntry('x.f', $baseContent),
            ])),
        ]);
        $ours = new Tree([
            $treeEntry('a', new Tree([
                $treeEntry('sub-renamed', new Tree([
                    $blobEntry('y.f', $ourContent),
                    $blobEntry('z', ''),
                ])),
                $blobEntry('w', ''),
                $blobEntry('x.f', $ourContent),
            ])),
        ]);
        $theirs = new Tree([
            $treeEntry('a', new Tree([
                $treeEntry('sub-different', new Tree([
                    $blobEntry('y.f', $theirContent),
                    $blobEntry('z', ''),
                ])),
                $blobEntry('w', ''),
                $blobEntry('x.f', $theirContent),
            ])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $aTree = Tree::fromObject($read($result->tree->entryNamed('a', true)?->oid ?? ''));
        $subTree = Tree::fromObject($read($aTree->entryNamed('sub', true)?->oid ?? ''));
        $x = $aTree->entryNamed('x.f');
        $expanded = MergeIndexFile::entriesForResult($result, $read);

        $t->same(false, $result->isClean());
        $t->same(['a'], $names($result->tree));
        $t->same(['sub', 'w', 'x.f'], $names($aTree));
        $t->same(['y.f', 'z'], $names($subTree));
        $t->same("1\n2\n3\n4\n5\n6\n", $read($x?->oid ?? '')->body);
        $t->same([
            ['path' => 'a/sub', 'reason' => 'rename-rename', 'base' => 'sub', 'ours' => 'sub-renamed', 'theirs' => 'sub-different'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'a/sub', 'kind' => 'tree'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'a/sub', 'kind' => 'tree'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'a/sub', 'kind' => 'tree'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'kind' => (new TreeEntry($entry->mode, basename($entry->path), $entry->oid))->kind(),
            ],
            $result->indexEntries(),
        ));
        $t->same([
            ['path' => 'a/sub-different/y.f', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'body' => $theirContent],
            ['path' => 'a/sub-different/z', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'body' => ''],
            ['path' => 'a/sub-renamed/y.f', 'stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'body' => $ourContent],
            ['path' => 'a/sub-renamed/z', 'stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'body' => ''],
            ['path' => 'a/sub/y.f', 'stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'body' => $baseContent],
            ['path' => 'a/sub/z', 'stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'body' => ''],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'path' => $entry->path,
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'body' => $read($entry->oid)->body,
            ],
            $expanded,
        ));

        $oursResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS, TreeMergeResult::RESOLVE_OURS);
        $oursResolvedTree = Tree::fromObject($read($oursResolved->tree->entryNamed('a', true)?->oid ?? ''));
        $oursResolvedSub = Tree::fromObject($read($oursResolvedTree->entryNamed('sub-renamed', true)?->oid ?? ''));

        $t->true($oursResolved->isClean());
        $t->same(['a'], $names($oursResolved->tree));
        $t->same(['sub-renamed', 'w', 'x.f'], $names($oursResolvedTree));
        $t->same(['y.f', 'z'], $names($oursResolvedSub));
        $t->same($mergedContent, $read($oursResolvedSub->entryNamed('y.f')?->oid ?? '')->body);
        $t->same($mergedContent, $read($oursResolvedTree->entryNamed('x.f')?->oid ?? '')->body);

        $reverse = TreeMerge::mergeRecursive($base, $theirs, $ours, $read, $write);
        $reverseResolved = $reverse->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS, TreeMergeResult::RESOLVE_OURS);
        $reverseResolvedTree = Tree::fromObject($read($reverseResolved->tree->entryNamed('a', true)?->oid ?? ''));
        $reverseResolvedSub = Tree::fromObject($read($reverseResolvedTree->entryNamed('sub-different', true)?->oid ?? ''));

        $t->true($reverseResolved->isClean());
        $t->same(['sub-different', 'w', 'x.f'], $names($reverseResolvedTree));
        $t->same($mergedContent, $read($reverseResolvedSub->entryNamed('y.f')?->oid ?? '')->body);
        $t->same($mergedContent, $read($reverseResolvedTree->entryNamed('x.f')?->oid ?? '')->body);
    },
    'maps upstream gix-merge tree-baseline conflicting-rename-complex fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $baseContent = "original\n1\n2\n3\n4\n5\n";
        $ourContent = "1\n2\n3\n4\n5\n";
        $theirContent = "original\n1\n2\n3\n4\n5\n6\n";
        $base = new Tree([
            $treeEntry('a', new Tree([
                $treeEntry('sub', new Tree([
                    $blobEntry('y.f', $baseContent),
                    $blobEntry('z', ''),
                ])),
                $blobEntry('w', ''),
                $blobEntry('x.f', $baseContent),
            ])),
        ]);
        $ours = new Tree([
            $treeEntry('a-renamed', new Tree([
                $treeEntry('sub', new Tree([
                    $blobEntry('y.f', $ourContent),
                    $blobEntry('z', ''),
                ])),
                $blobEntry('w', ''),
                $blobEntry('x.f', $ourContent),
            ])),
        ]);
        $theirs = new Tree([
            $treeEntry('a', new Tree([
                $blobEntry('y.f', $theirContent),
                $blobEntry('z', ''),
            ])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $renamed = Tree::fromObject($read($result->tree->entryNamed('a-renamed', true)?->oid ?? ''));
        $sub = Tree::fromObject($read($renamed->entryNamed('sub', true)?->oid ?? ''));
        $expanded = MergeIndexFile::entriesForResult($result, $read);

        $t->same(false, $result->isClean());
        $t->same(['a-renamed'], $names($result->tree));
        $t->same(['sub', 'w', 'x.f', 'y.f', 'z'], $names($renamed));
        $t->same(['y.f', 'z'], $names($sub));
        $t->same($ourContent, $read($sub->entryNamed('y.f')?->oid ?? '')->body);
        $t->same('', $read($sub->entryNamed('z')?->oid ?? '')->body);
        $t->same('', $read($renamed->entryNamed('w')?->oid ?? '')->body);
        $t->same($theirContent, $read($renamed->entryNamed('x.f')?->oid ?? '')->body);
        $t->same($theirContent, $read($renamed->entryNamed('y.f')?->oid ?? '')->body);
        $t->same('', $read($renamed->entryNamed('z')?->oid ?? '')->body);
        $t->same([
            ['path' => 'a-renamed', 'reason' => 'directory-rename-subtree-replacement', 'base' => null, 'ours' => 'a-renamed', 'theirs' => null],
            ['path' => 'a/x.f', 'reason' => 'rename-delete', 'base' => 'x.f', 'ours' => null, 'theirs' => null],
            ['path' => 'a/y.f', 'reason' => 'directory-rename-suggested', 'base' => null, 'ours' => null, 'theirs' => 'y.f'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['path' => 'a-renamed/sub/y.f', 'stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'body' => $ourContent],
            ['path' => 'a-renamed/sub/z', 'stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'body' => ''],
            ['path' => 'a-renamed/w', 'stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'body' => ''],
            ['path' => 'a-renamed/x.f', 'stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'body' => $theirContent],
            ['path' => 'a/x.f', 'stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'body' => $baseContent],
            ['path' => 'a/y.f', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'body' => $theirContent],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'path' => $entry->path,
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'body' => $read($entry->oid)->body,
            ],
            $expanded,
        ));
        $t->same([], $result->worktreeConflictFiles($read));

        $reverse = TreeMerge::mergeRecursive($base, $theirs, $ours, $read, $write);
        $reverseRenamed = Tree::fromObject($read($reverse->tree->entryNamed('a-renamed', true)?->oid ?? ''));
        $reverseExpanded = MergeIndexFile::entriesForResult($reverse, $read);

        $t->same(false, $reverse->isClean());
        $t->same(['sub', 'w', 'x.f', 'y.f', 'z'], $names($reverseRenamed));
        $t->same($theirContent, $read($reverseRenamed->entryNamed('x.f')?->oid ?? '')->body);
        $t->same([
            ['path' => 'a-renamed/sub/y.f', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'body' => $ourContent],
            ['path' => 'a-renamed/sub/z', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'body' => ''],
            ['path' => 'a-renamed/w', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'body' => ''],
            ['path' => 'a-renamed/x.f', 'stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'body' => $theirContent],
            ['path' => 'a/x.f', 'stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'body' => $baseContent],
            ['path' => 'a/y.f', 'stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'body' => $theirContent],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'path' => $entry->path,
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'body' => $read($entry->oid)->body,
            ],
            $reverseExpanded,
        ));

        $ancestorResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
        $ancestorATree = Tree::fromObject($read($ancestorResolved->tree->entryNamed('a', true)?->oid ?? ''));
        $ancestorSub = Tree::fromObject($read($ancestorATree->entryNamed('sub', true)?->oid ?? ''));
        $ancestorRenamed = Tree::fromObject($read($ancestorResolved->tree->entryNamed('a-renamed', true)?->oid ?? ''));

        $t->true($ancestorResolved->isClean());
        $t->same(['a', 'a-renamed'], $names($ancestorResolved->tree));
        $t->same(['z'], $names($ancestorRenamed));
        $t->same(['sub', 'w', 'x.f'], $names($ancestorATree));
        $t->same(['y.f', 'z'], $names($ancestorSub));
        $t->same($baseContent, $read($ancestorSub->entryNamed('y.f')?->oid ?? '')->body);
        $t->same($baseContent, $read($ancestorATree->entryNamed('x.f')?->oid ?? '')->body);
        $t->same([], $ancestorResolved->indexEntries());

        $oursResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
        $oursRenamed = Tree::fromObject($read($oursResolved->tree->entryNamed('a-renamed', true)?->oid ?? ''));
        $oursSub = Tree::fromObject($read($oursRenamed->entryNamed('sub', true)?->oid ?? ''));

        $t->true($oursResolved->isClean());
        $t->same(['a-renamed'], $names($oursResolved->tree));
        $t->same(['sub', 'w', 'x.f', 'z'], $names($oursRenamed));
        $t->same(['y.f', 'z'], $names($oursSub));
        $t->same($ourContent, $read($oursSub->entryNamed('y.f')?->oid ?? '')->body);
        $t->same($theirContent, $read($oursRenamed->entryNamed('x.f')?->oid ?? '')->body);
        $t->same('', $read($oursRenamed->entryNamed('z')?->oid ?? '')->body);
        $t->same([], $oursResolved->indexEntries());

        $theirsResolved = $result->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_THEIRS);
        $theirsRenamed = Tree::fromObject($read($theirsResolved->tree->entryNamed('a-renamed', true)?->oid ?? ''));

        $t->true($theirsResolved->isClean());
        $t->same(['a-renamed'], $names($theirsResolved->tree));
        $t->same(['y.f', 'z'], $names($theirsRenamed));
        $t->same($theirContent, $read($theirsRenamed->entryNamed('y.f')?->oid ?? '')->body);
        $t->same('', $read($theirsRenamed->entryNamed('z')?->oid ?? '')->body);
        $t->same([], $theirsResolved->indexEntries());

        $reverseOursResolved = $reverse->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
        $reverseOursRenamed = Tree::fromObject($read($reverseOursResolved->tree->entryNamed('a-renamed', true)?->oid ?? ''));
        $reverseTheirsResolved = $reverse->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_THEIRS);
        $reverseTheirsRenamed = Tree::fromObject($read($reverseTheirsResolved->tree->entryNamed('a-renamed', true)?->oid ?? ''));
        $reverseTheirsSub = Tree::fromObject($read($reverseTheirsRenamed->entryNamed('sub', true)?->oid ?? ''));

        $t->true($reverseOursResolved->isClean());
        $t->same(['y.f', 'z'], $names($reverseOursRenamed));
        $t->same($theirContent, $read($reverseOursRenamed->entryNamed('y.f')?->oid ?? '')->body);
        $t->same([], $reverseOursResolved->indexEntries());
        $t->true($reverseTheirsResolved->isClean());
        $t->same(['sub', 'w', 'x.f', 'z'], $names($reverseTheirsRenamed));
        $t->same($ourContent, $read($reverseTheirsSub->entryNamed('y.f')?->oid ?? '')->body);
        $t->same($theirContent, $read($reverseTheirsRenamed->entryNamed('x.f')?->oid ?? '')->body);
        $t->same([], $reverseTheirsResolved->indexEntries());
    },
    'maps upstream gix-merge tree-baseline rename-rename-plus-content fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $base = new Tree([$blobEntry('foo', "1\n2\n3\n4\n5\n")]);
        $ours = new Tree([$blobEntry('bar', "1\n2\n3\n4\n5\nsix\n")]);
        $theirs = new Tree([$blobEntry('baz', "1\n2\n3\n4\n5\n6\n")]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $mergedFoo = $read($result->tree->entryNamed('foo')?->oid ?? '');

        $t->same(false, $result->isClean());
        $t->same(['foo'], $names($result->tree));
        $t->same("1\n2\n3\n4\n5\n", $mergedFoo->body);
        $t->same([
            ['path' => 'foo', 'reason' => 'rename-rename', 'ours' => 'bar', 'theirs' => 'baz'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'foo', 'body' => "1\n2\n3\n4\n5\n"],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'foo', 'body' => "1\n2\n3\n4\n5\nsix\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'foo', 'body' => "1\n2\n3\n4\n5\n6\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
    },
    'maps upstream gix-merge tree-baseline rename-add-delete fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $base = new Tree([$blobEntry('foo', "original file\n")]);
        $ours = new Tree([$blobEntry('bar', "different file\n")]);
        $theirs = new Tree([$blobEntry('bar', "original file\n")]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $mergedBar = $read($result->tree->entryNamed('bar')?->oid ?? '');

        $t->same(false, $result->isClean());
        $t->same(['bar'], $names($result->tree));
        $t->contains('<<<<<<< ours/bar', $mergedBar->body);
        $t->contains('different file', $mergedBar->body);
        $t->contains('original file', $mergedBar->body);
        $t->same([
            ['path' => 'bar', 'reason' => 'content-conflict', 'base' => null, 'ours' => 'bar', 'theirs' => 'bar'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'bar', 'body' => "different file\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'bar', 'body' => "original file\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same(['bar'], array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read)));
    },
    'maps upstream gix-merge tree-baseline rename-rename-delete-delete fixture shape' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry] = $objectStore();
        $base = new Tree([
            $blobEntry('foo', "foo\n"),
            $blobEntry('bar', "bar\n"),
        ]);
        $ours = new Tree([$blobEntry('baz', "foo\n")]);
        $theirs = new Tree([$blobEntry('baz', "bar\n")]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $mergedBaz = $read($result->tree->entryNamed('baz')?->oid ?? '');

        $t->same(false, $result->isClean());
        $t->same(['baz'], $names($result->tree));
        $t->contains('<<<<<<< ours/baz', $mergedBaz->body);
        $t->contains("foo\n", $mergedBaz->body);
        $t->contains("bar\n", $mergedBaz->body);
        $t->same([
            ['path' => 'baz', 'reason' => 'content-conflict', 'base' => null, 'ours' => 'baz', 'theirs' => 'baz'],
        ], array_map(
            static fn ($conflict): array => [
                'path' => $conflict->path,
                'reason' => $conflict->reason,
                'base' => $conflict->base?->filename,
                'ours' => $conflict->ours?->filename,
                'theirs' => $conflict->theirs?->filename,
            ],
            $result->conflicts,
        ));
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'baz', 'body' => "foo\n"],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'baz', 'body' => "bar\n"],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => [
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'path' => $entry->path,
                'body' => $read($entry->oid)->body,
            ],
            $result->indexEntries(),
        ));
        $t->same(['baz'], array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read)));

        $bodyOf = static function (Tree $tree, string $path) use ($read): ?string {
            $entry = $tree->entryNamed($path);

            return $entry === null ? null : $read($entry->oid)->body;
        };
        $ancestorResolved = $result->resolveTreeConflicts(
            $read,
            $write,
            TreeMergeResult::RESOLVE_ANCESTOR,
            TreeMergeResult::RESOLVE_ANCESTOR,
        );
        $oursResolved = $result->resolveTreeConflicts(
            $read,
            $write,
            TreeMergeResult::RESOLVE_OURS,
            TreeMergeResult::RESOLVE_OURS,
        );
        $reverseResult = TreeMerge::mergeRecursive($base, $theirs, $ours, $read, $write);
        $reverseAncestorResolved = $reverseResult->resolveTreeConflicts(
            $read,
            $write,
            TreeMergeResult::RESOLVE_ANCESTOR,
            TreeMergeResult::RESOLVE_ANCESTOR,
        );
        $reverseOursResolved = $reverseResult->resolveTreeConflicts(
            $read,
            $write,
            TreeMergeResult::RESOLVE_OURS,
            TreeMergeResult::RESOLVE_OURS,
        );

        $t->true($ancestorResolved->isClean());
        $t->same(['bar', 'foo'], $names($ancestorResolved->tree));
        $t->same("bar\n", $bodyOf($ancestorResolved->tree, 'bar'));
        $t->same("foo\n", $bodyOf($ancestorResolved->tree, 'foo'));
        $t->same([], $ancestorResolved->indexEntries());
        $t->true($oursResolved->isClean());
        $t->same(['baz'], $names($oursResolved->tree));
        $t->same("foo\n", $bodyOf($oursResolved->tree, 'baz'));
        $t->same([], $oursResolved->indexEntries());
        $t->true($reverseAncestorResolved->isClean());
        $t->same(['bar', 'foo'], $names($reverseAncestorResolved->tree));
        $t->same("bar\n", $bodyOf($reverseAncestorResolved->tree, 'bar'));
        $t->same("foo\n", $bodyOf($reverseAncestorResolved->tree, 'foo'));
        $t->true($reverseOursResolved->isClean());
        $t->same(['baz'], $names($reverseOursResolved->tree));
        $t->same("bar\n", $bodyOf($reverseOursResolved->tree, 'baz'));
        $t->same([], $reverseOursResolved->indexEntries());
    },
    'recursive tree merge reports directory rename content conflicts at new path' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $basePlugin = "Plugin: Acme\nVersion: 1.0\nRequires: 6.5\nStatus: active\n";
        $ourPlugin = "Plugin: Acme Pro\nVersion: 1.1\nRequires: 6.5\nStatus: active\n";
        $theirPlugin = "Plugin: Acme\nVersion: 1.2\nRequires: 6.5\nStatus: active\n";
        $readme = "Acme plugin\nStable tag: 1.0\n";
        $base = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme', new Tree([
            $blobEntry('acme.php', $basePlugin),
            $blobEntry('readme.txt', $readme),
        ]))]))]))]);
        $ours = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme-pro', new Tree([
            $blobEntry('acme.php', $ourPlugin),
            $blobEntry('readme.txt', $readme),
        ]))]))]))]);
        $theirs = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme', new Tree([
            $blobEntry('acme.php', $theirPlugin),
            $blobEntry('readme.txt', $readme),
        ]))]))]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write, BlobMerge::STYLE_DIFF3);
        $contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
        $pluginsTree = Tree::fromObject($read($contentTree->entryNamed('plugins', true)?->oid ?? ''));
        $pluginTree = Tree::fromObject($read($pluginsTree->entryNamed('acme-pro', true)?->oid ?? ''));
        $mergedPlugin = $read($pluginTree->entryNamed('acme.php')?->oid ?? '');

        $t->same(false, $result->isClean());
        $t->same(['acme-pro'], $names($pluginsTree));
        $t->same('content-conflict', $result->conflicts[0]->reason);
        $t->same('wp-content/plugins/acme-pro/acme.php', $result->conflicts[0]->path);
        $t->contains('<<<<<<< ours/wp-content/plugins/acme-pro/acme.php', $mergedPlugin->body);
        $t->contains('Version: 1.1', $mergedPlugin->body);
        $t->contains('Version: 1.2', $mergedPlugin->body);
        $t->same([
            ['stage' => MergeIndexEntry::STAGE_ANCESTOR, 'side' => 'ancestor', 'path' => 'wp-content/plugins/acme-pro/acme.php'],
            ['stage' => MergeIndexEntry::STAGE_OURS, 'side' => 'ours', 'path' => 'wp-content/plugins/acme-pro/acme.php'],
            ['stage' => MergeIndexEntry::STAGE_THEIRS, 'side' => 'theirs', 'path' => 'wp-content/plugins/acme-pro/acme.php'],
        ], array_map(
            static fn (MergeIndexEntry $entry): array => ['stage' => $entry->stage, 'side' => $entry->side(), 'path' => $entry->path],
            $result->indexEntries(),
        ));
        $t->same(['wp-content/plugins/acme-pro/acme.php'], array_map(static fn ($file): string => $file->path, $result->worktreeConflictFiles($read)));
    },
    'recursive tree merge preserves side labels when theirs renames a modified directory' => static function (TestRunner $t) use ($objectStore): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $basePlugin = "Plugin: Acme\nVersion: 1.0\nRequires: 6.5\nStatus: active\n";
        $ourPlugin = "Plugin: Acme\nVersion: ours\nRequires: 6.5\nStatus: active\n";
        $theirPlugin = "Plugin: Acme Pro\nVersion: theirs\nRequires: 6.5\nStatus: active\n";
        $readme = "Acme plugin\nStable tag: 1.0\n";
        $base = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme', new Tree([
            $blobEntry('acme.php', $basePlugin),
            $blobEntry('readme.txt', $readme),
        ]))]))]))]);
        $ours = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme', new Tree([
            $blobEntry('acme.php', $ourPlugin),
            $blobEntry('readme.txt', $readme),
        ]))]))]))]);
        $theirs = new Tree([$treeEntry('wp-content', new Tree([$treeEntry('plugins', new Tree([$treeEntry('acme-pro', new Tree([
            $blobEntry('acme.php', $theirPlugin),
            $blobEntry('readme.txt', $readme),
        ]))]))]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
        $pluginsTree = Tree::fromObject($read($contentTree->entryNamed('plugins', true)?->oid ?? ''));
        $pluginTree = Tree::fromObject($read($pluginsTree->entryNamed('acme-pro', true)?->oid ?? ''));
        $mergedPlugin = $read($pluginTree->entryNamed('acme.php')?->oid ?? '');

        $t->same(false, $result->isClean());
        $t->same('wp-content/plugins/acme-pro/acme.php', $result->conflicts[0]->path);
        $t->contains("<<<<<<< ours/wp-content/plugins/acme-pro/acme.php\nPlugin: Acme\nVersion: ours", $mergedPlugin->body);
        $t->contains("=======\nPlugin: Acme Pro\nVersion: theirs", $mergedPlugin->body);
    },
    'recursive tree merge records nested directory file conflicts with stages' => static function (TestRunner $t) use ($objectStore, $names): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $base = new Tree([
            $treeEntry('wp-content', new Tree([])),
        ]);
        $ours = new Tree([
            $treeEntry('wp-content', new Tree([
                $treeEntry('cache', new Tree([
                    $blobEntry('index.php', "<?php\n"),
                ])),
            ])),
        ]);
        $theirs = new Tree([
            $treeEntry('wp-content', new Tree([
                $blobEntry('cache', "legacy cache file\n"),
            ])),
        ]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);
        $contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
        $cacheTree = Tree::fromObject($read($contentTree->entryNamed('cache', true)?->oid ?? ''));

        $t->same(false, $result->isClean());
        $t->same('directory-file', $result->conflicts[0]->reason);
        $t->same('wp-content/cache~B', $result->conflicts[0]->path);
        $t->same(['cache', 'cache~B'], $names($contentTree));
        $t->same(['index.php'], $names($cacheTree));
        $t->same([
            MergeIndexEntry::STAGE_THEIRS,
        ], array_map(static fn (MergeIndexEntry $entry): int => $entry->stage, $result->indexEntries()));
        $t->same([
            'blob',
        ], array_map(static fn (MergeIndexEntry $entry): string => (new TreeEntry($entry->mode, 'cache', $entry->oid))->kind(), $result->indexEntries()));
        $t->same([], $result->worktreeConflictFiles($read));
    },
];
