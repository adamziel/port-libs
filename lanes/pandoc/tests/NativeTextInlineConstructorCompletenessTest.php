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
];
