<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

function markerpdf_table_bbox_order_example_page(array $lines): array
{
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
}

function markerpdf_table_bbox_order_example_xxyy(float $x1, float $y1, float $x2, float $y2): array
{
    return [$x1, $x2, $y1, $y2];
}

function markerpdf_table_bbox_order_example_table(): array
{
    return [
        'coordinate_space' => 'page_image',
        'table_bbox' => markerpdf_table_bbox_order_example_xxyy(72.0, 150.0, 312.0, 230.0),
        'table_bbox_order' => 'x1_x2_y1_y2',
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'rows' => [
            ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
            ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
            ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 268.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
            ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
            ['col_id' => 99, 'bbox' => [342.0, 150.0, 362.0, 230.0]],
        ],
        'cells' => [
            ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale field-order row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale field-order col', 'row_ids' => [1], 'col_ids' => [99]],
        ],
    ];
}

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-bbox-order-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table bbox order boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            markerpdf_table_bbox_order_example_page([
                ['text' => 'Table Bbox Order Boundary', 'bbox' => [72.0, 48.0, 520.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale table-bbox-order line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                ['text' => 'After table bbox order.', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 520.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 520.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [markerpdf_table_bbox_order_example_table()],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

$metadata = $result['metadata'];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$tableBboxLocalized = ($coordinateReview['table_bbox'] ?? null) === [72.0, 150.0, 312.0, 230.0]
    && ($coordinateReview['status'] ?? null) === 'translated_to_table_crop';
$staleCellsFiltered = !in_array('Stale field-order row', $assignedTexts, true)
    && !in_array('Stale field-order col', $assignedTexts, true);

if (!$tableBboxLocalized) {
    throw new RuntimeException('Expected field-specific table_bbox_order to canonicalize before crop translation.');
}
if (!str_contains($result['text'], '| Feature | Status |') || !str_contains($result['text'], '| Images  | Ready  |')) {
    throw new RuntimeException('Expected supplied table bbox order fixture to render as a Markdown table.');
}
if (!$staleCellsFiltered || str_contains($result['text'], 'Stale table-bbox-order line should be replaced.')) {
    throw new RuntimeException('Expected off-crop stale cells and stale pdftext table line to be filtered.');
}

echo json_encode([
    'scenario' => 'wordpress-table-bbox-order-boundary-currentbase',
    'native_boundary' => 'field-specific table_bbox_order is normalized before page-image-to-table-crop localization',
    'source_truth' => [
        'upstream' => 'markerPDF/table-recognition handoffs crop each table image before table formatting; supplied table crop rectangles may use field-level coordinate order labels distinct from per-cell geometry labels.',
        'no_gpu_scope' => 'uses supplied layout and table-recognition sidecars; does not run Surya, tabled models, OCR, Python, or external PDF tools.',
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'table_bbox_order_localized' => $tableBboxLocalized,
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'coordinate_review_table_bbox' => $coordinateReview['table_bbox'] ?? null,
    'coordinate_review_translation' => $coordinateReview['translation'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'assigned_crop_active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'assigned_crop_excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'table_plan_bboxes' => $metadata['table_plan']['table_bboxes'] ?? [],
    'offcrop_cells_filtered_from_assignment' => $staleCellsFiltered,
    'stale_pdftext_table_line_filtered' => !str_contains($result['text'], 'Stale table-bbox-order line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
