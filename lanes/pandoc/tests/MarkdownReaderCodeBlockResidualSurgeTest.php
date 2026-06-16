<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$paragraphFormats = [
    'commonmark' => ['format' => 'commonmark'],
    'commonmark_x' => ['format' => 'commonmark_x'],
    'gfm' => ['format' => 'gfm'],
    'markdown_github' => ['format' => 'markdown_github'],
];

$fenceFormats = [
    'markdown' => ['format' => 'markdown'],
    'commonmark' => ['format' => 'commonmark'],
    'commonmark_x' => ['format' => 'commonmark_x'],
    'gfm' => ['format' => 'gfm'],
];

$paragraphInterruptionCases = [
    'four spaces' => ['    code', 'paragraph code'],
    'leading tab' => ["\tcode", 'paragraph code'],
    'one-space-tab' => [" \tcode", 'paragraph code'],
    'two-space-tab' => ["  \tcode", 'paragraph code'],
    'three-space-tab' => ["   \tcode", 'paragraph code'],
    'indented bullet text' => ['    - not list', 'paragraph - not list'],
    'indented ordered text' => ['    1. not ordered', 'paragraph 1. not ordered'],
    'indented fence text' => ['    ``` not fence', 'paragraph ``` not fence'],
];

$fenceTabCases = [];
foreach ([1, 2, 3] as $indent) {
    $fenceTabCases["indent {$indent} tab"] = [
        'indent' => $indent,
        'line' => "\tcode",
        'expected' => str_repeat(' ', 4 - $indent) . 'code',
    ];

    if ($indent > 1) {
        $fenceTabCases["indent {$indent} space-tab"] = [
            'indent' => $indent,
            'line' => " \tcode",
            'expected' => str_repeat(' ', 4 - $indent) . 'code',
        ];
    }

    if ($indent > 2) {
        $fenceTabCases["indent {$indent} two-space-tab"] = [
            'indent' => $indent,
            'line' => "  \tcode",
            'expected' => str_repeat(' ', 4 - $indent) . 'code',
        ];
    }
}

$fenceStyles = [
    'backtick' => '`',
    'tilde' => '~',
];

$tests = [];

$tests['maps upstream markdown reader indented code paragraph noninterruption residual cases'] =
    static function (TestRunner $t) use ($paragraphFormats, $paragraphInterruptionCases): void {
        $mappedCases = count($paragraphFormats) * count($paragraphInterruptionCases);
        $t->same(32, $mappedCases);

        foreach ($paragraphFormats as $formatName => $options) {
            $reader = new MarkdownReader($options);
            foreach ($paragraphInterruptionCases as $caseName => [$line, $expectedText]) {
                $markdown = "paragraph\n" . $line;
                $document = $reader->read($markdown);
                $label = "{$formatName} {$caseName}";

                $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children), $label);
                $t->same($expectedText, (string) ($document->children[0] ?? new AstNode('missing'))->attr('text', ''), $label);
            }
        }
    };

$tests['maps upstream markdown reader fenced code tab deindent residual cases'] =
    static function (TestRunner $t) use ($fenceFormats, $fenceStyles, $fenceTabCases): void {
        $mappedCases = count($fenceFormats) * count($fenceStyles) * count($fenceTabCases);
        $t->same(48, $mappedCases);

        foreach ($fenceFormats as $formatName => $options) {
            $reader = new MarkdownReader($options);
            foreach ($fenceStyles as $styleName => $fenceChar) {
                foreach ($fenceTabCases as $caseName => $case) {
                    $indent = (int) $case['indent'];
                    $fence = str_repeat($fenceChar, 3);
                    $padding = str_repeat(' ', $indent);
                    $markdown = $padding . $fence . "\n" . $case['line'] . "\n" . $padding . $fence;
                    $document = $reader->read($markdown);
                    $code = $document->children[0] ?? new AstNode('missing');
                    $label = "{$formatName} {$styleName} {$caseName}";

                    $t->same(['code_block'], array_map(static fn (AstNode $node): string => $node->type, $document->children), $label);
                    $t->same($case['expected'], (string) $code->attr('text', ''), $label);
                    $t->same([], $code->attr('classes', []), $label);
                    $t->same([], $code->attr('attributes', []), $label);
                }
            }
        }
    };

$tests['records markdown reader code block residual surge mapped-case count'] =
    static function (TestRunner $t) use ($paragraphFormats, $paragraphInterruptionCases, $fenceFormats, $fenceStyles, $fenceTabCases): void {
        $t->same(
            80,
            count($paragraphFormats) * count($paragraphInterruptionCases)
                + count($fenceFormats) * count($fenceStyles) * count($fenceTabCases)
        );
    };

return $tests;
