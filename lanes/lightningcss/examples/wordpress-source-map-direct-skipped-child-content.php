<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$parent = new SourceMap('/srv/www/site/wp-content/themes/example');
$compiled = $parent->addSource('wp-content/cache/block-editor.css');
$shared = $parent->addSource('blocks/shared.scss');
$parent->setSourceContent($compiled, ".wp-block-entry{}\n");
$parent->setSourceContent($shared, ".wp-block-shared { color: var(--old); }\n");
$parent->addMapping(0, 0, $compiled, 0, 0, 'compiledEntry');

$child = new SourceMap('/srv/www/site/wp-content/themes/example');
$childShared = $child->addSource('./blocks/shared.scss');
$child->setSourceContent($childShared, ".wp-block-shared { color: var(--new); }\n");
$child->addMapping(0, 4, $childShared, 8, 2, 'sharedSourceRule');
$child->addName('skippedButImportedName');

$parent->addSourceMap($child, -1);

$actual = [
    'map' => $parent->toJson(null, false),
    'mappings' => $parent->getMappings(),
    'sources' => $parent->getSources(),
    'sourcesContent' => $parent->getSourcesContent(),
    'names' => $parent->getNames(),
    'childConsumed' => $child->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'map' => '{"version":3,"mappings":"AAAAA","sources":["wp-content/cache/block-editor.css","blocks/shared.scss"],"sourcesContent":[".wp-block-entry{}\n",".wp-block-shared { color: var(--new); }\n"],"names":["compiledEntry","sharedSourceRule","skippedButImportedName"]}',
        'mappings' => [
            ['generatedLine' => 0, 'generatedColumn' => 0, 'sourceIndex' => 0, 'originalLine' => 0, 'originalColumn' => 0, 'nameIndex' => 0],
        ],
        'sources' => ['wp-content/cache/block-editor.css', 'blocks/shared.scss'],
        'sourcesContent' => [".wp-block-entry{}\n", ".wp-block-shared { color: var(--new); }\n"],
        'names' => ['compiledEntry', 'sharedSourceRule', 'skippedButImportedName'],
        'childConsumed' => '{"version":3,"mappings":"","sources":[],"sourcesContent":[],"names":[]}',
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected direct skipped-child source-map output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
