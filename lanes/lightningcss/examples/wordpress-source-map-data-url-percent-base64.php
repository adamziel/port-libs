<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$json = '{"version":3,"mappings":";CAAA","sources":["wp-content/themes/example/blocks/encoded.css"],"sourcesContent":[".wp-block-encoded{color:red}"],"names":[]}';
$encoded = str_replace('=', '%3D', base64_encode($json));
$encoded = substr($encoded, 0, 16) . '%0C' . substr($encoded, 16);

$child = SourceMap::fromDataUrl('data:application/json;charset=utf-8;base64,' . $encoded . '#theme-editor');
$childBeforeMerge = $child->toJson(null, false);

$parent = new SourceMap();
$parent->addGeneratedMapping(0, 0);
$parent->addSourceMap($child, 2);

$triviaJson = '{"version":3,"mappings":";CAAA","sources":["wp-content/themes/example/blocks/trivia.css"],"sourcesContent":[".wp-block-trivia{color:blue}"],"names":[]}';
$triviaEncoded = base64_encode($triviaJson);
$triviaEncoded = substr($triviaEncoded, 0, 10) . " \t" . substr($triviaEncoded, 10, 12) . '%0C' . substr($triviaEncoded, 22);
$triviaChild = SourceMap::fromDataUrl(" \x00\tD\na\rtA: application/json ; \tbase64 ," . $triviaEncoded . " \n#theme-editor");
$triviaChildBeforeMerge = $triviaChild->toJson(null, false);
$triviaParent = new SourceMap();
$triviaParent->addGeneratedMapping(0, 0);
$triviaParent->addSourceMap($triviaChild, 2);

$actual = [
    'childBeforeMerge' => $childBeforeMerge,
    'parentMap' => $parent->toJson(null, false),
    'parentMappings' => $parent->getMappings(),
    'childConsumed' => $child->toJson(null, false),
    'triviaChildBeforeMerge' => $triviaChildBeforeMerge,
    'triviaParentMap' => $triviaParent->toJson(null, false),
    'triviaParentMappings' => $triviaParent->getMappings(),
    'triviaChildConsumed' => $triviaChild->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'childBeforeMerge' => '{"version":3,"mappings":";CAAA","sources":["wp-content/themes/example/blocks/encoded.css"],"sourcesContent":[".wp-block-encoded{color:red}"],"names":[]}',
        'parentMap' => '{"version":3,"mappings":"A;;;CAAA","sources":["wp-content/themes/example/blocks/encoded.css"],"sourcesContent":[".wp-block-encoded{color:red}"],"names":[]}',
        'parentMappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null],
            ['generatedLine' => 3, 'generatedColumn' => 1, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => null],
        ],
        'childConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'triviaChildBeforeMerge' => '{"version":3,"mappings":";CAAA","sources":["wp-content/themes/example/blocks/trivia.css"],"sourcesContent":[".wp-block-trivia{color:blue}"],"names":[]}',
        'triviaParentMap' => '{"version":3,"mappings":"A;;;CAAA","sources":["wp-content/themes/example/blocks/trivia.css"],"sourcesContent":[".wp-block-trivia{color:blue}"],"names":[]}',
        'triviaParentMappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => null, 'originalLine' => null, 'originalColumn' => null, 'nameIndex' => null],
            ['generatedLine' => 3, 'generatedColumn' => 1, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => null],
        ],
        'triviaChildConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected percent-base64 source-map output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['parentMap'] . "\n";
