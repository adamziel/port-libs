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
$writeDocument = static fn (AstNode $node): string => (new MarkdownWriter(['htmlTableAutoFallback' => true]))->write($document([$node]));

$table = static function (array $sections, array $attrs = []): AstNode {
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

$assertAutoHtmlTable = static function (TestRunner $t, AstNode $table, array $contains, array $forbid = []) use ($writeDocument): void {
    $markdown = $writeDocument($table);

    $t->contains('<table', $markdown);
    $t->true(!str_contains($markdown, 'data-pandoc-writer'), 'Auto HTML fallback should not need an explicit per-table writer marker');
    foreach ($contains as $expected) {
        $t->contains($expected, $markdown);
    }
    foreach ($forbid as $forbidden) {
        $t->true(!str_contains($markdown, $forbidden), "Auto HTML fallback should not emit {$forbidden}");
    }
};

$tests = [];

$structureCases = [
    'colspan cell automatically uses html table' => [
        'table' => static fn () => $table([$body([$row([$cell('Merged', ['colspan' => 2])])])]),
        'contains' => ['<table>', 'colspan="2"', '>Merged</td>'],
    ],
    'rowspan cell automatically preserves vertical span' => [
        'table' => static fn () => $table([$body([
            $row([$cell('Group', ['rowspan' => 2]), $cell('One')]),
            $row([$cell('Two')]),
        ])]),
        'contains' => ['rowspan="2"', '>Group</td>', '>Two</td>'],
    ],
    'rowspan zero automatically extends to body end' => [
        'table' => static fn () => $table([$body([
            $row([$cell('All', ['rowspan' => '0']), $cell('One')]),
            $row([$cell('Two')]),
            $row([$cell('Three')]),
        ])]),
        'contains' => ['rowspan="3"', '>All</td>'],
    ],
    'explicit header cell automatically renders th' => [
        'table' => static fn () => $table([$body([$row([$cell('Row head', ['header' => true]), $cell('Value')])])]),
        'contains' => ['<th scope="row" style="text-align:left">Row head</th>', '<td style="text-align:right">Value</td>'],
    ],
    'body row head columns automatically render row scopes' => [
        'table' => static fn () => $table([$body([$row([$cell('Row label'), $cell('Value')])], ['rowHeadColumns' => 1])]),
        'contains' => ['<th scope="row" style="text-align:left">Row label</th>', '<td style="text-align:right">Value</td>'],
    ],
    'cell center alignment automatically renders style' => [
        'table' => static fn () => $twoColumnTable($cell('Centered', ['align' => 'center'])),
        'contains' => ['<td style="text-align:center">Centered</td>'],
    ],
    'cell left alignment override automatically renders style' => [
        'table' => static fn () => $twoColumnTable($cell('Left override', ['align' => 'left'])),
        'contains' => ['<td style="text-align:left">Left override</td>'],
    ],
    'cell top vertical alignment automatically renders style' => [
        'table' => static fn () => $twoColumnTable($cell('Top', ['valign' => 'top'])),
        'contains' => ['vertical-align:top'],
    ],
    'cell source style automatically keeps computed alignment' => [
        'table' => static fn () => $twoColumnTable($cell('Styled', ['htmlAttributes' => ['style' => 'font-weight:bold'], 'align' => 'center'])),
        'contains' => ['style="font-weight:bold; text-align:center"'],
    ],
    'row source attributes automatically stay on tr' => [
        'table' => static fn () => $table([$body([$row([$cell('Row'), $cell('Attrs')], ['id' => 'auto-row', 'classes' => ['review-row'], 'attributes' => ['data-state' => 'kept']])])]),
        'contains' => ['<tr id="auto-row" class="review-row" data-state="kept">'],
    ],
    'thead source attributes automatically stay on section' => [
        'table' => static fn () => $table([
            $head([$row([$cell('Metric'), $cell('Value')])], ['classes' => ['auto-head'], 'attributes' => ['data-origin' => 'reader']]),
            $body([$row([$cell('Posts'), $cell('42')])]),
        ]),
        'contains' => ['<thead class="auto-head" data-origin="reader">'],
    ],
    'tbody source attributes automatically stay on section' => [
        'table' => static fn () => $table([$body([$row([$cell('Posts'), $cell('42')])], ['id' => 'auto-body', 'attributes' => ['data-phase' => 'draft']])]),
        'contains' => ['<tbody id="auto-body" data-phase="draft">'],
    ],
    'tfoot source attributes automatically stay on section' => [
        'table' => static fn () => $table([
            $body([$row([$cell('Posts'), $cell('42')])]),
            $foot([$row([$cell('Total'), $cell('42')])], ['attributes' => ['data-total' => 'yes']]),
        ]),
        'contains' => ['<tfoot data-total="yes">'],
    ],
    'table html attributes automatically stay on table element' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['htmlAttributes' => ['summary' => 'Migration inventory', 'data-origin' => 'auto']]),
        'contains' => ['<table summary="Migration inventory" data-origin="auto">'],
    ],
    'table unsupported markdown attributes automatically stay as html' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['attributes' => ['summary' => 'Attribute inventory']]),
        'contains' => ['<table summary="Attribute inventory">'],
    ],
];

