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
    'rejects source keyed order payload whose direct envelope key is only one above selected pdftext page' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(5700, [
                    ['text' => 'Envelope alias cover page should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(5701, [
                    ['text' => 'Second envelope alias column remains source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First envelope alias column has no trusted order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'dictionary_output' => [
                        '5702' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'one-above stale order row payload must stay hidden'],
                            ],
                            'raw_payload' => 'one-above stale order payload must stay hidden',
                        ],
                    ],
                    'raw_payload' => 'one-above stale order wrapper payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'dictionary_output' => [
                        '5702' => ['image' => 'one-above-stale-order-render'],
                    ],
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(5701, $result['pages'][0]['pnum']);
        $t->same(['Second envelope alias column remains source ordered', 'First envelope alias column has no trusted order'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second envelope alias column remains source ordered First envelope alias column has no trusted order', $blocks[0]['text']);
        $t->same(null, $result['pages'][0]['order'] ?? null);
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Envelope alias cover page should stay skipped'));
        $t->true(!str_contains($encoded, 'one-above stale order payload'));
        $t->true(!str_contains($encoded, 'one-above stale order row payload'));
        $t->true(!str_contains($encoded, 'one-above stale order wrapper payload'));
    },
    'rejects source keyed layout and order payloads whose direct envelope keys are only one above selected WordPress page' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-envelope-key-one-above-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% envelope key one-above pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(5710, [
                        ['text' => 'Envelope key one-above converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(5711, [
                        ['text' => 'Second converter envelope alias column stays source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter envelope alias has no supplied layout.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        [
                            'pages' => [
                                '5712' => ['image' => 'one-above-stale-layout-render'],
                            ],
                        ],
                    ],
                    'layout_results' => [[
                        'pages' => [
                            '5712' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'one-above stale layout title payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'one-above stale layout payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'one-above stale layout wrapper payload must stay hidden',
                    ]],
                    'order_images' => [
                        [
                            'dictionary_output' => [
                                '5712' => ['image' => 'one-above-stale-order-render'],
                            ],
                        ],
                    ],
                    'order_results' => [[
                        'dictionary_output' => [
                            '5712' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'one-above stale converter order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'one-above stale converter order payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'one-above stale converter order wrapper payload must stay hidden',
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
        $t->same([], $result['metadata']['supplied_boundaries'] ?? null);
        $t->true(!array_key_exists('layout_plan', $result['metadata']));
        $t->true(!array_key_exists('order_plan', $result['metadata']));
        $t->contains('Second converter envelope alias column stays source ordered.', $text);
        $t->contains('First converter envelope alias has no supplied layout.', $text);
        $t->true(strpos($text, 'Second converter envelope alias column stays source ordered.') < strpos($text, 'First converter envelope alias has no supplied layout.'));
        $t->true(!str_contains($text, '# First Converter Envelope Alias Has No Supplied Layout.'));
        $t->true(!str_contains($text, 'Envelope key one-above converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'one-above stale layout title payload'));
        $t->true(!str_contains($encoded, 'one-above stale layout payload'));
        $t->true(!str_contains($encoded, 'one-above stale layout wrapper payload'));
        $t->true(!str_contains($encoded, 'one-above stale converter order row payload'));
        $t->true(!str_contains($encoded, 'one-above stale converter order payload'));
        $t->true(!str_contains($encoded, 'one-above stale converter order wrapper payload'));
    },
];
