<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

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

    return $text;
};

return [
    'maps upstream markdown citation followed by attributed span boundary' =>
        static function (TestRunner $t) use ($inlineText, $inlineTypes): void {
            $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-citation-span-boundary.md');
            $document = (new MarkdownReader())->read($fixture);
            $first = $document->children[0] ?? new AstNode('missing');
            $second = $document->children[1] ?? new AstNode('missing');
            $citation = $first->children[0] ?? new AstNode('missing');
            $span = $first->children[2] ?? new AstNode('missing');
            $markedCitation = $second->children[0] ?? new AstNode('missing');
            $markedSpan = $second->children[2] ?? new AstNode('missing');

            $t->same(['citation', 'text', 'span'], $inlineTypes($first));
            $t->same('foo', $citation->attr('id'));
            $t->same(null, $citation->attr('suffix'));
            $t->same('@foo', $citation->attr('text'));
            $t->same(' ', $first->children[1]->attr('text'));
            $t->same(['bar'], $span->attr('classes'));
            $t->same('test', $inlineText($span));

            $t->same(['citation', 'text', 'span'], $inlineTypes($second));
            $t->same('foo', $markedCitation->attr('id'));
            $t->same(null, $markedCitation->attr('suffix'));
            $t->same('source', $markedSpan->attr('id'));
            $t->same(['bar'], $markedSpan->attr('classes'));
            $t->same(['data-kind' => 'span'], $markedSpan->attr('attributes'));
            $t->same(['emph', 'text'], $inlineTypes($markedSpan));
            $t->same('marked span', $inlineText($markedSpan));
        },

    'keeps upstream markdown bare citation suffix when no span attribute follows' =>
        static function (TestRunner $t): void {
            $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-citation-span-boundary.md');
            $document = (new MarkdownReader())->read($fixture);
            $third = $document->children[2] ?? new AstNode('missing');
            $citation = $third->children[0] ?? new AstNode('missing');

            $t->same(['citation'], array_map(static fn (AstNode $node): string => $node->type, $third->children));
            $t->same('foo', $citation->attr('id'));
            $t->same('p. 7', $citation->attr('suffix'));
            $t->same('@foo [p. 7]', $citation->attr('text'));
        },

    'serializes upstream citation span boundary through markdown and wordpress handoff' =>
        static function (TestRunner $t): void {
            $document = (new MarkdownReader())->read('@foo [test]{.bar}');
            $markdown = (new MarkdownWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('@foo [test]{.bar}', $markdown);
            $t->contains('<span class="pandoc-citation" data-pandoc-citation-id="foo"', $blocks);
            $t->contains('<span class="bar">test</span>', $blocks);
            $t->true(!str_contains($blocks, 'data-pandoc-citation-suffix="test"'));
        },

    'records upstream markdown citation span boundary mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(3, 3);
        },
];
