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

$path = sys_get_temp_dir() . '/markerpdf-table-header-grid-rowspan-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table header grid rowspan fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'Rowspanned table header grid review', 'bbox' => [72.0, 48.0, 450.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale rowspanned header table text should be replaced.', 'bbox' => [72.0, 176.0, 510.0, 196.0]],
                ['text' => 'Reviewer note after rowspanned header table.', 'bbox' => [72.0, 306.0, 520.0, 324.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 450.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 260.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 306.0, 520.0, 324.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 320.0, 28.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 32.0, 320.0, 60.0]],
                    ['row_id' => 2, 'bbox' => [0.0, 72.0, 320.0, 100.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 90.0, 110.0]],
                    ['col_id' => 1, 'bbox' => [100.0, 0.0, 200.0, 110.0]],
                    ['col_id' => 2, 'bbox' => [210.0, 0.0, 320.0, 110.0]],
                ],
            ]],
            'table_detector_cells' => [[
                ['bbox' => [5.0, 5.0, 85.0, 45.0], 'text' => null],
                ['bbox' => [105.0, 5.0, 315.0, 24.0], 'text' => null],
                ['bbox' => [110.0, 36.0, 190.0, 56.0], 'text' => null],
                ['bbox' => [220.0, 36.0, 310.0, 56.0], 'text' => null],
                ['bbox' => [5.0, 76.0, 85.0, 96.0], 'text' => null],
                ['bbox' => [110.0, 76.0, 190.0, 96.0], 'text' => null],
                ['bbox' => [220.0, 76.0, 310.0, 96.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [[
                'lines' => [
                    ['text' => 'Import group'],
                    ['text' => 'Assets'],
                    ['text' => 'Images'],
                    ['text' => 'State'],
                    ['text' => 'Media'],
                    ['text' => '12'],
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

$review = $result['metadata']['table_spanning_grid_review'][0] ?? [
    'rows' => [],
    'cols' => [],
    'column_header_rows' => [],
    'render_cells' => [],
    'grid_cells' => [],
    'header_cells' => [],
    'data_cells' => [],
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
        if (($renderCell['header_id'] ?? null) !== null) {
            $attrs .= ' id="' . htmlspecialchars((string) $renderCell['header_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        if (($renderCell['headers'] ?? []) !== []) {
            $attrs .= ' headers="' . htmlspecialchars(implode(' ', $renderCell['headers']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        if (($renderCell['scope'] ?? null) !== null) {
            $attrs .= ' scope="' . htmlspecialchars((string) $renderCell['scope'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
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
    'scenario' => 'wordpress-table-header-grid-rowspan-currentbase',
    'native_boundary' => 'top-row table header rowspans promote covered header rows into WordPress column-header grid references before Markdown drops span occupancy',
    'source_truth' => 'tabled SpanTableCell preserves row_ids/col_ids from handle_rowcol_spans, while markdown/html formatters consume first-row/column anchors through tabulate(headers="firstrow")',
    'table_needs_ocr' => $result['metadata']['table_needs_ocr'] ?? [],
    'table_cell_counts' => $result['metadata']['table_cell_counts'] ?? [],
    'column_header_rows' => $review['column_header_rows'],
    'header_ids' => array_column($review['header_cells'], 'header_id'),
    'data_headers' => array_column($review['data_cells'], 'headers'),
    'table_spanning_grid_review' => $review,
    'wordpress_table_html' => $tableHtml,
    'has_rowspanned_corner_header' => str_contains($tableHtml, '<th id="h-r0-c0" scope="col" rowspan="2">Import group</th>'),
    'has_assets_group_header' => str_contains($tableHtml, '<th id="h-r0-c1" scope="colgroup" colspan="2">Assets</th>'),
    'has_images_subheader' => str_contains($tableHtml, '<th id="h-r1-c1" scope="col">Images</th>'),
    'has_state_subheader' => str_contains($tableHtml, '<th id="h-r1-c2" scope="col">State</th>'),
    'maps_count_to_group_and_subheader' => str_contains($tableHtml, '<td headers="h-r0-c1 h-r1-c1">12</td>'),
    'maps_ready_to_group_and_subheader' => str_contains($tableHtml, '<td headers="h-r0-c1 h-r1-c2">Ready</td>'),
    'covered_rowspan_header_cell_skipped' => !str_contains($tableHtml, '<td>Import group</td>'),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale rowspanned header table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