foreach ($structureCases as $label => $case) {
    $tests["maps upstream markdown writer auto html table structure {$label}"] =
        static function (TestRunner $t) use ($case, $assertAutoHtmlTable): void {
            $assertAutoHtmlTable($t, $case['table'](), $case['contains'], $case['forbid'] ?? []);
        };
}

$captionCases = [
    'caption source id class data attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'caption' => 'Caption',
            'captionSource' => ['sourceAttributes' => ['id' => 'auto-cap', 'classes' => ['review'], 'attributes' => ['data-source' => 'reader']]],
        ]),
        'contains' => ['<caption id="auto-cap" class="review" data-source="reader">Caption</caption>'],
    ],
    'caption source html class merges classes' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'caption' => 'Caption',
            'captionSource' => ['sourceAttributes' => ['htmlAttributes' => ['class' => 'source'], 'classes' => ['review']]],
        ]),
        'contains' => ['<caption class="source review">Caption</caption>'],
    ],
    'empty caption source attributes render caption element' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'captionSource' => ['sourceAttributes' => ['attributes' => ['data-empty' => 'caption']]],
        ]),
        'contains' => ['<caption data-empty="caption"></caption>'],
    ],
    'caption language direction title attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'caption' => 'Resumen',
            'captionSource' => ['sourceAttributes' => ['attributes' => ['lang' => 'es', 'dir' => 'ltr', 'title' => 'Summary']]],
        ]),
        'contains' => ['lang="es"', 'dir="ltr"', 'title="Summary"'],
    ],
    'plain short caption becomes data attribute' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'shortCaption' => 'Short',
            'caption' => 'Long caption',
            'captionSource' => ['sourceAttributes' => ['attributes' => ['data-source' => 'reader']]],
        ]),
        'contains' => ['<caption data-source="reader" data-pandoc-short-caption="Short">Long caption</caption>'],
    ],
    'short caption inlines flatten to data attribute' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'shortCaptionInlines' => [$text('Short'), $space(), new AstNode('emph', [], [$text('label')])],
            'caption' => 'Long',
            'captionSource' => ['sourceAttributes' => ['attributes' => ['data-source' => 'reader']]],
        ]),
        'contains' => ['data-pandoc-short-caption="Short label"'],
    ],
    'short caption blocks flatten to data attribute' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'shortCaptionBlocks' => [$plain([$text('Short block')])],
            'caption' => 'Long',
            'captionSource' => ['sourceAttributes' => ['attributes' => ['data-source' => 'reader']]],
        ]),
        'contains' => ['data-pandoc-short-caption="Short block"'],
    ],
    'paragraph caption block renders html content' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'captionBlocks' => [$paragraph([$text('Block '), new AstNode('emph', [], [$text('caption')])])],
            'captionSource' => ['sourceAttributes' => ['attributes' => ['data-source' => 'reader']]],
        ]),
        'contains' => ['<caption data-source="reader"><p>Block <em>caption</em></p></caption>'],
    ],
    'list caption block renders html list content' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'captionBlocks' => [new AstNode('bullet_list', [], [new AstNode('list_item', [], [$paragraph([$text('One')])])])],
            'captionSource' => ['sourceAttributes' => ['attributes' => ['data-source' => 'reader']]],
        ]),
        'contains' => ['<caption data-source="reader"><ul><li><p>One</p></li></ul></caption>'],
    ],
    'raw html inline caption remains raw html' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), [
            'captionInlines' => [$text('Ready '), new AstNode('raw_html_inline', ['html' => '<span data-ok="1">now</span>'])],
            'captionSource' => ['sourceAttributes' => ['attributes' => ['data-source' => 'reader']]],
        ]),
        'contains' => ['Ready <span data-ok="1">now</span>'],
    ],
];

