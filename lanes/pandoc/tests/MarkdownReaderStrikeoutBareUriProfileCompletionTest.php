<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$findInline = null;
$findInline = static function (AstNode $node, callable $predicate) use (&$findInline): AstNode {
    if ($predicate($node)) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findInline($child, $predicate);
        if ($match->type !== 'missing') {
            return $match;
        }
    }

    return new AstNode('missing');
};

$strikeoutEnabledCases = [
    'default markdown' => [],
    'markdown format' => ['format' => 'markdown'],
    'pandoc alias' => ['format' => 'pandoc'],
    'commonmark x underscore' => ['format' => 'commonmark_x'],
    'gfm format' => ['format' => 'gfm'],
    'commonmark plus strikeout' => ['format' => 'commonmark+strikeout'],
    'strict extension map' => ['format' => 'markdown_strict', 'extensions' => ['strikeout' => true]],
];

$strikeoutDisabledCases = [
    'commonmark default' => ['format' => 'commonmark'],
    'markdown strict default' => ['format' => 'markdown_strict'],
    'markdown php extra default' => ['format' => 'markdown_phpextra'],
    'markdown mmd default' => ['format' => 'markdown_mmd'],
    'markdown minus strikeout suffix' => ['format' => 'markdown-strikeout'],
    'default extension map disabled' => ['extensions' => ['strikeout' => false]],
];

$bareUriEnabledCases = [
    'default markdown' => [],
    'markdown format' => ['format' => 'markdown'],
    'pandoc alias' => ['format' => 'pandoc'],
    'commonmark x underscore' => ['format' => 'commonmark_x'],
    'gfm format' => ['format' => 'gfm'],
    'commonmark plus bare uri' => ['format' => 'commonmark+bare_uri_autolinks'],
    'commonmark plus pandoc autolink bare uri' => ['format' => 'commonmark+autolink_bare_uris'],
    'strict extension map' => ['format' => 'markdown_strict', 'extensions' => ['bare_uri_autolinks' => true]],
    'strict pandoc extension map' => ['format' => 'markdown_strict', 'extensions' => ['autolink_bare_uris' => true]],
];

$bareUriDisabledCases = [
    'commonmark default' => ['format' => 'commonmark'],
    'markdown strict default' => ['format' => 'markdown_strict'],
    'markdown php extra default' => ['format' => 'markdown_phpextra'],
    'markdown mmd default' => ['format' => 'markdown_mmd'],
    'gfm minus bare uri suffix' => ['format' => 'gfm-bare_uri_autolinks'],
    'gfm minus pandoc autolink bare uri suffix' => ['format' => 'gfm-autolink_bare_uris'],
    'default extension map disabled' => ['extensions' => ['bare_uri_autolinks' => false]],
    'default pandoc extension map disabled' => ['extensions' => ['autolink_bare_uris' => false]],
];

return [
    'maps upstream markdown strikeout extension enabled profiles' =>
        static function (TestRunner $t) use ($strikeoutEnabledCases, $findInline, $inlineText): void {
            foreach ($strikeoutEnabledCases as $label => $options) {
                $document = (new MarkdownReader($options))->read('Before ~~gone~~ after.');
                $strikeout = $findInline($document, static fn (AstNode $node): bool => $node->type === 'strikeout');

                $t->same('strikeout', $strikeout->type, $label);
                $t->same('gone', $inlineText($strikeout), $label . ' text');
            }
        },

    'maps upstream markdown strikeout extension disabled profiles as literal text' =>
        static function (TestRunner $t) use ($strikeoutDisabledCases, $findInline, $inlineText): void {
            foreach ($strikeoutDisabledCases as $label => $options) {
                $document = (new MarkdownReader($options))->read('Before ~~gone~~ after.');
                $paragraph = $document->children[0] ?? new AstNode('missing');

                $t->same('missing', $findInline($document, static fn (AstNode $node): bool => $node->type === 'strikeout')->type, $label);
                $t->same('Before ~~gone~~ after.', $inlineText($paragraph), $label . ' literal');
            }
        },

    'maps upstream markdown bare uri extension enabled profiles' =>
        static function (TestRunner $t) use ($bareUriEnabledCases, $findInline, $inlineText): void {
            foreach ($bareUriEnabledCases as $label => $options) {
                $document = (new MarkdownReader($options))->read('Visit www.example.test/docs now.');
                $link = $findInline($document, static fn (AstNode $node): bool => $node->type === 'link' && $node->attr('classes') === ['uri']);

                $t->same('link', $link->type, $label);
                $t->same('http://www.example.test/docs', $link->attr('url'), $label . ' url');
                $t->same('www.example.test/docs', $inlineText($link), $label . ' text');
            }
        },

    'maps upstream markdown bare uri extension disabled profiles as literal text' =>
        static function (TestRunner $t) use ($bareUriDisabledCases, $findInline, $inlineText): void {
            foreach ($bareUriDisabledCases as $label => $options) {
                $document = (new MarkdownReader($options))->read('Visit www.example.test/docs now.');
                $paragraph = $document->children[0] ?? new AstNode('missing');

                $t->same('missing', $findInline($document, static fn (AstNode $node): bool => $node->type === 'link' && $node->attr('classes') === ['uri'])->type, $label);
                $t->same('Visit www.example.test/docs now.', $inlineText($paragraph), $label . ' literal');
            }
        },

    'records upstream markdown strikeout bare uri profile mapped-case count' =>
        static function (TestRunner $t) use ($strikeoutEnabledCases, $strikeoutDisabledCases, $bareUriEnabledCases, $bareUriDisabledCases): void {
            $t->same(
                30,
                count($strikeoutEnabledCases)
                + count($strikeoutDisabledCases)
                + count($bareUriEnabledCases)
                + count($bareUriDisabledCases)
            );
        },
];
