<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ImageExtractor;

$imagePage = static function (): array {
    return [
        'pnum' => 2,
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'layout' => [
            'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
            'bboxes' => [
                ['label' => 'Text', 'bbox' => [120.0, 120.0, 560.0, 180.0]],
                ['label' => 'Figure', 'bbox' => [120.0, 200.0, 560.0, 440.0]],
                ['label' => 'Picture', 'bbox' => [700.0, 200.0, 1120.0, 440.0]],
            ],
        ],
        'blocks' => [
            [
                'type' => 'Text',
                'bbox' => [60.0, 100.0, 280.0, 220.0],
                'lines' => [
                    [
                        'bbox' => [62.0, 104.0, 270.0, 132.0],
                        'spans' => [
                            ['text' => 'Rasterized chart placeholder', 'span_id' => 'line_0'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'Text',
                'bbox' => [340.0, 100.0, 560.0, 220.0],
                'lines' => [
                    [
                        'bbox' => [350.0, 104.0, 550.0, 132.0],
                        'spans' => [
                            ['text' => 'Second image placeholder', 'span_id' => 'line_1'],
                        ],
                    ],
                ],
            ],
        ],
    ];
};

return [
    'names and exports page images like upstream marker image save helpers' => static function (TestRunner $t): void {
        $extractor = new ImageExtractor();

        $t->same('4_image_1.png', $extractor->getImageFilename(['pnum' => 4], 1));
        $t->same(
            [
                '4_image_0.png' => 'first-png',
                '4_image_1.png' => 'second-png',
                '7_image_0.png' => 'third-png',
            ],
            $extractor->imagesToDict([
                ['pnum' => 4, 'images' => ['first-png', 'second-png']],
                ['pnum' => 5, 'images' => null],
                ['pnum' => 7, 'images' => ['third-png']],
            ])
        );
    },
    'finds figure and picture regions with upstream bbox rescaling' => static function (TestRunner $t) use ($imagePage): void {
        $blocks = (new ImageExtractor())->findImageBlocks($imagePage());

        $t->same(
            [
                ['block_index' => 0, 'line_index' => 0, 'bbox' => [60.0, 100.0, 280.0, 220.0]],
                ['block_index' => 1, 'line_index' => 0, 'bbox' => [350.0, 100.0, 560.0, 220.0]],
            ],
            $blocks
        );
    },
    'keeps image regions inside table and formula layout boundaries out of image insertion' => static function (TestRunner $t) use ($imagePage): void {
        $page = $imagePage();
        $page['layout']['bboxes'][] = ['label' => 'Table', 'bbox' => [100.0, 180.0, 590.0, 460.0]];
        $page['layout']['bboxes'][] = ['label' => 'Formula', 'bbox' => [680.0, 180.0, 1140.0, 460.0]];

        $extractor = new ImageExtractor();
        $withImages = $extractor->insertImagePlaceholders($page, ['table-image', 'formula-image']);

        $t->same([], $extractor->findImageBlocks($page));
        $t->same([], $withImages['images']);
        $t->same('Rasterized chart placeholder', $withImages['blocks'][0]['lines'][0]['spans'][0]['text']);
        $t->same('Second image placeholder', $withImages['blocks'][1]['lines'][0]['spans'][0]['text']);
    },
    'inserts upstream markdown image spans and clears intersecting text lines' => static function (TestRunner $t) use ($imagePage): void {
        $page = (new ImageExtractor())->insertImagePlaceholders($imagePage(), ['figure-bytes', 'picture-bytes']);

        $t->same(1, count($page['blocks'][0]['lines'][0]['spans']));
        $t->same("\n\n![2_image_0.png](2_image_0.png)\n\n", $page['blocks'][0]['lines'][0]['spans'][0]['text']);
        $t->same('Image', $page['blocks'][0]['lines'][0]['spans'][0]['font']);
        $t->true($page['blocks'][0]['lines'][0]['spans'][0]['image']);
        $t->same(1, count($page['blocks'][1]['lines'][0]['spans']));
        $t->same("\n\n![2_image_1.png](2_image_1.png)\n\n", $page['blocks'][1]['lines'][0]['spans'][0]['text']);
        $t->same(['figure-bytes', 'picture-bytes'], $page['images']);
    },
    'falls back to nearest block and creates a line when no detected text overlaps' => static function (TestRunner $t): void {
        $extractor = new ImageExtractor();
        $page = [
            'pnum' => 0,
            'bbox' => [0.0, 0.0, 600.0, 800.0],
            'layout_boxes' => [
                ['label' => 'Picture', 'bbox' => [320.0, 420.0, 520.0, 540.0]],
            ],
            'blocks' => [
                ['bbox' => [72.0, 80.0, 260.0, 140.0], 'lines' => []],
                ['bbox' => [300.0, 400.0, 560.0, 470.0], 'lines' => []],
            ],
        ];

        $t->same(1, $extractor->findInsertBlock($page['blocks'], [320.0, 420.0, 520.0, 540.0]));

        $withImage = $extractor->insertImagePlaceholders($page);
        $t->same("\n\n![0_image_0.png](0_image_0.png)\n\n", $withImage['blocks'][1]['lines'][0]['spans'][0]['text']);
        $t->same([320.0, 420.0, 520.0, 540.0], $withImage['blocks'][1]['lines'][0]['bbox']);
    },
    'renders a WordPress image block scenario from inserted image markdown' => static function (TestRunner $t) use ($imagePage): void {
        $page = (new ImageExtractor())->insertImagePlaceholders($imagePage());
        $imageSpans = [];
        foreach ($page['blocks'] as $block) {
            foreach ($block['lines'] as $line) {
                foreach ($line['spans'] as $span) {
                    if (($span['image'] ?? false) === true) {
                        $imageSpans[] = $span;
                    }
                }
            }
        }

        $html = '';
        foreach ($imageSpans as $span) {
            preg_match('/!\[([^]]+)]\(([^)]+)\)/', $span['text'], $matches);
            $alt = htmlspecialchars($matches[1] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $src = htmlspecialchars($matches[2] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= "<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"{$src}\" alt=\"{$alt}\"/></figure>\n<!-- /wp:image -->\n";
        }

        $t->same(
            "<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"2_image_0.png\" alt=\"2_image_0.png\"/></figure>\n<!-- /wp:image -->\n"
            . "<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"2_image_1.png\" alt=\"2_image_1.png\"/></figure>\n<!-- /wp:image -->\n",
            $html
        );
    },
];
