<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array|string $children, array $attrs = []): AstNode => new AstNode(
    'paragraph',
    $attrs,
    is_string($children) ? [$text($children)] : $children
);
$plain = static fn (array|string $children, array $attrs = []): AstNode => new AstNode(
    'plain',
    $attrs,
    is_string($children) ? [$text($children)] : $children
);
$cell = static function (array|string $children, array $attrs = []) use ($text): AstNode {
    if (is_string($children)) {
        return new AstNode('table_cell', array_merge(['text' => $children], $attrs), [$text($children)]);
    }

    return new AstNode('table_cell', $attrs, $children);
};
$row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);
$head = static fn (array $rows, array $attrs = []): AstNode => new AstNode('table_head', $attrs, $rows);
$body = static fn (array $rows, array $attrs = []): AstNode => new AstNode('table_body', $attrs, $rows);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$writeDocument = static fn (AstNode $node): string => (new MarkdownWriter(['htmlTableAutoFallback' => true]))->write($document([$node]));
$table = static fn (array $sections, array $attrs = []): AstNode => new AstNode(
    'table',
    $attrs + ['alignments' => ['left', 'right']],
    $sections
);
$twoColumnTable = static fn (AstNode $valueCell, array $attrs = []) => $table([
    $head([$row([$cell('Metric'), $cell('Value')])]),
    $body([$row([$cell('Probe'), $valueCell])]),
], $attrs);
$line = static function (array|string $children, array $attrs = []) use ($text): AstNode {
    if (is_string($children)) {
        return new AstNode('line', array_merge(['text' => $children], $attrs), $children === '' ? [] : [$text($children)]);
    }

    return new AstNode('line', $attrs, $children);
};
$lineBlock = static fn (array $lines, array $attrs = []): AstNode => new AstNode('line_block', $attrs, $lines);
$definition = static fn (array $blocks, array $attrs = []): AstNode => new AstNode('definition', $attrs, $blocks);
$term = static function (array|string $children, array $attrs = []) use ($text): AstNode {
    if (is_string($children)) {
        return new AstNode('definition_term', array_merge(['text' => $children], $attrs), [$text($children)]);
    }

    return new AstNode('definition_term', $attrs, $children);
};
$definitionItem = static fn (AstNode $term, array $definitions, array $attrs = []): AstNode => new AstNode(
    'definition_item',
    $attrs,
    [$term, ...$definitions]
);
$definitionList = static fn (array $items, array $attrs = []): AstNode => new AstNode('definition_list', $attrs, $items);
$simpleDefinitionList = static fn (string $termText, string $definitionText, array $attrs = []) => $definitionList([
    $definitionItem($term($termText), [$definition([$paragraph($definitionText)])]),
], $attrs);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$bulletList = static fn (array $items, array $attrs = []): AstNode => new AstNode('bullet_list', $attrs, $items);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$image = static fn (array $attrs = [], array $children = []): AstNode => new AstNode('image', $attrs, $children);
$figure = static fn (array $children, array $attrs = []): AstNode => new AstNode('figure', $attrs, $children);
$assertAutoHtmlTable = static function (TestRunner $t, AstNode $table, array $contains, array $forbid = []) use ($writeDocument): void {
    $markdown = $writeDocument($table);

    $t->contains('<table', $markdown);
    foreach ($contains as $expected) {
        $t->contains($expected, $markdown);
    }
    foreach ($forbid as $forbidden) {
        $t->true(!str_contains($markdown, $forbidden), "Auto HTML table should not emit {$forbidden}");
    }
};

$tests = [];

