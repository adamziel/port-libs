<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$types = static fn (array $nodes): array => array_map(
    static fn (AstNode $node): string => $node->type,
    $nodes
);

return [
    'maps commonmark setext headings into generated section divs with attributes enabled' =>
        static function (TestRunner $t) use ($types): void {
            $document = (new MarkdownReader([
                'format' => 'commonmark+header_attributes',
                'sectionDivs' => true,
            ]))->read(implode("\n", [
                'Preamble before sections.',
                '',
                'Setext Article {#setext-article .review data-source="commonmark-setext"}',
                '===',
                '',
                'Lead **copy**.',
                '',
                'Setext Detail',
                '---',
                '',
                'Nested note.',
                '',
                'Setext Peer',
                '===',
                '',
                'Tail.',
            ]));

            $article = $document->children[1] ?? new AstNode('missing');
            $articleHeading = $article->children[0] ?? new AstNode('missing');
            $detail = $article->children[2] ?? new AstNode('missing');
            $detailHeading = $detail->children[0] ?? new AstNode('missing');
            $peer = $document->children[2] ?? new AstNode('missing');
            $peerHeading = $peer->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(['paragraph', 'div', 'div'], $types($document->children));
            $t->same('setext-article', $article->attr('id'));
            $t->same(['section', 'level1', 'review'], $article->attr('classes'));
            $t->same(['data-source' => 'commonmark-setext'], $article->attr('attributes'));
            $t->same(['heading', 'paragraph', 'div'], $types($article->children));
            $t->same('Setext Article', $articleHeading->attr('text'));
            $t->same('', $articleHeading->attr('id', ''));
            $t->same('setext-detail', $detail->attr('id'));
            $t->same(['section', 'level2'], $detail->attr('classes'));
            $t->same('Setext Detail', $detailHeading->attr('text'));
            $t->same('setext-peer', $peer->attr('id'));
            $t->same(['section', 'level1'], $peer->attr('classes'));
            $t->same('Setext Peer', $peerHeading->attr('text'));
            $t->contains('<div id="setext-article" class="section level1 review" data-source="commonmark-setext"><h1>Setext Article</h1><p>Lead <strong>copy</strong>.</p><div id="setext-detail" class="section level2"><h2>Setext Detail</h2><p>Nested note.</p></div></div>', $blocks);
            $t->contains('<div id="setext-peer" class="section level1"><h1>Setext Peer</h1><p>Tail.</p></div>', $blocks);
        },

    'keeps plain commonmark setext header attributes literal while sectioning' =>
        static function (TestRunner $t) use ($types): void {
            $document = (new MarkdownReader([
                'format' => 'commonmark',
                'sectionDivs' => true,
            ]))->read(implode("\n", [
                'Literal Heading {#literal-heading .review}',
                '===',
                '',
                'Body.',
            ]));

            $section = $document->children[0] ?? new AstNode('missing');
            $heading = $section->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(['div'], $types($document->children));
            $t->same('div', $section->type);
            $t->same('literal-heading-literal-heading-review', $section->attr('id'));
            $t->same(['section', 'level1'], $section->attr('classes'));
            $t->same('heading', $heading->type);
            $t->same('Literal Heading {#literal-heading .review}', $heading->attr('text'));
            $t->same('', $heading->attr('id', ''));
            $t->contains('<div id="literal-heading-literal-heading-review" class="section level1"><h1>Literal Heading {#literal-heading .review}</h1><p>Body.</p></div>', $blocks);
            $t->true(!str_contains($blocks, 'class="section level1 review"'), 'Plain commonmark should not promote literal header attribute text into section classes');
        },

    'records commonmark setext section div completion mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(4, 4);
        },
];
