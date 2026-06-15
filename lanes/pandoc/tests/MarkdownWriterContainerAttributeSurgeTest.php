<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$heading = static fn (int $level, array $children, array $attrs = []): AstNode => new AstNode(
    'heading',
    ['level' => $level] + $attrs,
    $children
);
$codeBlock = static fn (string $value, array $attrs = []): AstNode => new AstNode(
    'code_block',
    ['text' => $value] + $attrs
);
$div = static fn (array $children = [], array $attrs = []): AstNode => new AstNode('div', $attrs, $children);
$line = static fn (string $value): AstNode => new AstNode('line', [], [$text($value)]);
$lineBlock = static fn (array $lines, array $attrs = []): AstNode => new AstNode('line_block', $attrs, $lines);
$emph = static fn (string $value): AstNode => new AstNode('emph', [], [$text($value)]);
$strong = static fn (string $value): AstNode => new AstNode('strong', [], [$text($value)]);
$inlineCode = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);

$cases = [];
$attributeFallbackFormats = ['commonmark', 'gfm', 'markdown_strict'];
$containerFallbackFormats = ['commonmark', 'gfm', 'markdown_strict', 'markdown_phpextra', 'markdown_mmd'];

$headingVariants = [
    'id class data packet' => [
        $heading(1, [$text('Review packet')], [
            'id' => 'review-packet',
            'classes' => ['packet'],
            'attributes' => ['data-kind' => 'handoff'],
        ]),
        '<h1 id="review-packet" class="packet" data-kind="handoff">Review packet</h1>',
    ],
    'locale and direction metadata' => [
        $heading(2, [$text('Localized packet')], [
            'classes' => ['localized'],
            'attributes' => ['data-locale' => 'ar'],
            'dir' => 'rtl',
            'lang' => 'ar',
            'role' => 'doc-subtitle',
        ]),
        '<h2 class="localized" data-locale="ar" dir="rtl" lang="ar" role="doc-subtitle">Localized packet</h2>',
    ],
    'emphasis child metadata' => [
        $heading(3, [$text('Review '), $emph('source')], [
            'id' => 'source-review',
            'attributes' => ['data-source' => 'batch-17'],
        ]),
        '<h3 id="source-review" data-source="batch-17">Review <em>source</em></h3>',
    ],
    'code child metadata' => [
        $heading(4, [$text('Run '), $inlineCode('wp post get')], [
            'classes' => ['command-heading'],
            'attributes' => ['data-command' => 'wp-cli'],
        ]),
        '<h4 class="command-heading" data-command="wp-cli">Run <code>wp post get</code></h4>',
    ],
    'title metadata' => [
        $heading(5, [$text('Titled heading')], [
            'id' => 'titled-heading',
            'title' => 'Reviewer title',
        ]),
        '<h5 id="titled-heading" title="Reviewer title">Titled heading</h5>',
    ],
    'xml language metadata' => [
        $heading(6, [$text('French packet')], [
            'classes' => ['translated'],
            'attributes' => ['data-language' => 'fr'],
            'xml:lang' => 'fr',
        ]),
        '<h6 class="translated" data-language="fr" xml:lang="fr">French packet</h6>',
    ],
    'multiple classes metadata' => [
        $heading(2, [$text('Multi class')], [
            'classes' => ['section', 'level2', 'review'],
            'attributes' => ['data-depth' => '2'],
        ]),
        '<h2 class="section level2 review" data-depth="2">Multi class</h2>',
    ],
    'attribute escaping metadata' => [
        $heading(3, [$text('Quoted packet')], [
            'id' => 'quoted-packet',
            'attributes' => ['data-title' => 'Reviewer "quote" & source'],
        ]),
        '<h3 id="quoted-packet" data-title="Reviewer &quot;quote&quot; &amp; source">Quoted packet</h3>',
    ],
];

