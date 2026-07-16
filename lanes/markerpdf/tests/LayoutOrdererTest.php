<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\LayoutOrderer;
use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;

return [
    'uses upstream ordering batch size defaults overrides and multiplier truncation' => static function (TestRunner $t): void {
        $t->same(6, (new LayoutOrderer())->batchSize());
        $t->same(10, (new LayoutOrderer(new MarkerSettings(['ORDER_BATCH_SIZE' => '10'])))->batchSize());
        $t->same(15, (new LayoutOrderer(new MarkerSettings(['ORDER_BATCH_SIZE' => 10])))->batchSize(1.5));
        $t->same(4, (new LayoutOrderer())->batchSize(0.75));
        $t->same(6, (new LayoutOrderer(new MarkerSettings(['TORCH_DEVICE' => 'cuda'])))->batchSize());
    },
    'attaches supplied Surya ordering predictions with upstream bbox caps and zip semantics' => static function (TestRunner $t): void {
        $pages = [
            [
                'layout' => [
                    'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
                    'bboxes' => [
                        ['label' => 'Title', 'bbox' => [120.0, 80.0, 1000.0, 140.0]],
                        ['label' => 'Text', 'bbox' => [120.0, 200.0, 1000.0, 260.0]],
                        ['label' => 'Picture', 'bbox' => [120.0, 320.0, 1000.0, 480.0]],
                    ],
                ],
                'blocks' => [],
            ],
            [
                'layout_boxes' => [
                    ['label' => 'Text', 'bbox' => [60.0, 90.0, 280.0, 210.0]],
                ],
                'blocks' => [],
            ],
            [
                'blocks' => [],
            ],
        ];
        $orderResults = [
            ['image_bbox' => [0.0, 0.0, 1200.0, 1600.0], 'bboxes' => [['position' => 1, 'bbox' => [120.0, 80.0, 1000.0, 140.0]]]],
            ['image_bbox' => [0.0, 0.0, 600.0, 800.0], 'bboxes' => [['position' => 1, 'bbox' => [60.0, 90.0, 280.0, 210.0]]]],
            ['image_bbox' => [0.0, 0.0, 600.0, 800.0], 'bboxes' => []],
            ['image_bbox' => [0.0, 0.0, 600.0, 800.0], 'bboxes' => []],
        ];

        $result = (new LayoutOrderer(new MarkerSettings(['ORDER_MAX_BBOXES' => 2])))->runWithSuppliedOrder(
            ['image-1', 'image-2', 'image-3'],
            $pages,
            $orderResults,
            2.0
        );

        $t->same([
            'image_count' => 3,
            'page_count' => 3,
            'layout_bbox_counts' => [2, 1, 0],
            'requested_bboxes' => [
                [
                    [120.0, 80.0, 1000.0, 140.0],
                    [120.0, 200.0, 1000.0, 260.0],
                ],
                [[60.0, 90.0, 280.0, 210.0]],
                [],
            ],
            'order_result_count' => 4,
            'assigned_pages' => 3,
            'batch_size' => 12,
            'order_max_bboxes' => 2,
        ], $result['plan']);
        $t->same($orderResults[0], $result['pages'][0]['order']);
        $t->same($orderResults[2], $result['pages'][2]['order']);
    },
    'leaves unpaired pages unchanged and rejects invalid supplied order predictions' => static function (TestRunner $t): void {
        $pages = [
            ['blocks' => []],
            ['blocks' => []],
        ];
        $order = ['image_bbox' => [0.0, 0.0, 600.0, 800.0], 'bboxes' => []];
        $result = (new LayoutOrderer())->runWithSuppliedOrder(['image-1', 'image-2'], $pages, [$order]);

        $t->same(1, $result['plan']['assigned_pages']);
        $t->true(isset($result['pages'][0]['order']));
        $t->true(!isset($result['pages'][1]['order']));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => (new LayoutOrderer())->runWithSuppliedOrder(['image'], [['blocks' => []]], ['not-array'])
        );
    },
    'sorts same-position block groups by upstream vertical and horizontal tolerance' => static function (TestRunner $t): void {
        $orderer = new LayoutOrderer();
        $blocks = [
            ['text' => 'right top', 'bbox' => [320.0, 100.3, 520.0, 118.0]],
            ['text' => 'lower left', 'bbox' => [72.0, 124.0, 260.0, 140.0]],
            ['text' => 'left top', 'bbox' => [72.0, 100.0, 260.0, 118.0]],
        ];

        $sorted = $orderer->sortBlockGroup($blocks);

        $t->same(['left top', 'right top', 'lower left'], array_column($sorted, 'text'));
    },
    'uses upstream ordering positions then pins page headers and footers' => static function (TestRunner $t): void {
        $orderer = new LayoutOrderer();
        $pages = [
            [
                'bbox' => [0.0, 0.0, 600.0, 800.0],
                'order' => [
                    'image_bbox' => [0.0, 0.0, 600.0, 800.0],
                    'bboxes' => [
                        ['position' => 2, 'bbox' => [320.0, 100.0, 560.0, 240.0]],
                        ['position' => 1, 'bbox' => [60.0, 100.0, 280.0, 240.0]],
                        ['position' => 9, 'bbox' => [60.0, 760.0, 560.0, 790.0]],
                        ['position' => 0, 'bbox' => [60.0, 20.0, 560.0, 44.0]],
                    ],
                ],
                'blocks' => [
                    ['type' => 'Page-footer', 'text' => 'Internal footer', 'bbox' => [70.0, 764.0, 500.0, 786.0]],
                    ['type' => 'Text', 'text' => 'Right column', 'bbox' => [330.0, 110.0, 520.0, 130.0]],
                    ['type' => 'Page-header', 'text' => 'Migration Guide', 'bbox' => [72.0, 24.0, 520.0, 40.0]],
                    ['type' => 'Text', 'text' => 'Left column', 'bbox' => [72.0, 110.0, 250.0, 130.0]],
                ],
            ],
        ];

        $sorted = $orderer->sortBlocksInReadingOrder($pages);

        $t->same(
            ['Migration Guide', 'Left column', 'Right column', 'Internal footer'],
            array_column($sorted[0]['blocks'], 'text')
        );
    },
    'rescales ordering model boxes before intersection matching' => static function (TestRunner $t): void {
        $orderer = new LayoutOrderer();
        $pages = [
            [
                'bbox' => [0.0, 0.0, 600.0, 800.0],
                'order' => [
                    'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
                    'bboxes' => [
                        ['position' => 2, 'bbox' => [640.0, 200.0, 1120.0, 480.0]],
                        ['position' => 1, 'bbox' => [120.0, 200.0, 560.0, 480.0]],
                    ],
                ],
                'blocks' => [
                    ['type' => 'Text', 'text' => 'Right half after rescale', 'bbox' => [330.0, 110.0, 520.0, 130.0]],
                    ['type' => 'Text', 'text' => 'Left half after rescale', 'bbox' => [72.0, 110.0, 250.0, 130.0]],
                ],
            ],
        ];

        $sorted = $orderer->sortBlocksInReadingOrder($pages);

        $t->same(['Left half after rescale', 'Right half after rescale'], array_column($sorted[0]['blocks'], 'text'));
        $t->same([60.0, 100.0, 280.0, 240.0], $orderer->rescaleBbox(
            [0.0, 0.0, 1200.0, 1600.0],
            [0.0, 0.0, 600.0, 800.0],
            [120.0, 200.0, 560.0, 480.0]
        ));
    },
    'maps rotated PDF page-space block bboxes before reading-order matching' => static function (TestRunner $t): void {
        $orderer = new LayoutOrderer();
        $processor = new MarkdownPostProcessor();
        $rotatedNinety = [
            [
                'bbox' => [0.0, 0.0, 200.0, 160.0],
                'rotation' => 90,
                'pdf_page_bbox' => [20.0, 40.0, 180.0, 240.0],
                'block_bbox_coordinate_space' => 'pdf_page_user_space',
                'order' => [
                    'image_bbox' => [0.0, 0.0, 200.0, 160.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [20.0, 10.0, 40.0, 130.0]],
                        ['position' => 2, 'bbox' => [110.0, 10.0, 130.0, 130.0]],
                    ],
                ],
                'blocks' => [
                    [
                        'type' => 'Text',
                        'bbox' => [30.0, 150.0, 150.0, 170.0],
                        'lines' => [
                            ['text' => 'Right display column', 'bbox' => [30.0, 150.0, 150.0, 170.0]],
                        ],
                    ],
                    [
                        'type' => 'Text',
                        'bbox' => [30.0, 60.0, 150.0, 80.0],
                        'lines' => [
                            ['text' => 'Left display column', 'bbox' => [30.0, 60.0, 150.0, 80.0]],
                        ],
                    ],
                ],
            ],
        ];
        $rotatedTwoSeventy = [
            [
                'bbox' => [0.0, 0.0, 300.0, 200.0],
                'rotation' => 270,
                'pdf_page_bbox' => [0.0, 0.0, 200.0, 300.0],
                'order' => [
                    'image_bbox' => [0.0, 0.0, 300.0, 200.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [40.0, 20.0, 60.0, 160.0]],
                        ['position' => 2, 'bbox' => [180.0, 20.0, 200.0, 160.0]],
                    ],
                ],
                'blocks' => [
                    [
                        'type' => 'Text',
                        'bbox_coordinate_space' => 'pdf_page_user_space',
                        'bbox' => [40.0, 100.0, 180.0, 120.0],
                        'lines' => [
                            ['text' => 'Right 270 display column', 'bbox' => [40.0, 100.0, 180.0, 120.0]],
                        ],
                    ],
                    [
                        'type' => 'Text',
                        'bbox_coordinate_space' => 'pdf_page_user_space',
                        'bbox' => [40.0, 240.0, 180.0, 260.0],
                        'lines' => [
                            ['text' => 'Left 270 display column', 'bbox' => [40.0, 240.0, 180.0, 260.0]],
                        ],
                    ],
                ],
            ],
        ];

        $sortedNinety = $orderer->sortBlocksInReadingOrder($rotatedNinety);
        $sortedTwoSeventy = $orderer->sortBlocksInReadingOrder($rotatedTwoSeventy);
        $mergedNinety = $processor->mergeBlocks($sortedNinety);
        $mergedTwoSeventy = $processor->mergeBlocks($sortedTwoSeventy);

        $t->same(['Left display column', 'Right display column'], array_map(
            static fn (array $block): string => $block['lines'][0]['text'],
            $sortedNinety[0]['blocks']
        ));
        $t->same('Left display column Right display column', $mergedNinety[0]['text']);
        $t->same(['Left 270 display column', 'Right 270 display column'], array_map(
            static fn (array $block): string => $block['lines'][0]['text'],
            $sortedTwoSeventy[0]['blocks']
        ));
        $t->same('Left 270 display column Right 270 display column', $mergedTwoSeventy[0]['text']);
    },
    'preserves two-column WordPress import reading order before markdown block merge' => static function (TestRunner $t): void {
        $orderer = new LayoutOrderer();
        $processor = new MarkdownPostProcessor();
        $pages = [
            [
                'bbox' => [0.0, 0.0, 600.0, 800.0],
                'order' => [
                    'image_bbox' => [0.0, 0.0, 600.0, 800.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 90.0, 280.0, 180.0]],
                        ['position' => 2, 'bbox' => [320.0, 90.0, 560.0, 180.0]],
                    ],
                ],
                'blocks' => [
                    [
                        'type' => 'Text',
                        'lines' => [
                            ['text' => 'Second column media checklist.', 'bbox' => [330.0, 100.0, 540.0, 116.0]],
                        ],
                    ],
                    [
                        'type' => 'Text',
                        'lines' => [
                            ['text' => 'First column import summary.', 'bbox' => [72.0, 100.0, 260.0, 116.0]],
                        ],
                    ],
                ],
            ],
        ];

        $sorted = $orderer->sortBlocksInReadingOrder($pages);
        $merged = $processor->mergeBlocks($sorted);

        $t->same("First column import summary.\n\nSecond column media checklist.", $merged[0]['text']);
    },
    'rotates unrotated order boxes before sorting columns with pinned page edges' => static function (TestRunner $t): void {
        $orderer = new LayoutOrderer();
        $pages = [
            [
                'bbox' => [0.0, 0.0, 800.0, 600.0],
                'rotation' => 90,
                'order' => [
                    'image_bbox' => [0.0, 0.0, 600.0, 800.0],
                    'bboxes' => [
                        ['position' => 0, 'bbox' => [20.0, 60.0, 50.0, 740.0]],
                        ['position' => 1, 'bbox' => [310.0, 500.0, 500.0, 740.0]],
                        ['position' => 2, 'bbox' => [310.0, 60.0, 500.0, 360.0]],
                        ['position' => 9, 'bbox' => [560.0, 60.0, 590.0, 740.0]],
                    ],
                ],
                'blocks' => [
                    ['type' => 'Page-footer', 'text' => 'Rotated import footer', 'bbox' => [70.0, 564.0, 730.0, 586.0]],
                    ['type' => 'Text', 'text' => 'Right rotated column', 'bbox' => [450.0, 320.0, 720.0, 338.0]],
                    ['type' => 'Page-header', 'text' => 'Rotated import header', 'bbox' => [70.0, 24.0, 730.0, 44.0]],
                    ['type' => 'Text', 'text' => 'Left rotated column', 'bbox' => [72.0, 320.0, 280.0, 338.0]],
                ],
            ],
        ];

        $sorted = $orderer->sortBlocksInReadingOrder($pages);

        $t->same(
            ['Rotated import header', 'Left rotated column', 'Right rotated column', 'Rotated import footer'],
            array_column($sorted[0]['blocks'], 'text')
        );
    },
    'reconstructs reading order from layout regions when supplied order is absent' => static function (TestRunner $t): void {
        $orderer = new LayoutOrderer();
        $pages = [
            [
                'bbox' => [0.0, 0.0, 600.0, 800.0],
                'layout' => [
                    'image_bbox' => [0.0, 0.0, 600.0, 800.0],
                    'bboxes' => [
                        ['label' => 'Page-header', 'bbox' => [60.0, 20.0, 540.0, 46.0]],
                        ['label' => 'Title', 'bbox' => [60.0, 70.0, 540.0, 112.0]],
                        ['label' => 'Text', 'bbox' => [60.0, 140.0, 270.0, 190.0]],
                        ['label' => 'Formula', 'bbox' => [80.0, 210.0, 260.0, 246.0]],
                        ['label' => 'Text', 'bbox' => [60.0, 270.0, 270.0, 330.0]],
                        ['label' => 'Text', 'bbox' => [330.0, 140.0, 560.0, 190.0]],
                        ['label' => 'Picture', 'bbox' => [330.0, 210.0, 560.0, 300.0]],
                        ['label' => 'Caption', 'bbox' => [330.0, 308.0, 560.0, 335.0]],
                        ['label' => 'Text', 'bbox' => [330.0, 360.0, 560.0, 410.0]],
                        ['label' => 'Page-footer', 'bbox' => [60.0, 760.0, 540.0, 786.0]],
                    ],
                ],
                'blocks' => [
                    ['type' => 'Text', 'text' => 'Right column opening', 'bbox' => [340.0, 150.0, 550.0, 170.0]],
                    ['type' => 'Caption', 'text' => 'Figure 1 caption', 'bbox' => [340.0, 312.0, 520.0, 330.0]],
                    ['type' => 'Text', 'text' => 'Left introduction', 'bbox' => [70.0, 150.0, 250.0, 170.0]],
                    ['type' => 'Page-footer', 'text' => 'Page 12', 'bbox' => [70.0, 764.0, 530.0, 782.0]],
                    ['type' => 'Formula', 'text' => 'E = mc^2', 'bbox' => [90.0, 215.0, 250.0, 240.0]],
                    ['type' => 'Title', 'text' => 'Layout Order Study', 'bbox' => [70.0, 78.0, 520.0, 104.0]],
                    ['type' => 'Text', 'text' => 'Right follow-up', 'bbox' => [340.0, 365.0, 550.0, 390.0]],
                    ['type' => 'Picture', 'text' => '[figure]', 'bbox' => [340.0, 220.0, 550.0, 292.0]],
                    ['type' => 'Page-header', 'text' => 'MarkerPDF Import', 'bbox' => [70.0, 24.0, 530.0, 42.0]],
                    ['type' => 'Text', 'text' => 'Left continuation', 'bbox' => [70.0, 278.0, 250.0, 302.0]],
                ],
            ],
        ];

        $sorted = $orderer->sortBlocksInReadingOrder($pages);
        $blocks = $sorted[0]['blocks'];
        $byText = array_column($blocks, null, 'text');

        $t->same([
            'MarkerPDF Import',
            'Layout Order Study',
            'Left introduction',
            'E = mc^2',
            'Left continuation',
            'Right column opening',
            '[figure]',
            'Figure 1 caption',
            'Right follow-up',
            'Page 12',
        ], array_column($blocks, 'text'));

        $diagnostics = $sorted[0]['layout_reading_order_diagnostics'] ?? [];
        $t->same('marker_layout_reading_order_reconstruction', $diagnostics['review_target'] ?? null);
        $t->same('layout_boxes_fallback', $diagnostics['source'] ?? null);
        $t->same(10, $diagnostics['layout_box_count'] ?? null);
        $t->same(10, $diagnostics['matched_block_count'] ?? null);
        $t->same(0, $diagnostics['unmatched_block_count'] ?? null);
        $t->same(2, $diagnostics['max_column_count'] ?? null);
        $t->same('layout', $byText['Left introduction']['reading_order_source'] ?? null);
        $t->same(3, $byText['Left introduction']['order_position'] ?? null);
        $t->same('Text', $byText['Left introduction']['layout_label'] ?? null);
        $t->same(0, $byText['Left introduction']['layout_reading_order']['column'] ?? null);
        $t->same(1, $byText['Right column opening']['layout_reading_order']['column'] ?? null);
        $t->same('Caption', $byText['Figure 1 caption']['layout_label'] ?? null);
    },
    'does not replace an explicit empty order artifact with layout fallback' => static function (TestRunner $t): void {
        $orderer = new LayoutOrderer();
        $pages = [
            [
                'bbox' => [0.0, 0.0, 600.0, 800.0],
                'layout' => [
                    'image_bbox' => [0.0, 0.0, 600.0, 800.0],
                    'bboxes' => [
                        ['label' => 'Text', 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['label' => 'Text', 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                ],
                'order' => [
                    'image_bbox' => [0.0, 0.0, 600.0, 800.0],
                    'bboxes' => [],
                ],
                'blocks' => [
                    ['type' => 'Text', 'text' => 'Right source block remains first.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                    ['type' => 'Text', 'text' => 'Left source block remains second.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                ],
            ],
        ];

        $sorted = $orderer->sortBlocksInReadingOrder($pages);

        $t->same(
            ['Right source block remains first.', 'Left source block remains second.'],
            array_column($sorted[0]['blocks'], 'text')
        );
        $t->true(!isset($sorted[0]['layout_reading_order_diagnostics']));
    },
    'rotates layout fallback boxes before matching blocks without supplied order' => static function (TestRunner $t): void {
        $orderer = new LayoutOrderer();
        $pages = [
            [
                'bbox' => [0.0, 0.0, 800.0, 600.0],
                'rotation' => 90,
                'layout' => [
                    'image_bbox' => [0.0, 0.0, 600.0, 800.0],
                    'bboxes' => [
                        ['label' => 'Page-header', 'bbox' => [20.0, 60.0, 50.0, 740.0]],
                        ['label' => 'Text', 'bbox' => [310.0, 500.0, 500.0, 740.0]],
                        ['label' => 'Text', 'bbox' => [310.0, 60.0, 500.0, 360.0]],
                        ['label' => 'Page-footer', 'bbox' => [560.0, 60.0, 590.0, 740.0]],
                    ],
                ],
                'blocks' => [
                    ['type' => 'Page-footer', 'text' => 'Rotated layout footer', 'bbox' => [70.0, 564.0, 730.0, 586.0]],
                    ['type' => 'Text', 'text' => 'Right layout column', 'bbox' => [450.0, 320.0, 720.0, 338.0]],
                    ['type' => 'Page-header', 'text' => 'Rotated layout header', 'bbox' => [70.0, 24.0, 730.0, 44.0]],
                    ['type' => 'Text', 'text' => 'Left layout column', 'bbox' => [72.0, 320.0, 280.0, 338.0]],
                ],
            ],
        ];

        $sorted = $orderer->sortBlocksInReadingOrder($pages);

        $t->same(
            ['Rotated layout header', 'Left layout column', 'Right layout column', 'Rotated layout footer'],
            array_column($sorted[0]['blocks'], 'text')
        );
        $t->same('layout_boxes_fallback', $sorted[0]['layout_reading_order_diagnostics']['source'] ?? null);
        $t->same(4, $sorted[0]['layout_reading_order_diagnostics']['matched_block_count'] ?? null);
    },
    'drives a WordPress ordering-model preflight before Gutenberg paragraph merge' => static function (TestRunner $t): void {
        $orderer = new LayoutOrderer();
        $processor = new MarkdownPostProcessor();
        $page = [
            'bbox' => [0.0, 0.0, 600.0, 800.0],
            'layout' => [
                'image_bbox' => [0.0, 0.0, 600.0, 800.0],
                'bboxes' => [
                    ['label' => 'Text', 'bbox' => [60.0, 90.0, 280.0, 180.0]],
                    ['label' => 'Text', 'bbox' => [320.0, 90.0, 560.0, 180.0]],
                ],
            ],
            'blocks' => [
                [
                    'type' => 'Text',
                    'lines' => [
                        ['text' => 'Second column belongs after the import summary.', 'bbox' => [330.0, 100.0, 540.0, 116.0]],
                    ],
                ],
                [
                    'type' => 'Text',
                    'lines' => [
                        ['text' => 'First column starts the WordPress import.', 'bbox' => [72.0, 100.0, 260.0, 116.0]],
                    ],
                ],
            ],
        ];
        $order = [
            'image_bbox' => [0.0, 0.0, 600.0, 800.0],
            'bboxes' => [
                ['position' => 1, 'bbox' => [60.0, 90.0, 280.0, 180.0]],
                ['position' => 2, 'bbox' => [320.0, 90.0, 560.0, 180.0]],
            ],
        ];

        $detected = $orderer->runWithSuppliedOrder(['rendered-page-placeholder'], [$page], [$order]);
        $sorted = $orderer->sortBlocksInReadingOrder($detected['pages']);
        $merged = $processor->mergeBlocks($sorted);

        $t->same(1, $detected['plan']['assigned_pages']);
        $t->same([2], $detected['plan']['layout_bbox_counts']);
        $t->same("First column starts the WordPress import.\n\nSecond column belongs after the import summary.", $merged[0]['text']);
    },
];
