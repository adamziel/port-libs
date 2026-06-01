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

$actual = [
    'rejected' => $rejected,
    'parentMap' => $parent->toJson(null, false),
    'parentMappings' => $parent->getMappings(),
    'childConsumed' => $child->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'rejected' => true,
        'parentMap' => '{"version":3,"mappings":"AAAAA","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks/broken-child.css"],"sourcesContent":[".wp-block-parent{}\n",".wp-block-broken-child{}\n"],"names":["parent-rule","broken-child-rule"]}',
        'parentMappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 0],
        ],
        'childConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected rejected child source-map merge output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['parentMap'] . "\n";
