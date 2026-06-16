<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$span = static fn (array $attrs, array $children): AstNode => new AstNode('span', $attrs, $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$paragraphDocument = static fn (array $children): AstNode => $document([$paragraph($children)]);

$spanClass = static function (AstNode $node): array {
    $paragraph = $node->children[0] ?? null;
    $span = $paragraph instanceof AstNode ? ($paragraph->children[0] ?? null) : null;

    return $span instanceof AstNode ? [$span->type, $span->attr('classes', [])] : ['missing', []];
};

$cases = [
    'commonmark plus mark shorthand' => [
        'document' => $paragraphDocument([$span(['classes' => ['mark']], [$text('Marked')])]),
        'options' => ['format' => 'commonmark+mark'],
        'expected' => '==Marked==',
        'roundTripFormat' => 'commonmark+mark',
        'roundTripExpected' => '==Marked==',
    ],
    'gfm plus mark shorthand from extension array' => [
        'document' => $paragraphDocument([$span(['classes' => ['highlighted']], [$text('Marked')])]),
        'options' => ['format' => 'gfm', 'extensions' => ['+mark']],
        'expected' => '==Marked==',
        'roundTripFormat' => 'gfm+mark',
        'roundTripExpected' => '==Marked==',
    ],
    'commonmark bracketed span fallback when mark is disabled' => [
        'document' => $paragraphDocument([$span(['classes' => ['mark']], [$text('Marked')])]),
        'options' => ['format' => 'commonmark+bracketed_spans'],
        'expected' => '[Marked]{.mark}',
        'roundTripFormat' => 'commonmark+bracketed_spans',
        'roundTripExpected' => '[Marked]{.mark}',
    ],
    'gfm attributed mark span keeps bracketed fallback' => [
        'document' => $paragraphDocument([$span(['id' => 'review-mark', 'classes' => ['mark']], [$text('Marked')])]),
        'options' => ['format' => 'gfm+mark+bracketed_spans'],
        'expected' => '[Marked]{#review-mark .mark}',
        'roundTripFormat' => 'gfm+mark+bracketed_spans',
        'roundTripExpected' => '[Marked]{#review-mark .mark}',
    ],
    'commonmark delimiter content keeps escaped bracketed fallback' => [
        'document' => $paragraphDocument([$span(['classes' => ['mark']], [$text('a == b')])]),
        'options' => ['format' => 'commonmark+mark+bracketed_spans'],
        'expected' => '[a \=\= b]{.mark}',
        'roundTripFormat' => 'commonmark+mark+bracketed_spans',
        'roundTripExpected' => '[a \=\= b]{.mark}',
    ],
    'commonmark raw html fallback when mark and bracketed spans are disabled' => [
        'document' => $paragraphDocument([$span(['classes' => ['mark']], [$text('Marked')])]),
        'options' => ['format' => 'commonmark'],
        'expected' => '<span class="mark">Marked</span>',
        'roundTripFormat' => null,
        'roundTripExpected' => null,
    ],
];

$tests = [
    'records markdown writer mark extension completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(6, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer mark extension completion ' . $label] =
        static function (TestRunner $t) use ($case, $spanClass): void {
            $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

            $t->same($case['expected'], $markdown);
            if ($case['roundTripFormat'] === null) {
                return;
            }

            $roundTrip = (new MarkdownReader(['format' => $case['roundTripFormat']]))->read($markdown);
            $t->same(['span', ['mark']], $spanClass($roundTrip));
            $t->same(
                $case['roundTripExpected'],
                (new MarkdownWriter($case['options']))->write($roundTrip)
            );
        };
}

return $tests;
