<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$pdftextLinesPage = static function (int $page, array $lines): array {
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

$jsonEnvelope = static fn (array $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

return [
    'selects source-keyed typed JSON order_result envelopes before pdftext layout assignment' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage, $jsonEnvelope): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(9800, [
                    ['text' => 'Typed JSON order cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(9801, [
                    ['text' => 'Second typed JSON order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First typed JSON order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['page' => 9801],
                    'order_result' => [
                        'dictionary_output' => $jsonEnvelope([
                            '9800' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ],
                                'raw_payload' => 'stale typed JSON order payload must stay hidden',
                            ],
                            '9801' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'selected typed JSON order row payload must stay hidden'],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ],
                                'raw_payload' => 'selected typed JSON order payload must stay hidden',
                            ],
                        ]),
                    ],
                    'raw_payload' => 'outer typed JSON order wrapper payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'dictionary_output' => $jsonEnvelope([
                        '9800' => ['image' => 'typed-json-cover-order-render'],
                        '9801' => ['image' => 'typed-json-selected-order-render'],
                    ]),
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(9801, $result['pages'][0]['pnum']);
        $t->same(['First typed JSON order column', 'Second typed JSON order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First typed JSON order column Second typed JSON order column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('order_result', $order));
        $t->true(!array_key_exists('dictionary_output', $order));
        $t->true(!str_contains($encoded, 'Typed JSON order cover should stay skipped'));
        $t->true(!str_contains($encoded, 'stale typed JSON order payload'));
        $t->true(!str_contains($encoded, 'selected typed JSON order row payload'));
        $t->true(!str_contains($encoded, 'selected typed JSON order payload'));
        $t->true(!str_contains($encoded, 'outer typed JSON order wrapper payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'selects source-keyed typed JSON layout_result and order_result envelopes for WordPress imports' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage, $jsonEnvelope): void {
        $path = sys_get_temp_dir() . '/markerpdf-typed-json-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% typed JSON pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(9810, [
                        ['text' => 'Typed JSON converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(9811, [
                        ['text' => 'Second converter typed JSON body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter typed JSON heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [[
                        'dictionary_output' => $jsonEnvelope([
                            '9810' => ['image' => 'typed-json-layout-cover-render'],
                            '9811' => ['image' => 'typed-json-layout-selected-render'],
                        ]),
                    ]],
                    'layout_results' => [[
                        'metadata' => ['page' => 9811],
                        'layout_result' => [
                            'dictionary_output' => $jsonEnvelope([
                                '9810' => [
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                        ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => 'stale typed JSON layout row payload must stay hidden'],
                                    ],
                                    'raw_payload' => 'stale typed JSON layout payload must stay hidden',
                                ],
                                '9811' => [
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'selected typed JSON layout title payload must stay hidden'],
                                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                    ],
                                    'raw_payload' => 'selected typed JSON layout payload must stay hidden',
                                ],
                            ]),
                        ],
                        'raw_payload' => 'outer typed JSON layout wrapper payload must stay hidden',
                    ]],
                    'order_images' => [[
                        'dictionary_output' => $jsonEnvelope([
                            '9810' => ['image' => 'typed-json-order-cover-render'],
                            '9811' => ['image' => 'typed-json-order-selected-render'],
                        ]),
                    ]],
                    'order_results' => [[
                        'metadata' => ['page' => 9811],
                        'order_result' => [
                            'dictionary_output' => $jsonEnvelope([
                                '9810' => [
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'stale typed JSON order row payload must stay hidden'],
                                    ],
                                    'raw_payload' => 'stale typed JSON order payload must stay hidden',
                                ],
                                '9811' => [
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'selected typed JSON order row payload must stay hidden'],
                                    ],
                                    'raw_payload' => 'selected typed JSON order payload must stay hidden',
                                ],
                            ]),
                        ],
                        'raw_payload' => 'outer typed JSON order wrapper payload must stay hidden',
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
        $t->contains('# First Converter Typed Json Heading.', $text);
        $t->contains('Second converter typed JSON body.', $text);
        $t->true(strpos($text, '# First Converter Typed Json Heading.') < strpos($text, 'Second converter typed JSON body.'));
        $t->true(!str_contains($text, 'Typed JSON converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'stale typed JSON layout row payload'));
        $t->true(!str_contains($encoded, 'stale typed JSON layout payload'));
        $t->true(!str_contains($encoded, 'selected typed JSON layout title payload'));
        $t->true(!str_contains($encoded, 'selected typed JSON layout payload'));
        $t->true(!str_contains($encoded, 'outer typed JSON layout wrapper payload'));
        $t->true(!str_contains($encoded, 'stale typed JSON order row payload'));
        $t->true(!str_contains($encoded, 'stale typed JSON order payload'));
        $t->true(!str_contains($encoded, 'selected typed JSON order row payload'));
        $t->true(!str_contains($encoded, 'selected typed JSON order payload'));
        $t->true(!str_contains($encoded, 'outer typed JSON order wrapper payload'));
    },
];
