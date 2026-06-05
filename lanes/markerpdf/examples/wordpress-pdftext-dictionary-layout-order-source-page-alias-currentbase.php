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

$coverPage = $page(930, [
    ['text' => 'Source-page alias cover page should not import.', 'bbox' => [72.0, 80.0, 340.0, 94.0]],
]);
$selectedPage = $page(931, [
    ['text' => 'Second source-page alias column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First source-page alias column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$appendixPage = $page(932, [
    ['text' => 'Source-page alias appendix should not import.', 'bbox' => [72.0, 80.0, 340.0, 94.0]],
]);

$coverLayout = [
    'source_page' => 930,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];
$selectedLayout = [
    'metadata' => ['document_page' => 931],
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];
$coverOrder = [
    'source_page' => 930,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ['position' => 2, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
    ],
];
$selectedOrder = [
    'metadata' => ['document_page' => 931],
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];

$path = sys_get_temp_dir() . '/markerpdf-layout-order-source-page-alias-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% source-page alias pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage, $appendixPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['source_page' => 930, 'image' => 'source-page-cover-layout-render'],
                ['metadata' => ['document_page' => 931], 'image' => 'document-page-selected-layout-render'],
            ],
            'layout_results' => [$coverLayout, $selectedLayout],
            'order_images' => [
                ['source_page' => 930, 'image' => 'source-page-cover-order-render'],
                ['metadata' => ['document_page' => 931], 'image' => 'document-page-selected-order-render'],
            ],
            'order_results' => [$coverOrder, $selectedOrder],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$firstBeforeSecond = strpos($text, 'First source-page alias column.') < strpos($text, 'Second source-page alias column.');
$artifactsTrimmed = ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1
    && ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1
    && ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1
    && ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1;

if (
    !$firstBeforeSecond
    || !$artifactsTrimmed
    || str_contains($text, 'Source-page alias cover page should not import.')
    || str_contains($text, 'Source-page alias appendix should not import.')
) {
    throw new RuntimeException('Expected source_page/document_page aliases to select the current pdftext page artifacts before WordPress paragraph output.');
}

$paragraph = trim(str_replace("\n\n", ' ', $text));
echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":931}} -->' . "\n";
echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf-pdftext-dictionary-layout-order-source-page-alias-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-source-page-alias-currentbase',
    'source_truth' => 'markerPDF enumerates selected pdftext dictionary pages and zips layout/order predictions with selected pages; native supplied adapters may carry exact page identity as source_page or document_page before WordPress import',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'selected_page' => 931,
    'source_page_alias_cover_excluded' => !str_contains($text, 'Source-page alias cover page should not import.'),
    'document_page_alias_selected' => $artifactsTrimmed,
    'visible_columns_in_reading_order' => $firstBeforeSecond,
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
