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

$coverPage = $page(700, [
    ['text' => 'Duplicate-keyed cover page should not import.', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
]);
$firstSelectedPage = $page(701, [
    ['text' => 'Second duplicate-keyed first page column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First duplicate-keyed first page column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$secondSelectedPage = $page(701, [
    ['text' => 'Second duplicate-keyed second page keeps source order.', 'bbox' => [330.0, 140.0, 560.0, 156.0]],
    ['text' => 'First duplicate-keyed second page has no reused order.', 'bbox' => [72.0, 140.0, 280.0, 156.0]],
]);

$layout = [
    'page' => 701,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Text', 'bbox' => [60.0, 96.0, 290.0, 168.0]],
        ['label' => 'Text', 'bbox' => [318.0, 96.0, 570.0, 168.0]],
    ],
];
$order = [
    'page' => 701,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 168.0]],
        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 168.0]],
    ],
];

$path = sys_get_temp_dir() . '/markerpdf-duplicate-keyed-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% duplicate keyed pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $firstSelectedPage, $secondSelectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 2,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 701, 'image' => 'single-duplicate-keyed-layout-render'],
            ],
            'layout_results' => [$layout],
            'order_images' => [
                ['page' => 701, 'image' => 'single-duplicate-keyed-order-render'],
            ],
            'order_results' => [$order],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
if (str_contains($text, 'Duplicate-keyed cover page should not import.')) {
    throw new RuntimeException('Expected skipped cover text to remain outside WordPress paragraphs.');
}
if (strpos($text, 'First duplicate-keyed first page column.') > strpos($text, 'Second duplicate-keyed first page column.')) {
    throw new RuntimeException('Expected the first selected page to use the single matching order artifact.');
}
if (strpos($text, 'Second duplicate-keyed second page keeps source order.') > strpos($text, 'First duplicate-keyed second page has no reused order.')) {
    throw new RuntimeException('Expected the second duplicate-keyed selected page to keep source order.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-duplicate-keyed-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-duplicate-keyed-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; each supplied native layout/order artifact maps to one selected Marker page and must not replay across duplicate adapter page markers',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifact_count' => $result['metadata']['layout_plan']['layout_result_count'] ?? null,
    'layout_assigned_pages' => $result['metadata']['layout_plan']['assigned_pages'] ?? null,
    'order_artifact_count' => $result['metadata']['order_plan']['order_result_count'] ?? null,
    'order_assigned_pages' => $result['metadata']['order_plan']['assigned_pages'] ?? null,
    'first_page_order_applied' => strpos($text, 'First duplicate-keyed first page column.') < strpos($text, 'Second duplicate-keyed first page column.'),
    'second_page_source_order_preserved' => strpos($text, 'Second duplicate-keyed second page keeps source order.') < strpos($text, 'First duplicate-keyed second page has no reused order.'),
    'single_artifact_not_replayed' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'cover_excluded' => !str_contains($text, 'Duplicate-keyed cover page should not import.'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
