<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$writeDocument = static fn (AstNode $node): string => (new MarkdownWriter())->write($document([$node]));
$writeInlineDocument = static fn (AstNode $inline): string => (new MarkdownWriter())->write($document([$paragraph([$inline])]));

$cell = static function (array $children, array $attrs = []): AstNode {
    return new AstNode('table_cell', $attrs, $children);
};

$textCell = static function (string $value, array $attrs = []) use ($text): AstNode {
    return new AstNode('table_cell', array_merge(['text' => $value], $attrs), [$text($value)]);
};

$row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);

$twoColumnTable = static function (AstNode $valueCell, array $attrs = []) use ($row, $textCell): AstNode {
    return new AstNode('table', array_merge(['alignments' => ['left', 'right']], $attrs), [
        new AstNode('table_head', [], [
            $row([$textCell('Metric'), $textCell('Value')]),
        ]),
        new AstNode('table_body', [], [
            $row([$textCell('Probe'), $valueCell]),
        ]),
    ]);
};

$oneColumnTable = static function (string $alignment, ?float $width = null) use ($row, $textCell): AstNode {
    $attrs = ['alignments' => [$alignment]];
    if ($width !== null) {
        $attrs['widths'] = [$width];
    }

    return new AstNode('table', $attrs, [
        new AstNode('table_head', [], [
            $row([$textCell('H')]),
        ]),
        new AstNode('table_body', [], [
            $row([$textCell('x')]),
        ]),
    ]);
};

$delimiterFor = static function (string $alignment, ?float $width = null): string {
    $dashCount = $width === null ? 3 : max(3, (int) ceil($width * 40));
    $delimiter = match ($alignment) {
        'left' => ':' . str_repeat('-', $dashCount - 1),
        'right' => str_repeat('-', $dashCount - 1) . ':',
        'center' => ':' . str_repeat('-', max(1, $dashCount - 2)) . ':',
        default => str_repeat('-', $dashCount),
    };

    return '|' . $delimiter . '|';
};

$tests = [];

foreach (['left', 'right', 'center', 'default'] as $alignment) {
    foreach ([null, 0.1, 0.25] as $width) {
        $label = $width === null ? 'minimum width' : 'relative width ' . str_replace('.', '_', (string) $width);
        $tests["maps upstream markdown writer pipe table {$alignment} alignment {$label}"] =
            static function (TestRunner $t) use ($alignment, $width, $oneColumnTable, $writeDocument, $delimiterFor): void {
                $markdown = $writeDocument($oneColumnTable($alignment, $width));

                $t->contains($delimiterFor($alignment, $width), $markdown);
            };
    }
}

$captionCases = [
    'plain caption after pipe table' => [
        'attrs' => ['caption' => 'Plain caption'],
        'expected' => ': Plain caption',
    ],
    'caption pipe escaping' => [
        'attrs' => ['caption' => 'Caption | pipe'],
        'expected' => ': Caption \\| pipe',
    ],
    'caption inline emphasis' => [
        'attrs' => ['captionInlines' => [$text('Inline '), new AstNode('strong', [], [$text('caption')]), $text(' text')]],
        'expected' => ': Inline **caption** text',
    ],
    'plain short caption prefix' => [
        'attrs' => ['shortCaption' => 'Short', 'caption' => 'Long caption'],
        'expected' => ': [Short] Long caption',
    ],
    'formatted short caption prefix' => [
        'attrs' => [
            'shortCaptionInlines' => [$text('Short '), new AstNode('strong', [], [$text('label')])],
            'caption' => 'Long caption',
        ],
        'expected' => ': [Short **label**] Long caption',
    ],
    'paragraph caption blocks' => [
        'attrs' => ['captionBlocks' => [$paragraph([$text('Block '), new AstNode('emph', [], [$text('caption')]), $text(' text')])]],
        'expected' => ': Block *caption* text',
    ],
    'multi block caption flattening' => [
        'attrs' => ['captionBlocks' => [$paragraph([$text('First caption')]), $paragraph([$text('Second caption')])]],
        'expected' => ': First caption<br />Second caption',
    ],
    'short caption block prefix' => [
        'attrs' => [
            'shortCaptionBlocks' => [$paragraph([$text('Short '), new AstNode('emph', [], [$text('block')])])],
            'caption' => 'Long caption',
        ],
        'expected' => ': [Short *block*] Long caption',
    ],
    'table id class data attributes' => [
        'attrs' => ['caption' => 'Caption', 'id' => 'table-id', 'classes' => ['audit'], 'attributes' => ['data-source' => 'batch-1']],
        'expected' => ': Caption {#table-id .audit data-source="batch-1"}',
    ],
    'table role and aria attributes' => [
        'attrs' => ['caption' => 'Caption', 'attributes' => ['role' => 'presentation', 'aria-label' => 'Review table']],
        'expected' => ': Caption {role="presentation" aria-label="Review table"}',
    ],
    'table language title attributes' => [
        'attrs' => ['caption' => 'Caption', 'attributes' => ['lang' => 'en', 'title' => 'Review table']],
        'expected' => ': Caption {lang="en" title="Review table"}',
    ],
    'caption fallback after empty short caption' => [
        'attrs' => ['shortCaption' => '', 'caption' => 'Fallback caption'],
        'expected' => ': Fallback caption',
    ],
    'caption omitted when no caption or attributes' => [
        'attrs' => [],
        'expected' => null,
    ],
];

