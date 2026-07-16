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

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-layout-geometry-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% table layout geometry boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Layout table boundary review', 'bbox' => [72.0, 48.0, 450.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale reversed layout table text should be replaced.', 'bbox' => [72.0, 176.0, 300.0, 196.0]],
                ['text' => 'After layout geometry review.', 'bbox' => [72.0, 276.0, 450.0, 294.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 450.0, 68.0]],
                    ['label' => 'Table', 'left' => '312', 'top' => '230', 'right' => '72', 'bottom' => '150'],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 450.0, 294.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 32.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
                ],
                'cells' => [
                    ['bbox' => [10.0, 5.0, 90.0, 20.0], 'text' => 'Block'],
                    ['bbox' => [130.0, 5.0, 230.0, 20.0], 'text' => 'Status'],
                    ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images'],
                    ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => 'Ready'],
                ],
            ]],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($pdfPath);
}

$markdown = $result['text'];
$metadata = $result['metadata'];
$tablePlan = $metadata['table_plan'] ?? [];
$gridReview = $metadata['table_spanning_grid_review'][0] ?? [];
$imageSize = $gridReview['geometry_boundary_review']['image_size'] ?? null;
$staleTextExcluded = !str_contains($markdown, 'Stale reversed layout table text should be replaced.');

if (($metadata['inserted_tables'] ?? null) !== 1
    || ($tablePlan['table_bboxes'] ?? null) !== [[72.0, 150.0, 312.0, 230.0]]
    || $imageSize !== ['width' => 240, 'height' => 80]
    || !$staleTextExcluded
    || !str_contains($markdown, '| Block  | Status |')
    || !str_contains($markdown, '| Images | Ready  |')
) {
    throw new RuntimeException('Expected supplied layout table geometry to be canonicalized before WordPress table replacement.');
}

echo json_encode([
    'scenario' => 'wordpress-table-layout-geometry-boundary-currentbase',
    'native_boundary' => 'supplied layout Table bboxes with reversed numeric strings are canonicalized before crop planning, annotation matching, and WordPress table replacement',
    'source_truth' => [
        'upstream' => 'sddai/markerPDF marker/tables/table.py::get_table_boxes',
        'geometry_model' => 'Surya/tabled Bbox-style records are treated as unordered endpoints at this native supplied-boundary handoff.',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Layout Table Boundary Review</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Block</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After layout geometry review.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'table_plan_bboxes' => $tablePlan['table_bboxes'] ?? [],
    'table_crop_size' => $imageSize,
    'inserted_tables' => $metadata['inserted_tables'] ?? null,
    'matched_table_block_indexes' => $metadata['table_section_caption_review'][0]['matched_table_block_indexes'] ?? [],
    'excluded_stale_pdftext_table_line' => $staleTextExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
