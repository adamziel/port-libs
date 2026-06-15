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
        'contains' => ['<thead>', '<th scope="col" colspan="2"', '>Merged head</th>'],
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

return $tests;
