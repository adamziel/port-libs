<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$cell = static function (array|string $children = '', array $attrs = []) use ($text): AstNode {
    if (is_string($children)) {
        return new AstNode('table_cell', array_merge(['text' => $children], $attrs), [$text($children)]);
    }

    return new AstNode('table_cell', $attrs, $children);
};
$textOnlyCell = static fn (string $value, array $attrs = []): AstNode => new AstNode('table_cell', array_merge(['text' => $value], $attrs));
$row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);
$head = static fn (array $rows): AstNode => new AstNode('table_head', [], $rows);
$body = static fn (array $rows, array $attrs = []): AstNode => new AstNode('table_body', $attrs, $rows);
$foot = static fn (array $rows): AstNode => new AstNode('table_foot', [], $rows);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);

$table = static function (string $format, array $sections, array $attrs = []): AstNode {
    if ($format !== '') {
        $attrs['markdownTableFormat'] = $format;
    }

    return new AstNode('table', array_replace(['alignments' => ['left', 'right']], $attrs), $sections);
};

$twoColumnTable = static function (string $format, array $attrs = [], ?AstNode $valueCell = null) use ($table, $head, $body, $row, $cell): AstNode {
    return $table($format, [
        $head([$row([$cell('Metric'), $cell('Value')])]),
        $body([$row([$cell('Probe'), $valueCell ?? $cell('Ready')])]),
    ], $attrs);
};

$oneColumnTable = static function (string $format, array $attrs = [], string $headText = 'H', string $bodyText = 'x') use ($table, $head, $body, $row, $cell): AstNode {
    return $table($format, [
        $head([$row([$cell($headText)])]),
        $body([$row([$cell($bodyText)])]),
    ], array_replace(['alignments' => ['default']], $attrs));
};

$write = static fn (AstNode $node, array $options = []): string => (new MarkdownWriter($options))->write($document([$node]));
$lines = static fn (string $markdown): array => explode("\n", $markdown);

$assertSimpleTable = static function (TestRunner $t, string $markdown): void {
    $t->true(!str_contains($markdown, '|'), 'Simple table output should not use pipe table separators');
    $t->contains('------', $markdown);
    $t->contains('Metric', $markdown);
    $t->contains('Ready', $markdown);
};

$assertGridTable = static function (TestRunner $t, string $markdown): void {
    $t->contains('+--------+-------+', $markdown);
    $t->contains('| Metric | Value |', $markdown);
    $t->contains('+========+=======+', $markdown);
    $t->contains('| Probe  | Ready |', $markdown);
};

$tests = [];

$simpleAliasCases = [
    'attr simple' => ['format' => 'simple'],
    'attr simple table' => ['format' => 'simple-table'],
    'attr simple tables underscore' => ['format' => 'simple_tables'],
    'attr simple tables uppercase' => ['format' => 'SIMPLE_TABLES'],
    'attr simple table mixed case' => ['format' => 'Simple-Table'],
    'option tableStyle simple' => ['format' => '', 'options' => ['tableStyle' => 'simple']],
    'option markdownTableFormat simple table' => ['format' => '', 'options' => ['markdownTableFormat' => 'simple-table']],
    'attr wins over grid option' => ['format' => 'simple', 'options' => ['tableStyle' => 'grid']],
];

foreach ($simpleAliasCases as $label => $case) {
    $tests["maps upstream markdown writer simple table format {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $write, $assertSimpleTable): void {
            $markdown = $write($twoColumnTable($case['format']), $case['options'] ?? []);

            $assertSimpleTable($t, $markdown);
            $t->true(!str_contains($markdown, '+========+'), 'Simple table output should not use grid separators');
        };
}