$cellBlockCases = [
    'definition list simple term paragraph' => [
        'cell' => static fn () => $cell([$simpleDefinitionList('Term', 'Definition')]),
        'contains' => ['<dl><dt>Term</dt><dd><p>Definition</p></dd></dl>'],
    ],
    'definition list preserves dl identity attributes' => [
        'cell' => static fn () => $cell([$simpleDefinitionList('Term', 'Definition', ['id' => 'glossary', 'classes' => ['review'], 'attributes' => ['data-kind' => 'glossary']])]),
        'contains' => ['<dl id="glossary" class="review" data-kind="glossary">'],
    ],
    'definition list term inline emphasis' => [
        'cell' => static fn () => $cell([$definitionList([
            $definitionItem($term([new AstNode('emph', [], [$text('Term')])]), [$definition([$paragraph('Definition')])]),
        ])]),
        'contains' => ['<dt><em>Term</em></dt>'],
    ],
    'definition list term hard break' => [
        'cell' => static fn () => $cell([$definitionList([
            $definitionItem($term([$text('First'), new AstNode('linebreak'), $text('Second')]), [$definition([$paragraph('Definition')])]),
        ])]),
        'contains' => ['<dt>First<br />Second</dt>'],
    ],
    'definition list term source attributes' => [
        'cell' => static fn () => $cell([$definitionList([
            $definitionItem($term('Term', ['id' => 'term-id', 'classes' => ['source']]), [$definition([$paragraph('Definition')])]),
        ])]),
        'contains' => ['<dt id="term-id" class="source">Term</dt>'],
    ],
    'definition list definition source attributes' => [
        'cell' => static fn () => $cell([$definitionList([
            $definitionItem($term('Term'), [$definition([$paragraph('Definition')], ['classes' => ['source-dd'], 'attributes' => ['data-state' => 'kept']])]),
        ])]),
        'contains' => ['<dd class="source-dd" data-state="kept"><p>Definition</p></dd>'],
    ],
    'definition list multiple definitions' => [
        'cell' => static fn () => $cell([$definitionList([
            $definitionItem($term('Term'), [$definition([$paragraph('First')]), $definition([$paragraph('Second')])]),
        ])]),
        'contains' => ['<dd><p>First</p></dd><dd><p>Second</p></dd>'],
    ],
    'definition list bullet body' => [
        'cell' => static fn () => $cell([$definitionList([
            $definitionItem($term('Term'), [$definition([$bulletList([$listItem([$paragraph('One')])])])]),
        ])]),
        'contains' => ['<dd><ul><li><p>One</p></li></ul></dd>'],
    ],
    'definition list ordered body start' => [
        'cell' => static fn () => $cell([$definitionList([
            $definitionItem($term('Term'), [$definition([$orderedList([$listItem([$paragraph('Three')])], ['start' => 3])])]),
        ])]),
        'contains' => ['<ol start="3"><li><p>Three</p></li></ol>'],
    ],
    'definition list blockquote body' => [
        'cell' => static fn () => $cell([$definitionList([
            $definitionItem($term('Term'), [$definition([new AstNode('blockquote', [], [$paragraph('Quote')])])]),
        ])]),
        'contains' => ['<blockquote><p>Quote</p></blockquote>'],
    ],
    'definition list code body' => [
        'cell' => static fn () => $cell([$definitionList([
            $definitionItem($term('Term'), [$definition([new AstNode('code_block', ['text' => '<code>', 'classes' => ['php']])])]),
        ])]),
        'contains' => ['<pre><code class="php">&lt;code&gt;</code></pre>'],
    ],
    'definition list nested line block' => [
        'cell' => static fn () => $cell([$definitionList([
            $definitionItem($term('Term'), [$definition([$lineBlock([$line('A'), $line('B')])])]),
        ])]),
        'contains' => ['<div class="line-block">A<br />B</div>'],
    ],
    'definition list nested figure' => [
        'cell' => static fn () => $cell([$definitionList([
            $definitionItem($term('Term'), [$definition([$figure([$image(['url' => 'media/a.png', 'alt' => 'A'])], ['caption' => 'Nested'])])]),
        ])]),
        'contains' => ['<figure><img src="media/a.png" alt="A" /><figcaption>Nested</figcaption></figure>'],
    ],
    'definition list raw html body' => [
        'cell' => static fn () => $cell([$definitionList([
            $definitionItem($term('Term'), [$definition([new AstNode('raw_html', ['html' => '<aside data-note="1">Raw</aside>'])])]),
        ])]),
        'contains' => ['<aside data-note="1">Raw</aside>'],
    ],
    'definition list fallback term attribute' => [
        'cell' => static fn () => $cell([$definitionList([
            new AstNode('definition_item', ['term' => 'Fallback term'], [$definition([$paragraph('Fallback definition')])]),
        ])]),
        'contains' => ['<dt>Fallback term</dt><dd><p>Fallback definition</p></dd>'],
    ],
    'line block simple lines' => [
        'cell' => static fn () => $cell([$lineBlock([$line('First'), $line('Second')])]),
        'contains' => ['<div class="line-block">First<br />Second</div>'],
    ],
    'line block class merge' => [
        'cell' => static fn () => $cell([$lineBlock([$line('Verse')], ['classes' => ['verse']])]),
        'contains' => ['<div class="line-block verse">Verse</div>'],
    ],
    'line block id data attributes' => [
        'cell' => static fn () => $cell([$lineBlock([$line('Line')], ['id' => 'line-id', 'attributes' => ['data-source' => 'reader']])]),
        'contains' => ['<div id="line-id" class="line-block" data-source="reader">Line</div>'],
    ],
    'line block inline emphasis strong' => [
        'cell' => static fn () => $cell([$lineBlock([
            $line([new AstNode('emph', [], [$text('First')])]),
            $line([new AstNode('strong', [], [$text('Second')])]),
        ])]),
        'contains' => ['<div class="line-block"><em>First</em><br /><strong>Second</strong></div>'],
    ],
    'line block empty source line' => [
        'cell' => static fn () => $cell([$lineBlock([$line('First'), $line(''), $line('Third')])]),
        'contains' => ['<div class="line-block">First<br /><br />Third</div>'],
    ],
    'line block raw html inline' => [
        'cell' => static fn () => $cell([$lineBlock([$line([$text('Raw '), new AstNode('raw_html_inline', ['html' => '<span data-x="1">ok</span>'])])])]),
        'contains' => ['Raw <span data-x="1">ok</span>'],
    ],
    'line block math inline' => [
        'cell' => static fn () => $cell([$lineBlock([$line([new AstNode('math', ['text' => 'a < b'])])])]),
        'contains' => ['<span class="math inline">a &lt; b</span>'],
    ],
    'line block code inline' => [
        'cell' => static fn () => $cell([$lineBlock([$line([$text('Use '), new AstNode('code', ['text' => '<tag>'])])])]),
        'contains' => ['Use <code>&lt;tag&gt;</code>'],
    ],
    'figure image caption' => [
        'cell' => static fn () => $cell([$figure([$image(['url' => 'media/chart.png', 'alt' => 'Chart'])], ['caption' => 'Chart caption'])]),
        'contains' => ['<figure><img src="media/chart.png" alt="Chart" /><figcaption>Chart caption</figcaption></figure>'],
    ],
    'figure source attributes' => [
        'cell' => static fn () => $cell([$figure([$image(['url' => 'media/chart.png', 'alt' => 'Chart'])], ['id' => 'fig-id', 'classes' => ['review'], 'attributes' => ['data-kind' => 'figure'], 'caption' => 'Chart'])]),
        'contains' => ['<figure id="fig-id" class="review" data-kind="figure"><img src="media/chart.png" alt="Chart" /><figcaption>Chart</figcaption></figure>'],
    ],
    'figure image attributes' => [
        'cell' => static fn () => $cell([$figure([$image(['url' => 'media/chart.png', 'alt' => 'Chart', 'title' => 'Title', 'classes' => ['thumb']])], ['caption' => 'Chart'])]),
        'contains' => ['<img class="thumb" src="media/chart.png" alt="Chart" title="Title" />'],
    ],
    'figure caption inlines' => [
        'cell' => static fn () => $cell([$figure([$image(['url' => 'media/chart.png', 'alt' => 'Chart'])], ['captionInlines' => [$text('Chart '), new AstNode('strong', [], [$text('caption')])]])]),
        'contains' => ['<figcaption>Chart <strong>caption</strong></figcaption>'],
    ],
    'figure caption blocks' => [
        'cell' => static fn () => $cell([$figure([$image(['url' => 'media/chart.png', 'alt' => 'Chart'])], ['captionBlocks' => [$paragraph([$text('Block '), new AstNode('emph', [], [$text('caption')])])]])]),
        'contains' => ['<figcaption><p>Block <em>caption</em></p></figcaption>'],
    ],
    'figure paragraph content' => [
        'cell' => static fn () => $cell([$figure([$paragraph('Standalone figure content')], ['caption' => 'Content caption'])]),
        'contains' => ['<figure><p>Standalone figure content</p><figcaption>Content caption</figcaption></figure>'],
    ],
    'figure multiple content blocks' => [
        'cell' => static fn () => $cell([$figure([$paragraph('Intro'), new AstNode('code_block', ['text' => 'code'])], ['caption' => 'Code figure'])]),
        'contains' => ['<figure><p>Intro</p><pre><code>code</code></pre><figcaption>Code figure</figcaption></figure>'],
    ],
    'figure raw html content' => [
        'cell' => static fn () => $cell([$figure([new AstNode('raw_html', ['html' => '<svg data-chart="1"></svg>'])], ['caption' => 'Raw figure'])]),
        'contains' => ['<svg data-chart="1"></svg><figcaption>Raw figure</figcaption>'],
    ],
    'figure without caption' => [
        'cell' => static fn () => $cell([$figure([$image(['url' => 'media/plain.png', 'alt' => 'Plain'])])]),
        'contains' => ['<figure><img src="media/plain.png" alt="Plain" /></figure>'],
        'forbid' => ['<figcaption>'],
    ],
    'figure image alt from children' => [
        'cell' => static fn () => $cell([$figure([$image(['url' => 'media/child.png'], [$text('Child alt')])], ['caption' => 'Child'])]),
        'contains' => ['<img src="media/child.png" alt="Child alt" />'],
    ],
    'figure nested definition content' => [
        'cell' => static fn () => $cell([$figure([$simpleDefinitionList('Figure term', 'Figure definition')], ['caption' => 'Definition figure'])]),
        'contains' => ['<figure><dl><dt>Figure term</dt><dd><p>Figure definition</p></dd></dl><figcaption>Definition figure</figcaption></figure>'],
    ],
    'paragraph source attributes force html cell preservation' => [
        'cell' => static fn () => $cell([$paragraph('Attributed paragraph', ['id' => 'para-id', 'classes' => ['review']])]),
        'contains' => ['<p id="para-id" class="review">Attributed paragraph</p>'],
    ],
    'blockquote source attributes force html cell preservation' => [
        'cell' => static fn () => $cell([new AstNode('blockquote', ['classes' => ['quote'], 'attributes' => ['data-source' => 'reader']], [$paragraph('Quote')])]),
        'contains' => ['<blockquote class="quote" data-source="reader"><p>Quote</p></blockquote>'],
    ],
];

