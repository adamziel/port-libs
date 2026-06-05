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

$recognizedTable = [
    'rows' => [
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
        ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
        ['row_id' => 99, 'bbox' => [0.0, 96.0, 240.0, 116.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
        ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
        ['col_id' => 99, 'bbox' => [260.0, 0.0, 300.0, 80.0]],
    ],
    'cells' => [
        ['bbox' => [10.0, 5.0, 90.0, 20.0], 'text' => 'Header', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => [130.0, 5.0, 230.0, 20.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => [130.0, 45.0, 250.0, 65.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => [250.0, 45.0, 280.0, 65.0], 'text' => 'Offcrop assigned', 'row_ids' => [1], 'col_ids' => [99]],
        ['bbox' => [10.0, 92.0, 90.0, 112.0], 'text' => 'Offcrop row assigned', 'row_ids' => [99], 'col_ids' => [0]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-assigned-crop-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% assigned crop table geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Assigned crop table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale assigned table line should be replaced.', 'bbox' => [72.0, 176.0, 300.0, 196.0]],
                ['text' => 'After assigned crop table.', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
                ],
            ]],
            'recognized_tables' => [$recognizedTable],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($pdfPath);
}

$metadata = $result['metadata'];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$geometryBoundary = $gridReview['geometry_boundary_review'] ?? [];
$cellBoundary = $gridReview['cell_geometry_boundary_review'] ?? [];
$assignedCropBoundary = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$assignedCropRowsByText = [];
foreach (($assignedCropBoundary['cells'] ?? []) as $row) {
    if (is_array($row)) {
        $assignedCropRowsByText[(string) ($row['text'] ?? '')] = $row;
    }
}

if (in_array('Offcrop assigned', $assignedTexts, true) || in_array('Offcrop row assigned', $assignedTexts, true)) {
    throw new RuntimeException('Expected off-crop already assigned supplied cells to be filtered before table output.');
}
if (str_contains($result['text'], 'Offcrop assigned') || str_contains($result['text'], 'Offcrop row assigned')) {
    throw new RuntimeException('Expected off-crop already assigned supplied cell text to stay out of WordPress Markdown.');
}
if (str_contains($result['text'], 'Stale assigned table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-assigned-crop-boundary-currentbase',
    'native_boundary' => 'already assigned supplied table cells are bounded to the cropped table image before Markdown and WordPress table review',
    'source_truth' => [
        'upstream' => 'tabled.inference.recognition crops page images for each table and tabled.assignment/markdown consume SpanTableCell row_ids and col_ids in that table-crop coordinate space',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Assigned Crop Table Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Header</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After assigned crop table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'table_crop_size' => $geometryBoundary['image_size'] ?? null,
    'active_row_band_count' => $geometryBoundary['active_row_band_count'] ?? null,
    'active_col_band_count' => $geometryBoundary['active_col_band_count'] ?? null,
    'excluded_band_count' => $geometryBoundary['excluded_band_count'] ?? null,
    'active_cell_count' => $cellBoundary['active_cell_count'] ?? null,
    'clipped_cell_count' => $cellBoundary['clipped_cell_count'] ?? null,
    'excluded_cell_count_after_filter' => $cellBoundary['excluded_cell_count'] ?? null,
    'assigned_crop_review_target' => $assignedCropBoundary['review_target'] ?? null,
    'assigned_crop_cell_count' => $assignedCropBoundary['cell_count'] ?? null,
    'assigned_crop_excluded_cell_count' => $assignedCropBoundary['excluded_cell_count'] ?? null,
    'offcrop_assigned_crop_status' => $assignedCropRowsByText['Offcrop assigned']['status'] ?? null,
    'offcrop_row_crop_status' => $assignedCropRowsByText['Offcrop row assigned']['status'] ?? null,
    'offcrop_assignment_excluded_before_markdown' => ($assignedCropRowsByText['Offcrop assigned']['assignment_excluded_before_markdown'] ?? null) === true
        && ($assignedCropRowsByText['Offcrop row assigned']['assignment_excluded_before_markdown'] ?? null) === true,
    'assigned_table_texts' => $assignedTexts,
    'offcrop_assigned_cells_filtered_from_assignment' => !in_array('Offcrop assigned', $assignedTexts, true)
        && !in_array('Offcrop row assigned', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale assigned table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
