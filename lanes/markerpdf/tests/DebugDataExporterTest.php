<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\DebugDataExporter;
use PortLibs\MarkerPDF\MarkerSettings;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-debug-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf debug test folder.');
    }

    return $path;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            unlink($child);
        }
    }

    rmdir($path);
};

$debugPage = static function (): array {
    return [
        'pnum' => 0,
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'blocks' => [
            [
                'block_type' => 'Title',
                'bbox' => [72.0, 48.0, 420.0, 84.0],
                'lines' => [
                    ['text' => 'Migration Brief', 'bbox' => [72.0, 48.0, 420.0, 84.0]],
                ],
            ],
        ],
        'rotation' => 0,
        'images' => ['0_image_0.png' => 'PNG'],
        'layout' => [
            'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
            'bboxes' => [
                ['label' => 'Title', 'bbox' => [144.0, 96.0, 840.0, 168.0]],
            ],
            'segmentation_map' => 'large-pixel-mask',
        ],
        'text_lines' => [
            'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
            'bboxes' => [
                ['bbox' => [144.0, 96.0, 840.0, 168.0]],
            ],
            'heatmap' => 'large-heatmap',
            'affinity_map' => 'large-affinity-map',
        ],
    ];
};

return [
    'skips bbox debug export when upstream debug setting is disabled' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $debugPage): void {
        $root = $makeTempDir();
        try {
            $exporter = new DebugDataExporter(new MarkerSettings([
                'DEBUG' => false,
                'DEBUG_DATA_FOLDER' => $root,
            ]));

            $t->same(null, $exporter->dumpBboxDebugData('/uploads/migration.pdf', [$debugPage()]));
            $t->same([], array_values(array_diff(scandir($root) ?: [], ['.', '..'])));
        } finally {
            $removeTree($root);
        }
    },
    'builds bbox debug payload without image and model-only fields' => static function (TestRunner $t) use ($debugPage): void {
        $payload = (new DebugDataExporter())->bboxDebugData([$debugPage()]);

        $t->same([
            [
                'pnum' => 0,
                'bbox' => [0.0, 0.0, 600.0, 800.0],
                'blocks' => [
                    [
                        'block_type' => 'Title',
                        'bbox' => [72.0, 48.0, 420.0, 84.0],
                        'lines' => [
                            ['text' => 'Migration Brief', 'bbox' => [72.0, 48.0, 420.0, 84.0]],
                        ],
                    ],
                ],
                'rotation' => 0,
                'layout' => [
                    'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
                    'bboxes' => [
                        ['label' => 'Title', 'bbox' => [144.0, 96.0, 840.0, 168.0]],
                    ],
                ],
                'text_lines' => [
                    'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
                    'bboxes' => [
                        ['bbox' => [144.0, 96.0, 840.0, 168.0]],
                    ],
                ],
            ],
        ], $payload);
    },
    'writes upstream doc-base bbox json file when debug is enabled' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $debugPage): void {
        $root = $makeTempDir();
        try {
            $exporter = new DebugDataExporter(new MarkerSettings([
                'DEBUG' => true,
                'DEBUG_DATA_FOLDER' => $root,
            ]));

            $path = $exporter->dumpBboxDebugData('/uploads/migration.packet.pdf', [$debugPage()]);

            $t->same($root . DIRECTORY_SEPARATOR . 'migration.packet_bbox.json', $path);
            $json = (string) file_get_contents((string) $path);
            $t->contains('"layout"', $json);
            $t->contains('"text_lines"', $json);
            $t->true(!str_contains($json, 'segmentation_map'));
            $t->true(!str_contains($json, 'heatmap'));
            $t->true(!str_contains($json, '0_image_0.png'));
        } finally {
            $removeTree($root);
        }
    },
    'emits WordPress review metadata from bbox debug payload' => static function (TestRunner $t) use ($debugPage): void {
        $payload = (new DebugDataExporter())->bboxDebugData([$debugPage()]);
        $review = [
            'scenario' => 'wordpress-debug-bbox-export',
            'pageCount' => count($payload),
            'layoutLabels' => array_column($payload[0]['layout']['bboxes'], 'label'),
            'textLineCount' => count($payload[0]['text_lines']['bboxes']),
            'blockTypes' => array_column($payload[0]['blocks'], 'block_type'),
        ];

        $t->same('wordpress-debug-bbox-export', $review['scenario']);
        $t->same(['Title'], $review['layoutLabels']);
        $t->same(1, $review['textLineCount']);
        $t->same(['Title'], $review['blockTypes']);
    },
    'requires layout and text line data when debug payloads are built' => static function (TestRunner $t): void {
        $exporter = new DebugDataExporter(new MarkerSettings(['DEBUG' => true]));

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $exporter->bboxDebugData([['pnum' => 0, 'blocks' => []]])
        );
    },
];