foreach ($captionCases as $label => $case) {
    $tests["maps upstream markdown writer auto html table captions {$label}"] =
        static function (TestRunner $t) use ($case, $assertAutoHtmlTable): void {
            $assertAutoHtmlTable($t, $case['table'](), $case['contains']);
        };
}

$cellAttributeCases = [
    'cell id class data attributes' => [
        'cell' => static fn () => $cell('Cell', ['id' => 'auto-cell', 'classes' => ['audit'], 'attributes' => ['data-kind' => 'value']]),
        'contains' => ['<td id="auto-cell" class="audit" data-kind="value" style="text-align:right">Cell</td>'],
    ],
    'cell aria and role attributes' => [
        'cell' => static fn () => $cell('Term', ['attributes' => ['role' => 'term', 'aria-label' => 'Reviewed term']]),
        'contains' => ['role="term"', 'aria-label="Reviewed term"'],
    ],
    'cell headers scope and abbreviation attributes' => [
        'cell' => static fn () => $cell('Value', ['attributes' => ['headers' => 'h1 h2', 'scope' => 'row', 'abbr' => 'Val']]),
        'contains' => ['headers="h1 h2"', 'scope="row"', 'abbr="Val"'],
    ],
    'cell language direction title attributes' => [
        'cell' => static fn () => $cell('Texto', ['attributes' => ['lang' => 'es', 'dir' => 'ltr', 'title' => 'Review']]),
        'contains' => ['lang="es"', 'dir="ltr"', 'title="Review"'],
    ],
    'cell width and height attributes' => [
        'cell' => static fn () => $cell('Sized', ['attributes' => ['width' => '40', 'height' => '20']]),
        'contains' => ['width="40"', 'height="20"'],
    ],
    'cell safe style attribute' => [
        'cell' => static fn () => $cell('Styled', ['attributes' => ['style' => 'font-variant:small-caps']]),
        'contains' => ['style="font-variant:small-caps; text-align:right"'],
    ],
    'cell source vertical align style is not duplicated' => [
        'cell' => static fn () => $cell('Top style', ['htmlAttributes' => ['style' => 'vertical-align:top']]),
        'contains' => ['style="vertical-align:top; text-align:right"'],
    ],
    'unsafe event attribute is omitted' => [
        'cell' => static fn () => $cell('Safe', ['attributes' => ['onclick' => 'alert(1)', 'data-safe' => 'yes']]),
        'contains' => ['data-safe="yes"'],
        'forbid' => ['onclick='],
    ],
    'unsafe style url is omitted' => [
        'cell' => static fn () => $cell('Style', ['htmlAttributes' => ['style' => 'background:url(javascript:bad)']]),
        'contains' => ['>Style</td>'],
        'forbid' => ['background:url'],
    ],
    'cell source html class merges classes' => [
        'cell' => static fn () => $cell('Classes', ['htmlAttributes' => ['class' => 'source'], 'classes' => ['review']]),
        'contains' => ['class="source review"'],
    ],
];

