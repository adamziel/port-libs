<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$cell = static function (array|string $children, array $attrs = []) use ($text): AstNode {
    if (is_string($children)) {
        return new AstNode('table_cell', array_merge(['text' => $children], $attrs), [$text($children)]);
    }

    return new AstNode('table_cell', $attrs, $children);
};
$row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);
$head = static fn (array $rows, array $attrs = []): AstNode => new AstNode('table_head', $attrs, $rows);
$body = static fn (array $rows, array $attrs = []): AstNode => new AstNode('table_body', $attrs, $rows);
$foot = static fn (array $rows, array $attrs = []): AstNode => new AstNode('table_foot', $attrs, $rows);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$writeDocument = static fn (AstNode $node): string => (new MarkdownWriter())->write($document([$node]));

$table = static function (array $sections, array $attrs = []): AstNode {
    $htmlAttributes = ['data-pandoc-writer' => 'html'];
    if (isset($attrs['htmlAttributes']) && is_array($attrs['htmlAttributes'])) {
        $htmlAttributes = array_merge($htmlAttributes, $attrs['htmlAttributes']);
    }
    $attrs['htmlAttributes'] = $htmlAttributes;
    $attrs += [
        'alignments' => ['left', 'right'],
        'widths' => [0.25, 0.75],
    ];

    return new AstNode('table', $attrs, $sections);
};

$twoColumnTable = static function (AstNode $valueCell, array $attrs = []) use ($table, $head, $body, $row, $cell): AstNode {
    return $table([
        $head([$row([$cell('Metric'), $cell('Value')])]),
        $body([$row([$cell('Probe'), $valueCell])]),
    ], $attrs);
};

$tests = [];

$structureCases = [
    'colspan cell uses raw html table fallback' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Merged', ['colspan' => 2])])]),
        ]),
        'contains' => ['<table data-pandoc-writer="html">', 'colspan="2"', '>Merged</td>'],
    ],
    'rowspan cell preserves vertical span' => [
        'table' => static fn () => $table([
            $body([
                $row([$cell('Group', ['rowspan' => 2]), $cell('One')]),
                $row([$cell('Two')]),
            ]),
        ]),
        'contains' => ['rowspan="2"', '>Group</td>', '>Two</td>'],
    ],
    'rowspan zero extends to section end' => [
        'table' => static fn () => $table([
            $body([
                $row([$cell('All', ['rowspan' => '0']), $cell('One')]),
                $row([$cell('Two')]),
                $row([$cell('Three')]),
            ]),
        ]),
        'contains' => ['rowspan="3"', '>All</td>'],
    ],
    'explicit header cell emits th' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Row head', ['header' => true]), $cell('Value')])]),
        ]),
        'contains' => ['<th scope="row" style="text-align:left">Row head</th>', '<td style="text-align:right">Value</td>'],
    ],
    'body row head columns emit row scopes' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Row label'), $cell('Value')])], ['rowHeadColumns' => 1]),
        ]),
        'contains' => ['<th scope="row" style="text-align:left">Row label</th>', '<td style="text-align:right">Value</td>'],
    ],
    'body head rows stay inside tbody as column headers' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Body'), $cell('42')])], [
                'headRows' => [$row([$cell('Metric'), $cell('Value')])],
            ]),
        ]),
        'contains' => ['<tbody>', '<th scope="col" style="text-align:left">Metric</th>', '<td style="text-align:right">42</td>'],
    ],
    'table foot section remains separate' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Body'), $cell('42')])]),
            $foot([$row([$cell('Total'), $cell('42')])]),
        ]),
        'contains' => ['<tfoot>', '<td style="text-align:left">Total</td>', '</tfoot>'],
    ],
    'multiple body sections remain grouped' => [
        'table' => static fn () => $table([
            $body([$row([$cell('A'), $cell('1')])], ['htmlAttributes' => ['data-group' => 'a']]),
            $body([$row([$cell('B'), $cell('2')])], ['htmlAttributes' => ['data-group' => 'b']]),
        ]),
        'contains' => ['<tbody data-group="a">', '<tbody data-group="b">'],
    ],
    'cell center alignment becomes html style' => [
        'table' => static fn () => $twoColumnTable($cell('Centered', ['align' => 'center'])),
        'contains' => ['<td style="text-align:center">Centered</td>'],
    ],
    'cell top vertical alignment becomes html style' => [
        'table' => static fn () => $twoColumnTable($cell('Top', ['valign' => 'top'])),
        'contains' => ['vertical-align:top'],
    ],
    'cell source style is merged with computed alignment' => [
        'table' => static fn () => $twoColumnTable($cell('Styled', ['htmlAttributes' => ['style' => 'font-weight:bold'], 'align' => 'center'])),
        'contains' => ['style="font-weight:bold; text-align:center"'],
    ],
    'row stored attributes stay on tr' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Row'), $cell('Attrs')], ['id' => 'r1', 'classes' => ['review-row'], 'attributes' => ['data-state' => 'kept']])]),
        ]),
        'contains' => ['<tr id="r1" class="review-row" data-state="kept">'],
    ],
];

