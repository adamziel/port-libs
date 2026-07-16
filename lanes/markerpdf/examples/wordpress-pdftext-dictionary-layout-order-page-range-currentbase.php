<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, array $lines): array {
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
                        'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$coverPage = $page(2400, [
    ['text' => 'Page-range metadata cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$selectedPage = $page(2401, [
    ['text' => 'Second page-range metadata column is review material.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First page-range metadata column starts the import.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-page-range-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% page-range pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                [
                    'metadata' => ['page_range' => [1]],
                    'layout_result' => ['page' => 2400],
                    'image' => 'page-range-selected-layout-render',
                ],
            ],
            'layout_results' => [[
                'metadata' => ['page_range' => [1]],
                'layout_result' => [
                    'page' => 2400,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                ],
                'raw_payload' => 'stale typed layout payload must stay hidden',
            ]],
            'order_images' => [
                [
                    'metadata' => ['page_range' => [1]],
                    'order_result' => ['page' => 2400],
                    'image' => 'page-range-selected-order-render',
                ],
            ],
            'order_results' => [[
                'metadata' => ['page_range' => [1]],
                'order_result' => [
                    'page' => 2400,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
                'raw_payload' => 'stale typed order payload must stay hidden',
            ]],
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
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$pageResult = $result['pages'][0] ?? [];
$layout = is_array($pageResult) && is_array($pageResult['layout'] ?? null) ? $pageResult['layout'] : [];
$order = is_array($pageResult) && is_array($pageResult['order'] ?? null) ? $pageResult['order'] : [];

echo '<!-- markerpdf-pdftext-dictionary-layout-order-page-range-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-page-range-currentbase',
    'source_truth' => 'markerPDF calls pdftext.dictionary_output with a selected page_range before layout/order model zipping; native supplied artifacts that carry page_range metadata must align to that selected range before WordPress import',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'page_range_metadata_trusted' => !array_key_exists('page', $layout) && !array_key_exists('page', $order),
    'page_range_not_copied_to_review_metadata' => !array_key_exists('page_range', $layout) && !array_key_exists('page_range', $order),
    'stale_typed_payload_excluded' => !str_contains($encoded, 'stale typed layout payload') && !str_contains($encoded, 'stale typed order payload'),
    'ordered_text' => [
        'first_before_second' => strpos($text, 'First page-range metadata column starts the import.') < strpos($text, 'Second page-range metadata column is review material.'),
        'cover_excluded' => !str_contains($text, 'Page-range metadata cover should not import.'),
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
