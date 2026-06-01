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

$actual = [
    'childBeforeMerge' => $childBeforeMerge,
    'parentMap' => $parent->toJson(null, false),
    'parentMappings' => $parent->getMappings(),
    'clearedFirstLine' => $parent->findClosestMapping(0, 0),
    'childConsumed' => $child->toJson(null, false),
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
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected trailing-empty source-map output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['parentMap'] . "\n";
