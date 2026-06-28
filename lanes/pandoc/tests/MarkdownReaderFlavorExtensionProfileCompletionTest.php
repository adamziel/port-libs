<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math' || $node->type === 'raw_inline') {
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

$enabledCases = [
    'commonmark plus superscript enables script extension' => [
        'options' => ['format' => 'commonmark+superscript'],
        'markdown' => 'H^2^ packet',
        'match' => static fn (AstNode $node): bool => $node->type === 'superscript',
        'assert' => static function (TestRunner $t, AstNode $node) use ($inlineText): void {
            $t->same('2', $inlineText($node));
        },
    ],
    'commonmark plus subscript enables script extension' => [
        'options' => ['format' => 'commonmark+subscript'],
        'markdown' => 'H~2~ packet',
        'match' => static fn (AstNode $node): bool => $node->type === 'subscript',
        'assert' => static function (TestRunner $t, AstNode $node) use ($inlineText): void {
            $t->same('2', $inlineText($node));
        },
    ],
    'commonmark plus citations enables citation extension' => [
        'options' => ['format' => 'commonmark+citations'],
        'markdown' => '@doe2026 says yes.',
        'match' => static fn (AstNode $node): bool => $node->type === 'citation',
        'assert' => static function (TestRunner $t, AstNode $node): void {
            $t->same('doe2026', $node->attr('id'));
            $t->same('author_in_text', $node->attr('mode'));
        },
    ],
    'multimarkdown enables dollar math extension' => [
        'options' => ['format' => 'markdown_mmd'],
        'markdown' => 'Math $x+1$ done.',
        'match' => static fn (AstNode $node): bool => $node->type === 'math',
        'assert' => static function (TestRunner $t, AstNode $node): void {
            $t->same('x+1', $node->attr('text'));
            $t->same(false, $node->attr('display'));
        },
    ],
    'commonmark plus wikilinks enables wikilink extension' => [
        'options' => ['format' => 'commonmark+wikilinks'],
        'markdown' => 'See [[Label|/target]] now.',
        'match' => static fn (AstNode $node): bool => $node->type === 'link' && $node->attr('classes') === ['wikilink'],
        'assert' => static function (TestRunner $t, AstNode $node) use ($inlineText): void {
            $t->same('/target', $node->attr('url'));
            $t->same('Label', $inlineText($node));
        },
    ],
    'php extra enables bracketed span extension' => [
        'options' => ['format' => 'markdown_phpextra'],
        'markdown' => 'See [marked]{.review data-x=1} now.',
        'match' => static fn (AstNode $node): bool => $node->type === 'span' && $node->attr('classes') === ['review'],
        'assert' => static function (TestRunner $t, AstNode $node) use ($inlineText): void {
            $t->same(['review'], $node->attr('classes'));
            $t->same(['data-x' => '1'], $node->attr('attributes'));
            $t->same('marked', $inlineText($node));
        },
    ],
    'gfm plus raw attribute enables raw inline attribute extension' => [
        'options' => ['format' => 'gfm+raw_attribute'],
        'markdown' => 'Before `<b>x</b>`{=html} after.',
        'match' => static fn (AstNode $node): bool => $node->type === 'raw_inline',
        'assert' => static function (TestRunner $t, AstNode $node): void {
            $t->same('html', $node->attr('format'));
            $t->same('<b>x</b>', $node->attr('text'));
        },
    ],
    'gfm plus inline attributes enables code attributes extension' => [
        'options' => ['format' => 'gfm+inline_attributes'],
        'markdown' => 'Use `code`{.source data-kind=fixture} now.',
        'match' => static fn (AstNode $node): bool => $node->type === 'code' && $node->attr('classes') === ['source'],
        'assert' => static function (TestRunner $t, AstNode $node): void {
            $t->same('code', $node->attr('text'));
            $t->same(['source'], $node->attr('classes'));
            $t->same(['data-kind' => 'fixture'], $node->attr('attributes'));
        },
    ],
];

$disabledCases = [
    'gfm disables superscript script extension' => [
        'options' => ['format' => 'gfm'],
        'markdown' => 'H^2^ packet',
        'literal' => 'H^2^ packet',
        'match' => static fn (AstNode $node): bool => $node->type === 'superscript',
    ],
    'gfm disables subscript script extension' => [
        'options' => ['format' => 'gfm'],
        'markdown' => 'H~2~ packet',
        'literal' => 'H~2~ packet',
        'match' => static fn (AstNode $node): bool => $node->type === 'subscript',
    ],
    'gfm disables citation extension' => [
        'options' => ['format' => 'gfm'],
        'markdown' => '@doe2026 says yes.',
        'literal' => '@doe2026 says yes.',
        'match' => static fn (AstNode $node): bool => $node->type === 'citation',
    ],
    'gfm disables dollar math extension' => [
        'options' => ['format' => 'gfm'],
        'markdown' => 'Math $x+1$ done.',
        'literal' => 'Math $x+1$ done.',
        'match' => static fn (AstNode $node): bool => $node->type === 'math',
    ],
    'gfm disables wikilink extension' => [
        'options' => ['format' => 'gfm'],
        'markdown' => 'See [[Label|/target]] now.',
        'literal' => 'See [[Label|/target]] now.',
        'match' => static fn (AstNode $node): bool => $node->type === 'link' && $node->attr('classes') === ['wikilink'],
    ],
    'gfm disables bracketed span extension' => [
        'options' => ['format' => 'gfm'],
        'markdown' => 'See [marked]{.review data-x=1} now.',
        'literal' => 'See [marked]{.review data-x=1} now.',
        'match' => static fn (AstNode $node): bool => $node->type === 'span' && $node->attr('classes') === ['review'],
    ],
    'markdown minus raw attribute disables raw inline attribute extension' => [
        'options' => ['format' => 'markdown-raw_attribute'],
        'markdown' => 'Before `<b>x</b>`{=html} after.',
        'literal' => 'Before <b>x</b>{=html} after.',
        'match' => static fn (AstNode $node): bool => $node->type === 'raw_inline',
    ],
    'gfm disables inline code attributes extension' => [
        'options' => ['format' => 'gfm'],
        'markdown' => 'Use `code`{.source data-kind=fixture} now.',
        'literal' => 'Use code{.source data-kind=fixture} now.',
        'match' => static fn (AstNode $node): bool => $node->type === 'code' && $node->attr('classes') === ['source'],
    ],
];

return [
    'maps upstream markdown inline extension enabled flavor profiles' =>
        static function (TestRunner $t) use ($enabledCases, $findInline): void {
            foreach ($enabledCases as $label => $case) {
                $document = (new MarkdownReader($case['options']))->read($case['markdown']);
                $match = $findInline($document, $case['match']);

                $t->true($match->type !== 'missing', $label);
                if ($match->type !== 'missing') {
                    $case['assert']($t, $match);
                }
            }
        },

    'maps upstream markdown inline extension disabled flavor profiles as literal text' =>
        static function (TestRunner $t) use ($disabledCases, $findInline, $inlineText): void {
            foreach ($disabledCases as $label => $case) {
                $document = (new MarkdownReader($case['options']))->read($case['markdown']);
                $paragraph = $document->children[0] ?? new AstNode('missing');
                $match = $findInline($document, $case['match']);

                $t->same('missing', $match->type, $label);
                $t->same($case['literal'], $inlineText($paragraph), $label . ' literal');
            }
        },

    'records upstream markdown inline extension profile mapped-case count' =>
        static function (TestRunner $t) use ($enabledCases, $disabledCases): void {
            $t->same(16, count($enabledCases) + count($disabledCases));
        },
];
