<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$entryCss = ".wp-block-card{color:var(--preset)}\n";
$firstScss = <<<'SCSS'
.wp-block-card { color: $brand; }
.wp-block-card__title { font-weight: 600; }
SCSS;
$firstScss .= "\n";
$secondScss = ".wp-block-card { color: changed; }\n";

$parent = new SourceMap('/srv/www/site/wp-content/themes/example');
$sharedScss = $parent->addSource('assets/scss/blocks/card.scss');
$entry = $parent->addSource('build/editor.css');
$parent->setSourceContent($entry, $entryCss);
$parent->addMapping(0, 0, $entry, 0, 0, 'editor-entry');

$child = new SourceMap('/srv/www/site/wp-content/themes/example');
$childShared = $child->addSource('./assets/scss/blocks/card.scss');
$unused = $child->addSource('assets/scss/blocks/unused.scss');
$child->setSourceContent($childShared, $firstScss);
$child->setSourceContent($unused, ".wp-block-unused { color: red; }\n");
$child->addGeneratedMapping(0, 3);
$child->addMapping(0, 8, $childShared, 12, 4, 'card-rule');
$child->addMapping(1, 2, $childShared, 13, 6, 'title-rule');
$child->addName('unused-rule');

$parent->appendSourceMapWithGeneratedOffset($child, 2, 9, false);

$second = new SourceMap('/srv/www/site/wp-content/themes/example');
$secondShared = $second->addSource('assets/scss/blocks/card.scss');
$second->setSourceContent($secondShared, $secondScss);
$second->addMapping(0, 6, $secondShared, 20, 2, 'second-pass');

$parent->appendSourceMapWithGeneratedOffset($second, 4, 4, false);

$decoded = SourceMap::decodeVlq($parent->writeVlq());
$actual = [
    'map' => $parent->toJson(null, false),
    'decodedLines' => array_column($decoded, 'generatedLine'),
    'decodedColumns' => array_column($decoded, 'generatedColumn'),
    'sourcesContent' => $parent->getSourcesContent(),
    'names' => $parent->getNames(),
    'sharedIndex' => $sharedScss,
    'childConsumed' => $child->toJson(null, false),
    'secondConsumed' => $second->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'map' => '{"version":3,"mappings":"ACAAA;;iBDYIC;EACEC;UAOJC","sources":["assets/scss/blocks/card.scss","build/editor.css"],"sourcesContent":[".wp-block-card { color: $brand; }\n.wp-block-card__title { font-weight: 600; }\n",".wp-block-card{color:var(--preset)}\n"],"names":["editor-entry","card-rule","title-rule","second-pass"]}',
        'decodedLines' => [0, 2, 3, 4],
        'decodedColumns' => [0, 17, 2, 10],
        'sourcesContent' => [
            $firstScss,
            $entryCss,
        ],
        'names' => ['editor-entry', 'card-rule', 'title-rule', 'second-pass'],
        'sharedIndex' => 0,
        'childConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
        'secondConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected pruned reused source-map content output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
