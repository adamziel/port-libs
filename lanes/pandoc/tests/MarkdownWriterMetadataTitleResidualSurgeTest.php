<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$strong = static fn (array $children): AstNode => new AstNode('strong', [], $children);
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$heading = static fn (int $level, array $children, array $attrs = []): AstNode => new AstNode(
    'heading',
    ['level' => $level] + $attrs,
    $children
);
$div = static fn (array $children, array $attrs = []): AstNode => new AstNode('div', $attrs, $children);
$codeBlock = static fn (string $value, array $attrs = []): AstNode => new AstNode(
    'code_block',
    ['text' => $value] + $attrs
);
$document = static fn (array $meta, array $blocks): AstNode => new AstNode('document', ['meta' => $meta], $blocks);

$body = $paragraph([$text('Body title residual.')]);

$profileCases = [
    'markdown disables yaml metadata block' => ['format' => 'markdown-yaml_metadata_block'],
    'pandoc disables yaml metadata block' => ['format' => 'pandoc-yaml_metadata_block'],
    'commonmark enables pandoc title block' => ['format' => 'commonmark+pandoc_title_block'],
    'commonmark x enables pandoc title block' => ['format' => 'commonmark_x+pandoc_title_block'],
    'gfm enables pandoc title block' => ['format' => 'gfm+pandoc_title_block'],
    'github markdown enables pandoc title block' => ['format' => 'markdown_github+pandoc_title_block'],
    'strict markdown enables pandoc title block' => ['format' => 'markdown_strict+pandoc_title_block'],
    'php extra markdown enables pandoc title block' => ['format' => 'markdown_phpextra+pandoc_title_block'],
    'mmd markdown enables pandoc title block' => ['format' => 'markdown_mmd+pandoc_title_block'],
    'explicit writer options enable title block without format' => ['yamlMetadata' => false, 'titleBlock' => true],
];

$metadataVariants = [
    'full scalar title author date' => [
        'meta' => [
            'title' => 'Review packet',
            'author' => ['Ada Lovelace', 'Grace Hopper'],
            'date' => '2026-06-16',
            'review' => ['omitted' => true],
        ],
        'titleBlock' => "% Review packet\n% Ada Lovelace; Grace Hopper\n% 2026-06-16",
    ],
    'title only scalar' => [
        'meta' => ['title' => 'Title only packet'],
        'titleBlock' => '% Title only packet',
    ],
    'author only scalar' => [
        'meta' => ['author' => 'Solo Reviewer'],
        'titleBlock' => "%\n% Solo Reviewer",
    ],
    'date only scalar' => [
        'meta' => ['date' => '2026-06-16'],
        'titleBlock' => "%\n%\n% 2026-06-16",
    ],
    'title and date without author' => [
        'meta' => ['title' => 'Dated packet', 'date' => '2026-06-16'],
        'titleBlock' => "% Dated packet\n%\n% 2026-06-16",
    ],
    'author string with semicolon separator' => [
        'meta' => ['title' => 'Author string packet', 'author' => 'Ada Lovelace; Grace Hopper'],
        'titleBlock' => "% Author string packet\n% Ada Lovelace; Grace Hopper",
    ],
    'title inlines markdown survives' => [
        'meta' => [
            'title' => 'Ignored title fallback',
            'titleInlines' => [$emph([$text('Inline')]), $space(), $text('title')],
            'author' => ['Inline Reviewer'],
        ],
        'titleBlock' => "% *Inline* title\n% Inline Reviewer",
    ],
    'author inlines markdown survive' => [
        'meta' => [
            'title' => 'Inline authors',
            'authorInlines' => [
                [$text('Ada'), $space(), $emph([$text('source')])],
                [$strong([$text('Grace')])],
            ],
        ],
        'titleBlock' => "% Inline authors\n% Ada *source*; **Grace**",
    ],
    'date inlines markdown survive' => [
        'meta' => [
            'title' => 'Inline date',
            'dateInlines' => [$text('Built with '), $code('codex')],
        ],
        'titleBlock' => "% Inline date\n%\n% Built with `codex`",
    ],
    'multiline title uses continuation line' => [
        'meta' => [
            'title' => "Review\npacket",
            'author' => ['Continuation Reviewer'],
            'date' => '2026-06-16',
        ],
        'titleBlock' => "% Review\n  packet\n% Continuation Reviewer\n% 2026-06-16",
    ],
];

