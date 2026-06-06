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
    'bbox' => [72.0, 150.0, 312.0, 230.0],
    'row_bboxes_coordinate_space' => 'page_image',
    'columns_coordinate_space' => 'page_image',
    'cells_coordinate_space' => 'page_image',
    'row_bboxes' => [
        ['row_id' => 10, 'bbox' => [72.0, 150.0, 312.0, 182.0]],
        ['row_id' => 11, 'bbox' => [72.0, 190.0, 312.0, 220.0]],
        ['row_id' => 99, 'bbox' => [72.0, 250.0, 312.0, 270.0]],
    ],
    'columns' => [
        ['col_id' => 20, 'bbox' => [72.0, 150.0, 172.0, 230.0]],
        ['col_id' => 21, 'bbox' => [192.0, 150.0, 312.0, 230.0]],
        ['col_id' => 99, 'bbox' => [340.0, 150.0, 360.0, 230.0]],
    ],
    'cells' => [
        ['bbox' => [82.0, 155.0, 302.0, 170.0], 'text' => 'Header'],
        ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images'],
        ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready'],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-flat-alias-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% flat table alias boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Flat alias table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale flat-alias table line should be replaced.', 'bbox' => [72.0, 176.0, 380.0, 196.0]],
                ['text' => 'After flat alias table.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
            ]),
        ],
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
} finally {
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

$metadata = $result['metadata'];
$assignedByText = [];
foreach (($metadata['table_assigned_cells'][0] ?? []) as $cell) {
    $assignedByText[$cell['text']] = $cell;
}
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$renderByText = [];
foreach (($gridReview['render_cells'] ?? []) as $renderCell) {
    $renderByText[$renderCell['text']] = $renderCell;
}

if (($metadata['table_coordinate_space_reviews'][0]['status'] ?? null) !== 'translated_to_table_crop') {
    throw new RuntimeException('Expected flat table alias geometry to be localized from page-image to table-crop coordinates.');
}
if (($assignedByText['Header']['col_ids'] ?? null) !== [20, 21]) {
    throw new RuntimeException('Expected spanning header to preserve upstream column ids from flat columns alias.');
}
if (($renderByText['Header']['colspan'] ?? null) !== 2) {
    throw new RuntimeException('Expected WordPress spanning-grid review to retain header colspan.');
}
if (str_contains($result['text'], 'Stale flat-alias table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}

echo json_encode([
    'scenario' => 'wordpress-table-flat-alias-boundary-currentbase',
    'native_boundary' => 'flat tabled row_bboxes/columns aliases are canonicalized before crop-local assignment and WordPress grid review',
    'source_truth' => [
        'upstream' => 'marker.tables.table crops rendered page images before tabled.assignment.assign_rows_columns; tabled row, column, and SpanTableCell geometry is table-crop local after that boundary',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_status' => $metadata['table_coordinate_space_reviews'][0]['status'] ?? null,
    'source_coordinate_spaces' => $metadata['table_coordinate_space_reviews'][0]['source_coordinate_spaces'] ?? [],
    'assigned_header_row_ids' => $assignedByText['Header']['row_ids'] ?? null,
    'assigned_header_col_ids' => $assignedByText['Header']['col_ids'] ?? null,
    'assigned_header_colspan' => $renderByText['Header']['colspan'] ?? null,
    'active_row_ids' => $gridReview['geometry_boundary_review']['active_row_ids'] ?? [],
    'active_col_ids' => $gridReview['geometry_boundary_review']['active_col_ids'] ?? [],
    'flat_alias_geometry_preserved' => ($assignedByText['Header']['col_ids'] ?? null) === [20, 21]
        && ($renderByText['Header']['colspan'] ?? null) === 2,
    'stale_pdftext_table_line_excluded' => !str_contains($result['text'], 'Stale flat-alias table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
