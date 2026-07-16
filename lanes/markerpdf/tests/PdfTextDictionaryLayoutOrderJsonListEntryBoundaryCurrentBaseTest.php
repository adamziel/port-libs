<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$pdftextJsonListEntryPage = static function (int $page, array $lines): array {
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

$jsonArtifact = static function (array $value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
};

return [
    'decodes raw JSON list-entry order artifacts before selected pdftext layout assignment' => static function (
        TestRunner $t
    ) use ($pdftextJsonListEntryPage, $jsonArtifact): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextJsonListEntryPage(12300, [
                    ['text' => 'JSON list-entry order cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextJsonListEntryPage(12301, [
                    ['text' => 'Second JSON list-entry order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First JSON list-entry order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                $jsonArtifact([
                    'document_page' => 12301,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'json list-entry selected order row payload must stay hidden'],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'json list-entry selected order payload must stay hidden',
                ]),
            ],
            orderImages: [
                $jsonArtifact([
                    'document_page' => 12301,
                    'image' => 'json-list-entry-order-render',
                    'raw_payload' => 'json list-entry order image payload must stay hidden',
                ]),
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(12301, $result['pages'][0]['pnum']);
        $t->same(['First JSON list-entry order column', 'Second JSON list-entry order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First JSON list-entry order column Second JSON list-entry order column', $blocks[0]['text']);
        $t->same(12301, $order['document_page'] ?? null);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!str_contains($encoded, 'JSON list-entry order cover should stay skipped'));
        $t->true(!str_contains($encoded, 'json list-entry selected order row payload'));
        $t->true(!str_contains($encoded, 'json list-entry selected order payload'));
        $t->true(!str_contains($encoded, 'json list-entry order image payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'decodes raw JSON list-entry layout and order artifacts before WordPress pdftext imports' => static function (
        TestRunner $t
    ) use ($pdftextJsonListEntryPage, $jsonArtifact): void {
        $path = sys_get_temp_dir() . '/markerpdf-json-list-entry-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% JSON list-entry pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextJsonListEntryPage(12400, [
                        ['text' => 'JSON list-entry converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextJsonListEntryPage(12401, [
                        ['text' => 'Second converter JSON list-entry body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter JSON list-entry heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        $jsonArtifact([
                            'document_page' => 12401,
                            'image' => 'json-list-entry-layout-render',
                            'raw_payload' => 'json list-entry layout image payload must stay hidden',
                        ]),
                    ],
                    'layout_results' => [
                        $jsonArtifact([
                            'document_page' => 12401,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'json list-entry title layout row payload must stay hidden'],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'json list-entry layout payload must stay hidden',
                        ]),
                    ],
                    'order_images' => [
                        $jsonArtifact([
                            'document_page' => 12401,
                            'image' => 'json-list-entry-order-render',
                            'raw_payload' => 'json list-entry order image payload must stay hidden',
                        ]),
                    ],
                    'order_results' => [
                        $jsonArtifact([
                            'document_page' => 12401,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'json list-entry right order row payload must stay hidden'],
                            ],
                            'raw_payload' => 'json list-entry order payload must stay hidden',
                        ]),
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
        $t->contains('# First Converter Json List-Entry Heading.', $text);
        $t->contains('Second converter JSON list-entry body.', $text);
        $t->true(strpos($text, '# First Converter Json List-Entry Heading.') < strpos($text, 'Second converter JSON list-entry body.'));
        $t->true(!str_contains($text, 'JSON list-entry converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'json list-entry layout image payload'));
        $t->true(!str_contains($encoded, 'json list-entry title layout row payload'));
        $t->true(!str_contains($encoded, 'json list-entry layout payload'));
        $t->true(!str_contains($encoded, 'json list-entry order image payload'));
        $t->true(!str_contains($encoded, 'json list-entry right order row payload'));
        $t->true(!str_contains($encoded, 'json list-entry order payload'));
    },
];
