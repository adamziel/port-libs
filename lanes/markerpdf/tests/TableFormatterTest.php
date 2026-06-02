<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\TableFormatter;

$tablePage = static function (): array {
    return [
        'pnum' => 5,
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'layout' => [
            'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
            'bboxes' => [
                ['label' => 'Text', 'bbox' => [120.0, 100.0, 560.0, 160.0]],
                ['label' => 'Table', 'bbox' => [120.0, 220.0, 560.0, 420.0]],
            ],
        ],
        'blocks' => [
            [
                'type' => 'Text',
                'bbox' => [60.0, 60.0, 280.0, 92.0],
                'lines' => [
                    ['text' => 'Imported table follows.', 'bbox' => [60.0, 64.0, 260.0, 80.0]],
                ],
            ],
            [
                'type' => 'Table',
                'bbox' => [60.0, 110.0, 280.0, 210.0],
                'lines' => [
                    ['text' => 'Old PDF table text', 'bbox' => [62.0, 116.0, 270.0, 136.0]],
                ],
            ],
            [
                'type' => 'Caption',
                'bbox' => [60.0, 226.0, 280.0, 250.0],
                'lines' => [
                    ['text' => 'Table 1: Import status.', 'bbox' => [60.0, 230.0, 250.0, 244.0]],
                ],
            ],
        ],
    ];
};

$tableBoxPages = static function (): array {
    return [
        [
            'pnum' => 10,
            'bbox' => [0.0, 0.0, 600.0, 800.0],
            'layout' => [
                'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
                'bboxes' => [
                    ['label' => 'Table', 'bbox' => [100.0, 100.0, 210.0, 200.0]],
                    ['label' => 'Table', 'bbox' => [209.0, 100.0, 320.0, 200.0]],
                    ['label' => 'Table', 'bbox' => [20.0, 20.0, 29.0, 80.0]],
                    ['label' => 'Text', 'bbox' => [60.0, 20.0, 260.0, 70.0]],
                ],
            ],
            'blocks' => [],
        ],
        [
            'pnum' => 11,
            'bbox' => [0.0, 0.0, 600.0, 800.0],
            'layout' => [
                'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
                'bboxes' => [
                    ['label' => 'Text', 'bbox' => [60.0, 80.0, 260.0, 130.0]],
                ],
            ],
            'blocks' => [],
        ],
        [
            'pnum' => 20,
            'bbox' => [0.0, 0.0, 600.0, 800.0],
            'ocr_method' => 'surya',
            'layout' => [
                'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
                'bboxes' => [
                    ['label' => 'Table', 'bbox' => [100.0, 300.0, 260.0, 420.0]],
                    ['label' => 'Table', 'bbox' => [400.0, 300.0, 520.0, 420.0]],
                ],
            ],
            'blocks' => [],
        ],
    ];
};

$markdown = "| Block | Status |\n| --- | --- |\n| Intro | Published |\n| Media | Draft |";

