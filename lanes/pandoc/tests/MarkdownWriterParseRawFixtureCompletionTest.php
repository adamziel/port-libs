<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$rawInline = static fn (string $format, string $value): AstNode => new AstNode('raw_inline', [
    'format' => $format,
    'text' => $value,
]);
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-command-parse-raw.md'
);

$cases = [
    'latex raw_tex emits raw attribute inline' => [
        'document' => $document([
            $paragraph([
                $emph([
                    $text('Hi '),
                    $rawInline('latex', '\\foo{there}'),
                ]),
            ]),
        ]),
        'options' => ['format' => 'markdown'],
        'expected' => '*Hi `\\foo{there}`{=latex}*',
    ],
    'latex without raw_tex omits raw inline' => [
        'document' => $document([
            $paragraph([
                $emph([
                    $text('Hi'),
                    $rawInline('latex', '\\foo{there}'),
                ]),
            ]),
        ]),
        'options' => [
            'format' => 'markdown',
            'rawAttribute' => false,
            'rawTex' => false,
        ],
        'expected' => '*Hi*',
    ],
    'html raw_html emits raw attribute inlines' => [
        'document' => $document([
            $paragraph([
                $emph([
                    $text('Hi '),
                    $rawInline('html', '<blink>'),
                    $text('there'),
                    $rawInline('html', '</blink>'),
                ]),
            ]),
        ]),
        'options' => ['format' => 'markdown'],
        'expected' => '*Hi `<blink>`{=html}there`</blink>`{=html}*',
    ],
    'html without raw_html omits raw inline boundaries' => [
        'document' => $document([
            $paragraph([
                $emph([
                    $text('Hi '),
                    $rawInline('html', '<blink>'),
                    $text('there'),
                    $rawInline('html', '</blink>'),
                ]),
            ]),
        ]),
        'options' => [
            'format' => 'markdown',
            'rawAttribute' => false,
            'rawHtml' => false,
        ],
        'expected' => '*Hi there*',
    ],
];

$tests = [];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer parse raw fixture completion ' . $label] =
        static function (TestRunner $t) use ($case, $fixture): void {
            $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

            $t->contains($case['expected'], $fixture(), 'Expected output is not present in upstream parse-raw fixture transcript');
            $t->same($case['expected'], $markdown);
        };
}

$tests['records markdown writer parse raw fixture completion mapped case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(4, count($cases));
    };

return $tests;
