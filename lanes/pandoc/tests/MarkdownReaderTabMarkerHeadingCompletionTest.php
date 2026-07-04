<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$childTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$cases = [
    'dash marker heading first child' => [
        'markdown' => "-\t# Tab heading\n\tcontinuation",
        'listType' => 'bullet_list',
        'listAttrs' => ['marker' => '-'],
        'itemChildren' => ['heading', 'paragraph'],
        'headingIndex' => 0,
        'headingText' => 'Tab heading',
        'headingId' => '',
        'paragraphIndex' => 1,
        'paragraphText' => 'continuation',
        'wordpress' => '<ul><li><h1>Tab heading</h1>continuation</li></ul>',
    ],
    'ordered marker heading first child' => [
        'markdown' => "1.\t# Ordered tab heading\n\tcontinuation",
        'listType' => 'ordered_list',
        'listAttrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
        'itemChildren' => ['heading', 'paragraph'],
        'headingIndex' => 0,
        'headingText' => 'Ordered tab heading',
        'headingId' => '',
        'paragraphIndex' => 1,
        'paragraphText' => 'continuation',
        'wordpress' => '<ol><li><h1>Ordered tab heading</h1>continuation</li></ol>',
    ],
    'dash marker paragraph then heading' => [
        'markdown' => "-\tlead\n\t# Nested tab heading",
        'listType' => 'bullet_list',
        'listAttrs' => ['marker' => '-'],
        'itemChildren' => ['text', 'heading'],
        'headingIndex' => 1,
        'headingText' => 'Nested tab heading',
        'headingId' => '',
        'leadText' => 'lead',
        'wordpress' => '<ul><li>lead<h1>Nested tab heading</h1></li></ul>',
    ],
    'ordered marker paragraph then heading' => [
        'markdown' => "1.\tlead\n\t# Ordered nested tab heading",
        'listType' => 'ordered_list',
        'listAttrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
        'itemChildren' => ['text', 'heading'],
        'headingIndex' => 1,
        'headingText' => 'Ordered nested tab heading',
        'headingId' => '',
        'leadText' => 'lead',
        'wordpress' => '<ol><li>lead<h1>Ordered nested tab heading</h1></li></ol>',
    ],
];

$tests = [
    'records markdown reader tab marker heading completion mapped-case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(4, count($cases));
        },
];

foreach ($cases as $name => $case) {
    $tests['maps commonmark tab marker heading completion ' . $name] =
        static function (TestRunner $t) use ($case, $childTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read($case['markdown']);
            $list = $document->children[0] ?? new AstNode('missing');
            $item = $list->children[0] ?? new AstNode('missing');
            $heading = $item->children[$case['headingIndex']] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same([$case['listType']], $childTypes($document), $case['markdown']);
            $t->same($case['listType'], $list->type, $case['markdown']);
            foreach ($case['listAttrs'] as $name => $expected) {
                $t->same($expected, $list->attr($name), $case['markdown'] . ' list attr ' . $name);
            }
            $t->same(1, count($list->children), $case['markdown']);
            $t->same($case['itemChildren'], $childTypes($item), $case['markdown']);

            if (isset($case['leadText'])) {
                $lead = $item->children[0] ?? new AstNode('missing');
                $t->same($case['leadText'], $lead->attr('text'), $case['markdown'] . ' lead text');
            }

            $t->same('heading', $heading->type, $case['markdown']);
            $t->same(1, $heading->attr('level'), $case['markdown']);
            $t->same($case['headingText'], $heading->attr('text'), $case['markdown']);
            $t->same($case['headingId'], $heading->attr('id'), $case['markdown']);

            if (isset($case['paragraphIndex'])) {
                $paragraph = $item->children[$case['paragraphIndex']] ?? new AstNode('missing');
                $t->same('paragraph', $paragraph->type, $case['markdown']);
                $t->same($case['paragraphText'], $paragraph->attr('text'), $case['markdown']);
            }

            $t->contains($case['wordpress'], $blocks, $case['markdown'] . ' wordpress handoff');
        };
}

return $tests;
