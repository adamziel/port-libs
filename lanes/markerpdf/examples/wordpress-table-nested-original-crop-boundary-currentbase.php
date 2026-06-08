<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$normPage = static fn (array $bbox): array => [
    round(((float) $bbox[0] / 612.0) * 1000.0, 6),
    round(((float) $bbox[1] / 792.0) * 1000.0, 6),
    round(((float) $bbox[2] / 612.0) * 1000.0, 6),
    round(((float) $bbox[3] / 792.0) * 1000.0, 6),
];

$roundBbox = static fn (array $bbox): array => array_map(
    static fn (mixed $value): float => round((float) $value, 1),
    $bbox
);

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
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'table_image' => [
        'original_bbox' => $normPage([72.0, 150.0, 312.0, 230.0]),
        'original_coordinate_space' => 'normalized_page_image',
        'crop_width' => 240,
        'crop_height' => 80,
    ],
    'rows' => [
        ['row_id' => 0, 'original_bbox' => $normPage([72.0, 150.0, 312.0, 182.0]), 'original_coordinate_space' => 'normalized_page_image'],
        ['row_id' => 1, 'original_bbox' => $normPage([72.0, 190.0, 312.0, 220.0]), 'original_coordinate_space' => 'normalized_page_image'],
        ['row_id' => 99, 'original_bbox' => $normPage([72.0, 250.0, 312.0, 270.0]), 'original_coordinate_space' => 'normalized_page_image'],
    ],
    'cols' => [
        ['col_id' => 0, 'original_bbox' => $normPage([72.0, 150.0, 172.0, 230.0]), 'original_coordinate_space' => 'normalized_page_image'],
        ['col_id' => 1, 'original_bbox' => $normPage([192.0, 150.0, 312.0, 230.0]), 'original_coordinate_space' => 'normalized_page_image'],
        ['col_id' => 99, 'original_bbox' => $normPage([342.0, 150.0, 362.0, 230.0]), 'original_coordinate_space' => 'normalized_page_image'],
    ],
    'cells' => [
        ['original_bbox' => $normPage([82.0, 155.0, 162.0, 170.0]), 'original_coordinate_space' => 'normalized_page_image', 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
        ['original_bbox' => $normPage([202.0, 155.0, 302.0, 170.0]), 'original_coordinate_space' => 'normalized_page_image', 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
        ['original_bbox' => $normPage([82.0, 195.0, 162.0, 215.0]), 'original_coordinate_space' => 'normalized_page_image', 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
        ['original_bbox' => $normPage([202.0, 195.0, 302.0, 215.0]), 'original_coordinate_space' => 'normalized_page_image', 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
        ['original_bbox' => $normPage([82.0, 250.0, 162.0, 268.0]), 'original_coordinate_space' => 'normalized_page_image', 'text' => 'Stale original row', 'row_ids' => [99], 'col_ids' => [0]],
        ['original_bbox' => $normPage([360.0, 195.0, 382.0, 215.0]), 'original_coordinate_space' => 'normalized_page_image', 'text' => 'Stale original col', 'row_ids' => [1], 'col_ids' => [99]],
    ],
];

$recognizer = new TableRecognizer();
$direct = $recognizer->formatRecognizedTables([$recognizedTable], [['width' => 240, 'height' => 80]]);
$directReview = $direct['coordinate_space_reviews'][0] ?? [];
$directAssignedTexts = array_column($direct['assigned_cells'][0] ?? [], 'text');

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-nested-original-crop-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($pdfPath, "%PDF-1.4\n% nested original crop WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Nested Original Crop Boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale original crop table line should be replaced.', 'bbox' => [82.0, 176.0, 340.0, 196.0]],
                ['text' => 'After nested original crop table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

$originalCropUnwrapped = ($directReview['table_bbox_source'] ?? null) === 'table_image.original_bbox'
    && ($directReview['table_bbox_source_coordinate_space'] ?? null) === 'normalized_page_image'
    && $roundBbox($directReview['table_bbox'] ?? []) === [72.0, 150.0, 312.0, 230.0];
$wordpressTableRendered = str_contains($result['text'], '| Feature | Status |')
    && str_contains($result['text'], '| Images  | Ready  |');
$originalRecordsPreserved = ($coordinateReview['source_record_coordinate_spaces']['cells'] ?? null) === ['normalized_page_image' => 6]
    && ($gridReview['render_cells'][0]['source_coordinate_source'] ?? null) === 'original_bbox'
    && ($gridReview['render_cells'][0]['source_coordinate_space'] ?? null) === 'normalized_page_image';
$staleExcluded = !in_array('Stale original row', $assignedTexts, true)
    && !in_array('Stale original col', $assignedTexts, true)
    && !str_contains($result['text'], 'Stale original crop table line should be replaced.');

if (!$originalCropUnwrapped || !$wordpressTableRendered || !$originalRecordsPreserved || !$staleExcluded) {
    throw new RuntimeException('Expected nested original bbox metadata to localize before WordPress table output.');
}

echo json_encode([
    'scenario' => 'wordpress-table-nested-original-crop-boundary-currentbase',
    'native_boundary' => 'nested table_image.original_bbox metadata is unwrapped, unnormalized from page-image space, and translated into table-crop geometry before table Markdown output',
    'source_truth' => [
        'marker_pdf' => 'marker/tables/table.py crops page images before tabled recognition and later formats assigned rows, columns, and cells as Markdown',
        'tabled' => 'tabled ExtractPageResult carries crop-table geometry; review sidecars may preserve original crop geometry under original_bbox plus original_coordinate_space',
        'no_gpu_scope' => 'uses supplied recognition geometry only; does not run Surya, tabled model inference, OCR, Python, PDFium, PIL, or external PDF tools',
    ],
    'direct_table_bbox_source' => $directReview['table_bbox_source'] ?? null,
    'direct_table_bbox_source_coordinate_space' => $directReview['table_bbox_source_coordinate_space'] ?? null,
    'direct_table_bbox' => $roundBbox($directReview['table_bbox'] ?? []),
    'direct_assigned_table_texts' => $directAssignedTexts,
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'coordinate_review_status' => $coordinateReview['status'] ?? null,
    'coordinate_source_record_spaces_cells' => $coordinateReview['source_record_coordinate_spaces']['cells'] ?? null,
    'render_source_coordinate_source' => $gridReview['render_cells'][0]['source_coordinate_source'] ?? null,
    'render_source_coordinate_space' => $gridReview['render_cells'][0]['source_coordinate_space'] ?? null,
    'assigned_table_texts' => $assignedTexts,
    'nested_original_crop_unwrapped' => $originalCropUnwrapped,
    'wordpress_table_rendered' => $wordpressTableRendered,
    'stale_nested_original_cells_filtered' => $staleExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
