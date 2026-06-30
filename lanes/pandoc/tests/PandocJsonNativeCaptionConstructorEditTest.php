<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'regenerates edited tagged caption constructors without losing wrapper provenance' => static function (TestRunner $t): void {
        $shortMaybe = [
            't' => 'Just',
            'c' => [
                't' => 'ShortCaption',
                'c' => [[['t' => 'Str', 'c' => 'Old']]],
                'reviewQueue' => 'short-caption-source',
            ],
            'reviewQueue' => 'maybe-caption-source',
        ];
        $captionNative = [
            't' => 'Caption',
            'c' => [
                $shortMaybe,
                [[
                    't' => 'Plain',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Old'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'caption'],
                    ],
                    'reviewQueue' => 'old-caption-block-source',
                ]],
            ],
            'reviewQueue' => 'caption-source',
        ];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['caption-table', ['review'], [['data-source', 'caption']]],
                $captionNative,
                [],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
            'reviewQueue' => 'table-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $table = $document->children[0];
            $editedShort = [
                new AstNode('text', ['text' => 'New']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'short']),
            ];
            $editedLong = [
                new AstNode('text', ['text' => 'New']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'caption']),
            ];
            $attrs = array_replace($table->attrs, [
                'shortCaption' => 'New short',
                'shortCaptionInlines' => $editedShort,
                'caption' => 'New caption',
                'captionInlines' => $editedLong,
                'captionBlocks' => [new AstNode('plain', [], $editedLong)],
            ]);
            $editedDocument = new AstNode('document', $document->attrs, [
                new AstNode('table', $attrs, $table->children),
            ]);

            foreach ([
                'json writer' => (new PandocJsonWriter())->toArray($editedDocument),
                'native writer' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedCaption = $encoded['blocks'][0]['c'][1];
                $encodedMaybe = $encodedCaption['c'][0];
                $encodedShort = $encodedMaybe['c'];
                $encodedLong = $encodedCaption['c'][1][0];

                $t->same('Caption', $encodedCaption['t'], "{$source} {$writer} keeps Caption constructor");
                $t->same('caption-source', $encodedCaption['reviewQueue'] ?? null, "{$source} {$writer} preserves Caption wrapper provenance");
                $t->same('Just', $encodedMaybe['t'], "{$source} {$writer} keeps short-caption maybe constructor");
                $t->same('maybe-caption-source', $encodedMaybe['reviewQueue'] ?? null, "{$source} {$writer} preserves Just wrapper provenance");
                $t->same('ShortCaption', $encodedShort['t'], "{$source} {$writer} keeps ShortCaption constructor");
                $t->same('short-caption-source', $encodedShort['reviewQueue'] ?? null, "{$source} {$writer} preserves ShortCaption wrapper provenance");
                $t->same([
                    ['t' => 'Str', 'c' => 'New'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'short'],
                ], $encodedShort['c'][0], "{$source} {$writer} regenerates edited short caption");
                $t->same('Plain', $encodedLong['t'], "{$source} {$writer} regenerates long caption block");
                $t->same([
                    ['t' => 'Str', 'c' => 'New'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'caption'],
                ], $encodedLong['c'], "{$source} {$writer} regenerates edited long caption");
                $t->same(false, array_key_exists('reviewQueue', $encodedLong), "{$source} {$writer} drops stale long-caption block sidecar");
            }
        }
    },
];
