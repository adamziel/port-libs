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

$path = sys_get_temp_dir() . '/markerpdf-table-spans-ocr-grid-geometry-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table spans OCR grid geometry fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'OCR span grid geometry review', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale OCR span table text should be replaced.', 'bbox' => [72.0, 176.0, 460.0, 196.0]],
                ['text' => 'Reviewer note after span table.', 'bbox' => [72.0, 306.0, 480.0, 324.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 260.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 306.0, 480.0, 324.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 32.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 35.0, 300.0, 60.0]],
                    ['row_id' => 2, 'bbox' => [0.0, 85.0, 300.0, 110.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 120.0]],
                    ['col_id' => 1, 'bbox' => [105.0, 0.0, 195.0, 120.0]],
                    ['col_id' => 2, 'bbox' => [205.0, 0.0, 300.0, 120.0]],
                ],
            ]],
            'table_detector_cells' => [[
                ['bbox' => [5.0, 5.0, 295.0, 18.0], 'text' => null],
                ['bbox' => [30.0, 20.0, 270.0, 30.0], 'text' => null],
                ['bbox' => [5.0, 40.0, 90.0, 108.0], 'text' => null],
                ['bbox' => [120.0, 42.0, 180.0, 56.0], 'text' => null],
                ['bbox' => [210.0, 42.0, 250.0, 56.0], 'text' => null],
                ['bbox' => [120.0, 90.0, 180.0, 106.0], 'text' => null],
                ['bbox' => [210.0, 90.0, 290.0, 106.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [[
                'lines' => [
                    ['text' => 'Inventory'],
                    ['text' => 'continued'],
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
    'scenario' => 'wordpress-table-spans-ocr-grid-geometry-currentbase',
    'native_boundary' => 'forced OCR table cells preserve SpanTableCell row_ids/col_ids and expose row/column-band grid bboxes for WordPress rowspan/colspan review',
    'source_truth' => 'marker/tables/table.py formats tabled assigned cells; tabled SpanTableCell keeps row_ids and col_ids while Markdown/HTML use first-row/column anchors',
    'table_needs_ocr' => $result['metadata']['table_needs_ocr'] ?? [],
    'table_cell_counts' => $result['metadata']['table_cell_counts'] ?? [],
    'anchor_grid_bbox' => $review['render_cells'][0]['grid_bbox'] ?? null,
    'anchor_grid_cell_bboxes' => $review['render_cells'][0]['grid_cell_bboxes'] ?? [],
    'body_header_grid_bbox' => $review['render_cells'][1]['grid_bbox'] ?? null,
    'covered_col_header_grid_bbox' => $gridByPosition['0:1']['grid_bbox'] ?? null,
    'covered_row_header_grid_bbox' => $gridByPosition['2:0']['grid_bbox'] ?? null,
    'value_cell_grid_bbox' => $gridByPosition['2:2']['grid_bbox'] ?? null,
    'has_th_colgroup_colspan_3' => str_contains($tableHtml, '<th scope="colgroup" colspan="3">Inventory continued</th>'),
    'has_th_rowgroup_rowspan_2' => str_contains($tableHtml, '<th scope="rowgroup" rowspan="2">Media group</th>'),
    'covered_cells_skipped' => !str_contains($tableHtml, '<td></td>') && str_contains($tableHtml, '<td>Images</td>'),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale OCR span table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'wordpress_table_html' => $tableHtml,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
