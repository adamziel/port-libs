<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$nodeTypes = static fn (AstNode $document): array =>
    array_map(static fn (AstNode $node): string => $node->type, $document->children);

$inlineText = null;
$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return trim(preg_replace('/[ \t]+/', ' ', $text) ?? $text);
};

$headingInterruptProfiles = [
    'markdown opt out' => ['format' => 'markdown-blank_before_header'],
    'pandoc alias opt out' => ['format' => 'pandoc-blank_before_header'],
    'commonmark' => ['format' => 'commonmark'],
    'commonmark x' => ['format' => 'commonmark_x'],
    'gfm' => ['format' => 'gfm'],
    'strict' => ['format' => 'markdown_strict'],
    'multimarkdown' => ['format' => 'markdown_mmd'],
    'php extra' => ['format' => 'markdown_phpextra'],
];

$blockQuoteInterruptProfiles = [
    'markdown opt out' => ['format' => 'markdown-blank_before_blockquote'],
    'pandoc alias opt out' => ['format' => 'pandoc-blank_before_blockquote'],
    'commonmark' => ['format' => 'commonmark'],
    'commonmark x' => ['format' => 'commonmark_x'],
    'gfm' => ['format' => 'gfm'],
    'strict' => ['format' => 'markdown_strict'],
    'multimarkdown' => ['format' => 'markdown_mmd'],
    'php extra' => ['format' => 'markdown_phpextra'],
];

return [
    'keeps pandoc markdown blank-before-header default from interrupting paragraphs or implicit refs' =>
        static function (TestRunner $t) use ($inlineText, $nodeTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read("Lead\n# Review Heading\n\n[Review Heading]\n");
            $first = $document->children[0] ?? new AstNode('missing');
            $second = $document->children[1] ?? new AstNode('missing');

            $t->same(['paragraph', 'paragraph'], $nodeTypes($document));
            $t->same('Lead # Review Heading', $inlineText($first));
            $t->same('text', ($second->children[0] ?? new AstNode('missing'))->type);
            $t->same('[Review Heading]', $inlineText($second));
        },

    'maps pandoc 3.10 blank-before-header disabled profiles as paragraph interrupting' =>
        static function (TestRunner $t) use ($headingInterruptProfiles, $inlineText, $nodeTypes): void {
            foreach ($headingInterruptProfiles as $name => $options) {
                $document = (new MarkdownReader($options))->read("Lead\n# Review Heading\n");
                $heading = $document->children[1] ?? new AstNode('missing');

                $t->same(['paragraph', 'heading'], $nodeTypes($document), $name);
                $t->same('Lead', $inlineText($document->children[0] ?? new AstNode('missing')), $name);
                $t->same(1, $heading->attr('level'), $name);
                $t->same('Review Heading', $inlineText($heading), $name);
            }
        },

    'keeps pandoc markdown blank-before-blockquote default from interrupting paragraphs' =>
        static function (TestRunner $t) use ($inlineText, $nodeTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read("Lead\n> Review quote\n");

            $t->same(['paragraph'], $nodeTypes($document));
            $t->same('Lead > Review quote', $inlineText($document->children[0] ?? new AstNode('missing')));
        },

    'maps pandoc 3.10 blank-before-blockquote disabled profiles as paragraph interrupting' =>
        static function (TestRunner $t) use ($blockQuoteInterruptProfiles, $inlineText, $nodeTypes): void {
            foreach ($blockQuoteInterruptProfiles as $name => $options) {
                $document = (new MarkdownReader($options))->read("Lead\n> Review quote\n");
                $quote = $document->children[1] ?? new AstNode('missing');

                $t->same(['paragraph', 'blockquote'], $nodeTypes($document), $name);
                $t->same('Lead', $inlineText($document->children[0] ?? new AstNode('missing')), $name);
                $t->same('Review quote', $inlineText($quote), $name);
            }
        },

    'applies pandoc markdown blank-before gates inside block quotes' =>
        static function (TestRunner $t) use ($inlineText): void {
            $headingDefault = (new MarkdownReader(['format' => 'markdown']))->read("> Lead\n> # Review Heading\n");
            $headingOptOut = (new MarkdownReader(['format' => 'markdown-blank_before_header']))->read("> Lead\n> # Review Heading\n");
            $quoteDefault = (new MarkdownReader(['format' => 'markdown']))->read("> Lead\n> > Nested quote\n");
            $quoteOptOut = (new MarkdownReader(['format' => 'markdown-blank_before_blockquote']))->read("> Lead\n> > Nested quote\n");

            $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, ($headingDefault->children[0] ?? new AstNode('missing'))->children));
            $t->same('Lead # Review Heading', $inlineText($headingDefault->children[0] ?? new AstNode('missing')));
            $t->same(['paragraph', 'heading'], array_map(static fn (AstNode $node): string => $node->type, ($headingOptOut->children[0] ?? new AstNode('missing'))->children));
            $t->same('Review Heading', $inlineText(($headingOptOut->children[0] ?? new AstNode('missing'))->children[1] ?? new AstNode('missing')));

            $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, ($quoteDefault->children[0] ?? new AstNode('missing'))->children));
            $t->same('Lead > Nested quote', $inlineText($quoteDefault->children[0] ?? new AstNode('missing')));
            $t->same(['paragraph', 'blockquote'], array_map(static fn (AstNode $node): string => $node->type, ($quoteOptOut->children[0] ?? new AstNode('missing'))->children));
            $t->same('Nested quote', $inlineText(($quoteOptOut->children[0] ?? new AstNode('missing'))->children[1] ?? new AstNode('missing')));
        },

    'records pandoc 3.10 blank-before block boundary mapped-case count' =>
        static function (TestRunner $t) use ($headingInterruptProfiles, $blockQuoteInterruptProfiles): void {
            $t->same(22, 1 + count($headingInterruptProfiles) + 1 + count($blockQuoteInterruptProfiles) + 4);
        },
];
