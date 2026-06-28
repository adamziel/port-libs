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

$findEmoji = null;
$findEmoji = static function (AstNode $node) use (&$findEmoji): AstNode {
    if ($node->type === 'span' && $node->attr('classes') === ['emoji']) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findEmoji($child);
        if ($match->type !== 'missing') {
            return $match;
        }
    }

    return new AstNode('missing');
};

$enabledCases = [
    'default markdown' => [],
    'markdown format' => ['format' => 'markdown'],
    'pandoc alias' => ['format' => 'pandoc'],
    'commonmark x underscore' => ['format' => 'commonmark_x'],
    'commonmark x hyphen' => ['format' => 'commonmark-x'],
    'gfm format' => ['format' => 'gfm'],
    'github markdown alias' => ['format' => 'markdown_github'],
    'commonmark plus emoji' => ['format' => 'commonmark+emoji'],
    'commonmark extension array' => ['format' => 'commonmark', 'extensions' => ['+emoji']],
    'strict extension map' => ['format' => 'markdown_strict', 'extensions' => ['emoji' => true]],
];

$disabledCases = [
    'commonmark default' => ['format' => 'commonmark'],
    'markdown strict default' => ['format' => 'markdown_strict'],
    'markdown php extra default' => ['format' => 'markdown_phpextra'],
    'markdown mmd default' => ['format' => 'markdown_mmd'],
    'markdown minus emoji suffix' => ['format' => 'markdown-emoji'],
    'default extension map disabled' => ['extensions' => ['emoji' => false]],
];

return [
    'maps upstream markdown emoji extension enabled profiles' =>
        static function (TestRunner $t) use ($enabledCases, $findEmoji, $inlineText): void {
            foreach ($enabledCases as $label => $options) {
                $document = (new MarkdownReader($options))->read('Before :rocket: launch after.');
                $emoji = $findEmoji($document);

                $t->same('span', $emoji->type, $label);
                $t->same(['emoji'], $emoji->attr('classes'), $label . ' classes');
                $t->same(['data-emoji' => 'rocket'], $emoji->attr('attributes'), $label . ' attrs');
                $t->same("\u{1F680}", $inlineText($emoji), $label . ' text');
            }
        },

    'maps upstream markdown emoji extension disabled profiles as literal text' =>
        static function (TestRunner $t) use ($disabledCases, $findEmoji, $inlineText): void {
            foreach ($disabledCases as $label => $options) {
                $document = (new MarkdownReader($options))->read('Before :rocket: launch after.');
                $paragraph = $document->children[0] ?? new AstNode('missing');

                $t->same('missing', $findEmoji($document)->type, $label);
                $t->same('Before :rocket: launch after.', $inlineText($paragraph), $label . ' literal');
            }
        },

    'records upstream markdown emoji extension profile mapped-case count' =>
        static function (TestRunner $t) use ($enabledCases, $disabledCases): void {
            $t->same(16, count($enabledCases) + count($disabledCases));
        },
];
