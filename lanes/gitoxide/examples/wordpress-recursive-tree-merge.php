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
$commit = static fn (string $name, string $oid): TreeEntry => new TreeEntry('160000', $name, $oid);
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
$nestedAncestorResolved = $nestedResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
$nestedOursResolved = $nestedResult->resolveTreeConflicts(
    $read,
    $write,
    TreeMergeResult::RESOLVE_OURS,
    TreeMergeResult::RESOLVE_OURS,
);
$nestedAncestorPlugins = $treeAtPath($nestedAncestorResolved->tree, 'wp-content/plugins');
$nestedAncestorAcme = $treeAtPath($nestedAncestorResolved->tree, 'wp-content/plugins/acme');
$nestedAncestorIncludes = $treeAtPath($nestedAncestorResolved->tree, 'wp-content/plugins/acme/includes');
$nestedAncestorAcmePro = $treeAtPath($nestedAncestorResolved->tree, 'wp-content/plugins/acme-pro');
$nestedOursPlugin = $treeAtPath($nestedOursResolved->tree, 'wp-content/plugins/acme-pro');
$nestedOursIncludes = $treeAtPath($nestedOursResolved->tree, 'wp-content/plugins/acme-pro/includes');
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
$subtreeReplacementAncestorResolved = $subtreeReplacementResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
$subtreeReplacementOursResolved = $subtreeReplacementResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
$subtreeReplacementTheirsResolved = $subtreeReplacementResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_THEIRS);
$subtreeReplacementAncestorPlugin = $treeAtPathOrEmpty($subtreeReplacementAncestorResolved->tree, 'wp-content/plugins/acme');
$subtreeReplacementAncestorCleanRename = $treeAtPathOrEmpty($subtreeReplacementAncestorResolved->tree, 'wp-content/plugins/acme-pro');
$subtreeReplacementOursPlugin = $treeAtPathOrEmpty($subtreeReplacementOursResolved->tree, 'wp-content/plugins/acme-pro');
$subtreeReplacementTheirsPlugin = $treeAtPathOrEmpty($subtreeReplacementTheirsResolved->tree, 'wp-content/plugins/acme-pro');
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
$typeChangeBase = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $symlink('active-loader.php', '../plugins/acme/bootstrap.php'),
        ])),
        $tree('plugins', new Tree([
            $tree('acme', new Tree([
                $blob('bootstrap.php', "Plugin Name: Acme\nVersion: 1.0\n"),
            ])),
        ])),
    ])),
]);
$typeChangeOurs = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $blob('active-loader.php', "<?php\nrequire __DIR__ . '/../plugins/acme/bootstrap.php';\n"),
        ])),
        $tree('plugins', new Tree([
            $tree('acme', new Tree([
                $blob('bootstrap.php', "Plugin Name: Acme\nVersion: 1.0\n"),
            ])),
        ])),
    ])),
]);
$typeChangeTheirs = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $symlink('renamed-loader.php', '../plugins/acme/bootstrap.php'),
        ])),
        $tree('plugins', new Tree([
            $tree('acme', new Tree([
                $blob('bootstrap.php', "Plugin Name: Acme\nVersion: 1.0\n"),
            ])),
        ])),
    ])),
]);
$typeChangeResult = TreeMerge::mergeRecursive($typeChangeBase, $typeChangeOurs, $typeChangeTheirs, $read, $write);
$typeChangeTheirsResolved = $typeChangeResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_THEIRS);
$typeChangeResolvedMuPlugins = $treeAtPath($typeChangeTheirsResolved->tree, 'wp-content/mu-plugins');
$typeChangeResolvedEntry = $typeChangeResolvedMuPlugins->entryNamed('renamed-loader.php');
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
$directoryFileBase = new Tree([
    $tree('wp-content', new Tree([
        $blob('cache', "legacy cache marker\n"),
    ])),
]);
$directoryFileOurs = new Tree([
    $tree('wp-content', new Tree([
        $blob('cache', "drop-in cache marker\n"),
    ])),
]);
$directoryFileTheirs = new Tree([
    $tree('wp-content', new Tree([
        $tree('cache', new Tree([
            $blob('index.php', "<?php\n// Silence is golden.\n"),
        ])),
    ])),
]);
$directoryFileResult = TreeMerge::mergeRecursive($directoryFileBase, $directoryFileOurs, $directoryFileTheirs, $read, $write);
$directoryFileOursResolved = $directoryFileResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
$directoryFileAncestorResolved = $directoryFileResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
$directoryFileReverseResult = TreeMerge::mergeRecursive($directoryFileBase, $directoryFileTheirs, $directoryFileOurs, $read, $write);
$directoryFileReverseOursResolved = $directoryFileReverseResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
$directoryFileResolvedContent = $treeAtPath($directoryFileOursResolved->tree, 'wp-content');
$directoryFileResolvedEntry = $directoryFileResolvedContent->entryNamed('cache');
$directoryFileAncestorContent = $treeAtPath($directoryFileAncestorResolved->tree, 'wp-content');
$directoryFileAncestorEntry = $directoryFileAncestorContent->entryNamed('cache');
$directoryFileReverseResolvedCache = $treeAtPath($directoryFileReverseOursResolved->tree, 'wp-content/cache');
$directoryRenameConflictBase = new Tree([
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $tree('acme', $pluginTree('includes', $nestedBaseRoutes)),
        ])),
    ])),
]);
$directoryRenameConflictOurs = new Tree([
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $tree('acme-pro', $pluginTree('includes', $nestedOursRoutes)),
        ])),
    ])),
]);
$directoryRenameConflictTheirs = new Tree([
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $tree('acme-live', $pluginTree('includes', $nestedTheirsRoutes)),
        ])),
    ])),
]);
$directoryRenameConflictResult = TreeMerge::mergeRecursive(
    $directoryRenameConflictBase,
    $directoryRenameConflictOurs,
    $directoryRenameConflictTheirs,
    $read,
    $write,
);
$directoryRenameConflictResolved = $directoryRenameConflictResult->resolveTreeConflicts(
    $read,
    $write,
    TreeMergeResult::RESOLVE_OURS,
    TreeMergeResult::RESOLVE_OURS,
);
$directoryRenameResolvedPlugins = $treeAtPath($directoryRenameConflictResolved->tree, 'wp-content/plugins');
$directoryRenameResolvedPlugin = $treeAtPath($directoryRenameConflictResolved->tree, 'wp-content/plugins/acme-pro');
$directoryRenameResolvedIncludes = $treeAtPath($directoryRenameConflictResolved->tree, 'wp-content/plugins/acme-pro/includes');
$directoryRenameResolvedRoute = $read($directoryRenameResolvedIncludes->entryNamed('rest.php')?->oid ?? '')->body;
$reciprocalRenameBase = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $blob('bar-loader.php', "<?php\n// Loader B\n"),
            $blob('foo-loader.php', "<?php\n// Loader A\n"),
        ])),
    ])),
]);
$reciprocalRenameOurs = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $blob('shared-loader.php', "<?php\n// Loader A\n"),
        ])),
    ])),
]);
$reciprocalRenameTheirs = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $blob('shared-loader.php', "<?php\n// Loader B\n"),
        ])),
    ])),
]);
$reciprocalRenameResult = TreeMerge::mergeRecursive(
    $reciprocalRenameBase,
    $reciprocalRenameOurs,
    $reciprocalRenameTheirs,
    $read,
    $write,
);
$reciprocalRenameAncestorResolved = $reciprocalRenameResult->resolveTreeConflicts(
    $read,
    $write,
    TreeMergeResult::RESOLVE_ANCESTOR,
    TreeMergeResult::RESOLVE_ANCESTOR,
);
$reciprocalRenameOursResolved = $reciprocalRenameResult->resolveTreeConflicts(
    $read,
    $write,
    TreeMergeResult::RESOLVE_OURS,
    TreeMergeResult::RESOLVE_OURS,
);
$reciprocalAncestorMuPlugins = $treeAtPath($reciprocalRenameAncestorResolved->tree, 'wp-content/mu-plugins');
$reciprocalOursMuPlugins = $treeAtPath($reciprocalRenameOursResolved->tree, 'wp-content/mu-plugins');
$reciprocalOursSharedLoader = $reciprocalOursMuPlugins->entryNamed('shared-loader.php');
$submoduleBaseOid = 'e835c0c403c8e494c0ca98f3d25d0b8464c18d38';
$submoduleOursOid = '64466ebdff775ad618d9cc993cf52840e0af528c';
$submoduleTheirsOid = 'ea6eb701e03c2497915c25a851f3da8f8e362ca0';
$submoduleDependencyTree = static fn (string $oid): Tree => new Tree([
    $tree('wp-content', new Tree([
        $tree('plugins', new Tree([
            $tree('acme', new Tree([
                $tree('vendor', new Tree([
                    $commit('acme-lib', $oid),
                ])),
            ])),
        ])),
    ])),
]);
$submoduleResult = TreeMerge::mergeRecursive(
    $submoduleDependencyTree($submoduleBaseOid),
    $submoduleDependencyTree($submoduleOursOid),
    $submoduleDependencyTree($submoduleTheirsOid),
    $read,
    $write,
);
$submoduleAncestorResolved = $submoduleResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
$submoduleOursResolved = $submoduleResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
$submoduleTheirsResolved = $submoduleResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_THEIRS);
$submoduleAncestorVendor = $treeAtPath($submoduleAncestorResolved->tree, 'wp-content/plugins/acme/vendor');
$submoduleOursVendor = $treeAtPath($submoduleOursResolved->tree, 'wp-content/plugins/acme/vendor');
$submoduleTheirsVendor = $treeAtPath($submoduleTheirsResolved->tree, 'wp-content/plugins/acme/vendor');
$submoduleAncestorEntry = $submoduleAncestorVendor->entryNamed('acme-lib');
$submoduleOursEntry = $submoduleOursVendor->entryNamed('acme-lib');
$submoduleTheirsEntry = $submoduleTheirsVendor->entryNamed('acme-lib');
$changeDeleteBase = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $symlink('acme-loader.php', '../plugins/acme/bootstrap.php'),
        ])),
        $tree('plugins', new Tree([
            $tree('acme', new Tree([
                $blob('bootstrap.php', "Plugin Name: Acme\nVersion: 1.0\n"),
            ])),
        ])),
    ])),
]);
$changeDeleteOurs = new Tree([
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([
            $blob('acme-loader.php', "<?php\nrequire __DIR__ . '/../plugins/acme/bootstrap.php';\n"),
        ])),
        $tree('plugins', new Tree([
            $tree('acme', new Tree([
                $blob('bootstrap.php', "Plugin Name: Acme\nVersion: 1.1\n"),
            ])),
        ])),
    ])),
]);
$changeDeleteTheirs = new Tree([]);
$changeDeleteResult = TreeMerge::mergeRecursive($changeDeleteBase, $changeDeleteOurs, $changeDeleteTheirs, $read, $write);
$changeDeleteAncestorResolved = $changeDeleteResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_ANCESTOR);
$changeDeleteOursResolved = $changeDeleteResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_OURS);
$changeDeleteTheirsResolved = $changeDeleteResult->resolveTreeConflicts($read, $write, TreeMergeResult::RESOLVE_THEIRS);
$changeDeleteAncestorContent = $treeAtPathOrEmpty($changeDeleteAncestorResolved->tree, 'wp-content');
$changeDeleteOursMuPlugins = $treeAtPathOrEmpty($changeDeleteOursResolved->tree, 'wp-content/mu-plugins');
$changeDeleteOursPlugin = $treeAtPathOrEmpty($changeDeleteOursResolved->tree, 'wp-content/plugins/acme');
$changeDeleteOursLoader = $changeDeleteOursMuPlugins->entryNamed('acme-loader.php');
$changeDeleteOursBootstrap = $changeDeleteOursPlugin->entryNamed('bootstrap.php');
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
        'ancestorResolvedClean' => $nestedAncestorResolved->isClean(),
        'ancestorPluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $nestedAncestorPlugins->entries),
        'ancestorAcmeEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $nestedAncestorAcme->entries),
        'ancestorAcmeProEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $nestedAncestorAcmePro->entries),
        'ancestorRouteCopy' => $read($nestedAncestorIncludes->entryNamed('rest.php')?->oid ?? '')->body,
        'ancestorIndexStages' => count(MergeIndexFile::entriesForResult($nestedAncestorResolved, $read)),
        'oursResolvedClean' => $nestedOursResolved->isClean(),
        'oursResolvedPluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $nestedOursPlugin->entries),
        'oursResolvedIncludesEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $nestedOursIncludes->entries),
        'oursRouteCopy' => $read($nestedOursIncludes->entryNamed('rest.php')?->oid ?? '')->body,
        'oursIndexStages' => count(MergeIndexFile::entriesForResult($nestedOursResolved, $read)),
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
        'ancestorResolvedClean' => $subtreeReplacementAncestorResolved->isClean(),
        'ancestorPluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $subtreeReplacementAncestorPlugin->entries),
        'ancestorCleanRenamedEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $subtreeReplacementAncestorCleanRename->entries),
        'oursResolvedClean' => $subtreeReplacementOursResolved->isClean(),
        'oursResolvedPluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $subtreeReplacementOursPlugin->entries),
        'theirsResolvedClean' => $subtreeReplacementTheirsResolved->isClean(),
        'theirsResolvedPluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $subtreeReplacementTheirsPlugin->entries),
        'indexStagesAfterOursResolution' => count($subtreeReplacementOursResolved->indexEntries()),
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
    'typeChangeRenamedSymlinkResolution' => [
        'cleanBeforeResolution' => $typeChangeResult->isClean(),
        'conflicts' => array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $typeChangeResult->conflicts,
        ),
        'theirsResolvedClean' => $typeChangeTheirsResolved->isClean(),
        'muPluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $typeChangeResolvedMuPlugins->entries),
        'resolvedLoaderKind' => $typeChangeResolvedEntry?->kind(),
        'resolvedLoaderTarget' => $typeChangeResolvedEntry === null ? null : $read($typeChangeResolvedEntry->oid)->body,
        'indexStagesAfterResolution' => count($typeChangeTheirsResolved->indexEntries()),
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
    'directoryFileResolution' => [
        'cleanBeforeResolution' => $directoryFileResult->isClean(),
        'conflicts' => array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $directoryFileResult->conflicts,
        ),
        'ancestorResolvedClean' => $directoryFileAncestorResolved->isClean(),
        'ancestorCacheKind' => $directoryFileAncestorEntry?->kind(),
        'ancestorCacheBody' => $directoryFileAncestorEntry === null ? null : $read($directoryFileAncestorEntry->oid)->body,
        'oursResolvedClean' => $directoryFileOursResolved->isClean(),
        'contentEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $directoryFileResolvedContent->entries),
        'resolvedCacheKind' => $directoryFileResolvedEntry?->kind(),
        'resolvedCacheBody' => $directoryFileResolvedEntry === null ? null : $read($directoryFileResolvedEntry->oid)->body,
        'reverseOursResolvedClean' => $directoryFileReverseOursResolved->isClean(),
        'reverseOursCacheEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $directoryFileReverseResolvedCache->entries),
        'indexStagesAfterResolution' => count($directoryFileOursResolved->indexEntries()),
    ],
    'directoryRenameConflictResolution' => [
        'cleanBeforeResolution' => $directoryRenameConflictResult->isClean(),
        'conflicts' => array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $directoryRenameConflictResult->conflicts,
        ),
        'oursResolvedClean' => $directoryRenameConflictResolved->isClean(),
        'pluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $directoryRenameResolvedPlugins->entries),
        'resolvedPluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $directoryRenameResolvedPlugin->entries),
        'routeIncludesOtherSidePermissionCallback' => str_contains($directoryRenameResolvedRoute, 'permission_callback'),
        'routeContent' => $directoryRenameResolvedRoute,
        'indexStagesAfterResolution' => count($directoryRenameConflictResolved->indexEntries()),
    ],
    'reciprocalRenameDeleteResolution' => [
        'cleanBeforeResolution' => $reciprocalRenameResult->isClean(),
        'conflicts' => array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $reciprocalRenameResult->conflicts,
        ),
        'ancestorResolvedClean' => $reciprocalRenameAncestorResolved->isClean(),
        'ancestorMuPluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $reciprocalAncestorMuPlugins->entries),
        'oursResolvedClean' => $reciprocalRenameOursResolved->isClean(),
        'oursMuPluginEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $reciprocalOursMuPlugins->entries),
        'oursSharedLoaderBody' => $reciprocalOursSharedLoader === null ? null : $read($reciprocalOursSharedLoader->oid)->body,
        'indexStagesAfterAncestorResolution' => count($reciprocalRenameAncestorResolved->indexEntries()),
        'indexStagesAfterOursResolution' => count($reciprocalRenameOursResolved->indexEntries()),
    ],
    'submoduleCommitResolution' => [
        'cleanBeforeResolution' => $submoduleResult->isClean(),
        'conflicts' => array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $submoduleResult->conflicts,
        ),
        'indexStages' => array_map(
            static fn (MergeIndexEntry $entry): array => [
                'path' => $entry->path,
                'stage' => $entry->stage,
                'side' => $entry->side(),
                'oid' => $entry->oid,
            ],
            $submoduleResult->indexEntries(),
        ),
        'ancestorResolvedClean' => $submoduleAncestorResolved->isClean(),
        'ancestorDependencyOid' => $submoduleAncestorEntry?->oid,
        'oursResolvedClean' => $submoduleOursResolved->isClean(),
        'oursDependencyOid' => $submoduleOursEntry?->oid,
        'theirsResolvedClean' => $submoduleTheirsResolved->isClean(),
        'theirsDependencyOid' => $submoduleTheirsEntry?->oid,
        'indexStagesAfterOursResolution' => count($submoduleOursResolved->indexEntries()),
        'worktreeConflictFiles' => array_map(
            static fn ($file): string => $file->path,
            $submoduleResult->worktreeConflictFiles($read),
        ),
    ],
    'changeDeleteResolution' => [
        'cleanBeforeResolution' => $changeDeleteResult->isClean(),
        'conflicts' => array_map(
            static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
            $changeDeleteResult->conflicts,
        ),
        'ancestorResolvedClean' => $changeDeleteAncestorResolved->isClean(),
        'ancestorContentEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $changeDeleteAncestorContent->entries),
        'oursResolvedClean' => $changeDeleteOursResolved->isClean(),
        'oursLoaderKind' => $changeDeleteOursLoader?->kind(),
        'oursLoaderBody' => $changeDeleteOursLoader === null ? null : $read($changeDeleteOursLoader->oid)->body,
        'oursBootstrapBody' => $changeDeleteOursBootstrap === null ? null : $read($changeDeleteOursBootstrap->oid)->body,
        'theirsResolvedClean' => $changeDeleteTheirsResolved->isClean(),
        'theirsRootEntries' => array_map(static fn (TreeEntry $entry): string => $entry->filename, $changeDeleteTheirsResolved->tree->entries),
        'indexStagesAfterOursResolution' => count($changeDeleteOursResolved->indexEntries()),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
