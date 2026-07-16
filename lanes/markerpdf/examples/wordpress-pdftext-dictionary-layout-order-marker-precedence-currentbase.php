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

$coverPage = $page(500, [
    ['text' => 'Marker precedence cover page should not import.', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
]);
$selectedPage = $page(501, [
    ['text' => 'Second exact page marker column is review metadata.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First exact page marker column starts the import.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$staleOneBasedLayout = [
    'page_number' => 502,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];
$exactLayout = [
    'page' => 501,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];
$staleOneBasedOrder = [
    'page_number' => 502,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ['position' => 2, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
    ],
];
$exactOrder = [
    'page' => 501,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];

$path = sys_get_temp_dir() . '/markerpdf-layout-order-marker-precedence-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% marker precedence pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page_number' => 502, 'image' => 'one-based-collision-layout-render'],
                ['page' => 501, 'image' => 'exact-page-layout-render'],
            ],
            'layout_results' => [$staleOneBasedLayout, $exactLayout],
            'order_images' => [
                ['page_number' => 502, 'image' => 'one-based-collision-order-render'],
                ['page' => 501, 'image' => 'exact-page-order-render'],
            ],
            'order_results' => [$staleOneBasedOrder, $exactOrder],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
if (strpos($text, 'First exact page marker column starts the import.') > strpos($text, 'Second exact page marker column is review metadata.')) {
    throw new RuntimeException('Expected exact page marker ordering to win over the stale one-based collision.');
}
if (str_contains($text, 'Marker precedence cover page should not import.')) {
    throw new RuntimeException('Expected skipped cover page text to remain outside WordPress paragraphs.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-marker-precedence-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-marker-precedence-currentbase',
    'source_truth' => 'markerPDF selects pdftext dictionary pages before layout/order assignment; native supplied artifacts with exact selected page markers must win over weaker one-based page_number collisions before zip-style ordering',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'exact_page_marker_wins' => strpos($text, 'First exact page marker column starts the import.') < strpos($text, 'Second exact page marker column is review metadata.'),
    'cover_excluded' => !str_contains($text, 'Marker precedence cover page should not import.'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
