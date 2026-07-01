<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves textual native nullary inline constructor provenance' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Para [ Str "Alpha", Space, SoftBreak, Str "Beta", LineBreak, Str "Gamma" ] ]
NATIVE;
        $expectedInlines = [
            ['t' => 'Str', 'c' => 'Alpha'],
            ['t' => 'Space'],
            ['t' => 'SoftBreak'],
            ['t' => 'Str', 'c' => 'Beta'],
            ['t' => 'LineBreak'],
            ['t' => 'Str', 'c' => 'Gamma'],
        ];

        $nativeDocument = (new NativeReader())->read($native);
        $paragraph = $nativeDocument->children[0];
        $children = $paragraph->children;
        $jsonDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $jsonPacket = (new PandocJsonWriter())->toArray($jsonDocument);
        $nativePacket = json_decode((new NativeWriter())->write($jsonDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same(['text', 'text', 'softbreak', 'text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $children));
        $t->same(['Space'], $children[1]->attr('nativeInlineConstructors'));
        $t->same([['t' => 'Space']], $children[1]->attr('nativeInlineParts'));
        $t->same('SoftBreak', $children[2]->attr('constructor'));
        $t->same(['t' => 'SoftBreak'], $children[2]->attr('native'));
        $t->same('LineBreak', $children[4]->attr('constructor'));
        $t->same(['t' => 'LineBreak'], $children[4]->attr('native'));
        $t->same($expectedInlines, $jsonPacket['blocks'][0]['c']);
        $t->same($expectedInlines, $nativePacket['blocks'][0]['c']);
        $t->same(
            '[ Para [ Str "Alpha" , Space , SoftBreak , Str "Beta" , LineBreak , Str "Gamma" ]' . "\n" . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($nativeDocument)
        );
    },
];
