<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$writeDocument = static fn (AstNode $node): string => (new MarkdownWriter())->write($document([$node]));
$writeInlineDocument = static fn (AstNode $inline): string => (new MarkdownWriter())->write($document([$paragraph([$inline])]));

$cell = static function (array|string $children, array $attrs = []) use ($text): AstNode {
    if (is_string($children)) {
        return new AstNode('table_cell', array_merge(['text' => $children], $attrs), [$text($children)]);
    }

    return new AstNode('table_cell', $attrs, $children);
};
$row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);
$body = static fn (array $rows, array $attrs = []): AstNode => new AstNode('table_body', $attrs, $rows);
$head = static fn (array $rows, array $attrs = []): AstNode => new AstNode('table_head', $attrs, $rows);
$foot = static fn (array $rows, array $attrs = []): AstNode => new AstNode('table_foot', $attrs, $rows);

$oneColumnTable = static function (array $attrs) use ($row, $body, $cell): AstNode {
    return new AstNode('table', $attrs, [
        $body([
            $row([$cell('H')]),
            $row([$cell('x')]),
        ], ['headRowCount' => 1]),
    ]);
};

$twoColumnTable = static function (AstNode $valueCell, array $attrs = []) use ($head, $body, $row, $cell): AstNode {
    return new AstNode('table', array_replace(['alignments' => ['left', 'right']], $attrs), [
        $head([$row([$cell('Metric'), $cell('Value')])]),
        $body([$row([$cell('Probe'), $valueCell])]),
    ]);
};

$htmlTable = static function (array $sections, array $attrs = []): AstNode {
    return new AstNode('table', array_replace(['markdownTableFormat' => 'html'], $attrs), $sections);
};

$tests = [];