foreach ($structureCases as $label => $case) {
    $tests["maps upstream markdown writer html table structure {$label}"] =
        static function (TestRunner $t) use ($case, $writeDocument): void {
            $markdown = $writeDocument($case['table']());
            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }
        };
}

$attributeCases = [
    'table id class data and summary attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'id' => 'inventory',
            'classes' => ['wide', 'review'],
            'htmlAttributes' => ['summary' => 'Migration inventory'],
            'attributes' => ['data-source' => 'batch'],
        ]),
        'contains' => ['<table id="inventory" class="wide review" data-pandoc-writer="html" summary="Migration inventory" data-source="batch">'],
    ],
    'thead stored attributes are preserved' => [
        'table' => static fn () => $table([
            $head([$row([$cell('H'), $cell('V')])], ['classes' => ['head'], 'attributes' => ['data-origin' => 'reader']]),
            $body([$row([$cell('A'), $cell('1')])]),
        ]),
        'contains' => ['<thead class="head" data-origin="reader">'],
    ],
    'tbody stored attributes are preserved' => [
        'table' => static fn () => $table([
            $body([$row([$cell('A'), $cell('1')])], ['id' => 'body-a', 'attributes' => ['data-phase' => 'draft']]),
        ]),
        'contains' => ['<tbody id="body-a" data-phase="draft">'],
    ],
    'tfoot stored attributes are preserved' => [
        'table' => static fn () => $table([
            $body([$row([$cell('A'), $cell('1')])]),
            $foot([$row([$cell('F'), $cell('2')])], ['attributes' => ['data-total' => 'yes']]),
        ]),
        'contains' => ['<tfoot data-total="yes">'],
    ],
    'cell id class and data attributes are preserved' => [
        'table' => static fn () => $twoColumnTable($cell('Cell', ['id' => 'cell-1', 'classes' => ['audit'], 'attributes' => ['data-kind' => 'value']])),
        'contains' => ['<td id="cell-1" class="audit" data-kind="value" style="text-align:right">Cell</td>'],
    ],
    'cell aria and role attributes are preserved' => [
        'table' => static fn () => $twoColumnTable($cell('Term', ['attributes' => ['role' => 'term', 'aria-label' => 'Reviewed term']])),
        'contains' => ['role="term"', 'aria-label="Reviewed term"'],
    ],
    'cell headers scope and abbreviation attributes are preserved' => [
        'table' => static fn () => $twoColumnTable($cell('Value', ['attributes' => ['headers' => 'h1 h2', 'scope' => 'row', 'abbr' => 'Val']])),
        'contains' => ['headers="h1 h2"', 'scope="row"', 'abbr="Val"'],
    ],
    'cell language direction and title attributes are preserved' => [
        'table' => static fn () => $twoColumnTable($cell('Texto', ['attributes' => ['lang' => 'es', 'dir' => 'ltr', 'title' => 'Review']])),
        'contains' => ['lang="es"', 'dir="ltr"', 'title="Review"'],
    ],
    'unsafe event attributes are omitted' => [
        'table' => static fn () => $twoColumnTable($cell('Safe', ['attributes' => ['onclick' => 'alert(1)', 'data-safe' => 'yes']])),
        'contains' => ['data-safe="yes"'],
        'forbid' => ['onclick='],
    ],
    'unsafe style url is omitted' => [
        'table' => static fn () => $twoColumnTable($cell('Style', ['htmlAttributes' => ['style' => 'background:url(javascript:bad)']])),
        'contains' => ['>Style</td>'],
        'forbid' => ['background:url'],
    ],
    'link attributes render as html anchor' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('link', ['url' => 'https://example.test/a?x=1&y=2', 'title' => 'Example', 'classes' => ['external']], [$text('Example')])])),
        'contains' => ['<a class="external" href="https://example.test/a?x=1&amp;y=2" title="Example">Example</a>'],
    ],
    'image attributes render as html image' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('image', ['url' => 'media/a.png', 'alt' => 'Alt text', 'title' => 'Image', 'classes' => ['thumb']])])),
        'contains' => ['<img class="thumb" src="media/a.png" alt="Alt text" title="Image" />'],
    ],
];

foreach ($attributeCases as $label => $case) {
    $tests["maps upstream markdown writer html table attributes {$label}"] =
        static function (TestRunner $t) use ($case, $writeDocument): void {
            $markdown = $writeDocument($case['table']());
            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }
            foreach ($case['forbid'] ?? [] as $forbidden) {
                $t->true(!str_contains($markdown, $forbidden), "HTML table output should not contain {$forbidden}");
            }
        };
}

