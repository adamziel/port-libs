<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$pdftextTypedPayloadListPage = static function (int $page, array $lines): array {
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

$jsonEnvelope = static fn (array $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$ambiguousOrderPayloads = static function (string $label): array {
    return [
        [
            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
            'bboxes' => [
                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => "{$label} first unmarked order row payload must stay hidden"],
                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ],
            'raw_payload' => "{$label} first unmarked order payload must stay hidden",
        ],
        [
            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
            'bboxes' => [
                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => "{$label} second unmarked order row payload must stay hidden"],
            ],
            'raw_payload' => "{$label} second unmarked order payload must stay hidden",
        ],
    ];
};

$ambiguousLayoutPayloads = static function (string $label): array {
    return [
        [
            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
            'bboxes' => [
                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => "{$label} first unmarked layout row payload must stay hidden"],
                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
            ],
            'raw_payload' => "{$label} first unmarked layout payload must stay hidden",
        ],
        [
            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
            'bboxes' => [
                ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => "{$label} second unmarked layout row payload must stay hidden"],
            ],
            'raw_payload' => "{$label} second unmarked layout payload must stay hidden",
        ],
    ];
};

return [
    'rejects ambiguous typed JSON-list order payloads before selected pdftext assignment' => static function (
        TestRunner $t
    ) use ($pdftextTypedPayloadListPage, $jsonEnvelope, $ambiguousOrderPayloads): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextTypedPayloadListPage(17000, [
                    ['text' => 'Typed payload-list cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextTypedPayloadListPage(17001, [
                    ['text' => 'Second typed payload-list column stays source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First typed payload-list column has no trusted order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['page' => 17001],
                    'order_result' => [
                        'dictionary_output' => $jsonEnvelope($ambiguousOrderPayloads('typed payload-list extractor')),
                    ],
                    'raw_payload' => 'typed payload-list outer order wrapper payload must stay hidden',
                ],
            ],
            orderImages: [
                ['metadata' => ['page' => 17001], 'image' => 'typed-payload-list-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(17001, $result['pages'][0]['pnum']);
        $t->same([
            'Second typed payload-list column stays source ordered',
            'First typed payload-list column has no trusted order',
        ], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second typed payload-list column stays source ordered First typed payload-list column has no trusted order', $blocks[0]['text']);
        $t->same(null, $result['pages'][0]['order'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
        $t->true(!str_contains($encoded, 'Typed payload-list cover should stay skipped'));
        $t->true(!str_contains($encoded, 'typed payload-list extractor first unmarked order payload'));
        $t->true(!str_contains($encoded, 'typed payload-list extractor first unmarked order row payload'));
        $t->true(!str_contains($encoded, 'typed payload-list extractor second unmarked order payload'));
        $t->true(!str_contains($encoded, 'typed payload-list extractor second unmarked order row payload'));
        $t->true(!str_contains($encoded, 'typed payload-list outer order wrapper payload'));
    },
    'rejects ambiguous typed JSON-list layout and order payloads before WordPress imports' => static function (
        TestRunner $t
    ) use ($pdftextTypedPayloadListPage, $jsonEnvelope, $ambiguousLayoutPayloads, $ambiguousOrderPayloads): void {
        $path = sys_get_temp_dir() . '/markerpdf-typed-payload-list-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% typed JSON-list layout order payload boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextTypedPayloadListPage(17100, [
                        ['text' => 'Typed payload-list converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextTypedPayloadListPage(17101, [
                        ['text' => 'Second converter typed payload-list body stays source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter typed payload-list has no trusted layout.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['metadata' => ['page' => 17101], 'image' => 'typed-payload-list-layout-render'],
                    ],
                    'layout_results' => [[
                        'metadata' => ['page' => 17101],
                        'layout_result' => [
                            'pages' => $jsonEnvelope($ambiguousLayoutPayloads('typed payload-list converter')),
                        ],
                        'raw_payload' => 'typed payload-list outer layout wrapper payload must stay hidden',
                    ]],
                    'order_images' => [
                        ['metadata' => ['page' => 17101], 'image' => 'typed-payload-list-order-render'],
                    ],
                    'order_results' => [[
                        'metadata' => ['page' => 17101],
                        'order_result' => [
                            'dictionary_output' => $jsonEnvelope($ambiguousOrderPayloads('typed payload-list converter')),
                        ],
                        'raw_payload' => 'typed payload-list outer order wrapper payload must stay hidden',
                    ]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([1], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['layout_result_count'] ?? null);
        $t->same(0, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['image_count'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['order_result_count'] ?? null);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('Second converter typed payload-list body stays source ordered.', $text);
        $t->contains('First converter typed payload-list has no trusted layout.', $text);
        $t->true(strpos($text, 'Second converter typed payload-list body stays source ordered.') < strpos($text, 'First converter typed payload-list has no trusted layout.'));
        $t->true(!str_contains($text, 'Typed payload-list converter cover should stay skipped.'));
        $t->true(!str_contains($text, '# First Converter Typed Payload-List Has No Trusted Layout.'));
        $t->true(!str_contains($encoded, 'typed payload-list converter first unmarked layout payload'));
        $t->true(!str_contains($encoded, 'typed payload-list converter first unmarked layout row payload'));
        $t->true(!str_contains($encoded, 'typed payload-list converter second unmarked layout payload'));
        $t->true(!str_contains($encoded, 'typed payload-list converter second unmarked layout row payload'));
        $t->true(!str_contains($encoded, 'typed payload-list outer layout wrapper payload'));
        $t->true(!str_contains($encoded, 'typed payload-list converter first unmarked order payload'));
        $t->true(!str_contains($encoded, 'typed payload-list converter first unmarked order row payload'));
        $t->true(!str_contains($encoded, 'typed payload-list converter second unmarked order payload'));
        $t->true(!str_contains($encoded, 'typed payload-list converter second unmarked order row payload'));
        $t->true(!str_contains($encoded, 'typed payload-list outer order wrapper payload'));
    },
];
