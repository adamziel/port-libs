<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
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
$writeDocument = static fn (AstNode $node): string => (new MarkdownWriter(['autoHtmlTables' => true]))->write($document([$node]));

$table = static function (array $sections, array $attrs = []): AstNode {
    $attrs += [
        'alignments' => ['left', 'right', 'center'],
        'widths' => [0.2, 0.3, 0.5],
    ];

    return new AstNode('table', $attrs, $sections);
};

$twoColumnTable = static function (AstNode $valueCell, array $attrs = []) use ($table, $head, $body, $row, $cell): AstNode {
    return $table([
        $head([$row([$cell('Metric'), $cell('Value')])]),
        $body([$row([$cell('Probe'), $valueCell])]),
    ], $attrs + ['alignments' => ['left', 'right'], 'widths' => [0.25, 0.75]]);
};

$tests = [];

$structureCases = [
    'colspan cell chooses html without marker' => [
        'table' => static fn () => $table([$body([$row([$cell('Merged', ['colspan' => 2]), $cell('Tail')])])]),
        'contains' => ['<table>', 'colspan="2"', '>Merged</td>'],
    ],
    'rowspan cell chooses html without marker' => [
        'table' => static fn () => $table([$body([
            $row([$cell('Group', ['rowspan' => 2]), $cell('One')]),
            $row([$cell('Two')]),
        ])]),
        'contains' => ['<table>', 'rowspan="2"', '>Group</td>', '>Two</td>'],
    ],
    'rowspan zero expands to body end' => [
        'table' => static fn () => $table([$body([
            $row([$cell('All', ['rowspan' => '0']), $cell('One')]),
            $row([$cell('Two')]),
            $row([$cell('Three')]),
        ])]),
        'contains' => ['rowspan="3"', '>All</td>'],
    ],
    'explicit body header cell emits th' => [
        'table' => static fn () => $table([$body([$row([$cell('Row label', ['header' => true]), $cell('Value')])])], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="row" style="text-align:left">Row label</th>', '<td style="text-align:right">Value</td>'],
    ],
    'row head columns emit scoped row headers' => [
        'table' => static fn () => $table([$body([$row([$cell('Term'), $cell('Definition')])], ['rowHeadColumns' => 1])], ['alignments' => ['left', 'left']]),
        'contains' => ['<th scope="row" style="text-align:left">Term</th>', '<td style="text-align:left">Definition</td>'],
    ],
    'numeric string row head columns are honored' => [
        'table' => static fn () => $table([$body([$row([$cell('Term'), $cell('Definition')])], ['rowHeadColumns' => '1'])], ['alignments' => ['left', 'left']]),
        'contains' => ['<th scope="row" style="text-align:left">Term</th>'],
    ],
    'cell center alignment emits html style' => [
        'table' => static fn () => $twoColumnTable($cell('Centered', ['align' => 'center'])),
        'contains' => ['<td style="text-align:center">Centered</td>'],
    ],
    'cell vertical alignment emits html style' => [
        'table' => static fn () => $twoColumnTable($cell('Middle', ['valign' => 'middle'])),
        'contains' => ['vertical-align:middle'],
    ],
    'cell source style merges computed alignment' => [
        'table' => static fn () => $twoColumnTable($cell('Styled', ['htmlAttributes' => ['style' => 'font-weight:bold'], 'align' => 'center'])),
        'contains' => ['style="font-weight:bold; text-align:center"'],
    ],
    'cell id class and data attributes are preserved' => [
        'table' => static fn () => $twoColumnTable($cell('Cell', ['id' => 'cell-a', 'classes' => ['review'], 'attributes' => ['data-kind' => 'value']])),
        'contains' => ['<td id="cell-a" class="review" data-kind="value" style="text-align:right">Cell</td>'],
    ],
    'row attributes are preserved' => [
        'table' => static fn () => $table([$body([$row([$cell('Row'), $cell('Attrs')], ['id' => 'row-a', 'classes' => ['review-row'], 'attributes' => ['data-state' => 'kept']])])]),
        'contains' => ['<tr id="row-a" class="review-row" data-state="kept">'],
    ],
    'thead attributes are preserved' => [
        'table' => static fn () => $table([$head([$row([$cell('H'), $cell('V')])], ['id' => 'head-a', 'attributes' => ['data-origin' => 'reader']]), $body([$row([$cell('A'), $cell('1')])])]),
        'contains' => ['<thead id="head-a" data-origin="reader">'],
    ],
    'tbody html attributes are preserved' => [
        'table' => static fn () => $table([$body([$row([$cell('A'), $cell('1')])], ['htmlAttributes' => ['data-group' => 'alpha']])]),
        'contains' => ['<tbody data-group="alpha">'],
    ],
    'tfoot attributes are preserved' => [
        'table' => static fn () => $table([$body([$row([$cell('A'), $cell('1')])]), $foot([$row([$cell('Total'), $cell('1')])], ['attributes' => ['data-total' => 'yes']])]),
        'contains' => ['<tfoot data-total="yes">', '>Total</td>'],
    ],
    'table html summary attribute triggers html output' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['htmlAttributes' => ['summary' => 'Migration inventory']]),
        'contains' => ['<table summary="Migration inventory">'],
    ],
    'table non markdown source attribute triggers html output' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['attributes' => ['summary' => 'Review inventory']]),
        'contains' => ['<table summary="Review inventory">'],
    ],
    'multiple attributed bodies remain grouped' => [
        'table' => static fn () => $table([
            $body([$row([$cell('A'), $cell('1')])], ['attributes' => ['data-group' => 'a']]),
            $body([$row([$cell('B'), $cell('2')])], ['attributes' => ['data-group' => 'b']]),
        ]),
        'contains' => ['<tbody data-group="a">', '<tbody data-group="b">'],
    ],
    'direct row attributes render synthetic body' => [
        'table' => static fn () => new AstNode('table', [], [$row([$cell('Direct'), $cell('Row')], ['attributes' => ['data-direct' => 'yes']])]),
        'contains' => ['<tbody>', '<tr data-direct="yes"><td>Direct</td><td>Row</td></tr>'],
    ],
    'direct row cell colspan computes columns' => [
        'table' => static fn () => new AstNode('table', [], [$row([$cell('Direct span', ['colspan' => 2])])]),
        'contains' => ['<tbody>', 'colspan="2"', '>Direct span</td>'],
    ],
    'direct rows append after explicit body rows' => [
        'table' => static fn () => $table([$body([$row([$cell('Body')])]), $row([$cell('Direct', ['classes' => ['late']])])], ['alignments' => ['left']]),
        'contains' => ['<td style="text-align:left">Body</td>', '<td class="late" style="text-align:left">Direct</td>'],
    ],
    'data writer pipe marker does not block structural fallback' => [
        'table' => static fn () => $twoColumnTable($cell('Kept', ['classes' => ['marked']]), ['htmlAttributes' => ['data-pandoc-writer' => 'pipe']]),
        'contains' => ['<table data-pandoc-writer="pipe">', '<td class="marked" style="text-align:right">Kept</td>'],
    ],
];

