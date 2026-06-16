<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);

$writeParagraph = static fn (array $children, array $options = []): string => (new MarkdownWriter($options))->write(
    $document([$paragraph($children)])
);

$assertSingleParagraphText = static function (TestRunner $t, string $markdown, string $expected): void {
    $roundTrip = (new MarkdownReader())->read($markdown);

    $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
    $paragraph = $roundTrip->children[0] ?? null;
    $t->true($paragraph instanceof AstNode, 'Expected one paragraph after round-trip');
    if ($paragraph instanceof AstNode) {
        $t->same($expected, $paragraph->attr('text'));
    }
};

$assertSoftbreakShape = static function (
    TestRunner $t,
    string $markdown,
    string $expectedHead,
    string $expectedTail
): void {
    $roundTrip = (new MarkdownReader())->read($markdown);
    $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
    $paragraph = $roundTrip->children[0] ?? null;
    $t->true($paragraph instanceof AstNode, 'Expected one paragraph after softbreak round-trip');
    if (!$paragraph instanceof AstNode) {
        return;
    }

    $t->same(['text', 'softbreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
    $head = $paragraph->children[0] ?? null;
    $tail = $paragraph->children[2] ?? null;
    $t->true($head instanceof AstNode && $tail instanceof AstNode, 'Expected text on both sides of softbreak');
    if ($head instanceof AstNode && $tail instanceof AstNode) {
        $t->same($expectedHead, $head->attr('text'));
        $t->same($expectedTail, $tail->attr('text'));
    }
};

$tests = [];
$formats = ['markdown', 'commonmark', 'gfm'];
$mappedCaseCount = 0;

$rawHtmlTags = [
    'div',
    'section',
    'article',
    'table',
    'ul',
    'ol',
    'pre',
    'script',
    'style',
    'blockquote',
];

foreach ($formats as $format) {
    foreach ($rawHtmlTags as $tag) {
        $mappedCaseCount++;
        $tests["maps upstream {$format} writer paragraph wrap boundary final harvest wrapped raw html {$tag}"] =
            static function (TestRunner $t) use ($assertSingleParagraphText, $format, $space, $tag, $text, $writeParagraph): void {
                $prefix = 'alpha beta gamma delta';
                $markdown = $writeParagraph(
                    [$text($prefix), $space(), $text('<' . $tag . '>x')],
                    ['format' => $format, 'columns' => 22]
                );

                $t->same($prefix . "\n" . '&lt;' . $tag . '\\>x', $markdown);
                $assertSingleParagraphText($t, $markdown, $prefix . ' <' . $tag . '>x');
            };
    }
}

$softbreakCases = [
    'one trailing space plain' => [1, 'tail', 'tail'],
    'two trailing spaces plain' => [2, 'tail', 'tail'],
    'three trailing spaces plain' => [3, 'tail', 'tail'],
    'four trailing spaces plain' => [4, 'tail', 'tail'],
    'two trailing spaces atx marker' => [2, '# not a heading', '\\# not a heading'],
    'two trailing spaces raw html marker' => [2, '<div>x', '&lt;div\\>x'],
    'three trailing spaces definition marker' => [3, ': definition', '\\: definition'],
    'four trailing spaces ordered marker' => [4, '1. ordered', '1\\. ordered'],
];

foreach ($formats as $format) {
    foreach ($softbreakCases as $label => [$spaceCount, $tail, $expectedTail]) {
        $mappedCaseCount++;
        $tests["maps upstream {$format} writer paragraph wrap boundary final harvest softbreak {$label}"] =
            static function (TestRunner $t) use ($assertSoftbreakShape, $expectedTail, $format, $softbreak, $spaceCount, $tail, $text, $writeParagraph): void {
                $head = 'Lead' . str_repeat(' ', $spaceCount);
                $markdown = $writeParagraph(
                    [$text($head), $softbreak(), $text($tail)],
                    ['format' => $format]
                );

                $t->same('Lead' . str_repeat('&#32;', $spaceCount) . "\n" . $expectedTail, $markdown);
                $assertSoftbreakShape($t, $markdown, $head, $tail);
            };
    }
}

$indentCases = [
    'four spaces code text' => ['    code', str_repeat('&#32;', 4) . 'code'],
    'five spaces code text' => ['     code', str_repeat('&#32;', 5) . 'code'],
    'four spaces atx marker' => ['    # heading', str_repeat('&#32;', 4) . '\\# heading'],
    'four spaces raw html marker' => ['    <div>x', str_repeat('&#32;', 4) . '&lt;div\\>x'],
    'three spaces tab code text' => ["   \tcode", str_repeat('&#32;', 3) . '&#9;code'],
    'one space tab code text' => [" \tcode", '&#32;&#9;code'],
    'tab code text' => ["\tcode", '&#9;code'],
];

foreach ($formats as $format) {
    foreach ($indentCases as $label => [$source, $expected]) {
        $mappedCaseCount++;
        $tests["maps upstream {$format} writer paragraph wrap boundary final harvest indent {$label}"] =
            static function (TestRunner $t) use ($assertSingleParagraphText, $expected, $format, $source, $text, $writeParagraph): void {
                $markdown = $writeParagraph([$text($source)], ['format' => $format]);

                $t->same($expected, $markdown);
                $assertSingleParagraphText($t, $markdown, $source);
            };
    }
}

$tests['records markdown writer paragraph wrap boundary final harvest mapped case count'] =
    static function (TestRunner $t) use ($mappedCaseCount): void {
        $t->same(75, $mappedCaseCount);
    };

return $tests;
