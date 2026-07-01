<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'accepts single wrapped multi-item list definition and line collections' => static function (TestRunner $t): void {
        $plain = static fn (string $text): array => ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => $text]]];
        $bulletItems = [
            [$plain('Bullet one')],
            [$plain('Bullet two')],
        ];
        $orderedItems = [
            [$plain('Ordered one')],
            [$plain('Ordered two')],
        ];
        $definitionItems = [
            [
                [['t' => 'Str', 'c' => 'Term one']],
                [[$plain('Definition one')]],
            ],
            [
                [['t' => 'Str', 'c' => 'Term two']],
                [[$plain('Definition two')]],
            ],
        ];
        $lines = [
            [
                ['t' => 'Str', 'c' => 'Line'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'one'],
            ],
            [
                ['t' => 'Str', 'c' => 'Line'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'two'],
            ],
        ];
        $blocks = [
            ['t' => 'BulletList', 'c' => [$bulletItems], 'reviewQueue' => 'wrapped-bullet-collection'],
            ['t' => 'OrderedList', 'c' => [
                [4, ['t' => 'Decimal'], ['t' => 'Period']],
                [$orderedItems],
            ], 'reviewQueue' => 'wrapped-ordered-collection'],
            ['t' => 'DefinitionList', 'c' => [$definitionItems], 'reviewQueue' => 'wrapped-definition-collection'],
            ['t' => 'LineBlock', 'c' => [$lines], 'reviewQueue' => 'wrapped-line-collection'],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => $blocks,
        ];
        $withoutNativeWrapper = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $bullet = $document->children[0];
            $ordered = $document->children[1];
            $definitionList = $document->children[2];
            $lineBlock = $document->children[3];
            $definitionItem = $definitionList->children[0];
            $line = $lineBlock->children[0];
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same(['bullet_list', 'ordered_list', 'definition_list', 'line_block'], array_map(static fn (AstNode $node): string => $node->type, $document->children), "{$source} block types");
            $t->same(2, count($bullet->children), "{$source} bullet item count");
            $t->same(2, count($ordered->children), "{$source} ordered item count");
            $t->same(2, count($definitionList->children), "{$source} definition item count");
            $t->same(2, count($lineBlock->children), "{$source} line count");
            $t->same('Bullet one', $bullet->children[0]->children[0]->attr('text'), "{$source} first bullet text");
            $t->same('Ordered two', $ordered->children[1]->children[0]->attr('text'), "{$source} second ordered text");
            $t->same('Term one', $definitionItem->children[0]->attr('text'), "{$source} first definition term");
            $t->same('Definition one', $definitionItem->children[1]->children[0]->attr('text'), "{$source} first definition body");
            $t->same('Line one', $line->attr('text'), "{$source} first line text");
            $t->same($bulletItems[0], $bullet->children[0]->attr('listItemNative'), "{$source} first bullet native item");
            $t->same($orderedItems[1], $ordered->children[1]->attr('listItemNative'), "{$source} second ordered native item");
            $t->same($definitionItems[0], $definitionItem->attr('definitionItemNative'), "{$source} first definition native item");
            $t->same($lines[0], $line->attr('lineNative'), "{$source} first line native payload");
            $t->same($blocks, $jsonPacket['blocks'], "{$source} json writer preserves original collection wrappers");
            $t->same($blocks, $nativePacket['blocks'], "{$source} native writer preserves original collection wrappers");

            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('bullet_list', $withoutNativeWrapper($bullet), $bullet->children),
                new AstNode('ordered_list', $withoutNativeWrapper($ordered), $ordered->children),
                new AstNode('definition_list', $withoutNativeWrapper($definitionList), $definitionList->children),
                new AstNode('line_block', $withoutNativeWrapper($lineBlock), $lineBlock->children),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($bulletItems, $encoded['blocks'][0]['c'], "{$source} {$writer} writer preserves split bullet item payloads");
                $t->same($orderedItems, $encoded['blocks'][1]['c'][1], "{$source} {$writer} writer preserves split ordered item payloads");
                $t->same($definitionItems, $encoded['blocks'][2]['c'], "{$source} {$writer} writer preserves split definition item payloads");
                $t->same($lines, $encoded['blocks'][3]['c'], "{$source} {$writer} writer preserves split line payloads");
            }
        }
    },
    'accepts single wrapped multi-item table column spec collections' => static function (TestRunner $t): void {
        $firstSpec = [
            ['t' => 'AlignLeft', 'reviewQueue' => 'first-align-source'],
            ['t' => 'ColWidth', 'c' => [0.45], 'reviewQueue' => 'first-width-source'],
        ];
        $secondSpec = [
            ['t' => 'AlignDefault', 'reviewQueue' => 'second-align-source'],
            ['t' => 'ColWidthDefault', 'reviewQueue' => 'second-width-source'],
        ];
        $columnSpecs = [$firstSpec, $secondSpec];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['wrapped-colspec-collection', ['json-native'], []],
                ['t' => 'Caption', 'c' => [['t' => 'Nothing'], []]],
                [$columnSpecs],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
            'reviewQueue' => 'wrapped-colspec-table-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];
        $withoutNativeWrapper = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $table = $document->children[0];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('table', array_replace($withoutNativeWrapper($table), [
                    'id' => 'rebuilt-wrapped-colspec-collection',
                ]), $table->children),
            ]);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('table', array_replace($withoutNativeWrapper($table), [
                    'widths' => [0.5, null],
                ]), $table->children),
            ]);

            $t->same(['left', 'default'], $table->attr('alignments'), "{$source} table column alignments");
            $t->same([0.45, null], $table->attr('widths'), "{$source} table column widths");
            $t->same([$columnSpecs], $table->attr('columnSpecsNative'), "{$source} records collection wrapper sidecar");
            $t->same([$firstSpec, $secondSpec], $table->attr('columnSpecNatives'), "{$source} records per-spec sidecars");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same([$columnSpecs], $encoded['blocks'][0]['c'][2], "{$source} {$writer} writer preserves unchanged collection wrapper");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same('rebuilt-wrapped-colspec-collection', $encoded['blocks'][0]['c'][0][0], "{$source} {$writer} writer rebuilds table attrs");
                $t->same([$columnSpecs], $encoded['blocks'][0]['c'][2], "{$source} {$writer} writer preserves rebuilt collection wrapper");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} {$writer} writer drops stale table wrapper sidecar");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $editedSpecs = $encoded['blocks'][0]['c'][2][0];

                $t->same($firstSpec[0], $editedSpecs[0][0], "{$source} {$writer} writer preserves edited-column alignment sidecar");
                $t->same(['t' => 'ColWidth', 'c' => 0.5], $editedSpecs[0][1], "{$source} {$writer} writer regenerates edited column width");
                $t->same($secondSpec, $editedSpecs[1], "{$source} {$writer} writer preserves untouched default spec");
            }
        }
    },
];
