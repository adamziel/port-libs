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
$directoryExcludePathspec = SparseCheckoutSpec::fromPathspecs([
    ':/wp-content',
    ':!/wp-content/cache/',
], prefix: 'wp-content');
$deploymentRoot = '/srv/www/example.com/current';
$absolutePathspec = SparseCheckoutSpec::fromPathspecs([
    $deploymentRoot . '/wp-content/plugins/gutenberg/block.json',
    ':(icase)' . $deploymentRoot . '/wp-content/plugins/gutenberg/readme.md',
    ':(exclude)' . $deploymentRoot . '/wp-content/plugins/gutenberg/build/',
], root: $deploymentRoot);
$absoluteWildcardPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(icase)' . $deploymentRoot . '/*/readme.md',
], root: $deploymentRoot);
$ordinaryAbsoluteWildcardPathspec = SparseCheckoutSpec::fromPathspecs([
    $deploymentRoot . '/*/readme.md',
], root: $deploymentRoot);
$pathAwareDefaultPathspec = SparseCheckoutSpec::fromPathspecs(
    ['wp-content/plugins/*'],
    defaultSearchMode: SparseCheckoutSpec::PATHSPEC_SEARCH_PATH_AWARE_GLOB,
);
$noGlobDefaultPathspec = SparseCheckoutSpec::fromPathspecs(
    ['wp-content/plugins/*.php', ':(glob)wp-content/mu-plugins/*.php'],
    defaultSearchMode: SparseCheckoutSpec::PATHSPEC_SEARCH_LITERAL,
);
$literalDefaultPathspec = SparseCheckoutSpec::fromPathspecs(
    [':(glob)wp-content/plugins/*.php', ':'],
    literalDefault: true,
);
$filter = FetchFilterSpec::blobNone();

$root = new Tree([
    new TreeEntry('100644', 'index.php', $blob),
    new TreeEntry('100644', 'wp-config.php', $blob),
    new TreeEntry('040000', 'wp-admin', $tree),
    new TreeEntry('040000', 'wp-content', $tree),
]);
$wpContent = new Tree([
    new TreeEntry('100644', 'index.php', $blob),
    new TreeEntry('040000', 'cache', $tree),
    new TreeEntry('040000', 'cache-busting', $tree),
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
    'directoryOnlyCacheDirectorySkipped' => $directoryExcludePathspec->skipWorktree('wp-content/cache', true),
    'directoryOnlyCacheFileNameIncluded' => $directoryExcludePathspec->includesPath('wp-content/cache', false),
    'directoryOnlyCacheDescendantSkipped' => $directoryExcludePathspec->skipWorktree('wp-content/cache/page.html', false),
    'directoryOnlyCacheBustingIncluded' => $directoryExcludePathspec->includesPath('wp-content/cache-busting/loader.php', false),
    'directoryOnlyEntriesToMaterialize' => $entryNames($directoryExcludePathspec->includedTreeEntries($wpContent, 'wp-content')),
    'absoluteRootPathspecBlockIncluded' => $absolutePathspec->includesPath('wp-content/plugins/gutenberg/block.json', false),
    'absoluteRootPathspecIcaseReadmeIncluded' => $absolutePathspec->includesPath('wp-content/plugins/gutenberg/README.md', false),
    'absoluteRootPathspecUpperPrefixSkipped' => $absolutePathspec->skipWorktree('WP-CONTENT/plugins/gutenberg/README.md', false),
    'absoluteRootPathspecBuildSkipped' => $absolutePathspec->skipWorktree('wp-content/plugins/gutenberg/build/index.js', false),
    'absoluteWildcardIcaseRealDirectorySkipped' => $absoluteWildcardPathspec->skipWorktree('wp-content/README.md', false),
    'absoluteWildcardIcaseLiteralStarIncluded' => $absoluteWildcardPathspec->includesPath('*/README.md', false),
    'absoluteWildcardOrdinaryGlobIncluded' => $ordinaryAbsoluteWildcardPathspec->includesPath('wp-content/readme.md', false),
    'pathAwareDefaultNestedPluginSkipped' => $pathAwareDefaultPathspec->skipWorktree('wp-content/plugins/gutenberg/block.json', false),
    'pathAwareDefaultPluginDirectoryIncluded' => $pathAwareDefaultPathspec->includesPath('wp-content/plugins/gutenberg', true),
    'noGlobDefaultLiteralPluginIncluded' => $noGlobDefaultPathspec->includesPath('wp-content/plugins/*.php', false),
    'noGlobDefaultWildcardPluginSkipped' => $noGlobDefaultPathspec->skipWorktree('wp-content/plugins/gutenberg.php', false),
    'noGlobDefaultMagicGlobOverrideIncluded' => $noGlobDefaultPathspec->includesPath('wp-content/mu-plugins/loader.php', false),
    'literalDefaultMagicTextIncluded' => $literalDefaultPathspec->includesPath(':(glob)wp-content/plugins/*.php', false),
    'literalDefaultColonIsLiteral' => $literalDefaultPathspec->includesPath(':', false),
    'literalDefaultAdminSkipped' => $literalDefaultPathspec->skipWorktree('wp-admin/admin.php', false),
];
