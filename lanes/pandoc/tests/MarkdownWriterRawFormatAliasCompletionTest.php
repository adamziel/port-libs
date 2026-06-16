<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$rawBlock = static fn (array $attrs): AstNode => new AstNode('raw_block', $attrs);
$rawInline = static fn (array $attrs): AstNode => new AstNode('raw_inline', $attrs);
$rawMarkdown = static fn (array $attrs): AstNode => new AstNode('raw_markdown', $attrs);

$paragraphDocument = static fn (array $children): AstNode => $document([$paragraph($children)]);

$cases = [
    'raw block raw_format html alias' => [
        'document' => $document([$rawBlock(['raw_format' => 'html5', 'html' => '<aside data-source="raw">HTML</aside>'])]),
        'expected' => '<aside data-source="raw">HTML</aside>',
        'options' => ['format' => 'commonmark'],
    ],
    'raw block format_name xhtml alias' => [
        'document' => $document([$rawBlock(['format_name' => 'xhtml', 'raw' => '<section>Raw XHTML</section>'])]),
        'expected' => '<section>Raw XHTML</section>',
        'options' => ['format' => 'gfm'],
    ],
    'raw block raw_format gfm alias' => [
        'document' => $document([$rawBlock(['raw_format' => 'gfm', 'markdown' => '- [x] raw task'])]),
        'expected' => '- [x] raw task',
        'options' => ['format' => 'gfm'],
    ],
    'raw block format_name strict markdown alias' => [
        'document' => $document([$rawBlock(['format_name' => 'markdown_strict', 'text' => '> strict raw quote'])]),
        'expected' => '> strict raw quote',
        'options' => ['format' => 'markdown'],
    ],
    'raw inline raw_format html alias' => [
        'document' => $paragraphDocument([$text('Press '), $rawInline(['raw_format' => 'html', 'html' => '<kbd>Esc</kbd>'])]),
        'expected' => 'Press <kbd>Esc</kbd>',
        'options' => ['format' => 'commonmark'],
    ],
    'raw inline format_name html5 alias' => [
        'document' => $paragraphDocument([$rawInline(['format_name' => 'html5', 'content' => '<span data-x="1">raw</span>'])]),
        'expected' => '<span data-x="1">raw</span>',
        'options' => ['format' => 'gfm'],
    ],
    'raw inline raw_format gfm alias' => [
        'document' => $paragraphDocument([$text('Status '), $rawInline(['raw_format' => 'gfm', 'markdown' => '~~done~~'])]),
        'expected' => 'Status ~~done~~',
        'options' => ['format' => 'gfm'],
    ],
    'raw inline format_name markdown alias' => [
        'document' => $paragraphDocument([$rawInline(['format_name' => 'markdown', 'literal' => '*raw emphasis*'])]),
        'expected' => '*raw emphasis*',
        'options' => ['format' => 'markdown'],
    ],
    'raw markdown node raw_format commonmark alias' => [
        'document' => $paragraphDocument([$text('Inline '), $rawMarkdown(['raw_format' => 'commonmark', 'value' => '**commonmark**'])]),
        'expected' => 'Inline **commonmark**',
        'options' => ['format' => 'commonmark'],
    ],
    'raw markdown node format_name github alias' => [
        'document' => $document([$rawMarkdown(['format_name' => 'markdown_github', 'markdown' => '- [ ] github task'])]),
        'expected' => '- [ ] github task',
        'options' => ['format' => 'gfm'],
    ],
];

$tests = [
    'records markdown writer raw format alias completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(10, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer raw format alias completion ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

            $t->same($case['expected'], $markdown);
        };
}

return $tests;