foreach ($cellBlockCases as $label => $case) {
    $tests["maps upstream markdown writer auto html rich table cell {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $assertAutoHtmlTable): void {
            $assertAutoHtmlTable($t, $twoColumnTable($case['cell']()), $case['contains'], $case['forbid'] ?? []);
        };
}

$captionBlockCases = [
    'definition list caption block' => [
        'attrs' => ['captionBlocks' => [$simpleDefinitionList('Caption term', 'Caption definition')]],
        'contains' => ['<caption><dl><dt>Caption term</dt><dd><p>Caption definition</p></dd></dl></caption>'],
    ],
    'line block caption block' => [
        'attrs' => ['captionBlocks' => [$lineBlock([$line('Caption A'), $line('Caption B')])]],
        'contains' => ['<caption><div class="line-block">Caption A<br />Caption B</div></caption>'],
    ],
    'figure caption block' => [
        'attrs' => ['captionBlocks' => [$figure([$image(['url' => 'media/caption.png', 'alt' => 'Caption image'])], ['caption' => 'Nested caption figure'])]],
        'contains' => ['<caption><figure><img src="media/caption.png" alt="Caption image" /><figcaption>Nested caption figure</figcaption></figure></caption>'],
    ],
    'bullet list caption block' => [
        'attrs' => ['captionBlocks' => [$bulletList([$listItem([$paragraph('One')])])]],
        'contains' => ['<caption><ul><li><p>One</p></li></ul></caption>'],
    ],
    'ordered list caption block' => [
        'attrs' => ['captionBlocks' => [$orderedList([$listItem([$paragraph('Three')])], ['start' => 3])]],
        'contains' => ['<caption><ol start="3"><li><p>Three</p></li></ol></caption>'],
    ],
    'blockquote caption block' => [
        'attrs' => ['captionBlocks' => [new AstNode('blockquote', [], [$paragraph('Caption quote')])]],
        'contains' => ['<caption><blockquote><p>Caption quote</p></blockquote></caption>'],
    ],
    'code caption block' => [
        'attrs' => ['captionBlocks' => [new AstNode('code_block', ['text' => '<caption-code>'])]],
        'contains' => ['<caption><pre><code>&lt;caption-code&gt;</code></pre></caption>'],
    ],
    'div caption block attributes' => [
        'attrs' => ['captionBlocks' => [new AstNode('div', ['id' => 'caption-div', 'classes' => ['review']], [$paragraph('Caption div')])]],
        'contains' => ['<caption><div id="caption-div" class="review"><p>Caption div</p></div></caption>'],
    ],
    'raw html caption block' => [
        'attrs' => ['captionBlocks' => [new AstNode('raw_html', ['html' => '<span data-caption="1">Raw caption</span>'])]],
        'contains' => ['<caption><span data-caption="1">Raw caption</span></caption>'],
    ],
    'paragraph caption block attributes' => [
        'attrs' => ['captionBlocks' => [$paragraph('Attributed caption', ['classes' => ['caption-source'], 'attributes' => ['data-source' => 'reader']])]],
        'contains' => ['<caption><p class="caption-source" data-source="reader">Attributed caption</p></caption>'],
    ],
    'plain caption block attributes' => [
        'attrs' => ['captionBlocks' => [$plain('Plain attributed caption', ['id' => 'caption-plain'])]],
        'contains' => ['<caption><p id="caption-plain">Plain attributed caption</p></caption>'],
    ],
    'heading caption block' => [
        'attrs' => ['captionBlocks' => [new AstNode('heading', ['level' => 3, 'id' => 'caption-heading'], [$text('Caption heading')])]],
        'contains' => ['<caption><h3 id="caption-heading">Caption heading</h3></caption>'],
    ],
    'nested table caption block' => [
        'attrs' => ['captionBlocks' => [$table([$body([$row([$cell('Nested caption table')])])], ['alignments' => ['left']])]],
        'contains' => ['<td style="text-align:left">Nested caption table</td>'],
    ],
    'caption block with link inline' => [
        'attrs' => ['captionBlocks' => [$paragraph([new AstNode('link', ['url' => 'https://example.test', 'title' => 'Example'], [$text('Example')])], ['attributes' => ['data-link-caption' => 'yes']])]],
        'contains' => ['<caption><p data-link-caption="yes"><a href="https://example.test" title="Example">Example</a></p></caption>'],
    ],
];

foreach ($captionBlockCases as $label => $case) {
    $tests["maps upstream markdown writer auto html table caption block {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $cell, $assertAutoHtmlTable): void {
            $assertAutoHtmlTable($t, $twoColumnTable($cell('Ready'), $case['attrs']), $case['contains']);
        };
}

$tests['records markdown writer table block html completion mapped-case count'] =
    static function (TestRunner $t) use ($cellBlockCases, $captionBlockCases): void {
        $t->same(50, count($cellBlockCases) + count($captionBlockCases));
    };

return $tests;
