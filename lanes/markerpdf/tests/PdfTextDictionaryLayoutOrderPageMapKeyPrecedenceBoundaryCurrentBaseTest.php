<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$pageMapKeyPrecedencePage = static function (int $page, array $lines): array {
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
    'prefers pdftext page-number pageMap keys over source-index keys before order assignment' => static function (
        TestRunner $t
    ) use ($pageMapKeyPrecedencePage, $jsonEnvelope): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pageMapKeyPrecedencePage(1, [
                    ['text' => 'PageMap precedence cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pageMapKeyPrecedencePage(2, [
                    ['text' => 'Second pageMap precedence column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First pageMap precedence column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'pageMap' => $jsonEnvelope([
                        '1' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'source-index pageMap order payload must stay hidden',
                        ],
                        '2' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'page-number pageMap order row payload must stay hidden'],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'page-number pageMap order payload must stay hidden',
                        ],
                    ]),
                    'raw_payload' => 'outer pageMap order payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'pageMap' => $jsonEnvelope([
                        '1' => ['image' => 'source-index-page-map-order-render'],
                        '2' => ['image' => 'page-number-page-map-order-render'],
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
        $t->same(2, $result['pages'][0]['pnum']);
        $t->same(['First pageMap precedence column', 'Second pageMap precedence column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First pageMap precedence column Second pageMap precedence column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('pageMap', $order));
        $t->true(!str_contains($encoded, 'PageMap precedence cover should stay skipped'));
        $t->true(!str_contains($encoded, 'source-index pageMap order payload'));
        $t->true(!str_contains($encoded, 'page-number pageMap order row payload'));
        $t->true(!str_contains($encoded, 'page-number pageMap order payload'));
        $t->true(!str_contains($encoded, 'outer pageMap order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'prefers pdftext page-number pageMap keys over source-index keys for WordPress imports' => static function (
        TestRunner $t
    ) use ($pageMapKeyPrecedencePage, $jsonEnvelope): void {
        $path = sys_get_temp_dir() . '/markerpdf-page-map-key-precedence-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% pageMap key precedence pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pageMapKeyPrecedencePage(1, [
                        ['text' => 'PageMap precedence converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pageMapKeyPrecedencePage(2, [
                        ['text' => 'Second converter pageMap precedence body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter pageMap precedence heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        'pageMap' => $jsonEnvelope([
                            '1' => ['image' => 'source-index-page-map-layout-render'],
                            '2' => ['image' => 'page-number-page-map-layout-render'],
                        ]),
                    ],
                    'layout_results' => [
                        'pageMap' => $jsonEnvelope([
                            '1' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'source-index pageMap layout row payload must stay hidden'],
                                    ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'source-index pageMap layout payload must stay hidden',
                            ],
                            '2' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'page-number pageMap layout title payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'page-number pageMap layout payload must stay hidden',
                            ],
                        ]),
                    ],
                    'order_images' => [
                        'pageMap' => $jsonEnvelope([
                            '1' => ['image' => 'source-index-page-map-order-render'],
                            '2' => ['image' => 'page-number-page-map-order-render'],
                        ]),
                    ],
                    'order_results' => [
                        'pageMap' => $jsonEnvelope([
                            '1' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'source-index pageMap order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'source-index pageMap order payload must stay hidden',
                            ],
                            '2' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'page-number pageMap order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'page-number pageMap order payload must stay hidden',
                            ],
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
        $t->contains('# First Converter Pagemap Precedence Heading.', $text);
        $t->contains('Second converter pageMap precedence body.', $text);
        $t->true(strpos($text, '# First Converter Pagemap Precedence Heading.') < strpos($text, 'Second converter pageMap precedence body.'));
        $t->true(!str_contains($text, 'PageMap precedence converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'source-index pageMap layout row payload'));
        $t->true(!str_contains($encoded, 'source-index pageMap layout payload'));
        $t->true(!str_contains($encoded, 'page-number pageMap layout title payload'));
        $t->true(!str_contains($encoded, 'page-number pageMap layout payload'));
        $t->true(!str_contains($encoded, 'source-index pageMap order row payload'));
        $t->true(!str_contains($encoded, 'source-index pageMap order payload'));
        $t->true(!str_contains($encoded, 'page-number pageMap order row payload'));
        $t->true(!str_contains($encoded, 'page-number pageMap order payload'));
    },
];
