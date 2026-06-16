<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$span = static fn (array $children, array $attrs = []): AstNode => new AstNode('span', $attrs, $children);
$code = static fn (string $value, array $attrs = []): AstNode => new AstNode('code', ['text' => $value] + $attrs);
$math = static fn (string $value, array $attrs = []): AstNode => new AstNode('math', ['text' => $value] + $attrs);
$link = static fn (string $url, array $children, array $attrs = []): AstNode => new AstNode('link', ['url' => $url] + $attrs, $children);
$image = static fn (string $url, string $alt, array $attrs = []): AstNode => new AstNode('image', ['url' => $url, 'alt' => $alt] + $attrs);
$rawMarkdown = static fn (string $value): AstNode => new AstNode('raw_markdown', ['text' => $value]);
$rawTex = static fn (string $value): AstNode => new AstNode('raw_tex', ['text' => $value]);
$writeDocument = static fn (AstNode $node, array $options = []): string => (new MarkdownWriter($options))->write($node);

$plainText = null;
$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }

    if ($node->type === 'space') {
        return ' ';
    }

    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return "\n";
    }

    $value = '';
    foreach ($node->children as $child) {
        $value .= $plainText($child);
    }

    return $value;
};

$assertRoundTripPlainText = static function (TestRunner $t, string $markdown, string $expected) use ($plainText): void {
    $roundTrip = (new MarkdownReader(['format' => 'markdown']))->read($markdown);
    $paragraph = $roundTrip->children[0] ?? null;

    $t->true($paragraph instanceof AstNode && $paragraph->type === 'paragraph', 'Expected paragraph after round-trip');
    if (!$paragraph instanceof AstNode) {
        return;
    }

    $t->same($expected, $plainText($paragraph));
};

$payloads = [
    'tab' => ["\t", '&#9;'],
    'escape control' => ["\x1B", '&#x1B;'],
    'unit separator control' => ["\x1F", '&#x1F;'],
    'nonbreaking space' => ["\u{00A0}", '&nbsp;'],
    'figure space' => ["\u{2007}", '&#x2007;'],
    'thin space' => ["\u{2009}", '&#x2009;'],
    'narrow nonbreaking space' => ["\u{202F}", '&#x202F;'],
    'ideographic space' => ["\u{3000}", '&#x3000;'],
];

$slug = static fn (string $label): string => str_replace(' ', '-', $label);
$source = static fn (string $char): string => 'A' . $char . 'B & <tag>';
$htmlExpected = static fn (string $entity): string => 'A' . $entity . 'B &amp; &lt;tag&gt;';
$markdownExpected = static fn (string $entity): string => 'A' . $entity . 'B & \\<tag\\>';

$tests = [];
$mappedCaseCount = 0;

