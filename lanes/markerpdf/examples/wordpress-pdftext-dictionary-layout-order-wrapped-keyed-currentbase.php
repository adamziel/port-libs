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

$coverPage = $page(130, [
    ['text' => 'Wrapped metadata cover page should not import.', 'bbox' => [72.0, 80.0, 300.0, 94.0]],
]);
$selectedPage = $page(131, [
    ['text' => 'Second wrapped metadata column is review metadata.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First wrapped metadata column starts the import.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$appendixPage = $page(132, [
    ['text' => 'Wrapped metadata appendix page should not import.', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
]);

$layoutResults = [
    [
        'metadata' => ['page' => 130],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
    ],
    [
        'page_metadata' => ['page' => 131],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
    ],
];
$orderResults = [
    [
        'metadata' => ['page' => 130],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
            ['position' => 2, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ],
    ],
    [
        'page_metadata' => ['page' => 131],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
    ],
];

$path = sys_get_temp_dir() . '/markerpdf-wrapped-keyed-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% wrapped keyed pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage, $appendixPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['metadata' => ['page' => 130], 'image' => 'wrapped-cover-layout-render'],
                ['page_metadata' => ['page' => 131], 'image' => 'wrapped-selected-layout-render'],
            ],
            'layout_results' => $layoutResults,
            'order_images' => [
                ['metadata' => ['page' => 130], 'image' => 'wrapped-cover-order-render'],
                ['page_metadata' => ['page' => 131], 'image' => 'wrapped-selected-order-render'],
            ],
            'order_results' => $orderResults,
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
if (str_contains($text, 'Wrapped metadata cover page should not import.')
    || str_contains($text, 'Wrapped metadata appendix page should not import.')
    || strpos($text, 'First wrapped metadata column starts the import.') > strpos($text, 'Second wrapped metadata column is review metadata.')
) {
    throw new RuntimeException('Expected wrapped page metadata to select the current pdftext page artifact before WordPress paragraph output.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-wrapped-keyed-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-wrapped-keyed-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; supplied adapter metadata wrappers must still align artifacts to selected pdftext page numbers before zip-style ordering',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'ordered_text' => [
        'first_before_second' => strpos($text, 'First wrapped metadata column starts the import.') < strpos($text, 'Second wrapped metadata column is review metadata.'),
        'cover_excluded' => !str_contains($text, 'Wrapped metadata cover page should not import.'),
        'appendix_excluded' => !str_contains($text, 'Wrapped metadata appendix page should not import.'),
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
