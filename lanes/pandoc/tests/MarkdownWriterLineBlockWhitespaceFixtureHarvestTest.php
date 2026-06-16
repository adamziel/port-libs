<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$strong = static fn (array $children): AstNode => new AstNode('strong', [], $children);
$span = static fn (array $children): AstNode => new AstNode('span', ['classes' => ['verse']], $children);
$quoted = static fn (array $children): AstNode => new AstNode('quoted', ['kind' => 'double'], $children);
$superscript = static fn (array $children): AstNode => new AstNode('superscript', [], $children);
$subscript = static fn (array $children): AstNode => new AstNode('subscript', [], $children);
$line = static fn (array $children = []): AstNode => new AstNode('line', [], $children);
$lineBlock = static fn (array $lines): AstNode => new AstNode('line_block', [], $lines);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);

$writeLineBlock = static fn (array $lines, array $options = []): string => (new MarkdownWriter($options))->write(
    $document([$lineBlock($lines)])
);

$plainInlineText = static function (array $nodes) use (&$plainInlineText): string {
    $text = '';
    foreach ($nodes as $node) {
        if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
            $text .= (string) $node->attr('text', '');
            continue;
        }

        if ($node->type === 'softbreak' || $node->type === 'linebreak') {
            $text .= "\n";
            continue;
        }

        $text .= $plainInlineText($node->children);
    }

    return $text;
};

$roundTripLineTexts = static function (string $markdown) use ($plainInlineText): array {
    $roundTrip = (new MarkdownReader())->read($markdown);
    $lineBlock = $roundTrip->children[0] ?? null;
    if (!$lineBlock instanceof AstNode || $lineBlock->type !== 'line_block') {
        return [];
    }

    return array_map(
        static fn (AstNode $line): string => $plainInlineText($line->children),
        $lineBlock->children
    );
};

$whitespaceCases = [
    'tab' => ["\t", '&#9;'],
    'escape control' => ["\x1B", '&#x1B;'],
    'unit separator control' => ["\x1F", '&#x1F;'],
    'nonbreaking space' => ["\u{00A0}", '&nbsp;', ' '],
    'ogham space mark' => ["\u{1680}", '&#x1680;'],
    'en quad' => ["\u{2000}", '&#x2000;'],
    'em quad' => ["\u{2001}", '&#x2001;'],
    'en space' => ["\u{2002}", '&#x2002;'],
    'em space' => ["\u{2003}", '&#x2003;'],
    'three per em space' => ["\u{2004}", '&#x2004;'],
    'four per em space' => ["\u{2005}", '&#x2005;'],
    'six per em space' => ["\u{2006}", '&#x2006;'],
    'figure space' => ["\u{2007}", '&#x2007;'],
    'punctuation space' => ["\u{2008}", '&#x2008;'],
    'thin space' => ["\u{2009}", '&#x2009;'],
    'hair space' => ["\u{200A}", '&#x200A;'],
    'narrow nonbreaking space' => ["\u{202F}", '&#x202F;'],
    'medium mathematical space' => ["\u{205F}", '&#x205F;'],
    'ideographic space' => ["\u{3000}", '&#x3000;'],
];

$tests = [];
$mappedCaseCount = 0;
$formats = ['markdown', 'commonmark', 'gfm'];
$formatIndex = 0;

foreach ($whitespaceCases as $label => $case) {
    [$source, $entity] = $case;
    $leading = $case[2] ?? $entity;
    $format = $formats[$formatIndex % count($formats)];
    $formatIndex++;
    $options = ['format' => $format === 'markdown' ? 'markdown' : $format . '+line_blocks'];
    $caseLabel = str_replace(' ', '-', $label);

    $mappedCaseCount++;
    $tests["maps upstream {$format} writer line block whitespace fixture inline {$caseLabel}"] =
        static function (TestRunner $t) use ($entity, $line, $options, $roundTripLineTexts, $source, $text, $writeLineBlock): void {
            $markdown = $writeLineBlock([$line([$text('A' . $source . 'B')])], $options);

            $t->same('| A' . $entity . 'B', $markdown);
            $t->same(['A' . $source . 'B'], $roundTripLineTexts($markdown));
        };

    $mappedCaseCount++;
    $tests["maps upstream {$format} writer line block whitespace fixture leading-marker {$caseLabel}"] =
        static function (TestRunner $t) use ($leading, $line, $options, $roundTripLineTexts, $source, $text, $writeLineBlock): void {
            $markdown = $writeLineBlock([$line([$text($source . '- not a list item')])], $options);

            $t->same('| ' . $leading . '- not a list item', $markdown);
            $t->same([$source . '- not a list item'], $roundTripLineTexts($markdown));
        };
}

