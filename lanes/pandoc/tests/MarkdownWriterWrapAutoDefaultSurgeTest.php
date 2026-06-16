<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);

$plainLong = 'alpha beta gamma delta epsilon zeta eta theta iota kappa lambda mu nu xi omicron';
$medium = 'alpha beta gamma delta epsilon zeta eta theta';
$rawStart = str_repeat('alpha ', 12) . '<section>x';

$cases = [
    'markdown auto uses default writer columns' => [
        'children' => [$text($plainLong)],
        'options' => ['format' => 'markdown', 'wrap' => 'auto'],
        'expected' => "alpha beta gamma delta epsilon zeta eta theta iota kappa lambda mu nu xi\nomicron",
        'lineCount' => 2,
    ],
    'commonmark auto uses default writer columns' => [
        'children' => [$text($plainLong)],
        'options' => ['format' => 'commonmark', 'wrap' => 'auto'],
        'expected' => "alpha beta gamma delta epsilon zeta eta theta iota kappa lambda mu nu xi\nomicron",
        'lineCount' => 2,
    ],
    'gfm auto uses default writer columns' => [
        'children' => [$text($plainLong)],
        'options' => ['format' => 'gfm', 'wrap' => 'auto'],
        'expected' => "alpha beta gamma delta epsilon zeta eta theta iota kappa lambda mu nu xi\nomicron",
        'lineCount' => 2,
    ],
    'auto default escapes wrapped raw html line start' => [
        'children' => [$text($rawStart)],
        'options' => ['wrap' => 'auto'],
        'expected' => "alpha alpha alpha alpha alpha alpha alpha alpha alpha alpha alpha alpha\n&lt;section\\>x",
        'lineCount' => 2,
    ],
    'auto columns override default' => [
        'children' => [$text($medium)],
        'options' => ['wrap' => 'auto', 'columns' => 30],
        'expected' => "alpha beta gamma delta epsilon\nzeta eta theta",
        'lineCount' => 2,
    ],
    'auto wrapColumns override default' => [
        'children' => [$text($medium)],
        'options' => ['wrap' => 'auto', 'wrapColumns' => 26],
        'expected' => "alpha beta gamma delta\nepsilon zeta eta theta",
        'lineCount' => 2,
    ],
    'auto writerColumns override default' => [
        'children' => [$text($medium)],
        'options' => ['wrap' => 'auto', 'writerColumns' => 26],
        'expected' => "alpha beta gamma delta\nepsilon zeta eta theta",
        'lineCount' => 2,
    ],
    'auto lineWidth override default' => [
        'children' => [$text($medium)],
        'options' => ['wrap' => 'auto', 'lineWidth' => 26],
        'expected' => "alpha beta gamma delta\nepsilon zeta eta theta",
        'lineCount' => 2,
    ],
    'wrap none disables explicit columns' => [
        'children' => [$text($medium)],
        'options' => ['wrap' => 'none', 'columns' => 26],
        'expected' => $medium,
        'lineCount' => 1,
    ],
    'wrap preserve disables explicit columns' => [
        'children' => [$text($medium)],
        'options' => ['wrap' => 'preserve', 'columns' => 26],
        'expected' => $medium,
        'lineCount' => 1,
    ],
    'wrap nowrap disables explicit columns' => [
        'children' => [$text($medium)],
        'options' => ['wrap' => 'nowrap', 'columns' => 26],
        'expected' => $medium,
        'lineCount' => 1,
    ],
    'auto keeps non plain paragraph unwrapped' => [
        'children' => [$text('alpha beta gamma delta epsilon zeta eta theta '), $code('inline code')],
        'options' => ['wrap' => 'auto', 'columns' => 26],
        'expected' => 'alpha beta gamma delta epsilon zeta eta theta `inline code`',
        'lineCount' => 1,
    ],
];

$tests = [
    'records markdown writer wrap-auto default surge mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(12, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer wrap-auto default surge ' . $label] =
        static function (TestRunner $t) use ($case, $document, $paragraph): void {
            $markdown = (new MarkdownWriter($case['options']))->write($document([
                $paragraph($case['children']),
            ]));

            $t->same($case['expected'], $markdown);
            $t->same($case['lineCount'], count(explode("\n", $markdown)));
        };
}

return $tests;
