<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

function markerpdf_example_table_band_id_boundary_fixture(): array
{
    return [
        'rows' => [
            ['row_id' => '0 0 R', 'bbox' => [0.0, 0.0, 240.0, 30.0]],
            ['row_id' => 0, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
            ['row_id' => 1, 'bbox' => [0.0, 80.0, 240.0, 110.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 120.0]],
            ['col_id' => '1 0 R', 'bbox' => [40.0, 0.0, 110.0, 120.0]],
            ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 120.0]],
        ],
        'cells' => [
            ['bbox' => [10.0, 45.0, 90.0, 64.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [130.0, 45.0, 230.0, 64.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [10.0, 85.0, 90.0, 104.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [130.0, 85.0, 230.0, 104.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ],
        'table_bbox' => [0.0, 0.0, 240.0, 120.0],
    ];
}

function markerpdf_example_table_band_id_boundary_page(array $lines): array
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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-band-id-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table band id boundary WordPress fixture\n%%EOF");

try {
    $document = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            markerpdf_example_table_band_id_boundary_page([
                ['text' => 'Band id table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale malformed band table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                ['text' => 'After malformed band table.', 'bbox' => [72.0, 284.0, 430.0, 302.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 270.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 284.0, 430.0, 302.0]],
                ],
            ]],
            'recognized_tables' => [markerpdf_example_table_band_id_boundary_fixture()],
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

$assignedTexts = array_column($document['metadata']['table_assigned_cells'][0] ?? [], 'text');
$bandReview = $document['metadata']['table_assigned_band_boundary_reviews'][0] ?? [];
$gridReview = $document['metadata']['table_spanning_grid_review'][0]['geometry_boundary_review'] ?? [];

$markdownPreserved = str_contains($document['text'], "| Feature | Status |\n|---------|--------|\n| Images  | Ready  |")
    || str_contains($document['text'], "| Feature | Status |\n| --- | --- |\n| Images | Ready |");
$staleLineRemoved = !str_contains($document['text'], 'Stale malformed band table line should be replaced.');
$rowRejected = ($gridReview['row_bands'][0]['status'] ?? null) === 'excluded_invalid_row_id'
    && ($gridReview['row_bands'][0]['raw_id'] ?? null) === '0 0 R';
$columnRejected = ($gridReview['col_bands'][1]['status'] ?? null) === 'excluded_invalid_column_id'
    && ($gridReview['col_bands'][1]['raw_id'] ?? null) === '1 0 R';
$validCellsRetained = $assignedTexts === ['Feature', 'Status', 'Images', 'Ready']
    && ($bandReview['active_cell_count'] ?? null) === 4;

if (!$markdownPreserved || !$staleLineRemoved || !$rowRejected || !$columnRejected || !$validCellsRetained) {
    throw new RuntimeException('Expected malformed table band ids to be excluded without dropping the valid supplied table.');
}

echo json_encode([
    'scenario' => 'wordpress-table-band-id-boundary-currentbase',
    'native_boundary' => 'Malformed supplied row/column band identifiers are excluded before duplicate-id shadowing of valid table geometry.',
    'upstream' => 'tabled SpanTableCell assignments consume integer row_ids and col_ids; serialized PDF object-reference tokens are not valid band identifiers.',
    'markdown_table_preserved' => $markdownPreserved,
    'stale_pdf_table_line_removed' => $staleLineRemoved,
    'malformed_row_band_rejected' => $rowRejected,
    'malformed_column_band_rejected' => $columnRejected,
    'valid_cells_retained' => $validCellsRetained,
    'active_row_ids' => $gridReview['active_row_ids'] ?? [],
    'active_col_ids' => $gridReview['active_col_ids'] ?? [],
    'assigned_texts' => $assignedTexts,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
