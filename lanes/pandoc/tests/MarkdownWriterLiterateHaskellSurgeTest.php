<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$paragraph = static fn (string $text): AstNode => new AstNode('paragraph', [], [new AstNode('text', ['text' => $text])]);
$codeBlock = static fn (string $text, array $attrs = []): AstNode => new AstNode(
    'code_block',
    array_replace(['text' => $text], $attrs)
);

$birdTrackMarkdown = static function (string $marker, string $text): string {
    $lines = [];
    foreach (explode("\n", $text) as $line) {
        $lines[] = $line === '' ? $marker : $marker . ' ' . $line;
    }

    return implode("\n", $lines);
};

$profiles = [
    'markdown plus lhs' => ['format' => 'markdown+lhs'],
    'pandoc plus lhs' => ['format' => 'pandoc+lhs'],
    'markdown configured lhs' => ['format' => 'markdown', 'extensions' => ['+lhs']],
    'markdown literate haskell alias' => ['format' => 'markdown+literate_haskell'],
    'markdown lhs fenced attributes disabled' => ['format' => 'markdown+lhs-fenced_code_attributes'],
];

$codeCases = [
    '01 bird one line class pair' => [
        'text' => 'main = pure ()',
        'attrs' => ['classes' => ['haskell', 'literate']],
        'marker' => '>',
    ],
    '02 bird sourceCode class pair' => [
        'text' => "render post = writeBlocks post\npublish = map render",
        'attrs' => ['classes' => ['sourceCode', 'haskell', 'literate']],
        'marker' => '>',
    ],
    '03 bird class string' => [
        'text' => 'reviewPacket = True',
        'attrs' => ['class' => 'haskell literate'],
        'marker' => '>',
    ],
    '04 bird info string' => [
        'text' => 'loadFixture = pure fixture',
        'attrs' => ['info' => 'haskell literate'],
        'marker' => '>',
    ],
    '05 bird lhs class alias' => [
        'text' => 'lhsAlias = "source"',
        'attrs' => ['classes' => ['lhs']],
        'marker' => '>',
    ],
    '06 bird literate haskell class alias' => [
        'text' => 'aliasStyle = "literate"',
        'attrs' => ['classes' => ['literate-haskell']],
        'marker' => '>',
    ],
    '07 inverse haskell class' => [
        'text' => 'plainHaskell = id',
        'attrs' => ['classes' => ['haskell']],
        'marker' => '<',
    ],
    '08 inverse haskell info' => [
        'text' => 'infoOnly = maybe False id',
        'attrs' => ['info' => 'haskell'],
        'marker' => '<',
    ],
    '09 inverse sourceCode haskell' => [
        'text' => "sourceCodeOnly x = x\nsourceCodeDone = True",
        'attrs' => ['classes' => ['sourceCode', 'haskell']],
        'marker' => '<',
    ],
    '10 inverse hs class alias' => [
        'text' => 'shortAlias = pure ()',
        'attrs' => ['classes' => ['hs']],
        'marker' => '<',
    ],
    '11 bird blank middle line' => [
        'text' => "firstLine = 1\n\nsecondLine = 2",
        'attrs' => ['classes' => ['haskell', 'literate']],
        'marker' => '>',
    ],
    '12 bird empty block' => [
        'text' => '',
        'attrs' => ['classes' => ['haskell', 'literate']],
        'marker' => '>',
    ],
    '13 bird leading spaces' => [
        'text' => "  indented = True\n    deeper = indented",
        'attrs' => ['classes' => ['haskell', 'literate']],
        'marker' => '>',
    ],
    '14 inverse comparison operators' => [
        'text' => 'compareBounds x y = x > y && y < x',
        'attrs' => ['classes' => ['haskell']],
        'marker' => '<',
    ],
    '15 bird unicode lambda source' => [
        'text' => "lambda = \xCE\xBBx -> x",
        'attrs' => ['classes' => ['haskell', 'literate']],
        'marker' => '>',
    ],
];

$tests = [];

