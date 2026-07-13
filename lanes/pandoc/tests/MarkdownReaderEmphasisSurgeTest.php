<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'raw_tex') {
        return (string) $node->attr('tex', '');
    }
    if ($node->type === 'raw_html_inline') {
        return (string) $node->attr('html', '');
    }
    if ($node->type === 'softbreak') {
        return ' ';
    }
    if ($node->type === 'linebreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

$containsSemanticEmphasis = static function (AstNode $node) use (&$containsSemanticEmphasis): bool {
    if ($node->type === 'emph' || $node->type === 'strong') {
        return true;
    }

    foreach ($node->children as $child) {
        if ($containsSemanticEmphasis($child)) {
            return true;
        }
    }

    return false;
};

$firstChildOfType = static function (AstNode $node, string $type): AstNode {
    foreach ($node->children as $child) {
        if ($child->type === $type) {
            return $child;
        }
    }

    return new AstNode('missing');
};

$readFirstInline = static function (string $markdown): AstNode {
    $document = (new MarkdownReader())->read($markdown);

    return $document->children[0]->children[0] ?? new AstNode('missing');
};

$paragraphHtml = static function (string $markdown): string {
    $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($markdown));
    if (preg_match('/<p[^>]*>(.*?)<\/p>/s', $blocks, $match) !== 1) {
        return $blocks;
    }

    return $match[1];
};

$invalidFlankingCases = [];
foreach (['*', '**', '_', '__'] as $delimiter) {
    $invalidFlankingCases[] = [
        'Lead ' . $delimiter . ' source' . $delimiter,
        'Lead ' . $delimiter . ' source' . $delimiter,
    ];
    $invalidFlankingCases[] = [
        'Lead ' . $delimiter . '  source' . $delimiter,
        'Lead ' . $delimiter . '  source' . $delimiter,
    ];
    $invalidFlankingCases[] = [
        'Lead ' . $delimiter . 'source ' . $delimiter,
        'Lead ' . $delimiter . 'source ' . $delimiter,
    ];
    $invalidFlankingCases[] = [
        'Lead ' . $delimiter . 'source  ' . $delimiter,
        'Lead ' . $delimiter . 'source  ' . $delimiter,
    ];
}

foreach (['*', '**', '_', '__'] as $delimiter) {
    foreach ([')', ']', '}', '.', ',', '!', '?', ':', ';'] as $punctuation) {
        $invalidFlankingCases[] = [
            'a' . $delimiter . $punctuation . 'source' . $delimiter,
            'a' . $delimiter . $punctuation . 'source' . $delimiter,
        ];
    }
}

foreach ([
    'foo_bar_',
    '_foo_bar',
    'foo_bar_baz',
    'foo_bar_baz_',
    'foo__bar__',
    'foo__bar__baz',
    'abc_def_ghi_jkl',
    'version_1_2',
    'version__1__2',
    'snake_case_identifier_',
    'snake__case__identifier',
    'left_2_right',
] as $intraword) {
    $invalidFlankingCases[] = [$intraword, $intraword];
}

$validFlankingCases = [
    ['*source*', '<em>source</em>', ['emph']],
    ['**source**', '<strong>source</strong>', ['strong']],
    ['_source_', '<em>source</em>', ['emph']],
    ['__source__', '<strong>source</strong>', ['strong']],
    ['a *source* b', 'a <em>source</em> b', ['text', 'emph', 'text']],
    ['a **source** b', 'a <strong>source</strong> b', ['text', 'strong', 'text']],
    ['a _source_ b', 'a <em>source</em> b', ['text', 'emph', 'text']],
    ['a __source__ b', 'a <strong>source</strong> b', ['text', 'strong', 'text']],
    ['a *(source)* b', 'a <em>(source)</em> b', ['text', 'emph', 'text']],
    ['a *[source]* b', 'a <em>[source]</em> b', ['text', 'emph', 'text']],
    ['a *{source}* b', 'a <em>{source}</em> b', ['text', 'emph', 'text']],
    ['a _(source)_ b', 'a <em>(source)</em> b', ['text', 'emph', 'text']],
    ['*source*.', '<em>source</em>.', ['emph', 'text']],
    ['**source**.', '<strong>source</strong>.', ['strong', 'text']],
    ['_source_.', '<em>source</em>.', ['emph', 'text']],
    ['__source__.', '<strong>source</strong>.', ['strong', 'text']],
    ['alpha*beta*gamma', 'alpha<em>beta</em>gamma', ['text', 'emph', 'text']],
    ['alpha**beta**gamma', 'alpha<strong>beta</strong>gamma', ['text', 'strong', 'text']],
    ['_alpha_beta_', '<em>alpha_beta</em>', ['emph']],
    ['__alpha beta__', '<strong>alpha beta</strong>', ['strong']],
];

