<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves inline container child collection wrappers while rebuilding parent constructors' => static function (TestRunner $t): void {
        $wrappedInline = static fn (string $text): array => [[['t' => 'Str', 'c' => $text]]];
        $wrappedBlock = static fn (string $text): array => [[['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => $text]]]]];
        $spanAttr = ['wrapped-span', ['review'], [['data-kind', 'span']]];
        $containers = [
            ['constructor' => 'Emph', 'type' => 'emph', 'payload' => $wrappedInline('emph')],
            ['constructor' => 'Strong', 'type' => 'strong', 'payload' => $wrappedInline('strong')],
            ['constructor' => 'Underline', 'type' => 'underline', 'payload' => $wrappedInline('underline')],
            ['constructor' => 'Strikeout', 'type' => 'strikeout', 'payload' => $wrappedInline('strike')],
            ['constructor' => 'Superscript', 'type' => 'superscript', 'payload' => $wrappedInline('super')],
            ['constructor' => 'Subscript', 'type' => 'subscript', 'payload' => $wrappedInline('sub')],
            ['constructor' => 'SmallCaps', 'type' => 'small_caps', 'payload' => $wrappedInline('caps')],
        ];
        $paragraphInlines = array_map(
            static fn (array $case): array => [
                't' => $case['constructor'],
                'c' => $case['payload'],
                'reviewQueue' => strtolower($case['constructor']) . '-wrapper-source',
            ],
            $containers
        );
        $paragraphInlines[] = ['t' => 'Quoted', 'c' => [
            ['t' => 'SingleQuote'],
            $wrappedInline('quoted'),
        ], 'reviewQueue' => 'quoted-wrapper-source'];
        $paragraphInlines[] = ['t' => 'Span', 'c' => [
            $spanAttr,
            $wrappedInline('span'),
        ], 'reviewQueue' => 'span-wrapper-source'];
        $paragraphInlines[] = ['t' => 'Link', 'c' => [
            ['wrapped-link', ['review'], [['data-kind', 'link']]],
            $wrappedInline('link'),
            ['https://example.test/source', 'Source title'],
        ], 'reviewQueue' => 'link-wrapper-source'];
        $paragraphInlines[] = ['t' => 'Image', 'c' => [
            ['wrapped-image', ['review'], [['data-kind', 'image']]],
            $wrappedInline('image'),
            ['media/review.png', 'Image title'],
        ], 'reviewQueue' => 'image-wrapper-source'];
        $paragraphInlines[] = ['t' => 'Note', 'c' => $wrappedBlock('note'), 'reviewQueue' => 'note-wrapper-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => $paragraphInlines],
            ],
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

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $paragraph = $document->children[0];
            $rebuiltChildren = array_map(
                static fn (AstNode $inline): AstNode => new AstNode($inline->type, $stripWrapperNative($inline), $inline->children),
                $paragraph->children
            );
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', $stripWrapperNative($paragraph), $rebuiltChildren),
            ]);

            foreach ($containers as $index => $case) {
                $t->same($case['type'], $paragraph->children[$index]->type, "{$source} {$case['constructor']} type");
                $t->same($case['payload'], $paragraph->children[$index]->attr('inlineListNative'), "{$source} {$case['constructor']} records inline wrapper");
            }
            $t->same('quoted', $paragraph->children[7]->type, "{$source} quoted type");
            $t->same($wrappedInline('quoted'), $paragraph->children[7]->attr('inlineListNative'), "{$source} quoted records inline wrapper");
            $t->same('span', $paragraph->children[8]->type, "{$source} span type");
            $t->same($wrappedInline('span'), $paragraph->children[8]->attr('inlineListNative'), "{$source} span records inline wrapper");
            $t->same('link', $paragraph->children[9]->type, "{$source} link type");
            $t->same($wrappedInline('link'), $paragraph->children[9]->attr('inlineListNative'), "{$source} link records inline wrapper");
            $t->same('image', $paragraph->children[10]->type, "{$source} image type");
            $t->same($wrappedInline('image'), $paragraph->children[10]->attr('inlineListNative'), "{$source} image records inline wrapper");
            $t->same('note', $paragraph->children[11]->type, "{$source} note type");
            $t->same($wrappedBlock('note'), $paragraph->children[11]->attr('noteBlocksNative'), "{$source} note records block wrapper");

            foreach ($encode($rebuilt) as $writer => $encoded) {
                foreach ($containers as $index => $case) {
                    $t->same($case['payload'], $encoded['blocks'][0]['c'][$index]['c'], "{$source} {$writer} preserves {$case['constructor']} child wrapper");
                    $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]['c'][$index]), "{$source} {$writer} rebuilds {$case['constructor']} wrapper");
                }
                $t->same($wrappedInline('quoted'), $encoded['blocks'][0]['c'][7]['c'][1], "{$source} {$writer} preserves quoted child wrapper");
                $t->same($wrappedInline('span'), $encoded['blocks'][0]['c'][8]['c'][1], "{$source} {$writer} preserves span child wrapper");
                $t->same($wrappedInline('link'), $encoded['blocks'][0]['c'][9]['c'][1], "{$source} {$writer} preserves link label wrapper");
                $t->same($wrappedInline('image'), $encoded['blocks'][0]['c'][10]['c'][1], "{$source} {$writer} preserves image label wrapper");
                $t->same($wrappedBlock('note'), $encoded['blocks'][0]['c'][11]['c'], "{$source} {$writer} preserves note block wrapper");
            }

            $edited = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', $stripWrapperNative($paragraph), [
                    new AstNode('emph', $stripWrapperNative($paragraph->children[0]), [
                        new AstNode('text', ['text' => 'edited']),
                    ]),
                    ...array_slice($rebuiltChildren, 1),
                ]),
            ]);

            foreach ($encode($edited) as $writer => $encoded) {
                $t->same([['t' => 'Str', 'c' => 'edited']], $encoded['blocks'][0]['c'][0]['c'], "{$source} {$writer} regenerates edited inline child list");
                $t->same($containers[1]['payload'], $encoded['blocks'][0]['c'][1]['c'], "{$source} {$writer} preserves neighboring strong wrapper");
            }
        }
    },
];
