<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$pdftextLayoutOrderDuplicatePageKeyPage = static function (int $page, array $lines): array {
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

$layoutOrderDuplicatePageKeyPayload = static function (int $position = 1): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => $position, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => $position === 1 ? 2 : 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ],
        'raw_payload' => 'duplicate normalized layout-order page-key payload must stay hidden',
    ];
};

return [
    'rejects duplicate normalized source-keyed order artifact maps before selected pdftext assignment' => static function (
        TestRunner $t
    ) use ($pdftextLayoutOrderDuplicatePageKeyPage, $layoutOrderDuplicatePageKeyPayload): void {
        $extractor = new PdfTextDocumentExtractor();

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getOrderedTextBlocks(
            [
                'dictionary_output' => [
                    '1' => $pdftextLayoutOrderDuplicatePageKeyPage(1, [
                        ['text' => 'Duplicate order page-key selected text.', 'bbox' => [72.0, 112.0, 380.0, 128.0]],
                    ]),
                ],
            ],
            [
                [
                    'dictionary_output' => [
                        '01' => $layoutOrderDuplicatePageKeyPayload(1),
                        '+1.0' => $layoutOrderDuplicatePageKeyPayload(2),
                    ],
                    'raw_wrapper_payload' => 'duplicate normalized order wrapper payload must stay hidden',
                ],
            ],
            maxPages: 1
        ));
    },
    'rejects duplicate normalized layout and order artifact maps while unique maps still import' => static function (
        TestRunner $t
    ) use ($pdftextLayoutOrderDuplicatePageKeyPage, $layoutOrderDuplicatePageKeyPayload): void {
        $path = sys_get_temp_dir() . '/markerpdf-layout-order-duplicate-page-key-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% duplicate normalized layout-order page-key boundary\n%%EOF");

        $converter = new SuppliedDocumentConverter();
        $pdftextPages = [
            'dictionary_output' => [
                '+9900.0' => $pdftextLayoutOrderDuplicatePageKeyPage(9900, [
                    ['text' => 'Duplicate page-key converter cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                '+9901.0' => $pdftextLayoutOrderDuplicatePageKeyPage(9901, [
                    ['text' => 'Second duplicate page-key converter body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                    ['text' => 'First duplicate page-key converter heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                ]),
            ],
        ];

        $uniqueLowresImages = [[
            'dictionary_output' => [
                '+9900.0' => ['image' => 'duplicate-page-key-cover-render'],
                '+9901.0' => ['image' => 'duplicate-page-key-selected-render'],
            ],
        ]];
        $uniqueLayoutResults = [[
            'dictionary_output' => [
                '+9900.0' => $layoutOrderDuplicatePageKeyPayload(2),
                '+9901.0' => [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                ],
            ],
        ]];
        $uniqueOrderResults = [[
            'dictionary_output' => [
                '+9900.0' => $layoutOrderDuplicatePageKeyPayload(2),
                '+9901.0' => $layoutOrderDuplicatePageKeyPayload(1),
            ],
        ]];
        $duplicateArtifactMap = [[
            'dictionary_output' => [
                '09901' => $layoutOrderDuplicatePageKeyPayload(1),
                '+9901.0' => $layoutOrderDuplicatePageKeyPayload(2),
            ],
        ]];

        try {
            $baseOptions = [
                'metadata' => ['languages' => ['English']],
                'max_pages' => 1,
                'start_page' => 1,
                'lowres_images' => $uniqueLowresImages,
            ];

            $t->throws(InvalidArgumentException::class, static fn () => $converter->convert(
                $path,
                $pdftextPages,
                $baseOptions + [
                    'layout_results' => $duplicateArtifactMap,
                    'order_images' => $uniqueLowresImages,
                    'order_results' => $uniqueOrderResults,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            ));

            $t->throws(InvalidArgumentException::class, static fn () => $converter->convert(
                $path,
                $pdftextPages,
                $baseOptions + [
                    'layout_results' => $uniqueLayoutResults,
                    'order_images' => $uniqueLowresImages,
                    'order_results' => $duplicateArtifactMap,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            ));

            $result = $converter->convert(
                $path,
                $pdftextPages,
                $baseOptions + [
                    'layout_results' => $uniqueLayoutResults,
                    'order_images' => $uniqueLowresImages,
                    'order_results' => $uniqueOrderResults,
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
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('# First Duplicate Page-Key Converter Heading.', $text);
        $t->contains('Second duplicate page-key converter body.', $text);
        $t->true(strpos($text, '# First Duplicate Page-Key Converter Heading.') < strpos($text, 'Second duplicate page-key converter body.'));
        $t->true(!str_contains($text, 'Duplicate page-key converter cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'duplicate normalized layout-order page-key payload'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
    },
];
