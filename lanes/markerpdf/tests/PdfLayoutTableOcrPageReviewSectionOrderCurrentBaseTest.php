<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$layoutTableOcrSectionOrderLine = static fn (
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

return [
    'preserves layout ordered OCR table section and page review metadata' => static function (TestRunner $t) use ($layoutTableOcrSectionOrderLine): void {
        $path = sys_get_temp_dir() . '/markerpdf-layout-table-ocr-section-order-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% layout table OCR section order current-base fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [[
                    'page' => 0,
                    'bbox' => [0.0, 0.0, 612.0, 792.0],
                    'rotation' => 0,
                    'blocks' => [[
                        'lines' => [
                            $layoutTableOcrSectionOrderLine('Table 12: OCR order metrics.', [72.0, 272.0, 430.0, 290.0]),
                            $layoutTableOcrSectionOrderLine('Stale unordered OCR table text should be replaced.', [72.0, 176.0, 510.0, 196.0]),
                            $layoutTableOcrSectionOrderLine('Reviewer note after ordered table.', [72.0, 326.0, 470.0, 344.0]),
                            $layoutTableOcrSectionOrderLine('Ordered table imports', [72.0, 48.0, 360.0, 68.0], 'Heading-Bold', 700, 18),
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

        $context = $result['metadata']['table_section_caption_review'][0] ?? [];
        $sectionOrder = $context['section_order'] ?? [];
        $orderByRole = [];
        foreach (($sectionOrder['block_order'] ?? []) as $entry) {
            $orderByRole[$entry['role']] = $entry;
        }

        $t->contains('## Ordered Table Imports', $result['text']);
        $t->contains('| Metric        | Status    |', $result['text']);
        $t->contains('| Reading order | Preserved |', $result['text']);
        $t->contains('Table 12: OCR order metrics.', $result['text']);
        $t->contains('Reviewer note after ordered table.', $result['text']);
        $t->true(!str_contains($result['text'], 'Stale unordered OCR table text should be replaced.'));
        $t->true(strpos($result['text'], '## Ordered Table Imports') < strpos($result['text'], '| Metric'));
        $t->true(strpos($result['text'], '| Reading order | Preserved |') < strpos($result['text'], 'Table 12: OCR order metrics.'));
        $t->same(['layout', 'order', 'page-review-metadata', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);

        $t->same('layout_table_ocr_page_review_section_order', $sectionOrder['review_target']);
        $t->same('sort_blocks_in_reading_order_then_format_tables', $sectionOrder['upstream_stage']);
        $t->same(['section', 'table', 'caption'], $sectionOrder['final_role_order']);
        $t->same([0, 1, 2], array_column($sectionOrder['block_order'], 'final_index'));
        $t->same([0, 1, 2], array_column($sectionOrder['block_order'], 'order_position'));
        $t->same(true, $sectionOrder['section_before_table']);
        $t->same(true, $sectionOrder['caption_after_table']);
        $t->same(true, $sectionOrder['page_review_attached']);
        $t->same(false, $sectionOrder['visible_text_source']);
        $t->same('Ordered table imports', $orderByRole['section']['text']);
        $t->same('Table 12: OCR order metrics.', $orderByRole['caption']['text']);
        $t->same([72.0, 150.0, 430.0, 260.0], $orderByRole['table']['page_bbox']);
        $t->same(1.0, $orderByRole['table']['order_intersection_pct']);

        $pageReview = $context['page_review'] ?? [];
        $t->same('ocr-order-12', $pageReview['page_label']);
        $t->same(1, $pageReview['annotation_structure_parent_count']);
        $t->same([21], array_column($pageReview['annotation_structure_parent_rows'], 'annotation_object'));
        $t->same(['Table', 'Caption'], $pageReview['structure_roles']);
        $t->same(true, $pageReview['review_only']);
        $t->same(false, str_contains(json_encode($result['metadata'], JSON_UNESCAPED_SLASHES) ?: '', 'Outside review annotation'));
    },
];
