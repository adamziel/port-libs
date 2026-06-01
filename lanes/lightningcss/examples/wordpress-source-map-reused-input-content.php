<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\CssBundler;
use PortLibs\LightningCSS\SourceMap;

$firstInputMap = 'data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => 'AAAA',
    'sources' => ['blocks/shared.scss'],
    'sourcesContent' => ['.wp-block-card { color: $first-palette; }'],
    'names' => [],
], JSON_THROW_ON_ERROR));

$secondInputMap = 'data:application/json;base64,' . base64_encode(json_encode([
    'version' => 3,
    'mappings' => 'AAAA',
    'sources' => ['blocks/./shared.scss'],
    'sourcesContent' => ['.wp-block-card-alt { color: $second-palette; }'],
    'names' => [],
], JSON_THROW_ON_ERROR));

$result = (new CssBundler())->bundleWithSourceMap('/theme/entry.css', [
    '/theme/entry.css' => '@import "blocks/card.css"; @import "blocks/card-alt.css"; .theme-entry { color: red }',
    '/theme/blocks/card.css' => ".wp-block-card { color: green }\n/*# sourceMappingURL={$firstInputMap} */",
    '/theme/blocks/card-alt.css' => ".wp-block-card-alt { color: blue }\n/*# sourceMappingURL={$secondInputMap} */",
], null, '/theme');

$sourceMap = $result['sourceMap']->toArray(null, false);
$decoded = SourceMap::decodeVlq($sourceMap['mappings']);

$actual = [
    'code' => $result['code'],
    'mappings' => $sourceMap['mappings'],
    'sources' => $sourceMap['sources'],
    'sourcesContent' => $sourceMap['sourcesContent'],
    'generatedColumns' => array_column($decoded, 'generatedColumn'),
    'sourceIndexes' => array_column($decoded, 'sourceIndex'),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'code' => '.wp-block-card{color:green}.wp-block-card-alt{color:#00f}.theme-entry{color:red}',
        'mappings' => 'ACAA,2BAAA',
        'sources' => ['entry.css', 'blocks/shared.scss'],
        'sourcesContent' => [
            '@import "blocks/card.css"; @import "blocks/card-alt.css"; .theme-entry { color: red }',
            '.wp-block-card { color: $first-palette; }',
        ],
        'generatedColumns' => [0, 27],
        'sourceIndexes' => [1, 1],
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected reused input source-map content output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
