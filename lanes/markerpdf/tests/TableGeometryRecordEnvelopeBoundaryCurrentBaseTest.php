<?php

require_once __DIR__ . '/../src/SuppliedDocumentConverter.php';
require_once __DIR__ . '/../src/TableRecognizer.php';

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

function markerpdf_record_envelope_boundary_table(): array
{
    return [
        'coordinate_space' => 'page_image',
        'bbox' => [72, 150, 312, 230],
        'image_bbox' => [0, 0, 612, 792],
        'rows' => [
            ['row' => ['row_id' => 0, 'bbox' => [72, 150, 312, 182]]],
            ['row_record' => ['row_id' => 1, 'bbox' => [72, 192, 312, 230]]],
            ['row' => ['row_id' => 9, 'bbox' => [340, 150, 365, 182]]],
        ],
        'cols' => [
            ['column' => ['col_id' => 0, 'bbox' => [72, 150, 172, 230]]],
            ['col' => ['col_id' => 1, 'bbox' => [192, 150, 312, 230]]],
            ['column_record' => ['col_id' => 8, 'bbox' => [340, 150, 370, 230]]],
        ],
        'cells' => [
            ['cell' => ['bbox' => [82, 155, 162, 175], 'text' => 'Feature', 'row_ids' => [0], 'col_ids' => [0]]],
            ['table_cell' => ['bbox' => [198, 155, 300, 175], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [1]]],
            ['span_cell' => ['bbox' => [82, 198, 162, 222], 'text' => 'Images', 'row_id' => 1, 'col_id' => 0]],
            ['cell_record' => ['bbox' => [198, 198, 300, 222], 'text' => 'Ready', 'row_id' => 1, 'col_id' => 1]],
            ['cell' => ['bbox' => [342, 155, 360, 175], 'text' => 'Stale row', 'row_id' => 9, 'col_id' => 0]],
            ['cell' => ['bbox' => [198, 238, 300, 252], 'text' => 'Stale column', 'row_id' => 1, 'col_id' => 8]],
        ],
    ];
}

function markerpdf_record_envelope_boundary_page(): array
{
    return [
        'page' => 0,
        'width' => 612,
        'height' => 792,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [[
            'lines' => [[
                'bbox' => [72.0, 48.0, 560.0, 68.0],
                'spans' => [[
                    'text' => 'Record envelope table fixture',
                    'bbox' => [72.0, 48.0, 560.0, 68.0],
                    'font' => ['name' => 'Heading-Bold', 'flags' => 0, 'weight' => 700, 'size' => 18],
                ]],
            ], [
                'bbox' => [84.0, 160.0, 290.0, 218.0],
                'spans' => [[
                    'text' => 'Stale pdftext table line',
                    'bbox' => [84.0, 160.0, 290.0, 218.0],
                    'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12],
                ]],
            ], [
                'bbox' => [72.0, 276.0, 560.0, 294.0],
                'spans' => [[
                    'text' => 'After record envelope table.',
                    'bbox' => [72.0, 276.0, 560.0, 294.0],
                    'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12],
                ]],
            ]],
        ]],
    ];
}

return [
    'markerpdf table geometry unwraps row column and cell record envelopes before supplied boundary formatting' => function ($t): void {
        $recognizer = new TableRecognizer();
        $result = $recognizer->formatRecognizedTables(
            [markerpdf_record_envelope_boundary_table()],
            [['width' => 240, 'height' => 80]]
        );

        $localized = $result['recognized_tables'][0] ?? [];
        $assigned = $result['assigned_cells'][0] ?? [];
        $cropReview = $result['assigned_crop_boundary_reviews'][0] ?? [];

        $t->same(
            "| Feature | Status |\n|---------|--------|\n| Images  | Ready  |",
            $result['markdown_tables'][0] ?? ''
        );
        $t->same(4, count($assigned));
        $t->same(4, $cropReview['active_cell_count'] ?? null);
        $t->same(2, $cropReview['excluded_cell_count'] ?? null);

        $review = $result['coordinate_space_reviews'][0] ?? [];
        $t->same('translated_to_table_crop', $review['status']);
        $t->same(3, $review['translated_row_band_count'] ?? null);
        $t->same(3, $review['translated_col_band_count'] ?? null);
        $t->same(6, $review['translated_cell_count'] ?? null);

        $t->same([0.0, 0.0, 240.0, 32.0], $localized['rows'][0]['bbox'] ?? null);
        $t->same([120.0, 0.0, 240.0, 80.0], $localized['cols'][1]['bbox'] ?? null);
        $t->same([126.0, 48.0, 228.0, 72.0], $localized['cells'][3]['bbox'] ?? null);
    },
    'markerpdf supplied document converter replaces stale pdf text with record envelope table markdown' => function ($t): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-record-envelope-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table record envelope boundary current-base fixture\n%%EOF");

        try {
            $converter = new SuppliedDocumentConverter();
            $result = $converter->convert(
                $path,
                [markerpdf_record_envelope_boundary_page()],
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
                    'recognized_tables' => [markerpdf_record_envelope_boundary_table()],
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

        $markdown = $result['text'];
        $t->contains('| Feature | Status |', $markdown);
        $t->contains('| Images  | Ready  |', $markdown);
        $t->contains('After record envelope table.', $markdown);
        $t->same(false, str_contains($markdown, 'Stale pdftext table line'));
        $t->same(false, str_contains($markdown, 'Stale row'));
        $t->same(false, str_contains($markdown, 'Stale column'));

        $metadata = $result['metadata'];
        $assignedTexts = array_column($metadata['table_assigned_cells'][0] ?? [], 'text');
        $t->same(['layout', 'table-recognition', 'table-formatting'], $metadata['supplied_boundaries'] ?? null);
        $t->same(1, $metadata['inserted_tables'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
    },
];