foreach ($captionCases as $label => $case) {
    $tests["maps upstream markdown writer table {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $textCell, $writeDocument): void {
            $markdown = $writeDocument($twoColumnTable($textCell('Ready'), $case['attrs']));

            if ($case['expected'] === null) {
                $t->true(!str_contains($markdown, "\n\n:"), 'Table without caption or attributes should not emit a caption block');
                return;
            }

            $t->contains($case['expected'], $markdown);
        };
}

$tests['maps upstream markdown writer body only table with synthetic header row'] =
    static function (TestRunner $t) use ($row, $textCell, $writeDocument): void {
        $table = new AstNode('table', ['alignments' => ['default']], [
            new AstNode('table_body', [], [
                $row([$textCell('Body')]),
            ]),
        ]);
        $markdown = $writeDocument($table);

        $t->contains('|----|', $markdown);
        $t->contains('| Body |', $markdown);
    };

$tests['maps upstream markdown writer body head rows before delimiter'] =
    static function (TestRunner $t) use ($row, $textCell, $writeDocument): void {
        $headRow = $row([$textCell('Head')]);
        $table = new AstNode('table', ['alignments' => ['left']], [
            new AstNode('table_body', ['headRows' => [$headRow]], [
                $row([$textCell('Body')]),
            ]),
        ]);
        $markdown = $writeDocument($table);

        $t->true(strpos($markdown, '| Head |') < strpos($markdown, '|:---|'), 'Body-local head row should render before delimiter');
        $t->contains('| Body |', $markdown);
    };

$tests['maps upstream markdown writer table foot rows into body output'] =
    static function (TestRunner $t) use ($row, $textCell, $twoColumnTable, $writeDocument): void {
        $sourceTable = $twoColumnTable($textCell('Ready'));
        $table = new AstNode('table', $sourceTable->attrs, [
            ...$sourceTable->children,
            new AstNode('table_foot', [], [
            $row([$textCell('Total'), $textCell('Ready')]),
            ]),
        ]);
        $markdown = $writeDocument($table);

        $t->true(strpos($markdown, '| Probe') < strpos($markdown, '| Total'), 'Foot row should be flattened after body rows');
        $t->contains('| Total', $markdown);
        $t->contains('| Ready |', $markdown);
    };

$tests['maps upstream markdown writer multiple body groups into one pipe table'] =
    static function (TestRunner $t) use ($row, $textCell, $writeDocument): void {
        $table = new AstNode('table', ['alignments' => ['left', 'right']], [
            new AstNode('table_head', [], [$row([$textCell('Metric'), $textCell('Value')])]),
            new AstNode('table_body', [], [$row([$textCell('Posts'), $textCell('42')])]),
            new AstNode('table_body', [], [$row([$textCell('Media'), $textCell('7')])]),
        ]);
        $markdown = $writeDocument($table);

        $t->contains('| Posts', $markdown);
        $t->contains('| Media', $markdown);
    };

