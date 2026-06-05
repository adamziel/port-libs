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
        ['row_id' => 10, 'bbox' => [0.0, 0.0, 300.0, 26.0]],
        ['row_id' => 20, 'bbox' => [0.0, 34.0, 300.0, 60.0]],
        ['row_id' => 30, 'bbox' => [0.0, 70.0, 300.0, 96.0]],
        ['row_id' => 99, 'bbox' => [0.0, 122.0, 300.0, 146.0]],
    ],
    'cols' => [
        ['col_id' => 5, 'bbox' => [0.0, 0.0, 90.0, 110.0]],
        ['col_id' => 7, 'bbox' => [105.0, 0.0, 190.0, 110.0]],
        ['col_id' => 9, 'bbox' => [210.0, 0.0, 300.0, 110.0]],
        ['col_id' => 99, 'bbox' => [330.0, 0.0, 370.0, 110.0]],
    ],
    'cells' => [
        ['bbox' => [5.0, 5.0, 86.0, 22.0], 'text' => 'Inventory summary', 'row_id' => 10, 'col_id' => 5, 'rowspan' => 1, 'colspan' => 3, 'order' => 0],
        ['bbox' => [5.0, 40.0, 86.0, 56.0], 'text' => 'Media group', 'row_id' => 20, 'col_id' => 5, 'rowspan' => 2, 'colspan' => 1, 'order' => 1],
        ['bbox' => [112.0, 40.0, 180.0, 56.0], 'text' => 'Images', 'row_id' => 20, 'col_id' => 7, 'order' => 2],
        ['bbox' => [218.0, 40.0, 292.0, 56.0], 'text' => 'Ready', 'row_id' => 20, 'col_id' => 9, 'order' => 3],
        ['bbox' => [112.0, 76.0, 180.0, 92.0], 'text' => 'Attachments', 'row_id' => 30, 'col_id' => 7, 'order' => 4],
        ['bbox' => [218.0, 76.0, 292.0, 92.0], 'text' => 'Queued', 'row_id' => 30, 'col_id' => 9, 'order' => 5],
        ['bbox' => [334.0, 40.0, 360.0, 56.0], 'text' => 'Offcrop scalar col', 'row_id' => 20, 'col_id' => 99, 'order' => 6],
        ['bbox' => [5.0, 126.0, 86.0, 140.0], 'text' => 'Offcrop scalar row', 'row_id' => 99, 'col_id' => 5, 'order' => 7],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-scalar-span-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% scalar span table geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Scalar span table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale scalar span table line should be replaced.', 'bbox' => [72.0, 176.0, 430.0, 196.0]],
                ['text' => 'After scalar span table.', 'bbox' => [72.0, 300.0, 430.0, 318.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 372.0, 260.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 300.0, 430.0, 318.0]],
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
$assignedByText = [];
foreach ($assigned as $cell) {
    if (is_array($cell)) {
        $assignedByText[(string) ($cell['text'] ?? '')] = $cell;
    }
}
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$renderByText = [];
foreach (($gridReview['render_cells'] ?? []) as $renderCell) {
    if (is_array($renderCell)) {
        $renderByText[(string) ($renderCell['text'] ?? '')] = $renderCell;
    }
}
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$bandReview = $metadata['table_assigned_band_boundary_reviews'][0] ?? [];

if (($assignedByText['Inventory summary']['col_ids'] ?? []) !== [5, 7, 9]) {
    throw new RuntimeException('Expected scalar colspan to expand across current upstream column bands.');
}
if (($assignedByText['Media group']['row_ids'] ?? []) !== [20, 30]) {
    throw new RuntimeException('Expected scalar rowspan to expand across current upstream row bands.');
}
if (($renderByText['Inventory summary']['colspan'] ?? null) !== 3 || ($renderByText['Media group']['rowspan'] ?? null) !== 2) {
    throw new RuntimeException('Expected WordPress grid review to expose expanded scalar row/column spans.');
}
if (isset($assignedByText['Offcrop scalar col']) || isset($assignedByText['Offcrop scalar row'])) {
    throw new RuntimeException('Expected off-crop scalar assigned cells to be filtered before table output.');
}
if (str_contains($result['text'], 'Offcrop scalar') || str_contains($result['text'], 'Stale scalar span table line should be replaced.')) {
    throw new RuntimeException('Expected supplied scalar-span table Markdown to replace stale pdftext and omit off-crop cells.');
}

echo json_encode([
    'scenario' => 'wordpress-table-scalar-span-boundary-currentbase',
    'native_boundary' => 'current marker table cells with scalar row_id/col_id and rowspan/colspan expand to canonical row_ids/col_ids before crop and band filtering',
    'source_truth' => [
        'upstream' => 'current marker table handoff can serialize assigned cells with scalar row_id and col_id plus rowspan and colspan',
        'no_gpu_scope' => 'uses supplied rows, columns, and cells; does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Scalar Span Table Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td colspan="3">Inventory summary</td></tr><tr><td rowspan="2">Media group</td><td>Images</td><td>Ready</td></tr><tr><td>Attachments</td><td>Queued</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After scalar span table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'expanded_inventory_col_ids' => $assignedByText['Inventory summary']['col_ids'] ?? [],
    'expanded_media_row_ids' => $assignedByText['Media group']['row_ids'] ?? [],
    'inventory_colspan' => $renderByText['Inventory summary']['colspan'] ?? null,
    'media_rowspan' => $renderByText['Media group']['rowspan'] ?? null,
    'crop_boundary_excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'band_boundary_active_row_ids' => $bandReview['active_row_ids'] ?? [],
    'band_boundary_active_col_ids' => $bandReview['active_col_ids'] ?? [],
    'offcrop_scalar_cells_filtered' => !isset($assignedByText['Offcrop scalar col']) && !isset($assignedByText['Offcrop scalar row']),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale scalar span table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