foreach ($cellAttributeCases as $label => $case) {
    $tests["maps upstream markdown writer auto html table cell attributes {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $assertAutoHtmlTable): void {
            $assertAutoHtmlTable($t, $twoColumnTable($case['cell']()), $case['contains'], $case['forbid'] ?? []);
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
    'raw markdown inline is escaped' => [
        'inline' => new AstNode('raw_markdown', ['text' => '<raw>']),
        'contains' => ['&lt;raw&gt;'],
    ],
    'raw html inline is preserved' => [
        'inline' => new AstNode('raw_html_inline', ['html' => '<span data-x="1">raw</span>']),
        'contains' => ['<span data-x="1">raw</span>'],
    ],
    'link attributes render as html anchor' => [
        'inline' => new AstNode('link', ['url' => 'https://example.test/a?x=1&y=2', 'title' => 'Example', 'classes' => ['external']], [$text('Example')]),
        'contains' => ['<a class="external" href="https://example.test/a?x=1&amp;y=2" title="Example">Example</a>'],
    ],
    'image attributes render as html image' => [
        'inline' => new AstNode('image', ['url' => 'media/a.png', 'alt' => 'Alt text', 'title' => 'Image', 'classes' => ['thumb']]),
        'contains' => ['<img class="thumb" src="media/a.png" alt="Alt text" title="Image" />'],
    ],
    'soft and hard breaks render as br elements' => [
        'inline' => [$text('A'), new AstNode('softbreak'), $text('B'), new AstNode('linebreak'), $text('C')],
        'contains' => ['A<br />B<br />C'],
    ],
];

foreach ($inlineCases as $label => $case) {
    $tests["maps upstream markdown writer auto html table inlines {$label}"] =
        static function (TestRunner $t) use ($case, $cell, $twoColumnTable, $assertAutoHtmlTable): void {
            $inlines = is_array($case['inline']) ? $case['inline'] : [$case['inline']];
            $assertAutoHtmlTable($t, $twoColumnTable($cell($inlines, ['id' => 'auto-inline-cell'])), $case['contains']);
        };
}

$blockCases = [
    'heading block inside cell renders heading html' => [
        'block' => new AstNode('heading', ['level' => 3, 'id' => 'h'], [$text('Cell heading')]),
        'contains' => ['<h3 id="h">Cell heading</h3>'],
    ],
    'paragraph block inside cell renders paragraph html' => [
        'block' => $paragraph([$text('Para')]),
        'contains' => ['<td id="auto-block-cell" style="text-align:right"><p>Para</p></td>'],
    ],
    'bullet list block inside cell renders list html' => [
        'block' => new AstNode('bullet_list', [], [new AstNode('list_item', [], [$paragraph([$text('One')])])]),
        'contains' => ['<ul><li><p>One</p></li></ul>'],
    ],
    'ordered list start is preserved' => [
        'block' => new AstNode('ordered_list', ['start' => 3], [new AstNode('list_item', [], [$paragraph([$text('Three')])])]),
        'contains' => ['<ol start="3"><li><p>Three</p></li></ol>'],
    ],
    'blockquote block inside cell renders blockquote html' => [
        'block' => new AstNode('blockquote', [], [$paragraph([$text('Quote')])]),
        'contains' => ['<blockquote><p>Quote</p></blockquote>'],
    ],
    'code block inside cell renders pre code html' => [
        'block' => new AstNode('code_block', ['text' => '<code>', 'classes' => ['php']]),
        'contains' => ['<pre><code class="php">&lt;code&gt;</code></pre>'],
    ],
    'div block inside cell preserves attributes' => [
        'block' => new AstNode('div', ['id' => 'note', 'classes' => ['callout']], [$paragraph([$text('Note')])]),
        'contains' => ['<div id="note" class="callout"><p>Note</p></div>'],
    ],
    'horizontal rule block inside cell renders hr' => [
        'block' => new AstNode('horizontal_rule'),
        'contains' => ['<hr />'],
    ],
    'raw html block inside cell is preserved' => [
        'block' => new AstNode('raw_html', ['html' => '<aside data-note="1">Raw</aside>']),
        'contains' => ['<aside data-note="1">Raw</aside>'],
    ],
    'nested spanned table block inside cell renders nested html table' => [
        'block' => $table([$body([$row([$cell('Nested', ['colspan' => 2])])])], ['htmlAttributes' => ['data-nested' => 'yes']]),
        'contains' => ['<table data-nested="yes">', 'colspan="2"', '>Nested</td>'],
    ],
];

foreach ($blockCases as $label => $case) {
    $tests["maps upstream markdown writer auto html table blocks {$label}"] =
        static function (TestRunner $t) use ($case, $cell, $twoColumnTable, $assertAutoHtmlTable): void {
            $assertAutoHtmlTable($t, $twoColumnTable($cell([$case['block']], ['id' => 'auto-block-cell'])), $case['contains']);
        };
}

return $tests;
