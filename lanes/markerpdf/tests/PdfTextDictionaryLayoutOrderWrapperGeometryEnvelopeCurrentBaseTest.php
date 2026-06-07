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
    'unwraps source-keyed order maps from pdftext geometry envelopes before selected layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(7200, [
                    ['text' => 'Geometry-envelope cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(7201, [
                    ['text' => 'Second geometry-envelope column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First geometry-envelope column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 7201,
                    'bbox' => [0.0, 0.0, 612.0, 792.0],
                    'blocks' => [[
                        'lines' => [[
                            'spans' => [[
                                'text' => 'Wrapper pdftext blocks must stay hidden',
                                'bbox' => [72.0, 160.0, 520.0, 174.0],
                            ]],
                        ]],
                    ]],
                    'dictionary_output' => [
                        '7200' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'stale wrapper geometry order payload must stay hidden',
                        ],
                        '7201' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'selected wrapper geometry order row payload must stay hidden'],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'selected wrapper geometry order payload must stay hidden',
                        ],
                    ],
                    'raw_payload' => 'outer pdftext geometry order envelope must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'page' => 7201,
                    'bbox' => [0.0, 0.0, 612.0, 792.0],
                    'dictionary_output' => [
                        '7200' => ['image' => 'geometry-envelope-cover-order-render'],
                        '7201' => ['image' => 'geometry-envelope-selected-order-render'],
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
        $t->same(7201, $result['pages'][0]['pnum']);
        $t->same(['First geometry-envelope column', 'Second geometry-envelope column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First geometry-envelope column Second geometry-envelope column', $blocks[0]['text']);
        $t->same([0.0, 0.0, 612.0, 792.0], $order['image_bbox'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('dictionary_output', $order));
        $t->true(!array_key_exists('blocks', $order));
        $t->true(!array_key_exists('bbox', $order));
        $t->true(!str_contains($encoded, 'Geometry-envelope cover should stay skipped'));
        $t->true(!str_contains($encoded, 'Wrapper pdftext blocks must stay hidden'));
        $t->true(!str_contains($encoded, 'stale wrapper geometry order payload'));
        $t->true(!str_contains($encoded, 'selected wrapper geometry order row payload'));
        $t->true(!str_contains($encoded, 'selected wrapper geometry order payload'));
        $t->true(!str_contains($encoded, 'outer pdftext geometry order envelope'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'unwraps pdftext geometry layout and order envelopes for WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-wrapper-geometry-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% wrapper geometry pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(7210, [
                        ['text' => 'Geometry-envelope converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(7211, [
                        ['text' => 'Second converter geometry body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter geometry heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        [
                            'page' => 7211,
                            'bbox' => [0.0, 0.0, 612.0, 792.0],
                            'dictionary_output' => [
                                '7210' => ['image' => 'geometry-layout-cover-render'],
                                '7211' => ['image' => 'geometry-layout-selected-render'],
                            ],
                        ],
                    ],
                    'layout_results' => [[
                        'page' => 7211,
                        'bbox' => [0.0, 0.0, 612.0, 792.0],
                        'blocks' => [[
                            'lines' => [[
                                'spans' => [[
                                    'text' => 'Wrapper layout pdftext text must stay hidden',
                                    'bbox' => [72.0, 170.0, 520.0, 184.0],
                                ]],
                            ]],
                        ]],
                        'pages' => [
                            '7210' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                    ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => 'stale wrapper geometry layout row payload must stay hidden'],
                                ],
                                'raw_payload' => 'stale wrapper geometry layout payload must stay hidden',
                            ],
                            '7211' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'selected wrapper geometry title payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'selected wrapper geometry layout payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'outer wrapper geometry layout envelope must stay hidden',
                    ]],
                    'order_images' => [
                        [
                            'page' => 7211,
                            'bbox' => [0.0, 0.0, 612.0, 792.0],
                            'dictionary_output' => [
                                '7210' => ['image' => 'geometry-order-cover-render'],
                                '7211' => ['image' => 'geometry-order-selected-render'],
                            ],
                        ],
                    ],
                    'order_results' => [[
                        'page' => 7211,
                        'bbox' => [0.0, 0.0, 612.0, 792.0],
                        'dictionary_output' => [
                            '7210' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'stale wrapper geometry order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'stale wrapper geometry order payload must stay hidden',
                            ],
                            '7211' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'selected wrapper geometry order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'selected wrapper geometry order payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'outer wrapper geometry order envelope must stay hidden',
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
        $t->same([
            [60.0, 92.0, 290.0, 150.0],
            [318.0, 92.0, 570.0, 150.0],
        ], $result['metadata']['order_plan']['requested_bboxes'][0] ?? null);
        $t->contains('# First Converter Geometry Heading.', $text);
        $t->contains('Second converter geometry body.', $text);
        $t->true(strpos($text, '# First Converter Geometry Heading.') < strpos($text, 'Second converter geometry body.'));
        $t->true(!str_contains($text, 'Geometry-envelope converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'Wrapper layout pdftext text'));
        $t->true(!str_contains($encoded, 'stale wrapper geometry layout row payload'));
        $t->true(!str_contains($encoded, 'stale wrapper geometry layout payload'));
        $t->true(!str_contains($encoded, 'selected wrapper geometry title payload'));
        $t->true(!str_contains($encoded, 'selected wrapper geometry layout payload'));
        $t->true(!str_contains($encoded, 'outer wrapper geometry layout envelope'));
        $t->true(!str_contains($encoded, 'stale wrapper geometry order row payload'));
        $t->true(!str_contains($encoded, 'stale wrapper geometry order payload'));
        $t->true(!str_contains($encoded, 'selected wrapper geometry order row payload'));
        $t->true(!str_contains($encoded, 'selected wrapper geometry order payload'));
        $t->true(!str_contains($encoded, 'outer wrapper geometry order envelope'));
    },
];
