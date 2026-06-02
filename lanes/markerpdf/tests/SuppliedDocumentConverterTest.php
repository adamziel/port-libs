<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportVerifier;
use PortLibs\MarkerPDF\BenchmarkRunner;
use PortLibs\MarkerPDF\BenchmarkScorer;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-supplied-document-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf supplied document folder.');
    }

    return $path;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            unlink($child);
        }
    }

    rmdir($path);
};

$pdftextPage = static function (int $page, array $lines): array {
    return [
        'page' => $page,
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
                            'flags' => $line['flags'] ?? 0,
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

$ciPdftextPage = static function (string $text) use ($pdftextPage): array {
    $lines = [];
    foreach (preg_split('/\R+/', trim($text)) ?: [] as $index => $part) {
        if ($part === '') {
            continue;
        }
        $top = 72.0 + ($index * 16.0);
        $lines[] = [
            'text' => $part,
            'bbox' => [72.0, $top, 540.0, $top + 12.0],
        ];
    }

    return $pdftextPage(0, $lines);
};

$pdfTextChars = static function (string $text, float $x, float $y, float $charWidth = 8.0, float $gap = 1.0): array {
    $chars = [];
    $cursor = $x;
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
        $chars[] = [
            'char' => $char,
            'bbox' => [$cursor, $y, $cursor + $charWidth, $y + 14.0],
        ];
        $cursor += $charWidth + $gap;
    }

    return $chars;
};

$pdfTextLine = static function (array $charGroups): array {
    $chars = [];
    foreach ($charGroups as $group) {
        array_push($chars, ...$group);
    }

    $boxes = array_column($chars, 'bbox');

    return [
        'bbox' => [
            min(array_column($boxes, 0)),
            min(array_column($boxes, 1)),
            max(array_column($boxes, 2)),
            max(array_column($boxes, 3)),
        ],
        'spans' => [[
            'chars' => $chars,
        ]],
    ];
};

return [
    'converts supplied pdftext layout order and table dictionaries into markdown' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-supplied-document-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% supplied document pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'WordPress import packet', 'bbox' => [72.0, 48.0, 380.0, 72.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Second column media checklist.', 'bbox' => [330.0, 110.0, 560.0, 126.0]],
                ['text' => 'First column import summary.', 'bbox' => [72.0, 110.0, 280.0, 126.0]],
                ['text' => 'Feature Status', 'bbox' => [72.0, 190.0, 360.0, 212.0]],
            ]);

            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 380.0, 72.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 100.0, 280.0, 140.0]],
                    ['label' => 'Text', 'bbox' => [330.0, 100.0, 560.0, 140.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 180.0, 360.0, 240.0]],
                ],
            ];
            $order = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 0, 'bbox' => [72.0, 48.0, 380.0, 72.0]],
                    ['position' => 1, 'bbox' => [72.0, 100.0, 280.0, 140.0]],
                    ['position' => 2, 'bbox' => [330.0, 100.0, 560.0, 140.0]],
                    ['position' => 3, 'bbox' => [72.0, 180.0, 360.0, 240.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 600.0, 40.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 600.0, 80.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [300.0, 0.0, 600.0, 80.0]],
                ],
                'cells' => [
                    ['bbox' => [10.0, 5.0, 290.0, 30.0], 'text' => 'Feature'],
                    ['bbox' => [310.0, 5.0, 590.0, 30.0], 'text' => 'Status'],
                    ['bbox' => [10.0, 45.0, 290.0, 70.0], 'text' => 'Images'],
                    ['bbox' => [310.0, 45.0, 590.0, 70.0], 'text' => 'Needs review'],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'toc' => [['title' => 'WordPress import packet', 'level' => 1, 'page_index' => 0]],
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'order_results' => [$order],
                    'recognized_tables' => [$recognizedTable],
                    'table_text_lines' => [['blocks' => []]],
                    'batch_multiplier' => 2,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->contains('# Wordpress Import Packet', $result['text']);
            $t->contains('First column import summary.', $result['text']);
            $t->true(strpos($result['text'], 'First column import summary.') < strpos($result['text'], 'Second column media checklist.'));
            $t->contains('| Feature | Status       |', $result['text']);
            $t->contains('| Images  | Needs review |', $result['text']);
            $t->same(['layout', 'order', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same(1, $result['metadata']['block_stats']['table']);
            $t->same(1, $result['metadata']['inserted_tables']);
            $t->same([0], $result['metadata']['page_range']);
            $t->same(2.0, $result['metadata']['context']['batch_multiplier']);
        } finally {
            unlink($path);
        }
    },
    'routes forced OCR table cells through supplied detector output before formatting' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-forced-ocr-table-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% forced OCR table supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'Scanned table review', 'bbox' => [72.0, 48.0, 300.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Feature Status Images Needs OCR review', 'bbox' => [72.0, 180.0, 360.0, 214.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 300.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 180.0, 360.0, 240.0]],
                ],
            ];
            $order = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 0, 'bbox' => [72.0, 48.0, 300.0, 68.0]],
                    ['position' => 1, 'bbox' => [72.0, 180.0, 360.0, 240.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 110.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
                ],
            ];
            $detectorCells = [[
                ['bbox' => [10.0, 5.0, 100.0, 25.0], 'text' => null],
                ['bbox' => [130.0, 5.0, 230.0, 25.0], 'text' => null],
                ['bbox' => [10.0, 45.0, 100.0, 65.0], 'text' => null],
                ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => null],
            ]];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'order_results' => [$order],
                    'recognized_tables' => [$recognizedTable],
                    'table_text_lines' => [['blocks' => [
                        ['bbox' => [72.0, 180.0, 360.0, 214.0], 'text' => 'Feature Status Images Needs OCR review'],
                    ]]],
                    'table_detector_cells' => $detectorCells,
                    'table_ocr_text_lines' => [[
                        ['text' => 'Feature'],
                        ['text' => 'Status'],
                        ['text' => 'Images'],
                        ['text' => 'Needs OCR review'],
                    ]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                    'ocr_all_pages' => true,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->contains('# Scanned Table Review', $result['text']);
            $t->contains('| Feature | Status           |', $result['text']);
            $t->contains('| Images  | Needs OCR review |', $result['text']);
            $t->same(['layout', 'order', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same(true, $result['metadata']['table_detect_boxes']);
            $t->same([4], $result['metadata']['table_cell_counts']);
            $t->same('Needs OCR review', $result['metadata']['table_assigned_cells'][0][3]['text']);
        } finally {
            unlink($path);
        }
    },
    'routes forced OCR merged table layout boxes without stale pdftext table lines' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-forced-ocr-merged-table-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% forced OCR merged table supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'OCR merged table packet', 'bbox' => [72.0, 48.0, 340.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Legacy pdftext table should be ignored after OCR.', 'bbox' => [72.0, 176.0, 430.0, 196.0]],
                ['text' => 'Post table review note.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 340.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 250.0, 230.0]],
                    ['label' => 'Table', 'bbox' => [248.0, 150.0, 430.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 360.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 360.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 170.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [190.0, 0.0, 360.0, 80.0]],
                ],
            ];
            $detectorCells = [[
                ['bbox' => [12.0, 8.0, 160.0, 28.0], 'text' => null],
                ['bbox' => [198.0, 8.0, 344.0, 28.0], 'text' => null],
                ['bbox' => [12.0, 44.0, 160.0, 66.0], 'text' => null],
                ['bbox' => [198.0, 44.0, 344.0, 66.0], 'text' => null],
            ]];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_detector_cells' => $detectorCells,
                    'table_ocr_text_lines' => [[
                        ['text' => 'Segment'],
                        ['text' => 'State'],
                        ['text' => 'Merged OCR'],
                        ['text' => 'Imported'],
                    ]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                    'ocr_all_pages' => true,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->contains('# Ocr Merged Table Packet', $result['text']);
            $t->contains('| Segment    | State    |', $result['text']);
            $t->contains('| Merged OCR | Imported |', $result['text']);
            $t->contains('Post table review note.', $result['text']);
            $t->true(!str_contains($result['text'], 'Legacy pdftext table should be ignored after OCR.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([1], $result['metadata']['table_plan']['table_counts']);
            $t->same([[72.0, 150.0, 430.0, 230.0]], $result['metadata']['table_plan']['table_bboxes']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same(true, $result['metadata']['table_detect_boxes']);
            $t->same([4], $result['metadata']['table_cell_counts']);
            $t->same(1, $result['metadata']['block_stats']['table']);
            $t->same(1, $result['metadata']['inserted_tables']);
        } finally {
            unlink($path);
        }
    },
    'exposes forced OCR merged-cell geometry for WordPress table review' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-ocr-merged-cell-geometry-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% OCR merged cell geometry supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'OCR table geometry review', 'bbox' => [72.0, 48.0, 360.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale OCR table line should be replaced.', 'bbox' => [72.0, 176.0, 430.0, 196.0]],
                ['text' => 'Reviewer note after table.', 'bbox' => [72.0, 306.0, 430.0, 324.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 260.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 306.0, 430.0, 324.0]],
                ],
            ];
            $recognizedTable = [
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
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
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

            $geometry = $result['metadata']['table_merged_cell_geometry'][0] ?? [];
            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $gridByPosition = [];
            foreach (($gridReview['grid_cells'] ?? []) as $gridCell) {
                $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
            }

            $t->contains('# Ocr Table Geometry Review', $result['text']);
            $t->contains('Reviewer note after table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale OCR table line should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same(true, $result['metadata']['table_detect_boxes']);
            $t->same([6], $result['metadata']['table_cell_counts']);
            $t->same([1], $result['metadata']['table_plan']['table_counts']);
            $t->same(['Inventory OCR summary', 'Media group'], array_column($geometry, 'text'));
            $t->same([1, 3], [$geometry[0]['rowspan'], $geometry[0]['colspan']]);
            $t->same([2, 1], [$geometry[1]['rowspan'], $geometry[1]['colspan']]);
            $t->same(['row_id' => 0, 'col_id' => 0], $geometry[0]['anchor']);
            $t->same(['row_id' => 1, 'col_id' => 0], $geometry[1]['anchor']);
            $t->same([0.0, 0.0, 358.0, 25.0], $geometry[0]['grid_bbox']);
            $t->same([0.0, 35.0, 110.0, 110.0], $geometry[1]['grid_bbox']);
            $t->same(
                [
                    ['row_id' => 0, 'col_id' => 0],
                    ['row_id' => 0, 'col_id' => 1],
                    ['row_id' => 0, 'col_id' => 2],
                ],
                $geometry[0]['grid_cells']
            );
            $t->same(
                [
                    ['row_id' => 1, 'col_id' => 0],
                    ['row_id' => 2, 'col_id' => 0],
                ],
                $geometry[1]['grid_cells']
            );
            $t->same('Inventory OCR summary', $result['metadata']['table_assigned_cells'][0][0]['text']);
            $t->same([0, 1, 2], $result['metadata']['table_assigned_cells'][0][0]['col_ids']);
            $t->same([1, 2], $result['metadata']['table_assigned_cells'][0][1]['row_ids']);
            $t->same([0, 1, 2], $gridReview['rows']);
            $t->same([0, 1, 2], $gridReview['cols']);
            $t->same('th', $gridReview['render_cells'][0]['tag']);
            $t->same('colgroup', $gridReview['render_cells'][0]['scope']);
            $t->same('column_header', $gridReview['render_cells'][0]['header_role']);
            $t->same('th', $gridReview['render_cells'][1]['tag']);
            $t->same('rowgroup', $gridReview['render_cells'][1]['scope']);
            $t->same('row_header', $gridReview['render_cells'][1]['header_role']);
            $t->same('covered', $gridByPosition['0:1']['state']);
            $t->same(['row_id' => 0, 'col_id' => 0, 'render_cell_index' => 0], $gridByPosition['0:2']['covered_by']);
            $t->same('covered', $gridByPosition['2:0']['state']);
            $t->same('td', $gridByPosition['1:1']['tag']);
        } finally {
            unlink($path);
        }
    },
    'preserves OCR merged-cell header axes through supplied table conversion' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-ocr-merged-cell-header-axis-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% OCR merged cell header axis supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'OCR merged header axis review', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale OCR header-axis table text should be replaced.', 'bbox' => [72.0, 176.0, 490.0, 196.0]],
                ['text' => 'Reviewer note after header-axis table.', 'bbox' => [72.0, 326.0, 500.0, 344.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 290.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 326.0, 500.0, 344.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 28.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 32.0, 300.0, 60.0]],
                    ['row_id' => 2, 'bbox' => [0.0, 70.0, 300.0, 100.0]],
                    ['row_id' => 3, 'bbox' => [0.0, 110.0, 300.0, 140.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 90.0, 140.0]],
                    ['col_id' => 1, 'bbox' => [100.0, 0.0, 190.0, 140.0]],
                    ['col_id' => 2, 'bbox' => [200.0, 0.0, 300.0, 140.0]],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_detector_cells' => [[
                        ['bbox' => [5.0, 5.0, 185.0, 56.0], 'text' => null],
                        ['bbox' => [110.0, 8.0, 180.0, 20.0], 'text' => null],
                        ['bbox' => [205.0, 5.0, 295.0, 24.0], 'text' => null],
                        ['bbox' => [5.0, 74.0, 85.0, 136.0], 'text' => null],
                        ['bbox' => [110.0, 74.0, 180.0, 94.0], 'text' => null],
                        ['bbox' => [205.0, 74.0, 295.0, 94.0], 'text' => null],
                        ['bbox' => [110.0, 114.0, 180.0, 134.0], 'text' => null],
                        ['bbox' => [205.0, 114.0, 295.0, 134.0], 'text' => null],
                    ]],
                    'table_ocr_text_lines' => [[
                        'lines' => [
                            ['text' => 'Inventory'],
                            ['text' => 'axis'],
                            ['text' => 'Status'],
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

            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $gridByPosition = [];
            foreach (($gridReview['grid_cells'] ?? []) as $gridCell) {
                $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
            }

            $t->contains('# Ocr Merged Header Axis Review', $result['text']);
            $t->contains('Reviewer note after header-axis table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale OCR header-axis table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same([8], $result['metadata']['table_cell_counts']);
            $t->same('Inventory', $result['metadata']['table_assigned_cells'][0][0]['text']);
            $t->same([0, 1], $result['metadata']['table_assigned_cells'][0][0]['row_ids']);
            $t->same([0, 1], $result['metadata']['table_assigned_cells'][0][0]['col_ids']);
            $t->same('Inventory axis', $gridReview['render_cells'][0]['text']);
            $t->same('colgroup', $gridReview['render_cells'][0]['scope']);
            $t->same('both', $gridReview['render_cells'][0]['header_axis']);
            $t->same(['column', 'row'], $gridReview['render_cells'][0]['header_axes']);
            $t->same([2, 2], [$gridReview['render_cells'][0]['rowspan'], $gridReview['render_cells'][0]['colspan']]);
            $t->same(2, $gridReview['render_cells'][0]['source_cell_count']);
            $t->same('axis', $gridReview['render_cells'][0]['continuation_cells'][0]['text']);
            $t->same('Status', $gridReview['render_cells'][1]['text']);
            $t->same('column', $gridReview['render_cells'][1]['header_axis']);
            $t->same('Media group', $gridReview['render_cells'][2]['text']);
            $t->same('rowgroup', $gridReview['render_cells'][2]['scope']);
            $t->same('row', $gridReview['render_cells'][2]['header_axis']);
            $t->same('both', $gridByPosition['0:0']['header_axis']);
            $t->same('covered', $gridByPosition['0:1']['state']);
            $t->same('covered', $gridByPosition['1:0']['state']);
            $t->same('row', $gridByPosition['2:0']['header_axis']);
            $t->same('Needs review', $gridByPosition['3:2']['text']);
        } finally {
            unlink($path);
        }
    },
    'exposes OCR merged header grid references through supplied table conversion' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-ocr-merged-header-grid-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% OCR merged header grid supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'OCR merged header grid review', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale OCR merged header grid table text should be replaced.', 'bbox' => [72.0, 176.0, 510.0, 196.0]],
                ['text' => 'Reviewer note after merged header grid table.', 'bbox' => [72.0, 326.0, 520.0, 344.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 290.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 326.0, 520.0, 344.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 28.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 32.0, 300.0, 60.0]],
                    ['row_id' => 2, 'bbox' => [0.0, 70.0, 300.0, 100.0]],
                    ['row_id' => 3, 'bbox' => [0.0, 110.0, 300.0, 140.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 90.0, 140.0]],
                    ['col_id' => 1, 'bbox' => [100.0, 0.0, 190.0, 140.0]],
                    ['col_id' => 2, 'bbox' => [200.0, 0.0, 300.0, 140.0]],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_detector_cells' => [[
                        ['bbox' => [5.0, 5.0, 185.0, 56.0], 'text' => null],
                        ['bbox' => [110.0, 8.0, 180.0, 20.0], 'text' => null],
                        ['bbox' => [205.0, 5.0, 295.0, 24.0], 'text' => null],
                        ['bbox' => [5.0, 74.0, 85.0, 136.0], 'text' => null],
                        ['bbox' => [110.0, 74.0, 180.0, 94.0], 'text' => null],
                        ['bbox' => [205.0, 74.0, 295.0, 94.0], 'text' => null],
                        ['bbox' => [110.0, 114.0, 180.0, 134.0], 'text' => null],
                        ['bbox' => [205.0, 114.0, 295.0, 134.0], 'text' => null],
                    ]],
                    'table_ocr_text_lines' => [[
                        'lines' => [
                            ['text' => 'Inventory'],
                            ['text' => 'axis'],
                            ['text' => 'Status'],
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

            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $gridByPosition = [];
            foreach (($gridReview['grid_cells'] ?? []) as $gridCell) {
                $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
            }

            $t->contains('# Ocr Merged Header Grid Review', $result['text']);
            $t->contains('Reviewer note after merged header grid table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale OCR merged header grid table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same(['h-r0-c0', 'h-r0-c2', 'h-r2-c0'], array_column($gridReview['header_cells'], 'header_id'));
            $t->same(['Images', '12', 'State', 'Needs review'], array_column($gridReview['data_cells'], 'text'));
            $t->same('h-r0-c0', $gridReview['render_cells'][0]['header_id']);
            $t->same('h-r0-c2', $gridReview['render_cells'][1]['header_id']);
            $t->same('h-r2-c0', $gridReview['render_cells'][2]['header_id']);
            $t->same(['h-r0-c0', 'h-r2-c0'], $gridByPosition['2:1']['headers']);
            $t->same('Inventory axis / Media group', $gridByPosition['2:1']['header_text']);
            $t->same(['h-r0-c2', 'h-r2-c0'], $gridByPosition['3:2']['headers']);
            $t->same(['Status', 'Media group'], $gridByPosition['3:2']['header_texts']);
            $t->same('Needs review', $gridByPosition['3:2']['text']);
        } finally {
            unlink($path);
        }
    },
    'preserves rowspanned table header rows through supplied grid conversion' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-header-grid-rowspan-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% rowspanned table header grid supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'Rowspanned table header grid review', 'bbox' => [72.0, 48.0, 450.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale rowspanned header table text should be replaced.', 'bbox' => [72.0, 176.0, 510.0, 196.0]],
                ['text' => 'Reviewer note after rowspanned header table.', 'bbox' => [72.0, 306.0, 520.0, 324.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 450.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 260.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 306.0, 520.0, 324.0]],
                ],
            ];
            $recognizedTable = [
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
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
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

            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $gridByPosition = [];
            foreach (($gridReview['grid_cells'] ?? []) as $gridCell) {
                $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
            }

            $t->contains('# Rowspanned Table Header Grid Review', $result['text']);
            $t->contains('Reviewer note after rowspanned header table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale rowspanned header table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same([7], $result['metadata']['table_cell_counts']);
            $t->same([0, 1], $result['metadata']['table_assigned_cells'][0][0]['row_ids']);
            $t->same([1, 2], $result['metadata']['table_assigned_cells'][0][1]['col_ids']);
            $t->same([0, 1], $gridReview['column_header_rows']);
            $t->same(['h-r0-c0', 'h-r0-c1', 'h-r1-c1', 'h-r1-c2'], array_column($gridReview['header_cells'], 'header_id'));
            $t->same(['Media', '12', 'Ready'], array_column($gridReview['data_cells'], 'text'));
            $t->same('Import group', $gridReview['render_cells'][0]['text']);
            $t->same('both', $gridReview['render_cells'][0]['header_axis']);
            $t->same('Assets', $gridReview['render_cells'][1]['text']);
            $t->same('colgroup', $gridReview['render_cells'][1]['scope']);
            $t->same('Images', $gridReview['render_cells'][2]['text']);
            $t->same('col', $gridReview['render_cells'][2]['scope']);
            $t->same('State', $gridReview['render_cells'][3]['text']);
            $t->same('col', $gridReview['render_cells'][3]['scope']);
            $t->same('covered', $gridByPosition['1:0']['state']);
            $t->same('h-r1-c1', $gridByPosition['1:1']['header_id']);
            $t->same(['h-r0-c1', 'h-r1-c1'], $gridByPosition['2:1']['headers']);
            $t->same('Assets / Images', $gridByPosition['2:1']['header_text']);
            $t->same(['h-r0-c1', 'h-r1-c2'], $gridByPosition['2:2']['headers']);
            $t->same(['Assets', 'State'], $gridByPosition['2:2']['header_texts']);
        } finally {
            unlink($path);
        }
    },
    'binds table span grid review to surrounding section and caption blocks' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-span-grid-section-caption-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table span grid section caption supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'Import metrics', 'bbox' => [72.0, 48.0, 290.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale section caption table text should be replaced.', 'bbox' => [72.0, 176.0, 510.0, 196.0]],
                ['text' => 'Table 4: Review metrics from tabled grid.', 'bbox' => [72.0, 272.0, 430.0, 290.0]],
                ['text' => 'Reviewer note after caption.', 'bbox' => [72.0, 326.0, 440.0, 344.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Section-header', 'bbox' => [72.0, 48.0, 290.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 260.0]],
                    ['label' => 'Caption', 'bbox' => [72.0, 272.0, 430.0, 290.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 326.0, 440.0, 344.0]],
                ],
            ];
            $recognizedTable = [
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
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
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

            $context = $result['metadata']['table_section_caption_review'][0] ?? [];
            $grid = $context['spanning_grid'] ?? [];

            $t->contains('## Import Metrics', $result['text']);
            $t->contains('| Inventory summary', $result['text']);
            $t->contains('Table 4: Review metrics from tabled grid.', $result['text']);
            $t->contains('Reviewer note after caption.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale section caption table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same(true, $context['inserted']);
            $t->same(true, $context['has_section']);
            $t->same(true, $context['has_caption']);
            $t->same('Section-header', $context['section']['type']);
            $t->same('Import metrics', $context['section']['text']);
            $t->same('table_span_grid', $context['section']['review_target']);
            $t->same('Caption', $context['caption']['type']);
            $t->same('Table 4: Review metrics from tabled grid.', $context['caption']['text']);
            $t->same('after', $context['caption']['position']);
            $t->same('table_span_grid', $context['caption']['review_target']);
            $t->same([0, 1, 2], $grid['rows']);
            $t->same([0, 1, 2], $grid['cols']);
            $t->same(6, $grid['render_cell_count']);
            $t->same(['h-r0-c0', 'h-r1-c0'], $grid['header_ids']);
            $t->same(true, $grid['has_rowspan']);
            $t->same(true, $grid['has_colspan']);
            $t->same('table_span_grid', $grid['review_target']);
        } finally {
            unlink($path);
        }
    },
    'links rowspanned OCR table captions to accessible header grids' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-rowspan-caption-accessibility-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table rowspan caption accessibility supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'Import asset metrics', 'bbox' => [72.0, 48.0, 360.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale accessible rowspan table text should be replaced.', 'bbox' => [72.0, 176.0, 510.0, 196.0]],
                ['text' => 'Table 7: Asset OCR review counts.', 'bbox' => [72.0, 282.0, 430.0, 300.0]],
                ['text' => 'Reviewer note after accessible table.', 'bbox' => [72.0, 326.0, 480.0, 344.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Section-header', 'bbox' => [72.0, 48.0, 360.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 270.0]],
                    ['label' => 'Caption', 'bbox' => [72.0, 282.0, 430.0, 300.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 326.0, 480.0, 344.0]],
                ],
            ];
            $recognizedTable = [
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
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
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

            $context = $result['metadata']['table_section_caption_review'][0] ?? [];
            $accessibility = $context['accessibility'] ?? [];
            $dataByText = [];
            foreach (($accessibility['data_cell_headers'] ?? []) as $dataCell) {
                $dataByText[$dataCell['text']] = $dataCell;
            }

            $t->contains('## Import Asset Metrics', $result['text']);
            $t->contains('Table 7: Asset OCR review counts.', $result['text']);
            $t->contains('Reviewer note after accessible table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale accessible rowspan table text should be replaced.'));
            $t->same('markerpdf-table-0', $accessibility['table_id']);
            $t->same('markerpdf-table-0-caption', $accessibility['caption_id']);
            $t->same('markerpdf-table-0-section', $accessibility['section_id']);
            $t->same(['markerpdf-table-0-caption'], $accessibility['aria_describedby']);
            $t->same(['markerpdf-table-0-section'], $accessibility['aria_labelledby']);
            $t->same('Table 7: Asset OCR review counts.', $accessibility['caption_text']);
            $t->same('after', $accessibility['caption_position']);
            $t->same('table_span_grid_accessibility', $accessibility['review_target']);
            $t->same('markerpdf-table-0-caption', $context['caption']['caption_id']);
            $t->same('markerpdf-table-0-section', $context['section']['section_id']);
            $t->same(['h-r0-c0', 'h-r0-c1', 'h-r1-c1', 'h-r1-c2'], $accessibility['header_ids']);
            $t->same(1, $accessibility['rowspan_cell_count']);
            $t->same(1, $accessibility['colspan_cell_count']);
            $t->same('Import group', $accessibility['rowspan_cells'][0]['text']);
            $t->same([2, 1], [$accessibility['rowspan_cells'][0]['rowspan'], $accessibility['rowspan_cells'][0]['colspan']]);
            $t->same('Assets', $accessibility['colspan_cells'][0]['text']);
            $t->same([1, 2], [$accessibility['colspan_cells'][0]['rowspan'], $accessibility['colspan_cells'][0]['colspan']]);
            $t->same(['h-r0-c1', 'h-r1-c1'], $dataByText['12']['headers']);
            $t->same(['Assets', 'Images'], $dataByText['12']['header_texts']);
            $t->same('markerpdf-table-0-caption', $dataByText['12']['caption_id']);
            $t->same(['h-r0-c1', 'h-r1-c2'], $dataByText['Ready']['headers']);
            $t->same(['h-r0-c0'], $dataByText['Media']['headers']);
            $t->same(true, $accessibility['accessible_caption_bound']);
        } finally {
            unlink($path);
        }
    },
    'binds forced OCR header grid captions to cellspan occupancy review' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-ocr-header-grid-caption-cellspan-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table OCR header grid caption cellspan supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'OCR captioned header grid import', 'bbox' => [72.0, 48.0, 460.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale captioned header grid table text should be replaced.', 'bbox' => [72.0, 176.0, 520.0, 196.0]],
                ['text' => 'Table 9: Captioned OCR header-grid review.', 'bbox' => [72.0, 302.0, 456.0, 320.0]],
                ['text' => 'Reviewer note after captioned header grid.', 'bbox' => [72.0, 346.0, 520.0, 364.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Section-header', 'bbox' => [72.0, 48.0, 460.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 290.0]],
                    ['label' => 'Caption', 'bbox' => [72.0, 302.0, 456.0, 320.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 346.0, 520.0, 364.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 28.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 32.0, 300.0, 60.0]],
                    ['row_id' => 2, 'bbox' => [0.0, 70.0, 300.0, 100.0]],
                    ['row_id' => 3, 'bbox' => [0.0, 110.0, 300.0, 140.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 90.0, 140.0]],
                    ['col_id' => 1, 'bbox' => [100.0, 0.0, 190.0, 140.0]],
                    ['col_id' => 2, 'bbox' => [200.0, 0.0, 300.0, 140.0]],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_detector_cells' => [[
                        ['bbox' => [5.0, 5.0, 185.0, 56.0], 'text' => null],
                        ['bbox' => [110.0, 8.0, 180.0, 20.0], 'text' => null],
                        ['bbox' => [205.0, 5.0, 295.0, 24.0], 'text' => null],
                        ['bbox' => [5.0, 74.0, 85.0, 136.0], 'text' => null],
                        ['bbox' => [110.0, 74.0, 180.0, 94.0], 'text' => null],
                        ['bbox' => [205.0, 74.0, 295.0, 94.0], 'text' => null],
                        ['bbox' => [110.0, 114.0, 180.0, 134.0], 'text' => null],
                        ['bbox' => [205.0, 114.0, 295.0, 134.0], 'text' => null],
                    ]],
                    'table_ocr_text_lines' => [[
                        'lines' => [
                            ['text' => 'Inventory'],
                            ['text' => 'axis'],
                            ['text' => 'Status'],
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

            $context = $result['metadata']['table_section_caption_review'][0] ?? [];
            $accessibility = $context['accessibility'] ?? [];
            $cellspanGrid = $accessibility['cellspan_header_grid'] ?? [];
            $gridByPosition = [];
            foreach (($cellspanGrid['grid_cells'] ?? []) as $gridCell) {
                $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
            }
            $dataByText = [];
            foreach (($cellspanGrid['data_cell_headers'] ?? []) as $dataCell) {
                $dataByText[$dataCell['text']] = $dataCell;
            }

            $t->contains('## Ocr Captioned Header Grid Import', $result['text']);
            $t->contains('Table 9: Captioned OCR header-grid review.', $result['text']);
            $t->contains('Reviewer note after captioned header grid.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale captioned header grid table text should be replaced.'));
            $t->same('table_ocr_header_grid_caption_cellspan', $cellspanGrid['review_target']);
            $t->same('markerpdf-table-0-caption', $cellspanGrid['caption_id']);
            $t->same('markerpdf-table-0-section', $cellspanGrid['section_id']);
            $t->same(true, $cellspanGrid['caption_bound']);
            $t->same([0, 1, 2, 3], $cellspanGrid['rows']);
            $t->same([0, 1, 2], $cellspanGrid['cols']);
            $t->same(['h-r0-c0', 'h-r0-c2', 'h-r2-c0'], $cellspanGrid['header_ids']);
            $t->same(2, $cellspanGrid['cellspan_count']);
            $t->same(true, $cellspanGrid['has_rowspan']);
            $t->same(true, $cellspanGrid['has_colspan']);
            $t->same('Inventory axis', $cellspanGrid['render_cells'][0]['text']);
            $t->same([2, 2], [$cellspanGrid['render_cells'][0]['rowspan'], $cellspanGrid['render_cells'][0]['colspan']]);
            $t->same('h-r0-c0', $cellspanGrid['render_cells'][0]['header_id']);
            $t->same('colgroup', $cellspanGrid['render_cells'][0]['scope']);
            $t->same('markerpdf-table-0-caption', $cellspanGrid['render_cells'][0]['caption_id']);
            $t->same('covered', $gridByPosition['1:1']['state']);
            $t->same(['row_id' => 0, 'col_id' => 0, 'render_cell_index' => 0], $gridByPosition['1:1']['covered_by']);
            $t->same('markerpdf-table-0-caption', $gridByPosition['1:1']['caption_id']);
            $t->same(['h-r0-c0', 'h-r2-c0'], $dataByText['Images']['headers']);
            $t->same('Inventory axis / Media group', $dataByText['Images']['header_text']);
            $t->same('markerpdf-table-0-caption', $dataByText['Images']['caption_id']);
            $t->same(['h-r0-c2', 'h-r2-c0'], $dataByText['Needs review']['headers']);
        } finally {
            unlink($path);
        }
    },
    'binds forced OCR rotated header captions to physical-axis cellspan review' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-ocr-rotated-header-caption-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table OCR rotated header caption supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'Rotated OCR header caption import', 'bbox' => [72.0, 48.0, 500.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale rotated header table text should be replaced.', 'bbox' => [72.0, 176.0, 520.0, 196.0]],
                ['text' => 'Table 15: Rotated OCR header caption review.', 'bbox' => [72.0, 402.0, 500.0, 420.0]],
                ['text' => 'Reviewer note after rotated caption table.', 'bbox' => [72.0, 446.0, 530.0, 464.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Section-header', 'bbox' => [72.0, 48.0, 500.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 390.0]],
                    ['label' => 'Caption', 'bbox' => [72.0, 402.0, 500.0, 420.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 446.0, 530.0, 464.0]],
                ],
            ];
            $recognizedTable = [
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
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
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

            $context = $result['metadata']['table_section_caption_review'][0] ?? [];
            $accessibility = $context['accessibility'] ?? [];
            $cellspanGrid = $accessibility['cellspan_header_grid'] ?? [];
            $dataByText = [];
            foreach (($cellspanGrid['data_cell_headers'] ?? []) as $dataCell) {
                $dataByText[$dataCell['text']] = $dataCell;
            }

            $t->contains('## Rotated Ocr Header Caption Import', $result['text']);
            $t->contains('Rotated inventory', $result['text']);
            $t->contains('Table 15: Rotated OCR header caption review.', $result['text']);
            $t->contains('Reviewer note after rotated caption table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale rotated header table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same('markerpdf-table-0-caption', $cellspanGrid['caption_id']);
            $t->same('markerpdf-table-0-section', $cellspanGrid['section_id']);
            $t->same(true, $cellspanGrid['caption_bound']);
            $t->same(true, $accessibility['rotated'] ?? false);
            $t->same(true, $cellspanGrid['rotated'] ?? false);
            $t->same('rotated', $cellspanGrid['orientation']);
            $t->same('x', $cellspanGrid['row_axis']);
            $t->same('y', $cellspanGrid['col_axis']);
            $t->same(['h-r0-c0', 'h-r1-c0'], $cellspanGrid['header_ids']);
            $t->same('y', $dataByText['Image count']['column_header_physical_axis'] ?? null);
            $t->same('x', $dataByText['Image count']['row_header_physical_axis'] ?? null);
            $t->same(['h-r0-c0', 'h-r1-c0'], $dataByText['Image count']['headers']);
            $t->same('markerpdf-table-0-caption', $dataByText['Image count']['caption_id']);
            $t->same(['h-r0-c0', 'h-r1-c0'], $dataByText['Needs review']['headers']);
            $t->same('Rotated inventory / Media group', $dataByText['Needs review']['header_text']);
        } finally {
            unlink($path);
        }
    },
    'keeps multiline OCR table headers together in WordPress grid review' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-ocr-multiline-header-grid-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% OCR multiline header grid supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'OCR multiline header review', 'bbox' => [72.0, 48.0, 390.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale multiline OCR header table text should be replaced.', 'bbox' => [72.0, 176.0, 460.0, 196.0]],
                ['text' => 'Reviewer note after multiline table.', 'bbox' => [72.0, 306.0, 460.0, 324.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 390.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 260.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 306.0, 460.0, 324.0]],
                ],
            ];
            $recognizedTable = [
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
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
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

            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $gridByPosition = [];
            foreach (($gridReview['grid_cells'] ?? []) as $gridCell) {
                $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
            }

            $t->contains('# Ocr Multiline Header Review', $result['text']);
            $t->contains('Reviewer note after multiline table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale multiline OCR header table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same([6], $result['metadata']['table_cell_counts']);
            $t->same('Inventory OCR summary', $result['metadata']['table_assigned_cells'][0][0]['text']);
            $t->same('Needs review', $result['metadata']['table_assigned_cells'][0][5]['text']);
            $t->same([0, 1, 2], $result['metadata']['table_assigned_cells'][0][0]['col_ids']);
            $t->same('Inventory OCR summary', $gridReview['render_cells'][0]['text']);
            $t->same('th', $gridReview['render_cells'][0]['tag']);
            $t->same('colgroup', $gridReview['render_cells'][0]['scope']);
            $t->same('column_header', $gridReview['render_cells'][0]['header_role']);
            $t->same('Media group', $gridReview['render_cells'][1]['text']);
            $t->same('rowgroup', $gridReview['render_cells'][1]['scope']);
            $t->same('covered', $gridByPosition['0:1']['state']);
            $t->same('Needs review', $gridByPosition['2:2']['text']);
        } finally {
            unlink($path);
        }
    },
    'reviews OCR rowspan colspan continuation anchors through supplied table conversion' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-ocr-span-continuation-grid-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% OCR span continuation grid supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'OCR rowspan colspan continuation review', 'bbox' => [72.0, 48.0, 470.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale OCR rowspan table text should be replaced.', 'bbox' => [72.0, 176.0, 460.0, 196.0]],
                ['text' => 'Reviewer note after continuation table.', 'bbox' => [72.0, 306.0, 480.0, 324.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 470.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 260.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 306.0, 480.0, 324.0]],
                ],
            ];
            $recognizedTable = [
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
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
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

            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $gridByPosition = [];
            foreach (($gridReview['grid_cells'] ?? []) as $gridCell) {
                $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
            }

            $t->contains('# Ocr Rowspan Colspan Continuation Review', $result['text']);
            $t->contains('Reviewer note after continuation table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale OCR rowspan table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same([7], $result['metadata']['table_cell_counts']);
            $t->same('Inventory', $result['metadata']['table_assigned_cells'][0][0]['text']);
            $t->same('continued', $result['metadata']['table_assigned_cells'][0][1]['text']);
            $t->same([0, 1, 2], $result['metadata']['table_assigned_cells'][0][0]['col_ids']);
            $t->same('Inventory continued', $gridReview['render_cells'][0]['text']);
            $t->same('th', $gridReview['render_cells'][0]['tag']);
            $t->same('colgroup', $gridReview['render_cells'][0]['scope']);
            $t->same(2, $gridReview['render_cells'][0]['source_cell_count']);
            $t->same(1, $gridReview['render_cells'][0]['continuation_count']);
            $t->same(['Inventory', 'continued'], $gridReview['render_cells'][0]['text_parts']);
            $t->same('continued', $gridReview['render_cells'][0]['continuation_cells'][0]['text']);
            $t->same('anchor', $gridByPosition['0:0']['state']);
            $t->same('Inventory continued', $gridByPosition['0:0']['text']);
            $t->same('covered', $gridByPosition['0:1']['state']);
            $t->same('Media group', $gridReview['render_cells'][1]['text']);
            $t->same('rowgroup', $gridReview['render_cells'][1]['scope']);
            $t->same('covered', $gridByPosition['2:0']['state']);
            $t->same('Needs review', $gridByPosition['2:2']['text']);
        } finally {
            unlink($path);
        }
    },
    'preserves OCR source order when table-line bboxes cross detector grid borders' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-grid-border-ocr-conflict-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% OCR grid border conflict supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'OCR grid border conflict review', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale grid border table text should be replaced.', 'bbox' => [72.0, 176.0, 460.0, 196.0]],
                ['text' => 'Reviewer note after border conflict table.', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 38.0, 200.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 96.0, 72.0]],
                    ['col_id' => 1, 'bbox' => [98.0, 0.0, 200.0, 72.0]],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_detector_cells' => [[
                        ['bbox' => [0.0, 0.0, 90.0, 24.0], 'text' => null],
                        ['bbox' => [100.0, 0.0, 190.0, 24.0], 'text' => null],
                        ['bbox' => [0.0, 40.0, 90.0, 64.0], 'text' => null],
                        ['bbox' => [100.0, 40.0, 190.0, 64.0], 'text' => null],
                    ]],
                    'table_ocr_text_lines' => [[
                        'lines' => [
                            ['text' => 'Feature', 'bbox' => [0.0, 0.0, 190.0, 24.0]],
                            ['text' => 'Status', 'bbox' => [0.0, 0.0, 190.0, 24.0]],
                            ['text' => 'Images', 'bbox' => [0.0, 40.0, 190.0, 64.0]],
                            ['text' => 'Ready', 'bbox' => [0.0, 40.0, 190.0, 64.0]],
                        ],
                    ]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                    'ocr_all_pages' => true,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $conflicts = $result['metadata']['table_ocr_grid_border_conflicts'][0] ?? [];

            $t->contains('# Ocr Grid Border Conflict Review', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('Reviewer note after border conflict table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale grid border table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same(true, $result['metadata']['table_detect_boxes']);
            $t->same([4], $result['metadata']['table_cell_counts']);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], array_column($result['metadata']['table_assigned_cells'][0], 'text'));
            $t->same(4, count($conflicts));
            $t->same('source_order_grid_border', $conflicts[0]['assignment_mode']);
            $t->same([0, 1], $conflicts[0]['candidate_cell_indexes']);
            $t->same([2, 3], $conflicts[2]['candidate_cell_indexes']);
            $t->same(0, $conflicts[0]['assigned_cell_index']);
            $t->same(3, $conflicts[3]['assigned_cell_index']);
        } finally {
            unlink($path);
        }
    },
    'assigns supplied OCR polygon text by geometry before WordPress table rendering' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-ocr-polygon-geometry-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% OCR polygon geometry supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'OCR polygon geometry review', 'bbox' => [72.0, 48.0, 410.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale polygon table text should be replaced.', 'bbox' => [72.0, 176.0, 460.0, 196.0]],
                ['text' => 'Reviewer note after polygon table.', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 410.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 500.0, 294.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 38.0, 200.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 96.0, 72.0]],
                    ['col_id' => 1, 'bbox' => [98.0, 0.0, 200.0, 72.0]],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_detector_cells' => [[
                        ['bbox' => [0.0, 0.0, 90.0, 24.0], 'text' => null],
                        ['bbox' => [100.0, 0.0, 190.0, 24.0], 'text' => null],
                        ['bbox' => [0.0, 40.0, 90.0, 64.0], 'text' => null],
                        ['bbox' => [100.0, 40.0, 190.0, 64.0], 'text' => null],
                    ]],
                    'table_ocr_text_lines' => [[
                        'text_lines' => [
                            ['text' => 'Status', 'polygon' => [[102.0, 4.0], [188.0, 4.0], [188.0, 20.0], [102.0, 20.0]]],
                            ['text' => 'Feature', 'polygon' => [[2.0, 4.0], [88.0, 4.0], [88.0, 20.0], [2.0, 20.0]]],
                            ['text' => 'Ready', 'polygon' => [[102.0, 44.0], [188.0, 44.0], [188.0, 60.0], [102.0, 60.0]]],
                            ['text' => 'Images', 'polygon' => [[2.0, 44.0], [88.0, 44.0], [88.0, 60.0], [2.0, 60.0]]],
                        ],
                    ]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                    'ocr_all_pages' => true,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->contains('# Ocr Polygon Geometry Review', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('Reviewer note after polygon table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale polygon table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same(true, $result['metadata']['table_detect_boxes']);
            $t->same([4], $result['metadata']['table_cell_counts']);
            $t->same(['Feature', 'Status', 'Images', 'Ready'], array_column($result['metadata']['table_assigned_cells'][0], 'text'));
            $t->true(!isset($result['metadata']['table_ocr_grid_border_conflicts']));
        } finally {
            unlink($path);
        }
    },
    'exposes assigned grid-border conflict review metadata through supplied table conversion' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-grid-border-review-currentbase-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% OCR grid border assigned review supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'OCR grid border assigned review', 'bbox' => [72.0, 48.0, 450.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale assigned grid border table text should be replaced.', 'bbox' => [72.0, 176.0, 500.0, 196.0]],
                ['text' => 'Reviewer note after assigned border review.', 'bbox' => [72.0, 276.0, 510.0, 294.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 450.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 510.0, 294.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 38.0, 200.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 96.0, 72.0]],
                    ['col_id' => 1, 'bbox' => [98.0, 0.0, 200.0, 72.0]],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_detector_cells' => [[
                        ['bbox' => [0.0, 0.0, 90.0, 24.0], 'text' => null],
                        ['bbox' => [100.0, 0.0, 190.0, 24.0], 'text' => null],
                        ['bbox' => [0.0, 40.0, 90.0, 64.0], 'text' => null],
                        ['bbox' => [100.0, 40.0, 190.0, 64.0], 'text' => null],
                    ]],
                    'table_ocr_text_lines' => [[
                        'lines' => [
                            ['text' => 'Table-wide heading', 'bbox' => [0.0, 0.0, 190.0, 64.0]],
                            ['text' => 'Column border', 'bbox' => [0.0, 0.0, 190.0, 24.0]],
                            ['text' => 'Row border', 'bbox' => [0.0, 0.0, 90.0, 64.0]],
                            ['text' => 'Cell value', 'bbox' => [100.0, 40.0, 190.0, 64.0]],
                        ],
                    ]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                    'ocr_all_pages' => true,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $conflicts = $result['metadata']['table_ocr_grid_border_conflicts'][0] ?? [];

            $t->contains('# Ocr Grid Border Assigned Review', $result['text']);
            $t->contains('| Table\\-wide heading | Column border |', $result['text']);
            $t->contains('| Row border          | Cell value    |', $result['text']);
            $t->contains('Reviewer note after assigned border review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale assigned grid border table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same([4], $result['metadata']['table_cell_counts']);
            $t->same(3, count($conflicts));
            $t->same('both', $conflicts[0]['grid_border_axis']);
            $t->same(['column', 'row'], $conflicts[0]['grid_border_axes']);
            $t->same([0, 1], $conflicts[0]['candidate_row_ids']);
            $t->same([0, 1], $conflicts[0]['candidate_col_ids']);
            $t->same(['cell_index' => 0, 'row_id' => 0, 'col_id' => 0], $conflicts[0]['candidate_grid_anchors'][0]);
            $t->same(['cell_index' => 3, 'row_id' => 1, 'col_id' => 1], $conflicts[0]['candidate_grid_anchors'][3]);
            $t->same(0, $conflicts[0]['assigned_grid_cell']['cell_index']);
            $t->same([0], $conflicts[0]['assigned_grid_cell']['row_ids']);
            $t->same([0], $conflicts[0]['assigned_grid_cell']['col_ids']);
            $t->same('Table-wide heading', $conflicts[0]['assigned_grid_cell']['text']);
            $t->same([0.0, 0.0, 96.0, 30.0], $conflicts[0]['assigned_grid_cell']['grid_bbox']);
            $t->same([0.0, 0.0, 96.0, 30.0], $conflicts[0]['candidate_grid_cells'][0]['grid_bbox']);
            $t->same([98.0, 38.0, 200.0, 70.0], $conflicts[0]['candidate_grid_cells'][3]['grid_bbox']);
            $t->same('column', $conflicts[1]['grid_border_axis']);
            $t->same('row', $conflicts[2]['grid_border_axis']);
        } finally {
            unlink($path);
        }
    },
    'exposes OCR border-conflict spanning-grid render metadata through supplied table conversion' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-ocr-border-conflict-grid-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% OCR border conflict spanning grid supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'OCR border conflict grid review', 'bbox' => [72.0, 48.0, 440.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale border conflict grid table text should be replaced.', 'bbox' => [72.0, 176.0, 520.0, 196.0]],
                ['text' => 'Reviewer note after border conflict grid.', 'bbox' => [72.0, 326.0, 520.0, 344.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 440.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 290.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 326.0, 520.0, 344.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 28.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 32.0, 300.0, 60.0]],
                    ['row_id' => 2, 'bbox' => [0.0, 70.0, 300.0, 100.0]],
                    ['row_id' => 3, 'bbox' => [0.0, 110.0, 300.0, 140.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 90.0, 140.0]],
                    ['col_id' => 1, 'bbox' => [100.0, 0.0, 190.0, 140.0]],
                    ['col_id' => 2, 'bbox' => [200.0, 0.0, 300.0, 140.0]],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_detector_cells' => [[
                        ['bbox' => [5.0, 5.0, 185.0, 56.0], 'text' => null],
                        ['bbox' => [110.0, 8.0, 180.0, 20.0], 'text' => null],
                        ['bbox' => [205.0, 5.0, 295.0, 24.0], 'text' => null],
                        ['bbox' => [5.0, 74.0, 85.0, 136.0], 'text' => null],
                        ['bbox' => [110.0, 74.0, 180.0, 94.0], 'text' => null],
                        ['bbox' => [205.0, 74.0, 295.0, 94.0], 'text' => null],
                        ['bbox' => [110.0, 114.0, 180.0, 134.0], 'text' => null],
                        ['bbox' => [205.0, 114.0, 295.0, 134.0], 'text' => null],
                    ]],
                    'table_ocr_text_lines' => [[
                        'lines' => [
                            ['text' => 'Inventory', 'bbox' => [5.0, 5.0, 185.0, 20.0]],
                            ['text' => 'axis', 'bbox' => [5.0, 5.0, 185.0, 20.0]],
                            ['text' => 'Status', 'bbox' => [205.0, 5.0, 295.0, 24.0]],
                            ['text' => 'Media group', 'bbox' => [5.0, 74.0, 85.0, 136.0]],
                            ['text' => 'Images', 'bbox' => [110.0, 74.0, 295.0, 94.0]],
                            ['text' => '12', 'bbox' => [205.0, 74.0, 295.0, 94.0]],
                            ['text' => 'State', 'bbox' => [110.0, 114.0, 180.0, 134.0]],
                            ['text' => 'Needs review', 'bbox' => [205.0, 114.0, 295.0, 134.0]],
                        ],
                    ]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                    'ocr_all_pages' => true,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $conflicts = $result['metadata']['table_ocr_grid_border_conflicts'][0] ?? [];
            $byAssigned = [];
            foreach ($conflicts as $conflict) {
                $byAssigned[(int) $conflict['assigned_cell_index']] = $conflict;
            }

            $t->contains('# Ocr Border Conflict Grid Review', $result['text']);
            $t->contains('| Inventory   | axis   | Status       |', $result['text']);
            $t->contains('| Media group | Images | 12           |', $result['text']);
            $t->contains('Reviewer note after border conflict grid.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale border conflict grid table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same([8], $result['metadata']['table_cell_counts']);
            $t->same(3, count($conflicts));
            $t->same(['h-r0-c0', 'h-r0-c2', 'h-r2-c0'], array_column($result['metadata']['table_spanning_grid_review'][0]['header_cells'], 'header_id'));
            $t->same('h-r0-c0', $byAssigned[0]['assigned_grid_render_cell']['render_cell']['header_id']);
            $t->same('covered', $byAssigned[1]['assigned_grid_render_cell']['grid_cells'][0]['state']);
            $t->same(['row_id' => 0, 'col_id' => 0, 'render_cell_index' => 0], $byAssigned[1]['assigned_grid_render_cell']['grid_cells'][0]['covered_by']);
            $t->same('Inventory axis', $byAssigned[1]['assigned_grid_render_cell']['render_cell']['text']);
            $t->same(['h-r0-c0', 'h-r2-c0'], $byAssigned[4]['assigned_grid_render_cell']['render_cell']['headers']);
            $t->same(['Inventory axis', 'Media group'], $byAssigned[4]['assigned_grid_render_cell']['render_cell']['header_texts']);
            $t->same('Images', $byAssigned[4]['assigned_grid_render_cell']['render_cell']['text']);
            $t->same('h-r0-c0', $byAssigned[0]['candidate_grid_render_cells'][0]['render_cell']['header_id']);
        } finally {
            unlink($path);
        }
    },
    'routes upstream OCR prediction objects through forced table recognition' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-forced-ocr-table-prediction-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% forced OCR table prediction supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'Scanned import matrix', 'bbox' => [72.0, 48.0, 340.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale pdftext table line should not survive.', 'bbox' => [72.0, 178.0, 430.0, 196.0]],
                ['text' => 'Review after table.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 340.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 360.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 360.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 170.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [190.0, 0.0, 360.0, 80.0]],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_detector_cells' => [[
                        ['bbox' => [12.0, 8.0, 160.0, 28.0], 'text' => null],
                        ['bbox' => [198.0, 8.0, 344.0, 28.0], 'text' => null],
                        ['bbox' => [12.0, 44.0, 160.0, 66.0], 'text' => null],
                        ['bbox' => [198.0, 44.0, 344.0, 66.0], 'text' => null],
                    ]],
                    'table_ocr_text_lines' => [[
                        'text_lines' => [
                            ['text' => 'Metric'],
                            ['text' => 'State'],
                            ['text' => 'Prediction OCR'],
                            ['text' => 'Recovered'],
                        ],
                    ]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                    'ocr_all_pages' => true,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->contains('# Scanned Import Matrix', $result['text']);
            $t->contains('| Metric         | State     |', $result['text']);
            $t->contains('| Prediction OCR | Recovered |', $result['text']);
            $t->contains('Review after table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale pdftext table line should not survive.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same(true, $result['metadata']['table_detect_boxes']);
            $t->same([4], $result['metadata']['table_cell_counts']);
            $t->same('Prediction OCR', $result['metadata']['table_assigned_cells'][0][2]['text']);
        } finally {
            unlink($path);
        }
    },
    'preserves forced OCR text when supplied table structure cells are reordered' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-ocr-structure-assignment-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table OCR structure assignment supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'OCR structure assignment matrix', 'bbox' => [72.0, 48.0, 440.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale OCR structure table text should be replaced.', 'bbox' => [72.0, 178.0, 460.0, 196.0]],
                ['text' => 'After OCR structure table.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 440.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 38.0, 200.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 96.0, 72.0]],
                    ['col_id' => 1, 'bbox' => [98.0, 0.0, 200.0, 72.0]],
                ],
                'cells' => [
                    ['bbox' => [100.0, 0.0, 190.0, 24.0], 'text' => null],
                    ['bbox' => [0.0, 0.0, 90.0, 24.0], 'text' => null],
                    ['bbox' => [100.0, 40.0, 190.0, 64.0], 'text' => null],
                    ['bbox' => [0.0, 40.0, 90.0, 64.0], 'text' => null],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_detector_cells' => [[
                        ['bbox' => [0.0, 0.0, 90.0, 24.0], 'text' => null],
                        ['bbox' => [100.0, 0.0, 190.0, 24.0], 'text' => null],
                        ['bbox' => [0.0, 40.0, 90.0, 64.0], 'text' => null],
                        ['bbox' => [100.0, 40.0, 190.0, 64.0], 'text' => null],
                    ]],
                    'table_ocr_text_lines' => [[
                        'text_lines' => [
                            ['text' => 'Feature', 'bbox' => [2.0, 4.0, 88.0, 20.0]],
                            ['text' => 'Status', 'bbox' => [102.0, 4.0, 188.0, 20.0]],
                            ['text' => 'Imported', 'bbox' => [2.0, 44.0, 88.0, 60.0]],
                            ['text' => 'Ready', 'bbox' => [102.0, 44.0, 188.0, 60.0]],
                        ],
                    ]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                    'ocr_all_pages' => true,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->contains('# Ocr Structure Assignment Matrix', $result['text']);
            $t->contains('| Feature  | Status |', $result['text']);
            $t->contains('| Imported | Ready  |', $result['text']);
            $t->contains('After OCR structure table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale OCR structure table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([true], $result['metadata']['table_needs_ocr']);
            $t->same([4], $result['metadata']['table_cell_counts']);
            $t->same(['Status', 'Feature', 'Ready', 'Imported'], array_column($result['metadata']['table_assigned_cells'][0], 'text'));
            $t->same([0], $result['metadata']['table_assigned_cells'][0][1]['col_ids']);
            $t->same([1], $result['metadata']['table_assigned_cells'][0][0]['col_ids']);
        } finally {
            unlink($path);
        }
    },
    'uses pdftext table-line structures when recognition output needs cells' => static function (TestRunner $t) use ($pdftextPage, $pdfTextChars, $pdfTextLine): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-textline-structure-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table text-line structure supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'Layout table import', 'bbox' => [72.0, 48.0, 340.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Legacy table text should be replaced.', 'bbox' => [72.0, 178.0, 430.0, 196.0]],
                ['text' => 'After structured table.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 340.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 358.0, 32.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 38.0, 358.0, 72.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 170.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [180.0, 0.0, 358.0, 80.0]],
                ],
            ];
            $tableTextLines = [[
                'width' => 612,
                'height' => 792,
                'rotation' => 0,
                'blocks' => [[
                    'lines' => [
                        $pdfTextLine([
                            $pdfTextChars('Feature', 84.0, 160.0),
                            $pdfTextChars('Status', 260.0, 160.0),
                        ]),
                        $pdfTextLine([
                            $pdfTextChars('Imported', 84.0, 196.0),
                            $pdfTextChars('Ready', 260.0, 196.0),
                        ]),
                        $pdfTextLine([
                            $pdfTextChars('Stale', 50.0, 140.0),
                        ]),
                    ],
                ]],
            ]];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_text_lines' => $tableTextLines,
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->contains('# Layout Table Import', $result['text']);
            $t->contains('| Feature  | Status |', $result['text']);
            $t->contains('| Imported | Ready  |', $result['text']);
            $t->contains('After structured table.', $result['text']);
            $t->true(!str_contains($result['text'], 'Legacy table text should be replaced.'));
            $t->true(!str_contains($result['text'], 'Stale'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([false], $result['metadata']['table_needs_ocr']);
            $t->same(false, $result['metadata']['table_detect_boxes']);
            $t->same([4], $result['metadata']['table_cell_counts']);
            $t->same([12.0, 10.0, 74.0, 24.0], $result['metadata']['table_assigned_cells'][0][0]['bbox']);
            $t->same('Imported', $result['metadata']['table_assigned_cells'][0][2]['text']);
        } finally {
            unlink($path);
        }
    },
    'surfaces pdftext table-cell crop boundary metadata for WordPress review' => static function (TestRunner $t) use ($pdftextPage, $pdfTextChars, $pdfTextLine): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-text-cell-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table text cell boundary supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'Table text cell boundary review', 'bbox' => [72.0, 48.0, 440.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Legacy crop-edge table text should be replaced.', 'bbox' => [72.0, 178.0, 430.0, 196.0]],
                ['text' => 'After crop cell review.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 440.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 430.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 358.0, 32.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 170.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [180.0, 0.0, 358.0, 80.0]],
                ],
            ];
            $tableTextLines = [[
                'width' => 612,
                'height' => 792,
                'rotation' => 0,
                'blocks' => [[
                    'lines' => [
                        $pdfTextLine([
                            $pdfTextChars('Margin', 66.0, 160.0),
                            $pdfTextChars('Value', 260.0, 160.0),
                        ]),
                    ],
                ]],
            ]];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_text_lines' => $tableTextLines,
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $review = $result['metadata']['table_text_cell_boundary_reviews'][0] ?? [];

            $t->contains('# Table Text Cell Boundary Review', $result['text']);
            $t->contains('| Margin | Value |', $result['text']);
            $t->contains('After crop cell review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Legacy crop-edge table text should be replaced.'));
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same([false], $result['metadata']['table_needs_ocr']);
            $t->same([2], $result['metadata']['table_cell_counts']);
            $t->same([-6.0, 10.0, 47.0, 24.0], $result['metadata']['table_assigned_cells'][0][0]['bbox']);
            $t->same('table_text_cell_geometry_boundary', $review['review_target'] ?? null);
            $t->same(['width' => 358, 'height' => 80], $review['table_crop_size'] ?? null);
            $t->same(1, $review['clipped_cell_count'] ?? null);
            $t->same('clipped_to_table_image', $review['cells'][0]['status'] ?? null);
            $t->same([-6.0, 10.0, 47.0, 24.0], $review['cells'][0]['original_bbox'] ?? null);
            $t->same([0.0, 10.0, 47.0, 24.0], $review['cells'][0]['bounded_bbox'] ?? null);
            $t->same(true, $review['cells'][0]['upstream_cell_bbox_retained'] ?? null);
        } finally {
            unlink($path);
        }
    },
    'clips supplied table grid geometry to rendered crop boundaries before WordPress review metadata' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-geometry-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table geometry boundary supplied pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'Table geometry boundary review', 'bbox' => [72.0, 48.0, 440.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Stale clipped table text should be replaced.', 'bbox' => [72.0, 176.0, 430.0, 196.0]],
                ['text' => 'After clipped geometry review.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 440.0, 68.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 372.0, 230.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [-5.0, -4.0, 310.0, 28.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 300.0, 68.0]],
                    ['row_id' => 2, 'bbox' => [0.0, 130.0, 300.0, 150.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [-10.0, 0.0, 100.0, 140.0]],
                    ['col_id' => 1, 'bbox' => [110.0, 0.0, 330.0, 140.0]],
                    ['col_id' => 2, 'bbox' => [340.0, 0.0, 360.0, 80.0]],
                ],
                'cells' => [
                    ['bbox' => [5.0, 4.0, 295.0, 20.0], 'text' => 'Header'],
                    ['bbox' => [5.0, 44.0, 90.0, 62.0], 'text' => 'Images'],
                    ['bbox' => [120.0, 44.0, 290.0, 62.0], 'text' => 'Ready'],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $boundary = $gridReview['geometry_boundary_review'] ?? [];
            $gridByPosition = [];
            foreach (($gridReview['grid_cells'] ?? []) as $gridCell) {
                if (is_array($gridCell)) {
                    $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
                }
            }

            $t->contains('# Table Geometry Boundary Review', $result['text']);
            $t->contains('| Header |       |', $result['text']);
            $t->contains('| Images | Ready |', $result['text']);
            $t->contains('After clipped geometry review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale clipped table text should be replaced.'));
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same('table_grid_geometry_boundary', $boundary['review_target'] ?? null);
            $t->same(['width' => 300, 'height' => 80], $boundary['image_size'] ?? null);
            $t->same(3, $boundary['clipped_band_count'] ?? null);
            $t->same(2, $boundary['excluded_band_count'] ?? null);
            $t->same([0.0, 0.0, 300.0, 28.0], $gridReview['render_cells'][0]['grid_bbox']);
            $t->same([0.0, 40.0, 100.0, 68.0], $gridByPosition['1:0']['grid_bbox']);
            $t->same([110.0, 40.0, 300.0, 68.0], $gridByPosition['1:1']['grid_bbox']);
            $t->same('excluded_outside_table_image', $boundary['row_bands'][2]['status']);
            $t->same('excluded_outside_table_image', $boundary['col_bands'][2]['status']);
        } finally {
            unlink($path);
        }
    },
    'converts a fuller multicolcnn supplied dictionary excerpt with upstream finalization metadata' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-multicolcnn-supplied-document.php';
        $path = sys_get_temp_dir() . '/markerpdf-multicolcnn-supplied-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% multicolcnn supplied dictionary fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                $fixture['pdftextPages'],
                $fixture['options'],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $score = (new BenchmarkScorer())->scoreText(
                $result['text'],
                $fixture['referenceExcerpt'],
                $fixture['chunkLength']
            );

            $t->same($fixture['expectedMarkdown'], $result['text']);
            $t->contains('Perspective-Free Counting', $result['text']);
            $t->true($score > $fixture['scoreThreshold']);
            $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries']);
            $t->same([
                'ocr_pages' => 0,
                'ocr_failed' => 0,
                'ocr_success' => 0,
                'ocr_engine' => 'none',
            ], $result['metadata']['ocr_stats']);
            $t->same(0, $result['metadata']['block_stats']['table']);
            $t->same(0, $result['metadata']['block_stats']['code']);
            $t->same(0, $result['metadata']['block_stats']['header_footer']);
            $t->same([0], $result['metadata']['page_range']);
            $t->same(1, $result['metadata']['context']['lowres_image_count']);
            $t->same('Abstract', $result['metadata']['pdf_toc'][0]['title']);
            $t->same('An Aggregated Multicolumn Dilated Convolution Network For Perspective-Free Counting', $result['metadata']['computed_toc'][0]['title']);
        } finally {
            unlink($path);
        }
    },
    'converts a fuller switch transformer supplied dictionary excerpt with styled spans' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-switch-transformers-supplied-document.php';
        $path = sys_get_temp_dir() . '/markerpdf-switch-transformers-supplied-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% switch transformer supplied dictionary fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                $fixture['pdftextPages'],
                $fixture['options'],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $score = (new BenchmarkScorer())->scoreText(
                $result['text'],
                $fixture['referenceExcerpt'],
                $fixture['chunkLength']
            );

            $t->same($fixture['expectedMarkdown'], $result['text']);
            $t->contains('select *different* parameters', $result['text']);
            $t->true($score > $fixture['scoreThreshold']);
            $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries']);
            $t->same([
                'ocr_pages' => 0,
                'ocr_failed' => 0,
                'ocr_success' => 0,
                'ocr_engine' => 'none',
            ], $result['metadata']['ocr_stats']);
            $t->same([0], $result['metadata']['page_range']);
            $t->same(1, $result['metadata']['context']['lowres_image_count']);
            $t->same('Abstract', $result['metadata']['pdf_toc'][0]['title']);
            $t->same('Switch Transformers: Scaling To Trillion Parameter Models With Simple And Efficient Sparsity', $result['metadata']['computed_toc'][0]['title']);
        } finally {
            unlink($path);
        }
    },
    'converts the switch transformer contents table page slice through supplied table recognition' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-switch-transformers-toc-table-supplied-document.php';
        $path = sys_get_temp_dir() . '/markerpdf-switch-transformers-toc-table-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% switch transformer toc table supplied fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                $fixture['pdftextPages'],
                $fixture['options'],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $score = (new BenchmarkScorer())->scoreText(
                $result['text'],
                $fixture['markerExcerpt'],
                $fixture['chunkLength']
            );

            $t->same($fixture['expectedMarkdown'], $result['text']);
            $t->contains('## Contents', $result['text']);
            $t->contains('| 2.1 | Simplifying Sparse Routing', $result['text']);
            $t->contains('Expert\\-Parallelism', $result['text']);
            $t->true($score > $fixture['scoreThreshold']);
            $t->same(['layout', 'order', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same(1, $result['metadata']['block_stats']['table']);
            $t->same(1, $result['metadata']['inserted_tables']);
            $t->same([1], $result['metadata']['table_plan']['table_counts']);
            $t->same([0], $result['metadata']['table_plan']['doc_indexes']);
            $t->same('Preventing Token Dropping with No-Token-Left-Behind', $result['metadata']['table_assigned_cells'][0][19]['text']);
            $t->same([3], $result['metadata']['table_assigned_cells'][0][8]['col_ids']);
            $t->same('Contents', $result['metadata']['computed_toc'][2]['title']);
            $t->same(33, $result['context']['document_page_count']);
        } finally {
            unlink($path);
        }
    },
    'converts the upstream switch transformer table 1 slice with unicode table metrics and caption' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-switch-transformers-table1-supplied-document.php';
        $path = sys_get_temp_dir() . '/markerpdf-switch-transformers-table1-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% switch transformer table 1 supplied fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                $fixture['pdftextPages'],
                $fixture['options'],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $score = (new BenchmarkScorer())->scoreText(
                $result['text'],
                $fixture['markerExcerpt'],
                $fixture['chunkLength']
            );

            $t->contains('## 2.4 Improved Training And Fine-Tuning Techniques', $result['text']);
            $t->contains('| Model', $result['text']);
            $t->contains('Speed (↑)', $result['text']);
            $t->contains('Not achieved†', $result['text']);
            $t->contains('Switch\\-Base+', $result['text']);
            $t->contains('Table 1: Benchmarking Switch versus MoE.', $result['text']);
            $t->true($score > $fixture['scoreThreshold']);
            $t->same(['layout', 'order', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same(1, $result['metadata']['block_stats']['table']);
            $t->same(1, $result['metadata']['inserted_tables']);
            $t->same([1], $result['metadata']['table_plan']['table_counts']);
            $t->same('Switch-Base+', $result['metadata']['table_assigned_cells'][0][55]['text']);
            $t->same(33, $result['context']['document_page_count']);
        } finally {
            unlink($path);
        }
    },
    'converts supplied equation dictionaries inside the document-level pipeline' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-formula-supplied-document.php';
        $path = sys_get_temp_dir() . '/markerpdf-formula-supplied-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% formula supplied dictionary fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                $fixture['pdftextPages'],
                $fixture['options'],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->same($fixture['expectedMarkdown'], $result['text']);
            $t->contains('$$E=mc^2$$', $result['text']);
            $t->true(!str_contains($result['text'], 'E = m c ^ 2'));
            $t->same(['layout', 'order', 'equation-recognition'], $result['metadata']['supplied_boundaries']);
            $t->same([
                'successful_ocr' => 1,
                'unsuccessful_ocr' => 0,
                'equations' => 1,
            ], $result['metadata']['block_stats']['equations']);
            $t->same('$$E=mc^2$$', $result['metadata']['converted_equation_spans'][0]['text']);
            $t->same('WordPress math migration', $result['metadata']['pdf_toc'][0]['title']);
            $t->same('WordPress math migration', $result['metadata']['computed_toc'][0]['title']);
        } finally {
            unlink($path);
        }
    },
    'preserves upstream table boundaries before nested equation and image regions' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-structure-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% structure boundary supplied fixture\n%%EOF");

        try {
            $page = $pdftextPage(0, [
                ['text' => 'Structure import', 'bbox' => [72.0, 60.0, 360.0, 78.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Raw table formula image text', 'bbox' => [72.0, 160.0, 420.0, 178.0]],
                ['text' => 'After structure.', 'bbox' => [72.0, 260.0, 420.0, 278.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 60.0, 360.0, 78.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 420.0, 210.0]],
                    ['label' => 'Formula', 'bbox' => [80.0, 158.0, 230.0, 182.0]],
                    ['label' => 'Picture', 'bbox' => [260.0, 158.0, 420.0, 200.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 260.0, 420.0, 278.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 30.0, 300.0, 60.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 150.0, 60.0]],
                    ['col_id' => 1, 'bbox' => [150.0, 0.0, 300.0, 60.0]],
                ],
                'cells' => [
                    ['bbox' => [0.0, 0.0, 140.0, 25.0], 'text' => 'Metric'],
                    ['bbox' => [150.0, 0.0, 290.0, 25.0], 'text' => 'Value'],
                    ['bbox' => [0.0, 30.0, 140.0, 55.0], 'text' => 'Equation'],
                    ['bbox' => [150.0, 30.0, 290.0, 55.0], 'text' => 'E=mc^2'],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_text_lines' => [['blocks' => []]],
                    'equation_predictions' => ['$$E=mc^2$$'],
                    'image_payloads' => [['PNG-CHART-BYTES']],
                ]
            );

            $t->contains('# Structure Import', $result['text']);
            $t->contains('| Metric   | Value  |', $result['text']);
            $t->contains('| Equation | E=mc^2 |', $result['text']);
            $t->contains('After structure.', $result['text']);
            $t->true(!str_contains($result['text'], '$$E=mc^2$$'));
            $t->true(!str_contains($result['text'], '![0_image_0.png](0_image_0.png)'));
            $t->true(!str_contains($result['text'], 'Raw table formula image text'));
            $t->same([], $result['images']);
            $t->same(['layout', 'table-recognition', 'table-formatting', 'equation-recognition', 'image-extraction'], $result['metadata']['supplied_boundaries']);
            $t->same(1, $result['metadata']['block_stats']['table']);
            $t->same(1, $result['metadata']['inserted_tables']);
            $t->same(['successful_ocr' => 0, 'unsuccessful_ocr' => 0, 'equations' => 0], $result['metadata']['block_stats']['equations']);
            $t->same(0, $result['metadata']['block_stats']['images']);
        } finally {
            unlink($path);
        }
    },
    'uses merged supplied table boundaries before seam equation and image regions' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-merged-table-structure-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% merged table structure boundary supplied fixture\n%%EOF");

        try {
            $page = $pdftextPage(0, [
                ['text' => 'Merged structure import', 'bbox' => [72.0, 60.0, 420.0, 78.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Raw merged table formula image seam text', 'bbox' => [72.0, 160.0, 420.0, 178.0]],
                ['text' => 'After merged structure.', 'bbox' => [72.0, 260.0, 420.0, 278.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 60.0, 420.0, 78.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 250.0, 210.0]],
                    ['label' => 'Table', 'bbox' => [258.0, 150.0, 420.0, 210.0]],
                    ['label' => 'Formula', 'bbox' => [252.0, 160.0, 257.0, 182.0]],
                    ['label' => 'Picture', 'bbox' => [252.0, 184.0, 257.0, 205.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 260.0, 420.0, 278.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 30.0, 300.0, 60.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 150.0, 60.0]],
                    ['col_id' => 1, 'bbox' => [150.0, 0.0, 300.0, 60.0]],
                ],
                'cells' => [
                    ['bbox' => [0.0, 0.0, 140.0, 25.0], 'text' => 'Metric'],
                    ['bbox' => [150.0, 0.0, 290.0, 25.0], 'text' => 'Value'],
                    ['bbox' => [0.0, 30.0, 140.0, 55.0], 'text' => 'Seam'],
                    ['bbox' => [150.0, 30.0, 290.0, 55.0], 'text' => 'Protected'],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'recognized_tables' => [$recognizedTable],
                    'table_text_lines' => [['blocks' => []]],
                    'equation_predictions' => ['$$E=mc^2$$'],
                    'image_payloads' => [['PNG-SEAM-BYTES']],
                ]
            );

            $t->contains('# Merged Structure Import', $result['text']);
            $t->contains('| Metric | Value     |', $result['text']);
            $t->contains('| Seam   | Protected |', $result['text']);
            $t->contains('After merged structure.', $result['text']);
            $t->true(!str_contains($result['text'], '$$E=mc^2$$'));
            $t->true(!str_contains($result['text'], '![0_image_0.png](0_image_0.png)'));
            $t->true(!str_contains($result['text'], 'Raw merged table formula image seam text'));
            $t->same([], $result['images']);
            $t->same(['layout', 'table-recognition', 'table-formatting', 'equation-recognition', 'image-extraction'], $result['metadata']['supplied_boundaries']);
            $t->same([1], $result['metadata']['table_plan']['table_counts']);
            $t->same([[192.0, 400.0, 1120.0, 560.0]], $result['metadata']['table_plan']['table_bboxes']);
            $t->same(1, $result['metadata']['block_stats']['table']);
            $t->same(['successful_ocr' => 0, 'unsuccessful_ocr' => 0, 'equations' => 0], $result['metadata']['block_stats']['equations']);
            $t->same(0, $result['metadata']['block_stats']['images']);
        } finally {
            unlink($path);
        }
    },
    'short-circuits supplied documents with no extracted blocks like convert_single_pdf' => static function (TestRunner $t): void {
        $path = sys_get_temp_dir() . '/markerpdf-empty-supplied-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% empty supplied dictionary fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [[
                    'page' => 0,
                    'bbox' => [0.0, 0.0, 612.0, 792.0],
                    'rotation' => 0,
                    'blocks' => [],
                ]],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Text', 'bbox' => [72.0, 72.0, 540.0, 120.0]],
                        ],
                    ]],
                    'order_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 0, 'bbox' => [72.0, 72.0, 540.0, 120.0]],
                        ],
                    ]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->same('', $result['text']);
            $t->same([], $result['images']);
            $t->same(true, $result['metadata']['empty_text_blocks']);
            $t->same([], $result['metadata']['supplied_boundaries']);
            $t->same([
                'ocr_pages' => 0,
                'ocr_failed' => 0,
                'ocr_success' => 0,
                'ocr_engine' => 'none',
            ], $result['metadata']['ocr_stats']);
            $t->same([0], $result['metadata']['page_range']);
        } finally {
            unlink($path);
        }
    },
    'threads supplied page images through the upstream extract_images boundary after bad span filtering' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-supplied-images-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% supplied image pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'Import overview paragraph.', 'bbox' => [72.0, 60.0, 360.0, 78.0]],
                ['text' => 'Chart OCR should not render.', 'bbox' => [84.0, 180.0, 310.0, 216.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Text', 'bbox' => [72.0, 60.0, 360.0, 78.0]],
                    ['label' => 'Picture', 'bbox' => [80.0, 170.0, 330.0, 230.0]],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'image_payloads' => [['PNG-CHART-BYTES']],
                ]
            );

            $t->contains('Import overview paragraph.', $result['text']);
            $t->contains('![0_image_0.png](0_image_0.png)', $result['text']);
            $t->true(!str_contains($result['text'], 'Chart OCR should not render.'));
            $t->same(['0_image_0.png' => 'PNG-CHART-BYTES'], $result['images']);
            $t->same(['layout', 'image-extraction'], $result['metadata']['supplied_boundaries']);
            $t->same(1, $result['metadata']['block_stats']['images']);
        } finally {
            unlink($path);
        }
    },
    'acts as a benchmark runner callback for actual CI reference excerpts' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $ciPdftextPage): void {
        $base = $makeTempDir();
        $pdfFolder = $base . '/pdfs';
        $referenceFolder = $base . '/references';
        $markdownFolder = $base . '/markdown';
        mkdir($pdfFolder);
        mkdir($referenceFolder);
        mkdir($markdownFolder);

        try {
            $fixture = require __DIR__ . '/../fixtures/upstream-ci-benchmark-short.php';
            $pairsByDocument = [];
            foreach ($fixture['benchmarkPairs'] as $pair) {
                $pairsByDocument[$pair['document']] = $pair;
                file_put_contents($pdfFolder . '/' . $pair['document'], "%PDF-1.4\n% " . $pair['document'] . "\n%%EOF");
                file_put_contents($referenceFolder . '/' . preg_replace('/\.[^.]*$/', '.md', $pair['document']), $pair['referenceExcerpt']);
            }

            $converter = new SuppliedDocumentConverter();
            $result = (new BenchmarkRunner())->run(
                $pdfFolder,
                $referenceFolder,
                [
                    'marker' => static fn (string $pdfPath, string $document): string => $converter->convert(
                        $pdfPath,
                        [$ciPdftextPage($pairsByDocument[$document]['markerExcerpt'])],
                        [
                            'metadata' => ['languages' => ['English']],
                            'layout_results' => [[
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Text', 'bbox' => [72.0, 72.0, 540.0, 760.0]],
                                ],
                            ]],
                        ],
                        new MarkerSettings(['EXTRACT_IMAGES' => false])
                    )['text'],
                ],
                static fn (string $pdfPath): int => str_contains($pdfPath, 'switch_trans') ? 4 : 3,
                $markdownFolder,
                array_map(static fn (array $pair): int => $pair['chunkLength'], $pairsByDocument)
            );

            (new BenchmarkReportVerifier())->verifyMarkerScores($result['report']);
            $t->same(['multicolcnn.pdf', 'switch_trans.pdf'], $result['benchmark_files']);
            $t->true($result['report']['marker']['files']['multicolcnn.pdf']['score'] > 0.34);
            $t->true($result['report']['marker']['files']['switch_trans.pdf']['score'] > 0.40);
            $t->contains('Learning to count', (string) file_get_contents($markdownFolder . '/marker_multicolcnn.md'));
        } finally {
            $removeTree($base);
        }
    },
    'rejects malformed supplied document options before benchmark import' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-supplied-document-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n%%EOF");
        try {
            $converter = new SuppliedDocumentConverter();
            $page = $pdftextPage(0, [
                ['text' => 'Option validation source text.', 'bbox' => [72.0, 72.0, 360.0, 84.0]],
            ]);
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $converter->convert($path, [$page], ['layout_results' => ['not' => 'a-list']])
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $converter->convert($path, [$page], ['batch_multiplier' => 'fast'])
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $converter->convert($path, [$page], ['image_payloads' => ['not-a-page-list']])
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $converter->convert($path, [$page], ['ocr_stats' => 'none'])
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $converter->convert($path, [$page], ['equation_results' => [['score' => 1.0]]])
            );
        } finally {
            unlink($path);
        }
    },
];
