<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'round trips text native table short caption constructors' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Table ( "native-text-table" , [ "caption-slice" ] , [ ( "data-source" , "native-text" ) ] )
    (Caption (Just (ShortCaption [ Str "Short" , Space , Strong [ Str "queue" ] ]))
      [ Para [ Str "Long" , Space , Emph [ Str "caption" ] ] ])
    [ ( AlignLeft , ColWidth 0.5 ) ]
    (TableHead ( "" , [  ] , [  ] ) [  ])
    [ TableBody ( "" , [  ] , [  ] ) (RowHeadColumns 0) [  ]
      [ Row ( "" , [  ] , [  ] )
        [ Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1)
          [ Plain [ Str "Cell" ] ] ] ] ]
    (TableFoot ( "" , [  ] , [  ] ) [  ])
]
NATIVE;

        $legacyNative = str_replace(
            '(Just (ShortCaption [ Str "Short" , Space , Strong [ Str "queue" ] ]))',
            '(Just [ Str "Short" , Space , Strong [ Str "queue" ] ])',
            $native
        );

        foreach (['current' => $native, 'legacy' => $legacyNative] as $label => $source) {
            $document = (new NativeReader())->read($source);
            $table = $document->children[0];
            $shortCaptionInlines = $table->attr('shortCaptionInlines');
            $nativeText = (new NativeWriter(['blocksOnly' => true]))->write($document);
            $roundTrip = (new NativeReader())->read($nativeText);
            $jsonPacket = (new PandocJsonWriter())->toArray(new AstNode('document', [
                'pandocApiVersion' => [1, 23, 1],
                'meta' => [],
            ], [$table]));

            $t->same('table', $table->type, "{$label} text native table maps to shared table");
            $t->same('native-text-table', $table->attr('id'), "{$label} table attr id");
            $t->same(['caption-slice'], $table->attr('classes'), "{$label} table classes");
            $t->same(['data-source' => 'native-text'], $table->attr('attributes'), "{$label} table attributes");
            $t->same('Short queue', $table->attr('shortCaption'), "{$label} short caption text");
            $t->same('Long caption', $table->attr('caption'), "{$label} long caption text");
            $t->same(['text', 'text', 'strong'], array_map(
                static fn (AstNode $node): string => $node->type,
                $shortCaptionInlines
            ), "{$label} short caption inline types");
            $t->same(['left'], $table->attr('alignments'), "{$label} table alignment");
            $t->contains(
                '(Just (ShortCaption [ Str "Short" , Space , Strong [ Str "queue" ] ]))',
                $nativeText,
                "{$label} writer emits current ShortCaption constructor"
            );
            $t->same('Short queue', $roundTrip->children[0]->attr('shortCaption'), "{$label} writer output reads back short caption");
            $t->same('Long caption', $roundTrip->children[0]->attr('caption'), "{$label} writer output reads back long caption");
            $t->same('Caption', $jsonPacket['blocks'][0]['c'][1]['t'], "{$label} JSON writer emits Caption constructor");
            $t->same('Just', $jsonPacket['blocks'][0]['c'][1]['c'][0]['t'], "{$label} JSON writer emits Just constructor");
            $t->same('ShortCaption', $jsonPacket['blocks'][0]['c'][1]['c'][0]['c']['t'], "{$label} JSON writer emits ShortCaption constructor");
            $t->same('Short', $jsonPacket['blocks'][0]['c'][1]['c'][0]['c']['c'][0][0]['c'], "{$label} JSON writer keeps short caption text");
            $t->same('Long', $jsonPacket['blocks'][0]['c'][1]['c'][1][0]['c'][0]['c'], "{$label} JSON writer keeps long caption text");
        }

        $current = (new NativeReader())->read($native)->children[0];
        $legacy = (new NativeReader())->read($legacyNative)->children[0];

        $t->same('Caption', $current->attr('captionConstructor'));
        $t->same('Just', $current->attr('shortCaptionMaybeConstructor'));
        $t->same('ShortCaption', $current->attr('shortCaptionConstructor'));
        $t->same('Caption', $current->attr('captionNative')['t'] ?? null);
        $t->same(null, $legacy->attr('captionNative'));
    },
];