$simpleAlignmentCases = [
    'left short body' => ['alignment' => 'left', 'body' => 'x', 'expectedBody' => 'x'],
    'left wide body' => ['alignment' => 'left', 'body' => 'wide', 'expectedBody' => 'wide'],
    'right short body' => ['alignment' => 'right', 'body' => 'x', 'expectedBody' => '   x'],
    'right wide body' => ['alignment' => 'right', 'body' => 'wide', 'expectedBody' => 'wide'],
    'center short body' => ['alignment' => 'center', 'body' => 'x', 'expectedBody' => ' x'],
    'center wide body' => ['alignment' => 'center', 'body' => 'wide', 'expectedBody' => 'wide'],
    'default short body' => ['alignment' => 'default', 'body' => 'x', 'expectedBody' => 'x'],
    'unknown alignment body' => ['alignment' => 'unsupported', 'body' => 'x', 'expectedBody' => 'x'],
];

foreach ($simpleAlignmentCases as $label => $case) {
    $tests["maps upstream markdown writer simple table alignment {$label}"] =
        static function (TestRunner $t) use ($case, $oneColumnTable, $write, $lines): void {
            $markdown = $write($oneColumnTable('simple', ['alignments' => [$case['alignment']]], 'Head', $case['body']));
            $outputLines = $lines($markdown);

            $t->same($case['expectedBody'], $outputLines[2]);
            $t->true(!str_contains($markdown, '|'), 'Simple alignment case should stay in simple-table form');
        };
}

$simpleWidthCases = [
    'relative width 0_10' => ['width' => 0.10, 'dashes' => 4],
    'relative width 0_15' => ['width' => 0.15, 'dashes' => 6],
    'relative width 0_20' => ['width' => 0.20, 'dashes' => 8],
    'relative width 0_25' => ['width' => 0.25, 'dashes' => 10],
    'relative width 0_30' => ['width' => 0.30, 'dashes' => 12],
    'relative width 0_40' => ['width' => 0.40, 'dashes' => 16],
    'relative width 0_50' => ['width' => 0.50, 'dashes' => 20],
    'relative width 0_75' => ['width' => 0.75, 'dashes' => 30],
];

foreach ($simpleWidthCases as $label => $case) {
    $tests["maps upstream markdown writer simple table width {$label}"] =
        static function (TestRunner $t) use ($case, $oneColumnTable, $write, $lines): void {
            $markdown = $write($oneColumnTable('simple', ['widths' => [$case['width']]]));
            $outputLines = $lines($markdown);

            $t->same(str_repeat('-', $case['dashes']), $outputLines[1]);
        };
}

$simpleCaptionCases = [
    'bottom caption text' => ['attrs' => ['caption' => 'Simple caption'], 'contains' => "\n\n: Simple caption"],
    'top caption text' => ['attrs' => ['caption' => 'Top caption', 'captionSide' => 'top'], 'starts' => ': Top caption'],
    'short and long caption' => ['attrs' => ['shortCaption' => 'Short', 'caption' => 'Long caption'], 'contains' => ': [Short] Long caption'],
    'inline strong caption' => ['attrs' => ['captionInlines' => [$text('Strong '), new AstNode('strong', [], [$text('caption')])]], 'contains' => ': Strong **caption**'],
    'source caption text' => ['attrs' => ['captionSource' => ['text' => 'Source caption']], 'contains' => ': Source caption'],
    'caption attributes' => ['attrs' => ['caption' => 'Caption attrs', 'id' => 'tbl', 'classes' => ['audit'], 'attributes' => ['data-kind' => 'simple']], 'contains' => ': Caption attrs {#tbl .audit data-kind="simple"}'],
    'hard break caption' => ['attrs' => ['captionInlines' => [$text('First'), new AstNode('linebreak'), $text('Second')]], 'contains' => ': First<br />Second'],
    'escaped marker caption' => ['attrs' => ['caption' => '- marker caption'], 'contains' => ': \\- marker caption'],
];

foreach ($simpleCaptionCases as $label => $case) {
    $tests["maps upstream markdown writer simple table caption {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $write): void {
            $markdown = $write($twoColumnTable('simple', $case['attrs']));

            if (isset($case['starts'])) {
                $t->true(str_starts_with($markdown, $case['starts']), 'Top caption should precede simple table');
            }
            $t->contains($case['contains'] ?? $case['starts'], $markdown);
            $t->true(!str_contains($markdown, '|'), 'Caption case should stay in simple-table form');
        };
}