$tests['maps upstream markdown writer empty table omission'] =
    static function (TestRunner $t) use ($writeDocument): void {
        $markdown = $writeDocument(new AstNode('table', ['caption' => 'Empty'], [new AstNode('table_body')]));

        $t->same('', trim($markdown));
    };

$spanCases = [
    'generic bracketed span attributes' => [
        'inline' => new AstNode('span', ['id' => 'span-id', 'classes' => ['review'], 'attributes' => ['data-source' => 'batch-2']], [$text('label')]),
        'expected' => '[label]{#span-id .review data-source="batch-2"}',
    ],
    'nested strong bracketed span' => [
        'inline' => new AstNode('span', ['classes' => ['review']], [$text('source '), new AstNode('strong', [], [$text('label')])]),
        'expected' => '[source **label**]{.review}',
    ],
    'mark shortcut span' => [
        'inline' => new AstNode('span', ['classes' => ['mark']], [$text('marked')]),
        'expected' => '==marked==',
    ],
    'attributed mark bracketed span' => [
        'inline' => new AstNode('span', ['id' => 'marked', 'classes' => ['mark']], [$text('marked')]),
        'expected' => '[marked]{#marked .mark}',
    ],
    'small caps semantic span' => [
        'inline' => new AstNode('small_caps', [], [$text('Small caps')]),
        'expected' => '[Small caps]{.smallcaps}',
    ],
    'underline semantic span with attribute' => [
        'inline' => new AstNode('underline', ['attributes' => ['data-source' => 'batch-3']], [$text('insert')]),
        'expected' => '[insert]{.underline data-source="batch-3"}',
    ],
    'strikeout attributed semantic span' => [
        'inline' => new AstNode('strikeout', ['attributes' => ['data-source' => 'batch-4']], [$text('gone')]),
        'expected' => '[gone]{.strikeout data-source="batch-4"}',
    ],
    'superscript attributed semantic span' => [
        'inline' => new AstNode('superscript', ['id' => 'pow'], [$text('2')]),
        'expected' => '[2]{#pow .superscript}',
    ],
    'subscript attributed semantic span' => [
        'inline' => new AstNode('subscript', ['classes' => ['chem']], [$text('n')]),
        'expected' => '[n]{.subscript .chem}',
    ],
    'abbreviation span definition' => [
        'inline' => new AstNode('span', ['classes' => ['abbr'], 'attributes' => ['title' => 'Hypertext Markup Language']], [$text('HTML')]),
        'expected' => "HTML\n\n*[HTML]: Hypertext Markup Language",
    ],
    'xml language span attribute' => [
        'inline' => new AstNode('span', ['attributes' => ['xml:lang' => 'es']], [$text('texto')]),
        'expected' => '[texto]{xml:lang="es"}',
    ],
    'role and aria span attributes' => [
        'inline' => new AstNode('span', ['attributes' => ['role' => 'term', 'aria-label' => 'Term']], [$text('term')]),
        'expected' => '[term]{role="term" aria-label="Term"}',
    ],
    'quoted span attribute escaping' => [
        'inline' => new AstNode('span', ['attributes' => ['title' => 'a "quoted" value']], [$text('quote')]),
        'expected' => '[quote]{title="a \\"quoted\\" value"}',
    ],
    'bracket text escaping in bracketed span' => [
        'inline' => new AstNode('span', ['classes' => ['review']], [$text('A [B]')]),
        'expected' => '[A \\[B\\]]{.review}',
    ],
    'raw markdown inside bracketed span' => [
        'inline' => new AstNode('span', ['classes' => ['review']], [new AstNode('raw_markdown', ['text' => '*raw*'])]),
        'expected' => '[*raw*]{.review}',
    ],
    'empty attributed span' => [
        'inline' => new AstNode('span', ['classes' => ['empty']], []),
        'expected' => '[]{.empty}',
    ],
];

foreach ($spanCases as $label => $case) {
    $tests["maps upstream markdown writer {$label}"] =
        static function (TestRunner $t) use ($case, $writeInlineDocument): void {
            $t->same($case['expected'], $writeInlineDocument($case['inline']));
        };
}

