<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$pdftextCurrentKeyPayloadPage = static function (int $page, array $lines): array {
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

$currentOrderPayload = static function (string $label): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => "{$label} current order row payload must stay hidden"],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ],
        'raw_payload' => "{$label} current order payload must stay hidden",
    ];
};

$staleOrderPayload = static function (string $label): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => "{$label} stale order row payload must stay hidden"],
        ],
        'raw_payload' => "{$label} stale order payload must stay hidden",
    ];
};

$currentLayoutPayload = static function (string $label): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => "{$label} current layout row payload must stay hidden"],
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
        'raw_payload' => "{$label} current layout payload must stay hidden",
    ];
};

$staleLayoutPayload = static function (string $label): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => "{$label} stale layout row payload must stay hidden"],
        ],
        'raw_payload' => "{$label} stale layout payload must stay hidden",
    ];
};

return [
    'uses exact current keyed nested order payload before stale unmarked dictionary rows' => static function (
        TestRunner $t
    ) use ($pdftextCurrentKeyPayloadPage, $currentOrderPayload, $staleOrderPayload): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextCurrentKeyPayloadPage(18200, [
                    ['text' => 'Current-key payload cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextCurrentKeyPayloadPage(18201, [
                    ['text' => 'Second current-key nested payload column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First current-key nested payload column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['page' => 18201],
                    'order_result' => [
                        'dictionary_output' => [
                            '18201' => $currentOrderPayload('extractor keyed nested'),
                            'stale_unmarked' => $staleOrderPayload('extractor unmarked nested'),
                        ],
                    ],
                    'raw_payload' => 'outer current-key nested order wrapper payload must stay hidden',
                ],
            ],
            orderImages: [
                ['metadata' => ['page' => 18201], 'image' => 'current-key-nested-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(18201, $result['pages'][0]['pnum']);
        $t->same([
            'First current-key nested payload column',
            'Second current-key nested payload column',
        ], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First current-key nested payload column Second current-key nested payload column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Current-key payload cover should stay skipped'));
        $t->true(!str_contains($encoded, 'extractor keyed nested current order row payload'));
        $t->true(!str_contains($encoded, 'extractor keyed nested current order payload'));
        $t->true(!str_contains($encoded, 'extractor unmarked nested stale order row payload'));
        $t->true(!str_contains($encoded, 'extractor unmarked nested stale order payload'));
        $t->true(!str_contains($encoded, 'outer current-key nested order wrapper payload'));
    },
    'uses exact current keyed nested layout and order payloads for WordPress imports' => static function (
        TestRunner $t
    ) use ($pdftextCurrentKeyPayloadPage, $currentLayoutPayload, $staleLayoutPayload, $currentOrderPayload, $staleOrderPayload): void {
        $path = sys_get_temp_dir() . '/markerpdf-current-key-nested-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% current-key nested pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextCurrentKeyPayloadPage(18300, [
                        ['text' => 'Current-key nested converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextCurrentKeyPayloadPage(18301, [
                        ['text' => 'Second converter current-key nested body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter current-key nested heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['metadata' => ['page' => 18301], 'image' => 'current-key-nested-layout-render'],
                    ],
                    'layout_results' => [[
                        'metadata' => ['page' => 18301],
                        'layout_result' => [
                            'dictionary_output' => [
                                '18301' => $currentLayoutPayload('converter keyed nested'),
                                'stale_unmarked' => $staleLayoutPayload('converter unmarked nested'),
                            ],
                        ],
                        'raw_payload' => 'outer current-key nested layout wrapper payload must stay hidden',
                    ]],
                    'order_images' => [
                        ['metadata' => ['page' => 18301], 'image' => 'current-key-nested-order-render'],
                    ],
                    'order_results' => [[
                        'metadata' => ['page' => 18301],
                        'order_result' => [
                            'dictionary_output' => [
                                '18301' => $currentOrderPayload('converter keyed nested'),
                                'stale_unmarked' => $staleOrderPayload('converter unmarked nested'),
                            ],
                        ],
                        'raw_payload' => 'outer current-key nested order wrapper payload must stay hidden',
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
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('# First Converter Current-Key Nested Heading.', $text);
        $t->contains('Second converter current-key nested body.', $text);
        $t->true(strpos($text, '# First Converter Current-Key Nested Heading.') < strpos($text, 'Second converter current-key nested body.'));
        $t->true(!str_contains($text, 'Current-key nested converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'converter keyed nested current layout row payload'));
        $t->true(!str_contains($encoded, 'converter keyed nested current layout payload'));
        $t->true(!str_contains($encoded, 'converter unmarked nested stale layout row payload'));
        $t->true(!str_contains($encoded, 'converter unmarked nested stale layout payload'));
        $t->true(!str_contains($encoded, 'outer current-key nested layout wrapper payload'));
        $t->true(!str_contains($encoded, 'converter keyed nested current order row payload'));
        $t->true(!str_contains($encoded, 'converter keyed nested current order payload'));
        $t->true(!str_contains($encoded, 'converter unmarked nested stale order row payload'));
        $t->true(!str_contains($encoded, 'converter unmarked nested stale order payload'));
        $t->true(!str_contains($encoded, 'outer current-key nested order wrapper payload'));
    },
];