$simpleShapeCases = [
    'body only synthetic header' => static function (TestRunner $t) use ($table, $body, $row, $cell, $write): void {
        $markdown = $write($table('simple', [$body([$row([$cell('Body')])])], ['alignments' => ['left']]));
        $t->contains('Body', $markdown);
        $t->contains('----', $markdown);
    },
    'direct rows' => static function (TestRunner $t) use ($table, $row, $cell, $write): void {
        $markdown = $write($table('simple', [$row([$cell('A'), $cell('B')]), $row([$cell('C'), $cell('D')])]));
        $t->contains('A', $markdown);
        $t->contains('C', $markdown);
    },
    'body head rows before body' => static function (TestRunner $t) use ($table, $body, $row, $cell, $write): void {
        $markdown = $write($table('simple', [$body([$row([$cell('Body')])], ['headRows' => [$row([$cell('Head')])]])], ['alignments' => ['left']]));
        $t->true(strpos($markdown, 'Head') < strpos($markdown, 'Body'), 'Body-local head row should render before body rows');
    },
    'foot rows after body' => static function (TestRunner $t) use ($table, $body, $foot, $row, $cell, $write): void {
        $markdown = $write($table('simple', [$body([$row([$cell('Body')])]), $foot([$row([$cell('Total')])])], ['alignments' => ['left']]));
        $t->true(strpos($markdown, 'Body') < strpos($markdown, 'Total'), 'Foot row should render after body rows');
    },
    'multiple body groups' => static function (TestRunner $t) use ($table, $body, $row, $cell, $write): void {
        $markdown = $write($table('simple', [$body([$row([$cell('A')])]), $body([$row([$cell('B')])])], ['alignments' => ['left']]));
        $t->true(strpos($markdown, 'A') < strpos($markdown, 'B'), 'Multiple body groups should preserve order');
    },
    'literal pipe cell remains literal' => static function (TestRunner $t) use ($twoColumnTable, $cell, $write): void {
        $markdown = $write($twoColumnTable('simple', [], $cell('A | B')));
        $t->contains('A | B', $markdown);
        $t->true(!str_contains($markdown, 'A \\| B'), 'Simple table cells should not use pipe-table escaping');
    },
    'cell hard break stays single-line marker' => static function (TestRunner $t) use ($twoColumnTable, $cell, $text, $write): void {
        $markdown = $write($twoColumnTable('simple', [], $cell([$text('Alpha'), new AstNode('linebreak'), $text('Beta')])));
        $t->contains('Alpha<br />Beta', $markdown);
    },
    'option pipe overridden by simple attr' => static function (TestRunner $t) use ($twoColumnTable, $write): void {
        $markdown = $write($twoColumnTable('simple'), ['markdownTableFormat' => 'pipe']);
        $t->true(!str_contains($markdown, '| Metric |'), 'Node simple format should override pipe option');
    },
];

foreach ($simpleShapeCases as $label => $case) {
    $tests["maps upstream markdown writer simple table shape {$label}"] = $case;
}

$gridAliasCases = [
    'attr grid' => ['format' => 'grid'],
    'attr grid table' => ['format' => 'grid-table'],
    'attr grid tables underscore' => ['format' => 'grid_tables'],
    'attr grid tables uppercase' => ['format' => 'GRID_TABLES'],
    'attr grid table mixed case' => ['format' => 'Grid-Table'],
    'option tableStyle grid' => ['format' => '', 'options' => ['tableStyle' => 'grid']],
    'option markdownTableFormat grid table' => ['format' => '', 'options' => ['markdownTableFormat' => 'grid-table']],
    'attr wins over simple option' => ['format' => 'grid', 'options' => ['tableStyle' => 'simple']],
];

