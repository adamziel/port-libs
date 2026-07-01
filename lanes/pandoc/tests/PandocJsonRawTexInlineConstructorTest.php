<?php

declare(strict_types=1);

use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'maps pandoc json raw tex inline constructors to inline ast nodes' => static function (TestRunner $t): void {
        $findRawTexInline = static function ($document) {
            foreach ($document->children[1]->children as $inline) {
                if ($inline->type === 'raw_tex_inline') {
                    return $inline;
                }
            }

            return null;
        };
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'RawBlock', 'c' => ['latex', '\\clearpage']],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Before'],
                    ['t' => 'Space'],
                    ['t' => 'RawInline', 'c' => ['tex', '\\alpha']],
                ]],
            ],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $rawBlock = $document->children[0];
            $rawInline = $findRawTexInline($document);
            if ($rawInline === null) {
                throw new \RuntimeException("{$source} raw tex inline was not found");
            }
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
            $jsonRoundTrip = (new PandocJsonReader())->readPacket($jsonPacket);
            $nativeRoundTrip = (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR));
            $jsonRoundTripRawInline = $findRawTexInline($jsonRoundTrip);
            $nativeRoundTripRawInline = $findRawTexInline($nativeRoundTrip);

            $t->same('raw_tex', $rawBlock->type, "{$source} raw block remains block scoped");
            $t->same('RawBlock', $rawBlock->attr('constructor'), "{$source} raw block constructor");
            $t->same('latex', $rawBlock->attr('format'), "{$source} raw block format");
            $t->same('\\clearpage', $rawBlock->attr('tex'), "{$source} raw block tex");
            $t->same('raw_tex_inline', $rawInline->type, "{$source} raw inline stays inline scoped");
            $t->same('RawInline', $rawInline->attr('constructor'), "{$source} raw inline constructor");
            $t->same('tex', $rawInline->attr('format'), "{$source} raw inline format");
            $t->same('\\alpha', $rawInline->attr('tex'), "{$source} raw inline tex");
            $t->same($packet['blocks'], $jsonPacket['blocks'], "{$source} json writer preserves raw constructors");
            $t->same($packet['blocks'], $nativePacket['blocks'], "{$source} native writer preserves raw constructors");
            $t->same('raw_tex_inline', $jsonRoundTripRawInline?->type, "{$source} json round trip raw inline type");
            $t->same('raw_tex_inline', $nativeRoundTripRawInline?->type, "{$source} native round trip raw inline type");
        }
    },
];
