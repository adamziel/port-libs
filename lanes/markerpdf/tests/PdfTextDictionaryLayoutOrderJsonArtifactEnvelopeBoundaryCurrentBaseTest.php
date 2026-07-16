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

$jsonEnvelope = static function (array $value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
};

return [
    'unwraps raw JSON source-keyed order artifact envelopes before selected pdftext assignment' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage, $jsonEnvelope): void {
        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(9700, [
                    ['text' => 'JSON artifact cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(9701, [
                    ['text' => 'Second JSON artifact column', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First JSON artifact column', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            [
                [
                    'dictionary_output' => $jsonEnvelope([
                        '9700' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'stale JSON order artifact payload must stay hidden',
                        ],
                        '9701' => [
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'selected JSON order row payload must stay hidden'],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'selected JSON order artifact payload must stay hidden',
                        ],
                    ]),
                    'raw_payload' => 'outer JSON order envelope payload must stay hidden',
                ],
            ],
            orderImages: [
                [
                    'dictionary_output' => $jsonEnvelope([
                        '9700' => ['image' => 'json-artifact-cover-order-render'],
                        '9701' => ['image' => 'json-artifact-selected-order-render'],
                    ]),
                ],
            ],
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $order = $result['pages'][0]['order'] ?? [];

        $t->same([1], $result['page_range']);
        $t->same(9701, $result['pages'][0]['pnum']);
        $t->same(['First JSON artifact column', 'Second JSON artifact column'], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $t->same('First JSON artifact column Second JSON artifact column', $blocks[0]['text']);
        $t->same([
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ], $order['bboxes'] ?? []);
        $t->true(!array_key_exists('dictionary_output', $order));
        $t->true(!str_contains($encoded, 'JSON artifact cover should stay skipped'));
        $t->true(!str_contains($encoded, 'stale JSON order artifact payload'));
        $t->true(!str_contains($encoded, 'selected JSON order row payload'));
        $t->true(!str_contains($encoded, 'selected JSON order artifact payload'));
        $t->true(!str_contains($encoded, 'outer JSON order envelope payload'));
        $t->same(1, $result['metadata']['order_plan']['image_count']);
        $t->same(1, $result['metadata']['order_plan']['order_result_count']);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages']);
    },
    'unwraps raw JSON layout and order artifact envelopes for WordPress pdftext imports' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage, $jsonEnvelope): void {
        $path = sys_get_temp_dir() . '/markerpdf-json-artifact-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% JSON artifact pdftext layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(9710, [
                        ['text' => 'JSON artifact converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(9711, [
                        ['text' => 'Second converter JSON artifact body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter JSON artifact heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [[
                        'dictionary_output' => $jsonEnvelope([
                            '9710' => ['image' => 'json-artifact-layout-cover-render'],
                            '9711' => ['image' => 'json-artifact-layout-selected-render'],
                        ]),
                    ]],
                    'layout_results' => [[
                        'dictionary_output' => $jsonEnvelope([
                            '9710' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                    ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => 'stale JSON layout row payload must stay hidden'],
                                ],
                                'raw_payload' => 'stale JSON layout artifact payload must stay hidden',
                            ],
                            '9711' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'selected JSON layout title payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'selected JSON layout artifact payload must stay hidden',
                            ],
                        ]),
                        'raw_payload' => 'outer JSON layout envelope payload must stay hidden',
                    ]],
                    'order_images' => [[
                        'dictionary_output' => $jsonEnvelope([
                            '9710' => ['image' => 'json-artifact-order-cover-render'],
                            '9711' => ['image' => 'json-artifact-order-selected-render'],
                        ]),
                    ]],
                    'order_results' => [[
                        'dictionary_output' => $jsonEnvelope([
                            '9710' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                    ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'stale JSON order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'stale JSON order artifact payload must stay hidden',
                            ],
                            '9711' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'selected JSON order row payload must stay hidden'],
                                ],
                                'raw_payload' => 'selected JSON order artifact payload must stay hidden',
                            ],
                        ]),
                        'raw_payload' => 'outer JSON order envelope payload must stay hidden',
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
        $t->contains('# First Converter Json Artifact Heading.', $text);
        $t->contains('Second converter JSON artifact body.', $text);
        $t->true(strpos($text, '# First Converter Json Artifact Heading.') < strpos($text, 'Second converter JSON artifact body.'));
        $t->true(!str_contains($text, 'JSON artifact converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'stale JSON layout row payload'));
        $t->true(!str_contains($encoded, 'stale JSON layout artifact payload'));
        $t->true(!str_contains($encoded, 'selected JSON layout title payload'));
        $t->true(!str_contains($encoded, 'selected JSON layout artifact payload'));
        $t->true(!str_contains($encoded, 'outer JSON layout envelope payload'));
        $t->true(!str_contains($encoded, 'stale JSON order row payload'));
        $t->true(!str_contains($encoded, 'stale JSON order artifact payload'));
        $t->true(!str_contains($encoded, 'selected JSON order row payload'));
        $t->true(!str_contains($encoded, 'selected JSON order artifact payload'));
        $t->true(!str_contains($encoded, 'outer JSON order envelope payload'));
    },
];
