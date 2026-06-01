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
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected rejected child source-map merge output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['parentMap'] . "\n";
