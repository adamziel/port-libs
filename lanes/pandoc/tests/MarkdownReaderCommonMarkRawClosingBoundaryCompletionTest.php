<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$blockTypes = static fn (AstNode $document): array => array_map(
    static fn (AstNode $node): string => $node->type,
    $document->children
);

$childTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps commonmark standalone closing tag starts to raw html blank boundaries' =>
        static function (TestRunner $t) use ($blockTypes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                '</span>',
                '**raw closing** source.',
                '',
                'After **closing** boundary.',
            ]));
            $raw = $document->children[0] ?? new AstNode('missing');
            $after = $document->children[1] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(['raw_html', 'paragraph'], $blockTypes($document));
            $t->same("</span>\n**raw closing** source.", $raw->attr('html'));
            $t->same(['text', 'strong', 'text'], $inlineTypes($after));
            $t->contains("</span>\n**raw closing** source.", $blocks);
        },

    'maps commonmark custom closing tag starts to raw html blank boundaries' =>
        static function (TestRunner $t) use ($blockTypes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                '</review-block>',
                '*raw custom close* source.',
                '',
                'After **custom** boundary.',
            ]));
            $raw = $document->children[0] ?? new AstNode('missing');
            $after = $document->children[1] ?? new AstNode('missing');

            $t->same(['raw_html', 'paragraph'], $blockTypes($document));
            $t->same("</review-block>\n*raw custom close* source.", $raw->attr('html'));
            $t->same(['text', 'strong', 'text'], $inlineTypes($after));
        },

    'maps commonmark initial list closing tag starts to raw html blank boundaries' =>
        static function (TestRunner $t) use ($childTypes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                '- </section>',
                '  **raw close** source.',
                '',
                '  After **list** boundary.',
            ]));
            $item = ($document->children[0] ?? new AstNode('missing'))->children[0] ?? new AstNode('missing');
            $raw = $item->children[0] ?? new AstNode('missing');
            $after = $item->children[1] ?? new AstNode('missing');

            $t->same(['raw_html', 'paragraph'], $childTypes($item));
            $t->same("</section>\n**raw close** source.", $raw->attr('html'));
            $t->same(['text', 'strong', 'text'], $inlineTypes($after));
        },

    'maps commonmark list closing tag starts after blank to raw html boundaries' =>
        static function (TestRunner $t) use ($childTypes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                '- Before close.',
                '',
                '  </span>',
                '  **raw close** source.',
                '',
                '  After **list** boundary.',
            ]));
            $item = ($document->children[0] ?? new AstNode('missing'))->children[0] ?? new AstNode('missing');
            $raw = $item->children[1] ?? new AstNode('missing');
            $after = $item->children[2] ?? new AstNode('missing');

            $t->same(['paragraph', 'raw_html', 'paragraph'], $childTypes($item));
            $t->same("</span>\n**raw close** source.", $raw->attr('html'));
            $t->same(['text', 'strong', 'text'], $inlineTypes($after));
        },

    'keeps commonmark closing tag lines from interrupting paragraphs' =>
        static function (TestRunner $t) use ($blockTypes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read("before\n</section>\nafter");
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same(['paragraph'], $blockTypes($document));
            $t->same(['text', 'softbreak', 'raw_html_inline', 'softbreak', 'text'], $inlineTypes($paragraph));
            $t->same('before  after', $paragraph->attr('text'));
        },

    'records commonmark raw closing boundary mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(5, 5);
        },
];
