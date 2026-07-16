<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_table_pdf_page_bottom_left_page(array $lines): array
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

function markerpdf_table_pdf_page_bottom_left_bbox(array $pageImageBbox, float $pageHeight = 792.0): array
{
    return [
        (float) $pageImageBbox[0],
        $pageHeight - (float) $pageImageBbox[3],
        (float) $pageImageBbox[2],
        $pageHeight - (float) $pageImageBbox[1],
    ];
}

function markerpdf_table_pdf_page_bottom_left_round(array $bbox): array
{
    return array_map(
        static fn (mixed $value): float => round((float) $value, 1),
        $bbox
    );
}

function markerpdf_table_pdf_page_bottom_left_table(): array
{
    return [
        'coordinate_space' => 'pdf_page_bottom_left',
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bbox' => markerpdf_table_pdf_page_bottom_left_bbox([72.0, 150.0, 312.0, 230.0]),
        'rows' => [
            ['row_id' => 0, 'bbox' => markerpdf_table_pdf_page_bottom_left_bbox([72.0, 150.0, 312.0, 182.0])],
            ['row_id' => 1, 'bbox' => markerpdf_table_pdf_page_bottom_left_bbox([72.0, 190.0, 312.0, 220.0])],
            ['row_id' => 99, 'bbox' => markerpdf_table_pdf_page_bottom_left_bbox([72.0, 250.0, 312.0, 268.0])],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => markerpdf_table_pdf_page_bottom_left_bbox([72.0, 150.0, 172.0, 230.0])],
            ['col_id' => 1, 'bbox' => markerpdf_table_pdf_page_bottom_left_bbox([192.0, 150.0, 312.0, 230.0])],
            ['col_id' => 99, 'bbox' => markerpdf_table_pdf_page_bottom_left_bbox([342.0, 150.0, 362.0, 230.0])],
        ],
        'cells' => [
            ['bbox' => markerpdf_table_pdf_page_bottom_left_bbox([82.0, 155.0, 162.0, 170.0]), 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => markerpdf_table_pdf_page_bottom_left_bbox([202.0, 155.0, 302.0, 170.0]), 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => markerpdf_table_pdf_page_bottom_left_bbox([82.0, 195.0, 162.0, 215.0]), 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => markerpdf_table_pdf_page_bottom_left_bbox([202.0, 195.0, 302.0, 215.0]), 'text' => 'Ready', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => markerpdf_table_pdf_page_bottom_left_bbox([82.0, 250.0, 162.0, 268.0]), 'text' => 'Stale PDF row', 'row_ids' => [99], 'col_ids' => [0]],
            ['bbox' => markerpdf_table_pdf_page_bottom_left_bbox([360.0, 195.0, 382.0, 215.0]), 'text' => 'Stale PDF col', 'row_ids' => [1], 'col_ids' => [99]],
        ],
        'ocr_grid_border_conflicts' => [[
            'ocr_index' => 0,
            'text' => 'Wide PDF-coordinate OCR',
            'bbox' => markerpdf_table_pdf_page_bottom_left_bbox([82.0, 155.0, 302.0, 215.0]),
            'candidate_cell_indexes' => [0, 1, 2],
            'candidate_cell_bboxes' => [
                markerpdf_table_pdf_page_bottom_left_bbox([82.0, 155.0, 162.0, 170.0]),
                markerpdf_table_pdf_page_bottom_left_bbox([202.0, 155.0, 302.0, 170.0]),
                markerpdf_table_pdf_page_bottom_left_bbox([82.0, 195.0, 162.0, 215.0]),
            ],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]],
    ];
}

