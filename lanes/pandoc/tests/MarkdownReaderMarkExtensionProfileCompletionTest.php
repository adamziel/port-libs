<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzz-mark-extension-profile.md'
);

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

$findMark = null;
$findMark = static function (AstNode $node) use (&$findMark): AstNode {
    if ($node->type === 'span' && $node->attr('classes') === ['mark']) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findMark($child);
        if ($match->type !== 'missing') {
            return $match;
        }
    }

    return new AstNode('missing');
};

$enabledCases = [
    'commonmark plus mark' => ['format' => 'commonmark+mark'],
    'gfm extension array' => ['format' => 'gfm', 'extensions' => ['+mark']],
    'strict extension map' => ['format' => 'markdown_strict', 'extensions' => ['mark' => true]],
];

$disabledCases = [
    'default markdown' => [],
    'markdown format' => ['format' => 'markdown'],
    'pandoc alias' => ['format' => 'pandoc'],
    'commonmark x underscore' => ['format' => 'commonmark_x'],
    'commonmark x hyphen' => ['format' => 'commonmark-x'],
    'commonmark default' => ['format' => 'commonmark'],
    'gfm default' => ['format' => 'gfm'],
    'markdown strict default' => ['format' => 'markdown_strict'],
    'markdown minus mark suffix' => ['format' => 'markdown-mark'],
    'default extension map disabled' => ['extensions' => ['mark' => false]],
];

return [
    'maps upstream markdown mark extension native parity fixture' =>
        static function (TestRunner $t) use ($fixture, $findMark, $inlineText): void {
            $document = (new MarkdownReader(['format' => 'markdown+mark']))->read($fixture());
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $mark = $findMark($document);
            $native = (new NativeWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same('Before flagged claim after.', $inlineText($paragraph));
            $t->same('span', $mark->type);
            $t->same(['mark'], $mark->attr('classes'));
            $t->same('flagged claim', $inlineText($mark));
            $t->same(['text', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $mark->children));
            $t->contains('Span ( "" , [ "mark" ] , [  ] ) [ Str "flagged" , Space , Strong [ Str "claim" ] ]', $native);
        },

    'maps upstream markdown mark extension enabled profiles' =>
        static function (TestRunner $t) use ($enabledCases, $findMark, $inlineText): void {
            foreach ($enabledCases as $label => $options) {
                $document = (new MarkdownReader($options))->read('Before ==flagged **claim**== after.');
                $mark = $findMark($document);

                $t->same('span', $mark->type, $label);
                $t->same(['mark'], $mark->attr('classes'), $label . ' classes');
                $t->same('flagged claim', $inlineText($mark), $label . ' text');
                $t->same(['text', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $mark->children), $label . ' children');
            }
        },

    'maps upstream markdown mark extension disabled profiles as literal text' =>
        static function (TestRunner $t) use ($disabledCases, $findMark, $inlineText): void {
            foreach ($disabledCases as $label => $options) {
                $document = (new MarkdownReader($options))->read('Before ==flagged== after.');
                $paragraph = $document->children[0] ?? new AstNode('missing');

                $t->same('missing', $findMark($document)->type, $label);
                $t->same('Before ==flagged== after.', $inlineText($paragraph), $label . ' literal');
            }
        },

    'records upstream markdown mark extension profile mapped-case count' =>
        static function (TestRunner $t) use ($enabledCases, $disabledCases): void {
            $t->same(13, count($enabledCases) + count($disabledCases));
        },
];
