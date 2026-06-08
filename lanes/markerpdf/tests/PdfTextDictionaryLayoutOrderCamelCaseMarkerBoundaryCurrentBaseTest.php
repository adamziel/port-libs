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
    'uses camelCase page markers before selected pdftext dictionary order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(9400, [
                    ['text' => 'CamelCase cover must stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(9401, [
                    ['text' => 'Second camelCase sparse column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First camelCase sparse column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
                $pdftextLinesPage(9402, [
                    ['text' => 'CamelCase appendix must stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['documentPage' => 9400],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'stale camelCase order payload must stay hidden',
                ],
                [
                    'metadata' => ['pdftextPage' => 9401],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['documentPage' => 9400, 'position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'stale row-level camelCase order payload must stay hidden'],
                        ['documentPage' => 9401, 'position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'selected row-level camelCase order payload must stay hidden'],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'selected camelCase order payload must stay hidden',
                ],
            ],
            orderImages: [
                ['metadata' => ['sourcePageIndex' => 0], 'image' => 'camelcase-cover-order-render'],
                ['metadata' => ['pageNumber' => 9402], 'image' => 'camelcase-selected-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(9401, $result['pages'][0]['pnum']);
        $t->same(['First camelCase sparse column', 'Second camelCase sparse column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First camelCase sparse column Second camelCase sparse column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $result['pages'][0]['order']['bboxes'] ?? []);
        $t->true(!str_contains($encoded, 'CamelCase cover must stay skipped'));
        $t->true(!str_contains($encoded, 'CamelCase appendix must stay skipped'));
        $t->true(!str_contains($encoded, 'stale camelCase order payload'));
        $t->true(!str_contains($encoded, 'stale row-level camelCase order payload'));
        $t->true(!str_contains($encoded, 'selected row-level camelCase order payload'));
        $t->true(!str_contains($encoded, 'selected camelCase order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses camelCase layout and order markers before WordPress pdftext imports' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-camelcase-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% camelCase pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(9410, [
                        ['text' => 'CamelCase converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(9411, [
                        ['text' => 'Second camelCase converter body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First camelCase converter heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                    $pdftextLinesPage(9412, [
                        ['text' => 'CamelCase converter appendix should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['metadata' => ['sourcePageIndex' => 0], 'image' => 'camelcase-cover-layout-render'],
                        ['metadata' => ['pageNumber' => 9412], 'image' => 'camelcase-selected-layout-render'],
                    ],
                    'layout_results' => [
                        [
                            'metadata' => ['documentPage' => 9410],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'stale camelCase layout payload must stay hidden',
                        ],
                        [
                            'metadata' => ['pdftextPage' => 9411],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['documentPage' => 9410, 'label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => 'stale row-level camelCase layout payload must stay hidden'],
                                ['documentPage' => 9411, 'label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'selected row-level camelCase layout payload must stay hidden'],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'selected camelCase layout payload must stay hidden',
                        ],
                    ],
                    'order_images' => [
                        ['metadata' => ['sourcePageIndex' => 0], 'image' => 'camelcase-cover-order-render'],
                        ['metadata' => ['pageNumber' => 9412], 'image' => 'camelcase-selected-order-render'],
                    ],
                    'order_results' => [
                        [
                            'metadata' => ['documentPage' => 9410],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'stale camelCase order payload must stay hidden',
                        ],
                        [
                            'metadata' => ['pdftextPage' => 9411],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['documentPage' => 9410, 'position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'stale row-level camelCase order payload must stay hidden'],
                                ['documentPage' => 9411, 'position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'selected row-level camelCase order payload must stay hidden'],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'selected camelCase order payload must stay hidden',
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
        $t->contains('# First Camelcase Converter Heading.', $text);
        $t->contains('Second camelCase converter body.', $text);
        $t->true(strpos($text, '# First Camelcase Converter Heading.') < strpos($text, 'Second camelCase converter body.'));
        $t->true(!str_contains($text, 'CamelCase converter cover should stay skipped.'));
        $t->true(!str_contains($text, 'CamelCase converter appendix should stay skipped.'));
        $t->true(!str_contains($encoded, 'stale camelCase layout payload'));
        $t->true(!str_contains($encoded, 'stale row-level camelCase layout payload'));
        $t->true(!str_contains($encoded, 'selected row-level camelCase layout payload'));
        $t->true(!str_contains($encoded, 'selected camelCase layout payload'));
        $t->true(!str_contains($encoded, 'stale camelCase order payload'));
        $t->true(!str_contains($encoded, 'stale row-level camelCase order payload'));
        $t->true(!str_contains($encoded, 'selected row-level camelCase order payload'));
        $t->true(!str_contains($encoded, 'selected camelCase order payload'));
    },
];
