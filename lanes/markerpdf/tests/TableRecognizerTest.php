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
    'adds tabled-style row and column spans when geometry covers open bands' => static function (TestRunner $t): void {
        $recognizer = new TableRecognizer();
        $assigned = $recognizer->assignRowsColumns(
            [
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
            ],
            ['width' => 300, 'height' => 120]
        );
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
