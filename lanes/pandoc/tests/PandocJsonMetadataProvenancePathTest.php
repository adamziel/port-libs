<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves metadata native payloads for slash and tilde escaped provenance paths' => static function (TestRunner $t): void {
        $tildeKeyNative = ['t' => 'MetaString', 'c' => 'tilde-owner', 'reviewQueue' => 'tilde-key-source'];
        $slashTildeKeyNative = ['t' => 'MetaString', 'c' => 'slash-tilde-owner', 'reviewQueue' => 'slash-tilde-key-source'];
        $flagNative = ['t' => 'MetaBool', 'c' => true, 'reviewQueue' => 'flag-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => ['t' => 'MetaMap', 'c' => [
                'review' => ['t' => 'MetaMap', 'c' => [
                    'owner~team' => $tildeKeyNative,
                    'owner/team~lead' => $slashTildeKeyNative,
                    'flags' => ['t' => 'MetaList', 'c' => [
                        $flagNative,
                        ['t' => 'MetaString', 'c' => 'stale', 'reviewQueue' => 'stale-flag-source'],
                    ], 'reviewQueue' => 'flags-source'],
                ], 'reviewQueue' => 'review-source'],
            ]],
            'blocks' => [],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $meta = $document->attr('meta');
            $meta['review'] = [
                'type' => 'map',
                'items' => [
                    'owner~team' => 'tilde-owner',
                    'owner/team~lead' => 'slash-tilde-owner',
                    'flags' => [
                        'type' => 'list',
                        'items' => [
                            true,
                            'edited',
                        ],
                    ],
                    'added/key~name' => 'new-value',
                ],
            ];
            $editedDocument = new AstNode('document', array_replace($document->attrs, ['meta' => $meta]), $document->children);
            $provenance = $document->attr('metaConstructorProvenance');

            $t->same($tildeKeyNative, $provenance['/review/owner~0team']['native'], "{$source} reader escapes tilde metadata path");
            $t->same($slashTildeKeyNative, $provenance['/review/owner~1team~0lead']['native'], "{$source} reader escapes slash and tilde metadata path");

            foreach ([
                'json writer' => (new PandocJsonWriter())->toArray($editedDocument),
                'native writer' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $review = $encoded['meta']['review'];
                $flags = $review['c']['flags'];

                $t->same('MetaMap', $review['t'], "{$source} {$writer} rebuilds edited review metadata map");
                $t->same(false, array_key_exists('reviewQueue', $review), "{$source} {$writer} drops stale edited map sidecar");
                $t->same($tildeKeyNative, $review['c']['owner~team'], "{$source} {$writer} preserves tilde-key metadata sidecar");
                $t->same($slashTildeKeyNative, $review['c']['owner/team~lead'], "{$source} {$writer} preserves slash-tilde-key metadata sidecar");
                $t->same('MetaList', $flags['t'], "{$source} {$writer} rebuilds edited list metadata");
                $t->same(false, array_key_exists('reviewQueue', $flags), "{$source} {$writer} drops stale edited list sidecar");
                $t->same($flagNative, $flags['c'][0], "{$source} {$writer} preserves unchanged list item metadata sidecar");
                $t->same(['t' => 'MetaString', 'c' => 'edited'], $flags['c'][1], "{$source} {$writer} regenerates edited list item metadata");
                $t->same(['t' => 'MetaString', 'c' => 'new-value'], $review['c']['added/key~name'], "{$source} {$writer} emits new escaped-key metadata field");
            }
        }
    },
];
