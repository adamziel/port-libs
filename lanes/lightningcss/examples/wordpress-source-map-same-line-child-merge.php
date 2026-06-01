<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$parent = new SourceMap();
$entry = $parent->addSource('wp-content/themes/example/style.css');
$parent->setSourceContent($entry, ".wp-block-parent{color:green}\n.wp-block-footer{color:blue}\n");
foreach ([0, 1, 2, 3] as $line) {
    $parent->addMapping($line, 0, $entry, $line, 0, 'parent-' . $line);
    $parent->addMapping($line, 10, $entry, $line, 5, 'parent-' . $line . '-tail');
}

$child = new SourceMap();
$block = $child->addSource('wp-content/themes/example/blocks/card.css');
$child->setSourceContent($block, ".wp-block-card{color:red}.wp-block-card__icon{color:blue}\n");
$child->addMapping(0, 2, $block, 8, 1, 'card');
$child->addGeneratedMapping(0, 6);
$child->addMapping(0, 9, $block, 8, 7, 'card-icon');

$parent->addSourceMap($child, 1);
$decoded = SourceMap::decodeVlq($parent->writeVlq());

$actual = [
    'map' => $parent->toJson(null, false),
    'decodedGeneratedColumns' => array_column($decoded, 'generatedColumn'),
    'decodedSourceIndexes' => array_column($decoded, 'sourceIndex'),
    'decodedNameIndexes' => array_column($decoded, 'nameIndex'),
    'sameLineGeneratedOnly' => $parent->findClosestMapping(1, 6),
    'sameLineSecondSourceBacked' => $parent->findClosestMapping(1, 9),
    'childConsumed' => $child->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'map' => '{"version":3,"mappings":"AAAAA,UAAKC;ECQJO,I,GAAMC;ADNPL,UAAKC;AACLC,UAAKC","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks/card.css"],"sourcesContent":[".wp-block-parent{color:green}\n.wp-block-footer{color:blue}\n",".wp-block-card{color:red}.wp-block-card__icon{color:blue}\n"],"names":["parent-0","parent-0-tail","parent-1","parent-1-tail","parent-2","parent-2-tail","parent-3","parent-3-tail","card","card-icon"]}',
        'decodedGeneratedColumns' => [0, 10, 2, 6, 9, 0, 10, 0, 10],
        'decodedSourceIndexes' => [0, 0, 1, null, 1, 0, 0, 0, 0],
        'decodedNameIndexes' => [0, 1, 8, null, 9, 4, 5, 6, 7],
        'sameLineGeneratedOnly' => [
            'generatedLine' => 1,
            'generatedColumn' => 6,
            'sourceIndex' => null,
            'originalLine' => null,
            'originalColumn' => null,
            'nameIndex' => null,
        ],
        'sameLineSecondSourceBacked' => [
            'generatedLine' => 1,
            'generatedColumn' => 9,
            'sourceIndex' => 1,
            'originalLine' => 8,
            'originalColumn' => 7,
            'nameIndex' => 9,
        ],
        'childConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected same-line child source-map output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['map'] . "\n";
