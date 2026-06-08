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

return [
    'rejects direct source-keyed raw pdftext page maps as selected order artifacts' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage): void {
        $rawPdftextArtifactMap = [
            11200 => $pdftextLinesPage(11200, [
                ['text' => 'Raw pdftext artifact cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            11201 => $pdftextLinesPage(11201, [
                ['text' => 'Raw selected order artifact text must stay hidden', 'bbox' => [72.0, 170.0, 520.0, 184.0]],
            ]),
        ];

        $result = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
            [
                $pdftextLinesPage(11200, [
                    ['text' => 'Source raw artifact cover should stay skipped', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                $pdftextLinesPage(11201, [
                    ['text' => 'Second raw artifact column remains first.', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
                    ['text' => 'First raw artifact column remains second.', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
                ]),
            ],
            $rawPdftextArtifactMap,
            orderImages: $rawPdftextArtifactMap,
            maxPages: 1,
            startPage: 1
        );

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($result['pages']));
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

        $t->same([1], $result['page_range']);
        $t->same(11201, $result['pages'][0]['pnum']);
        $t->same([
            'Second raw artifact column remains first.',
            'First raw artifact column remains second.',
        ], array_map(
            static fn (array $block): string => $block['lines'][0]['spans'][0]['text'],
            $result['pages'][0]['blocks']
        ));
        $mergedText = implode("\n", array_map(
            static fn (array $block): string => (string) ($block['text'] ?? ''),
            $blocks
        ));
        $t->contains('Second raw artifact column remains first.', $mergedText);
        $t->contains('First raw artifact column remains second.', $mergedText);
        $t->true(strpos($mergedText, 'Second raw artifact column remains first.') < strpos($mergedText, 'First raw artifact column remains second.'));
        $t->true(!array_key_exists('order', $result['pages'][0]));
        $t->same(0, $result['metadata']['order_plan']['image_count']);
        $t->same(0, $result['metadata']['order_plan']['order_result_count']);
        $t->same(0, $result['metadata']['order_plan']['assigned_pages']);
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Raw pdftext artifact cover should stay skipped'));
        $t->true(!str_contains($encoded, 'Raw selected order artifact text must stay hidden'));
    },
    'rejects raw pdftext page sidecars as layout and order artifacts before WordPress import' => static function (
        TestRunner $t
    ) use ($pdftextLinesPage): void {
        $rawPdftextArtifactMap = [
            11310 => $pdftextLinesPage(11310, [
                ['text' => 'Raw converter artifact cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            11311 => $pdftextLinesPage(11311, [
                ['text' => 'Raw converter artifact model payload must stay hidden.', 'bbox' => [72.0, 170.0, 520.0, 184.0]],
            ]),
        ];

        $path = sys_get_temp_dir() . '/markerpdf-raw-pdftext-artifact-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% raw pdftext artifact layout order boundary\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextLinesPage(11310, [
                        ['text' => 'Source converter raw artifact cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                    ]),
                    $pdftextLinesPage(11311, [
                        ['text' => 'Second converter raw artifact body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                        ['text' => 'First converter raw artifact heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'max_pages' => 1,
                    'start_page' => 1,
                    'lowres_images' => $rawPdftextArtifactMap,
                    'layout_results' => $rawPdftextArtifactMap,
                    'order_images' => $rawPdftextArtifactMap,
                    'order_results' => $rawPdftextArtifactMap,
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
        $t->contains('Second converter raw artifact body.', $text);
        $t->contains('First converter raw artifact heading.', $text);
        $t->true(strpos($text, 'Second converter raw artifact body.') < strpos($text, 'First converter raw artifact heading.'));
        $t->true(!str_contains($text, 'Source converter raw artifact cover should stay skipped.'));
        $t->true(!str_contains($text, '# First Converter Raw Artifact Heading.'));
        $t->true(!str_contains($encoded, '__markerpdf_envelope_page_key_marker'));
        $t->true(!str_contains($encoded, 'Raw converter artifact cover should stay skipped.'));
        $t->true(!str_contains($encoded, 'Raw converter artifact model payload must stay hidden.'));
    },
];
