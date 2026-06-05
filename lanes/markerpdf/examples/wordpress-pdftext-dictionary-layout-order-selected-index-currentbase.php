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

$coverPage = $page(210, [
    ['text' => 'Selected-index cover page should not import.', 'bbox' => [72.0, 80.0, 300.0, 94.0]],
]);
$firstSelectedPage = $page(211, [
    ['text' => 'Second selected-index page keeps source order.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First selected-index page has no supplied order.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$secondSelectedPage = $page(212, [
    ['text' => 'Second relative-index matched page column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First relative-index matched page column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$appendixPage = $page(213, [
    ['text' => 'Selected-index appendix page should not import.', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
]);

$layout = [
    'selected_page_index' => 1,
    'selected_page_number' => 2,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];
$order = [
    'selected_page_index' => 1,
    'selected_page_number' => 2,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];

$path = sys_get_temp_dir() . '/markerpdf-selected-index-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% selected-index pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $firstSelectedPage, $secondSelectedPage, $appendixPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 2,
            'start_page' => 1,
            'lowres_images' => [
                ['selected_page_index' => 1, 'selected_page_number' => 2, 'image' => 'matched-second-selected-layout-render'],
            ],
            'layout_results' => [$layout],
            'order_images' => [
                ['selected_page_index' => 1, 'selected_page_number' => 2, 'image' => 'matched-second-selected-order-render'],
            ],
            'order_results' => [$order],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

foreach (preg_split('/\R{2,}/', trim($result['text'])) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

$text = $result['text'];
$firstPageOffset = strpos($text, 'Second selected-index page keeps source order.');
$firstPageSecondOffset = strpos($text, 'First selected-index page has no supplied order.');
$secondPageOffset = strpos($text, 'First relative-index matched page column.');
$secondPageSecondOffset = strpos($text, 'Second relative-index matched page column.');

echo '<!-- markerpdf-pdftext-dictionary-layout-order-selected-index-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-selected-index-currentbase',
    'source_truth' => 'markerPDF trims pdftext/PDFium pages before layout/order model assignment; sparse native supplied artifacts with explicit selected_page_index markers must align to the post-trim selected page before zip-style ordering',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'selected_index_artifact_attached_to_second_page' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1
        && ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'ordered_text' => [
        'first_page_kept_source_order' => $firstPageOffset !== false && $firstPageSecondOffset !== false && $firstPageOffset < $firstPageSecondOffset,
        'second_page_reordered' => $secondPageOffset !== false && $secondPageSecondOffset !== false && $secondPageOffset < $secondPageSecondOffset,
        'cover_excluded' => !str_contains($text, 'Selected-index cover page should not import.'),
        'appendix_excluded' => !str_contains($text, 'Selected-index appendix page should not import.'),
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
