<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, array $lines): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
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

$coverPage = $page(5810, [
    ['text' => 'Envelope key alias cover page should stay skipped.', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
]);
$selectedPage = $page(5811, [
    ['text' => 'Second envelope key alias paragraph stays source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First envelope key alias paragraph has no trusted layout.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-layout-order-envelope-key-alias-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% envelope key alias pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [[
                'pages' => [
                    '5812' => ['image' => 'appendix-layout-render'],
                ],
            ]],
            'layout_results' => [[
                'pages' => [
                    '5812' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                    ],
                ],
                'raw_payload' => 'appendix layout payload must stay hidden',
            ]],
            'order_images' => [[
                'dictionary_output' => [
                    '5812' => ['image' => 'appendix-order-render'],
                ],
            ]],
            'order_results' => [[
                'dictionary_output' => [
                    '5812' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                    ],
                ],
                'raw_payload' => 'appendix order payload must stay hidden',
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
if (strpos($text, 'Second envelope key alias paragraph stays source ordered.') > strpos($text, 'First envelope key alias paragraph has no trusted layout.')) {
    throw new RuntimeException('Expected source order to remain unchanged when only a selected+1 source key is supplied.');
}
if (($result['metadata']['supplied_boundaries'] ?? null) !== []) {
    throw new RuntimeException('Expected selected+1 source-keyed layout/order artifacts to be excluded from supplied boundaries.');
}
if (array_key_exists('layout_plan', $result['metadata']) || array_key_exists('order_plan', $result['metadata'])) {
    throw new RuntimeException('Expected no layout/order plan when the only artifacts are source-key mismatches.');
}
if (str_contains($text, '# First Envelope Key Alias Paragraph Has No Trusted Layout.')) {
    throw new RuntimeException('Expected source-key-mismatched layout Title rows not to promote WordPress headings.');
}
if (str_contains($text, 'Envelope key alias cover page should stay skipped.')) {
    throw new RuntimeException('Expected skipped cover page text to remain outside WordPress paragraphs.');
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-envelope-key-alias-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-envelope-key-alias-currentbase',
    'source_truth' => 'markerPDF slices selected pdftext dictionary pages before layout/order assignment; source-keyed PHP adapter maps are exact source-page identities, while explicit page_number metadata owns one-based aliases',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'appendix_keyed_layout_excluded' => !array_key_exists('layout_plan', $result['metadata']),
    'appendix_keyed_order_excluded' => !array_key_exists('order_plan', $result['metadata']),
    'source_order_preserved_without_trusted_order' => strpos($text, 'Second envelope key alias paragraph stays source ordered.') < strpos($text, 'First envelope key alias paragraph has no trusted layout.'),
    'no_heading_promotion' => !str_contains($text, '# First Envelope Key Alias Paragraph Has No Trusted Layout.'),
    'cover_excluded' => !str_contains($text, 'Envelope key alias cover page should stay skipped.'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
