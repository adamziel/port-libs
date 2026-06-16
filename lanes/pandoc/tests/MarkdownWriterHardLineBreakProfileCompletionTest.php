<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$linebreak = static fn (): AstNode => new AstNode('linebreak');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$listItem = static fn (array $children): AstNode => new AstNode('list_item', [], $children);
$bulletList = static fn (array $children): AstNode => new AstNode('bullet_list', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$writeDocument = static fn (AstNode $node, array $options = []): string => (new MarkdownWriter($options))->write($node);
$readDocument = static fn (string $markdown, string $format): AstNode => (new MarkdownReader(['format' => $format]))->read($markdown);

$hardLineBreakFormats = [
    'pandoc markdown' => 'markdown+hard_line_breaks',
    'commonmark' => 'commonmark+hard_line_breaks',
    'gfm' => 'gfm+hard_line_breaks',
];

$tests = [];

foreach ($hardLineBreakFormats as $label => $format) {
    $tests["maps upstream markdown writer hard line break profile {$label} paragraph"] =
        static function (TestRunner $t) use ($document, $format, $linebreak, $paragraph, $readDocument, $text, $writeDocument): void {
            $markdown = $writeDocument($document([
                $paragraph([$text('Reviewer'), $linebreak(), $text('packet')]),
            ]), ['format' => $format]);
            $roundTrip = $readDocument($markdown, $format);

            $t->same("Reviewer\npacket", $markdown);
            $t->same(['text', 'linebreak', 'text'], array_map(
                static fn (AstNode $node): string => $node->type,
                $roundTrip->children[0]->children
            ));
            $t->same("Reviewer\npacket", $roundTrip->children[0]->attr('text'));
        };

    $tests["maps upstream markdown writer hard line break profile {$label} softbreak spacing"] =
        static function (TestRunner $t) use ($document, $format, $paragraph, $readDocument, $softbreak, $text, $writeDocument): void {
            $markdown = $writeDocument($document([
                $paragraph([$text('Reviewer'), $softbreak(), $text('packet')]),
            ]), ['format' => $format]);
            $roundTrip = $readDocument($markdown, $format);

            $t->same('Reviewer packet', $markdown);
            $t->same(['text'], array_map(
                static fn (AstNode $node): string => $node->type,
                $roundTrip->children[0]->children
            ));
            $t->same('Reviewer packet', $roundTrip->children[0]->attr('text'));
        };
}

$tests['maps upstream markdown writer hard line break profile blockquote continuation'] =
    static function (TestRunner $t) use ($blockquote, $document, $linebreak, $paragraph, $readDocument, $text, $writeDocument): void {
        $format = 'markdown+hard_line_breaks';
        $markdown = $writeDocument($document([
            $blockquote([$paragraph([$text('Quoted'), $linebreak(), $text('packet')])]),
        ]), ['format' => $format]);
        $roundTrip = $readDocument($markdown, $format);

        $t->same("> Quoted\n> packet", $markdown);
        $t->same(['text', 'linebreak', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $roundTrip->children[0]->children[0]->children
        ));
        $t->same("Quoted\npacket", $roundTrip->children[0]->children[0]->attr('text'));
    };

$tests['maps upstream markdown writer hard line break profile list item continuation'] =
    static function (TestRunner $t) use ($bulletList, $document, $linebreak, $listItem, $readDocument, $text, $writeDocument): void {
        $format = 'markdown+hard_line_breaks';
        $markdown = $writeDocument($document([
            $bulletList([$listItem([$text('Review'), $linebreak(), $text('packet')])]),
        ]), ['format' => $format]);
        $roundTrip = $readDocument($markdown, $format);

        $t->same("- Review\n  packet", $markdown);
        $t->same(['text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $roundTrip->children[0]->children[0]->children
        ));
        $t->same('Review packet', $roundTrip->children[0]->children[0]->children[0]->attr('text'));
    };

$tests['maps upstream markdown writer hard line break profile disables paragraph wrapping'] =
    static function (TestRunner $t) use ($document, $paragraph, $text, $writeDocument): void {
        $markdown = $writeDocument($document([
            $paragraph([$text('alpha beta gamma delta')]),
        ]), [
            'format' => 'markdown+hard_line_breaks',
            'columns' => 8,
        ]);

        $t->same('alpha beta gamma delta', $markdown);
        $t->true(!str_contains($markdown, "\n"), 'Hard-line-break profile should force writer wrapping off');
    };

$tests['keeps explicit softbreak preserve option available for hard line break profile'] =
    static function (TestRunner $t) use ($document, $paragraph, $softbreak, $text, $writeDocument): void {
        $markdown = $writeDocument($document([
            $paragraph([$text('Reviewer'), $softbreak(), $text('packet')]),
        ]), [
            'format' => 'markdown+hard_line_breaks',
            'softBreak' => 'preserve',
        ]);

        $t->same("Reviewer\npacket", $markdown);
    };

return $tests;
