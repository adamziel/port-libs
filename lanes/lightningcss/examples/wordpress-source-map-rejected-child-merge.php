<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$parent = new SourceMap();
$parentSource = $parent->addSource('wp-content/themes/example/style.css');
$parent->setSourceContent($parentSource, ".wp-block-parent{}\n");
$parent->addMapping(0, 0, $parentSource, 0, 0, 'parent-rule');

$child = new SourceMap();
$childSource = $child->addSource('wp-content/themes/example/blocks/broken-child.css');
$child->setSourceContent($childSource, ".wp-block-broken-child{}\n");
$child->addMapping(0, 4, $childSource, 2, 1, 'broken-child-rule');

$corruptChildMapping = Closure::bind(static function (SourceMap $sourceMap): void {
    $sourceMap->mappings[0]['sourceIndex'] = 99;
}, null, SourceMap::class);
if ($corruptChildMapping === null) {
    throw new RuntimeException('Unable to bind SourceMap example helper.');
}

$corruptChildMapping($child);

$rejected = false;
try {
    $parent->addSourceMap($child, 2);
} catch (InvalidArgumentException) {
    $rejected = true;
}

$partialParent = new SourceMap();
$partialParentSource = $partialParent->addSource('wp-content/themes/example/partial-style.css');
for ($line = 0; $line < 3; $line++) {
    $partialParent->addMapping($line, 0, $partialParentSource, $line, 0, 'partial-parent-' . $line);
}

$partialChild = new SourceMap();
$partialChildSource = $partialChild->addSource('wp-content/themes/example/blocks/partial-broken-child.css');
$partialChild->setSourceContent($partialChildSource, ".wp-block-valid{}\n.wp-block-broken{}\n");
$partialChild->addMapping(0, 4, $partialChildSource, 10, 1, 'partial-valid-child');
$partialChild->addMapping(1, 6, $partialChildSource, 11, 2, 'partial-broken-child');

$corruptPartialChildMapping = Closure::bind(static function (SourceMap $sourceMap): void {
    $sourceMap->mappings[1]['sourceIndex'] = 99;
}, null, SourceMap::class);
if ($corruptPartialChildMapping === null) {
    throw new RuntimeException('Unable to bind SourceMap example helper.');
}

$corruptPartialChildMapping($partialChild);

$partialRejected = false;
try {
    $partialParent->addSourceMap($partialChild, 1);
} catch (InvalidArgumentException) {
    $partialRejected = true;
}

$sameLineParent = new SourceMap();
$sameLineParentSource = $sameLineParent->addSource('wp-content/themes/example/same-line-style.css');
for ($line = 0; $line < 4; $line++) {
    $sameLineParent->addMapping($line, 0, $sameLineParentSource, $line, 0, 'same-line-parent-' . $line);
}

$sameLineChild = new SourceMap();
$sameLineChildSource = $sameLineChild->addSource('wp-content/themes/example/blocks/same-line-broken-child.css');
$sameLineChild->setSourceContent($sameLineChildSource, ".wp-block-same-line-0{}\n.wp-block-same-line-1{}\n");
$sameLineChild->addMapping(0, 4, $sameLineChildSource, 10, 1, 'same-line-child-0');
$sameLineChild->addMapping(1, 2, $sameLineChildSource, 20, 1, 'same-line-valid');
$sameLineChild->addMapping(1, 8, $sameLineChildSource, 21, 2, 'same-line-broken');

$corruptSameLineChildMapping = Closure::bind(static function (SourceMap $sourceMap): void {
    $sourceMap->mappings[2]['nameIndex'] = 99;
}, null, SourceMap::class);
if ($corruptSameLineChildMapping === null) {
    throw new RuntimeException('Unable to bind SourceMap example helper.');
}

$corruptSameLineChildMapping($sameLineChild);

$sameLineRejected = false;
try {
    $sameLineParent->addSourceMap($sameLineChild, 1);
} catch (InvalidArgumentException) {
    $sameLineRejected = true;
}

$skippedCorruptParent = new SourceMap();
$skippedCorruptParentSource = $skippedCorruptParent->addSource('wp-content/themes/example/skipped-corrupt-style.css');
$skippedCorruptParent->setSourceContent($skippedCorruptParentSource, ".wp-block-skipped-parent{}\n");
$skippedCorruptParent->addMapping(0, 0, $skippedCorruptParentSource, 0, 0, 'skipped-corrupt-parent');

$skippedCorruptChild = new SourceMap();
$skippedCorruptChildSource = $skippedCorruptChild->addSource('wp-content/themes/example/blocks/skipped-corrupt-child.css');
$skippedCorruptChild->setSourceContent($skippedCorruptChildSource, ".wp-block-skipped-corrupt{}\n");
$skippedCorruptChild->addMapping(0, 4, $skippedCorruptChildSource, 6, 1, 'skipped-corrupt-child');

$corruptSkippedChildMapping = Closure::bind(static function (SourceMap $sourceMap): void {
    $sourceMap->mappings[0]['sourceIndex'] = 99;
}, null, SourceMap::class);
if ($corruptSkippedChildMapping === null) {
    throw new RuntimeException('Unable to bind SourceMap example helper.');
}