foreach ($gridAliasCases as $label => $case) {
    $tests["maps upstream markdown writer grid table format {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $write, $assertGridTable): void {
            $markdown = $write($twoColumnTable($case['format']), $case['options'] ?? []);

            $assertGridTable($t, $markdown);
        };
}

$gridAlignmentCases = [
    'left short body' => ['alignment' => 'left', 'body' => 'x', 'expected' => '| x    |'],
    'left wide body' => ['alignment' => 'left', 'body' => 'wide', 'expected' => '| wide |'],
    'right short body' => ['alignment' => 'right', 'body' => 'x', 'expected' => '|    x |'],
    'right wide body' => ['alignment' => 'right', 'body' => 'wide', 'expected' => '| wide |'],
    'center short body' => ['alignment' => 'center', 'body' => 'x', 'expected' => '|  x   |'],
    'center wide body' => ['alignment' => 'center', 'body' => 'wide', 'expected' => '| wide |'],
    'default short body' => ['alignment' => 'default', 'body' => 'x', 'expected' => '| x    |'],
    'unknown alignment body' => ['alignment' => 'unsupported', 'body' => 'x', 'expected' => '| x    |'],
];

foreach ($gridAlignmentCases as $label => $case) {
    $tests["maps upstream markdown writer grid table alignment {$label}"] =
        static function (TestRunner $t) use ($case, $oneColumnTable, $write): void {
            $markdown = $write($oneColumnTable('grid', ['alignments' => [$case['alignment']]], 'Head', $case['body']));

            $t->contains($case['expected'], $markdown);
            $t->contains('+======+', $markdown);
        };
}

$gridWidthCases = [
    'relative width 0_10' => ['width' => 0.10, 'dashes' => 6],
    'relative width 0_15' => ['width' => 0.15, 'dashes' => 8],
    'relative width 0_20' => ['width' => 0.20, 'dashes' => 10],
    'relative width 0_25' => ['width' => 0.25, 'dashes' => 12],
    'relative width 0_30' => ['width' => 0.30, 'dashes' => 14],
    'relative width 0_40' => ['width' => 0.40, 'dashes' => 18],
    'relative width 0_50' => ['width' => 0.50, 'dashes' => 22],
    'relative width 0_75' => ['width' => 0.75, 'dashes' => 32],
];

foreach ($gridWidthCases as $label => $case) {
    $tests["maps upstream markdown writer grid table width {$label}"] =
        static function (TestRunner $t) use ($case, $oneColumnTable, $write): void {
            $markdown = $write($oneColumnTable('grid', ['widths' => [$case['width']]]));

            $t->contains('+' . str_repeat('-', $case['dashes']) . '+', $markdown);
            $t->contains('+' . str_repeat('=', $case['dashes']) . '+', $markdown);
        };
}

$gridCaptionCases = [
    'bottom caption text' => ['attrs' => ['caption' => 'Grid caption'], 'contains' => "\n\n: Grid caption"],
    'top caption text' => ['attrs' => ['caption' => 'Top grid caption', 'captionSide' => 'top'], 'starts' => ': Top grid caption'],
    'short and long caption' => ['attrs' => ['shortCaption' => 'Short', 'caption' => 'Long caption'], 'contains' => ': [Short] Long caption'],
    'inline strong caption' => ['attrs' => ['captionInlines' => [$text('Strong '), new AstNode('strong', [], [$text('caption')])]], 'contains' => ': Strong **caption**'],
    'source caption text' => ['attrs' => ['captionSource' => ['text' => 'Source caption']], 'contains' => ': Source caption'],
    'caption attributes' => ['attrs' => ['caption' => 'Caption attrs', 'id' => 'grid', 'classes' => ['audit'], 'attributes' => ['data-kind' => 'grid']], 'contains' => ': Caption attrs {#grid .audit data-kind="grid"}'],
    'hard break caption' => ['attrs' => ['captionInlines' => [$text('First'), new AstNode('linebreak'), $text('Second')]], 'contains' => ': First<br />Second'],
    'escaped marker caption' => ['attrs' => ['caption' => '# marker caption'], 'contains' => ': \\# marker caption'],
];

