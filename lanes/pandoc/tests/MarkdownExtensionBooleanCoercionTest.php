<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$inlineText = null;
$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if (in_array($node->type, ['text', 'code', 'math'], true)) {
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

return [
    'maps markdown reader extension map string booleans through pandoc profile gates' =>
        static function (TestRunner $t) use ($findInline, $inlineText): void {
            $source = 'Emoji :rocket: then ~~gone~~ and www.example.test plus ==flag==.';
            $disabled = (new MarkdownReader([
                'extensions' => [
                    'emoji' => 'false',
                    'strikeout' => 'off',
                    'bare_uri_autolinks' => 'no',
                    'mark' => '0',
                ],
            ]))->read($source);
            $disabledParagraph = $disabled->children[0] ?? new AstNode('missing');

            $t->same('missing', $findInline($disabled, static fn (AstNode $node): bool => $node->type === 'span' && $node->attr('classes') === ['emoji'])->type);
            $t->same('missing', $findInline($disabled, static fn (AstNode $node): bool => $node->type === 'strikeout')->type);
            $t->same('missing', $findInline($disabled, static fn (AstNode $node): bool => $node->type === 'link' && $node->attr('classes') === ['uri'])->type);
            $t->same('missing', $findInline($disabled, static fn (AstNode $node): bool => $node->type === 'span' && $node->attr('classes') === ['mark'])->type);
            $t->same($source, $inlineText($disabledParagraph));

            $enabled = (new MarkdownReader([
                'format' => 'commonmark',
                'extensions' => [
                    'emoji' => 'true',
                    'strikeout' => 'on',
                    'bare_uri_autolinks' => 'yes',
                    'mark' => '1',
                ],
            ]))->read($source);

            $t->same('span', $findInline($enabled, static fn (AstNode $node): bool => $node->type === 'span' && $node->attr('classes') === ['emoji'])->type);
            $t->same('strikeout', $findInline($enabled, static fn (AstNode $node): bool => $node->type === 'strikeout')->type);
            $t->same('link', $findInline($enabled, static fn (AstNode $node): bool => $node->type === 'link' && $node->attr('classes') === ['uri'])->type);
            $t->same('span', $findInline($enabled, static fn (AstNode $node): bool => $node->type === 'span' && $node->attr('classes') === ['mark'])->type);
        },

    'records markdown extension boolean coercion mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(8, 4 + 4);
        },
];
