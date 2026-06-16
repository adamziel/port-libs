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
$writeDocument = static fn (AstNode $node, array $options = []): string => (new MarkdownWriter($options))->write($document([$node]));

$table = static function (array $sections, array $attrs = []): AstNode {
    $attrs += [
        'alignments' => ['left', 'right', 'center', 'default'],
        'widths' => [0.20, 0.30, 0.25, 0.25],
    ];

    return new AstNode('table', $attrs, $sections);
};

$twoColumnTable = static function (AstNode $valueCell, array $attrs = [], array $bodyAttrs = []) use ($table, $head, $body, $row, $cell): AstNode {
    return $table([
        $head([$row([$cell('Metric'), $cell('Value')])]),
        $body([$row([$cell('Probe'), $valueCell])], $bodyAttrs),
    ], array_replace(['alignments' => ['left', 'right'], 'widths' => [0.25, 0.75]], $attrs));
};

$assertDefaultHtmlTable = static function (TestRunner $t, AstNode $table, array $contains, array $forbid = [], array $options = []) use ($writeDocument): void {
    $markdown = $writeDocument($table, $options);

    $t->contains('<table', $markdown);
    foreach ($contains as $expected) {
        $t->contains($expected, $markdown);
    }
    foreach ($forbid as $unexpected) {
        $t->true(!str_contains($markdown, $unexpected), "Default semantic fallback should not emit {$unexpected}");
    }
};

$tests = [];

$spanCases = [
    'body colspan preserves merged cell' => [
        'table' => static fn () => $table([$body([$row([$cell('Merged', ['colspan' => 2]), $cell('Tail')])])]),
        'contains' => ['<table>', 'colspan="2"', '>Merged</td>', '>Tail</td>'],
    ],
    'head colspan preserves column group scope' => [
        'table' => static fn () => $table([$head([$row([$cell('Document', ['colspan' => 2]), $cell('State')])])]),
        'contains' => ['<thead>', '<th scope="colgroup" colspan="2" style="text-align:left">Document</th>'],
    ],
    'foot colspan remains in tfoot' => [
        'table' => static fn () => $table([$body([$row([$cell('Body'), $cell('1')])]), $foot([$row([$cell('Total', ['colspan' => 2])])])]),
        'contains' => ['<tfoot>', 'colspan="2"', '>Total</td>'],
    ],
    'body rowspan preserves vertical span' => [
        'table' => static fn () => $table([$body([$row([$cell('Group', ['rowspan' => 2]), $cell('One')]), $row([$cell('Two')])])]),
        'contains' => ['rowspan="2"', '>Group</td>', '>Two</td>'],
    ],
    'rowspan zero extends to section end' => [
        'table' => static fn () => $table([$body([$row([$cell('All', ['rowspan' => '0']), $cell('One')]), $row([$cell('Two')]), $row([$cell('Three')])])]),
        'contains' => ['rowspan="3"', '>All</td>', '>Three</td>'],
    ],
    'combined row and column span is retained' => [
        'table' => static fn () => $table([$body([$row([$cell('Block', ['colspan' => 2, 'rowspan' => 2]), $cell('A')]), $row([$cell('B')])])]),
        'contains' => ['colspan="2"', 'rowspan="2"', '>Block</td>'],
    ],
    'direct row colspan computes synthetic body' => [
        'table' => static fn () => new AstNode('table', ['alignments' => ['left', 'right']], [$row([$cell('Direct span', ['colspan' => 2])])]),
        'contains' => ['<tbody>', 'colspan="2"', '>Direct span</td>'],
    ],
    'direct row rowspan preserves covered slot' => [
        'table' => static fn () => new AstNode('table', ['alignments' => ['left', 'right']], [$row([$cell('Direct group', ['rowspan' => 2]), $cell('One')]), $row([$cell('Two')])]),
        'contains' => ['rowspan="2"', '>Direct group</td>', '<td style="text-align:right">Two</td>'],
    ],
    'body head row colspan keeps header semantics' => [
        'table' => static fn () => $table([$body([$row([$cell('Posts'), $cell('42')])], ['headRows' => [$row([$cell('Queue scope', ['colspan' => 2])])]])], ['alignments' => ['left', 'right']]),
        'contains' => ['<th scope="colgroup" colspan="2" style="text-align:left">Queue scope</th>'],
    ],
];

