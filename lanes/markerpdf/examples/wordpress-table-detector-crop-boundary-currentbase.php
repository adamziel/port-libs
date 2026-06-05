<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdftextPage = static function (array $lines): array {
    return [
        'page' => 0,
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
                            'name' => $line['font'] ?? 'Times-Roman',
                            'flags' => 0,
                            'weight' => $line['weight'] ?? 400,
                            'size' => $line['size'] ?? 12,
                        ],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-detector-crop-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table detector crop boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Detector crop table boundary', 'bbox' => [72.0, 48.0, 460.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale detector table line should be replaced.', 'bbox' => [72.0, 176.0, 330.0, 196.0]],
                ['text' => 'After detector crop review.', 'bbox' => [72.0, 276.0, 460.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 460.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 460.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 32.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
                ],
            ]],
            'table_detect_boxes' => true,
            'table_detector_cells' => [[
                ['bbox' => [260.0, 4.0, 290.0, 20.0], 'text' => null],
                ['bbox' => [10.0, 4.0, 90.0, 20.0], 'text' => null],
                ['bbox' => [130.0, 4.0, 230.0, 20.0], 'text' => null],
                ['bbox' => [10.0, 44.0, 90.0, 60.0], 'text' => null],
                ['bbox' => [130.0, 44.0, 230.0, 60.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [['Feature', 'Status', 'Images', 'Ready']],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

$markdown = $result['text'];
$detectorReview = $result['metadata']['table_detector_cell_boundary_reviews'][0] ?? [];
$assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');
$sourceOrderPreserved = $assignedTexts === ['Feature', 'Status', 'Images', 'Ready'];
$offcropExcluded = ($detectorReview['excluded_cell_count'] ?? null) === 1
    && (($detectorReview['cells'][0]['status'] ?? null) === 'excluded_outside_table_image')
    && (($detectorReview['cells'][0]['detector_cell_excluded_before_ocr'] ?? null) === true);
$staleTextExcluded = !str_contains($markdown, 'Stale detector table line should be replaced.');

if (!$sourceOrderPreserved || !$offcropExcluded || !$staleTextExcluded) {
    throw new RuntimeException('Expected off-crop detector cells to be filtered before OCR source-order assignment.');
}

echo json_encode([
    'scenario' => 'wordpress-table-detector-crop-boundary-currentbase',
    'native_boundary' => 'forced-OCR detector cells are bounded to the cropped table image before source-order OCR text assignment',
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Detector Crop Table Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After detector crop review.</p>'],
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'table_needs_ocr' => $result['metadata']['table_needs_ocr'] ?? [],
    'table_cell_counts_after_detector_crop' => $result['metadata']['table_cell_counts'] ?? [],
    'detector_boundary_target' => $detectorReview['review_target'] ?? null,
    'detector_boundary_cell_count' => $detectorReview['cell_count'] ?? null,
    'detector_boundary_active_cell_count' => $detectorReview['active_cell_count'] ?? null,
    'detector_boundary_excluded_cell_count' => $detectorReview['excluded_cell_count'] ?? null,
    'offcrop_detector_cell_excluded_before_ocr' => $offcropExcluded,
    'ocr_source_order_preserved_after_crop_filter' => $sourceOrderPreserved,
    'excluded_stale_pdftext_table_line' => $staleTextExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
