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
    'uses page_id artifacts before selected pdftext dictionary layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(8300, [
                    ['text' => 'Page-id cover must stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(8301, [
                    ['text' => 'Second page-id sparse column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First page-id sparse column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(8302, [
                    ['text' => 'Page-id appendix must stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['page_id' => 8300],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'stale page-id order payload must stay hidden',
                ],
                [
                    'metadata' => ['page_id' => 8301],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['page_id' => 8300, 'position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'stale row-level page-id order payload must stay hidden'],
                        ['page_id' => 8301, 'position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'selected row-level page-id order payload must stay hidden'],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'selected page-id order payload must stay hidden',
                ],
            ],
            orderImages: [
                ['metadata' => ['page_id' => 8300], 'image' => 'page-id-cover-order-render'],
                ['metadata' => ['page_id' => 8301], 'image' => 'page-id-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(8301, $result['pages'][0]['pnum']);
        $t->same(['First page-id sparse column', 'Second page-id sparse column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First page-id sparse column Second page-id sparse column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $result['pages'][0]['order']['bboxes'] ?? []);
        $t->true(!str_contains($encoded, 'Page-id cover must stay skipped'));
        $t->true(!str_contains($encoded, 'Page-id appendix must stay skipped'));
        $t->true(!str_contains($encoded, 'stale page-id order payload'));
        $t->true(!str_contains($encoded, 'stale row-level page-id order payload'));
        $t->true(!str_contains($encoded, 'selected row-level page-id order payload'));
        $t->true(!str_contains($encoded, 'selected page-id order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses page_id layout and order artifacts before WordPress pdftext imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-page-id-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% page-id pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(8310, [
                        ['text' => 'Page-id converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(8311, [
                        ['text' => 'Second page-id converter body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First page-id converter heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                    $pdftextLinesPage(8312, [
                        ['text' => 'Page-id converter appendix should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['metadata' => ['page_id' => 8310], 'image' => 'page-id-cover-layout-render'],
                        ['metadata' => ['page_id' => 8311], 'image' => 'page-id-selected-layout-render'],
                    ],
                    'layout_results' => [
                        [
                            'metadata' => ['page_id' => 8310],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'stale page-id layout payload must stay hidden',
                        ],
                        [
                            'metadata' => ['page_id' => 8311],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['page_id' => 8310, 'label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => 'stale row-level page-id layout payload must stay hidden'],
                                ['page_id' => 8311, 'label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'selected row-level page-id layout payload must stay hidden'],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'selected page-id layout payload must stay hidden',
                        ],
                    ],
                    'order_images' => [
                        ['metadata' => ['page_id' => 8310], 'image' => 'page-id-cover-order-render'],
                        ['metadata' => ['page_id' => 8311], 'image' => 'page-id-selected-order-render'],
                    ],
                    'order_results' => [
                        [
                            'metadata' => ['page_id' => 8310],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'stale page-id order payload must stay hidden',
                        ],
                        [
                            'metadata' => ['page_id' => 8311],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['page_id' => 8310, 'position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'stale row-level page-id order payload must stay hidden'],
                                ['page_id' => 8311, 'position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'selected row-level page-id order payload must stay hidden'],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'selected page-id order payload must stay hidden',
                        ],
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
        $t->contains('# First Page-Id Converter Heading.', $text);
        $t->contains('Second page-id converter body.', $text);
        $t->true(strpos($text, '# First Page-Id Converter Heading.') < strpos($text, 'Second page-id converter body.'));
        $t->true(!str_contains($text, 'Page-id converter cover should stay skipped.'));
        $t->true(!str_contains($text, 'Page-id converter appendix should stay skipped.'));
        $t->true(!str_contains($encoded, 'stale page-id layout payload'));
        $t->true(!str_contains($encoded, 'stale row-level page-id layout payload'));
        $t->true(!str_contains($encoded, 'selected row-level page-id layout payload'));
        $t->true(!str_contains($encoded, 'selected page-id layout payload'));
        $t->true(!str_contains($encoded, 'stale page-id order payload'));
        $t->true(!str_contains($encoded, 'stale row-level page-id order payload'));
        $t->true(!str_contains($encoded, 'selected row-level page-id order payload'));
        $t->true(!str_contains($encoded, 'selected page-id order payload'));
    },
];
