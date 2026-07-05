<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlReader;
use PortLibs\Pandoc\HtmlWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (string $text): AstNode => new AstNode('paragraph', [], [
    new AstNode('text', ['text' => $text]),
]);

$roundTripComparable = null;
$roundTripComparable = static function (AstNode $node) use (&$roundTripComparable, $text): AstNode {
    return match ($node->type) {
        'code_block' => new AstNode('paragraph', [], [$text('code block was here')]),
        'line_block' => new AstNode('paragraph', [], [$text('line block was here')]),
        'raw_block', 'raw_html', 'raw_tex', 'raw_markdown' => new AstNode('paragraph', [], [$text('raw block was here')]),
        'table' => new AstNode('paragraph', [], [$text('table block was here')]),
        'raw_inline', 'raw_html_inline', 'raw_tex_inline' => $text('raw inline was here'),
        'div' => new AstNode(
            'div',
            $node->attrs,
            array_values(array_filter(
                array_map($roundTripComparable, $node->children),
                static fn (AstNode $child): bool => $child->type !== 'heading'
            ))
        ),
        default => new AstNode($node->type, $node->attrs, array_map($roundTripComparable, $node->children)),
    };
};

$astData = null;
$normalizedValue = null;
$normalizedValue = static function (mixed $value) use (&$astData, &$normalizedValue): mixed {
    if ($value instanceof AstNode) {
        return $astData($value);
    }

    if (!is_array($value)) {
        return $value;
    }

    $normalized = [];
    foreach ($value as $key => $item) {
        $normalized[$key] = $normalizedValue($item);
    }
    if (!array_is_list($normalized)) {
        ksort($normalized);
    }

    return $normalized;
};
$astData = static function (AstNode $node) use (&$astData, &$normalizedValue): array {
    $attrs = [];
    foreach ($node->attrs as $key => $value) {
        $attrs[$key] = $normalizedValue($value);
    }
    ksort($attrs);

    return [
        'type' => $node->type,
        'attrs' => $attrs,
        'children' => array_map($astData, $node->children),
    ];
};

return [
    'covers Tests.Readers.HTML QuickCheck property Round trip' => static function (TestRunner $t) use (
        $astData,
        $paragraph,
        $roundTripComparable,
        $text
    ): void {
        $reader = new HtmlReader();
        $writer = new HtmlWriter(['writerWrapText' => 'WrapPreserve']);
        $rewrite = static fn (AstNode $document): AstNode => $reader->read($writer->write($document) . "\n");

        // Mirrors upstream Tests.Readers.HTML roundTrip: d'' must equal d'''.
        $documents = [
            'block and inline constructors' => new AstNode('document', [], [
                new AstNode('heading', ['level' => 2], [$text('Heading')]),
                new AstNode('paragraph', [], [
                    $text('Alpha'),
                    new AstNode('softbreak'),
                    $text('Beta '),
                    new AstNode('strong', [], [$text('strong')]),
                    $text(' '),
                    new AstNode('emph', [], [$text('em')]),
                    new AstNode('linebreak'),
                    $text('after break'),
                ]),
                new AstNode('blockquote', [], [$paragraph('quoted body')]),
                new AstNode('bullet_list', [], [
                    new AstNode('list_item', [], [new AstNode('plain', [], [$text('one')])]),
                    new AstNode('list_item', [], [$paragraph('two')]),
                ]),
                new AstNode('ordered_list', ['start' => 3, 'style' => 'lower_alpha'], [
                    new AstNode('list_item', [], [new AstNode('plain', [], [$text('three')])]),
                ]),
                new AstNode('definition_list', [], [
                    new AstNode('definition_item', [], [
                        new AstNode('term', [], [$text('term')]),
                        new AstNode('definition', [], [$paragraph('definition')]),
                    ]),
                ]),
                new AstNode('horizontal_rule'),
            ]),
            'links images math and citations' => new AstNode('document', [], [
                new AstNode('paragraph', [], [
                    new AstNode('link', ['url' => 'https://example.test', 'title' => 'Example'], [$text('link')]),
                    $text(' '),
                    new AstNode('image', ['url' => 'img.png', 'title' => 'Image title', 'alt' => 'Alt text'], [$text('Alt text')]),
                    $text(' '),
                    new AstNode('math', ['text' => 'x^2', 'display' => false]),
                    $text(' '),
                    new AstNode('citation', ['citations' => [['id' => 'doe2024']]], [$text('Doe')]),
                ]),
            ]),
            'native divs and upstream pre-rewrite substitutions' => new AstNode('document', [], [
                new AstNode('div', ['classes' => ['section']], [
                    new AstNode('heading', ['level' => 3], [$text('removed inside div')]),
                    new AstNode('paragraph', [], [
                        $text('kept '),
                        new AstNode('span', ['classes' => ['mark']], [$text('marked')]),
                        $text(' '),
                        new AstNode('raw_inline', ['format' => 'html', 'text' => '<br>']),
                    ]),
                ]),
                new AstNode('code_block', ['text' => "echo 1;\n"]),
                new AstNode('line_block', [], [
                    new AstNode('line', [], [$text('first')]),
                    new AstNode('line', [], [$text('second')]),
                ]),
                new AstNode('raw_html', ['format' => 'html', 'html' => '<aside>raw</aside>']),
                new AstNode('table'),
            ]),
            'inline notes' => new AstNode('document', [], [
                new AstNode('paragraph', [], [
                    $text('with note'),
                    new AstNode('note', [], [$paragraph('note body')]),
                ]),
            ]),
        ];

        foreach ($documents as $name => $document) {
            $d = $roundTripComparable($document);
            $first = $rewrite($d);
            $second = $rewrite($first);
            $third = $rewrite($second);

            $t->same($astData($second), $astData($third), "{$name} AST must be stable after the second HTML rewrite");
            $t->same(
                $writer->write($second) . "\n",
                $writer->write($third) . "\n",
                "{$name} HTML must be stable after the second HTML rewrite"
            );
        }
    },
];
