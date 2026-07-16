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

$path = sys_get_temp_dir() . '/markerpdf-table-multiline-header-ocr-grid-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table multiline OCR header grid WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'Table multiline OCR header review', 'bbox' => [72.0, 48.0, 420.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale multiline OCR header table text should be replaced.', 'bbox' => [72.0, 176.0, 460.0, 196.0]],
                ['text' => 'Reviewer note after multiline table.', 'bbox' => [72.0, 306.0, 460.0, 324.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 420.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 260.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 306.0, 460.0, 324.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 358.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 35.0, 358.0, 60.0]],
                    ['row_id' => 2, 'bbox' => [0.0, 85.0, 358.0, 110.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 110.0, 110.0]],
                    ['col_id' => 1, 'bbox' => [124.0, 0.0, 238.0, 110.0]],
                    ['col_id' => 2, 'bbox' => [252.0, 0.0, 358.0, 110.0]],
                ],
            ]],
            'table_detector_cells' => [[
                ['bbox' => [5.0, 5.0, 353.0, 26.0], 'text' => null],
                ['bbox' => [5.0, 36.0, 106.0, 108.0], 'text' => null],
                ['bbox' => [128.0, 39.0, 232.0, 56.0], 'text' => null],
                ['bbox' => [258.0, 39.0, 348.0, 56.0], 'text' => null],
                ['bbox' => [128.0, 89.0, 232.0, 106.0], 'text' => null],
                ['bbox' => [258.0, 89.0, 348.0, 106.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [[
                'lines' => [
                    ['text' => 'Inventory', 'bbox' => [8.0, 6.0, 148.0, 14.0]],
                    ['text' => 'OCR summary', 'bbox' => [8.0, 16.0, 196.0, 24.0]],
                    ['text' => 'Media group', 'bbox' => [8.0, 42.0, 102.0, 55.0]],
                    ['text' => 'Image count', 'bbox' => [132.0, 42.0, 228.0, 55.0]],
                    ['text' => '12', 'bbox' => [262.0, 42.0, 284.0, 55.0]],
                    ['text' => 'Review state', 'bbox' => [132.0, 92.0, 228.0, 105.0]],
                    ['text' => 'Needs review', 'bbox' => [262.0, 92.0, 344.0, 105.0]],
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
    'scenario' => 'wordpress-table-multiline-header-ocr-grid-review-currentbase',
    'native_boundary' => 'bbox-bearing OCR line fragments are folded into detector cells before tabled-style assignment and WordPress header grid review',
    'source_truth' => 'tabled recognize_tables applies OCR text before assign_rows_columns, SpanTableCell keeps row_ids/col_ids, and markdown/html formatters render headers from the first row',
    'table_needs_ocr' => $result['metadata']['table_needs_ocr'] ?? [],
    'table_cell_counts' => $result['metadata']['table_cell_counts'] ?? [],
    'header_fragment_count' => 2,
    'table_spanning_grid_review' => $review,
    'wordpress_table_html' => $tableHtml,
    'joined_multiline_header_fragments' => ($review['render_cells'][0]['text'] ?? '') === 'Inventory OCR summary',
    'has_th_colgroup_colspan_3' => str_contains($tableHtml, '<th scope="colgroup" colspan="3">Inventory OCR summary</th>'),
    'has_th_rowgroup_rowspan_2' => str_contains($tableHtml, '<th scope="rowgroup" rowspan="2">Media group</th>'),
    'covered_header_cells_skipped' => !str_contains($tableHtml, '<td></td>') && str_contains($tableHtml, '<td>Image count</td>'),
    'preserved_tail_cell_text' => ($gridByPosition['2:2']['text'] ?? '') === 'Needs review',
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale multiline OCR header table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
