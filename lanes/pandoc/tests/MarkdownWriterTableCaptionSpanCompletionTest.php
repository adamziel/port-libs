<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
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

$captionSourceAttributeCases = [
    'source id' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['id' => 'cap']]],
        'line' => ': Caption {#cap}',
    ],
    'source class' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['classes' => ['caption']]]],
        'line' => ': Caption {.caption}',
    ],
    'source classes' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['classes' => ['review', 'caption']]]],
        'line' => ': Caption {.review .caption}',
    ],
    'source data attribute' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-source' => 'reader']]]],
        'line' => ': Caption {data-source="reader"}',
    ],
    'source aria label' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['aria-label' => 'Review caption']]]],
        'line' => ': Caption {aria-label="Review caption"}',
    ],
    'source language' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['lang' => 'es']]]],
        'line' => ': Caption {lang="es"}',
    ],
    'source direction' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['dir' => 'rtl']]]],
        'line' => ': Caption {dir="rtl"}',
    ],
    'source role' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['role' => 'note']]]],
        'line' => ': Caption {role="note"}',
    ],
    'source title' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['title' => 'Source caption']]]],
        'line' => ': Caption {title="Source caption"}',
    ],
    'source xml language' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['xml:lang' => 'fr']]]],
        'line' => ': Caption {xml:lang="fr"}',
    ],
    'html source id' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['htmlAttributes' => ['id' => 'html-cap']]]],
        'line' => ': Caption {#html-cap}',
    ],
    'html source class' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['htmlAttributes' => ['class' => 'source caption']]]],
        'line' => ': Caption {.source .caption}',
    ],
    'html and parsed source classes' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['htmlAttributes' => ['class' => 'source'], 'classes' => ['review']]]],
        'line' => ': Caption {.source .review}',
    ],
    'html source data attribute' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['htmlAttributes' => ['data-source' => 'html']]]],
        'line' => ': Caption {data-source="html"}',
    ],
    'html language direction attributes' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['htmlAttributes' => ['lang' => 'en', 'dir' => 'ltr']]]],
        'line' => ': Caption {lang="en" dir="ltr"}',
    ],
    'combined source id class data' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['id' => 'cap', 'classes' => ['review'], 'attributes' => ['data-source' => 'reader']]]],
        'line' => ': Caption {#cap .review data-source="reader"}',
    ],
    'quoted source data attribute' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-label' => 'a "quoted" value']]]],
        'line' => ': Caption {data-label="a \\"quoted\\" value"}',
    ],
    'backslash source title' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['title' => 'path \\ source']]]],
        'line' => ': Caption {title="path \\\\ source"}',
    ],
    'table id wins over caption id' => [
        'attrs' => ['caption' => 'Caption', 'id' => 'table-id', 'captionSource' => ['sourceAttributes' => ['id' => 'cap', 'attributes' => ['data-caption' => 'source']]]],
        'line' => ': Caption {#table-id data-caption="source"}',
        'forbid' => '#cap',
    ],
    'table class merges caption class' => [
        'attrs' => ['caption' => 'Caption', 'classes' => ['table'], 'captionSource' => ['sourceAttributes' => ['classes' => ['caption']]]],
        'line' => ': Caption {.table .caption}',
    ],
    'duplicate caption class is deduplicated' => [
        'attrs' => ['caption' => 'Caption', 'classes' => ['table'], 'captionSource' => ['sourceAttributes' => ['classes' => ['table', 'caption']]]],
        'line' => ': Caption {.table .caption}',
    ],
    'table attribute wins duplicate source attribute' => [
        'attrs' => ['caption' => 'Caption', 'attributes' => ['data-source' => 'table'], 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-source' => 'caption', 'data-caption' => 'source']]]],
        'line' => ': Caption {data-source="table" data-caption="source"}',
        'forbid' => 'data-source="caption"',
    ],
    'docx source metadata is filtered' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-docx-style' => 'Caption', 'data-source' => 'reader']]]],
        'line' => ': Caption {data-source="reader"}',
        'forbid' => 'data-docx-style',
    ],
    'event handler source metadata is filtered' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['onclick' => 'alert(1)', 'data-source' => 'reader']]]],
        'line' => ': Caption {data-source="reader"}',
        'forbid' => 'onclick',
    ],
    'style source metadata is filtered' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['style' => 'color:red', 'data-source' => 'reader']]]],
        'line' => ': Caption {data-source="reader"}',
        'forbid' => 'style=',
    ],
    'summary source metadata is filtered' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['summary' => 'old summary', 'data-source' => 'reader']]]],
        'line' => ': Caption {data-source="reader"}',
        'forbid' => 'summary=',
    ],
    'empty caption keeps source id' => [
        'attrs' => ['captionSource' => ['sourceAttributes' => ['id' => 'cap']]],
        'line' => ': {#cap}',
    ],
    'short-only caption keeps source class' => [
        'attrs' => ['shortCaption' => 'Short only', 'captionSource' => ['sourceAttributes' => ['classes' => ['caption']]]],
        'line' => ': [Short only] {.caption}',
    ],
    'short and long caption keeps source data' => [
        'attrs' => ['shortCaption' => 'Short', 'caption' => 'Long', 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-caption' => 'source']]]],
        'line' => ': [Short] Long {data-caption="source"}',
    ],
    'inline caption keeps source data' => [
        'attrs' => ['captionInlines' => [$text('Inline '), new AstNode('strong', [], [$text('caption')])], 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-caption' => 'inline']]]],
        'line' => ': Inline **caption** {data-caption="inline"}',
    ],
    'block caption keeps source data' => [
        'attrs' => ['captionBlocks' => [$paragraph([$text('Block caption')])], 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-caption' => 'block']]]],
        'line' => ': Block caption {data-caption="block"}',
    ],
    'raw markdown caption keeps source data' => [
        'attrs' => ['captionInlines' => [new AstNode('raw_markdown', ['text' => '*raw* caption'])], 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-caption' => 'raw']]]],
        'line' => ': *raw* caption {data-caption="raw"}',
    ],
    'mark span caption keeps source data' => [
        'attrs' => ['captionInlines' => [new AstNode('span', ['classes' => ['mark']], [$text('marked')])], 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-caption' => 'mark']]]],
        'line' => ': ==marked== {data-caption="mark"}',
    ],
    'html class and source id combine' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['id' => 'cap', 'htmlAttributes' => ['class' => 'source']]]],
        'line' => ': Caption {#cap .source}',
    ],
    'html id takes precedence inside source attributes' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['id' => 'cap', 'htmlAttributes' => ['id' => 'html-cap']]]],
        'line' => ': Caption {#html-cap}',
        'forbid' => '#cap',
    ],
    'numeric source data attribute' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-count' => 42]]]],
        'line' => ': Caption {data-count="42"}',
    ],
    'boolean source data attribute' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['data-enabled' => true]]]],
        'line' => ': Caption {data-enabled="1"}',
    ],
    'uppercase source attribute name normalizes' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['attributes' => ['DATA-SOURCE' => 'Reader']]]],
        'line' => ': Caption {data-source="Reader"}',
    ],
    'uppercase html aria name normalizes' => [
        'attrs' => ['caption' => 'Caption', 'captionSource' => ['sourceAttributes' => ['htmlAttributes' => ['ARIA-LABEL' => 'Caption label']]]],
        'line' => ': Caption {aria-label="Caption label"}',
    ],
    'table language merges source direction' => [
        'attrs' => ['caption' => 'Caption', 'attributes' => ['lang' => 'en'], 'captionSource' => ['sourceAttributes' => ['attributes' => ['dir' => 'ltr']]]],
        'line' => ': Caption {lang="en" dir="ltr"}',
    ],
];

