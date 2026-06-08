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
    'rejects stale direct source-keyed order artifacts even when inner selected markers match' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(10400, [
                    ['text' => 'Stale direct-key cover page should remain skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(10401, [
                    ['text' => 'Second direct-key conflict body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                    ['text' => 'First direct-key conflict heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                ]),
            ],
            [
                10400 => [
                    'metadata' => ['selected_page_index' => 0],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'stale direct-key selected-marker row payload must stay hidden'],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'stale direct-key selected-marker order payload must stay hidden',
                ],
            ],
            orderImages: [
                10400 => [
                    'metadata' => ['selected_page_index' => 0],
                    'image' => 'stale-direct-key-selected-marker-order-render',
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
        $t->same([
            'Second direct-key conflict body.',
            'First direct-key conflict heading.',
        ], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $mergedText = $blocks[0]['text'] ?? '';
        $t->contains('Second direct-key conflict body.', $mergedText);
        $t->contains('First direct-key conflict heading.', $mergedText);
        $t->true(strpos($mergedText, 'Second direct-key conflict body.') < strpos($mergedText, 'First direct-key conflict heading.'));
        $t->same([], $order['bboxes'] ?? []);
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Stale direct-key cover page should remain skipped.'));
        $t->true(!str_contains($encoded, 'stale direct-key selected-marker order payload'));
        $t->true(!str_contains($encoded, 'stale direct-key selected-marker row payload'));
    },
    'rejects stale direct source-keyed layout and order artifacts before WordPress pdftext import' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-direct-key-marker-conflict-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% direct source-key marker conflict boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(10500, [
                        ['text' => 'Stale converter direct-key cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(10501, [
                        ['text' => 'Second converter direct-key conflict body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter direct-key conflict heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        10500 => [
                            'metadata' => ['selected_page_index' => 0],
                            'image' => 'stale-direct-key-selected-marker-layout-render',
                        ],
                    ],
                    'layout_results' => [
                        10500 => [
                            'metadata' => ['selected_page_index' => 0],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'stale direct-key selected-marker layout row payload must stay hidden'],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'stale direct-key selected-marker layout payload must stay hidden',
                        ],
                    ],
                    'order_images' => [
                        10500 => [
                            'metadata' => ['selected_page_index' => 0],
                            'image' => 'stale-direct-key-selected-marker-order-render',
                        ],
                    ],
                    'order_results' => [
                        10500 => [
                            'metadata' => ['selected_page_index' => 0],
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'stale direct-key selected-marker order row payload must stay hidden'],
                            ],
                            'raw_payload' => 'stale direct-key selected-marker order payload must stay hidden',
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
        $t->same([], $result['metadata']['supplied_boundaries'] ?? null);
        $t->true(!array_key_exists('layout_plan', $result['metadata']));
        $t->true(!array_key_exists('order_plan', $result['metadata']));
        $t->contains('Second converter direct-key conflict body.', $text);
        $t->contains('First converter direct-key conflict heading.', $text);
        $t->true(strpos($text, 'Second converter direct-key conflict body.') < strpos($text, 'First converter direct-key conflict heading.'));
        $t->true(!str_contains($text, 'Stale converter direct-key cover should stay skipped.'));
        $t->true(!str_contains($text, '# First Converter Direct-Key Conflict Heading.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'stale direct-key selected-marker layout payload'));
        $t->true(!str_contains($encoded, 'stale direct-key selected-marker layout row payload'));
        $t->true(!str_contains($encoded, 'stale direct-key selected-marker order payload'));
        $t->true(!str_contains($encoded, 'stale direct-key selected-marker order row payload'));
    },
];