$wrapperCases = [
    'emphasis nonbreaking space' => [$emph([$text('A' . "\u{00A0}" . 'B')]), '| *A&nbsp;B*', 'A' . "\u{00A0}" . 'B'],
    'strong tab entity' => [$strong([$text("A\tB")]), '| **A&#9;B**', "A\tB"],
    'span figure space' => [$span([$text('A' . "\u{2007}" . 'B')]), '| [A&#x2007;B]{.verse}', 'A' . "\u{2007}" . 'B'],
    'quoted narrow nonbreaking space' => [$quoted([$text('A' . "\u{202F}" . 'B')]), '| ' . "\u{201C}" . 'A&#x202F;B' . "\u{201D}", "\u{201C}" . 'A' . "\u{202F}" . 'B' . "\u{201D}"],
    'superscript nonbreaking space' => [$superscript([$text('A' . "\u{00A0}" . 'B')]), '| ^A&nbsp;B^', 'A' . "\u{00A0}" . 'B'],
    'subscript tab entity' => [$subscript([$text("A\tB")]), '| ~A&#9;B~', "A\tB"],
];

foreach ($wrapperCases as $label => [$inline, $expected, $roundTripText]) {
    $mappedCaseCount++;
    $tests['maps upstream markdown writer line block whitespace fixture wrapper ' . $label] =
        static function (TestRunner $t) use ($expected, $inline, $line, $roundTripLineTexts, $roundTripText, $writeLineBlock): void {
            $markdown = $writeLineBlock([$line([$inline])]);

            $t->same($expected, $markdown);
            $t->same([$roundTripText], $roundTripLineTexts($markdown));
        };
}

$indentCases = [
    'one line indent' => [[1, 'alpha'], "|  alpha", ["\u{00A0}" . 'alpha']],
    'two line indent' => [[2, 'beta'], "|   beta", [str_repeat("\u{00A0}", 2) . 'beta']],
    'three line indent' => [[3, 'gamma'], "|    gamma", [str_repeat("\u{00A0}", 3) . 'gamma']],
    'indented marker text' => [[1, '- marker'], "|  - marker", ["\u{00A0}" . '- marker']],
    'mixed indented and inline entity' => [[2, 'A' . "\u{00A0}" . 'B'], "|   A&nbsp;B", [str_repeat("\u{00A0}", 2) . 'A' . "\u{00A0}" . 'B']],
    'multi line indent fixture' => [
        [[1, 'first'], [0, 'A' . "\u{2007}" . 'B'], [2, 'last']],
        "|  first\n| A&#x2007;B\n|   last",
        ["\u{00A0}" . 'first', 'A' . "\u{2007}" . 'B', str_repeat("\u{00A0}", 2) . 'last'],
    ],
];

foreach ($indentCases as $label => [$input, $expected, $roundTripTexts]) {
    $mappedCaseCount++;
    $tests['maps upstream markdown writer line block whitespace fixture indentation ' . $label] =
        static function (TestRunner $t) use ($expected, $input, $line, $roundTripLineTexts, $roundTripTexts, $text, $writeLineBlock): void {
            $lineInputs = is_int($input[0] ?? null) ? [$input] : $input;
            $lines = array_map(
                static fn (array $entry): AstNode => $line([$text(str_repeat("\u{00A0}", $entry[0]) . $entry[1])]),
                $lineInputs
            );
            $markdown = $writeLineBlock($lines);

            $t->same($expected, $markdown);
            $t->same($roundTripTexts, $roundTripLineTexts($markdown));
        };
}

$tests['records markdown writer line block whitespace fixture harvest mapped-case count'] =
    static function (TestRunner $t) use ($mappedCaseCount): void {
        $t->same(50, $mappedCaseCount);
    };

return $tests;
