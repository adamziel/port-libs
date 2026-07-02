<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'maps textual code inline constructors through json and native readers' => static function (TestRunner $t): void {
        $attr = ['t' => 'Attr', 'c' => [[
            'code-inline',
            ['php', 'review'],
            [['data-code', 'constructor']],
        ]]];
        $codeInline = ['t' => 'Code', 'c' => [
            $attr,
            'wp_insert_post',
        ], 'reviewQueue' => 'code-inline-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [$codeInline]],
            ],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $paragraph = $document->children[0];
            $code = $paragraph->children[0];
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same(['code'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), "{$source} paragraph inline type");
            $t->same('code', $code->type, "{$source} shared AST code type");
            $t->same('Code', $code->attr('constructor'), "{$source} constructor attr");
            $t->same('wp_insert_post', $code->attr('text'), "{$source} code text");
            $t->same('code-inline', $code->attr('id'), "{$source} attr id");
            $t->same(['php', 'review'], $code->attr('classes'), "{$source} attr classes");
            $t->same(['data-code' => 'constructor'], $code->attr('attributes'), "{$source} attr key-values");
            $t->same($attr, $code->attr('attrNative'), "{$source} attr native helper");
            $t->same($codeInline, $code->attr('native'), "{$source} native sidecar");
            $t->same($packet['blocks'], $jsonPacket['blocks'], "{$source} JSON writer preserves code constructor");
            $t->same($packet['blocks'], $nativePacket['blocks'], "{$source} native writer preserves code constructor");
        }
    },
];
