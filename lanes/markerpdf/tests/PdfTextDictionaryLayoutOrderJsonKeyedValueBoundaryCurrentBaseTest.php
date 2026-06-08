<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$pdftextJsonKeyedValuePage = static function (int $page, array $lines): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'blocks' => [[
            'lines' => array_map(
                static fn (array $line): array => [
                    'bbox' => $line['bbox'],
                    'spans' => [[
                        'text' => $line['text'],
                        'bbox' => $line['bbox'],
                        'font' => ['name' => 'Times-Roman', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$jsonArtifactValue = static fn (array $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

return [
    'preserves source keys while decoding raw JSON keyed order values before selected pdftext assignment' => static function (
        TestRunner $t
    ) use ($pdftextJsonKeyedValuePage, $jsonArtifactValue): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextJsonKeyedValuePage(12600, [
                    ['text' => 'JSON keyed-value cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextJsonKeyedValuePage(12601, [
                    ['text' => 'Second JSON keyed-value order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First JSON keyed-value order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                '12601' => $jsonArtifactValue([
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'json keyed-value selected order row payload must stay hidden'],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'json keyed-value selected order payload must stay hidden',
                ]),
                '12600' => $jsonArtifactValue([
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'json keyed-value stale order payload must stay hidden',
                ]),
            ],
            orderImages: [
                '12601' => $jsonArtifactValue(['image' => 'json-keyed-value-selected-order-render']),
                '12600' => $jsonArtifactValue(['image' => 'json-keyed-value-stale-order-render']),
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(12601, $result['pages'][0]['pnum']);
        $t->same(['First JSON keyed-value order column', 'Second JSON keyed-value order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First JSON keyed-value order column Second JSON keyed-value order column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('dictionary_output', $order));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'JSON keyed-value cover should stay skipped'));
        $t->true(!str_contains($encoded, 'json keyed-value selected order row payload'));
        $t->true(!str_contains($encoded, 'json keyed-value selected order payload'));
        $t->true(!str_contains($encoded, 'json keyed-value stale order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'preserves source keys while decoding raw JSON keyed layout and order values for WordPress imports' => static function (
        TestRunner $t
    ) use ($pdftextJsonKeyedValuePage, $jsonArtifactValue): void {
        $path = sys_get_temp_dir() . '/markerpdf-json-keyed-value-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% JSON keyed-value pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextJsonKeyedValuePage(12700, [
                        ['text' => 'JSON keyed-value converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextJsonKeyedValuePage(12701, [
                        ['text' => 'Second converter JSON keyed-value body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter JSON keyed-value heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        '12701' => $jsonArtifactValue(['image' => 'json-keyed-value-selected-layout-render']),
                        '12700' => $jsonArtifactValue(['image' => 'json-keyed-value-stale-layout-render']),
                    ],
                    'layout_results' => [
                        '12701' => $jsonArtifactValue([
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'json keyed-value selected layout title payload must stay hidden'],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'json keyed-value selected layout payload must stay hidden',
                        ]),
                        '12700' => $jsonArtifactValue([
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'json keyed-value stale layout payload must stay hidden',
                        ]),
                    ],
                    'order_images' => [
                        '12701' => $jsonArtifactValue(['image' => 'json-keyed-value-selected-order-render']),
                        '12700' => $jsonArtifactValue(['image' => 'json-keyed-value-stale-order-render']),
                    ],
                    'order_results' => [
                        '12701' => $jsonArtifactValue([
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'json keyed-value selected order row payload must stay hidden'],
                            ],
                            'raw_payload' => 'json keyed-value selected order payload must stay hidden',
                        ]),
                        '12700' => $jsonArtifactValue([
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'json keyed-value stale order payload must stay hidden',
                        ]),
                    ],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('# First Converter Json Keyed-Value Heading.', $text);
        $t->contains('Second converter JSON keyed-value body.', $text);
        $t->true(strpos($text, '# First Converter Json Keyed-Value Heading.') < strpos($text, 'Second converter JSON keyed-value body.'));
        $t->true(!str_contains($text, 'JSON keyed-value converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'json keyed-value selected layout title payload'));
        $t->true(!str_contains($encoded, 'json keyed-value selected layout payload'));
        $t->true(!str_contains($encoded, 'json keyed-value stale layout payload'));
        $t->true(!str_contains($encoded, 'json keyed-value selected order row payload'));
        $t->true(!str_contains($encoded, 'json keyed-value selected order payload'));
        $t->true(!str_contains($encoded, 'json keyed-value stale order payload'));
    },
];
