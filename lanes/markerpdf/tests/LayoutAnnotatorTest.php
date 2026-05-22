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
