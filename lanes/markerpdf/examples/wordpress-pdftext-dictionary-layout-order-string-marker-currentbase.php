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

$coverPage = $page(200, [
    ['text' => 'Whitespace keyed cover page should not import.', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
]);
$selectedPage = $page(201, [
    ['text' => 'Second whitespace marker column is review metadata.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First whitespace marker column starts the import.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$appendixPage = $page(202, [
    ['text' => 'Whitespace keyed appendix page should not import.', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
]);

$layoutResults = [
    [
        'page' => " 200\t",
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
    ],
    [
        'page' => "\n201 ",
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
    ],
];
$orderResults = [
    [
        'page' => " 200\t",
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
            ['position' => 2, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ],
    ],
    [
        'page' => "\n201 ",
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
    ],
];

$path = sys_get_temp_dir() . '/markerpdf-string-marker-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% whitespace string marker pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage, $appendixPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => " 200\t", 'image' => 'cover-layout-render'],
                ['page' => "\n201 ", 'image' => 'selected-layout-render'],
            ],
            'layout_results' => $layoutResults,
            'order_images' => [
                ['page' => " 200\t", 'image' => 'cover-order-render'],
                ['page' => "\n201 ", 'image' => 'selected-order-render'],
            ],
            'order_results' => $orderResults,
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
echo '<!-- markerpdf-pdftext-dictionary-layout-order-string-marker-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-string-marker-currentbase',
    'source_truth' => 'markerPDF selects pdftext dictionary pages before layout/order assignment; native supplied artifacts with numeric page markers serialized as strings must still align to the selected pdftext page before zip-style ordering',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'string_markers_normalized' => true,
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'ordered_text' => [
        'first_before_second' => strpos($text, 'First whitespace marker column starts the import.') < strpos($text, 'Second whitespace marker column is review metadata.'),
        'cover_excluded' => !str_contains($text, 'Whitespace keyed cover page should not import.'),
        'appendix_excluded' => !str_contains($text, 'Whitespace keyed appendix page should not import.'),
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
