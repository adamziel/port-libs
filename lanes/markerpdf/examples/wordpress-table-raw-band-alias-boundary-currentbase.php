<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (array $lines): array {
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

$orderedBand = static fn (float $x1, float $y1, float $x2, float $y2): array => [$x1, $x2, $y1, $y2];

$recognizedTable = [
    'bbox' => [72.0, 150.0, 312.0, 230.0],
    'row_bboxes_coordinate_space' => 'page_image',
    'columns_coordinate_space' => 'page_image',
    'cells_coordinate_space' => 'page_image',
    'row_bboxes_bbox_order' => 'x1_x2_y1_y2',
    'columns_bbox_order' => 'x1_x2_y1_y2',
    'row_bboxes' => [
        $orderedBand(72.0, 150.0, 312.0, 182.0),
        $orderedBand(72.0, 190.0, 312.0, 220.0),
        $orderedBand(72.0, 250.0, 312.0, 268.0),
    ],
    'columns' => [
        $orderedBand(72.0, 150.0, 172.0, 230.0),
        $orderedBand(192.0, 150.0, 312.0, 230.0),
        $orderedBand(342.0, 150.0, 362.0, 230.0),
    ],
    'cells' => [
        ['bbox' => [82.0, 155.0, 302.0, 170.0], 'text' => 'Header', 'row_ids' => [0], 'col_ids' => [0, 1]],
        ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale raw row', 'row_ids' => [2], 'col_ids' => [0]],
        ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale raw column', 'row_ids' => [1], 'col_ids' => [2]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-raw-band-alias-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% raw band alias table WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $page([
                ['text' => 'Raw band alias table boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale raw-band table line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                ['text' => 'After raw band alias table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
            ]),
        ],
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
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$rowBands = $gridReview['geometry_boundary_review']['row_bands'] ?? [];
$colBands = $gridReview['geometry_boundary_review']['col_bands'] ?? [];

$rawBandsLocalized = ($coordinateReview['status'] ?? null) === 'translated_to_table_crop'
    && ($rowBands[0]['source_coordinate_source'] ?? null) === 'bbox_array_x1_x2_y1_y2_order'
    && ($colBands[1]['source_coordinate_source'] ?? null) === 'bbox_array_x1_x2_y1_y2_order'
    && ($rowBands[0]['bounded_bbox'] ?? null) === [0.0, 0.0, 240.0, 32.0]
    && ($colBands[1]['bounded_bbox'] ?? null) === [120.0, 0.0, 240.0, 80.0];
$staleCellsFiltered = !in_array('Stale raw row', $assignedTexts, true)
    && !in_array('Stale raw column', $assignedTexts, true)
    && !str_contains($result['text'], 'Stale raw row')
    && !str_contains($result['text'], 'Stale raw column');
$staleLineRemoved = !str_contains($result['text'], 'Stale raw-band table line should be replaced.');

if (!$rawBandsLocalized || !$staleCellsFiltered || !$staleLineRemoved) {
    throw new RuntimeException('Expected raw row/column band aliases to localize before WordPress table insertion.');
}

echo json_encode([
    'scenario' => 'wordpress-table-raw-band-alias-boundary-currentbase',
    'native_boundary' => 'Supplied raw row_bboxes/columns arrays with explicit coordinate order are localized before table assignment and WordPress table output',
    'source_truth' => [
        'upstream' => 'marker/tables/table.py crops table images before handing row/column/cell geometry to tabled assignment and formatting',
        'tabled_boundary' => 'saved table sidecars may preserve row_bboxes and columns as direct four-value arrays; the native adapter keeps that supplied-boundary geometry crop-local',
        'no_gpu_scope' => 'uses supplied table recognition rows/cells and does not run Surya, tabled models, OCR, Python, PDFium, PIL, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Raw Band Alias Table Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td colspan="2">Header</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After raw band alias table.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'translated_row_band_count' => $coordinateReview['translated_row_band_count'] ?? null,
    'translated_col_band_count' => $coordinateReview['translated_col_band_count'] ?? null,
    'raw_row_band_source_coordinate_source' => $rowBands[0]['source_coordinate_source'] ?? null,
    'raw_column_band_source_coordinate_source' => $colBands[1]['source_coordinate_source'] ?? null,
    'raw_row_band_bbox' => $rowBands[0]['bounded_bbox'] ?? null,
    'raw_column_band_bbox' => $colBands[1]['bounded_bbox'] ?? null,
    'assigned_crop_active_cell_count' => $cropReview['active_cell_count'] ?? null,
    'assigned_crop_excluded_cell_count' => $cropReview['excluded_cell_count'] ?? null,
    'active_row_ids' => $gridReview['geometry_boundary_review']['active_row_ids'] ?? [],
    'active_col_ids' => $gridReview['geometry_boundary_review']['active_col_ids'] ?? [],
    'assigned_table_texts' => $assignedTexts,
    'raw_band_aliases_localized' => $rawBandsLocalized,
    'offcrop_raw_band_cells_filtered' => $staleCellsFiltered,
    'excluded_stale_pdftext_table_line' => $staleLineRemoved,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
