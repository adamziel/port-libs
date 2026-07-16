<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzz-startnum-disabled-profile.md'
);

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'space' || $node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
};

return [
    'maps upstream markdown startnum disabled fixture to normalized ordered list starts' =>
        static function (TestRunner $t) use ($fixture, $inlineText): void {
            $document = (new MarkdownReader(['format' => 'markdown-startnum+fancy_lists']))->read($fixture());

            $decimal = $document->children[0] ?? new AstNode('missing');
            $alpha = $document->children[1] ?? new AstNode('missing');

            $t->same(['ordered_list', 'ordered_list'], array_map(
                static fn (AstNode $node): string => $node->type,
                $document->children
            ));
            $t->same(1, $decimal->attr('start'));
            $t->same('decimal', $decimal->attr('style'));
            $t->same('period', $decimal->attr('delimiter'));
            $t->same(['four', 'five'], array_map($inlineText, $decimal->children));
            $t->same(1, $alpha->attr('start'));
            $t->same('upper_alpha', $alpha->attr('style'));
            $t->same('period', $alpha->attr('delimiter'));
            $t->same(['beta', 'gamma'], array_map($inlineText, $alpha->children));
        },

    'preserves ordered list source start numbers when startnum is enabled' =>
        static function (TestRunner $t): void {
            $document = (new MarkdownReader(['format' => 'markdown+fancy_lists']))->read("4. four\n5. five\n\nB.  beta\nE.  gamma\n");

            $decimal = $document->children[0] ?? new AstNode('missing');
            $alpha = $document->children[1] ?? new AstNode('missing');

            $t->same(4, $decimal->attr('start'));
            $t->same(2, $alpha->attr('start'));
        },

    'maps non-pandoc markdown profiles with startnum disabled by default' =>
        static function (TestRunner $t): void {
            foreach (['markdown_strict', 'markdown_mmd', 'markdown_phpextra'] as $format) {
                $document = (new MarkdownReader(['format' => $format . '+fancy_lists']))->read("4. four\n\nB.  beta\nE.  gamma\n");

                $t->same(1, ($document->children[0] ?? new AstNode('missing'))->attr('start'), $format . ' decimal');
                $t->same(1, ($document->children[1] ?? new AstNode('missing'))->attr('start'), $format . ' alpha');
            }
        },
];
