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
    'uses direct source-keyed artifact maps before selected pdftext layout order assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(9100, [
                    ['text' => 'Direct keyed map cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(9101, [
                    ['text' => 'Second direct keyed map column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First direct keyed map column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                9101 => [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'direct selected order row payload must stay hidden'],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'direct selected order payload must stay hidden',
                ],
                9100 => [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'direct stale order payload must stay hidden',
                ],
            ],
            orderImages: [
                9101 => ['image' => 'direct-key-selected-order-render'],
                9100 => ['image' => 'direct-key-stale-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(9101, $result['pages'][0]['pnum']);
        $t->same(['First direct keyed map column', 'Second direct keyed map column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First direct keyed map column Second direct keyed map column', $blocks[0]['text']);
        $t->same([0.0, 0.0, 612.0, 792.0], $order['image_bbox'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('dictionary_output', $order));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Direct keyed map cover should stay skipped'));
        $t->true(!str_contains($encoded, 'direct selected order payload'));
        $t->true(!str_contains($encoded, 'direct selected order row payload'));
        $t->true(!str_contains($encoded, 'direct stale order payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses direct source-keyed layout and order maps before WordPress pdftext import' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-direct-key-map-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% direct source-keyed pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(9110, [
                        ['text' => 'Direct keyed converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(9111, [
                        ['text' => 'Second converter direct keyed body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter direct keyed heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        9111 => ['image' => 'direct-key-layout-render'],
                        9110 => ['image' => 'direct-key-stale-layout-render'],
                    ],
                    'layout_results' => [
                        9111 => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'direct selected layout title payload must stay hidden'],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'direct selected layout payload must stay hidden',
                        ],
                        9110 => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ],
                            'raw_payload' => 'direct stale layout payload must stay hidden',
                        ],
                    ],
                    'order_images' => [
                        9111 => ['image' => 'direct-key-order-render'],
                        9110 => ['image' => 'direct-key-stale-order-render'],
                    ],
                    'order_results' => [
                        9111 => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'direct selected order row payload must stay hidden'],
                            ],
                            'raw_payload' => 'direct selected order payload must stay hidden',
                        ],
                        9110 => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'direct stale order payload must stay hidden',
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
        $t->contains('# First Converter Direct Keyed Heading.', $text);
        $t->contains('Second converter direct keyed body.', $text);
        $t->true(strpos($text, '# First Converter Direct Keyed Heading.') < strpos($text, 'Second converter direct keyed body.'));
        $t->true(!str_contains($text, 'Direct keyed converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'direct selected layout title payload'));
        $t->true(!str_contains($encoded, 'direct selected layout payload'));
        $t->true(!str_contains($encoded, 'direct stale layout payload'));
        $t->true(!str_contains($encoded, 'direct selected order row payload'));
        $t->true(!str_contains($encoded, 'direct selected order payload'));
        $t->true(!str_contains($encoded, 'direct stale order payload'));
    },
];
