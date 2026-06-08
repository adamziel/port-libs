<?php

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

return [
    'table geometry page result surplus bbox boundary current base suppresses ghost table records' => static function (
        TestRunner $t
    ): void {
        $tempDir = sys_get_temp_dir() . '/markerpdf-page-result-surplus-bbox-' . uniqid('', true);
        if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            throw new RuntimeException('Unable to create temp directory for markerPDF test');
        }

        $pdfPath = $tempDir . '/surplus-bbox.pdf';
        file_put_contents($pdfPath, "%PDF-1.4\n% page result surplus bbox boundary fixture\n%%EOF");

        $page = [
            'page' => 0,
            'width' => 612,
            'height' => 792,
            'bbox' => [0.0, 0.0, 612.0, 792.0],
            'rotation' => 0,
            'blocks' => [
                [
                    'bbox' => [72, 700, 230, 720],
                    'lines' => [
                        [
                            'bbox' => [72, 700, 230, 720],
                            'spans' => [
                                [
                                    'text' => 'Before surplus bbox table results.',
                                    'bbox' => [72, 700, 230, 720],
                                    'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'bbox' => [72, 560, 320, 660],
                    'lines' => [
                        [
                            'bbox' => [82, 586, 280, 606],
                            'spans' => [
                                [
                                    'text' => 'First stale table line should be replaced.',
                                    'bbox' => [82, 586, 280, 606],
                                    'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'bbox' => [72, 420, 320, 520],
                    'lines' => [
                        [
                            'bbox' => [82, 446, 280, 466],
                            'spans' => [
                                [
                                    'text' => 'Second stale table line should be replaced.',
                                    'bbox' => [82, 446, 280, 466],
                                    'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'bbox' => [72, 380, 260, 400],
                    'lines' => [
                        [
                            'bbox' => [72, 380, 260, 400],
                            'spans' => [
                                [
                                    'text' => 'After surplus bbox table results.',
                                    'bbox' => [72, 380, 260, 400],
                                    'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $firstRowsCols = [
            'bbox' => [72, 560, 320, 660],
            'image_bbox' => [0, 0, 248, 100],
            'rows' => [
                ['bbox' => [0, 0, 248, 48]],
                ['bbox' => [0, 52, 248, 100]],
            ],
            'cols' => [
                ['bbox' => [0, 0, 122, 100]],
                ['bbox' => [126, 0, 248, 100]],
            ],
        ];

        $secondRowsCols = [
            'bbox' => [72, 420, 320, 520],
            'image_bbox' => [0, 0, 248, 100],
            'rows' => [
                ['bbox' => [0, 0, 248, 48]],
                ['bbox' => [0, 52, 248, 100]],
            ],
            'cols' => [
                ['bbox' => [0, 0, 122, 100]],
                ['bbox' => [126, 0, 248, 100]],
            ],
        ];

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $pdfPath,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Text', 'bbox' => [72, 700, 230, 720]],
                            ['label' => 'Table', 'bbox' => [72, 560, 320, 660]],
                            ['label' => 'Table', 'bbox' => [72, 420, 320, 520]],
                            ['label' => 'Text', 'bbox' => [72, 380, 260, 400]],
                        ],
                    ]],
                    'recognized_tables' => [[
                        'page_index' => 0,
                        'page_id' => 0,
                        'cells' => [
                            [
                                ['bbox' => [0, 0, 122, 48], 'text' => 'Alpha', 'row_ids' => [0], 'col_ids' => [0]],
                                ['bbox' => [126, 0, 248, 48], 'text' => 'Beta', 'row_ids' => [0], 'col_ids' => [1]],
                                ['bbox' => [0, 52, 122, 100], 'text' => 'Gamma', 'row_ids' => [1], 'col_ids' => [0]],
                                ['bbox' => [126, 52, 248, 100], 'text' => 'Delta', 'row_ids' => [1], 'col_ids' => [1]],
                            ],
                            [
                                ['bbox' => [0, 0, 122, 48], 'text' => 'One', 'row_ids' => [0], 'col_ids' => [0]],
                                ['bbox' => [126, 0, 248, 48], 'text' => 'Two', 'row_ids' => [0], 'col_ids' => [1]],
                                ['bbox' => [0, 52, 122, 100], 'text' => 'Three', 'row_ids' => [1], 'col_ids' => [0]],
                                ['bbox' => [126, 52, 248, 100], 'text' => 'Four', 'row_ids' => [1], 'col_ids' => [1]],
                            ],
                        ],
                        'rows_cols' => [$firstRowsCols, $secondRowsCols],
                        'bboxes' => [
                            ['bbox' => [72, 560, 320, 660]],
                            ['bbox' => [72, 420, 320, 520]],
                            ['bbox' => [72, 260, 320, 360]],
                        ],
                        'image_bboxes' => [
                            ['bbox' => [0, 0, 248, 100]],
                            ['bbox' => [0, 0, 248, 100]],
                            ['bbox' => [0, 0, 248, 100]],
                        ],
                    ]],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->contains('| Alpha | Beta  |', $result['text']);
            $t->contains('| One   | Two  |', $result['text']);
            $t->true(!str_contains($result['text'], 'First stale table line should be replaced.'));
            $t->true(!str_contains($result['text'], 'Second stale table line should be replaced.'));

            $pageResultReview = $result['metadata']['table_page_result_boundary_reviews'][0] ?? [];
            $t->same(2, $result['metadata']['inserted_tables'] ?? null);
            $t->same([
                [72.0, 560.0, 320.0, 660.0],
                [72.0, 420.0, 320.0, 520.0],
            ], $result['metadata']['table_plan']['table_bboxes'] ?? null);

            $t->same('table_page_result_boundary', $pageResultReview['review_target'] ?? null);
            $t->same('tabled.schema.ExtractPageResult', $pageResultReview['upstream_boundary'] ?? null);
            $t->same(2, $pageResultReview['table_count'] ?? null);
            $t->same(3, $pageResultReview['table_bbox_count'] ?? null);
            $t->same(3, $pageResultReview['image_bbox_count'] ?? null);
            $t->same(1, $pageResultReview['surplus_table_bbox_count'] ?? null);
            $t->same(1, $pageResultReview['surplus_image_bbox_count'] ?? null);
            $t->same(true, $pageResultReview['ghost_table_records_suppressed'] ?? null);
            $t->same('ExtractPageResult.cells', $pageResultReview['authoritative_table_count_source'] ?? null);
            $t->same([0, 1], $pageResultReview['flattened_table_indexes'] ?? null);
        } finally {
            @unlink($pdfPath);
            @rmdir($tempDir);
        }
    },
];
