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

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$objects = [];
$write = static function (GitObject $object) use (&$objects): string {
    $oid = $object->oid();
    $objects[$oid] = $object;

    return $oid;
};
$read = static function (string $oid) use (&$objects): GitObject {
    if (!isset($objects[$oid])) {
        throw new RuntimeException("Object not found: {$oid}");
    }

    return $objects[$oid];
};
$blob = static fn (string $name, string $content): TreeEntry => new TreeEntry('100644', $name, $write(new GitObject('blob', $content)));
$symlink = static fn (string $name, string $target): TreeEntry => new TreeEntry('120000', $name, $write(new GitObject('blob', $target)));
$tree = static fn (string $name, Tree $tree): TreeEntry => new TreeEntry('40000', $name, $write($tree->toObject()));
$treeAtPath = static function (Tree $root, string $path) use ($read): Tree {
    $current = $root;
    foreach (explode('/', $path) as $part) {
        $entry = $current->entryNamed($part, true);
        if ($entry === null) {
            throw new RuntimeException("Tree entry not found: {$path}");
        }
        $current = Tree::fromObject($read($entry->oid));
    }

    return $current;
};
$treeAtPathOrEmpty = static function (Tree $root, string $path) use ($read): Tree {
    $current = $root;
    foreach (explode('/', $path) as $part) {
        $entry = $current->entryNamed($part, true);
        if ($entry === null || !$entry->isTree()) {
            return new Tree([]);
        }
        $current = Tree::fromObject($read($entry->oid));
    }

    return $current;
};
$wpContent = static fn (TreeEntry $metadata, TreeEntry $theme): Tree => new Tree([
    $metadata,
    $tree('themes', new Tree([
        $tree('acme', new Tree([$theme])),
    ])),
]);

$base = new Tree([
    $tree('wp-content', $wpContent(
        $blob('post.meta', "title: Demo\nslug: demo\nstatus: draft\n"),
        $blob('theme.json', "{\n  \"color\": \"base\"\n}\n"),
    )),
]);
$ours = new Tree([
    $tree('wp-content', $wpContent(
        $blob('post.meta', "title: Demo Import\nslug: demo\nstatus: draft\n"),
        $blob('theme.json', "{\n  \"color\": \"blue\"\n}\n"),
    )),
]);
$theirs = new Tree([
    $tree('wp-content', $wpContent(
        $blob('post.meta', "title: Demo\nslug: demo\nstatus: publish\n"),
        $blob('theme.json', "{\n  \"color\": \"green\"\n}\n"),
    )),
]);

