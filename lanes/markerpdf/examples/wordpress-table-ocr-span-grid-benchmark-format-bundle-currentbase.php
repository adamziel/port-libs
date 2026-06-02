<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportVerifier;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableBenchmarkBundleBuilder;

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

$path = sys_get_temp_dir() . '/markerpdf-table-ocr-span-grid-benchmark-bundle-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table OCR span grid benchmark bundle fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'OCR span grid benchmark import', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale benchmark table text should be replaced.', 'bbox' => [72.0, 176.0, 500.0, 196.0]],
                ['text' => 'Table 12: OCR benchmark review.', 'bbox' => [72.0, 302.0, 456.0, 320.0]],
                ['text' => 'Reviewer note after benchmark table.', 'bbox' => [72.0, 346.0, 520.0, 364.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Section-header', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 290.0]],
                            ['label' => 'Caption', 'bbox' => [72.0, 302.0, 456.0, 320.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 346.0, 520.0, 364.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 28.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 32.0, 300.0, 60.0]],
                    ['row_id' => 2, 'bbox' => [0.0, 70.0, 300.0, 100.0]],
                    ['row_id' => 3, 'bbox' => [0.0, 110.0, 300.0, 140.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 90.0, 140.0]],
                    ['col_id' => 1, 'bbox' => [100.0, 0.0, 190.0, 140.0]],
                    ['col_id' => 2, 'bbox' => [200.0, 0.0, 300.0, 140.0]],
                ],
            ]],
            'table_detector_cells' => [[
                ['bbox' => [5.0, 5.0, 185.0, 56.0], 'text' => null],
                ['bbox' => [110.0, 8.0, 180.0, 20.0], 'text' => null],
                ['bbox' => [205.0, 5.0, 295.0, 24.0], 'text' => null],
                ['bbox' => [5.0, 74.0, 85.0, 136.0], 'text' => null],
                ['bbox' => [110.0, 74.0, 180.0, 94.0], 'text' => null],
                ['bbox' => [205.0, 74.0, 295.0, 94.0], 'text' => null],
                ['bbox' => [110.0, 114.0, 180.0, 134.0], 'text' => null],
                ['bbox' => [205.0, 114.0, 295.0, 134.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [[
                'lines' => [
                    ['text' => 'Inventory'],
                    ['text' => 'axis'],
                    ['text' => 'Status'],
                    ['text' => 'Media group'],
                    ['text' => 'Images'],
                    ['text' => '12'],
                    ['text' => 'State'],
                    ['text' => 'Needs review'],
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

$builder = new TableBenchmarkBundleBuilder();
$reference = "| Inventory   | axis   | Status       |\n|-------------|--------|--------------|\n| Media group | Images | 12           |\n|             | State  | Needs review |";
$rows = $builder->buildRowsFromConversion('wordpress-import.pdf', $result, [$reference], ['elapsed' => 0.5]);
$tables = $builder->outputTables($rows);
(new BenchmarkReportVerifier())->verifyTableScores($rows);

echo json_encode([
    'scenario' => 'wordpress-table-ocr-span-grid-benchmark-format-bundle-currentbase',
    'native_boundary' => 'forced-OCR table Markdown is scored with upstream marker.benchmark.table semantics while span-grid and caption context stay attached to the same verifier row',
    'source_truth' => 'marker/benchmark/table.py scores pipe-cell Markdown rows; marker/tables/table.py formats tabled assigned cells; tabled markdown_format emits anchor cells while SpanTableCell keeps row_ids and col_ids',
    'score_headers' => $tables['score_headers'],
    'score_rows' => $tables['score_rows'],
    'benchmark_rows' => $rows,
    'passes_upstream_table_threshold' => true,
    'has_span_grid_bundle' => ($rows[0]['review_target'] ?? null) === 'table_ocr_span_grid_benchmark_format'
        && ($rows[0]['context']['caption_id'] ?? null) === 'markerpdf-table-0-caption',
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale benchmark table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
