<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$pageKeyBoundaryPage = static function (int $page, array $lines): array {
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

$pageKeyBoundaryOrderPayload = static function (int $position = 1): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => $position, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => $position === 1 ? 2 : 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ],
        'raw_payload' => 'invalid page-key order payload must stay hidden',
    ];
};

$pageKeyBoundaryLayoutPayload = static function (): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
        'raw_payload' => 'invalid page-key layout payload must stay hidden',
    ];
};

return [
    'rejects overflow source-keyed order artifact maps before selected pdftext assignment' => static function (
        TestRunner $t
    ) use ($pageKeyBoundaryPage, $pageKeyBoundaryOrderPayload): void {
        $overflowKey = (string) PHP_INT_MAX . '0';
        $extractor = new PdfTextDocumentExtractor();

        $t->throws(InvalidArgumentException::class, static fn () => $extractor->getOrderedTextBlocks(
            [
                $pageKeyBoundaryPage(0, [
                    ['text' => 'Second overflow sibling column must not import.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                    ['text' => 'First overflow sibling column must not import.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                ]),
            ],
            [
                [
                    'dictionary_output' => [
                        $overflowKey => $pageKeyBoundaryOrderPayload(2),
                        '0' => $pageKeyBoundaryOrderPayload(1),
                    ],
                    'raw_wrapper_payload' => 'overflow order wrapper payload must stay hidden',
                ],
            ],
            maxPages: 1
        ));
    },
    'rejects negative layout and order artifact map keys while zero source keys still import' => static function (
        TestRunner $t
    ) use ($pageKeyBoundaryPage, $pageKeyBoundaryLayoutPayload, $pageKeyBoundaryOrderPayload): void {
        $path = sys_get_temp_dir() . '/markerpdf-layout-order-page-key-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% layout order invalid page-key boundary\n%%EOF");

        $converter = new SuppliedDocumentConverter();
        $pdftextPages = [
            'dictionary_output' => [
                '0' => $pageKeyBoundaryPage(0, [
                    ['text' => 'Second page-key converter body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                    ['text' => 'First page-key converter heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                ]),
            ],
        ];
        $lowresImages = [[
            'dictionary_output' => [
                '-0.0' => ['image' => 'valid-zero-layout-render'],
            ],
        ]];
        $validLayoutResults = [[
            'dictionary_output' => [
                '-0.0' => $pageKeyBoundaryLayoutPayload(),
            ],
        ]];
        $validOrderResults = [[
            'dictionary_output' => [
                '-0.0' => $pageKeyBoundaryOrderPayload(1),
            ],
        ]];
        $negativeLayoutResults = [[
            'dictionary_output' => [
                '-1' => $pageKeyBoundaryLayoutPayload(),
                '0' => $pageKeyBoundaryLayoutPayload(),
            ],
        ]];
        $negativeOrderResults = [[
            'dictionary_output' => [
                '-1.0' => $pageKeyBoundaryOrderPayload(2),
                '0' => $pageKeyBoundaryOrderPayload(1),
            ],
        ]];
        $settings = new MarkerSettings(['EXTRACT_IMAGES' => false]);
        $baseOptions = [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'lowres_images' => $lowresImages,
        ];

        try {
            $t->throws(InvalidArgumentException::class, static fn () => $converter->convert(
                $path,
                $pdftextPages,
                $baseOptions + [
                    'layout_results' => $negativeLayoutResults,
                    'order_images' => $lowresImages,
                    'order_results' => $validOrderResults,
                ],
                $settings
            ));

            $t->throws(InvalidArgumentException::class, static fn () => $converter->convert(
                $path,
                $pdftextPages,
                $baseOptions + [
                    'layout_results' => $validLayoutResults,
                    'order_images' => $lowresImages,
                    'order_results' => $negativeOrderResults,
                ],
                $settings
            ));

            $result = $converter->convert(
                $path,
                $pdftextPages,
                $baseOptions + [
                    'layout_results' => $validLayoutResults,
                    'order_images' => $lowresImages,
                    'order_results' => $validOrderResults,
                ],
                $settings
            );
        } finally {
            unlink($path);
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $text = $result['text'];

        $t->same([0], $result['metadata']['page_range'] ?? null);
        $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries'] ?? null);
        $t->same(1, $result['metadata']['layout_plan']['assigned_pages'] ?? null);
        $t->same(1, $result['metadata']['order_plan']['assigned_pages'] ?? null);
        $t->contains('# First Page-Key Converter Heading.', $text);
        $t->contains('Second page-key converter body.', $text);
        $t->true(strpos($text, '# First Page-Key Converter Heading.') < strpos($text, 'Second page-key converter body.'));
        $t->true(!str_contains($encoded, 'invalid page-key layout payload'));
        $t->true(!str_contains($encoded, 'invalid page-key order payload'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
    },
];