foreach ($spanCases as $label => $case) {
    $tests["maps upstream markdown writer default semantic table fallback span {$label}"] =
        static function (TestRunner $t) use ($case, $assertDefaultHtmlTable): void {
            $assertDefaultHtmlTable($t, $case['table'](), $case['contains']);
        };
}

$headerCases = [
    'explicit body header cell emits row header' => [
        'table' => static fn () => $twoColumnTable($cell('Ready', ['header' => true])),
        'contains' => ['<th scope="row" style="text-align:right">Ready</th>'],
    ],
    'row head column emits scoped row header' => [
        'table' => static fn () => $twoColumnTable($cell('Definition'), [], ['rowHeadColumns' => 1]),
        'contains' => ['<th scope="row" style="text-align:left">Probe</th>', '<td style="text-align:right">Definition</td>'],
    ],
    'numeric string row head columns are honored' => [
        'table' => static fn () => $twoColumnTable($cell('Definition'), [], ['rowHeadColumns' => '1']),
        'contains' => ['<th scope="row" style="text-align:left">Probe</th>'],
    ],
    'multiple row head columns retain all row headers' => [
        'table' => static fn () => $table([$body([$row([$cell('Family'), $cell('Group'), $cell('Value')])], ['rowHeadColumns' => 2])], ['alignments' => ['left', 'left', 'right']]),
        'contains' => ['<th scope="row" style="text-align:left">Family</th>', '<th scope="row" style="text-align:left">Group</th>'],
    ],
    'attributed head header cell remains column scoped' => [
        'table' => static fn () => $table([$head([$row([$cell('Metric', ['header' => true, 'classes' => ['priority']]), $cell('Value')])])], ['alignments' => ['left', 'right']]),
        'contains' => ['<th class="priority" scope="col" style="text-align:left">Metric</th>', '<th scope="col" style="text-align:right">Value</th>'],
    ],
    'direct header row colspan computes colgroup' => [
        'table' => static fn () => new AstNode('table', ['alignments' => ['left', 'right']], [$row([$cell('Direct heading', ['colspan' => 2])], ['header' => true])]),
        'contains' => ['<th scope="colgroup" colspan="2" style="text-align:left">Direct heading</th>'],
    ],
    'direct explicit header cell computes row scope' => [
        'table' => static fn () => new AstNode('table', ['alignments' => ['left', 'right']], [$row([$cell('Direct row head', ['header' => true]), $cell('Value')])]),
        'contains' => ['<th scope="row" style="text-align:left">Direct row head</th>'],
    ],
    'tfoot explicit header computes row scope' => [
        'table' => static fn () => $table([$foot([$row([$cell('Total', ['header' => true]), $cell('49')])])], ['alignments' => ['left', 'right']]),
        'contains' => ['<tfoot>', '<th scope="row" style="text-align:left">Total</th>'],
    ],
];

foreach ($headerCases as $label => $case) {
    $tests["maps upstream markdown writer default semantic table fallback header {$label}"] =
        static function (TestRunner $t) use ($case, $assertDefaultHtmlTable): void {
            $assertDefaultHtmlTable($t, $case['table'](), $case['contains']);
        };
}

$cellAlignmentCases = [
    'cell center alignment emits text-align style' => ['attrs' => ['align' => 'center'], 'contains' => ['<td style="text-align:center">Value</td>']],
    'cell left alignment override emits text-align style' => ['attrs' => ['align' => 'left'], 'contains' => ['<td style="text-align:left">Value</td>']],
    'cell right alignment override emits text-align style' => ['attrs' => ['align' => 'right'], 'contains' => ['<td style="text-align:right">Value</td>']],
    'cell vertical top emits vertical-align style' => ['attrs' => ['valign' => 'top'], 'contains' => ['vertical-align:top']],
    'cell vertical middle emits vertical-align style' => ['attrs' => ['valign' => 'middle'], 'contains' => ['vertical-align:middle']],
    'cell vertical bottom emits vertical-align style' => ['attrs' => ['valign' => 'bottom'], 'contains' => ['vertical-align:bottom']],
    'source style merges computed alignment' => ['attrs' => ['htmlAttributes' => ['style' => 'font-weight:bold'], 'align' => 'center'], 'contains' => ['style="font-weight:bold; text-align:center"']],
    'source text-align style is not duplicated' => ['attrs' => ['htmlAttributes' => ['style' => 'text-align:right'], 'align' => 'center'], 'contains' => ['style="text-align:right"', '>Value</td>']],
];

