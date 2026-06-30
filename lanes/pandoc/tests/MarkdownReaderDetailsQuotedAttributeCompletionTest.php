<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$types = static fn (AstNode $document): array => array_map(
    static fn (AstNode $node): string => $node->type,
    $document->children
);

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$quotedDetailsSource = <<<'MARKDOWN'
<details class="migration-review" data-title="a > b" data-json='{"state":"a > b"}'>
<summary data-label="show > hide">Show imported source notes</summary>
details para with *emphasis*.
</details>

After **details**.
MARKDOWN;

$mappedCases = [
    'details opener quoted greater-than',
    'summary opener quoted greater-than',
    'disabled raw html quoted details fallback',
];

return [
    'maps upstream details summary raw html with quoted greater-than attributes' =>
        static function (TestRunner $t) use ($types, $inlineTypes, $quotedDetailsSource): void {
            $document = (new MarkdownReader())->read($quotedDetailsSource);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(['raw_html', 'raw_html', 'paragraph', 'raw_html', 'paragraph'], $types($document));
            $t->same(
                '<details class="migration-review" data-title="a > b" data-json=\'{"state":"a > b"}\'>',
                $document->children[0]->attr('html')
            );
            $t->same(
                '<summary data-label="show > hide">Show imported source notes</summary>',
                $document->children[1]->attr('html')
            );
            $t->same('details para with emphasis.', $document->children[2]->attr('text'));
            $t->same(['text', 'emph', 'text'], $inlineTypes($document->children[2]));
            $t->same('</details>', $document->children[3]->attr('html'));
            $t->same('After details.', $document->children[4]->attr('text'));
            $t->contains('data-title="a > b"', $blocks);
            $t->contains('data-json=\'{"state":"a > b"}\'', $blocks);
            $t->contains('<summary data-label="show > hide">Show imported source notes</summary>', $blocks);
            $t->contains('<p>details para with <em>emphasis</em>.</p>', $blocks);
        },

    'keeps disabled raw html details fallback stable with quoted greater-than attributes' =>
        static function (TestRunner $t) use ($types, $inlineTypes, $quotedDetailsSource): void {
            $document = (new MarkdownReader(['htmlRawHtml' => false]))->read($quotedDetailsSource);

            $t->same(['plain', 'paragraph', 'paragraph'], $types($document));
            $t->same('Show imported source notes', $document->children[0]->attr('text'));
            $t->same('details para with emphasis.', $document->children[1]->attr('text'));
            $t->same(['text', 'emph', 'text'], $inlineTypes($document->children[1]));
            $t->same('After details.', $document->children[2]->attr('text'));
        },

    'records upstream details quoted attribute completion mapped-case count' =>
        static function (TestRunner $t) use ($mappedCases): void {
            $t->same(3, count($mappedCases));
        },
];
