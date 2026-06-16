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

$blockTagRawHtmlCases = [
    'article' => [
        "before\n<article data-review=\"raw-boundary\">\n**raw article** stays raw.\n\nafter",
        "<article data-review=\"raw-boundary\">\n**raw article** stays raw.",
    ],
    'blockquote' => [
        "before\n<blockquote data-review=\"raw-boundary\">\n> raw quote stays raw.\n\nafter",
        "<blockquote data-review=\"raw-boundary\">\n> raw quote stays raw.",
    ],
    'div' => [
        "before\n<div data-review=\"raw-boundary\">\n_raw div_ stays raw.\n\nafter",
        "<div data-review=\"raw-boundary\">\n_raw div_ stays raw.",
    ],
    'p' => [
        "before\n<p data-review=\"raw-boundary\">\n**raw paragraph** stays raw.\n\nafter",
        "<p data-review=\"raw-boundary\">\n**raw paragraph** stays raw.",
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

    'maps commonmark block tag raw html starts to blank line boundary blocks' =>
        static function (TestRunner $t) use ($blockTypes, $blockTagRawHtmlCases): void {
            foreach ($blockTagRawHtmlCases as $name => [$markdown, $expectedRaw]) {
                $document = (new MarkdownReader(['format' => 'commonmark']))->read($markdown);

                $t->same(['paragraph', 'raw_html', 'paragraph'], $blockTypes($document), $name);
                $t->same('before', $document->children[0]->attr('text'), $name . ' leading paragraph');
                $t->same($expectedRaw, $document->children[1]->attr('html'), $name . ' raw html');
                $t->same('after', $document->children[2]->attr('text'), $name . ' trailing paragraph');
            }
        },

    'keeps commonmark standalone generic raw html tag lines as blank line blocks' =>
        static function (TestRunner $t) use ($blockTypes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                '<x-review data-case="standalone">',
                '*standalone generic raw*',
                '</x-review>',
                '',
                'After **generic** boundary.',
            ]));
            $raw = $document->children[0] ?? new AstNode('missing');
            $paragraph = $document->children[1] ?? new AstNode('missing');

            $t->same(['raw_html', 'paragraph'], $blockTypes($document));
            $t->same("<x-review data-case=\"standalone\">\n*standalone generic raw*\n</x-review>", $raw->attr('html'));
            $t->same(['text', 'strong', 'text'], $inlineTypes($paragraph));
            $t->same('After generic boundary.', $paragraph->attr('text'));
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
        static function (TestRunner $t) use ($specialRawHtmlCases, $namedRawHtmlCases, $blockTagRawHtmlCases): void {
            $t->same(13, count($specialRawHtmlCases) + count($namedRawHtmlCases) + count($blockTagRawHtmlCases) + 2);
        },
];
