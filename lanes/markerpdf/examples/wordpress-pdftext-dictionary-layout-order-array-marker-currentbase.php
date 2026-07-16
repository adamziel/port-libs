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
                        'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$coverPage = $page(760, [
    ['text' => 'Array marker cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$selectedPage = $page(761, [
    ['text' => 'Second array marker artifact column is review metadata.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First array marker artifact column starts the import.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$appendixPage = $page(762, [
    ['text' => 'Array marker appendix page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);

$selectedLayout = [
    'page' => [761],
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];
$selectedOrder = [
    'page' => [761],
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];
$ambiguousOrder = [
    'page' => [760, 761],
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];

$path = sys_get_temp_dir() . '/markerpdf-array-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% array pdftext layout order boundary\n%%EOF");

try {
    $converter = new SuppliedDocumentConverter();
    $result = $converter->convert(
        $path,
        [$coverPage, $selectedPage, $appendixPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => [760], 'image' => 'array-cover-layout-render'],
                ['page' => [761], 'image' => 'array-selected-layout-render'],
            ],
            'layout_results' => [
                array_replace($selectedLayout, ['page' => [760], 'bboxes' => [['label' => 'Picture', 'bbox' => [60.0, 92.0, 570.0, 150.0]]]]),
                $selectedLayout,
            ],
            'order_images' => [
                ['page' => [760], 'image' => 'array-cover-order-render'],
                ['page' => [761], 'image' => 'array-selected-order-render'],
            ],
            'order_results' => [
                array_replace($selectedOrder, ['page' => [760], 'bboxes' => array_reverse($selectedOrder['bboxes'])]),
                $selectedOrder,
            ],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );

    $ambiguous = $converter->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'order_images' => [
                ['page' => [760, 761], 'image' => 'ambiguous-array-order-render'],
            ],
            'order_results' => [$ambiguousOrder],
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
$ambiguousText = $ambiguous['text'];
echo '<!-- markerpdf-pdftext-dictionary-layout-order-array-marker-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-array-marker-currentbase',
    'source_truth' => 'markerPDF selects pdftext dictionary pages before layout/order assignment; native supplied artifacts with list-wrapped page markers must align only when the list resolves to the selected pdftext page',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'singleton_array_marker_normalized' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'ambiguous_array_not_positionally_assigned' => !in_array('order', $ambiguous['metadata']['supplied_boundaries'] ?? [], true),
    'ordered_text' => [
        'first_before_second' => strpos($text, 'First array marker artifact column starts the import.') < strpos($text, 'Second array marker artifact column is review metadata.'),
        'ambiguous_source_order_preserved' => strpos($ambiguousText, 'Second array marker artifact column is review metadata.') < strpos($ambiguousText, 'First array marker artifact column starts the import.'),
        'cover_excluded' => !str_contains($text, 'Array marker cover page should not import.'),
        'appendix_excluded' => !str_contains($text, 'Array marker appendix page should not import.'),
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
