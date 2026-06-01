<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitAttributes;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\PathspecPattern;
use PortLibs\Gitoxide\PathspecSearch;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;
use PortLibs\Gitoxide\TreePathspecWalk;
use PortLibs\Gitoxide\TreeWalkEntry;

$objects = [];
$blobOid = str_repeat('1', 40);
$worktreeRoot = '/srv/www/example.com/current';
$blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
$tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
    $object = $tree->toObject();
    $objects[$object->oid()] = $object;

    return new TreeEntry('040000', $name, $object->oid());
};

$root = new Tree([
    $blob('   '),
    $blob("\f"),
    $blob('index.php'),
    $tree('wp-admin', new Tree([$blob('admin.php')])),
    $tree('wp-content', new Tree([
        $tree('mu-plugins', new Tree([$blob('Loader.PHP')])),
        $tree('plugins', new Tree([
            $tree('..', new Tree([$blob('secret.php')])),
            $tree('akismet', new Tree([$blob('akismet.php'), $blob('block.json')])),
            $tree('gutenberg', new Tree([
                $blob('block.json'),
                $blob('block.gson'),
                $tree('build', new Tree([$blob('index.js')])),
                $tree('src', new Tree([$blob('editor.js')])),
            ])),
            $tree("new\nline", new Tree([$blob('block.json')])),
            $blob('safe.php'),
            $blob('weird\\name.php'),
            $blob('dangling\\'),
            $blob('dang*\\'),
            $blob('dangx\\'),
            $tree('[literal]', new Tree([$blob('block.?son')])),
        ])),
        $tree('themes', new Tree([
            $tree('acme', new Tree([$blob('theme.json'), $blob('theme.?son'), $blob('style.css')])),
        ])),
        $blob('theme.?son'),
        $tree('uploads', new Tree([
            $tree('a', new Tree([$blob('hero.jpg')])),
            $tree('[', new Tree([$blob('hero.jpg')])),
            $tree('[[:alpha]', new Tree([$blob('hero.jpg')])),
            $tree('2026', new Tree([
                $blob('[hero].jpg'),
                $tree('05', new Tree([$blob('hero.jpg')])),
                $tree('02', new Tree([$blob('hero.jpg')])),
            ])),
        ])),
    ])),
    $tree('WP-CONTENT', new Tree([
        $tree('mu-plugins', new Tree([$blob('Loader.PHP')])),
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
$noGlobPathspecs = PathspecSearch::fromSpecs(
    ['wp-content/plugins/gutenberg/*.json'],
    defaultSearchMode: PathspecPattern::SEARCH_LITERAL,
);
$globOverrideNoGlobPathspecs = PathspecSearch::fromSpecs(
    [':(glob)wp-content/plugins/gutenberg/*.json'],
    defaultSearchMode: PathspecPattern::SEARCH_LITERAL,
);
$pathAwareDefaultPathspecs = PathspecSearch::fromSpecs(
    ['wp-content/plugins/gutenberg/*'],
    defaultSearchMode: PathspecPattern::SEARCH_PATH_AWARE_GLOB,
);
$inheritedIcasePathspecs = PathspecSearch::fromSpecs(
    ['mu-plugins/*.php'],
    'wp-content',
    defaultIgnoreCase: true,
);
$prefixedPathspecs = PathspecSearch::fromSpecs([':(icase)mu-plugins/*.php'], 'WP-CONTENT');
$mixedPrefixPathspecs = PathspecSearch::fromSpecs([
    ':(icase)mu-plugins/*.php',
    ':(top)index.php',
], 'WP-CONTENT');
$siblingPrefixPathspecs = PathspecSearch::fromSpecs(
    ['../themes/acme/theme.json'],
    'wp-content/plugins',
);
$absoluteRootPathspecs = PathspecSearch::fromSpecs(
    [
        $worktreeRoot . '/wp-content/plugins/gutenberg/block.json',
        ':(top)' . $worktreeRoot . '/index.php',
    ],
    'wp-content/plugins',
    root: $worktreeRoot,
);
$pluginPruningHintPathspecs = PathspecSearch::fromSpecs([
    'wp-content/plugins/gutenberg/*.json',
    'wp-content/plugins/gutenberg/*.gson',
]);
$callerPrefixHintPathspecs = PathspecSearch::fromSpecs(
    [':(icase)mu-plugins/*.php'],
    'WP-CONTENT',
);
$directoryOnlyHintPathspecs = PathspecSearch::fromSpecs([
    'wp-content/plugins/gutenberg/',
]);
$excludeNilPathspecs = PathspecSearch::fromSpecs([':(exclude)']);
$prefixedNilPathspecs = PathspecSearch::fromSpecs([':'], 'wp-content/themes');
$prefixedEmptyMagicPathspecs = PathspecSearch::fromSpecs([':()'], 'wp-content/plugins/gutenberg');
$prefixedExcludeNilPathspecs = PathspecSearch::fromSpecs([':(exclude)'], 'wp-content/themes');
$emptyPatternsRepositoryWidePathspecs = PathspecSearch::fromSpecs(
    [],
    'wp-content/themes',
    emptyPatternsMatchPrefix: false,
);
$rawComponentGuardPathspecs = PathspecSearch::fromSpecs([
    'wp-content/secret.php',
    'wp-content/plugins/safe.php',
    'wp-content/plugins/weird/name.php',
]);
$rootDotPathspecs = PathspecSearch::fromSpecs(['.']);
$topDotPathspecs = PathspecSearch::fromSpecs([':(top).'], 'wp-content/plugins');
$parentToRootPathspecs = PathspecSearch::fromSpecs(['../..'], 'wp-content/plugins');
$prefixedDotPathspecs = PathspecSearch::fromSpecs(['.'], 'wp-content/plugins');
$shellNewlinePathspecs = PathspecSearch::fromSpecs([
    'wp-content/plugins/new?line/block.json',
]);
$danglingBackslashPathspecs = PathspecSearch::fromSpecs([
    ':(glob)wp-content/plugins/dangling\\',
    ':(glob)wp-content/plugins/dang*\\',
]);
$malformedPosixClassPathspecs = PathspecSearch::fromSpecs([
    ':(glob)wp-content/uploads/[[:alpha]/hero.jpg',
]);
$whitespaceDirectoryOnlyPathspecs = PathspecSearch::fromSpecs([
    '   /',
    "\f/",
]);
$deploymentAttributes = GitAttributes::fromString(
    "wp-content/plugins/gutenberg/** deploy=plugin merge=union\n"
    . "wp-content/plugins/gutenberg/build/** !deploy\n"
    . "wp-content/themes/acme/** deploy=theme\n",
    withBuiltInMacros: false,
);
$attrFilteredPathspecs = PathspecSearch::fromSpecs([
    ':(glob,attr:deploy=plugin)wp-content/plugins/gutenberg/block.[jg]son',
    ':(glob,attr:deploy=theme)wp-content/themes/acme/*',
    ':!:(attr:!deploy)wp-content/plugins/gutenberg/build/**',
]);
$rootEscapingPathspecRejected = false;
try {
    PathspecSearch::fromSpecs(['../../../wp-config.php'], 'wp-content/plugins');
} catch (InvalidArgumentException) {
    $rootEscapingPathspecRejected = true;
}
$absoluteRootOutsideRejected = false;
try {
    PathspecSearch::fromSpecs([$worktreeRoot . '/../shared/wp-config.php'], root: $worktreeRoot);
} catch (InvalidArgumentException) {
    $absoluteRootOutsideRejected = true;
}
$absoluteRootRelativeRejected = false;
try {
    PathspecSearch::fromSpecs([$worktreeRoot . '/wp-config.php'], root: 'relative/root');
} catch (InvalidArgumentException) {
    $absoluteRootRelativeRejected = true;
}

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
$globOverrideNoGlobRecords = TreePathspecWalk::breadthFirst(
    $root,
    $globOverrideNoGlobPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$pathAwareDefaultRecords = TreePathspecWalk::breadthFirst(
    $root,
    $pathAwareDefaultPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$inheritedIcaseRecords = TreePathspecWalk::breadthFirst(
    $root,
    $inheritedIcasePathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$mixedPrefixRecords = TreePathspecWalk::breadthFirst(
    $root,
    $mixedPrefixPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$siblingPrefixRecords = TreePathspecWalk::breadthFirst(
    $root,
    $siblingPrefixPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$absoluteRootRecords = TreePathspecWalk::breadthFirst(
    $root,
    $absoluteRootPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$attrFilteredRecords = TreePathspecWalk::breadthFirst(
    $root,
    $attrFilteredPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
    attributes: $deploymentAttributes,
);
$attrFilteredWithoutProviderRecords = TreePathspecWalk::breadthFirst(
    $root,
    $attrFilteredPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$excludeNilReadPaths = [];
$excludeNilRecords = TreePathspecWalk::breadthFirst(
    $root,
    $excludeNilPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects, &$excludeNilReadPaths): GitObject {
        $excludeNilReadPaths[] = $path;
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$prefixedNilRecords = TreePathspecWalk::breadthFirst(
    $root,
    $prefixedNilPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$prefixedEmptyMagicRecords = TreePathspecWalk::breadthFirst(
    $root,
    $prefixedEmptyMagicPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$emptyPatternsRepositoryWideRecords = TreePathspecWalk::breadthFirst(
    $root,
    $emptyPatternsRepositoryWidePathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$rawComponentReadPaths = [];
$rawComponentGuardRecords = TreePathspecWalk::breadthFirst(
    $root,
    $rawComponentGuardPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects, &$rawComponentReadPaths): GitObject {
        $rawComponentReadPaths[] = $path;
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$rootDotReadPaths = [];
$rootDotRecords = TreePathspecWalk::breadthFirst(
    $root,
    $rootDotPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects, &$rootDotReadPaths): GitObject {
        $rootDotReadPaths[] = $path;
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$topDotRecords = TreePathspecWalk::breadthFirst(
    $root,
    $topDotPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$parentToRootRecords = TreePathspecWalk::breadthFirst(
    $root,
    $parentToRootPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$prefixedDotRecords = TreePathspecWalk::breadthFirst(
    $root,
    $prefixedDotPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$shellNewlineRecords = TreePathspecWalk::breadthFirst(
    $root,
    $shellNewlinePathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$danglingBackslashRecords = TreePathspecWalk::breadthFirst(
    $root,
    $danglingBackslashPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$malformedPosixClassRecords = TreePathspecWalk::breadthFirst(
    $root,
    $malformedPosixClassPathspecs,
    static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    },
    includeTrees: false,
);
$whitespaceDirectoryOnlyRecords = TreePathspecWalk::breadthFirst(
    $root,
    $whitespaceDirectoryOnlyPathspecs,
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
    'noGlobLiteralPluginGlobSkipped' => !$noGlobPathspecs->isIncluded('wp-content/plugins/gutenberg/block.json', false),
    'globOverrideNoGlobContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $globOverrideNoGlobRecords),
    'pathAwareDefaultContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $pathAwareDefaultRecords),
    'pathAwareDefaultNestedSrcSkipped' => !$pathAwareDefaultPathspecs->isIncluded('wp-content/plugins/gutenberg/src/editor.js', false),
    'inheritedIcaseDefaultContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $inheritedIcaseRecords),
    'mixedPrefixCommonPrefixCollapsed' => $mixedPrefixPathspecs->commonPrefix() === '',
    'mixedPrefixContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $mixedPrefixRecords),
    'mixedPrefixLowerContentSkipped' => !$mixedPrefixPathspecs->isIncluded('wp-content/mu-plugins/Loader.PHP', false),
    'mixedPrefixUpperContentIncluded' => $mixedPrefixPathspecs->isIncluded('WP-CONTENT/mu-plugins/Loader.PHP', false),
    'siblingPrefixContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $siblingPrefixRecords),
    'absoluteRootContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $absoluteRootRecords),
    'absoluteRootPluginPrefix' => $absoluteRootPathspecs->patterns()[0]->prefixDirectory(),
    'absoluteRootOutsideRejected' => $absoluteRootOutsideRejected,
    'absoluteRootRelativeRejected' => $absoluteRootRelativeRejected,
    'pluginPruningHintDirectory' => $pluginPruningHintPathspecs->longestCommonDirectory(),
    'callerPrefixOnlyPruningHint' => $callerPrefixHintPathspecs->longestCommonDirectory(),
    'directoryOnlyPruningHint' => $directoryOnlyHintPathspecs->longestCommonDirectory(),
    'excludeNilCanDescendIntoContent' => $excludeNilPathspecs->canMatch('wp-content', true),
    'excludeNilContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $excludeNilRecords),
    'excludeNilReadPaths' => $excludeNilReadPaths,
    'prefixedNilCommonPrefix' => $prefixedNilPathspecs->commonPrefix(),
    'prefixedNilContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $prefixedNilRecords),
    'prefixedNilRootIndexSkipped' => !$prefixedNilPathspecs->isIncluded('index.php', false),
    'prefixedEmptyMagicContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $prefixedEmptyMagicRecords),
    'prefixedEmptyMagicAkismetSkipped' => !$prefixedEmptyMagicPathspecs->isIncluded('wp-content/plugins/akismet/akismet.php', false),
    'prefixedExcludeNilKeepsIndex' => $prefixedExcludeNilPathspecs->isIncluded('index.php', false),
    'prefixedExcludeNilSkipsTheme' => !$prefixedExcludeNilPathspecs->isIncluded('wp-content/themes/acme/style.css', false),
    'emptyPatternsRepositoryWideCount' => count($emptyPatternsRepositoryWideRecords),
    'emptyPatternsRepositoryWideAdminIncluded' => $emptyPatternsRepositoryWidePathspecs->isIncluded('wp-admin/admin.php', false),
    'emptyPatternsRepositoryWideThemePrefixIgnored' => in_array(
        'wp-content/plugins/gutenberg/build/index.js',
        array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $emptyPatternsRepositoryWideRecords),
        true,
    ),
    'rawComponentGuardContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $rawComponentGuardRecords),
    'rawComponentGuardReadPaths' => $rawComponentReadPaths,
    'rawParentComponentSkipped' => $rawComponentGuardPathspecs->match('wp-content/plugins/../secret.php', false) === null,
    'rawBackslashComponentSkipped' => $rawComponentGuardPathspecs->match('wp-content/plugins/weird\\name.php', false) === null,
    'rootDotCommonPrefix' => $rootDotPathspecs->commonPrefix(),
    'rootDotContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $rootDotRecords),
    'rootDotReadPaths' => $rootDotReadPaths,
    'topDotContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $topDotRecords),
    'parentToRootDotContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $parentToRootRecords),
    'parentToRootDotCommonPrefix' => $parentToRootPathspecs->commonPrefix(),
    'prefixedDotPrefixDirectory' => $prefixedDotPathspecs->prefixDirectory(),
    'prefixedDotContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $prefixedDotRecords),
    'shellGlobNewlineContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $shellNewlineRecords),
    'shellGlobNewlineIncluded' => $shellNewlinePathspecs->isIncluded("wp-content/plugins/new\nline/block.json", false),
    'danglingBackslashContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $danglingBackslashRecords),
    'danglingBackslashExactMatchKind' => $danglingBackslashPathspecs->match('wp-content/plugins/dangling\\', false)?->kind,
    'danglingBackslashWildcardSkipped' => !$danglingBackslashPathspecs->isIncluded('wp-content/plugins/dangx\\', false),
    'danglingBackslashLiteralStarIncluded' => $danglingBackslashPathspecs->isIncluded('wp-content/plugins/dang*\\', false),
    'malformedPosixClassContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $malformedPosixClassRecords),
    'malformedPosixClassLetterSkipped' => !$malformedPosixClassPathspecs->isIncluded('wp-content/uploads/a/hero.jpg', false),
    'malformedPosixClassBracketSkipped' => !$malformedPosixClassPathspecs->isIncluded('wp-content/uploads/[/hero.jpg', false),
    'malformedPosixClassLiteralIncluded' => $malformedPosixClassPathspecs->isIncluded('wp-content/uploads/[[:alpha]/hero.jpg', false),
    'whitespaceDirectoryOnlyContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $whitespaceDirectoryOnlyRecords),
    'whitespaceDirectoryOnlySpaceFileIncluded' => $whitespaceDirectoryOnlyPathspecs->isIncluded('   ', false),
    'whitespaceDirectoryOnlyFormFeedFileIncluded' => $whitespaceDirectoryOnlyPathspecs->isIncluded("\f", false),
    'whitespaceDirectoryOnlySpaceMatchKind' => $whitespaceDirectoryOnlyPathspecs->match('   ', false)?->kind,
    'attrFilteredContentPaths' => array_map(static fn (TreeWalkEntry $entry): string => $entry->path, $attrFilteredRecords),
    'attrFilteredWithoutProviderEmpty' => $attrFilteredWithoutProviderRecords === [],
    'rootEscapingPathspecRejected' => $rootEscapingPathspecRejected,
    'pathAwareSlashClassSkipped' => !PathspecSearch::fromSpecs([':(glob)wp-content/plugins/foo[/]bar.php'])->isIncluded('wp-content/plugins/foo/bar.php', false),
    'shellSlashClassIncluded' => PathspecSearch::fromSpecs(['wp-content/plugins/foo[/]bar.php'])->isIncluded('wp-content/plugins/foo/bar.php', false),
    'prefixCaseSensitiveUpperContentIncluded' => $prefixedPathspecs->isIncluded('WP-CONTENT/mu-plugins/loader.php', false),
    'prefixCaseSensitiveLowerContentSkipped' => !$prefixedPathspecs->isIncluded('wp-content/mu-plugins/Loader.PHP', false),
];
