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

    'uses structural block list native sidecars as native writer json trigger' => static function (TestRunner $t): void {
        $quotePlain = ['t' => 'Plain', 'c' => [
            ['t' => 'Str', 'c' => 'Quote'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'sidecar'],
        ], 'reviewQueue' => 'manual-quote-child'];
        $divPlain = ['t' => 'Plain', 'c' => [
            ['t' => 'Str', 'c' => 'Div'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'sidecar'],
        ], 'reviewQueue' => 'manual-div-child'];
        $figurePara = ['t' => 'Para', 'c' => [
            ['t' => 'Str', 'c' => 'Figure'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'sidecar'],
        ], 'reviewQueue' => 'manual-figure-child'];

        $inlineBlock = static fn (string $first, string $second, string $type = 'plain'): AstNode => new AstNode($type, [], [
            new AstNode('text', ['text' => $first]),
            new AstNode('space'),
            new AstNode('text', ['text' => $second]),
        ]);
        $encode = static fn (AstNode $document): array => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $document = new AstNode('document', [], [
            new AstNode('blockquote', ['blockQuoteBlocksNative' => [[$quotePlain]]], [$inlineBlock('Quote', 'sidecar')]),
            new AstNode('div', [
                'id' => 'manual-div',
                'classes' => ['review'],
                'divBlocksNative' => [[$divPlain]],
            ], [$inlineBlock('Div', 'sidecar')]),
            new AstNode('figure', [
                'id' => 'manual-figure',
                'classes' => ['review'],
                'figureBlocksNative' => [[$figurePara]],
            ], [$inlineBlock('Figure', 'sidecar', 'paragraph')]),
        ]);

        $encoded = $encode($document);
        $t->same([1, 23, 1], $encoded['pandoc-api-version']);
        $t->same(['BlockQuote', 'Div', 'Figure'], array_map(static fn (array $block): string => (string) $block['t'], $encoded['blocks']));
        $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]));
        $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][1]));
        $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][2]));
        $t->same([[$quotePlain]], $encoded['blocks'][0]['c']);
        $t->same([[$divPlain]], $encoded['blocks'][1]['c'][1]);
        $t->same([[$figurePara]], $encoded['blocks'][2]['c'][2]);

        $editedQuote = new AstNode('document', [], [
            new AstNode('blockquote', ['blockQuoteBlocksNative' => [[$quotePlain]]], [$inlineBlock('Edited', 'quote')]),
            $document->children[1],
            $document->children[2],
        ]);
        $encodedEdited = $encode($editedQuote);
        $t->same([['t' => 'Plain', 'c' => [
            ['t' => 'Str', 'c' => 'Edited'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'quote'],
        ]]], $encodedEdited['blocks'][0]['c']);
        $t->same([[$divPlain]], $encodedEdited['blocks'][1]['c'][1]);
        $t->same([[$figurePara]], $encodedEdited['blocks'][2]['c'][2]);
    },
];
