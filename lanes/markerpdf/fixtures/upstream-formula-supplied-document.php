<?php

declare(strict_types=1);

return [
    'pdftextPages' => [
        [
            'page' => 0,
            'bbox' => [0.0, 0.0, 612.0, 792.0],
            'rotation' => 0,
            'blocks' => [
                [
                    'lines' => [
                        [
                            'bbox' => [72.0, 54.0, 420.0, 76.0],
                            'spans' => [[
                                'text' => 'WordPress math migration',
                                'bbox' => [72.0, 54.0, 420.0, 76.0],
                                'font' => [
                                    'name' => 'Times-Bold',
                                    'flags' => 0,
                                    'weight' => 700,
                                    'size' => 18,
                                ],
                            ]],
                        ],
                        [
                            'bbox' => [72.0, 116.0, 430.0, 130.0],
                            'spans' => [[
                                'text' => 'Legacy PDFs may encode equations as extracted text.',
                                'bbox' => [72.0, 116.0, 430.0, 130.0],
                                'font' => [
                                    'name' => 'Times-Roman',
                                    'flags' => 0,
                                    'weight' => 400,
                                    'size' => 12,
                                ],
                            ]],
                        ],
                        [
                            'bbox' => [110.0, 170.0, 260.0, 188.0],
                            'spans' => [[
                                'text' => 'E = m c ^ 2',
                                'bbox' => [110.0, 170.0, 260.0, 188.0],
                                'font' => [
                                    'name' => 'Times-Italic',
                                    'flags' => 0,
                                    'weight' => 400,
                                    'size' => 12,
                                ],
                            ]],
                        ],
                        [
                            'bbox' => [72.0, 226.0, 430.0, 240.0],
                            'spans' => [[
                                'text' => 'Editors review converted math before publishing.',
                                'bbox' => [72.0, 226.0, 430.0, 240.0],
                                'font' => [
                                    'name' => 'Times-Roman',
                                    'flags' => 0,
                                    'weight' => 400,
                                    'size' => 12,
                                ],
                            ]],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'options' => [
        'metadata' => ['languages' => ['English']],
        'toc' => [['title' => 'WordPress math migration', 'level' => 1, 'page_index' => 0]],
        'lowres_images' => ['page-0-lowres'],
        'layout_results' => [
            [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 54.0, 420.0, 76.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 110.0, 430.0, 136.0]],
                    ['label' => 'Formula', 'bbox' => [106.0, 164.0, 270.0, 194.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 220.0, 430.0, 246.0]],
                ],
            ],
        ],
        'order_images' => ['page-0-lowres'],
        'order_results' => [
            [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 0, 'bbox' => [72.0, 54.0, 420.0, 76.0]],
                    ['position' => 1, 'bbox' => [72.0, 110.0, 430.0, 136.0]],
                    ['position' => 2, 'bbox' => [106.0, 164.0, 270.0, 194.0]],
                    ['position' => 3, 'bbox' => [72.0, 220.0, 430.0, 246.0]],
                ],
            ],
        ],
        'equation_results' => [
            ['latex' => '$$E=mc^2$$'],
        ],
    ],
    'expectedMarkdown' => "# Wordpress Math Migration\n\nLegacy PDFs may encode equations as extracted text.\n\n" . '$$E=mc^2$$' . "\n\nEditors review converted math before publishing.",
];
