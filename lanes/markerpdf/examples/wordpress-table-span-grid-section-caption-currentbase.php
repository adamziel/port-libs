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

$path = sys_get_temp_dir() . '/markerpdf-table-span-grid-section-caption-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table span grid section caption fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'Import metrics', 'bbox' => [72.0, 48.0, 290.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale section caption table text should be replaced.', 'bbox' => [72.0, 176.0, 510.0, 196.0]],
                ['text' => 'Table 4: Review metrics from tabled grid.', 'bbox' => [72.0, 272.0, 430.0, 290.0]],
                ['text' => 'Reviewer note after caption.', 'bbox' => [72.0, 326.0, 440.0, 344.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Section-header', 'bbox' => [72.0, 48.0, 290.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 260.0]],
                    ['label' => 'Caption', 'bbox' => [72.0, 272.0, 430.0, 290.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 326.0, 440.0, 344.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 25.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 35.0, 300.0, 60.0]],
                    ['row_id' => 2, 'bbox' => [0.0, 85.0, 300.0, 110.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 95.0, 120.0]],
                    ['col_id' => 1, 'bbox' => [105.0, 0.0, 195.0, 120.0]],
                    ['col_id' => 2, 'bbox' => [205.0, 0.0, 300.0, 120.0]],
                ],
            ]],
            'table_detector_cells' => [[
                ['bbox' => [5.0, 5.0, 295.0, 20.0], 'text' => null],
                ['bbox' => [5.0, 36.0, 92.0, 109.0], 'text' => null],
                ['bbox' => [110.0, 39.0, 190.0, 56.0], 'text' => null],
                ['bbox' => [210.0, 39.0, 290.0, 56.0], 'text' => null],
                ['bbox' => [110.0, 89.0, 190.0, 106.0], 'text' => null],
                ['bbox' => [210.0, 89.0, 290.0, 106.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [[
                'lines' => [
                    ['text' => 'Inventory summary'],
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

$tableHtml = '<figure class="wp-block-table"><table>';
$caption = $context['caption']['text'] ?? '';
if ($caption !== '') {
    $tableHtml .= '<caption>' . htmlspecialchars((string) $caption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</caption>';
}
$tableHtml .= '<tbody>';
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

$sectionHeading = '<!-- wp:heading --><h2>' . htmlspecialchars((string) ($context['section']['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h2><!-- /wp:heading -->';

echo json_encode([
    'scenario' => 'wordpress-table-span-grid-section-caption-currentbase',
    'native_boundary' => 'section and caption blocks that markerPDF leaves around replaced tables are bound to native table span-grid review metadata for WordPress import',
    'source_truth' => 'marker.tables.table::format_tables only removes intersecting Table blocks; marker.postprocessors.markdown wraps Section-header, Caption, and Table separately; tabled SpanTableCell keeps row_ids/col_ids before markdown/html drop covered span occupancy',
    'section_text' => $context['section']['text'] ?? null,
    'caption_text' => $context['caption']['text'] ?? null,
    'caption_position' => $context['caption']['position'] ?? null,
    'spanning_grid' => $context['spanning_grid'] ?? [],
    'wordpress_heading_html' => $sectionHeading,
    'wordpress_table_html' => $tableHtml,
    'has_section_heading' => str_contains($sectionHeading, '<h2>Import metrics</h2>'),
    'has_caption_element' => str_contains($tableHtml, '<caption>Table 4: Review metrics from tabled grid.</caption>'),
    'has_colgroup_span' => str_contains($tableHtml, '<th id="h-r0-c0" scope="colgroup" colspan="3">Inventory summary</th>'),
    'has_rowgroup_span' => str_contains($tableHtml, '<th id="h-r1-c0" scope="rowgroup" rowspan="2">Media group</th>'),
    'maps_data_to_section_caption_grid' => ($context['section']['review_target'] ?? null) === 'table_span_grid'
        && ($context['caption']['review_target'] ?? null) === 'table_span_grid'
        && (($context['spanning_grid']['has_colspan'] ?? false) === true)
        && (($context['spanning_grid']['has_rowspan'] ?? false) === true),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale section caption table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
