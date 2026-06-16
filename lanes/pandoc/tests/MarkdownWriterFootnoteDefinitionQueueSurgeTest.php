<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$note = static fn (array $blocks, array $attrs = []): AstNode => new AstNode('note', $attrs, $blocks);
$link = static fn (string $url, string $label, array $attrs = []): AstNode => new AstNode(
    'link',
    ['url' => $url] + $attrs,
    [$text($label)]
);
$abbr = static fn (string $term, string $title): AstNode => new AstNode(
    'span',
    ['classes' => ['abbr'], 'attributes' => ['title' => $title]],
    [$text($term)]
);

$cases = [];

for ($i = 1; $i <= 30; $i++) {
    $label = "Inside {$i}";
    $attrs = [];
    $definitionSuffix = '';
    if ($i > 10 && $i <= 20) {
        $attrs['title'] = "Title {$i}";
        $definitionSuffix = " \"Title {$i}\"";
    } elseif ($i > 20) {
        $attrs = [
            'id' => "inside-{$i}",
            'classes' => ['tracked'],
            'attributes' => ['data-id' => (string) $i],
        ];
        $definitionSuffix = " {#inside-{$i} .tracked data-id=\"{$i}\"}";
    }

    $cases["scopes reference link definition inside footnote body {$i}"] = [
        'document' => $document([
            $paragraph([
                $text("main {$i}"),
                $note([
                    $paragraph([
                        $text('inner '),
                        $link("/inside-{$i}", $label, $attrs),
                    ]),
                ]),
            ]),
        ]),
        'expected' => "main {$i}[^1]\n\n"
            . "[^1]: inner [{$label}]\n\n"
            . "      [{$label}]: /inside-{$i}{$definitionSuffix}",
        'options' => ['referenceLinks' => true],
    ];
}

for ($i = 1; $i <= 15; $i++) {
    $innerAttrs = $i > 5 ? ['label' => "inner-{$i}"] : [];
    $innerLabel = $i > 5 ? "inner-{$i}" : '2';

    $cases["scopes nested footnote definition inside parent footnote {$i}"] = [
        'document' => $document([
            $paragraph([
                $text("main {$i}"),
                $note([
                    $paragraph([
                        $text('outer '),
                        $note([
                            $paragraph([$text("inner {$i}")]),
                        ], $innerAttrs),
                    ]),
                ]),
            ]),
        ]),
        'expected' => "main {$i}[^1]\n\n"
            . "[^1]: outer [^{$innerLabel}]\n\n"
            . "    [^{$innerLabel}]: inner {$i}",
    ];
}

for ($i = 1; $i <= 15; $i++) {
    $term = "API{$i}";
    $title = "Application Programming Interface {$i}";

    $cases["scopes abbreviation definition inside footnote body {$i}"] = [
        'document' => $document([
            $paragraph([
                $text("main {$i}"),
                $note([
                    $paragraph([
                        $text('uses '),
                        $abbr($term, $title),
                    ]),
                ]),
            ]),
        ]),
        'expected' => "main {$i}[^1]\n\n"
            . "[^1]: uses {$term}\n\n"
            . "    *[{$term}]: {$title}",
    ];
}

for ($i = 1; $i <= 10; $i++) {
    $location = $i > 7 ? 'end_of_section' : ($i > 4 ? 'end_of_document' : 'end_of_block');
    $blocks = [
        $paragraph([
            $link("/outer-{$i}", "Outer {$i}"),
            $text(' note '),
            $note([
                $paragraph([
                    $text('nested '),
                    $link("/inner-{$i}", "Inner {$i}"),
                ]),
            ]),
        ]),
    ];
    $expected = "[Outer {$i}] note [^1]\n\n"
        . "[^1]: nested [Inner {$i}]\n\n"
        . "      [Inner {$i}]: /inner-{$i}\n\n"
        . "  [Outer {$i}]: /outer-{$i}";

    if ($location === 'end_of_section') {
        $blocks[] = new AstNode('heading', ['level' => 1], [$text("Next {$i}")]);
        $expected .= "\n\n# Next {$i}";
    }

    $cases["keeps document reference location outside footnote-local definitions {$i}"] = [
        'document' => $document($blocks),
        'expected' => $expected,
        'options' => ['referenceLinks' => true, 'referenceLocation' => $location],
    ];
}

for ($i = 1; $i <= 5; $i++) {
    $term = "SDK{$i}";
    $title = "Software Development Kit {$i}";

    $cases["recursively scopes footnote child definitions {$i}"] = [
        'document' => $document([
            $paragraph([
                $text("main {$i}"),
                $note([
                    $paragraph([
                        $text('outer '),
                        $note([
                            $paragraph([
                                $text('inner '),
                                $link("/deep-{$i}", "Deep {$i}"),
                                $text(' '),
                                $abbr($term, $title),
                            ]),
                        ]),
                    ]),
                ]),
            ]),
        ]),
        'expected' => "main {$i}[^1]\n\n"
            . "[^1]: outer [^2]\n\n"
            . "    [^2]: inner [Deep {$i}] {$term}\n\n"
            . "          [Deep {$i}]: /deep-{$i}\n\n"
            . "        *[{$term}]: {$title}",
        'options' => ['referenceLinks' => true],
    ];
}

$tests = [
    'records markdown writer footnote definition queue surge mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(75, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer footnote definition queue surge ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter($case['options'] ?? []))->write($case['document']);

            $t->same($case['expected'], $markdown);
        };
}

return $tests;
