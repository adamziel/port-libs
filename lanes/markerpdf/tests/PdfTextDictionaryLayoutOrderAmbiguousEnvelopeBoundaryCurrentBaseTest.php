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
    'rejects ambiguous unmarked dictionary-output order payload envelopes before pdftext layout assignment' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(6100, [
                    ['text' => 'Second ambiguous direct-envelope column remains source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First ambiguous direct-envelope column has no trusted order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'dictionary_output' => [
                        [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'ambiguous first direct order row payload must stay hidden'],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'ambiguous first direct order payload must stay hidden',
                        ],
                        [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'ambiguous second direct order row payload must stay hidden'],
                            ],
                            'raw_payload' => 'ambiguous second direct order payload must stay hidden',
                        ],
                    ],
                    'raw_payload' => 'ambiguous direct order envelope payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'dictionary_output' => [
                        ['image' => 'ambiguous-direct-order-render-a'],
                        ['image' => 'ambiguous-direct-order-render-b'],
                    ],
                ],
            ],
            maxPages: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([0], $result['page_range']);
        $t->same(6100, $result['pages'][0]['pnum']);
        $t->same([
            'Second ambiguous direct-envelope column remains source ordered',
            'First ambiguous direct-envelope column has no trusted order',
        ], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second ambiguous direct-envelope column remains source ordered First ambiguous direct-envelope column has no trusted order', $blocks[0]['text']);
        $t->same(null, $result['pages'][0]['order'] ?? null);
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
        $t->true(!str_contains($encoded, 'ambiguous first direct order payload'));
        $t->true(!str_contains($encoded, 'ambiguous first direct order row payload'));
        $t->true(!str_contains($encoded, 'ambiguous second direct order payload'));
        $t->true(!str_contains($encoded, 'ambiguous second direct order row payload'));
        $t->true(!str_contains($encoded, 'ambiguous direct order envelope payload'));
    },
    'rejects ambiguous unmarked layout and order envelopes before WordPress supplied imports' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-ambiguous-direct-envelope-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% ambiguous direct envelope pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(6110, [
                        ['text' => 'Second converter ambiguous envelope body stays source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter ambiguous envelope has no supplied layout.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'lowres_images' => [[
                        'pages' => [
                            ['image' => 'ambiguous-layout-render-a'],
                            ['image' => 'ambiguous-layout-render-b'],
                        ],
                    ]],
                    'layout_results' => [[
                        'pages' => [
                            [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'ambiguous first layout title payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'ambiguous first layout payload must stay hidden',
                            ],
                            [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                    ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => 'ambiguous second layout picture payload must stay hidden'],
                                ],
                                'raw_payload' => 'ambiguous second layout payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'ambiguous layout envelope payload must stay hidden',
                    ]],
                    'order_images' => [[
                        'dictionary_output' => [
                            ['image' => 'ambiguous-order-render-a'],
                            ['image' => 'ambiguous-order-render-b'],
                        ],
                    ]],
                    'order_results' => [[
                        'dictionary_output' => [
                            [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'ambiguous first order row payload must stay hidden'],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ],
                                'raw_payload' => 'ambiguous first order payload must stay hidden',
                            ],
                            [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'ambiguous second order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'ambiguous second order payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'ambiguous order envelope payload must stay hidden',
                    ]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([0], $result['metadata']['page_range'] ?? null);
        $t->same([], $result['metadata']['supplied_boundaries'] ?? null);
        $t->true(!array_key_exists('layout_plan', $result['metadata']));
        $t->true(!array_key_exists('order_plan', $result['metadata']));
        $t->contains('Second converter ambiguous envelope body stays source ordered.', $text);
        $t->contains('First converter ambiguous envelope has no supplied layout.', $text);
        $t->true(strpos($text, 'Second converter ambiguous envelope body stays source ordered.') < strpos($text, 'First converter ambiguous envelope has no supplied layout.'));
        $t->true(!str_contains($text, '# First Converter Ambiguous Envelope Has No Supplied Layout.'));
        $t->true(!str_contains($encoded, 'ambiguous first layout title payload'));
        $t->true(!str_contains($encoded, 'ambiguous first layout payload'));
        $t->true(!str_contains($encoded, 'ambiguous second layout picture payload'));
        $t->true(!str_contains($encoded, 'ambiguous second layout payload'));
        $t->true(!str_contains($encoded, 'ambiguous layout envelope payload'));
        $t->true(!str_contains($encoded, 'ambiguous first order row payload'));
        $t->true(!str_contains($encoded, 'ambiguous first order payload'));
        $t->true(!str_contains($encoded, 'ambiguous second order row payload'));
        $t->true(!str_contains($encoded, 'ambiguous second order payload'));
        $t->true(!str_contains($encoded, 'ambiguous order envelope payload'));
    },
];
