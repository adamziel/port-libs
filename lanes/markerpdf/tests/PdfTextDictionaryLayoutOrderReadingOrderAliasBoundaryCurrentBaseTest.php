<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$pdftextReadingOrderAliasPage = static function (int $page, array $lines): array {
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

return [
    'uses supplied reading_order rows before source geometry order for selected pdftext pages' => static function (
        TestRunner $t
    ) use ($pdftextReadingOrderAliasPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextReadingOrderAliasPage(19000, [
                    ['text' => 'Reading-order alias cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextReadingOrderAliasPage(19001, [
                    ['text' => 'Second reading-order alias column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First reading-order alias column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [[
                'metadata' => ['page' => 19001],
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['reading_order' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'reading-order alias right row payload must stay hidden'],
                    ['reading_order' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'reading-order alias left row payload must stay hidden'],
                ],
                'raw_payload' => 'reading-order alias order payload must stay hidden',
            ]],
            orderImages: [
                ['metadata' => ['page' => 19001], 'image' => 'reading-order-alias-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(19001, $result['pages'][0]['pnum']);
        $t->same([
            'First reading-order alias column',
            'Second reading-order alias column',
        ], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First reading-order alias column Second reading-order alias column', $blocks[0]['text']);
        $t->same([
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
        $t->true(!str_contains($encoded, 'Reading-order alias cover should stay skipped'));
        $t->true(!str_contains($encoded, 'reading-order alias order payload'));
        $t->true(!str_contains($encoded, 'reading-order alias right row payload'));
        $t->true(!str_contains($encoded, 'reading-order alias left row payload'));
    },
    'uses readingOrder and order_position rows for WordPress supplied pdftext imports' => static function (
        TestRunner $t
    ) use ($pdftextReadingOrderAliasPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-reading-order-alias-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% reading-order alias pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextReadingOrderAliasPage(19100, [
                        ['text' => 'Reading-order alias converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextReadingOrderAliasPage(19101, [
                        ['text' => 'Second converter reading-order alias body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter reading-order alias heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['metadata' => ['page' => 19101], 'image' => 'reading-order-alias-layout-render'],
                    ],
                    'layout_results' => [[
                        'metadata' => ['page' => 19101],
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'reading-order alias layout title payload must stay hidden'],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'reading-order alias layout payload must stay hidden',
                    ]],
                    'order_images' => [
                        ['metadata' => ['page' => 19101], 'image' => 'reading-order-alias-order-render'],
                    ],
                    'order_results' => [[
                        'metadata' => ['page' => 19101],
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['readingOrder' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'reading-order alias converter right row payload must stay hidden'],
                            ['order_position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'reading-order alias converter left row payload must stay hidden'],
                        ],
                        'raw_payload' => 'reading-order alias converter order payload must stay hidden',
                    ]],
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
        $t->contains('# First Converter Reading-Order Alias Heading.', $text);
        $t->contains('Second converter reading-order alias body.', $text);
        $t->true(strpos($text, '# First Converter Reading-Order Alias Heading.') < strpos($text, 'Second converter reading-order alias body.'));
        $t->true(!str_contains($text, 'Reading-order alias converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'reading-order alias layout title payload'));
        $t->true(!str_contains($encoded, 'reading-order alias layout payload'));
        $t->true(!str_contains($encoded, 'reading-order alias converter order payload'));
        $t->true(!str_contains($encoded, 'reading-order alias converter right row payload'));
        $t->true(!str_contains($encoded, 'reading-order alias converter left row payload'));
    },
];
