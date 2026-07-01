<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'maps pandoc json tex raw inline constructors to raw_tex_inline' => static function (TestRunner $t): void {
        $formatNative = ['t' => 'Format', 'c' => 'latex', 'reviewQueue' => 'raw-inline-format-source'];
        $rawInline = [
            't' => 'RawInline',
            'c' => [$formatNative, '\\alpha'],
            'reviewQueue' => 'raw-inline-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [[
                't' => 'Para',
                'c' => [
                    ['t' => 'Str', 'c' => 'Before'],
                    ['t' => 'Space'],
                    $rawInline,
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'after'],
                ],
            ]],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $paragraph = $document->children[0];
            $inline = null;
            foreach ($paragraph->children as $child) {
                if ($child instanceof AstNode && $child->type === 'raw_tex_inline') {
                    $inline = $child;
                    break;
                }
            }
            $inline ??= new AstNode('missing');
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same('paragraph', $paragraph->type, "{$source} paragraph type");
            $t->same('Before \\alpha after', $paragraph->attr('text'), "{$source} paragraph text includes raw tex inline");
            $t->same('raw_tex_inline', $inline->type, "{$source} raw tex inline constructor");
            $t->same('RawInline', $inline->attr('constructor'), "{$source} native constructor");
            $t->same($rawInline, $inline->attr('native'), "{$source} native payload");
            $t->same('Format', $inline->attr('formatConstructor'), "{$source} format constructor");
            $t->same($formatNative, $inline->attr('formatNative'), "{$source} format native payload");
            $t->same('latex', $inline->attr('format'), "{$source} raw format");
            $t->same('\\alpha', $inline->attr('tex'), "{$source} raw tex text");
            $t->same($packet['blocks'], $jsonPacket['blocks'], "{$source} json writer preserves raw inline payload");
            $t->same($packet['blocks'], $nativePacket['blocks'], "{$source} native writer preserves raw inline payload");

            $editedInlineAttrs = array_replace($inline->attrs, [
                'text' => '\\beta',
                'tex' => '\\beta',
            ]);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('raw_tex_inline', $editedInlineAttrs),
                ]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $editedPacket) {
                $editedRawInline = $editedPacket['blocks'][0]['c'][0];

                $t->same('RawInline', $editedRawInline['t'], "{$source} {$writer} edited raw inline constructor");
                $t->same($formatNative, $editedRawInline['c'][0], "{$source} {$writer} edited raw inline preserves format helper");
                $t->same('\\beta', $editedRawInline['c'][1], "{$source} {$writer} edited raw inline text");
                $t->same(false, array_key_exists('reviewQueue', $editedRawInline), "{$source} {$writer} edited raw inline drops stale sidecar");
            }
        }
    },
];
