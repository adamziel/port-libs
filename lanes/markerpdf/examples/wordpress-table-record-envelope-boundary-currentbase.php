<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdftextPage = static function (): array {
    return [
        'page' => 0,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [[
            'lines' => [[
                'bbox' => [72.0, 48.0, 560.0, 68.0],
                'spans' => [[
                    'text' => 'Record envelope table fixture',
                    'bbox' => [72.0, 48.0, 560.0, 68.0],
                    'font' => ['name' => 'Heading-Bold', 'flags' => 0, 'weight' => 700, 'size' => 18],
                ]],
            ], [
                'bbox' => [84.0, 160.0, 290.0, 218.0],
                'spans' => [[
                    'text' => 'Stale pdftext table line',
                    'bbox' => [84.0, 160.0, 290.0, 218.0],
                    'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12],
                ]],
            ], [
                'bbox' => [72.0, 276.0, 560.0, 294.0],
                'spans' => [[
                    'text' => 'After record envelope table.',
                    'bbox' => [72.0, 276.0, 560.0, 294.0],
                    'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12],
                ]],
            ]],
        ]],
    ];
};

$recognizedTable = [
    'coordinate_space' => 'page_image',
    'bbox' => [72.0, 150.0, 312.0, 230.0],
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'rows' => [
        ['row' => ['row_id' => 0, 'bbox' => [72.0, 150.0, 312.0, 182.0]]],
        ['row_record' => ['row_id' => 1, 'bbox' => [72.0, 192.0, 312.0, 230.0]]],
        ['row' => ['row_id' => 9, 'bbox' => [340.0, 150.0, 365.0, 182.0]]],
    ],
    'cols' => [
        ['column' => ['col_id' => 0, 'bbox' => [72.0, 150.0, 172.0, 230.0]]],
        ['col' => ['col_id' => 1, 'bbox' => [192.0, 150.0, 312.0, 230.0]]],
        ['column_record' => ['col_id' => 8, 'bbox' => [340.0, 150.0, 370.0, 230.0]]],
    ],
    'cells' => [
        ['cell' => ['bbox' => [82.0, 155.0, 162.0, 175.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]]],
        ['table_cell' => ['bbox' => [198.0, 155.0, 300.0, 175.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]]],
        ['span_cell' => ['bbox' => [82.0, 198.0, 162.0, 222.0], 'text' => 'Images', 'row_id' => 1, 'col_id' => 0]],
        ['cell_record' => ['bbox' => [198.0, 198.0, 300.0, 222.0], 'text' => 'Ready', 'row_id' => 1, 'col_id' => 1]],
        ['cell' => ['bbox' => [342.0, 155.0, 360.0, 175.0], 'text' => 'Stale row', 'row_id' => 9, 'col_id' => 0]],
        ['cell' => ['bbox' => [198.0, 238.0, 300.0, 252.0], 'text' => 'Stale column', 'row_id' => 1, 'col_id' => 8]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-record-envelope-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table record envelope boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [$pdftextPage()],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 560.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$staleFiltered = !str_contains($result['text'], 'Stale pdftext table line')
    && !str_contains($result['text'], 'Stale row')
    && !str_contains($result['text'], 'Stale column');

if (($coordinateReview['status'] ?? null) !== 'translated_to_table_crop') {
    throw new RuntimeException('Expected record-envelope table records to localize to table-crop coordinates.');
}
if ($assignedTexts !== ['Feature', 'Status', 'Images', 'Ready'] || !$staleFiltered) {
    throw new RuntimeException('Expected record-envelope table assignments to replace stale pdftext and off-band cells.');
}
if (($cropReview['active_cell_count'] ?? null) !== 4 || ($cropReview['excluded_cell_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected record-envelope stale cells to be excluded at the supplied table crop boundary.');
}

echo json_encode([
    'scenario' => 'wordpress-table-record-envelope-boundary-currentbase',
    'native_boundary' => 'row, column, and SpanTableCell record envelopes are unwrapped before supplied table localization and Markdown formatting',
    'source_truth' => [
        'upstream_tabled' => 'tabled-pdf 0.1.4 serializes table rows, columns, and SpanTableCell-style cell records with bbox plus row_ids/col_ids assignments',
        'no_gpu_scope' => 'uses supplied table recognition artifacts only; does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_status' => $coordinateReview['status'] ?? null,
    'translated_counts' => [
        'rows' => $coordinateReview['translated_row_band_count'] ?? null,
        'cols' => $coordinateReview['translated_col_band_count'] ?? null,
        'cells' => $coordinateReview['translated_cell_count'] ?? null,
    ],
    'active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'record_envelope_geometry_unwrapped' => ($coordinateReview['translated_cell_count'] ?? null) === 6,
    'stale_pdftext_and_offband_cells_filtered' => $staleFiltered,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
