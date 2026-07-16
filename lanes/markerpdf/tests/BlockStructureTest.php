<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BlockStructure;
use PortLibs\MarkerPDF\MarkdownPostProcessor;

$block = static function (): array {
    return [
        'pnum' => 2,
        'block_type' => 'Text',
        'source_id' => 'kept-on-original-only',
        'lines' => [
            [
                'bbox' => [72.0, 90.0, 420.0, 106.0],
                'spans' => [[
                    'text' => 'Migration Checklist',
                    'span_id' => '2_0',
                    'bbox' => [80.0, 90.0, 420.0, 106.0],
                ]],
            ],
            [
                'bbox' => [70.0, 122.0, 510.0, 138.0],
                'spans' => [[
                    'text' => 'Confirm media imports before publishing.',
                    'span_id' => '2_1',
                    'bbox' => [74.0, 122.0, 510.0, 138.0],
                ]],
            ],
            [
                'bbox' => [96.0, 146.0, 500.0, 162.0],
                'spans' => [[
                    'text' => 'Keep reviewer notes attached.',
                    'span_id' => '2_2',
                    'bbox' => [48.0, 146.0, 500.0, 162.0],
                ]],
            ],
        ],
    ];
};

return [
    'computes bbox_from_lines extents like upstream block helper' => static function (TestRunner $t) use ($block): void {
        $structure = new BlockStructure();

        $t->same([70.0, 90.0, 510.0, 162.0], $structure->bboxFromLines($block()['lines']));
        $t->throws(InvalidArgumentException::class, static fn (): array => $structure->bboxFromLines([]));
    },
    'splits block lines with upstream zero and overlong guards' => static function (TestRunner $t) use ($block): void {
        $structure = new BlockStructure();
        $source = $block();

        $t->same([$source], $structure->splitBlockLines($source, 0));
        $t->same([$source], $structure->splitBlockLines($source, 3));

        $split = $structure->splitBlockLines($source, 1);

        $t->same(2, count($split));
        $t->same([72.0, 90.0, 420.0, 106.0], $split[0]['bbox']);
        $t->same([70.0, 122.0, 510.0, 162.0], $split[1]['bbox']);
        $t->same(2, $split[0]['pnum']);
        $t->same(1, count($split[0]['lines']));
        $t->same(2, count($split[1]['lines']));
        $t->true(!array_key_exists('block_type', $split[0]));
        $t->true(!array_key_exists('source_id', $split[1]));
        $t->throws(InvalidArgumentException::class, static fn (): array => $structure->splitBlockLines($source, -1));
    },
    'uses first span starts for upstream get_min_line_start semantics' => static function (TestRunner $t) use ($block): void {
        $structure = new BlockStructure();

        $t->same(48.0, $structure->getMinLineStart($block()));
        $t->same(null, $structure->getMinLineStart(['lines' => []]));
        $t->same(null, $structure->getMinLineStart(['lines' => [['spans' => []]]]));
    },
    'splits a WordPress import block before Gutenberg heading and paragraph rendering' => static function (TestRunner $t) use ($block): void {
        $structure = new BlockStructure();
        $processor = new MarkdownPostProcessor();
        $split = $structure->splitBlockLines($block(), 1);
        $split[0]['block_type'] = 'Section-header';
        $split[0]['heading_level'] = 2;
        $split[1]['block_type'] = 'Text';

        $merged = $processor->mergeBlocks([['pnum' => 2, 'blocks' => $split]]);
        $html = '';
        foreach ($merged as $mergedBlock) {
            $text = trim($mergedBlock['text']);
            if (($mergedBlock['block_type'] ?? '') === 'Section-header') {
                $level = max(1, min(6, strspn($text, '#') ?: 2));
                $text = trim(ltrim($text, '# '));
                $html .= '<!-- wp:heading {"level":' . $level . '} -->'
                    . '<h' . $level . '>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h' . $level . '>'
                    . '<!-- /wp:heading -->';
                continue;
            }

            foreach (preg_split('/\n{2,}/', $text) ?: [] as $paragraph) {
                if ($paragraph === '') {
                    continue;
                }
                $html .= '<!-- wp:paragraph -->'
                    . '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
                    . '<!-- /wp:paragraph -->';
            }
        }

        $t->contains('<h2>Migration Checklist</h2>', $html);
        $t->contains('<p>Confirm media imports before publishing.</p>', $html);
        $t->contains('<p>Keep reviewer notes attached.</p>', $html);
    },
];