$captionCases = [
    'inline strong caption renders html caption' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['captionInlines' => [$text('Migration '), new AstNode('strong', [], [$text('matrix')])]]),
        'contains' => ['<caption>Migration <strong>matrix</strong></caption>'],
    ],
    'caption source attributes are preserved' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'caption' => 'Caption',
            'captionSource' => ['sourceAttributes' => ['id' => 'cap', 'classes' => ['review'], 'attributes' => ['data-source' => 'reader']]],
        ]),
        'contains' => ['<caption id="cap" class="review" data-source="reader">Caption</caption>'],
    ],
    'short caption text is preserved as data attribute' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['shortCaption' => 'Short', 'caption' => 'Long caption']),
        'contains' => ['<caption data-pandoc-short-caption="Short">Long caption</caption>'],
    ],
    'short caption inlines flatten into data attribute' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['shortCaptionInlines' => [$text('Short'), $space(), new AstNode('emph', [], [$text('label')])], 'caption' => 'Long']),
        'contains' => ['data-pandoc-short-caption="Short label"'],
    ],
    'paragraph caption block renders inside caption' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['captionBlocks' => [$paragraph([$text('Block '), new AstNode('emph', [], [$text('caption')])])]]),
        'contains' => ['<caption><p>Block <em>caption</em></p></caption>'],
    ],
    'list caption block renders html list' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['captionBlocks' => [new AstNode('bullet_list', [], [new AstNode('list_item', [], [$paragraph([$text('One')])])])]]),
        'contains' => ['<caption><ul><li><p>One</p></li></ul></caption>'],
    ],
    'caption text is html escaped' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['caption' => 'A & <B> "quoted"']),
        'contains' => ['A &amp; &lt;B&gt; &quot;quoted&quot;'],
    ],
    'raw html inline caption is preserved' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['captionInlines' => [$text('Ready '), new AstNode('raw_html_inline', ['html' => '<span data-ok="1">now</span>'])]]),
        'contains' => ['Ready <span data-ok="1">now</span>'],
    ],
    'caption html attributes merge classes' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'caption' => 'Caption',
            'captionSource' => ['sourceAttributes' => ['htmlAttributes' => ['class' => 'source'], 'classes' => ['review']]],
        ]),
        'contains' => ['<caption class="source review">Caption</caption>'],
    ],
    'empty caption with source attributes still renders caption element' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'captionSource' => ['sourceAttributes' => ['attributes' => ['data-empty' => 'caption']]],
        ]),
        'contains' => ['<caption data-empty="caption"></caption>'],
    ],
    'caption language direction and title are preserved' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'caption' => 'Resumen',
            'captionSource' => ['sourceAttributes' => ['attributes' => ['lang' => 'es', 'dir' => 'ltr', 'title' => 'Summary']]],
        ]),
        'contains' => ['lang="es"', 'dir="ltr"', 'title="Summary"'],
    ],
    'short caption blocks flatten into data attribute' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['shortCaptionBlocks' => [$plain([$text('Short block')])], 'caption' => 'Long']),
        'contains' => ['data-pandoc-short-caption="Short block"'],
    ],
];

foreach ($captionCases as $label => $case) {
    $tests["maps upstream markdown writer html table captions {$label}"] =
        static function (TestRunner $t) use ($case, $writeDocument): void {
            $markdown = $writeDocument($case['table']());
            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }
        };
}

