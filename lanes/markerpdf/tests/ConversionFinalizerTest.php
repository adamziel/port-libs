<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportBuilder;
use PortLibs\MarkerPDF\BenchmarkReportVerifier;
use PortLibs\MarkerPDF\ConversionFinalizer;
use PortLibs\MarkerPDF\MarkerSettings;

$line = static function (
    string $text,
    string $spanId,
    array $bbox,
    string $font = 'Body',
    float $weight = 400.0,
    float $fontSize = 12.0
): array {
    return [
        'bbox' => $bbox,
        'spans' => [[
            'span_id' => $spanId,
            'text' => $text,
            'font' => $font,
            'font_weight' => $weight,
            'font_size' => $fontSize,
            'bbox' => $bbox,
        ]],
    ];
};

$pageFromText = static function (string $text, string $document): array {
    $lines = [];
    foreach (preg_split('/\R+/', trim($text)) ?: [] as $index => $part) {
        if ($part === '') {
            continue;
        }
        $top = 72.0 + ($index * 16.0);
        $lines[] = [
            'bbox' => [72.0, $top, 540.0, $top + 12.0],
            'spans' => [[
                'span_id' => $document . '_' . $index,
                'text' => $part,
                'font' => 'Times-Roman',
                'font_weight' => 400.0,
                'font_size' => 12.0,
                'bbox' => [72.0, $top, 540.0, $top + 12.0],
            ]],
        ];
    }

    return [
        'pnum' => 0,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'blocks' => [[
            'block_type' => 'Text',
            'bbox' => [72.0, 72.0, 540.0, 740.0],
            'lines' => $lines,
        ]],
    ];
};

return [
    'finalizes convert_single_pdf cleanup order for WordPress block handoff' => static function (TestRunner $t) use ($line): void {
        $pages = [];
        for ($page = 0; $page < 4; $page++) {
            $bodyLine = [
                'bbox' => [72.0, 110.0, 430.0, 122.0],
                'spans' => [
                    ['span_id' => "body_{$page}_0", 'text' => 'Body ', 'font' => 'Body', 'font_weight' => 400.0, 'font_size' => 12.0, 'bbox' => [72.0, 110.0, 104.0, 122.0]],
                    ['span_id' => "body_{$page}_1", 'text' => 'review text', 'font' => 'Body-Bold', 'font_weight' => 700.0, 'font_size' => 12.0, 'bbox' => [104.0, 110.0, 178.0, 122.0]],
                    ['span_id' => "body_{$page}_2", 'text' => ' remains importable.', 'font' => 'Body', 'font_weight' => 400.0, 'font_size' => 12.0, 'bbox' => [178.0, 110.0, 310.0, 122.0]],
                ],
            ];

            $pages[] = [
                'pnum' => $page,
                'bbox' => [0.0, 0.0, 612.0, 792.0],
                'images' => $page === 3 ? [['bytes' => 'png-bytes', 'alt' => 'chart crop']] : [],
                'blocks' => [
                    [
                        'block_type' => 'Page-header',
                        'bbox' => [72.0, 24.0, 240.0, 36.0],
                        'lines' => [$line('Confidential migration packet', "header_{$page}", [72.0, 24.0, 240.0, 36.0])],
                    ],
                    [
                        'block_type' => 'Section-header',
                        'bbox' => [72.0, 54.0, 260.0, 76.0],
                        'lines' => [$line(($page + 1) . ' Migration Packet', "title_{$page}", [72.0, 54.0, 260.0, 76.0], 'Heading-Bold', 700.0, 18.0)],
                    ],
                    [
                        'block_type' => 'Text',
                        'bbox' => [72.0, 110.0, 430.0, 122.0],
                        'lines' => [$bodyLine],
                    ],
                    [
                        'block_type' => 'List-item',
                        'bbox' => [90.0, 150.0, 260.0, 162.0],
                        'lines' => [$line('• Confirm imports', "bullet_{$page}", [90.0, 150.0, 260.0, 162.0])],
                    ],
                ],
            ];
        }

        $result = (new ConversionFinalizer())->finalizePages(
            $pages,
            ['header_0', 'header_1', 'header_2', 'header_3'],
            new MarkerSettings(['EXTRACT_IMAGES' => true])
        );

        $t->true(!str_contains($result['text'], 'Confidential migration packet'));
        $t->true(!str_contains($result['text'], 'Migration Packet'));
        $t->contains('Body **review text** remains importable.', $result['text']);
        $t->contains('- Confirm imports', $result['text']);
        $t->same(4, $result['metadata']['block_stats']['header_footer']);
        $t->same(4, count($result['metadata']['computed_toc']));
        $t->same('1 Migration Packet', $result['metadata']['computed_toc'][0]['title']);
        $t->same(['3_image_0.png'], array_keys($result['images']));
        $t->same('chart crop', $result['images']['3_image_0.png']['alt']);
    },
    'turns actual CI benchmark excerpts into final text that clears upstream score thresholds' => static function (TestRunner $t) use ($pageFromText): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-ci-benchmark-short.php';
        $runs = [];
        foreach ($fixture['benchmarkPairs'] as $index => $pair) {
            $result = (new ConversionFinalizer())->finalizePages(
                [$pageFromText($pair['markerExcerpt'], $pair['document'])],
                [],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
            $runs[] = [
                'method' => 'marker',
                'document' => $pair['document'],
                'hypothesis' => $result['text'],
                'reference' => $pair['referenceExcerpt'],
                'time' => (float) ($index + 1),
                'pages' => $index + 2,
                'chunkLength' => $pair['chunkLength'],
            ];
        }

        $report = (new BenchmarkReportBuilder())->build($runs);
        (new BenchmarkReportVerifier())->verifyMarkerScores($report);

        $t->true($report['marker']['files']['multicolcnn.pdf']['score'] > 0.34);
        $t->true($report['marker']['files']['switch_trans.pdf']['score'] > 0.40);
    },
];
