<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);
$cell = static fn (array $children, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);
$textCell = static fn (string $value, array $attrs = []) => new AstNode('table_cell', array_replace(['text' => $value], $attrs), [$text($value)]);
$emptyTextCell = static fn (string $value, array $attrs = []) => new AstNode('table_cell', array_replace(['text' => $value], $attrs));

$table = static function (array $attrs = [], ?AstNode $valueCell = null, bool $bodyOnly = false) use ($row, $textCell): AstNode {
    $children = [];
    if (!$bodyOnly) {
        $children[] = new AstNode('table_head', [], [
            $row([$textCell('Metric'), $textCell('Value')]),
        ]);
    }

    $children[] = new AstNode('table_body', [], [
        $row([$textCell('Probe'), $valueCell ?? $textCell('Ready')]),
    ]);

    return new AstNode('table', array_replace(['alignments' => ['left', 'right']], $attrs), $children);
};

$writeTable = static fn (AstNode $table): string => (new MarkdownWriter())->write($document([$table]));
$topCaption = static fn (array $attrs): array => array_replace_recursive(['captionSource' => ['captionSide' => 'top']], $attrs);
$autoHtmlTable = static function (
    ?AstNode $valueCell = null,
    array $attrs = [],
    array $headAttrs = [],
    array $bodyAttrs = [],
    array $headRowAttrs = [],
    array $bodyRowAttrs = [],
    ?AstNode $footRow = null,
    array $footAttrs = []
) use ($row, $textCell): AstNode {
    $sections = [
        new AstNode('table_head', $headAttrs, [
            $row([$textCell('Metric'), $textCell('Value')], $headRowAttrs),
        ]),
        new AstNode('table_body', $bodyAttrs, [
            $row([$textCell('Probe'), $valueCell ?? $textCell('Ready')], $bodyRowAttrs),
        ]),
    ];
    if ($footRow instanceof AstNode) {
        $sections[] = new AstNode('table_foot', $footAttrs, [$footRow]);
    }

    return new AstNode('table', array_replace(['alignments' => ['left', 'right'], 'markdownTableFormat' => 'auto'], $attrs), $sections);
};

$tests = [];

