<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$blockTypes = static fn (AstNode $document): array => array_map(
    static fn (AstNode $node): string => $node->type,
    $document->children
);

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$specialRawHtmlCases = [
    'comment' => [
        "before\n<!-- raw note -->\nafter",
        '<!-- raw note -->',
    ],
    'processing instruction' => [
        "before\n<?review packet?>\nafter",
        '<?review packet?>',
    ],
    'declaration' => [
        "before\n<!DOCTYPE html>\nafter",
        '<!DOCTYPE html>',
    ],
    'cdata' => [
        "before\n<![CDATA[raw < data]]>\nafter",
        '<![CDATA[raw < data]]>',
    ],
];

$namedRawHtmlCases = [
    'pre' => [
        "before\n<pre>\nraw *markdown*\n</pre>\nafter",
        "<pre>\nraw *markdown*\n</pre>",
    ],
    'style' => [
        "before\n<style>\na > b {}\n</style>\nafter",
        "<style>\na > b {}\n</style>",
    ],
    'textarea' => [
        "before\n<textarea>\nraw [label]\n</textarea>\nafter",
        "<textarea>\nraw [label]\n</textarea>",
    ],
];

return [
    'maps commonmark special raw html starts as paragraph interrupting blocks' =>
        static function (TestRunner $t) use ($blockTypes, $specialRawHtmlCases): void {
            foreach ($specialRawHtmlCases as $name => [$markdown, $expectedRaw]) {
                $document = (new MarkdownReader(['format' => 'commonmark']))->read($markdown);

                $t->same(['paragraph', 'raw_html', 'paragraph'], $blockTypes($document), $name);
                $t->same('before', $document->children[0]->attr('text'), $name . ' leading paragraph');
                $t->same($expectedRaw, $document->children[1]->attr('html'), $name . ' raw html');
                $t->same('after', $document->children[2]->attr('text'), $name . ' trailing paragraph');
            }
        },

    'maps commonmark named raw html starts as paragraph interrupting blocks' =>
        static function (TestRunner $t) use ($blockTypes, $namedRawHtmlCases): void {
            foreach ($namedRawHtmlCases as $name => [$markdown, $expectedRaw]) {
                $document = (new MarkdownReader(['format' => 'commonmark']))->read($markdown);

                $t->same(['paragraph', 'raw_html', 'paragraph'], $blockTypes($document), $name);
                $t->same('before', $document->children[0]->attr('text'), $name . ' leading paragraph');
                $t->same($expectedRaw, $document->children[1]->attr('html'), $name . ' raw html');
                $t->same('after', $document->children[2]->attr('text'), $name . ' trailing paragraph');
            }
        },

    'keeps commonmark generic raw html tag starts from interrupting paragraphs' =>
        static function (TestRunner $t) use ($blockTypes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read("before\n<custom-tag>\nafter");
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $raw = $paragraph->children[2] ?? new AstNode('missing');

            $t->same(['paragraph'], $blockTypes($document));
            $t->same(['text', 'softbreak', 'raw_html_inline', 'softbreak', 'text'], $inlineTypes($paragraph));
            $t->same('before  after', $paragraph->attr('text'));
            $t->same('<custom-tag>', $raw->attr('html'));
            $t->same('raw_html_inline', $raw->type);
        },

    'records commonmark raw html paragraph interrupt completion mapped-case count' =>
        static function (TestRunner $t) use ($specialRawHtmlCases, $namedRawHtmlCases): void {
            $t->same(8, count($specialRawHtmlCases) + count($namedRawHtmlCases) + 1);
        },
];
