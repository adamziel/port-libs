<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$strong = static fn (array $children): AstNode => new AstNode('strong', [], $children);
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$document = static fn (array $meta, string $body): AstNode => new AstNode('document', ['meta' => $meta], [
    $paragraph($body),
]);

$cases = [
    'title value native inlines' => [
        'meta' => ['title' => [$text('Native '), $strong([$text('title')])]],
        'expected' => "% Native **title**\n\nBody native title.",
        'body' => 'Body native title.',
        'options' => ['format' => 'markdown'],
    ],
    'date value native inlines' => [
        'meta' => ['date' => [$text('Built '), $code('2026-06-16')]],
        'expected' => "%\n%\n% Built `2026-06-16`\n\nBody native date.",
        'body' => 'Body native date.',
        'options' => ['format' => 'markdown'],
    ],
    'single author value native inlines' => [
        'meta' => [
            'title' => 'Native author packet',
            'author' => [$text('Ada '), $emph([$text('Lovelace')])],
        ],
        'expected' => "% Native author packet\n% Ada *Lovelace*\n\nBody native author.",
        'body' => 'Body native author.',
        'options' => ['format' => 'markdown'],
    ],
    'authors value native inline list' => [
        'meta' => [
            'title' => 'Native authors packet',
            'authors' => [
                [$text('Ada '), $emph([$text('Lovelace')])],
                [$strong([$text('Grace')]), $space(), $text('Hopper')],
            ],
        ],
        'expected' => "% Native authors packet\n% Ada *Lovelace*\n  **Grace** Hopper\n\nBody native authors.",
        'body' => 'Body native authors.',
        'options' => ['format' => 'markdown'],
    ],
    'yaml disabled native authors join title block line' => [
        'meta' => [
            'title' => 'Joined native authors',
            'author' => [
                [$text('Ada '), $emph([$text('Lovelace')])],
                [$strong([$text('Grace')]), $space(), $text('Hopper')],
            ],
        ],
        'expected' => "% Joined native authors\n% Ada *Lovelace*; **Grace** Hopper\n\nBody joined native authors.",
        'body' => 'Body joined native authors.',
        'options' => ['yamlMetadata' => false, 'titleBlock' => true],
    ],
    'commonmark opt in native title softbreak continuation' => [
        'meta' => ['title' => [$text('Line one'), $softbreak(), $text('Line two')]],
        'expected' => "% Line one\n  Line two\n\nBody native continuation.",
        'body' => 'Body native continuation.',
        'options' => ['format' => 'commonmark+pandoc_title_block'],
    ],
];

$tests = [
    'records markdown writer title block native metadata completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(6, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer title block native metadata completion ' . $label] =
        static function (TestRunner $t) use ($case, $document): void {
            $markdown = (new MarkdownWriter($case['options']))->write($document($case['meta'], $case['body']));

            $t->same($case['expected'], $markdown);
            $t->true(!str_starts_with($markdown, "---\n"), 'Native title block metadata should not fall back to YAML');
        };
}

return $tests;