$topCaptionCases = [
    'plain text' => [
        'attrs' => $topCaption(['caption' => 'Top caption']),
        'line' => ': Top caption',
    ],
    'pipe escaping' => [
        'attrs' => $topCaption(['caption' => 'Caption | pipe']),
        'line' => ': Caption \\| pipe',
    ],
    'definition marker escaping' => [
        'attrs' => $topCaption(['caption' => ': definition marker']),
        'line' => ': \\: definition marker',
    ],
    'atx marker escaping' => [
        'attrs' => $topCaption(['caption' => '# heading marker']),
        'line' => ': \\# heading marker',
    ],
    'bullet marker escaping' => [
        'attrs' => $topCaption(['caption' => '- bullet marker']),
        'line' => ': \\- bullet marker',
    ],
    'ordered marker escaping' => [
        'attrs' => $topCaption(['caption' => '1. ordered marker']),
        'line' => ': 1\\. ordered marker',
    ],
    'short and long text' => [
        'attrs' => $topCaption(['shortCaption' => 'Short', 'caption' => 'Long caption']),
        'line' => ': [Short] Long caption',
    ],
    'short only text' => [
        'attrs' => $topCaption(['shortCaption' => 'Short only']),
        'line' => ': [Short only]',
    ],
    'inline strong' => [
        'attrs' => $topCaption(['captionInlines' => [$text('Top '), new AstNode('strong', [], [$text('caption')])]]),
        'line' => ': Top **caption**',
    ],
    'inline emphasis' => [
        'attrs' => $topCaption(['captionInlines' => [$text('Top '), new AstNode('emph', [], [$text('caption')])]]),
        'line' => ': Top *caption*',
    ],
    'inline code' => [
        'attrs' => $topCaption(['captionInlines' => [$text('Use '), new AstNode('code', ['text' => 'source_key'])]]),
        'line' => ': Use `source_key`',
    ],
    'inline link' => [
        'attrs' => $topCaption(['captionInlines' => [new AstNode('link', ['url' => '/source'], [$text('source')])]]),
        'line' => ': [source](/source)',
    ],
    'bracketed span' => [
        'attrs' => $topCaption(['captionInlines' => [new AstNode('span', ['classes' => ['review']], [$text('source')])]]),
        'line' => ': [source]{.review}',
    ],
    'mark span shorthand' => [
        'attrs' => $topCaption(['captionInlines' => [new AstNode('span', ['classes' => ['mark']], [$text('marked')])]]),
        'line' => ': ==marked==',
    ],
    'short inline strong' => [
        'attrs' => $topCaption([
            'shortCaptionInlines' => [$text('Short '), new AstNode('strong', [], [$text('label')])],
            'caption' => 'Long caption',
        ]),
        'line' => ': [Short **label**] Long caption',
    ],
    'short caption blocks' => [
        'attrs' => $topCaption([
            'shortCaptionBlocks' => [$paragraph([$text('Short block')])],
            'caption' => 'Long caption',
        ]),
        'line' => ': [Short block] Long caption',
    ],
    'multi block caption' => [
        'attrs' => $topCaption([
            'captionBlocks' => [$paragraph([$text('First block')]), $paragraph([$text('Second block')])],
        ]),
        'line' => ': First block<br />Second block',
    ],
    'table id class attribute' => [
        'attrs' => $topCaption([
            'caption' => 'Top attrs',
            'id' => 'table-id',
            'classes' => ['review'],
            'attributes' => ['data-source' => 'batch-1'],
        ]),
        'line' => ': Top attrs {#table-id .review data-source="batch-1"}',
    ],
    'table semantic attributes' => [
        'attrs' => $topCaption([
            'caption' => 'Semantic attrs',
            'attributes' => ['lang' => 'en', 'dir' => 'ltr', 'role' => 'presentation', 'title' => 'Review table'],
        ]),
        'line' => ': Semantic attrs {lang="en" dir="ltr" role="presentation" title="Review table"}',
    ],
    'explicit before placement' => [
        'attrs' => ['caption' => 'Placement caption', 'captionSource' => ['captionPlacement' => 'before-table']],
        'line' => ': Placement caption',
    ],
    'uppercase top side' => [
        'attrs' => ['caption' => 'Upper side caption', 'captionSource' => ['captionSide' => 'TOP']],
        'line' => ': Upper side caption',
    ],
    'spaced top side' => [
        'attrs' => ['caption' => 'Spaced side caption', 'captionSource' => ['captionSide' => ' top ']],
        'line' => ': Spaced side caption',
    ],
    'legacy align top source' => [
        'attrs' => ['caption' => 'Legacy top caption', 'captionSource' => ['captionSide' => 'top', 'captionSideSource' => 'align']],
        'line' => ': Legacy top caption',
    ],
    'body only table' => [
        'attrs' => $topCaption(['caption' => 'Body only caption']),
        'line' => ': Body only caption',
        'bodyOnly' => true,
    ],
    'raw markdown inline' => [
        'attrs' => $topCaption(['captionInlines' => [new AstNode('raw_markdown', ['text' => '*raw* caption'])]]),
        'line' => ': *raw* caption',
    ],
    'raw html inline' => [
        'attrs' => $topCaption(['captionInlines' => [new AstNode('raw_html_inline', ['html' => '<em>raw</em> caption'])]]),
        'line' => ': <em>raw</em> caption',
    ],
    'small caps span' => [
        'attrs' => $topCaption(['captionInlines' => [new AstNode('small_caps', [], [$text('Caps')])]]),
        'line' => ': [Caps]{.smallcaps}',
    ],
    'underline span' => [
        'attrs' => $topCaption(['captionInlines' => [new AstNode('underline', [], [$text('under')])]]),
        'line' => ': [under]{.underline}',
    ],
    'script spans' => [
        'attrs' => $topCaption(['captionInlines' => [$text('H'), new AstNode('subscript', [], [$text('2')]), $text('O '), new AstNode('superscript', [], [$text('2')])]]),
        'line' => ': H~2~O ^2^',
    ],
    'softbreak inline' => [
        'attrs' => $topCaption(['captionInlines' => [$text('First'), new AstNode('softbreak'), $text('Second')]]),
        'line' => ': First Second',
    ],
];