$payloads = [
    'plain text' => [
        'source' => static fn (string $delimiter): string => 'inner',
        'plain' => static fn (string $delimiter): string => 'inner',
    ],
    'two words' => [
        'source' => static fn (string $delimiter): string => 'review packet',
        'plain' => static fn (string $delimiter): string => 'review packet',
    ],
    'inline code' => [
        'source' => static fn (string $delimiter): string => '`code value`',
        'plain' => static fn (string $delimiter): string => 'code value',
    ],
    'inline link' => [
        'source' => static fn (string $delimiter): string => '[packet](/packet)',
        'plain' => static fn (string $delimiter): string => 'packet',
    ],
    'entity text' => [
        'source' => static fn (string $delimiter): string => '&copy;',
        'plain' => static fn (string $delimiter): string => "\u{00A9}",
    ],
    'smart dash' => [
        'source' => static fn (string $delimiter): string => 'a -- b',
        'plain' => static fn (string $delimiter): string => "a \u{2013} b",
    ],
    'subscript' => [
        'source' => static fn (string $delimiter): string => 'H~2~O',
        'plain' => static fn (string $delimiter): string => 'H2O',
    ],
    'superscript' => [
        'source' => static fn (string $delimiter): string => 'x^2^',
        'plain' => static fn (string $delimiter): string => 'x2',
    ],
    'strikeout' => [
        'source' => static fn (string $delimiter): string => '~~gone~~',
        'plain' => static fn (string $delimiter): string => 'gone',
    ],
    'mark span' => [
        'source' => static fn (string $delimiter): string => '==marked==',
        'plain' => static fn (string $delimiter): string => '==marked==',
    ],
    'raw html inline' => [
        'source' => static fn (string $delimiter): string => '<span>raw</span>',
        'plain' => static fn (string $delimiter): string => '<span>raw</span>',
    ],
    'math inline' => [
        'source' => static fn (string $delimiter): string => '$x + y$',
        'plain' => static fn (string $delimiter): string => 'x + y',
    ],
    'raw tex inline' => [
        'source' => static fn (string $delimiter): string => '\\LaTeX{}',
        'plain' => static fn (string $delimiter): string => '\\LaTeX{}',
    ],
    'smart quote' => [
        'source' => static fn (string $delimiter): string => '"quote"',
        'plain' => static fn (string $delimiter): string => 'quote',
    ],
    'escaped delimiter' => [
        'source' => static fn (string $delimiter): string => '\\' . $delimiter . 'literal',
        'plain' => static fn (string $delimiter): string => $delimiter . 'literal',
    ],
];

$delimiterSpecs = [
    'asterisk' => ['emph' => '*', 'strong' => '**', 'triple' => '***'],
    'underscore' => ['emph' => '_', 'strong' => '__', 'triple' => '___'],
];

$tests = [];

foreach ($invalidFlankingCases as $index => [$markdown, $expectedText]) {
    $caseNumber = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
    $tests['maps commonmark emphasis invalid flanking case ' . $caseNumber] =
        static function (TestRunner $t) use ($markdown, $expectedText, $plainText, $containsSemanticEmphasis): void {
            $paragraph = (new MarkdownReader())->read($markdown)->children[0] ?? new AstNode('missing');

            $t->same($expectedText, $plainText($paragraph), $markdown);
            $t->same(false, $containsSemanticEmphasis($paragraph), $markdown);
        };
}

foreach ($validFlankingCases as $index => [$markdown, $expectedHtml, $expectedTypes]) {
    $caseNumber = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
    $tests['maps commonmark emphasis valid flanking case ' . $caseNumber] =
        static function (TestRunner $t) use ($markdown, $expectedHtml, $expectedTypes, $paragraphHtml): void {
            $paragraph = (new MarkdownReader())->read($markdown)->children[0] ?? new AstNode('missing');

            $t->same($expectedTypes, array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), $markdown);
            $t->same($expectedHtml, $paragraphHtml($markdown), $markdown);
        };
}

foreach ($delimiterSpecs as $delimiterName => $delimiters) {
    foreach ($payloads as $payloadName => $payload) {
        $innerSource = $payload['source']($delimiters['emph']);
        $expectedInner = $payload['plain']($delimiters['emph']);
        $markdown = $delimiters['strong'] . 'outer ' . $delimiters['emph'] . $innerSource . $delimiters['triple'];

        $tests["maps commonmark nested emphasis surge strong outer {$delimiterName} {$payloadName}"] =
            static function (TestRunner $t) use ($markdown, $expectedInner, $plainText, $firstChildOfType, $readFirstInline): void {
                $outer = $readFirstInline($markdown);
                $inner = $firstChildOfType($outer, 'emph');
                $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($markdown));

                $t->same('strong', $outer->type);
                $t->same('emph', $inner->type);
                $t->same($expectedInner, $plainText($inner));
                $t->same('outer ' . $expectedInner, $plainText($outer));
                $t->contains('<strong', $blocks);
                $t->contains('<em', $blocks);
            };

        $innerSource = $payload['source']($delimiters['emph']);
        $expectedInner = $payload['plain']($delimiters['emph']);
        $markdown = $delimiters['emph'] . 'outer ' . $delimiters['strong'] . $innerSource . $delimiters['triple'];

        $tests["maps commonmark nested emphasis surge emph outer {$delimiterName} {$payloadName}"] =
            static function (TestRunner $t) use ($markdown, $expectedInner, $plainText, $firstChildOfType, $readFirstInline): void {
                $outer = $readFirstInline($markdown);
                $inner = $firstChildOfType($outer, 'strong');
                $blocks = (new WordPressBlockWriter())->write((new MarkdownReader())->read($markdown));

                $t->same('emph', $outer->type);
                $t->same('strong', $inner->type);
                $t->same($expectedInner, $plainText($inner));
                $t->same('outer ' . $expectedInner, $plainText($outer));
                $t->contains('<strong', $blocks);
                $t->contains('<em', $blocks);
            };
    }
}

$tests['records commonmark emphasis delimiter surge mapped-case count'] =
    static function (TestRunner $t) use ($invalidFlankingCases, $validFlankingCases): void {
        $t->same(84, count($invalidFlankingCases) + count($validFlankingCases));
    };

return $tests;