$result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write, BlobMerge::STYLE_DIFF3);
$virtualAncestor = new Tree([
    $blob('wp-plugin.php', "Plugin Name: Acme\nVersion: 1.0\n"),
]);
$virtualFirstBase = new Tree([
    $blob('wp-plugin.php', "Requires PHP: 8.1\nPlugin Name: Acme\nVersion: 1.0\n"),
]);
$virtualSecondBase = new Tree([
    $blob('wp-plugin.php', "Plugin Name: Acme\nVersion: 1.0\nText Domain: acme\n"),
]);
$virtualOurs = new Tree([
    $blob('wp-plugin.php', "Requires PHP: 8.1\nPlugin Name: Acme\nVersion: 1.1\n"),
]);
$virtualTheirs = new Tree([
    $blob('acme-plugin.php', "Requires PHP: 8.1\nPlugin Name: Acme\nVersion: 1.0\nText Domain: acme-plugin\n"),
]);
$virtualResult = TreeMerge::mergeRecursiveWithVirtualBase(
    $virtualAncestor,
    [$virtualFirstBase, $virtualSecondBase],
    $virtualOurs,
    $virtualTheirs,
    $read,
    $write,
);
$virtualIndexEntries = $virtualResult->indexEntries();
$pluginTree = static fn (string $directoryName, string $routeContent): Tree => new Tree([
    $blob('acme.php', "<?php\n// Plugin bootstrap.\n"),
    $tree($directoryName, new Tree([
        $blob('rest.php', $routeContent),
        $blob('index.php', "<?php\n// Silence is golden.\n"),
    ])),
]);
$nestedBaseRoutes = "original\nregister_rest_route\nsanitize_callback\n";
$nestedOursRoutes = "register_rest_route\nsanitize_callback\n";
$nestedTheirsRoutes = "original\nregister_rest_route\nsanitize_callback\npermission_callback\n";
$nestedBase = new Tree([
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $tree('acme', $pluginTree('includes', $nestedBaseRoutes)),
        ])),
    ])),
]);
$nestedOurs = new Tree([
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $tree('acme-pro', $pluginTree('includes', $nestedOursRoutes)),
        ])),
    ])),
]);
$nestedTheirs = new Tree([
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $tree('acme', $pluginTree('src', $nestedTheirsRoutes)),
        ])),
    ])),
]);
$nestedResult = TreeMerge::mergeRecursive($nestedBase, $nestedOurs, $nestedTheirs, $read, $write);
$nestedContent = Tree::fromObject($read($nestedResult->tree->entryNamed('wp-content', true)?->oid ?? ''));
$nestedPlugins = Tree::fromObject($read($nestedContent->entryNamed('plugins', true)?->oid ?? ''));
$nestedPlugin = Tree::fromObject($read($nestedPlugins->entryNamed('acme-pro', true)?->oid ?? ''));
$nestedIncludes = Tree::fromObject($read($nestedPlugin->entryNamed('includes', true)?->oid ?? ''));
$nestedSrc = Tree::fromObject($read($nestedPlugin->entryNamed('src', true)?->oid ?? ''));
$sameTargetBase = new Tree([
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $tree('acme', $pluginTree('includes', $nestedBaseRoutes)),
        ])),
    ])),
]);
$sameTargetOurs = new Tree([
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $tree('acme-pro', $pluginTree('src', $nestedOursRoutes)),
        ])),
    ])),
]);
$sameTargetTheirs = new Tree([
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $tree('acme', $pluginTree('src', $nestedTheirsRoutes)),
        ])),
    ])),
]);
$sameTargetNestedResult = TreeMerge::mergeRecursive($sameTargetBase, $sameTargetOurs, $sameTargetTheirs, $read, $write);
$sameTargetContent = Tree::fromObject($read($sameTargetNestedResult->tree->entryNamed('wp-content', true)?->oid ?? ''));
$sameTargetPlugins = Tree::fromObject($read($sameTargetContent->entryNamed('plugins', true)?->oid ?? ''));
$sameTargetPlugin = Tree::fromObject($read($sameTargetPlugins->entryNamed('acme-pro', true)?->oid ?? ''));
$sameTargetSrc = Tree::fromObject($read($sameTargetPlugin->entryNamed('src', true)?->oid ?? ''));
$sameTargetRoute = $read($sameTargetSrc->entryNamed('rest.php')?->oid ?? '')->body;
$subtreeReplacementPlugin = static fn (string $routeContent, string $bootstrapContent, bool $asSubtreeRoot = false): Tree => $asSubtreeRoot
    ? new Tree([
        $blob('index.php', "<?php\n// Silence is golden.\n"),
        $blob('rest.php', $routeContent),
    ])
    : new Tree([
        $blob('bootstrap.php', $bootstrapContent),
        $tree('includes', new Tree([
            $blob('index.php', "<?php\n// Silence is golden.\n"),
            $blob('rest.php', $routeContent),
        ])),
        $blob('readme.txt', "Acme plugin\nStable tag: 1.0\n"),
    ]);