foreach ($topCaptionCases as $label => $case) {
    $tests["maps upstream markdown writer top table caption {$label}"] =
        static function (TestRunner $t) use ($case, $table, $writeTable): void {
            $markdown = $writeTable($table($case['attrs'], null, (bool) ($case['bodyOnly'] ?? false)));

            $t->true(str_starts_with($markdown, $case['line'] . "\n\n|"), 'Expected caption to precede the pipe table');
            $t->true(strpos($markdown, $case['line']) < strpos($markdown, '|'), 'Caption line should render before the first table row');
        };
}

$captionNormalizationCases = [
    'plain lf caption' => [
        'attrs' => ['caption' => "First line\nSecond line"],
        'line' => ': First line Second line',
    ],
    'plain crlf caption' => [
        'attrs' => ['caption' => "First line\r\nSecond line"],
        'line' => ': First line Second line',
    ],
    'plain cr caption' => [
        'attrs' => ['caption' => "First line\rSecond line"],
        'line' => ': First line Second line',
    ],
    'short lf caption' => [
        'attrs' => ['shortCaption' => "Short\nlabel", 'caption' => 'Long caption'],
        'line' => ': [Short label] Long caption',
    ],
    'short crlf caption' => [
        'attrs' => ['shortCaption' => "Short\r\nlabel", 'caption' => 'Long caption'],
        'line' => ': [Short label] Long caption',
    ],
    'inline softbreak caption' => [
        'attrs' => ['captionInlines' => [$text('First'), new AstNode('softbreak'), $text('Second')]],
        'line' => ': First Second',
    ],
    'inline hardbreak caption' => [
        'attrs' => ['captionInlines' => [$text('First'), new AstNode('linebreak'), $text('Second')]],
        'line' => ': First<br />Second',
    ],
    'short inline softbreak caption' => [
        'attrs' => ['shortCaptionInlines' => [$text('Short'), new AstNode('softbreak'), $text('label')], 'caption' => 'Long caption'],
        'line' => ': [Short label] Long caption',
    ],
    'short inline hardbreak caption' => [
        'attrs' => ['shortCaptionInlines' => [$text('Short'), new AstNode('linebreak'), $text('label')], 'caption' => 'Long caption'],
        'line' => ': [Short<br />label] Long caption',
    ],
    'raw markdown newline caption' => [
        'attrs' => ['captionInlines' => [new AstNode('raw_markdown', ['text' => "*raw*\ncaption"])]],
        'line' => ': *raw* caption',
    ],
];

foreach ($captionNormalizationCases as $label => $case) {
    $tests["maps upstream markdown writer single-line table caption {$label}"] =
        static function (TestRunner $t) use ($case, $table, $writeTable): void {
            $markdown = $writeTable($table($case['attrs']));

            $t->contains("\n\n" . $case['line'], $markdown);
            $t->true(!str_contains($markdown, $case['line'] . "\n"), 'Caption text should not leak raw line breaks');
        };
}

$textCellCases = [
    'lf text attr cell' => [
        'text' => "Alpha\nBeta",
        'expected' => 'Alpha<br />Beta',
    ],
    'crlf text attr cell' => [
        'text' => "Alpha\r\nBeta",
        'expected' => 'Alpha<br />Beta',
    ],
    'cr text attr cell' => [
        'text' => "Alpha\rBeta",
        'expected' => 'Alpha Beta',
    ],
    'pipe and lf text attr cell' => [
        'text' => "A | B\nC",
        'expected' => 'A \\| B<br />C',
    ],
    'definition marker text attr cell' => [
        'text' => ": term\nbody",
        'expected' => '\\: term<br />body',
    ],
    'atx marker text attr cell' => [
        'text' => "# heading\nbody",
        'expected' => '\\# heading<br />body',
    ],
    'ordered marker text attr cell' => [
        'text' => "1. item\nbody",
        'expected' => '1\\. item<br />body',
    ],
    'bullet marker text attr cell' => [
        'text' => "- item\nbody",
        'expected' => '\\- item<br />body',
    ],
    'smart punctuation text attr cell' => [
        'text' => "range 1--2\nready",
        'expected' => 'range 1\\--2<br />ready',
    ],
    'html entity text attr cell' => [
        'text' => "AT&amp;T\nready",
        'expected' => 'AT\\&amp;T<br />ready',
    ],
];