foreach ($cellAlignmentCases as $label => $case) {
    $tests["maps upstream markdown writer default semantic table fallback alignment {$label}"] =
        static function (TestRunner $t) use ($case, $cell, $twoColumnTable, $assertDefaultHtmlTable): void {
            $assertDefaultHtmlTable($t, $twoColumnTable($cell('Value', $case['attrs'])), $case['contains']);
        };
}

$cellAttributeCases = [
    'cell id attribute' => ['attrs' => ['id' => 'value-cell'], 'contains' => ['<td id="value-cell" style="text-align:right">Value</td>']],
    'cell class attribute' => ['attrs' => ['classes' => ['review-cell']], 'contains' => ['<td class="review-cell" style="text-align:right">Value</td>']],
    'cell data attribute' => ['attrs' => ['attributes' => ['data-kind' => 'value']], 'contains' => ['data-kind="value"']],
    'cell aria attribute' => ['attrs' => ['attributes' => ['aria-label' => 'Reviewed value']], 'contains' => ['aria-label="Reviewed value"']],
    'cell role attribute' => ['attrs' => ['attributes' => ['role' => 'term']], 'contains' => ['role="term"']],
    'cell headers attribute' => ['attrs' => ['attributes' => ['headers' => 'metric value']], 'contains' => ['headers="metric value"']],
    'cell scope attribute' => ['attrs' => ['attributes' => ['scope' => 'row']], 'contains' => ['scope="row"']],
    'cell abbreviation attribute' => ['attrs' => ['attributes' => ['abbr' => 'Val']], 'contains' => ['abbr="Val"']],
    'cell language attribute' => ['attrs' => ['attributes' => ['lang' => 'es']], 'contains' => ['lang="es"']],
    'cell direction attribute' => ['attrs' => ['attributes' => ['dir' => 'rtl']], 'contains' => ['dir="rtl"']],
    'cell title attribute' => ['attrs' => ['attributes' => ['title' => 'Review title']], 'contains' => ['title="Review title"']],
    'cell width attribute' => ['attrs' => ['attributes' => ['width' => '40%']], 'contains' => ['width="40%"']],
    'cell height attribute' => ['attrs' => ['attributes' => ['height' => '2em']], 'contains' => ['height="2em"']],
    'cell xml language attribute' => ['attrs' => ['attributes' => ['xml:lang' => 'fr']], 'contains' => ['xml:lang="fr"']],
    'cell html data attribute' => ['attrs' => ['htmlAttributes' => ['data-html' => 'kept']], 'contains' => ['data-html="kept"']],
    'cell html class merges classes' => ['attrs' => ['htmlAttributes' => ['class' => 'source'], 'classes' => ['review']], 'contains' => ['class="source review"']],
    'cell safe style attribute' => ['attrs' => ['attributes' => ['style' => 'font-variant:small-caps']], 'contains' => ['style="font-variant:small-caps; text-align:right"']],
    'unsafe event attribute is omitted' => ['attrs' => ['attributes' => ['onclick' => 'alert(1)', 'data-safe' => 'yes']], 'contains' => ['data-safe="yes"'], 'forbid' => ['onclick=']],
    'unsafe style url is omitted' => ['attrs' => ['htmlAttributes' => ['style' => 'background:url(javascript:bad)']], 'contains' => ['>Value</td>'], 'forbid' => ['background:url']],
];

foreach ($cellAttributeCases as $label => $case) {
    $tests["maps upstream markdown writer default semantic table fallback cell attribute {$label}"] =
        static function (TestRunner $t) use ($case, $cell, $twoColumnTable, $assertDefaultHtmlTable): void {
            $assertDefaultHtmlTable(
                $t,
                $twoColumnTable($cell('Value', $case['attrs'])),
                $case['contains'],
                $case['forbid'] ?? []
            );
        };
}