$tests = [];
$mappedCaseCount = 0;

foreach ($profileCases as $profileLabel => $options) {
    foreach ($metadataVariants as $variantLabel => $variant) {
        $mappedCaseCount++;
        $tests['maps upstream markdown writer metadata title residual '
            . $profileLabel . ' ' . $variantLabel] =
            static function (TestRunner $t) use ($document, $body, $options, $variant): void {
                $markdown = (new MarkdownWriter($options))->write($document($variant['meta'], [$body]));

                $t->same($variant['titleBlock'] . "\n\nBody title residual.", $markdown);
            };
    }
}

$titleMeta = [
    'title' => 'Attribute residual packet',
    'author' => ['Lane 1404'],
    'date' => '2026-06-16',
];
$titleBlock = "% Attribute residual packet\n% Lane 1404\n% 2026-06-16";

$interactionCases = [
    'commonmark title block with header attributes override' => [
        'options' => ['format' => 'commonmark+pandoc_title_block+header_attributes'],
        'blocks' => [
            $heading(2, [$text('Section')], [
                'id' => 'section-id',
                'classes' => ['packet'],
                'attributes' => ['data-kind' => 'metadata'],
            ]),
        ],
        'expectedBody' => '## Section {#section-id .packet data-kind="metadata"}',
    ],
    'commonmark title block with header html fallback' => [
        'options' => ['format' => 'commonmark+pandoc_title_block'],
        'blocks' => [
            $heading(2, [$text('Section')], [
                'id' => 'section-id',
                'attributes' => ['data-kind' => 'metadata'],
            ]),
        ],
        'expectedBody' => '<h2 id="section-id" data-kind="metadata">Section</h2>',
    ],
    'gfm title block with fenced div override' => [
        'options' => ['format' => 'gfm+pandoc_title_block+fenced_divs'],
        'blocks' => [
            $div([
                $paragraph([$text('Section body')]),
            ], [
                'id' => 'review-section',
                'classes' => ['section', 'level1'],
                'attributes' => ['data-kind' => 'metadata'],
            ]),
        ],
        'expectedBody' => "::: {#review-section .section .level1 data-kind=\"metadata\"}\nSection body\n:::",
    ],
    'commonmark x title block keeps native fenced div and identifier' => [
        'options' => ['format' => 'commonmark_x+pandoc_title_block'],
        'blocks' => [
            $div([
                $heading(1, [$text('Article')]),
            ], [
                'id' => 'article',
                'classes' => ['section', 'level1'],
            ]),
        ],
        'expectedBody' => "::: {#article .section .level1}\n# Article\n:::",
    ],
    'markdown title block with raw html block residual' => [
        'options' => ['format' => 'markdown-yaml_metadata_block'],
        'blocks' => [
            new AstNode('raw_block', ['format' => 'html', 'text' => '<aside data-raw="html">Raw</aside>']),
        ],
        'expectedBody' => '<aside data-raw="html">Raw</aside>',
    ],
    'commonmark title block with raw html inline residual' => [
        'options' => ['format' => 'commonmark+pandoc_title_block'],
        'blocks' => [
            $paragraph([
                $text('Before '),
                new AstNode('raw_inline', ['format' => 'html', 'text' => '<span data-raw="html">raw</span>']),
                $text(' after.'),
            ]),
        ],
        'expectedBody' => 'Before <span data-raw="html">raw</span> after.',
    ],
    'gfm title block with raw markdown target residual' => [
        'options' => ['format' => 'gfm+pandoc_title_block'],
        'blocks' => [
            $paragraph([
                $text('Before '),
                new AstNode('raw_markdown', ['format' => 'gfm', 'markdown' => '**raw gfm**']),
                $text(' after.'),
            ]),
        ],
        'expectedBody' => 'Before **raw gfm** after.',
    ],
    'commonmark title block with raw tex extension residual' => [
        'options' => ['format' => 'commonmark+pandoc_title_block+raw_tex'],
        'blocks' => [
            $paragraph([
                $text('Before '),
                new AstNode('raw_tex', ['tex' => '\\LaTeX{}']),
                $text(' after.'),
            ]),
        ],
        'expectedBody' => 'Before \LaTeX{} after.',
    ],
    'commonmark title block with fenced code attributes override' => [
        'options' => ['format' => 'commonmark+pandoc_title_block+fenced_code_attributes'],
        'blocks' => [
            $codeBlock('echo alpha', [
                'id' => 'src',
                'classes' => ['php'],
                'attributes' => ['data-kind' => 'metadata'],
            ]),
        ],
        'expectedBody' => "```{#src .php data-kind=\"metadata\"}\necho alpha\n```",
    ],
    'gfm title block with header and div extension options' => [
        'options' => ['format' => 'gfm+pandoc_title_block+header_attributes+fenced_divs'],
        'blocks' => [
            $heading(1, [$text('Packet')], ['id' => 'packet']),
            $div([$paragraph([$text('Body')])], ['classes' => ['review']]),
        ],
        'expectedBody' => "# Packet {#packet}\n\n::: {.review}\nBody\n:::",
    ],
    'strict markdown title block keeps raw markdown target' => [
        'options' => ['format' => 'markdown_strict+pandoc_title_block'],
        'blocks' => [
            new AstNode('raw_markdown', ['format' => 'markdown_strict', 'markdown' => '> raw strict']),
        ],
        'expectedBody' => '> raw strict',
    ],
    'php extra title block keeps native div override' => [
        'options' => ['format' => 'markdown_phpextra+pandoc_title_block+native_divs'],
        'blocks' => [
            $div([$paragraph([$text('PHP extra body')])], ['id' => 'php-extra']),
        ],
        'expectedBody' => "::: {#php-extra}\nPHP extra body\n:::",
    ],
    'mmd title block keeps section div identifier' => [
        'options' => ['format' => 'markdown_mmd+pandoc_title_block+fenced_divs'],
        'blocks' => [
            $div([$paragraph([$text('MMD body')])], ['id' => 'mmd-section', 'classes' => ['section']]),
        ],
        'expectedBody' => "::: {#mmd-section .section}\nMMD body\n:::",
    ],
    'explicit options title block with markdown raw default' => [
        'options' => ['yamlMetadata' => false, 'titleBlock' => true],
        'blocks' => [
            new AstNode('raw_markdown', ['format' => 'markdown', 'markdown' => '## Raw markdown']),
        ],
        'expectedBody' => '## Raw markdown',
    ],
    'pandoc title block with yaml disabled and header attributes' => [
        'options' => ['format' => 'pandoc-yaml_metadata_block'],
        'blocks' => [
            $heading(3, [$text('Pandoc packet')], ['id' => 'pandoc-packet']),
        ],
        'expectedBody' => '### Pandoc packet {#pandoc-packet}',
    ],
];