$subtreeReplacementBaseRoute = "original\nregister_rest_route\nsanitize_callback\n";
$subtreeReplacementOursRoute = "register_rest_route\nsanitize_callback\n";
$subtreeReplacementTheirsRoute = "original\nregister_rest_route\nsanitize_callback\npermission_callback\n";
$subtreeReplacementBase = new Tree([
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $tree('acme', $subtreeReplacementPlugin($subtreeReplacementBaseRoute, $subtreeReplacementBaseRoute)),
        ])),
    ])),
]);
$subtreeReplacementOurs = new Tree([
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $tree('acme-pro', $subtreeReplacementPlugin($subtreeReplacementOursRoute, $subtreeReplacementOursRoute)),
        ])),
    ])),
]);
$subtreeReplacementTheirs = new Tree([
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $tree('acme', $subtreeReplacementPlugin($subtreeReplacementTheirsRoute, '', true)),
        ])),
    ])),
]);
$subtreeReplacementResult = TreeMerge::mergeRecursive($subtreeReplacementBase, $subtreeReplacementOurs, $subtreeReplacementTheirs, $read, $write);
$subtreeReplacementContent = Tree::fromObject($read($subtreeReplacementResult->tree->entryNamed('wp-content', true)?->oid ?? ''));
$subtreeReplacementPlugins = Tree::fromObject($read($subtreeReplacementContent->entryNamed('plugins', true)?->oid ?? ''));
$subtreeReplacementMergedPlugin = Tree::fromObject($read($subtreeReplacementPlugins->entryNamed('acme-pro', true)?->oid ?? ''));
$subtreeReplacementIncludes = Tree::fromObject($read($subtreeReplacementMergedPlugin->entryNamed('includes', true)?->oid ?? ''));
$symlinkTarget = '../plugins/acme/bootstrap.php';
$symlinkBase = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $symlink('acme-bootstrap.php', $symlinkTarget),
        ])),
        $tree('plugins', new Tree([
            $tree('acme', new Tree([
                $blob('bootstrap.php', "Plugin Name: Acme\nVersion: 1.0\n"),
            ])),
        ])),
    ])),
]);
$symlinkOurs = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $symlink('acme-bootstrap-current.php', $symlinkTarget),
        ])),
        $tree('plugins', new Tree([
            $tree('acme', new Tree([
                $blob('bootstrap.php', "Plugin Name: Acme\nVersion: 1.1\n"),
            ])),
        ])),
    ])),
]);
$symlinkTheirs = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $symlink('acme-bootstrap-deployed.php', $symlinkTarget),
        ])),
        $tree('plugins', new Tree([
            $tree('acme', new Tree([
                $blob('bootstrap.php', "Plugin Name: Acme\nVersion: 1.1\n"),
            ])),
        ])),
    ])),
]);
$symlinkConflictResult = TreeMerge::mergeRecursive($symlinkBase, $symlinkOurs, $symlinkTheirs, $read, $write);
$symlinkOursResolved = $symlinkConflictResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
$symlinkResolvedMuPlugins = $treeAtPath($symlinkOursResolved->tree, 'wp-content/mu-plugins');
$symlinkResolvedPlugin = $treeAtPath($symlinkOursResolved->tree, 'wp-content/plugins/acme');
$symlinkResolvedEntry = $symlinkResolvedMuPlugins->entryNamed('acme-bootstrap-current.php');
$targetAddBase = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $blob('legacy-loader.php', "<?php\n// original\nload_plugin_textdomain('acme');\n"),
        ])),
    ])),
]);
$targetAddOurs = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $blob('legacy-loader.php', "<?php\nload_plugin_textdomain('acme');\n"),
            $symlink('active-loader.php', 'legacy-loader.php'),
        ])),
    ])),
]);
$targetAddTheirs = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $blob('active-loader.php', "<?php\n// original\nload_plugin_textdomain('acme');\nadd_action('init', 'acme_boot');\n"),
        ])),
    ])),
]);
$targetAddResult = TreeMerge::mergeRecursive($targetAddBase, $targetAddOurs, $targetAddTheirs, $read, $write);
$targetAddAncestorResolved = $targetAddResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
$targetAddOursResolved = $targetAddResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
$targetAddAncestorMuPlugins = $treeAtPathOrEmpty($targetAddAncestorResolved->tree, 'wp-content/mu-plugins');
$targetAddOursMuPlugins = $treeAtPathOrEmpty($targetAddOursResolved->tree, 'wp-content/mu-plugins');
$targetAddOursEntry = $targetAddOursMuPlugins->entryNamed('active-loader.php');
$contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
$metadata = $read($contentTree->entryNamed('post.meta')?->oid ?? '');
$demoRoot = sys_get_temp_dir() . '/port-libs-recursive-merge-' . bin2hex(random_bytes(4));
$indexChecksum = MergeIndexFile::writeResult($demoRoot . '/.git/index', $result, $read);
$worktreeRoot = $demoRoot . '/worktree';
mkdir($worktreeRoot . '/.git', 0777, true);
mkdir($worktreeRoot . '/wp-content/plugins/old-plugin', 0777, true);
file_put_contents($worktreeRoot . '/.git/config', "[core]\n");
file_put_contents($worktreeRoot . '/wp-content/plugins/old-plugin/bootstrap.php', "<?php\nreturn 'stale';\n");
$worktreeFiles = MergeWorktreeWriter::checkoutMergedTree($result, $worktreeRoot, $read);
$themeJsonPath = $worktreeRoot . '/wp-content/themes/acme/theme.json';