$sectionTableCaptionCases = [
    'table html summary attribute' => ['table' => static fn () => $twoColumnTable($cell('Ready'), ['htmlAttributes' => ['summary' => 'Migration inventory']]), 'contains' => ['<table summary="Migration inventory">']],
    'table html summary with language and title attributes' => ['table' => static fn () => $twoColumnTable($cell('Ready'), ['htmlAttributes' => ['summary' => 'Migration inventory', 'lang' => 'en', 'title' => 'Inventory']]), 'contains' => ['<table summary="Migration inventory" lang="en" title="Inventory">']],
    'table non markdown summary attribute' => ['table' => static fn () => $twoColumnTable($cell('Ready'), ['attributes' => ['summary' => 'Review inventory']]), 'contains' => ['<table summary="Review inventory">']],
    'thead id attribute' => ['table' => static fn () => $table([$head([$row([$cell('H'), $cell('V')])], ['id' => 'head-section']), $body([$row([$cell('A'), $cell('1')])])]), 'contains' => ['<thead id="head-section">']],
    'thead class attribute' => ['table' => static fn () => $table([$head([$row([$cell('H'), $cell('V')])], ['classes' => ['head-section']]), $body([$row([$cell('A'), $cell('1')])])]), 'contains' => ['<thead class="head-section">']],
    'thead data attribute' => ['table' => static fn () => $table([$head([$row([$cell('H'), $cell('V')])], ['attributes' => ['data-head' => 'kept']]), $body([$row([$cell('A'), $cell('1')])])]), 'contains' => ['<thead data-head="kept">']],
    'tbody id attribute' => ['table' => static fn () => $table([$body([$row([$cell('A'), $cell('1')])], ['id' => 'body-section'])]), 'contains' => ['<tbody id="body-section">']],
    'tbody class attribute' => ['table' => static fn () => $table([$body([$row([$cell('A'), $cell('1')])], ['classes' => ['body-section']])]), 'contains' => ['<tbody class="body-section">']],
    'tbody html data attribute' => ['table' => static fn () => $table([$body([$row([$cell('A'), $cell('1')])], ['htmlAttributes' => ['data-group' => 'alpha']])]), 'contains' => ['<tbody data-group="alpha">']],
    'tfoot id attribute' => ['table' => static fn () => $table([$body([$row([$cell('A'), $cell('1')])]), $foot([$row([$cell('F'), $cell('2')])], ['id' => 'foot-section'])]), 'contains' => ['<tfoot id="foot-section">']],
    'tfoot class attribute' => ['table' => static fn () => $table([$body([$row([$cell('A'), $cell('1')])]), $foot([$row([$cell('F'), $cell('2')])], ['classes' => ['foot-section']])]), 'contains' => ['<tfoot class="foot-section">']],
    'tfoot data attribute' => ['table' => static fn () => $table([$body([$row([$cell('A'), $cell('1')])]), $foot([$row([$cell('F'), $cell('2')])], ['attributes' => ['data-foot' => 'kept']])]), 'contains' => ['<tfoot data-foot="kept">']],
    'row id attribute' => ['table' => static fn () => $table([$body([$row([$cell('Row'), $cell('Attrs')], ['id' => 'row-a'])])]), 'contains' => ['<tr id="row-a">']],
    'row class attribute' => ['table' => static fn () => $table([$body([$row([$cell('Row'), $cell('Attrs')], ['classes' => ['review-row']])])]), 'contains' => ['<tr class="review-row">']],
    'row data attribute' => ['table' => static fn () => $table([$body([$row([$cell('Row'), $cell('Attrs')], ['attributes' => ['data-row' => 'kept']])])]), 'contains' => ['<tr data-row="kept">']],
    'direct row attributes render synthetic body' => ['table' => static fn () => new AstNode('table', [], [$row([$cell('Direct'), $cell('Row')], ['attributes' => ['data-direct' => 'yes']])]), 'contains' => ['<tbody>', '<tr data-direct="yes"><td>Direct</td><td>Row</td></tr>']],
    'multiple attributed bodies stay grouped' => ['table' => static fn () => $table([$body([$row([$cell('A'), $cell('1')])], ['attributes' => ['data-group' => 'a']]), $body([$row([$cell('B'), $cell('2')])], ['attributes' => ['data-group' => 'b']])]), 'contains' => ['<tbody data-group="a">', '<tbody data-group="b">']],
    'caption source id attribute with structural fallback' => ['table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['id' => 'caption-id']]]), 'contains' => ['<caption id="caption-id">Caption</caption>']],
    'caption source class attribute with structural fallback' => ['table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['classes' => ['review-caption']]]]), 'contains' => ['<caption class="review-caption">Caption</caption>']],
    'caption source data attribute with structural fallback' => ['table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-caption' => 'kept']]]]), 'contains' => ['<caption data-caption="kept">Caption</caption>']],
    'caption source html class merge with structural fallback' => ['table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['htmlAttributes' => ['class' => 'source'], 'classes' => ['review']]]]), 'contains' => ['<caption class="source review">Caption</caption>']],
    'caption short text data attribute with structural fallback' => ['table' => static fn () => $twoColumnTable($cell('Ready', ['classes' => ['trigger']]), ['shortCaption' => 'Short', 'caption' => 'Long', 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-caption' => 'kept']]]]), 'contains' => ['data-caption="kept"', 'data-pandoc-short-caption="Short"', '>Long</caption>']],
    'column source attributes require html' => ['table' => static fn () => $twoColumnTable($cell('Ready'), ['columnSources' => [['attributes' => ['data-column' => 'metric']]]]), 'contains' => ['<table>']],
];

foreach ($sectionTableCaptionCases as $label => $case) {
    $tests["maps upstream markdown writer default semantic table fallback source {$label}"] =
        static function (TestRunner $t) use ($case, $assertDefaultHtmlTable): void {
            $assertDefaultHtmlTable($t, $case['table'](), $case['contains']);
        };
}

$profileCases = [
    'node simple table request with colspan still preserves span' => ['attrs' => ['markdownTableFormat' => 'simple'], 'options' => [], 'contains' => ['colspan="2"']],
    'node grid table request with colspan still preserves span' => ['attrs' => ['markdownTableFormat' => 'grid'], 'options' => [], 'contains' => ['colspan="2"']],
    'option simple table request with colspan still preserves span' => ['attrs' => [], 'options' => ['markdownTableFormat' => 'simple-table'], 'contains' => ['colspan="2"']],
    'option grid table request with colspan still preserves span' => ['attrs' => [], 'options' => ['tableStyle' => 'grid'], 'contains' => ['colspan="2"']],
    'gfm pipe-capable format still falls back for colspan' => ['attrs' => [], 'options' => ['format' => 'gfm'], 'contains' => ['colspan="2"']],
    'gfm simple extension still falls back for colspan' => ['attrs' => ['markdownTableFormat' => 'simple'], 'options' => ['format' => 'gfm', 'extensions' => ['+simple_tables']], 'contains' => ['colspan="2"']],
    'commonmark pipe extension still falls back for colspan' => ['attrs' => [], 'options' => ['format' => 'commonmark+pipe_tables'], 'contains' => ['colspan="2"']],
    'strict grid extension still falls back for colspan' => ['attrs' => ['markdownTableFormat' => 'grid'], 'options' => ['format' => 'markdown_strict+grid_tables'], 'contains' => ['colspan="2"']],
    'pipe disabled simple enabled still falls back for colspan' => ['attrs' => [], 'options' => ['extensions' => '-pipe_tables +simple_tables'], 'contains' => ['colspan="2"']],
    'pipe and simple disabled grid enabled still falls back for colspan' => ['attrs' => [], 'options' => ['extensions' => ['-pipe_tables', '-simple_tables', '+grid_tables']], 'contains' => ['colspan="2"']],
    'commonmark explicit row header still falls back' => ['attrs' => [], 'options' => ['format' => 'commonmark+pipe_tables'], 'bodyAttrs' => ['rowHeadColumns' => 1], 'contains' => ['<th scope="row"']],
    'gfm explicit cell class still falls back' => ['attrs' => [], 'options' => ['format' => 'gfm'], 'cellAttrs' => ['classes' => ['profile-cell']], 'contains' => ['class="profile-cell"']],
];

foreach ($profileCases as $label => $case) {
    $tests["maps upstream markdown writer default semantic table fallback profile {$label}"] =
        static function (TestRunner $t) use ($case, $cell, $twoColumnTable, $assertDefaultHtmlTable): void {
            $cellAttrs = $case['cellAttrs'] ?? ['colspan' => 2];
            $assertDefaultHtmlTable(
                $t,
                $twoColumnTable($cell('Profile cell', $cellAttrs), $case['attrs'], $case['bodyAttrs'] ?? []),
                $case['contains'],
                [],
                $case['options']
            );
        };
}

$blockInlineCases = [
    'generic span inside attributed cell renders html span' => ['children' => [new AstNode('span', ['classes' => ['review']], [$text('Label')])], 'contains' => ['<span class="review">Label</span>']],
    'link attributes inside attributed cell render html anchor' => ['children' => [new AstNode('link', ['url' => 'https://example.test/a?x=1&y=2', 'title' => 'Example', 'classes' => ['external']], [$text('Example')])], 'contains' => ['<a class="external" href="https://example.test/a?x=1&amp;y=2" title="Example">Example</a>']],
    'image attributes inside attributed cell render html image' => ['children' => [new AstNode('image', ['url' => 'media/a.png', 'alt' => 'Alt text', 'title' => 'Image', 'classes' => ['thumb']])], 'contains' => ['<img class="thumb" src="media/a.png" alt="Alt text" title="Image" />']],
    'raw html inline inside attributed cell is preserved' => ['children' => [new AstNode('raw_html_inline', ['html' => '<span data-x="1">raw</span>'])], 'contains' => ['<span data-x="1">raw</span>']],
    'math inline inside attributed cell renders math span' => ['children' => [new AstNode('math', ['text' => 'a < b'])], 'contains' => ['<span class="math inline">a &lt; b</span>']],
    'soft and hard breaks inside attributed cell render br elements' => ['children' => [$text('A'), new AstNode('softbreak'), $text('B'), new AstNode('linebreak'), $text('C')], 'contains' => ['A<br />B<br />C']],
    'paragraph block inside attributed cell renders paragraph html' => ['children' => [$paragraph([$text('Para')])], 'contains' => ['<p>Para</p>']],
    'heading block inside attributed cell renders heading html' => ['children' => [new AstNode('heading', ['level' => 3, 'id' => 'cell-heading'], [$text('Cell heading')])], 'contains' => ['<h3 id="cell-heading">Cell heading</h3>']],
    'bullet list block inside attributed cell renders list html' => ['children' => [new AstNode('bullet_list', [], [new AstNode('list_item', [], [$paragraph([$text('One')])])])], 'contains' => ['<ul><li><p>One</p></li></ul>']],
    'blockquote block inside attributed cell renders blockquote html' => ['children' => [new AstNode('blockquote', [], [$paragraph([$text('Quote')])])], 'contains' => ['<blockquote><p>Quote</p></blockquote>']],
    'code block inside attributed cell renders pre code html' => ['children' => [new AstNode('code_block', ['text' => '<code>', 'classes' => ['php']])], 'contains' => ['<pre><code class="php">&lt;code&gt;</code></pre>']],
    'nested spanned table block inside attributed cell renders nested html table' => ['children' => [$table([$body([$row([$cell('Nested', ['colspan' => 2])])])], ['htmlAttributes' => ['data-nested' => 'yes']])], 'contains' => ['<table data-nested="yes">', 'colspan="2"', '>Nested</td>']],
];

foreach ($blockInlineCases as $label => $case) {
    $tests["maps upstream markdown writer default semantic table fallback content {$label}"] =
        static function (TestRunner $t) use ($case, $cell, $twoColumnTable, $assertDefaultHtmlTable): void {
            $assertDefaultHtmlTable($t, $twoColumnTable($cell($case['children'], ['id' => 'semantic-cell'])), $case['contains']);
        };
}

$tests['keeps ordinary markdown table output as pipe table by default'] =
    static function (TestRunner $t) use ($cell, $twoColumnTable, $writeDocument): void {
        $markdown = $writeDocument($twoColumnTable($cell('Ready'), ['caption' => 'Plain caption', 'attributes' => ['data-source' => 'batch']]));

        $t->contains('| Metric', $markdown);
        $t->contains(': Plain caption {data-source="batch"}', $markdown);
        $t->true(!str_contains($markdown, '<table'), 'Plain tables should stay Markdown when no structure would be lost');
    };

$tests['records markdown writer table semantic fallback final harvest mapped-case count'] =
    static function (TestRunner $t) use ($spanCases, $headerCases, $cellAlignmentCases, $cellAttributeCases, $sectionTableCaptionCases, $profileCases, $blockInlineCases): void {
        $t->same(91, count($spanCases) + count($headerCases) + count($cellAlignmentCases) + count($cellAttributeCases) + count($sectionTableCaptionCases) + count($profileCases) + count($blockInlineCases));
    };

return $tests;
