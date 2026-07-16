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

$coverPage = $page(3000, [
    ['text' => 'Pdftext source cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$selectedPage = $page(3001, [
    ['text' => 'Second pdftext source supplied column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First pdftext source supplied column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$appendixPage = $page(3002, [
    ['text' => 'Pdftext source appendix page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-pdftext-source-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% pdftext_source layout order current-base boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage, $appendixPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['pdftext_source' => ['page' => 3000], 'image' => 'pdftext-source-cover-layout-render'],
                ['pdftext_source' => ['page' => 3001], 'image' => 'pdftext-source-selected-layout-render'],
            ],
            'layout_results' => [
                [
                    'pdftext_source' => ['page' => 3000],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                    'raw_payload' => 'cover pdftext_source layout payload must stay hidden',
                ],
                [
                    'pdftext_source' => ['page' => 3001],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                    'raw_payload' => 'selected pdftext_source layout payload must stay hidden',
                ],
            ],
            'order_images' => [
                ['pdftext_source' => ['page' => 3000], 'image' => 'pdftext-source-cover-order-render'],
                ['pdftext_source' => ['page' => 3001], 'image' => 'pdftext-source-selected-order-render'],
            ],
            'order_results' => [
                [
                    'pdftext_source' => ['page' => 3000],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ],
                    'raw_payload' => 'cover pdftext_source order payload must stay hidden',
                ],
                [
                    'pdftext_source' => ['page' => 3001],
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'selected pdftext_source order payload must stay hidden',
                ],
            ],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$firstPosition = strpos($text, 'First pdftext source supplied column.');
$secondPosition = strpos($text, 'Second pdftext source supplied column.');
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

if (($result['metadata']['page_range'] ?? null) !== [1]
    || ($result['metadata']['layout_plan']['layout_result_count'] ?? null) !== 1
    || ($result['metadata']['order_plan']['order_result_count'] ?? null) !== 1
    || $firstPosition === false
    || $secondPosition === false
    || $firstPosition > $secondPosition
    || str_contains($text, 'Pdftext source cover page should not import.')
    || str_contains($text, 'Pdftext source appendix page should not import.')
    || str_contains($encoded, 'cover pdftext_source layout payload')
    || str_contains($encoded, 'selected pdftext_source layout payload')
    || str_contains($encoded, 'cover pdftext_source order payload')
    || str_contains($encoded, 'selected pdftext_source order payload')
) {
    throw new RuntimeException('Expected pdftext_source page metadata to align supplied WordPress layout/order artifacts to the selected pdftext dictionary page.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-pdftext-source-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-pdftext-source-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before supplied layout/order assignment; native adapters may carry selected-page identity in pdftext_source.page metadata',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'selected_page_aligned_by_pdftext_source' => true,
    'ordered_text' => [
        'first_before_second' => $firstPosition < $secondPosition,
        'cover_excluded' => !str_contains($text, 'Pdftext source cover page should not import.'),
        'appendix_excluded' => !str_contains($text, 'Pdftext source appendix page should not import.'),
        'payload_excluded' => !str_contains($encoded, 'cover pdftext_source layout payload')
            && !str_contains($encoded, 'selected pdftext_source layout payload')
            && !str_contains($encoded, 'cover pdftext_source order payload')
            && !str_contains($encoded, 'selected pdftext_source order payload'),
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
