<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$parent = new SourceMap();
$entrySource = $parent->addSource('wp-content/themes/example/editor.css');
$parent->setSourceContent($entrySource, ".wp-block-entry{}\n");
$parent->addMapping(0, 0, $entrySource, 0, 0, 'entry-rule');

$child = new SourceMap();
$cardSource = $child->addSource('wp-content/themes/example/blocks/card.scss');
$unusedSource = $child->addSource('wp-content/themes/example/blocks/unused.scss');
$child->setSourceContent($cardSource, ".wp-block-card{color:\$brand}\n.wp-block-card__icon{display:block}\n");
$child->setSourceContent($unusedSource, ".wp-block-unused{}\n");
$child->addGeneratedMapping(0, 5);
$child->addGeneratedMapping(0, 12);
$child->addMapping(1, 3, $cardSource, 4, 2, 'card-rule');
$child->addMapping(1, 9, $cardSource, 4, 8, 'card-decl');
$child->addGeneratedMapping(2, 4);
$child->addMapping(3, 1, $cardSource, 6, 0, 'card-after');
$child->addName('unused-generated-only');

$parent->appendSourceMapWithGeneratedOffset($child, 2, 7, false);
$decoded = SourceMap::decodeVlq($parent->writeVlq());
$roundTrip = SourceMap::fromBuffer('/', $parent->toBuffer());

$actual = [
    'map' => $parent->toJson(null, false),
    'generatedLines' => array_column($decoded, 'generatedLine'),
    'generatedColumns' => array_column($decoded, 'generatedColumn'),
    'sourceIndexes' => array_column($decoded, 'sourceIndex'),
    'originalLines' => array_column($decoded, 'originalLine'),
    'names' => $parent->getNames(),
    'sources' => $parent->getSources(),
    'prunedGapClosest' => $parent->findClosestMapping(2, 7),
    'sourceBackedAfterGap' => $parent->findClosestMapping(3, 6),
    'lateSourceBackedAfterGap' => $parent->findClosestMapping(5, 1),
    'roundTrip' => $roundTrip->toJson(null, false),
    'childConsumed' => $child->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'map' => '{"version":3,"mappings":"AAAAA;;;GCIEC,MAAMC;;CAERC","sources":["wp-content/themes/example/editor.css","wp-content/themes/example/blocks/card.scss"],"sourcesContent":[".wp-block-entry{}\n",".wp-block-card{color:$brand}\n.wp-block-card__icon{display:block}\n"],"names":["entry-rule","card-rule","card-decl","card-after"]}',
        'generatedLines' => [0, 3, 3, 5],
        'generatedColumns' => [0, 3, 9, 1],
        'sourceIndexes' => [0, 1, 1, 1],
        'originalLines' => [0, 4, 4, 6],
        'names' => ['entry-rule', 'card-rule', 'card-decl', 'card-after'],
        'sources' => ['wp-content/themes/example/editor.css', 'wp-content/themes/example/blocks/card.scss'],
        'prunedGapClosest' => null,
        'sourceBackedAfterGap' => ['generatedLine' => 3, 'generatedColumn' => 3, 'sourceIndex' => 1, 'originalLine' => 4, 'originalColumn' => 2, 'nameIndex' => 1],
        'lateSourceBackedAfterGap' => ['generatedLine' => 5, 'generatedColumn' => 1, 'sourceIndex' => 1, 'originalLine' => 6, 'originalColumn' => 0, 'nameIndex' => 3],
        'roundTrip' => '{"version":3,"mappings":"AAAAA;;;GCIEC,MAAMC;;CAERC","sources":["wp-content/themes/example/editor.css","wp-content/themes/example/blocks/card.scss"],"sourcesContent":[".wp-block-entry{}\n",".wp-block-card{color:$brand}\n.wp-block-card__icon{display:block}\n"],"names":["entry-rule","card-rule","card-decl","card-after"]}',
        'childConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected pruned input-gap source-map output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['map'] . "\n";
