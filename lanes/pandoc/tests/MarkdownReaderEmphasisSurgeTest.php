<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak') {
        return ' ';
    }
    if ($node->type === 'linebreak') {
        return "\n";
    }
    if ($node->type === 'raw_tex') {
        return (string) $node->attr('tex', '');
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

$tests['records commonmark emphasis delimiter surge mapped-case count'] =
    static function (TestRunner $t) use ($invalidFlankingCases, $validFlankingCases): void {
        $t->same(84, count($invalidFlankingCases) + count($validFlankingCases));
    };

return $tests;
