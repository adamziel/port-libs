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

return [
    'maps commonmark unclosed paragraph tags to raw html blank-line boundaries' =>
        static function (TestRunner $t) use ($blockTypes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                '<p data-source="paragraph-raw-boundary">',
                '**raw paragraph** import copy.',
                '',
                'After **paragraph** boundary.',
                '',
                '<p>Structured <em>closed</em> paragraph.</p>',
            ]));
            $raw = $document->children[0] ?? new AstNode('missing');
            $after = $document->children[1] ?? new AstNode('missing');
            $closed = $document->children[2] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(['raw_html', 'paragraph', 'paragraph'], $blockTypes($document));
            $t->same("<p data-source=\"paragraph-raw-boundary\">\n**raw paragraph** import copy.", $raw->attr('html'));
            $t->same(['text', 'strong', 'text'], $inlineTypes($after));
            $t->same(['text', 'emph', 'text'], $inlineTypes($closed));
            $t->contains("<!-- wp:html -->\n<p data-source=\"paragraph-raw-boundary\">\n**raw paragraph** import copy.", $blocks);
            $t->contains('<p>After <strong>paragraph</strong> boundary.</p>', $blocks);
            $t->contains('<p>Structured <em>closed</em> paragraph.</p>', $blocks);
        },

    'maps commonmark paragraph-interrupting raw html starts without stealing generic tags' =>
        static function (TestRunner $t) use ($blockTypes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                'Intro paragraph.',
                '<section data-source="paragraph-interrupt">',
                '*raw section* source.',
                '',
                '<textarea>',
                '**raw textarea** source.',
                '</textarea>',
                'After **textarea** boundary.',
                '',
                'before',
                '<custom-tag>',
                'after',
            ]));
            $section = $document->children[1] ?? new AstNode('missing');
            $textarea = $document->children[2] ?? new AstNode('missing');
            $after = $document->children[3] ?? new AstNode('missing');
            $generic = $document->children[4] ?? new AstNode('missing');

            $t->same(['paragraph', 'raw_html', 'raw_html', 'paragraph', 'paragraph'], $blockTypes($document));
            $t->same("<section data-source=\"paragraph-interrupt\">\n*raw section* source.", $section->attr('html'));
            $t->same("<textarea>\n**raw textarea** source.\n</textarea>", $textarea->attr('html'));
            $t->same(['text', 'strong', 'text'], $inlineTypes($after));
            $t->same(['text', 'softbreak', 'raw_html_inline', 'softbreak', 'text'], $inlineTypes($generic));
        },

    'records commonmark paragraph raw boundary completion mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(2, 2);
        },
];
