<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$paragraphDocument = static fn (array $children): AstNode => $document([$paragraph($children)]);
$span = static fn (array $children, array $attrs = []): AstNode => new AstNode('span', $attrs, $children);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$bulletList = static fn (array $items): AstNode => new AstNode('bullet_list', [], $items);
$link = static fn (string $label, string $url, array $attrs = []): AstNode => new AstNode(
    'link',
    array_replace(['url' => $url], $attrs),
    [$text($label)]
);

$cases = [
    'bracketed span singular alias keeps attribute tuple' => [
        'format' => 'commonmark+bracketed_span',
        'document' => $paragraphDocument([
            $span([$text('packet')], ['classes' => ['review']]),
        ]),
        'expected' => '[packet]{.review}',
    ],
    'emoji shortcode alias keeps compact emoji token' => [
        'format' => 'commonmark+emoji_shortcode',
        'document' => $paragraphDocument([
            $span([$text("\u{2728}")], ['classes' => ['emoji'], 'attributes' => ['data-emoji' => 'sparkles']]),
        ]),
        'expected' => ':sparkles:',
    ],
    'emoji shortcodes alias from extension option keeps compact emoji token' => [
        'format' => 'commonmark',
        'extensions' => '+emoji_shortcodes',
        'document' => $paragraphDocument([
            $span([$text("\u{2728}")], ['classes' => ['gemoji'], 'attributes' => ['shortcode' => ':sparkles:']]),
        ]),
        'expected' => ':sparkles:',
    ],
    'header attrs alias keeps heading tuple' => [
        'format' => 'commonmark+header_attrs',
        'document' => $document([
            new AstNode('heading', [
                'level' => 2,
                'text' => 'Review',
                'id' => 'review',
                'classes' => ['packet'],
                'attributes' => ['data-kind' => 'heading'],
            ], [$text('Review')]),
        ]),
        'expected' => '## Review {#review .packet data-kind="heading"}',
    ],
    'inline attribute alias keeps code tuple' => [
        'format' => 'commonmark+inline_attribute',
        'document' => $paragraphDocument([
            $text('Use '),
            new AstNode('code', [
                'text' => 'packet',
                'id' => 'code-id',
                'classes' => ['source'],
                'attributes' => ['data-kind' => 'profile'],
            ]),
            $text(' now.'),
        ]),
        'expected' => 'Use `packet`{#code-id .source data-kind="profile"} now.',
    ],
    'markdown attribute alias keeps link tuple' => [
        'format' => 'commonmark+markdown_attribute',
        'document' => $paragraphDocument([
            $text('Visit '),
            $link('profile', 'https://example.test/profile', [
                'id' => 'link-id',
                'classes' => ['linked'],
                'attributes' => ['data-kind' => 'profile'],
            ]),
            $text('.'),
        ]),
        'expected' => 'Visit [profile](https://example.test/profile){#link-id .linked data-kind="profile"}.',
    ],
    'tasklist alias keeps unchecked task syntax' => [
        'format' => 'commonmark+tasklist',
        'document' => $document([
            $bulletList([
                $listItem([$text('Todo')], ['taskChecked' => false]),
            ]),
        ]),
        'expected' => '- [ ] Todo',
    ],
    'tasklists alias keeps checked task syntax' => [
        'format' => 'commonmark+tasklists',
        'document' => $document([
            $bulletList([
                $listItem([$text('Done')], ['taskChecked' => true]),
            ]),
        ]),
        'expected' => '- [x] Done',
    ],
    'superscripts alias keeps caret syntax' => [
        'format' => 'commonmark+superscripts',
        'document' => $paragraphDocument([
            $text('Build '),
            new AstNode('superscript', [], [$text('42')]),
        ]),
        'expected' => 'Build ^42^',
    ],
    'subscripts alias keeps tilde syntax' => [
        'format' => 'commonmark+subscripts',
        'document' => $paragraphDocument([
            $text('H'),
            new AstNode('subscript', [], [$text('2')]),
            $text('O'),
        ]),
        'expected' => 'H~2~O',
    ],
    'line block singular alias keeps line block syntax' => [
        'format' => 'commonmark+line_block',
        'document' => $document([
            new AstNode('line_block', [], [
                new AstNode('line', [], [$text('alpha')]),
                new AstNode('line', [], [$text('beta')]),
            ]),
        ]),
        'expected' => "| alpha\n| beta",
    ],
    'wiki link singular alias keeps wiki syntax' => [
        'format' => 'commonmark+wiki_link',
        'document' => $paragraphDocument([
            $link('Runbook', '/docs/runbook', ['classes' => ['wikilink']]),
        ]),
        'expected' => '[[Runbook|/docs/runbook]]',
    ],
];

$tests = [
    'records markdown writer extension alias parity mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(12, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer extension alias parity ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $options = ['format' => $case['format']];
            if (array_key_exists('extensions', $case)) {
                $options['extensions'] = $case['extensions'];
            }

            $markdown = (new MarkdownWriter($options))->write($case['document']);

            $t->same($case['expected'], $markdown);
        };
}

return $tests;
