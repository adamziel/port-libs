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

$bboxXxyy = static fn (float $x1, float $y1, float $x2, float $y2): array => [$x1, $x2, $y1, $y2];

$savedTable = static function () use ($bboxXxyy): array {
    return [
        'pnum' => 0,
        'tnum' => 0,
        'coordinate_space' => 'page_image',
        'bbox' => [72.0, 150.0, 312.0, 230.0],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'rows' => [
            ['row_id' => 0, 'bbox' => $bboxXxyy(72.0, 150.0, 312.0, 182.0)],
            ['row_id' => 1, 'bbox' => $bboxXxyy(72.0, 190.0, 312.0, 220.0)],
            ['row_id' => 99, 'bbox' => $bboxXxyy(72.0, 250.0, 312.0, 268.0)],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => $bboxXxyy(72.0, 150.0, 172.0, 230.0)],
            ['col_id' => 1, 'bbox' => $bboxXxyy(192.0, 150.0, 312.0, 230.0)],
            ['col_id' => 99, 'bbox' => $bboxXxyy(342.0, 150.0, 362.0, 230.0)],
        ],
        'cells' => [
            ['bbox' => [82.0, 155.0, 162.0, 170.0], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [202.0, 155.0, 302.0, 170.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [82.0, 195.0, 162.0, 215.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [202.0, 195.0, 302.0, 215.0], 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [82.0, 250.0, 162.0, 268.0], 'text' => 'Stale envelope row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => [360.0, 195.0, 382.0, 215.0], 'text' => 'Stale envelope col', 'row_ids' => [1], 'col_ids' => [99]],
        ],
    ];
};

$decoyTable = $savedTable();
$decoyTable['cells'][0]['text'] = 'Decoy';
$decoyTable['cells'][1]['text'] = 'Wrong';
$decoyTable['cells'][2]['text'] = 'Other';
$decoyTable['cells'][3]['text'] = 'Source';

$pdfPath = sys_get_temp_dir() . '/markerpdf-wordpress-table-saved-result-envelope-' . bin2hex(random_bytes(4)) . '.pdf';
$sourceStem = pathinfo($pdfPath, PATHINFO_FILENAME);
file_put_contents($pdfPath, "%PDF-1.4\n% saved tabled result envelope WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $pdfPath,
        [
            $pdftextPage([
                ['text' => 'Saved tabled result envelope boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale saved-result envelope table line should be replaced.', 'bbox' => [82.0, 176.0, 380.0, 196.0]],
                ['text' => 'After saved tabled result envelope.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
            'recognized_tables' => [
                'decoy-source' => [$decoyTable],
                $sourceStem => [$savedTable()],
            ],
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
$envelopeReview = $metadata['table_result_envelope_review'] ?? [];
$coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
$cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
$assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

if (($envelopeReview['selected_key'] ?? null) !== $sourceStem) {
    throw new RuntimeException('Expected the saved-result envelope to select the current PDF basename key.');
}
if (str_contains($result['text'], 'Decoy') || str_contains($result['text'], 'Stale saved-result envelope table line should be replaced.')) {
    throw new RuntimeException('Expected decoy and stale PDF text table content to be excluded.');
}
if (in_array('Stale envelope row', $assignedTexts, true) || in_array('Stale envelope col', $assignedTexts, true)) {
    throw new RuntimeException('Expected saved-result cells outside the table crop grid to be filtered from assignment.');
}
if (!str_contains($result['text'], '| Feature | Status |') || !str_contains($result['text'], '| Images  | Ready  |')) {
    throw new RuntimeException('Expected selected saved-result table cells to produce a WordPress table.');
}

echo json_encode([
    'scenario' => 'wordpress-table-saved-result-envelope-currentbase',
    'native_boundary' => 'saved tabled-pdf results.json basename-keyed envelope selected before table geometry localization',
    'source_truth' => [
        'upstream' => 'tabled-pdf 0.1.4 extract.py writes results.json as a dictionary keyed by input filenames without extensions; each value is a list of table records with bbox, image_bbox, rows, cols, and cells.',
        'no_gpu_scope' => 'uses supplied native table recognition artifacts and does not run Surya, tabled models, OCR, Python, or external PDF tools',
    ],
    'gutenberg_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h1>Saved Tabled Result Envelope Boundary</h1>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>After saved tabled result envelope.</p>'],
    ],
    'supplied_boundaries' => $metadata['supplied_boundaries'] ?? [],
    'envelope_review' => $envelopeReview,
    'coordinate_review' => $coordinateReview,
    'crop_review' => $cropReview,
    'assigned_table_texts' => $assignedTexts,
    'saved_result_envelope_selected_by_basename' => ($envelopeReview['selected_key'] ?? null) === $sourceStem,
    'decoy_result_excluded' => !str_contains($result['text'], 'Decoy'),
    'offcrop_saved_result_cells_filtered_from_assignment' => !in_array('Stale envelope row', $assignedTexts, true)
        && !in_array('Stale envelope col', $assignedTexts, true)
        && ($cropReview['excluded_cell_count'] ?? null) === 2,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
