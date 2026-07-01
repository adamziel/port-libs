<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves single wrapped untagged attr tuple sidecars when rebuilding constructors' => static function (TestRunner $t): void {
        $headingAttr = [[
            'wrapped-heading',
            ['review'],
            [['data-source', 'json-native']],
            'heading-attr-sidecar',
        ]];
        $linkAttr = [[
            'wrapped-link',
            ['source-link'],
            [['data-link', 'source']],
            ['sourceOrdinal' => 9],
        ]];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Header', 'c' => [
                    2,
                    $headingAttr,
                    [['t' => 'Str', 'c' => 'Wrapped']],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Link', 'c' => [
                        $linkAttr,
                        [['t' => 'Str', 'c' => 'source']],
                        ['https://example.test/source', 'Source'],
                    ]],
                ]],
            ],
        ];
        $withoutWrapperNative = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $heading = $document->children[0];
            $link = $document->children[1]->children[0];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('heading', $withoutWrapperNative($heading), $heading->children),
                new AstNode('paragraph', [], [
                    new AstNode('link', $withoutWrapperNative($link), $link->children),
                ]),
            ]);

            $t->same($headingAttr, $heading->attr('attrNative'), "{$source} heading attr keeps single wrapper");
            $t->same($linkAttr, $link->attr('attrNative'), "{$source} link attr keeps single wrapper");
            $t->same('wrapped-heading', $heading->attr('id'), "{$source} heading id");
            $t->same(['review'], $heading->attr('classes'), "{$source} heading classes");
            $t->same(['data-link' => 'source'], $link->attr('attributes'), "{$source} link attributes");

            foreach ([
                "{$source} json" => (new PandocJsonWriter())->toArray($rebuilt),
                "{$source} native" => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($headingAttr, $encoded['blocks'][0]['c'][1], "{$writer} preserves heading wrapped attr tuple");
                $t->same($linkAttr, $encoded['blocks'][1]['c'][0]['c'][0], "{$writer} preserves link wrapped attr tuple");
            }

            $editedHeading = new AstNode('heading', array_replace($withoutWrapperNative($heading), [
                'id' => 'edited-heading',
            ]), $heading->children);

            foreach ([
                "{$source} edited json" => (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [$editedHeading])),
                "{$source} edited native" => json_decode((new NativeWriter())->write(new AstNode('document', $document->attrs, [$editedHeading])), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $editedAttr = $encoded['blocks'][0]['c'][1];

                $t->same(['edited-heading', ['review'], [['data-source', 'json-native']]], $editedAttr, "{$writer} regenerates edited attr tuple");
                $t->same(false, array_key_exists(3, $editedAttr), "{$writer} drops stale attr sidecar after edit");
            }
        }
    },
];
