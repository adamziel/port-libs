<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LightningCSS\SourceMap;

$map = new SourceMap('/srv/www/site/wp-content/themes/example');
$shared = $map->addSource('src/shared.scss');
$map->setSourceContent($shared, ".wp-block-card { color: old; }\n");
$map->addName('wp-block-card');

$map->addVlqMap(
    'KAAAA;OCEGC;EDIDD',
    [
        '/srv/www/site/wp-content/themes/example/src/./shared.scss',
        'src/other.scss',
    ],
    [
        ".wp-block-card { color: var(--wp--preset--color--primary); }\n",
        ".wp-block-other { color: blue; }\n",
    ],
    [
        'wp-block-card',
        'wp-block-other',
    ],
    -2,
    0
);

$decoded = SourceMap::decodeVlq($map->writeVlq());
$roundTrip = SourceMap::fromBuffer('/srv/www/site/wp-content/themes/example', $map->toBuffer());

$actual = [
    'map' => $map->toJson(null, false),
    'decoded' => $decoded,
    'closest' => $map->findClosestMapping(0, 2),
    'roundTrip' => $roundTrip->toJson(null, false),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'map' => '{"version":3,"mappings":"EAMEA","sources":["src/shared.scss","src/other.scss"],"sourcesContent":[".wp-block-card { color: var(--wp--preset--color--primary); }\n",".wp-block-other { color: blue; }\n"],"names":["wp-block-card","wp-block-other"]}',
        'decoded' => [
            [
                'generatedLine' => 0,
                'generatedColumn' => 2,
                'sourceIndex' => 0,
                'originalLine' => 6,
                'originalColumn' => 2,
                'nameIndex' => 0,
            ],
        ],
        'closest' => [
            'generatedLine' => 0,
            'generatedColumn' => 2,
            'sourceIndex' => 0,
            'originalLine' => 6,
            'originalColumn' => 2,
            'nameIndex' => 0,
        ],
        'roundTrip' => '{"version":3,"mappings":"EAMEA","sources":["src/shared.scss","src/other.scss"],"sourcesContent":[".wp-block-card { color: var(--wp--preset--color--primary); }\n",".wp-block-other { color: blue; }\n"],"names":["wp-block-card","wp-block-other"]}',
    ];

    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected skipped raw VLQ source-map state output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
