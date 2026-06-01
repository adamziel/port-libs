<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitAttributes;
use PortLibs\Gitoxide\PathspecMatcher;
use PortLibs\Gitoxide\PathspecSearch;

$fixture = require __DIR__ . '/../fixtures/wordpress-attributes-pathspec.php';
$attributes = GitAttributes::fromString($fixture['attributes']);
$matcher = PathspecMatcher::fromSpecs($fixture['deploymentPathspecs']);
$search = PathspecSearch::fromSpecs($fixture['deploymentPathspecs']);
$classAttributes = GitAttributes::fromString(
    "wp-content/uploads/[[:digit:]][[:digit:]]/** dated-upload\n"
    . "\"wp-content/uploads/slot[[:blank:]]/**\" whitespace-upload\n"
    . "\"wp-content/uploads/[[:unknown:]]/**\" invalid-upload\n"
    . "wp-content/uploads/[[:digit]ab] malformed-posix\n"
    . "wp-content/uploads/[[:]ab] empty-name-posix\n"
    . "wp-content/uploads/[[::]ab] double-colon-posix\n"
    . "wp-content/uploads/[z-a]/** reversed-range\n"
    . "wp-content/uploads/[!z-a]/** not-reversed-range\n"
    . "wp-content/uploads/[Z-A]/** folded-reversed-range\n"
    . "wp-content/plugins/foo[/]bar.php slash-class\n",
    withBuiltInMacros: false,
);
$posixClassNameFoldAttributes = GitAttributes::fromString(
    "WP-CONTENT/uploads/[[:UPPER:]]LUGINS/** folded-class\n"
    . "wp-content/uploads/plugins/** deploy\n",
    withBuiltInMacros: false,
);
$directoryOnlyAttributes = GitAttributes::fromString(
    "wp-content/plugins/**/ deploy-directory\n"
    . "wp-content/uploads/** deploy-file\n",
    withBuiltInMacros: false,
);
$quotedPatternAttributes = GitAttributes::fromString(
    "\"wp-content/uploads/slot\\040hero.jpg\" quoted-space\n"
    . "\"wp-content/uploads/form\\fhero.jpg\" formfeed-upload\n"
    . "\"wp-content/uploads/bad\\qhero.jpg\" invalid-escape\n",
    withBuiltInMacros: false,
);
$doubleStarAttributes = GitAttributes::fromString(
    "wp-content/plugins/a**f.php component-local\n"
    . "wp-content/plugins/**.php top-level-php\n"
    . "wp-content/plugins/**/block.json recursive-block\n",
    withBuiltInMacros: false,
);
$lfByteAttributes = GitAttributes::fromString(
    "wp-content/uploads/** lf-upload\n"
    . "wp-content/uploads/exact.jpg exact-upload\n",
    withBuiltInMacros: false,
);
$backslashAttributes = GitAttributes::fromString(
    'wp-content/plugins/f\\\\oo/block.json backslash-plugin' . "\n"
    . 'wp-content/plugins/f/oo/block.json slash-plugin' . "\n",
    withBuiltInMacros: false,
);
$danglingBackslashAttributes = GitAttributes::fromString(
    'wp-content/plugins/trailing\\ dangling-backslash' . "\n"
    . 'wp-content/plugins/trailing\\\\ escaped-backslash' . "\n",
    withBuiltInMacros: false,
);
$malformedBracketAttributes = GitAttributes::fromString(
    "wp-content/uploads/foo[ malformed\n"
    . "wp-content/uploads/foo[] empty-class\n"
    . "wp-content/uploads/foo[!] negated-empty-class\n"
    . "wp-content/uploads/foo[[] literal-open\n"
    . "wp-content/uploads/foo[!]] not-close\n",
    withBuiltInMacros: false,
);
$validBracketFallbackAttributes = GitAttributes::fromString(
    "wp-content/uploads/foo[abc] bracket-class\n"
    . "wp-content/uploads/foo[[]abc] literal-bracket\n",
    withBuiltInMacros: false,
);
$backslashPath = 'wp-content/plugins/f\\oo/block.json';
$slashPath = 'wp-content/plugins/f/oo/block.json';
$backslashPathspec = ':(glob,attr:backslash-plugin)wp-content/plugins/f\\\\oo/block.json';
$danglingBackslashPath = 'wp-content/plugins/trailing\\';
$danglingBackslashPathspec = 'wp-content/plugins/trailing\\';
$danglingBackslashMatch = PathspecSearch::fromSpecs([$danglingBackslashPathspec])
    ->match($danglingBackslashPath, false);
