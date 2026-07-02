<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves target helper constructor shape when regenerating link and image targets' => static function (TestRunner $t): void {
        $linkTarget = [
            't' => 'Target',
            'c' => [['https://example.test/source', 'Source title']],
            'reviewQueue' => 'link-target-source',
        ];
        $imageTarget = [[
            't' => 'Target',
            'c' => ['media/source.png', 'Source image'],
            'reviewQueue' => 'image-target-source',
        ]];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [[
                't' => 'Para',
                'c' => [
                    ['t' => 'Link', 'c' => [
                        ['', ['tracked'], [['data-kind', 'target']]],
                        [['t' => 'Str', 'c' => 'source']],
                        $linkTarget,
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Image', 'c' => [
                        ['', ['asset'], []],
                        [['t' => 'Str', 'c' => 'source image']],
                        $imageTarget,
                    ]],
                ],
            ]],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $paragraph = $document->children[0];
            $link = $paragraph->children[0];
            $image = $paragraph->children[2];

            $t->same('Target', $link->attr('targetConstructor'), "{$source} records tagged link Target helper");
            $t->same($linkTarget, $link->attr('targetNative'), "{$source} records link Target native");
            $t->same($imageTarget, $image->attr('targetNative'), "{$source} records single-wrapped image Target native");

            foreach ([
                "{$source} json" => (new PandocJsonWriter())->toArray($document),
                "{$source} native" => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($packet['blocks'], $encoded['blocks'], "{$writer} preserves unchanged Target helpers");
            }

            $edited = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('link', array_replace($link->attrs, [
                        'url' => 'https://example.test/edited',
                        'title' => 'Edited title',
                    ]), $link->children),
                    new AstNode('image', array_replace($image->attrs, [
                        'url' => 'media/edited.png',
                        'title' => 'Edited image',
                    ]), $image->children),
                ]),
            ]);

            foreach ([
                "{$source} edited json" => (new PandocJsonWriter())->toArray($edited),
                "{$source} edited native" => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $editedLinkTarget = $encoded['blocks'][0]['c'][0]['c'][2];
                $editedImageTarget = $encoded['blocks'][0]['c'][1]['c'][2];

                $t->same(['t' => 'Target', 'c' => [['https://example.test/edited', 'Edited title']]], $editedLinkTarget, "{$writer} keeps wrapped link Target constructor");
                $t->same([['t' => 'Target', 'c' => ['media/edited.png', 'Edited image']]], $editedImageTarget, "{$writer} keeps single-wrapped image Target constructor");
                $t->same(false, array_key_exists('reviewQueue', $editedLinkTarget), "{$writer} drops stale link target sidecar");
                $t->same(false, array_key_exists('reviewQueue', $editedImageTarget[0]), "{$writer} drops stale image target sidecar");
            }
        }
    },
];
