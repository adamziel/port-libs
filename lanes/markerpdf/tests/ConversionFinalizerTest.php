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
    'detects and indents code blocks during convert_single_pdf finalization' => static function (TestRunner $t) use ($line): void {
        $bodyLines = [];
        for ($index = 0; $index < 8; $index++) {
            $top = 72.0 + ($index * 16.0);
            $bodyLines[] = $line(
                'Paragraph line ' . ($index + 1) . ' remains ordinary imported prose.',
                'body_' . $index,
                [72.0, $top, 430.0, $top + 12.0],
                'Body',
                400.0,
                12.0
            );
        }

        $codeLines = [];
        foreach ([
            ['// source: imported benchmark sample', 72.0],
            ['// target: WordPress code block', 72.0],
            ['// cleaner: marker.cleaners.code', 72.0],
            ['function migrate_pdf() {', 72.0],
            ['return true;', 86.0],
            ['}', 72.0],
            ['// done', 72.0],
        ] as $index => [$text, $left]) {
            $top = 230.0 + ($index * 9.0);
            $codeLines[] = $line(
                $text,
                'code_' . $index,
                [$left, $top, $left + (strlen($text) * 7.0), $top + 7.0],
                'Mono',
                400.0,
                8.0
            );
        }

        $result = (new ConversionFinalizer())->finalizePages(
            [[
                'pnum' => 0,
                'bbox' => [0.0, 0.0, 612.0, 792.0],
                'blocks' => [
                    [
                        'block_type' => 'Text',
                        'bbox' => [72.0, 72.0, 430.0, 196.0],
                        'lines' => $bodyLines,
                    ],
                    [
                        'block_type' => 'Text',
                        'bbox' => [72.0, 230.0, 320.0, 296.0],
                        'lines' => $codeLines,
                    ],
                ],
            ]],
            [],
            new MarkerSettings(['EXTRACT_IMAGES' => false])
        );

        $t->same(1, $result['metadata']['block_stats']['code']);
        $t->same('Code', $result['pages'][0]['blocks'][1]['block_type']);
        $t->same('0_fix_code', $result['pages'][0]['blocks'][1]['lines'][0]['spans'][0]['span_id']);
        $t->contains("```\n// source: imported benchmark sample", $result['text']);
        $t->contains("  return true;\n}", $result['text']);
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
