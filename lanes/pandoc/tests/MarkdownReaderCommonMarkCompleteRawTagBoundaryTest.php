<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$blockTypes = static fn (AstNode $document): array => array_map(
    static fn (AstNode $node): string => $node->type,
    $document->children
);

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$rawTagCases = [
    'inline container tag' => [
        "<span data-review=\"type7\">\n*raw span* source.\n</span>\n\nAfter **span**.",
        "<span data-review=\"type7\">\n*raw span* source.\n</span>",
    ],
    'inline phrasing tag' => [
        "<strong>\n**raw strong** source.\n</strong>\n\nAfter **strong**.",
        "<strong>\n**raw strong** source.\n</strong>",
    ],
    'void source tag' => [
        "<source src=\"clip.webm\" type=\"video/webm\">\n\nAfter **source**.",
        '<source src="clip.webm" type="video/webm">',
    ],
    'metadata tag' => [
        "<meta name=\"review\" content=\"raw-boundary\">\n\nAfter **meta**.",
        '<meta name="review" content="raw-boundary">',
    ],
    'closing tag line' => [
        "</span>\nlegacy close marker\n\nAfter **close**.",
        "</span>\nlegacy close marker",
    ],
];

return [
    'maps commonmark complete html tag lines to type7 raw html blocks' =>
        static function (TestRunner $t) use ($blockTypes, $inlineTypes, $rawTagCases): void {
            foreach ($rawTagCases as $name => [$markdown, $expectedRaw]) {
                $document = (new MarkdownReader(['format' => 'commonmark']))->read($markdown);
                $raw = $document->children[0] ?? new AstNode('missing');
                $after = $document->children[1] ?? new AstNode('missing');
                $blocks = (new WordPressBlockWriter())->write($document);

                $t->same(['raw_html', 'paragraph'], $blockTypes($document), $name);
                $t->same($expectedRaw, $raw->attr('html'), $name . ' raw html');
                $t->same(['text', 'strong', 'text'], $inlineTypes($after), $name . ' following markdown');
                $t->contains("<!-- wp:html -->\n" . $expectedRaw . "\n<!-- /wp:html -->", $blocks, $name . ' wordpress raw handoff');
            }
        },

    'keeps commonmark complete html tag lines from interrupting paragraphs' =>
        static function (TestRunner $t) use ($blockTypes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                'before',
                '<span data-review="inline">',
                'after',
            ]));
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same(['paragraph'], $blockTypes($document));
            $t->same(['text', 'softbreak', 'raw_html_inline', 'softbreak', 'text'], $inlineTypes($paragraph));
            $t->same('before  after', $paragraph->attr('text'));
        },

    'keeps non-commonmark standalone void inline parsing unchanged' =>
        static function (TestRunner $t) use ($blockTypes, $inlineTypes): void {
            $document = (new MarkdownReader())->read('<source src="clip.webm" type="video/webm"> keeps media visible.');
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same(['paragraph'], $blockTypes($document));
            $t->same(['raw_html_inline', 'text'], $inlineTypes($paragraph));
            $t->same('<source src="clip.webm" type="video/webm">', $paragraph->children[0]->attr('html'));
        },

    'records commonmark complete html tag boundary mapped-case count' =>
        static function (TestRunner $t) use ($rawTagCases): void {
            $t->same(5, count($rawTagCases));
        },
];
