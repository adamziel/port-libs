<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$blockMap = new SourceMap();
$blockUnderflowRejected = false;
try {
    $blockMap->addVlqMap(
        'KAAAA;A',
        ['wp-content/themes/example/blocks/card.scss'],
        [".wp-block-card { color: \$brand; }\n"],
        ['card-block-rule'],
        0,
        -3
    );
} catch (InvalidArgumentException) {
    $blockUnderflowRejected = true;
}

$generatedOnlyMap = new SourceMap();
$generatedOnlyUnderflowRejected = false;
try {
    $generatedOnlyMap->addVlqMap(
        'K;A',
        ['wp-content/themes/example/blocks/unused.scss'],
        ['.wp-block-unused { color: red; }'],
        ['unused-block-rule'],
        0,
        -3
    );
} catch (InvalidArgumentException) {
    $generatedOnlyUnderflowRejected = true;
}

$skippedLineMap = new SourceMap();
$skippedLineUnderflowRejected = false;
try {
    $skippedLineMap->addVlqMap(
        'C;A',
        ['wp-content/themes/example/blocks/skipped-reset.scss'],
        ['.wp-block-skipped-reset { color: blue; }'],
        ['skipped-reset-rule'],
        -2,
        -1
    );
} catch (InvalidArgumentException) {
    $skippedLineUnderflowRejected = true;
}

$actual = [
    'blockUnderflowRejected' => $blockUnderflowRejected,
    'blockMap' => $blockMap->toJson(null, false),
    'blockMappings' => $blockMap->getMappings(),
    'generatedOnlyUnderflowRejected' => $generatedOnlyUnderflowRejected,
    'generatedOnlyMap' => $generatedOnlyMap->toJson(null, false),
    'generatedOnlyMappings' => $generatedOnlyMap->getMappings(),
    'skippedLineUnderflowRejected' => $skippedLineUnderflowRejected,
    'skippedLineMap' => $skippedLineMap->toJson(null, false),
    'skippedLineMappings' => $skippedLineMap->getMappings(),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'blockUnderflowRejected' => true,
        'blockMap' => '{"version":3,"mappings":"EAAAA","sources":["wp-content/themes/example/blocks/card.scss"],"sourcesContent":[".wp-block-card { color: $brand; }\n"],"names":["card-block-rule"]}',
        'blockMappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 2, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 0],
        ],
        'generatedOnlyUnderflowRejected' => true,
        'generatedOnlyMap' => '{"version":3,"mappings":"E","sources":["wp-content/themes/example/blocks/unused.scss"],"sourcesContent":[".wp-block-unused { color: red; }"],"names":["unused-block-rule"]}',
        'generatedOnlyMappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 2, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null],
        ],
        'skippedLineUnderflowRejected' => true,
        'skippedLineMap' => '{"version":3,"mappings":"","sources":["wp-content/themes/example/blocks/skipped-reset.scss"],"sourcesContent":[".wp-block-skipped-reset { color: blue; }"],"names":["skipped-reset-rule"]}',
        'skippedLineMappings' => [],
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected negative-column source-map reset output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
