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

return $tests;
