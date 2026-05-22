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
    'recursive tree merge reports similar rename modify conflicts' => static function (TestRunner $t) use ($objectStore): void {
        [$read, $write, $blobEntry, $treeEntry] = $objectStore();
        $baseContent = "name: old-plugin\nversion: 1.0\nrequires: 6.5\nstatus: active\nentry: bootstrap.php\n";
        $ourContent = "name: new-plugin\nversion: 1.1\nrequires: 6.5\nstatus: active\nentry: bootstrap.php\n";
        $theirContent = "name: old-plugin\nversion: 1.0\nrequires: 6.6\nstatus: paused\nentry: bootstrap.php\n";
        $base = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('old-plugin.php', $baseContent)]))]);
        $ours = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('new-plugin.php', $ourContent)]))]);
        $theirs = new Tree([$treeEntry('wp-content', new Tree([$blobEntry('old-plugin.php', $theirContent)]))]);

        $result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write);

        $t->same(false, $result->isClean());
        $t->same('rename-modify', $result->conflicts[0]->reason);
        $t->same('wp-content/old-plugin.php', $result->conflicts[0]->path);
        $t->same('new-plugin.php', $result->conflicts[0]->ours?->filename);
        $t->same('old-plugin.php', $result->conflicts[0]->theirs?->filename);
        $t->same([
            MergeIndexEntry::STAGE_ANCESTOR,
            MergeIndexEntry::STAGE_OURS,
            MergeIndexEntry::STAGE_THEIRS,
        ], array_map(static fn (MergeIndexEntry $entry): int => $entry->stage, $result->indexEntries()));
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
    'recursive tree merge records nested directory file conflicts with stages' => static function (TestRunner $t) use ($objectStore): void {
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

        $t->same(false, $result->isClean());
        $t->same('directory-file', $result->conflicts[0]->reason);
        $t->same('wp-content/cache', $result->conflicts[0]->path);
        $t->same([
            MergeIndexEntry::STAGE_OURS,
            MergeIndexEntry::STAGE_THEIRS,
        ], array_map(static fn (MergeIndexEntry $entry): int => $entry->stage, $result->indexEntries()));
        $t->same([
            'tree',
            'blob',
        ], array_map(static fn (MergeIndexEntry $entry): string => (new TreeEntry($entry->mode, 'cache', $entry->oid))->kind(), $result->indexEntries()));
        $t->same([], $result->worktreeConflictFiles($read));
    },
];
