<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
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
    'accepts decimal source-keyed top-level layout and order maps before WordPress pdftext import' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-decimal-direct-map-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% decimal direct map pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    'dictionary_output' => [
                        '+9810.0' => $pdftextLinesPage(9810, [
                            ['text' => 'Decimal direct map cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                        ]),
                        '+9811.0' => $pdftextLinesPage(9811, [
                            ['text' => 'Second decimal direct map body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                            ['text' => 'First decimal direct map heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                        ]),
                    ],
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        '+9810.0' => ['image' => 'decimal-direct-cover-layout-render'],
                        '+9811.0' => ['image' => 'decimal-direct-selected-layout-render'],
                    ],
                    'layout_results' => [
                        '+9810.0' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'decimal direct stale layout payload must stay hidden',
                        ],
                        '+9811.0' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'decimal direct selected layout title payload must stay hidden'],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'decimal direct selected layout payload must stay hidden',
                        ],
                    ],
                    'order_images' => [
                        '+9810.0' => ['image' => 'decimal-direct-cover-order-render'],
                        '+9811.0' => ['image' => 'decimal-direct-selected-order-render'],
                    ],
                    'order_results' => [
                        '+9810.0' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'decimal direct stale order payload must stay hidden',
                        ],
                        '+9811.0' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'decimal direct selected order row payload must stay hidden'],
                            ],
                            'raw_payload' => 'decimal direct selected order payload must stay hidden',
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
        $t->contains('# First Decimal Direct Map Heading.', $text);
        $t->contains('Second decimal direct map body.', $text);
        $t->true(strpos($text, '# First Decimal Direct Map Heading.') < strpos($text, 'Second decimal direct map body.'));
        $t->true(!str_contains($text, 'Decimal direct map cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'decimal direct stale layout payload'));
        $t->true(!str_contains($encoded, 'decimal direct selected layout title payload'));
        $t->true(!str_contains($encoded, 'decimal direct selected layout payload'));
        $t->true(!str_contains($encoded, 'decimal direct stale order payload'));
        $t->true(!str_contains($encoded, 'decimal direct selected order row payload'));
        $t->true(!str_contains($encoded, 'decimal direct selected order payload'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
    },
];
