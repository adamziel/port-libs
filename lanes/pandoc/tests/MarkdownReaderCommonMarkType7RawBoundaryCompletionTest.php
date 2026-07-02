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
    'maps commonmark complete generic tag lines to raw html blank boundaries' =>
        static function (TestRunner $t) use ($blockTypes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                '<span data-review="type-7">',
                '**raw span** source.',
                '',
                'After **span** boundary.',
            ]));
            $raw = $document->children[0] ?? new AstNode('missing');
            $after = $document->children[1] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(['raw_html', 'paragraph'], $blockTypes($document));
            $t->same("<span data-review=\"type-7\">\n**raw span** source.", $raw->attr('html'));
            $t->same(['text', 'strong', 'text'], $inlineTypes($after));
            $t->contains("<span data-review=\"type-7\">\n**raw span** source.", $blocks);
            $t->contains('<strong>span</strong>', $blocks);
        },

    'maps commonmark initial list complete generic tag lines to raw html boundaries' =>
        static function (TestRunner $t) use ($childTypes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                '- <del cite="/changes/1">',
                '  **raw deletion** source.',
                '',
                '  After **deletion** boundary.',
            ]));
            $item = ($document->children[0] ?? new AstNode('missing'))->children[0] ?? new AstNode('missing');
            $raw = $item->children[0] ?? new AstNode('missing');
            $after = $item->children[1] ?? new AstNode('missing');

            $t->same(['raw_html', 'paragraph'], $childTypes($item));
            $t->same("<del cite=\"/changes/1\">\n**raw deletion** source.", $raw->attr('html'));
            $t->same(['text', 'strong', 'text'], $inlineTypes($after));
        },

    'keeps commonmark complete generic tag lines from interrupting paragraphs' =>
        static function (TestRunner $t) use ($blockTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read("before\n<span>\nafter");
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same(['paragraph'], $blockTypes($document));
            $t->same('before <span> after', $paragraph->attr('text'));
        },

    'keeps malformed commonmark complete generic tag starts as markdown paragraphs' =>
        static function (TestRunner $t) use ($blockTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                '<span title="unterminated>',
                '**not raw** source.',
            ]));
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same(['paragraph'], $blockTypes($document));
            $t->same('<span title=“unterminated> not raw source.', $paragraph->attr('text'));
        },

    'records commonmark type 7 raw boundary mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(4, 4);
        },
];
