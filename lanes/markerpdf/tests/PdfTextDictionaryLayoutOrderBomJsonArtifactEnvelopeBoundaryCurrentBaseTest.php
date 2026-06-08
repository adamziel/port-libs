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

$bomJsonEnvelope = static fn (array $value): string => "\xEF\xBB\xBF" . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

return [
    'unwraps BOM-prefixed typed order-result payload envelopes before pdftext layout assignment' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage, $bomJsonEnvelope): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(9800, [
                    ['text' => 'BOM typed order cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(9801, [
                    ['text' => 'Second BOM typed order column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First BOM typed order column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'metadata' => ['document_page' => 9801],
                    'order_result' => [
                        'dictionary_output' => $bomJsonEnvelope([
                            '9800' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ],
                                'raw_payload' => 'stale BOM typed order payload must stay hidden',
                            ],
                            '9801' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'selected BOM typed order row payload must stay hidden'],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ],
                                'raw_payload' => 'selected BOM typed order payload must stay hidden',
                            ],
                        ]),
                    ],
                    'raw_payload' => 'outer BOM typed order wrapper payload must stay hidden',
                ],
            ],
            orderImages: [
                ['metadata' => ['document_page' => 9801], 'image' => 'bom-typed-order-render'],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(9801, $result['pages'][0]['pnum']);
        $t->same(['First BOM typed order column', 'Second BOM typed order column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First BOM typed order column Second BOM typed order column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('dictionary_output', $order));
        $t->true(!str_contains($encoded, 'BOM typed order cover should stay skipped'));
        $t->true(!str_contains($encoded, 'stale BOM typed order payload'));
        $t->true(!str_contains($encoded, 'selected BOM typed order row payload'));
        $t->true(!str_contains($encoded, 'selected BOM typed order payload'));
        $t->true(!str_contains($encoded, 'outer BOM typed order wrapper payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'unwraps BOM-prefixed layout and order artifact envelopes for WordPress pdftext imports' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage, $bomJsonEnvelope): void {
        $path = sys_get_temp_dir() . '/markerpdf-bom-artifact-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% BOM artifact pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(9810, [
                        ['text' => 'BOM artifact converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(9811, [
                        ['text' => 'Second converter BOM artifact body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter BOM artifact heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [[
                        'dictionary_output' => $bomJsonEnvelope([
                            '9810' => ['image' => 'bom-artifact-layout-cover-render'],
                            '9811' => ['image' => 'bom-artifact-layout-selected-render'],
                        ]),
                    ]],
                    'layout_results' => [[
                        'dictionary_output' => $bomJsonEnvelope([
                            '9810' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                    ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => 'stale BOM layout row payload must stay hidden'],
                                ],
                                'raw_payload' => 'stale BOM layout artifact payload must stay hidden',
                            ],
                            '9811' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'selected BOM layout title payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'selected BOM layout artifact payload must stay hidden',
                            ],
                        ]),
                        'raw_payload' => 'outer BOM layout envelope payload must stay hidden',
                    ]],
                    'order_images' => [[
                        'dictionary_output' => $bomJsonEnvelope([
                            '9810' => ['image' => 'bom-artifact-order-cover-render'],
                            '9811' => ['image' => 'bom-artifact-order-selected-render'],
                        ]),
                    ]],
                    'order_results' => [[
                        'dictionary_output' => $bomJsonEnvelope([
                            '9810' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'stale BOM order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'stale BOM order artifact payload must stay hidden',
                            ],
                            '9811' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'selected BOM order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'selected BOM order artifact payload must stay hidden',
                            ],
                        ]),
                        'raw_payload' => 'outer BOM order envelope payload must stay hidden',
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
        $t->contains('# First Converter Bom Artifact Heading.', $text);
        $t->contains('Second converter BOM artifact body.', $text);
        $t->true(strpos($text, '# First Converter Bom Artifact Heading.') < strpos($text, 'Second converter BOM artifact body.'));
        $t->true(!str_contains($text, 'BOM artifact converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'stale BOM layout row payload'));
        $t->true(!str_contains($encoded, 'stale BOM layout artifact payload'));
        $t->true(!str_contains($encoded, 'selected BOM layout title payload'));
        $t->true(!str_contains($encoded, 'selected BOM layout artifact payload'));
        $t->true(!str_contains($encoded, 'outer BOM layout envelope payload'));
        $t->true(!str_contains($encoded, 'stale BOM order row payload'));
        $t->true(!str_contains($encoded, 'stale BOM order artifact payload'));
        $t->true(!str_contains($encoded, 'selected BOM order row payload'));
        $t->true(!str_contains($encoded, 'selected BOM order artifact payload'));
        $t->true(!str_contains($encoded, 'outer BOM order envelope payload'));
    },
];
