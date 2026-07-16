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

$path = sys_get_temp_dir() . '/markerpdf-table-ocr-merged-cell-geometry-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% OCR merged-cell geometry WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'OCR table geometry review', 'bbox' => [72.0, 48.0, 360.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale OCR table line should be replaced.', 'bbox' => [72.0, 176.0, 430.0, 196.0]],
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

$assigned = $result['metadata']['table_assigned_cells'][0] ?? [];
$geometry = $result['metadata']['table_merged_cell_geometry'][0] ?? [];
$spansByAnchor = [];
$coveredCells = [];
foreach ($geometry as $span) {
    $anchor = $span['anchor']['row_id'] . ':' . $span['anchor']['col_id'];
    $spansByAnchor[$anchor] = $span;
    foreach ($span['grid_cells'] as $gridCell) {
        $key = $gridCell['row_id'] . ':' . $gridCell['col_id'];
        if ($key !== $anchor) {
            $coveredCells[$key] = true;
        }
    }
}

$cellText = [];
$rows = [];
$cols = [];
foreach ($assigned as $cell) {
    $row = (int) $cell['row_ids'][0];
    $col = (int) $cell['col_ids'][0];
    $rows[$row] = true;
    $cols[$col] = true;
    $cellText[$row . ':' . $col] = (string) $cell['text'];
}
ksort($rows, SORT_NUMERIC);
ksort($cols, SORT_NUMERIC);

$tableHtml = '<figure class="wp-block-table"><table><tbody>';
foreach (array_keys($rows) as $row) {
    $tableHtml .= '<tr>';
    foreach (array_keys($cols) as $col) {
        $key = $row . ':' . $col;
        if (isset($coveredCells[$key])) {
            continue;
        }

        $attrs = '';
        $span = $spansByAnchor[$key] ?? null;
        if ($span !== null && $span['colspan'] > 1) {
            $attrs .= ' colspan="' . $span['colspan'] . '"';
        }
        if ($span !== null && $span['rowspan'] > 1) {
            $attrs .= ' rowspan="' . $span['rowspan'] . '"';
        }

        $tableHtml .= '<td' . $attrs . '>' . htmlspecialchars($cellText[$key] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    }
    $tableHtml .= '</tr>';
}
$tableHtml .= '</tbody></table></figure>';

echo json_encode([
    'scenario' => 'wordpress-table-ocr-merged-cell-geometry-currentbase',
    'native_boundary' => 'forced OCR table cells preserve tabled row_ids and col_ids as merged-cell review geometry before Markdown drops span occupancy',
    'table_needs_ocr' => $result['metadata']['table_needs_ocr'] ?? [],
    'table_cell_counts' => $result['metadata']['table_cell_counts'] ?? [],
    'merged_cell_geometry' => $geometry,
    'wordpress_table_html' => $tableHtml,
    'has_colspan_3' => str_contains($tableHtml, 'colspan="3"'),
    'has_rowspan_2' => str_contains($tableHtml, 'rowspan="2"'),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale OCR table line should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
