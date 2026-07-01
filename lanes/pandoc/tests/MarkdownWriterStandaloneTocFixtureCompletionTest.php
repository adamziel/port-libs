<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$heading = static fn (int $level, string $value, array $attrs = []): AstNode => new AstNode(
    'heading',
    ['level' => $level] + $attrs,
    [$text($value)]
);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);

$collectNodes = null;
$collectNodes = static function (AstNode $node, string $type) use (&$collectNodes): array {
    $matches = [];
    if ($node->type === $type) {
        $matches[] = $node;
    }

    foreach ($node->children as $child) {
        array_push($matches, ...$collectNodes($child, $type));
    }

    return $matches;
};

$cases = [
    'basic nested standalone toc' => [
        'document' => $document([
            $heading(1, 'Intro'),
            $heading(2, 'Details'),
            $paragraph('Body.'),
        ]),
        'options' => ['standalone' => true, 'toc' => true],
        'expected' => implode("\n", [
            '- [Intro](#intro)',
            '  - [Details](#details)',
            '',
            '# Intro',
            '',
            '## Details',
            '',
            'Body.',
        ]),
        'urls' => ['#intro', '#details'],
    ],
    'numbered standalone toc' => [
        'document' => $document([
            $heading(1, 'Intro'),
            $heading(2, 'Details'),
        ]),
        'options' => ['standalone' => true, 'toc' => true, 'numberSections' => true],
        'expected' => implode("\n", [
            '- [1 Intro](#intro)',
            '  - [1.1 Details](#details)',
            '',
            '# Intro',
            '',
            '## Details',
        ]),
        'urls' => ['#intro', '#details'],
    ],
    'toc depth omits deeper headings' => [
        'document' => $document([
            $heading(1, 'Intro'),
            $heading(2, 'Details'),
            $heading(1, 'Next'),
        ]),
        'options' => ['standalone' => true, 'toc' => true, 'tocDepth' => 1],
        'expected' => implode("\n", [
            '- [Intro](#intro)',
            '- [Next](#next)',
            '',
            '# Intro',
            '',
            '## Details',
            '',
            '# Next',
        ]),
        'urls' => ['#intro', '#next'],
    ],
    'explicit heading identifier remains toc target only' => [
        'document' => $document([
            $heading(1, 'Intro', ['id' => 'intro-section']),
            $heading(2, 'Details', ['id' => 'details-section']),
        ]),
        'options' => ['standalone' => true, 'toc' => true],
        'expected' => implode("\n", [
            '- [Intro](#intro-section)',
            '  - [Details](#details-section)',
            '',
            '# Intro {#intro-section}',
            '',
            '## Details {#details-section}',
        ]),
        'urls' => ['#intro-section', '#details-section'],
    ],
];

$tests = [
    'records markdown writer standalone toc fixture completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(4, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer standalone toc fixture completion ' . $label] =
        static function (TestRunner $t) use ($case, $collectNodes): void {
            $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

            $t->same($case['expected'], $markdown);
            $t->true(!str_contains($markdown, '{#toc-'), 'Standalone TOC links should not carry synthetic link ids');

            $roundTrip = (new MarkdownReader())->read($markdown);
            $links = $collectNodes($roundTrip, 'link');
            $t->same($case['urls'], array_map(
                static fn (AstNode $node): string => (string) $node->attr('url', ''),
                array_slice($links, 0, count($case['urls']))
            ));
        };
}

return $tests;
