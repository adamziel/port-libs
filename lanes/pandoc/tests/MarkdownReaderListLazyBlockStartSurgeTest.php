<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$nodeText = static function (AstNode $node) use (&$nodeText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $parts = [];
    foreach ($node->children as $child) {
        $text = $nodeText($child);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? '');
};

$markers = [
    'dash bullet' => ['marker' => '- ', 'list' => 'bullet_list', 'attrs' => ['marker' => '-']],
    'plus bullet' => ['marker' => '+ ', 'list' => 'bullet_list', 'attrs' => ['marker' => '+']],
    'star bullet' => ['marker' => '* ', 'list' => 'bullet_list', 'attrs' => ['marker' => '*']],
    'decimal period' => ['marker' => '1. ', 'list' => 'ordered_list', 'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period']],
    'decimal paren' => ['marker' => '1) ', 'list' => 'ordered_list', 'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'one_paren']],
    'parenthesized decimal' => ['marker' => '(1) ', 'list' => 'ordered_list', 'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'two_parens']],
    'numbered example' => ['marker' => '(@) ', 'list' => 'ordered_list', 'attrs' => ['start' => 1, 'style' => 'example', 'delimiter' => 'two_parens']],
    'default ordered' => ['marker' => '#. ', 'list' => 'ordered_list', 'attrs' => ['start' => 1, 'style' => 'default', 'delimiter' => 'default']],
    'upper alpha' => ['marker' => 'A.  ', 'list' => 'ordered_list', 'attrs' => ['start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'period']],
    'upper roman' => ['marker' => 'I.  ', 'list' => 'ordered_list', 'attrs' => ['start' => 1, 'style' => 'upper_roman', 'delimiter' => 'period']],
];

$blockStarts = [
    'backtick fenced code' => ['markdown' => "```\ncode\n```", 'type' => 'code_block'],
    'tilde fenced code' => ['markdown' => "~~~\ncode\n~~~", 'type' => 'code_block'],
    'fenced div' => ['markdown' => "::: note\ncontent\n:::", 'type' => 'div'],
    'raw html div' => ['markdown' => '<div>html</div>', 'type' => 'div'],
    'processing instruction' => ['markdown' => '<?note?>', 'type' => 'raw_html'],
    'line block' => ['markdown' => '| verse line', 'type' => 'line_block'],
    'pipe table' => ['markdown' => "| A | B |\n|---|---|\n| 1 | 2 |", 'type' => 'table'],
    'raw tex environment' => ['markdown' => "\\begin{note}\nbody\n\\end{note}", 'type' => 'raw_tex'],
];

$tests = [
    'records markdown reader list lazy block-start surge mapped-case count' =>
        static function (TestRunner $t) use ($markers, $blockStarts): void {
            $t->same(80, count($markers) * count($blockStarts));
        },
];

foreach ($markers as $markerName => $marker) {
    foreach ($blockStarts as $blockName => $block) {
        $tests['maps upstream markdown reader list lazy block-start surge ' . $markerName . ' before ' . $blockName] =
            static function (TestRunner $t) use ($marker, $block, $nodeText): void {
                $markdown = $marker['marker'] . "item\n" . $block['markdown'];
                $document = (new MarkdownReader())->read($markdown);
                $list = $document->children[0] ?? new AstNode('missing');
                $item = $list->children[0] ?? new AstNode('missing');
                $following = $document->children[1] ?? new AstNode('missing');

                $t->same([$marker['list'], $block['type']], array_map(
                    static fn (AstNode $node): string => $node->type,
                    $document->children
                ), $markdown);
                $t->same(1, count($list->children), $markdown);
                $t->same('item', $nodeText($item), $markdown);
                $t->same($block['type'], $following->type, $markdown);
                foreach ($marker['attrs'] as $attr => $expected) {
                    $t->same($expected, $list->attr($attr), $markdown . ' list attr ' . $attr);
                }
            };
    }
}

return $tests;