foreach ($structureCases as $label => $case) {
    $tests["maps upstream markdown writer auto html table structure {$label}"] =
        static function (TestRunner $t) use ($case, $writeDocument): void {
            $markdown = $writeDocument($case['table']());
            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }
        };
}

$captionCases = [
    'plain caption renders html caption' => [
        'table' => static fn () => $twoColumnTable($cell('Ready', ['colspan' => 1, 'classes' => ['trigger']]), ['caption' => 'Plain caption']),
        'contains' => ['<caption>Plain caption</caption>'],
    ],
    'inline strong caption renders html caption' => [
        'table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['captionInlines' => [$text('Migration '), new AstNode('strong', [], [$text('matrix')])]]),
        'contains' => ['<caption>Migration <strong>matrix</strong></caption>'],
    ],
    'paragraph caption block renders paragraph' => [
        'table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['captionBlocks' => [$paragraph([$text('Block caption')])]]),
        'contains' => ['<caption><p>Block caption</p></caption>'],
    ],
    'list caption block renders html list' => [
        'table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['captionBlocks' => [new AstNode('bullet_list', [], [new AstNode('list_item', [], [$paragraph([$text('One')])])])]]),
        'contains' => ['<caption><ul><li><p>One</p></li></ul></caption>'],
    ],
    'short caption text becomes data attribute' => [
        'table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['shortCaption' => 'Short', 'caption' => 'Long caption']),
        'contains' => ['<caption data-pandoc-short-caption="Short">Long caption</caption>'],
    ],
    'short caption inlines flatten to data attribute' => [
        'table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['shortCaptionInlines' => [$text('Short'), $space(), new AstNode('emph', [], [$text('label')])], 'caption' => 'Long']),
        'contains' => ['data-pandoc-short-caption="Short label"'],
    ],
    'short caption blocks flatten to data attribute' => [
        'table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['shortCaptionBlocks' => [$paragraph([$text('Short block')])], 'caption' => 'Long']),
        'contains' => ['data-pandoc-short-caption="Short block"'],
    ],
    'caption source attributes are preserved' => [
        'table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['id' => 'cap', 'classes' => ['review'], 'attributes' => ['data-source' => 'reader']]]]),
        'contains' => ['<caption id="cap" class="review" data-source="reader">Caption</caption>'],
    ],
    'raw html caption inline is preserved' => [
        'table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['captionInlines' => [$text('Ready '), new AstNode('raw_html_inline', ['html' => '<span data-ok="1">now</span>'])]]),
        'contains' => ['Ready <span data-ok="1">now</span>'],
    ],
    'caption text is html escaped' => [
        'table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['caption' => 'A & <B> "quoted"']),
        'contains' => ['A &amp; &lt;B&gt; &quot;quoted&quot;'],
    ],
];