$escapedBackslashPathspec = ':(glob,attr:escaped-backslash)wp-content/plugins/trailing\\\\';
$malformedBracketMatch = PathspecSearch::fromSpecs(['wp-content/uploads/foo['])
    ->match('wp-content/uploads/foo[', false);
$validBracketLiteralPath = 'wp-content/uploads/foo[abc]';
$validBracketWildcardPath = 'wp-content/uploads/fooa';
$validBracketWildcardSearch = PathspecSearch::fromSpecs([
    ':(glob,attr:bracket-class)wp-content/uploads/foo[abc]',
]);
$validBracketLiteralSearch = PathspecSearch::fromSpecs([
    ':(glob,attr:literal-bracket)wp-content/uploads/foo[abc]',
]);
$validBracketLiteralMatch = $validBracketLiteralSearch->match(
    $validBracketLiteralPath,
    false,
    $validBracketFallbackAttributes,
);
$tabAttributes = GitAttributes::fromString(
    "wp-content/plugins/gutenberg/** deploy review=yes\n",
    withBuiltInMacros: false,
);
$asciiWhitespaceAttributes = GitAttributes::fromString(
    "wp-content/plugins/editor/** deploy\vreview=yes\n"
    . "wp-content/themes/classic/** deploy\ftheme\n"
    . "wp-content/uploads/** binary\fdiff=image\vreview\n",
    withBuiltInMacros: false,
);
$asciiWhitespaceOnlyAttributes = GitAttributes::fromString(
    "\f formfeed-only\n"
    . "\"\\f\" quoted-formfeed-only\n"
    . "\" \\f \" quoted-spaced-formfeed-only\n"
    . "\"wp-content/uploads/slot\\fhero.jpg\" embedded-formfeed\n",
    withBuiltInMacros: false,
);
$nulBoundaryAttributes = GitAttributes::fromString(
    "wp-content/plugins/nul/** deploy\0\n"
    . "wp-content/uploads/nul/** deploy=media\0\n",
    withBuiltInMacros: false,
);
$recursiveMacroAttributes = GitAttributes::fromString(
    "[attr]my-text text\n"
    . "[attr]my-binary binary\n"
    . "[attr]b-cycle a-cycle my-text\n"
    . "[attr]a-cycle b-cycle my-binary\n"
    . "[attr]recursive recursively-assigned-attr\n"
    . "[attr]my-binary binary macro-overridden recursive\n"
    . "wp-content/** other a-cycle\n"
    . "wp-content/** -other b-cycle\n"
);
$nestedAttributes = GitAttributes::fromSources([
    [
        'contents' => "wp-content/** deploy=root review=pending\n"
            . "wp-content/cache/** export-ignore\n"
            . "[attr]root-binary binary root-macro\n"
            . "wp-content/uploads/** root-binary\n",
    ],
    [
        'baseDirectory' => 'wp-content/plugins',
        'contents' => "gutenberg/** deploy=plugin merge=union local-macro\n"
            . "gutenberg/build/** -deploy\n"
            . "[attr]local-macro nested-macro\n",
        'allowMacros' => false,
    ],
    [
        'baseDirectory' => 'wp-content/themes/twentytwentyfour',
        'contents' => "theme.json deploy=theme merge=union\n",
    ],
]);
$datedUploadSearch = PathspecSearch::fromSpecs([':(attr:dated-upload)wp-content/uploads/**']);
$whitespaceUploadSearch = PathspecSearch::fromSpecs([':(attr:whitespace-upload)wp-content/uploads/**']);
$malformedPosixSearch = PathspecSearch::fromSpecs([
    ':(glob,attr:malformed-posix)wp-content/uploads/[[:digit]ab]',
]);
$emptyNamePosixSearch = PathspecSearch::fromSpecs([
    ':(glob,attr:empty-name-posix)wp-content/uploads/[[:]ab]',
]);
$doubleColonPosixSearch = PathspecSearch::fromSpecs([
    ':(glob,attr:double-colon-posix)wp-content/uploads/[[::]ab]',
]);
$posixClassNameFoldSearch = PathspecSearch::fromSpecs([
    ':(icase,attr:deploy)wp-content/uploads/[[:UPPER:]]LUGINS/**',
]);
$directoryOnlyPathspec = ':(attr:deploy-directory)wp-content/plugins/**';
$reversedRangeSearch = PathspecSearch::fromSpecs([':(attr:reversed-range)wp-content/uploads/[z-a]/**']);
$foldedReversedRangeSearch = PathspecSearch::fromSpecs([':(icase)wp-content/uploads/[Z-A]/**']);
$componentLocalDoubleStarSearch = PathspecSearch::fromSpecs([
    ':(glob,attr:component-local)wp-content/plugins/a**f.php',
]);
$topLevelDoubleStarSearch = PathspecSearch::fromSpecs([
    ':(glob,attr:top-level-php)wp-content/plugins/**.php',
]);
$recursiveDoubleStarSearch = PathspecSearch::fromSpecs([
    ':(glob,attr:recursive-block)wp-content/plugins/**/block.json',
]);
$lfByteShellStarSearch = PathspecSearch::fromSpecs([
    ':(attr:lf-upload)wp-content/uploads/*hero.jpg',
]);
$lfBytePathAwareQuestionSearch = PathspecSearch::fromSpecs([
    ':(glob,attr:lf-upload)wp-content/uploads/?hero.jpg',
]);
$trailingLfWildcardSearch = PathspecSearch::fromSpecs([
    'wp-content/uploads/*.jpg',
]);
$nestedDeploymentSearch = PathspecSearch::fromSpecs([
    ':(attr:deploy=plugin)wp-content/plugins/**',
    ':(attr:deploy=theme)wp-content/themes/**',
    ':!:(attr:-deploy)wp-content/plugins/gutenberg/build/**',
    ':!:(attr:export-ignore)wp-content/cache/**',
]);
$quotedSpacePath = 'wp-content/uploads/slot hero.jpg';
$quotedFormFeedPath = "wp-content/uploads/form\x0chero.jpg";
$posixClassNameFoldPath = 'wp-content/uploads/plugins/block.json';
$directoryOnlyPath = 'wp-content/plugins/gutenberg';
$directoryOnlyChildPath = 'wp-content/plugins/gutenberg/block.json';
$malformedPosixPath = 'wp-content/uploads/[ab]';
$malformedPosixLiteralPath = 'wp-content/uploads/[[:digit]ab]';
$formFeedOnlyPath = "\f";
$spacedFormFeedOnlyPath = " \f ";
$embeddedFormFeedPath = "wp-content/uploads/slot\fhero.jpg";
$lfBytePath = "wp-content/uploads/line\nhero.jpg";
$lfByteQuestionPath = "wp-content/uploads/\nhero.jpg";
$trailingLfPath = "wp-content/uploads/exact.jpg\n";
$quotedSpaceSearch = PathspecSearch::fromSpecs([':(attr:quoted-space)wp-content/uploads/**']);
$quotedFormFeedSearch = PathspecSearch::fromSpecs([':(attr:formfeed-upload)wp-content/uploads/**']);
$valueTabRequirementRejected = false;
try {
    PathspecMatcher::fromSpecs([":(attr:deploy=plugin\treview=yes)wp-content/plugins/**"]);
} catch (InvalidArgumentException) {
    $valueTabRequirementRejected = true;
}
$verticalValueRequirementRejected = false;
try {
    PathspecMatcher::fromSpecs([":(attr:deploy=plugin\vreview=yes)wp-content/plugins/**"]);
} catch (InvalidArgumentException) {
    $verticalValueRequirementRejected = true;
}
$nulStateRequirementRejected = false;
try {
    PathspecSearch::fromSpecs([":(attr:deploy\0)wp-content/plugins/nul/**"]);
} catch (InvalidArgumentException) {
    $nulStateRequirementRejected = true;
}
$nulValueRequirementRejected = false;
try {
    PathspecSearch::fromSpecs([":(attr:deploy=media\0)wp-content/uploads/nul/**"]);
} catch (InvalidArgumentException) {
    $nulValueRequirementRejected = true;
}
$emptyLongMagicComponentRejected = false;
try {
    PathspecSearch::fromSpecs([':(attr:deploy,)wp-content/plugins/**']);
} catch (InvalidArgumentException) {
    $emptyLongMagicComponentRejected = true;
}
$unimplementedShortMagicRejected = false;
try {
    PathspecSearch::fromSpecs([':;:(attr:deploy=plugin)wp-content/plugins/**']);
} catch (InvalidArgumentException) {
    $unimplementedShortMagicRejected = true;
}
$searchSelected = [];
foreach ($fixture['paths'] as $path => $isDirectory) {
    if ($search->isIncluded($path, $isDirectory, $attributes)) {
        $searchSelected[] = $path;
    }
}
sort($searchSelected, SORT_STRING);
$nestedSelected = [];
foreach ($fixture['paths'] as $path => $isDirectory) {
    if ($nestedDeploymentSearch->isIncluded($path, $isDirectory, $nestedAttributes)) {
        $nestedSelected[] = $path;
    }
}
sort($nestedSelected, SORT_STRING);
$pluginSearchMatch = $search->match('wp-content/plugins/gutenberg/block.json', false, $attributes);
$normalizedParentComponentPathspecMatches = PathspecMatcher::matchesOne(
    ':(attr:deploy=theme)wp-content/plugins/../themes/**',
    'wp-content/themes/twentytwentyfour/theme.json',
    false,
    $attributes,
);
$rootEscapingPathspecRejected = false;
try {
    PathspecMatcher::fromSpecs([':(attr:deploy=plugin)../wp-content/plugins/**']);
} catch (InvalidArgumentException) {
    $rootEscapingPathspecRejected = true;
}
$rootEscapingCandidateRejected = false;
try {
    PathspecMatcher::matchesOne(
        ':(attr:deploy=plugin)wp-content/plugins/**',
        '../wp-content/plugins/gutenberg/block.json',
        false,
        $attributes,
    );
} catch (InvalidArgumentException) {
    $rootEscapingCandidateRejected = true;
}

