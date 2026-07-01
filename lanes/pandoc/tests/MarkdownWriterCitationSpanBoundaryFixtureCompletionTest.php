<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$span = static fn (array $attrs, array $children): AstNode => new AstNode('span', $attrs, $children);
$citation = static fn (array $attrs): AstNode => new AstNode('citation', $attrs);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);

$fixture = static fn (): string =>
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-citation-span-boundary.md');

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$cases = [
    'attributed span after author citation' => [
        'source' => '@foo [test]{.bar}',
        'document' => $document([
            $paragraph([
                $citation(['id' => 'foo', 'mode' => 'author_in_text']),
                $text(' '),
                $span(['classes' => ['bar']], [$text('test')]),
            ]),
        ]),
        'expected' => '@foo [test]{.bar}',
        'types' => ['citation', 'text', 'span'],
    ],
    'marked attributed span after author citation' => [
        'source' => '@foo [*marked* span]{#source .bar data-kind="span"}',
        'document' => $document([
            $paragraph([
                $citation(['id' => 'foo', 'mode' => 'author_in_text']),
                $text(' '),
                $span(
                    ['id' => 'source', 'classes' => ['bar'], 'attributes' => ['data-kind' => 'span']],
                    [$emph([$text('marked')]), $text(' span')]
                ),
            ]),
        ]),
        'expected' => '@foo [*marked* span]{#source .bar data-kind="span"}',
        'types' => ['citation', 'text', 'span'],
    ],
    'author citation suffix stays bracketed' => [
        'source' => '@foo [p. 7]',
        'document' => $document([
            $paragraph([
                $citation(['id' => 'foo', 'mode' => 'author_in_text', 'suffix' => [$text('p. 7')]]),
            ]),
        ]),
        'expected' => '@foo [p. 7]',
        'types' => ['citation'],
    ],
];

$tests = [
    'records markdown writer citation span boundary fixture mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(3, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer citation span boundary fixture ' . $label] =
        static function (TestRunner $t) use ($case, $fixture, $inlineTypes, $label): void {
            $markdown = (new MarkdownWriter())->write($case['document']);

            $t->contains($case['source'], $fixture(), $label . ' source fixture line');
            $t->same($case['expected'], $markdown, $label . ' markdown');

            $roundTrip = (new MarkdownReader())->read($markdown);
            $first = $roundTrip->children[0] ?? new AstNode('missing');
            $t->same($case['types'], $inlineTypes($first), $label . ' round-trip inline types');
            $t->same($case['expected'], (new MarkdownWriter())->write($roundTrip), $label . ' stable regeneration');
        };
}

$tests['renders citation locators separately from suffixes and escapes braced ids'] =
    static function (TestRunner $t) use ($citation, $document, $paragraph, $text): void {
        $markdown = (new MarkdownWriter())->write($document([
            $paragraph([
                $citation(['id' => 'roe2025', 'mode' => 'author_in_text', 'locator' => [$text('p. 9')]]),
                $text(' and '),
                $citation(['id' => 'doe 2026', 'locator' => [$text('p. 1|2')]]),
                $text(' and '),
                $citation(['id' => 'doe}2026']),
                $text(' and '),
                $citation(['id' => '']),
            ]),
        ]));

        $t->same('@roe2025, p. 9 and [@{doe 2026}, p. 1\\|2] and [@{doe\\}2026}] and [@{}]', $markdown);
    };

return $tests;
