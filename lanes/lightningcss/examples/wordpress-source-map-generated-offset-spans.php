<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$parent = new SourceMap();
$parentSource = $parent->addSource('wp-content/themes/example/style.css');
$parent->setSourceContent($parentSource, ".theme{color:green}\n");
$parent->addMapping(0, 0, $parentSource, 0, 0, 'theme-root');

$child = new SourceMap();
$childSource = $child->addSource('wp-content/themes/example/blocks/card.generated.css');
$child->setSourceContent($childSource, ".wp-block-card{color:red}\n");
$child->addMapping(0, 0, $childSource, 0, 0, 'card-block');
$child->offsetLines(1, 2);

$childBeforeAppend = $child->toJson(null, false);
$parent->appendSourceMapWithGeneratedOffset($child, 2, 4);

$actual = [
    'childBeforeAppend' => $childBeforeAppend,
    'map' => $parent->toJson(null, false),
    'mappings' => $parent->getMappings(),
    'emptyTrailingLine' => $parent->findClosestMapping(3, 0),
    'childConsumed' => $child->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'childBeforeAppend' => '{"version":3,"mappings":"AAAAA;;","sources":["wp-content/themes/example/blocks/card.generated.css"],"sourcesContent":[".wp-block-card{color:red}\n"],"names":["card-block"]}',
        'map' => '{"version":3,"mappings":"AAAAA;;ICAAC;;","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks/card.generated.css"],"sourcesContent":[".theme{color:green}\n",".wp-block-card{color:red}\n"],"names":["theme-root","card-block"]}',
        'mappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 0],
            ['generatedLine' => 2, 'generatedColumn' => 4, 'sourceIndex' => 1, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 1],
        ],
        'emptyTrailingLine' => null,
        'childConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected generated-offset source-map span output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
