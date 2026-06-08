<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\LayoutAnnotator;
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
    'unwraps BOM-prefixed typed layout-result payload envelopes before pdftext layout annotation' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage, $bomJsonEnvelope): void {
        $document = (new PdfTextDocumentExtractor())->getTextBlocks(
            [
                $pdftextLinesPage(9900, [
                    ['text' => 'BOM typed layout cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(9901, [
                    ['text' => 'BOM typed layout heading', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                    ['text' => 'BOM typed layout body', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                ]),
            ],
            maxPages: 1,
            startPage: 1
        );

        $layout = (new LayoutAnnotator())->runWithSuppliedLayouts(
            [
                ['metadata' => ['document_page' => 9901], 'image' => 'bom-typed-layout-render'],
            ],
            $document['pages'],
            [
                [
                    'metadata' => ['document_page' => 9901],
                    'layout_result' => [
                        'dictionary_output' => $bomJsonEnvelope([
                            '9900' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ],
                                'raw_payload' => 'stale BOM typed layout payload must stay hidden',
                            ],
                            '9901' => [
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'selected BOM typed layout title payload must stay hidden'],
                                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                ],
                                'raw_payload' => 'selected BOM typed layout payload must stay hidden',
                            ],
                        ]),
                    ],
                    'raw_payload' => 'outer BOM typed layout wrapper payload must stay hidden',
                ],
            ],
            pageRange: $document['page_range']
        );

        $encoded = json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $page = $layout['pages'][0] ?? [];
        $layoutMetadata = is_array($page) ? ($page['layout'] ?? []) : [];

        $t->same([1], $document['page_range']);
        $t->same(9901, $page['pnum'] ?? null);
        $t->same(1, $layout['plan']['image_count'] ?? null);
        $t->same(1, $layout['plan']['layout_result_count'] ?? null);
        $t->same(1, $layout['plan']['assigned_pages'] ?? null);
        $t->same([
            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ], is_array($layoutMetadata) ? ($layoutMetadata['bboxes'] ?? []) : []);
        $t->true(!str_contains($encoded, 'BOM typed layout cover should stay skipped'));
        $t->true(!str_contains($encoded, 'stale BOM typed layout payload'));
        $t->true(!str_contains($encoded, 'selected BOM typed layout title payload'));
        $t->true(!str_contains($encoded, 'selected BOM typed layout payload'));
        $t->true(!str_contains($encoded, 'outer BOM typed layout wrapper payload'));
    },
    'unwraps BOM-prefixed typed layout-result envelopes for WordPress supplied imports' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage, $bomJsonEnvelope): void {
        $path = sys_get_temp_dir() . '/markerpdf-bom-typed-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% BOM typed layout-result pdftext boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(9910, [
                        ['text' => 'BOM typed layout converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(9911, [
                        ['text' => 'Second converter BOM typed layout body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter BOM typed layout heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => [
                        ['metadata' => ['document_page' => 9911], 'image' => 'bom-typed-layout-render'],
                    ],
                    'layout_results' => [[
                        'metadata' => ['document_page' => 9911],
                        'layout_result' => [
                            'dictionary_output' => $bomJsonEnvelope([
                                '9910' => [
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => 'stale BOM typed layout row payload must stay hidden'],
                                    ],
                                    'raw_payload' => 'stale BOM typed layout payload must stay hidden',
                                ],
                                '9911' => [
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'selected BOM typed layout title payload must stay hidden'],
                                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                                    ],
                                    'raw_payload' => 'selected BOM typed layout payload must stay hidden',
                                ],
                            ]),
                        ],
                        'raw_payload' => 'outer BOM typed layout wrapper payload must stay hidden',
                    ]],
                    'order_images' => [
                        ['metadata' => ['document_page' => 9911], 'image' => 'bom-typed-order-render'],
                    ],
                    'order_results' => [[
                        'metadata' => ['document_page' => 9911],
                        'order_result' => [
                            'dictionary_output' => $bomJsonEnvelope([
                                '9911' => [
                                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                    'bboxes' => [
                                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'selected BOM typed order row payload must stay hidden'],
                                    ],
                                    'raw_payload' => 'selected BOM typed order payload must stay hidden',
                                ],
                            ]),
                        ],
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
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('# First Converter Bom Typed Layout Heading.', $text);
        $t->contains('Second converter BOM typed layout body.', $text);
        $t->true(strpos($text, '# First Converter Bom Typed Layout Heading.') < strpos($text, 'Second converter BOM typed layout body.'));
        $t->true(!str_contains($text, 'BOM typed layout converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'stale BOM typed layout row payload'));
        $t->true(!str_contains($encoded, 'stale BOM typed layout payload'));
        $t->true(!str_contains($encoded, 'selected BOM typed layout title payload'));
        $t->true(!str_contains($encoded, 'selected BOM typed layout payload'));
        $t->true(!str_contains($encoded, 'outer BOM typed layout wrapper payload'));
        $t->true(!str_contains($encoded, 'selected BOM typed order row payload'));
        $t->true(!str_contains($encoded, 'selected BOM typed order payload'));
    },
];
