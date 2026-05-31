<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\PathspecSearch;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;
use PortLibs\Gitoxide\TreePathspecWalk;
use PortLibs\Gitoxide\TreeWalkEntry;

$objects = [];
$blobOid = str_repeat('1', 40);
$blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
$tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
    $object = $tree->toObject();
    $objects[$object->oid()] = $object;

    return new TreeEntry('040000', $name, $object->oid());
};

$root = new Tree([
    $blob('index.php'),
    $tree('wp-admin', new Tree([$blob('admin.php')])),
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([$blob('Loader.PHP')])),
        $tree('plugins', new Tree([
            $tree('akismet', new Tree([$blob('akismet.php'), $blob('block.json')])),
            $tree('gutenberg', new Tree([
                $blob('block.json'),
                $blob('block.gson'),
                $tree('build', new Tree([$blob('index.js')])),
                $tree('src', new Tree([$blob('editor.js')])),
            ])),
            $tree('[literal]', new Tree([$blob('block.?son')])),
        ])),
        $tree('themes', new Tree([
            $tree('acme', new Tree([$blob('theme.json'), $blob('theme.?son'), $blob('style.css')])),
        ])),
        $blob('theme.?son'),
        $tree('uploads', new Tree([
            $tree('2026', new Tree([
                $blob('[hero].jpg'),
                $tree('05', new Tree([$blob('hero.jpg')])),
                $tree('02', new Tree([$blob('hero.jpg')])),
            ])),
        ])),
    ])),
]);

$pathspecs = PathspecSearch::fromSpecs([
    'wp-content/plugins/gutenberg/',
    ':(glob)wp-content/themes/*/theme.json',
    ':!wp-content/plugins/gutenberg/build/',
    ':(icase)WP-CONTENT/MU-PLUGINS/*.PHP',
    ':(literal)wp-content/uploads/2026/[hero].jpg',
]);
$wildmatchPathspecs = PathspecSearch::fromSpecs([
    ':(glob)wp-content/plugins/[ag]*/block.[jt]son',
    ':(glob)wp-content/uploads/2026/0[!1-4]/**',
    ':(glob)wp-content/**/theme.\?son',
    ':(glob)wp-content/plugins/\[literal\]/block.\?son',
]);
$prefixedPathspecs = PathspecSearch::fromSpecs([':(icase)mu-plugins/*.php'], 'WP-CONTENT');

$records = TreePathspecWalk::breadthFirst(
    $root,
    $pathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$allRecords = TreePathspecWalk::breadthFirst(
    $root,
    PathspecSearch::fromSpecs([]),
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$wildmatchRecords = TreePathspecWalk::breadthFirst(
    $root,
    $wildmatchPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);

return [
    'matchedContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $records),
    'matchKinds' => array_map(static fn (TreeWalkEntry $entry): string => $entry->matchKind, $records),
    'wildmatchContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $wildmatchRecords),
    'noPathspecWalkCount' => count($allRecords),
    'noPathspecAdminIncluded' => in_array('wp-admin/admin.php', array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $allRecords), true),
    'noPathspecGeneratedBuildIncluded' => in_array('wp-content/plugins/gutenberg/build/index.js', array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $allRecords), true),
    'gutenbergBuildSkipped' => !$pathspecs->isIncluded('wp-content/plugins/gutenberg/build/index.js', false),
    'literalUploadIncluded' => $pathspecs->isIncluded('wp-content/uploads/2026/[hero].jpg', false),
    'caseFoldedMuPluginIncluded' => $pathspecs->isIncluded('wp-content/mu-plugins/Loader.PHP', false),
    'wildmatchRecursiveThemeAtRoot' => $wildmatchPathspecs->isIncluded('wp-content/theme.?son', false),
    'wildmatchEscapedLiteralBlockIncluded' => $wildmatchPathspecs->isIncluded('wp-content/plugins/[literal]/block.?son', false),
    'wildmatchNegatedUploadRangeSkipped' => !$wildmatchPathspecs->isIncluded('wp-content/uploads/2026/02/hero.jpg', false),
    'pathAwareSlashClassSkipped' => !PathspecSearch::fromSpecs([':(glob)wp-content/plugins/foo[/]bar.php'])->isIncluded('wp-content/plugins/foo/bar.php', false),
    'shellSlashClassIncluded' => PathspecSearch::fromSpecs(['wp-content/plugins/foo[/]bar.php'])->isIncluded('wp-content/plugins/foo/bar.php', false),
    'prefixCaseSensitiveUpperContentIncluded' => $prefixedPathspecs->isIncluded('WP-CONTENT/mu-plugins/loader.php', false),
    'prefixCaseSensitiveLowerContentSkipped' => !$prefixedPathspecs->isIncluded('wp-content/mu-plugins/Loader.PHP', false),
];
