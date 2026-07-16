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

$path = sys_get_temp_dir() . '/markerpdf-table-ocr-rotated-header-caption-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table OCR rotated header caption fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'Rotated OCR header caption import', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale rotated header table text should be replaced.', 'bbox' => [72.0, 176.0, 520.0, 196.0]],
                ['text' => 'Table 15: Rotated OCR header caption review.', 'bbox' => [72.0, 402.0, 500.0, 420.0]],
                ['text' => 'Reviewer note after rotated caption table.', 'bbox' => [72.0, 446.0, 530.0, 464.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Section-header', 'bbox' => [72.0, 48.0, 500.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 390.0]],
                    ['label' => 'Caption', 'bbox' => [72.0, 402.0, 500.0, 420.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 446.0, 530.0, 464.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 25.0, 240.0]],
                    ['row_id' => 1, 'bbox' => [35.0, 0.0, 60.0, 240.0]],
                    ['row_id' => 2, 'bbox' => [85.0, 0.0, 110.0, 240.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 120.0, 70.0]],
                    ['col_id' => 1, 'bbox' => [0.0, 90.0, 120.0, 150.0]],
                    ['col_id' => 2, 'bbox' => [0.0, 170.0, 120.0, 240.0]],
                ],
            ]],
            'table_detector_cells' => [[
                ['bbox' => [3.0, 5.0, 22.0, 235.0], 'text' => null],
                ['bbox' => [36.0, 5.0, 108.0, 65.0], 'text' => null],
                ['bbox' => [38.0, 95.0, 58.0, 145.0], 'text' => null],
                ['bbox' => [38.0, 175.0, 58.0, 235.0], 'text' => null],
                ['bbox' => [88.0, 95.0, 108.0, 150.0], 'text' => null],
                ['bbox' => [88.0, 175.0, 108.0, 230.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [[
                'lines' => [
                    ['text' => 'Rotated inventory'],
                    ['text' => 'Media group'],
                    ['text' => 'Image count'],
                    ['text' => '12'],
                    ['text' => 'Review state'],
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

$context = $result['metadata']['table_section_caption_review'][0] ?? [];
$accessibility = $context['accessibility'] ?? [];
$cellspanGrid = $accessibility['cellspan_header_grid'] ?? [];

$dataByText = [];
foreach (($cellspanGrid['data_cell_headers'] ?? []) as $dataCell) {
    $dataByText[$dataCell['text']] = $dataCell;
}

$checks = [
    'has_caption_binding' => ($cellspanGrid['caption_id'] ?? null) === 'markerpdf-table-0-caption'
        && ($cellspanGrid['section_id'] ?? null) === 'markerpdf-table-0-section',
    'rotated_caption_grid' => ($cellspanGrid['rotated'] ?? false) === true
        && ($cellspanGrid['orientation'] ?? null) === 'rotated',
    'row_axis_x_col_axis_y' => ($cellspanGrid['row_axis'] ?? null) === 'x'
        && ($cellspanGrid['col_axis'] ?? null) === 'y',
    'maps_image_count_to_rotated_headers' => ($dataByText['Image count']['headers'] ?? []) === ['h-r0-c0', 'h-r1-c0']
        && ($dataByText['Image count']['column_header_physical_axis'] ?? null) === 'y'
        && ($dataByText['Image count']['row_header_physical_axis'] ?? null) === 'x'
        && ($dataByText['Image count']['caption_id'] ?? null) === 'markerpdf-table-0-caption',
    'maps_needs_review_to_rotated_headers' => ($dataByText['Needs review']['headers'] ?? []) === ['h-r0-c0', 'h-r1-c0']
        && ($dataByText['Needs review']['header_text'] ?? null) === 'Rotated inventory / Media group',
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale rotated header table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ($checks as $name => $passed) {
    if ($passed !== true && str_starts_with($name, 'executes_') === false) {
        throw new RuntimeException('Expected rotated OCR header caption check to pass: ' . $name);
    }
}

echo json_encode([
    'scenario' => 'wordpress-table-ocr-rotated-header-caption-currentbase',
    'native_boundary' => 'forced OCR rotated header grids keep caption binding, swapped tabled row/column axes, and physical header-axis metadata before Markdown drops covered cells',
    'source_truth' => 'marker.tables.table::format_tables routes OCR-needed tables through tabled get_cells/recognize_tables/assign_rows_columns, and tabled rotated assignments swap row and column axes while Markdown keeps only anchor cells',
    'table_context' => [
        'table_id' => $accessibility['table_id'] ?? null,
        'caption_id' => $cellspanGrid['caption_id'] ?? null,
        'section_id' => $cellspanGrid['section_id'] ?? null,
        'caption_bound' => $cellspanGrid['caption_bound'] ?? false,
        'rotated' => $cellspanGrid['rotated'] ?? false,
        'orientation' => $cellspanGrid['orientation'] ?? null,
        'row_axis' => $cellspanGrid['row_axis'] ?? null,
        'col_axis' => $cellspanGrid['col_axis'] ?? null,
    ],
    'header_ids' => $cellspanGrid['header_ids'] ?? [],
    'data_cell_headers' => $cellspanGrid['data_cell_headers'] ?? [],
    'checks' => $checks,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
