<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\LayoutOrderer;
use PortLibs\MarkerPDF\MarkdownPostProcessor;

return [
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
];
