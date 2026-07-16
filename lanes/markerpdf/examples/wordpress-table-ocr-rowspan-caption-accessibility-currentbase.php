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

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$path = sys_get_temp_dir() . '/markerpdf-table-rowspan-caption-accessibility-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% table OCR rowspan caption accessibility fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage([
                ['text' => 'Import asset metrics', 'bbox' => [72.0, 48.0, 360.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale accessible rowspan table text should be replaced.', 'bbox' => [72.0, 176.0, 510.0, 196.0]],
                ['text' => 'Table 7: Asset OCR review counts.', 'bbox' => [72.0, 282.0, 430.0, 300.0]],
                ['text' => 'Reviewer note after accessible table.', 'bbox' => [72.0, 326.0, 480.0, 344.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Section-header', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 270.0]],
                    ['label' => 'Caption', 'bbox' => [72.0, 282.0, 430.0, 300.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 326.0, 480.0, 344.0]],
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

$context = $result['metadata']['table_section_caption_review'][0] ?? [];
$accessibility = $context['accessibility'] ?? [];
$review = $result['metadata']['table_spanning_grid_review'][0] ?? [
    'rows' => [],
    'cols' => [],
    'render_cells' => [],
    'grid_cells' => [],
];

$gridByPosition = [];
foreach (($review['grid_cells'] ?? []) as $gridCell) {
    $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
}

$sectionHtml = '<!-- wp:heading --><h2 id="' . $escape((string) ($accessibility['section_id'] ?? '')) . '">'
    . $escape((string) ($context['section']['text'] ?? '')) . "</h2><!-- /wp:heading -->";

$tableAttrs = '';
if (($accessibility['table_id'] ?? '') !== '') {
    $tableAttrs .= ' id="' . $escape((string) $accessibility['table_id']) . '"';
}
if (($accessibility['aria_describedby'] ?? []) !== []) {
    $tableAttrs .= ' aria-describedby="' . $escape(implode(' ', $accessibility['aria_describedby'])) . '"';
}
if (($accessibility['aria_labelledby'] ?? []) !== []) {
    $tableAttrs .= ' aria-labelledby="' . $escape(implode(' ', $accessibility['aria_labelledby'])) . '"';
}

$tableHtml = '<figure class="wp-block-table"><table' . $tableAttrs . '>';
if (($accessibility['caption_id'] ?? null) !== null) {
    $tableHtml .= '<caption id="' . $escape((string) $accessibility['caption_id']) . '">'
        . $escape((string) ($accessibility['caption_text'] ?? '')) . '</caption>';
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
            $attrs .= ' id="' . $escape((string) $renderCell['header_id']) . '"';
        }
        if (($renderCell['headers'] ?? []) !== []) {
            $attrs .= ' headers="' . $escape(implode(' ', $renderCell['headers'])) . '"';
        }
        if (($renderCell['scope'] ?? null) !== null) {
            $attrs .= ' scope="' . $escape((string) $renderCell['scope']) . '"';
        }
        if (($renderCell['colspan'] ?? 1) > 1) {
            $attrs .= ' colspan="' . (int) $renderCell['colspan'] . '"';
        }
        if (($renderCell['rowspan'] ?? 1) > 1) {
            $attrs .= ' rowspan="' . (int) $renderCell['rowspan'] . '"';
        }

        $tableHtml .= '<' . $tag . $attrs . '>'
            . $escape((string) ($renderCell['text'] ?? ''))
            . '</' . $tag . '>';
    }
    $tableHtml .= '</tr>';
}
$tableHtml .= '</tbody></table></figure>';

$dataByText = [];
foreach (($accessibility['data_cell_headers'] ?? []) as $dataCell) {
    $dataByText[$dataCell['text']] = $dataCell;
}

echo json_encode([
    'scenario' => 'wordpress-table-ocr-rowspan-caption-accessibility-currentbase',
    'native_boundary' => 'forced OCR rowspanned table headers bind preserved markerPDF Caption blocks to accessible WordPress table ids and headers attributes',
    'source_truth' => 'marker.tables.table::format_tables preserves surrounding Caption blocks; marker.postprocessors.markdown renders Caption separately; tabled SpanTableCell keeps row_ids/col_ids while markdown/html formatters consume first-row/column anchors',
    'accessibility' => $accessibility,
    'wordpress_section_html' => $sectionHtml,
    'wordpress_table_html' => $tableHtml,
    'has_table_labelledby_section' => str_contains($tableHtml, 'aria-labelledby="markerpdf-table-0-section"'),
    'has_table_describedby_caption' => str_contains($tableHtml, 'aria-describedby="markerpdf-table-0-caption"'),
    'has_caption_id' => str_contains($tableHtml, '<caption id="markerpdf-table-0-caption">Table 7: Asset OCR review counts.</caption>'),
    'has_rowspanned_header' => str_contains($tableHtml, '<th id="h-r0-c0" scope="col" rowspan="2">Import group</th>'),
    'maps_count_to_group_subheader_and_caption' => ($dataByText['12']['headers'] ?? []) === ['h-r0-c1', 'h-r1-c1']
        && ($dataByText['12']['caption_id'] ?? null) === 'markerpdf-table-0-caption',
    'maps_ready_to_state_subheader' => str_contains($tableHtml, '<td headers="h-r0-c1 h-r1-c2">Ready</td>'),
    'covered_rowspan_header_cell_skipped' => !str_contains($tableHtml, '<td>Import group</td>'),
    'excluded_stale_pdftext_table_line' => !str_contains($result['text'], 'Stale accessible rowspan table text should be replaced.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $result['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