foreach ($gridCaptionCases as $label => $case) {
    $tests["maps upstream markdown writer grid table caption {$label}"] =
        static function (TestRunner $t) use ($case, $twoColumnTable, $write): void {
            $markdown = $write($twoColumnTable('grid', $case['attrs']));

            if (isset($case['starts'])) {
                $t->true(str_starts_with($markdown, $case['starts']), 'Top caption should precede grid table');
            }
            $t->contains($case['contains'] ?? $case['starts'], $markdown);
            $t->contains('+========+=======+', $markdown);
        };
}

$gridShapeCases = [
    'multiline text cell expands row height' => static function (TestRunner $t) use ($twoColumnTable, $textOnlyCell, $write): void {
        $markdown = $write($twoColumnTable('grid', ['alignments' => ['left', 'left']], $textOnlyCell("Alpha\nBeta")));
        $t->contains('| Probe  | Alpha |', $markdown);
        $t->contains('|        | Beta  |', $markdown);
    },
    'inline hard break expands row height' => static function (TestRunner $t) use ($twoColumnTable, $cell, $text, $write): void {
        $markdown = $write($twoColumnTable('grid', ['alignments' => ['left', 'left']], $cell([$text('Alpha'), new AstNode('linebreak'), $text('Beta')])));
        $t->contains('| Probe  | Alpha |', $markdown);
        $t->contains('|        | Beta  |', $markdown);
    },
    'block cell preserves paragraph lines' => static function (TestRunner $t) use ($twoColumnTable, $cell, $paragraph, $text, $write): void {
        $markdown = $write($twoColumnTable('grid', ['alignments' => ['left', 'left']], $cell([$paragraph([$text('First')]), $paragraph([$text('Second')])])));
        $t->contains('| Probe  | First  |', $markdown);
        $t->contains('|        | Second |', $markdown);
    },
    'body only synthetic header' => static function (TestRunner $t) use ($table, $body, $row, $cell, $write): void {
        $markdown = $write($table('grid', [$body([$row([$cell('Body')])])], ['alignments' => ['left']]));
        $t->contains('| Body |', $markdown);
        $t->contains('+======+', $markdown);
    },
    'direct rows' => static function (TestRunner $t) use ($table, $row, $cell, $write): void {
        $markdown = $write($table('grid', [$row([$cell('A'), $cell('B')]), $row([$cell('C'), $cell('D')])]));
        $t->contains('| A', $markdown);
        $t->contains('| C', $markdown);
    },
    'body head rows before body' => static function (TestRunner $t) use ($table, $body, $row, $cell, $write): void {
        $markdown = $write($table('grid', [$body([$row([$cell('Body')])], ['headRows' => [$row([$cell('Head')])]])], ['alignments' => ['left']]));
        $t->true(strpos($markdown, 'Head') < strpos($markdown, 'Body'), 'Body-local head row should render before grid body rows');
    },
    'foot rows after body' => static function (TestRunner $t) use ($table, $body, $foot, $row, $cell, $write): void {
        $markdown = $write($table('grid', [$body([$row([$cell('Body')])]), $foot([$row([$cell('Total')])])], ['alignments' => ['left']]));
        $t->true(strpos($markdown, 'Body') < strpos($markdown, 'Total'), 'Foot row should render after grid body rows');
    },
    'literal pipe cell remains literal' => static function (TestRunner $t) use ($twoColumnTable, $cell, $write): void {
        $markdown = $write($twoColumnTable('grid', ['alignments' => ['left', 'left']], $cell('A | B')));
        $t->contains('| Probe  | A | B |', $markdown);
        $t->true(!str_contains($markdown, 'A \\| B'), 'Grid table cells should not use pipe-table escaping');
    },
];

foreach ($gridShapeCases as $label => $case) {
    $tests["maps upstream markdown writer grid table shape {$label}"] = $case;
}

return $tests;
