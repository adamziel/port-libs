<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$strong = static fn (array $children): AstNode => new AstNode('strong', [], $children);
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);

$tests = [];

$tests['records markdown writer spaced nested strong fixture mapped case count'] =
    static function (TestRunner $t): void {
        $t->same(1, 1);
    };

$tests['maps upstream markdown writer emph strong with spaces 10696 fixture'] =
    static function (TestRunner $t) use ($document, $emph, $space, $strong, $text): void {
        $markdown = (new MarkdownWriter())->write($document([
            $emph([
                $text('f'),
                $strong([$space(), $text('d'), $space()]),
            ]),
            $text('l'),
        ]));

        $t->same('*f **d*** l', $markdown);

        $roundTrip = (new MarkdownReader())->read($markdown);
        $paragraph = $roundTrip->children[0] ?? null;
        $outer = $paragraph instanceof AstNode ? ($paragraph->children[0] ?? null) : null;
        $inner = $outer instanceof AstNode ? ($outer->children[1] ?? null) : null;
        $tail = $paragraph instanceof AstNode ? ($paragraph->children[1] ?? null) : null;

        $t->true($outer instanceof AstNode && $outer->type === 'emph', 'Expected outer emphasis after round-trip');
        $t->true($inner instanceof AstNode && $inner->type === 'strong', 'Expected nested strong after round-trip');
        $t->same('d', $inner instanceof AstNode ? ($inner->children[0]->attr('text') ?? null) : null);
        $t->same(' l', $tail instanceof AstNode ? ($tail->attr('text') ?? null) : null);
    };

return $tests;
