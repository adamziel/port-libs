<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$inlineText = null;
$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak') {
        return ' ';
    }
    if ($node->type === 'linebreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$nodeTypes = static fn (AstNode $document): array =>
    array_map(static fn (AstNode $node): string => $node->type, $document->children);

$thematicVariantBuilders = [
    'compact three' => static fn (string $marker): string => str_repeat($marker, 3),
    'compact four' => static fn (string $marker): string => str_repeat($marker, 4),
    'compact five' => static fn (string $marker): string => str_repeat($marker, 5),
    'single spaced' => static fn (string $marker): string => "{$marker} {$marker} {$marker}",
    'double spaced' => static fn (string $marker): string => "{$marker}  {$marker}  {$marker}",
    'tab spaced' => static fn (string $marker): string => "{$marker}\t{$marker}\t{$marker}",
    'one-space indent' => static fn (string $marker): string => ' ' . str_repeat($marker, 3),
    'two-space indent' => static fn (string $marker): string => "  {$marker} {$marker} {$marker}",
    'three-space indent' => static fn (string $marker): string => "   {$marker} {$marker} {$marker}",
    'trailing tab' => static fn (string $marker): string => str_repeat($marker, 3) . "\t ",
    'mixed compact spaced' => static fn (string $marker): string => "{$marker} {$marker}{$marker} {$marker}",
    'tabs and spaces' => static fn (string $marker): string => "{$marker} \t{$marker}\t {$marker}",
    'many spaced' => static fn (string $marker): string => "{$marker} {$marker} {$marker} {$marker} {$marker}",
    'interrupts paragraph' => static fn (string $marker): string => "alpha\n{$marker} {$marker} {$marker}",
    'after blank paragraph' => static fn (string $marker): string => "alpha\n\n" . str_repeat($marker, 3),
];

$thematicCases = [];
foreach (['asterisk' => '*', 'dash' => '-', 'underscore' => '_'] as $markerName => $marker) {
    foreach ($thematicVariantBuilders as $variantName => $build) {
        $markdown = $build($marker);
        $thematicCases["{$markerName} {$variantName}"] = [
            'markdown' => $markdown,
            'leadingParagraph' => str_starts_with($markdown, 'alpha'),
        ];
    }
}

$setextVariantBuilders = [
    'single marker underline' => static fn (string $marker): array => ['Foo', $marker],
    'double marker underline' => static fn (string $marker): array => ['Foo', str_repeat($marker, 2)],
    'triple marker underline' => static fn (string $marker): array => ['Foo', str_repeat($marker, 3)],
    'one-space underline indent' => static fn (string $marker): array => ['Foo', ' ' . str_repeat($marker, 3)],
    'two-space underline indent' => static fn (string $marker): array => ['Foo', '  ' . str_repeat($marker, 3)],
    'three-space underline indent' => static fn (string $marker): array => ['Foo', '   ' . str_repeat($marker, 3)],
    'trailing spaces underline' => static fn (string $marker): array => ['Foo', str_repeat($marker, 3) . '   '],
    'trailing tab underline' => static fn (string $marker): array => ['Foo', str_repeat($marker, 3) . "\t"],
    'one-space content indent' => static fn (string $marker): array => [' Foo', str_repeat($marker, 3)],
    'trailing content spaces' => static fn (string $marker): array => ['Foo   ', str_repeat($marker, 3)],
    'emphasis content' => static fn (string $marker): array => ['Foo *bar*', str_repeat($marker, 3)],
    'code content' => static fn (string $marker): array => ['`Foo` bar', str_repeat($marker, 3)],
    'explicit attributes' => static fn (string $marker): array => ['Foo {#setext-boundary .review key=val}', str_repeat($marker, 3)],
    'two-line content' => static fn (string $marker): array => ["Foo\nbar", str_repeat($marker, 3)],
    'linklike literal content' => static fn (string $marker): array => ['[Foo] source', str_repeat($marker, 3)],
];

$setextCases = [];
foreach (['equals' => ['marker' => '=', 'level' => 1], 'dash' => ['marker' => '-', 'level' => 2]] as $markerName => $config) {
    foreach ($setextVariantBuilders as $variantName => $build) {
        [$content, $underline] = $build($config['marker']);
        $expectedText = str_replace("\n", ' ', trim($content));
        $expectedId = 'foo';
        $expectedClasses = [];
        $expectedAttributes = [];
        if ($variantName === 'explicit attributes') {
            $expectedText = 'Foo';
            $expectedId = 'setext-boundary';
            $expectedClasses = ['review'];
            $expectedAttributes = ['key' => 'val'];
        } elseif ($variantName === 'code content') {
            $expectedText = 'Foo bar';
            $expectedId = 'foo-bar';
        } elseif ($variantName === 'emphasis content') {
            $expectedText = 'Foo bar';
            $expectedId = 'foo-bar';
        } elseif ($variantName === 'linklike literal content') {
            $expectedText = '[Foo] source';
            $expectedId = 'foo-source';
        } elseif ($variantName === 'two-line content') {
            $expectedId = 'foo-bar';
        }

        $setextCases["{$markerName} {$variantName}"] = [
            'markdown' => "{$content}\n{$underline}",
            'level' => $config['level'],
            'text' => $expectedText,
            'id' => $expectedId,
            'classes' => $expectedClasses,
            'attributes' => $expectedAttributes,
        ];
    }
}

return [
    'maps commonmark thematic break marker boundary surge' =>
        static function (TestRunner $t) use ($thematicCases, $nodeTypes): void {
            $t->same(45, count($thematicCases));

            foreach ($thematicCases as $name => $case) {
                $document = (new MarkdownReader(['format' => 'commonmark']))->read($case['markdown']);
                $label = 'thematic ' . $name;

                if ($case['leadingParagraph']) {
                    $t->same(['paragraph', 'horizontal_rule'], $nodeTypes($document), $label);
                    $t->same('alpha', (string) $document->children[0]->attr('text', ''), $label);
                    continue;
                }

                $t->same(['horizontal_rule'], $nodeTypes($document), $label);
            }
        },
    'maps pandoc markdown setext heading thematic boundary surge' =>
        static function (TestRunner $t) use ($setextCases, $inlineText): void {
            $t->same(30, count($setextCases));

            foreach ($setextCases as $name => $case) {
                $document = (new MarkdownReader())->read($case['markdown']);
                $heading = $document->children[0] ?? new AstNode('missing');
                $label = 'setext ' . $name;

                $t->same(['heading'], array_map(static fn (AstNode $node): string => $node->type, $document->children), $label);
                $t->same($case['level'], $heading->attr('level'), $label);
                $t->same($case['text'], $inlineText($heading), $label);
                $t->same($case['id'], $heading->attr('id'), $label);
                $t->same($case['classes'], $heading->attr('classes', []), $label);
                $t->same($case['attributes'], $heading->attr('attributes', []), $label);
            }
        },
    'renders setext and thematic boundary cases through WordPress blocks' =>
        static function (TestRunner $t): void {
            $markdown = implode("\n", [
                'Review heading {#review-heading .source}',
                '---',
                '',
                'alpha',
                '* * *',
                '',
                'Next heading',
                '===',
            ]);
            $document = (new MarkdownReader())->read($markdown);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(['heading', 'paragraph', 'horizontal_rule', 'heading'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
            $t->same('review-heading', $document->children[0]->attr('id'));
            $t->same(['source'], $document->children[0]->attr('classes'));
            $t->contains('<h2 id="review-heading" class="source">Review heading</h2>', $blocks);
            $t->contains('<hr class="wp-block-separator has-alpha-channel-opacity"/>', $blocks);
            $t->contains('<h1 id="next-heading">Next heading</h1>', $blocks);
        },
    'records commonmark setext thematic boundary surge mapped-case count' =>
        static function (TestRunner $t) use ($thematicCases, $setextCases): void {
            $t->same(75, count($thematicCases) + count($setextCases));
        },
];
