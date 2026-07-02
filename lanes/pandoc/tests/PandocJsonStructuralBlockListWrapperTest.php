<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves structural block child list wrappers while rebuilding parent constructors' => static function (TestRunner $t): void {
        $quotePlain = ['t' => 'Plain', 'c' => [
            ['t' => 'Str', 'c' => 'Quoted'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'source'],
        ], 'reviewQueue' => 'quote-child-source'];
        $divPlain = ['t' => 'Plain', 'c' => [
            ['t' => 'Str', 'c' => 'Div'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'source'],
        ], 'reviewQueue' => 'div-child-source'];
        $figurePlain = ['t' => 'Para', 'c' => [
            ['t' => 'Str', 'c' => 'Figure'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'body'],
        ], 'reviewQueue' => 'figure-child-source'];
        $caption = ['t' => 'Caption', 'c' => [['t' => 'Nothing'], []], 'reviewQueue' => 'figure-caption-source'];
        $quoteBlock = ['t' => 'BlockQuote', 'c' => [[$quotePlain]], 'reviewQueue' => 'quote-wrapper-source'];
        $divBlock = ['t' => 'Div', 'c' => [
            ['wrapped-div', ['review'], []],
            [[$divPlain]],
        ], 'reviewQueue' => 'div-wrapper-source'];
        $figureBlock = ['t' => 'Figure', 'c' => [
            ['wrapped-figure', ['review'], []],
            $caption,
            [[$figurePlain]],
        ], 'reviewQueue' => 'figure-wrapper-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$quoteBlock, $divBlock, $figureBlock],
        ];
        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];
        $stripWrapperNative = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };
        $encode = static function (AstNode $document): array {
            return [
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ];
        };
        $textBlock = static fn (string $first, string $second): AstNode => new AstNode('plain', [], [
            new AstNode('text', ['text' => $first]),
            new AstNode('space'),
            new AstNode('text', ['text' => $second]),
        ]);

        foreach ($documents as $source => $document) {
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('blockquote', $stripWrapperNative($document->children[0]), $document->children[0]->children),
                new AstNode('div', $stripWrapperNative($document->children[1]), $document->children[1]->children),
                new AstNode('figure', $stripWrapperNative($document->children[2]), $document->children[2]->children),
            ]);

            foreach ($encode($rebuilt) as $writer => $encoded) {
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} {$writer} rebuilds block quote wrapper");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][1]), "{$source} {$writer} rebuilds div wrapper");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][2]), "{$source} {$writer} rebuilds figure wrapper");
                $t->same([[$quotePlain]], $encoded['blocks'][0]['c'], "{$source} {$writer} preserves block quote child list wrapper");
                $t->same([[$divPlain]], $encoded['blocks'][1]['c'][1], "{$source} {$writer} preserves div child list wrapper");
                $t->same([[$figurePlain]], $encoded['blocks'][2]['c'][2], "{$source} {$writer} preserves figure child list wrapper");
            }

            $editedQuote = new AstNode('document', $document->attrs, [
                new AstNode('blockquote', $stripWrapperNative($document->children[0]), [$textBlock('Edited', 'quote')]),
                new AstNode('div', $stripWrapperNative($document->children[1]), $document->children[1]->children),
                new AstNode('figure', $stripWrapperNative($document->children[2]), $document->children[2]->children),
            ]);
            $editedQuoteBlock = ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Edited'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'quote'],
            ]];

            foreach ($encode($editedQuote) as $writer => $encoded) {
                $t->same([$editedQuoteBlock], $encoded['blocks'][0]['c'], "{$source} {$writer} regenerates edited block quote child list");
                $t->same([[$divPlain]], $encoded['blocks'][1]['c'][1], "{$source} {$writer} keeps neighboring div child wrapper");
                $t->same([[$figurePlain]], $encoded['blocks'][2]['c'][2], "{$source} {$writer} keeps neighboring figure child wrapper");
            }
        }
    },
    'native writer treats structural block list sidecars as json native provenance' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('blockquote', [
                'blockQuoteBlocksNative' => [[]],
            ]),
            new AstNode('div', [
                'id' => 'empty-div',
                'divBlocksNative' => [[]],
            ]),
            new AstNode('figure', [
                'id' => 'empty-figure',
                'figureBlocksNative' => [[]],
            ]),
        ]);

        $packet = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $roundTrip = (new PandocJsonReader())->readPacket($packet);

        $t->same([1, 23, 1], $packet['pandoc-api-version']);
        $t->same('BlockQuote', $packet['blocks'][0]['t']);
        $t->same([[]], $packet['blocks'][0]['c']);
        $t->same('Div', $packet['blocks'][1]['t']);
        $t->same('empty-div', $packet['blocks'][1]['c'][0][0]);
        $t->same([[]], $packet['blocks'][1]['c'][1]);
        $t->same('Figure', $packet['blocks'][2]['t']);
        $t->same('empty-figure', $packet['blocks'][2]['c'][0][0]);
        $t->same([[]], $packet['blocks'][2]['c'][2]);
        $t->same(['blockquote', 'div', 'figure'], array_map(
            static fn (AstNode $node): string => $node->type,
            $roundTrip->children
        ));
        $t->same([[]], $roundTrip->children[0]->attr('blockQuoteBlocksNative'));
        $t->same([[]], $roundTrip->children[1]->attr('divBlocksNative'));
        $t->same([[]], $roundTrip->children[2]->attr('figureBlocksNative'));
    },
];
