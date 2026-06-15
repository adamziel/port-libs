<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$listItem = static fn (array $children): AstNode => new AstNode('list_item', [], $children);
$bulletList = static fn (array $items): AstNode => new AstNode('bullet_list', [], $items);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$div = static fn (array $children): AstNode => new AstNode('div', ['classes' => ['review']], $children);

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }

    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $parts = [];
    foreach ($node->children as $child) {
        $part = $plainText($child);
        if ($part !== '') {
            $parts[] = $part;
        }
    }

    if ($parts !== []) {
        return implode(' ', $parts);
    }

    $textAttr = $node->attr('text', '');

    return is_scalar($textAttr) ? (string) $textAttr : '';
};

$normalizedText = static fn (string $value): string => trim(preg_replace('/\s+/', ' ', $value) ?? $value);

$containers = [
    'paragraph' => static fn (AstNode $paragraph): AstNode => $document([$paragraph]),
    'bullet list item' => static fn (AstNode $paragraph): AstNode => $document([$bulletList([$listItem([$paragraph])])]),
    'blockquote paragraph' => static fn (AstNode $paragraph): AstNode => $document([$blockquote([$paragraph])]),
    'fenced div paragraph' => static fn (AstNode $paragraph): AstNode => $document([$div([$paragraph])]),
];

$escapeCases = [
    'setext single equals underline' => [
        'text' => "Setext one\n=",
        'fragment' => '\\=',
    ],
    'setext double equals underline' => [
        'text' => "Setext two\n==",
        'fragment' => '\\==',
    ],
    'setext triple equals underline' => [
        'text' => "Setext three\n===",
        'fragment' => '\\===',
    ],
    'setext quadruple equals underline' => [
        'text' => "Setext four\n====",
        'fragment' => '\\====',
    ],
    'setext equals underline before following text' => [
        'text' => "Setext five\n=====\nnext",
        'fragment' => '\\=====',
    ],
    'inline triple hyphen literal' => [
        'text' => 'dash a---b stays literal',
        'fragment' => 'a\\-\\-\\-b',
    ],
    'inline quadruple hyphen literal' => [
        'text' => 'dash a----b stays literal',
        'fragment' => 'a\\-\\-\\-\\-b',
    ],
    'inline quintuple hyphen literal' => [
        'text' => 'dash a-----b stays literal',
        'fragment' => 'a\\-\\-\\-\\-\\-b',
    ],
    'line triple hyphen literal' => [
        'text' => "Dash line\n---",
        'fragment' => '\\-\\-\\-',
    ],
    'line quadruple hyphen literal before following text' => [
        'text' => "Dash line\n----\nnext",
        'fragment' => '\\-\\-\\-\\-',
    ],
    'inline quadruple dot literal' => [
        'text' => 'ellipsis a....b stays literal',
        'fragment' => 'a\\.\\.\\.\\.b',
    ],
    'inline quintuple dot literal' => [
        'text' => 'ellipsis a.....b stays literal',
        'fragment' => 'a\\.\\.\\.\\.\\.b',
    ],
    'line quadruple dot literal' => [
        'text' => "Dots line\n....",
        'fragment' => '\\.\\.\\.\\.',
    ],
];

$tests = [];

foreach ($containers as $containerName => $container) {
    foreach ($escapeCases as $caseName => $case) {
        $tests['maps upstream markdown writer block escape surge ' . $containerName . ' ' . $caseName] =
            static function (TestRunner $t) use ($case, $paragraph, $container, $plainText, $normalizedText): void {
                $sourceText = $case['text'];
                $markdown = (new MarkdownWriter())->write($container($paragraph($sourceText)));
                $roundTrip = (new MarkdownReader())->read($markdown);

                $t->contains($case['fragment'], $markdown);
                $t->same($normalizedText($sourceText), $normalizedText($plainText($roundTrip)));
                $t->same([], array_values(array_filter(
                    array_map(
                        static fn (AstNode $node): string => in_array($node->type, ['heading', 'horizontal_rule'], true) ? $node->type : '',
                        $roundTrip->children
                    ),
                    static fn (string $type): bool => $type !== ''
                )));
            };
    }
}

return $tests;
