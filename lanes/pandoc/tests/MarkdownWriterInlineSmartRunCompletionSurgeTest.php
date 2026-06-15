<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$linebreak = static fn (): AstNode => new AstNode('linebreak');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$writeParagraph = static fn (array $children, array $options = []): string => (new MarkdownWriter($options))->write($document([$paragraph($children)]));
$inline = static fn (string $type, array $children, array $attrs = []): AstNode => new AstNode($type, $attrs, $children);
$link = static fn (string $url, array $children, array $attrs = []): AstNode => new AstNode('link', ['url' => $url] + $attrs, $children);
$image = static fn (string $url, string $alt, array $children = [], array $attrs = []): AstNode => new AstNode(
    'image',
    ['url' => $url, 'alt' => $alt] + $attrs,
    $children
);

$escapedRun = static fn (string $char, int $length): string => str_repeat('\\' . $char, $length);
$dashRun = static fn (int $length): string => str_repeat('-', $length);
$dotRun = static fn (int $length): string => str_repeat('.', $length);

$tests = [];

foreach (range(3, 14) as $length) {
    $source = 'alpha' . $dashRun($length) . 'omega';
    $expected = 'alpha' . $escapedRun('-', $length) . 'omega';

    $tests["maps upstream markdown writer inline smart-run completion dash run length {$length}"] =
        static function (TestRunner $t) use ($source, $expected, $text, $writeParagraph): void {
            $markdown = $writeParagraph([$text($source)]);
            $roundTrip = (new MarkdownReader())->read($markdown);

            $t->same($expected, $markdown);
            $t->same($source, $roundTrip->children[0]->attr('text'));
        };
}

foreach (range(4, 15) as $length) {
    $source = 'alpha' . $dotRun($length) . 'omega';
    $expected = 'alpha' . $escapedRun('.', $length) . 'omega';

    $tests["maps upstream markdown writer inline smart-run completion dot run length {$length}"] =
        static function (TestRunner $t) use ($source, $expected, $text, $writeParagraph): void {
            $markdown = $writeParagraph([$text($source)]);
            $roundTrip = (new MarkdownReader())->read($markdown);

            $t->same($expected, $markdown);
            $t->same($source, $roundTrip->children[0]->attr('text'));
        };
}

$mixedCases = [
    'dash then ellipsis' => ['---...', $escapedRun('-', 3) . '\\...'],
    'ellipsis then dash' => ['...---', '\\...' . $escapedRun('-', 3)],
    'long dash then long dots' => ['----....', $escapedRun('-', 4) . $escapedRun('.', 4)],
    'long dots then long dash' => ['.....-----', $escapedRun('.', 5) . $escapedRun('-', 5)],
    'word dash dots word' => ['alpha---....omega', 'alpha' . $escapedRun('-', 3) . $escapedRun('.', 4) . 'omega'],
    'word dots dash word' => ['alpha....---omega', 'alpha' . $escapedRun('.', 4) . $escapedRun('-', 3) . 'omega'],
    'dash run before smart quotes' => ['---"quoted"', $escapedRun('-', 3) . '\\"quoted\\"'],
    'dot run before bracket label' => ['....[label]', $escapedRun('.', 4) . '\\[label\\]', false],
    'dash run before entity' => ['---&ouml;', $escapedRun('-', 3) . '\\&ouml;', false],
    'dot run before raw angle' => ['....<tag>', $escapedRun('.', 4) . '\\<tag\\>'],
];

foreach ($mixedCases as $label => $case) {
    $tests["maps upstream markdown writer inline smart-run completion mixed smart run {$label}"] =
        static function (TestRunner $t) use ($case, $text, $writeParagraph): void {
            [$source, $expected] = $case;
            $markdown = $writeParagraph([$text($source)]);

            $t->same($expected, $markdown);

            if (($case[2] ?? true) === true) {
                $roundTrip = (new MarkdownReader())->read($markdown);
                $t->same($source, $roundTrip->children[0]->attr('text'));
            }
        };
}

