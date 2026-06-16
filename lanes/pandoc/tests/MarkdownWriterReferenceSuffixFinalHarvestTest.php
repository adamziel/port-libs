<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$linebreak = static fn (): AstNode => new AstNode('linebreak');
$rawMarkdown = static fn (string $value): AstNode => new AstNode('raw_markdown', [
    'format' => 'markdown',
    'text' => $value,
    'markdown' => $value,
]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$link = static fn (array $attrs, array $children): AstNode => new AstNode('link', $attrs, $children);
$image = static fn (array $attrs, array $children = []): AstNode => new AstNode('image', $attrs, $children);

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

$subjects = [
    'markdown link plain destination' => [
        'node' => $link(['url' => '/source'], [$text('Source')]),
        'prefix' => '[Source][]',
        'definition' => '  [Source]: /source',
        'type' => 'link',
        'url' => '/source',
        'options' => ['format' => 'markdown'],
    ],
    'markdown link spaced destination title' => [
        'node' => $link(['url' => '/source packet', 'title' => 'Packet title'], [$text('Source')]),
        'prefix' => '[Source][]',
        'definition' => '  [Source]: </source packet> "Packet title"',
        'type' => 'link',
        'url' => '/source packet',
        'roundTripUrl' => '/source%20packet',
        'options' => ['format' => 'markdown'],
    ],
    'commonmark link quoted title' => [
        'node' => $link(['url' => '/commonmark-source', 'title' => 'CommonMark "quoted" title'], [$text('Source')]),
        'prefix' => '[Source][]',
        'definition' => '  [Source]: /commonmark-source "CommonMark \"quoted\" title"',
        'type' => 'link',
        'url' => '/commonmark-source',
        'options' => ['format' => 'commonmark'],
    ],
    'gfm link spaced destination quoted title' => [
        'node' => $link(['url' => '/gfm source', 'title' => 'GFM "quoted" title'], [$text('Source')]),
        'prefix' => '[Source][]',
        'definition' => '  [Source]: </gfm source> "GFM \"quoted\" title"',
        'type' => 'link',
        'url' => '/gfm source',
        'roundTripUrl' => '/gfm%20source',
        'options' => ['format' => 'gfm'],
    ],
    'commonmark image title' => [
        'node' => $image(['url' => 'media/commonmark.png', 'alt' => 'Source', 'title' => 'Image title']),
        'prefix' => '![Source][]',
        'definition' => '  [Source]: media/commonmark.png "Image title"',
        'type' => 'image',
        'url' => 'media/commonmark.png',
        'options' => ['format' => 'commonmark'],
    ],
    'gfm image spaced destination quoted title' => [
        'node' => $image(['url' => 'media/gfm source.png', 'alt' => 'Source', 'title' => 'GFM "image" title']),
        'prefix' => '![Source][]',
        'definition' => '  [Source]: <media/gfm source.png> "GFM \"image\" title"',
        'type' => 'image',
        'url' => 'media/gfm source.png',
        'roundTripUrl' => 'media/gfm%20source.png',
        'options' => ['format' => 'gfm'],
    ],
];

$tails = [
    'space attribute text' => [
        'nodes' => [$text(' {#tail}')],
        'markdown' => ' {#tail}',
    ],
    'tab attribute text' => [
        'nodes' => [$text("\t{#tail}")],
        'markdown' => '&#9;{#tail}',
    ],
    'space parenthesized text' => [
        'nodes' => [$text(' (tail)')],
        'markdown' => ' (tail)',
    ],
    'space definition text' => [
        'nodes' => [$text(' : tail')],
        'markdown' => ' : tail',
    ],
    'softbreak attribute text' => [
        'nodes' => [$softbreak(), $text('{#tail}')],
        'markdown' => "\n{#tail}",
    ],
    'softbreak parenthesized text' => [
        'nodes' => [$softbreak(), $text('(tail)')],
        'markdown' => "\n(tail)",
    ],
    'softbreak definition text' => [
        'nodes' => [$softbreak(), $text(': tail')],
        'markdown' => "\n\\: tail",
    ],
    'linebreak attribute text' => [
        'nodes' => [$linebreak(), $text('{#tail}')],
        'markdown' => "\\\n{#tail}",
    ],
    'linebreak parenthesized text' => [
        'nodes' => [$linebreak(), $text('(tail)')],
        'markdown' => "\\\n(tail)",
    ],
    'linebreak definition text' => [
        'nodes' => [$linebreak(), $text(': tail')],
        'markdown' => "\\\n\\: tail",
    ],
    'raw markdown attribute suffix' => [
        'nodes' => [$rawMarkdown(' {#tail}')],
        'markdown' => ' {#tail}',
    ],
    'raw markdown parenthesized suffix' => [
        'nodes' => [$rawMarkdown(' (tail)')],
        'markdown' => ' (tail)',
    ],
    'raw markdown definition suffix' => [
        'nodes' => [$rawMarkdown(' : tail')],
        'markdown' => ' : tail',
    ],
];

$cases = [];
foreach ($subjects as $subjectName => $subject) {
    foreach ($tails as $tailName => $tail) {
        $cases[$subjectName . ' before ' . $tailName] = [
            'document' => $document([$paragraph(array_merge([$subject['node']], $tail['nodes']))]),
            'expected' => $subject['prefix'] . $tail['markdown'] . "\n\n" . $subject['definition'],
            'options' => ['referenceLinks' => true] + $subject['options'],
            'type' => $subject['type'],
            'url' => $subject['roundTripUrl'] ?? $subject['url'],
        ];
    }
}

$tests = [
    'records markdown writer reference suffix final harvest mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(78, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer reference suffix final harvest ' . $label] =
        static function (TestRunner $t) use ($case, $collectNodes): void {
            $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

            $t->same($case['expected'], $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $nodes = $collectNodes($roundTrip, $case['type']);
            $node = $nodes[0] ?? new AstNode('missing');
            $t->same($case['type'], $node->type);
            $t->same($case['url'], $node->attr('url'));
        };
}

return $tests;
