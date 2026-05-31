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
    . "wp-content/plugins/foo[/]bar.php slash-class\n",
    withBuiltInMacros: false,
);
$datedUploadSearch = PathspecSearch::fromSpecs([':(attr:dated-upload)wp-content/uploads/**']);
$whitespaceUploadSearch = PathspecSearch::fromSpecs([':(attr:whitespace-upload)wp-content/uploads/**']);
$searchSelected = [];
foreach ($fixture['paths'] as $path => $isDirectory) {
    if ($search->isIncluded($path, $isDirectory, $attributes)) {
        $searchSelected[] = $path;
    }
}
sort($searchSelected, SORT_STRING);
$pluginSearchMatch = $search->match('wp-content/plugins/gutenberg/block.json', false, $attributes);

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
        "wp-content/uploads/slot\v/photo.jpg",
        false,
        $classAttributes,
    ),
    'invalidClassDoesNotMatchLiteral' => !PathspecMatcher::matchesOne(
        ':(attr:invalid-upload)wp-content/uploads/**',
        'wp-content/uploads/[[:unknown:]]/photo.jpg',
        false,
        $classAttributes,
    ),
    'slashClassDoesNotCrossDirectory' => !PathspecMatcher::matchesOne(
        ':(attr:slash-class)wp-content/plugins/**',
        'wp-content/plugins/foo/bar.php',
        false,
        $classAttributes,
    ),
    'cacheExcluded' => !$matcher->matches('wp-content/cache/page.html', false, $attributes),
    'buildExcludedByPathspec' => !$matcher->matches('wp-content/plugins/gutenberg/build/index.js', false, $attributes),
];
