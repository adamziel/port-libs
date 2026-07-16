<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$compiled = new SourceMap();
$compiledSource = $compiled->addSource('wp-content/cache/block-editor.compiled.css');
$compiled->setSourceContent($compiledSource, ".wp-block-first{color:red}.wp-block-broken{color:blue}\n.wp-block-keep{}");
$compiled->addMapping(0, 0, $compiledSource, 0, 0, 'compiled-first');
$compiled->addMapping(0, 28, $compiledSource, 1, 0, 'compiled-broken');
$compiled->addMapping(1, 3, $compiledSource, 2, 0, 'compiled-keep');

$input = new SourceMap();
$first = $input->addSource('wp-content/themes/example/blocks/first.scss');
$broken = $input->addSource('wp-content/themes/example/blocks/broken.scss');
$input->setSourceContent($first, ".wp-block-first {\n  color: red;\n}");
$input->setSourceContent($broken, ".wp-block-broken {\n  color: blue;\n}");
$input->addMapping(0, 0, $first, 12, 2, 'input-first');
$input->addMapping(1, 0, $broken, 20, 4, 'input-broken');

$corruptInputMapping = Closure::bind(static function (SourceMap $sourceMap): void {
    $sourceMap->mappings[1]['sourceIndex'] = 99;
}, null, SourceMap::class);
if ($corruptInputMapping === null) {
    throw new RuntimeException('Unable to bind SourceMap example helper.');
}

$corruptInputMapping($input);

$rejected = false;
try {
    $compiled->extendWithSourceMap($input);
} catch (InvalidArgumentException) {
    $rejected = true;
}

$actual = [
    'rejected' => $rejected,
    'compiledMap' => $compiled->toJson(null, false),
    'compiledMappings' => $compiled->getMappings(),
    'remappedFirst' => $compiled->findClosestMapping(0, 0),
    'retainedBroken' => $compiled->findClosestMapping(0, 28),
    'inputSources' => $input->getSources(),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'rejected' => true,
        'compiledMap' => '{"version":3,"mappings":"ACYEG,4BDXFF;GACAC","sources":["wp-content/cache/block-editor.compiled.css","wp-content/themes/example/blocks/first.scss","wp-content/themes/example/blocks/broken.scss"],"sourcesContent":[".wp-block-first{color:red}.wp-block-broken{color:blue}\n.wp-block-keep{}",".wp-block-first {\n  color: red;\n}",".wp-block-broken {\n  color: blue;\n}"],"names":["compiled-first","compiled-broken","compiled-keep","input-first","input-broken"]}',
        'compiledMappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 1, 'originalLine' => 12, 'originalColumn' => 2, 'nameIndex' => 3],
            ['generatedLine' => 0, 'generatedColumn' => 28, 'sourceIndex' => 0, 'originalLine' => 1, 'originalColumn' => 0, 'nameIndex' => 1],
            ['generatedLine' => 1, 'generatedColumn' => 3, 'sourceIndex' => 0, 'originalLine' => 2, 'originalColumn' => 0, 'nameIndex' => 2],
        ],
        'remappedFirst' => ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 1, 'originalLine' => 12, 'originalColumn' => 2, 'nameIndex' => 3],
        'retainedBroken' => ['generatedLine' => 0, 'generatedColumn' => 28, 'sourceIndex' => 0, 'originalLine' => 1, 'originalColumn' => 0, 'nameIndex' => 1],
        'inputSources' => [
            'wp-content/themes/example/blocks/first.scss',
            'wp-content/themes/example/blocks/broken.scss',
        ],
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected rejected input source-map extension output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['compiledMap'] . "\n";
