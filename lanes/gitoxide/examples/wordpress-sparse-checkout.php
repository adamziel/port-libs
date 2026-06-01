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
$nonConePatternFilePathspec = SparseCheckoutSpec::fromNonConePatternFile(
    "\xEF\xBB\xBFwp-content/mu-plugins/**   \n"
    . "wp-content/plugins/**   \n"
    . "!wp-content/plugins/cache/**   \n"
    . "\\#literal-plugin.php   \n"
    . "\\!literal-plugin.php   \n"
    . "wp-content/uploads/hero\\  \n"
    . "  \t  \n"
);
$nonConeExtraSlashPathspec = SparseCheckoutSpec::fromNonConePatternFile(
    "//wp-content/cache/**\n"
    . "wp-content/generated///\n"
    . "/wp-content/plugins/\n"
);
$nonConeLiteralAncestorPathspec = SparseCheckoutSpec::fromNonConePatternFile(
    "wp-content/plugins/gutenberg/src\n"
);
$wildmatchPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content/plugins/[ag]*/block.[jt]son',
    ':(exclude,glob)wp-content/cache/**',
    ':(glob)wp-content/**/theme.\?son',
]);
$backslashBytePathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content/plugins/f\\\\oo/block.json',
    ':(glob)wp-content/plugins/[[-\\]]/block.json',
]);
$posixClassPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content/uploads/slot[[:blank:]]/**',
    ':(glob)wp-content/uploads/[[:unknown:]]*.jpg',
]);
$posixSpaceClassPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content/uploads/slot[[:space:]]/**',
]);
$malformedPosixClassPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content/uploads/[[:alpha]/photo.jpg',
]);
$malformedPosixDigitPrefixPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content/uploads/[[:digit]ab]',
]);
$malformedPosixEmptyNamePrefixPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content/uploads/[[:]ab]',
]);
$malformedPosixDoubleColonPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content/uploads/[[::]ab]',
]);
$reversedRangePathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content/uploads/[z-a]/**',
]);
$negatedReversedRangePathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content/uploads/[!z-a]/**',
]);
$foldedReversedRangePathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob,icase)wp-content/uploads/[Z-A]/**',
]);
$doubleStarComponentPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content/**.php',
    ':(glob)wp-content/**/loader.php',
    ':(glob)wp-content/plugins**/block.json',
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
$negativeWildcardPathspec = SparseCheckoutSpec::fromPathspecs([
    'wp-content/**',
    ':(exclude,glob)wp-content/*-cache',
]);
$negativeWildcardExcludeOnlyPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(exclude,glob)wp-content/*-cache',
]);
$directoryOnlyWildcardExcludePathspec = SparseCheckoutSpec::fromPathspecs([
    'wp-content/**',
    ':(exclude,glob)wp-content/*-cache/',
]);
$negativeNilPathspec = SparseCheckoutSpec::fromPathspecs([
    ':!',
]);
$deploymentRoot = '/srv/www/example.com/current';
$absolutePathspec = SparseCheckoutSpec::fromPathspecs([
    $deploymentRoot . '/wp-content/plugins/gutenberg/block.json',
    ':(icase)' . $deploymentRoot . '/wp-content/plugins/gutenberg/readme.md',
    ':(exclude)' . $deploymentRoot . '/wp-content/plugins/gutenberg/build/',
], root: $deploymentRoot);
$absoluteBackslashLiteralPathspec = SparseCheckoutSpec::fromPathspecs(
    [$deploymentRoot . '/wp-content/plugins/f\\oo/block.json'],
    root: $deploymentRoot,
    defaultSearchMode: SparseCheckoutSpec::PATHSPEC_SEARCH_LITERAL,
);
$absoluteBackslashGlobPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)' . $deploymentRoot . '/wp-content/plugins/f\\\\oo/*.php',
], root: $deploymentRoot);
$absoluteBackslashOrdinaryPathspec = SparseCheckoutSpec::fromPathspecs([
    $deploymentRoot . '/wp-content/plugins/f\\oo/block.json',
], root: $deploymentRoot);
$escapedByteTraversalPathspec = SparseCheckoutSpec::fromPathspecs([
    'wp-content/plugins/f\\oo/block.json',
]);
$escapedSlashTraversalPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content/plugins/foo\\/block.json',
]);
$newlineByteShellStarPathspec = SparseCheckoutSpec::fromPathspecs([
    'wp-content*',
]);
$newlineByteShellQuestionPathspec = SparseCheckoutSpec::fromPathspecs([
    'wp-content?/plugins/gutenberg/block.json',
]);
$newlineBytePathAwareQuestionPathspec = SparseCheckoutSpec::fromPathspecs([
    ':(glob)wp-content?/plugins/gutenberg/block.json',
]);
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
$environmentLiteralPathspec = SparseCheckoutSpec::fromPathspecsWithEnvironment(
    [':(glob)wp-content/plugins/*.php', ':'],
    [
        'GIT_LITERAL_PATHSPECS' => 'yes',
        'GIT_ICASE_PATHSPECS' => '+10',
        'GIT_GLOB_PATHSPECS' => "yesn't",
        'GIT_NOGLOB_PATHSPECS' => 'true',
    ],
);
$environmentGlobPathspec = SparseCheckoutSpec::fromPathspecsWithEnvironment(
    ['wp-content/plugins/*.php'],
    ['GIT_GLOB_PATHSPECS' => '1'],
);
$environmentNoGlobPathspec = SparseCheckoutSpec::fromPathspecsWithEnvironment(
    ['wp-content/plugins/*.php', ':(glob)wp-content/mu-plugins/*.php'],
    ['GIT_NOGLOB_PATHSPECS' => 'on'],
);
$environmentFalseNoGlobPathspec = SparseCheckoutSpec::fromPathspecsWithEnvironment(
    ['wp-content/plugins/*.php'],
    [
        'GIT_GLOB_PATHSPECS' => 'true',
        'GIT_NOGLOB_PATHSPECS' => 'false',
    ],
);
$environmentIcasePathspec = SparseCheckoutSpec::fromPathspecsWithEnvironment(
    ['plugins/*.php'],
    ['GIT_ICASE_PATHSPECS' => '-1'],
    prefix: 'WP-CONTENT',
);
$environmentConflictRejected = false;
try {
    SparseCheckoutSpec::fromPathspecsWithEnvironment(
        ['wp-content/plugins/*.php'],
        [
            'GIT_GLOB_PATHSPECS' => 'true',
            'GIT_NOGLOB_PATHSPECS' => 'true',
        ],
    );
} catch (InvalidArgumentException) {
    $environmentConflictRejected = true;
}
$directoryOnlyUnknownPathspec = SparseCheckoutSpec::fromPathspecs([
    'wp-content/cache/',
]);
$prefixedEmptyPathspec = SparseCheckoutSpec::fromPathspecs(
    [],
    prefix: 'wp-content/themes',
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
    new TreeEntry('040000', 'generated-cache', $tree),
    new TreeEntry('040000', 'generated-cache-busting', $tree),
    new TreeEntry('040000', 'plugins', $tree),
    new TreeEntry('040000', 'uploads', $tree),
]);
$plugins = new Tree([
    new TreeEntry('100644', 'plugin-loader.php', $blob),
    new TreeEntry('040000', 'akismet', $tree),
    new TreeEntry('040000', 'gutenberg', $tree),
]);
$gutenberg = new Tree([
    new TreeEntry('100644', 'block.json', $blob),
    new TreeEntry('040000', 'build', $tree),
    new TreeEntry('040000', 'src', $tree),
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
    'nonConePatternFileBomMuPluginIncluded' => $nonConePatternFilePathspec->includesPath('wp-content/mu-plugins/loader.php', false),
    'nonConePatternFileTrailingSpacePluginIncluded' => $nonConePatternFilePathspec->includesPath('wp-content/plugins/gutenberg/block.json', false),
    'nonConePatternFileTrailingSpaceCacheSkipped' => $nonConePatternFilePathspec->skipWorktree('wp-content/plugins/cache/page.html', false),
    'nonConePatternFileEscapedHashLiteralIncluded' => $nonConePatternFilePathspec->includesPath('#literal-plugin.php', false),
    'nonConePatternFileEscapedBangLiteralIncluded' => $nonConePatternFilePathspec->includesPath('!literal-plugin.php', false),
    'nonConePatternFileEscapedTrailingSpaceIncluded' => $nonConePatternFilePathspec->includesPath('wp-content/uploads/hero ', false),
    'nonConePatternFileUnescapedTrailingSpaceSkipped' => $nonConePatternFilePathspec->skipWorktree('wp-content/uploads/hero', false),
    'nonConeExtraLeadingSlashCacheSkipped' => $nonConeExtraSlashPathspec->skipWorktree('wp-content/cache/page.html', false),
    'nonConeExtraTrailingSlashGeneratedSkipped' => $nonConeExtraSlashPathspec->skipWorktree('wp-content/generated/page.html', false),
    'nonConeSingleLeadingSlashPluginIncluded' => $nonConeExtraSlashPathspec->includesPath('wp-content/plugins/gutenberg/block.json', false),
    'nonConeExtraSlashEntriesToMaterialize' => $entryNames($nonConeExtraSlashPathspec->includedTreeEntries($wpContent, 'wp-content')),
    'nonConeLiteralAncestorRootEntriesToMaterialize' => $entryNames($nonConeLiteralAncestorPathspec->includedTreeEntries($root)),
    'nonConeLiteralAncestorWpContentEntriesToMaterialize' => $entryNames($nonConeLiteralAncestorPathspec->includedTreeEntries($wpContent, 'wp-content')),
    'nonConeLiteralAncestorPluginEntriesToMaterialize' => $entryNames($nonConeLiteralAncestorPathspec->includedTreeEntries($plugins, 'wp-content/plugins')),
    'nonConeLiteralAncestorGutenbergEntriesToMaterialize' => $entryNames($nonConeLiteralAncestorPathspec->includedTreeEntries($gutenberg, 'wp-content/plugins/gutenberg')),
    'nonConeLiteralAncestorFileNamedWpContentSkipped' => $nonConeLiteralAncestorPathspec->skipWorktree('wp-content', false),
    'nonConeLiteralAncestorGutenbergDirectoryIncluded' => $nonConeLiteralAncestorPathspec->includesPath('wp-content/plugins/gutenberg', true),
    'nonConeLiteralAncestorGutenbergFileSkipped' => $nonConeLiteralAncestorPathspec->skipWorktree('wp-content/plugins/gutenberg', false),
    'nonConeLiteralAncestorSrcIncluded' => $nonConeLiteralAncestorPathspec->includesPath('wp-content/plugins/gutenberg/src', true),
    'nonConeLiteralAncestorBuildSkipped' => $nonConeLiteralAncestorPathspec->skipWorktree('wp-content/plugins/gutenberg/build', true),
    'pathspecBracketPluginBlockIncluded' => $wildmatchPathspec->includesPath('wp-content/plugins/akismet/block.json', false),
    'pathspecCacheExcludeAuthoritative' => $wildmatchPathspec->skipWorktree('wp-content/cache/page.html', false),
    'pathspecRecursiveEscapedThemeIncluded' => $wildmatchPathspec->includesPath('wp-content/themes/site/theme.?son', false),
    'pathspecBackslashByteIncluded' => $backslashBytePathspec->includesPath('wp-content/plugins/f\\oo/block.json', false),
    'pathspecBackslashByteNotSeparator' => $backslashBytePathspec->skipWorktree('wp-content/plugins/f/oo/block.json', false),
    'pathspecBackslashBracketRangeIncluded' => $backslashBytePathspec->includesPath('wp-content/plugins/\\/block.json', false),
    'pathspecBackslashRangeDashSkipped' => $backslashBytePathspec->skipWorktree('wp-content/plugins/-/block.json', false),
    'pathspecPosixBlankOddWhitespaceIncluded' => $posixClassPathspec->includesPath("wp-content/uploads/slot\v/photo.jpg", false),
    'pathspecPosixSpaceTabSkipped' => $posixSpaceClassPathspec->skipWorktree("wp-content/uploads/slot\t/photo.jpg", false),
    'pathspecInvalidClassLiteralFallbackIncluded' => $posixClassPathspec->includesPath('wp-content/uploads/[[:unknown:]]*.jpg', false),
    'pathspecInvalidClassWildcardExpansionSkipped' => $posixClassPathspec->skipWorktree('wp-content/uploads/[[:unknown:]]hero.jpg', false),
    'pathspecMalformedPosixAlphaSkipped' => $malformedPosixClassPathspec->skipWorktree('wp-content/uploads/a/photo.jpg', false),
    'pathspecMalformedPosixOpenBracketIncluded' => $malformedPosixClassPathspec->includesPath('wp-content/uploads/[/photo.jpg', false),
    'pathspecMalformedPosixLiteralFallbackIncluded' => $malformedPosixClassPathspec->includesPath('wp-content/uploads/[[:alpha]/photo.jpg', false),
    'pathspecMalformedPosixDigitPrefixResumed' => $malformedPosixDigitPrefixPathspec->includesPath('wp-content/uploads/[ab]', false),
    'pathspecMalformedPosixEmptyNamePrefixResumed' => $malformedPosixEmptyNamePrefixPathspec->includesPath('wp-content/uploads/[ab]', false),
    'pathspecMalformedPosixDoubleColonSkipped' => $malformedPosixDoubleColonPathspec->skipWorktree('wp-content/uploads/[ab]', false),
    'pathspecReversedRangeStartIncluded' => $reversedRangePathspec->includesPath('wp-content/uploads/z/photo.jpg', false),
    'pathspecReversedRangeMiddleSkipped' => $reversedRangePathspec->skipWorktree('wp-content/uploads/m/photo.jpg', false),
    'pathspecNegatedReversedRangeStartSkipped' => $negatedReversedRangePathspec->skipWorktree('wp-content/uploads/z/photo.jpg', false),
    'pathspecNegatedReversedRangeMiddleIncluded' => $negatedReversedRangePathspec->includesPath('wp-content/uploads/m/photo.jpg', false),
    'pathspecIcaseReversedRangeMiddleIncluded' => $foldedReversedRangePathspec->includesPath('wp-content/uploads/m/photo.jpg', false),
    'pathspecIcaseReversedRangeDigitSkipped' => $foldedReversedRangePathspec->skipWorktree('wp-content/uploads/0/photo.jpg', false),
    'pathspecDoubleStarComponentLocalFileIncluded' => $doubleStarComponentPathspec->includesPath('wp-content/index.php', false),
    'pathspecDoubleStarComponentLocalNestedFileSkipped' => $doubleStarComponentPathspec->skipWorktree('wp-content/plugins/editor.php', false),
    'pathspecDoubleStarComponentDirectoryGlobIncluded' => $doubleStarComponentPathspec->includesPath('wp-content/plugins/loader.php', false),
    'pathspecDoubleStarMidComponentNestedDirectorySkipped' => $doubleStarComponentPathspec->skipWorktree('wp-content/plugins/vendor/block.json', false),
    'pathspecDoubleStarMidComponentSiblingIncluded' => $doubleStarComponentPathspec->includesPath('wp-content/plugins-vendor/block.json', false),
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
    'negativeWildcardCacheDirectoryTraversable' => $negativeWildcardPathspec->includesPath('wp-content/generated-cache', true),
    'negativeWildcardCacheFileNameSkipped' => $negativeWildcardPathspec->skipWorktree('wp-content/generated-cache', false),
    'negativeWildcardCacheDescendantIncluded' => $negativeWildcardPathspec->includesPath('wp-content/generated-cache/index.php', false),
    'negativeWildcardExcludeOnlyDescendantIncluded' => $negativeWildcardExcludeOnlyPathspec->includesPath('wp-content/generated-cache/index.php', false),
    'directoryOnlyWildcardExcludeDirectoryTraversable' => $directoryOnlyWildcardExcludePathspec->includesPath('wp-content/generated-cache', true),
    'negativeWildcardEntriesToMaterialize' => $entryNames($negativeWildcardPathspec->includedTreeEntries($wpContent, 'wp-content')),
    'negativeNilRootIncluded' => $negativeNilPathspec->includesPath('', true),
    'negativeNilRootSkipWorktree' => $negativeNilPathspec->skipWorktree('', true),
    'negativeNilPluginSkipped' => $negativeNilPathspec->skipWorktree('wp-content/plugins/gutenberg/block.json', false),
    'absoluteRootPathspecBlockIncluded' => $absolutePathspec->includesPath('wp-content/plugins/gutenberg/block.json', false),
    'absoluteRootPathspecIcaseReadmeIncluded' => $absolutePathspec->includesPath('wp-content/plugins/gutenberg/README.md', false),
    'absoluteRootPathspecUpperPrefixSkipped' => $absolutePathspec->skipWorktree('WP-CONTENT/plugins/gutenberg/README.md', false),
    'absoluteRootPathspecBuildSkipped' => $absolutePathspec->skipWorktree('wp-content/plugins/gutenberg/build/index.js', false),
    'absoluteBackslashLiteralIncluded' => $absoluteBackslashLiteralPathspec->includesPath('wp-content/plugins/f\\oo/block.json', false),
    'absoluteBackslashLiteralSlashSkipped' => $absoluteBackslashLiteralPathspec->skipWorktree('wp-content/plugins/f/oo/block.json', false),
    'absoluteBackslashGlobIncluded' => $absoluteBackslashGlobPathspec->includesPath('wp-content/plugins/f\\oo/loader.php', false),
    'absoluteBackslashGlobSlashSkipped' => $absoluteBackslashGlobPathspec->skipWorktree('wp-content/plugins/f/oo/loader.php', false),
    'absoluteBackslashOrdinaryEscapesNextByte' => $absoluteBackslashOrdinaryPathspec->includesPath('wp-content/plugins/foo/block.json', false),
    'absoluteBackslashOrdinaryVerbatimFallbackIncluded' => $absoluteBackslashOrdinaryPathspec->includesPath('wp-content/plugins/f\\oo/block.json', false),
    'pathspecEscapedBytePrefixDirectoryTraversable' => $escapedByteTraversalPathspec->includesPath('wp-content/plugins/f', true),
    'pathspecEscapedByteDirectoryTraversable' => $escapedByteTraversalPathspec->includesPath('wp-content/plugins/foo', true),
    'pathspecEscapedByteVerbatimDirectoryIncluded' => $escapedByteTraversalPathspec->includesPath('wp-content/plugins/f\\oo', true),
    'pathspecEscapedByteEscapedFileIncluded' => $escapedByteTraversalPathspec->includesPath('wp-content/plugins/foo/block.json', false),
    'pathspecEscapedByteVerbatimFileIncluded' => $escapedByteTraversalPathspec->includesPath('wp-content/plugins/f\\oo/block.json', false),
    'pathspecEscapedSlashDirectoryTraversable' => $escapedSlashTraversalPathspec->includesPath('wp-content/plugins/foo', true),
    'pathspecEscapedSlashFileIncluded' => $escapedSlashTraversalPathspec->includesPath('wp-content/plugins/foo/block.json', false),
    'pathspecLfByteShellStarIncluded' => $newlineByteShellStarPathspec->includesPath("wp-content\n/plugins/gutenberg/block.json", false),
    'pathspecLfByteShellQuestionIncluded' => $newlineByteShellQuestionPathspec->includesPath("wp-content\n/plugins/gutenberg/block.json", false),
    'pathspecLfBytePathAwareQuestionIncluded' => $newlineBytePathAwareQuestionPathspec->includesPath("wp-content\n/plugins/gutenberg/block.json", false),
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
    'environmentLiteralIcaseMagicTextIncluded' => $environmentLiteralPathspec->includesPath(':(GLOB)WP-CONTENT/PLUGINS/*.PHP', false),
    'environmentLiteralColonIncluded' => $environmentLiteralPathspec->includesPath(':', false),
    'environmentGlobNestedPluginSkipped' => $environmentGlobPathspec->skipWorktree('wp-content/plugins/nested/plugin.php', false),
    'environmentNoGlobLiteralPluginIncluded' => $environmentNoGlobPathspec->includesPath('wp-content/plugins/*.php', false),
    'environmentNoGlobMagicOverrideIncluded' => $environmentNoGlobPathspec->includesPath('wp-content/mu-plugins/loader.php', false),
    'environmentFalseNoGlobLiteralIncluded' => $environmentFalseNoGlobPathspec->includesPath('wp-content/plugins/*.php', false),
    'environmentFalseNoGlobPluginSkipped' => $environmentFalseNoGlobPathspec->skipWorktree('wp-content/plugins/gutenberg.php', false),
    'environmentIcaseUpperPrefixIncluded' => $environmentIcasePathspec->includesPath('WP-CONTENT/plugins/Loader.PHP', false),
    'environmentIcaseLowerPrefixSkipped' => $environmentIcasePathspec->skipWorktree('wp-content/plugins/Loader.PHP', false),
    'environmentGlobNoGlobConflictRejected' => $environmentConflictRejected,
    'directoryOnlyUnknownExactSkipped' => $directoryOnlyUnknownPathspec->skipWorktree('wp-content/cache', null),
    'directoryOnlyUnknownDescendantIncluded' => $directoryOnlyUnknownPathspec->includesPath('wp-content/cache/page.html', null),
    'prefixedEmptyUnknownPrefixSkipped' => $prefixedEmptyPathspec->skipWorktree('wp-content/themes', null),
    'prefixedEmptyDescendantIncluded' => $prefixedEmptyPathspec->includesPath('wp-content/themes/acme/style.css', null),
];
