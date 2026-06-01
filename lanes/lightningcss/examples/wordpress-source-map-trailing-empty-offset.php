<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$parent = new SourceMap();
$parentSource = $parent->addSource('wp-content/themes/example/style.css');
for ($line = 0; $line < 4; $line++) {
    $parent->addMapping($line, 0, $parentSource, $line, 0, 'theme-line-' . $line);
}

$child = new SourceMap();
$childSource = $child->addSource('wp-content/themes/example/blocks/skipped-trailing.css');
$child->setSourceContent($childSource, ".wp-block-skipped-trailing{}\n");
$child->addMapping(0, 5, $childSource, 4, 1, 'skipped-trailing-block');
$child->offsetLines(1, 2);

$childBeforeMerge = $child->toJson(null, false);
$parent->addSourceMap($child, -1);

$positiveParent = new SourceMap();
$positiveParentSource = $positiveParent->addSource('wp-content/themes/example/style.css');
for ($line = 0; $line < 6; $line++) {
    $positiveParent->addMapping($line, 0, $positiveParentSource, $line, 0, 'theme-tail-line-' . $line);
}

$positiveChild = new SourceMap();
$positiveChildSource = $positiveChild->addSource('wp-content/themes/example/blocks/tail-span.css');
$positiveChild->setSourceContent($positiveChildSource, ".wp-block-tail-a{}\n.wp-block-tail-b{}\n");
$positiveChild->addMapping(0, 0, $positiveChildSource, 0, 0, 'tail-span-a');
$positiveChild->addMapping(1, 0, $positiveChildSource, 1, 0, 'tail-span-b');
$positiveChild->offsetLines(2, 2);

$positiveChildBeforeMerge = $positiveChild->toJson(null, false);
$positiveParent->addSourceMap($positiveChild, 1);

$interiorParent = new SourceMap();
$interiorParentSource = $interiorParent->addSource('wp-content/themes/example/source-map-interior-parent.css');
for ($line = 0; $line < 5; $line++) {
    $interiorParent->addMapping($line, 0, $interiorParentSource, $line, 0, 'interior-parent-' . $line);
}

$interiorChild = new SourceMap();
$interiorChildSource = $interiorChild->addSource('wp-content/themes/example/blocks/interior-child.css');
$interiorChild->setSourceContent($interiorChildSource, ".wp-block-skip-interior{}\n\n.wp-block-keep-interior{}\n");
$interiorChild->addMapping(0, 2, $interiorChildSource, 0, 0, 'interior-skipped-child');
$interiorChild->addMapping(2, 4, $interiorChildSource, 2, 1, 'interior-kept-child');

$interiorChildBeforeMerge = $interiorChild->toJson(null, false);
$interiorParent->addSourceMap($interiorChild, -1);

