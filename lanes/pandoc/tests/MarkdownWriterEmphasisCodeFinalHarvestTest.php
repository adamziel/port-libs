<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$code = static fn (string $value, array $attrs = []): AstNode => new AstNode('code', ['text' => $value] + $attrs);
$emph = static fn (array $children, array $attrs = []): AstNode => new AstNode('emph', $attrs, $children);
$strong = static fn (array $children, array $attrs = []): AstNode => new AstNode('strong', $attrs, $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);
$writeParagraph = static fn (array $children, array $options = []): string => (new MarkdownWriter($options))->write($document($children));

/**
 * @return list<string>
 */
$describeInlines = static function (array $nodes) use (&$describeInlines): array {
    $described = [];
    foreach ($nodes as $node) {
        if ($node->type === 'text') {
            $described[] = 'text:' . $node->attr('text', '');
            continue;
        }

        if ($node->type === 'code') {
            $described[] = 'code:' . $node->attr('text', '');
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

$assertRoundTripShape = static function (
    TestRunner $t,
    string $markdown,
    array $expectedShape,
    array $options
) use ($describeInlines): void {
    $roundTrip = (new MarkdownReader($options))->read($markdown);
    $paragraph = $roundTrip->children[0] ?? null;

    $t->true($paragraph instanceof AstNode && $paragraph->type === 'paragraph', 'Expected paragraph after round-trip');
    if ($paragraph instanceof AstNode) {
        $t->same($expectedShape, $describeInlines($paragraph->children), $markdown);
    }
};

$formats = ['markdown', 'commonmark', 'gfm'];

$explicitDelimiterCases = [
    'emph underscore delimiter' => [
        [$emph([$text('alpha')], ['delimiter' => '_'])],
        '_alpha_',
        ['emph(text:alpha)'],
    ],
    'emph markdown delimiter underscore name trims edge spaces' => [
        [$emph([$text(' edge ')], ['markdownDelimiter' => 'underscore'])],
        ' _edge_ ',
        ['emph(text:edge)'],
    ],
    'strong double underscore delimiter' => [
        [$strong([$text('alpha')], ['delimiter' => '__'])],
        '__alpha__',
        ['strong(text:alpha)'],
    ],
    'strong source delimiter underscore keeps intraword underscore' => [
        [$strong([$text('alpha_beta')], ['sourceDelimiter' => 'underscore'])],
        '__alpha_beta__',
        ['strong(text:alpha_beta)'],
    ],
    'emph source delimiter asterisk' => [
        [$emph([$text('alpha')], ['sourceDelimiter' => 'asterisk'])],
        '*alpha*',
        ['emph(text:alpha)'],
    ],
    'strong markdown delimiter asterisks' => [
        [$strong([$text('alpha')], ['markdownStrongDelimiter' => 'asterisks'])],
        '**alpha**',
        ['strong(text:alpha)'],
    ],
    'emph invalid delimiter falls back to asterisk' => [
        [$emph([$text('alpha')], ['delimiter' => 'pipe'])],
        '*alpha*',
        ['emph(text:alpha)'],
    ],
    'strong underscore delimiter still escapes literal star text' => [
        [$strong([$text('a * b')], ['delimiter' => '_'])],
        '__a \\* b__',
        ['strong(text:a * b)'],
    ],
];

$nestedCases = [
    'emph contains strong' => [
        [$emph([$strong([$text('foo')])])],
        '*__foo__*',
        ['emph(strong(text:foo))'],
    ],
    'strong contains emph' => [
        [$strong([$emph([$text('foo')])])],
        '**_foo_**',
        ['strong(emph(text:foo))'],
    ],
    'emph contains emph' => [
        [$emph([$emph([$text('foo')])])],
        '*_foo_*',
        ['emph(emph(text:foo))'],
    ],
    'strong contains strong' => [
        [$strong([$strong([$text('foo')])])],
        '**__foo__**',
        ['strong(strong(text:foo))'],
    ],
    'emph sentence contains strong' => [
        [$emph([$text('alpha '), $strong([$text('beta')]), $text(' gamma')])],
        '*alpha __beta__ gamma*',
        ['emph(text:alpha |strong(text:beta)|text: gamma)'],
    ],
    'strong sentence contains emph' => [
        [$strong([$text('alpha '), $emph([$text('beta')]), $text(' gamma')])],
        '**alpha _beta_ gamma**',
        ['strong(text:alpha |emph(text:beta)|text: gamma)'],
    ],
    'emph contains strong with intraword underscore' => [
        [$emph([$strong([$text('alpha_beta')])])],
        '*__alpha_beta__*',
        ['emph(strong(text:alpha_beta))'],
    ],
    'strong contains emph with escaped literal star' => [
        [$strong([$emph([$text('alpha * beta')])])],
        '**_alpha \\* beta_**',
        ['strong(emph(text:alpha * beta))'],
    ],
    'emph contains code' => [
        [$emph([$code('alpha code')])],
        '*`alpha code`*',
        ['emph(code:alpha code)'],
    ],
    'strong contains normalized code' => [
        [$strong([$code("alpha\nbeta")])],
        '**`alpha beta`**',
        ['strong(code:alpha beta)'],
    ],
    'explicit underscore emph contains strong' => [
        [$emph([$strong([$text('foo')])], ['delimiter' => '_'])],
        '_**foo**_',
        ['emph(strong(text:foo))'],
    ],
    'explicit underscore strong contains emph' => [
        [$strong([$emph([$text('foo')])], ['delimiter' => '_'])],
        '__*foo*__',
        ['strong(emph(text:foo))'],
    ],
];

$codeWhitespaceCases = [
    'line feed interior' => ["alpha\nbeta", '`alpha beta`', 'alpha beta'],
    'crlf interior' => ["alpha\r\nbeta", '`alpha beta`', 'alpha beta'],
    'carriage return interior' => ["alpha\rbeta", '`alpha beta`', 'alpha beta'],
    'double line feed interior' => ["alpha\n\nbeta", '`alpha  beta`', 'alpha  beta'],
    'leading line feed' => ["\nalpha", '`  alpha `', ' alpha'],
    'trailing line feed' => ["alpha\n", '` alpha  `', 'alpha '],
    'edge line feeds' => ["\nalpha\n", '`  alpha  `', ' alpha '],
    'backtick with line feed' => ["alpha `\nbeta", '`` alpha ` beta ``', 'alpha ` beta'],
];

$profileCases = [
    'markdown keeps inline code attributes' => [
        ['format' => 'markdown'],
        '`alpha`{#code-id .php data-x="1"}',
    ],
    'commonmark falls back to html code attributes' => [
        ['format' => 'commonmark'],
        '<code id="code-id" class="php" data-x="1">alpha</code>',
    ],
    'gfm falls back to html code attributes' => [
        ['format' => 'gfm'],
        '<code id="code-id" class="php" data-x="1">alpha</code>',
    ],
    'markdown disabled inline code attributes falls back to html' => [
        ['format' => 'markdown', 'extensions' => ['-inline_code_attributes']],
        '<code id="code-id" class="php" data-x="1">alpha</code>',
    ],
    'commonmark enabled inline code attributes keeps markdown attributes' => [
        ['format' => 'commonmark+inline_code_attributes'],
        '`alpha`{#code-id .php data-x="1"}',
    ],
    'gfm enabled inline code attributes keeps markdown attributes' => [
        ['format' => 'gfm+inline_code_attributes'],
        '`alpha`{#code-id .php data-x="1"}',
    ],
];

$tests = [];
$mappedCaseCount = 0;

foreach ($formats as $format) {
    foreach ($explicitDelimiterCases as $label => [$children, $expected, $shape]) {
        $mappedCaseCount++;
        $tests["maps upstream {$format} writer emphasis delimiter final harvest {$label}"] =
            static function (TestRunner $t) use ($assertRoundTripShape, $children, $expected, $format, $shape, $writeParagraph): void {
                $options = ['format' => $format];
                $markdown = $writeParagraph($children, $options);

                $t->same($expected, $markdown);
                $assertRoundTripShape($t, $markdown, $shape, $options);
            };
    }
}

foreach ($formats as $format) {
    foreach ($nestedCases as $label => [$children, $expected, $shape]) {
        $mappedCaseCount++;
        $tests["maps upstream {$format} writer nested emphasis code final harvest {$label}"] =
            static function (TestRunner $t) use ($assertRoundTripShape, $children, $expected, $format, $shape, $writeParagraph): void {
                $options = ['format' => $format];
                $markdown = $writeParagraph($children, $options);

                $t->same($expected, $markdown);
                $assertRoundTripShape($t, $markdown, $shape, $options);
            };
    }
}

foreach ($formats as $format) {
    foreach ($codeWhitespaceCases as $label => [$source, $expected, $shapeText]) {
        $mappedCaseCount++;
        $tests["maps upstream {$format} writer code span whitespace final harvest {$label}"] =
            static function (TestRunner $t) use ($assertRoundTripShape, $code, $expected, $format, $shapeText, $source, $writeParagraph): void {
                $options = ['format' => $format];
                $markdown = $writeParagraph([$code($source)], $options);

                $t->same($expected, $markdown);
                $assertRoundTripShape($t, $markdown, ['code:' . $shapeText], $options);
            };
    }
}

foreach ($profileCases as $label => [$options, $expected]) {
    $mappedCaseCount++;
    $tests['maps upstream writer profile-sensitive inline code final harvest ' . $label] =
        static function (TestRunner $t) use ($code, $expected, $options, $writeParagraph): void {
            $markdown = $writeParagraph([
                $code('alpha', [
                    'id' => 'code-id',
                    'classes' => ['php'],
                    'attributes' => ['data-x' => '1'],
                ]),
            ], $options);

            $t->same($expected, $markdown);
        };
}

$tests['records markdown writer emphasis code final harvest mapped-case count'] =
    static function (TestRunner $t) use ($mappedCaseCount): void {
        $t->same(90, $mappedCaseCount);
    };

return $tests;
