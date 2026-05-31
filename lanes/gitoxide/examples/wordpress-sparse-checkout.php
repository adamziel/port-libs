<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\FetchFilterSpec;
use PortLibs\Gitoxide\SparseCheckoutSpec;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$blob = str_repeat('1', 40);
$tree = str_repeat('2', 40);
$sparse = SparseCheckoutSpec::cone(['wp-content/plugins/gutenberg']);
$pathspec = SparseCheckoutSpec::fromPathspecs([
    'wp-content/**',
    ':!wp-content/cache/**',
    ':(top,glob,icase)WP-CONTENT/Plugins/*/block.json',
]);
$wildmatchPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content/plugins/[ag]*/block.[jt]son',
    ':(exclude,glob)wp-content/cache/**',
    ':(glob)wp-content/**/theme.\?son',
]);
$prefixedPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)*.php',
    ':(icase)BLOCK.JSON',
    ':(top)wp-config.php',
    ':(exclude,glob)build/**',
], prefix: 'wp-content/plugins/gutenberg');
$filter = FetchFilterSpec::blobNone();

$root = new Tree([
    new TreeEntry('100644', 'index.php', $blob),
    new TreeEntry('100644', 'wp-config.php', $blob),
    new TreeEntry('040000', 'wp-admin', $tree),
    new TreeEntry('040000', 'wp-content', $tree),
]);
$wpContent = new Tree([
    new TreeEntry('100644', 'index.php', $blob),
    new TreeEntry('040000', 'plugins', $tree),
    new TreeEntry('040000', 'uploads', $tree),
]);
$plugins = new Tree([
    new TreeEntry('100644', 'plugin-loader.php', $blob),
    new TreeEntry('040000', 'akismet', $tree),
    new TreeEntry('040000', 'gutenberg', $tree),
]);

$entryNames = static fn (array $entries): array => array_map(
    static fn (TreeEntry $entry): string => $entry->filename,
    $entries
);

return [
    'fetchFilter' => (string) $filter,
    'sparseMode' => $sparse->mode,
    'recursiveDirectories' => $sparse->recursiveDirectories(),
    'parentDirectories' => $sparse->parentDirectories(),
    'rootEntriesToMaterialize' => $entryNames($sparse->includedTreeEntries($root)),
    'wpContentEntriesToMaterialize' => $entryNames($sparse->includedTreeEntries($wpContent, 'wp-content')),
    'pluginEntriesToMaterialize' => $entryNames($sparse->includedTreeEntries($plugins, 'wp-content/plugins')),
    'akismetSkipped' => $sparse->skipWorktree('wp-content/plugins/akismet/akismet.php', false),
    'gutenbergBlockIncluded' => $sparse->includesPath('wp-content/plugins/gutenberg/block.json', false),
    'pathspecPluginBlockIncluded' => $pathspec->includesPath('WP-CONTENT/Plugins/Gutenberg/block.json', false),
    'pathspecCacheSkipped' => $pathspec->skipWorktree('wp-content/cache/page.html', false),
    'pathspecAdminSkipped' => $pathspec->skipWorktree('wp-admin/admin.php', false),
    'pathspecBracketPluginBlockIncluded' => $wildmatchPathspec->includesPath('wp-content/plugins/akismet/block.json', false),
    'pathspecCacheExcludeAuthoritative' => $wildmatchPathspec->skipWorktree('wp-content/cache/page.html', false),
    'pathspecRecursiveEscapedThemeIncluded' => $wildmatchPathspec->includesPath('wp-content/themes/site/theme.?son', false),
    'prefixedPathspecIndexIncluded' => $prefixedPathspec->includesPath('wp-content/plugins/gutenberg/index.php', false),
    'prefixedPathspecNestedPhpSkipped' => $prefixedPathspec->skipWorktree('wp-content/plugins/gutenberg/src/editor.php', false),
    'prefixedPathspecIcaseFileIncluded' => $prefixedPathspec->includesPath('wp-content/plugins/gutenberg/block.json', false),
    'prefixedPathspecUpperPrefixSkipped' => $prefixedPathspec->skipWorktree('WP-CONTENT/plugins/gutenberg/block.json', false),
    'prefixedTopConfigIncluded' => $prefixedPathspec->includesPath('wp-config.php', false),
];
