<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves textual native space as an inline constructor boundary' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Para [ Str "Alpha" , Space , Str "Beta" , SoftBreak , Str "Gamma" , LineBreak , Str "Delta" ] ]
NATIVE;

        $document = (new NativeReader())->read($native);
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $packet = (new PandocJsonWriter())->toArray($document);
        $roundTrip = (new PandocJsonReader())->readPacket($packet);
        $nativeText = (new NativeWriter(['blocksOnly' => true]))->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same('Alpha Beta Gamma Delta', $paragraph->attr('text'));
        $t->same(
            ['text', 'space', 'text', 'softbreak', 'text', 'linebreak', 'text'],
            array_map(static fn (AstNode $node): string => $node->type, $paragraph->children)
        );
        $t->same(
            [
                ['t' => 'Str', 'c' => 'Alpha'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'Beta'],
                ['t' => 'SoftBreak'],
                ['t' => 'Str', 'c' => 'Gamma'],
                ['t' => 'LineBreak'],
                ['t' => 'Str', 'c' => 'Delta'],
            ],
            $packet['blocks'][0]['c']
        );
        $t->same('space', $roundTrip->children[0]->children[1]->type);
        $t->contains('Str "Alpha" , Space , Str "Beta" , SoftBreak , Str "Gamma" , LineBreak , Str "Delta"', $nativeText);
    },
    'expands direct inline note text separators through native json output' => static function (TestRunner $t): void {
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Archive']),
                new AstNode('space'),
                new AstNode('note', [], [
                    new AstNode('text', ['text' => 'Review source']),
                ]),
            ]),
        ]);

        $packet = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $note = $packet['blocks'][0]['c'][2] ?? [];
        $noteBlock = is_array($note) ? ($note['c'][0] ?? []) : [];
        $roundTrip = (new PandocJsonReader())->readPacket($packet);
        $roundTripNote = $roundTrip->children[0]->children[2] ?? new AstNode('missing');
        $roundTripBlock = $roundTripNote->children[0] ?? new AstNode('missing');

        $t->same('Note', is_array($note) ? ($note['t'] ?? null) : null);
        $t->same('Plain', is_array($noteBlock) ? ($noteBlock['t'] ?? null) : null);
        $t->same([
            ['t' => 'Str', 'c' => 'Review'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'source'],
        ], is_array($noteBlock) ? ($noteBlock['c'] ?? null) : null);
        $t->same('note', $roundTripNote->type);
        $t->same('plain', $roundTripBlock->type);
        $t->same(['text', 'space', 'text'], array_map(static fn (AstNode $node): string => $node->type, $roundTripBlock->children));
    },
];