foreach ($attributeFallbackFormats as $format) {
    foreach ($headingVariants as $label => [$block, $expected]) {
        $cases["heading attributes fallback {$format} {$label}"] = [
            'document' => $document([$block]),
            'options' => ['format' => $format],
            'expected' => $expected,
        ];
    }
}

$codeBlockVariants = [
    'id class data packet' => [
        $codeBlock('echo alpha', [
            'id' => 'src',
            'classes' => ['php'],
            'attributes' => ['data-kind' => 'handoff'],
        ]),
        '<pre><code id="src" class="php" data-kind="handoff">echo alpha</code></pre>',
    ],
    'multiple classes' => [
        $codeBlock('return true;', [
            'classes' => ['php', 'numberLines'],
        ]),
        '<pre><code class="php numberLines">return true;</code></pre>',
    ],
    'key value only' => [
        $codeBlock('console.log(source)', [
            'attributes' => ['data-runtime' => 'node'],
        ]),
        '<pre><code data-runtime="node">console.log(source)</code></pre>',
    ],
    'id only' => [
        $codeBlock('wp option get home', [
            'id' => 'wp-cli-snippet',
        ]),
        '<pre><code id="wp-cli-snippet">wp option get home</code></pre>',
    ],
    'escaped html text' => [
        $codeBlock('echo <review> & status;', [
            'classes' => ['php', 'unsafe'],
        ]),
        '<pre><code class="php unsafe">echo &lt;review&gt; &amp; status;</code></pre>',
    ],
    'locale attributes' => [
        $codeBlock('printf fuente', [
            'classes' => ['bash'],
            'lang' => 'es',
            'attributes' => ['data-source' => 'localized'],
        ]),
        '<pre><code class="bash" data-source="localized" lang="es">printf fuente</code></pre>',
    ],
    'title attribute' => [
        $codeBlock('SELECT 1;', [
            'classes' => ['sql'],
            'title' => 'Review query',
            'attributes' => ['data-db' => 'staging'],
        ]),
        '<pre><code class="sql" data-db="staging" title="Review query">SELECT 1;</code></pre>',
    ],
];

foreach ($attributeFallbackFormats as $format) {
    foreach ($codeBlockVariants as $label => [$block, $expected]) {
        $cases["code block attributes fallback {$format} {$label}"] = [
            'document' => $document([$block]),
            'options' => ['format' => $format],
            'expected' => $expected,
        ];
    }
}

$divVariants = [
    'paragraph strong packet' => [
        $div([
            $paragraph([$text('Body '), $strong('strong')]),
        ], [
            'id' => 'review-div',
            'classes' => ['review', 'packet'],
            'attributes' => ['data-kind' => 'handoff'],
        ]),
        '<div id="review-div" class="review packet" data-kind="handoff"><p>Body <strong>strong</strong></p></div>',
    ],
    'nested blockquote packet' => [
        $div([
            $blockquote([$paragraph([$text('Quoted packet')])]),
        ], [
            'classes' => ['source'],
            'attributes' => ['data-depth' => 'quote'],
        ]),
        '<div class="source" data-depth="quote"><blockquote><p>Quoted packet</p></blockquote></div>',
    ],
    'empty metadata packet' => [
        $div([], [
            'attributes' => ['data-empty' => 'true'],
        ]),
        '<div data-empty="true"></div>',
    ],
    'section like packet' => [
        $div([
            $heading(1, [$text('Imported Article')]),
            $paragraph([$text('Lead body')]),
        ], [
            'id' => 'article',
            'classes' => ['section', 'level1'],
            'attributes' => ['data-source' => 'batch-42'],
        ]),
        '<div id="article" class="section level1" data-source="batch-42"><h1>Imported Article</h1><p>Lead body</p></div>',
    ],
];

foreach ($containerFallbackFormats as $format) {
    foreach ($divVariants as $label => [$block, $expected]) {
        $cases["div container fallback {$format} {$label}"] = [
            'document' => $document([$block]),
            'options' => ['format' => $format],
            'expected' => $expected,
        ];
    }
}

