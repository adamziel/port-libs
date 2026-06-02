<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportVerifier;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableBenchmarkBundleBuilder;

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

$spanGrid = static function (): array {
    return [
        'review_target' => 'table_span_grid',
        'rows' => [0, 1, 2],
        'cols' => [0, 1, 2],
        'orientation' => 'normal',
        'row_axis' => 'y',
        'col_axis' => 'x',
        'render_cells' => [
            ['text' => 'Inventory axis', 'row_ids' => [0, 1], 'col_ids' => [0, 1], 'rowspan' => 2, 'colspan' => 2, 'header' => true, 'header_id' => 'h-r0-c0'],
            ['text' => 'Status', 'row_ids' => [0], 'col_ids' => [2], 'rowspan' => 1, 'colspan' => 1, 'header' => true, 'header_id' => 'h-r0-c2'],
            ['text' => 'Media group', 'row_ids' => [2], 'col_ids' => [0], 'rowspan' => 1, 'colspan' => 1, 'header' => true, 'header_id' => 'h-r2-c0'],
            ['text' => 'Images', 'row_ids' => [2], 'col_ids' => [1], 'headers' => ['h-r0-c0', 'h-r2-c0']],
            ['text' => 'Needs review', 'row_ids' => [2], 'col_ids' => [2], 'headers' => ['h-r0-c2', 'h-r2-c0']],
        ],
        'grid_cells' => [
            ['row_id' => 0, 'col_id' => 0, 'state' => 'anchor', 'render_cell_index' => 0],
            ['row_id' => 0, 'col_id' => 1, 'state' => 'covered', 'covered_by' => ['row_id' => 0, 'col_id' => 0, 'render_cell_index' => 0]],
            ['row_id' => 1, 'col_id' => 0, 'state' => 'covered', 'covered_by' => ['row_id' => 0, 'col_id' => 0, 'render_cell_index' => 0]],
            ['row_id' => 1, 'col_id' => 1, 'state' => 'covered', 'covered_by' => ['row_id' => 0, 'col_id' => 0, 'render_cell_index' => 0]],
            ['row_id' => 0, 'col_id' => 2, 'state' => 'anchor', 'render_cell_index' => 1],
            ['row_id' => 1, 'col_id' => 2, 'state' => 'empty'],
            ['row_id' => 2, 'col_id' => 0, 'state' => 'anchor', 'render_cell_index' => 2],
            ['row_id' => 2, 'col_id' => 1, 'state' => 'anchor', 'render_cell_index' => 3],
            ['row_id' => 2, 'col_id' => 2, 'state' => 'anchor', 'render_cell_index' => 4],
        ],
        'header_cells' => [
            ['header_id' => 'h-r0-c0', 'text' => 'Inventory axis'],
            ['header_id' => 'h-r0-c2', 'text' => 'Status'],
            ['header_id' => 'h-r2-c0', 'text' => 'Media group'],
        ],
        'data_cells' => [
            ['text' => 'Images', 'headers' => ['h-r0-c0', 'h-r2-c0']],
            ['text' => 'Needs review', 'headers' => ['h-r0-c2', 'h-r2-c0']],
        ],
        'accessibility_grid' => [
            'review_target' => 'table_header_accessibility_grid',
            'header_ids' => ['h-r0-c0', 'h-r0-c2', 'h-r2-c0'],
        ],
    ];
};

$context = static function (): array {
    return [
        'review_target' => 'table_span_grid',
        'page_number' => 4,
        'has_section' => true,
        'has_caption' => true,
        'accessibility' => [
            'table_id' => 'markerpdf-table-0',
            'aria_labelledby' => 'markerpdf-table-0-section',
            'aria_describedby' => 'markerpdf-table-0-caption',
            'header_ids' => ['h-r0-c0', 'h-r0-c2', 'h-r2-c0'],
            'cellspan_header_grid' => [
                'caption_id' => 'markerpdf-table-0-caption',
                'section_id' => 'markerpdf-table-0-section',
                'caption_bound' => true,
            ],
        ],
    ];
};

