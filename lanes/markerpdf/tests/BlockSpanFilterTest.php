<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BlockSpanFilter;
use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;

return [
    'removes marked span ids and drops empty lines like upstream filter_spans' => static function (TestRunner $t): void {
        $filter = new BlockSpanFilter();
        $block = [
            'block_type' => 'Text',
            'lines' => [
                ['spans' => [
                    ['span_id' => 'header_0', 'text' => 'Confidential '],
                    ['span_id' => 'body_0', 'text' => 'Migration body text.'],
                ]],
                ['spans' => [
                    ['span_id' => 'footer_0', 'text' => 'Page 1'],
                ]],
            ],
        ];

        $filtered = $filter->filterSpans($block, ['header_0', 'footer_0']);

        $t->same(1, count($filtered['lines']));
        $t->same(1, count($filtered['lines'][0]['spans']));
        $t->same('body_0', $filtered['lines'][0]['spans'][0]['span_id']);
        $t->same('Migration body text.', $filtered['lines'][0]['spans'][0]['text']);
    },
    'drops already-empty lines even when upstream filters keep all spans' => static function (TestRunner $t): void {
        $filter = new BlockSpanFilter();
        $block = [
            'block_type' => 'Text',
            'lines' => [
                ['spans' => []],
                ['spans' => [
                    ['span_id' => 'body_0', 'text' => 'Kept import text.'],
                ]],
                ['spans' => []],
            ],
        ];

        $filteredSpans = $filter->filterSpans($block, []);
        $filteredTypes = $filter->filterBadSpanTypes($block, new MarkerSettings());

        $t->same(1, count($filteredSpans['lines']));
        $t->same('Kept import text.', $filteredSpans['lines'][0]['spans'][0]['text']);
        $t->same($filteredSpans, $filteredTypes);
    },
    'clears bad span type text while preserving block metadata for image review' => static function (TestRunner $t): void {
        $filter = new BlockSpanFilter();
        $block = [
            'block_type' => 'Picture',
            'bbox' => [40.0, 100.0, 300.0, 240.0],
            'image_filename' => '4_image_0.png',
            'lines' => [
                ['spans' => [
                    ['span_id' => 'picture_ocr_0', 'text' => 'Chart OCR text should not become a paragraph.'],
                ]],
            ],
        ];

        $filtered = $filter->filterBadSpanTypes($block, new MarkerSettings());

        $t->same([], $filtered['lines']);
        $t->same('Picture', $filtered['block_type']);
        $t->same('4_image_0.png', $filtered['image_filename']);
        $t->same([40.0, 100.0, 300.0, 240.0], $filtered['bbox']);
    },
    'filters WordPress render text without losing picture block metadata' => static function (TestRunner $t): void {
        $pages = [[
            'pnum' => 4,
            'blocks' => [
                [
                    'block_type' => 'Page-header',
                    'bbox' => [40.0, 20.0, 300.0, 36.0],
                    'lines' => [[
                        'bbox' => [40.0, 20.0, 300.0, 36.0],
                        'spans' => [['span_id' => 'header_4', 'font' => 'Header', 'text' => 'Migration Packet']],
                    ]],
                ],
                [
                    'block_type' => 'Text',
                    'bbox' => [40.0, 72.0, 520.0, 96.0],
                    'lines' => [[
                        'bbox' => [40.0, 72.0, 520.0, 96.0],
                        'spans' => [['span_id' => 'body_4', 'font' => 'Body', 'text' => 'Imported paragraph for editorial review.']],
                    ]],
                ],
                [
                    'block_type' => 'Picture',
                    'bbox' => [40.0, 120.0, 520.0, 300.0],
                    'image_filename' => '4_image_0.png',
                    'lines' => [[
                        'bbox' => [60.0, 140.0, 400.0, 160.0],
                        'spans' => [['span_id' => 'picture_text_4', 'font' => 'Picture', 'text' => 'Screenshot OCR overlay']],
                    ]],
                ],
            ],
        ]];

        $filtered = (new BlockSpanFilter())->filterPages($pages, ['header_4']);
        $mergedPages = (new MarkdownPostProcessor())->mergeSpans($filtered);
        $blocks = (new MarkdownPostProcessor())->mergeBlocks($mergedPages);
        $html = '';
        foreach ($blocks as $block) {
            $html .= '<p>' . htmlspecialchars(trim($block['text']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        $t->same([], $filtered[0]['blocks'][0]['lines']);
        $t->same([], $filtered[0]['blocks'][2]['lines']);
        $t->same('4_image_0.png', $filtered[0]['blocks'][2]['image_filename']);
        $t->contains('<p>Imported paragraph for editorial review.</p>', $html);
        $t->true(!str_contains($html, 'Migration Packet'));
        $t->true(!str_contains($html, 'Screenshot OCR overlay'));
    },
];