$inlineCases = [
    'generic span id class data attributes' => [
        'inline' => new AstNode('span', ['id' => 'span-id', 'classes' => ['review'], 'attributes' => ['data-kind' => 'label']], [$text('Label')]),
        'contains' => ['<span id="span-id" class="review" data-kind="label">Label</span>'],
    ],
    'nested strong span content' => [
        'inline' => new AstNode('span', ['classes' => ['review']], [$text('Source '), new AstNode('strong', [], [$text('label')])]),
        'contains' => ['<span class="review">Source <strong>label</strong></span>'],
    ],
    'small caps semantic span merges class' => [
        'inline' => new AstNode('small_caps', ['classes' => ['source']], [$text('Small')]),
        'contains' => ['<span class="smallcaps source">Small</span>'],
    ],
    'underline semantic span renders class' => [
        'inline' => new AstNode('underline', ['attributes' => ['data-edit' => 'insert']], [$text('Insert')]),
        'contains' => ['<span class="underline" data-edit="insert">Insert</span>'],
    ],
    'strikeout renders del with attributes' => [
        'inline' => new AstNode('strikeout', ['attributes' => ['data-edit' => 'delete']], [$text('Gone')]),
        'contains' => ['<del data-edit="delete">Gone</del>'],
    ],
    'superscript renders sup with id' => [
        'inline' => new AstNode('superscript', ['id' => 'pow'], [$text('2')]),
        'contains' => ['<sup id="pow">2</sup>'],
    ],
    'subscript renders sub with class' => [
        'inline' => new AstNode('subscript', ['classes' => ['chem']], [$text('n')]),
        'contains' => ['<sub class="chem">n</sub>'],
    ],
    'code inline escapes html' => [
        'inline' => new AstNode('code', ['text' => '<tag>&value', 'attributes' => ['data-code' => 'yes']]),
        'contains' => ['<code data-code="yes">&lt;tag&gt;&amp;value</code>'],
    ],
    'single quoted inline uses html entities' => [
        'inline' => new AstNode('quoted', ['kind' => 'single'], [$text('term')]),
        'contains' => ['&lsquo;term&rsquo;'],
    ],
    'math inline renders math span' => [
        'inline' => new AstNode('math', ['text' => 'a < b']),
        'contains' => ['<span class="math inline">a &lt; b</span>'],
    ],
    'raw markdown inline is escaped inside html table' => [
        'inline' => new AstNode('raw_markdown', ['text' => '<raw>']),
        'contains' => ['&lt;raw&gt;'],
    ],
    'text inline escapes html boundaries' => [
        'inline' => $text('A < B & C'),
        'contains' => ['A &lt; B &amp; C'],
    ],
];

foreach ($inlineCases as $label => $case) {
    $tests["maps upstream markdown writer html table inlines {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $cell, $writeDocument): void {
            $markdown = $writeDocument($twoColumnTable($cell([$case['inline']])));
            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }
        };
}

$blockCases = [
    'colgroup widths render percent columns' => [
        'table' => static fn () => $twoColumnTable($cell('Ready')),
        'contains' => ['<col style="width:25%" data-pandoc-align="left" />', '<col style="width:75%" data-pandoc-align="right" />'],
    ],
    'heading block inside cell renders heading html' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('heading', ['level' => 3, 'id' => 'h'], [$text('Cell heading')])])),
        'contains' => ['<h3 id="h">Cell heading</h3>'],
    ],
    'paragraph block inside cell renders paragraph html' => [
        'table' => static fn () => $twoColumnTable($cell([$paragraph([$text('Para')])])),
        'contains' => ['<td style="text-align:right"><p>Para</p></td>'],
    ],
    'bullet list block inside cell renders list html' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('bullet_list', [], [new AstNode('list_item', [], [$paragraph([$text('One')])])])])),
        'contains' => ['<ul><li><p>One</p></li></ul>'],
    ],
    'ordered list start is preserved' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('ordered_list', ['start' => 3], [new AstNode('list_item', [], [$paragraph([$text('Three')])])])])),
        'contains' => ['<ol start="3"><li><p>Three</p></li></ol>'],
    ],
    'blockquote block inside cell renders blockquote html' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('blockquote', [], [$paragraph([$text('Quote')])])])),
        'contains' => ['<blockquote><p>Quote</p></blockquote>'],
    ],
    'code block inside cell renders pre code html' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('code_block', ['text' => '<code>', 'classes' => ['php']])])),
        'contains' => ['<pre><code class="php">&lt;code&gt;</code></pre>'],
    ],
    'div block inside cell preserves attributes' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('div', ['id' => 'note', 'classes' => ['callout']], [$paragraph([$text('Note')])])])),
        'contains' => ['<div id="note" class="callout"><p>Note</p></div>'],
    ],
    'horizontal rule block inside cell renders hr' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('horizontal_rule')])),
        'contains' => ['<hr />'],
    ],
    'nested table block inside cell renders nested html table' => [
        'table' => static fn () => $twoColumnTable($cell([$table([$body([$row([$cell('Nested', ['colspan' => 2])])])], ['htmlAttributes' => ['data-nested' => 'yes']])])),
        'contains' => ['<table data-pandoc-writer="html" data-nested="yes">', 'colspan="2"', '>Nested</td>'],
    ],
    'raw html block inside cell is preserved' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('raw_html', ['html' => '<aside data-note="1">Raw</aside>'])])),
        'contains' => ['<aside data-note="1">Raw</aside>'],
    ],
    'soft and hard breaks become html breaks' => [
        'table' => static fn () => $twoColumnTable($cell([$text('A'), new AstNode('softbreak'), $text('B'), new AstNode('linebreak'), $text('C')])),
        'contains' => ['A<br />B<br />C'],
    ],
];

foreach ($blockCases as $label => $case) {
    $tests["maps upstream markdown writer html table blocks {$label}"] =
        static function (TestRunner $t) use ($case, $writeDocument): void {
            $markdown = $writeDocument($case['table']());
            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }
        };
}

return $tests;
