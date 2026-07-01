<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;

return [
    'expands table cell text separators in json native output' => static function (TestRunner $t): void {
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('table', [], [
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'Cell before']),
                            new AstNode('space'),
                            new AstNode('strong', [], [
                                new AstNode('text', ['text' => 'review']),
                            ]),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $packet = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $cellPayload = $packet['blocks'][0]['c'][4][0]['c'][3][0]['c'][1][0]['c'];
        $inlines = $cellPayload[4][0]['c'];
        $roundTrip = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR));
        $roundTripCell = $roundTrip->children[0]->children[0]->children[0]->children[0];

        $t->same(['Str', 'Space', 'Str', 'Space', 'Strong'], array_map(static fn (array $inline): string => $inline['t'], $inlines));
        $t->same('Cell', $inlines[0]['c']);
        $t->same('before', $inlines[2]['c']);
        $t->same('Cell before review', $roundTripCell->attr('text'));
    },
];
