<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$pageMapDirectPayloadOwnerPage = static function (int $page, array $lines): array {
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

$stalePageMapOrderArtifact = static function (int $stalePage): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ],
        'pageMap' => [
            (string) $stalePage => [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                ],
                'raw_payload' => 'stale pageMap direct-payload owner row must stay hidden',
            ],
        ],
        'raw_payload' => 'stale pageMap direct-payload owner order payload must stay hidden',
    ];
};

$stalePageMapLayoutArtifact = static function (int $stalePage): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
        'page_map' => [
            (string) $stalePage => [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                ],
                'raw_payload' => 'stale page_map direct-payload owner row must stay hidden',
            ],
        ],
        'raw_payload' => 'stale page_map direct-payload owner layout payload must stay hidden',
    ];
};

return [
    'rejects stale pageMap direct-payload owner keys before pdftext dictionary order assignment' => static function (
        TestRunner $t
    ) use ($pageMapDirectPayloadOwnerPage, $stalePageMapOrderArtifact): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pageMapDirectPayloadOwnerPage(13000, [
                    ['text' => 'Stale pageMap owner cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pageMapDirectPayloadOwnerPage(13001, [
                    ['text' => 'Second pageMap owner body stays source ordered', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First pageMap owner heading has no trusted order', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                $stalePageMapOrderArtifact(13000),
            ],
            orderImages: [
                [
                    'image' => 'stale-page-map-owner-order-render',
                    'pageMap' => [
                        '13000' => ['image' => 'stale-page-map-owner-order-render'],
                    ],
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(13001, $result['pages'][0]['pnum']);
        $t->same([
            'Second pageMap owner body stays source ordered',
            'First pageMap owner heading has no trusted order',
        ], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second pageMap owner body stays source ordered First pageMap owner heading has no trusted order', $blocks[0]['text']);
        $t->same([], $order['bboxes'] ?? []);
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Stale pageMap owner cover should stay skipped'));
        $t->true(!str_contains($encoded, 'stale pageMap direct-payload owner row'));
        $t->true(!str_contains($encoded, 'stale pageMap direct-payload owner order payload'));
    },
    'rejects stale page_map direct-payload owner keys before WordPress layout and order import' => static function (
        TestRunner $t
    ) use ($pageMapDirectPayloadOwnerPage, $stalePageMapLayoutArtifact, $stalePageMapOrderArtifact): void {
        $path = sys_get_temp_dir() . '/markerpdf-page-map-direct-payload-owner-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% page_map direct payload owner boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pageMapDirectPayloadOwnerPage(13100, [
                        ['text' => 'Stale page_map owner converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pageMapDirectPayloadOwnerPage(13101, [
                        ['text' => 'Second converter page_map owner body stays source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter page_map owner heading has no trusted layout.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [[
                        'image' => 'stale-page-map-owner-layout-render',
                        'page_map' => [
                            '13100' => ['image' => 'stale-page-map-owner-layout-render'],
                        ],
                    ]],
                    'layout_results' => [
                        $stalePageMapLayoutArtifact(13100),
                    ],
                    'order_images' => [[
                        'image' => 'stale-page-map-owner-order-render',
                        'pageMap' => [
                            '13100' => ['image' => 'stale-page-map-owner-order-render'],
                        ],
                    ]],
                    'order_results' => [
                        $stalePageMapOrderArtifact(13100),
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
        $t->contains('Second converter page_map owner body stays source ordered.', $text);
        $t->contains('First converter page_map owner heading has no trusted layout.', $text);
        $t->true(strpos($text, 'Second converter page_map owner body stays source ordered.') < strpos($text, 'First converter page_map owner heading has no trusted layout.'));
        $t->true(!str_contains($text, 'Stale page_map owner converter cover should stay skipped.'));
        $t->true(!str_contains($text, '# First Converter Page_Map Owner Heading Has No Trusted Layout.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'stale page_map direct-payload owner row'));
        $t->true(!str_contains($encoded, 'stale page_map direct-payload owner layout payload'));
        $t->true(!str_contains($encoded, 'stale pageMap direct-payload owner row'));
        $t->true(!str_contains($encoded, 'stale pageMap direct-payload owner order payload'));
    },
];
