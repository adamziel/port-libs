<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\LayoutAnnotator;
use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;

$layoutPage = static function (): array {
    return [
        'pnum' => 0,
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'layout' => [
            'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
            'bboxes' => [
                ['label' => 'Title', 'bbox' => [120.0, 80.0, 1000.0, 140.0]],
                ['label' => 'Text', 'bbox' => [120.0, 200.0, 1000.0, 260.0]],
                ['label' => 'Picture', 'bbox' => [120.0, 320.0, 1000.0, 480.0]],
            ],
        ],
        'blocks' => [
            [
                'bbox' => [60.0, 42.0, 270.0, 54.0],
                'lines' => [
                    ['text' => 'migration', 'bbox' => [60.0, 42.0, 270.0, 54.0]],
                ],
            ],
            [
                'bbox' => [280.0, 42.0, 500.0, 54.0],
                'lines' => [
                    ['text' => 'packet', 'bbox' => [280.0, 42.0, 500.0, 54.0]],
                ],
            ],
            [
                'bbox' => [60.0, 102.0, 420.0, 126.0],
                'lines' => [
                    ['text' => 'Review imported content before publishing.', 'bbox' => [60.0, 102.0, 420.0, 126.0]],
                ],
            ],
            [
                'bbox' => [60.0, 164.0, 500.0, 224.0],
                'lines' => [
                    ['text' => 'Screenshot text that should not become a paragraph.', 'bbox' => [60.0, 164.0, 500.0, 224.0]],
                ],
            ],
        ],
    ];
};