return [
    'builds upstream table benchmark rows with OCR span grid metadata bundle' => static function (TestRunner $t) use ($spanGrid, $context): void {
        $markdown = "| Inventory axis | Status |\n| --- | --- |\n| Images | Needs review |";
        $rows = (new TableBenchmarkBundleBuilder())->buildRows([[
            'document' => 'switch_trans.pdf',
            'table_index' => 2,
            'page_number' => 4,
            'elapsed' => 0.25,
            'markdown' => $markdown,
            'reference' => $markdown,
            'span_grid' => $spanGrid(),
            'context' => $context(),
            'ocr_assignment' => 'forced_ocr',
        ]]);

        $t->same(1, count($rows));
        $t->same('switch_trans.pdf', $rows[0]['document']);
        $t->same(2, $rows[0]['table_index']);
        $t->same(4, $rows[0]['page_number']);
        $t->true($rows[0]['score'] > 0.89, 'Expected conversion-derived OCR table score above 0.89.');
        $t->same(0.25, $rows[0]['time']);
        $t->same('table_ocr_span_grid_benchmark_format', $rows[0]['review_target']);
        $t->same(['h-r0-c0', 'h-r0-c2', 'h-r2-c0'], $rows[0]['span_grid']['header_ids']);
        $t->same(3, $rows[0]['span_grid']['covered_cell_count']);
        $t->same(9, $rows[0]['span_grid']['expected_grid_cell_count']);
        $t->same(5, $rows[0]['span_grid']['anchor_cell_count']);
        $t->same(1, $rows[0]['span_grid']['empty_cell_count']);
        $t->same(1, $rows[0]['span_grid']['cellspan_count']);
        $t->same(true, $rows[0]['span_grid']['has_rowspan']);
        $t->same(true, $rows[0]['span_grid']['has_colspan']);
        $t->same('table_ocr_span_grid_quality', $rows[0]['span_grid']['quality_review_target']);
        $t->same(true, $rows[0]['span_grid']['covered_cell_count_matches_spans']);
        $t->same(true, $rows[0]['span_grid']['quality_passes']);
        $t->same(['complete_grid', 'contiguous_spans', 'resolved_covered_cells'], $rows[0]['span_grid']['quality_flags']);
        $t->same('markerpdf-table-0-caption', $rows[0]['context']['caption_id']);
        $t->same('markerpdf-table-0-section', $rows[0]['context']['section_id']);
        $t->same('forced_ocr', $rows[0]['ocr_assignment']);
    },
    'builds table benchmark rows from supplied OCR conversion metadata' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-benchmark-bundle-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table benchmark bundle supplied pipeline\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $pdftextPage([
                        ['text' => 'OCR span grid benchmark import', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale benchmark table text should be replaced.', 'bbox' => [72.0, 176.0, 500.0, 196.0]],
                        ['text' => 'Table 12: OCR benchmark review.', 'bbox' => [72.0, 282.0, 430.0, 300.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Section-header', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 270.0]],
                            ['label' => 'Caption', 'bbox' => [72.0, 282.0, 430.0, 300.0]],
                        ],
                    ]],
                    'recognized_tables' => [[
                        'rows' => [
                            ['row_id' => 0, 'bbox' => [0.0, 0.0, 220.0, 30.0]],
                            ['row_id' => 1, 'bbox' => [0.0, 38.0, 220.0, 70.0]],
                        ],
                        'cols' => [
                            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 72.0]],
                            ['col_id' => 1, 'bbox' => [112.0, 0.0, 220.0, 72.0]],
                        ],
                    ]],
                    'table_detector_cells' => [[
                        ['bbox' => [0.0, 0.0, 100.0, 28.0], 'text' => null],
                        ['bbox' => [112.0, 0.0, 220.0, 28.0], 'text' => null],
                        ['bbox' => [0.0, 40.0, 100.0, 68.0], 'text' => null],
                        ['bbox' => [112.0, 40.0, 220.0, 68.0], 'text' => null],
                    ]],
                    'table_ocr_text_lines' => [[
                        'lines' => [
                            ['text' => 'Feature'],
                            ['text' => 'Status'],
                            ['text' => 'Images'],
                            ['text' => 'Ready'],
                        ],
                    ]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                    'ocr_all_pages' => true,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );
        } finally {
            unlink($path);
        }

        $reference = "| Feature | Status |\n| --- | --- |\n| Images | Ready |";
        $builder = new TableBenchmarkBundleBuilder();
        $rows = $builder->buildRowsFromConversion('wordpress-import.pdf', $result, [$reference], ['elapsed' => 0.5]);
        (new BenchmarkReportVerifier())->verifyTableScores($rows);

        $t->contains('# Ocr Span Grid Benchmark Import', $result['text']);
        $t->contains('| Feature | Status |', $result['text']);
        $t->true(!str_contains($result['text'], 'Stale benchmark table text should be replaced.'));
        $t->same(1, count($rows));
        $t->same('wordpress-import.pdf', $rows[0]['document']);
        $t->true($rows[0]['score'] > 0.89, 'Expected conversion-derived OCR table score above 0.89.');
        $t->same('forced_ocr', $rows[0]['ocr_assignment']);
        $t->same('table_span_grid', $rows[0]['span_grid']['source_review_target']);
        $t->same(4, $rows[0]['span_grid']['grid_cell_count']);
        $t->same(['h-r0-c0', 'h-r0-c1'], $rows[0]['span_grid']['header_ids']);
        $t->same(true, $rows[0]['span_grid']['quality_passes']);
        $t->same(['complete_grid', 'contiguous_spans', 'resolved_covered_cells'], $rows[0]['span_grid']['quality_flags']);
        $t->same('markerpdf-table-0-caption', $rows[0]['context']['caption_id']);
        $t->same(true, $rows[0]['context']['caption_bound']);
        $t->same(0.5, $rows[0]['time']);
    },
    'exports table benchmark score rows and writes verifier compatible json' => static function (TestRunner $t) use ($spanGrid): void {
        $builder = new TableBenchmarkBundleBuilder();
        $markdown = "| Feature | Status |\n| --- | --- |\n| Images | Ready |";
        $rows = $builder->buildRows([[
            'document' => 'multicolcnn.pdf',
            'markdown' => $markdown,
            'reference' => $markdown,
            'span_grid' => $spanGrid(),
        ]]);
        $outputFile = sys_get_temp_dir() . '/markerpdf-table-benchmark-bundle-' . bin2hex(random_bytes(4)) . '.json';

        try {
            $tables = $builder->outputTables($rows);
            $builder->writeJsonRows($outputFile, $rows);
            $decoded = json_decode((string) file_get_contents($outputFile), true, flags: JSON_THROW_ON_ERROR);
            (new BenchmarkReportVerifier())->verifyTableScores($decoded);

            $t->same(['Document', 'Table', 'Score', 'Reference cells', 'Hypothesis cells', 'Header IDs', 'Span grid', 'Span quality'], $tables['score_headers']);
            $t->same('multicolcnn.pdf', $tables['score_rows'][0][0]);
            $t->same(1.0, $tables['score_rows'][0][2]);
            $t->same('h-r0-c0,h-r0-c2,h-r2-c0', $tables['score_rows'][0][5]);
            $t->same('rowspan+colspan+covered', $tables['score_rows'][0][6]);
            $t->same('complete_grid+contiguous_spans+resolved_covered_cells', $tables['score_rows'][0][7]);
            $t->same('table_ocr_span_grid_benchmark_format', $decoded[0]['review_target']);
        } finally {
            if (is_file($outputFile)) {
                unlink($outputFile);
            }
        }
    },
    'rejects malformed table benchmark bundles before quality gates consume them' => static function (TestRunner $t): void {
        $builder = new TableBenchmarkBundleBuilder();
        $markdown = "| Feature | Status |\n| --- | --- |\n| Images | Ready |";

        $t->throws(InvalidArgumentException::class, static fn () => $builder->buildRows([]));
        $t->throws(InvalidArgumentException::class, static fn () => $builder->buildRows([['markdown' => $markdown, 'reference' => $markdown]]));
        $t->throws(InvalidArgumentException::class, static fn () => $builder->buildRows([['document' => 'doc.pdf', 'markdown' => $markdown, 'reference' => $markdown, 'span_grid' => 'bad']]));
        $t->throws(InvalidArgumentException::class, static fn () => $builder->buildRowsFromConversion('', ['text' => $markdown], [$markdown]));
        $t->throws(InvalidArgumentException::class, static fn () => $builder->buildRowsFromConversion('doc.pdf', ['text' => 'no table here'], [$markdown]));
        $t->throws(InvalidArgumentException::class, static fn () => $builder->outputTables([]));
    },
    'flags incomplete or non-contiguous OCR span grids before benchmark quality gates trust them' => static function (TestRunner $t) use ($spanGrid): void {
        $grid = $spanGrid();
        array_pop($grid['grid_cells']);
        $grid['render_cells'][0]['row_ids'] = [0, 2];
        $grid['render_cells'][0]['rowspan'] = 2;
        $grid['grid_cells'][] = [
            'row_id' => 2,
            'col_id' => 2,
            'state' => 'covered',
            'covered_by' => ['row_id' => 9, 'col_id' => 9, 'render_cell_index' => 99],
        ];

        $markdown = "| Feature | Status |\n| --- | --- |\n| Images | Ready |";
        $rows = (new TableBenchmarkBundleBuilder())->buildRows([[
            'document' => 'quality-gate.pdf',
            'markdown' => $markdown,
            'reference' => $markdown,
            'span_grid' => $grid,
        ]]);
        $span = $rows[0]['span_grid'];

        $t->same(false, $span['quality_passes']);
        $t->same(
            ['orphan_covered_cells', 'non_contiguous_spans', 'covered_cell_count_mismatch'],
            $span['quality_flags']
        );
        $t->same(1, $span['orphan_covered_cell_count']);
        $t->same(1, $span['non_contiguous_span_count']);
        $t->same(['rows'], $span['non_contiguous_spans'][0]['axes']);
        $t->same('Inventory axis', $span['non_contiguous_spans'][0]['text']);
        $t->same(false, $span['covered_cell_count_matches_spans']);

        $missing = (new TableBenchmarkBundleBuilder())->buildRows([[
            'document' => 'missing-grid.pdf',
            'markdown' => $markdown,
            'reference' => $markdown,
        ]]);
        $t->same(false, $missing[0]['span_grid']['quality_passes']);
        $t->same(['missing_span_grid'], $missing[0]['span_grid']['quality_flags']);
    },
];