$wordpressTable = static function (string $tableMarkdown): string {
    $rows = array_values(array_filter(
        preg_split('/\R/', trim($tableMarkdown)) ?: [],
        static fn (string $row): bool => trim($row, " \t|") !== '' && !preg_match('/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/', $row)
    ));
    $htmlRows = [];
    foreach ($rows as $row) {
        $cells = array_map(
            static fn (string $cell): string => htmlspecialchars(trim($cell), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            explode('|', trim($row, " \t|"))
        );
        $htmlRows[] = '<tr><td>' . implode('</td><td>', $cells) . '</td></tr>';
    }

    return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><tbody>"
        . implode('', $htmlRows)
        . "</tbody></table></figure>\n<!-- /wp:table -->\n";
};

return [
    'finds upstream table layout regions with page bbox rescaling' => static function (TestRunner $t) use ($tablePage): void {
        $regions = (new TableFormatter())->findTableBlocks($tablePage());

        $t->same(
            [
                [
                    'bbox' => [120.0, 220.0, 560.0, 420.0],
                    'page_bbox' => [60.0, 110.0, 280.0, 210.0],
                ],
            ],
            $regions
        );
    },
    'plans upstream get_table_boxes crop metadata with merged tables and OCR null lines' => static function (TestRunner $t) use ($tableBoxPages): void {
        $textLines = [
            ['page' => 10, 'lines' => [['bbox' => [200.0, 210.0, 630.0, 230.0], 'text' => 'Block Status']]],
        ];
        $sizes = [
            ['width' => 2400, 'height' => 3200],
            ['width' => 2400, 'height' => 3200],
            ['width' => 2400, 'height' => 3200],
        ];

        $planned = (new TableFormatter())->getTableBoxes($tableBoxPages(), $textLines, $sizes);

        $t->same([1, 0, 2], $planned['table_counts']);
        $t->same([10, 20], $planned['doc_indexes']);
        $t->same([0, 2], $planned['table_page_indexes']);
        $t->same(
            [
                [200.0, 200.0, 640.0, 400.0],
                [200.0, 600.0, 520.0, 840.0],
                [800.0, 600.0, 1040.0, 840.0],
            ],
            $planned['table_bboxes']
        );
        $t->same(
            [
                $textLines[0],
                null,
                null,
            ],
            $planned['text_lines']
        );
        $t->same(
            [
                ['width' => 2400, 'height' => 3200],
                null,
                ['width' => 2400, 'height' => 3200],
            ],
            $planned['page_image_sizes']
        );
        $t->same([100.0, 100.0, 320.0, 200.0], $planned['table_images'][0]['source_bbox']);
        $t->same(440.0, $planned['table_images'][0]['crop_width']);
        $t->same(200.0, $planned['table_images'][0]['crop_height']);
    },
    'rejects missing supplied text lines for non-OCR table pages' => static function (TestRunner $t) use ($tableBoxPages): void {
        $pages = [$tableBoxPages()[0]];

        $t->throws(InvalidArgumentException::class, static fn () => (new TableFormatter())->getTableBoxes(
            $pages,
            [],
            [['width' => 2400, 'height' => 3200]]
        ));
    },
    'replaces intersecting upstream table blocks with supplied markdown tables' => static function (TestRunner $t) use ($tablePage, $markdown): void {
        $formatted = (new TableFormatter())->formatTables([$tablePage()], [$markdown]);
        $blocks = $formatted['pages'][0]['blocks'];

        $t->same(1, $formatted['table_count']);
        $t->same(1, $formatted['inserted_tables']);
        $t->same(['Text', 'Table', 'Caption'], array_column($blocks, 'type'));
        $t->same([120.0, 220.0, 560.0, 420.0], $blocks[1]['bbox']);
        $t->same('0_table', $blocks[1]['lines'][0]['spans'][0]['span_id']);
        $t->same('Table', $blocks[1]['lines'][0]['spans'][0]['font']);
        $t->same($markdown, $blocks[1]['lines'][0]['spans'][0]['text']);
    },
    'preserves section and caption context for span-grid table review metadata' => static function (TestRunner $t) use ($tablePage, $markdown): void {
        $page = $tablePage();
        array_unshift($page['blocks'], [
            'type' => 'Section-header',
            'heading_level' => 2,
            'bbox' => [60.0, 26.0, 280.0, 48.0],
            'lines' => [
                ['text' => 'imported tables', 'bbox' => [60.0, 30.0, 250.0, 44.0]],
            ],
        ]);

        $formatted = (new TableFormatter())->formatTables([$page], [$markdown]);
        $blocks = $formatted['pages'][0]['blocks'];
        $reviews = $formatted['table_context_reviews'];

        $t->same(['Section-header', 'Text', 'Table', 'Caption'], array_column($blocks, 'type'));
        $t->same(1, count($reviews));
        $t->same(0, $reviews[0]['table_index']);
        $t->same(true, $reviews[0]['inserted']);
        $t->same('table_span_grid', $reviews[0]['review_target']);
        $t->same([2], $reviews[0]['matched_table_block_indexes']);
        $t->same('section', $reviews[0]['section']['role']);
        $t->same('Section-header', $reviews[0]['section']['type']);
        $t->same('imported tables', $reviews[0]['section']['text']);
        $t->same(2, $reviews[0]['section']['heading_level']);
        $t->same('caption', $reviews[0]['caption']['role']);
        $t->same('Caption', $reviews[0]['caption']['type']);
        $t->same('Table 1: Import status.', $reviews[0]['caption']['text']);
        $t->same('after', $reviews[0]['caption']['position']);
        $t->same(16.0, $reviews[0]['caption']['vertical_gap']);
        $t->same(true, $reviews[0]['has_section']);
        $t->same(true, $reviews[0]['has_caption']);
    },
    'formats merged adjacent table layout boxes as one recognized table' => static function (TestRunner $t) use ($tableBoxPages, $markdown): void {
        $page = $tableBoxPages()[0];
        $page['blocks'] = [
            [
                'type' => 'Text',
                'bbox' => [30.0, 20.0, 260.0, 40.0],
                'lines' => [
                    ['text' => 'Adjacent fragments follow.', 'bbox' => [30.0, 24.0, 250.0, 36.0]],
                ],
            ],
            [
                'type' => 'Table',
                'bbox' => [50.0, 50.0, 105.0, 100.0],
                'lines' => [
                    ['text' => 'Left PDF fragment', 'bbox' => [52.0, 55.0, 104.0, 70.0]],
                ],
            ],
            [
                'type' => 'Table',
                'bbox' => [104.5, 50.0, 160.0, 100.0],
                'lines' => [
                    ['text' => 'Right PDF fragment', 'bbox' => [106.0, 55.0, 158.0, 70.0]],
                ],
            ],
            [
                'type' => 'Caption',
                'bbox' => [50.0, 120.0, 260.0, 140.0],
                'lines' => [
                    ['text' => 'Table 2: Split source table.', 'bbox' => [50.0, 124.0, 240.0, 136.0]],
                ],
            ],
        ];

        $formatted = (new TableFormatter())->formatTables([$page], [$markdown]);
        $blocks = $formatted['pages'][0]['blocks'];

        $t->same(1, $formatted['table_count']);
        $t->same(1, $formatted['inserted_tables']);
        $t->same(['Text', 'Table', 'Caption'], array_column($blocks, 'type'));
        $t->same([100.0, 100.0, 320.0, 200.0], $blocks[1]['bbox']);
        $t->same('0_table', $blocks[1]['lines'][0]['spans'][0]['span_id']);
        $t->same($markdown, $blocks[1]['lines'][0]['spans'][0]['text']);
    },
    'skips recognized tables without matching table layout blocks like upstream formatter' => static function (TestRunner $t) use ($tablePage): void {
        $page = $tablePage();
        $page['blocks'][1]['type'] = 'Text';
        $formatted = (new TableFormatter())->formatTables([$page], []);

        $t->same(1, $formatted['table_count']);
        $t->same(0, $formatted['inserted_tables']);
        $t->same(['Text', 'Text', 'Caption'], array_column($formatted['pages'][0]['blocks'], 'type'));
        $t->same('Old PDF table text', $formatted['pages'][0]['blocks'][1]['lines'][0]['text']);
    },
    'rejects missing supplied markdown for intersecting recognized tables' => static function (TestRunner $t) use ($tablePage): void {
        $t->throws(InvalidArgumentException::class, static fn () => (new TableFormatter())->formatTables([$tablePage()], []));
    },
    'renders a WordPress table block scenario from formatted marker table markdown' => static function (TestRunner $t) use ($tablePage, $markdown, $wordpressTable): void {
        $formatted = (new TableFormatter())->formatTables([$tablePage()], [$markdown]);
        $merged = (new MarkdownPostProcessor())->mergeBlocks($formatted['pages']);
        $tableBlocks = array_values(array_filter(
            $merged,
            static fn (array $block): bool => ($block['block_type'] ?? '') === 'Table'
        ));
        $html = $wordpressTable($tableBlocks[0]['text']);

        $t->same(1, count($tableBlocks));
        $t->same(
            "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><tbody>"
            . '<tr><td>Block</td><td>Status</td></tr>'
            . '<tr><td>Intro</td><td>Published</td></tr>'
            . '<tr><td>Media</td><td>Draft</td></tr>'
            . "</tbody></table></figure>\n<!-- /wp:table -->\n",
            $html
        );
    },
];
