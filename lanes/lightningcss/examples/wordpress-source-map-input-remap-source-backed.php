<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$parent = new SourceMap();
$entrySource = $parent->addSource('wp-content/themes/example/style.css');
$parent->setSourceContent($entrySource, ".theme{color:green}\n");
$parent->addMapping(0, 0, $entrySource, 0, 0, 'theme-root');

$child = new SourceMap();
$childSource = $child->addSource('wp-content/themes/example/blocks/card.scss');
$unusedSource = $child->addSource('wp-content/themes/example/blocks/unused.scss');
$child->setSourceContent($childSource, ".wp-block-card { color: \$brand; }\n");
$child->setSourceContent($unusedSource, ".wp-block-unused { color: red; }\n");
$child->addGeneratedMapping(0, 0);
$child->addMapping(0, 5, $childSource, 2, 1, 'card-rule');
$child->addGeneratedMapping(1, 3);
$child->addMapping(1, 8, $childSource, 3, 2, 'card-decl');
$child->addName('unused-name');

$childBeforeAppend = $child->toJson(null, false);
$parent->appendSourceMapWithGeneratedOffset($child, 2, 4, false);

$trailingParent = new SourceMap();
$trailingEntrySource = $trailingParent->addSource('wp-content/themes/example/style.css');
$trailingParent->setSourceContent($trailingEntrySource, ".theme{color:green}\n");
$trailingParent->addMapping(0, 0, $trailingEntrySource, 0, 0, 'theme-root');

$trailingChild = new SourceMap();
$trailingChildSource = $trailingChild->addSource('wp-content/themes/example/blocks/trailing.scss');
$trailingChild->setSourceContent($trailingChildSource, ".wp-block-trailing { color: \$brand; }\n");
$trailingChild->addMapping(0, 0, $trailingChildSource, 2, 1, 'trailing-rule');
$trailingChild->addGeneratedMapping(1, 4);
$trailingChild->offsetLines(2, 2);
$trailingChildBeforeAppend = $trailingChild->toJson(null, false);
$trailingParent->appendSourceMapWithGeneratedOffset($trailingChild, 2, 4, false);

$generatedOnlyParent = new SourceMap();
$generatedOnlyEntrySource = $generatedOnlyParent->addSource('wp-content/themes/example/style.css');
$generatedOnlyParent->setSourceContent($generatedOnlyEntrySource, ".theme{color:green}\n");
$generatedOnlyParent->addMapping(0, 0, $generatedOnlyEntrySource, 0, 0, 'theme-root');

$generatedOnlyChild = new SourceMap();
$generatedOnlyChildSource = $generatedOnlyChild->addSource('wp-content/themes/example/blocks/generated-only.scss');
$generatedOnlyChild->setSourceContent($generatedOnlyChildSource, ".wp-block-generated-only { color: \$brand; }\n");
$generatedOnlyChild->addGeneratedMapping(1, 5);
$generatedOnlyChild->addName('unused-generated-only');
$generatedOnlyParent->appendSourceMapWithGeneratedOffset($generatedOnlyChild, 2, 4, false);

$actual = [
    'childBeforeAppend' => $childBeforeAppend,
    'map' => $parent->toJson(null, false),
    'mappings' => $parent->getMappings(),
    'childConsumed' => $child->toJson(null, false),
    'trailingChildBeforeAppend' => $trailingChildBeforeAppend,
    'trailingMap' => $trailingParent->toJson(null, false),
    'trailingEmptyClosest' => $trailingParent->findClosestMapping(3, 0),
    'trailingChildConsumed' => $trailingChild->toJson(null, false),
    'generatedOnlyMap' => $generatedOnlyParent->toJson(null, false),
    'generatedOnlyChildConsumed' => $generatedOnlyChild->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'childBeforeAppend' => '{"version":3,"mappings":"A,KAECA;G,KACCC","sources":["wp-content/themes/example/blocks/card.scss","wp-content/themes/example/blocks/unused.scss"],"sourcesContent":[".wp-block-card { color: $brand; }\n",".wp-block-unused { color: red; }\n"],"names":["card-rule","card-decl","unused-name"]}',
        'map' => '{"version":3,"mappings":"AAAAA;;SCECC;QACCC","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks/card.scss"],"sourcesContent":[".theme{color:green}\n",".wp-block-card { color: $brand; }\n"],"names":["theme-root","card-rule","card-decl"]}',
        'mappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 0],
            ['generatedLine' => 2, 'generatedColumn' => 9, 'sourceIndex' => 1, 'originalLine' => 2, 'originalColumn' => 1, 'nameIndex' => 1],
            ['generatedLine' => 3, 'generatedColumn' => 8, 'sourceIndex' => 1, 'originalLine' => 3, 'originalColumn' => 2, 'nameIndex' => 2],
        ],
        'childConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'trailingChildBeforeAppend' => '{"version":3,"mappings":"AAECA;I;;","sources":["wp-content/themes/example/blocks/trailing.scss"],"sourcesContent":[".wp-block-trailing { color: $brand; }\n"],"names":["trailing-rule"]}',
        'trailingMap' => '{"version":3,"mappings":"AAAAA;;ICECC","sources":["wp-content/themes/example/style.css","wp-content/themes/example/blocks/trailing.scss"],"sourcesContent":[".theme{color:green}\n",".wp-block-trailing { color: $brand; }\n"],"names":["theme-root","trailing-rule"]}',
        'trailingEmptyClosest' => null,
        'trailingChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'generatedOnlyMap' => '{"version":3,"mappings":"AAAAA","sources":["wp-content/themes/example/style.css"],"sourcesContent":[".theme{color:green}\n"],"names":["theme-root"]}',
        'generatedOnlyChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected input-remap source-map output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
