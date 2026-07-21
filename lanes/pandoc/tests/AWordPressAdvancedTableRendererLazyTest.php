<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;

$sourceProvenance = static fn (string $id, int $line): array => [
    'sourceNodeId' => $id,
    'sourceLineIds' => [$id . '-line-' . $line],
    'sourceLineEdges' => [[
        'sourceLineId' => $id . '-line-' . $line,
        'startByte' => 0,
        'endByte' => 1,
    ]],
];
$cell = static fn (string $value, int $line): AstNode => new AstNode(
    'table_cell',
    ['text' => $value] + $sourceProvenance('tracemonkey-table', $line),
    [new AstNode('plain', ['text' => $value], [new AstNode('text', ['text' => $value])])]
);
$row = static fn (array $values, int $firstLine): AstNode => new AstNode(
    'table_row',
    [],
    array_map(
        static fn (string $value, int $offset): AstNode => $cell($value, $firstLine + $offset),
        $values,
        array_keys($values)
    )
);

return [
    'strict TraceMonkey table stays on the core writer fast path' => static function (
        TestRunner $t
    ) use ($sourceProvenance, $row): void {
        $rendererClass = 'PortLibs\\Pandoc\\WordPressAdvancedTableRenderer';
        $t->same(false, class_exists($rendererClass, false));

        $table = new AstNode(
            'table',
            $sourceProvenance('tracemonkey-table', 1),
            [
                new AstNode('table_head', [], [
                    $row(['Tag', 'JS Type', 'Description'], 2),
                ]),
                new AstNode('table_body', [], [
                    $row(['xx1', 'number', '31-bit integer representation'], 5),
                    $row(['000', 'object', 'pointer to JSObject handle'], 8),
                    $row(['010', 'number', 'pointer to double handle'], 11),
                    $row(['100', 'string', 'pointer to JSString handle'], 14),
                    $row(['110', 'boolean', 'enumeration for null, undefined, true, false null, or undefined'], 17),
                ]),
            ]
        );

        $expected = '<!-- wp:table -->' . "\n"
            . '<figure class="wp-block-table"><table><thead><tr><th>Tag</th><th>JS Type</th><th>Description</th></tr></thead>'
            . '<tbody><tr><td>xx1</td><td>number</td><td>31-bit integer representation</td></tr>'
            . '<tr><td>000</td><td>object</td><td>pointer to JSObject handle</td></tr>'
            . '<tr><td>010</td><td>number</td><td>pointer to double handle</td></tr>'
            . '<tr><td>100</td><td>string</td><td>pointer to JSString handle</td></tr>'
            . '<tr><td>110</td><td>boolean</td><td>enumeration for null, undefined, true, false null, or undefined</td></tr>'
            . '</tbody></table></figure>' . "\n"
            . '<!-- /wp:table -->';
        $actual = (new WordPressBlockWriter())->write(new AstNode('document', [], [$table]));

        $t->same($expected, $actual);
        $t->same(
            '89edd6ed0988bc72ad7c98a6df0465e6f2e83735e550e1048018bbfaf18f84ea',
            hash('sha256', $actual)
        );
        $t->same(false, class_exists($rendererClass, false));
    },
];