foreach ($captionCases as $label => $case) {
    $tests["maps upstream markdown writer auto html table caption {$label}"] =
        static function (TestRunner $t) use ($case, $writeDocument): void {
            $markdown = $writeDocument($case['table']());
            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }
        };
}

$inlineCases = [
    'link inline renders anchor' => [
        'inline' => new AstNode('link', ['url' => 'https://example.test/a?x=1&y=2', 'title' => 'Example'], [$text('Example')]),
        'contains' => ['<a href="https://example.test/a?x=1&amp;y=2" title="Example">Example</a>'],
    ],
    'image inline renders image element' => [
        'inline' => new AstNode('image', ['url' => 'media/a.png', 'alt' => 'Alt text', 'classes' => ['thumb']]),
        'contains' => ['<img class="thumb" src="media/a.png" alt="Alt text" />'],
    ],
    'span inline preserves attributes' => [
        'inline' => new AstNode('span', ['classes' => ['review'], 'attributes' => ['data-kind' => 'term']], [$text('Term')]),
        'contains' => ['<span class="review" data-kind="term">Term</span>'],
    ],
    'small caps inline renders semantic class' => [
        'inline' => new AstNode('small_caps', ['classes' => ['source']], [$text('Small')]),
        'contains' => ['<span class="smallcaps source">Small</span>'],
    ],
    'underline inline renders semantic class' => [
        'inline' => new AstNode('underline', ['attributes' => ['data-edit' => 'insert']], [$text('Insert')]),
        'contains' => ['<span class="underline" data-edit="insert">Insert</span>'],
    ],
    'strikeout inline renders del' => [
        'inline' => new AstNode('strikeout', ['attributes' => ['data-edit' => 'delete']], [$text('Gone')]),
        'contains' => ['<del data-edit="delete">Gone</del>'],
    ],
    'superscript inline renders sup' => [
        'inline' => new AstNode('superscript', ['id' => 'pow'], [$text('2')]),
        'contains' => ['<sup id="pow">2</sup>'],
    ],
    'subscript inline renders sub' => [
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
    'display math inline renders math span' => [
        'inline' => new AstNode('math', ['text' => 'a < b', 'display' => true]),
        'contains' => ['<span class="math display">a &lt; b</span>'],
    ],
    'raw html inline is preserved' => [
        'inline' => new AstNode('raw_html_inline', ['html' => '<kbd>Esc</kbd>']),
        'contains' => ['<kbd>Esc</kbd>'],
    ],
    'raw inline html format is preserved' => [
        'inline' => new AstNode('raw_inline', ['format' => 'html', 'text' => '<span>raw</span>']),
        'contains' => ['<span>raw</span>'],
    ],
    'raw markdown inline is escaped' => [
        'inline' => new AstNode('raw_markdown', ['text' => '<raw>']),
        'contains' => ['&lt;raw&gt;'],
    ],
    'citation inline falls back to escaped markdown' => [
        'inline' => new AstNode('citation', ['id' => 'doe2026', 'suffix' => 'p. 4']),
        'contains' => ['[@doe2026, p. 4]'],
    ],
    'soft and hard breaks render html breaks' => [
        'inline' => [$text('A'), new AstNode('softbreak'), $text('B'), new AstNode('linebreak'), $text('C')],
        'contains' => ['A<br />B<br />C'],
    ],
];

foreach ($inlineCases as $label => $case) {
    $tests["maps upstream markdown writer auto html table inline {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $cell, $writeDocument): void {
            $inlines = is_array($case['inline']) ? $case['inline'] : [$case['inline']];
            $markdown = $writeDocument($twoColumnTable($cell($inlines, ['classes' => ['trigger']])));
            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }
        };
}

