<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$cell = static fn (string $value): AstNode => new AstNode('table_cell', ['text' => $value], [$text($value)]);
$row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);

return [
    'projects native table short captions through markdown and wordpress writers' => static function (
        TestRunner $t
    ) use ($text, $space, $cell, $row): void {
        $table = new AstNode('table', [
            'alignments' => ['left', 'right'],
            'shortCaption' => 'Review Q1',
            'shortCaptionInlines' => [
                $text('Review'),
                $space(),
                new AstNode('code', ['text' => 'Q1']),
            ],
            'caption' => 'Long caption',
            'captionInlines' => [
                $text('Long'),
                $space(),
                new AstNode('emph', [], [$text('caption')]),
            ],
        ], [
            new AstNode('table_head', [], [
                $row([$cell('Metric'), $cell('Value')]),
            ]),
            new AstNode('table_body', [], [
                $row([$cell('Probe'), $cell('Ready')]),
            ]),
        ]);
        $document = new AstNode('document', [], [$table]);
        $shortOnlyTable = new AstNode('table', [
            'alignments' => ['left'],
            'shortCaption' => 'Short only',
            'shortCaptionInlines' => [
                $text('Short'),
                $space(),
                new AstNode('code', ['text' => 'only']),
            ],
        ], [
            new AstNode('table_body', [], [
                $row([$cell('Ready')]),
            ]),
        ]);

        $markdown = (new MarkdownWriter())->write($document);
        $shortOnlyMarkdown = (new MarkdownWriter())->write(new AstNode('document', [], [$shortOnlyTable]));
        $wordpress = (new WordPressBlockWriter())->write($document);

        $t->contains(': [Review `Q1`] Long *caption*', $markdown);
        $t->true(!str_contains($markdown, ': Long *caption*'), 'Markdown caption keeps native short-caption prefix');
        $t->contains(': [Short `only`]', $shortOnlyMarkdown);
        $t->true(!str_contains($shortOnlyMarkdown, ': []'), 'Short-only table caption should not render an empty long caption');
        $t->contains('<figure class="wp-block-table" data-pandoc-short-caption="Review Q1">', $wordpress);
        $t->contains('<figcaption class="wp-element-caption">Long <em>caption</em></figcaption>', $wordpress);
    },
];
