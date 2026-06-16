<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$inline = static fn (string $type, array $children = [], array $attrs = []): AstNode => new AstNode($type, $attrs, $children);
$span = static fn (array $children, array $attrs = []): AstNode => new AstNode('span', $attrs, $children);
$paragraphCase = static fn (array $children, string $expected, array $options = []): array => [
    'document' => $document([$paragraph($children)]),
    'expected' => $expected,
    'options' => $options,
];

$cases = [];
$add = static function (string $label, array $children, string $expected, array $options = []) use (&$cases, $paragraphCase): void {
    $cases[$label] = $paragraphCase($children, $expected, $options);
};

$fallbackProfiles = [
    'commonmark bracketed spans' => ['format' => 'commonmark+bracketed_spans'],
    'gfm bracketed spans without strikeout' => ['format' => 'gfm', 'extensions' => ['+bracketed_spans', '-strikeout']],
];
$semanticDecorations = [
    'strikeout' => ['type' => 'strikeout', 'class' => 'strikeout'],
    'superscript' => ['type' => 'superscript', 'class' => 'superscript'],
    'subscript' => ['type' => 'subscript', 'class' => 'subscript'],
    'underline' => ['type' => 'underline', 'class' => 'underline'],
    'small caps' => ['type' => 'small_caps', 'class' => 'smallcaps'],
];
$payloads = [
    'plain text' => [
        'children' => [$text('review')],
        'markdown' => 'review',
    ],
    'escaped punctuation' => [
        'children' => [$text('a * b [c]')],
        'markdown' => 'a \* b \[c\]',
    ],
    'nested emphasis' => [
        'children' => [$text('pre '), $inline('emph', [$text('em')]), $text(' post')],
        'markdown' => 'pre *em* post',
    ],
    'inline code' => [
        'children' => [$text('use '), $inline('code', [], ['text' => 'wp code'])],
        'markdown' => 'use `wp code`',
    ],
];

foreach ($fallbackProfiles as $profileLabel => $options) {
    foreach ($semanticDecorations as $decorationLabel => $decoration) {
        foreach ($payloads as $payloadLabel => $payload) {
            $add(
                "bracketed span fallback {$profileLabel} {$decorationLabel} {$payloadLabel}",
                [$inline($decoration['type'], $payload['children'])],
                '[' . $payload['markdown'] . ']{.' . $decoration['class'] . '}',
                $options
            );
        }
    }
}

$nativeProfiles = [
    'markdown defaults' => ['format' => 'markdown'],
    'commonmark explicit extensions' => ['format' => 'commonmark+strikeout+superscript+subscript+underline+mark'],
    'gfm explicit extensions' => ['format' => 'gfm', 'extensions' => ['+superscript', '+subscript', '+underline', '+mark']],
];

foreach ($nativeProfiles as $profileLabel => $options) {
    $add("native inline {$profileLabel} strikeout delimiter", [
        $inline('strikeout', [$text('gone')]),
    ], '~~gone~~', $options);
    $add("native inline {$profileLabel} superscript delimiter", [
        $inline('superscript', [$text('build 42')]),
    ], '^build\ 42^', $options);
    $add("native inline {$profileLabel} subscript delimiter", [
        $inline('subscript', [$text('H2O')]),
    ], '~H2O~', $options);
    $add("native inline {$profileLabel} underline span", [
        $inline('underline', [$text('under')]),
    ], '[under]{.underline}', $options);
    $add("native inline {$profileLabel} mark delimiter", [
        $span([$text('marked')], ['classes' => ['mark']]),
    ], '==marked==', $options);
    $add("native inline {$profileLabel} highlight delimiter alias", [
        $span([$text('marked')], ['classes' => ['highlight']]),
    ], '==marked==', $options);
}

$htmlProfiles = [
    'commonmark' => ['format' => 'commonmark'],
    'gfm without strikeout' => ['format' => 'gfm', 'extensions' => ['-strikeout']],
];

foreach ($htmlProfiles as $profileLabel => $options) {
    $add("html fallback {$profileLabel} strikeout", [
        $inline('strikeout', [$text('gone')]),
    ], '<del>gone</del>', $options);
    $add("html fallback {$profileLabel} superscript", [
        $inline('superscript', [$text('build 42')]),
    ], '<sup>build 42</sup>', $options);
    $add("html fallback {$profileLabel} subscript", [
        $inline('subscript', [$text('H2O')]),
    ], '<sub>H2O</sub>', $options);
    $add("html fallback {$profileLabel} underline", [
        $inline('underline', [$text('under')]),
    ], '<span class="underline">under</span>', $options);
    $add("html fallback {$profileLabel} small caps", [
        $inline('small_caps', [$text('Caps')]),
    ], '<span class="smallcaps">Caps</span>', $options);
    $add("html fallback {$profileLabel} mark span", [
        $span([$text('marked')], ['classes' => ['mark']]),
    ], '<span class="mark">marked</span>', $options);
    $add("html fallback {$profileLabel} highlight span", [
        $span([$text('marked')], ['classes' => ['highlight']]),
    ], '<span class="highlight">marked</span>', $options);
}

$attributedOptions = ['format' => 'commonmark+bracketed_spans'];
$attributedAttrs = ['id' => 'review', 'classes' => ['tracked'], 'attributes' => ['data-kind' => 'inline']];
foreach ($semanticDecorations as $decorationLabel => $decoration) {
    $add("attributed bracketed semantic fallback {$decorationLabel}", [
        $inline($decoration['type'], [$text('packet')], $attributedAttrs),
    ], '[packet]{#review .' . $decoration['class'] . ' .tracked data-kind="inline"}', $attributedOptions);
}
$add('attributed bracketed mark fallback keeps class', [
    $span([$text('marked')], ['classes' => ['mark'], 'attributes' => ['data-kind' => 'inline']]),
], '[marked]{.mark data-kind="inline"}', $attributedOptions);
$add('attributed bracketed highlight fallback keeps class', [
    $span([$text('marked')], ['classes' => ['highlight'], 'attributes' => ['data-kind' => 'inline']]),
], '[marked]{.highlight data-kind="inline"}', $attributedOptions);
$add('highlight span escapes mark delimiter when shorthand is unsafe', [
    $span([$text('a == b')], ['classes' => ['highlight']]),
], '[a \=\= b]{.highlight}', ['format' => 'markdown']);

$tests = [
    'records markdown writer extended inline decoration harvest mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(80, count($cases));
    },
];

foreach ($cases as $label => $item) {
    $tests['maps upstream markdown writer extended inline decoration harvest ' . $label] =
        static function (TestRunner $t) use ($item): void {
            $markdown = (new MarkdownWriter($item['options']))->write($item['document']);

            $t->same($item['expected'], $markdown);
        };
}

return $tests;
