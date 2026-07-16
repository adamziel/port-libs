<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$paragraphText = static function (array $options, string $markdown): string {
    $paragraph = (new MarkdownReader($options))->read($markdown)->children[0] ?? new AstNode('missing');

    return (string) $paragraph->attr('text', '');
};

$fixture = (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzz-angle-brackets-escapable-profile.md'
);

$enabledCases = [
    'strict plus angle brackets' => ['format' => 'markdown_strict+angle_brackets_escapable'],
    'php extra plus angle brackets' => ['format' => 'markdown_phpextra+angle_brackets_escapable'],
    'markdown all symbols disabled plus angle brackets' => ['format' => 'markdown-all_symbols_escapable+angle_brackets_escapable'],
    'strict extension option map' => ['format' => 'markdown_strict', 'extensions' => ['angle_brackets_escapable' => true]],
    'strict hyphenated extension option map' => ['format' => 'markdown_strict', 'extensions' => ['angle-brackets-escapable' => true]],
];

$disabledCases = [
    'strict default' => ['format' => 'markdown_strict'],
    'php extra default' => ['format' => 'markdown_phpextra'],
    'markdown all symbols disabled' => ['format' => 'markdown-all_symbols_escapable'],
];

return [
    'maps pandoc angle brackets escapable enabled profiles' =>
        static function (TestRunner $t) use ($enabledCases, $paragraphText): void {
            foreach ($enabledCases as $label => $options) {
                $t->same('<x>', $paragraphText($options, '\<x\>'), $label);
            }
        },

    'keeps pandoc angle brackets escapable disabled profiles on classic escapes' =>
        static function (TestRunner $t) use ($disabledCases, $paragraphText): void {
            foreach ($disabledCases as $label => $options) {
                $t->same('\<x>', $paragraphText($options, '\<x\>'), $label);
            }
        },

    'keeps pandoc angle brackets escapable bounded to angle punctuation' =>
        static function (TestRunner $t) use ($paragraphText): void {
            $t->same(
                '\@ <x> !',
                $paragraphText(['format' => 'markdown-all_symbols_escapable+angle_brackets_escapable'], '\@ \<x\> \!'),
            );
        },

    'maps pandoc angle brackets escapable checked-in profile fixture' =>
        static function (TestRunner $t) use ($fixture, $paragraphText): void {
            $t->same(
                '\@ <x> !',
                $paragraphText(['format' => 'markdown-all_symbols_escapable+angle_brackets_escapable'], $fixture),
            );
        },

    'records pandoc angle brackets escapable profile mapped-case count' =>
        static function (TestRunner $t) use ($enabledCases, $disabledCases): void {
            $t->same(10, count($enabledCases) + count($disabledCases) + 2);
        },
];