foreach ($profiles as $profileLabel => $options) {
    foreach ($codeCases as $caseLabel => $case) {
        $tests['maps upstream markdown writer literate haskell bird tracks '
            . $profileLabel . ' ' . $caseLabel] =
            static function (TestRunner $t) use ($birdTrackMarkdown, $case, $codeBlock, $document, $options): void {
                $markdown = (new MarkdownWriter($options))->write($document([
                    $codeBlock($case['text'], $case['attrs']),
                ]));
                $roundTrip = (new MarkdownReader(['literateHaskell' => true]))->read($markdown);
                $node = $roundTrip->children[0] ?? new AstNode('missing');
                $expectedClasses = $case['marker'] === '>' ? ['haskell', 'literate'] : ['haskell'];

                $t->same($birdTrackMarkdown($case['marker'], $case['text']), $markdown);
                $t->true(!str_contains($markdown, '```'), 'LHS code should not remain fenced');
                $t->true(!str_contains($markdown, '{.'), 'LHS code should not leak fenced attributes');
                $t->same('code_block', $node->type);
                $t->same($expectedClasses, $node->attr('classes'));
                $t->same($case['text'], $node->attr('text'));
            };
    }
}

$tests['records markdown writer literate haskell surge mapped-case count'] =
    static function (TestRunner $t) use ($codeCases, $profiles): void {
        $t->same(75, count($codeCases) * count($profiles));
    };

$tests['keeps literate haskell bird track as blockquote without reader option'] =
    static function (TestRunner $t) use ($codeBlock, $document): void {
        $markdown = (new MarkdownWriter(['format' => 'markdown+lhs']))->write($document([
            $codeBlock('main = pure ()', ['classes' => ['haskell', 'literate']]),
        ]));
        $default = (new MarkdownReader())->read($markdown);

        $t->same('blockquote', $default->children[0]->type ?? 'missing');
    };

$tests['keeps rich attributed haskell code fenced under lhs format'] =
    static function (TestRunner $t) use ($codeBlock, $document): void {
        $markdown = (new MarkdownWriter(['format' => 'markdown+lhs']))->write($document([
            $codeBlock('main = pure ()', [
                'id' => 'snippet',
                'classes' => ['haskell', 'literate'],
                'attributes' => ['data-kind' => 'fixture'],
            ]),
        ]));

        $t->same("```{#snippet .haskell .literate data-kind=\"fixture\"}\nmain = pure ()\n```", $markdown);
    };

$tests['keeps explicit fenced code blocks fenced under lhs format'] =
    static function (TestRunner $t) use ($codeBlock, $document): void {
        $markdown = (new MarkdownWriter(['format' => 'markdown+lhs', 'fencedCodeBlocks' => true]))->write($document([
            $codeBlock('main = pure ()', ['classes' => ['haskell', 'literate']]),
        ]));

        $t->same("```{.haskell .literate}\nmain = pure ()\n```", $markdown);
    };

$tests['keeps disabled lhs extension fenced'] =
    static function (TestRunner $t) use ($codeBlock, $document): void {
        $markdown = (new MarkdownWriter(['format' => 'markdown-lhs']))->write($document([
            $codeBlock('main = pure ()', ['classes' => ['haskell', 'literate']]),
        ]));

        $t->same("```{.haskell .literate}\nmain = pure ()\n```", $markdown);
    };

$tests['allows explicit literateHaskell writer option without format suffix'] =
    static function (TestRunner $t) use ($codeBlock, $document): void {
        $markdown = (new MarkdownWriter(['literateHaskell' => true]))->write($document([
            $codeBlock('optionEnabled = True', ['classes' => ['haskell', 'literate']]),
        ]));

        $t->same('> optionEnabled = True', $markdown);
    };

$tests['keeps prose boundaries around lhs code blocks'] =
    static function (TestRunner $t) use ($codeBlock, $document, $paragraph): void {
        $markdown = (new MarkdownWriter(['format' => 'markdown+lhs']))->write($document([
            $paragraph('Before'),
            $codeBlock('main = pure ()', ['classes' => ['haskell', 'literate']]),
            $paragraph('After'),
        ]));

        $t->same("Before\n\n> main = pure ()\n\nAfter", $markdown);
    };

return $tests;
