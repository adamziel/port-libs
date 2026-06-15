<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$link = static fn (array $attrs, array $children): AstNode => new AstNode('link', $attrs, $children);
$image = static fn (array $attrs, array $children = []): AstNode => new AstNode('image', $attrs, $children);
$escapedTail = static fn (string $tail): string => '\\' . str_replace('"', '\\"', $tail);

$previousCases = [
    'plain reference link' => [
        'node' => $link(['url' => '/source'], [$text('Source')]),
        'prefix' => '[Source][]',
        'definition' => '  [Source]: /source',
        'roundTripType' => 'link',
    ],
    'titled reference link' => [
        'node' => $link(['url' => '/source', 'title' => 'Source title'], [$text('Source')]),
        'prefix' => '[Source][]',
        'definition' => '  [Source]: /source "Source title"',
        'roundTripType' => 'link',
    ],
    'attributed reference link' => [
        'node' => $link([
            'url' => '/source',
            'id' => 'source-link',
            'classes' => ['tracked'],
            'attributes' => ['data-id' => '42'],
        ], [$text('Source')]),
        'prefix' => '[Source][]',
        'definition' => '  [Source]: /source {#source-link .tracked data-id="42"}',
        'roundTripType' => 'link',
    ],
    'softbreak label reference link' => [
        'node' => $link(['url' => '/source-packet'], [$text('Source'), new AstNode('softbreak'), $text('Packet')]),
        'prefix' => "[Source\nPacket][]",
        'definition' => '  [Source Packet]: /source-packet',
        'roundTripType' => 'link',
    ],
    'spaced destination reference link' => [
        'node' => $link(['url' => '/source packet'], [$text('Packet')]),
        'prefix' => '[Packet][]',
        'definition' => '  [Packet]: </source packet>',
        'roundTripType' => 'link',
    ],
    'plain reference image' => [
        'node' => $image(['url' => 'media/diagram.png', 'alt' => 'Diagram']),
        'prefix' => '![Diagram][]',
        'definition' => '  [Diagram]: media/diagram.png',
        'roundTripType' => 'image',
    ],
    'titled reference image' => [
        'node' => $image(['url' => 'media/diagram.png', 'alt' => 'Diagram', 'title' => 'Diagram title']),
        'prefix' => '![Diagram][]',
        'definition' => '  [Diagram]: media/diagram.png "Diagram title"',
        'roundTripType' => 'image',
    ],
    'attributed reference image' => [
        'node' => $image([
            'url' => 'media/diagram.png',
            'alt' => 'Diagram',
            'id' => 'diagram',
            'classes' => ['asset'],
            'attributes' => ['data-id' => '9'],
        ]),
        'prefix' => '![Diagram][]',
        'definition' => '  [Diagram]: media/diagram.png {#diagram .asset data-id="9"}',
        'roundTripType' => 'image',
    ],
    'empty label reference image' => [
        'node' => $image(['url' => 'media/empty.png']),
        'prefix' => '![][1]',
        'definition' => '  [1]: media/empty.png',
        'roundTripType' => 'image',
    ],
    'caption alt reference image' => [
        'node' => $image(['url' => 'media/caption.png', 'alt' => 'Plain alt'], [$text('Caption')]),
        'prefix' => '![Caption][]',
        'definition' => '  [Caption]: media/caption.png {alt="Plain alt"}',
        'roundTripType' => 'image',
    ],
    'spaced destination reference image' => [
        'node' => $image(['url' => 'media/source packet.png', 'alt' => 'Packet']),
        'prefix' => '![Packet][]',
        'definition' => '  [Packet]: <media/source packet.png>',
        'roundTripType' => 'image',
    ],
];

$attributeTails = [
    'id tail' => '{#tail}',
    'class tail' => '{.tail}',
    'key value tail' => '{data-kind="tail"}',
    'compound tail' => '{#tail .class data-id="7"}',
    'double brace literal tail' => '{{literal}}',
];

$cases = [];
foreach ($previousCases as $previousName => $previous) {
    foreach ($attributeTails as $tailName => $tail) {
        $cases[$previousName . ' before ' . $tailName] = [
            'document' => $document([$paragraph([$previous['node'], $text($tail)])]),
            'expected' => $previous['prefix'] . $escapedTail($tail) . "\n\n" . $previous['definition'],
            'roundTripType' => $previous['roundTripType'],
            'tail' => $tail,
        ];
    }
}

$tests = [
    'records markdown writer reference attribute brace surge mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(55, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer reference attribute brace surge ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter(['referenceLinks' => true]))->write($case['document']);

            $t->same($case['expected'], $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $t->same(
                [$case['roundTripType'], 'text'],
                array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children[0]->children)
            );
            $t->same($case['tail'], $roundTrip->children[0]->children[1]->attr('text'));
        };
}

return $tests;
