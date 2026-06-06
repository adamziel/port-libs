<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\LayoutAnnotator;
use PortLibs\MarkerPDF\LayoutOrderer;
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
    $page(4800, [
        ['text' => 'Zero image-bbox WordPress cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
    ]),
    $page(4801, [
        ['text' => 'Second zero image-bbox WordPress body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
        ['text' => 'First zero image-bbox WordPress heading remains text.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
    ]),
];

$layoutResult = [
    'page' => 4801,
    'image_bbox' => [0.0, 0.0, 612.0, 0.0],
    'bboxes' => [
        ['label' => 'Title', 'bbox' => [0.098, 0.116, 0.474, 0.189], 'raw_payload' => 'normalized layout image-bbox row must stay hidden'],
        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];
$orderResult = [
    'page' => 4801,
    'image_bbox' => [0.0, 0.0, 0.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [0.519, 0.121, 0.932, 0.182], 'raw_payload' => 'normalized order image-bbox row must stay hidden'],
        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
    ],
];

$layoutPreview = (new LayoutAnnotator())->runWithSuppliedLayouts(
    [['page' => 4801, 'image' => 'zero-image-bbox-layout-render']],
    [$pdftextPages[1]],
    [$layoutResult],
    1.0,
    [1]
);
$orderPreview = (new LayoutOrderer())->runWithSuppliedOrder(
    [['page' => 4801, 'image' => 'zero-image-bbox-order-render']],
    [$pdftextPages[1]],
    [$orderResult],
    1.0,
    [1]
);
$layout = $layoutPreview['pages'][0]['layout'] ?? [];
$order = $orderPreview['pages'][0]['order'] ?? [];

$path = sys_get_temp_dir() . '/markerpdf-zero-image-bbox-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% zero image bbox supplied layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        $pdftextPages,
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 4801, 'image' => 'zero-image-bbox-layout-render'],
            ],
            'layout_results' => [$layoutResult],
            'order_images' => [
                ['page' => 4801, 'image' => 'zero-image-bbox-order-render'],
            ],
            'order_results' => [$orderResult],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
if ($encoded === false) {
    throw new RuntimeException('Expected zero-area image_bbox boundary metadata to remain JSON serializable.');
}

$text = $result['text'];
$layoutBboxes = is_array($layout) ? ($layout['bboxes'] ?? []) : [];
$orderBboxes = is_array($order) ? ($order['bboxes'] ?? []) : [];
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-image-bbox-currentbase',
    'source_truth' => 'upstream markerPDF maps normalized supplied layout/order bboxes through image_bbox; native supplied-boundary imports must require a positive image extent before trusting normalized geometry',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifact_assigned' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'order_artifact_assigned' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'layout_image_bbox_dropped' => is_array($layout) && !array_key_exists('image_bbox', $layout),
    'order_image_bbox_dropped' => is_array($order) && !array_key_exists('image_bbox', $order),
    'normalized_layout_row_skipped' => count($layoutBboxes) === 2 && !in_array('Title', array_column($layoutBboxes, 'label'), true),
    'normalized_order_row_skipped' => $orderBboxes === [
        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
    ],
    'first_before_second' => strpos($text, 'First zero image-bbox WordPress heading remains text.') < strpos($text, 'Second zero image-bbox WordPress body.'),
    'invalid_title_not_promoted' => !str_contains($text, '# First Zero Image-Bbox WordPress Heading Remains Text.'),
    'cover_excluded' => !str_contains($text, 'Zero image-bbox WordPress cover should stay skipped.'),
    'payload_metadata_safe' => !str_contains($encoded, 'normalized layout image-bbox row must stay hidden')
        && !str_contains($encoded, 'normalized order image-bbox row must stay hidden'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['layout_artifact_assigned']
    || !$flags['order_artifact_assigned']
    || !$flags['layout_image_bbox_dropped']
    || !$flags['order_image_bbox_dropped']
    || !$flags['normalized_layout_row_skipped']
    || !$flags['normalized_order_row_skipped']
    || !$flags['first_before_second']
    || !$flags['invalid_title_not_promoted']
    || !$flags['cover_excluded']
    || !$flags['payload_metadata_safe']
) {
    throw new RuntimeException('Expected zero-area image_bbox supplied-boundary flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-image-bbox-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
