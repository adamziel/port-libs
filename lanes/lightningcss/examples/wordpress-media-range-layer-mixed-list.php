<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();
$actual = [
    'layerNotAllRangeFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media not all, (width >= 240px) { .wp-block-query.is-wide { color: yellow; } } }',
        ['firefox' => 60]
    ),
    'layerNotAllIntervalFallback' => $prefixer->prefixForTargets(
        '@layer theme.blocks { @media not all, (100px <= width <= 200px) { .wp-block-query.is-window { color: yellow; } } }',
        ['firefox' => 85]
    ),
    'importNotAllRangeFallback' => $prefixer->prefixForTargets(
        '@import "blocks/query.css" layer(theme.blocks) not all, (width >= 240px); @layer theme.blocks { .wp-block-query.is-imported-wide { color: yellow; } }',
        ['firefox' => 60]
    ),
    'importAllIntervalFallback' => $prefixer->prefixForTargets(
        '@import "blocks/query.css" layer(theme.blocks) all, (100px <= width <= 200px); @layer theme.blocks { .wp-block-query.is-imported-window { color: yellow; } }',
        ['firefox' => 85]
    ),
];

$expected = [
    'layerNotAllRangeFallback' => '@layer theme.blocks{@media not all,(min-width:240px){.wp-block-query.is-wide{color:#ff0}}}',
    'layerNotAllIntervalFallback' => '@layer theme.blocks{@media not all,(min-width:100px) and (max-width:200px){.wp-block-query.is-window{color:#ff0}}}',
    'importNotAllRangeFallback' => '@import "blocks/query.css" layer(theme.blocks) not all,(min-width:240px);@layer theme.blocks{.wp-block-query.is-imported-wide{color:#ff0}}',
    'importAllIntervalFallback' => '@import "blocks/query.css" layer(theme.blocks) all,(min-width:100px) and (max-width:200px);@layer theme.blocks{.wp-block-query.is-imported-window{color:#ff0}}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected mixed media range layer output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . PHP_EOL;
