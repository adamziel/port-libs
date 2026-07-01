<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$link = static fn (string $url, string $label, string $title): AstNode => new AstNode(
    'link',
    ['url' => $url, 'title' => $title],
    [$text($label)]
);
$image = static fn (string $url, string $alt, string $title): AstNode => new AstNode(
    'image',
    ['url' => $url, 'alt' => $alt, 'title' => $title]
);

$findFirstNode = null;
$findFirstNode = static function (AstNode $node, string $type) use (&$findFirstNode): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirstNode($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

$cases = [
    'inline link newline title' => [
        'node' => $link('/packet', 'Packet', "First line\nsecond line"),
        'options' => ['format' => 'markdown'],
        'expected' => '[Packet](/packet "First line second line")',
        'type' => 'link',
        'title' => 'First line second line',
    ],
    'inline image tab and nul title' => [
        'node' => $image('media/packet.png', 'Packet', "First\tline\0second"),
        'options' => ['format' => 'commonmark'],
        'expected' => '![Packet](media/packet.png "First line second")',
        'type' => 'image',
        'title' => 'First line second',
    ],
    'reference link carriage return title' => [
        'node' => $link('/packet', 'Packet', "First\rsecond"),
        'options' => ['format' => 'gfm', 'referenceLinks' => true],
        'expected' => "[Packet]\n\n  [Packet]: /packet \"First second\"",
        'type' => 'link',
        'title' => 'First second',
    ],
    'reference image del title with escapes' => [
        'node' => $image('media/packet.png', 'Packet', "A\\B\x7F\"C\""),
        'options' => ['format' => 'markdown', 'referenceLinks' => true],
        'expected' => "![Packet]\n\n  [Packet]: media/packet.png \"A\\\\B \\\"C\\\"\"",
        'type' => 'image',
        'title' => 'A\\B "C"',
    ],
];

$tests = [
    'records markdown writer link title control completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(4, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer link title control completion ' . $label] =
        static function (TestRunner $t) use ($case, $document, $findFirstNode, $paragraph): void {
            $markdown = (new MarkdownWriter($case['options']))->write($document([
                $paragraph([$case['node']]),
            ]));
            $roundTrip = (new MarkdownReader())->read($markdown);
            $node = $findFirstNode($roundTrip, $case['type']);

            $t->same($case['expected'], $markdown);
            $t->same($case['type'], $node->type);
            $t->same($case['title'], $node->attr('title'));
            $t->true(
                preg_match('/[\x00-\x09\x0B-\x1F\x7F]/', $markdown) !== 1,
                'Rendered Markdown title should not contain raw ASCII control bytes other than structural line breaks'
            );
        };
}

return $tests;
