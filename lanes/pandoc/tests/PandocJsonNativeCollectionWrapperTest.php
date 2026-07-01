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
];
