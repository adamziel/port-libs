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

$path = sys_get_temp_dir() . '/markerpdf-table-header-spanning-grid-review-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table header spanning grid WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'Table header grid review', 'bbox' => [72.0, 48.0, 360.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale table header text should be replaced.', 'bbox' => [72.0, 176.0, 430.0, 196.0]],
                ['text' => 'Reviewer note after table.', 'bbox' => [72.0, 306.0, 430.0, 324.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 260.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 306.0, 430.0, 324.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 358.0, 25.0]],
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
                ['bbox' => [5.0, 5.0, 353.0, 20.0], 'text' => null],
                ['bbox' => [5.0, 36.0, 106.0, 108.0], 'text' => null],
                ['bbox' => [128.0, 39.0, 232.0, 56.0], 'text' => null],
                ['bbox' => [258.0, 39.0, 348.0, 56.0], 'text' => null],
                ['bbox' => [128.0, 89.0, 232.0, 106.0], 'text' => null],
                ['bbox' => [258.0, 89.0, 348.0, 106.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [[
                'lines' => [
                    ['text' => 'Inventory OCR summary'],
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
    'scenario' => 'wordpress-table-header-spanning-grid-review-currentbase',
    'native_boundary' => 'supplied table recognition exposes first-row headers and spanning cells as a full WordPress render grid before Markdown drops non-anchor occupancy',
    'source_truth' => 'tabled markdown/html formatters use tabulate(headers="firstrow") and SpanTableCell row_ids/col_ids retain row and column span occupancy',
    'table_spanning_grid_review' => $review,
    'wordpress_table_html' => $tableHtml,
    'has_th_colgroup_colspan_3' => str_contains($tableHtml, '<th scope="colgroup" colspan="3">Inventory OCR summary</th>'),
    'has_th_rowgroup_rowspan_2' => str_contains($tableHtml, '<th scope="rowgroup" rowspan="2">Media group</th>'),
    'covered_header_cells_skipped' => !str_contains($tableHtml, '<td></td>') && str_contains($tableHtml, '<td>Image count</td>'),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale table header text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
