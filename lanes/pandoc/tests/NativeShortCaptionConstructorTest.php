<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'reads current textual native ShortCaption constructors and writes them back' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Figure ("fig-current",[],[]) (Caption (Just (ShortCaption [Str "Queue", Space, Str "short"])) [Plain [Str "Long", Space, Str "caption"]]) [Plain [Str "Figure", Space, Str "body"]]
, Figure ("fig-legacy",[],[]) (Caption (Just [Str "Legacy", Space, Str "short"]) [Plain [Str "Legacy", Space, Str "caption"]]) [Plain [Str "Legacy", Space, Str "body"]]
]
NATIVE;

        $document = (new NativeReader())->read($native);
        $current = $document->children[0];
        $legacy = $document->children[1];
        $nativeText = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $roundTrip = (new NativeReader())->read($nativeText);
        $jsonPacket = (new PandocJsonWriter())->toArray(new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $document->children));

        $t->same('figure', $current->type);
        $t->same('Queue short', $current->attr('shortCaption'));
        $t->same('Long caption', $current->attr('caption'));
        $t->same(['text', 'text', 'text'], array_map(static fn (AstNode $node): string => $node->type, $current->attr('shortCaptionInlines')));
        $t->same('Legacy short', $legacy->attr('shortCaption'));
        $t->same('Legacy caption', $legacy->attr('caption'));

        $t->contains('(Just [ Str "Queue" , Space , Str "short" ])', $nativeText);
        $t->contains('(Just [ Str "Legacy" , Space , Str "short" ])', $nativeText);
        $t->same('Queue short', $roundTrip->children[0]->attr('shortCaption'));
        $t->same('Legacy short', $roundTrip->children[1]->attr('shortCaption'));

        $t->same('Caption', $jsonPacket['blocks'][0]['c'][1]['t']);
        $t->same('Just', $jsonPacket['blocks'][0]['c'][1]['c'][0]['t']);
        $t->same('ShortCaption', $jsonPacket['blocks'][0]['c'][1]['c'][0]['c']['t']);
        $t->same('Queue', $jsonPacket['blocks'][0]['c'][1]['c'][0]['c']['c'][0][0]['c']);
        $t->same('ShortCaption', $jsonPacket['blocks'][1]['c'][1]['c'][0]['c']['t']);
        $t->same('Legacy', $jsonPacket['blocks'][1]['c'][1]['c'][0]['c']['c'][0][0]['c']);
    },
];
