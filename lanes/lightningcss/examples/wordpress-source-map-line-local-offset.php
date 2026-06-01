<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$map = new SourceMap();
$sourceIndex = $map->addSource('wp-content/themes/example/blocks/line-local.css');
$map->setSourceContent($sourceIndex, ".wp-block-later{}\n.wp-block-earlier{}\n");
$map->addMapping(0, 10, $sourceIndex, 0, 0, 'line0-later');
$map->addMapping(0, 2, $sourceIndex, 1, 0, 'line0-earlier');
$map->addMapping(1, 8, $sourceIndex, 2, 0, 'line1-later');
$map->addMapping(1, 3, $sourceIndex, 3, 0, 'line1-earlier');

$beforeOffsetColumns = array_column($map->getMappings(), 'generatedColumn');
$beforeOffsetNames = array_column($map->getMappings(), 'nameIndex');

$map->offsetColumns(1, 5, 2);

$afterOffsetColumns = array_column($map->getMappings(), 'generatedColumn');
$afterOffsetNames = array_column($map->getMappings(), 'nameIndex');
$vlq = $map->writeVlq();
$afterWriteColumns = array_column($map->getMappings(), 'generatedColumn');
$afterWriteNames = array_column($map->getMappings(), 'nameIndex');
$line0Closest = $map->findClosestMapping(0, 8);
$line1Closest = $map->findClosestMapping(1, 9);

$actual = [
    'beforeOffsetColumns' => $beforeOffsetColumns,
    'beforeOffsetNames' => $beforeOffsetNames,
    'afterOffsetColumns' => $afterOffsetColumns,
    'afterOffsetNames' => $afterOffsetNames,
    'vlq' => $vlq,
    'afterWriteColumns' => $afterWriteColumns,
    'afterWriteNames' => $afterWriteNames,
    'line0Closest' => $line0Closest,
    'line1Closest' => $line1Closest,
    'map' => $map->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'beforeOffsetColumns' => [10, 2, 8, 3],
        'beforeOffsetNames' => [0, 1, 2, 3],
        'afterOffsetColumns' => [10, 2, 3, 10],
        'afterOffsetNames' => [0, 1, 3, 2],
        'vlq' => 'EACAC,QADAD;GAGAG,OADAD',
        'afterWriteColumns' => [2, 10, 3, 10],
        'afterWriteNames' => [1, 0, 3, 2],
        'line0Closest' => ['generatedLine' => 0, 'generatedColumn' => 2, 'sourceIndex' => 0, 'originalLine' => 1, 'originalColumn' => 0, 'nameIndex' => 1],
        'line1Closest' => ['generatedLine' => 1, 'generatedColumn' => 3, 'sourceIndex' => 0, 'originalLine' => 3, 'originalColumn' => 0, 'nameIndex' => 3],
        'map' => '{"version":3,"mappings":"EACAC,QADAD;GAGAG,OADAD","sources":["wp-content/themes/example/blocks/line-local.css"],"sourcesContent":[".wp-block-later{}\n.wp-block-earlier{}\n"],"names":["line0-later","line0-earlier","line1-later","line1-earlier"]}',
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected source-map line-local offset output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