$columnSpecCases = [
    'tuple left alignment and numeric width' => [
        'columnSpecs' => [[['t' => 'AlignLeft'], ['t' => 'ColWidth', 'c' => 0.1]]],
        'expected' => '|:---|',
    ],
    'tuple right alignment and wrapped width' => [
        'columnSpecs' => [[['t' => 'AlignRight'], ['t' => 'ColWidth', 'c' => [0.2]]]],
        'expected' => '|-------:|',
    ],
    'tuple center alignment and width' => [
        'columnSpecs' => [[['t' => 'AlignCenter'], ['t' => 'ColWidth', 'c' => 0.15]]],
        'expected' => '|:----:|',
    ],
    'tuple default alignment and default width' => [
        'columnSpecs' => [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
        'expected' => '|---|',
    ],
    'record left alignment and string width' => [
        'columnSpecs' => [['alignment' => 'align-left', 'width' => '0.25']],
        'expected' => '|:---------|',
    ],
    'record right alignment and native width' => [
        'columnSpecs' => [['align' => 'AlignRight', 'colWidth' => ['t' => 'ColWidth', 'c' => 0.25]]],
        'expected' => '|---------:|',
    ],
    'record center style alignment and wrapped width' => [
        'columnSpecs' => [['alignment' => 'text-align:center;', 'width' => [0.25]]],
        'expected' => '|:--------:|',
    ],
    'record unknown alignment defaults cleanly' => [
        'columnSpecs' => [['alignment' => 'AlignDecimal', 'width' => ['t' => 'ColWidthDefault']]],
        'expected' => '|---|',
    ],
    'record source metadata still renders width' => [
        'columnSpecs' => [['alignment' => 'left', 'width' => 0.125, 'source' => ['kind' => 'col', 'colIndex' => 0]]],
        'expected' => '|:----|',
    ],
    'record zero width falls back to content width' => [
        'columnSpecs' => [['alignment' => 'right', 'width' => 0]],
        'expected' => '|--:|',
    ],
    'record negative width falls back to content width' => [
        'columnSpecs' => [['alignment' => 'center', 'width' => -0.2]],
        'expected' => '|:-:|',
    ],
    'record nonnumeric width falls back to content width' => [
        'columnSpecs' => [['alignment' => 'left', 'width' => 'auto']],
        'expected' => '|:--|',
    ],
];

foreach ($columnSpecCases as $label => $case) {
    $tests["maps upstream markdown writer normalized column spec {$label}"] =
        static function (TestRunner $t) use ($case, $oneColumnTable, $writeDocument): void {
            $markdown = $writeDocument($oneColumnTable(['columnSpecs' => $case['columnSpecs']]));

            $t->contains($case['expected'], $markdown);
        };
}

$directRowCases = [
    'pipe table column count from direct rows' => static function (TestRunner $t) use ($row, $cell, $writeDocument): void {
        $table = new AstNode('table', [], [
            $row([$cell('A'), $cell('B')]),
            $row([$cell('C'), $cell('D')]),
        ]);
        $markdown = $writeDocument($table);

        $t->contains('| A', $markdown);
        $t->contains('| C', $markdown);
        $t->contains('| B', $markdown);
        $t->contains('| D', $markdown);
    },
    'direct rows use column spec alignment' => static function (TestRunner $t) use ($row, $cell, $writeDocument): void {
        $table = new AstNode('table', [
            'columnSpecs' => [
                [['t' => 'AlignLeft'], ['t' => 'ColWidthDefault']],
                [['t' => 'AlignRight'], ['t' => 'ColWidthDefault']],
            ],
        ], [
            $row([$cell('A'), $cell('B')]),
        ]);
        $markdown = $writeDocument($table);

        $t->contains('|:--|--:|', $markdown);
    },
    'html fallback preserves direct rows in tbody' => static function (TestRunner $t) use ($row, $cell, $htmlTable, $writeDocument): void {
        $table = $htmlTable([
            $row([$cell('A'), $cell('B')]),
            $row([$cell('C'), $cell('D')]),
        ], ['alignments' => ['left', 'right']]);
        $markdown = $writeDocument($table);

        $t->contains('<tbody>', $markdown);
        $t->contains('<td style="text-align:left">A</td>', $markdown);
        $t->contains('<td style="text-align:right">D</td>', $markdown);
    },
    'html fallback places direct rows before foot' => static function (TestRunner $t) use ($row, $cell, $foot, $htmlTable, $writeDocument): void {
        $table = $htmlTable([
            $row([$cell('Body')]),
            $foot([$row([$cell('Total')])]),
        ], ['alignments' => ['left']]);
        $markdown = $writeDocument($table);

        $t->true(strpos($markdown, '>Body</td>') < strpos($markdown, '<tfoot>'), 'Direct rows should render before table foot');
        $t->contains('<tfoot>', $markdown);
    },
    'direct rows can carry row attributes in html fallback' => static function (TestRunner $t) use ($row, $cell, $htmlTable, $writeDocument): void {
        $table = $htmlTable([
            $row([$cell('A')], ['classes' => 'direct-row', 'attributes' => [['data-kind', 'source']]]),
        ], ['alignments' => ['left']]);
        $markdown = $writeDocument($table);

        $t->contains('<tr class="direct-row" data-kind="source">', $markdown);
    },
];

foreach ($directRowCases as $label => $case) {
    $tests["maps upstream markdown writer {$label}"] = $case;
}

$captionCases = [
    'caption attr inline array' => [
        'attrs' => ['caption' => [$text('Alpha '), new AstNode('strong', [], [$text('Beta')])]],
        'expected' => ': Alpha **Beta**',
    ],
    'caption attr paragraph node' => [
        'attrs' => ['caption' => $paragraph([$text('Para '), new AstNode('emph', [], [$text('caption')])])],
        'expected' => ': Para *caption*',
    ],
    'caption source inline array' => [
        'attrs' => ['captionSource' => ['captionInlines' => [$text('Source '), new AstNode('code', ['text' => 'caption'])]]],
        'expected' => ': Source `caption`',
    ],
    'caption source text' => [
        'attrs' => ['captionSource' => ['text' => 'Source caption']],
        'expected' => ': Source caption',
    ],
    'caption source block array' => [
        'attrs' => ['captionSource' => ['captionBlocks' => [$paragraph([$text('Block caption')])]]],
        'expected' => ': Block caption',
    ],
    'caption source generic blocks' => [
        'attrs' => ['captionSource' => ['blocks' => [$plain([$text('Generic block')])]]],
        'expected' => ': Generic block',
    ],
    'short caption attr inline array' => [
        'attrs' => ['shortCaption' => [$text('Short '), new AstNode('strong', [], [$text('label')])], 'caption' => 'Long'],
        'expected' => ': [Short **label**] Long',
    ],
    'short caption attr paragraph node' => [
        'attrs' => ['shortCaption' => $paragraph([$text('Short '), new AstNode('emph', [], [$text('node')])]), 'caption' => 'Long'],
        'expected' => ': [Short *node*] Long',
    ],
    'short caption source inline array' => [
        'attrs' => ['captionSource' => ['shortCaptionInlines' => [$text('Short'), $space(), new AstNode('code', ['text' => 'src'])]], 'caption' => 'Long'],
        'expected' => ': [Short `src`] Long',
    ],
    'short caption source text' => [
        'attrs' => ['captionSource' => ['shortText' => 'Short source'], 'caption' => 'Long'],
        'expected' => ': [Short source] Long',
    ],
    'caption source text escapes brackets' => [
        'attrs' => ['captionSource' => ['caption' => '[Source]']],
        'expected' => ': \\[Source\\]',
    ],
    'caption attr inline hard break' => [
        'attrs' => ['caption' => [$text('Alpha'), new AstNode('linebreak'), $text('Beta')]],
        'expected' => ': Alpha<br />Beta',
    ],
    'caption source inline soft break' => [
        'attrs' => ['captionSource' => ['inlines' => [$text('Alpha'), new AstNode('softbreak'), $text('Beta')]]],
        'expected' => ': Alpha Beta',
    ],
    'source short caption without long caption' => [
        'attrs' => ['captionSource' => ['short' => 'Only short']],
        'expected' => ': [Only short]',
    ],
];

foreach ($captionCases as $label => $case) {
    $tests["maps upstream markdown writer normalized table caption {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $cell, $writeDocument): void {
            $markdown = $writeDocument($twoColumnTable($cell('Ready'), $case['attrs']));

            $t->contains($case['expected'], $markdown);
        };
}

$htmlCaptionCases = [
    'html caption attr inline array' => [
        'attrs' => ['caption' => [$text('HTML '), new AstNode('strong', [], [$text('caption')])]],
        'expected' => '<caption>HTML <strong>caption</strong></caption>',
    ],
    'html caption source text' => [
        'attrs' => ['captionSource' => ['text' => 'HTML source caption']],
        'expected' => '<caption>HTML source caption</caption>',
    ],
    'html short caption source attribute' => [
        'attrs' => ['caption' => 'Long', 'captionSource' => ['shortCaptionInlines' => [$text('Short'), $space(), $text('HTML')]]],
        'expected' => 'data-pandoc-short-caption="Short HTML"',
    ],
    'html caption source attributes from pair list' => [
        'attrs' => [
            'caption' => 'Long',
            'captionSource' => ['sourceAttributes' => ['classes' => 'caption-source', 'attributes' => [['data-caption', 'reader']]]],
        ],
        'expected' => '<caption class="caption-source" data-caption="reader">Long</caption>',
    ],
];

foreach ($htmlCaptionCases as $label => $case) {
    $tests["maps upstream markdown writer normalized {$label}"] =
        static function (TestRunner $t) use ($case, $head, $body, $row, $cell, $htmlTable, $writeDocument): void {
            $table = $htmlTable([
                $head([$row([$cell('H')])]),
                $body([$row([$cell('x')])]),
            ], array_replace(['alignments' => ['left']], $case['attrs']));
            $markdown = $writeDocument($table);

            $t->contains($case['expected'], $markdown);
        };
}

$spanCases = [
    'classes string span' => [
        'inline' => new AstNode('span', ['classes' => 'review source'], [$text('label')]),
        'expected' => '[label]{.review .source}',
    ],
    'className string span' => [
        'inline' => new AstNode('span', ['className' => 'review source'], [$text('label')]),
        'expected' => '[label]{.review .source}',
    ],
    'attribute pair list span' => [
        'inline' => new AstNode('span', ['attributes' => [['data-source', 'pair'], ['aria-label', 'Pair label']]], [$text('label')]),
        'expected' => '[label]{data-source="pair" aria-label="Pair label"}',
    ],
    'top level language and direction span' => [
        'inline' => new AstNode('span', ['lang' => 'ar', 'dir' => 'rtl'], [$text('label')]),
        'expected' => '[label]{dir="rtl" lang="ar"}',
    ],
    'top level role title and xml language span' => [
        'inline' => new AstNode('span', ['role' => 'term', 'title' => 'Term title', 'xml:lang' => 'es'], [$text('term')]),
        'expected' => '[term]{role="term" xml:lang="es" title="Term title"}',
    ],
    'mark class string shortcut' => [
        'inline' => new AstNode('span', ['classes' => 'mark'], [$text('marked')]),
        'expected' => '==marked==',
    ],
    'abbreviation class string and pair title' => [
        'inline' => new AstNode('span', ['classes' => 'abbr', 'attributes' => [['title', 'Hypertext Markup Language']]], [$text('HTML')]),
        'expected' => "HTML\n\n*[HTML]: Hypertext Markup Language",
    ],
    'small caps class string merge' => [
        'inline' => new AstNode('small_caps', ['classes' => 'source'], [$text('Small')]),
        'expected' => '[Small]{.smallcaps .source}',
    ],
    'underline top level language' => [
        'inline' => new AstNode('underline', ['lang' => 'en'], [$text('insert')]),
        'expected' => '[insert]{.underline lang="en"}',
    ],
    'strikeout class string attributed span' => [
        'inline' => new AstNode('strikeout', ['classes' => 'edit'], [$text('gone')]),
        'expected' => '[gone]{.strikeout .edit}',
    ],
    'superscript className attributed span' => [
        'inline' => new AstNode('superscript', ['className' => 'power'], [$text('2')]),
        'expected' => '[2]{.superscript .power}',
    ],
    'subscript pair attribute attributed span' => [
        'inline' => new AstNode('subscript', ['attributes' => [['data-formula', 'chem']]], [$text('2')]),
        'expected' => '[2]{.subscript data-formula="chem"}',
    ],
];

foreach ($spanCases as $label => $case) {
    $tests["maps upstream markdown writer normalized {$label}"] =
        static function (TestRunner $t) use ($case, $writeInlineDocument): void {
            $t->same($case['expected'], $writeInlineDocument($case['inline']));
        };
}

$htmlAttributeCases = [
    'table classes string and attribute pairs' => [
        'tableAttrs' => ['classes' => 'wide review', 'attributes' => [['data-source', 'reader']]],
        'contains' => '<table class="wide review" data-source="reader">',
    ],
    'table htmlAttributes pair list' => [
        'tableAttrs' => ['htmlAttributes' => [['summary', 'Migration queue']]],
        'contains' => '<table summary="Migration queue">',
    ],
    'thead classes string' => [
        'headAttrs' => ['classes' => 'head-source'],
        'contains' => '<thead class="head-source">',
    ],
    'tbody attribute pairs' => [
        'bodyAttrs' => ['attributes' => [['data-body', 'source']]],
        'contains' => '<tbody data-body="source">',
    ],
    'row className string and attribute pairs' => [
        'rowAttrs' => ['className' => 'row-source', 'attributes' => [['data-row', 'source']]],
        'contains' => '<tr class="row-source" data-row="source">',
    ],
    'cell classes string and attribute pairs' => [
        'cellAttrs' => ['classes' => 'cell-source', 'attributes' => [['data-cell', 'source']]],
        'contains' => '<td class="cell-source" data-cell="source" style="text-align:left">x</td>',
    ],
    'cell htmlAttributes pair list' => [
        'cellAttrs' => ['htmlAttributes' => [['title', 'Cell title']]],
        'contains' => '<td title="Cell title" style="text-align:left">x</td>',
    ],
    'span classes string inside html table' => [
        'cellChildren' => [new AstNode('span', ['classes' => 'inline-source'], [$text('x')])],
        'contains' => '<span class="inline-source">x</span>',
    ],
    'span attribute pairs inside html table' => [
        'cellChildren' => [new AstNode('span', ['attributes' => [['data-inline', 'source']]], [$text('x')])],
        'contains' => '<span data-inline="source">x</span>',
    ],
    'code htmlAttributes pair list inside html table' => [
        'cellChildren' => [new AstNode('code', ['text' => 'x', 'htmlAttributes' => [['data-code', 'source']]])],
        'contains' => '<code data-code="source">x</code>',
    ],
];

foreach ($htmlAttributeCases as $label => $case) {
    $tests["maps upstream markdown writer normalized html attributes {$label}"] =
        static function (TestRunner $t) use ($case, $head, $body, $row, $cell, $htmlTable, $writeDocument): void {
            $cellNode = isset($case['cellChildren'])
                ? $cell($case['cellChildren'], $case['cellAttrs'] ?? [])
                : $cell('x', $case['cellAttrs'] ?? []);
            $table = $htmlTable([
                $head([$row([$cell('H')])], $case['headAttrs'] ?? []),
                $body([$row([$cellNode], $case['rowAttrs'] ?? [])], $case['bodyAttrs'] ?? []),
            ], array_replace(['alignments' => ['left']], $case['tableAttrs'] ?? []));
            $markdown = $writeDocument($table);

            $t->contains($case['contains'], $markdown);
        };
}

return $tests;
