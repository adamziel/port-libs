<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\TableGeometry;

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
$readFirstTable = static function (string $markdown): AstNode {
    $document = (new MarkdownReader())->read($markdown);
    foreach ($document->children as $node) {
        if ($node->type === 'table') {
            return $node;
        }
    }

    return new AstNode('missing');
};

$readerTableFixtures = [
    'pipe' => implode("\n", [
        '| Metric | Value |',
        '|:-------|------:|',
        '| Probe | 42 |',
    ]),
    'simple' => implode("\n", [
        'Metric  Value',
        '------  -----',
        'Probe   42',
    ]),
    'grid' => implode("\n", [
        '+--------+-------+',
        '| Metric | Value |',
        '+========+=======+',
        '| Probe  | 42    |',
        '+--------+-------+',
    ]),
];
$captionedReaderTable = static function (string $tableMarkdown, string $position, string $marker, string $caption): string {
    $captionLine = $marker . ' ' . $caption;

    return $position === 'before-table'
        ? $captionLine . "\n\n" . $tableMarkdown
        : $tableMarkdown . "\n\n" . $captionLine;
};
$inlineTypes = static fn (array $nodes): array => array_values(array_map(
    static fn (AstNode $node): string => $node->type,
    $nodes
));

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

$readerShortCaptionCases = [
    'plain text' => [
        'source' => 'Short label {n}',
        'plain' => 'Short label {n}',
        'types' => ['text'],
    ],
    'strong inline' => [
        'source' => 'Short **label** {n}',
        'plain' => 'Short label {n}',
        'types' => ['text', 'strong', 'text'],
    ],
    'link inline' => [
        'source' => '[Short link {n}](/short-{n})',
        'plain' => 'Short link {n}',
        'types' => ['link'],
    ],
];

$shortCaptionCaseNumber = 1;
foreach ($readerTableFixtures as $tableName => $tableMarkdown) {
    foreach (['before-table', 'after-table'] as $position) {
        foreach (['Table:', 'Caption:', ':'] as $marker) {
            foreach ($readerShortCaptionCases as $label => $case) {
                $caseNumber = $shortCaptionCaseNumber++;
                $source = str_replace('{n}', (string) $caseNumber, $case['source']);
                $plain = str_replace('{n}', (string) $caseNumber, $case['plain']);

                $tests["maps upstream markdown reader short-only table caption {$tableName} {$position} {$marker} {$label}"] =
                    static function (TestRunner $t) use ($captionedReaderTable, $readFirstTable, $inlineTypes, $tableMarkdown, $position, $marker, $source, $plain, $case): void {
                        $markdown = $captionedReaderTable($tableMarkdown, $position, $marker, '[' . $source . ']');
                        $table = $readFirstTable($markdown);
                        $sourceRecord = $table->attr('captionSource');
                        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
                        $rewritten = (new MarkdownWriter())->write(new AstNode('document', [], [$table]));

                        $t->same('table', $table->type, $markdown);
                        $t->same('', $table->attr('caption'), $markdown);
                        $t->same(null, $table->attr('captionInlines', null), $markdown);
                        $t->same($plain, $table->attr('shortCaption'), $markdown);
                        $t->same($case['types'], $inlineTypes($table->attr('shortCaptionInlines', [])), $markdown);
                        $t->same('markdown-table-caption', $sourceRecord['element'] ?? null, $markdown);
                        $t->same($position, $sourceRecord['position'] ?? null, $markdown);
                        $t->same($marker, $sourceRecord['marker'] ?? null, $markdown);
                        $t->same($position === 'before-table' ? 'top' : 'bottom', $sourceRecord['captionSide'] ?? null, $markdown);
                        $t->same(false, $packet['summary']['hasCaption'] ?? null, $markdown);
                        $t->same(true, $packet['summary']['hasShortCaption'] ?? null, $markdown);
                        $t->same($plain, $packet['captions']['short']['text'] ?? null, $markdown);
                        $t->contains(': [' . $source . ']', $rewritten);
                    };
            }
        }
    }
}

$readerCaptionAttributeCases = [
    'same line attributes' => 'Attributed **caption** {n} {#tbl-{n} .review data-source="batch-{n}"}',
    'continuation line attributes' => "Attributed **caption** {n}\n  {#tbl-{n} .review data-source=\"batch-{n}\"}",
];

$attributeCaseNumber = 1;
foreach ($readerTableFixtures as $tableName => $tableMarkdown) {
    foreach (['before-table' => 'Table:', 'after-table' => ':'] as $position => $marker) {
        foreach ($readerCaptionAttributeCases as $label => $sourceTemplate) {
            $caseNumber = $attributeCaseNumber++;
            $source = str_replace('{n}', (string) $caseNumber, $sourceTemplate);
            $id = 'tbl-' . $caseNumber;

            $tests["maps upstream markdown reader table caption trailing attributes {$tableName} {$position} {$label}"] =
                static function (TestRunner $t) use ($captionedReaderTable, $readFirstTable, $inlineTypes, $tableMarkdown, $position, $marker, $source, $caseNumber, $id): void {
                    $markdown = $captionedReaderTable($tableMarkdown, $position, $marker, $source);
                    $table = $readFirstTable($markdown);
                    $rewritten = (new MarkdownWriter())->write(new AstNode('document', [], [$table]));

                    $t->same('table', $table->type, $markdown);
                    $t->same('Attributed **caption** ' . $caseNumber, $table->attr('caption'), $markdown);
                    $t->same(['text', 'strong', 'text'], $inlineTypes($table->attr('captionInlines', [])), $markdown);
                    $t->same($id, $table->attr('id'), $markdown);
                    $t->same(['review'], $table->attr('classes'), $markdown);
                    $t->same('batch-' . $caseNumber, $table->attr('attributes')['data-source'] ?? null, $markdown);
                    $t->contains('{#' . $id . ' .review data-source="batch-' . $caseNumber . '"}', $rewritten);
                };
        }
    }
}

$tests['records markdown writer table caption span completion mapped-case count'] =
    static function (TestRunner $t): void {
        $t->same(116, 30 + 10 + 10 + 54 + 12);
    };

return $tests;