foreach ($captionSourceAttributeCases as $label => $case) {
    $tests["maps upstream markdown writer table caption source attributes {$label}"] =
        static function (TestRunner $t) use ($case, $table, $writeTable): void {
            $markdown = $writeTable($table($case['attrs']));

            $t->contains("\n\n" . $case['line'], $markdown);
            if (isset($case['forbid'])) {
                $t->true(!str_contains($markdown, $case['forbid']), 'Caption source attributes should not leak filtered or overridden metadata');
            }
        };
}

$captionSourcePlacementCases = [
    'html position before table sections' => [
        'attrs' => ['caption' => 'Before source', 'captionSource' => ['position' => 'before-table-sections', 'sourceAttributes' => ['attributes' => ['data-pos' => 'before']]]],
        'line' => ': Before source {data-pos="before"}',
        'before' => true,
    ],
    'markdown position before table' => [
        'attrs' => ['caption' => 'Before table', 'captionSource' => ['position' => 'before-table', 'sourceAttributes' => ['attributes' => ['data-pos' => 'before-table']]]],
        'line' => ': Before table {data-pos="before-table"}',
        'before' => true,
    ],
    'source position before table sections' => [
        'attrs' => ['caption' => 'Source before', 'captionSource' => ['sourcePosition' => 'before-table-sections', 'sourceAttributes' => ['attributes' => ['data-pos' => 'source-before']]]],
        'line' => ': Source before {data-pos="source-before"}',
        'before' => true,
    ],
    'caption placement before table sections' => [
        'attrs' => ['caption' => 'Placement before', 'captionSource' => ['captionPlacement' => 'before-table-sections', 'sourceAttributes' => ['attributes' => ['data-pos' => 'placement-before']]]],
        'line' => ': Placement before {data-pos="placement-before"}',
        'before' => true,
    ],
    'caption side top keeps source attributes before table' => [
        'attrs' => ['caption' => 'Side before', 'captionSource' => ['captionSide' => 'top', 'sourceAttributes' => ['attributes' => ['data-pos' => 'side-top']]]],
        'line' => ': Side before {data-pos="side-top"}',
        'before' => true,
    ],
    'empty caption with before position keeps source id' => [
        'attrs' => ['captionSource' => ['position' => 'before-table-sections', 'sourceAttributes' => ['id' => 'cap']]],
        'line' => ': {#cap}',
        'before' => true,
    ],
    'body-only table keeps leading source caption' => [
        'attrs' => ['caption' => 'Body before', 'captionSource' => ['position' => 'before-table-sections', 'sourceAttributes' => ['attributes' => ['data-pos' => 'body']]]],
        'line' => ': Body before {data-pos="body"}',
        'before' => true,
        'bodyOnly' => true,
    ],
    'html position after table sections remains trailing' => [
        'attrs' => ['caption' => 'After source', 'captionSource' => ['position' => 'after-table-sections', 'sourceAttributes' => ['attributes' => ['data-pos' => 'after']]]],
        'line' => ': After source {data-pos="after"}',
        'before' => false,
    ],
    'odf following table source remains trailing' => [
        'attrs' => ['caption' => 'Following source', 'captionSource' => ['sourcePosition' => 'following-table', 'sourceAttributes' => ['attributes' => ['data-odf-table-caption-style-name' => 'Caption']]]],
        'line' => ': Following source {data-odf-table-caption-style-name="Caption"}',
        'before' => false,
    ],
    'explicit after placement remains trailing' => [
        'attrs' => ['caption' => 'After placement', 'captionSource' => ['captionPlacement' => 'after-table', 'sourceAttributes' => ['attributes' => ['data-pos' => 'after-placement']]]],
        'line' => ': After placement {data-pos="after-placement"}',
        'before' => false,
    ],
];

foreach ($captionSourcePlacementCases as $label => $case) {
    $tests["maps upstream markdown writer table caption source placement {$label}"] =
        static function (TestRunner $t) use ($case, $table, $writeTable): void {
            $markdown = $writeTable($table($case['attrs'], null, (bool) ($case['bodyOnly'] ?? false)));

            if ((bool) $case['before']) {
                $t->true(str_starts_with($markdown, $case['line'] . "\n\n|"), 'Expected source caption placement to precede the pipe table');
                return;
            }

            $t->contains("\n\n" . $case['line'], $markdown);
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

$tests['records markdown writer table caption span completion mapped-case count'] =
    static function (TestRunner $t): void {
        $t->same(100, 30 + 10 + 40 + 10 + 10);
    };

return $tests;
