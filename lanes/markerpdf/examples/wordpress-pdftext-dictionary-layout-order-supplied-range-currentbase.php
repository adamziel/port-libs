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
                        'font' => [
                            'name' => 'Times-Roman',
                            'flags' => 0,
                            'weight' => 400,
                            'size' => 11,
                        ],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$path = sys_get_temp_dir() . '/markerpdf-pdftext-layout-order-range-smoke-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% pdftext layout order supplied range smoke\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(6, [
                ['text' => 'Skipped editorial cover.', 'bbox' => [72.0, 80.0, 280.0, 94.0]],
            ]),
            $page(7, [
                ['text' => 'Second column lists media attachments.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First column introduces the import.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
            $page(8, [
                ['text' => 'Skipped appendix notes.', 'bbox' => [72.0, 80.0, 300.0, 94.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => ['cover-render', 'selected-render', 'appendix-render'],
            'layout_results' => [
                [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                ],
                [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                ],
                [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Caption', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Caption', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                ],
            ],
            'order_images' => ['cover-order-render', 'selected-order-render', 'appendix-order-render'],
            'order_results' => [
                [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ['position' => 2, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                    ],
                ],
                [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                ],
                [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ['position' => 2, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                    ],
                ],
            ],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

if (str_contains($converted['text'], 'Skipped editorial cover.')
    || str_contains($converted['text'], 'Skipped appendix notes.')
    || strpos($converted['text'], 'First column introduces the import.') > strpos($converted['text'], 'Second column lists media attachments.')
) {
    throw new RuntimeException('Expected selected-page layout/order artifacts to drive WordPress paragraph order.');
}

echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":7}} -->' . "\n";
echo '<p>' . htmlspecialchars(str_replace("\n\n", ' ', trim($converted['text'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf-pdftext-dictionary-layout-order-supplied-range-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-supplied-range-currentbase',
    'source_truth' => 'marker.convert deletes pages before start_page before rendering layout/order images, so supplied full-document artifacts are sliced to the selected pdftext page range before zip-style assignment',
    'page_range' => $converted['metadata']['page_range'] ?? [],
    'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
    'layout_artifacts_trimmed' => ($converted['metadata']['layout_plan']['image_count'] ?? null) === 1
        && ($converted['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($converted['metadata']['order_plan']['image_count'] ?? null) === 1
        && ($converted['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'cover_page_excluded' => !str_contains($converted['text'], 'Skipped editorial cover.'),
    'appendix_page_excluded' => !str_contains($converted['text'], 'Skipped appendix notes.'),
    'selected_text' => trim($converted['text']),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
