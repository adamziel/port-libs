<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$path = sys_get_temp_dir() . '/markerpdf-table-crop-polygon-boundary-example-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table crop polygon boundary example\n%%EOF");

$recognizedTable = [
    'coordinate_space' => 'page_image',
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'polygon' => [
        ['x' => '72.0', 'y' => '150.0'],
        ['x' => '312.0', 'y' => '150.0'],
        ['x' => '312.0', 'y' => '230.0'],
        ['x' => '72.0', 'y' => '230.0'],
    ],
    'rows' => [
        ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
        ['row_id' => 1, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
        ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
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
        ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale polygon row', 'row_ids' => [99], 'col_ids' => [0]],
        ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale polygon column', 'row_ids' => [1], 'col_ids' => [99]],
    ],
];

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [[
            'page' => 0,
            'bbox' => [0.0, 0.0, 612.0, 792.0],
            'rotation' => 0,
            'blocks' => [[
                'lines' => [
                    [
                        'bbox' => [72.0, 48.0, 480.0, 68.0],
                        'spans' => [[
                            'text' => 'Table Crop Polygon Boundary',
                            'bbox' => [72.0, 48.0, 480.0, 68.0],
                            'font' => ['name' => 'Helvetica-Bold', 'flags' => 0, 'weight' => 700, 'size' => 18],
                        ]],
                    ],
                    [
                        'bbox' => [72.0, 176.0, 380.0, 196.0],
                        'spans' => [[
                            'text' => 'Stale crop-polygon table line should be replaced.',
                            'bbox' => [72.0, 176.0, 380.0, 196.0],
                            'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12],
                        ]],
                    ],
                    [
                        'bbox' => [72.0, 276.0, 480.0, 294.0],
                        'spans' => [[
                            'text' => 'After crop polygon table.',
                            'bbox' => [72.0, 276.0, 480.0, 294.0],
                            'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12],
                        ]],
                    ],
                ],
            ]],
        ]],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [$recognizedTable],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );

    $coordinateReview = $result['metadata']['table_coordinate_space_reviews'][0] ?? [];
    $assignedTexts = array_column($result['metadata']['table_assigned_cells'][0] ?? [], 'text');
    $markdown = $result['text'];

    if (($coordinateReview['status'] ?? null) !== 'translated_to_table_crop') {
        throw new RuntimeException('Expected table crop polygon to localize page-image table geometry.');
    }
    if (str_contains($markdown, 'Stale crop-polygon table line should be replaced.')) {
        throw new RuntimeException('Expected stale pdftext table line to be replaced by supplied table Markdown.');
    }
    if (str_contains($markdown, 'Stale polygon row') || str_contains($markdown, 'Stale polygon column')) {
        throw new RuntimeException('Expected off-crop polygon-localized cells to be excluded before Markdown.');
    }

    echo json_encode([
        'scenario' => 'wordpress-table-crop-polygon-boundary-currentbase',
        'native_boundary' => 'saved table-recognition polygon crop is accepted as the page-image table crop before assigned SpanTableCell geometry reaches WordPress table output',
        'source_truth' => [
            'upstream_marker' => 'sddai/markerPDF marker/tables/table.py crops high-resolution page images before tabled assignment and Markdown formatting',
            'upstream_tabled' => 'tabled-pdf SpanTableCell uses Surya Bbox geometry before assign_rows_columns and formatters consume row_ids/col_ids',
            'no_gpu_scope' => 'uses supplied saved table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
        ],
        'coordinate_review' => [
            'status' => $coordinateReview['status'] ?? null,
            'table_bbox' => $coordinateReview['table_bbox'] ?? null,
            'translation' => $coordinateReview['translation'] ?? null,
            'translated_cell_count' => $coordinateReview['translated_cell_count'] ?? null,
        ],
        'assigned_table_texts' => $assignedTexts,
        'gutenberg_blocks' => [
            ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ],
        'table_crop_polygon_translated' => ($coordinateReview['status'] ?? null) === 'translated_to_table_crop',
        'offcrop_polygon_cells_filtered_from_assignment' => !in_array('Stale polygon row', $assignedTexts, true)
            && !in_array('Stale polygon column', $assignedTexts, true),
        'stale_pdftext_table_line_excluded' => !str_contains($markdown, 'Stale crop-polygon table line should be replaced.'),
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
        'markdown' => $markdown,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    if (is_file($path)) {
        unlink($path);
    }
}
