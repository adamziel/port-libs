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
                        'font' => ['name' => 'Times-Roman', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$coverPage = $page(3200, [
    ['text' => 'Page-idx cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$selectedPage = $page(3201, [
    ['text' => 'Second page-idx supplied column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First page-idx supplied column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$appendixPage = $page(3202, [
    ['text' => 'Page-idx appendix should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-page-idx-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% page_idx pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage, $appendixPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page_idx' => 0, 'image' => 'page-idx-cover-layout-render'],
                ['page_idx' => 1, 'image' => 'page-idx-selected-layout-render'],
            ],
            'layout_results' => [
                [
                    'page_idx' => 0,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                    'raw_payload' => 'cover page_idx layout payload must stay hidden',
                ],
                [
                    'page_idx' => 1,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                    'raw_payload' => 'selected page_idx layout payload must stay hidden',
                ],
            ],
            'order_images' => [
                ['page_idx' => 0, 'image' => 'page-idx-cover-order-render'],
                ['page_idx' => 1, 'image' => 'page-idx-selected-order-render'],
            ],
            'order_results' => [
                [
                    'page_idx' => 0,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'cover page_idx order payload must stay hidden',
                ],
                [
                    'page_idx' => 1,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'selected page_idx order payload must stay hidden',
                ],
            ],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-page-idx-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before zipping layout/order results; native supplied adapters may carry zero-based page_idx aliases that must align to selected pdftext pages before WordPress import',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'page_idx_layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'page_idx_order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'page_idx_metadata_matched' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1
        && ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'first_before_second' => strpos($text, 'First page-idx supplied column.') < strpos($text, 'Second page-idx supplied column.'),
    'cover_excluded' => !str_contains($text, 'Page-idx cover page should not import.'),
    'appendix_excluded' => !str_contains($text, 'Page-idx appendix should not import.'),
    'stale_payload_excluded' => !str_contains($encoded, 'page_idx layout payload')
        && !str_contains($encoded, 'page_idx order payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['page_idx_layout_artifacts_trimmed']
    || !$flags['page_idx_order_artifacts_trimmed']
    || !$flags['page_idx_metadata_matched']
    || !$flags['first_before_second']
    || !$flags['cover_excluded']
    || !$flags['appendix_excluded']
    || !$flags['stale_payload_excluded']
) {
    throw new RuntimeException('Expected page_idx layout/order supplied-boundary flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-page-idx-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
