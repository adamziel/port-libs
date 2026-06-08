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
    'unwraps page_map pdftext and order envelopes before selected layout order assignment' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                'page_map' => [
                    'metadata' => ['raw_payload' => 'page-map pdftext envelope metadata must stay hidden'],
                    '10400' => $pdftextLinesPage(10400, [
                        ['text' => 'Page-map envelope cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    '10401' => $pdftextLinesPage(10401, [
                        ['text' => 'Second page-map envelope column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                        ['text' => 'First page-map envelope column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                    ]),
                ],
            ],
            [
                [
                    'page_map' => [
                        '10400' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'stale page-map order payload must stay hidden',
                        ],
                        '10401' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'selected page-map order row payload must stay hidden'],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'selected page-map order payload must stay hidden',
                        ],
                        'metadata' => ['raw_payload' => 'page-map order envelope metadata must stay hidden'],
                    ],
                    'raw_payload' => 'outer page-map order wrapper payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'page_map' => [
                        '10400' => ['image' => 'page-map-envelope-cover-order-render'],
                        '10401' => ['image' => 'page-map-envelope-selected-order-render'],
                        'metadata' => ['raw_payload' => 'page-map order image envelope metadata must stay hidden'],
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
        $t->same(10401, $result['pages'][0]['pnum']);
        $t->same(['First page-map envelope column', 'Second page-map envelope column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First page-map envelope column Second page-map envelope column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('page_map', $order));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Page-map envelope cover should stay skipped'));
        $t->true(!str_contains($encoded, 'page-map pdftext envelope metadata'));
        $t->true(!str_contains($encoded, 'stale page-map order payload'));
        $t->true(!str_contains($encoded, 'selected page-map order row payload'));
        $t->true(!str_contains($encoded, 'selected page-map order payload'));
        $t->true(!str_contains($encoded, 'page-map order envelope metadata'));
        $t->true(!str_contains($encoded, 'page-map order image envelope metadata'));
        $t->true(!str_contains($encoded, 'outer page-map order wrapper payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'unwraps page_map layout and order envelopes for WordPress pdftext imports' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-page-map-envelope-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% page_map envelope pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    'page_map' => [
                        'metadata' => ['raw_payload' => 'converter pdftext page-map envelope metadata must stay hidden'],
                        '10510' => $pdftextLinesPage(10510, [
                            ['text' => 'Converter page-map envelope cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                        ]),
                        '10511' => $pdftextLinesPage(10511, [
                            ['text' => 'Second converter page-map envelope body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                            ['text' => 'First converter page-map envelope heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                        ]),
                    ],
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [[
                        'page_map' => [
                            '10510' => ['image' => 'page-map-envelope-layout-cover-render'],
                            '10511' => ['image' => 'page-map-envelope-layout-selected-render'],
                            'metadata' => ['raw_payload' => 'layout image page-map metadata must stay hidden'],
                        ],
                    ]],
                    'layout_results' => [[
                        'page_map' => [
                            '10510' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                    ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => 'stale page-map layout row payload must stay hidden'],
                                ],
                                'raw_payload' => 'stale page-map layout payload must stay hidden',
                            ],
                            '10511' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'selected page-map layout title payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'selected page-map layout payload must stay hidden',
                            ],
                            'metadata' => ['raw_payload' => 'layout page-map metadata must stay hidden'],
                        ],
                    ]],
                    'order_images' => [[
                        'page_map' => [
                            '10510' => ['image' => 'page-map-envelope-order-cover-render'],
                            '10511' => ['image' => 'page-map-envelope-order-selected-render'],
                            'metadata' => ['raw_payload' => 'order image page-map metadata must stay hidden'],
                        ],
                    ]],
                    'order_results' => [[
                        'page_map' => [
                            '10510' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'stale page-map order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'stale page-map order payload must stay hidden',
                            ],
                            '10511' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'selected page-map order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'selected page-map order payload must stay hidden',
                            ],
                            'metadata' => ['raw_payload' => 'order page-map metadata must stay hidden'],
                        ],
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
        $t->contains('# First Converter Page-Map Envelope Heading.', $text);
        $t->contains('Second converter page-map envelope body.', $text);
        $t->true(strpos($text, '# First Converter Page-Map Envelope Heading.') < strpos($text, 'Second converter page-map envelope body.'));
        $t->true(!str_contains($text, 'Converter page-map envelope cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'converter pdftext page-map envelope metadata'));
        $t->true(!str_contains($encoded, 'layout image page-map metadata'));
        $t->true(!str_contains($encoded, 'stale page-map layout row payload'));
        $t->true(!str_contains($encoded, 'stale page-map layout payload'));
        $t->true(!str_contains($encoded, 'selected page-map layout title payload'));
        $t->true(!str_contains($encoded, 'selected page-map layout payload'));
        $t->true(!str_contains($encoded, 'layout page-map metadata'));
        $t->true(!str_contains($encoded, 'order image page-map metadata'));
        $t->true(!str_contains($encoded, 'stale page-map order row payload'));
        $t->true(!str_contains($encoded, 'stale page-map order payload'));
        $t->true(!str_contains($encoded, 'selected page-map order row payload'));
        $t->true(!str_contains($encoded, 'selected page-map order payload'));
        $t->true(!str_contains($encoded, 'order page-map metadata'));
    },
];