foreach ($payloads as $label => [$char, $entity]) {
    $caseSlug = $slug($label);
    $sourceText = $source($char);
    $html = $htmlExpected($entity);
    $markdown = $markdownExpected($entity);
    $fallbackOptions = ['format' => 'commonmark'];
    $markdownHtmlFallbackOptions = ['format' => 'markdown', 'extensions' => ['-bracketed_spans']];

    $htmlFallbackCases = [
        'span text and attribute' => [
            'document' => $document([$paragraph([
                $span([$text($sourceText)], ['classes' => ['review'], 'attributes' => ['data-note' => $sourceText]]),
            ])]),
            'expected' => '<span class="review" data-note="' . $html . '">' . $html . '</span>',
            'options' => $fallbackOptions,
        ],
        'inline code text and attribute' => [
            'document' => $document([$paragraph([
                $code($sourceText, ['classes' => ['php'], 'attributes' => ['data-note' => $sourceText]]),
            ])]),
            'expected' => '<code class="php" data-note="' . $html . '">' . $html . '</code>',
            'options' => $fallbackOptions,
        ],
        'inline math text and attribute' => [
            'document' => $document([$paragraph([
                $math($sourceText, ['attributes' => ['data-note' => $sourceText]]),
            ])]),
            'expected' => '<span class="math inline" data-note="' . $html . '">' . $html . '</span>',
            'options' => $fallbackOptions,
        ],
        'link text destination and title' => [
            'document' => $document([$paragraph([
                $link('/target?value=' . $sourceText, [$text($sourceText)], ['classes' => ['tracked'], 'title' => $sourceText]),
            ])]),
            'expected' => '<a class="tracked" href="/target?value=' . $html . '" title="' . $html . '">' . $html . '</a>',
            'options' => $fallbackOptions,
        ],
        'image destination alt and title' => [
            'document' => $document([$paragraph([
                $image('media/' . $caseSlug . '.png?value=' . $sourceText, $sourceText, ['classes' => ['asset'], 'title' => $sourceText]),
            ])]),
            'expected' => '<img class="asset" src="media/' . $caseSlug . '.png?value=' . $html . '" alt="' . $html . '" title="' . $html . '" />',
            'options' => $fallbackOptions,
        ],
        'raw markdown inside html fallback span' => [
            'document' => $document([$paragraph([
                $span([$rawMarkdown($sourceText)], ['classes' => ['review']]),
            ])]),
            'expected' => '<span class="review">' . $html . '</span>',
            'options' => $markdownHtmlFallbackOptions,
        ],
        'raw tex inside html fallback span' => [
            'document' => $document([$paragraph([
                $span([$rawTex($sourceText)], ['classes' => ['review']]),
            ])]),
            'expected' => '<span class="review">' . $html . '</span>',
            'options' => $markdownHtmlFallbackOptions,
        ],
        'raw markdown disabled inside html fallback span' => [
            'document' => $document([$paragraph([
                $span([$rawMarkdown($sourceText)], ['classes' => ['review']]),
            ])]),
            'expected' => '<span class="review"></span>',
            'options' => $markdownHtmlFallbackOptions + ['rawMarkdown' => false],
        ],
        'raw tex disabled inside html fallback span' => [
            'document' => $document([$paragraph([
                $span([$rawTex($sourceText)], ['classes' => ['review']]),
            ])]),
            'expected' => '<span class="review"></span>',
            'options' => $markdownHtmlFallbackOptions + ['rawTex' => false],
        ],
    ];

    foreach ($htmlFallbackCases as $context => $case) {
        $mappedCaseCount++;
        $tests['maps upstream markdown writer html entity raw option residual '
            . str_pad((string) $mappedCaseCount, 2, '0', STR_PAD_LEFT)
            . ' ' . $caseSlug . ' ' . $context] =
            static function (TestRunner $t) use ($case, $char, $writeDocument): void {
                $markdown = $writeDocument($case['document'], $case['options']);

                $t->same($case['expected'], $markdown);
                $t->true(!str_contains($markdown, $char), 'HTML fallback must not leak raw residual whitespace/control bytes');
            };
    }

    $mappedCaseCount++;
    $tests['maps upstream markdown writer html entity raw option residual '
        . str_pad((string) $mappedCaseCount, 2, '0', STR_PAD_LEFT)
        . ' ' . $caseSlug . ' markdown span round trip'] =
        static function (TestRunner $t) use ($assertRoundTripPlainText, $document, $markdown, $paragraph, $sourceText, $span, $text, $writeDocument): void {
            $node = $document([$paragraph([
                $span([$text($sourceText)], ['classes' => ['review']]),
            ])]);
            $rendered = $writeDocument($node, ['format' => 'markdown']);

            $t->same('[' . $markdown . ']{.review}', $rendered);
            $assertRoundTripPlainText($t, $rendered, $sourceText);
        };
}

$tests['records markdown writer html entity raw option residual mapped case count'] =
    static function (TestRunner $t) use ($mappedCaseCount): void {
        $t->same(80, $mappedCaseCount);
    };

return $tests;
