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
$writeDocument = static fn (AstNode $node): string => (new MarkdownWriter(['htmlTableAutoFallback' => true]))->write($document([$node]));
$table = static function (array $sections, array $attrs = []): AstNode {
    $attrs += [
        'alignments' => ['left', 'right', 'center'],
        'widths' => [0.2, 0.5, 0.3],
    ];

    return new AstNode('table', $attrs, $sections);
};
$twoColumnTable = static fn (AstNode $valueCell, array $attrs = []): AstNode => $table([
    $head([$row([$cell('Metric'), $cell('Value')])]),
    $body([$row([$cell('Probe'), $valueCell])]),
], $attrs);

$autoFallbackCases = [
    'body cell colspan' => [
        'table' => static fn () => $table([$body([$row([$cell('Merged', ['colspan' => 2])])])]),
        'contains' => ['<table>', 'colspan="2"', '>Merged</td>'],
    ],
    'head cell colspan' => [
        'table' => static fn () => $table([$head([$row([$cell('Merged head', ['colspan' => 2])])])]),
        'contains' => ['<thead>', '<th scope="colgroup" colspan="2"', '>Merged head</th>'],
    ],
    'foot cell colspan' => [
        'table' => static fn () => $table([$body([$row([$cell('Body'), $cell('1')])]), $foot([$row([$cell('Total', ['colspan' => 2])])])]),
        'contains' => ['<tfoot>', 'colspan="2"', '>Total</td>'],
    ],
    'body cell rowspan' => [
        'table' => static fn () => $table([$body([$row([$cell('Group', ['rowspan' => 2]), $cell('One')]), $row([$cell('Two')])])]),
        'contains' => ['rowspan="2"', '>Group</td>', '>Two</td>'],
    ],
    'body cell rowspan to section end' => [
        'table' => static fn () => $table([$body([$row([$cell('All', ['rowspan' => '0']), $cell('One')]), $row([$cell('Two')]), $row([$cell('Three')])])]),
        'contains' => ['rowspan="3"', '>All</td>', '>Three</td>'],
    ],
    'combined row and column span' => [
        'table' => static fn () => $table([$body([$row([$cell('Block', ['colspan' => 2, 'rowspan' => 2]), $cell('A')]), $row([$cell('B')])])]),
        'contains' => ['colspan="2"', 'rowspan="2"', '>Block</td>'],
    ],
    'explicit body header cell' => [
        'table' => static fn () => $table([$body([$row([$cell('Label', ['header' => true]), $cell('Value')])])]),
        'contains' => ['<th scope="row" style="text-align:left">Label</th>', '<td style="text-align:right">Value</td>'],
    ],
    'explicit head header cell remains column scoped' => [
        'table' => static fn () => $table([$head([$row([$cell('Label', ['header' => true]), $cell('Value')])])]),
        'contains' => ['<th scope="col" style="text-align:left">Label</th>', '<th scope="col" style="text-align:right">Value</th>'],
    ],
    'single row head column' => [
        'table' => static fn () => $table([$body([$row([$cell('Row label'), $cell('Value')])], ['rowHeadColumns' => 1])]),
        'contains' => ['<th scope="row" style="text-align:left">Row label</th>', '<td style="text-align:right">Value</td>'],
    ],
    'multiple row head columns' => [
        'table' => static fn () => $table([$body([$row([$cell('Family'), $cell('Group'), $cell('Value')])], ['rowHeadColumns' => 2])]),
        'contains' => ['<th scope="row" style="text-align:left">Family</th>', '<th scope="row" style="text-align:right">Group</th>', '<td style="text-align:center">Value</td>'],
    ],
    'cell source horizontal alignment' => [
        'table' => static fn () => $twoColumnTable($cell('Centered', ['align' => 'center'])),
        'contains' => ['<td style="text-align:center">Centered</td>'],
    ],
    'cell source vertical alignment' => [
        'table' => static fn () => $twoColumnTable($cell('Top', ['valign' => 'top'])),
        'contains' => ['vertical-align:top', '>Top</td>'],
    ],
    'table html summary attribute' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['htmlAttributes' => ['summary' => 'Review matrix']]),
        'contains' => ['<table summary="Review matrix">'],
    ],
    'table html language and title attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['htmlAttributes' => ['lang' => 'en', 'title' => 'Inventory']]),
        'contains' => ['<table lang="en" title="Inventory">'],
    ],
    'thead attributes' => [
        'table' => static fn () => $table([$head([$row([$cell('H'), $cell('V')])], ['id' => 'thead', 'classes' => ['review'], 'attributes' => ['data-origin' => 'html']]), $body([$row([$cell('A'), $cell('1')])])]),
        'contains' => ['<thead id="thead" class="review" data-origin="html">'],
    ],
    'tbody attributes' => [
        'table' => static fn () => $table([$body([$row([$cell('A'), $cell('1')])], ['id' => 'body-a', 'classes' => ['phase'], 'attributes' => ['data-state' => 'draft']])]),
        'contains' => ['<tbody id="body-a" class="phase" data-state="draft">'],
    ],
    'tfoot attributes' => [
        'table' => static fn () => $table([$body([$row([$cell('A'), $cell('1')])]), $foot([$row([$cell('F'), $cell('2')])], ['classes' => ['totals'], 'attributes' => ['data-total' => 'yes']])]),
        'contains' => ['<tfoot class="totals" data-total="yes">'],
    ],
    'body row attributes' => [
        'table' => static fn () => $table([$body([$row([$cell('A'), $cell('1')], ['id' => 'row-a', 'classes' => ['odd'], 'attributes' => ['data-row' => 'a']])])]),
        'contains' => ['<tr id="row-a" class="odd" data-row="a">'],
    ],
    'head row attributes' => [
        'table' => static fn () => $table([$head([$row([$cell('H'), $cell('V')], ['classes' => ['header-row'], 'attributes' => ['data-row' => 'head']])])]),
        'contains' => ['<tr class="header-row" data-row="head">'],
    ],
    'foot row attributes' => [
        'table' => static fn () => $table([$body([$row([$cell('A'), $cell('1')])]), $foot([$row([$cell('F'), $cell('2')], ['attributes' => ['data-row' => 'foot']])])]),
        'contains' => ['<tr data-row="foot">'],
    ],
    'cell id class data attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Cell', ['id' => 'cell-a', 'classes' => ['review'], 'attributes' => ['data-kind' => 'value']])),
        'contains' => ['<td id="cell-a" class="review" data-kind="value" style="text-align:right">Cell</td>'],
    ],
    'cell html style attribute' => [
        'table' => static fn () => $twoColumnTable($cell('Styled', ['htmlAttributes' => ['style' => 'font-style:italic']])),
        'contains' => ['style="font-style:italic; text-align:right"', '>Styled</td>'],
    ],
    'cell role and aria attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Term', ['attributes' => ['role' => 'term', 'aria-label' => 'Reviewed term']])),
        'contains' => ['role="term"', 'aria-label="Reviewed term"'],
    ],
    'cell header metadata attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Value', ['attributes' => ['headers' => 'h1 h2', 'scope' => 'row', 'abbr' => 'Val']])),
        'contains' => ['headers="h1 h2"', 'scope="row"', 'abbr="Val"'],
    ],
    'cell localization attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Texto', ['attributes' => ['lang' => 'es', 'dir' => 'ltr', 'title' => 'Review']])),
        'contains' => ['lang="es"', 'dir="ltr"', 'title="Review"'],
    ],
    'cell dimensions attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Sized', ['attributes' => ['width' => '20', 'height' => '10']])),
        'contains' => ['width="20"', 'height="10"'],
    ],
    'unsafe event attribute omitted' => [
        'table' => static fn () => $twoColumnTable($cell('Safe', ['attributes' => ['onclick' => 'alert(1)', 'data-safe' => 'yes']])),
        'contains' => ['data-safe="yes"', '>Safe</td>'],
        'forbid' => ['onclick='],
    ],
    'unsafe style url omitted' => [
        'table' => static fn () => $twoColumnTable($cell('Safe', ['htmlAttributes' => ['style' => 'background:url(javascript:bad)']])),
        'contains' => ['>Safe</td>'],
        'forbid' => ['background:url'],
    ],
    'caption source attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['id' => 'cap', 'classes' => ['review'], 'attributes' => ['data-source' => 'reader']]]]),
        'contains' => ['<caption id="cap" class="review" data-source="reader">Caption</caption>'],
    ],
    'caption source class merge' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['htmlAttributes' => ['class' => 'source'], 'classes' => ['review']]]]),
        'contains' => ['<caption class="source review">Caption</caption>'],
    ],
    'caption source localization attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['caption' => 'Resumen', 'captionSource' => ['sourceAttributes' => ['attributes' => ['lang' => 'es', 'dir' => 'ltr', 'title' => 'Summary']]]]),
        'contains' => ['lang="es"', 'dir="ltr"', 'title="Summary"'],
    ],
    'empty caption source attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['captionSource' => ['sourceAttributes' => ['attributes' => ['data-empty' => 'caption']]]]),
        'contains' => ['<caption data-empty="caption"></caption>'],
    ],
    'short caption with caption source attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['shortCaption' => 'Short', 'caption' => 'Long', 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-caption' => 'source']]]]),
        'contains' => ['<caption data-caption="source" data-pandoc-short-caption="Short">Long</caption>'],
    ],
    'caption inline span html attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['captionInlines' => [$text('Ready '), new AstNode('span', ['htmlAttributes' => ['data-caption' => 'inline']], [$text('now')])]]),
        'contains' => ['<caption>Ready <span data-caption="inline">now</span></caption>'],
    ],
    'short caption inline span html attributes' => [
        'table' => static fn () => $twoColumnTable($cell('Ready'), ['shortCaptionInlines' => [$text('Short'), $space(), new AstNode('span', ['htmlAttributes' => ['data-short' => 'yes']], [$text('label')])], 'caption' => 'Long']),
        'contains' => ['data-pandoc-short-caption="Short label"'],
    ],
    'cell span html attributes' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('span', ['htmlAttributes' => ['data-kind' => 'span']], [$text('Span')])])),
        'contains' => ['<span data-kind="span">Span</span>'],
    ],
    'cell code html attributes' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('code', ['text' => '<code>', 'htmlAttributes' => ['data-code' => 'yes']])])),
        'contains' => ['<code data-code="yes">&lt;code&gt;</code>'],
    ],
    'cell link html attributes' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('link', ['url' => 'https://example.test', 'htmlAttributes' => ['rel' => 'nofollow']], [$text('Example')])])),
        'contains' => ['<a rel="nofollow" href="https://example.test">Example</a>'],
    ],
    'cell image html attributes' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('image', ['url' => 'media/a.png', 'alt' => 'Alt', 'htmlAttributes' => ['loading' => 'lazy']])])),
        'contains' => ['<img loading="lazy" src="media/a.png" alt="Alt" />'],
    ],
    'cell small caps html attributes' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('small_caps', ['htmlAttributes' => ['data-style' => 'caps']], [$text('Caps')])])),
        'contains' => ['<span class="smallcaps" data-style="caps">Caps</span>'],
    ],
    'cell underline html attributes' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('underline', ['htmlAttributes' => ['data-edit' => 'insert']], [$text('Insert')])])),
        'contains' => ['<span class="underline" data-edit="insert">Insert</span>'],
    ],
    'cell strikeout html attributes' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('strikeout', ['htmlAttributes' => ['data-edit' => 'delete']], [$text('Gone')])])),
        'contains' => ['<del data-edit="delete">Gone</del>'],
    ],
    'cell superscript html attributes' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('superscript', ['htmlAttributes' => ['data-power' => '2']], [$text('2')])])),
        'contains' => ['<sup data-power="2">2</sup>'],
    ],
    'cell subscript html attributes' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('subscript', ['htmlAttributes' => ['data-chem' => 'n']], [$text('n')])])),
        'contains' => ['<sub data-chem="n">n</sub>'],
    ],
    'nested strong span html attributes' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('strong', [], [$text('Strong '), new AstNode('span', ['htmlAttributes' => ['data-nested' => 'yes']], [$text('span')])])])),
        'contains' => ['<strong>Strong <span data-nested="yes">span</span></strong>'],
    ],
    'ordered list html attributes in cell' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('ordered_list', ['start' => 4, 'htmlAttributes' => ['data-list' => 'ordered']], [new AstNode('list_item', [], [$paragraph([$text('Four')])])])])),
        'contains' => ['<ol data-list="ordered" start="4"><li><p>Four</p></li></ol>'],
    ],
    'bullet list html attributes in cell' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('bullet_list', ['htmlAttributes' => ['data-list' => 'bullet']], [new AstNode('list_item', [], [$paragraph([$text('One')])])])])),
        'contains' => ['<ul data-list="bullet"><li><p>One</p></li></ul>'],
    ],
    'heading html attributes in cell' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('heading', ['level' => 3, 'htmlAttributes' => ['data-heading' => 'cell']], [$text('Heading')])])),
        'contains' => ['<h3 data-heading="cell">Heading</h3>'],
    ],
    'code block html attributes in cell' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('code_block', ['text' => '<body>', 'htmlAttributes' => ['data-code' => 'block']])])),
        'contains' => ['<pre><code data-code="block">&lt;body&gt;</code></pre>'],
    ],
    'div html attributes in cell' => [
        'table' => static fn () => $twoColumnTable($cell([new AstNode('div', ['htmlAttributes' => ['data-div' => 'cell']], [$paragraph([$text('Note')])])])),
        'contains' => ['<div data-div="cell"><p>Note</p></div>'],
    ],
];

$tests = [];
foreach ($autoFallbackCases as $label => $case) {
    $tests["maps upstream markdown writer automatic html table fallback {$label}"] =
        static function (TestRunner $t) use ($case, $writeDocument): void {
            $markdown = $writeDocument($case['table']());

            $t->contains('<table', $markdown);
            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }
            foreach ($case['forbid'] ?? [] as $forbidden) {
                $t->true(!str_contains($markdown, $forbidden), "HTML table fallback should not contain {$forbidden}");
            }
        };
}

$tests['keeps simple markdown writer table as a pipe table by default'] =
    static function (TestRunner $t) use ($twoColumnTable, $cell, $writeDocument): void {
        $markdown = $writeDocument($twoColumnTable($cell('Ready')));

        $t->contains('| Metric', $markdown);
        $t->true(!str_contains($markdown, '<table'), 'Simple tables should stay pipe tables without structural HTML-only metadata');
    };

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
