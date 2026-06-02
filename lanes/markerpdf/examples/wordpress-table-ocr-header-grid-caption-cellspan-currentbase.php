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

$path = sys_get_temp_dir() . '/markerpdf-table-ocr-header-grid-caption-cellspan-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table OCR header grid caption cellspan fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'OCR captioned header grid import', 'bbox' => [72.0, 48.0, 460.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale captioned header grid table text should be replaced.', 'bbox' => [72.0, 176.0, 520.0, 196.0]],
                ['text' => 'Table 9: Captioned OCR header-grid review.', 'bbox' => [72.0, 302.0, 456.0, 320.0]],
                ['text' => 'Reviewer note after captioned header grid.', 'bbox' => [72.0, 346.0, 520.0, 364.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Section-header', 'bbox' => [72.0, 48.0, 460.0, 68.0]],
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

$context = $result['metadata']['table_section_caption_review'][0] ?? [];
$accessibility = $context['accessibility'] ?? [];
$cellspanGrid = $accessibility['cellspan_header_grid'] ?? [];

$gridByPosition = [];
foreach (($cellspanGrid['grid_cells'] ?? []) as $gridCell) {
    $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
}

$renderByText = [];
foreach (($cellspanGrid['render_cells'] ?? []) as $renderCell) {
    $renderByText[$renderCell['text']] = $renderCell;
}

$dataByText = [];
foreach (($cellspanGrid['data_cell_headers'] ?? []) as $dataCell) {
    $dataByText[$dataCell['text']] = $dataCell;
}

echo json_encode([
    'scenario' => 'wordpress-table-ocr-header-grid-caption-cellspan-currentbase',
    'native_boundary' => 'forced OCR header-grid table captions stay bound to explicit cellspan occupancy before Markdown drops covered cells',
    'source_truth' => 'marker.tables.table::format_tables consumes tabled OCR cells; tabled SpanTableCell preserves row_ids/col_ids while markdown_format only emits anchor cells through tabulate(headers="firstrow")',
    'table_context' => [
        'table_id' => $accessibility['table_id'] ?? null,
        'caption_id' => $cellspanGrid['caption_id'] ?? null,
        'section_id' => $cellspanGrid['section_id'] ?? null,
        'caption_bound' => $cellspanGrid['caption_bound'] ?? false,
    ],
    'header_ids' => $cellspanGrid['header_ids'] ?? [],
    'cellspan_header_grid' => $cellspanGrid,
    'has_caption_binding' => ($cellspanGrid['caption_id'] ?? null) === 'markerpdf-table-0-caption'
        && ($cellspanGrid['section_id'] ?? null) === 'markerpdf-table-0-section',
    'maps_merged_header_cellspan' => ($renderByText['Inventory axis']['rowspan'] ?? null) === 2
        && ($renderByText['Inventory axis']['colspan'] ?? null) === 2
        && ($renderByText['Inventory axis']['header_id'] ?? null) === 'h-r0-c0',
    'skips_covered_cell' => ($gridByPosition['1:1']['state'] ?? null) === 'covered'
        && ($gridByPosition['1:1']['covered_by'] ?? []) === ['row_id' => 0, 'col_id' => 0, 'render_cell_index' => 0],
    'maps_images_to_captioned_headers' => ($dataByText['Images']['headers'] ?? []) === ['h-r0-c0', 'h-r2-c0']
        && ($dataByText['Images']['caption_id'] ?? null) === 'markerpdf-table-0-caption',
    'maps_needs_review_to_status_and_row_headers' => ($dataByText['Needs review']['headers'] ?? []) === ['h-r0-c2', 'h-r2-c0'],
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale captioned header grid table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