$actual = [
    'childBeforeMerge' => $childBeforeMerge,
    'parentMap' => $parent->toJson(null, false),
    'parentMappings' => $parent->getMappings(),
    'clearedFirstLine' => $parent->findClosestMapping(0, 0),
    'childConsumed' => $child->toJson(null, false),
    'positiveChildBeforeMerge' => $positiveChildBeforeMerge,
    'positiveParentMap' => $positiveParent->toJson(null, false),
    'positiveParentMappings' => $positiveParent->getMappings(),
    'positiveClearedMiddleLine' => $positiveParent->findClosestMapping(3, 0),
    'positiveChildConsumed' => $positiveChild->toJson(null, false),
    'interiorChildBeforeMerge' => $interiorChildBeforeMerge,
    'interiorParentMap' => $interiorParent->toJson(null, false),
    'interiorParentMappings' => $interiorParent->getMappings(),
    'interiorClearedGapLine' => $interiorParent->findClosestMapping(0, 0),
    'interiorKeptChildMapping' => $interiorParent->findClosestMapping(1, 4),
    'interiorChildConsumed' => $interiorChild->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'childBeforeMerge' => '{"version":3,"mappings":"KAICA;;","sources":["wp-content/themes/example/blocks/skipped-trailing.css"],"sourcesContent":[".wp-block-skipped-trailing{}\n"],"names":["skipped-trailing-block"]}',
        'parentMap' => '{"version":3,"mappings":";;AAEAE;AACAC","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks/skipped-trailing.css"],"sourcesContent":["",".wp-block-skipped-trailing{}\n"],"names":["theme-line-0","theme-line-1","theme-line-2","theme-line-3","skipped-trailing-block"]}',
        'parentMappings' => [
            ['generatedLine' => 2, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 2, 'originalColumn' => 0, 'nameIndex' => 2],
            ['generatedLine' => 3, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 3, 'originalColumn' => 0, 'nameIndex' => 3],
        ],
        'clearedFirstLine' => null,
        'childConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'positiveChildBeforeMerge' => '{"version":3,"mappings":"AAAAA;AACAC;;","sources":["wp-content/themes/example/blocks/tail-span.css"],"sourcesContent":[".wp-block-tail-a{}\n.wp-block-tail-b{}\n"],"names":["tail-span-a","tail-span-b"]}',
        'positiveParentMap' => '{"version":3,"mappings":"AAAAA;ACAAM;AACAC;;;ADIAF","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks/tail-span.css"],"sourcesContent":["",".wp-block-tail-a{}\n.wp-block-tail-b{}\n"],"names":["theme-tail-line-0","theme-tail-line-1","theme-tail-line-2","theme-tail-line-3","theme-tail-line-4","theme-tail-line-5","tail-span-a","tail-span-b"]}',
        'positiveParentMappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 0],
            ['generatedLine' => 1, 'generatedColumn' => 0, 'sourceIndex' => 1, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 6],
            ['generatedLine' => 2, 'generatedColumn' => 0, 'sourceIndex' => 1, 'originalLine' => 1, 'originalColumn' => 0, 'nameIndex' => 7],
            ['generatedLine' => 5, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 5, 'originalColumn' => 0, 'nameIndex' => 5],
        ],
        'positiveClearedMiddleLine' => null,
        'positiveChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'interiorChildBeforeMerge' => '{"version":3,"mappings":"EAAAA;;IAECC","sources":["wp-content/themes/example/blocks/interior-child.css"],"sourcesContent":[".wp-block-skip-interior{}\n\n.wp-block-keep-interior{}\n"],"names":["interior-skipped-child","interior-kept-child"]}',
        'interiorParentMap' => '{"version":3,"mappings":";ICECM;ADADJ;AACAC;AACAC","sources":["wp-content/themes/example/source-map-interior-parent.css","wp-content/themes/example/blocks/interior-child.css"],"sourcesContent":["",".wp-block-skip-interior{}\n\n.wp-block-keep-interior{}\n"],"names":["interior-parent-0","interior-parent-1","interior-parent-2","interior-parent-3","interior-parent-4","interior-skipped-child","interior-kept-child"]}',
        'interiorParentMappings' => [
            ['generatedLine' => 1, 'generatedColumn' => 4, 'sourceIndex' => 1, 'originalLine' => 2, 'originalColumn' => 1, 'nameIndex' => 6],
            ['generatedLine' => 2, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 2, 'originalColumn' => 0, 'nameIndex' => 2],
            ['generatedLine' => 3, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 3, 'originalColumn' => 0, 'nameIndex' => 3],
            ['generatedLine' => 4, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 4, 'originalColumn' => 0, 'nameIndex' => 4],
        ],
        'interiorClearedGapLine' => null,
        'interiorKeptChildMapping' => ['generatedLine' => 1, 'generatedColumn' => 4, 'sourceIndex' => 1, 'originalLine' => 2, 'originalColumn' => 1, 'nameIndex' => 6],
        'interiorChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected trailing-empty source-map output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['parentMap'] . "\n";
