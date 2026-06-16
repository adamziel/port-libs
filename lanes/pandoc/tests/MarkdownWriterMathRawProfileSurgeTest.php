<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$inline = static fn (string $type, array $children = [], array $attrs = []): AstNode => new AstNode($type, $attrs, $children);
$case = static fn (array $children, string $expected, array $options = []): array => [
    'document' => $document([$paragraph($children)]),
    'expected' => $expected,
    'options' => $options,
];
$blockCase = static fn (AstNode $block, string $expected, array $options = []): array => [
    'document' => $document([$block]),
    'expected' => $expected,
    'options' => $options,
];

$cases = [];

$mathFallbackVariants = [
    'inline id class data escapes html text' => [
        'node' => $inline('math', [], [
            'text' => 'a < b & c',
            'id' => 'eq-alpha',
            'classes' => ['physics', 'review'],
            'attributes' => ['data-source' => 'manual'],
        ]),
        'expected' => '<span id="eq-alpha" class="math inline physics review" data-source="manual">a &lt; b &amp; c</span>',
    ],
    'display id class data' => [
        'node' => $inline('math', [], [
            'text' => 'x = y',
            'display' => true,
            'id' => 'eq-display',
            'classes' => ['physics'],
            'attributes' => ['data-source' => 'manual'],
        ]),
        'expected' => '<span id="eq-display" class="math display physics" data-source="manual">x = y</span>',
    ],
    'top level semantic attributes' => [
        'node' => $inline('math', [], [
            'text' => 'rtl + ltr',
            'dir' => 'rtl',
            'lang' => 'ar',
            'role' => 'math',
            'title' => 'Equation title',
        ]),
        'expected' => '<span class="math inline" dir="rtl" lang="ar" role="math" title="Equation title">rtl + ltr</span>',
    ],
    'html attributes merge before native classes' => [
        'node' => $inline('math', [], [
            'text' => 'html attrs',
            'id' => 'ignored-id',
            'classes' => ['native-class'],
            'attributes' => ['data-source' => 'native'],
            'htmlAttributes' => ['id' => 'html-eq', 'class' => 'html-class', 'data-source' => 'html'],
        ]),
        'expected' => '<span id="html-eq" class="math inline html-class native-class" data-source="html">html attrs</span>',
    ],
    'unsafe html attributes are filtered' => [
        'node' => $inline('math', [], [
            'text' => 'safe',
            'classes' => ['filtered'],
            'htmlAttributes' => ['onclick' => 'alert(1)', 'style' => 'background:url(javascript:bad)', 'data-ok' => 'yes'],
        ]),
        'expected' => '<span class="math inline filtered" data-ok="yes">safe</span>',
    ],
    'formula payload is serialized' => [
        'node' => $inline('math', [], ['formula' => 'f(x) = x^2']),
        'expected' => '<span class="math inline">f(x) = x^2</span>',
    ],
    'math payload is serialized' => [
        'node' => $inline('math', [], ['math' => 'm+n']),
        'expected' => '<span class="math inline">m+n</span>',
    ],
    'literal payload escapes quotes' => [
        'node' => $inline('math', [], ['literal' => '"quoted" <math>']),
        'expected' => '<span class="math inline">&quot;quoted&quot; &lt;math&gt;</span>',
    ],
];

foreach (['commonmark', 'gfm'] as $profile) {
    foreach ($mathFallbackVariants as $name => $variant) {
        $cases["{$profile} math html fallback {$name}"] = $case(
            [$variant['node']],
            $variant['expected'],
            ['format' => $profile]
        );
    }
}