$cellCases = [
    'text pipe delimiter escaping' => [
        'cell' => static fn () => $textCell('A | B'),
        'expected' => 'A \\| B',
    ],
    'code pipe delimiter escaping' => [
        'cell' => static fn () => $cell([new AstNode('code', ['text' => 'a|b'])]),
        'expected' => '`a\\|b`',
    ],
    'link label and target pipe escaping' => [
        'cell' => static fn () => $cell([new AstNode('link', ['url' => 'https://e.test/a|b'], [$text('link | label')])]),
        'expected' => '[link \\| label](https://e.test/a\\|b)',
    ],
    'image label and target pipe escaping' => [
        'cell' => static fn () => $cell([new AstNode('image', ['url' => 'media/a|b.png', 'alt' => 'alt | text'])]),
        'expected' => '![alt \\| text](media/a\\|b.png)',
    ],
    'bracketed span pipe escaping' => [
        'cell' => static fn () => $cell([new AstNode('span', ['classes' => ['review']], [$text('span | label')])]),
        'expected' => '[span \\| label]{.review}',
    ],
    'raw markdown pipe escaping' => [
        'cell' => static fn () => $cell([new AstNode('raw_markdown', ['text' => 'raw|pipe'])]),
        'expected' => 'raw\\|pipe',
    ],
    'raw html inline pipe escaping' => [
        'cell' => static fn () => $cell([new AstNode('raw_html_inline', ['html' => '<span>a|b</span>'])]),
        'expected' => '<span>a\\|b</span>',
    ],
    'softbreak conversion' => [
        'cell' => static fn () => $cell([$text('a'), new AstNode('softbreak'), $text('b')]),
        'expected' => 'a<br />b',
    ],
    'linebreak conversion' => [
        'cell' => static fn () => $cell([$text('a'), new AstNode('linebreak'), $text('b')]),
        'expected' => 'a<br />b',
    ],
    'block cell flattening' => [
        'cell' => static fn () => $cell([
            $paragraph([$text('Intro')]),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [$paragraph([$text('One')])]),
                new AstNode('list_item', [], [$paragraph([$text('Two')])]),
            ]),
        ]),
        'expected' => 'Intro<br /><br />- One<br />- Two',
    ],
    'math pipe escaping' => [
        'cell' => static fn () => $cell([new AstNode('math', ['text' => 'a|b'])]),
        'expected' => '$a\\|b$',
    ],
    'citation pipe suffix escaping' => [
        'cell' => static fn () => $cell([new AstNode('citation', ['id' => 'doe2026', 'suffix' => 'p. 1|2'])]),
        'expected' => '[@doe2026, p. 1\\|2]',
    ],
    'definition marker escaping in first cell text' => [
        'cell' => static fn () => $textCell(': term'),
        'expected' => '\\: term',
    ],
    'table cell avoids unescaped pipe regression' => [
        'cell' => static fn () => $cell([new AstNode('link', ['url' => 'https://e.test/a|b'], [$text('A | B')])]),
        'expected' => '[A \\| B](https://e.test/a\\|b)',
        'forbid' => '[A | B](https://e.test/a|b)',
    ],
];

foreach ($cellCases as $label => $case) {
    $tests["maps upstream markdown writer table cell {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $writeDocument): void {
            $markdown = $writeDocument($twoColumnTable($case['cell']()));

            $t->contains($case['expected'], $markdown);
            if (isset($case['forbid'])) {
                $t->true(!str_contains($markdown, $case['forbid']), 'Table cell should not leave raw pipe delimiters in inline output');
            }
        };
}

