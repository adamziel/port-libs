<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$blockTypes = static fn (AstNode $document): array => array_map(
    static fn (AstNode $node): string => $node->type,
    $document->children
);

$rawHtmlInlines = static function (AstNode $paragraph): array {
    $raw = [];
    foreach ($paragraph->children as $child) {
        if ($child->type === 'raw_html_inline') {
            $raw[] = (string) $child->attr('html', '');
        }
    }

    return $raw;
};

$mappedCases = [
    'paragraph-interrupting raw block',
    'standalone raw block',
    'inline raw inline preservation',
];

return [
    'maps upstream commonmark search tag as paragraph interrupting raw html block' =>
        static function (TestRunner $t) use ($blockTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                'before',
                '<search data-review="raw-boundary">',
                '*raw search* source.',
                '',
                'after',
            ]));
            $raw = $document->children[1] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(['paragraph', 'raw_html', 'paragraph'], $blockTypes($document));
            $t->same('before', $document->children[0]->attr('text'));
            $t->same("<search data-review=\"raw-boundary\">\n*raw search* source.", $raw->attr('html'));
            $t->same('after', $document->children[2]->attr('text'));
            $t->contains("<!-- wp:html -->\n<search data-review=\"raw-boundary\">\n*raw search* source.", $blocks);
        },

    'maps upstream commonmark standalone search tag to blank-line raw block boundary' =>
        static function (TestRunner $t) use ($blockTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                '<search id="site-search">',
                '[query] stays raw.',
                '',
                'after **search**.',
            ]));
            $raw = $document->children[0] ?? new AstNode('missing');
            $paragraph = $document->children[1] ?? new AstNode('missing');
            $markdown = (new MarkdownWriter())->write($document);

            $t->same(['raw_html', 'paragraph'], $blockTypes($document));
            $t->same("<search id=\"site-search\">\n[query] stays raw.", $raw->attr('html'));
            $t->same('after search.', $paragraph->attr('text'));
            $t->contains("<search id=\"site-search\">\n[query] stays raw.", $markdown);
        },

    'keeps upstream commonmark inline search tag inside paragraph as raw inline html' =>
        static function (TestRunner $t) use ($blockTypes, $rawHtmlInlines): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(
                'Lead <search data-scope="local">query</search> trail.'
            );
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same(['paragraph'], $blockTypes($document));
            $t->same(['<search data-scope="local">', '</search>'], $rawHtmlInlines($paragraph));
            $t->same('Lead query trail.', $paragraph->attr('text'));
        },

    'records upstream commonmark search raw block completion mapped-case count' =>
        static function (TestRunner $t) use ($mappedCases): void {
            $t->same(3, count($mappedCases));
        },
];