$blockCases = [
    'paragraph block renders paragraph html' => [
        'blocks' => [$paragraph([$text('Para')])],
        'contains' => ['<p>Para</p>'],
    ],
    'heading block renders heading html' => [
        'blocks' => [new AstNode('heading', ['level' => 2, 'id' => 'cell-heading'], [$text('Cell heading')])],
        'contains' => ['<h2 id="cell-heading">Cell heading</h2>'],
    ],
    'bullet list block renders list html' => [
        'blocks' => [new AstNode('bullet_list', [], [new AstNode('list_item', [], [$paragraph([$text('One')])])])],
        'contains' => ['<ul><li><p>One</p></li></ul>'],
    ],
    'ordered list start is preserved' => [
        'blocks' => [new AstNode('ordered_list', ['start' => 3], [new AstNode('list_item', [], [$paragraph([$text('Three')])])])],
        'contains' => ['<ol start="3"><li><p>Three</p></li></ol>'],
    ],
    'blockquote block renders blockquote html' => [
        'blocks' => [new AstNode('blockquote', [], [$paragraph([$text('Quote')])])],
        'contains' => ['<blockquote><p>Quote</p></blockquote>'],
    ],
    'code block renders pre code html' => [
        'blocks' => [new AstNode('code_block', ['text' => '<code>', 'classes' => ['php']])],
        'contains' => ['<pre><code class="php">&lt;code&gt;</code></pre>'],
    ],
    'div block preserves attributes' => [
        'blocks' => [new AstNode('div', ['id' => 'note', 'classes' => ['callout']], [$paragraph([$text('Note')])])],
        'contains' => ['<div id="note" class="callout"><p>Note</p></div>'],
    ],
    'horizontal rule block renders hr' => [
        'blocks' => [new AstNode('horizontal_rule')],
        'contains' => ['<hr />'],
    ],
    'nested table block renders html table' => [
        'blocks' => [$table([$body([$row([$cell('Nested', ['colspan' => 2])])])])],
        'contains' => ['<table>', 'colspan="2"', '>Nested</td>'],
    ],
    'raw html block is preserved' => [
        'blocks' => [new AstNode('raw_html', ['html' => '<aside data-note="1">Raw</aside>'])],
        'contains' => ['<aside data-note="1">Raw</aside>'],
    ],
];

foreach ($blockCases as $label => $case) {
    $tests["maps upstream markdown writer auto html table block {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $cell, $writeDocument): void {
            $markdown = $writeDocument($twoColumnTable($cell($case['blocks'], ['classes' => ['trigger']])));
            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }
        };
}

return $tests;
