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
    'ignores scalar sidecars in source-keyed order maps before selected pdftext layout assignment' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(9300, [
                    ['text' => 'Scalar sidecar cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(9301, [
                    ['text' => 'Second scalar sidecar column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First scalar sidecar column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                'page' => 9301,
                'dictionary_output' => [
                    9301 => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'selected scalar sidecar row payload must stay hidden'],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                        'raw_payload' => 'selected scalar sidecar order payload must stay hidden',
                    ],
                    9300 => null,
                    9302 => 'numeric scalar sidecar must stay hidden',
                    'metadata' => [
                        'adapter' => 'cached source-keyed order map',
                        'raw_payload' => 'order metadata sidecar must stay hidden',
                    ],
                ],
                'raw_payload' => 'outer scalar sidecar order wrapper payload must stay hidden',
            ],
            orderImages: [
                9301 => ['image' => 'scalar-sidecar-selected-order-render'],
                9300 => null,
                'metadata' => ['raw_payload' => 'order image metadata sidecar must stay hidden'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(9301, $result['pages'][0]['pnum']);
        $t->same(['First scalar sidecar column', 'Second scalar sidecar column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First scalar sidecar column Second scalar sidecar column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Scalar sidecar cover should stay skipped'));
        $t->true(!str_contains($encoded, 'selected scalar sidecar row payload'));
        $t->true(!str_contains($encoded, 'selected scalar sidecar order payload'));
        $t->true(!str_contains($encoded, 'numeric scalar sidecar'));
        $t->true(!str_contains($encoded, 'order metadata sidecar'));
        $t->true(!str_contains($encoded, 'outer scalar sidecar order wrapper payload'));
        $t->true(!str_contains($encoded, 'order image metadata sidecar'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'ignores scalar sidecars in source-keyed layout and order maps before WordPress imports' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-scalar-sidecar-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% scalar sidecar pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(9310, [
                        ['text' => 'Scalar sidecar converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(9311, [
                        ['text' => 'Second converter scalar sidecar body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter scalar sidecar heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        9311 => ['image' => 'scalar-sidecar-layout-render'],
                        9310 => null,
                        'metadata' => ['raw_payload' => 'layout image metadata sidecar must stay hidden'],
                    ],
                    'layout_results' => [
                        9311 => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'selected scalar sidecar layout title payload must stay hidden'],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'selected scalar sidecar layout payload must stay hidden',
                        ],
                        9310 => false,
                        'metadata' => ['raw_payload' => 'layout metadata sidecar must stay hidden'],
                    ],
                    'order_images' => [
                        9311 => ['image' => 'scalar-sidecar-order-render'],
                        9310 => 'numeric order-image sidecar must stay hidden',
                    ],
                    'order_results' => [
                        9311 => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'selected scalar sidecar order row payload must stay hidden'],
                            ],
                            'raw_payload' => 'selected scalar sidecar order payload must stay hidden',
                        ],
                        9310 => ['raw_payload' => 'nonpayload numeric order sidecar must stay hidden'],
                        'metadata' => ['raw_payload' => 'order metadata sidecar must stay hidden'],
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
        $t->contains('# First Converter Scalar Sidecar Heading.', $text);
        $t->contains('Second converter scalar sidecar body.', $text);
        $t->true(strpos($text, '# First Converter Scalar Sidecar Heading.') < strpos($text, 'Second converter scalar sidecar body.'));
        $t->true(!str_contains($text, 'Scalar sidecar converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'layout image metadata sidecar'));
        $t->true(!str_contains($encoded, 'selected scalar sidecar layout title payload'));
        $t->true(!str_contains($encoded, 'selected scalar sidecar layout payload'));
        $t->true(!str_contains($encoded, 'layout metadata sidecar'));
        $t->true(!str_contains($encoded, 'numeric order-image sidecar'));
        $t->true(!str_contains($encoded, 'selected scalar sidecar order row payload'));
        $t->true(!str_contains($encoded, 'selected scalar sidecar order payload'));
        $t->true(!str_contains($encoded, 'nonpayload numeric order sidecar'));
        $t->true(!str_contains($encoded, 'order metadata sidecar'));
    },
];
