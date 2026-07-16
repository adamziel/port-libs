<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$line = static fn (
    string $text,
    array $bbox,
    string $font = 'Times-Roman',
    int $weight = 400,
    int $size = 11
): array => [
    'bbox' => $bbox,
    'spans' => [[
        'text' => $text,
        'bbox' => $bbox,
        'font' => [
            'name' => $font,
            'flags' => 0,
            'weight' => $weight,
            'size' => $size,
        ],
    ]],
];

$path = sys_get_temp_dir() . '/markerpdf-layout-table-ocr-section-order-smoke-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% layout table OCR section-order smoke\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $path,
        [[
            'page' => 0,
            'bbox' => [0.0, 0.0, 612.0, 792.0],
            'rotation' => 0,
            'blocks' => [[
                'lines' => [
                    $line('Table 12: OCR order metrics.', [72.0, 272.0, 430.0, 290.0]),
                    $line('Stale unordered OCR table text should be replaced.', [72.0, 176.0, 510.0, 196.0]),
                    $line('Reviewer note after ordered table.', [72.0, 326.0, 470.0, 344.0]),
                    $line('Ordered table imports', [72.0, 48.0, 360.0, 68.0], 'Heading-Bold', 700, 18),
                ],
            ]],
        ]],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Section-header', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 260.0]],
                    ['label' => 'Caption', 'bbox' => [72.0, 272.0, 430.0, 290.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 326.0, 470.0, 344.0]],
                ],
            ]],
            'order_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 0, 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                    ['position' => 1, 'bbox' => [72.0, 150.0, 430.0, 260.0]],
                    ['position' => 2, 'bbox' => [72.0, 272.0, 430.0, 290.0]],
                    ['position' => 3, 'bbox' => [72.0, 326.0, 470.0, 344.0]],
                ],
            ]],
            'page_review_metadata' => [[
                'pnum' => 0,
                'page_number' => 1,
                'page_label' => 'ocr-order-12',
                'page_object' => 14,
                'struct_parents' => 3,
                'piece_info' => [
                    'WPTableOrder' => [
                        'private' => [
                            'ReviewStage' => 'layout-table-ocr-section-order',
                            'NeedsReview' => true,
                        ],
                    ],
                ],
                'structure_marked_content' => [
                    ['mcid' => 0, 'role' => 'Table', 'title' => 'Ordered OCR table structure'],
                    ['mcid' => 1, 'role' => 'Caption', 'title' => 'Ordered OCR caption structure'],
                ],
                'annotation_structure_parent_rows' => [
                    [
                        'annotation_object' => 21,
                        'struct_parent' => 9,
                        'rect' => [72.0, 150.0, 430.0, 260.0],
                        'structure_parent' => ['title' => 'Table review annotation'],
                    ],
                    [
                        'annotation_object' => 22,
                        'struct_parent' => 10,
                        'rect' => [72.0, 500.0, 430.0, 540.0],
                        'structure_parent' => ['title' => 'Outside review annotation'],
                    ],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 28.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 36.0, 300.0, 64.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 140.0, 70.0]],
                    ['col_id' => 1, 'bbox' => [160.0, 0.0, 300.0, 70.0]],
                ],
            ]],
            'table_detector_cells' => [[
                ['bbox' => [8.0, 6.0, 132.0, 24.0], 'text' => null],
                ['bbox' => [168.0, 6.0, 292.0, 24.0], 'text' => null],
                ['bbox' => [8.0, 42.0, 132.0, 60.0], 'text' => null],
                ['bbox' => [168.0, 42.0, 292.0, 60.0], 'text' => null],
            ]],
            'table_ocr_text_lines' => [[
                'lines' => [
                    ['text' => 'Metric'],
                    ['text' => 'Status'],
                    ['text' => 'Reading order'],
                    ['text' => 'Preserved'],
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

$context = $converted['metadata']['table_section_caption_review'][0] ?? [];
$sectionOrder = is_array($context['section_order'] ?? null) ? $context['section_order'] : [];
$pageReview = is_array($context['page_review'] ?? null) ? $context['page_review'] : [];
$annotationRows = is_array($pageReview['annotation_structure_parent_rows'] ?? null)
    ? $pageReview['annotation_structure_parent_rows']
    : [];

if (($sectionOrder['final_role_order'] ?? []) !== ['section', 'table', 'caption']) {
    throw new RuntimeException('Expected section, table, and caption to stay linked in layout reading order.');
}
if (($pageReview['page_label'] ?? null) !== 'ocr-order-12' || (($annotationRows[0]['annotation_object'] ?? null) !== 21)) {
    throw new RuntimeException('Expected overlapping page review metadata to attach to ordered table review.');
}
if (str_contains($converted['text'], 'Stale unordered OCR table text should be replaced.')
    || str_contains(json_encode($converted['metadata'], JSON_UNESCAPED_SLASHES) ?: '', 'Outside review annotation')
) {
    throw new RuntimeException('Expected stale table text and outside page-review annotation to stay excluded.');
}

echo json_encode([
    'scenario' => 'wordpress-layout-table-ocr-page-review-section-order-currentbase',
    'native_boundary' => 'layout reading-order sorted sections stay bound to forced-OCR table replacement, caption, and page-review metadata for WordPress import',
    'source_truth' => 'marker.convert runs annotate_block_types, surya_order, sort_blocks_in_reading_order, then marker.tables.table::format_tables removes only intersecting Table blocks and inserts recognized table Markdown at that sorted position',
    'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
    'wordpress_blocks' => [
        ['blockName' => 'core/heading', 'innerHTML' => '<h2>Ordered Table Imports</h2>'],
        ['blockName' => 'core/table', 'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Metric</td><td>Status</td></tr><tr><td>Reading order</td><td>Preserved</td></tr></tbody></table></figure>'],
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>Table 12: OCR order metrics.</p>'],
    ],
    'section_order' => $sectionOrder,
    'table_page_review' => [
        'page_label' => $pageReview['page_label'] ?? null,
        'page_object' => $pageReview['page_object'] ?? null,
        'structure_roles' => $pageReview['structure_roles'] ?? [],
        'annotation_objects' => array_column($annotationRows, 'annotation_object'),
        'annotation_struct_parents' => $pageReview['annotation_struct_parents'] ?? [],
        'review_only' => $pageReview['review_only'] ?? null,
        'visible_text_source' => $pageReview['visible_text_source'] ?? null,
    ],
    'section_before_table' => ($sectionOrder['section_before_table'] ?? false) === true,
    'caption_after_table' => ($sectionOrder['caption_after_table'] ?? false) === true,
    'page_review_attached' => ($sectionOrder['page_review_attached'] ?? false) === true,
    'excluded_stale_table_text' => !str_contains($converted['text'], 'Stale unordered OCR table text should be replaced.'),
    'excluded_outside_page_review' => !str_contains(json_encode($converted['metadata'], JSON_UNESCAPED_SLASHES) ?: '', 'Outside review annotation'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $converted['text'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