return [
    'uses upstream layout batch size defaults overrides and multiplier truncation' => static function (TestRunner $t): void {
        $t->same(6, (new LayoutAnnotator())->batchSize());
        $t->same(9, (new LayoutAnnotator(null, new MarkerSettings(['LAYOUT_BATCH_SIZE' => '9'])))->batchSize());
        $t->same(13, (new LayoutAnnotator(null, new MarkerSettings(['LAYOUT_BATCH_SIZE' => 9])))->batchSize(1.5));
        $t->same(4, (new LayoutAnnotator())->batchSize(0.75));
        $t->same(6, (new LayoutAnnotator(null, new MarkerSettings(['TORCH_DEVICE' => 'cuda'])))->batchSize());
    },
    'attaches supplied Surya layout predictions to pages with upstream zip semantics' => static function (TestRunner $t): void {
        $pages = [
            [
                'text_lines' => ['bboxes' => [['bbox' => [20.0, 20.0, 200.0, 42.0]]]],
                'blocks' => [],
            ],
            [
                'blocks' => [],
            ],
        ];
        $layouts = [
            ['image_bbox' => [0.0, 0.0, 1200.0, 1600.0], 'bboxes' => [['label' => 'Title', 'bbox' => [80.0, 80.0, 600.0, 140.0]]]],
            ['image_bbox' => [0.0, 0.0, 1200.0, 1600.0], 'bboxes' => [['label' => 'Text', 'bbox' => [90.0, 200.0, 640.0, 260.0]]]],
            ['image_bbox' => [0.0, 0.0, 1200.0, 1600.0], 'bboxes' => [['label' => 'Picture', 'bbox' => [90.0, 320.0, 640.0, 480.0]]]],
        ];

        $result = (new LayoutAnnotator())->runWithSuppliedLayouts(['image-1', 'image-2'], $pages, $layouts, 2.0);

        $t->same([
            'image_count' => 2,
            'page_count' => 2,
            'detection_result_count' => 1,
            'layout_result_count' => 3,
            'assigned_pages' => 2,
            'batch_size' => 12,
        ], $result['plan']);
        $t->same($layouts[0], $result['pages'][0]['layout']);
        $t->same($layouts[1], $result['pages'][1]['layout']);
    },
    'leaves unpaired pages unchanged when supplied layouts are shorter than pages' => static function (TestRunner $t): void {
        $pages = [
            ['blocks' => []],
            ['blocks' => []],
        ];
        $layout = ['image_bbox' => [0.0, 0.0, 1200.0, 1600.0], 'bboxes' => []];

        $result = (new LayoutAnnotator())->runWithSuppliedLayouts(['image-1', 'image-2'], $pages, [$layout]);

        $t->same(1, $result['plan']['assigned_pages']);
        $t->true(isset($result['pages'][0]['layout']));
        $t->true(!isset($result['pages'][1]['layout']));
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => (new LayoutAnnotator())->runWithSuppliedLayouts(['image'], [['blocks' => []]], ['not-array'])
        );
    },
    'assigns block types from rescaled upstream layout intersections and merges same layout block runs' => static function (TestRunner $t) use ($layoutPage): void {
        $annotated = (new LayoutAnnotator())->annotateBlockTypes([$layoutPage()]);
        $blocks = $annotated[0]['blocks'];

        $t->same(['Title', 'Text', 'Picture'], array_column($blocks, 'block_type'));
        $t->same(['migration', 'packet'], array_column($blocks[0]['lines'], 'text'));
        $t->same([60.0, 42.0, 500.0, 54.0], $blocks[0]['bbox']);
        $t->same('Review imported content before publishing.', $blocks[1]['lines'][0]['text']);
    },
    'falls back to the closest annotated block then default text like marker layout annotation' => static function (TestRunner $t): void {
        $page = [
            'bbox' => [0.0, 0.0, 600.0, 800.0],
            'layout_boxes' => [
                ['label' => 'Caption', 'bbox' => [70.0, 100.0, 320.0, 130.0]],
            ],
            'blocks' => [
                ['bbox' => [72.0, 104.0, 318.0, 126.0], 'lines' => [['text' => 'Figure 1. Imported media.', 'bbox' => [72.0, 104.0, 318.0, 126.0]]]],
                ['bbox' => [72.0, 132.0, 318.0, 150.0], 'lines' => [['text' => 'Continued caption text.', 'bbox' => [72.0, 132.0, 318.0, 150.0]]]],
            ],
        ];
        $noLayoutPage = [
            'blocks' => [
                ['bbox' => [72.0, 80.0, 320.0, 100.0], 'lines' => [['text' => 'Untyped fallback.', 'bbox' => [72.0, 80.0, 320.0, 100.0]]]],
            ],
        ];

        $annotated = (new LayoutAnnotator())->annotateBlockTypes([$page, $noLayoutPage]);

        $t->same(['Caption', 'Caption'], array_column($annotated[0]['blocks'], 'block_type'));
        $t->same('Text', $annotated[1]['blocks'][0]['block_type']);
    },
    'drives a WordPress layout detection preflight before annotation' => static function (TestRunner $t): void {
        $page = [
            'pnum' => 0,
            'bbox' => [0.0, 0.0, 600.0, 800.0],
            'text_lines' => [
                'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
                'bboxes' => [
                    ['bbox' => [120.0, 80.0, 1000.0, 140.0]],
                    ['bbox' => [120.0, 200.0, 1000.0, 260.0]],
                ],
            ],
            'blocks' => [
                ['bbox' => [60.0, 42.0, 500.0, 54.0], 'lines' => [['text' => 'migration packet', 'bbox' => [60.0, 42.0, 500.0, 54.0]]]],
                ['bbox' => [60.0, 102.0, 420.0, 126.0], 'lines' => [['text' => 'Review supplied layout output before publishing.', 'bbox' => [60.0, 102.0, 420.0, 126.0]]]],
            ],
        ];
        $layout = [
            'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
            'bboxes' => [
                ['label' => 'Title', 'bbox' => [120.0, 80.0, 1000.0, 140.0]],
                ['label' => 'Text', 'bbox' => [120.0, 200.0, 1000.0, 260.0]],
            ],
        ];

        $annotator = new LayoutAnnotator();
        $detected = $annotator->runWithSuppliedLayouts(['rendered-page-placeholder'], [$page], [$layout]);
        $annotated = $annotator->annotateBlockTypes($detected['pages']);
        $merged = (new MarkdownPostProcessor())->mergeBlocks($annotated);

        $t->same(1, $detected['plan']['assigned_pages']);
        $t->same(['Title', 'Text'], array_column($annotated[0]['blocks'], 'block_type'));
        $t->same("# Migration Packet\n", $merged[0]['text']);
        $t->contains('Review supplied layout output before publishing.', $merged[1]['text']);
    },
    'renders a WordPress import after layout annotation while honoring bad span type settings' => static function (TestRunner $t) use ($layoutPage): void {
        $settings = new MarkerSettings();
        $annotated = (new LayoutAnnotator())->annotateBlockTypes([$layoutPage()]);
        $annotated[0]['blocks'] = array_values(array_filter(
            $annotated[0]['blocks'],
            static fn (array $block): bool => !in_array($block['block_type'], $settings->badSpanTypes(), true)
        ));

        $merged = (new MarkdownPostProcessor())->mergeBlocks($annotated);
        $html = '';
        foreach ($merged as $block) {
            if ($block['block_type'] === 'Title') {
                $text = trim(ltrim($block['text'], '# '));
                $html .= "<!-- wp:heading -->\n<h1>" . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h1>\n<!-- /wp:heading -->\n";
                continue;
            }

            $html .= "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(trim($block['text']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
        }

        $t->contains('<h1>Migration Packet</h1>', $html);
        $t->contains('<p>Review imported content before publishing.</p>', $html);
        $t->true(!str_contains($html, 'Screenshot text'), 'Picture text should be removed by BAD_SPAN_TYPES before WordPress rendering.');
    },
];
