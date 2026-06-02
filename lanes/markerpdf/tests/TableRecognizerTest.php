<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\TableFormatter;
use PortLibs\MarkerPDF\TableRecognizer;

$tableResult = static function (): array {
    return [
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
            ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
            ['row_id' => 2, 'bbox' => [0.0, 80.0, 240.0, 110.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 120.0]],
            ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 120.0]],
        ],
        'cells' => [
            ['bbox' => [10.0, 5.0, 90.0, 25.0], 'text' => 'Block'],
            ['bbox' => [130.0, 5.0, 230.0, 25.0], 'text' => 'Status'],
            ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Intro'],
            ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => 'Published'],
            ['bbox' => [10.0, 85.0, 90.0, 105.0], 'text' => 'Media .... 24'],
            ['bbox' => [130.0, 85.0, 230.0, 105.0], 'text' => "Needs\nReview"],
        ],
    ];
};

$mergedSpanResult = static function (): array {
    return [
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
        'cells' => [
            ['bbox' => [5.0, 5.0, 295.0, 20.0], 'text' => 'Inventory summary'],
            ['bbox' => [5.0, 36.0, 92.0, 109.0], 'text' => 'Media group'],
            ['bbox' => [110.0, 39.0, 190.0, 56.0], 'text' => 'Image count'],
            ['bbox' => [210.0, 39.0, 290.0, 56.0], 'text' => '12'],
            ['bbox' => [110.0, 89.0, 190.0, 106.0], 'text' => 'Review state'],
            ['bbox' => [210.0, 89.0, 290.0, 106.0], 'text' => 'Needs review'],
        ],
    ];
};

$discontiguousSpanResult = static function (): array {
    return [
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
            ['row_id' => 1, 'bbox' => [0.0, 45.0, 200.0, 75.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 120.0, 90.0]],
            ['col_id' => 1, 'bbox' => [120.0, 0.0, 140.0, 90.0]],
            ['col_id' => 2, 'bbox' => [140.0, 0.0, 200.0, 90.0]],
        ],
        'cells' => [
            ['bbox' => [0.0, 5.0, 190.0, 24.0], 'text' => 'Section note'],
            ['bbox' => [125.0, 50.0, 135.0, 70.0], 'text' => 'Gap marker'],
            ['bbox' => [150.0, 50.0, 190.0, 70.0], 'text' => 'Status'],
        ],
    ];
};

$rotatedSpanResult = static function (): array {
    return [
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
        'cells' => [
            ['bbox' => [3.0, 5.0, 22.0, 235.0], 'text' => 'Rotated inventory'],
            ['bbox' => [36.0, 5.0, 108.0, 65.0], 'text' => 'Media group'],
            ['bbox' => [38.0, 95.0, 58.0, 145.0], 'text' => 'Image count'],
            ['bbox' => [38.0, 175.0, 58.0, 235.0], 'text' => '12'],
            ['bbox' => [88.0, 95.0, 108.0, 150.0], 'text' => 'Review state'],
            ['bbox' => [88.0, 175.0, 108.0, 230.0], 'text' => 'Needs review'],
        ],
    ];
};

$tablePage = static function (): array {
    return [
        'pnum' => 2,
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'layout' => [
            'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
            'bboxes' => [
                ['label' => 'Table', 'bbox' => [120.0, 220.0, 560.0, 420.0]],
            ],
        ],
        'blocks' => [
            [
                'type' => 'Text',
                'bbox' => [60.0, 60.0, 280.0, 92.0],
                'lines' => [
                    ['text' => 'Recognized table follows.', 'bbox' => [60.0, 64.0, 260.0, 80.0]],
                ],
            ],
            [
                'type' => 'Table',
                'bbox' => [60.0, 110.0, 280.0, 210.0],
                'lines' => [
                    ['text' => 'Old PDF table text', 'bbox' => [62.0, 116.0, 270.0, 136.0]],
                ],
            ],
        ],
    ];
};

