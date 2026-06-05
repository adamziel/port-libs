<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

function markerpdf_wordpress_nested_crop_boundary_page(array $lines): array
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

function markerpdf_wordpress_nested_crop_boundary_table(string $nestedKey = 'table_image', string $bboxKey = 'highres_bbox'): array
{
    return [
        'coordinate_space' => 'page_image',
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        $nestedKey => [
            $bboxKey => [72.0, 150.0, 312.0, 230.0],
            'crop_width' => 240,
            'crop_height' => 80,
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
            ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale nested row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale nested column', 'row_ids' => [1], 'col_ids' => [99]],
        ],
    ];
}

$recognizer = new TableRecognizer();
$direct = $recognizer->formatRecognizedTables(
    [markerpdf_wordpress_nested_crop_boundary_table()],
    [[]]
);

$path = sys_get_temp_dir() . '/markerpdf-wordpress-table-nested-crop-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% WordPress nested crop table geometry boundary fixture\n%%EOF");

try {
    $document = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            markerpdf_wordpress_nested_crop_boundary_page([
                ['text' => 'Nested crop table boundary', 'bbox' => [72.0, 48.0, 480.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale nested crop table line should be replaced.', 'bbox' => [82.0, 176.0, 380.0, 196.0]],
                ['text' => 'After nested crop table.', 'bbox' => [72.0, 276.0, 480.0, 294.0]],
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
            'recognized_tables' => [markerpdf_wordpress_nested_crop_boundary_table('crop', 'bbox')],
            'table_text_lines' => [['blocks' => []]],
            'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    if (is_file($path)) {
        unlink($path);
    }
}

$markdown = $document['text'];
$directReview = $direct['coordinate_space_reviews'][0] ?? [];
$conversionReview = $document['metadata']['table_coordinate_space_reviews'][0] ?? [];
$gridReview = $document['metadata']['table_spanning_grid_review'][0] ?? [];

echo json_encode([
    'scenario' => 'wordpress-table-geometry-nested-crop-boundary-currentbase',
    'native_boundary' => 'saved table-recognition sidecars can carry crop geometry in nested table_image/crop records before tabled assignment',
    'direct_nested_crop_source' => $directReview['table_bbox_source'] ?? null,
    'direct_table_crop_size' => $directReview['table_crop_size'] ?? null,
    'direct_translated_cell_count' => $directReview['translated_cell_count'] ?? null,
    'conversion_table_bbox_source' => $conversionReview['table_bbox_source'] ?? null,
    'conversion_table_bbox' => $conversionReview['table_bbox'] ?? null,
    'assigned_texts' => array_column($document['metadata']['table_assigned_cells'][0] ?? [], 'text'),
    'grid_review_target' => $gridReview['geometry_boundary_review']['review_target'] ?? null,
    'excluded_stale_pdftext_table_line' => !str_contains($markdown, 'Stale nested crop table line should be replaced.'),
    'excluded_stale_nested_sidecar_cells' => !str_contains($markdown, 'Stale nested row') && !str_contains($markdown, 'Stale nested column'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