$corruptSkippedChildMapping($skippedCorruptChild);

$skippedCorruptRejected = false;
try {
    $skippedCorruptParent->addSourceMap($skippedCorruptChild, -1);
} catch (InvalidArgumentException) {
    $skippedCorruptRejected = true;
}

$actual = [
    'rejected' => $rejected,
    'parentMap' => $parent->toJson(null, false),
    'parentMappings' => $parent->getMappings(),
    'childConsumed' => $child->toJson(null, false),
    'partialRejected' => $partialRejected,
    'partialParentMap' => $partialParent->toJson(null, false),
    'partialParentMappings' => $partialParent->getMappings(),
    'partialValidLookup' => $partialParent->findClosestMapping(1, 4),
    'partialChildConsumed' => $partialChild->toJson(null, false),
    'sameLineRejected' => $sameLineRejected,
    'sameLineParentMap' => $sameLineParent->toJson(null, false),
    'sameLineParentMappings' => $sameLineParent->getMappings(),
    'sameLineAppliedLookup' => $sameLineParent->findClosestMapping(1, 4),
    'sameLineRetainedLookup' => $sameLineParent->findClosestMapping(2, 0),
    'sameLineChildConsumed' => $sameLineChild->toJson(null, false),
    'skippedCorruptRejected' => $skippedCorruptRejected,
    'skippedCorruptParentMap' => $skippedCorruptParent->toJson(null, false),
    'skippedCorruptParentMappings' => $skippedCorruptParent->getMappings(),
    'skippedCorruptChildConsumed' => $skippedCorruptChild->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'rejected' => true,
        'parentMap' => '{"version":3,"mappings":"AAAAA","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks/broken-child.css"],"sourcesContent":[".wp-block-parent{}\n",".wp-block-broken-child{}\n"],"names":["parent-rule","broken-child-rule"]}',
        'parentMappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 0],
        ],
        'childConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'partialRejected' => true,
        'partialParentMap' => '{"version":3,"mappings":"AAAAA;ICUCG;ADRDD","sources":["wp-content/themes/example/partial-style.css","wp-content/themes/example/blocks/partial-broken-child.css"],"sourcesContent":["",".wp-block-valid{}\n.wp-block-broken{}\n"],"names":["partial-parent-0","partial-parent-1","partial-parent-2","partial-valid-child","partial-broken-child"]}',
        'partialParentMappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 0],
            ['generatedLine' => 1, 'generatedColumn' => 4, 'sourceIndex' => 1, 'originalLine' => 10, 'originalColumn' => 1, 'nameIndex' => 3],
            ['generatedLine' => 2, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 2, 'originalColumn' => 0, 'nameIndex' => 2],
        ],
        'partialValidLookup' => ['generatedLine' => 1, 'generatedColumn' => 4, 'sourceIndex' => 1, 'originalLine' => 10, 'originalColumn' => 1, 'nameIndex' => 3],
        'partialChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'sameLineRejected' => true,
        'sameLineParentMap' => '{"version":3,"mappings":"AAAAA;ICUCI;ADRDF;AACAC","sources":["wp-content/themes/example/same-line-style.css","wp-content/themes/example/blocks/same-line-broken-child.css"],"sourcesContent":["",".wp-block-same-line-0{}\n.wp-block-same-line-1{}\n"],"names":["same-line-parent-0","same-line-parent-1","same-line-parent-2","same-line-parent-3","same-line-child-0","same-line-valid","same-line-broken"]}',
        'sameLineParentMappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 0],
            ['generatedLine' => 1, 'generatedColumn' => 4, 'sourceIndex' => 1, 'originalLine' => 10, 'originalColumn' => 1, 'nameIndex' => 4],
            ['generatedLine' => 2, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 2, 'originalColumn' => 0, 'nameIndex' => 2],
            ['generatedLine' => 3, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 3, 'originalColumn' => 0, 'nameIndex' => 3],
        ],
        'sameLineAppliedLookup' => ['generatedLine' => 1, 'generatedColumn' => 4, 'sourceIndex' => 1, 'originalLine' => 10, 'originalColumn' => 1, 'nameIndex' => 4],
        'sameLineRetainedLookup' => ['generatedLine' => 2, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 2, 'originalColumn' => 0, 'nameIndex' => 2],
        'sameLineChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'skippedCorruptRejected' => false,
        'skippedCorruptParentMap' => '{"version":3,"mappings":"AAAAA","sources":["wp-content/themes/example/skipped-corrupt-style.css","wp-content/themes/example/blocks/skipped-corrupt-child.css"],"sourcesContent":[".wp-block-skipped-parent{}\n",".wp-block-skipped-corrupt{}\n"],"names":["skipped-corrupt-parent","skipped-corrupt-child"]}',
        'skippedCorruptParentMappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 0],
        ],
        'skippedCorruptChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected rejected child source-map merge output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['parentMap'] . "\n";
