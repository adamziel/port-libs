<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'flushes mixed caption and table cell inline runs through native writer blocks' => static function (TestRunner $t): void {
        $sourceTable = new AstNode('table', [
            'captionBlocks' => [
                new AstNode('text', ['text' => 'Lead']),
                new AstNode('space'),
                new AstNode('strong', [], [
                    new AstNode('text', ['text' => 'caption']),
                ]),
                new AstNode('bullet_list', [], [
                    new AstNode('list_item', [], [
                        new AstNode('text', ['text' => 'Nested']),
                        new AstNode('space'),
                        new AstNode('text', ['text' => 'caption']),
                    ]),
                ]),
                new AstNode('text', ['text' => 'Tail']),
                new AstNode('space'),
                new AstNode('emph', [], [
                    new AstNode('text', ['text' => 'caption']),
                ]),
            ],
        ], [
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [], [
                        new AstNode('text', ['text' => 'Cell']),
                        new AstNode('space'),
                        new AstNode('text', ['text' => 'intro']),
                        new AstNode('blockquote', [], [
                            new AstNode('paragraph', [], [
                                new AstNode('text', ['text' => 'Nested']),
                                new AstNode('space'),
                                new AstNode('text', ['text' => 'quote']),
                            ]),
                        ]),
                        new AstNode('text', ['text' => 'Cell']),
                        new AstNode('space'),
                        new AstNode('text', ['text' => 'outro']),
                    ]),
                ]),
            ]),
        ]);
        $document = new AstNode('document', [], [$sourceTable]);

        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativeText = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $nativeRoundTrip = (new NativeReader())->read($nativeText);
        $nativePacket = (new PandocJsonWriter())->toArray($nativeRoundTrip);
        $firstChildOfType = static function (AstNode $node, string $type): AstNode {
            foreach ($node->children as $child) {
                if ($child->type === $type) {
                    return $child;
                }
            }

            return new AstNode('missing');
        };

        $packets = [
            'json' => $jsonPacket,
            'native' => $nativePacket,
        ];

        foreach ($packets as $writer => $packet) {
            $captionBlocks = $packet['blocks'][0]['c'][1]['c'][1];
            $tableBody = $packet['blocks'][0]['c'][4][0]['c'] ?? $packet['blocks'][0]['c'][4][0];
            $tableRow = $tableBody[3][0]['c'] ?? $tableBody[3][0];
            $tableCell = $tableRow[1][0]['c'] ?? $tableRow[1][0];
            $cellBlocks = $tableCell[4];

            $t->same(['Plain', 'BulletList', 'Plain'], array_map(static fn (array $block): string => $block['t'], $captionBlocks), "{$writer} caption block constructors");
            $t->same('Lead', $captionBlocks[0]['c'][0]['c'], "{$writer} caption leading text");
            $t->same('Strong', $captionBlocks[0]['c'][2]['t'], "{$writer} caption leading inline formatting");
            $t->same('Tail', $captionBlocks[2]['c'][0]['c'], "{$writer} caption trailing text");
            $t->same('Emph', $captionBlocks[2]['c'][2]['t'], "{$writer} caption trailing inline formatting");
            $t->same(['Plain', 'BlockQuote', 'Plain'], array_map(static fn (array $block): string => $block['t'], $cellBlocks), "{$writer} cell block constructors");
            $t->same('Cell', $cellBlocks[0]['c'][0]['c'], "{$writer} cell leading text");
            $t->same('Nested', $cellBlocks[1]['c'][0]['c'][0]['c'], "{$writer} cell nested quote text");
            $t->same('Cell', $cellBlocks[2]['c'][0]['c'], "{$writer} cell trailing text");
        }

        $roundTripTable = $nativeRoundTrip->children[0];
        $captionBlocks = $roundTripTable->attr('captionBlocks');
        $roundTripBody = $firstChildOfType($roundTripTable, 'table_body');
        $roundTripRow = $firstChildOfType($roundTripBody, 'table_row');
        $roundTripCell = $firstChildOfType($roundTripRow, 'table_cell');

        $t->same(['plain', 'bullet_list', 'plain'], array_map(static fn (AstNode $block): string => $block->type, $captionBlocks), 'native reader keeps mixed caption block boundaries');
        $t->same(['plain', 'blockquote', 'plain'], array_map(static fn (AstNode $block): string => $block->type, $roundTripCell->children), 'native reader keeps mixed cell block boundaries');
        $t->same('Lead caption Nested caption Tail caption', $roundTripTable->attr('caption'), 'native reader preserves mixed caption text');
        $t->same('outro', $roundTripCell->children[2]->children[2]->attr('text'), 'native reader preserves trailing mixed cell text');
        $t->contains('Caption Nothing', $nativeText, 'native writer emits a native Caption constructor');
    },
];
