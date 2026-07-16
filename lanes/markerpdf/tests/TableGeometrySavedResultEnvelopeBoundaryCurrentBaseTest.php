<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

function markerpdf_saved_result_envelope_boundary_page(array $lines): array
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

function markerpdf_saved_result_envelope_boundary_xxyy(float $x1, float $y1, float $x2, float $y2): array
{
    return [$x1, $x2, $y1, $y2];
}

function markerpdf_saved_result_envelope_boundary_table(): array
{
    return [
        'pnum' => 0,
        'tnum' => 0,
        'coordinate_space' => 'page_image',
        'bbox' => [72.0, 150.0, 312.0, 230.0],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'rows' => [
            ['row_id' => 0, 'bbox' => markerpdf_saved_result_envelope_boundary_xxyy(72.0, 150.0, 312.0, 182.0)],
            ['row_id' => 1, 'bbox' => markerpdf_saved_result_envelope_boundary_xxyy(72.0, 190.0, 312.0, 220.0)],
            ['row_id' => 99, 'bbox' => markerpdf_saved_result_envelope_boundary_xxyy(72.0, 250.0, 312.0, 268.0)],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => markerpdf_saved_result_envelope_boundary_xxyy(72.0, 150.0, 172.0, 230.0)],
            ['col_id' => 1, 'bbox' => markerpdf_saved_result_envelope_boundary_xxyy(192.0, 150.0, 312.0, 230.0)],
            ['col_id' => 99, 'bbox' => markerpdf_saved_result_envelope_boundary_xxyy(342.0, 150.0, 362.0, 230.0)],
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
}

function markerpdf_saved_result_envelope_boundary_decoy_table(): array
{
    $table = markerpdf_saved_result_envelope_boundary_table();
    $table['cells'][0]['text'] = 'Decoy';
    $table['cells'][1]['text'] = 'Wrong';
    $table['cells'][2]['text'] = 'Other';
    $table['cells'][3]['text'] = 'Source';

    return $table;
}

return [
    'selects upstream tabled saved result envelope by source basename before table geometry localization' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-envelope-source-' . bin2hex(random_bytes(4)) . '.pdf';
        $stem = pathinfo($path, PATHINFO_FILENAME);
        file_put_contents($path, "%PDF-1.4\n% saved tabled result envelope boundary fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_saved_result_envelope_boundary_page([
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
                        'decoy-source' => [markerpdf_saved_result_envelope_boundary_decoy_table()],
                        $stem => [markerpdf_saved_result_envelope_boundary_table()],
                    ],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $metadata = $result['metadata'];
            $envelopeReview = $metadata['table_result_envelope_review'] ?? [];
            $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
            $cropReview = $metadata['table_assigned_crop_boundary_reviews'][0] ?? [];
            $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Saved Tabled Result Envelope Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After saved tabled result envelope.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale saved-result envelope table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale envelope row'));
            $t->true(!str_contains($result['text'], 'Stale envelope col'));
            $t->true(!str_contains($result['text'], 'Decoy'));
            $t->same('table_saved_result_envelope_boundary', $envelopeReview['review_target'] ?? null);
            $t->same('tabled.extract.py results.json basename-keyed table list', $envelopeReview['upstream_boundary'] ?? null);
            $t->same($stem, $envelopeReview['selected_key'] ?? null);
            $t->same(basename($path), $envelopeReview['source_basename'] ?? null);
            $t->same($stem, $envelopeReview['source_basename_without_extension'] ?? null);
            $t->same(2, $envelopeReview['available_key_count'] ?? null);
            $t->same(1, $envelopeReview['selected_table_count'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same('table_bbox', $coordinateReview['table_bbox_source'] ?? null);
            $t->same([72.0, 150.0, 312.0, 230.0], $coordinateReview['table_bbox'] ?? null);
            $t->same(6, $coordinateReview['translated_cell_count'] ?? null);
            $t->same(4, $cropReview['active_cell_count'] ?? null);
            $t->same(2, $cropReview['excluded_cell_count'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(['layout', 'table-result-envelope', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
            $t->same(false, $metadata['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
