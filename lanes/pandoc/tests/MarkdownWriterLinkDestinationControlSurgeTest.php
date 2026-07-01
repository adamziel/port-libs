<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$link = static fn (string $url, array $attrs = []): AstNode => new AstNode('link', ['url' => $url] + $attrs, [$text('packet')]);
$image = static fn (string $url, array $attrs = []): AstNode => new AstNode('image', ['url' => $url, 'alt' => 'packet'] + $attrs);
$control = static fn (int $byte): string => chr($byte);

$cases = [
    'commonmark link nul byte destination' => [
        'node' => $link('https://example.test/a' . $control(0x00) . 'b'),
        'options' => ['format' => 'commonmark'],
        'expected' => '[packet](https://example.test/a%00b)',
    ],
    'markdown link tab destination' => [
        'node' => $link('https://example.test/a' . $control(0x09) . 'b'),
        'options' => ['format' => 'markdown'],
        'expected' => '[packet](https://example.test/a%09b)',
    ],
    'gfm link lf destination' => [
        'node' => $link('https://example.test/a' . $control(0x0A) . 'b'),
        'options' => ['format' => 'gfm'],
        'expected' => '[packet](https://example.test/a%0Ab)',
    ],
    'commonmark link cr destination' => [
        'node' => $link('https://example.test/a' . $control(0x0D) . 'b'),
        'options' => ['format' => 'commonmark'],
        'expected' => '[packet](https://example.test/a%0Db)',
    ],
    'commonmark link form feed parenthesized destination' => [
        'node' => $link('/source' . $control(0x0C) . '(packet)'),
        'options' => ['format' => 'commonmark'],
        'expected' => '[packet](</source%0C(packet)>)',
    ],
    'gfm link unit separator spaced destination' => [
        'node' => $link('/source' . $control(0x1F) . ' packet'),
        'options' => ['format' => 'gfm'],
        'expected' => '[packet](</source%1F packet>)',
    ],
    'markdown link del byte destination' => [
        'node' => $link('https://example.test/a' . $control(0x7F) . 'b'),
        'options' => ['format' => 'markdown'],
        'expected' => '[packet](https://example.test/a%7Fb)',
    ],
    'commonmark image nul byte destination' => [
        'node' => $image('media/a' . $control(0x00) . 'b.png'),
        'options' => ['format' => 'commonmark'],
        'expected' => '![packet](media/a%00b.png)',
    ],
    'gfm image tab destination' => [
        'node' => $image('media/a' . $control(0x09) . 'b.png'),
        'options' => ['format' => 'gfm'],
        'expected' => '![packet](media/a%09b.png)',
    ],
    'markdown image lf destination' => [
        'node' => $image('media/a' . $control(0x0A) . 'b.png'),
        'options' => ['format' => 'markdown'],
        'expected' => '![packet](media/a%0Ab.png)',
    ],
    'commonmark reference link tab destination' => [
        'node' => $link('https://example.test/a' . $control(0x09) . 'b'),
        'options' => ['format' => 'commonmark', 'referenceLinks' => true],
        'expected' => "[packet]\n\n  [packet]: https://example.test/a%09b",
    ],
    'gfm reference image del destination' => [
        'node' => $image('media/a' . $control(0x7F) . 'b.png'),
        'options' => ['format' => 'gfm', 'referenceLinks' => true],
        'expected' => "![packet]\n\n  [packet]: media/a%7Fb.png",
    ],
    'markdown link lf title' => [
        'node' => $link('/source', ['title' => "Line\nTwo"]),
        'options' => ['format' => 'markdown'],
        'expected' => '[packet](/source "Line Two")',
    ],
    'gfm image tab and soh title' => [
        'node' => $image('media/source.png', ['title' => "A\tB" . $control(0x01) . 'C']),
        'options' => ['format' => 'gfm'],
        'expected' => '![packet](media/source.png "A B C")',
    ],
    'commonmark reference link cr title' => [
        'node' => $link('/source', ['title' => "Line\rTwo"]),
        'options' => ['format' => 'commonmark', 'referenceLinks' => true],
        'expected' => "[packet]\n\n  [packet]: /source \"Line Two\"",
    ],
    'markdown reference image del title' => [
        'node' => $image('media/source.png', ['title' => 'A' . $control(0x7F) . 'B']),
        'options' => ['format' => 'markdown', 'referenceLinks' => true],
        'expected' => "![packet]\n\n  [packet]: media/source.png \"A B\"",
    ],
];

$tests = [
    'records markdown writer link destination control surge mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(16, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer link destination control surge ' . $label] =
        static function (TestRunner $t) use ($case, $document, $paragraph): void {
            $markdown = (new MarkdownWriter($case['options']))->write($document([
                $paragraph([$case['node']]),
            ]));

            $t->same($case['expected'], $markdown);
            $t->true(
                preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $markdown) !== 1,
                'Rendered Markdown destination should not contain raw ASCII control bytes'
            );
        };
}

return $tests;
