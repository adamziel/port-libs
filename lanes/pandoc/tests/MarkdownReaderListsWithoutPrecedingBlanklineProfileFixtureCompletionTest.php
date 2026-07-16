<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static fn (): string => file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-z-lists-without-preceding-blankline-profile.md'
) ?: '';

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

return [
    'maps pandoc markdown lists-without-preceding-blankline profile fixture' =>
        static function (TestRunner $t) use ($fixture, $inlineText): void {
            $document = (new MarkdownReader(['format' => 'markdown+lists_without_preceding_blankline']))->read($fixture());

            $t->same(
                ['paragraph', 'bullet_list', 'paragraph', 'ordered_list'],
                array_map(static fn (AstNode $node): string => $node->type, $document->children)
            );
            $t->same('Lead', trim($inlineText($document->children[0])));
            $t->same('dash item', trim($inlineText($document->children[1])));
            $t->same('Lead ordered', trim($inlineText($document->children[2])));
            $t->same('decimal item', trim($inlineText($document->children[3])));
            $t->same(2, $document->children[3]->attr('start'));
            $t->same('decimal', $document->children[3]->attr('style'));
            $t->same('period', $document->children[3]->attr('delimiter'));
        },

    'keeps pandoc markdown lists-without-preceding-blankline fixture literal by default' =>
        static function (TestRunner $t) use ($fixture, $inlineText): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture());

            $t->same(['paragraph', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
            $t->same('Lead - dash item', trim($inlineText($document->children[0])));
            $t->same('Lead ordered 2. decimal item', trim($inlineText($document->children[1])));
        },
];