return [
    'selectedForDeployment' => $matcher->matchingPaths($fixture['paths'], $attributes),
    'searchSelectedForDeployment' => $searchSelected,
    'pluginPathspecSearchKind' => $pluginSearchMatch?->kind,
    'pluginBlockAttributes' => $attributes->attributesForPath(
        'wp-content/plugins/gutenberg/block.json',
        ['merge', 'deploy', 'diff'],
    ),
    'uploadAttributes' => $attributes->attributesForPath(
        'wp-content/uploads/logo.png',
        ['binary', 'merge', 'diff', 'text'],
    ),
    'mustUsePluginAttributes' => $attributes->attributesForPath(
        'wp-content/mu-plugins/loader.php',
        ['merge', 'deploy', 'diff'],
    ),
    'explicitDeployUnspecifiedMatches' => PathspecMatcher::matchesOne(
        ':(attr:!deploy)wp-content/cache/**',
        'wp-content/cache/page.html',
        false,
        $attributes,
    ),
    'absentDeployUnspecifiedMatches' => PathspecMatcher::matchesOne(
        ':(attr:!deploy)wp-content/uploads/**',
        'wp-content/uploads/logo.png',
        false,
        $attributes,
    ),
    'normalizedParentComponentPathspecMatches' => $normalizedParentComponentPathspecMatches,
    'rootEscapingPathspecRejected' => $rootEscapingPathspecRejected,
    'rootEscapingCandidateRejected' => $rootEscapingCandidateRejected,
    'datedUploadAttributes' => $classAttributes->attributesForPath(
        'wp-content/uploads/05/photo.jpg',
        ['dated-upload'],
    ),
    'datedUploadPathspecMatches' => $datedUploadSearch->isIncluded(
        'wp-content/uploads/05/photo.jpg',
        false,
        $classAttributes,
    ),
    'whitespaceUploadPathspecMatches' => $whitespaceUploadSearch->isIncluded(
        "wp-content/uploads/slot\t/photo.jpg",
        false,
        $classAttributes,
    ),
    'verticalTabBlankUploadSkipped' => !$whitespaceUploadSearch->isIncluded(
        "wp-content/uploads/slot\v/photo.jpg",
        false,
        $classAttributes,
    ),
    'formFeedBlankPathspecMatches' => PathspecMatcher::matchesOne(
        ':(glob)wp-content/uploads/slot[[:blank:]]/photo.jpg',
        "wp-content/uploads/slot\f/photo.jpg",
        false,
    ),
    'invalidClassDoesNotMatchLiteral' => !PathspecMatcher::matchesOne(
        ':(attr:invalid-upload)wp-content/uploads/**',
        'wp-content/uploads/[[:unknown:]]/photo.jpg',
        false,
        $classAttributes,
    ),
    'malformedPosixAttributeMatchesLiteralOpen' => $classAttributes->attributesForPath(
        $malformedPosixPath,
        ['malformed-posix'],
    ),
    'malformedPosixPathspecSearchResumes' => $malformedPosixSearch->isIncluded(
        $malformedPosixPath,
        false,
        $classAttributes,
    ),
    'malformedPosixPathspecLiteralSkippedByAttribute' => !$malformedPosixSearch->isIncluded(
        $malformedPosixLiteralPath,
        false,
        $classAttributes,
    ),
    'emptyNamePosixPathspecSearchResumes' => $emptyNamePosixSearch->isIncluded(
        $malformedPosixPath,
        false,
        $classAttributes,
    ),
    'doubleColonPosixPathspecSkipped' => !$doubleColonPosixSearch->isIncluded(
        $malformedPosixPath,
        false,
        $classAttributes,
    ),
    'caseSensitivePosixClassNameAttributeSkipped' => $posixClassNameFoldAttributes->attributesForPath(
        $posixClassNameFoldPath,
        ['folded-class'],
    ),
    'foldedPosixClassNameAttributeMatches' => $posixClassNameFoldAttributes->attributesForPath(
        $posixClassNameFoldPath,
        ['folded-class'],
        false,
        true,
    ),
    'caseSensitivePosixClassNamePathspecSkipped' => !PathspecMatcher::matchesOne(
        'wp-content/uploads/[[:UPPER:]]LUGINS/**',
        $posixClassNameFoldPath,
        false,
    ),
    'foldedPosixClassNamePathspecMatches' => PathspecMatcher::matchesOne(
        ':(icase)wp-content/uploads/[[:LOWER:]]LUGINS/**',
        $posixClassNameFoldPath,
        false,
    ),
    'foldedPosixClassNameAttrPathspecMatches' => $posixClassNameFoldSearch->isIncluded(
        $posixClassNameFoldPath,
        false,
        $posixClassNameFoldAttributes,
    ),
    'directoryOnlyAttributeMatchesExplicitDirectory' => $directoryOnlyAttributes->attributesForPath(
        $directoryOnlyPath,
        ['deploy-directory'],
        true,
    ),
    'directoryOnlyAttributeSkippedWithoutDirectoryMetadata' => $directoryOnlyAttributes->attributesForPath(
        $directoryOnlyPath,
        ['deploy-directory'],
    ),
    'directoryOnlyAttributeSkipsFileChild' => $directoryOnlyAttributes->attributesForPath(
        $directoryOnlyChildPath,
        ['deploy-directory'],
        false,
    ),
    'directoryOnlyAttrPathspecRequiresDirectoryMetadata' => !PathspecMatcher::matchesOne(
        $directoryOnlyPathspec,
        $directoryOnlyPath,
        null,
        $directoryOnlyAttributes,
    ),
    'directoryOnlyAttrPathspecMatchesExplicitDirectory' => PathspecMatcher::matchesOne(
        $directoryOnlyPathspec,
        $directoryOnlyPath,
        true,
        $directoryOnlyAttributes,
    ),
    'directoryOnlyAttrPathspecSkipsFileChild' => !PathspecSearch::fromSpecs([
        $directoryOnlyPathspec,
    ])->isIncluded($directoryOnlyChildPath, false, $directoryOnlyAttributes),
    'quotedSpaceUploadAttributes' => $quotedPatternAttributes->attributesForPath(
        $quotedSpacePath,
        ['quoted-space'],
    ),
    'quotedSpaceUploadPathspecMatches' => $quotedSpaceSearch->isIncluded(
        $quotedSpacePath,
        false,
        $quotedPatternAttributes,
    ),
    'quotedSpaceUploadOctalTextSkipped' => !$quotedSpaceSearch->isIncluded(
        'wp-content/uploads/slot040hero.jpg',
        false,
        $quotedPatternAttributes,
    ),
    'quotedFormFeedUploadPathspecMatches' => $quotedFormFeedSearch->isIncluded(
        $quotedFormFeedPath,
        false,
        $quotedPatternAttributes,
    ),
    'invalidQuotedEscapeAttributeSkipped' => !PathspecSearch::fromSpecs([
        ':(attr:invalid-escape)wp-content/uploads/**',
    ])->isIncluded('wp-content/uploads/badqhero.jpg', false, $quotedPatternAttributes),
    'slashClassDoesNotCrossDirectory' => !PathspecMatcher::matchesOne(
        ':(attr:slash-class)wp-content/plugins/**',
        'wp-content/plugins/foo/bar.php',
        false,
        $classAttributes,
    ),
    'componentLocalDoubleStarAttributes' => $doubleStarAttributes->attributesForPath(
        'wp-content/plugins/axf.php',
        ['component-local'],
    ),
    'componentLocalDoubleStarSkipsNestedPath' => !PathspecMatcher::matchesOne(
        ':(glob,attr:component-local)wp-content/plugins/a**f.php',
        'wp-content/plugins/a/x/f.php',
        false,
        $doubleStarAttributes,
    ),
    'componentLocalDoubleStarSearchMatchesSibling' => $componentLocalDoubleStarSearch->isIncluded(
        'wp-content/plugins/axf.php',
        false,
        $doubleStarAttributes,
    ),
    'topLevelDoubleStarSkipsNestedPhp' => !$topLevelDoubleStarSearch->isIncluded(
        'wp-content/plugins/nested/index.php',
        false,
        $doubleStarAttributes,
    ),
    'recursiveDoubleStarMatchesDirectBlock' => $recursiveDoubleStarSearch->isIncluded(
        'wp-content/plugins/block.json',
        false,
        $doubleStarAttributes,
    ),
    'recursiveDoubleStarMatchesNestedBlock' => $recursiveDoubleStarSearch->isIncluded(
        'wp-content/plugins/nested/block.json',
        false,
        $doubleStarAttributes,
    ),
    'recursiveDoubleStarSkipsSuffixBlock' => !$recursiveDoubleStarSearch->isIncluded(
        'wp-content/plugins/nested/xblock.json',
        false,
        $doubleStarAttributes,
    ),
    'lfByteUploadAttributes' => $lfByteAttributes->attributesForPath(
        $lfBytePath,
        ['lf-upload'],
    ),
    'lfByteShellStarPathspecMatches' => PathspecMatcher::matchesOne(
        ':(attr:lf-upload)wp-content/uploads/*hero.jpg',
        $lfBytePath,
        false,
        $lfByteAttributes,
    ),
    'lfByteShellQuestionPathspecMatches' => PathspecMatcher::matchesOne(
        ':(attr:lf-upload)wp-content/uploads/?hero.jpg',
        $lfByteQuestionPath,
        false,
        $lfByteAttributes,
    ),
    'lfByteSearchShellStarMatches' => $lfByteShellStarSearch->isIncluded(
        $lfBytePath,
        false,
        $lfByteAttributes,
    ),
    'lfBytePathAwareQuestionMatches' => $lfBytePathAwareQuestionSearch->isIncluded(
        $lfByteQuestionPath,
        false,
        $lfByteAttributes,
    ),
    'trailingLfExactAttributeSkipped' => $lfByteAttributes->attributesForPath(
        $trailingLfPath,
        ['exact-upload'],
    ),
    'trailingLfWildcardPathspecSkipped' => !PathspecMatcher::matchesOne(
        'wp-content/uploads/*.jpg',
        $trailingLfPath,
        false,
    ),
    'trailingLfSearchPathspecSkipped' => !$trailingLfWildcardSearch->isIncluded(
        $trailingLfPath,
        false,
    ),
    'backslashPathAttributes' => $backslashAttributes->attributesForPath(
        $backslashPath,
        ['backslash-plugin'],
    ),
    'slashPathDoesNotMatchBackslashAttribute' => $backslashAttributes->attributesForPath(
        $slashPath,
        ['backslash-plugin'],
    ),
    'backslashPathspecMatchesByte' => PathspecMatcher::matchesOne(
        $backslashPathspec,
        $backslashPath,
        false,
        $backslashAttributes,
    ),
    'backslashPathspecSkipsSlash' => !PathspecSearch::fromSpecs([$backslashPathspec])->isIncluded(
        $slashPath,
        false,
        $backslashAttributes,
    ),
    'danglingBackslashAttributeSkipped' => $danglingBackslashAttributes->attributesForPath(
        $danglingBackslashPath,
        ['dangling-backslash'],
    ),
    'escapedBackslashAttributeStillMatches' => $danglingBackslashAttributes->attributesForPath(
        $danglingBackslashPath,
        ['escaped-backslash'],
    ),
    'danglingBackslashPathspecFallsBackVerbatim' => $danglingBackslashMatch?->kind,
    'danglingBackslashAttrPathspecSkipped' => !PathspecSearch::fromSpecs([
        ':(attr:dangling-backslash)' . $danglingBackslashPathspec,
    ])->isIncluded($danglingBackslashPath, false, $danglingBackslashAttributes),
    'escapedBackslashAttrPathspecMatches' => PathspecMatcher::matchesOne(
        $escapedBackslashPathspec,
        $danglingBackslashPath,
        false,
        $danglingBackslashAttributes,
    ),
    'malformedBracketAttributeSkipped' => $malformedBracketAttributes->attributesForPath(
        'wp-content/uploads/foo[',
        ['malformed'],
    ),
    'validLiteralBracketAttributeMatches' => $malformedBracketAttributes->attributesForPath(
        'wp-content/uploads/foo[',
        ['literal-open'],
    ),
    'malformedBracketPathspecFallsBackVerbatim' => $malformedBracketMatch?->kind,
    'malformedBracketAttrPathspecSkipped' => !PathspecSearch::fromSpecs([
        ':(attr:malformed)wp-content/uploads/foo[',
    ])->isIncluded('wp-content/uploads/foo[', false, $malformedBracketAttributes),
    'validNegatedCloseBracketPathspecMatches' => PathspecSearch::fromSpecs([
        'wp-content/uploads/foo[!]]',
    ])->isIncluded('wp-content/uploads/fooX', false),
    'validBracketClassAttributeSkipsLiteral' => $validBracketFallbackAttributes->attributesForPath(
        $validBracketLiteralPath,
        ['bracket-class'],
    ),
    'validBracketLiteralAttributeMatches' => $validBracketFallbackAttributes->attributesForPath(
        $validBracketLiteralPath,
        ['literal-bracket'],
    ),
    'validBracketWildcardPathspecMatchesClass' => $validBracketWildcardSearch->isIncluded(
        $validBracketWildcardPath,
        false,
        $validBracketFallbackAttributes,
    ),
    'validBracketAttrPathspecFallsBackVerbatim' => $validBracketLiteralMatch?->kind,
    'validBracketFallbackRequiresLiteralAttribute' => $validBracketLiteralSearch->isIncluded(
        $validBracketLiteralPath,
        false,
        $validBracketFallbackAttributes,
    ),
    'validBracketClassAttributeBlocksLiteralFallback' => !$validBracketWildcardSearch->isIncluded(
        $validBracketLiteralPath,
        false,
        $validBracketFallbackAttributes,
    ),
    'reversedRangePathspecMatchesStart' => $reversedRangeSearch->isIncluded(
        'wp-content/uploads/z/photo.jpg',
        false,
        $classAttributes,
    ),
    'reversedRangePathspecSkipsMiddle' => !$reversedRangeSearch->isIncluded(
        'wp-content/uploads/m/photo.jpg',
        false,
        $classAttributes,
    ),
    'reversedRangeNegationMatchesMiddle' => $classAttributes->attributesForPath(
        'wp-content/uploads/m/photo.jpg',
        ['not-reversed-range'],
    ),
    'foldedReversedRangeAttributeMatchesMiddle' => $classAttributes->attributesForPath(
        'wp-content/uploads/m/photo.jpg',
        ['folded-reversed-range'],
        false,
        true,
    ),
    'foldedReversedRangePathspecMatchesMiddle' => $foldedReversedRangeSearch->isIncluded(
        'wp-content/uploads/m/photo.jpg',
        false,
    ),
    'tabSeparatedStatePathspecMatches' => PathspecMatcher::matchesOne(
        ":(attr:deploy\treview=yes)wp-content/plugins/**",
        'wp-content/plugins/gutenberg/block.json',
        false,
        $tabAttributes,
    ),
    'verticalSeparatedAttributeFields' => $asciiWhitespaceAttributes->attributesForPath(
        'wp-content/plugins/editor/block.json',
        ['deploy', 'review'],
    ),
    'formFeedSeparatedAttributeFields' => $asciiWhitespaceAttributes->attributesForPath(
        'wp-content/themes/classic/style.css',
        ['deploy', 'theme'],
    ),
    'mixedAsciiWhitespaceAttributeFields' => $asciiWhitespaceAttributes->attributesForPath(
        'wp-content/uploads/banner.png',
        ['binary', 'diff', 'review'],
    ),
    'verticalSeparatedStatePathspecMatches' => PathspecMatcher::matchesOne(
        ":(attr:deploy\vreview=yes)wp-content/plugins/editor/**",
        'wp-content/plugins/editor/block.json',
        false,
        $asciiWhitespaceAttributes,
    ),
    'formFeedSeparatedStatePathspecMatches' => PathspecSearch::fromSpecs([
        ":(attr:deploy\ftheme)wp-content/themes/classic/**",
    ])->isIncluded('wp-content/themes/classic/style.css', false, $asciiWhitespaceAttributes),
    'mixedAsciiWhitespacePathspecMatches' => PathspecSearch::fromSpecs([
        ":(attr:binary\freview\vdiff=image)wp-content/uploads/**",
    ])->isIncluded('wp-content/uploads/banner.png', false, $asciiWhitespaceAttributes),
    'formFeedOnlyAttributeSkipped' => $asciiWhitespaceOnlyAttributes->attributesForPath(
        $formFeedOnlyPath,
        ['formfeed-only'],
    ),
    'quotedFormFeedOnlyAttributeSkipped' => $asciiWhitespaceOnlyAttributes->attributesForPath(
        $formFeedOnlyPath,
        ['quoted-formfeed-only'],
    ),
    'quotedSpacedFormFeedOnlyAttributeSkipped' => $asciiWhitespaceOnlyAttributes->attributesForPath(
        $spacedFormFeedOnlyPath,
        ['quoted-spaced-formfeed-only'],
    ),
    'formFeedOnlyPathspecSkipped' => !PathspecMatcher::matchesOne(
        ":(attr:formfeed-only)\f",
        $formFeedOnlyPath,
        false,
        $asciiWhitespaceOnlyAttributes,
    ),
    'quotedSpacedFormFeedPathspecSkipped' => !PathspecSearch::fromSpecs([
        ":(attr:quoted-spaced-formfeed-only) \f ",
    ])->isIncluded($spacedFormFeedOnlyPath, false, $asciiWhitespaceOnlyAttributes),
    'embeddedFormFeedAttributeMatches' => $asciiWhitespaceOnlyAttributes->attributesForPath(
        $embeddedFormFeedPath,
        ['embedded-formfeed'],
    ),
    'embeddedFormFeedPathspecMatches' => PathspecSearch::fromSpecs([
        ':(attr:embedded-formfeed)wp-content/uploads/**',
    ])->isIncluded($embeddedFormFeedPath, false, $asciiWhitespaceOnlyAttributes),
    'nulTerminatedAssignmentSkipped' => $nulBoundaryAttributes->attributesForPath(
        'wp-content/plugins/nul/editor.php',
        ['deploy'],
    ),
    'nulPreservedAttributeValue' => $nulBoundaryAttributes->attributesForPath(
        'wp-content/uploads/nul/banner.png',
        ['deploy'],
    ),
    'nulStateRequirementRejected' => $nulStateRequirementRejected,
    'nulValueRequirementRejected' => $nulValueRequirementRejected,
    'valueTabRequirementRejected' => $valueTabRequirementRejected,
    'verticalValueRequirementRejected' => $verticalValueRequirementRejected,
    'emptyLongMagicComponentRejected' => $emptyLongMagicComponentRejected,
    'unimplementedShortMagicRejected' => $unimplementedShortMagicRejected,
    'recursiveMacroAttributes' => $recursiveMacroAttributes->attributesForPath(
        'wp-content/plugins/gutenberg/block.php',
        ['text', 'other', 'macro-overridden', 'recursively-assigned-attr'],
    ),
    'recursiveMacroPathspecMatches' => PathspecSearch::fromSpecs([
        ':(attr:text recursively-assigned-attr macro-overridden -other)wp-content/**',
    ])->isIncluded('wp-content/plugins/gutenberg/block.php', false, $recursiveMacroAttributes),
    'nestedSelectedForDeployment' => $nestedSelected,
    'nestedPluginAttributes' => $nestedAttributes->attributesForPath(
        'wp-content/plugins/gutenberg/block.json',
        ['deploy', 'merge', 'review'],
    ),
    'nestedBuildExcluded' => !$nestedDeploymentSearch->isIncluded(
        'wp-content/plugins/gutenberg/build/index.js',
        false,
        $nestedAttributes,
    ),
    'nestedThemeAttributes' => $nestedAttributes->attributesForPath(
        'wp-content/themes/twentytwentyfour/theme.json',
        ['deploy', 'merge'],
    ),
    'nestedLocalMacroDefinitionIgnored' => $nestedAttributes->attributesForPath(
        'wp-content/plugins/gutenberg/block.json',
        ['local-macro', 'nested-macro'],
    ),
    'cacheExcluded' => !$matcher->matches('wp-content/cache/page.html', false, $attributes),
    'buildExcludedByPathspec' => !$matcher->matches('wp-content/plugins/gutenberg/build/index.js', false, $attributes),
];
