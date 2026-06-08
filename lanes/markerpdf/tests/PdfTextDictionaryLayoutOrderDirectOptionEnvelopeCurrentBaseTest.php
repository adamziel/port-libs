<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$pdftextDirectOptionEnvelopePage = static function (int $page, array $lines): array {
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
    'accepts direct artifact option envelopes before selected WordPress layout order import' => static function (
        TestRunner $t
    ) use ($pdftextDirectOptionEnvelopePage): void {
        $path = sys_get_temp_dir() . '/markerpdf-direct-option-envelope-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% direct option envelope pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextDirectOptionEnvelopePage(12100, [
                        ['text' => 'Direct option envelope cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextDirectOptionEnvelopePage(12101, [
                        ['text' => 'Second direct option envelope body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First direct option envelope heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        'dictionary_output' => [
                            '12100' => ['image' => 'direct-option-cover-layout-render'],
                            '12101' => ['image' => 'direct-option-selected-layout-render'],
                            'metadata' => ['raw_payload' => 'direct option layout image metadata must stay hidden'],
                        ],
                    ],
                    'layout_results' => [
                        'pages' => [
                            [
                                'page' => 12100,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ],
                                'raw_payload' => 'direct option cover layout payload must stay hidden',
                            ],
                            [
                                'page' => 12101,
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'direct option selected layout title payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'direct option selected layout payload must stay hidden',
                            ],
                        ],
                        'raw_payload' => 'direct option layout envelope payload must stay hidden',
                    ],
                    'order_images' => [
                        'pdftext' => [
                            'pages' => [
                                ['page' => 12100, 'image' => 'direct-option-cover-order-render'],
                                ['page' => 12101, 'image' => 'direct-option-selected-order-render'],
                            ],
                            'raw_payload' => 'direct option order image envelope payload must stay hidden',
                        ],
                    ],
                    'order_results' => [
                        'page_map' => [
                            '12100' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ],
                                'raw_payload' => 'direct option cover order payload must stay hidden',
                            ],
                            '12101' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'direct option selected order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'direct option selected order payload must stay hidden',
                            ],
                            'metadata' => ['raw_payload' => 'direct option order page-map metadata must stay hidden'],
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
        $t->contains('# First Direct Option Envelope Heading.', $text);
        $t->contains('Second direct option envelope body.', $text);
        $t->true(strpos($text, '# First Direct Option Envelope Heading.') < strpos($text, 'Second direct option envelope body.'));
        $t->true(!str_contains($text, 'Direct option envelope cover should stay skipped.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'direct option layout image metadata'));
        $t->true(!str_contains($encoded, 'direct option cover layout payload'));
        $t->true(!str_contains($encoded, 'direct option selected layout title payload'));
        $t->true(!str_contains($encoded, 'direct option selected layout payload'));
        $t->true(!str_contains($encoded, 'direct option layout envelope payload'));
        $t->true(!str_contains($encoded, 'direct option order image envelope payload'));
        $t->true(!str_contains($encoded, 'direct option cover order payload'));
        $t->true(!str_contains($encoded, 'direct option selected order row payload'));
        $t->true(!str_contains($encoded, 'direct option selected order payload'));
        $t->true(!str_contains($encoded, 'direct option order page-map metadata'));
    },
    'rejects metadata-only direct artifact option envelopes before supplied import' => static function (
        TestRunner $t
    ) use ($pdftextDirectOptionEnvelopePage): void {
        $path = sys_get_temp_dir() . '/markerpdf-direct-option-envelope-metadata-only-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% metadata-only direct option envelope boundary\n%%EOF");

        try {
            $t->throws(InvalidArgumentException::class, static fn () => (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextDirectOptionEnvelopePage(12200, [
                        ['text' => 'Metadata only direct envelope body.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'lowres_images' => [
                        'dictionary_output' => [
                            'metadata' => ['raw_payload' => 'metadata-only direct envelope must stay inert'],
                        ],
                    ],
                    'layout_results' => [],
                    'order_results' => [],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            ));
        } finally {
            unlink($path);
        }
    },
];
