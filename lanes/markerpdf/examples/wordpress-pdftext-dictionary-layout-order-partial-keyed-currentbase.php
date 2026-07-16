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

$coverPage = $page(100, [
    ['text' => 'Partial keyed cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$firstSelectedPage = $page(101, [
    ['text' => 'Second partial page keeps source order.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First partial page has no supplied order.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$secondSelectedPage = $page(102, [
    ['text' => 'Second matched sparse page column.', 'bbox' => [330.0, 140.0, 560.0, 156.0]],
    ['text' => 'First matched sparse page column.', 'bbox' => [72.0, 140.0, 280.0, 156.0]],
]);
$appendixPage = $page(103, [
    ['text' => 'Partial keyed appendix should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);

$layout = [
    'page' => 102,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Text', 'bbox' => [60.0, 132.0, 290.0, 168.0]],
        ['label' => 'Text', 'bbox' => [318.0, 132.0, 570.0, 168.0]],
    ],
];
$order = [
    'page' => 102,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [60.0, 132.0, 290.0, 168.0]],
        ['position' => 2, 'bbox' => [318.0, 132.0, 570.0, 168.0]],
    ],
];

$path = sys_get_temp_dir() . '/markerpdf-partial-keyed-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% partial sparse keyed pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $firstSelectedPage, $secondSelectedPage, $appendixPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 2,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 102, 'image' => 'matched-second-selected-layout-render'],
            ],
            'layout_results' => [$layout],
            'order_images' => [
                ['page' => 102, 'image' => 'matched-second-selected-order-render'],
            ],
            'order_results' => [$order],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
if (str_contains($text, 'Partial keyed cover page should not import.') || str_contains($text, 'Partial keyed appendix should not import.')) {
    throw new RuntimeException('Expected skipped cover and appendix pdftext pages to remain outside WordPress paragraphs.');
}
if (($result['metadata']['layout_plan']['assigned_pages'] ?? null) !== 1 || ($result['metadata']['order_plan']['assigned_pages'] ?? null) !== 1) {
    throw new RuntimeException('Expected only the matched sparse keyed layout/order artifact to be counted as assigned.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-partial-keyed-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-partial-keyed-currentbase',
    'source_truth' => 'markerPDF trims pdftext pages before layout/order assignment; partial sparse keyed supplied artifacts must keep selected-page slots but count only actual matched predictions',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifact_count' => $result['metadata']['layout_plan']['layout_result_count'] ?? null,
    'layout_assigned_pages' => $result['metadata']['layout_plan']['assigned_pages'] ?? null,
    'order_artifact_count' => $result['metadata']['order_plan']['order_result_count'] ?? null,
    'order_assigned_pages' => $result['metadata']['order_plan']['assigned_pages'] ?? null,
    'unmatched_page_source_order_preserved' => strpos($text, 'Second partial page keeps source order.') < strpos($text, 'First partial page has no supplied order.'),
    'matched_page_order_applied' => strpos($text, 'First matched sparse page column.') < strpos($text, 'Second matched sparse page column.'),
    'cover_excluded' => !str_contains($text, 'Partial keyed cover page should not import.'),
    'appendix_excluded' => !str_contains($text, 'Partial keyed appendix should not import.'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