foreach ($interactionCases as $label => $case) {
    $mappedCaseCount++;
    $tests['maps upstream markdown writer metadata title residual ' . $label] =
        static function (TestRunner $t) use ($document, $titleMeta, $titleBlock, $case): void {
            $markdown = (new MarkdownWriter($case['options']))->write($document($titleMeta, $case['blocks']));

            $t->same($titleBlock . "\n\n" . $case['expectedBody'], $markdown);
        };
}

$tests['keeps yaml metadata ahead of title block when both writer extensions are enabled'] =
    static function (TestRunner $t) use ($document, $body): void {
        $markdown = (new MarkdownWriter([
            'format' => 'commonmark+pandoc_title_block+yaml_metadata_block',
        ]))->write($document([
            'title' => 'YAML wins',
            'author' => ['Metadata Reviewer'],
            'date' => '2026-06-16',
        ], [$body]));

        $t->true(str_starts_with($markdown, "---\n"), 'YAML metadata block should remain the richer writer format');
        $t->contains('title: "YAML wins"', $markdown);
        $t->true(!str_starts_with($markdown, '% YAML wins'), 'Title block should not precede YAML metadata');
    };

$tests['does not emit title block when the title extension is disabled'] =
    static function (TestRunner $t) use ($document, $body): void {
        $markdown = (new MarkdownWriter([
            'format' => 'markdown-yaml_metadata_block-pandoc_title_block',
        ]))->write($document([
            'title' => 'Disabled title block',
            'author' => ['Metadata Reviewer'],
        ], [$body]));

        $t->same('Body title residual.', $markdown);
    };

$tests['records markdown writer metadata title residual mapped-case count'] =
    static function (TestRunner $t) use ($mappedCaseCount): void {
        $t->same(115, $mappedCaseCount);
    };

return $tests;
