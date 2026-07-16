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

return [
    'orders decimal-string keyed pdftext and order maps before selected layout assignment' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                'dictionary_output' => [
                    '+9601.0' => $pdftextLinesPage(9601, [
                        ['text' => 'Second decimal-key selected column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                        ['text' => 'First decimal-key selected column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                    ]),
                    '+9600.0' => $pdftextLinesPage(9600, [
                        ['text' => 'Decimal-key cover must not become selected', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                ],
            ],
            [
                [
                    'dictionary_output' => [
                        '+9601.0' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'decimal-key selected order row payload must stay hidden'],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'decimal-key selected order payload must stay hidden',
                        ],
                        '+9600.0' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'decimal-key stale order payload must stay hidden',
                        ],
                    ],
                    'raw_payload' => 'decimal-key order wrapper payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'dictionary_output' => [
                        '+9601.0' => ['image' => 'decimal-key-selected-order-render'],
                        '+9600.0' => ['image' => 'decimal-key-stale-order-render'],
                    ],
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(9601, $result['pages'][0]['pnum']);
        $t->same(['First decimal-key selected column', 'Second decimal-key selected column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First decimal-key selected column Second decimal-key selected column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Decimal-key cover must not become selected'));
        $t->true(!str_contains($encoded, 'decimal-key selected order row payload'));
        $t->true(!str_contains($encoded, 'decimal-key selected order payload'));
        $t->true(!str_contains($encoded, 'decimal-key stale order payload'));
        $t->true(!str_contains($encoded, 'decimal-key order wrapper payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses decimal-string keyed layout and order maps before WordPress supplied imports' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-decimal-key-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% decimal-key pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    'dictionary_output' => [
                        '+9611.0' => $pdftextLinesPage(9611, [
                            ['text' => 'Second converter decimal-key body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                            ['text' => 'First converter decimal-key heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                        ]),
                        '+9610.0' => $pdftextLinesPage(9610, [
                            ['text' => 'Decimal-key converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                        ]),
                    ],
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [[
                        'dictionary_output' => [
                            '+9611.0' => ['image' => 'decimal-key-layout-render'],
                            '+9610.0' => ['image' => 'decimal-key-stale-layout-render'],
                        ],
                    ]],
                    'layout_results' => [[
                        'dictionary_output' => [
                            '+9611.0' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'decimal-key selected layout title payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'decimal-key selected layout payload must stay hidden',
                            ],
                            '+9610.0' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                    ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'decimal-key stale layout payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'decimal-key layout wrapper payload must stay hidden',
                    ]],
                    'order_images' => [[
                        'dictionary_output' => [
                            '+9611.0' => ['image' => 'decimal-key-order-render'],
                            '+9610.0' => ['image' => 'decimal-key-stale-order-render'],
                        ],
                    ]],
                    'order_results' => [[
                        'dictionary_output' => [
                            '+9611.0' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'decimal-key selected order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'decimal-key selected order payload must stay hidden',
                            ],
                            '+9610.0' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ],
                                'raw_payload' => 'decimal-key stale order payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'decimal-key order wrapper payload must stay hidden',
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
        $t->contains('# First Converter Decimal-Key Heading.', $text);
        $t->contains('Second converter decimal-key body.', $text);
        $t->true(strpos($text, '# First Converter Decimal-Key Heading.') < strpos($text, 'Second converter decimal-key body.'));
        $t->true(!str_contains($text, 'Decimal-key converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'decimal-key selected layout title payload'));
        $t->true(!str_contains($encoded, 'decimal-key selected layout payload'));
        $t->true(!str_contains($encoded, 'decimal-key stale layout payload'));
        $t->true(!str_contains($encoded, 'decimal-key layout wrapper payload'));
        $t->true(!str_contains($encoded, 'decimal-key selected order row payload'));
        $t->true(!str_contains($encoded, 'decimal-key selected order payload'));
        $t->true(!str_contains($encoded, 'decimal-key stale order payload'));
        $t->true(!str_contains($encoded, 'decimal-key order wrapper payload'));
    },
];