$lineBlockVariants = [
    'plain line block' => [
        $lineBlock([$line('Alpha'), $line('Beta')]),
        "<div class=\"line-block\">Alpha<br />\nBeta</div>",
    ],
    'attributed line block' => [
        $lineBlock([$line('First'), $line('Second')], [
            'id' => 'poem',
            'classes' => ['verse'],
            'attributes' => ['data-kind' => 'poem'],
        ]),
        "<div id=\"poem\" class=\"line-block verse\" data-kind=\"poem\">First<br />\nSecond</div>",
    ],
];

foreach ($containerFallbackFormats as $format) {
    foreach ($lineBlockVariants as $label => [$block, $expected]) {
        $cases["line block fallback {$format} {$label}"] = [
            'document' => $document([$block]),
            'options' => ['format' => $format],
            'expected' => $expected,
        ];
    }
}

$overrideCases = [
    'commonmark header attributes override keeps tuple' => [
        $document([
            $heading(2, [$text('Review')], [
                'id' => 'review',
                'classes' => ['packet'],
                'attributes' => ['data-kind' => 'handoff'],
            ]),
        ]),
        ['format' => 'commonmark+header_attributes'],
        '## Review {#review .packet data-kind="handoff"}',
    ],
    'gfm header attributes override keeps tuple' => [
        $document([
            $heading(1, [$text('GFM Review')], [
                'id' => 'gfm-review',
            ]),
        ]),
        ['format' => 'gfm+header_attributes'],
        '# GFM Review {#gfm-review}',
    ],
    'commonmark fenced code attributes override keeps tuple' => [
        $document([
            $codeBlock('echo alpha', [
                'id' => 'src',
                'classes' => ['php'],
                'attributes' => ['data-kind' => 'handoff'],
            ]),
        ]),
        ['format' => 'commonmark+fenced_code_attributes'],
        "```{#src .php data-kind=\"handoff\"}\necho alpha\n```",
    ],
    'markdown strict fenced code attributes override keeps tuple' => [
        $document([
            $codeBlock('SELECT 1;', [
                'classes' => ['sql', 'numberLines'],
            ]),
        ]),
        ['format' => 'markdown_strict+fenced_code_attributes'],
        "```{.sql .numberLines}\nSELECT 1;\n```",
    ],
    'commonmark fenced div override keeps container' => [
        $document([
            $div([$paragraph([$text('Body')])], ['classes' => ['review']]),
        ]),
        ['format' => 'commonmark+fenced_divs'],
        "::: {.review}\nBody\n:::",
    ],
    'markdown mmd native div alias override keeps container' => [
        $document([
            $div([$paragraph([$text('Body')])], ['id' => 'mmd-div']),
        ]),
        ['format' => 'markdown_mmd+native_divs'],
        "::: {#mmd-div}\nBody\n:::",
    ],
    'commonmark line blocks override keeps pipe lines' => [
        $document([
            $lineBlock([$line('Alpha'), $line('Beta')]),
        ]),
        ['format' => 'commonmark+line_blocks'],
        "| Alpha\n| Beta",
    ],
    'gfm fenced div override keeps section container' => [
        $document([
            $div([
                $heading(1, [$text('Article')]),
            ], [
                'id' => 'article',
                'classes' => ['section', 'level1'],
            ]),
        ]),
        ['format' => 'gfm+fenced_divs'],
        "::: {#article .section .level1}\n# Article\n:::",
    ],
];

foreach ($overrideCases as $label => [$doc, $options, $expected]) {
    $cases['extension override ' . $label] = [
        'document' => $doc,
        'options' => $options,
        'expected' => $expected,
    ];
}

$tests = [
    'records markdown writer container attribute surge mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(83, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer container attribute surge ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

            $t->same($case['expected'], $markdown);
        };
}

return $tests;
