<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$strong = static fn (array $children): AstNode => new AstNode('strong', [], $children);
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);

$describeInlines = static function (array $nodes) use (&$describeInlines): array {
    $described = [];
    foreach ($nodes as $node) {
        if ($node->type === 'text') {
            $described[] = 'text:' . $node->attr('text', '');
            continue;
        }

        if ($node->type === 'emph' || $node->type === 'strong') {
            $described[] = $node->type . '(' . implode('|', $describeInlines($node->children)) . ')';
            continue;
        }

        $described[] = $node->type;
    }

    return $described;
};

$fixtureCases = [
    'prefix text spaced node suffix text' => [
        'children' => [
            $emph([
                $text('f'),
                $strong([$space(), $text('d'), $space()]),
                $text('l'),
            ]),
        ],
        'expected' => '*f **d** l*',
        'shape' => ['emph(text:f |strong(text:d)|text: l)'],
        'text' => 'f d l',
    ],
    'prefix text spaced node outer suffix text' => [
        'children' => [
            $emph([
                $text('f'),
                $strong([$space(), $text('d'), $space()]),
            ]),
            $text('l'),
        ],
        'expected' => '*f **d*** l',
        'shape' => ['emph(text:f |strong(text:d))', 'text: l'],
        'text' => 'f d l',
    ],
    'prefix text spaced node' => [
        'children' => [
            $emph([
                $text('f'),
                $strong([$space(), $text('d'), $space()]),
            ]),
        ],
        'expected' => '*f **d*** ',
        'shape' => ['emph(text:f |strong(text:d))'],
        'text' => 'f d',
    ],
    'spaced node suffix text' => [
        'children' => [
            $emph([
                $strong([$space(), $text('d'), $space()]),
                $text('l'),
            ]),
        ],
        'expected' => ' ***d** l*',
        'shape' => ['emph(strong(text:d)|text: l)'],
        'text' => 'd l',
    ],
    'text payload with outer spaces' => [
        'children' => [
            $emph([
                $text('f'),
                $strong([$text(' d ')]),
                $text('l'),
            ]),
        ],
        'expected' => '*f **d** l*',
        'shape' => ['emph(text:f |strong(text:d)|text: l)'],
        'text' => 'f d l',
    ],
];

$formats = ['markdown', 'commonmark', 'gfm'];
$mappedCaseCount = count($fixtureCases) * count($formats);

$tests = [];

$tests['records markdown writer spaced nested strong fixture mapped case count'] =
    static function (TestRunner $t) use ($mappedCaseCount): void {
        $t->same(15, $mappedCaseCount);
    };

foreach ($formats as $format) {
    foreach ($fixtureCases as $label => $case) {
        $tests["maps upstream {$format} writer emph strong with spaces 10696 fixture {$label}"] =
            static function (TestRunner $t) use ($case, $describeInlines, $document, $format): void {
                $options = ['format' => $format];
                $markdown = (new MarkdownWriter($options))->write($document($case['children']));

                $t->same($case['expected'], $markdown);

                $roundTrip = (new MarkdownReader($options))->read($markdown);
                $paragraph = $roundTrip->children[0] ?? null;

                $t->true($paragraph instanceof AstNode && $paragraph->type === 'paragraph', 'Expected paragraph after round-trip');
                if ($paragraph instanceof AstNode) {
                    $t->same($case['shape'], $describeInlines($paragraph->children), $markdown);
                    $t->same($case['text'], $paragraph->attr('text'));
                }
            };
    }
}

return $tests;
