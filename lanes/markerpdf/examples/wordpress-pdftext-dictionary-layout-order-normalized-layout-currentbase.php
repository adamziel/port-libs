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
                        'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$path = sys_get_temp_dir() . '/markerpdf-normalized-layout-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% normalized layout pdftext boundary current-base smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(1210, [
                ['text' => 'Normalized layout cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(1211, [
                ['text' => 'Normalized layout title', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                ['text' => 'Normalized layout body remains paragraph.', 'bbox' => [72.0, 112.0, 480.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 1211, 'image' => 'normalized-layout-render'],
            ],
            'layout_results' => [[
                'page' => 1211,
                'image_bbox' => [0.0, 0.0, 1224.0, 1584.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [0.098, 0.055, 0.588, 0.11]],
                    ['label' => 'Text', 'bbox' => [0.098, 0.125, 0.785, 0.19]],
                ],
                'raw_payload' => 'normalized layout payload must stay hidden',
            ]],
            'order_images' => [
                ['page' => 1211, 'image' => 'normalized-layout-order-render'],
            ],
            'order_results' => [[
                'page' => 1211,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 1, 'bbox' => [60.0, 42.0, 370.0, 76.0]],
                    ['position' => 2, 'bbox' => [60.0, 100.0, 490.0, 140.0]],
                ],
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$headingPromoted = str_contains($text, '# Normalized Layout Title');
$bodyPreserved = str_contains($text, 'Normalized layout body remains paragraph.');
$coverExcluded = !str_contains($text, 'Normalized layout cover should not import.');
$payloadExcluded = !str_contains($encoded, 'normalized layout payload must stay hidden');

if (
    !$headingPromoted
    || !$bodyPreserved
    || !$coverExcluded
    || !$payloadExcluded
    || strpos($text, '# Normalized Layout Title') > strpos($text, 'Normalized layout body remains paragraph.')
) {
    throw new RuntimeException('Expected normalized layout bboxes to assign WordPress heading/text blocks before final Markdown output.');
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $block) {
    $block = trim($block);
    if ($block === '') {
        continue;
    }

    if (str_starts_with($block, '# ')) {
        echo "<!-- wp:heading {\"level\":1} -->\n";
        echo '<h1>' . htmlspecialchars(substr($block, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h1>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-normalized-layout-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-normalized-layout-currentbase',
    'source_truth' => 'markerPDF builds selected pdftext.dictionary_output pages before layout/order; no-GPU supplied layout bboxes may be normalized against the rendered layout image before WordPress block typing',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'selected_page' => 1211,
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'normalized_layout_bboxes_scaled_for_block_types' => $headingPromoted,
    'body_preserved_as_paragraph' => $bodyPreserved,
    'cover_excluded' => $coverExcluded,
    'payload_excluded' => $payloadExcluded,
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
