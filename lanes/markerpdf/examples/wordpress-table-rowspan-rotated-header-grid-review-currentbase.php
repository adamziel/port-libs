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

$path = sys_get_temp_dir() . '/markerpdf-table-rowspan-rotated-header-grid-review-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table rotated header grid WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'Rotated table grid review', 'bbox' => [72.0, 48.0, 360.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale rotated table text should be replaced.', 'bbox' => [72.0, 176.0, 430.0, 196.0]],
                ['text' => 'Reviewer note after rotated table.', 'bbox' => [72.0, 430.0, 430.0, 448.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 260.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 430.0, 430.0, 448.0]],
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

$review = $result['metadata']['table_spanning_grid_review'][0] ?? [
    'rows' => [],
    'cols' => [],
    'rotated' => false,
    'orientation' => 'normal',
    'row_axis' => 'y',
    'col_axis' => 'x',
    'render_cells' => [],
    'grid_cells' => [],
    'accessibility_grid' => [],
];
$accessibilityGrid = $review['accessibility_grid'] ?? [];

$gridByPosition = [];
foreach ($review['grid_cells'] as $gridCell) {
    $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
}

$tableHtml = '<figure class="wp-block-table"'
    . ' data-markerpdf-orientation="' . htmlspecialchars((string) $review['orientation'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
    . ' data-markerpdf-row-axis="' . htmlspecialchars((string) $review['row_axis'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
    . ' data-markerpdf-col-axis="' . htmlspecialchars((string) $review['col_axis'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . '<table><tbody>';
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
        if (($renderCell['rotated'] ?? false) === true) {
            $attrs .= ' data-markerpdf-rotated="true"';
        }
        if (($renderCell['column_header_physical_axis'] ?? null) !== null) {
            $attrs .= ' data-markerpdf-column-header-axis="' . htmlspecialchars((string) $renderCell['column_header_physical_axis'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        if (($renderCell['row_header_physical_axis'] ?? null) !== null) {
            $attrs .= ' data-markerpdf-row-header-axis="' . htmlspecialchars((string) $renderCell['row_header_physical_axis'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        $tableHtml .= '<' . $tag . $attrs . '>'
            . htmlspecialchars((string) ($renderCell['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</' . $tag . '>';
    }
    $tableHtml .= '</tr>';
}
$tableHtml .= '</tbody></table></figure>';

echo json_encode([
    'scenario' => 'wordpress-table-rowspan-rotated-header-grid-review-currentbase',
    'native_boundary' => 'supplied rotated table recognition exposes tabled row_ids/col_ids spans plus swapped grid axes before Markdown drops covered cells',
    'source_truth' => 'tabled.assignment.is_rotated swaps row/column intersection axes, while tabled markdown/html formatters still use only first row/column anchors with headers="firstrow"',
    'table_spanning_grid_review' => $review,
    'table_accessibility_grid' => $accessibilityGrid,
    'wordpress_table_html' => $tableHtml,
    'rotated_grid_review' => ($review['rotated'] ?? false) === true,
    'row_axis_x_col_axis_y' => ($review['row_axis'] ?? null) === 'x' && ($review['col_axis'] ?? null) === 'y',
    'has_th_colgroup_colspan_3' => str_contains($tableHtml, '<th id="h-r0-c0" scope="colgroup" colspan="3" data-markerpdf-rotated="true">Rotated inventory</th>'),
    'has_th_rowgroup_rowspan_2' => str_contains($tableHtml, '<th id="h-r1-c0" scope="rowgroup" rowspan="2" data-markerpdf-rotated="true">Media group</th>'),
    'has_rotated_accessibility_grid' => ($accessibilityGrid['review_target'] ?? null) === 'table_rotated_header_accessibility_grid',
    'column_headers_follow_y_axis' => ($accessibilityGrid['column_header_physical_axis'] ?? null) === 'y',
    'row_headers_follow_x_axis' => ($accessibilityGrid['row_header_physical_axis'] ?? null) === 'x',
    'maps_image_count_to_rotated_headers' => str_contains($tableHtml, '<td headers="h-r0-c0 h-r1-c0" data-markerpdf-rotated="true" data-markerpdf-column-header-axis="y" data-markerpdf-row-header-axis="x">Image count</td>'),
    'maps_needs_review_to_rotated_headers' => str_contains($tableHtml, '<td headers="h-r0-c0 h-r1-c0" data-markerpdf-rotated="true" data-markerpdf-column-header-axis="y" data-markerpdf-row-header-axis="x">Needs review</td>'),
    'rotated_rowspan_grid_bbox' => $review['render_cells'][1]['grid_bbox'] ?? null,
    'covered_rotated_cells_skipped' => !str_contains($tableHtml, '<td></td>') && str_contains($tableHtml, '<td headers="h-r0-c0 h-r1-c0" data-markerpdf-rotated="true" data-markerpdf-column-header-axis="y" data-markerpdf-row-header-axis="x">Review state</td>'),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale rotated table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
