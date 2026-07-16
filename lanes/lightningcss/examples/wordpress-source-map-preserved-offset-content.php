<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$parent = new SourceMap('/theme');
$sharedSource = $parent->addSource('blocks/shared.scss');
$parent->setSourceContent($sharedSource, ".wp-block-card{color:old}\n");
$parent->addMapping(0, 0, $sharedSource, 0, 0, 'oldShared');

$child = new SourceMap('/theme');
$childSource = $child->addSource('blocks/./shared.scss');
$child->setSourceContent($childSource, ".wp-block-card{color:new}\n.wp-block-card__title{font-weight:600}\n");
$child->addGeneratedMapping(0, 2);
$child->addMapping(0, 6, $childSource, 8, 2, 'newShared');
$child->addMapping(1, 4, $childSource, 9, 4, 'newTitle');
$child->addName('unusedPreservedName');

$parent->appendSourceMapWithGeneratedOffset($child, 3, 11, true);

$sourceMap = $parent->toArray(null, false);
$decoded = SourceMap::decodeVlq($sourceMap['mappings']);

$actual = [
    'mappings' => $sourceMap['mappings'],
    'sources' => $sourceMap['sources'],
    'sourcesContent' => $sourceMap['sourcesContent'],
    'names' => $sourceMap['names'],
    'generatedLines' => array_column($decoded, 'generatedLine'),
    'generatedColumns' => array_column($decoded, 'generatedColumn'),
    'sourceIndexes' => array_column($decoded, 'sourceIndex'),
    'childDrained' => $child->toArray(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'mappings' => 'AAAAA;;;a,IAQEC;IACEC',
        'sources' => ['blocks/shared.scss'],
        'sourcesContent' => [
            ".wp-block-card{color:new}\n.wp-block-card__title{font-weight:600}\n",
        ],
        'names' => ['oldShared', 'newShared', 'newTitle', 'unusedPreservedName'],
        'generatedLines' => [0, 3, 3, 4],
        'generatedColumns' => [0, 13, 17, 4],
        'sourceIndexes' => [0, null, 0, 0],
        'childDrained' => [
            'version' => 3,
            'mappings' => '',
            'sources' => [],
            'sourcesContent' => [],
            'names' => [],
        ],
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected preserved offset content source-map output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
