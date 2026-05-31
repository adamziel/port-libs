<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitAttributes;
use PortLibs\Gitoxide\PathspecMatcher;

$fixture = require __DIR__ . '/../fixtures/wordpress-attributes-pathspec.php';
$attributes = GitAttributes::fromString($fixture['attributes']);
$matcher = PathspecMatcher::fromSpecs($fixture['deploymentPathspecs']);

return [
    'selectedForDeployment' => $matcher->matchingPaths($fixture['paths'], $attributes),
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
    'cacheExcluded' => !$matcher->matches('wp-content/cache/page.html', false, $attributes),
    'buildExcludedByPathspec' => !$matcher->matches('wp-content/plugins/gutenberg/build/index.js', false, $attributes),
];
