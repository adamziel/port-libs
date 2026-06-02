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

$path = sys_get_temp_dir() . '/markerpdf-table-ocr-merged-header-axis-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table OCR merged header axis fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'OCR merged header axis review', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale OCR header-axis table text should be replaced.', 'bbox' => [72.0, 176.0, 490.0, 196.0]],
                ['text' => 'Reviewer note after header-axis table.', 'bbox' => [72.0, 326.0, 500.0, 344.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 290.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 326.0, 500.0, 344.0]],
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

$review = $result['metadata']['table_spanning_grid_review'][0] ?? [
    'rows' => [],
    'cols' => [],
    'render_cells' => [],
    'grid_cells' => [],
];

$gridByPosition = [];
foreach ($review['grid_cells'] as $gridCell) {
    $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
}

$tableHtml = '<figure class="wp-block-table"><table><tbody>';
foreach ($review['rows'] as $rowId) {
    $tableHtml .= '<tr>';
    foreach ($review['cols'] as $colId) {
        $gridCell = $gridByPosition[$rowId . ':' . $colId] ?? ['state' => 'empty'];
        if (($gridCell['state'] ?? '') === 'covered') {
            continue;
        }

        $renderCell = isset($gridCell['render_cell_index'])
            ? ($review['render_cells'][(int) $gridCell['render_cell_index']] ?? null)
            : null;
        $tag = $renderCell['tag'] ?? 'td';
        $attrs = '';
        if (($renderCell['scope'] ?? null) !== null) {
            $attrs .= ' scope="' . htmlspecialchars((string) $renderCell['scope'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        if (($renderCell['header_axis'] ?? null) !== null) {
            $attrs .= ' data-markerpdf-header-axis="' . htmlspecialchars((string) $renderCell['header_axis'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        if (($renderCell['colspan'] ?? 1) > 1) {
            $attrs .= ' colspan="' . (int) $renderCell['colspan'] . '"';
        }
        if (($renderCell['rowspan'] ?? 1) > 1) {
            $attrs .= ' rowspan="' . (int) $renderCell['rowspan'] . '"';
        }

        $tableHtml .= '<' . $tag . $attrs . '>'
            . htmlspecialchars((string) ($renderCell['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</' . $tag . '>';
    }
    $tableHtml .= '</tr>';
}
$tableHtml .= '</tbody></table></figure>';

echo json_encode([
    'scenario' => 'wordpress-table-ocr-merged-cell-header-axis-currentbase',
    'native_boundary' => 'forced OCR merged table headers keep row/column header-axis review metadata before Markdown drops covered span cells',
    'source_truth' => 'tabled SpanTableCell preserves row_ids and col_ids; tabled markdown/html formatters render first-row headers from anchor cells with tabulate(headers="firstrow")',
    'table_needs_ocr' => $result['metadata']['table_needs_ocr'] ?? [],
    'table_cell_counts' => $result['metadata']['table_cell_counts'] ?? [],
    'table_spanning_grid_review' => $review,
    'wordpress_table_html' => $tableHtml,
    'corner_header_axis_both' => ($review['render_cells'][0]['header_axis'] ?? null) === 'both',
    'corner_header_axes' => $review['render_cells'][0]['header_axes'] ?? [],
    'has_corner_header_axis_attr' => str_contains($tableHtml, '<th scope="colgroup" data-markerpdf-header-axis="both" colspan="2" rowspan="2">Inventory axis</th>'),
    'has_column_header_axis_attr' => str_contains($tableHtml, '<th scope="col" data-markerpdf-header-axis="column">Status</th>'),
    'has_row_header_axis_attr' => str_contains($tableHtml, '<th scope="rowgroup" data-markerpdf-header-axis="row" rowspan="2">Media group</th>'),
    'covered_axis_cells_skipped' => !str_contains($tableHtml, '<td>axis</td>') && !str_contains($tableHtml, '<td>Inventory</td>') && str_contains($tableHtml, '<td>Images</td>'),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale OCR header-axis table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
