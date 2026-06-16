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
$heading = static fn (array $children, int $level = 1, array $attrs = []): AstNode => new AstNode(
    'heading',
    array_replace(['level' => $level], $attrs),
    $children
);
$setext = static fn (string $value, int $level = 1): string => $value . "\n"
    . str_repeat($level === 1 ? '=' : '-', max(1, strlen($value)));

$tests = [];
$cases = [];

$wrappedMarkers = [
    'atx h1 marker' => ['#', '\\#'],
    'deep atx marker' => ['###', '\\###'],
    'dash bullet marker' => ['-', '\\-'],
    'plus bullet marker' => ['+', '\\+'],
    'asterisk bullet marker' => ['*', '\\*'],
    'thematic break dashes' => ['---', '\\-\\-\\-'],
    'long thematic break dashes' => ['----', '\\-\\-\\-\\-'],
    'single setext equals' => ['=', '\\='],
    'double setext equals' => ['==', '\\=='],
    'long setext equals' => ['===', '\\==='],
    'decimal period marker' => ['1.', '1\\.'],
    'decimal paren marker' => ['2)', '2\\)'],
    'default period marker' => ['#.', '\\#.'],
    'default paren marker' => ['#)', '\\#)'],
    'upper alpha marker' => ['A.  item', 'A. item'],
    'lower roman marker' => ['iv. item', 'iv\\. item'],
    'parenthesized ordered marker' => ['(1)', '\\(1)'],
    'numbered example marker' => ['(@)', '\\(@)'],
    'labeled numbered example marker' => ['(@fig)', '(\\@fig)'],
    'definition colon marker' => [':', '\\:'],
    'definition tilde marker' => ['~', '\\~'],
];

foreach ([8, 10, 12] as $columns) {
    foreach ($wrappedMarkers as $label => [$marker, $expectedMarker]) {
        $lead = str_repeat('a', $columns);
        $cases['wrapped paragraph guard ' . $columns . ' columns ' . $label] = [
            'document' => $document([$paragraph([$text($lead), $space(), $text($marker)])]),
            'expected' => $lead . "\n" . $expectedMarker,
            'options' => ['columns' => $columns],
            'roundTripParagraph' => true,
        ];
    }
}

for ($level = 1; $level <= 6; $level++) {
    $prefix = str_repeat('#', $level);
    $cases['atx heading level ' . $level . ' escapes closing single hash'] = [
        'document' => $document([$heading([$text('Review #')], $level)]),
        'expected' => $prefix . ' Review \\#',
    ];
    $cases['atx heading level ' . $level . ' escapes closing hash run'] = [
        'document' => $document([$heading([$text('Review ###')], $level)]),
        'expected' => $prefix . ' Review \\###',
    ];
}

$cases += [
    'atx heading keeps adjacent hash text literal' => [
        'document' => $document([$heading([$text('C# review')])]),
        'expected' => '# C# review',
    ],
    'atx heading trims leading and trailing spaces' => [
        'document' => $document([$heading([$text('  Review packet  ')])]),
        'expected' => '# Review packet',
    ],
    'atx heading collapses tab whitespace' => [
        'document' => $document([$heading([$text("Review\t\tpacket")])]),
        'expected' => '# Review packet',
    ],
    'atx heading normalizes softbreak to space' => [
        'document' => $document([$heading([$text('Review'), $softbreak(), $text('packet')])]),
        'expected' => '# Review packet',
    ],
    'atx heading normalizes softbreak surrounding spaces' => [
        'document' => $document([$heading([$text('Review  '), $softbreak(), $text('  packet')])]),
        'expected' => '# Review packet',
    ],
    'atx heading escapes closing hash before attributes' => [
        'document' => $document([$heading([$text('Review #')], 2, ['id' => 'review'])]),
        'expected' => '## Review \\# {#review}',
    ],
    'setext heading level one stays single line' => [
        'document' => $document([$heading([$text('Review packet')])]),
        'expected' => $setext('Review packet'),
        'options' => ['setextHeadings' => true],
    ],
    'setext heading level two stays single line' => [
        'document' => $document([$heading([$text('Review packet')], 2)]),
        'expected' => $setext('Review packet', 2),
        'options' => ['setextHeadings' => true],
    ],
    'setext heading normalizes softbreak to space' => [
        'document' => $document([$heading([$text('Review'), $softbreak(), $text('packet')])]),
        'expected' => $setext('Review packet'),
        'options' => ['setextHeadings' => true],
    ],
    'setext heading leaves trailing hash as content' => [
        'document' => $document([$heading([$text('Review #')])]),
        'expected' => $setext('Review #'),
        'options' => ['setextHeadings' => true],
    ],
    'setext heading includes attribute tuple in underline width' => [
        'document' => $document([$heading([$text('Review')], 1, ['id' => 'review'])]),
        'expected' => $setext('Review {#review}'),
        'options' => ['setextHeadings' => true],
    ],
];

$tests['records markdown writer heading paragraph residual mapped case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(86, count($cases));
    };

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer heading paragraph residual ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter($case['options'] ?? []))->write($case['document']);
            $t->same($case['expected'], $markdown);

            if (($case['roundTripParagraph'] ?? false) === true) {
                $roundTrip = (new MarkdownReader())->read($markdown);
                $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
            }
        };
}

return $tests;
