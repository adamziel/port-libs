<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$linebreak = static fn (): AstNode => new AstNode('linebreak');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$strong = static fn (array $children): AstNode => new AstNode('strong', [], $children);
$span = static fn (array $children): AstNode => new AstNode('span', ['classes' => ['review']], $children);
$quoted = static fn (array $children): AstNode => new AstNode('quoted', ['kind' => 'double'], $children);
$superscript = static fn (array $children): AstNode => new AstNode('superscript', [], $children);
$subscript = static fn (array $children): AstNode => new AstNode('subscript', [], $children);

$writeParagraph = static fn (array $children, array $options = []): string => (new MarkdownWriter($options))->write(
    $document([$paragraph($children)])
);

$assertParagraphText = static function (TestRunner $t, string $markdown, string $expected): void {
    $roundTrip = (new MarkdownReader())->read($markdown);
    $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
    $paragraph = $roundTrip->children[0] ?? null;
    $t->true($paragraph instanceof AstNode, 'Expected paragraph after round-trip');
    if ($paragraph instanceof AstNode) {
        $t->same($expected, $paragraph->attr('text'));
    }
};

$assertBreakShape = static function (
    TestRunner $t,
    string $markdown,
    string $breakType,
    string $expectedTail
): void {
    $roundTrip = (new MarkdownReader())->read($markdown);
    $paragraph = $roundTrip->children[0] ?? null;
    $t->true($paragraph instanceof AstNode && $paragraph->type === 'paragraph', 'Expected paragraph after round-trip');
    if (!$paragraph instanceof AstNode) {
        return;
    }

    $t->same(['text', $breakType, 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
    $tail = $paragraph->children[2] ?? null;
    $t->true($tail instanceof AstNode, 'Expected trailing text after break');
    if ($tail instanceof AstNode) {
        $t->same($expectedTail, $tail->attr('text'));
    }
};

$whitespaceCases = [
    'tab' => ["\t", '&#9;'],
    'escape control' => ["\x1B", '&#x1B;'],
    'unit separator control' => ["\x1F", '&#x1F;'],
    'nonbreaking space' => ["\u{00A0}", '&nbsp;'],
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

foreach ($whitespaceCases as $label => [$source, $entity]) {
    $format = $formats[$formatIndex % count($formats)];
    $formatIndex++;
    $options = ['format' => $format];
    $caseLabel = str_replace(' ', '-', $label);

    $mappedCaseCount++;
    $tests["maps upstream {$format} writer whitespace entity fifth wave inline {$caseLabel}"] =
        static function (TestRunner $t) use ($assertParagraphText, $entity, $options, $source, $text, $writeParagraph): void {
            $markdown = $writeParagraph([$text('A' . $source . 'B')], $options);

            $t->same('A' . $entity . 'B', $markdown);
            $assertParagraphText($t, $markdown, 'A' . $source . 'B');
        };

    $mappedCaseCount++;
    $tests["maps upstream {$format} writer whitespace entity fifth wave line-start {$caseLabel}"] =
        static function (TestRunner $t) use ($assertParagraphText, $entity, $options, $source, $text, $writeParagraph): void {
            $markdown = $writeParagraph([$text($source . '# not a heading')], $options);

            $t->same($entity . '# not a heading', $markdown);
            $assertParagraphText($t, $markdown, $source . '# not a heading');
        };

    $mappedCaseCount++;
    $tests["maps upstream {$format} writer whitespace entity fifth wave after softbreak {$caseLabel}"] =
        static function (TestRunner $t) use ($assertBreakShape, $entity, $options, $softbreak, $source, $text, $writeParagraph): void {
            $markdown = $writeParagraph([$text('Lead'), $softbreak(), $text($source . '# not a heading')], $options);

            $t->same("Lead\n" . $entity . '# not a heading', $markdown);
            $assertBreakShape($t, $markdown, 'softbreak', $source . '# not a heading');
        };

    $mappedCaseCount++;
    $tests["maps upstream {$format} writer whitespace entity fifth wave after hardbreak {$caseLabel}"] =
        static function (TestRunner $t) use ($assertBreakShape, $entity, $linebreak, $options, $source, $text, $writeParagraph): void {
            $markdown = $writeParagraph([$text('Lead'), $linebreak(), $text($source . '# not a heading')], $options);

            $t->same("Lead\\\n" . $entity . '# not a heading', $markdown);
            $assertBreakShape($t, $markdown, 'linebreak', $source . '# not a heading');
        };
}

$literalEntityCases = [
    'literal amp entity text' => ['AT&amp;T', 'AT&amp;amp;T'],
    'literal nbsp entity text' => ['A&nbsp;B', 'A&amp;nbsp;B'],
    'literal decimal entity text' => ['A&#160;B', 'A&amp;#160;B'],
    'literal hex entity text' => ['A&#xA0;B', 'A&amp;#xA0;B'],
    'literal copyright entity text' => ['A&copy;B', 'A&amp;copy;B'],
    'literal ellipsis entity text' => ['A&hellip;B', 'A&amp;hellip;B'],
    'literal nested amp entity text' => ['A&amp;amp;B', 'A&amp;amp;amp;B'],
    'ordinary ampersand remains compact' => ['AT&T R&D', 'AT&T R&D'],
];

foreach ($literalEntityCases as $label => [$source, $expected]) {
    $mappedCaseCount++;
    $tests['maps upstream markdown writer literal entity policy fifth wave ' . $label] =
        static function (TestRunner $t) use ($expected, $source, $text, $writeParagraph): void {
            $markdown = $writeParagraph([$text($source)]);

            $t->same($expected, $markdown);
        };
}

$wrapperCases = [
    'emphasis nonbreaking space' => [
        [$emph([$text('A' . "\u{00A0}" . 'B')])],
        '*A&nbsp;B*',
    ],
    'strong tab entity' => [
        [$strong([$text("A\tB")])],
        '**A&#9;B**',
    ],
    'span figure space' => [
        [$span([$text('A' . "\u{2007}" . 'B')])],
        '[A&#x2007;B]{.review}',
    ],
    'quoted narrow nonbreaking space' => [
        [$quoted([$text('A' . "\u{202F}" . 'B')])],
        "\u{201C}A&#x202F;B\u{201D}",
    ],
    'superscript nonbreaking space' => [
        [$superscript([$text('A' . "\u{00A0}" . 'B')])],
        '^A&nbsp;B^',
    ],
    'subscript tab entity' => [
        [$subscript([$text("A\tB")])],
        '~A&#9;B~',
    ],
];

foreach ($wrapperCases as $label => [$children, $expected]) {
    $mappedCaseCount++;
    $tests['maps upstream markdown writer inline wrapper whitespace entity fifth wave ' . $label] =
        static function (TestRunner $t) use ($children, $expected, $writeParagraph): void {
            $markdown = $writeParagraph($children);

            $t->same($expected, $markdown);
        };
}

$tests['records markdown writer linebreak whitespace entity fifth wave mapped case count'] =
    static function (TestRunner $t) use ($mappedCaseCount): void {
        $t->same(90, $mappedCaseCount);
    };

return $tests;