$bodyHeadCountCases = [
    'single body head row before delimiter' => static function (TestRunner $t) use ($row, $textCell, $writeDocument): void {
        $table = new AstNode('table', ['alignments' => ['left', 'right']], [
            new AstNode('table_body', ['headRowCount' => 1], [
                $row([$textCell('Queue'), $textCell('Count')]),
                $row([$textCell('Posts'), $textCell('42')]),
            ]),
        ]);
        $markdown = $writeDocument($table);

        $t->true(strpos($markdown, '| Queue') < strpos($markdown, '|:----'), 'Body headRowCount header should render before delimiter');
        $t->true(strpos($markdown, '| Posts') > strpos($markdown, '|:----'), 'Body row should render after delimiter');
    },
    'two body head rows before delimiter' => static function (TestRunner $t) use ($row, $textCell, $writeDocument): void {
        $table = new AstNode('table', ['alignments' => ['left', 'right']], [
            new AstNode('table_body', ['headRowCount' => 2], [
                $row([$textCell('Queue'), $textCell('Count')]),
                $row([$textCell('Scope'), $textCell('Items')]),
                $row([$textCell('Posts'), $textCell('42')]),
            ]),
        ]);
        $markdown = $writeDocument($table);

        $t->true(strpos($markdown, '| Queue') < strpos($markdown, '|:----'), 'First body-local header should precede delimiter');
        $t->true(strpos($markdown, '| Scope') < strpos($markdown, '|:----'), 'Second body-local header should precede delimiter');
        $t->true(strpos($markdown, '| Posts') > strpos($markdown, '|:----'), 'Body row should follow delimiter');
    },
    'head row count clamps to body rows' => static function (TestRunner $t) use ($row, $textCell, $writeDocument): void {
        $table = new AstNode('table', ['alignments' => ['left']], [
            new AstNode('table_body', ['headRowCount' => 4], [
                $row([$textCell('Only head')]),
            ]),
        ]);
        $markdown = $writeDocument($table);

        $t->same(1, substr_count($markdown, '| Only head |'));
        $t->true(strpos($markdown, '| Only head |') < strpos($markdown, '|:--------|'), 'Clamped header row should stay before delimiter');
    },
    'zero head row count uses synthetic header' => static function (TestRunner $t) use ($row, $textCell, $writeDocument): void {
        $table = new AstNode('table', ['alignments' => ['center']], [
            new AstNode('table_body', ['headRowCount' => 0], [
                $row([$textCell('Body')]),
            ]),
        ]);
        $markdown = $writeDocument($table);

        $t->contains('|:--:|', $markdown);
        $t->true(strpos($markdown, '| Body |') > strpos($markdown, '|:--:|'), 'Zero headRowCount should leave body row after synthetic delimiter');
    },
    'negative head row count uses synthetic header' => static function (TestRunner $t) use ($row, $textCell, $writeDocument): void {
        $table = new AstNode('table', ['alignments' => ['default']], [
            new AstNode('table_body', ['headRowCount' => -2], [
                $row([$textCell('Body')]),
            ]),
        ]);
        $markdown = $writeDocument($table);

        $t->contains('|----|', $markdown);
        $t->true(strpos($markdown, '| Body |') > strpos($markdown, '|----|'), 'Negative headRowCount should not promote rows');
    },
    'numeric string head row count is honored' => static function (TestRunner $t) use ($row, $textCell, $writeDocument): void {
        $table = new AstNode('table', ['alignments' => ['right']], [
            new AstNode('table_body', ['headRowCount' => '1'], [
                $row([$textCell('Count')]),
                $row([$textCell('42')]),
            ]),
        ]);
        $markdown = $writeDocument($table);

        $t->true(strpos($markdown, '| Count |') < strpos($markdown, '|----:|'), 'Numeric string headRowCount should promote a header row');
        $t->true(strpos($markdown, '42 |') > strpos($markdown, '|----:|'), 'Remaining body row should follow delimiter');
    },
    'explicit head rows are not duplicated when also present in body children' => static function (TestRunner $t) use ($row, $textCell, $writeDocument): void {
        $headRow = $row([$textCell('Queue')]);
        $table = new AstNode('table', ['alignments' => ['left']], [
            new AstNode('table_body', ['headRows' => [$headRow]], [
                $headRow,
                $row([$textCell('Posts')]),
            ]),
        ]);
        $markdown = $writeDocument($table);

        $t->same(1, substr_count($markdown, '| Queue |'));
        $t->true(strpos($markdown, '| Posts |') > strpos($markdown, '|:----|'), 'Body row should remain after delimiter');
    },
    'direct table rows render as body rows' => static function (TestRunner $t) use ($row, $textCell, $writeDocument): void {
        $table = new AstNode('table', ['alignments' => ['left', 'right']], [
            $row([$textCell('Posts'), $textCell('42')]),
            $row([$textCell('Media'), $textCell('7')]),
        ]);
        $markdown = $writeDocument($table);

        $t->contains('| Posts', $markdown);
        $t->contains('| Media', $markdown);
        $t->true(strpos($markdown, '| Posts') > strpos($markdown, '|:---'), 'Direct rows should be downgraded as body rows after a synthetic header');
    },
    'direct table rows append after section rows' => static function (TestRunner $t) use ($row, $textCell, $writeDocument): void {
        $table = new AstNode('table', ['alignments' => ['left']], [
            new AstNode('table_head', [], [$row([$textCell('Queue')])]),
            new AstNode('table_body', [], [$row([$textCell('Posts')])]),
            $row([$textCell('Media')]),
        ]);
        $markdown = $writeDocument($table);

        $t->true(strpos($markdown, '| Posts |') < strpos($markdown, '| Media |'), 'Direct rows should append after explicit body rows');
    },
    'table head plus body head row both precede delimiter' => static function (TestRunner $t) use ($row, $textCell, $writeDocument): void {
        $table = new AstNode('table', ['alignments' => ['left', 'right']], [
            new AstNode('table_head', [], [$row([$textCell('Queue'), $textCell('Count')])]),
            new AstNode('table_body', ['headRowCount' => 1], [
                $row([$textCell('Scope'), $textCell('Items')]),
                $row([$textCell('Posts'), $textCell('42')]),
            ]),
        ]);
        $markdown = $writeDocument($table);

        $t->true(strpos($markdown, '| Queue') < strpos($markdown, '|:----'), 'Table head should precede delimiter');
        $t->true(strpos($markdown, '| Scope') < strpos($markdown, '|:----'), 'Body-local head should also precede delimiter');
    },
];

