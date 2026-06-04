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

$coverPage = $page(70, [
    ['text' => 'One-based cover page artifact should not import.', 'bbox' => [72.0, 80.0, 340.0, 94.0]],
]);
$selectedPage = $page(71, [
    ['text' => 'Second collision column remains source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First collision column lacks matching order.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-page-index-collision-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% page identity source-index collision pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 1, 'image' => 'one-based-cover-layout-render'],
            ],
            'layout_results' => [[
                'page' => 1,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                    ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                ],
            ]],
            'order_images' => [
                ['page' => 1, 'image' => 'one-based-cover-order-render'],
            ],
            'order_results' => [[
                'page' => 1,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                    ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                ],
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

if (array_key_exists('layout_plan', $result['metadata'] ?? []) || array_key_exists('order_plan', $result['metadata'] ?? [])) {
    throw new RuntimeException('Expected page identity artifacts that only collide with the selected source index to stay excluded.');
}

if (str_contains($result['text'], 'One-based cover page artifact should not import.')) {
    throw new RuntimeException('Expected skipped cover text to remain outside WordPress paragraphs.');
}

$paragraph = trim(str_replace("\n\n", ' ', $result['text']));
echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":71}} -->' . "\n";
echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf-pdftext-dictionary-layout-order-page-index-collision-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-page-index-collision-currentbase',
    'source_truth' => 'markerPDF trims pdftext pages before layout/order assignment; native page identity artifacts must match the selected pdftext page, not only the selected source index',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'selected_page' => 71,
    'layout_artifacts_excluded' => !array_key_exists('layout_plan', $result['metadata'] ?? []),
    'order_artifacts_excluded' => !array_key_exists('order_plan', $result['metadata'] ?? []),
    'cover_excluded' => !str_contains($result['text'], 'One-based cover page artifact should not import.'),
    'source_order_preserved_without_matching_order' => strpos($result['text'], 'Second collision column remains source ordered.') < strpos($result['text'], 'First collision column lacks matching order.'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