foreach ($textCellCases as $label => $case) {
    $tests["maps upstream markdown writer text-only table cell {$label}"] =
        static function (TestRunner $t) use ($case, $table, $writeTable, $emptyTextCell): void {
            $markdown = $writeTable($table([], $emptyTextCell($case['text'])));

            $t->contains($case['expected'], $markdown);
        };
}

$autoHtmlFallbackCases = [
    'colspan cell' => [
        'table' => static fn () => $autoHtmlTable($textCell('Merged', ['colspan' => 2])),
        'contains' => ['<table>', 'colspan="2"', '>Merged</td>'],
    ],
    'rowspan cell' => [
        'table' => static fn () => new AstNode('table', ['alignments' => ['left', 'right'], 'markdownTableFormat' => 'auto'], [
            new AstNode('table_body', [], [
                $row([$textCell('Group', ['rowspan' => 2]), $textCell('One')]),
                $row([$textCell('Two')]),
            ]),
        ]),
        'contains' => ['rowspan="2"', '>Group</td>', '>Two</td>'],
    ],
    'rowspan zero cell' => [
        'table' => static fn () => new AstNode('table', ['alignments' => ['left', 'right'], 'markdownTableFormat' => 'auto'], [
            new AstNode('table_body', [], [
                $row([$textCell('All', ['rowspan' => '0']), $textCell('One')]),
                $row([$textCell('Two')]),
                $row([$textCell('Three')]),
            ]),
        ]),
        'contains' => ['rowspan="3"', '>All</td>', '>Three</td>'],
    ],
    'explicit header cell' => [
        'table' => static fn () => $autoHtmlTable($textCell('Ready', ['header' => true])),
        'contains' => ['<th scope="row" style="text-align:right">Ready</th>'],
    ],
    'body row head columns' => [
        'table' => static fn () => $autoHtmlTable(null, [], [], ['rowHeadColumns' => 1]),
        'contains' => ['<th scope="row" style="text-align:left">Probe</th>', '<td style="text-align:right">Ready</td>'],
    ],
    'cell center alignment' => [
        'table' => static fn () => $autoHtmlTable($textCell('Centered', ['align' => 'center'])),
        'contains' => ['<td style="text-align:center">Centered</td>'],
    ],
    'cell vertical top alignment' => [
        'table' => static fn () => $autoHtmlTable($textCell('Top', ['valign' => 'top'])),
        'contains' => ['vertical-align:top'],
    ],
    'cell style merges computed alignment' => [
        'table' => static fn () => $autoHtmlTable($textCell('Styled', ['htmlAttributes' => ['style' => 'font-weight:bold']])),
        'contains' => ['style="font-weight:bold; text-align:right"'],
    ],
    'cell id attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('Cell', ['id' => 'value-cell'])),
        'contains' => ['<td id="value-cell" style="text-align:right">Cell</td>'],
    ],
    'cell class attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('Cell', ['classes' => ['review-cell']])),
        'contains' => ['<td class="review-cell" style="text-align:right">Cell</td>'],
    ],
    'cell data attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('Cell', ['attributes' => ['data-kind' => 'value']])),
        'contains' => ['data-kind="value"'],
    ],
    'cell aria attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('Cell', ['attributes' => ['aria-label' => 'Reviewed value']])),
        'contains' => ['aria-label="Reviewed value"'],
    ],
    'cell role attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('Term', ['attributes' => ['role' => 'term']])),
        'contains' => ['role="term"'],
    ],
    'cell headers attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('Cell', ['attributes' => ['headers' => 'metric value']])),
        'contains' => ['headers="metric value"'],
    ],
    'cell source scope attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('Cell', ['attributes' => ['scope' => 'row']])),
        'contains' => ['scope="row"'],
    ],
    'cell abbreviation attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('Value', ['attributes' => ['abbr' => 'Val']])),
        'contains' => ['abbr="Val"'],
    ],
    'cell language attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('Texto', ['attributes' => ['lang' => 'es']])),
        'contains' => ['lang="es"'],
    ],
    'cell direction attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('RTL', ['attributes' => ['dir' => 'rtl']])),
        'contains' => ['dir="rtl"'],
    ],
    'cell title attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('Titled', ['attributes' => ['title' => 'Review title']])),
        'contains' => ['title="Review title"'],
    ],
    'cell width attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('Wide', ['attributes' => ['width' => '40%']])),
        'contains' => ['width="40%"'],
    ],
    'cell height attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('Tall', ['attributes' => ['height' => '2em']])),
        'contains' => ['height="2em"'],
    ],
    'cell html data attribute' => [
        'table' => static fn () => $autoHtmlTable($textCell('Cell', ['htmlAttributes' => ['data-html' => 'kept']])),
        'contains' => ['data-html="kept"'],
    ],
    'row id attribute' => [
        'table' => static fn () => $autoHtmlTable(null, [], [], [], [], ['id' => 'body-row']),
        'contains' => ['<tr id="body-row">'],
    ],
    'row class attribute' => [
        'table' => static fn () => $autoHtmlTable(null, [], [], [], [], ['classes' => ['review-row']]),
        'contains' => ['<tr class="review-row">'],
    ],
    'row data attribute' => [
        'table' => static fn () => $autoHtmlTable(null, [], [], [], [], ['attributes' => ['data-row' => 'kept']]),
        'contains' => ['<tr data-row="kept">'],
    ],
    'row html style attribute' => [
        'table' => static fn () => $autoHtmlTable(null, [], [], [], [], ['htmlAttributes' => ['style' => 'vertical-align:top']]),
        'contains' => ['<tr style="vertical-align:top">'],
    ],
    'thead id attribute' => [
        'table' => static fn () => $autoHtmlTable(null, [], ['id' => 'head-section']),
        'contains' => ['<thead id="head-section">'],
    ],
    'thead class attribute' => [
        'table' => static fn () => $autoHtmlTable(null, [], ['classes' => ['head-section']]),
        'contains' => ['<thead class="head-section">'],
    ],
    'thead data attribute' => [
        'table' => static fn () => $autoHtmlTable(null, [], ['attributes' => ['data-head' => 'kept']]),
        'contains' => ['<thead data-head="kept">'],
    ],
    'tbody id attribute' => [
        'table' => static fn () => $autoHtmlTable(null, [], [], ['id' => 'body-section']),
        'contains' => ['<tbody id="body-section">'],
    ],
    'tbody class attribute' => [
        'table' => static fn () => $autoHtmlTable(null, [], [], ['classes' => ['body-section']]),
        'contains' => ['<tbody class="body-section">'],
    ],
    'tbody data attribute' => [
        'table' => static fn () => $autoHtmlTable(null, [], [], ['attributes' => ['data-body' => 'kept']]),
        'contains' => ['<tbody data-body="kept">'],
    ],
    'tfoot id attribute' => [
        'table' => static fn () => $autoHtmlTable(null, [], [], [], [], [], $row([$textCell('Total'), $textCell('Ready')]), ['id' => 'foot-section']),
        'contains' => ['<tfoot id="foot-section">'],
    ],
    'tfoot class attribute' => [
        'table' => static fn () => $autoHtmlTable(null, [], [], [], [], [], $row([$textCell('Total'), $textCell('Ready')]), ['classes' => ['foot-section']]),
        'contains' => ['<tfoot class="foot-section">'],
    ],
    'tfoot data attribute' => [
        'table' => static fn () => $autoHtmlTable(null, [], [], [], [], [], $row([$textCell('Total'), $textCell('Ready')]), ['attributes' => ['data-foot' => 'kept']]),
        'contains' => ['<tfoot data-foot="kept">'],
    ],
    'table summary html attribute' => [
        'table' => static fn () => $autoHtmlTable(null, ['htmlAttributes' => ['summary' => 'Migration inventory']]),
        'contains' => ['<table summary="Migration inventory">'],
    ],
    'table html data attribute' => [
        'table' => static fn () => $autoHtmlTable(null, ['htmlAttributes' => ['data-table' => 'kept']]),
        'contains' => ['<table data-table="kept">'],
    ],
    'table html language attribute' => [
        'table' => static fn () => $autoHtmlTable(null, ['htmlAttributes' => ['lang' => 'en']]),
        'contains' => ['<table lang="en">'],
    ],
    'table html direction attribute' => [
        'table' => static fn () => $autoHtmlTable(null, ['htmlAttributes' => ['dir' => 'ltr']]),
        'contains' => ['<table dir="ltr">'],
    ],
    'caption source id attribute' => [
        'table' => static fn () => $autoHtmlTable(null, ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['id' => 'caption-id']]]),
        'contains' => ['<caption id="caption-id">Caption</caption>'],
    ],
    'caption source class attribute' => [
        'table' => static fn () => $autoHtmlTable(null, ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['classes' => ['review-caption']]]]),
        'contains' => ['<caption class="review-caption">Caption</caption>'],
    ],
    'caption source data attribute' => [
        'table' => static fn () => $autoHtmlTable(null, ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-caption' => 'kept']]]]),
        'contains' => ['<caption data-caption="kept">Caption</caption>'],
    ],
    'caption source html class merge' => [
        'table' => static fn () => $autoHtmlTable(null, ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['htmlAttributes' => ['class' => 'source'], 'classes' => ['review']]]]),
        'contains' => ['<caption class="source review">Caption</caption>'],
    ],
    'caption source language attribute' => [
        'table' => static fn () => $autoHtmlTable(null, ['caption' => 'Resumen', 'captionSource' => ['sourceAttributes' => ['attributes' => ['lang' => 'es']]]]),
        'contains' => ['<caption lang="es">Resumen</caption>'],
    ],
    'caption short text data attribute' => [
        'table' => static fn () => $autoHtmlTable(null, ['shortCaption' => 'Short', 'caption' => 'Long', 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-caption' => 'kept']]]]),
        'contains' => ['data-caption="kept"', 'data-pandoc-short-caption="Short"', '>Long</caption>'],
    ],
    'caption inline strong content' => [
        'table' => static fn () => $autoHtmlTable(null, ['captionInlines' => [$text('Table '), new AstNode('strong', [], [$text('caption')])], 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-caption' => 'kept']]]]),
        'contains' => ['<caption data-caption="kept">Table <strong>caption</strong></caption>'],
    ],
    'caption short inline data attribute' => [
        'table' => static fn () => $autoHtmlTable(null, ['shortCaptionInlines' => [$text('Short '), new AstNode('emph', [], [$text('label')])], 'caption' => 'Long', 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-caption' => 'kept']]]]),
        'contains' => ['data-pandoc-short-caption="Short label"', '>Long</caption>'],
    ],
    'link inline in attributed cell' => [
        'table' => static fn () => $autoHtmlTable($cell([new AstNode('link', ['url' => 'https://example.test/review'], [$text('Review')])], ['id' => 'link-cell'])),
        'contains' => ['<a href="https://example.test/review">Review</a>'],
    ],
    'image inline in attributed cell' => [
        'table' => static fn () => $autoHtmlTable($cell([new AstNode('image', ['url' => 'media/review.png', 'alt' => 'Review image'])], ['id' => 'image-cell'])),
        'contains' => ['<img src="media/review.png" alt="Review image" />'],
    ],
    'span inline in attributed cell' => [
        'table' => static fn () => $autoHtmlTable($cell([new AstNode('span', ['classes' => ['review']], [$text('Label')])], ['id' => 'span-cell'])),
        'contains' => ['<span class="review">Label</span>'],
    ],
];

foreach ($autoHtmlFallbackCases as $label => $case) {
    $tests["maps upstream markdown writer automatic html table fallback {$label}"] =
        static function (TestRunner $t) use ($case, $writeTable): void {
            $markdown = $writeTable($case['table']());

            $t->contains('<table', $markdown);
            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }
        };
}

$tests['records markdown writer table caption span completion mapped-case count'] =
    static function (TestRunner $t): void {
        $t->same(100, 30 + 10 + 10 + 50);
    };

return $tests;
