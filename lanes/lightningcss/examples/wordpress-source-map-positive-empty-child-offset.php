<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$parent = new SourceMap();
$parentSource = $parent->addSource('wp-content/themes/example/style.css');
$parent->setSourceContent($parentSource, ".wp-block-parent{}\n");
$parent->addMapping(0, 0, $parentSource, 0, 0, 'parent-style-rule');

$child = new SourceMap();
$childSource = $child->addSource('wp-content/themes/example/blocks/empty-child.css');
$child->setSourceContent($childSource, ".wp-block-empty-child{}\n");
$child->addMapping(2, 0, $childSource, 2, 0, 'empty-child-rule');
$child->offsetColumns(2, 1, -1);

$childBeforeMerge = $child->toJson(null, false);
$parent->addSourceMap($child, 5);
$roundTrip = SourceMap::fromBuffer('/', $parent->toBuffer());

$actual = [
    'parentMap' => $parent->toJson(null, false),
    'childBeforeMerge' => $childBeforeMerge,
    'childConsumed' => $child->toJson(null, false),
    'closestAtInsertedSpan' => $parent->findClosestMapping(5, 0),
    'roundTripMap' => $roundTrip->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'parentMap' => '{"version":3,"mappings":"AAAAA;;;;;;;","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks/empty-child.css"],"sourcesContent":[".wp-block-parent{}\n",".wp-block-empty-child{}\n"],"names":["parent-style-rule","empty-child-rule"]}',
        'childBeforeMerge' => '{"version":3,"mappings":";;","sources":["wp-content/themes/example/blocks/empty-child.css"],"sourcesContent":[".wp-block-empty-child{}\n"],"names":["empty-child-rule"]}',
        'childConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'closestAtInsertedSpan' => null,
        'roundTripMap' => '{"version":3,"mappings":"AAAAA;;;;;;;","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks/empty-child.css"],"sourcesContent":[".wp-block-parent{}\n",".wp-block-empty-child{}\n"],"names":["parent-style-rule","empty-child-rule"]}',
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected source-map positive empty child offset output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['parentMap'] . "\n";