foreach ($bodyHeadCountCases as $label => $case) {
    $tests["maps upstream markdown writer body headRowCount {$label}"] = $case;
}

$textOnlyCellCases = [
    'line feed normalization' => [
        'text' => "Line one\nLine two",
        'expected' => 'Line one<br />Line two',
    ],
    'carriage return normalization' => [
        'text' => "Line one\rLine two",
        'expected' => 'Line one Line two',
    ],
    'pipe and line feed normalization' => [
        'text' => "A | B\nC | D",
        'expected' => 'A \\| B<br />C \\| D',
    ],
    'atx heading marker escaping' => [
        'text' => '# Heading',
        'expected' => '\\# Heading',
    ],
    'bullet marker escaping' => [
        'text' => '- item',
        'expected' => '\\- item',
    ],
    'ordered marker escaping' => [
        'text' => '1. item',
        'expected' => '1\\. item',
    ],
    'definition marker escaping' => [
        'text' => ': term',
        'expected' => '\\: term',
    ],
    'image opener escaping' => [
        'text' => '![alt]',
        'expected' => '\\![alt\\]',
    ],
    'code delimiter escaping' => [
        'text' => 'Use `code`',
        'expected' => 'Use \\`code\\`',
    ],
    'emphasis delimiter escaping' => [
        'text' => '*strong-ish*',
        'expected' => '\\*strong-ish\\*',
    ],
    'citation opener escaping' => [
        'text' => '@doe2026',
        'expected' => '\\@doe2026',
    ],
    'fenced div opener escaping' => [
        'text' => '::: note',
        'expected' => '\\::: note',
    ],
];

foreach ($textOnlyCellCases as $label => $case) {
    $tests["maps upstream markdown writer text-only table cell {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $textCell, $writeDocument): void {
            $markdown = $writeDocument($twoColumnTable($textCell($case['text'])));

            $t->contains($case['expected'], $markdown);
        };
}

