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

$path = sys_get_temp_dir() . '/markerpdf-wrapper-list-payload-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% wrapper-list payload pdftext layout order boundary\n%%EOF");

$pages = [
    $page(2400, [
        ['text' => 'Mixed wrapper-list cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
    ]),
    $page(2401, [
        ['text' => 'Second mixed wrapper-list WordPress column remains source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
        ['text' => 'First mixed wrapper-list WordPress column has no trusted order.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
    ]),
];

$wrapperPayload = [
    ['page' => 2401],
    ['raw_payload' => 'mixed wrapper-list payload dictionary must not select layout or order'],
];
$layoutPayload = [
    'metadata' => $wrapperPayload,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];
$orderPayload = [
    'metadata' => $wrapperPayload,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        $pages,
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['metadata' => $wrapperPayload, 'image' => 'mixed-wrapper-list-layout-render'],
            ],
            'layout_results' => [$layoutPayload],
            'order_images' => [
                ['metadata' => $wrapperPayload, 'image' => 'mixed-wrapper-list-order-render'],
            ],
            'order_results' => [$orderPayload],
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
echo '<!-- markerpdf-pdftext-dictionary-layout-order-wrapper-list-payload-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-wrapper-list-payload-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; native supplied-boundary adapters may serialize singleton metadata wrappers, but multi-dictionary wrappers with payload dictionaries are ambiguous and must fail closed',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'mixed_wrapper_list_rejected' => ($result['metadata']['supplied_boundaries'] ?? null) === [],
    'layout_artifacts_rejected' => ($result['metadata']['layout_plan']['image_count'] ?? null) === null
        && ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === null
        && ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === null,
    'order_artifacts_rejected' => ($result['metadata']['order_plan']['image_count'] ?? null) === null
        && ($result['metadata']['order_plan']['order_result_count'] ?? null) === null
        && ($result['metadata']['order_plan']['assigned_pages'] ?? null) === null,
    'source_order_preserved' => strpos($text, 'Second mixed wrapper-list WordPress column remains source ordered.') < strpos($text, 'First mixed wrapper-list WordPress column has no trusted order.'),
    'cover_excluded' => !str_contains($text, 'Mixed wrapper-list cover page should not import.'),
    'payload_excluded' => !str_contains($encoded, 'mixed wrapper-list payload dictionary must not select'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
