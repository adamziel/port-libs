<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

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
    'rescales normalized supplied order boxes before pdftext dictionary layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(1200, [
                    ['text' => 'Normalized order cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(1201, [
                    ['text' => 'Second normalized order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First normalized order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 1201,
                    'image_bbox' => [0.0, 0.0, 1224.0, 1584.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [0.098, 0.121, 0.474, 0.182]],
                        ['position' => 2, 'bbox' => [0.519, 0.121, 0.932, 0.182]],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 1201, 'image' => 'normalized-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));

        $t->same([1], $result['page_range']);
        $t->same(1201, $result['pages'][0]['pnum']);
        $t->same(['First normalized order column', 'Second normalized order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First normalized order column Second normalized order column', $blocks[0]['text']);
        $t->same([0.0, 0.0, 1224.0, 1584.0], $result['pages'][0]['order']['image_bbox']);
        $t->same([
            ['position' => 1, 'bbox' => [0.098, 0.121, 0.474, 0.182]],
            ['position' => 2, 'bbox' => [0.519, 0.121, 0.932, 0.182]],
        ], $result['pages'][0]['order']['bboxes']);
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'keeps nested pdftext page payload markers out of trusted document-page order metadata' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(1300, [
                    ['text' => 'Payload source cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(1301, [
                    ['text' => 'Second document-page trusted column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First document-page trusted column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['document_page' => 1301],
                    'pdftext' => $pdftextLinesPage(1300, [
                        ['text' => 'Stale nested pdftext page marker must stay out of order metadata', 'bbox' => [72.0, 160.0, 520.0, 174.0]],
                    ]),
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
            ],
            orderImages: [
                [
                    'metadata' => ['document_page' => 1301],
                    'pdftext' => $pdftextLinesPage(1300, [
                        ['text' => 'Stale nested pdftext render marker must stay out of order metadata', 'bbox' => [72.0, 180.0, 520.0, 194.0]],
                    ]),
                    'image' => 'document-page-trusted-order-render',
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(1301, $result['pages'][0]['pnum']);
        $t->same(['First document-page trusted column', 'Second document-page trusted column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First document-page trusted column Second document-page trusted column', $blocks[0]['text']);
        $t->same(1301, $order['document_page'] ?? null);
        $t->true(!array_key_exists('page', $order), 'Stale nested pdftext.page must not be preserved beside trusted document_page.');
        $t->true(!array_key_exists('pdftext', $order));
        $t->true(!str_contains($encoded, 'Stale nested pdftext page marker'));
        $t->true(!str_contains($encoded, 'Stale nested pdftext render marker'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'uses bbox-list order rows as ordered geometry before pdftext dictionary layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(1400, [
                    ['text' => 'Bbox-list cover page skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(1401, [
                    ['text' => 'Second bbox-list order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First bbox-list order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 1401,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        [318.0, 96.0, 570.0, 144.0],
                        [60.0, 96.0, 290.0, 144.0],
                    ],
                    'raw_payload' => 'bbox-list order payload must not cross page order metadata',
                ],
            ],
            orderImages: [
                ['page' => 1401, 'image' => 'bbox-list-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(1401, $result['pages'][0]['pnum']);
        $t->same(['Second bbox-list order column', 'First bbox-list order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second bbox-list order column First bbox-list order column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
        ], $result['pages'][0]['order']['bboxes']);
        $t->true(!str_contains($encoded, 'bbox-list order payload must not cross'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'infers missing order positions from bbox dictionary row order before pdftext layout assignment' => static function (TestRunner $t) use ($pdftextLinesPage): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(1500, [
                    ['text' => 'Positionless dictionary row cover skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(1501, [
                    ['text' => 'Second positionless row supplied first', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First positionless row supplied second', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'page' => 1501,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'positionless right column payload must stay review-only'],
                        ['bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'positionless left column payload must stay review-only'],
                    ],
                ],
            ],
            orderImages: [
                ['page' => 1501, 'image' => 'positionless-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(1501, $result['pages'][0]['pnum']);
        $t->same(['Second positionless row supplied first', 'First positionless row supplied second'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('Second positionless row supplied first First positionless row supplied second', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
        ], $result['pages'][0]['order']['bboxes']);
        $t->true(!str_contains($encoded, 'positionless right column payload'));
        $t->true(!str_contains($encoded, 'positionless left column payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
];