return [
    'flips bottom-left PDF page coordinates into table crop geometry before assignment' => static function (
        TestRunner $t
    ): void {
        $recognizer = new TableRecognizer();
        $formatted = $recognizer->formatRecognizedTables(
            [markerpdf_table_pdf_page_bottom_left_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $formatted['recognized_tables'][0] ?? [];
        $review = $formatted['coordinate_space_reviews'][0] ?? [];
        $assigned = $formatted['assigned_cells'][0] ?? [];
        $assignedTexts = array_column($assigned, 'text');
        $conflict = $localized['ocr_grid_border_conflicts'][0] ?? [];
        $cropReview = $formatted['assigned_crop_boundary_reviews'][0] ?? [];
        $gridReview = $recognizer->spanningGridReview(
            $assigned,
            $localized['rows'] ?? [],
            $localized['cols'] ?? [],
            ['width' => 240, 'height' => 80]
        );

        $t->same('table_recognition_coordinate_space_boundary', $review['review_target'] ?? null);
        $t->same('translated_to_table_crop', $review['status'] ?? null);
        $t->same('pdf_page_bottom_left', $review['source_coordinate_spaces']['cells'] ?? null);
        $t->same('pdf_page_bottom_left', $review['table_bbox_source_coordinate_space'] ?? null);
        $t->same(markerpdf_table_pdf_page_bottom_left_bbox([72.0, 150.0, 312.0, 230.0]), $review['source_table_bbox'] ?? null);
        $t->same([72.0, 150.0, 312.0, 230.0], $review['table_bbox'] ?? null);
        $t->same(['width' => 612, 'height' => 792], $review['pdf_page_image_size'] ?? null);
        $t->same(['x' => -72.0, 'y' => -150.0], $review['translation'] ?? null);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);
        $t->same(1, $review['translated_conflict_count'] ?? null);

        $t->same([0.0, 0.0, 240.0, 32.0], markerpdf_table_pdf_page_bottom_left_round($localized['rows'][0]['bbox'] ?? []));
        $t->same([120.0, 0.0, 240.0, 80.0], markerpdf_table_pdf_page_bottom_left_round($localized['cols'][1]['bbox'] ?? []));
        $t->same([10.0, 5.0, 90.0, 20.0], markerpdf_table_pdf_page_bottom_left_round($localized['cells'][0]['bbox'] ?? []));
        $t->same(markerpdf_table_pdf_page_bottom_left_bbox([82.0, 155.0, 162.0, 170.0]), markerpdf_table_pdf_page_bottom_left_round($localized['cells'][0]['source_bbox'] ?? []));
        $t->same([82.0, 155.0, 162.0, 170.0], markerpdf_table_pdf_page_bottom_left_round($localized['cells'][0]['source_page_image_bbox'] ?? []));
        $t->same('pdf_page_bottom_left', $localized['cells'][0]['source_coordinate_space'] ?? null);
        $t->same([10.0, 5.0, 230.0, 65.0], markerpdf_table_pdf_page_bottom_left_round($conflict['bbox'] ?? []));
        $t->same(markerpdf_table_pdf_page_bottom_left_bbox([82.0, 155.0, 302.0, 215.0]), markerpdf_table_pdf_page_bottom_left_round($conflict['source_bbox'] ?? []));
        $t->same([82.0, 155.0, 302.0, 215.0], markerpdf_table_pdf_page_bottom_left_round($conflict['source_page_image_bbox'] ?? []));
        $t->same([[10.0, 5.0, 90.0, 20.0], [130.0, 5.0, 230.0, 20.0], [10.0, 45.0, 90.0, 65.0]], $conflict['candidate_cell_bboxes'] ?? null);

        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
        $t->true(!in_array('Stale PDF row', $assignedTexts, true));
        $t->true(!in_array('Stale PDF col', $assignedTexts, true));
        $t->same(6, $cropReview['cell_count'] ?? null);
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);
        $t->same('pdf_page_bottom_left', $gridReview['render_cells'][0]['source_coordinate_space'] ?? null);
        $t->same([82.0, 155.0, 162.0, 170.0], markerpdf_table_pdf_page_bottom_left_round($gridReview['render_cells'][0]['source_page_image_bbox'] ?? []));
        $t->same("| Feature | Status |\n|---------|--------|\n| Images  | Ready  |", $formatted['markdown_tables'][0] ?? '');
    },
    'surfaces bottom-left PDF table coordinates through supplied WordPress conversion metadata' => static function (
        TestRunner $t
    ): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-pdf-page-bottom-left-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table PDF bottom-left coordinate boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    markerpdf_table_pdf_page_bottom_left_page([
                        ['text' => 'PDF Bottom Left Table Boundary', 'bbox' => [72.0, 48.0, 560.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale PDF-coordinate table line should be replaced.', 'bbox' => [82.0, 176.0, 360.0, 196.0]],
                        ['text' => 'After PDF bottom-left table.', 'bbox' => [72.0, 276.0, 560.0, 294.0]],
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
                    'recognized_tables' => [markerpdf_table_pdf_page_bottom_left_table()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $metadata = $result['metadata'];
            $coordinateReview = $metadata['table_coordinate_space_reviews'][0] ?? [];
            $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
            $gridReview = $metadata['table_spanning_grid_review'][0] ?? [];

            $t->contains('# Pdf Bottom Left Table Boundary', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After PDF bottom-left table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale PDF-coordinate table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale PDF row'));
            $t->true(!str_contains($result['text'], 'Stale PDF col'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
            $t->same('translated_to_table_crop', $coordinateReview['status'] ?? null);
            $t->same('pdf_page_bottom_left', $coordinateReview['source_coordinate_spaces']['cells'] ?? null);
            $t->same([72.0, 150.0, 312.0, 230.0], $coordinateReview['table_bbox'] ?? null);
            $t->same(['width' => 612, 'height' => 792], $coordinateReview['pdf_page_image_size'] ?? null);
            $t->same(6, $coordinateReview['translated_cell_count'] ?? null);
            $t->same(1, $coordinateReview['translated_conflict_count'] ?? null);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same('pdf_page_bottom_left', $gridReview['render_cells'][0]['source_coordinate_space'] ?? null);
            $t->same([82.0, 155.0, 162.0, 170.0], markerpdf_table_pdf_page_bottom_left_round($gridReview['render_cells'][0]['source_page_image_bbox'] ?? []));
            $t->same([[72.0, 150.0, 312.0, 230.0]], $metadata['table_plan']['table_bboxes'] ?? null);
            $t->same(false, $metadata['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
