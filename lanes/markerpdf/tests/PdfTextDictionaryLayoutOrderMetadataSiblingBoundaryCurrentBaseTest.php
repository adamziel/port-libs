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
    'ignores adapter metadata siblings in keyed pdftext and order maps before selected layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                'dictionary_output' => [
                    'metadata' => [
                        'adapter' => 'cached pdftext.dictionary_output',
                        'raw_payload' => 'pdftext metadata sibling must stay out of selected pages',
                    ],
                    '8300' => $pdftextLinesPage(8300, [
                        ['text' => 'Metadata sibling page-map cover must stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    '8301' => $pdftextLinesPage(8301, [
                        ['text' => 'Second metadata sibling order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                        ['text' => 'First metadata sibling order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                    ]),
                    '8302' => $pdftextLinesPage(8302, [
                        ['text' => 'Metadata sibling page-map appendix must stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                ],
            ],
            [
                [
                    'dictionary_output' => [
                        'metadata' => [
                            'adapter' => 'cached order result map',
                            'raw_payload' => 'order metadata sibling must stay hidden',
                        ],
                        '8301' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'metadata sibling selected order row payload must stay hidden'],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'metadata sibling selected order payload must stay hidden',
                        ],
                        '8300' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'metadata sibling stale order payload must stay hidden',
                        ],
                    ],
                    'raw_payload' => 'outer metadata sibling order wrapper payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'dictionary_output' => [
                        'metadata' => ['raw_payload' => 'order image metadata sibling must stay hidden'],
                        '8301' => ['image' => 'metadata-sibling-selected-order-render'],
                        '8300' => ['image' => 'metadata-sibling-stale-order-render'],
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
        $t->same(8301, $result['pages'][0]['pnum']);
        $t->same(['First metadata sibling order column', 'Second metadata sibling order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First metadata sibling order column Second metadata sibling order column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->same(3, $result['metadata']['source_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->true(!str_contains($encoded, 'pdftext metadata sibling'));
        $t->true(!str_contains($encoded, 'Metadata sibling page-map cover'));
        $t->true(!str_contains($encoded, 'Metadata sibling page-map appendix'));
        $t->true(!str_contains($encoded, 'order metadata sibling'));
        $t->true(!str_contains($encoded, 'metadata sibling selected order row payload'));
        $t->true(!str_contains($encoded, 'metadata sibling selected order payload'));
        $t->true(!str_contains($encoded, 'metadata sibling stale order payload'));
        $t->true(!str_contains($encoded, 'order image metadata sibling'));
        $t->true(!str_contains($encoded, 'outer metadata sibling order wrapper payload'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
    },
    'ignores adapter metadata siblings in keyed layout and order maps for WordPress imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-pdftext-metadata-sibling-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% metadata sibling pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    'dictionary_output' => [
                        'metadata' => ['raw_payload' => 'converter pdftext metadata sibling must stay hidden'],
                        '8310' => $pdftextLinesPage(8310, [
                            ['text' => 'Metadata sibling converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                        ]),
                        '8311' => $pdftextLinesPage(8311, [
                            ['text' => 'Second converter metadata sibling body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                            ['text' => 'First converter metadata sibling heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                        ]),
                        '8312' => $pdftextLinesPage(8312, [
                            ['text' => 'Metadata sibling converter appendix should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                        ]),
                    ],
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [[
                        'dictionary_output' => [
                            'metadata' => ['raw_payload' => 'layout image metadata sibling must stay hidden'],
                            '8311' => ['image' => 'metadata-sibling-layout-render'],
                            '8310' => ['image' => 'metadata-sibling-stale-layout-render'],
                        ],
                    ]],
                    'layout_results' => [[
                        'dictionary_output' => [
                            'metadata' => ['raw_payload' => 'layout metadata sibling must stay hidden'],
                            '8311' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'metadata sibling selected layout title payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'metadata sibling selected layout payload must stay hidden',
                            ],
                            '8310' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                    ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'metadata sibling stale layout payload must stay hidden',
                            ],
                        ],
                    ]],
                    'order_images' => [[
                        'dictionary_output' => [
                            'metadata' => ['raw_payload' => 'order image metadata sibling must stay hidden'],
                            '8311' => ['image' => 'metadata-sibling-order-render'],
                            '8310' => ['image' => 'metadata-sibling-stale-order-render'],
                        ],
                    ]],
                    'order_results' => [[
                        'dictionary_output' => [
                            'metadata' => ['raw_payload' => 'order metadata sibling must stay hidden'],
                            '8311' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'metadata sibling selected order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'metadata sibling selected order payload must stay hidden',
                            ],
                            '8310' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ],
                                'raw_payload' => 'metadata sibling stale order payload must stay hidden',
                            ],
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
        $t->same(3, $result['metadata']['pdftext']['source_pages'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('# First Converter Metadata Sibling Heading.', $text);
        $t->contains('Second converter metadata sibling body.', $text);
        $t->true(strpos($text, '# First Converter Metadata Sibling Heading.') < strpos($text, 'Second converter metadata sibling body.'));
        $t->true(!str_contains($text, 'Metadata sibling converter cover should stay skipped.'));
        $t->true(!str_contains($text, 'Metadata sibling converter appendix should stay skipped.'));
        $t->true(!str_contains($encoded, 'converter pdftext metadata sibling'));
        $t->true(!str_contains($encoded, 'layout image metadata sibling'));
        $t->true(!str_contains($encoded, 'layout metadata sibling'));
        $t->true(!str_contains($encoded, 'metadata sibling selected layout title payload'));
        $t->true(!str_contains($encoded, 'metadata sibling selected layout payload'));
        $t->true(!str_contains($encoded, 'metadata sibling stale layout payload'));
        $t->true(!str_contains($encoded, 'order image metadata sibling'));
        $t->true(!str_contains($encoded, 'order metadata sibling'));
        $t->true(!str_contains($encoded, 'metadata sibling selected order row payload'));
        $t->true(!str_contains($encoded, 'metadata sibling selected order payload'));
        $t->true(!str_contains($encoded, 'metadata sibling stale order payload'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
    },
];
