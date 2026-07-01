<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;

return [
    'emits json-native for sidecar-free mixed block containers' => static function (TestRunner $t): void {
        $document = new AstNode('document', ['meta' => []], [
            new AstNode('blockquote', [], [
                new AstNode('text', ['text' => 'Quote lead']),
                new AstNode('space'),
                new AstNode('code_block', ['text' => 'wp post get 42']),
                new AstNode('text', ['text' => 'Quote tail']),
            ]),
            new AstNode('div', ['id' => 'mixed-div'], [
                new AstNode('text', ['text' => 'Div lead']),
                new AstNode('bullet_list', [], [
                    new AstNode('list_item', [], [
                        new AstNode('text', ['text' => 'Nested item']),
                    ]),
                ]),
                new AstNode('text', ['text' => 'Div tail']),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Footnote']),
                new AstNode('space'),
                new AstNode('note', [], [
                    new AstNode('text', ['text' => 'Note lead']),
                    new AstNode('blockquote', [], [
                        new AstNode('text', ['text' => 'Nested quote']),
                    ]),
                    new AstNode('text', ['text' => 'Note tail']),
                ]),
            ]),
        ]);

        $packet = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $quoteBlocks = $packet['blocks'][0]['c'];
        $divBlocks = $packet['blocks'][1]['c'][1];
        $noteInline = $packet['blocks'][2]['c'][2];
        $noteBlocks = $noteInline['c'];

        $t->same(['BlockQuote', 'Div', 'Para'], array_map(static fn (array $block): string => $block['t'], $packet['blocks']));
        $t->same(['Plain', 'CodeBlock', 'Plain'], array_map(static fn (array $block): string => $block['t'], $quoteBlocks));
        $t->same(['Plain', 'BulletList', 'Plain'], array_map(static fn (array $block): string => $block['t'], $divBlocks));
        $t->same(['Plain', 'BlockQuote', 'Plain'], array_map(static fn (array $block): string => $block['t'], $noteBlocks));
        $t->same('wp post get 42', $quoteBlocks[1]['c'][1]);
        $t->same('Nested item', $divBlocks[1]['c'][0][0]['c'][0]['c']);
        $t->same('Nested quote', $noteBlocks[1]['c'][0]['c'][0]['c']);

        $roundTrip = (new PandocJsonReader())->readPacket($packet);
        $roundTripNote = $roundTrip->children[2]->children[2];

        $t->same(['plain', 'code_block', 'plain'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children[0]->children));
        $t->same(['plain', 'bullet_list', 'plain'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children[1]->children));
        $t->same(['plain', 'blockquote', 'plain'], array_map(static fn (AstNode $node): string => $node->type, $roundTripNote->children));
    },
];
