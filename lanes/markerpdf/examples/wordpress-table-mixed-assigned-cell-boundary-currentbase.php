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
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 32.0]],
        ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 72.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
        ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
    ],
    'cells' => [
        ['bbox' => [10.0, 5.0, 90.0, 22.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0], 'order' => 0],
        ['bbox' => [130.0, 5.0, 230.0, 22.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1], 'order' => 1],
        ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0], 'order' => 2],
        ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1], 'order' => 3],
        ['bbox' => [132.0, 46.0, 232.0, 66.0], 'text' => 'Stale unassigned sidecar', 'row_ids' => [null], 'col_ids' => [1], 'order' => 4],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-mixed-assigned-cell-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% mixed assigned table cell boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Mixed assigned table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale mixed assigned table line should be replaced.', 'bbox' => [72.0, 176.0, 300.0, 196.0]],
                ['text' => 'After mixed assigned table.', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
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
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}

$metadata = $result['metadata'];
$assigned = $metadata['table_assigned_cells'][0] ?? [];
$assignedTexts = array_column($assigned, 'text');
$sourceReview = $metadata['table_assigned_source_boundary_reviews'][0] ?? [];
$rejected = $sourceReview['rejected_cells'][0] ?? [];

if ($assignedTexts !== ['Feature', 'Status', 'Images', 'Ready']) {
    throw new RuntimeException('Expected stale unassigned sidecar cell to be excluded before WordPress table output.');
}
if (($sourceReview['rejected_cell_count'] ?? null) !== 1 || ($rejected['status'] ?? null) !== 'rejected_missing_row_assignment_anchor') {
    throw new RuntimeException('Expected mixed assigned-cell source boundary review for the stale sidecar cell.');
}
if (!str_contains($result['text'], '| Feature | Status |') || !str_contains($result['text'], '| Images  | Ready  |')) {
    throw new RuntimeException('Expected supplied mixed-assignment table Markdown to be inserted.');
}
if (str_contains($result['text'], 'Stale unassigned sidecar') || str_contains($result['text'], 'Stale mixed assigned table line should be replaced.')) {
    throw new RuntimeException('Expected stale assigned sidecar and stale pdftext table line to be excluded.');
}

echo json_encode([
    'scenario' => 'wordpress-table-mixed-assigned-cell-boundary-currentbase',
    'native_boundary' => 'mixed saved tabled SpanTableCell assignments retain valid row_ids/col_ids while rejecting stale unassigned sidecar cells before detector reassignment and Markdown output',
    'source_truth' => [
        'upstream' => 'tabled.extract.extract_tables stores cells after assign_rows_columns() in ExtractPageResult.cells, while rows_cols remains the recognition grid',
        'no_gpu_scope' => 'uses supplied table rows, columns, and cells; does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Mixed Assigned Table Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After mixed assigned table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'assigned_texts' => $assignedTexts,
    'source_boundary_rejected_cell_count' => $sourceReview['rejected_cell_count'] ?? null,
    'source_boundary_rejected_status' => $rejected['status'] ?? null,
    'detector_reassignment_blocked' => ($sourceReview['detector_reassignment_blocked'] ?? null) === true,
    'stale_unassigned_sidecar_excluded' => !str_contains($result['text'], 'Stale unassigned sidecar'),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale mixed assigned table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
