<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$codeSpanSource = static function (string $payload, int $ticks): string {
    $delimiter = str_repeat('`', $ticks);

    return $delimiter . $payload . $delimiter;
};

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }

    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

$profileFormats = [
    'markdown',
    'commonmark',
    'gfm',
];

$codeSpanCases = [
    'tab as sole non-space' => [
        'payload' => " \t ",
        'expected' => "\t",
        'ticks' => 1,
    ],
    'nul as sole non-space' => [
        'payload' => " \0 ",
        'expected' => "\0",
        'ticks' => 1,
    ],
    'no-break space as sole non-space' => [
        'payload' => " \u{00A0} ",
        'expected' => "\u{00A0}",
        'ticks' => 1,
    ],
    'em space as sole non-space' => [
        'payload' => " \u{2003} ",
        'expected' => "\u{2003}",
        'ticks' => 1,
    ],
    'zero width joiner as sole non-space' => [
        'payload' => " \u{200D} ",
        'expected' => "\u{200D}",
        'ticks' => 1,
    ],
    'tab before word' => [
        'payload' => " \tcode ",
        'expected' => "\tcode",
        'ticks' => 1,
    ],
    'word before tab' => [
        'payload' => " code\t ",
        'expected' => "code\t",
        'ticks' => 1,
    ],
    'tabs without boundary spaces' => [
        'payload' => "\tcode\t",
        'expected' => "\tcode\t",
        'ticks' => 1,
    ],
    'single all-space span' => [
        'payload' => ' ',
        'expected' => ' ',
        'ticks' => 1,
    ],
    'double all-space span' => [
        'payload' => '  ',
        'expected' => '  ',
        'ticks' => 1,
    ],
    'triple all-space span' => [
        'payload' => '   ',
        'expected' => '   ',
        'ticks' => 1,
    ],
    'multiline code span' => [
        'payload' => "line\nnext",
        'expected' => 'line next',
        'ticks' => 1,
    ],
    'boundary spaces around multiline text' => [
        'payload' => " line\nnext ",
        'expected' => 'line next',
        'ticks' => 1,
    ],
    'boundary spaces around tab and no-break space' => [
        'payload' => " \t\u{00A0} ",
        'expected' => "\t\u{00A0}",
        'ticks' => 1,
    ],
    'newline before trailing tabbed text' => [
        'payload' => " a\nb\t ",
        'expected' => "a b\t",
        'ticks' => 1,
    ],
    'emphasis markers stay literal' => [
        'payload' => '*em*',
        'expected' => '*em*',
        'ticks' => 1,
    ],
    'strong markers stay literal' => [
        'payload' => '**strong**',
        'expected' => '**strong**',
        'ticks' => 1,
    ],
    'link syntax stays literal' => [
        'payload' => '[label](/url)',
        'expected' => '[label](/url)',
        'ticks' => 1,
    ],
    'autolink syntax stays literal' => [
        'payload' => '<https://example.test>',
        'expected' => '<https://example.test>',
        'ticks' => 1,
    ],
    'entity syntax stays literal' => [
        'payload' => '&amp;',
        'expected' => '&amp;',
        'ticks' => 1,
    ],
    'escaped emphasis stays literal' => [
        'payload' => '\\*literal\\*',
        'expected' => '\\*literal\\*',
        'ticks' => 1,
    ],
    'raw html syntax stays literal' => [
        'payload' => '<em>literal</em>',
        'expected' => '<em>literal</em>',
        'ticks' => 1,
    ],
    'math syntax stays literal' => [
        'payload' => '$x + y$',
        'expected' => '$x + y$',
        'ticks' => 1,
    ],
    'single literal backtick inside text' => [
        'payload' => 'a`b',
        'expected' => 'a`b',
        'ticks' => 2,
    ],
    'double literal backtick inside text' => [
        'payload' => 'a``b',
        'expected' => 'a``b',
        'ticks' => 3,
    ],
    'spaced double literal backtick' => [
        'payload' => ' `` ',
        'expected' => '``',
        'ticks' => 3,
    ],
    'leading space without trailing space' => [
        'payload' => ' code',
        'expected' => ' code',
        'ticks' => 1,
    ],
    'trailing space without leading space' => [
        'payload' => 'code ',
        'expected' => 'code ',
        'ticks' => 1,
    ],
    'interior double space preserved' => [
        'payload' => 'code  span',
        'expected' => 'code  span',
        'ticks' => 1,
    ],
    'boundary spaces around escaped backslash' => [
        'payload' => ' \\ ',
        'expected' => '\\',
        'ticks' => 1,
    ],
];

$tests = [];

foreach ($profileFormats as $format) {
    foreach ($codeSpanCases as $caseName => $case) {
        $tests["maps upstream markdown reader code-span whitespace profile {$format} {$caseName}"] =
            static function (TestRunner $t) use ($format, $case, $caseName, $codeSpanSource, $plainText): void {
                $source = 'before ' . $codeSpanSource($case['payload'], $case['ticks']) . ' after';
                $document = (new MarkdownReader(['format' => $format]))->read($source);
                $paragraph = $document->children[0] ?? new AstNode('missing');
                $code = $paragraph->children[1] ?? new AstNode('missing');

                $t->same('paragraph', $paragraph->type, $caseName);
                $t->same(['text', 'code', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), $caseName);
                $t->same($case['expected'], $code->attr('text'), $caseName);
                $t->same('before ' . $case['expected'] . ' after', $plainText($paragraph), $caseName);
                $t->same(false, in_array('emph', array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), true), $caseName);
            };
    }
}

$tests['records upstream markdown reader code-span whitespace profile mapped-case count'] =
    static function (TestRunner $t) use ($profileFormats, $codeSpanCases): void {
        $t->same(90, count($profileFormats) * count($codeSpanCases));
    };

return $tests;