$captionBreakCases = [
    'caption inline hard break' => [
        'attrs' => ['captionInlines' => [$text('Alpha'), new AstNode('linebreak'), $text('Beta')]],
        'expected' => ': Alpha<br />Beta',
    ],
    'caption inline soft break' => [
        'attrs' => ['captionInlines' => [$text('Alpha'), new AstNode('softbreak'), $text('Beta')]],
        'expected' => ': Alpha Beta',
    ],
    'plain caption line feed' => [
        'attrs' => ['caption' => "Alpha\nBeta"],
        'expected' => ': Alpha Beta',
    ],
    'short caption line feed' => [
        'attrs' => ['shortCaption' => "Short\nLabel", 'caption' => 'Long caption'],
        'expected' => ': [Short Label] Long caption',
    ],
    'short caption inline hard break' => [
        'attrs' => ['shortCaptionInlines' => [$text('Short'), new AstNode('linebreak'), $text('Label')], 'caption' => 'Long caption'],
        'expected' => ': [Short<br />Label] Long caption',
    ],
    'short caption inline soft break' => [
        'attrs' => ['shortCaptionInlines' => [$text('Short'), new AstNode('softbreak'), $text('Label')], 'caption' => 'Long caption'],
        'expected' => ': [Short Label] Long caption',
    ],
    'caption block hard break' => [
        'attrs' => ['captionBlocks' => [$paragraph([$text('Alpha'), new AstNode('linebreak'), $text('Beta')])]],
        'expected' => ': Alpha<br />Beta',
    ],
    'caption block soft break' => [
        'attrs' => ['captionBlocks' => [$paragraph([$text('Alpha'), new AstNode('softbreak'), $text('Beta')])]],
        'expected' => ': Alpha Beta',
    ],
    'caption list block flattening' => [
        'attrs' => ['captionBlocks' => [new AstNode('bullet_list', [], [
            new AstNode('list_item', [], [$paragraph([$text('One')])]),
            new AstNode('list_item', [], [$paragraph([$text('Two')])]),
        ])]],
        'expected' => ': - One - Two',
    ],
    'short caption block hard break' => [
        'attrs' => [
            'shortCaptionBlocks' => [$paragraph([$text('Short'), new AstNode('linebreak'), $text('Block')])],
            'caption' => 'Long caption',
        ],
        'expected' => ': [Short<br />Block] Long caption',
    ],
    'caption raw markdown block' => [
        'attrs' => ['captionBlocks' => [new AstNode('raw_markdown', ['text' => '*raw caption*'])]],
        'expected' => ': *raw caption*',
    ],
    'caption bracket escaping' => [
        'attrs' => ['caption' => '[Caption]'],
        'expected' => ': \\[Caption\\]',
    ],
    'short-only caption' => [
        'attrs' => ['shortCaption' => 'Short only'],
        'expected' => ': [Short only]',
    ],
    'caption hard break with table attributes' => [
        'attrs' => [
            'captionInlines' => [$text('Alpha'), new AstNode('linebreak'), $text('Beta')],
            'id' => 'caption-breaks',
            'classes' => ['review'],
        ],
        'expected' => ': Alpha<br />Beta {#caption-breaks .review}',
    ],
];

foreach ($captionBreakCases as $label => $case) {
    $tests["maps upstream markdown writer table {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $textCell, $writeDocument): void {
            $markdown = $writeDocument($twoColumnTable($textCell('Ready'), $case['attrs']));

            $t->contains($case['expected'], $markdown);
        };
}