$heuristicRows = static function (): array {
    $rows = [];
    for ($row = 0; $row < 8; $row++) {
        $jitter = $row < 4 ? 0.0 : 2.0;
        $top = 10.0 + ($row * 30.0);
        $bottom = $top + 16.0;
        $rows[] = [
            ['bbox' => [10.0 + $jitter, $top, 70.0 + $jitter, $bottom], 'text' => 'Block ' . $row],
            ['bbox' => [210.0 + $jitter, $top, 270.0 + $jitter, $bottom], 'text' => 'Status ' . $row],
            ['bbox' => [410.0 + $jitter, $top, 470.0 + $jitter, $bottom], 'text' => 'Owner ' . $row],
        ];
    }

    return $rows;
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

$wordpressTable = static function (string $tableMarkdown): string {
    $rows = array_values(array_filter(
        preg_split('/\R/', trim($tableMarkdown)) ?: [],
        static fn (string $row): bool => trim($row, " \t|") !== '' && !preg_match('/^\s*\|-+\|-+\|?\s*$/', $row)
    ));
    $htmlRows = [];
    foreach ($rows as $row) {
        $cells = array_map(
            static fn (string $cell): string => htmlspecialchars(trim(str_replace('\\-', '-', $cell)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            explode('|', trim($row, " \t|"))
        );
        $htmlRows[] = '<tr><td>' . implode('</td><td>', $cells) . '</td></tr>';
    }

    return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><tbody>"
        . implode('', $htmlRows)
        . "</tbody></table></figure>\n<!-- /wp:table -->\n";
};

return [
    'uses upstream table recognition batch size defaults and overrides' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();

        $t->same(6, $recognizer->batchSize());
        $t->same(3, $recognizer->batchSize(new MarkerSettings(['TABLE_REC_BATCH_SIZE' => '3'])));
    },
    'routes supplied text-line table blocks and OCR-needed detector cells like get_cells' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $cells = $recognizer->getCells(
            [
                [20.0, 20.0, 220.0, 120.0],
                [30.0, 140.0, 220.0, 260.0],
            ],
            [
                ['width' => 1200, 'height' => 1600],
                ['width' => 1200, 'height' => 1600],
            ],
            [
                ['table_blocks' => [
                    ['bbox' => [30.0, 30.0, 90.0, 50.0], 'text' => 'Block'],
                    ['bbox' => [120.0, 30.0, 210.0, 50.0], 'text' => 'Status'],
                ]],
                null,
            ],
            [
                1 => [
                    ['bbox' => [35.0, 150.0, 90.0, 170.0], 'text' => null],
                    ['bbox' => [120.0, 150.0, 210.0, 170.0], 'text' => null],
                ],
            ]
        );

        $t->same([false, true], $cells['needs_ocr']);
        $t->same('Block', $cells['table_cells'][0][0]['text']);
        $t->same('', $cells['table_cells'][1][0]['text']);
    },
    'filters duplicated text-line payloads to each table bbox like get_table_blocks' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $fullPageBlocks = [
            ['bbox' => [10.0, 10.0, 120.0, 36.0], 'text' => 'Left table'],
            ['bbox' => [230.0, 10.0, 340.0, 36.0], 'text' => 'Right table'],
        ];
        $cells = $recognizer->getCells(
            [
                [0.0, 0.0, 150.0, 120.0],
                [200.0, 0.0, 360.0, 120.0],
            ],
            [
                ['width' => 400, 'height' => 200],
                ['width' => 400, 'height' => 200],
            ],
            [
                ['table_blocks' => $fullPageBlocks],
                ['blocks' => $fullPageBlocks],
            ]
        );

        $t->same([false, false], $cells['needs_ocr']);
        $t->same(['Left table'], array_column($cells['table_cells'][0], 'text'));
        $t->same(['Right table'], array_column($cells['table_cells'][1], 'text'));
    },
    'extracts highres pdftext dictionary lines into table-local cells like get_table_blocks' => static function (TestRunner $t) use ($pdfTextChars, $pdfTextLine): void {
        $recognizer = new TableRecognizer();
        $fullText = [
            'width' => 1000,
            'height' => 800,
            'rotation' => 0,
            'blocks' => [[
                'lines' => [
                    $pdfTextLine([
                        $pdfTextChars('Key', 110.0, 210.0),
                        $pdfTextChars('Value', 250.0, 210.0),
                    ]),
                    $pdfTextLine([
                        $pdfTextChars('Imported', 110.0, 250.0),
                        $pdfTextChars('Ready', 250.0, 250.0),
                    ]),
                    $pdfTextLine([
                        $pdfTextChars('Stale', 88.0, 190.0),
                    ]),
                ],
            ]],
        ];

        $cells = $recognizer->getCells(
            [[100.0, 200.0, 500.0, 320.0]],
            [['width' => 1000, 'height' => 800]],
            [$fullText]
        );

        $t->same([false], $cells['needs_ocr']);
        $t->same(['Key', 'Value', 'Imported', 'Ready'], array_column($cells['table_cells'][0], 'text'));
        $t->same([10.0, 10.0, 36.0, 24.0], $cells['table_cells'][0][0]['bbox']);
        $t->same([10.0, 50.0, 81.0, 64.0], $cells['table_cells'][0][2]['bbox']);
    },
    'forces supplied detector cells when detect_boxes is enabled' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $cells = $recognizer->getCells(
            [[20.0, 20.0, 220.0, 120.0]],
            [['width' => 1200, 'height' => 1600]],
            [[
                ['bbox' => [30.0, 30.0, 90.0, 50.0], 'text' => 'Text line cell'],
            ]],
            [
                [
                    ['bbox' => [40.0, 40.0, 100.0, 60.0], 'text' => null],
                ],
            ],
            true
        );

        $t->same([true], $cells['needs_ocr']);
        $t->same([40.0, 40.0, 100.0, 60.0], $cells['table_cells'][0][0]['bbox']);
    },
    'drops zero-area supplied detector cells before OCR like tabled get_cells' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $cells = $recognizer->getCells(
            [[20.0, 20.0, 220.0, 120.0]],
            [['width' => 1200, 'height' => 1600]],
            [null],
            [
                [
                    ['bbox' => [40.0, 40.0, 40.0, 60.0], 'text' => null],
                    ['bbox' => [50.0, 72.0, 130.0, 72.0], 'text' => null],
                    ['bbox' => [60.0, 80.0, 140.0, 105.0], 'text' => null],
                ],
            ]
        );

        $t->same([true], $cells['needs_ocr']);
        $t->same(1, count($cells['table_cells'][0]));
        $t->same([60.0, 80.0, 140.0, 105.0], $cells['table_cells'][0][0]['bbox']);
    },
    'assigns recognized table cells to rows and columns then formats markdown' => static function (TestRunner $t) use ($tableResult): void {
        $recognizer = new TableRecognizer();
        $assigned = $recognizer->assignRowsColumns($tableResult(), ['width' => 1200, 'height' => 1600]);
        $markdown = $recognizer->markdownFormat($assigned);

        $t->same([[0], [1], [2]], array_map(static fn (array $cell): array => $cell['row_ids'], [$assigned[0], $assigned[2], $assigned[4]]));
        $t->same([[0], [1]], [$assigned[0]['col_ids'], $assigned[1]['col_ids']]);
        $t->same(
            "| Block    | Status       |\n"
            . "|----------|--------------|\n"
            . "| Intro    | Published    |\n"
            . "| Media 24 | Needs Review |",
            $markdown
        );
    },
    'formats upstream unicode table headers with character width like tabulate' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $markdown = $recognizer->markdownFormat([
            ['bbox' => [0.0, 0.0, 120.0, 20.0], 'text' => 'Model', 'row_ids' => [0], 'col_ids' => [0]],
            ['bbox' => [130.0, 0.0, 220.0, 20.0], 'text' => 'Speed (↑)', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [230.0, 0.0, 320.0, 20.0], 'text' => 'Note', 'row_ids' => [0], 'col_ids' => [2]],
            ['bbox' => [0.0, 30.0, 120.0, 50.0], 'text' => 'Switch-Base', 'row_ids' => [1], 'col_ids' => [0]],
            ['bbox' => [130.0, 30.0, 220.0, 50.0], 'text' => '1000', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [230.0, 30.0, 320.0, 50.0], 'text' => 'achieved†', 'row_ids' => [1], 'col_ids' => [2]],
            ['bbox' => [0.0, 60.0, 120.0, 80.0], 'text' => 'Switch-C', 'row_ids' => [2], 'col_ids' => [0]],
            ['bbox' => [130.0, 60.0, 220.0, 80.0], 'text' => '4x', 'row_ids' => [2], 'col_ids' => [1]],
            ['bbox' => [230.0, 60.0, 320.0, 80.0], 'text' => 'stable', 'row_ids' => [2], 'col_ids' => [2]],
        ]);

        $t->same(
            "| Model        | Speed (↑) | Note      |\n"
            . "|--------------|-----------|-----------|\n"
            . "| Switch\\-Base | 1000      | achieved† |\n"
            . "| Switch\\-C    | 4x        | stable    |",
            $markdown
        );
    },
    'merges multiline continuation rows using assigned column ids despite x jitter' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $assigned = $recognizer->assignRowsColumns(
            [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 34.0, 300.0, 56.0]],
                    ['row_id' => 2, 'bbox' => [0.0, 95.0, 300.0, 125.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 95.0, 130.0]],
                    ['col_id' => 1, 'bbox' => [100.0, 0.0, 195.0, 130.0]],
                    ['col_id' => 2, 'bbox' => [200.0, 0.0, 300.0, 130.0]],
                ],
                'cells' => [
                    ['bbox' => [8.0, 5.0, 88.0, 24.0], 'text' => 'Section'],
                    ['bbox' => [108.0, 5.0, 188.0, 24.0], 'text' => 'Summary'],
                    ['bbox' => [208.0, 5.0, 288.0, 24.0], 'text' => 'Status'],
                    ['bbox' => [112.0, 38.0, 192.0, 53.0], 'text' => 'continued'],
                    ['bbox' => [8.0, 100.0, 88.0, 119.0], 'text' => 'Media'],
                    ['bbox' => [108.0, 100.0, 188.0, 119.0], 'text' => 'Ready'],
                    ['bbox' => [208.0, 100.0, 288.0, 119.0], 'text' => 'Published'],
                ],
            ],
            ['width' => 300, 'height' => 130]
        );
        $byText = [];
        foreach ($assigned as $cell) {
            $byText[$cell['text']] = $cell;
        }

        $t->same([0], $byText['continued']['row_ids']);
        $t->same([1], $byText['continued']['col_ids']);
        $t->same([1], $byText['Media']['row_ids']);
        $t->contains('Summary continued', $recognizer->markdownFormat($assigned));
    },
    'adds tabled-style row and column spans when geometry covers open bands' => static function (TestRunner $t) use ($mergedSpanResult): void {
        $recognizer = new TableRecognizer();
        $assigned = $recognizer->assignRowsColumns($mergedSpanResult(), ['width' => 300, 'height' => 120]);
        $byText = [];
        foreach ($assigned as $cell) {
            $byText[$cell['text']] = $cell;
        }

        $t->same([0], $byText['Inventory summary']['row_ids']);
        $t->same([0, 1, 2], $byText['Inventory summary']['col_ids']);
        $t->same([1, 2], $byText['Media group']['row_ids']);
        $t->same([0], $byText['Media group']['col_ids']);
        $t->same([1], $byText['Image count']['row_ids']);
        $t->same([1], $byText['Image count']['col_ids']);
        $t->same([2], $byText['Needs review']['row_ids']);
        $t->same([2], $byText['Needs review']['col_ids']);
    },
    'stops merged column spans at the first unspanned band like tabled handle_rowcol_spans' => static function (TestRunner $t) use ($discontiguousSpanResult): void {
        $recognizer = new TableRecognizer();
        $assigned = $recognizer->assignRowsColumns($discontiguousSpanResult(), ['width' => 200, 'height' => 90]);
        $byText = [];
        foreach ($assigned as $cell) {
            $byText[$cell['text']] = $cell;
        }

        $t->same([0], $byText['Section note']['row_ids']);
        $t->same([0], $byText['Section note']['col_ids']);
        $t->same([], $recognizer->mergedCellGeometry($assigned, $discontiguousSpanResult()['rows'], $discontiguousSpanResult()['cols']));
    },
    'exports merged-cell grid geometry for WordPress rowspan and colspan review' => static function (TestRunner $t) use ($mergedSpanResult): void {
        $recognizer = new TableRecognizer();
        $result = $mergedSpanResult();
        $assigned = $recognizer->assignRowsColumns($result, ['width' => 300, 'height' => 120]);
        $geometry = $recognizer->mergedCellGeometry($assigned, $result['rows'], $result['cols']);

        $t->same(['Inventory summary', 'Media group'], array_column($geometry, 'text'));
        $t->same([1, 3], [$geometry[0]['rowspan'], $geometry[0]['colspan']]);
        $t->same([2, 1], [$geometry[1]['rowspan'], $geometry[1]['colspan']]);
        $t->same(['row_id' => 0, 'col_id' => 0], $geometry[0]['anchor']);
        $t->same(
            [
                ['row_id' => 0, 'col_id' => 0],
                ['row_id' => 0, 'col_id' => 1],
                ['row_id' => 0, 'col_id' => 2],
            ],
            $geometry[0]['grid_cells']
        );
        $t->same([0.0, 0.0, 300.0, 25.0], $geometry[0]['grid_bbox']);
        $t->same([5.0, 5.0, 295.0, 20.0], $geometry[0]['cell_bbox']);
        $t->same(
            [
                ['row_id' => 1, 'col_id' => 0],
                ['row_id' => 2, 'col_id' => 0],
            ],
            $geometry[1]['grid_cells']
        );
        $t->same([0.0, 35.0, 95.0, 110.0], $geometry[1]['grid_bbox']);
        $t->same([5.0, 36.0, 92.0, 109.0], $geometry[1]['cell_bbox']);
    },
    'marks spanning first-row and first-column anchors for WordPress table header review' => static function (TestRunner $t) use ($mergedSpanResult): void {
        $recognizer = new TableRecognizer();
        $result = $mergedSpanResult();
        $assigned = $recognizer->assignRowsColumns($result, ['width' => 300, 'height' => 120]);
        $review = $recognizer->spanningGridReview($assigned, $result['rows'], $result['cols']);
        $renderCells = $review['render_cells'];
        $gridByPosition = [];
        foreach ($review['grid_cells'] as $gridCell) {
            $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
        }

        $t->same([0, 1, 2], $review['rows']);
        $t->same([0, 1, 2], $review['cols']);
        $t->same('Inventory summary', $renderCells[0]['text']);
        $t->same('th', $renderCells[0]['tag']);
        $t->same('colgroup', $renderCells[0]['scope']);
        $t->same('column_header', $renderCells[0]['header_role']);
        $t->same(true, $renderCells[0]['header']);
        $t->same([1, 3], [$renderCells[0]['rowspan'], $renderCells[0]['colspan']]);
        $t->same('Media group', $renderCells[1]['text']);
        $t->same('th', $renderCells[1]['tag']);
        $t->same('rowgroup', $renderCells[1]['scope']);
        $t->same('row_header', $renderCells[1]['header_role']);
        $t->same([2, 1], [$renderCells[1]['rowspan'], $renderCells[1]['colspan']]);
        $t->same('anchor', $gridByPosition['0:0']['state']);
        $t->same('covered', $gridByPosition['0:1']['state']);
        $t->same(['row_id' => 0, 'col_id' => 0, 'render_cell_index' => 0], $gridByPosition['0:2']['covered_by']);
        $t->same('covered', $gridByPosition['2:0']['state']);
        $t->same('td', $gridByPosition['1:1']['tag']);
        $t->same(null, $gridByPosition['1:1']['scope']);
        $t->same('Image count', $gridByPosition['1:1']['text']);
    },
    'preserves rotated rowspan header grid axes for WordPress table review' => static function (TestRunner $t) use ($rotatedSpanResult): void {
        $recognizer = new TableRecognizer();
        $result = $rotatedSpanResult();
        $assigned = $recognizer->assignRowsColumns($result, ['width' => 120, 'height' => 240]);
        $review = $recognizer->spanningGridReview($assigned, $result['rows'], $result['cols']);
        $gridByPosition = [];
        foreach ($review['grid_cells'] as $gridCell) {
            $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
        }

        $t->same(true, $review['rotated']);
        $t->same('rotated', $review['orientation']);
        $t->same('x', $review['row_axis']);
        $t->same('y', $review['col_axis']);
        $t->same([0, 1, 2], $assigned[0]['col_ids']);
        $t->same([1, 2], $assigned[1]['row_ids']);
        $t->same('Rotated inventory', $review['render_cells'][0]['text']);
        $t->same('th', $review['render_cells'][0]['tag']);
        $t->same('colgroup', $review['render_cells'][0]['scope']);
        $t->same(true, $review['render_cells'][0]['rotated']);
        $t->same([0.0, 0.0, 25.0, 240.0], $review['render_cells'][0]['grid_bbox']);
        $t->same('Media group', $review['render_cells'][1]['text']);
        $t->same('rowgroup', $review['render_cells'][1]['scope']);
        $t->same([2, 1], [$review['render_cells'][1]['rowspan'], $review['render_cells'][1]['colspan']]);
        $t->same([35.0, 0.0, 110.0, 70.0], $review['render_cells'][1]['grid_bbox']);
        $t->same('covered', $gridByPosition['0:2']['state']);
        $t->same(['row_id' => 0, 'col_id' => 0, 'render_cell_index' => 0], $gridByPosition['0:1']['covered_by']);
        $t->same('covered', $gridByPosition['2:0']['state']);
        $t->same('td', $gridByPosition['2:1']['tag']);
        $t->same('Review state', $gridByPosition['2:1']['text']);
    },
    'folds OCR continuation covered anchors into rowspan colspan grid review' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $rows = [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 32.0]],
            ['row_id' => 1, 'bbox' => [0.0, 35.0, 300.0, 60.0]],
            ['row_id' => 2, 'bbox' => [0.0, 85.0, 300.0, 110.0]],
        ];
        $cols = [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 120.0]],
            ['col_id' => 1, 'bbox' => [105.0, 0.0, 195.0, 120.0]],
            ['col_id' => 2, 'bbox' => [205.0, 0.0, 300.0, 120.0]],
        ];
        $assigned = [
            ['bbox' => [5.0, 5.0, 295.0, 18.0], 'text' => 'Inventory', 'row_ids' => [0], 'col_ids' => [0, 1, 2]],
            ['bbox' => [30.0, 20.0, 270.0, 30.0], 'text' => 'continued', 'row_ids' => [0], 'col_ids' => [1, 2]],
            ['bbox' => [5.0, 40.0, 90.0, 108.0], 'text' => 'Media group', 'row_ids' => [1, 2], 'col_ids' => [0]],
            ['bbox' => [120.0, 42.0, 180.0, 56.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [210.0, 42.0, 250.0, 56.0], 'text' => '12', 'row_ids' => [1], 'col_ids' => [2]],
            ['bbox' => [120.0, 90.0, 180.0, 106.0], 'text' => 'State', 'row_ids' => [2], 'col_ids' => [1]],
            ['bbox' => [210.0, 90.0, 290.0, 106.0], 'text' => 'Needs review', 'row_ids' => [2], 'col_ids' => [2]],
        ];
        $review = $recognizer->spanningGridReview($assigned, $rows, $cols);
        $gridByPosition = [];
        foreach ($review['grid_cells'] as $gridCell) {
            $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
        }

        $markdown = $recognizer->markdownFormat($assigned);
        $t->contains('Inventory', $markdown);
        $t->contains('continued', $markdown);
        $t->same('Inventory continued', $review['render_cells'][0]['text']);
        $t->same([0, 1, 2], $review['render_cells'][0]['col_ids']);
        $t->same([1, 3], [$review['render_cells'][0]['rowspan'], $review['render_cells'][0]['colspan']]);
        $t->same('colgroup', $review['render_cells'][0]['scope']);
        $t->same(2, $review['render_cells'][0]['source_cell_count']);
        $t->same(1, $review['render_cells'][0]['continuation_count']);
        $t->same(['Inventory', 'continued'], $review['render_cells'][0]['text_parts']);
        $t->same('continued', $review['render_cells'][0]['continuation_cells'][0]['text']);
        $t->same([0], $review['render_cells'][0]['continuation_cells'][0]['row_ids']);
        $t->same([1, 2], $review['render_cells'][0]['continuation_cells'][0]['col_ids']);
        $t->same([5.0, 5.0, 295.0, 30.0], $review['render_cells'][0]['cell_bbox']);
        $t->same([5.0, 5.0, 295.0, 18.0], $review['render_cells'][0]['anchor_cell_bbox']);
        $t->same('anchor', $gridByPosition['0:0']['state']);
        $t->same('Inventory continued', $gridByPosition['0:0']['text']);
        $t->same('covered', $gridByPosition['0:1']['state']);
        $t->same(['row_id' => 0, 'col_id' => 0, 'render_cell_index' => 0], $gridByPosition['0:2']['covered_by']);
        $t->same('Media group', $review['render_cells'][1]['text']);
        $t->same('rowgroup', $review['render_cells'][1]['scope']);
        $t->same([2, 1], [$review['render_cells'][1]['rowspan'], $review['render_cells'][1]['colspan']]);
        $t->same('covered', $gridByPosition['2:0']['state']);
        $t->same('Needs review', $gridByPosition['2:2']['text']);
    },
    'exports OCR span grid cell bboxes from row and column bands for WordPress review' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $rows = [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 32.0]],
            ['row_id' => 1, 'bbox' => [0.0, 35.0, 300.0, 60.0]],
            ['row_id' => 2, 'bbox' => [0.0, 85.0, 300.0, 110.0]],
        ];
        $cols = [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 120.0]],
            ['col_id' => 1, 'bbox' => [105.0, 0.0, 195.0, 120.0]],
            ['col_id' => 2, 'bbox' => [205.0, 0.0, 300.0, 120.0]],
        ];
        $assigned = [
            ['bbox' => [5.0, 5.0, 295.0, 18.0], 'text' => 'Inventory', 'row_ids' => [0], 'col_ids' => [0, 1, 2]],
            ['bbox' => [5.0, 40.0, 90.0, 108.0], 'text' => 'Media group', 'row_ids' => [1, 2], 'col_ids' => [0]],
            ['bbox' => [120.0, 42.0, 180.0, 56.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [1]],
            ['bbox' => [210.0, 90.0, 290.0, 106.0], 'text' => 'Needs review', 'row_ids' => [2], 'col_ids' => [2]],
        ];

        $review = $recognizer->spanningGridReview($assigned, $rows, $cols);
        $gridByPosition = [];
        foreach ($review['grid_cells'] as $gridCell) {
            $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
        }

        $t->same([0.0, 0.0, 300.0, 32.0], $review['render_cells'][0]['grid_bbox']);
        $t->same(
            [
                ['row_id' => 0, 'col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 32.0]],
                ['row_id' => 0, 'col_id' => 1, 'bbox' => [105.0, 0.0, 195.0, 32.0]],
                ['row_id' => 0, 'col_id' => 2, 'bbox' => [205.0, 0.0, 300.0, 32.0]],
            ],
            $review['render_cells'][0]['grid_cell_bboxes']
        );
        $t->same([0.0, 35.0, 100.0, 110.0], $review['render_cells'][1]['grid_bbox']);
        $t->same(
            [
                ['row_id' => 1, 'col_id' => 0, 'bbox' => [0.0, 35.0, 100.0, 60.0]],
                ['row_id' => 2, 'col_id' => 0, 'bbox' => [0.0, 85.0, 100.0, 110.0]],
            ],
            $review['render_cells'][1]['grid_cell_bboxes']
        );
        $t->same([105.0, 0.0, 195.0, 32.0], $gridByPosition['0:1']['grid_bbox']);
        $t->same('covered', $gridByPosition['0:1']['state']);
        $t->same([0.0, 85.0, 100.0, 110.0], $gridByPosition['2:0']['grid_bbox']);
        $t->same('covered', $gridByPosition['2:0']['state']);
        $t->same([205.0, 85.0, 300.0, 110.0], $gridByPosition['2:2']['grid_bbox']);
        $t->same('Needs review', $gridByPosition['2:2']['text']);
    },
    'marks OCR merged corner headers with both row and column axes for review' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $rows = [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 28.0]],
            ['row_id' => 1, 'bbox' => [0.0, 32.0, 300.0, 60.0]],
            ['row_id' => 2, 'bbox' => [0.0, 70.0, 300.0, 100.0]],
            ['row_id' => 3, 'bbox' => [0.0, 110.0, 300.0, 140.0]],
        ];
        $cols = [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 90.0, 140.0]],
            ['col_id' => 1, 'bbox' => [100.0, 0.0, 190.0, 140.0]],
            ['col_id' => 2, 'bbox' => [200.0, 0.0, 300.0, 140.0]],
        ];
        $assigned = [
            ['bbox' => [5.0, 5.0, 185.0, 56.0], 'text' => 'Inventory', 'row_ids' => [0, 1], 'col_ids' => [0, 1]],
            ['bbox' => [110.0, 8.0, 180.0, 20.0], 'text' => 'axis', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [205.0, 5.0, 295.0, 24.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [2]],
            ['bbox' => [5.0, 74.0, 85.0, 136.0], 'text' => 'Media group', 'row_ids' => [2, 3], 'col_ids' => [0]],
            ['bbox' => [110.0, 74.0, 180.0, 94.0], 'text' => 'Images', 'row_ids' => [2], 'col_ids' => [1]],
            ['bbox' => [205.0, 74.0, 295.0, 94.0], 'text' => '12', 'row_ids' => [2], 'col_ids' => [2]],
            ['bbox' => [110.0, 114.0, 180.0, 134.0], 'text' => 'State', 'row_ids' => [3], 'col_ids' => [1]],
            ['bbox' => [205.0, 114.0, 295.0, 134.0], 'text' => 'Needs review', 'row_ids' => [3], 'col_ids' => [2]],
        ];

        $review = $recognizer->spanningGridReview($assigned, $rows, $cols);
        $gridByPosition = [];
        foreach ($review['grid_cells'] as $gridCell) {
            $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
        }

        $t->same([0, 1, 2, 3], $review['rows']);
        $t->same([0, 1, 2], $review['cols']);
        $t->same('Inventory axis', $review['render_cells'][0]['text']);
        $t->same('th', $review['render_cells'][0]['tag']);
        $t->same('colgroup', $review['render_cells'][0]['scope']);
        $t->same('column_header', $review['render_cells'][0]['header_role']);
        $t->same('both', $review['render_cells'][0]['header_axis']);
        $t->same(['column', 'row'], $review['render_cells'][0]['header_axes']);
        $t->same([2, 2], [$review['render_cells'][0]['rowspan'], $review['render_cells'][0]['colspan']]);
        $t->same(2, $review['render_cells'][0]['source_cell_count']);
        $t->same('axis', $review['render_cells'][0]['continuation_cells'][0]['text']);
        $t->same([0.0, 0.0, 190.0, 60.0], $review['render_cells'][0]['grid_bbox']);
        $t->same('Status', $review['render_cells'][1]['text']);
        $t->same('column', $review['render_cells'][1]['header_axis']);
        $t->same(['column'], $review['render_cells'][1]['header_axes']);
        $t->same('Media group', $review['render_cells'][2]['text']);
        $t->same('rowgroup', $review['render_cells'][2]['scope']);
        $t->same('row', $review['render_cells'][2]['header_axis']);
        $t->same(['row'], $review['render_cells'][2]['header_axes']);
        $t->same('anchor', $gridByPosition['0:0']['state']);
        $t->same('both', $gridByPosition['0:0']['header_axis']);
        $t->same(['column', 'row'], $gridByPosition['0:0']['header_axes']);
        $t->same('covered', $gridByPosition['0:1']['state']);
        $t->same(['row_id' => 0, 'col_id' => 0, 'render_cell_index' => 0], $gridByPosition['1:1']['covered_by']);
        $t->same('row', $gridByPosition['2:0']['header_axis']);
        $t->same(null, $gridByPosition['2:1']['header_axis']);
        $t->same('Needs review', $gridByPosition['3:2']['text']);
    },
    'applies supplied OCR text before row column assignment and markdown formatting' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $recognized = $recognizer->recognizeTables(
            [[
                ['bbox' => [10.0, 5.0, 90.0, 25.0], 'text' => ''],
                ['bbox' => [130.0, 5.0, 230.0, 25.0], 'text' => ''],
            ]],
            [true],
            [[
                'rows' => [['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]]],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 40.0]],
                    ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 40.0]],
                ],
            ]],
            [[['text' => 'Block'], ['text' => 'Status']]]
        );
        $formatted = $recognizer->formatRecognizedTables($recognized, [['width' => 1200, 'height' => 1600]]);

        $t->same('Block', $recognized[0]['cells'][0]['text']);
        $t->same("| Block | Status |\n|-------|--------|", $formatted['markdown_tables'][0]);
    },
    'unwraps upstream OCR prediction text_lines before table recognition' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $recognized = $recognizer->recognizeTables(
            [[
                ['bbox' => [10.0, 5.0, 90.0, 25.0], 'text' => ''],
                ['bbox' => [130.0, 5.0, 230.0, 25.0], 'text' => ''],
                ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => ''],
                ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => ''],
            ]],
            [true],
            [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
                ],
            ]],
            [[
                'text_lines' => [
                    ['text' => 'Metric'],
                    ['text' => 'State'],
                    ['text' => 'OCR table'],
                    ['text' => 'Recovered'],
                ],
            ]]
        );
        $formatted = $recognizer->formatRecognizedTables($recognized, [['width' => 240, 'height' => 80]]);

        $t->same(['Metric', 'State', 'OCR table', 'Recovered'], array_column($recognized[0]['cells'], 'text'));
        $t->contains('| OCR table | Recovered |', $formatted['markdown_tables'][0]);
    },
    'groups bboxed OCR fragments into detector cells before header grid review' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $recognized = $recognizer->recognizeTables(
            [[
                ['bbox' => [5.0, 5.0, 353.0, 26.0], 'text' => ''],
                ['bbox' => [5.0, 36.0, 106.0, 108.0], 'text' => ''],
                ['bbox' => [128.0, 39.0, 232.0, 56.0], 'text' => ''],
                ['bbox' => [258.0, 39.0, 348.0, 56.0], 'text' => ''],
                ['bbox' => [128.0, 89.0, 232.0, 106.0], 'text' => ''],
                ['bbox' => [258.0, 89.0, 348.0, 106.0], 'text' => ''],
            ]],
            [true],
            [[
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
            [[
                'lines' => [
                    ['text' => 'Inventory', 'bbox' => [8.0, 6.0, 148.0, 14.0]],
                    ['text' => 'OCR summary', 'bbox' => [8.0, 16.0, 196.0, 24.0]],
                    ['text' => 'Media group', 'bbox' => [8.0, 42.0, 102.0, 55.0]],
                    ['text' => 'Image count', 'bbox' => [132.0, 42.0, 228.0, 55.0]],
                    ['text' => '12', 'bbox' => [262.0, 42.0, 284.0, 55.0]],
                    ['text' => 'Review state', 'bbox' => [132.0, 92.0, 228.0, 105.0]],
                    ['text' => 'Needs review', 'bbox' => [262.0, 92.0, 344.0, 105.0]],
                ],
            ]]
        );
        $formatted = $recognizer->formatRecognizedTables($recognized, [['width' => 612, 'height' => 792]]);
        $assigned = $formatted['assigned_cells'][0];
        $review = $recognizer->spanningGridReview($assigned, $recognized[0]['rows'], $recognized[0]['cols']);
        $gridByPosition = [];
        foreach ($review['grid_cells'] as $gridCell) {
            $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
        }

        $t->same(['Inventory OCR summary', 'Media group', 'Image count', '12', 'Review state', 'Needs review'], array_column($recognized[0]['cells'], 'text'));
        $t->same('Inventory OCR summary', $assigned[0]['text']);
        $t->same([0, 1, 2], $assigned[0]['col_ids']);
        $t->same('th', $review['render_cells'][0]['tag']);
        $t->same('colgroup', $review['render_cells'][0]['scope']);
        $t->same('column_header', $review['render_cells'][0]['header_role']);
        $t->same('Inventory OCR summary', $review['render_cells'][0]['text']);
        $t->same('Media group', $review['render_cells'][1]['text']);
        $t->same('rowgroup', $review['render_cells'][1]['scope']);
        $t->same('covered', $gridByPosition['0:1']['state']);
        $t->same('Needs review', $gridByPosition['2:2']['text']);
    },
    'builds merged OCR header reference grid for WordPress table headers' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $rows = [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 28.0]],
            ['row_id' => 1, 'bbox' => [0.0, 32.0, 300.0, 60.0]],
            ['row_id' => 2, 'bbox' => [0.0, 70.0, 300.0, 100.0]],
            ['row_id' => 3, 'bbox' => [0.0, 110.0, 300.0, 140.0]],
        ];
        $cols = [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 90.0, 140.0]],
            ['col_id' => 1, 'bbox' => [100.0, 0.0, 190.0, 140.0]],
            ['col_id' => 2, 'bbox' => [200.0, 0.0, 300.0, 140.0]],
        ];
        $assigned = [
            ['bbox' => [5.0, 5.0, 185.0, 56.0], 'text' => 'Inventory', 'row_ids' => [0, 1], 'col_ids' => [0, 1]],
            ['bbox' => [110.0, 8.0, 180.0, 20.0], 'text' => 'axis', 'row_ids' => [0], 'col_ids' => [1]],
            ['bbox' => [205.0, 5.0, 295.0, 24.0], 'text' => 'Status', 'row_ids' => [0], 'col_ids' => [2]],
            ['bbox' => [5.0, 74.0, 85.0, 136.0], 'text' => 'Media group', 'row_ids' => [2, 3], 'col_ids' => [0]],
            ['bbox' => [110.0, 74.0, 180.0, 94.0], 'text' => 'Images', 'row_ids' => [2], 'col_ids' => [1]],
            ['bbox' => [205.0, 74.0, 295.0, 94.0], 'text' => '12', 'row_ids' => [2], 'col_ids' => [2]],
            ['bbox' => [110.0, 114.0, 180.0, 134.0], 'text' => 'State', 'row_ids' => [3], 'col_ids' => [1]],
            ['bbox' => [205.0, 114.0, 295.0, 134.0], 'text' => 'Needs review', 'row_ids' => [3], 'col_ids' => [2]],
        ];

        $review = $recognizer->spanningGridReview($assigned, $rows, $cols);
        $gridByPosition = [];
        foreach ($review['grid_cells'] as $gridCell) {
            $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
        }

        $t->same(['h-r0-c0', 'h-r0-c2', 'h-r2-c0'], array_column($review['header_cells'], 'header_id'));
        $t->same('h-r0-c0', $review['render_cells'][0]['header_id']);
        $t->same('h-r0-c2', $review['render_cells'][1]['header_id']);
        $t->same('h-r2-c0', $review['render_cells'][2]['header_id']);
        $t->same(['Images', '12', 'State', 'Needs review'], array_column($review['data_cells'], 'text'));
        $t->same(['h-r0-c0', 'h-r2-c0'], $gridByPosition['2:1']['headers']);
        $t->same(['h-r0-c0'], $gridByPosition['2:1']['column_header_ids']);
        $t->same(['h-r2-c0'], $gridByPosition['2:1']['row_header_ids']);
        $t->same(['Inventory axis', 'Media group'], $gridByPosition['2:1']['header_texts']);
        $t->same('Inventory axis / Media group', $gridByPosition['2:1']['header_text']);
        $t->same(['h-r0-c2', 'h-r2-c0'], $gridByPosition['2:2']['headers']);
        $t->same(['Status', 'Media group'], $gridByPosition['2:2']['header_texts']);
        $t->same(['h-r0-c0', 'h-r2-c0'], $gridByPosition['3:1']['headers']);
        $t->same(['h-r0-c2', 'h-r2-c0'], $gridByPosition['3:2']['headers']);
        $t->same(
            [
                'render_cell_index' => 3,
                'text' => 'Images',
                'row_ids' => [2],
                'col_ids' => [1],
                'anchor' => ['row_id' => 2, 'col_id' => 1],
                'headers' => ['h-r0-c0', 'h-r2-c0'],
                'column_header_ids' => ['h-r0-c0'],
                'row_header_ids' => ['h-r2-c0'],
                'header_texts' => ['Inventory axis', 'Media group'],
                'header_text' => 'Inventory axis / Media group',
            ],
            $review['data_cells'][0]
        );
    },
    'preserves detector grid order when OCR bboxes straddle cell borders' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $recognized = $recognizer->recognizeTables(
            [[
                ['bbox' => [0.0, 0.0, 90.0, 24.0], 'text' => ''],
                ['bbox' => [100.0, 0.0, 190.0, 24.0], 'text' => ''],
                ['bbox' => [0.0, 40.0, 90.0, 64.0], 'text' => ''],
                ['bbox' => [100.0, 40.0, 190.0, 64.0], 'text' => ''],
            ]],
            [true],
            [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 38.0, 200.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 96.0, 72.0]],
                    ['col_id' => 1, 'bbox' => [98.0, 0.0, 200.0, 72.0]],
                ],
            ]],
            [[
                'lines' => [
                    ['text' => 'Feature', 'bbox' => [0.0, 0.0, 190.0, 24.0]],
                    ['text' => 'Status', 'bbox' => [0.0, 0.0, 190.0, 24.0]],
                    ['text' => 'Images', 'bbox' => [0.0, 40.0, 190.0, 64.0]],
                    ['text' => 'Ready', 'bbox' => [0.0, 40.0, 190.0, 64.0]],
                ],
            ]]
        );
        $formatted = $recognizer->formatRecognizedTables($recognized, [['width' => 200, 'height' => 72]]);
        $conflicts = $recognized[0]['ocr_grid_border_conflicts'] ?? [];

        $t->same('source_order_grid_border', $recognized[0]['ocr_text_assignment'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], array_column($recognized[0]['cells'], 'text'));
        $t->same(4, count($conflicts));
        $t->same([0, 1], $conflicts[0]['candidate_cell_indexes']);
        $t->same(0, $conflicts[0]['assigned_cell_index']);
        $t->same(3, $conflicts[3]['assigned_cell_index']);
        $t->same(true, $conflicts[0]['spans_grid_border']);
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0]);
    },
    'assigns out-of-order OCR polygon text by upstream TextLine geometry' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $recognized = $recognizer->recognizeTables(
            [[
                ['bbox' => [0.0, 0.0, 90.0, 24.0], 'text' => ''],
                ['bbox' => [100.0, 0.0, 190.0, 24.0], 'text' => ''],
                ['bbox' => [0.0, 40.0, 90.0, 64.0], 'text' => ''],
                ['bbox' => [100.0, 40.0, 190.0, 64.0], 'text' => ''],
            ]],
            [true],
            [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 38.0, 200.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 96.0, 72.0]],
                    ['col_id' => 1, 'bbox' => [98.0, 0.0, 200.0, 72.0]],
                ],
            ]],
            [[
                'text_lines' => [
                    ['text' => 'Status', 'polygon' => [[102.0, 4.0], [188.0, 4.0], [188.0, 20.0], [102.0, 20.0]]],
                    ['text' => 'Feature', 'polygon' => [[2.0, 4.0], [88.0, 4.0], [88.0, 20.0], [2.0, 20.0]]],
                    ['text' => 'Ready', 'polygon' => [[102.0, 44.0], [188.0, 44.0], [188.0, 60.0], [102.0, 60.0]]],
                    ['text' => 'Images', 'polygon' => [[2.0, 44.0], [88.0, 44.0], [88.0, 60.0], [2.0, 60.0]]],
                ],
            ]]
        );
        $formatted = $recognizer->formatRecognizedTables($recognized, [['width' => 200, 'height' => 72]]);

        $t->same(['Feature', 'Status', 'Images', 'Ready'], array_column($recognized[0]['cells'], 'text'));
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0]);
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0]);
        $t->same([], $recognized[0]['ocr_grid_border_conflicts'] ?? []);
    },
    'labels OCR grid-border conflicts with assigned table row and column ids' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $recognized = $recognizer->recognizeTables(
            [[
                ['bbox' => [0.0, 0.0, 90.0, 24.0], 'text' => ''],
                ['bbox' => [100.0, 0.0, 190.0, 24.0], 'text' => ''],
                ['bbox' => [0.0, 40.0, 90.0, 64.0], 'text' => ''],
                ['bbox' => [100.0, 40.0, 190.0, 64.0], 'text' => ''],
            ]],
            [true],
            [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 38.0, 200.0, 70.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 96.0, 72.0]],
                    ['col_id' => 1, 'bbox' => [98.0, 0.0, 200.0, 72.0]],
                ],
            ]],
            [[
                'lines' => [
                    ['text' => 'Table-wide heading', 'bbox' => [0.0, 0.0, 190.0, 64.0]],
                    ['text' => 'Column border', 'bbox' => [0.0, 0.0, 190.0, 24.0]],
                    ['text' => 'Row border', 'bbox' => [0.0, 0.0, 90.0, 64.0]],
                    ['text' => 'Cell value', 'bbox' => [100.0, 40.0, 190.0, 64.0]],
                ],
            ]]
        );
        $formatted = $recognizer->formatRecognizedTables($recognized, [['width' => 200, 'height' => 72]]);
        $reviewConflicts = $recognizer->gridBorderConflictReview(
            $recognized[0]['ocr_grid_border_conflicts'] ?? [],
            $formatted['assigned_cells'][0]
        );

        $t->same(['Table-wide heading', 'Column border', 'Row border', 'Cell value'], array_column($recognized[0]['cells'], 'text'));
        $t->same(3, count($reviewConflicts));
        $t->same('both', $reviewConflicts[0]['grid_border_axis']);
        $t->same(['column', 'row'], $reviewConflicts[0]['grid_border_axes']);
        $t->same([0, 1], $reviewConflicts[0]['candidate_row_ids']);
        $t->same([0, 1], $reviewConflicts[0]['candidate_col_ids']);
        $t->same(
            [
                ['cell_index' => 0, 'row_id' => 0, 'col_id' => 0],
                ['cell_index' => 1, 'row_id' => 0, 'col_id' => 1],
                ['cell_index' => 2, 'row_id' => 1, 'col_id' => 0],
                ['cell_index' => 3, 'row_id' => 1, 'col_id' => 1],
            ],
            $reviewConflicts[0]['candidate_grid_anchors']
        );
        $t->same(['cell_index' => 0, 'row_id' => 0, 'col_id' => 0, 'row_ids' => [0], 'col_ids' => [0], 'text' => 'Table-wide heading'], $reviewConflicts[0]['assigned_grid_cell']);
        $t->same('column', $reviewConflicts[1]['grid_border_axis']);
        $t->same([0], $reviewConflicts[1]['candidate_row_ids']);
        $t->same([0, 1], $reviewConflicts[1]['candidate_col_ids']);
        $t->same('row', $reviewConflicts[2]['grid_border_axis']);
        $t->same([0, 1], $reviewConflicts[2]['candidate_row_ids']);
        $t->same([0], $reviewConflicts[2]['candidate_col_ids']);
    },
    'adds row column band geometry to OCR grid-border conflict review rows' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $rows = [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 32.0]],
            ['row_id' => 1, 'bbox' => [0.0, 35.0, 300.0, 60.0]],
        ];
        $cols = [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 72.0]],
            ['col_id' => 1, 'bbox' => [105.0, 0.0, 195.0, 72.0]],
            ['col_id' => 2, 'bbox' => [205.0, 0.0, 300.0, 72.0]],
        ];
        $assigned = [
            ['bbox' => [5.0, 5.0, 295.0, 18.0], 'text' => 'Inventory', 'row_ids' => [0], 'col_ids' => [0, 1, 2]],
            ['bbox' => [120.0, 42.0, 180.0, 56.0], 'text' => 'Images', 'row_ids' => [1], 'col_ids' => [1]],
        ];
        $conflicts = [[
            'ocr_index' => 0,
            'text' => 'OCR span',
            'bbox' => [0.0, 0.0, 300.0, 56.0],
            'candidate_cell_indexes' => [0, 1],
            'assigned_cell_index' => 0,
            'spans_grid_border' => true,
        ]];

        $review = $recognizer->gridBorderConflictReview($conflicts, $assigned, $rows, $cols);

        $t->same('both', $review[0]['grid_border_axis']);
        $t->same([0, 1], $review[0]['candidate_row_ids']);
        $t->same([0, 1, 2], $review[0]['candidate_col_ids']);
        $t->same([0.0, 0.0, 300.0, 32.0], $review[0]['candidate_grid_cells'][0]['grid_bbox']);
        $t->same(
            [
                ['row_id' => 0, 'col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 32.0]],
                ['row_id' => 0, 'col_id' => 1, 'bbox' => [105.0, 0.0, 195.0, 32.0]],
                ['row_id' => 0, 'col_id' => 2, 'bbox' => [205.0, 0.0, 300.0, 32.0]],
            ],
            $review[0]['candidate_grid_cells'][0]['grid_cell_bboxes']
        );
        $t->same([105.0, 35.0, 195.0, 60.0], $review[0]['candidate_grid_cells'][1]['grid_bbox']);
        $t->same([0.0, 0.0, 300.0, 32.0], $review[0]['assigned_grid_cell']['grid_bbox']);
        $t->same(3, count($review[0]['assigned_grid_cell']['grid_cell_bboxes']));
    },
    'falls back to heuristic row layout when model rows and columns leave most cells unassigned' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $assigned = $recognizer->assignRowsColumns(
            [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 300.0, 240.0, 330.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [300.0, 0.0, 340.0, 120.0]],
                ],
                'cells' => [
                    ['bbox' => [10.0, 10.0, 80.0, 30.0], 'text' => 'Name'],
                    ['bbox' => [120.0, 10.0, 220.0, 30.0], 'text' => 'Role'],
                    ['bbox' => [10.0, 50.0, 80.0, 70.0], 'text' => 'Ada'],
                    ['bbox' => [120.0, 50.0, 220.0, 70.0], 'text' => 'Editor'],
                ],
            ],
            ['width' => 240, 'height' => 120]
        );

        $t->same([[0], [0], [1], [1]], array_column($assigned, 'row_ids'));
        $t->same([[0], [1], [0], [1]], array_column($assigned, 'col_ids'));
    },
    'clusters heuristic column separators with locked tabled DBSCAN semantics' => static function (TestRunner $t) use ($heuristicRows): void {
        $recognizer = new TableRecognizer();
        $separators = array_map(
            static fn (float $separator): float => round($separator, 3),
            $recognizer->heuristicColumnSeparators($heuristicRows(), ['width' => 1000, 'height' => 800])
        );

        $t->same([0.0, 0.011, 0.211, 0.411, 1.0], $separators);
    },
    'uses clustered heuristic separators when model row and column boxes are unavailable' => static function (TestRunner $t) use ($heuristicRows): void {
        $recognizer = new TableRecognizer();
        $cells = [];
        foreach ($heuristicRows() as $row) {
            foreach ($row as $cell) {
                $cells[] = $cell;
            }
        }

        $assigned = $recognizer->assignRowsColumns(
            ['cells' => $cells],
            ['width' => 1000, 'height' => 800]
        );

        $t->same([[0], [0], [0], [1], [1], [1]], array_column(array_slice($assigned, 0, 6), 'row_ids'));
        $t->same([[0], [1], [2], [0], [1], [2]], array_column(array_slice($assigned, 0, 6), 'col_ids'));
        $t->same('Owner 7', $assigned[23]['text']);
    },
    'renders a WordPress table from supplied recognition output before model-backed inference exists' => static function (TestRunner $t) use ($tableResult, $tablePage, $wordpressTable): void {
        $recognizer = new TableRecognizer();
        $formattedRecognition = $recognizer->formatRecognizedTables([$tableResult()], [['width' => 1200, 'height' => 1600]]);
        $formattedPage = (new TableFormatter())->formatTables([$tablePage()], $formattedRecognition['markdown_tables']);
        $merged = (new MarkdownPostProcessor())->mergeBlocks($formattedPage['pages']);
        $tableBlocks = array_values(array_filter(
            $merged,
            static fn (array $block): bool => ($block['block_type'] ?? '') === 'Table'
        ));
        $html = $wordpressTable($tableBlocks[0]['text']);

        $t->same(1, count($tableBlocks));
        $t->contains('<tr><td>Intro</td><td>Published</td></tr>', $html);
        $t->contains('<tr><td>Media 24</td><td>Needs Review</td></tr>', $html);
    },
    'rejects missing supplied detector cells for OCR-needed tables' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => (new TableRecognizer())->getCells(
            [[20.0, 20.0, 220.0, 120.0]],
            [['width' => 1200, 'height' => 1600]],
            [null],
            []
        ));
    },
];
