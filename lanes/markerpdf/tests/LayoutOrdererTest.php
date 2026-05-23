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
