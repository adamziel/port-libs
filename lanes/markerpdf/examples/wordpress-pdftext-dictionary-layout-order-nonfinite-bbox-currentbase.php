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

$pdftextPages = [
    $page(3601, [
        ['text' => 'Right finite geometry column has the supplied bbox.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
        ['text' => 'Left nonfinite geometry row shares the upstream group.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
    ]),
];

$path = sys_get_temp_dir() . '/markerpdf-nonfinite-bbox-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% nonfinite bbox pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        $pdftextPages,
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 0,
            'lowres_images' => [
                ['page' => 3601, 'image' => 'nonfinite-bbox-layout-render'],
            ],
            'layout_results' => [[
                'page' => 3601,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [60.0, 92.0, INF, 150.0], 'raw_payload' => 'nonfinite layout bbox payload must stay hidden'],
                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                ],
            ]],
            'order_images' => [
                ['page' => 3601, 'image' => 'nonfinite-bbox-order-render'],
            ],
            'order_results' => [[
                'page' => 3601,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 1, 'bbox' => [60.0, 96.0, INF, 144.0], 'raw_payload' => 'nonfinite order bbox payload must stay hidden'],
                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                ],
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
if ($encoded === false) {
    throw new RuntimeException('Expected non-finite layout/order geometry to be excluded before JSON metadata serialization.');
}

$text = $result['text'];
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-nonfinite-bbox-currentbase',
    'source_truth' => 'markerPDF assigns supplied layout/order geometry to selected pdftext dictionary pages; native adapters must reject non-finite bbox coordinates before WordPress block conversion and review metadata serialization',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifact_assigned' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'order_artifact_assigned' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'finite_order_row_preserved' => ($result['metadata']['order_plan']['layout_bbox_counts'][0] ?? null) === 1,
    'zero_overlap_block_grouped_with_first_order_position' => strpos($text, 'Left nonfinite geometry row shares the upstream group.') < strpos($text, 'Right finite geometry column has the supplied bbox.'),
    'invalid_title_not_promoted' => !str_contains($text, '# Left Nonfinite Geometry Row Shares The Upstream Group.'),
    'nonfinite_metadata_json_safe' => !str_contains($encoded, 'INF') && !str_contains($encoded, 'nonfinite layout bbox payload') && !str_contains($encoded, 'nonfinite order bbox payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['layout_artifact_assigned']
    || !$flags['order_artifact_assigned']
    || !$flags['finite_order_row_preserved']
    || !$flags['zero_overlap_block_grouped_with_first_order_position']
    || !$flags['invalid_title_not_promoted']
    || !$flags['nonfinite_metadata_json_safe']
) {
    throw new RuntimeException('Expected non-finite supplied geometry boundary flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-nonfinite-bbox-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