$rawAttributeGateCases = [
    'commonmark plus math no attrs keeps dollars' => $case([
        $inline('math', [], ['text' => 'x + y']),
    ], '$x + y$', ['format' => 'commonmark+tex_math_dollars']),
    'commonmark plus math attrs fall back to html' => $case([
        $inline('math', [], ['text' => 'x + y', 'id' => 'eq', 'classes' => ['physics']]),
    ], '<span id="eq" class="math inline physics">x + y</span>', ['format' => 'commonmark+tex_math_dollars']),
    'commonmark plus display attrs fall back to html' => $case([
        $inline('math', [], ['text' => 'x = y', 'display' => true, 'attributes' => ['data-eq' => 'one']]),
    ], '<span class="math display" data-eq="one">x = y</span>', ['format' => 'commonmark+tex_math_dollars']),
    'commonmark plus raw attribute keeps inline attr tuple' => $case([
        $inline('math', [], ['text' => 'x + y', 'id' => 'eq']),
    ], '$x + y${#eq}', ['format' => 'commonmark+tex_math_dollars+raw_attribute']),
    'commonmark plus raw attribute keeps display attr tuple' => $case([
        $inline('math', [], ['text' => 'x = y', 'display' => true, 'classes' => ['physics']]),
    ], '$$x = y$${.physics}', ['format' => 'commonmark+tex_math_dollars+raw_attribute']),
    'gfm plus math no attrs keeps dollars' => $case([
        $inline('math', [], ['text' => 'x + y']),
    ], '$x + y$', ['format' => 'gfm+tex_math_dollars']),
    'gfm plus math attrs fall back to html' => $case([
        $inline('math', [], ['text' => 'x + y', 'id' => 'eq', 'attributes' => ['data-source' => 'gfm']]),
    ], '<span id="eq" class="math inline" data-source="gfm">x + y</span>', ['format' => 'gfm+tex_math_dollars']),
    'gfm plus display attrs fall back to html' => $case([
        $inline('math', [], ['text' => 'x = y', 'display' => true, 'classes' => ['physics']]),
    ], '<span class="math display physics">x = y</span>', ['format' => 'gfm+tex_math_dollars']),
    'gfm plus raw attribute keeps inline attr tuple' => $case([
        $inline('math', [], ['text' => 'x + y', 'attributes' => ['data-source' => 'gfm']]),
    ], '$x + y${data-source="gfm"}', ['format' => 'gfm+tex_math_dollars+raw_attribute']),
    'gfm plus raw attribute keeps display attr tuple' => $case([
        $inline('math', [], ['text' => 'x = y', 'display' => true, 'id' => 'eq']),
    ], '$$x = y$${#eq}', ['format' => 'gfm+tex_math_dollars+raw_attribute']),
    'markdown minus raw attribute falls back for inline attrs' => $case([
        $inline('math', [], ['text' => 'x + y', 'id' => 'eq']),
    ], '<span id="eq" class="math inline">x + y</span>', ['format' => 'markdown-raw_attribute']),
    'markdown minus raw attribute still keeps plain dollars' => $case([
        $inline('math', [], ['text' => 'x + y']),
    ], '$x + y$', ['format' => 'markdown-raw_attribute']),
    'pandoc minus raw attribute falls back for display attrs' => $case([
        $inline('math', [], ['text' => 'x = y', 'display' => true, 'id' => 'eq']),
    ], '<span id="eq" class="math display">x = y</span>', ['format' => 'pandoc-raw_attribute']),
    'commonmark x raw attribute default keeps inline attr tuple' => $case([
        $inline('math', [], ['text' => 'x + y', 'id' => 'eq']),
    ], '$x + y${#eq}', ['format' => 'commonmark_x']),
    'commonmark x minus raw attribute falls back for inline attrs' => $case([
        $inline('math', [], ['text' => 'x + y', 'id' => 'eq']),
    ], '<span id="eq" class="math inline">x + y</span>', ['format' => 'commonmark_x-raw_attribute']),
    'option rawAttribute false falls back for inline attrs' => $case([
        $inline('math', [], ['text' => 'x + y', 'classes' => ['physics']]),
    ], '<span class="math inline physics">x + y</span>', ['rawAttribute' => false]),
    'extension raw attribute false falls back for inline attrs' => $case([
        $inline('math', [], ['text' => 'x + y', 'classes' => ['physics']]),
    ], '<span class="math inline physics">x + y</span>', ['extensions' => ['-raw_attribute']]),
    'extension raw attribute true keeps inline attr tuple' => $case([
        $inline('math', [], ['text' => 'x + y', 'classes' => ['physics']]),
    ], '$x + y${.physics}', ['format' => 'gfm+tex_math_dollars', 'extensions' => ['+raw_attribute']]),
];
$cases += array_combine(
    array_map(static fn (string $name): string => 'raw attribute math gate ' . $name, array_keys($rawAttributeGateCases)),
    array_values($rawAttributeGateCases)
);

$rawInlineMatrix = [
    'commonmark' => [
        'markdown' => '*generic*',
        'commonmark' => '*commonmark*',
        'gfm' => '',
        'markdown_github' => '',
        'latex' => '',
        'html' => '<span>html</span>',
    ],
    'gfm' => [
        'markdown' => '*generic*',
        'commonmark' => '*commonmark*',
        'gfm' => '*gfm*',
        'markdown_github' => '*github*',
        'latex' => '',
        'html' => '<span>html</span>',
    ],
    'commonmark_x' => [
        'markdown' => '*generic*',
        'commonmark' => '*commonmark*',
        'gfm' => '*gfm*',
        'markdown_github' => '*github*',
        'latex' => '\\LaTeX{}',
        'html' => '<span>html</span>',
    ],
    'markdown' => [
        'markdown' => '*generic*',
        'commonmark' => '',
        'gfm' => '',
        'markdown_github' => '',
        'latex' => '\\LaTeX{}',
        'html' => '<span>html</span>',
    ],
];

foreach ($rawInlineMatrix as $target => $formats) {
    foreach ($formats as $format => $expected) {
        $attrs = ['format' => $format, 'text' => $expected !== '' ? $expected : 'dropped'];
        if ($format === 'html') {
            $attrs['html'] = $attrs['text'];
        } elseif ($format === 'latex') {
            $attrs['tex'] = $attrs['text'];
        } else {
            $attrs['markdown'] = $attrs['text'];
        }

        $cases["raw inline profile {$target} accepts {$format}"] = $case([
            $inline('raw_inline', [], $attrs),
        ], $expected, ['format' => $target]);
    }
}