echo json_encode([
    'clean' => $result->isClean(),
    'conflicts' => array_map(
        static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
        $result->conflicts,
    ),
    'indexStages' => array_map(
        static fn (MergeIndexEntry $entry): array => [
            'path' => $entry->path,
            'stage' => $entry->stage,
            'side' => $entry->side(),
            'oid' => $entry->oid,
        ],
        $result->indexEntries(),
    ),
    'worktreeConflictFiles' => array_map(
        static fn ($file): array => [
            'path' => $file->path,
            'oid' => $file->oid,
            'containsMarkers' => str_contains($file->content, '<<<<<<<'),
        ],
        $result->worktreeConflictFiles($read),
    ),
    'writtenIndex' => [
        'path' => $demoRoot . '/.git/index',
        'checksum' => $indexChecksum,
        'stages' => count(MergeIndexFile::entriesFromBytes((string) file_get_contents($demoRoot . '/.git/index'))),
    ],
    'writtenWorktree' => [
        'root' => $worktreeRoot,
        'files' => array_map(static fn ($file): string => $file->path, $worktreeFiles),
        'themeJsonContainsMarkers' => str_contains((string) file_get_contents($themeJsonPath), '<<<<<<<'),
        'stalePluginRemoved' => !file_exists($worktreeRoot . '/wp-content/plugins/old-plugin/bootstrap.php'),
        'gitMetadataPreserved' => is_file($worktreeRoot . '/.git/config'),
    ],
    'mergedMetadata' => $metadata->body,
    'virtualMergeBase' => [
        'clean' => $virtualResult->isClean(),
        'entries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $virtualResult->tree->entries),
        'conflicts' => array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $virtualResult->conflicts,
        ),
        'ancestorStageContainsBothBaseEdits' => isset($virtualIndexEntries[0])
            && str_contains($read($virtualIndexEntries[0]->oid)->body, 'Requires PHP: 8.1')
            && str_contains($read($virtualIndexEntries[0]->oid)->body, 'Text Domain: acme'),
    ],
    'nestedDirectoryRename' => [
        'clean' => $nestedResult->isClean(),
        'conflicts' => array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $nestedResult->conflicts,
        ),
        'pluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $nestedPlugin->entries),
        'expandedIndexStages' => array_map(
            static fn (MergeIndexEntry $entry): array => [
                'path' => $entry->path,
                'stage' => $entry->stage,
                'side' => $entry->side(),
            ],
            MergeIndexFile::entriesForResult($nestedResult, $read),
        ),
        'restRouteCopies' => [
            'includes' => $read($nestedIncludes->entryNamed('rest.php')?->oid ?? '')->body,
            'src' => $read($nestedSrc->entryNamed('rest.php')?->oid ?? '')->body,
        ],
    ],
    'sameTargetNestedRename' => [
        'clean' => $sameTargetNestedResult->isClean(),
        'conflicts' => array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $sameTargetNestedResult->conflicts,
        ),
        'pluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $sameTargetPlugin->entries),
        'srcEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $sameTargetSrc->entries),
        'routeContainsMarkers' => str_contains($sameTargetRoute, '<<<<<<<'),
        'expandedIndexStages' => array_map(
            static fn (MergeIndexEntry $entry): array => [
                'path' => $entry->path,
                'stage' => $entry->stage,
                'side' => $entry->side(),
            ],
            MergeIndexFile::entriesForResult($sameTargetNestedResult, $read),
        ),
        'worktreeConflictFiles' => array_map(
            static fn ($file): string => $file->path,
            $sameTargetNestedResult->worktreeConflictFiles($read),
        ),
    ],
    'subtreeReplacementRename' => [
        'clean' => $subtreeReplacementResult->isClean(),
        'conflicts' => array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $subtreeReplacementResult->conflicts,
        ),
        'pluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $subtreeReplacementMergedPlugin->entries),
        'includesEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $subtreeReplacementIncludes->entries),
        'rootRestRoute' => $read($subtreeReplacementMergedPlugin->entryNamed('rest.php')?->oid ?? '')->body,
        'bootstrapRoute' => $read($subtreeReplacementMergedPlugin->entryNamed('bootstrap.php')?->oid ?? '')->body,
        'expandedIndexStages' => array_map(
            static fn (MergeIndexEntry $entry): array => [
                'path' => $entry->path,
                'stage' => $entry->stage,
                'side' => $entry->side(),
            ],
            MergeIndexFile::entriesForResult($subtreeReplacementResult, $read),
        ),
        'worktreeConflictFiles' => array_map(
            static fn ($file): string => $file->path,
            $subtreeReplacementResult->worktreeConflictFiles($read),
        ),
    ],
    'symlinkRenameResolution' => [
        'cleanBeforeResolution' => $symlinkConflictResult->isClean(),
        'conflicts' => array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $symlinkConflictResult->conflicts,
        ),
        'oursResolvedClean' => $symlinkOursResolved->isClean(),
        'muPluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $symlinkResolvedMuPlugins->entries),
        'resolvedSymlinkTarget' => $symlinkResolvedEntry === null ? null : $read($symlinkResolvedEntry->oid)->body,
        'bootstrapVersion' => $read($symlinkResolvedPlugin->entryNamed('bootstrap.php')?->oid ?? '')->body,
        'indexStagesAfterResolution' => count($symlinkOursResolved->indexEntries()),
    ],
    'renameTargetAddSymlinkResolution' => [
        'cleanBeforeResolution' => $targetAddResult->isClean(),
        'conflicts' => array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $targetAddResult->conflicts,
        ),
        'ancestorResolvedClean' => $targetAddAncestorResolved->isClean(),
        'ancestorMuPluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $targetAddAncestorMuPlugins->entries),
        'oursResolvedClean' => $targetAddOursResolved->isClean(),
        'oursMuPluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $targetAddOursMuPlugins->entries),
        'oursActiveKind' => $targetAddOursEntry?->kind(),
        'oursActiveTarget' => $targetAddOursEntry === null ? null : $read($targetAddOursEntry->oid)->body,
        'indexStagesAfterAncestorResolution' => count($targetAddAncestorResolved->indexEntries()),
        'indexStagesAfterOursResolution' => count($targetAddOursResolved->indexEntries()),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