$spanCompletionCases = [
    'plain span unwraps when no attributes' => [
        'inline' => new AstNode('span', [], [$text('plain span')]),
        'expected' => 'plain span',
    ],
    'mark span containing delimiter falls back to bracketed attributes' => [
        'inline' => new AstNode('span', ['classes' => ['mark']], [$text('a==b')]),
        'expected' => '[a==b]{.mark}',
    ],
    'emoji alias span' => [
        'inline' => new AstNode('span', ['classes' => ['emoji'], 'attributes' => ['data-emoji' => 'sparkles']], [$text("\u{2728}")]),
        'expected' => ':sparkles:',
    ],
    'emoji alias mismatched glyph falls back to bracketed span' => [
        'inline' => new AstNode('span', ['classes' => ['emoji'], 'attributes' => ['data-emoji' => 'sparkles']], [$text('x')]),
        'expected' => '[x]{.emoji data-emoji="sparkles"}',
    ],
    'span containing link' => [
        'inline' => new AstNode('span', ['classes' => ['review']], [
            $text('See '),
            new AstNode('link', ['url' => 'https://example.test/review'], [$text('review')]),
        ]),
        'expected' => '[See [review](https://example.test/review)]{.review}',
    ],
    'span containing image' => [
        'inline' => new AstNode('span', ['classes' => ['media']], [
            new AstNode('image', ['url' => 'media/review.png', 'alt' => 'Review image']),
        ]),
        'expected' => '[![Review image](media/review.png)]{.media}',
    ],
    'span containing raw html inline' => [
        'inline' => new AstNode('span', ['classes' => ['html']], [
            new AstNode('raw_html_inline', ['html' => '<kbd>Esc</kbd>']),
        ]),
        'expected' => '[<kbd>Esc</kbd>]{.html}',
    ],
    'span containing raw inline html format' => [
        'inline' => new AstNode('span', ['classes' => ['html']], [
            new AstNode('raw_inline', ['format' => 'html', 'text' => '<span>raw</span>']),
        ]),
        'expected' => '[<span>raw</span>]{.html}',
    ],
    'span containing raw inline markdown format' => [
        'inline' => new AstNode('span', ['classes' => ['markdown']], [
            new AstNode('raw_inline', ['format' => 'markdown', 'text' => '*raw*']),
        ]),
        'expected' => '[*raw*]{.markdown}',
    ],
    'span containing inline math' => [
        'inline' => new AstNode('span', ['classes' => ['math']], [
            new AstNode('math', ['text' => 'x+1']),
        ]),
        'expected' => '[$x+1$]{.math}',
    ],
    'span containing citation' => [
        'inline' => new AstNode('span', ['classes' => ['cite']], [
            new AstNode('citation', ['id' => 'doe2026', 'suffix' => 'p. 4']),
        ]),
        'expected' => '[[@doe2026, p. 4]]{.cite}',
    ],
    'span containing citation group' => [
        'inline' => new AstNode('span', ['classes' => ['cite']], [
            new AstNode('citation_group', [], [
                new AstNode('citation', ['id' => 'doe2026']),
                new AstNode('citation', ['id' => 'roe2026', 'mode' => 'suppress_author']),
            ]),
        ]),
        'expected' => '[[@doe2026; -@roe2026]]{.cite}',
    ],
    'span containing quoted text' => [
        'inline' => new AstNode('span', ['classes' => ['quote']], [
            new AstNode('quoted', ['kind' => 'single'], [$text('quoted')]),
        ]),
        'expected' => "[\u{2018}quoted\u{2019}]{.quote}",
    ],
    'span containing superscript shortcut' => [
        'inline' => new AstNode('span', ['classes' => ['power']], [
            $text('x'),
            new AstNode('superscript', [], [$text('2')]),
        ]),
        'expected' => '[x^2^]{.power}',
    ],
    'span containing subscript shortcut' => [
        'inline' => new AstNode('span', ['classes' => ['formula']], [
            $text('H'),
            new AstNode('subscript', [], [$text('2')]),
            $text('O'),
        ]),
        'expected' => '[H~2~O]{.formula}',
    ],
    'span attributes preserve dir and language' => [
        'inline' => new AstNode('span', ['attributes' => ['dir' => 'rtl', 'lang' => 'ar']], [$text('label')]),
        'expected' => '[label]{dir="rtl" lang="ar"}',
    ],
];

foreach ($spanCompletionCases as $label => $case) {
    $tests["maps upstream markdown writer {$label}"] =
        static function (TestRunner $t) use ($case, $writeInlineDocument): void {
            $t->same($case['expected'], $writeInlineDocument($case['inline']));
        };
}

$tests['maps upstream markdown writer attributed span containing note definition'] =
    static function (TestRunner $t) use ($text, $paragraph, $writeInlineDocument): void {
        $markdown = $writeInlineDocument(new AstNode('span', ['classes' => ['note']], [
            $text('review'),
            new AstNode('note', [], [$paragraph([$text('note body')])]),
        ]));

        $t->contains('[review[^1]]{.note}', $markdown);
        $t->contains('[^1]: note body', $markdown);
    };

return $tests;
