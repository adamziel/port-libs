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
    'coordinate_space' => 'page_image',
    'bbox' => ['x' => '72', 'y' => '150', 'width' => '240', 'height' => '80'],
    'rows' => [
        ['row_id' => 0, 'bbox' => ['x' => 72.0, 'y' => 150.0, 'width' => 240.0, 'height' => 32.0]],
        ['row_id' => 1, 'bbox' => ['x' => 72.0, 'y' => 190.0, 'width' => 240.0, 'height' => 30.0]],
        ['row_id' => 99, 'bbox' => ['x' => 72.0, 'y' => 250.0, 'width' => 240.0, 'height' => 20.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => ['x' => 72.0, 'y' => 150.0, 'width' => 100.0, 'height' => 80.0]],
        ['col_id' => 1, 'bbox' => ['x' => 192.0, 'y' => 150.0, 'width' => 120.0, 'height' => 80.0]],
        ['col_id' => 99, 'bbox' => ['x' => 340.0, 'y' => 150.0, 'width' => 20.0, 'height' => 80.0]],
    ],
    'cells' => [
        ['bbox' => ['x' => 82.0, 'y' => 155.0, 'width' => 80.0, 'height' => 15.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['bbox' => ['x' => 202.0, 'y' => 155.0, 'width' => 100.0, 'height' => 15.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['bbox' => ['x' => 82.0, 'y' => 195.0, 'width' => 80.0, 'height' => 20.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['bbox' => ['x' => 202.0, 'y' => 195.0, 'width' => 100.0, 'height' => 20.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['bbox' => ['x' => 342.0, 'y' => 195.0, 'width' => 16.0, 'height' => 20.0], 'text' => 'Off-crop extent edge', 'row_ids' => [1], 'col_ids' => [99]],
    ],
];

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-extent-geometry-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table extent geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Extent table geometry review', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale extent table line should be replaced.', 'bbox' => [72.0, 176.0, 360.0, 196.0]],
                ['text' => 'After extent geometry review.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 480.0, 68.0]],
                    ['label' => 'Table', 'bbox' => ['x' => 72.0, 'y' => 150.0, 'width' => 240.0, 'height' => 80.0]],
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
    unlink($pdfPath);
}

$metadata = $result['metadata'];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];

if (str_contains($result['text'], 'Stale extent table line should be replaced.')) {
    throw new RuntimeException('Expected supplied extent table Markdown to replace stale pdftext table lines.');
}
if (in_array('Off-crop extent edge', $assignedTexts, true) || str_contains($result['text'], 'Off-crop extent edge')) {
    throw new RuntimeException('Expected off-crop extent cells to stay out of assigned WordPress table output.');
}

echo json_encode([
    'scenario' => 'wordpress-table-extent-geometry-boundary-currentbase',
    'native_boundary' => 'extent-shaped x/y/width/height table layout and recognition bboxes are converted to endpoint bboxes before crop-local table assignment',
    'source_truth' => [
        'tabled-pdf results.json documents cell bbox as within the table bbox plus row_ids and col_ids for assigned cells',
        'markerPDF get_table_boxes crops rendered page images before tabled recognition, so page-image records must be translated to table-crop coordinates',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Extent Table Geometry Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After extent geometry review.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'table_bbox' => $coordinateReview['table_bbox'] ?? null,
    'translation' => $coordinateReview['translation'] ?? null,
    'translated_cell_count' => $coordinateReview['translated_cell_count'] ?? null,
    'translated_conflict_count' => $coordinateReview['translated_conflict_count'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'source_cell_bbox' => $gridReview['render_cells'][0]['source_cell_bbox'] ?? null,
    'source_coordinate_space' => $gridReview['render_cells'][0]['source_coordinate_space'] ?? null,
    'stale_pdftext_table_line_excluded' => !str_contains($result['text'], 'Stale extent table line should be replaced.'),
    'offcrop_extent_cells_filtered' => !in_array('Off-crop extent edge', $assignedTexts, true),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