$inlineCases = [
    'emphasis dash run' => [
        [$inline('emph', [$text('alpha---omega')])],
        '*alpha' . $escapedRun('-', 3) . 'omega*',
    ],
    'strong dot run' => [
        [$inline('strong', [$text('alpha....omega')])],
        '**alpha' . $escapedRun('.', 4) . 'omega**',
    ],
    'strikeout dash run' => [
        [$inline('strikeout', [$text('alpha----omega')])],
        '~~alpha' . $escapedRun('-', 4) . 'omega~~',
    ],
    'superscript dash run' => [
        [$inline('superscript', [$text('alpha---omega')])],
        '^alpha' . $escapedRun('-', 3) . 'omega^',
    ],
    'subscript dot run' => [
        [$inline('subscript', [$text('alpha....omega')])],
        '~alpha' . $escapedRun('.', 4) . 'omega~',
    ],
    'mark span dash run' => [
        [$inline('span', [$text('alpha---omega')], ['classes' => ['mark']])],
        '==alpha' . $escapedRun('-', 3) . 'omega==',
    ],
    'generic span dot run' => [
        [$inline('span', [$text('alpha....omega')], ['classes' => ['review']])],
        '[alpha' . $escapedRun('.', 4) . 'omega]{.review}',
    ],
    'link label dash run' => [
        [$link('/dash', [$text('alpha---omega')])],
        '[alpha' . $escapedRun('-', 3) . 'omega](/dash)',
    ],
    'link label dot run' => [
        [$link('/dots', [$text('alpha....omega')])],
        '[alpha' . $escapedRun('.', 4) . 'omega](/dots)',
    ],
    'image alt dash run' => [
        [$image('media/dash.png', 'alpha---omega')],
        '![alpha' . $escapedRun('-', 3) . 'omega](media/dash.png)',
    ],
    'image label dot run' => [
        [$image('media/dots.png', 'plain alt', [$text('alpha....omega')])],
        '![alpha' . $escapedRun('.', 4) . 'omega](media/dots.png){alt="plain alt"}',
    ],
    'reference link dash run' => [
        [$link('/dash', [$text('alpha---omega')])],
        '[alpha' . $escapedRun('-', 3) . "omega]\n\n  [alpha---omega]: /dash",
        ['referenceLinks' => true],
    ],
    'reference image dot run' => [
        [$image('media/dots.png', 'alpha....omega')],
        '![alpha' . $escapedRun('.', 4) . "omega]\n\n  [alpha....omega]: media/dots.png",
        ['referenceLinks' => true],
    ],
    'citation prefix dash run' => [
        [new AstNode('citation', ['id' => 'doe2026', 'prefix' => 'see---also'])],
        '[see' . $escapedRun('-', 3) . 'also @doe2026]',
    ],
    'citation suffix dot run' => [
        [new AstNode('citation', ['id' => 'doe2026', 'suffix' => 'note....more'])],
        '[@doe2026, note' . $escapedRun('.', 4) . 'more]',
    ],
];

foreach ($inlineCases as $label => $case) {
    $tests["maps upstream markdown writer inline smart-run completion context {$label}"] =
        static function (TestRunner $t) use ($case, $writeParagraph): void {
            $children = $case[0];
            $expected = $case[1];
            $options = $case[2] ?? [];

            $t->same($expected, $writeParagraph($children, $options));
        };
}

$lineStartCases = [
    'paragraph triple dash line' => [[$text('---')], $escapedRun('-', 3)],
    'paragraph long dash line' => [[$text('-----')], $escapedRun('-', 5)],
    'paragraph long dot line' => [[$text('....')], $escapedRun('.', 4)],
    'softbreak before triple dash' => [[$text('alpha'), $softbreak(), $text('---')], 'alpha' . "\n" . $escapedRun('-', 3)],
    'softbreak before long dots' => [[$text('alpha'), $softbreak(), $text('....')], 'alpha' . "\n" . $escapedRun('.', 4)],
    'hardbreak before triple dash' => [[$text('alpha'), $linebreak(), $text('---')], 'alpha\\' . "\n" . $escapedRun('-', 3)],
    'hardbreak before long dots' => [[$text('alpha'), $linebreak(), $text('....')], 'alpha\\' . "\n" . $escapedRun('.', 4)],
    'space then dash run text' => [[$text('alpha'), $space(), $text('---')], 'alpha ' . $escapedRun('-', 3)],
    'emphasis softbreak dash run' => [[$inline('emph', [$text('alpha'), $softbreak(), $text('---')])], '*alpha' . "\n" . $escapedRun('-', 3) . '*'],
    'strong hardbreak dot run' => [[$inline('strong', [$text('alpha'), $linebreak(), $text('....')])], '**alpha\\' . "\n" . $escapedRun('.', 4) . '**'],
    'link softbreak dash run' => [[$link('/line', [$text('alpha'), $softbreak(), $text('---')])], '[alpha' . "\n" . $escapedRun('-', 3) . '](/line)'],
    'image softbreak dot run' => [[$image('media/line.png', 'alpha dots', [$text('alpha'), $softbreak(), $text('....')])], '![alpha' . "\n" . $escapedRun('.', 4) . '](media/line.png){alt="alpha dots"}'],
];

foreach ($lineStartCases as $label => [$children, $expected]) {
    $tests["maps upstream markdown writer inline smart-run completion line-start {$label}"] =
        static function (TestRunner $t) use ($children, $expected, $writeParagraph): void {
            $t->same($expected, $writeParagraph($children));
        };
}

return $tests;
