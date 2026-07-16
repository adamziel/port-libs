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
    'orders source-keyed pdftext dictionary page maps before selected layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                'dictionary_output' => [
                    7101 => $pdftextLinesPage(7101, [
                        ['text' => 'Second page-map selected column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                        ['text' => 'First page-map selected column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                    ]),
                    7100 => $pdftextLinesPage(7100, [
                        ['text' => 'Page-map cover must not become selected', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    7102 => $pdftextLinesPage(7102, [
                        ['text' => 'Page-map appendix must not become selected', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                ],
            ],
            [
                [
                    'page' => 7101,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'page-map selected order payload must stay hidden',
                ],
            ],
            orderImages: [
                ['page' => 7101, 'image' => 'page-map-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(7101, $result['pages'][0]['pnum']);
        $t->same(['First page-map selected column', 'Second page-map selected column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First page-map selected column Second page-map selected column', $blocks[0]['text']);
        $t->same(7101, $result['pages'][0]['order']['page'] ?? null);
        $t->true(!str_contains($encoded, 'Page-map cover must not become selected'));
        $t->true(!str_contains($encoded, 'Page-map appendix must not become selected'));
        $t->true(!str_contains($encoded, 'page-map selected order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'orders source-keyed pdftext dictionary page maps before WordPress supplied imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-pdftext-page-map-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% page-map pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    'dictionary_output' => [
                        '7111' => $pdftextLinesPage(7111, [
                            ['text' => 'Second converter page-map body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                            ['text' => 'First converter page-map heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                        ]),
                        '7110' => $pdftextLinesPage(7110, [
                            ['text' => 'Converter page-map cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                        ]),
                        '7112' => $pdftextLinesPage(7112, [
                            ['text' => 'Converter page-map appendix should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                        ]),
                    ],
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['page' => 7111, 'image' => 'page-map-layout-render'],
                    ],
                    'layout_results' => [[
                        'page' => 7111,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'page-map title layout row payload must stay hidden'],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'page-map layout payload must stay hidden',
                    ]],
                    'order_images' => [
                        ['page' => 7111, 'image' => 'page-map-order-render'],
                    ],
                    'order_results' => [[
                        'page' => 7111,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'page-map first order row payload must stay hidden'],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                        'raw_payload' => 'page-map order payload must stay hidden',
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
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('# First Converter Page-Map Heading.', $text);
        $t->contains('Second converter page-map body.', $text);
        $t->true(strpos($text, '# First Converter Page-Map Heading.') < strpos($text, 'Second converter page-map body.'));
        $t->true(!str_contains($text, 'Converter page-map cover should stay skipped.'));
        $t->true(!str_contains($text, 'Converter page-map appendix should stay skipped.'));
        $t->true(!str_contains($encoded, 'page-map title layout row payload'));
        $t->true(!str_contains($encoded, 'page-map layout payload'));
        $t->true(!str_contains($encoded, 'page-map first order row payload'));
        $t->true(!str_contains($encoded, 'page-map order payload'));
    },
];
