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
        ['row_id' => 20, 'bbox' => [0.0, 0.0, 240.0, 24.0]],
        ['row_id' => -5, 'bbox' => [0.0, 32.0, 240.0, 54.0]],
        ['row_id' => 7, 'bbox' => [0.0, 62.0, 240.0, 78.0]],
        ['row_id' => 99, 'bbox' => [0.0, 98.0, 240.0, 126.0]],
    ],
    'cols' => [
        ['col_id' => 100, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
        ['col_id' => -10, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
        ['col_id' => 99, 'bbox' => [264.0, 0.0, 304.0, 80.0]],
    ],
    'cells' => [
        ['bbox' => [10.0, 5.0, 90.0, 20.0], 'text' => 'Feature', 'row_ids' => [20], 'col_ids' => [100]],
        ['bbox' => [130.0, 5.0, 230.0, 20.0], 'text' => 'Status', 'row_ids' => [20], 'col_ids' => [-10]],
        ['bbox' => [10.0, 36.0, 90.0, 50.0], 'text' => 'Images', 'row_ids' => [-5], 'col_ids' => [100]],
        ['bbox' => [130.0, 36.0, 230.0, 50.0], 'text' => 'Ready', 'row_ids' => [-5], 'col_ids' => [-10]],
        ['bbox' => [205.0, 36.0, 235.0, 50.0], 'text' => 'Ghost column', 'row_ids' => [-5], 'col_ids' => [99]],
        ['bbox' => [10.0, 52.0, 90.0, 68.0], 'text' => 'Ghost row', 'row_ids' => [99], 'col_ids' => [100]],
        ['bbox' => [0.0, 64.0, 238.0, 76.0], 'text' => 'Wide Note', 'row_ids' => [7], 'col_ids' => [100, -10, 99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-assigned-band-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% assigned band table geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Assigned band table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale assigned band table line should be replaced.', 'bbox' => [72.0, 176.0, 330.0, 196.0]],
                ['text' => 'After assigned band table.', 'bbox' => [72.0, 260.0, 430.0, 278.0]],
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
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$bandReview = $metadata['table_assigned_band_boundary_reviews'][0] ?? [];
$reviewByText = [];
foreach (($bandReview['cells'] ?? []) as $row) {
    if (is_array($row)) {
        $reviewByText[(string) ($row['text'] ?? '')] = $row;
    }
}

if (in_array('Ghost column', $assignedTexts, true) || in_array('Ghost row', $assignedTexts, true)) {
    throw new RuntimeException('Expected inactive-band assigned cells to be filtered before WordPress table output.');
}
if (str_contains($result['text'], 'Ghost column') || str_contains($result['text'], 'Ghost row')) {
    throw new RuntimeException('Expected inactive-band assigned cell text to stay out of Markdown.');
}
if (str_contains($result['text'], 'Stale assigned band table line should be replaced.')) {
    throw new RuntimeException('Expected supplied table Markdown to replace stale pdftext table line.');
}
if (($reviewByText['Wide Note']['status'] ?? null) !== 'trimmed_to_active_bands') {
    throw new RuntimeException('Expected the spanning cell to be trimmed to active row/column bands.');
}

echo json_encode([
    'scenario' => 'wordpress-table-assigned-band-boundary-currentbase',
    'native_boundary' => 'already assigned tabled SpanTableCell row_ids and col_ids are bounded to active table-crop bands before Markdown and WordPress table review',
    'source_truth' => [
        'upstream' => 'tabled-pdf 0.1.4 SpanTableCell stores row_ids and col_ids after markerPDF crops each table image before assignment',
        'no_gpu_scope' => 'uses supplied rows, columns, and cells; does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Assigned Band Table Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr><tr><td>Wide Note</td><td></td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After assigned band table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'assigned_table_texts' => $assignedTexts,
    'active_row_ids' => $bandReview['active_row_ids'] ?? [],
    'active_col_ids' => $bandReview['active_col_ids'] ?? [],
    'trimmed_cell_count' => $bandReview['trimmed_cell_count'] ?? null,
    'excluded_cell_count' => $bandReview['excluded_cell_count'] ?? null,
    'wide_note_bounded_col_ids' => $reviewByText['Wide Note']['bounded_col_ids'] ?? [],
    'ghost_cells_filtered_from_assignment' => !in_array('Ghost column', $assignedTexts, true)
        && !in_array('Ghost row', $assignedTexts, true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale assigned band table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