$rawBlockMatrix = [
    'commonmark' => [
        'markdown' => 'generic block',
        'commonmark' => 'commonmark block',
        'gfm' => '',
        'latex' => '',
        'html' => '<div>html block</div>',
    ],
    'gfm' => [
        'markdown' => 'generic block',
        'commonmark' => 'commonmark block',
        'gfm' => 'gfm block',
        'latex' => '',
        'html' => '<div>html block</div>',
    ],
    'commonmark_x' => [
        'markdown' => 'generic block',
        'commonmark' => 'commonmark block',
        'gfm' => 'gfm block',
        'latex' => '\\LaTeX{}',
        'html' => '<div>html block</div>',
    ],
    'markdown' => [
        'markdown' => 'generic block',
        'commonmark' => '',
        'gfm' => '',
        'latex' => '\\LaTeX{}',
        'html' => '<div>html block</div>',
    ],
];

foreach ($rawBlockMatrix as $target => $formats) {
    foreach ($formats as $format => $expected) {
        $attrs = ['format' => $format, 'text' => $expected !== '' ? $expected : 'dropped'];
        if ($format === 'html') {
            $attrs['html'] = $attrs['text'];
        } elseif ($format === 'latex') {
            $attrs['tex'] = $attrs['text'];
        } else {
            $attrs['markdown'] = $attrs['text'];
        }

        $cases["raw block profile {$target} accepts {$format}"] = $blockCase(
            new AstNode('raw_block', $attrs),
            $expected,
            ['format' => $target]
        );
    }
}

$spanProfileCases = [
    'markdown span attributes stay bracketed' => $case([
        $inline('span', [$text('packet')], ['id' => 's', 'classes' => ['review'], 'attributes' => ['data-id' => '1']]),
    ], '[packet]{#s .review data-id="1"}', ['format' => 'markdown']),
    'commonmark span attributes use html fallback' => $case([
        $inline('span', [$text('packet')], ['id' => 's', 'classes' => ['review'], 'attributes' => ['data-id' => '1']]),
    ], '<span id="s" class="review" data-id="1">packet</span>', ['format' => 'commonmark']),
    'gfm span attributes use html fallback' => $case([
        $inline('span', [$text('packet')], ['classes' => ['review']]),
    ], '<span class="review">packet</span>', ['format' => 'gfm']),
    'commonmark bracketed spans extension keeps tuple' => $case([
        $inline('span', [$text('packet')], ['classes' => ['review']]),
    ], '[packet]{.review}', ['format' => 'commonmark+bracketed_spans']),
    'gfm bracketed spans extension keeps tuple' => $case([
        $inline('span', [$text('packet')], ['classes' => ['review']]),
    ], '[packet]{.review}', ['format' => 'gfm+bracketed_spans']),
    'commonmark html span preserves raw html child' => $case([
        $inline('span', [$inline('raw_html_inline', [], ['html' => '<kbd>Esc</kbd>'])], ['classes' => ['raw']]),
    ], '<span class="raw"><kbd>Esc</kbd></span>', ['format' => 'commonmark']),
    'commonmark bracketed span preserves raw markdown child' => $case([
        $inline('span', [$inline('raw_markdown', [], ['text' => '**raw**'])], ['classes' => ['raw']]),
    ], '[**raw**]{.raw}', ['format' => 'commonmark+bracketed_spans']),
    'gfm bracketed span wraps html math fallback child' => $case([
        $inline('span', [$inline('math', [], ['text' => 'x + y'])], ['classes' => ['math-wrap']]),
    ], '[<span class="math inline">x + y</span>]{.math-wrap}', ['format' => 'gfm+bracketed_spans']),
    'gfm bracketed span keeps attributed dollar math with raw attribute' => $case([
        $inline('span', [$inline('math', [], ['text' => 'x + y', 'id' => 'eq'])], ['classes' => ['math-wrap']]),
    ], '[$x + y${#eq}]{.math-wrap}', ['format' => 'gfm+bracketed_spans+tex_math_dollars+raw_attribute']),
    'commonmark bracketed span uses html for attributed math without raw attribute' => $case([
        $inline('span', [$inline('math', [], ['text' => 'x + y', 'id' => 'eq'])], ['classes' => ['math-wrap']]),
    ], '[<span id="eq" class="math inline">x + y</span>]{.math-wrap}', ['format' => 'commonmark+bracketed_spans+tex_math_dollars']),
];
$cases += array_combine(
    array_map(static fn (string $name): string => 'span math raw profile ' . $name, array_keys($spanProfileCases)),
    array_values($spanProfileCases)
);

$tests = [
    'records markdown writer math raw profile surge mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(88, count($cases));
    },
];

foreach ($cases as $label => $item) {
    $tests['maps upstream markdown writer math raw profile surge ' . $label] =
        static function (TestRunner $t) use ($item): void {
            $markdown = (new MarkdownWriter($item['options']))->write($item['document']);

            $t->same($item['expected'], $markdown);
        };
}

return $tests;
