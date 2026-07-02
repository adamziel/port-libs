<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads pandoc json filter packets into shared ast documents' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'title' => ['t' => 'MetaInlines', 'c' => [
                    ['t' => 'Str', 'c' => 'Review'],
                    ['t' => 'Space'],
                    ['t' => 'Emph', 'c' => [
                        ['t' => 'Str', 'c' => 'Packet'],
                    ]],
                ]],
                'draft' => ['t' => 'MetaBool', 'c' => true],
                'source' => ['t' => 'MetaString', 'c' => 'batch-42'],
            ],
            'blocks' => [
                ['t' => 'Header', 'c' => [
                    2,
                    ['review-packet', ['wp-import'], [['data-source', 'batch-42']]],
                    [
                        ['t' => 'Str', 'c' => 'Review'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'Packet'],
                    ],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Archive'],
                    ['t' => 'Space'],
                    ['t' => 'Link', 'c' => [
                        ['', ['source-link'], [['data-source', 'source']]],
                        [['t' => 'Str', 'c' => 'source']],
                        ['https://example.test/source', 'Source title'],
                    ]],
                    ['t' => 'Str', 'c' => '.'],
                    ['t' => 'Space'],
                    ['t' => 'Note', 'c' => [
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Str', 'c' => 'Check'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'source'],
                        ]],
                    ]],
                ]],
            ],
        ];

        $document = (new PandocJsonReader())->readPacket($packet);
        $meta = $document->attr('meta');
        $heading = $document->children[0];
        $paragraph = $document->children[1];
        $link = $paragraph->children[2];
        $note = $paragraph->children[5];

        $t->same('document', $document->type);
        $t->same([1, 23, 1], $document->attr('pandocApiVersion'));
        $t->same(true, $meta['draft']);
        $t->same('batch-42', $meta['source']);
        $t->same('inlines', $meta['title']['type']);
        $t->same('emph', $meta['title']['children'][2]->type);
        $t->same('heading', $heading->type);
        $t->same(2, $heading->attr('level'));
        $t->same('review-packet', $heading->attr('id'));
        $t->same(['wp-import'], $heading->attr('classes'));
        $t->same(['data-source' => 'batch-42'], $heading->attr('attributes'));
        $t->same('link', $link->type);
        $t->same('https://example.test/source', $link->attr('url'));
        $t->same('Source title', $link->attr('title'));
        $t->same(['source-link'], $link->attr('classes'));
        $t->same('note', $note->type);
        $t->same('paragraph', $note->children[0]->type);
    },
    'reads tagged pandoc document constructors into shared ast documents' => static function (TestRunner $t): void {
        $pandoc = [
            't' => 'Pandoc',
            'pandoc-api-version' => [1, 24, 2],
            'c' => [
                ['t' => 'MetaMap', 'c' => [
                    'source' => ['t' => 'MetaString', 'c' => 'tagged-document'],
                    'review' => ['t' => 'MetaBool', 'c' => true],
                ]],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Str', 'c' => 'Tagged'],
                        ['t' => 'Space'],
                        ['t' => 'Emph', 'c' => [
                            ['t' => 'Str', 'c' => 'document'],
                        ]],
                    ], 'reviewQueue' => 'pandoc-constructor-block'],
                ],
            ],
            'reviewQueue' => 'pandoc-constructor',
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($pandoc),
            'native' => (new NativeReader())->read(json_encode($pandoc, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $paragraph = $document->children[0];
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same('document', $document->type, "{$source} document type");
            $t->same('Pandoc', $document->attr('documentConstructor'), "{$source} document constructor");
            $t->same($pandoc, $document->attr('documentNative'), "{$source} document native payload");
            $t->same([1, 24, 2], $document->attr('pandocApiVersion'), "{$source} tagged document API version");
            $t->same($source === 'json' ? 'tagged-document' : ['t' => 'MetaString', 'c' => 'tagged-document'], $document->attr('meta')['source'], "{$source} tagged document metadata");
            $t->same($source === 'json' ? true : ['t' => 'MetaBool', 'c' => true], $document->attr('meta')['review'], "{$source} tagged document bool metadata");
            $t->same('paragraph', $paragraph->type, "{$source} paragraph type");
            $t->same('Para', $paragraph->attr('constructor'), "{$source} paragraph constructor");
            $t->same($pandoc['c'][1][0], $paragraph->attr('native'), "{$source} paragraph native payload");
            $t->same(['t' => 'MetaString', 'c' => 'tagged-document'], $jsonPacket['meta']['source'], "{$source} json writer emits standard meta");
            $t->same(['t' => 'MetaString', 'c' => 'tagged-document'], $nativePacket['meta']['source'], "{$source} native writer emits standard meta");
            $t->same([1, 24, 2], $jsonPacket['pandoc-api-version'], "{$source} json writer emits tagged document API version");
            $t->same([1, 24, 2], $nativePacket['pandoc-api-version'], "{$source} native writer emits tagged document API version");
            $t->same($pandoc['c'][1], $jsonPacket['blocks'], "{$source} json writer preserves tagged document blocks");
            $t->same($pandoc['c'][1], $nativePacket['blocks'], "{$source} native writer preserves tagged document blocks");
            $t->same(false, array_key_exists('t', $jsonPacket), "{$source} json writer emits packet object");
            $t->same(false, array_key_exists('t', $nativePacket), "{$source} native writer emits packet object");
        }
    },
    'reads single wrapped pandoc document constructor payloads through json and native readers' => static function (TestRunner $t): void {
        $wrappedPandoc = [
            't' => 'Pandoc',
            'pandoc-api-version' => [1, 23, 1],
            'c' => [[
                ['t' => 'MetaMap', 'c' => [
                    'title' => ['t' => 'MetaInlines', 'c' => [
                        ['t' => 'Str', 'c' => 'Wrapped'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'Pandoc'],
                    ]],
                    'source' => ['t' => 'MetaString', 'c' => 'wrapped-document'],
                ]],
                [
                    ['t' => 'Header', 'c' => [
                        2,
                        ['wrapped-doc', ['review'], [['data-source', 'constructor']]],
                        [
                            ['t' => 'Str', 'c' => 'Wrapped'],
                        ],
                    ], 'reviewQueue' => 'wrapped-heading-source'],
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Str', 'c' => 'constructor'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'payload'],
                    ], 'reviewQueue' => 'wrapped-paragraph-source'],
                ],
            ]],
            'reviewQueue' => 'wrapped-pandoc-document',
        ];

        $sourceMeta = $wrappedPandoc['c'][0][0]['c'];
        $sourceBlocks = $wrappedPandoc['c'][0][1];
        $documents = [
            'json' => (new PandocJsonReader())->readPacket($wrappedPandoc),
            'native' => (new NativeReader())->read(json_encode($wrappedPandoc, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $meta = $document->attr('meta');
            $titleInlines = $meta['titleInlines'];
            $heading = $document->children[0];
            $paragraph = $document->children[1];
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same('Pandoc', $document->attr('documentConstructor'), "{$source} document constructor");
            $t->same($wrappedPandoc, $document->attr('documentNative'), "{$source} document native payload");
            $t->same([1, 23, 1], $document->attr('pandocApiVersion'), "{$source} API version");
            $t->same($source === 'json' ? 'wrapped-document' : $sourceMeta['source'], $meta['source'], "{$source} source metadata");
            $t->same($source === 'json' ? ['text', 'space', 'text'] : ['text'], array_map(static fn (AstNode $node): string => $node->type, $titleInlines), "{$source} title helper node shape");
            $t->same($source === 'json' ? 'Wrapped' : 'Wrapped Pandoc', $titleInlines[0]->attr('text'), "{$source} title helper text");
            $t->same('heading', $heading->type, "{$source} heading block");
            $t->same('wrapped-doc', $heading->attr('id'), "{$source} heading attr id");
            $t->same($sourceBlocks[0], $heading->attr('native'), "{$source} heading native sidecar");
            $t->same('paragraph', $paragraph->type, "{$source} paragraph block");
            $t->same('constructor payload', $paragraph->attr('text'), "{$source} paragraph text");
            $t->same($sourceBlocks[1], $paragraph->attr('native'), "{$source} paragraph native sidecar");

            foreach ([
                "{$source} json writer" => $jsonPacket,
                "{$source} native writer" => $nativePacket,
            ] as $writer => $packet) {
                $t->same([1, 23, 1], $packet['pandoc-api-version'], "{$writer} API version");
                $t->same(false, array_key_exists('t', $packet), "{$writer} emits filter packet object");
                $t->same($sourceMeta, $packet['meta'], "{$writer} emits unwrapped constructor metadata");
                $t->same($sourceBlocks, $packet['blocks'], "{$writer} emits unwrapped constructor blocks");
            }
        }
    },
    'reads single wrapped tagged pandoc document constructor content' => static function (TestRunner $t): void {
        $meta = ['t' => 'MetaMap', 'c' => [
            'source' => ['t' => 'MetaString', 'c' => 'single-wrapped-pandoc'],
            'review' => ['t' => 'MetaBool', 'c' => true],
        ]];
        $blocks = [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Single'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'wrapped'],
                ['t' => 'Space'],
                ['t' => 'Code', 'c' => [
                    ['wrapped-code', ['php'], [['data-source', 'pandoc']]],
                    'wp_insert_post',
                ]],
            ], 'reviewQueue' => 'single-wrapped-pandoc-block'],
        ];
        $pandoc = [
            't' => 'Pandoc',
            'pandoc-api-version' => [1, 23, 1],
            'c' => [[$meta, $blocks]],
            'reviewQueue' => 'single-wrapped-pandoc-document',
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($pandoc),
            'native' => (new NativeReader())->read(json_encode($pandoc, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $paragraph = $document->children[0];
            $codeNodes = array_values(array_filter(
                $paragraph->children,
                static fn (AstNode $node): bool => $node->type === 'code'
            ));
            $code = $codeNodes[0] ?? new AstNode('missing');
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same('Pandoc', $document->attr('documentConstructor'), "{$source} single-wrapped document constructor");
            $t->same($pandoc, $document->attr('documentNative'), "{$source} single-wrapped document native payload");
            $t->same([1, 23, 1], $document->attr('pandocApiVersion'), "{$source} single-wrapped document API version");
            $t->same('paragraph', $paragraph->type, "{$source} single-wrapped paragraph type");
            $t->same('Single wrapped wp_insert_post', $paragraph->attr('text'), "{$source} single-wrapped paragraph text");
            $t->same('Code', $code->attr('constructor'), "{$source} single-wrapped code constructor");
            $t->same(['t' => 'MetaString', 'c' => 'single-wrapped-pandoc'], $jsonPacket['meta']['source'], "{$source} json writer emits source metadata");
            $t->same(['t' => 'MetaBool', 'c' => true], $nativePacket['meta']['review'], "{$source} native writer emits review metadata");
            $t->same($blocks, $jsonPacket['blocks'], "{$source} json writer preserves wrapped document blocks");
            $t->same($blocks, $nativePacket['blocks'], "{$source} native writer preserves wrapped document blocks");
            $t->same(false, array_key_exists('t', $jsonPacket), "{$source} json writer emits packet object");
            $t->same(false, array_key_exists('t', $nativePacket), "{$source} native writer emits packet object");
        }
    },
    'reads legacy pandoc json metadata envelopes into shared ast documents' => static function (TestRunner $t): void {
        $reader = new PandocJsonReader();
        $wrappedPacket = [
            'pandoc-api-version' => [1, 17, 0, 4],
            'meta' => [
                'unMeta' => [
                    'title' => ['t' => 'MetaInlines', 'c' => [
                        ['t' => 'Str', 'c' => 'Legacy'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'Packet'],
                    ]],
                    'review' => ['t' => 'MetaMap', 'c' => [
                        'queue' => ['t' => 'MetaString', 'c' => 'wp-import'],
                        'blocked' => ['t' => 'MetaBool', 'c' => false],
                    ]],
                ],
            ],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Wrapped'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'metadata'],
                ]],
            ],
        ];

        $wrappedDocument = $reader->readPacket($wrappedPacket);
        $wrappedMeta = $wrappedDocument->attr('meta');
        $title = $wrappedMeta['title'];
        $review = $wrappedMeta['review'];

        $t->same([1, 17, 0, 4], $wrappedDocument->attr('pandocApiVersion'));
        $t->same('inlines', $title['type']);
        $t->same('Legacy', $title['children'][0]->attr('text'));
        $t->same('Packet', $title['children'][2]->attr('text'));
        $t->same('map', $review['type']);
        $t->same('wp-import', $review['items']['queue']);
        $t->same(false, $review['items']['blocked']);
        $t->same('paragraph', $wrappedDocument->children[0]->type);
        $t->same('Wrapped', $wrappedDocument->children[0]->children[0]->attr('text'));
        $t->same('metadata', $wrappedDocument->children[0]->children[2]->attr('text'));

        $legacyJson = json_encode([
            [
                'unMeta' => [
                    'source' => ['t' => 'MetaString', 'c' => 'legacy-filter'],
                    'reviewers' => ['t' => 'MetaList', 'c' => [
                        ['t' => 'MetaString', 'c' => 'Ada'],
                        ['t' => 'MetaString', 'c' => 'Grace'],
                    ]],
                ],
            ],
            [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Tuple'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'packet'],
                ]],
            ],
        ], JSON_THROW_ON_ERROR);

        $legacyDocument = $reader->read($legacyJson);
        $legacyMeta = $legacyDocument->attr('meta');

        $t->same('legacy-filter', $legacyMeta['source']);
        $t->same('list', $legacyMeta['reviewers']['type']);
        $t->same(['Ada', 'Grace'], $legacyMeta['reviewers']['items']);
        $t->same('paragraph', $legacyDocument->children[0]->type);

        $modernUnMetaKey = $reader->readPacket([
            'meta' => [
                'unMeta' => ['t' => 'MetaString', 'c' => 'literal-key'],
            ],
            'blocks' => [],
        ]);
        $t->same('literal-key', $modernUnMetaKey->attr('meta')['unMeta']);
    },
    'reads legacy native object unMeta metadata envelopes into shared ast documents' => static function (TestRunner $t): void {
        $reader = new NativeReader();
        $packet = [
            'pandoc-api-version' => [1, 17, 0, 4],
            'meta' => [
                'unMeta' => [
                    'title' => ['t' => 'MetaInlines', 'c' => [
                        ['t' => 'Str', 'c' => 'Legacy'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'Native'],
                    ]],
                    'source' => ['t' => 'MetaString', 'c' => 'legacy-native-object'],
                    'review' => ['t' => 'MetaMap', 'c' => [
                        'queue' => ['t' => 'MetaString', 'c' => 'native-import'],
                        'blocked' => ['t' => 'MetaBool', 'c' => false],
                    ]],
                ],
            ],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Legacy'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'native'],
                ]],
            ],
        ];

        $document = $reader->read(json_encode($packet, JSON_THROW_ON_ERROR));
        $meta = $document->attr('meta');
        $titleInlines = $meta['titleInlines'];
        $provenance = $document->attr('metaConstructorProvenance');
        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $modernLiteral = $reader->read(json_encode([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'unMeta' => ['t' => 'MetaString', 'c' => 'literal-key'],
            ],
            'blocks' => [],
        ], JSON_THROW_ON_ERROR))->attr('meta');

        $t->same([1, 17, 0, 4], $document->attr('pandocApiVersion'));
        $t->same('MetaInlines', $meta['title']['t']);
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $titleInlines));
        $t->same('Legacy Native', $titleInlines[0]->attr('text'));
        $t->same($packet['meta']['unMeta']['title']['c'], $titleInlines[0]->attr('nativeInlineParts'));
        $t->same('MetaString', $meta['source']['t']);
        $t->same('legacy-native-object', $meta['source']['c']);
        $t->same('MetaMap', $meta['review']['t']);
        $t->same('MetaBool', $meta['review']['c']['blocked']['t']);
        $t->same('paragraph', $document->children[0]->type);
        $t->same('Legacy native', $document->children[0]->children[0]->attr('text'));
        $t->same('MetaString', $provenance['/source']['constructor']);
        $t->same('MetaBool', $provenance['/review/blocked']['constructor']);
        $t->same(false, array_key_exists('unMeta', $jsonPacket['meta']));
        $t->same($jsonPacket['meta'], $nativePacket['meta']);
        $t->same($jsonPacket['blocks'], $nativePacket['blocks']);
        $t->same('MetaString', $modernLiteral['unMeta']['t']);
        $t->same('literal-key', $modernLiteral['unMeta']['c']);
    },
    'reads top-level pandoc json MetaMap metadata envelopes without losing literal unMeta keys' => static function (TestRunner $t): void {
        $reader = new PandocJsonReader();
        $writer = new PandocJsonWriter();
        $document = $reader->readPacket([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => ['t' => 'MetaMap', 'c' => [
                'title' => ['t' => 'MetaInlines', 'c' => [
                    ['t' => 'Str', 'c' => 'Envelope'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'Metadata'],
                ]],
                'review' => ['t' => 'MetaMap', 'c' => [
                    'queue' => ['t' => 'MetaString', 'c' => 'json-import'],
                    'flags' => ['t' => 'MetaList', 'c' => [
                        ['t' => 'MetaString', 'c' => 'compat'],
                        ['t' => 'MetaBool', 'c' => true],
                    ]],
                ]],
            ]],
            'blocks' => [],
        ]);

        $meta = $document->attr('meta');
        $encoded = $writer->toArray($document);
        $literalUnMeta = $reader->readPacket([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'unMeta' => [
                    'queue' => 'literal-modern-key',
                    'blocked' => false,
                ],
            ],
            'blocks' => [],
        ])->attr('meta');

        $t->same('inlines', $meta['title']['type']);
        $t->same('Envelope', $meta['title']['children'][0]->attr('text'));
        $t->same('Metadata', $meta['title']['children'][2]->attr('text'));
        $t->same('map', $meta['review']['type']);
        $t->same('json-import', $meta['review']['items']['queue']);
        $t->same(['compat', true], $meta['review']['items']['flags']['items']);
        $t->same('MetaInlines', $encoded['meta']['title']['t']);
        $t->same('MetaMap', $encoded['meta']['review']['t']);
        $t->same('MetaList', $encoded['meta']['review']['c']['flags']['t']);
        $t->same('map', $literalUnMeta['unMeta']['type']);
        $t->same('literal-modern-key', $literalUnMeta['unMeta']['items']['queue']);
        $t->same(false, $literalUnMeta['unMeta']['items']['blocked']);
    },
    'reads tagged pandoc Meta metadata envelopes through json and native readers' => static function (TestRunner $t): void {
        $metaEnvelope = ['t' => 'Meta', 'c' => [
            'unMeta' => [
                'source' => ['t' => 'MetaString', 'c' => 'legacy-meta-constructor'],
                'draft' => ['t' => 'MetaBool', 'c' => false],
                'title' => ['t' => 'MetaInlines', 'c' => [
                    ['t' => 'Str', 'c' => 'Legacy'],
                    ['t' => 'Space'],
                    ['t' => 'Emph', 'c' => [
                        ['t' => 'Str', 'c' => 'metadata'],
                    ]],
                ]],
            ],
        ], 'reviewQueue' => 'meta-envelope-source'];
        $packet = [
            'pandoc-api-version' => [1, 17, 0, 4],
            'meta' => $metaEnvelope,
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Legacy'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'metadata'],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $meta = $document->attr('meta');
            $titleInlines = $meta['titleInlines'] ?? null;
            $provenance = $document->attr('metaConstructorProvenance');

            $t->same('Meta', $document->attr('metaConstructor'), "{$source} records top-level Meta constructor");
            $t->same($metaEnvelope, $document->attr('metaNative'), "{$source} records top-level Meta native payload");
            $t->same('Meta', $provenance['/']['constructor'], "{$source} indexes root Meta constructor provenance");
            $t->same($metaEnvelope, $provenance['/']['native'], "{$source} indexes root Meta native payload");
            $t->same('MetaString', $provenance['/source']['constructor'], "{$source} indexes child MetaString provenance");
            $t->same('MetaInlines', $provenance['/title']['constructor'], "{$source} indexes child MetaInlines provenance");
            $t->same('paragraph', $document->children[0]->type, "{$source} reads body block after Meta envelope");
            $t->same('Legacy metadata', $document->children[0]->attr('text'), "{$source} preserves body text");
            $t->same(true, is_array($titleInlines), "{$source} exposes title helper inlines");

            if ($source === 'json') {
                $t->same('legacy-meta-constructor', $meta['source'], "{$source} unwraps MetaString");
                $t->same(false, $meta['draft'], "{$source} unwraps MetaBool");
                $t->same('inlines', $meta['title']['type'], "{$source} exposes normalized title metadata");
                $t->same('emph', $titleInlines[2]->type, "{$source} preserves title inline formatting");
            } else {
                $t->same($metaEnvelope['c']['unMeta']['source'], $meta['source'], "{$source} keeps native MetaString payload");
                $t->same($metaEnvelope['c']['unMeta']['draft'], $meta['draft'], "{$source} keeps native MetaBool payload");
                $t->same($metaEnvelope['c']['unMeta']['title'], $meta['title'], "{$source} keeps native MetaInlines payload");
                $t->same('Legacy ', $titleInlines[0]->attr('text'), "{$source} exposes title helper text run");
                $t->same('emph', $titleInlines[1]->type, "{$source} exposes title helper emphasis");
            }

            foreach ([
                "{$source} json writer" => (new PandocJsonWriter())->toArray($document),
                "{$source} native writer" => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($metaEnvelope['c']['unMeta'], $encoded['meta'], "{$writer} canonicalizes Meta envelope to metadata map");
                $t->same($packet['blocks'], $encoded['blocks'], "{$writer} preserves body block payload");
                $t->same(false, isset($encoded['meta']['t']), "{$writer} does not re-emit top-level Meta envelope");
            }
        }
    },
    'reads simplified pandoc json metadata values as compatible meta constructors' => static function (TestRunner $t): void {
        $reader = new PandocJsonReader();
        $writer = new PandocJsonWriter();
        $document = $reader->readPacket([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'title' => ['t' => 'MetaInlines', 'c' => [
                    ['t' => 'Str', 'c' => 'Plain'],
                    ['t' => 'Space'],
                    ['t' => 'Emph', 'c' => [
                        ['t' => 'Str', 'c' => 'metadata'],
                    ]],
                ]],
                'source' => 'json-sidecar',
                'draft' => false,
                'priority' => 3,
                'review' => [
                    'queue' => 'wp-import',
                    'nullable' => null,
                    'flags' => ['needs-alt-text', true, 2],
                    'ticket' => ['t' => 'plain-ticket'],
                ],
            ],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Metadata'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'packet'],
                ]],
            ],
        ]);

        $meta = $document->attr('meta');
        $encoded = $writer->toArray($document);
        $roundTrip = $reader->readPacket($encoded);
        $roundTripMeta = $roundTrip->attr('meta');

        $t->same('inlines', $meta['title']['type']);
        $t->same('emph', $meta['title']['children'][2]->type);
        $t->same('json-sidecar', $meta['source']);
        $t->same(false, $meta['draft']);
        $t->same('3', $meta['priority']);
        $t->same('map', $meta['review']['type']);
        $t->same('wp-import', $meta['review']['items']['queue']);
        $t->same('', $meta['review']['items']['nullable']);
        $t->same(['needs-alt-text', true, '2'], $meta['review']['items']['flags']['items']);
        $t->same('plain-ticket', $meta['review']['items']['ticket']['items']['t']);
        $t->same('MetaString', $encoded['meta']['source']['t']);
        $t->same('MetaBool', $encoded['meta']['draft']['t']);
        $t->same('MetaString', $encoded['meta']['priority']['t']);
        $t->same('MetaMap', $encoded['meta']['review']['t']);
        $t->same('MetaList', $encoded['meta']['review']['c']['flags']['t']);
        $t->same('MetaMap', $encoded['meta']['review']['c']['ticket']['t']);
        $t->same('plain-ticket', $roundTripMeta['review']['items']['ticket']['items']['t']);
    },
    'accepts document metadata encoded as a MetaMap envelope' => static function (TestRunner $t): void {
        $reader = new PandocJsonReader();
        $writer = new PandocJsonWriter();
        $document = $reader->readPacket([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                't' => 'MetaMap',
                'c' => [
                    'title' => ['t' => 'MetaInlines', 'c' => [
                        ['t' => 'Str', 'c' => 'Envelope'],
                        ['t' => 'Space'],
                        ['t' => 'Strong', 'c' => [
                            ['t' => 'Str', 'c' => 'metadata'],
                        ]],
                    ]],
                    'review' => ['t' => 'MetaMap', 'c' => [
                        'source' => ['t' => 'MetaString', 'c' => 'meta-map-envelope'],
                        'draft' => ['t' => 'MetaBool', 'c' => false],
                        'tags' => ['t' => 'MetaList', 'c' => [
                            ['t' => 'MetaString', 'c' => 'json'],
                            ['t' => 'MetaString', 'c' => 'compat'],
                        ]],
                    ]],
                ],
            ],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Envelope'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'packet'],
                ]],
            ],
        ]);
        $encoded = $writer->toArray($document);
        $typedMapPacket = $writer->toArray(new AstNode('document', [
            'meta' => ['type' => 'map', 'items' => [
                'source' => 'typed-document-map',
                'draft' => true,
                'tags' => ['type' => 'list', 'items' => ['json', false]],
            ]],
        ]));
        $taggedMapPacket = $writer->toArray(new AstNode('document', [
            'meta' => ['t' => 'MetaMap', 'c' => [
                'source' => ['t' => 'MetaString', 'c' => 'tagged-document-map'],
            ]],
        ]));

        $meta = $document->attr('meta');
        $review = $meta['review']['items'];

        $t->same('inlines', $meta['title']['type']);
        $t->same('strong', $meta['title']['children'][2]->type);
        $t->same('map', $meta['review']['type']);
        $t->same('meta-map-envelope', $review['source']);
        $t->same(false, $review['draft']);
        $t->same(['json', 'compat'], $review['tags']['items']);
        $t->same('MetaInlines', $encoded['meta']['title']['t']);
        $t->same('MetaMap', $encoded['meta']['review']['t']);
        $t->same(false, isset($encoded['meta']['t']));
        $t->same('MetaString', $typedMapPacket['meta']['source']['t']);
        $t->same('MetaBool', $typedMapPacket['meta']['draft']['t']);
        $t->same('MetaList', $typedMapPacket['meta']['tags']['t']);
        $t->same('MetaString', $taggedMapPacket['meta']['source']['t']);
        $t->same('tagged-document-map', $taggedMapPacket['meta']['source']['c']);
    },
    'preserves simplified metadata maps with constructor-like keys' => static function (TestRunner $t): void {
        $reader = new PandocJsonReader();
        $writer = new PandocJsonWriter();
        $document = $reader->readPacket([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'sourcePacket' => [
                    't' => 'review-packet',
                    'c' => [
                        'queue' => 'wp-import',
                        'priority' => 2,
                    ],
                    'status' => 'needs-review',
                ],
                'nested' => [
                    [
                        't' => 'review-note',
                        'c' => 'literal metadata payload',
                    ],
                ],
            ],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Constructor-like'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'metadata'],
                ]],
            ],
        ]);

        $meta = $document->attr('meta');
        $encoded = $writer->toArray($document);
        $roundTrip = $reader->readPacket($encoded)->attr('meta');

        $t->same('map', $meta['sourcePacket']['type']);
        $t->same('review-packet', $meta['sourcePacket']['items']['t']);
        $t->same('map', $meta['sourcePacket']['items']['c']['type']);
        $t->same('wp-import', $meta['sourcePacket']['items']['c']['items']['queue']);
        $t->same('2', $meta['sourcePacket']['items']['c']['items']['priority']);
        $t->same('needs-review', $meta['sourcePacket']['items']['status']);
        $t->same('list', $meta['nested']['type']);
        $t->same('map', $meta['nested']['items'][0]['type']);
        $t->same('review-note', $meta['nested']['items'][0]['items']['t']);
        $t->same('literal metadata payload', $meta['nested']['items'][0]['items']['c']);
        $t->same('MetaMap', $encoded['meta']['sourcePacket']['t']);
        $t->same('MetaMap', $encoded['meta']['sourcePacket']['c']['c']['t']);
        $t->same('review-packet', $roundTrip['sourcePacket']['items']['t']);
        $t->same('literal metadata payload', $roundTrip['nested']['items'][0]['items']['c']);
    },
    'accepts single wrapped pandoc metadata constructor payloads' => static function (TestRunner $t): void {
        $sourceMeta = [
            'source' => ['t' => 'MetaString', 'c' => ['wrapped-source'], 'reviewQueue' => 'source-meta'],
            'draft' => ['t' => 'MetaBool', 'c' => [true], 'reviewQueue' => 'draft-meta'],
            'title' => ['t' => 'MetaInlines', 'c' => [[
                ['t' => 'Str', 'c' => 'Wrapped'],
                ['t' => 'Space'],
                ['t' => 'Emph', 'c' => [
                    ['t' => 'Str', 'c' => 'metadata'],
                ]],
            ]], 'reviewQueue' => 'title-meta'],
            'summary' => ['t' => 'MetaBlocks', 'c' => [[
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Wrapped'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'summary'],
                ]],
            ]], 'reviewQueue' => 'summary-meta'],
            'tags' => ['t' => 'MetaList', 'c' => [[
                ['t' => 'MetaString', 'c' => 'json'],
                ['t' => 'MetaBool', 'c' => false],
            ]], 'reviewQueue' => 'tags-meta'],
            'review' => ['t' => 'MetaMap', 'c' => [[
                'queue' => ['t' => 'MetaString', 'c' => 'wp-import'],
                'ready' => ['t' => 'MetaBool', 'c' => true],
            ]], 'reviewQueue' => 'review-meta'],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => $sourceMeta,
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Wrapped'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'metadata'],
                ]],
            ],
        ];
        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $meta = $document->attr('meta');
            $titleInlines = $meta['titleInlines'] ?? null;
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same(true, is_array($titleInlines), "{$source} exposes title helper inlines");
            $t->same('Wrapped', trim((string) $titleInlines[0]->attr('text')), "{$source} title helper text");
            $t->same('emph', $titleInlines[count($titleInlines) - 1]->type, "{$source} title helper formatting");
            if ($source === 'json') {
                $t->same('wrapped-source', $meta['source'], "{$source} unwraps MetaString payload");
                $t->same(true, $meta['draft'], "{$source} unwraps MetaBool payload");
                $t->same('blocks', $meta['summary']['type'], "{$source} unwraps MetaBlocks payload");
                $t->same(['json', false], $meta['tags']['items'], "{$source} unwraps MetaList payload");
                $t->same('wp-import', $meta['review']['items']['queue'], "{$source} unwraps MetaMap payload");
            } else {
                $t->same($sourceMeta['source'], $meta['source'], "{$source} preserves raw MetaString payload");
                $t->same($sourceMeta['title'], $meta['title'], "{$source} preserves raw MetaInlines payload");
            }

            foreach ([
                "{$source} json writer" => $jsonPacket,
                "{$source} native writer" => $nativePacket,
            ] as $writer => $encoded) {
                $t->same($sourceMeta['source'], $encoded['meta']['source'], "{$writer} preserves wrapped MetaString payload");
                $t->same($sourceMeta['draft'], $encoded['meta']['draft'], "{$writer} preserves wrapped MetaBool payload");
                $t->same($sourceMeta['title'], $encoded['meta']['title'], "{$writer} preserves wrapped MetaInlines payload");
                $t->same($sourceMeta['summary'], $encoded['meta']['summary'], "{$writer} preserves wrapped MetaBlocks payload");
                $t->same($sourceMeta['tags'], $encoded['meta']['tags'], "{$writer} preserves wrapped MetaList payload");
                $t->same($sourceMeta['review'], $encoded['meta']['review'], "{$writer} preserves wrapped MetaMap payload");
            }
        }
    },
    'reads pandoc json metamap envelopes without confusing literal t c metadata records' => static function (TestRunner $t): void {
        $reader = new PandocJsonReader();
        $writer = new PandocJsonWriter();
        $document = $reader->readPacket([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                't' => 'MetaMap',
                'c' => [
                    'title' => ['t' => 'MetaString', 'c' => 'Envelope title'],
                    'reviewRecord' => [
                        't' => 'MetadataStatus',
                        'c' => [
                            'state' => 'queued',
                            'priority' => 2,
                        ],
                    ],
                    'filterRecord' => [
                        't' => 'record',
                        'c' => 'literal-content-field',
                    ],
                ],
            ],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Envelope'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'metadata'],
                ]],
            ],
        ]);
        $legacyDocument = $reader->readPacket([
            'meta' => [
                'unMeta' => ['t' => 'MetaMap', 'c' => [
                    'source' => ['t' => 'MetaString', 'c' => 'legacy-metamap-envelope'],
                ]],
            ],
            'blocks' => [],
        ]);
        $literalUnMetaDocument = $reader->readPacket([
            'meta' => [
                'unMeta' => [
                    't' => 'record',
                    'c' => 'literal-unmeta-key',
                ],
            ],
            'blocks' => [],
        ]);

        $meta = $document->attr('meta');
        $reviewRecord = $meta['reviewRecord'];
        $filterRecord = $meta['filterRecord'];
        $encoded = $writer->toArray($document);
        $roundTripMeta = $reader->readPacket($encoded)->attr('meta');
        $literalUnMeta = $literalUnMetaDocument->attr('meta')['unMeta'];

        $t->same('Envelope title', $meta['title']);
        $t->same('map', $reviewRecord['type']);
        $t->same('MetadataStatus', $reviewRecord['items']['t']);
        $t->same('map', $reviewRecord['items']['c']['type']);
        $t->same('queued', $reviewRecord['items']['c']['items']['state']);
        $t->same('2', $reviewRecord['items']['c']['items']['priority']);
        $t->same('map', $filterRecord['type']);
        $t->same('record', $filterRecord['items']['t']);
        $t->same('literal-content-field', $filterRecord['items']['c']);
        $t->same('legacy-metamap-envelope', $legacyDocument->attr('meta')['source']);
        $t->same('map', $literalUnMeta['type']);
        $t->same('record', $literalUnMeta['items']['t']);
        $t->same('literal-unmeta-key', $literalUnMeta['items']['c']);
        $t->same('MetaString', $encoded['meta']['title']['t']);
        $t->same('MetaMap', $encoded['meta']['reviewRecord']['t']);
        $t->same('MetaMap', $encoded['meta']['reviewRecord']['c']['c']['t']);
        $t->same('MetadataStatus', $roundTripMeta['reviewRecord']['items']['t']);
        $t->same('literal-content-field', $roundTripMeta['filterRecord']['items']['c']);
    },
    'reads legacy nested pandoc json MetaMap unMeta wrappers as compatible maps' => static function (TestRunner $t): void {
        $reader = new PandocJsonReader();
        $writer = new PandocJsonWriter();
        $document = $reader->readPacket([
            'pandoc-api-version' => [1, 17, 0, 4],
            'meta' => [
                'review' => ['t' => 'MetaMap', 'c' => [
                    'unMeta' => [
                        'queue' => ['t' => 'MetaString', 'c' => 'legacy-json-filter'],
                        'flags' => ['t' => 'MetaList', 'c' => [
                            ['t' => 'MetaString', 'c' => 'needs-review'],
                            ['t' => 'MetaBool', 'c' => true],
                        ]],
                        'nested' => ['t' => 'MetaMap', 'c' => [
                            'unMeta' => [
                                'owner' => ['t' => 'MetaInlines', 'c' => [
                                    ['t' => 'Str', 'c' => 'WordPress'],
                                    ['t' => 'Space'],
                                    ['t' => 'Str', 'c' => 'review'],
                                ]],
                                'blocked' => ['t' => 'MetaBool', 'c' => false],
                            ],
                        ]],
                    ],
                ]],
            ],
            'blocks' => [],
        ]);

        $meta = $document->attr('meta');
        $review = $meta['review'];
        $encoded = $writer->toArray($document);
        $roundTrip = $reader->readPacket($encoded)->attr('meta')['review'];
        $legacyTaggedPacket = $writer->toArray(new AstNode('document', [
            'meta' => [
                'review' => ['t' => 'MetaMap', 'c' => [
                    'unMeta' => [
                        'queue' => ['t' => 'MetaString', 'c' => 'writer-legacy-packet'],
                    ],
                ]],
            ],
        ]));

        $t->same([1, 17, 0, 4], $document->attr('pandocApiVersion'));
        $t->same('map', $review['type']);
        $t->same('legacy-json-filter', $review['items']['queue']);
        $t->same(['needs-review', true], $review['items']['flags']['items']);
        $t->same('map', $review['items']['nested']['type']);
        $t->same('inlines', $review['items']['nested']['items']['owner']['type']);
        $t->same('review', $review['items']['nested']['items']['owner']['children'][2]->attr('text'));
        $t->same(false, $review['items']['nested']['items']['blocked']);
        $t->same('MetaMap', $encoded['meta']['review']['t']);
        $t->true(!array_key_exists('unMeta', $encoded['meta']['review']['c']), 'Nested legacy MetaMap wrappers should re-emit as canonical map content');
        $t->same('MetaString', $encoded['meta']['review']['c']['queue']['t']);
        $t->same('MetaMap', $encoded['meta']['review']['c']['nested']['t']);
        $t->true(!array_key_exists('unMeta', $encoded['meta']['review']['c']['nested']['c']), 'Nested MetaMap wrapper should be canonicalized');
        $t->same('legacy-json-filter', $roundTrip['items']['queue']);
        $t->same('MetaMap', $legacyTaggedPacket['meta']['review']['t']);
        $t->true(!array_key_exists('unMeta', $legacyTaggedPacket['meta']['review']['c']), 'Pre-tagged writer metadata should canonicalize legacy MetaMap wrappers');
        $t->same('writer-legacy-packet', $legacyTaggedPacket['meta']['review']['c']['queue']['c']);
    },
    'reads legacy top-level pandoc json MetaMap unMeta wrappers as document metadata' => static function (TestRunner $t): void {
        $reader = new PandocJsonReader();
        $writer = new PandocJsonWriter();
        $document = $reader->readPacket([
            'pandoc-api-version' => [1, 17, 0, 4],
            'meta' => ['t' => 'MetaMap', 'c' => [
                'unMeta' => [
                    'source' => ['t' => 'MetaString', 'c' => 'legacy-top-level-filter'],
                    'draft' => ['t' => 'MetaBool', 'c' => false],
                    'review' => ['t' => 'MetaMap', 'c' => [
                        'unMeta' => [
                            'queue' => ['t' => 'MetaString', 'c' => 'wp-import'],
                            'tags' => ['t' => 'MetaList', 'c' => [
                                ['t' => 'MetaString', 'c' => 'json'],
                                ['t' => 'MetaString', 'c' => 'legacy'],
                            ]],
                        ],
                    ]],
                ],
            ]],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Legacy'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'metadata'],
                ]],
            ],
        ]);

        $meta = $document->attr('meta');
        $encoded = $writer->toArray($document);
        $roundTripMeta = $reader->readPacket($encoded)->attr('meta');

        $t->same('legacy-top-level-filter', $meta['source']);
        $t->same(false, $meta['draft']);
        $t->same('map', $meta['review']['type']);
        $t->same('wp-import', $meta['review']['items']['queue']);
        $t->same(['json', 'legacy'], $meta['review']['items']['tags']['items']);
        $t->same('paragraph', $document->children[0]->type);
        $t->same('MetaString', $encoded['meta']['source']['t']);
        $t->same('MetaMap', $encoded['meta']['review']['t']);
        $t->true(!array_key_exists('unMeta', $encoded['meta']), 'Top-level legacy MetaMap wrapper should re-emit canonical document metadata');
        $t->same('legacy-top-level-filter', $roundTripMeta['source']);
        $t->same('wp-import', $roundTripMeta['review']['items']['queue']);
    },
    'writes shared ast documents as pandoc json filter exchange shape' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [
                'title' => ['type' => 'inlines', 'children' => [
                    new AstNode('text', ['text' => 'Review']),
                    new AstNode('space'),
                    new AstNode('emph', [], [new AstNode('text', ['text' => 'Packet'])]),
                ]],
                'draft' => true,
                'source' => 'batch-42',
            ],
        ], [
            new AstNode('heading', [
                'level' => 2,
                'id' => 'review-packet',
                'classes' => ['wp-import'],
                'attributes' => ['data-source' => 'batch-42'],
            ], [
                new AstNode('text', ['text' => 'Review']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'Packet']),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Archive']),
                new AstNode('space'),
                new AstNode('link', [
                    'url' => 'https://example.test/source',
                    'title' => 'Source title',
                    'classes' => ['source-link'],
                    'attributes' => ['data-source' => 'source'],
                ], [new AstNode('text', ['text' => 'source'])]),
            ]),
        ]);

        $packet = (new PandocJsonWriter())->toArray($document);

        $t->same([1, 23, 1], $packet['pandoc-api-version']);
        $t->same('MetaInlines', $packet['meta']['title']['t']);
        $t->same('MetaBool', $packet['meta']['draft']['t']);
        $t->same('MetaString', $packet['meta']['source']['t']);
        $t->same('Header', $packet['blocks'][0]['t']);
        $t->same([2, ['review-packet', ['wp-import'], [['data-source', 'batch-42']]], [
            ['t' => 'Str', 'c' => 'Review'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'Packet'],
        ]], $packet['blocks'][0]['c']);
        $t->same('Para', $packet['blocks'][1]['t']);
        $t->same('Link', $packet['blocks'][1]['c'][2]['t']);
        $t->same(['https://example.test/source', 'Source title'], $packet['blocks'][1]['c'][2]['c'][2]);
        $t->same(['', ['source-link'], [['data-source', 'source']]], $packet['blocks'][1]['c'][2]['c'][0]);
    },
    'normalizes standard pandoc json metadata helpers without leaking lane helper fields' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'title' => ['t' => 'MetaInlines', 'c' => [
                    ['t' => 'Str', 'c' => 'JSON'],
                    ['t' => 'Space'],
                    ['t' => 'Emph', 'c' => [
                        ['t' => 'Str', 'c' => 'Metadata'],
                    ]],
                ]],
                'author' => ['t' => 'MetaList', 'c' => [
                    ['t' => 'MetaInlines', 'c' => [
                        ['t' => 'Str', 'c' => 'Data'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'Team'],
                    ]],
                    ['t' => 'MetaInlines', 'c' => [
                        ['t' => 'Str', 'c' => 'Reviewer'],
                    ]],
                ]],
                'date' => ['t' => 'MetaInlines', 'c' => [
                    ['t' => 'Str', 'c' => '2026-06-09'],
                ]],
                'source' => ['t' => 'MetaString', 'c' => 'metadata-fixture'],
            ],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Body'],
                ]],
            ],
        ];

        $document = (new PandocJsonReader())->readPacket($packet);
        $meta = $document->attr('meta');

        $t->same('inlines', $meta['title']['type']);
        $t->same('emph', $meta['titleInlines'][2]->type);
        $t->same('Data', $meta['authorInlines'][0][0]->attr('text'));
        $t->same('Team', $meta['authorInlines'][0][2]->attr('text'));
        $t->same('Reviewer', $meta['authorInlines'][1][0]->attr('text'));
        $t->same('2026-06-09', $meta['dateInlines'][0]->attr('text'));

        $encoded = (new PandocJsonWriter())->toArray(new AstNode('document', [
            'meta' => [
                'title' => 'Fallback title',
                'titleInlines' => $meta['titleInlines'],
                'author' => ['Fallback Author'],
                'authors' => ['Fallback Author'],
                'authorInlines' => $meta['authorInlines'],
                'date' => 'fallback-date',
                'dateInlines' => $meta['dateInlines'],
                'source' => 'metadata-fixture',
            ],
        ], [
            new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Body'])]),
        ]));

        $t->same('MetaInlines', $encoded['meta']['title']['t']);
        $t->same('Emph', $encoded['meta']['title']['c'][2]['t']);
        $t->same('MetaList', $encoded['meta']['author']['t']);
        $t->same('Data', $encoded['meta']['author']['c'][0]['c'][0]['c']);
        $t->same('Reviewer', $encoded['meta']['author']['c'][1]['c'][0]['c']);
        $t->same('MetaInlines', $encoded['meta']['date']['t']);
        $t->same('2026-06-09', $encoded['meta']['date']['c'][0]['c']);
        $t->same('MetaString', $encoded['meta']['source']['t']);
        $t->same(false, array_key_exists('titleInlines', $encoded['meta']));
        $t->same(false, array_key_exists('authorInlines', $encoded['meta']));
        $t->same(false, array_key_exists('authors', $encoded['meta']));
        $t->same(false, array_key_exists('dateInlines', $encoded['meta']));
    },
    'writes pre-tagged pandoc json metadata values as compatible constructors' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'meta' => [
                'title' => ['t' => 'MetaInlines', 'c' => [
                    ['t' => 'Str', 'c' => 'Tagged'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'Metadata'],
                ]],
                'review' => ['t' => 'MetaMap', 'c' => [
                    'draft' => ['t' => 'MetaBool', 'c' => false],
                    'aliases' => ['t' => 'MetaList', 'c' => [
                        ['t' => 'MetaString', 'c' => 'json-filter'],
                        ['t' => 'MetaString', 'c' => 'legacy-packet'],
                    ]],
                    'body' => ['t' => 'MetaBlocks', 'c' => [
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Str', 'c' => 'Reviewer'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'note'],
                        ]],
                    ]],
                ]],
            ],
        ], [
            new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Packet'])]),
        ]);

        $writer = new PandocJsonWriter();
        $packet = $writer->toArray($document);
        $roundTrip = (new PandocJsonReader())->readPacket($packet);
        $meta = $roundTrip->attr('meta');

        $t->same('MetaInlines', $packet['meta']['title']['t']);
        $t->same('Tagged', $packet['meta']['title']['c'][0]['c']);
        $t->same('MetaMap', $packet['meta']['review']['t']);
        $t->same('MetaBool', $packet['meta']['review']['c']['draft']['t']);
        $t->same('MetaList', $packet['meta']['review']['c']['aliases']['t']);
        $t->same('MetaBlocks', $packet['meta']['review']['c']['body']['t']);
        $t->same('inlines', $meta['title']['type']);
        $t->same('Metadata', $meta['title']['children'][2]->attr('text'));
        $t->same(false, $meta['review']['items']['draft']);
        $t->same(['json-filter', 'legacy-packet'], $meta['review']['items']['aliases']['items']);
        $t->same('paragraph', $meta['review']['items']['body']['children'][0]->type);
    },
    'preserves direct pre-tagged metadata payloads through json and native writers until edited' => static function (TestRunner $t): void {
        $titleNative = ['t' => 'MetaString', 'c' => 'Direct title', 'reviewQueue' => 'title-direct'];
        $reviewNative = ['t' => 'MetaMap', 'c' => [
            'status' => ['t' => 'MetaString', 'c' => 'queued', 'reviewQueue' => 'status-direct'],
            'draft' => ['t' => 'MetaBool', 'c' => false],
        ], 'reviewQueue' => 'review-direct'];
        $bodyNative = ['t' => 'MetaBlocks', 'c' => [
            ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Direct'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'body'],
            ], 'reviewQueue' => 'body-plain-direct'],
        ], 'reviewQueue' => 'body-direct'];
        $document = new AstNode('document', [
            'meta' => [
                'title' => $titleNative,
                'review' => $reviewNative,
                'body' => $bodyNative,
            ],
        ]);

        $packets = [
            'json' => (new PandocJsonWriter())->toArray($document),
            'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
        ];

        foreach ($packets as $writer => $packet) {
            $t->same($titleNative, $packet['meta']['title'], "{$writer} writer preserves direct title metadata payload");
            $t->same($reviewNative, $packet['meta']['review'], "{$writer} writer preserves direct map metadata payload");
            $t->same($bodyNative, $packet['meta']['body'], "{$writer} writer preserves direct block metadata payload");
        }

        $roundTrip = (new PandocJsonReader())->readPacket($packets['json'])->attr('meta');
        $t->same('Direct title', $roundTrip['title']);
        $t->same('queued', $roundTrip['review']['items']['status']);
        $t->same('Direct', $roundTrip['body']['children'][0]->children[0]->attr('text'));

        $editedDocument = new AstNode('document', [
            'meta' => [
                'title' => 'Edited title',
                'review' => $reviewNative,
                'body' => $bodyNative,
            ],
        ]);
        $editedPackets = [
            'json' => (new PandocJsonWriter())->toArray($editedDocument),
            'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
        ];

        foreach ($editedPackets as $writer => $packet) {
            $t->same(['t' => 'MetaString', 'c' => 'Edited title'], $packet['meta']['title'], "{$writer} writer regenerates edited direct title metadata");
            $t->same(false, array_key_exists('reviewQueue', $packet['meta']['title']), "{$writer} writer drops stale direct title sidecar");
            $t->same($reviewNative, $packet['meta']['review'], "{$writer} writer keeps unchanged direct map metadata payload");
            $t->same($bodyNative, $packet['meta']['body'], "{$writer} writer keeps unchanged direct block metadata payload");
        }
    },
    'emits native json for direct pre-tagged metadata constructors without document sidecars' => static function (TestRunner $t): void {
        $statusNative = ['t' => 'MetaString', 'c' => 'queued', 'reviewQueue' => 'status-source'];
        $aliasesNative = ['t' => 'MetaList', 'c' => [
            ['t' => 'MetaString', 'c' => 'json-native'],
            ['t' => 'MetaBool', 'c' => true],
        ], 'reviewQueue' => 'aliases-source'];
        $reviewNative = ['t' => 'MetaMap', 'c' => [
            'status' => $statusNative,
            'aliases' => $aliasesNative,
        ], 'reviewQueue' => 'review-source'];
        $document = new AstNode('document', [
            'meta' => [
                'review' => $reviewNative,
            ],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Constructor']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'metadata']),
            ]),
        ]);

        $nativeJson = (new NativeWriter())->write($document);
        $packet = json_decode($nativeJson, true, 512, JSON_THROW_ON_ERROR);
        $jsonDocument = (new PandocJsonReader())->readPacket($packet);
        $nativeDocument = (new NativeReader())->read($nativeJson);

        $t->same([1, 23, 1], $packet['pandoc-api-version']);
        $t->same($reviewNative, $packet['meta']['review']);
        $t->same('Para', $packet['blocks'][0]['t']);
        $t->same('Constructor', $packet['blocks'][0]['c'][0]['c']);
        $t->same('queued', $jsonDocument->attr('meta')['review']['items']['status'] ?? null);
        $t->same(['json-native', true], $jsonDocument->attr('meta')['review']['items']['aliases']['items'] ?? null);
        $t->same($reviewNative, $nativeDocument->attr('meta')['review'] ?? null);
        $t->same('Constructor metadata', $nativeDocument->children[0]->attr('text'));
    },
    'reads constructorless json-compatible metadata through json and native ast readers' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'title' => 'Constructorless title',
                'draft' => false,
                'priority' => 7,
                'empty' => null,
                'review' => [
                    'queue' => 'native-json',
                    'flags' => ['core', true],
                    'details' => [
                        'format' => 'json-native',
                    ],
                ],
            ],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Metadata'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'packet'],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $meta = $document->attr('meta');
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
            $jsonRoundTripMeta = (new PandocJsonReader())->readPacket($jsonPacket)->attr('meta');
            $nativeRoundTripMeta = (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR))->attr('meta');

            $t->same('Constructorless title', $meta['title'], "{$source} reads plain string metadata");
            $t->same(false, $meta['draft'], "{$source} reads plain bool metadata");
            $t->same('7', $meta['priority'], "{$source} normalizes numeric metadata as string");
            $t->same('', $meta['empty'], "{$source} normalizes null metadata as empty string");
            $t->same('map', $meta['review']['type'], "{$source} reads plain map metadata");
            $t->same('native-json', $meta['review']['items']['queue'], "{$source} reads nested map scalar metadata");
            $t->same('list', $meta['review']['items']['flags']['type'], "{$source} reads nested list metadata");
            $t->same(['core', true], $meta['review']['items']['flags']['items'], "{$source} preserves nested list values");
            $t->same('json-native', $meta['review']['items']['details']['items']['format'], "{$source} reads nested map values");
            $t->same('paragraph', $document->children[0]->type, "{$source} reads block payload with constructorless metadata");

            $t->same($jsonPacket['meta'], $nativePacket['meta'], "{$source} writers agree on regenerated metadata constructors");
            $t->same('MetaString', $jsonPacket['meta']['title']['t'], "{$source} writer emits title constructor");
            $t->same('Constructorless title', $jsonPacket['meta']['title']['c'], "{$source} writer emits title value");
            $t->same('MetaBool', $jsonPacket['meta']['draft']['t'], "{$source} writer emits bool constructor");
            $t->same(false, $jsonPacket['meta']['draft']['c'], "{$source} writer emits bool value");
            $t->same('MetaString', $jsonPacket['meta']['priority']['t'], "{$source} writer emits numeric metadata as string constructor");
            $t->same('7', $jsonPacket['meta']['priority']['c'], "{$source} writer emits numeric metadata as string");
            $t->same('MetaString', $jsonPacket['meta']['empty']['t'], "{$source} writer emits null metadata as string constructor");
            $t->same('', $jsonPacket['meta']['empty']['c'], "{$source} writer emits null metadata as empty string");
            $t->same('MetaMap', $jsonPacket['meta']['review']['t'], "{$source} writer emits map constructor");
            $t->same('MetaList', $jsonPacket['meta']['review']['c']['flags']['t'], "{$source} writer emits nested list constructor");
            $t->same('MetaString', $jsonPacket['meta']['review']['c']['flags']['c'][0]['t'], "{$source} writer emits nested string constructor");
            $t->same('MetaBool', $jsonPacket['meta']['review']['c']['flags']['c'][1]['t'], "{$source} writer emits nested bool constructor");

            $t->same('Constructorless title', $jsonRoundTripMeta['title'], "{$source} json round trip unwraps regenerated title");
            $t->same('native-json', $jsonRoundTripMeta['review']['items']['queue'], "{$source} json round trip unwraps nested map");
            $t->same('MetaString', $nativeRoundTripMeta['title']['t'], "{$source} native round trip preserves regenerated title constructor");
            $t->same('MetaMap', $nativeRoundTripMeta['review']['t'], "{$source} native round trip preserves regenerated map constructor");
        }
    },
    'reuses native wrapper payloads when derived helper sidecars are absent' => static function (TestRunner $t): void {
        $formatNative = ['t' => 'Format', 'c' => 'html', 'reviewQueue' => 'format-source'];
        $linkAttr = ['source-link', ['review'], [['data-source', 'json-native']]];
        $targetNative = ['https://example.test/source', 'Source title', 'target-sidecar'];
        $rawInline = ['t' => 'RawInline', 'c' => [$formatNative, '<span>ok</span>'], 'reviewQueue' => 'raw-source'];
        $linkInline = ['t' => 'Link', 'c' => [
            $linkAttr,
            [['t' => 'Str', 'c' => 'source']],
            $targetNative,
        ], 'reviewQueue' => 'link-source'];
        $paragraphNative = ['t' => 'Para', 'c' => [
            ['t' => 'Str', 'c' => 'Keep'],
            ['t' => 'Space'],
            $rawInline,
            ['t' => 'Space'],
            $linkInline,
        ], 'reviewQueue' => 'paragraph-source'];

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [
                'constructor' => 'Para',
                'native' => $paragraphNative,
            ], [
                new AstNode('text', ['text' => 'Keep']),
                new AstNode('space'),
                new AstNode('raw_html_inline', ['format' => 'html', 'text' => '<span>ok</span>', 'html' => '<span>ok</span>']),
                new AstNode('space'),
                new AstNode('link', [
                    'id' => 'source-link',
                    'classes' => ['review'],
                    'attributes' => ['data-source' => 'json-native'],
                    'url' => 'https://example.test/source',
                    'title' => 'Source title',
                ], [
                    new AstNode('text', ['text' => 'source']),
                ]),
            ]),
        ]);

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($document)['blocks'][0],
            'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR)['blocks'][0],
        ] as $writer => $encoded) {
            $t->same($paragraphNative, $encoded, "{$writer} writer reuses wrapper native payload");
            $t->same('paragraph-source', $encoded['reviewQueue'], "{$writer} writer keeps wrapper sidecar");
            $t->same('format-source', $encoded['c'][2]['c'][0]['reviewQueue'], "{$writer} writer keeps raw format sidecar");
            $t->same('target-sidecar', $encoded['c'][4]['c'][2][2], "{$writer} writer keeps target tuple sidecar");
        }

        $editedDocument = new AstNode('document', [], [
            new AstNode('paragraph', [
                'constructor' => 'Para',
                'native' => $paragraphNative,
            ], [
                new AstNode('text', ['text' => 'Keep']),
                new AstNode('space'),
                new AstNode('raw_html_inline', ['format' => 'html', 'text' => '<span>edited</span>', 'html' => '<span>edited</span>']),
                new AstNode('space'),
                new AstNode('link', [
                    'id' => 'source-link',
                    'classes' => ['review'],
                    'attributes' => ['data-source' => 'json-native'],
                    'url' => 'https://example.test/source',
                    'title' => 'Source title',
                ], [
                    new AstNode('text', ['text' => 'source']),
                ]),
            ]),
        ]);

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($editedDocument)['blocks'][0],
            'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR)['blocks'][0],
        ] as $writer => $encoded) {
            $t->same('Para', $encoded['t'], "{$writer} writer regenerates edited paragraph constructor");
            $t->same(false, array_key_exists('reviewQueue', $encoded), "{$writer} writer drops stale wrapper sidecar");
            $t->same('<span>edited</span>', $encoded['c'][2]['c'][1], "{$writer} writer emits edited raw payload");
            $t->same(false, array_key_exists('reviewQueue', $encoded['c'][2]), "{$writer} writer drops stale raw wrapper sidecar");
        }
    },
    'indexes pandoc metadata constructors for json and native review provenance' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'title' => ['t' => 'MetaInlines', 'c' => [
                    ['t' => 'Str', 'c' => 'Constructor'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'metadata'],
                ]],
                'draft' => ['t' => 'MetaBool', 'c' => false],
                'review' => ['t' => 'MetaMap', 'c' => [
                    'aliases' => ['t' => 'MetaList', 'c' => [
                        ['t' => 'MetaString', 'c' => 'json-filter'],
                        ['t' => 'MetaBool', 'c' => true],
                    ]],
                    'body' => ['t' => 'MetaBlocks', 'c' => [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Reviewer'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'note'],
                        ]],
                    ]],
                    'inline' => ['t' => 'MetaInlines', 'c' => [
                        ['t' => 'Code', 'c' => [['meta-code', ['php'], [['data-review', 'meta']]], 'wp_insert_post']],
                    ]],
                ]],
            ],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Metadata'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'provenance'],
                ]],
            ],
        ];
        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $provenance = $document->attr('metaConstructorProvenance');

            $t->same('MetaInlines', $provenance['/title']['constructor'], "{$source} title constructor");
            $t->same($packet['meta']['title'], $provenance['/title']['native'], "{$source} title native payload");
            $t->same('MetaBool', $provenance['/draft']['constructor'], "{$source} draft constructor");
            $t->same(false, $provenance['/draft']['native']['c'], "{$source} draft native boolean");
            $t->same('MetaMap', $provenance['/review']['constructor'], "{$source} review map constructor");
            $t->same('MetaList', $provenance['/review/aliases']['constructor'], "{$source} aliases list constructor");
            $t->same('MetaString', $provenance['/review/aliases/0']['constructor'], "{$source} alias string constructor");
            $t->same('json-filter', $provenance['/review/aliases/0']['native']['c'], "{$source} alias string native value");
            $t->same('MetaBool', $provenance['/review/aliases/1']['constructor'], "{$source} alias bool constructor");
            $t->same(true, $provenance['/review/aliases/1']['native']['c'], "{$source} alias bool native value");
            $t->same('MetaBlocks', $provenance['/review/body']['constructor'], "{$source} body blocks constructor");
            $t->same('Plain', $provenance['/review/body']['native']['c'][0]['t'], "{$source} body block native payload");
            $t->same('MetaInlines', $provenance['/review/inline']['constructor'], "{$source} inline metadata constructor");
            $t->same('Code', $provenance['/review/inline']['native']['c'][0]['t'], "{$source} inline native payload");
        }

        $jsonMeta = $documents['json']->attr('meta');
        $nativeMeta = $documents['native']->attr('meta');
        $jsonPacket = (new PandocJsonWriter())->toArray($documents['json']);
        $nativePacket = json_decode((new NativeWriter())->write($documents['native']), true, 512, JSON_THROW_ON_ERROR);

        $t->same('inlines', $jsonMeta['title']['type']);
        $t->same(false, $jsonMeta['draft']);
        $t->same('list', $jsonMeta['review']['items']['aliases']['type']);
        $t->same('blocks', $jsonMeta['review']['items']['body']['type']);
        $t->same('inlines', $jsonMeta['review']['items']['inline']['type']);
        $t->same('MetaInlines', $nativeMeta['title']['t']);
        $t->same('MetaMap', $nativeMeta['review']['t']);
        $t->same('MetaBlocks', $jsonPacket['meta']['review']['c']['body']['t']);
        $t->same('MetaBlocks', $nativePacket['meta']['review']['c']['body']['t']);
        $t->same('Code', $jsonPacket['meta']['review']['c']['inline']['c'][0]['t']);
        $t->same('Code', $nativePacket['meta']['review']['c']['inline']['c'][0]['t']);
    },
    'round trips core inline constructors through pandoc json' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'A']),
                new AstNode('space'),
                new AstNode('emph', [], [new AstNode('text', ['text' => 'em'])]),
                new AstNode('strong', [], [new AstNode('text', ['text' => 'strong'])]),
                new AstNode('underline', [], [new AstNode('text', ['text' => 'under'])]),
                new AstNode('strikeout', [], [new AstNode('text', ['text' => 'old'])]),
                new AstNode('superscript', [], [new AstNode('text', ['text' => '2'])]),
                new AstNode('subscript', [], [new AstNode('text', ['text' => 'n'])]),
                new AstNode('small_caps', [], [new AstNode('text', ['text' => 'caps'])]),
                new AstNode('quoted', ['kind' => 'double'], [new AstNode('text', ['text' => 'quote'])]),
                new AstNode('code', ['text' => 'wp_insert_post', 'classes' => ['php']]),
                new AstNode('math', ['display' => true, 'text' => 'E = mc^2']),
                new AstNode('raw_markdown', ['format' => 'markdown+tex_math_dollars', 'text' => '$raw$']),
                new AstNode('linebreak'),
                new AstNode('softbreak'),
                new AstNode('span', ['id' => 'source-span'], [new AstNode('text', ['text' => 'span'])]),
            ]),
        ]);

        $roundTrip = (new PandocJsonReader())->readPacket((new PandocJsonWriter())->toArray($document));
        $children = $roundTrip->children[0]->children;

        $t->same([
            'text',
            'space',
            'emph',
            'strong',
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'small_caps',
            'quoted',
            'code',
            'math',
            'raw_markdown',
            'linebreak',
            'softbreak',
            'span',
        ], array_map(static fn (AstNode $node): string => $node->type, $children));
        $t->same('double', $children[9]->attr('kind'));
        $t->same(['php'], $children[10]->attr('classes'));
        $t->same(true, $children[11]->attr('display'));
        $t->same('markdown+tex_math_dollars', $children[12]->attr('format'));
        $t->same('source-span', $children[15]->attr('id'));
    },
    'preserves styled inline constructor sidecars through json and native readers' => static function (TestRunner $t): void {
        $cases = [
            ['type' => 'emph', 'text' => 'emphasis', 'native' => ['t' => 'Emph', 'c' => [['t' => 'Str', 'c' => 'emphasis']], 'reviewQueue' => 'emphasis-source']],
            ['type' => 'strong', 'text' => 'strong', 'native' => ['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'strong']], 'reviewQueue' => 'strong-source']],
            ['type' => 'underline', 'text' => 'underline', 'native' => ['t' => 'Underline', 'c' => [['t' => 'Str', 'c' => 'underline']], 'reviewQueue' => 'underline-source']],
            ['type' => 'strikeout', 'text' => 'strikeout', 'native' => ['t' => 'Strikeout', 'c' => [['t' => 'Str', 'c' => 'strikeout']], 'reviewQueue' => 'strikeout-source']],
            ['type' => 'superscript', 'text' => 'super', 'native' => ['t' => 'Superscript', 'c' => [['t' => 'Str', 'c' => 'super']], 'reviewQueue' => 'superscript-source']],
            ['type' => 'subscript', 'text' => 'sub', 'native' => ['t' => 'Subscript', 'c' => [['t' => 'Str', 'c' => 'sub']], 'reviewQueue' => 'subscript-source']],
            ['type' => 'small_caps', 'text' => 'caps', 'native' => ['t' => 'SmallCaps', 'c' => [['t' => 'Str', 'c' => 'caps']], 'reviewQueue' => 'small-caps-source']],
        ];
        $sourceInlines = array_map(static fn (array $case): array => $case['native'], $cases);
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => $sourceInlines, 'reviewQueue' => 'styled-paragraph-source'],
            ],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $paragraph = $document->children[0];

            $t->same(array_column($cases, 'type'), array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), "{$source} styled inline node types");
            foreach ($cases as $index => $case) {
                $node = $paragraph->children[$index];
                $text = $node->children[0] ?? null;

                $t->same($case['native']['t'], $node->attr('constructor'), "{$source} {$case['type']} constructor");
                $t->same($case['native'], $node->attr('native'), "{$source} {$case['type']} native payload");
                $t->same('text', $text instanceof AstNode ? $text->type : null, "{$source} {$case['type']} child type");
                $t->same($case['text'], $text instanceof AstNode ? $text->attr('text') : null, "{$source} {$case['type']} child text");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($packet['blocks'], $encoded['blocks'], "{$source} {$writer} writer preserves styled paragraph wrapper");
            }

            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], $paragraph->children),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same('Para', $encoded['blocks'][0]['t'], "{$source} {$writer} writer regenerates rebuilt paragraph constructor");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} {$writer} writer drops stale rebuilt paragraph sidecar");
                $t->same($sourceInlines, $encoded['blocks'][0]['c'], "{$source} {$writer} writer preserves rebuilt styled inline sidecars");
            }
        }
    },
    'accepts single wrapped constructor list payloads through json and native readers' => static function (TestRunner $t): void {
        $inlineCases = [
            ['constructor' => 'Emph', 'type' => 'emph', 'text' => 'emphasis'],
            ['constructor' => 'Strong', 'type' => 'strong', 'text' => 'strong'],
            ['constructor' => 'Underline', 'type' => 'underline', 'text' => 'underline'],
            ['constructor' => 'Strikeout', 'type' => 'strikeout', 'text' => 'strikeout'],
            ['constructor' => 'Superscript', 'type' => 'superscript', 'text' => 'super'],
            ['constructor' => 'Subscript', 'type' => 'subscript', 'text' => 'sub'],
            ['constructor' => 'SmallCaps', 'type' => 'small_caps', 'text' => 'caps'],
            [
                'constructor' => 'Span',
                'type' => 'span',
                'text' => 'span',
                'native' => ['t' => 'Span', 'c' => [
                    ['wrapped-span', ['review'], [['data-kind', 'single-wrap']]],
                    [[['t' => 'Str', 'c' => 'span']]],
                ], 'reviewQueue' => 'span-source'],
            ],
        ];
        foreach ($inlineCases as &$case) {
            $case['native'] ??= [
                't' => $case['constructor'],
                'c' => [[['t' => 'Str', 'c' => $case['text']]]],
                'reviewQueue' => strtolower($case['constructor']) . '-single-wrap-source',
            ];
        }
        unset($case);

        $sourceInlines = array_map(static fn (array $case): array => $case['native'], $inlineCases);
        $noteNative = ['t' => 'Note', 'c' => [[
            ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'noted'],
            ]],
        ]], 'noteLabel' => 'wrapped-note', 'reviewQueue' => 'note-source'];
        $blockquoteNative = ['t' => 'BlockQuote', 'c' => [[
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'quoted'],
            ]],
        ]], 'reviewQueue' => 'quote-source'];
        $paragraphNative = ['t' => 'Para', 'c' => [...$sourceInlines, $noteNative], 'reviewQueue' => 'paragraph-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$paragraphNative, $blockquoteNative],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $paragraph = $document->children[0];
            $blockquote = $document->children[1];
            $note = $paragraph->children[count($inlineCases)];

            $t->same([...array_column($inlineCases, 'type'), 'note'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), "{$source} normalizes single wrapped inline constructor payloads");
            foreach ($inlineCases as $index => $case) {
                $node = $paragraph->children[$index];
                $text = $node->children[0] ?? null;

                $t->same($case['constructor'], $node->attr('constructor'), "{$source} {$case['constructor']} constructor");
                $t->same($case['native'], $node->attr('native'), "{$source} {$case['constructor']} keeps single wrapped native payload");
                $t->same('text', $text instanceof AstNode ? $text->type : null, "{$source} {$case['constructor']} child type");
                $t->same($case['text'], $text instanceof AstNode ? $text->attr('text') : null, "{$source} {$case['constructor']} child text");
            }
            $t->same('wrapped-span', $paragraph->children[7]->attr('id'), "{$source} span attr id");
            $t->same(['review'], $paragraph->children[7]->attr('classes'), "{$source} span attr classes");
            $t->same(['data-kind' => 'single-wrap'], $paragraph->children[7]->attr('attributes'), "{$source} span attr map");
            $t->same('Note', $note->attr('constructor'), "{$source} note constructor");
            $t->same($noteNative, $note->attr('native'), "{$source} note keeps single wrapped block payload");
            $t->same('wrapped-note', $note->attr('label'), "{$source} note label");
            $t->same('plain', $note->children[0]->type, "{$source} note child block type");
            $t->same('noted', $note->children[0]->children[0]->attr('text'), "{$source} note child text");
            $t->same('blockquote', $blockquote->type, "{$source} blockquote type");
            $t->same('BlockQuote', $blockquote->attr('constructor'), "{$source} blockquote constructor");
            $t->same($blockquoteNative, $blockquote->attr('native'), "{$source} blockquote keeps single wrapped block payload");
            $t->same('quoted', $blockquote->children[0]->children[0]->attr('text'), "{$source} blockquote child text");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($packet['blocks'], $encoded['blocks'], "{$source} {$writer} writer preserves unchanged single wrapped wrappers");
            }

            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], $paragraph->children),
                $blockquote,
            ]);
            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} {$writer} writer regenerates rebuilt paragraph");
                $t->same($sourceInlines, array_slice($encoded['blocks'][0]['c'], 0, count($sourceInlines)), "{$source} {$writer} writer preserves rebuilt inline single wraps");
                $t->same($noteNative, $encoded['blocks'][0]['c'][count($sourceInlines)], "{$source} {$writer} writer preserves rebuilt note single wrap");
                $t->same($blockquoteNative, $encoded['blocks'][1], "{$source} {$writer} writer preserves blockquote single wrap");
            }

            $editedChildren = $paragraph->children;
            $editedChildren[0] = new AstNode($editedChildren[0]->type, $editedChildren[0]->attrs, [
                new AstNode('text', ['text' => 'edited']),
            ]);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], $editedChildren),
            ]);
            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $editedEmph = $encoded['blocks'][0]['c'][0];

                $t->same('Emph', $editedEmph['t'], "{$source} {$writer} writer keeps edited constructor");
                $t->same([['t' => 'Str', 'c' => 'edited']], $editedEmph['c'], "{$source} {$writer} writer canonicalizes edited single wrapped inline");
                $t->same(false, array_key_exists('reviewQueue', $editedEmph), "{$source} {$writer} writer drops stale edited inline sidecar");
                $t->same($sourceInlines[1], $encoded['blocks'][0]['c'][1], "{$source} {$writer} writer keeps neighboring single wrapped inline");
            }
        }
    },
    'accepts single wrapped plain paragraph and edited table block tuple payloads' => static function (TestRunner $t): void {
        $sourceBlocks = [
            ['t' => 'Header', 'c' => [[
                2,
                ['wrapped-heading', ['review'], [['data-source', 'block-tuple']]],
                [
                    ['t' => 'Str', 'c' => 'Wrapped'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'Heading'],
                ],
            ]], 'reviewQueue' => 'heading-tuple-source'],
            ['t' => 'CodeBlock', 'c' => [[
                ['wrapped-code', ['php'], [['data-source', 'block-tuple']]],
                "echo 'wrapped';\n",
            ]], 'reviewQueue' => 'code-tuple-source'],
            ['t' => 'RawBlock', 'c' => [[
                ['t' => 'Format', 'c' => ['html'], 'reviewQueue' => 'format-tuple-source'],
                '<section>wrapped raw</section>',
            ]], 'reviewQueue' => 'raw-tuple-source'],
            ['t' => 'OrderedList', 'c' => [[
                [4, ['t' => 'Decimal'], ['t' => 'Period']],
                [[
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Wrapped'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'item'],
                    ]],
                ]],
            ]], 'reviewQueue' => 'ordered-list-tuple-source'],
            ['t' => 'Div', 'c' => [[
                ['wrapped-div', ['container'], [['data-source', 'block-tuple']]],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Str', 'c' => 'Wrapped'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'div'],
                    ]],
                ],
            ]], 'reviewQueue' => 'div-tuple-source'],
            ['t' => 'Figure', 'c' => [[
                ['wrapped-figure', ['media'], [['data-source', 'block-tuple']]],
                ['t' => 'Caption', 'c' => [
                    ['t' => 'Nothing'],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Wrapped'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'caption'],
                        ]],
                    ],
                ]],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Str', 'c' => 'Wrapped'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'figure'],
                    ]],
                ],
            ]], 'reviewQueue' => 'figure-tuple-source'],
            ['t' => 'Table', 'c' => [[
                ['wrapped-table', ['grid'], [['data-source', 'block-tuple']]],
                ['t' => 'Caption', 'c' => [
                    ['t' => 'Nothing'],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Wrapped'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'table'],
                        ]],
                    ],
                ]],
                [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => 0],
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [
                                            ['t' => 'Plain', 'c' => [
                                                ['t' => 'Str', 'c' => 'Wrapped'],
                                                ['t' => 'Space'],
                                                ['t' => 'Str', 'c' => 'cell'],
                                            ]],
                                        ],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ]], 'reviewQueue' => 'table-tuple-source'],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => $sourceBlocks,
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $blocks = $document->children;
            $table = $blocks[6];
            $tableBody = $table->children[0];
            $tableCell = $tableBody->children[0]->children[0];

            $t->same([
                'heading',
                'code_block',
                'raw_html',
                'ordered_list',
                'div',
                'figure',
                'table',
            ], array_map(static fn (AstNode $node): string => $node->type, $blocks), "{$source} normalizes single wrapped block tuple constructors");
            $t->same('wrapped-heading', $blocks[0]->attr('id'), "{$source} header attr id");
            $t->same('Wrapped Heading', $blocks[0]->attr('text'), "{$source} header text");
            $t->same($sourceBlocks[0], $blocks[0]->attr('native'), "{$source} header native payload");
            $t->same("echo 'wrapped';\n", $blocks[1]->attr('text'), "{$source} code block text");
            $t->same($sourceBlocks[1], $blocks[1]->attr('native'), "{$source} code block native payload");
            $t->same('html', $blocks[2]->attr('format'), "{$source} raw block format");
            $t->same('<section>wrapped raw</section>', $blocks[2]->attr('text'), "{$source} raw block text");
            $t->same(4, $blocks[3]->attr('start'), "{$source} ordered list start");
            $t->same('decimal', $blocks[3]->attr('style'), "{$source} ordered list style");
            $t->same('Wrapped item', $blocks[3]->children[0]->children[0]->attr('text'), "{$source} ordered list item text");
            $t->same('wrapped-div', $blocks[4]->attr('id'), "{$source} div attr id");
            $t->same('Wrapped div', $blocks[4]->children[0]->attr('text'), "{$source} div text");
            $t->same('wrapped-figure', $blocks[5]->attr('id'), "{$source} figure attr id");
            $t->same('Wrapped caption', $blocks[5]->attr('caption'), "{$source} figure caption text");
            $t->same('Wrapped figure', $blocks[5]->children[0]->attr('text'), "{$source} figure body text");
            $t->same('wrapped-table', $table->attr('id'), "{$source} table attr id");
            $t->same('Wrapped table', $table->attr('caption'), "{$source} table caption text");
            $t->same('Wrapped cell', $tableCell->attr('text'), "{$source} table cell text");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same(array_slice($sourceBlocks, 0, 5), array_slice($encoded['blocks'], 0, 5), "{$source} {$writer} writer preserves reusable single wrapped block tuples");
                $encodedFigure = (new PandocJsonReader())->readPacket(['blocks' => [$encoded['blocks'][5]]])->children[0];
                $encodedTable = (new PandocJsonReader())->readPacket(['blocks' => [$encoded['blocks'][6]]])->children[0];

                $t->same('Figure', $encoded['blocks'][5]['t'], "{$source} {$writer} writer keeps figure constructor");
                $t->same('wrapped-figure', $encodedFigure->attr('id'), "{$source} {$writer} writer keeps figure attr semantics");
                $t->same('Wrapped caption', $encodedFigure->attr('caption'), "{$source} {$writer} writer keeps figure caption semantics");
                $t->same('Table', $encoded['blocks'][6]['t'], "{$source} {$writer} writer keeps table constructor");
                $t->same('wrapped-table', $encodedTable->attr('id'), "{$source} {$writer} writer keeps table attr semantics");
                $t->same('Wrapped table', $encodedTable->attr('caption'), "{$source} {$writer} writer keeps table caption semantics");
            }

            $editedBlocks = $blocks;
            $editedBlocks[0] = new AstNode('heading', array_replace($blocks[0]->attrs, ['text' => 'Edited Heading']), [
                new AstNode('text', ['text' => 'Edited']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'Heading']),
            ]);
            $edited = new AstNode('document', $document->attrs, $editedBlocks);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $heading = $encoded['blocks'][0];

                $t->same('Header', $heading['t'], "{$source} {$writer} writer keeps edited heading constructor");
                $t->same(2, $heading['c'][0], "{$source} {$writer} writer emits canonical edited heading level");
                $t->same([['t' => 'Str', 'c' => 'Edited'], ['t' => 'Space'], ['t' => 'Str', 'c' => 'Heading']], $heading['c'][2], "{$source} {$writer} writer canonicalizes edited heading content");
                $t->same(false, array_key_exists('reviewQueue', $heading), "{$source} {$writer} writer drops stale edited heading sidecar");
                $t->same($sourceBlocks[1], $encoded['blocks'][1], "{$source} {$writer} writer preserves neighboring single wrapped block tuple");
            }
        }
    },
    'accepts single wrapped inline tuple constructor payloads through json and native readers' => static function (TestRunner $t): void {
        $quoteType = ['t' => 'SingleQuote', 'reviewQueue' => 'quote-kind-source'];
        $mathType = ['t' => 'InlineMath', 'reviewQueue' => 'math-type-source'];
        $format = ['t' => 'Format', 'c' => ['html'], 'reviewQueue' => 'format-source'];
        $citationMode = ['t' => 'NormalCitation', 'reviewQueue' => 'citation-mode-source'];
        $citationRecord = [
            'citationId' => 'smith1899',
            'citationPrefix' => [],
            'citationSuffix' => [],
            'citationMode' => $citationMode,
            'citationNoteNum' => 0,
            'citationHash' => 1899,
            'reviewQueue' => 'citation-record-source',
        ];
        $sourceInlines = [
            ['t' => 'Quoted', 'c' => [[$quoteType, [['t' => 'Str', 'c' => 'quoted']]]], 'reviewQueue' => 'quoted-tuple-source'],
            ['t' => 'Code', 'c' => [[['code-id', ['php'], [['data-source', 'tuple']]], 'wp_insert_post']], 'reviewQueue' => 'code-tuple-source'],
            ['t' => 'Math', 'c' => [[$mathType, 'x + y']], 'reviewQueue' => 'math-tuple-source'],
            ['t' => 'RawInline', 'c' => [[$format, '<span>raw</span>']], 'reviewQueue' => 'raw-tuple-source'],
            ['t' => 'Cite', 'c' => [[[$citationRecord], [['t' => 'Str', 'c' => '@smith1899']]]], 'reviewQueue' => 'cite-tuple-source'],
            ['t' => 'Link', 'c' => [[['link-id', ['review'], [['data-link', 'tuple']]], [['t' => 'Str', 'c' => 'source']], ['https://example.test/source', 'Source title', 'target-sidecar']]], 'reviewQueue' => 'link-tuple-source'],
            ['t' => 'Image', 'c' => [[['image-id', ['media'], [['data-image', 'tuple']]], [['t' => 'Str', 'c' => 'Alt']], ['media/image.png', 'Image title', 'image-target-sidecar']]], 'reviewQueue' => 'image-tuple-source'],
            ['t' => 'Span', 'c' => [[['span-id', ['metadata'], [['data-span', 'tuple']]], [['t' => 'Str', 'c' => 'span']]]], 'reviewQueue' => 'span-tuple-source'],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => $sourceInlines, 'reviewQueue' => 'tuple-paragraph-source'],
            ],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $paragraph = $document->children[0];
            $children = $paragraph->children;

            $t->same([
                'quoted',
                'code',
                'math',
                'raw_html_inline',
                'citation',
                'link',
                'image',
                'span',
            ], array_map(static fn (AstNode $node): string => $node->type, $children), "{$source} normalizes single wrapped inline tuple constructors");
            $t->same('single', $children[0]->attr('kind'), "{$source} quoted kind");
            $t->same($sourceInlines[0], $children[0]->attr('native'), "{$source} quoted native payload");
            $t->same('wp_insert_post', $children[1]->attr('text'), "{$source} code text");
            $t->same($sourceInlines[1], $children[1]->attr('native'), "{$source} code native payload");
            $t->same(false, $children[2]->attr('display'), "{$source} inline math display flag");
            $t->same($mathType, $children[2]->attr('mathTypeNative'), "{$source} math type native");
            $t->same('html', $children[3]->attr('format'), "{$source} raw format");
            $t->same($format, $children[3]->attr('formatNative'), "{$source} raw format native");
            $t->same('smith1899', $children[4]->attr('id'), "{$source} citation id");
            $t->same($citationRecord, $children[4]->attr('citationNative'), "{$source} citation record native");
            $t->same('link-id', $children[5]->attr('id'), "{$source} link attr id");
            $t->same(['https://example.test/source', 'Source title', 'target-sidecar'], $children[5]->attr('targetNative'), "{$source} link target sidecar");
            $t->same('Alt', $children[6]->attr('alt'), "{$source} image alt");
            $t->same(['media/image.png', 'Image title', 'image-target-sidecar'], $children[6]->attr('targetNative'), "{$source} image target sidecar");
            $t->same('span-id', $children[7]->attr('id'), "{$source} span attr id");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($packet['blocks'], $encoded['blocks'], "{$source} {$writer} writer preserves unchanged tuple wrapper payloads");
            }

            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], $children),
            ]);
            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same('Para', $encoded['blocks'][0]['t'], "{$source} {$writer} writer rebuilds paragraph wrapper");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} {$writer} writer drops rebuilt paragraph sidecar");
                $t->same($sourceInlines, $encoded['blocks'][0]['c'], "{$source} {$writer} writer preserves rebuilt tuple inline payloads");
            }

            $editedChildren = $children;
            $editedChildren[1] = new AstNode('code', array_replace($children[1]->attrs, ['text' => 'edited']));
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], $editedChildren),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $editedCode = $encoded['blocks'][0]['c'][1];

                $t->same('Code', $editedCode['t'], "{$source} {$writer} writer keeps edited code constructor");
                $t->same('edited', $editedCode['c'][1], "{$source} {$writer} writer emits edited code text");
                $t->same(false, array_key_exists('reviewQueue', $editedCode), "{$source} {$writer} writer drops stale edited code sidecar");
                $t->same($sourceInlines[0], $encoded['blocks'][0]['c'][0], "{$source} {$writer} writer preserves neighboring tuple inline");
            }
        }
    },
    'accepts single wrapped block tuple constructor payloads through json and native readers' => static function (TestRunner $t): void {
        $plainBlock = ['t' => 'Plain', 'c' => [[
            ['t' => 'Str', 'c' => 'Plain'],
        ]], 'reviewQueue' => 'plain-wrapper-source'];
        $paragraphBlock = ['t' => 'Para', 'c' => [[
            ['t' => 'Str', 'c' => 'Paragraph'],
        ]], 'reviewQueue' => 'paragraph-wrapper-source'];
        $headerBlock = ['t' => 'Header', 'c' => [[
            2,
            ['wrapped-heading', ['review'], [['data-source', 'header']]],
            [['t' => 'Str', 'c' => 'Heading']],
        ]], 'reviewQueue' => 'header-wrapper-source'];
        $codeBlock = ['t' => 'CodeBlock', 'c' => [[
            ['wrapped-code', ['php'], [['data-kind', 'cli']]],
            'wp option get home',
        ]], 'reviewQueue' => 'code-wrapper-source'];
        $rawBlock = ['t' => 'RawBlock', 'c' => [[
            ['t' => 'Format', 'c' => ['html'], 'reviewQueue' => 'format-wrapper-source'],
            '<section>raw</section>',
        ]], 'reviewQueue' => 'raw-wrapper-source'];
        $divBlock = ['t' => 'Div', 'c' => [[
            ['wrapped-div', ['container'], []],
            [
                ['t' => 'Para', 'c' => [[
                    ['t' => 'Str', 'c' => 'Nested'],
                ]], 'reviewQueue' => 'nested-paragraph-source'],
            ],
        ]], 'reviewQueue' => 'div-wrapper-source'];
        $figureBlock = ['t' => 'Figure', 'c' => [[
            ['wrapped-figure', ['media'], []],
            ['t' => 'Caption', 'c' => [
                ['t' => 'Just', 'c' => ['t' => 'ShortCaption', 'c' => [[
                    ['t' => 'Str', 'c' => 'Short'],
                ]]]],
                [
                    ['t' => 'Plain', 'c' => [[
                        ['t' => 'Str', 'c' => 'Long'],
                    ]]],
                ],
            ]],
            [
                ['t' => 'Para', 'c' => [[
                    ['t' => 'Str', 'c' => 'Figure'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'body'],
                ]]],
            ],
        ]], 'reviewQueue' => 'figure-wrapper-source'];
        $tableBlock = ['t' => 'Table', 'c' => [[
            ['wrapped-table', ['review'], []],
            ['t' => 'Caption', 'c' => [
                ['t' => 'Nothing'],
                [
                    ['t' => 'Plain', 'c' => [[
                        ['t' => 'Str', 'c' => 'Table'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'caption'],
                    ]]],
                ],
            ]],
            [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
            ['t' => 'TableHead', 'c' => [['', [], []], []]],
            [],
            ['t' => 'TableFoot', 'c' => [['', [], []], []]],
        ]], 'reviewQueue' => 'table-wrapper-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                $plainBlock,
                $paragraphBlock,
                $headerBlock,
                $codeBlock,
                $rawBlock,
                $divBlock,
                $figureBlock,
                $tableBlock,
            ],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $blocks = $document->children;
            $heading = $blocks[2];
            $code = $blocks[3];
            $raw = $blocks[4];
            $div = $blocks[5];
            $figure = $blocks[6];
            $table = $blocks[7];

            $t->same(['plain', 'paragraph', 'heading', 'code_block', 'raw_html', 'div', 'figure', 'table'], array_map(static fn (AstNode $node): string => $node->type, $blocks), "{$source} block tuple wrapper types");
            $t->same($plainBlock, $blocks[0]->attr('native'), "{$source} plain native wrapper");
            $t->same($paragraphBlock, $blocks[1]->attr('native'), "{$source} paragraph native wrapper");
            $t->same($headerBlock, $heading->attr('native'), "{$source} heading native wrapper");
            $t->same($codeBlock, $code->attr('native'), "{$source} code block native wrapper");
            $t->same($rawBlock, $raw->attr('native'), "{$source} raw block native wrapper");
            $t->same($divBlock, $div->attr('native'), "{$source} div native wrapper");
            $t->same($figureBlock, $figure->attr('native'), "{$source} figure native wrapper");
            $t->same($tableBlock, $table->attr('native'), "{$source} table native wrapper");
            $t->same('wrapped-heading', $heading->attr('id'), "{$source} heading attr id");
            $t->same('wp option get home', $code->attr('text'), "{$source} code text");
            $t->same('html', $raw->attr('format'), "{$source} raw format");
            $t->same('Nested', $div->children[0]->children[0]->attr('text'), "{$source} nested div paragraph text");
            $t->same('Short', $figure->attr('shortCaption'), "{$source} figure short caption");
            $t->same('Long', $figure->attr('caption'), "{$source} figure caption");
            $t->same('Table caption', $table->attr('caption'), "{$source} table caption");

            foreach ([
                "{$source} json" => (new PandocJsonWriter())->toArray($document),
                "{$source} native" => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($packet['blocks'], $encoded['blocks'], "{$writer} writer preserves unchanged single wrapped block tuples");
            }

            $editedHeading = new AstNode('heading', array_replace($heading->attrs, [
                'id' => 'edited-heading',
            ]), $heading->children);
            $editedTable = new AstNode('table', array_replace($table->attrs, [
                'id' => 'edited-table',
            ]), $table->children);
            $edited = new AstNode('document', $document->attrs, [$editedHeading, $editedTable]);

            foreach ([
                "{$source} json edited" => (new PandocJsonWriter())->toArray($edited),
                "{$source} native edited" => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedHeading = $encoded['blocks'][0];
                $encodedTable = $encoded['blocks'][1];

                $t->same('Header', $encodedHeading['t'], "{$writer} writer regenerates edited heading constructor");
                $t->same(false, array_key_exists('reviewQueue', $encodedHeading), "{$writer} writer drops stale edited heading sidecar");
                $t->same('edited-heading', $encodedHeading['c'][1][0], "{$writer} writer emits edited heading id");
                $t->same('Table', $encodedTable['t'], "{$writer} writer regenerates edited table constructor");
                $t->same(false, array_key_exists('reviewQueue', $encodedTable), "{$writer} writer drops stale edited table sidecar");
                $t->same('edited-table', $encodedTable['c'][0][0], "{$writer} writer emits edited table id");
                $t->same(false, count($encodedTable['c']) === 1 && is_array($encodedTable['c'][0]) && array_is_list($encodedTable['c'][0]), "{$writer} writer canonicalizes edited table tuple wrapper");
            }
        }
    },
    'accepts single wrapped block tuple payloads with table body edits through json and native readers' => static function (TestRunner $t): void {
        $headerBlock = ['t' => 'Header', 'c' => [[
            2,
            ['wrapped-heading', ['review'], [['data-source', 'block-tuple']]],
            [
                ['t' => 'Str', 'c' => 'Wrapped'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'heading'],
            ],
        ]], 'reviewQueue' => 'header-tuple-source'];
        $codeBlock = ['t' => 'CodeBlock', 'c' => [[
            ['wrapped-code', ['bash'], [['data-source', 'block-tuple']]],
            'wp option get home',
        ]], 'reviewQueue' => 'code-block-tuple-source'];
        $rawBlock = ['t' => 'RawBlock', 'c' => [[
            ['t' => 'Format', 'c' => ['html'], 'reviewQueue' => 'raw-format-source'],
            '<aside>raw</aside>',
        ]], 'reviewQueue' => 'raw-block-tuple-source'];
        $orderedBlock = ['t' => 'OrderedList', 'c' => [[
            [5, ['t' => 'UpperRoman'], ['t' => 'Period']],
            [[
                ['t' => 'Plain', 'c' => [
                    ['t' => 'Str', 'c' => 'ordered'],
                ]],
            ]],
        ]], 'reviewQueue' => 'ordered-tuple-source'];
        $divBlock = ['t' => 'Div', 'c' => [[
            ['wrapped-div', ['review'], [['data-source', 'block-tuple']]],
            [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'div'],
                ]],
            ],
        ]], 'reviewQueue' => 'div-tuple-source'];
        $figureBlock = ['t' => 'Figure', 'c' => [[
            ['wrapped-figure', ['review'], [['data-source', 'block-tuple']]],
            ['t' => 'Caption', 'c' => [
                ['t' => 'Nothing'],
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Figure'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'caption'],
                    ]],
                ],
            ]],
            [
                ['t' => 'Plain', 'c' => [
                    ['t' => 'Image', 'c' => [
                        ['', [], []],
                        [['t' => 'Str', 'c' => 'Alt']],
                        ['media/figure.png', 'Figure title'],
                    ]],
                ]],
            ],
        ]], 'reviewQueue' => 'figure-tuple-source'];
        $tableBlock = ['t' => 'Table', 'c' => [[
            ['wrapped-table', ['review'], [['data-source', 'block-tuple']]],
            ['t' => 'Caption', 'c' => [
                ['t' => 'Nothing'],
                [],
            ]],
            [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
            ['t' => 'TableHead', 'c' => [['', [], []], []]],
            [
                ['t' => 'TableBody', 'c' => [
                    ['', [], []],
                    ['t' => 'RowHeadColumns', 'c' => 0],
                    [],
                    [
                        ['t' => 'Row', 'c' => [
                            ['', [], []],
                            [
                                ['t' => 'Cell', 'c' => [
                                    ['', [], []],
                                    ['t' => 'AlignDefault'],
                                    ['t' => 'RowSpan', 'c' => 1],
                                    ['t' => 'ColSpan', 'c' => 1],
                                    [
                                        ['t' => 'Plain', 'c' => [
                                            ['t' => 'Str', 'c' => 'cell'],
                                        ]],
                                    ],
                                ]],
                            ],
                        ]],
                    ],
                ]],
            ],
            ['t' => 'TableFoot', 'c' => [['', [], []], []]],
        ]], 'reviewQueue' => 'table-tuple-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$headerBlock, $codeBlock, $rawBlock, $orderedBlock, $divBlock, $figureBlock, $tableBlock],
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
            $code = $document->children[1];
            $raw = $document->children[2];
            $ordered = $document->children[3];
            $div = $document->children[4];
            $figure = $document->children[5];
            $table = $document->children[6];

            $t->same(['heading', 'code_block', 'raw_html', 'ordered_list', 'div', 'figure', 'table'], array_map(static fn (AstNode $node): string => $node->type, $document->children), "{$source} normalizes single wrapped block tuple constructors");
            $t->same($headerBlock, $heading->attr('native'), "{$source} header native payload");
            $t->same(2, $heading->attr('level'), "{$source} heading level");
            $t->same('Wrapped heading', $heading->attr('text'), "{$source} heading text");
            $t->same($codeBlock, $code->attr('native'), "{$source} code block native payload");
            $t->same(['bash'], $code->attr('classes'), "{$source} code block classes");
            $t->same('wp option get home', $code->attr('text'), "{$source} code block text");
            $t->same($rawBlock, $raw->attr('native'), "{$source} raw block native payload");
            $t->same('html', $raw->attr('format'), "{$source} raw block format");
            $t->same('<aside>raw</aside>', $raw->attr('html'), "{$source} raw html");
            $t->same($orderedBlock, $ordered->attr('native'), "{$source} ordered list native payload");
            $t->same(5, $ordered->attr('start'), "{$source} ordered list start");
            $t->same('upper_roman', $ordered->attr('style'), "{$source} ordered list style");
            $t->same('period', $ordered->attr('delimiter'), "{$source} ordered list delimiter");
            $t->same($divBlock, $div->attr('native'), "{$source} div native payload");
            $t->same('wrapped-div', $div->attr('id'), "{$source} div attr id");
            $t->same('div', $div->children[0]->attr('text'), "{$source} div child text");
            $t->same($figureBlock, $figure->attr('native'), "{$source} figure native payload");
            $t->same('Figure caption', $figure->attr('caption'), "{$source} figure caption");
            $t->same('image', $figure->children[0]->type, "{$source} figure image child");
            $t->same($tableBlock, $table->attr('native'), "{$source} table native payload");
            $t->same('wrapped-table', $table->attr('id'), "{$source} table attr id");
            $t->same('cell', $table->children[0]->children[0]->children[0]->attr('text'), "{$source} table cell text");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($packet['blocks'], $encoded['blocks'], "{$source} {$writer} writer preserves unchanged single wrapped block tuple payloads");
            }

            $edited = new AstNode('document', $document->attrs, [
                new AstNode('heading', array_replace($withoutWrapperNative($heading), ['level' => 3]), $heading->children),
                new AstNode('code_block', array_replace($withoutWrapperNative($code), ['text' => 'wp option update home https://example.test'])),
                new AstNode('raw_html', array_replace($withoutWrapperNative($raw), [
                    'text' => '<aside>edited</aside>',
                    'html' => '<aside>edited</aside>',
                ])),
                new AstNode('ordered_list', array_replace($withoutWrapperNative($ordered), ['start' => 6]), $ordered->children),
                new AstNode('div', $withoutWrapperNative($div), $div->children),
                new AstNode('figure', $withoutWrapperNative($figure), $figure->children),
                new AstNode('table', $withoutWrapperNative($table), $table->children),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $editedHeader = $encoded['blocks'][0];
                $editedCode = $encoded['blocks'][1];
                $editedRaw = $encoded['blocks'][2];
                $editedOrdered = $encoded['blocks'][3];
                $editedDiv = $encoded['blocks'][4];

                $t->same('Header', $editedHeader['t'], "{$source} {$writer} writer keeps edited header constructor");
                $t->same(3, $editedHeader['c'][0], "{$source} {$writer} writer emits edited header level");
                $t->same(false, array_key_exists('reviewQueue', $editedHeader), "{$source} {$writer} writer drops stale header sidecar");
                $t->same('CodeBlock', $editedCode['t'], "{$source} {$writer} writer keeps edited code block constructor");
                $t->same('wp option update home https://example.test', $editedCode['c'][1], "{$source} {$writer} writer emits edited code block text");
                $t->same(false, array_key_exists('reviewQueue', $editedCode), "{$source} {$writer} writer drops stale code block sidecar");
                $t->same('RawBlock', $editedRaw['t'], "{$source} {$writer} writer keeps edited raw block constructor");
                $t->same('<aside>edited</aside>', $editedRaw['c'][1], "{$source} {$writer} writer emits edited raw block text");
                $t->same(6, $editedOrdered['c'][0][0], "{$source} {$writer} writer emits edited ordered list start");
                $t->same('Div', $editedDiv['t'], "{$source} {$writer} writer keeps rebuilt div constructor");
                $t->same(2, count($editedDiv['c']), "{$source} {$writer} writer canonicalizes rebuilt div tuple");
                $t->same(false, array_key_exists('reviewQueue', $editedDiv), "{$source} {$writer} writer drops stale div sidecar");
            }
        }
    },
    'accepts tagged single wrapped block tuple constructors through json and native readers' => static function (TestRunner $t): void {
        $emptyAttr = ['', [], []];
        $sourceBlocks = [
            ['t' => 'Header', 'c' => [[
                2,
                ['t' => 'Attr', 'c' => [['wrapped-heading', ['json-native'], [['data-source', 'block-tuple']]]]],
                [
                    ['t' => 'Str', 'c' => 'Wrapped'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'heading'],
                ],
            ]], 'reviewQueue' => 'header-tuple-source'],
            ['t' => 'CodeBlock', 'c' => [[
                ['t' => 'Attr', 'c' => [['wrapped-code', ['php'], []]]],
                'wp_insert_post',
            ]], 'reviewQueue' => 'code-block-tuple-source'],
            ['t' => 'OrderedList', 'c' => [[
                ['t' => 'ListAttributes', 'c' => [[3, ['t' => 'UpperAlpha'], ['t' => 'OneParen']]], 'reviewQueue' => 'list-attrs-source'],
                [
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Review'],
                        ]],
                    ],
                ],
            ]], 'reviewQueue' => 'ordered-list-tuple-source'],
            ['t' => 'DefinitionList', 'c' => [
                [[
                    [
                        ['t' => 'Str', 'c' => 'Term'],
                    ],
                    [
                        [
                            ['t' => 'Plain', 'c' => [
                                ['t' => 'Str', 'c' => 'Definition'],
                            ]],
                        ],
                    ],
                ]],
            ], 'reviewQueue' => 'definition-list-tuple-source'],
            ['t' => 'Div', 'c' => [[
                ['t' => 'Attr', 'c' => [['wrapped-div', ['metadata'], []]]],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Str', 'c' => 'Div'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'body'],
                    ]],
                ],
            ]], 'reviewQueue' => 'div-tuple-source'],
            ['t' => 'Figure', 'c' => [[
                ['t' => 'Attr', 'c' => [['wrapped-figure', ['media'], []]]],
                ['t' => 'Caption', 'c' => [
                    ['t' => 'Nothing'],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Figure'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'caption'],
                        ]],
                    ],
                ]],
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Figure'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'body'],
                    ]],
                ],
            ]], 'reviewQueue' => 'figure-tuple-source'],
            ['t' => 'Table', 'c' => [[
                ['t' => 'Attr', 'c' => [['wrapped-table', ['json-native'], []]]],
                ['t' => 'Caption', 'c' => [['t' => 'Nothing'], []]],
                [],
                ['t' => 'TableHead', 'c' => [$emptyAttr, []]],
                [],
                ['t' => 'TableFoot', 'c' => [$emptyAttr, []]],
            ]], 'reviewQueue' => 'table-tuple-source'],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => $sourceBlocks,
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $children = $document->children;

            $t->same([
                'heading',
                'code_block',
                'ordered_list',
                'definition_list',
                'div',
                'figure',
                'table',
            ], array_map(static fn (AstNode $node): string => $node->type, $children), "{$source} normalizes tagged single wrapped block tuple constructors");
            $t->same('wrapped-heading', $children[0]->attr('id'), "{$source} header attr tuple");
            $t->same(['php'], $children[1]->attr('classes'), "{$source} code block attr tuple");
            $t->same(3, $children[2]->attr('start'), "{$source} ordered list start");
            $t->same('upper_alpha', $children[2]->attr('style'), "{$source} ordered list style");
            $t->same('one_paren', $children[2]->attr('delimiter'), "{$source} ordered list delimiter");
            $definitionItem = $children[3]->children[0];
            $t->same('Term', $definitionItem->children[0]->attr('text'), "{$source} definition term");
            $t->same('Definition', $definitionItem->children[1]->children[0]->attr('text'), "{$source} definition body");
            $t->same('wrapped-div', $children[4]->attr('id'), "{$source} div attr tuple");
            $t->same('Figure caption', $children[5]->attr('caption'), "{$source} figure caption");
            $t->same('wrapped-table', $children[6]->attr('id'), "{$source} table attr tuple");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($sourceBlocks, $encoded['blocks'], "{$source} {$writer} writer preserves unchanged tagged block tuple wrappers");
            }

            $edited = new AstNode('document', $document->attrs, [
                new AstNode('code_block', array_replace($children[1]->attrs, ['text' => 'edited'])),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $code = $encoded['blocks'][0];

                $t->same('CodeBlock', $code['t'], "{$source} {$writer} writer keeps edited code block constructor");
                $t->same('edited', $code['c'][1], "{$source} {$writer} writer emits edited code block text");
                $t->same(false, array_key_exists('reviewQueue', $code), "{$source} {$writer} writer drops stale edited code block sidecar");
            }
        }
    },
    'round trips core block constructors through pandoc json' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('blockquote', [], [
                new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Quoted source'])]),
            ]),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Check media'])])]),
            ]),
            new AstNode('ordered_list', ['start' => 3, 'style' => 'upper_alpha', 'delimiter' => 'one_paren'], [
                new AstNode('list_item', [], [new AstNode('plain', [], [new AstNode('text', ['text' => 'Review'])])]),
            ]),
            new AstNode('line_block', [], [
                new AstNode('line', [], [new AstNode('text', ['text' => 'Address line'])]),
            ]),
            new AstNode('code_block', ['text' => 'wp post get 42', 'classes' => ['bash']]),
            new AstNode('raw_markdown', ['format' => 'markdown', 'text' => '*raw*']),
            new AstNode('div', ['id' => 'packet'], [
                new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Wrapped'])]),
            ]),
            new AstNode('horizontal_rule'),
            new AstNode('null_block'),
        ]);

        $packet = (new PandocJsonWriter())->toArray($document);
        $roundTrip = (new PandocJsonReader())->readPacket($packet);

        $t->same(['BlockQuote', 'BulletList', 'OrderedList', 'LineBlock', 'CodeBlock', 'RawBlock', 'Div', 'HorizontalRule', 'Null'], array_map(static fn (array $block): string => $block['t'], $packet['blocks']));
        $t->same('blockquote', $roundTrip->children[0]->type);
        $t->same('bullet_list', $roundTrip->children[1]->type);
        $t->same('ordered_list', $roundTrip->children[2]->type);
        $t->same(3, $roundTrip->children[2]->attr('start'));
        $t->same('upper_alpha', $roundTrip->children[2]->attr('style'));
        $t->same('one_paren', $roundTrip->children[2]->attr('delimiter'));
        $t->same('Address line', $roundTrip->children[3]->children[0]->attr('text'));
        $t->same(['bash'], $roundTrip->children[4]->attr('classes'));
        $t->same('raw_markdown', $roundTrip->children[5]->type);
        $t->same('packet', $roundTrip->children[6]->attr('id'));
        $t->same('horizontal_rule', $roundTrip->children[7]->type);
        $t->same('null_block', $roundTrip->children[8]->type);
    },
    'round trips native writer core block constructors through both readers' => static function (TestRunner $t): void {
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('blockquote', [], [
                new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Quoted source'])]),
            ]),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Check media'])])]),
            ]),
            new AstNode('ordered_list', ['start' => 3, 'style' => 'upper_alpha', 'delimiter' => 'one_paren'], [
                new AstNode('list_item', [], [new AstNode('plain', [], [new AstNode('text', ['text' => 'Review'])])]),
            ]),
            new AstNode('line_block', [], [
                new AstNode('line', [], [new AstNode('text', ['text' => 'Address line'])]),
            ]),
            new AstNode('code_block', ['text' => 'wp post get 42', 'classes' => ['bash']]),
            new AstNode('raw_markdown', ['format' => 'markdown', 'text' => '*raw*']),
            new AstNode('div', ['id' => 'packet'], [
                new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Wrapped'])]),
            ]),
            new AstNode('horizontal_rule'),
            new AstNode('null_block'),
        ]);

        $packet = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same(
            ['BlockQuote', 'BulletList', 'OrderedList', 'LineBlock', 'CodeBlock', 'RawBlock', 'Div', 'HorizontalRule', 'Null'],
            array_map(static fn (array $block): string => $block['t'], $packet['blocks'])
        );

        $roundTrips = [
            'json reader' => (new PandocJsonReader())->readPacket($packet),
            'native reader' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($roundTrips as $source => $roundTrip) {
            $t->same('blockquote', $roundTrip->children[0]->type, "{$source} blockquote node");
            $t->same('bullet_list', $roundTrip->children[1]->type, "{$source} bullet list node");
            $t->same('ordered_list', $roundTrip->children[2]->type, "{$source} ordered list node");
            $t->same(3, $roundTrip->children[2]->attr('start'), "{$source} ordered list start");
            $t->same('upper_alpha', $roundTrip->children[2]->attr('style'), "{$source} ordered list style");
            $t->same('one_paren', $roundTrip->children[2]->attr('delimiter'), "{$source} ordered list delimiter");
            $t->same('Address line', $roundTrip->children[3]->children[0]->attr('text'), "{$source} line block text");
            $t->same(['bash'], $roundTrip->children[4]->attr('classes'), "{$source} code block classes");
            $t->same('raw_markdown', $roundTrip->children[5]->type, "{$source} raw block alias");
            $t->same('packet', $roundTrip->children[6]->attr('id'), "{$source} div attr");
            $t->same('horizontal_rule', $roundTrip->children[7]->type, "{$source} horizontal rule node");
            $t->same('null_block', $roundTrip->children[8]->type, "{$source} null block node");
        }
    },
    'regenerates present nullary block constructor payloads through json and native writers' => static function (TestRunner $t): void {
        $horizontalRule = ['t' => 'HorizontalRule', 'c' => ['stale'], 'reviewQueue' => 'rule-source'];
        $nullBlock = ['t' => 'Null', 'c' => 'stale', 'reviewQueue' => 'null-source'];
        $emptyHorizontalRule = ['t' => 'HorizontalRule', 'c' => [], 'reviewQueue' => 'empty-rule-source'];
        $emptyNullBlock = ['t' => 'Null', 'c' => [], 'reviewQueue' => 'empty-null-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$horizontalRule, $nullBlock, $emptyHorizontalRule, $emptyNullBlock],
        ];
        $expectedBlocks = [
            ['t' => 'HorizontalRule'],
            ['t' => 'Null'],
            ['t' => 'HorizontalRule'],
            ['t' => 'Null'],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $t->same(['horizontal_rule', 'null_block', 'horizontal_rule', 'null_block'], array_map(static fn (AstNode $node): string => $node->type, $document->children), "{$source} records nullary block nodes");
            $t->same($horizontalRule, $document->children[0]->attr('native'), "{$source} records stale horizontal rule payload");
            $t->same($nullBlock, $document->children[1]->attr('native'), "{$source} records stale null payload");
            $t->same($emptyHorizontalRule, $document->children[2]->attr('native'), "{$source} records empty horizontal rule payload");
            $t->same($emptyNullBlock, $document->children[3]->attr('native'), "{$source} records empty null payload");

            foreach ([
                'json writer' => (new PandocJsonWriter())->toArray($document),
                'native writer' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($expectedBlocks, $encoded['blocks'], "{$source} {$writer} regenerates present nullary block payloads");
            }
        }
    },
    'regenerates stale nullary helper constructor payloads through json and native writers' => static function (TestRunner $t): void {
        $quoteType = ['t' => 'DoubleQuote', 'c' => ['stale'], 'reviewQueue' => 'quote-type-source'];
        $mathType = ['t' => 'DisplayMath', 'c' => ['stale'], 'reviewQueue' => 'math-type-source'];
        $citationMode = ['t' => 'SuppressAuthor', 'c' => ['stale'], 'reviewQueue' => 'citation-mode-source'];
        $listStyle = ['t' => 'UpperRoman', 'c' => ['stale'], 'reviewQueue' => 'list-style-source'];
        $listDelimiter = ['t' => 'TwoParens', 'c' => ['stale'], 'reviewQueue' => 'list-delimiter-source'];
        $tableAlignment = ['t' => 'AlignRight', 'c' => ['stale'], 'reviewQueue' => 'table-alignment-source'];
        $tableWidth = ['t' => 'ColWidthDefault', 'c' => ['stale'], 'reviewQueue' => 'table-width-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Quoted', 'c' => [
                        $quoteType,
                        [['t' => 'Str', 'c' => 'quoted']],
                    ], 'reviewQueue' => 'quoted-wrapper-source'],
                    ['t' => 'Space'],
                    ['t' => 'Math', 'c' => [
                        $mathType,
                        'E=mc^2',
                    ], 'reviewQueue' => 'math-wrapper-source'],
                    ['t' => 'Space'],
                    ['t' => 'Cite', 'c' => [
                        [[
                            'citationId' => 'smith1899',
                            'citationPrefix' => [],
                            'citationSuffix' => [],
                            'citationMode' => $citationMode,
                            'citationNoteNum' => 0,
                            'citationHash' => 1899,
                            'reviewQueue' => 'citation-record-source',
                        ]],
                        [['t' => 'Str', 'c' => '@smith1899']],
                    ], 'reviewQueue' => 'cite-wrapper-source'],
                ], 'reviewQueue' => 'paragraph-wrapper-source'],
                ['t' => 'OrderedList', 'c' => [
                    [2, $listStyle, $listDelimiter],
                    [[
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'item'],
                        ]],
                    ]],
                ], 'reviewQueue' => 'ordered-wrapper-source'],
                ['t' => 'Table', 'c' => [
                    ['', [], []],
                    ['t' => 'Caption', 'c' => [
                        ['t' => 'Nothing'],
                        [],
                    ]],
                    [[$tableAlignment, $tableWidth]],
                    ['t' => 'TableHead', 'c' => [['', [], []], []]],
                    [],
                    ['t' => 'TableFoot', 'c' => [['', [], []], []]],
                ], 'reviewQueue' => 'table-wrapper-source'],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $paragraph = $document->children[0];
            $quoted = $paragraph->children[0];
            $math = $paragraph->children[2];
            $cite = $paragraph->children[4];
            $ordered = $document->children[1];
            $table = $document->children[2];

            $t->same($quoteType, $quoted->attr('quoteTypeNative'), "{$source} records source quote helper payload");
            $t->same($mathType, $math->attr('mathTypeNative'), "{$source} records source math helper payload");
            $t->same($citationMode, $cite->attr('citationModeNative'), "{$source} records source citation mode helper payload");
            $t->same($listStyle, $ordered->attr('listStyleNative'), "{$source} records source list style helper payload");
            $t->same($listDelimiter, $ordered->attr('listDelimiterNative'), "{$source} records source list delimiter helper payload");
            $t->same([$tableAlignment], $table->attr('alignmentNatives'), "{$source} records source table alignment helper payload");
            $t->same([$tableWidth], $table->attr('columnWidthNatives'), "{$source} records source table width helper payload");

            foreach ([
                'json writer' => (new PandocJsonWriter())->toArray($document),
                'native writer' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedPara = $encoded['blocks'][0];
                $encodedQuoteType = $encodedPara['c'][0]['c'][0];
                $encodedMathType = $encodedPara['c'][2]['c'][0];
                $encodedCitationMode = $encodedPara['c'][4]['c'][0][0]['citationMode'];
                $encodedOrdered = $encoded['blocks'][1];
                $encodedTableColSpec = $encoded['blocks'][2]['c'][2][0];

                $t->same(false, array_key_exists('reviewQueue', $encodedPara), "{$source} {$writer} regenerates stale paragraph wrapper");
                $t->same('quote-type-source', $encodedQuoteType['reviewQueue'], "{$source} {$writer} preserves quote helper sidecar");
                $t->same(false, array_key_exists('c', $encodedQuoteType), "{$source} {$writer} drops stale quote helper content");
                $t->same('math-type-source', $encodedMathType['reviewQueue'], "{$source} {$writer} preserves math helper sidecar");
                $t->same(false, array_key_exists('c', $encodedMathType), "{$source} {$writer} drops stale math helper content");
                $t->same('citation-mode-source', $encodedCitationMode['reviewQueue'], "{$source} {$writer} preserves citation mode helper sidecar");
                $t->same(false, array_key_exists('c', $encodedCitationMode), "{$source} {$writer} drops stale citation mode helper content");
                $t->same(false, array_key_exists('reviewQueue', $encodedPara['c'][4]['c'][0][0]), "{$source} {$writer} regenerates stale citation record");
                $t->same(false, array_key_exists('reviewQueue', $encodedOrdered), "{$source} {$writer} regenerates stale ordered-list wrapper");
                $t->same('list-style-source', $encodedOrdered['c'][0][1]['reviewQueue'], "{$source} {$writer} preserves list style sidecar");
                $t->same(false, array_key_exists('c', $encodedOrdered['c'][0][1]), "{$source} {$writer} drops stale list style content");
                $t->same('list-delimiter-source', $encodedOrdered['c'][0][2]['reviewQueue'], "{$source} {$writer} preserves list delimiter sidecar");
                $t->same(false, array_key_exists('c', $encodedOrdered['c'][0][2]), "{$source} {$writer} drops stale list delimiter content");
                $t->same('table-alignment-source', $encodedTableColSpec[0]['reviewQueue'], "{$source} {$writer} preserves table alignment sidecar");
                $t->same(false, array_key_exists('c', $encodedTableColSpec[0]), "{$source} {$writer} drops stale table alignment content");
                $t->same('table-width-source', $encodedTableColSpec[1]['reviewQueue'], "{$source} {$writer} preserves table width sidecar");
                $t->same(false, array_key_exists('c', $encodedTableColSpec[1]), "{$source} {$writer} drops stale table width content");
            }
        }
    },
    'regenerates stale caption nothing helpers through json and native writers' => static function (TestRunner $t): void {
        $staleNothing = ['t' => 'Nothing', 'c' => ['stale'], 'reviewQueue' => 'caption-nothing-source'];
        $cleanNothing = ['t' => 'Nothing', 'reviewQueue' => 'caption-nothing-source'];
        $captionBlocks = [
            ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Long'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'caption'],
            ]],
        ];
        $caption = [
            't' => 'Caption',
            'c' => [$staleNothing, $captionBlocks],
            'reviewQueue' => 'caption-source',
        ];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['caption-nothing-table', ['json-native'], []],
                $caption,
                [],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
            'reviewQueue' => 'table-wrapper-source',
        ];
        $figureBlock = [
            't' => 'Figure',
            'c' => [
                ['caption-nothing-figure', [], []],
                $caption,
                [],
            ],
            'reviewQueue' => 'figure-wrapper-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock, $figureBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $figure = $document->children[1];

            $t->same($staleNothing, $table->attr('shortCaptionMaybeNative'), "{$source} records stale table Nothing helper");
            $t->same($staleNothing, $figure->attr('shortCaptionMaybeNative'), "{$source} records stale figure Nothing helper");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $tableCaption = $encoded['blocks'][0]['c'][1];
                $figureCaption = $encoded['blocks'][1]['c'][1];

                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} {$writer} writer regenerates stale table wrapper");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][1]), "{$source} {$writer} writer regenerates stale figure wrapper");
                $t->same('caption-source', $tableCaption['reviewQueue'] ?? null, "{$source} {$writer} writer preserves table caption sidecar");
                $t->same('caption-source', $figureCaption['reviewQueue'] ?? null, "{$source} {$writer} writer preserves figure caption sidecar");
                $t->same($cleanNothing, $tableCaption['c'][0], "{$source} {$writer} writer cleans table Nothing helper");
                $t->same($cleanNothing, $figureCaption['c'][0], "{$source} {$writer} writer cleans figure Nothing helper");

                $roundTrip = $writer === 'json'
                    ? (new PandocJsonReader())->readPacket($encoded)
                    : (new NativeReader())->read(json_encode($encoded, JSON_THROW_ON_ERROR));

                $t->same($cleanNothing, $roundTrip->children[0]->attr('shortCaptionMaybeNative'), "{$source} {$writer} round trip table Nothing helper");
                $t->same($cleanNothing, $roundTrip->children[1]->attr('shortCaptionMaybeNative'), "{$source} {$writer} round trip figure Nothing helper");
            }
        }
    },
    'regenerates stale nullary block payloads inside metadata lists and nested containers' => static function (TestRunner $t): void {
        $metaRule = ['t' => 'HorizontalRule', 'c' => ['stale-meta-rule'], 'reviewQueue' => 'meta-rule-source'];
        $metaNull = ['t' => 'Null', 'c' => ['stale-meta-null'], 'reviewQueue' => 'meta-null-source'];
        $listRule = ['t' => 'HorizontalRule', 'c' => ['stale-list-rule'], 'reviewQueue' => 'list-rule-source'];
        $listNull = ['t' => 'Null', 'c' => ['stale-list-null'], 'reviewQueue' => 'list-null-source'];
        $nestedRule = ['t' => 'HorizontalRule', 'c' => ['stale-nested-rule'], 'reviewQueue' => 'nested-rule-source'];
        $noteNull = ['t' => 'Null', 'c' => ['stale-note-null'], 'reviewQueue' => 'note-null-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'review' => ['t' => 'MetaMap', 'c' => [
                    'body' => ['t' => 'MetaBlocks', 'c' => [
                        $metaRule,
                        ['t' => 'BulletList', 'c' => [
                            [
                                $metaNull,
                                ['t' => 'Para', 'c' => [
                                    ['t' => 'Str', 'c' => 'metadata'],
                                ]],
                            ],
                        ], 'reviewQueue' => 'meta-list-source'],
                        ['t' => 'Div', 'c' => [
                            ['meta-container', [], []],
                            [
                                ['t' => 'BlockQuote', 'c' => [$nestedRule], 'reviewQueue' => 'meta-blockquote-source'],
                            ],
                        ], 'reviewQueue' => 'meta-div-source'],
                    ], 'reviewQueue' => 'meta-blocks-source'],
                    'items' => ['t' => 'MetaList', 'c' => [
                        ['t' => 'MetaBlocks', 'c' => [$metaNull], 'reviewQueue' => 'meta-list-blocks-source'],
                    ]],
                ]],
            ],
            'blocks' => [
                ['t' => 'BulletList', 'c' => [
                    [
                        $listRule,
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Str', 'c' => 'after'],
                        ]],
                    ],
                    [
                        $listNull,
                    ],
                ], 'reviewQueue' => 'list-wrapper-source'],
                ['t' => 'Div', 'c' => [
                    ['nested-container', [], []],
                    [
                        ['t' => 'BlockQuote', 'c' => [$nestedRule], 'reviewQueue' => 'block-wrapper-source'],
                    ],
                ], 'reviewQueue' => 'div-wrapper-source'],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Note', 'c' => [$noteNull], 'reviewQueue' => 'note-wrapper-source'],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $provenance = $document->attr('metaConstructorProvenance');
            $list = $document->children[0];
            $div = $document->children[1];
            $note = $document->children[2]->children[0];

            $t->same($packet['meta']['review']['c']['body'], $provenance['/review/body']['native'], "{$source} retains MetaBlocks source payload");
            $t->same($metaRule, $provenance['/review/body']['native']['c'][0], "{$source} retains stale metadata rule provenance");
            $t->same($listNull, $list->children[1]->children[0]->attr('native'), "{$source} retains stale list null provenance");
            $t->same($nestedRule, $div->children[0]->children[0]->attr('native'), "{$source} retains stale nested rule provenance");
            $t->same($noteNull, $note->children[0]->attr('native'), "{$source} retains stale note block provenance");

            foreach ([
                'json writer' => (new PandocJsonWriter())->toArray($document),
                'native writer' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedMetaBody = $encoded['meta']['review']['c']['body']['c'];
                $encodedMetaListBlocks = $encoded['meta']['review']['c']['items']['c'][0]['c'];
                $encodedList = $encoded['blocks'][0];
                $encodedDiv = $encoded['blocks'][1];
                $encodedNote = $encoded['blocks'][2]['c'][0];

                $t->same(['t' => 'HorizontalRule'], $encodedMetaBody[0], "{$source} {$writer} regenerates MetaBlocks rule");
                $t->same(['t' => 'Null'], $encodedMetaBody[1]['c'][0][0], "{$source} {$writer} regenerates MetaBlocks list null");
                $t->same(['t' => 'HorizontalRule'], $encodedMetaBody[2]['c'][1][0]['c'][0], "{$source} {$writer} regenerates nested MetaBlocks rule");
                $t->same(['t' => 'Null'], $encodedMetaListBlocks[0], "{$source} {$writer} regenerates MetaList block null");
                $t->same(['t' => 'HorizontalRule'], $encodedList['c'][0][0], "{$source} {$writer} regenerates list rule payload");
                $t->same(['t' => 'Null'], $encodedList['c'][1][0], "{$source} {$writer} regenerates list null payload");
                $t->same(['t' => 'HorizontalRule'], $encodedDiv['c'][1][0]['c'][0], "{$source} {$writer} regenerates nested container rule");
                $t->same(['t' => 'Null'], $encodedNote['c'][0], "{$source} {$writer} regenerates note block null");
                $t->same(false, array_key_exists('reviewQueue', $encodedList), "{$source} {$writer} drops stale list wrapper sidecar");
                $t->same(false, array_key_exists('reviewQueue', $encodedNote), "{$source} {$writer} drops stale note wrapper sidecar");
            }
        }
    },
    'regenerates stale nullary block sidecars through nested json and native writers' => static function (TestRunner $t): void {
        $topRule = ['t' => 'HorizontalRule', 'c' => ['old-rule-payload'], 'reviewQueue' => 'top-rule-source'];
        $topNull = ['t' => 'Null', 'c' => ['old-null-payload'], 'reviewQueue' => 'top-null-source'];
        $quoteRule = ['t' => 'HorizontalRule', 'c' => ['quote-rule-payload'], 'reviewQueue' => 'quote-rule-source'];
        $quoteNull = ['t' => 'Null', 'c' => ['quote-null-payload'], 'reviewQueue' => 'quote-null-source'];
        $divRule = ['t' => 'HorizontalRule', 'c' => ['div-rule-payload'], 'reviewQueue' => 'div-rule-source'];
        $listNull = ['t' => 'Null', 'c' => ['list-null-payload'], 'reviewQueue' => 'list-null-source'];
        $metaRule = ['t' => 'HorizontalRule', 'c' => ['meta-rule-payload'], 'reviewQueue' => 'meta-rule-source'];
        $metaNull = ['t' => 'Null', 'c' => ['meta-null-payload'], 'reviewQueue' => 'meta-null-source'];
        $metaBlocks = ['t' => 'MetaBlocks', 'c' => [$metaRule, $metaNull], 'reviewQueue' => 'meta-blocks-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'provenanceBlocks' => $metaBlocks,
            ],
            'blocks' => [
                $topRule,
                $topNull,
                ['t' => 'BlockQuote', 'c' => [$quoteRule, $quoteNull], 'reviewQueue' => 'quote-wrapper-source'],
                ['t' => 'Div', 'c' => [['nullary-sidecars', [], []], [$divRule]], 'reviewQueue' => 'div-wrapper-source'],
                ['t' => 'BulletList', 'c' => [[$listNull]], 'reviewQueue' => 'list-wrapper-source'],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $quote = $document->children[2];
            $div = $document->children[3];
            $list = $document->children[4];
            $provenance = $document->attr('metaConstructorProvenance');

            $t->same($topRule, $document->children[0]->attr('native'), "{$source} reader retains top rule native sidecar");
            $t->same($topNull, $document->children[1]->attr('native'), "{$source} reader retains top null native sidecar");
            $t->same($quoteRule, $quote->children[0]->attr('native'), "{$source} reader retains nested quote rule sidecar");
            $t->same($quoteNull, $quote->children[1]->attr('native'), "{$source} reader retains nested quote null sidecar");
            $t->same($divRule, $div->children[0]->attr('native'), "{$source} reader retains nested div rule sidecar");
            $t->same($listNull, $list->children[0]->children[0]->attr('native'), "{$source} reader retains list null sidecar");
            $t->same($metaBlocks, $provenance['/provenanceBlocks']['native'], "{$source} reader records metadata block constructor provenance");

            if ($source === 'json') {
                $metaChildren = $document->attr('meta')['provenanceBlocks']['children'];

                $t->same($metaRule, $metaChildren[0]->attr('native'), "{$source} reader retains meta rule sidecar");
                $t->same($metaNull, $metaChildren[1]->attr('native'), "{$source} reader retains meta null sidecar");
            }

            foreach ([
                'json writer' => (new PandocJsonWriter())->toArray($document),
                'native writer' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedQuote = $encoded['blocks'][2];
                $encodedDiv = $encoded['blocks'][3];
                $encodedList = $encoded['blocks'][4];
                $encodedMetaBlocks = $encoded['meta']['provenanceBlocks'];

                $t->same(['t' => 'HorizontalRule'], $encoded['blocks'][0], "{$source} {$writer} regenerates top rule");
                $t->same(['t' => 'Null'], $encoded['blocks'][1], "{$source} {$writer} regenerates top null");
                $t->same(false, array_key_exists('reviewQueue', $encodedQuote), "{$source} {$writer} regenerates quote wrapper");
                $t->same(['t' => 'HorizontalRule'], $encodedQuote['c'][0], "{$source} {$writer} regenerates quote rule");
                $t->same(['t' => 'Null'], $encodedQuote['c'][1], "{$source} {$writer} regenerates quote null");
                $t->same(false, array_key_exists('reviewQueue', $encodedDiv), "{$source} {$writer} regenerates div wrapper");
                $t->same(['t' => 'HorizontalRule'], $encodedDiv['c'][1][0], "{$source} {$writer} regenerates div rule");
                $t->same(false, array_key_exists('reviewQueue', $encodedList), "{$source} {$writer} regenerates list wrapper");
                $t->same(['t' => 'Null'], $encodedList['c'][0][0], "{$source} {$writer} regenerates list null");
                $t->same('MetaBlocks', $encodedMetaBlocks['t'], "{$source} {$writer} keeps metadata block constructor");
                $t->same(false, array_key_exists('reviewQueue', $encodedMetaBlocks), "{$source} {$writer} regenerates metadata block wrapper");
                $t->same([['t' => 'HorizontalRule'], ['t' => 'Null']], $encodedMetaBlocks['c'], "{$source} {$writer} regenerates metadata nullary blocks");

                $roundTrips = [
                    'json round trip' => (new PandocJsonReader())->readPacket($encoded),
                    'native round trip' => (new NativeReader())->read(json_encode($encoded, JSON_THROW_ON_ERROR)),
                ];

                foreach ($roundTrips as $roundTripSource => $roundTrip) {
                    $roundTripProvenance = $roundTrip->attr('metaConstructorProvenance');

                    $t->same(['t' => 'HorizontalRule'], $roundTrip->children[0]->attr('native'), "{$source} {$writer} {$roundTripSource} keeps clean top rule");
                    $t->same(['t' => 'Null'], $roundTrip->children[1]->attr('native'), "{$source} {$writer} {$roundTripSource} keeps clean top null");
                    $t->same(['t' => 'HorizontalRule'], $roundTripProvenance['/provenanceBlocks']['native']['c'][0], "{$source} {$writer} {$roundTripSource} keeps clean meta rule");
                    $t->same(['t' => 'Null'], $roundTripProvenance['/provenanceBlocks']['native']['c'][1], "{$source} {$writer} {$roundTripSource} keeps clean meta null");
                }
            }
        }
    },
    'reads legacy table and target inline constructor shapes through json and native readers' => static function (TestRunner $t): void {
        $legacyTable = [
            't' => 'Table',
            'c' => [
                [
                    ['t' => 'Str', 'c' => 'Legacy'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'caption'],
                ],
                [['t' => 'AlignLeft'], ['t' => 'AlignRight']],
                [0.4, 0],
                [
                    [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Metric']]]],
                    [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'State']]]],
                ],
                [
                    [
                        [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Posts']]]],
                        [['t' => 'Para', 'c' => [['t' => 'Str', 'c' => 'Ready']]]],
                    ],
                ],
            ],
        ];
        $legacyPara = [
            't' => 'Para',
            'c' => [
                ['t' => 'Link', 'c' => [
                    [['t' => 'Str', 'c' => 'source']],
                    ['https://example.test/source', 'Legacy source'],
                ]],
                ['t' => 'Space'],
                ['t' => 'Image', 'c' => [
                    [['t' => 'Str', 'c' => 'diagram']],
                    ['media/diagram.png', 'Diagram title'],
                ]],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 17, 5, 1],
            'meta' => [],
            'blocks' => [$legacyTable, $legacyPara],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $paragraph = $document->children[1];

            $t->same('table', $table->type, "{$source} table node");
            $t->same('Table', $table->attr('constructor'), "{$source} table constructor");
            $t->same($legacyTable, $table->attr('native'), "{$source} legacy table native payload");
            $t->same('Legacy caption', $table->attr('caption'), "{$source} legacy caption text");
            $t->same(['left', 'right'], $table->attr('alignments'), "{$source} legacy alignments");
            $t->same(['AlignLeft', 'AlignRight'], $table->attr('alignmentConstructors'), "{$source} legacy alignment constructors");
            $t->same($legacyTable['c'][1], $table->attr('alignmentNatives'), "{$source} legacy alignment native payloads");
            $t->same([0.4, null], $table->attr('widths'), "{$source} legacy widths");
            $t->same(['ColWidth', 'ColWidthDefault'], $table->attr('columnWidthConstructors'), "{$source} legacy width constructors");
            $t->same($legacyTable['c'][2], $table->attr('columnWidthNatives'), "{$source} legacy width native payloads");
            $t->same(['table_head', 'table_body'], array_map(static fn (AstNode $node): string => $node->type, $table->children), "{$source} legacy table sections");
            $t->same('Metric', $table->children[0]->children[0]->children[0]->attr('text'), "{$source} legacy header cell text");
            $t->same('Ready', $table->children[1]->children[0]->children[1]->attr('text'), "{$source} legacy body cell text");
            $t->same('link', $paragraph->children[0]->type, "{$source} legacy link node");
            $t->same('https://example.test/source', $paragraph->children[0]->attr('url'), "{$source} legacy link target");
            $t->same(['https://example.test/source', 'Legacy source'], $paragraph->children[0]->attr('targetNative'), "{$source} legacy link target tuple");
            $t->same('image', $paragraph->children[2]->type, "{$source} legacy image node");
            $t->same(['media/diagram.png', 'Diagram title'], $paragraph->children[2]->attr('targetNative'), "{$source} legacy image target tuple");
            $t->same('diagram', $paragraph->children[2]->attr('alt'), "{$source} legacy image alt");
        }

        $nativeRoundTrip = json_decode((new NativeWriter())->write($documents['native']), true, 512, JSON_THROW_ON_ERROR);
        $jsonEncoded = (new PandocJsonWriter())->toArray($documents['json']);
        $t->same($packet['blocks'], $nativeRoundTrip['blocks']);
        $t->same(6, count($jsonEncoded['blocks'][0]['c']));
        $t->same(['', [], []], $jsonEncoded['blocks'][1]['c'][0]['c'][0]);
        $t->same(['', [], []], $jsonEncoded['blocks'][1]['c'][2]['c'][0]);
    },
    'regenerates legacy target inline constructor sidecars through native writer' => static function (TestRunner $t): void {
        $legacyLink = [
            't' => 'Link',
            'c' => [
                [['t' => 'Str', 'c' => 'source']],
                ['https://example.test/source', 'Legacy source'],
            ],
            'reviewQueue' => 'legacy-link-source',
            'sourceOrdinal' => 71,
        ];
        $legacyImage = [
            't' => 'Image',
            'c' => [
                [['t' => 'Str', 'c' => 'diagram']],
                ['media/diagram.png', 'Diagram title'],
            ],
            'reviewQueue' => 'legacy-image-source',
            'sourceOrdinal' => 72,
        ];
        $legacyPara = [
            't' => 'Para',
            'c' => [
                $legacyLink,
                ['t' => 'Space'],
                $legacyImage,
            ],
            'reviewQueue' => 'legacy-paragraph-source',
            'sourceOrdinal' => 70,
        ];
        $packet = [
            'pandoc-api-version' => [1, 17, 5, 1],
            'meta' => [],
            'blocks' => [$legacyPara],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $paragraph = $document->children[0];
            $link = $paragraph->children[0];
            $image = $paragraph->children[2];
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
            $encodedPara = $nativePacket['blocks'][0];
            $encodedLink = $encodedPara['c'][0];
            $encodedImage = $encodedPara['c'][2];

            $t->same($legacyLink, $link->attr('native'), "{$source} source link native payload");
            $t->same($legacyImage, $image->attr('native'), "{$source} source image native payload");
            $t->same($legacyLink['c'][1], $link->attr('targetNative'), "{$source} link target tuple");
            $t->same($legacyImage['c'][1], $image->attr('targetNative'), "{$source} image target tuple");
            $t->same('Para', $encodedPara['t'], "{$source} regenerated paragraph constructor");
            $t->same(false, array_key_exists('reviewQueue', $encodedPara), "{$source} paragraph sidecar dropped");
            $t->same(false, array_key_exists('sourceOrdinal', $encodedPara), "{$source} paragraph ordinal dropped");
            $t->same('Link', $encodedLink['t'], "{$source} regenerated link constructor");
            $t->same(3, count($encodedLink['c']), "{$source} current link payload shape");
            $t->same(['', [], []], $encodedLink['c'][0], "{$source} generated link attr tuple");
            $t->same($legacyLink['c'][0], $encodedLink['c'][1], "{$source} generated link label");
            $t->same($legacyLink['c'][1], $encodedLink['c'][2], "{$source} generated link target");
            $t->same(false, array_key_exists('reviewQueue', $encodedLink), "{$source} link sidecar dropped");
            $t->same(false, array_key_exists('sourceOrdinal', $encodedLink), "{$source} link ordinal dropped");
            $t->same('Image', $encodedImage['t'], "{$source} regenerated image constructor");
            $t->same(3, count($encodedImage['c']), "{$source} current image payload shape");
            $t->same(['', [], []], $encodedImage['c'][0], "{$source} generated image attr tuple");
            $t->same($legacyImage['c'][0], $encodedImage['c'][1], "{$source} generated image label");
            $t->same($legacyImage['c'][1], $encodedImage['c'][2], "{$source} generated image target");
            $t->same(false, array_key_exists('reviewQueue', $encodedImage), "{$source} image sidecar dropped");
            $t->same(false, array_key_exists('sourceOrdinal', $encodedImage), "{$source} image ordinal dropped");
        }
    },
    'records legacy table column helper native payloads on json and native ast nodes' => static function (TestRunner $t): void {
        $legacyTable = [
            't' => 'Table',
            'c' => [
                [],
                [['t' => 'AlignDefault'], ['t' => 'AlignCenter'], ['t' => 'AlignRight']],
                [0, 0.75, 0.25],
                [
                    [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Default']]]],
                    [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Center']]]],
                    [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Right']]]],
                ],
                [],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 17, 5, 1],
            'meta' => [],
            'blocks' => [$legacyTable],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];

            $t->same('table', $table->type, "{$source} legacy table node");
            $t->same($legacyTable, $table->attr('native'), "{$source} legacy table native payload");
            $t->same(['default', 'center', 'right'], $table->attr('alignments'), "{$source} legacy alignment values");
            $t->same(['AlignDefault', 'AlignCenter', 'AlignRight'], $table->attr('alignmentConstructors'), "{$source} legacy alignment constructors");
            $t->same($legacyTable['c'][1], $table->attr('alignmentNatives'), "{$source} legacy alignment native payloads");
            $t->same([null, 0.75, 0.25], $table->attr('widths'), "{$source} legacy widths");
            $t->same(['ColWidthDefault', 'ColWidth', 'ColWidth'], $table->attr('columnWidthConstructors'), "{$source} legacy width constructors");
            $t->same($legacyTable['c'][2], $table->attr('columnWidthNatives'), "{$source} legacy width native payloads");
        }

        $nativeRoundTrip = json_decode((new NativeWriter())->write($documents['native']), true, 512, JSON_THROW_ON_ERROR);
        $jsonEncoded = (new PandocJsonWriter())->toArray($documents['json']);

        $t->same($legacyTable, $nativeRoundTrip['blocks'][0]);
        $t->same('Table', $jsonEncoded['blocks'][0]['t']);
        $t->same(['t' => 'AlignDefault'], $jsonEncoded['blocks'][0]['c'][2][0][0]);
        $t->same(['t' => 'ColWidthDefault'], $jsonEncoded['blocks'][0]['c'][2][0][1]);
        $t->same(['t' => 'AlignCenter'], $jsonEncoded['blocks'][0]['c'][2][1][0]);
        $t->same(['t' => 'ColWidth', 'c' => 0.75], $jsonEncoded['blocks'][0]['c'][2][1][1]);
        $t->same(['t' => 'AlignRight'], $jsonEncoded['blocks'][0]['c'][2][2][0]);
        $t->same(['t' => 'ColWidth', 'c' => 0.25], $jsonEncoded['blocks'][0]['c'][2][2][1]);
    },
    'maps legacy table column width constructor variants through json and native stacks' => static function (TestRunner $t): void {
        $defaultWidth = ['t' => 'ColWidthDefault', 'c' => [], 'reviewQueue' => 'legacy-default-width-source'];
        $scalarWidth = ['t' => 'ColWidth', 'c' => 0.5, 'reviewQueue' => 'legacy-scalar-width-source'];
        $wrappedWidth = ['t' => 'ColWidth', 'c' => [0.75], 'reviewQueue' => 'legacy-wrapped-width-source'];
        $legacyWidths = [0, 0.25, $defaultWidth, $scalarWidth, $wrappedWidth];
        $legacyTable = [
            't' => 'Table',
            'c' => [
                [],
                [
                    ['t' => 'AlignDefault'],
                    ['t' => 'AlignLeft'],
                    ['t' => 'AlignCenter'],
                    ['t' => 'AlignRight'],
                    ['t' => 'AlignDefault'],
                ],
                $legacyWidths,
                [],
                [],
            ],
            'reviewQueue' => 'legacy-width-table-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 17, 5, 1],
            'meta' => [],
            'blocks' => [$legacyTable],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];

            $t->same('Table', $table->attr('constructor'), "{$source} legacy table constructor");
            $t->same($legacyTable, $table->attr('native'), "{$source} legacy table native payload");
            $t->same(['default', 'left', 'center', 'right', 'default'], $table->attr('alignments'), "{$source} legacy alignments");
            $t->same([null, 0.25, null, 0.5, 0.75], $table->attr('widths'), "{$source} legacy width variants");
            $t->same(['ColWidthDefault', 'ColWidth', 'ColWidthDefault', 'ColWidth', 'ColWidth'], $table->attr('columnWidthConstructors'), "{$source} legacy width constructors");
            $t->same($legacyWidths, $table->attr('columnWidthNatives'), "{$source} legacy width native payloads");

            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
            $jsonSpecs = $jsonPacket['blocks'][0]['c'][2];

            $t->same($legacyTable, $nativePacket['blocks'][0], "{$source} native writer preserves unchanged legacy width table");
            $t->same('Table', $jsonPacket['blocks'][0]['t'], "{$source} json writer upgrades legacy width table");
            $t->same(6, count($jsonPacket['blocks'][0]['c']), "{$source} json writer emits current table shape");
            $t->same(['t' => 'ColWidthDefault'], $jsonSpecs[0][1], "{$source} json writer regenerates numeric zero width");
            $t->same(['t' => 'ColWidth', 'c' => 0.25], $jsonSpecs[1][1], "{$source} json writer regenerates numeric width");
            $t->same($defaultWidth, $jsonSpecs[2][1], "{$source} json writer preserves default width sidecar");
            $t->same($scalarWidth, $jsonSpecs[3][1], "{$source} json writer preserves scalar width sidecar");
            $t->same($wrappedWidth, $jsonSpecs[4][1], "{$source} json writer preserves wrapped width sidecar");
        }
    },
    'preserves legacy table cell block payloads while upgrading table constructors' => static function (TestRunner $t): void {
        $headerCellBlocks = [
            ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Metric'],
            ], 'reviewQueue' => 'legacy-header-cell-source'],
        ];
        $bodyCellBlocks = [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Ready'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'state'],
            ], 'reviewQueue' => 'legacy-body-cell-source'],
        ];
        $legacyTable = [
            't' => 'Table',
            'c' => [
                [],
                [['t' => 'AlignLeft']],
                [0.35],
                [$headerCellBlocks],
                [[$bodyCellBlocks]],
            ],
            'reviewQueue' => 'legacy-table-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 17, 5, 1],
            'meta' => [],
            'blocks' => [$legacyTable],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        $payload = static fn (array $value): array => $value['c'] ?? $value;

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $tableAttrs = $table->attrs;
            unset($tableAttrs['constructor'], $tableAttrs['native']);
            $headerCell = $table->children[0]->children[0]->children[0];
            $body = $table->children[1];
            $bodyRow = $body->children[0];
            $bodyCell = $bodyRow->children[0];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('table', $tableAttrs, $table->children),
            ]);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('table', $tableAttrs, [
                    $table->children[0],
                    new AstNode('table_body', $body->attrs, [
                        new AstNode('table_row', $bodyRow->attrs, [
                            new AstNode('table_cell', $bodyCell->attrs, [
                                new AstNode('text', ['text' => 'Edited']),
                                new AstNode('space'),
                                new AstNode('text', ['text' => 'state']),
                            ]),
                        ]),
                    ]),
                ]),
            ]);

            $t->same($headerCellBlocks, $headerCell->attr('legacyTableCellBlocksNative'), "{$source} header cell records legacy block payload");
            $t->same($bodyCellBlocks, $bodyCell->attr('legacyTableCellBlocksNative'), "{$source} body cell records legacy block payload");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];
                $encodedHead = $payload($encodedTable['c'][3]);
                $encodedHeadRow = $payload($encodedHead[1][0]);
                $encodedHeadCell = $payload($encodedHeadRow[1][0]);
                $encodedBody = $payload($encodedTable['c'][4][0]);
                $encodedBodyRow = $payload($encodedBody[3][0]);
                $encodedBodyCell = $payload($encodedBodyRow[1][0]);
                $encodedHeaderBlocks = $encodedHeadCell[4];
                $encodedBodyBlocks = $encodedBodyCell[4];

                $t->same(6, count($encodedTable['c']), "{$source} {$writer} writer emits current table constructor");
                $t->same($headerCellBlocks, $encodedHeaderBlocks, "{$source} {$writer} writer preserves unchanged header cell blocks");
                $t->same($bodyCellBlocks, $encodedBodyBlocks, "{$source} {$writer} writer preserves unchanged body cell blocks");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedBody = $payload($encoded['blocks'][0]['c'][4][0]);
                $encodedRow = $payload($encodedBody[3][0]);
                $encodedCell = $payload($encodedRow[1][0]);
                $editedBlocks = $encodedCell[4];

                $t->same('Plain', $editedBlocks[0]['t'], "{$source} {$writer} writer regenerates edited cell block");
                $t->same('Edited', $editedBlocks[0]['c'][0]['c'], "{$source} {$writer} writer emits edited cell text");
                $t->same(false, array_key_exists('reviewQueue', $editedBlocks[0]), "{$source} {$writer} writer drops stale edited cell sidecar");
            }
        }
    },
    'emits native fallback constructors through pandoc json writer' => static function (TestRunner $t): void {
        $nativePacket = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'VendorBlock', 'c' => [
                    'source' => 'filter-extension',
                    'payload' => [['t' => 'Str', 'c' => 'opaque']],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Before'],
                    ['t' => 'Space'],
                    ['t' => 'VendorInline', 'c' => ['name' => 'review-anchor', 'value' => 42]],
                ]],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR));
        $encoded = (new PandocJsonWriter())->toArray($document);

        $t->same('native_block', $document->children[0]->type);
        $t->same('VendorBlock', $encoded['blocks'][0]['t']);
        $t->same($nativePacket['blocks'][0]['c'], $encoded['blocks'][0]['c']);
        $t->same('paragraph', $document->children[1]->type);
        $t->same('native_inline', $document->children[1]->children[1]->type);
        $t->same($nativePacket['blocks'][1]['c'], $encoded['blocks'][1]['c']);

        $writer = new PandocJsonWriter();
        $t->throws(InvalidArgumentException::class, static fn (): array => $writer->toArray(new AstNode('document', [], [
            new AstNode('native_block'),
        ])));
        $t->throws(InvalidArgumentException::class, static fn (): array => $writer->toArray(new AstNode('document', [], [
            new AstNode('paragraph', [], [new AstNode('native_inline')]),
        ])));
    },
    'reads pandoc json unknown constructors as native fallbacks' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'VendorBlock', 'c' => [
                    'source' => 'json-filter',
                    'payload' => [['t' => 'Str', 'c' => 'opaque']],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Before'],
                    ['t' => 'Space'],
                    ['t' => 'VendorInline', 'c' => ['name' => 'review-anchor', 'value' => 42]],
                ]],
            ],
        ];

        $document = (new PandocJsonReader())->readPacket($packet);
        $encoded = (new PandocJsonWriter())->toArray($document);
        $nativeBlock = $document->children[0];
        $paragraph = $document->children[1];
        $nativeInline = $paragraph->children[2];

        $t->same('native_block', $nativeBlock->type);
        $t->same('VendorBlock', $nativeBlock->attr('constructor'));
        $t->same($packet['blocks'][0], $nativeBlock->attr('native'));
        $t->same('paragraph', $paragraph->type);
        $t->same('native_inline', $nativeInline->type);
        $t->same('VendorInline', $nativeInline->attr('constructor'));
        $t->same($packet['blocks'][1]['c'][2], $nativeInline->attr('native'));
        $t->same($packet['blocks'], $encoded['blocks']);
    },
    'writes native inline fallback constructors as metadata inlines' => static function (TestRunner $t): void {
        $reviewInline = ['t' => 'VendorInline', 'c' => ['name' => 'review-anchor', 'value' => 42]];
        $titleInline = ['t' => 'VendorTitleInline', 'c' => ['label' => 'opaque-title']];
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [
                'reviewInline' => [
                    new AstNode('native_inline', [
                        'constructor' => 'VendorInline',
                        'native' => $reviewInline,
                    ]),
                ],
                'titleInlines' => [
                    new AstNode('native_inline', [
                        'constructor' => 'VendorTitleInline',
                        'native' => $titleInline,
                    ]),
                ],
            ],
        ]);

        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $roundTrip = (new PandocJsonReader())->readPacket($jsonPacket);
        $roundTripMeta = $roundTrip->attr('meta');

        $t->same('MetaInlines', $jsonPacket['meta']['reviewInline']['t']);
        $t->same($reviewInline, $jsonPacket['meta']['reviewInline']['c'][0]);
        $t->same('MetaInlines', $jsonPacket['meta']['title']['t']);
        $t->same($titleInline, $jsonPacket['meta']['title']['c'][0]);
        $t->same($jsonPacket['meta'], $nativePacket['meta']);
        $t->same('native_inline', $roundTripMeta['reviewInline']['children'][0]->type);
        $t->same('VendorInline', $roundTripMeta['reviewInline']['children'][0]->attr('constructor'));
        $t->same($reviewInline, $roundTripMeta['reviewInline']['children'][0]->attr('native'));
        $t->same('native_inline', $roundTripMeta['titleInlines'][0]->type);
        $t->same($titleInline, $roundTripMeta['titleInlines'][0]->attr('native'));
    },
    'records native str and space constructor provenance on coalesced text nodes' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Alpha'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'Beta'],
                    ['t' => 'SoftBreak'],
                    ['t' => 'Str', 'c' => 'Gamma'],
                    ['t' => 'Space'],
                    ['t' => 'Code', 'c' => [
                        ['', ['review-code'], []],
                        'delta',
                    ]],
                ]],
            ],
        ];

        $jsonDocument = (new PandocJsonReader())->readPacket($packet);
        $nativeDocument = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR));
        $jsonChildren = $jsonDocument->children[0]->children;
        $nativeChildren = $nativeDocument->children[0]->children;
        $encodedNative = json_decode((new NativeWriter())->write($nativeDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same(['text', 'space', 'text', 'softbreak', 'text', 'space', 'code'], array_map(static fn (AstNode $node): string => $node->type, $jsonChildren));
        $t->same('Str', $jsonChildren[0]->attr('constructor'));
        $t->same($packet['blocks'][0]['c'][0], $jsonChildren[0]->attr('native'));
        $t->same('Space', $jsonChildren[1]->attr('constructor'));
        $t->same($packet['blocks'][0]['c'][1], $jsonChildren[1]->attr('native'));

        $t->same(['text', 'softbreak', 'text', 'code'], array_map(static fn (AstNode $node): string => $node->type, $nativeChildren));
        $t->same('Alpha Beta', $nativeChildren[0]->attr('text'));
        $t->same(['Str', 'Space', 'Str'], $nativeChildren[0]->attr('nativeInlineConstructors'));
        $t->same(array_slice($packet['blocks'][0]['c'], 0, 3), $nativeChildren[0]->attr('nativeInlineParts'));
        $t->same('SoftBreak', $nativeChildren[1]->attr('constructor'));
        $t->same('Gamma ', $nativeChildren[2]->attr('text'));
        $t->same(['Str', 'Space'], $nativeChildren[2]->attr('nativeInlineConstructors'));
        $t->same(array_slice($packet['blocks'][0]['c'], 4, 2), $nativeChildren[2]->attr('nativeInlineParts'));
        $t->same('Code', $nativeChildren[3]->attr('constructor'));
        $t->same($packet['blocks'][0]['c'], $encodedNative['blocks'][0]['c']);
    },
    'preserves coalesced native text constructor parts through json and native writers' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Alpha  Beta'],
                    ['t' => 'Space'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'Gamma'],
                ]],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR));
        $text = $document->children[0]->children[0];
        $nativeEncoded = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $jsonEncoded = (new PandocJsonWriter())->toArray($document);

        $t->same('text', $text->type);
        $t->same('Alpha  Beta  Gamma', $text->attr('text'));
        $t->same(['Str', 'Space', 'Space', 'Str'], $text->attr('nativeInlineConstructors'));
        $t->same($packet['blocks'][0]['c'], $text->attr('nativeInlineParts'));
        $t->same($packet['blocks'][0]['c'], $nativeEncoded['blocks'][0]['c']);
        $t->same($packet['blocks'][0]['c'], $jsonEncoded['blocks'][0]['c']);

        $editedText = new AstNode('text', array_replace($text->attrs, ['text' => 'Edited text']));
        $editedDocument = new AstNode('document', $document->attrs, [
            new AstNode('paragraph', $document->children[0]->attrs, [$editedText]),
        ]);
        $editedNative = json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR);
        $editedJson = (new PandocJsonWriter())->toArray($editedDocument);

        $t->same([
            ['t' => 'Str', 'c' => 'Edited'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'text'],
        ], $editedNative['blocks'][0]['c']);
        $t->same([
            ['t' => 'Str', 'c' => 'Edited text'],
        ], $editedJson['blocks'][0]['c']);
    },
    'accepts single wrapped str constructor text payloads' => static function (TestRunner $t): void {
        $sourceInlines = [
            ['t' => 'Str', 'c' => ['Alpha'], 'reviewQueue' => 'alpha-source'],
            ['t' => 'Space', 'reviewQueue' => 'space-source'],
            ['t' => 'Str', 'c' => ['Beta'], 'reviewQueue' => 'beta-source'],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => $sourceInlines],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $paragraph = $document->children[0];
            $textNodes = array_values(array_filter(
                $paragraph->children,
                static fn (AstNode $node): bool => $node->type === 'text'
            ));

            $t->same('Alpha Beta', $paragraph->attr('text'), "{$source} paragraph text");
            $t->same($source === 'json' ? ['text', 'space', 'text'] : ['text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), "{$source} text node shape");
            $t->same($source === 'json' ? 'Alpha' : 'Alpha Beta', $textNodes[0]->attr('text'), "{$source} first wrapped Str text");
            if ($source === 'json') {
                $t->same($sourceInlines[0], $textNodes[0]->attr('native'), "{$source} first wrapped Str native payload");
            } else {
                $t->same(['Str', 'Space', 'Str'], $textNodes[0]->attr('nativeInlineConstructors'), "{$source} coalesced wrapped Str native constructors");
                $t->same($sourceInlines, $textNodes[0]->attr('nativeInlineParts'), "{$source} coalesced wrapped Str native payloads");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($sourceInlines, $encoded['blocks'][0]['c'], "{$source} {$writer} preserves unchanged wrapped Str payloads");
            }

            $editedText = new AstNode('text', array_replace($textNodes[0]->attrs, ['text' => 'Edited']));
            $editedChildren = $source === 'json'
                ? [$editedText, $paragraph->children[1], $paragraph->children[2]]
                : [$editedText];
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], $editedChildren),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same('Edited', $encoded['blocks'][0]['c'][0]['c'], "{$source} {$writer} canonicalizes edited wrapped Str");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]['c'][0]), "{$source} {$writer} drops stale edited Str sidecar");
            }
        }
    },
    'preserves native soft and line break text parts through json and native writers' => static function (TestRunner $t): void {
        $nativeParts = [
            ['t' => 'Str', 'c' => 'Alpha', 'reviewQueue' => 'alpha-source'],
            ['t' => 'SoftBreak', 'reviewQueue' => 'soft-break-source'],
            ['t' => 'Str', 'c' => 'Beta', 'reviewQueue' => 'beta-source'],
            ['t' => 'LineBreak', 'reviewQueue' => 'line-break-source'],
            ['t' => 'Str', 'c' => 'Gamma', 'reviewQueue' => 'gamma-source'],
        ];
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', [
                    'text' => 'Alpha Beta Gamma',
                    'nativeInlineParts' => $nativeParts,
                ]),
            ]),
        ]);

        $packets = [
            'json writer' => (new PandocJsonWriter())->toArray($document),
            'native writer' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
        ];

        foreach ($packets as $writer => $packet) {
            $inlines = $packet['blocks'][0]['c'];

            $t->same($nativeParts, $inlines, "{$writer} preserves text separator native parts");
            $t->same('soft-break-source', $inlines[1]['reviewQueue'], "{$writer} keeps SoftBreak sidecar");
            $t->same('line-break-source', $inlines[3]['reviewQueue'], "{$writer} keeps LineBreak sidecar");
            $t->same(false, array_key_exists('c', $inlines[1]), "{$writer} keeps SoftBreak nullary");
            $t->same(false, array_key_exists('c', $inlines[3]), "{$writer} keeps LineBreak nullary");
        }

        $roundTrips = [
            'json reader' => (new PandocJsonReader())->readPacket($packets['json writer']),
            'native reader' => (new NativeReader())->read(json_encode($packets['native writer'], JSON_THROW_ON_ERROR)),
        ];

        foreach ($roundTrips as $source => $roundTrip) {
            $t->same(['text', 'softbreak', 'text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children[0]->children), "{$source} reads preserved separator constructors");
        }
    },
    'preserves native text part constructors in textual native output' => static function (TestRunner $t): void {
        $nativeParts = [
            ['t' => 'Str', 'c' => ['Alpha']],
            ['t' => 'SoftBreak'],
            ['t' => 'Str', 'c' => 'Beta'],
            ['t' => 'LineBreak'],
            ['t' => 'Str', 'c' => 'Gamma'],
        ];
        $emptyStr = ['t' => 'Str', 'c' => ''];
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', [
                    'text' => 'Alpha Beta Gamma',
                    'nativeInlineParts' => $nativeParts,
                ]),
            ]),
            new AstNode('plain', [], [
                new AstNode('text', [
                    'text' => '',
                    'nativeInlineParts' => [$emptyStr],
                ]),
            ]),
        ]);

        $nativeText = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $roundTrip = (new NativeReader())->read($nativeText);
        $paragraphChildren = $roundTrip->children[0]->children;
        $plainChildren = $roundTrip->children[1]->children;

        $t->contains('SoftBreak', $nativeText);
        $t->contains('LineBreak', $nativeText);
        $t->contains('Str ""', $nativeText);
        $t->same(false, str_contains($nativeText, 'Str "Alpha Beta Gamma"'));
        $t->same(['text', 'softbreak', 'text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraphChildren));
        $t->same('', $plainChildren[0]->attr('text'));
    },
    'preserves empty native string constructors through json and native writers' => static function (TestRunner $t): void {
        $emptyStr = ['t' => 'Str', 'c' => '', 'reviewQueue' => 'empty-str-source'];
        $codeInline = ['t' => 'Code', 'c' => [['empty-code', ['php'], [['data-source', 'empty-str']]], 'wp_insert_post']];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'title' => ['t' => 'MetaInlines', 'c' => [$emptyStr], 'reviewQueue' => 'empty-title-source'],
            ],
            'blocks' => [
                ['t' => 'Para', 'c' => [$emptyStr]],
                ['t' => 'Para', 'c' => [$emptyStr, $codeInline]],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR));
        $meta = $document->attr('meta');
        $titleInlines = $meta['titleInlines'];
        $firstText = $document->children[0]->children[0];
        $secondText = $document->children[1]->children[0];
        $code = $document->children[1]->children[1];

        $t->same(true, is_array($titleInlines));
        $t->same('text', $titleInlines[0]->type);
        $t->same('', $titleInlines[0]->attr('text'));
        $t->same([$emptyStr], $titleInlines[0]->attr('nativeInlineParts'));
        $t->same('text', $firstText->type);
        $t->same('', $firstText->attr('text'));
        $t->same('Str', $firstText->attr('constructor'));
        $t->same($emptyStr, $firstText->attr('native'));
        $t->same([$emptyStr], $firstText->attr('nativeInlineParts'));
        $t->same('text', $secondText->type);
        $t->same('', $secondText->attr('text'));
        $t->same($emptyStr, $secondText->attr('native'));
        $t->same('code', $code->type);
        $t->same('wp_insert_post', $code->attr('text'));

        foreach ([
            'json writer' => (new PandocJsonWriter())->toArray($document),
            'native writer' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
        ] as $writer => $encoded) {
            $t->same($emptyStr, $encoded['meta']['title']['c'][0], "{$writer} preserves empty metadata string constructor");
            $t->same($emptyStr, $encoded['blocks'][0]['c'][0], "{$writer} preserves standalone empty string constructor");
            $t->same($emptyStr, $encoded['blocks'][1]['c'][0], "{$writer} preserves empty string before inline constructor");
            $t->same($codeInline, $encoded['blocks'][1]['c'][1], "{$writer} preserves neighboring code constructor");
        }
    },
    'records pandoc constructor provenance on json and native helper ast nodes' => static function (TestRunner $t): void {
        $citationRecord = [
            'citationId' => 'source-a',
            'citationPrefix' => [
                ['t' => 'Str', 'c' => 'see'],
            ],
            'citationSuffix' => [
                ['t' => 'Str', 'c' => 'p.'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => '4'],
            ],
            'citationMode' => ['t' => 'SuppressAuthor'],
            'citationNoteNum' => 2,
            'citationHash' => 404,
        ];
        $citeInline = ['t' => 'Cite', 'c' => [
            [$citationRecord],
            [
                ['t' => 'Str', 'c' => '[see'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => '-@source-a,'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'p.'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => '4]'],
            ],
        ]];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['constructor-table', ['provenance'], [['data-source', 'constructor-fixture']]],
                ['t' => 'Caption', 'c' => [
                    null,
                    [],
                ]],
                [
                    [['t' => 'AlignCenter'], ['t' => 'ColWidth', 'c' => 0.5]],
                ],
                ['t' => 'TableHead', 'c' => [
                    ['head-attrs', [], []],
                    [
                        ['t' => 'Row', 'c' => [
                            ['head-row', [], []],
                            [
                                ['t' => 'Cell', 'c' => [
                                    ['head-cell', [], []],
                                    ['t' => 'AlignDefault'],
                                    ['t' => 'RowSpan', 'c' => 1],
                                    ['t' => 'ColSpan', 'c' => 1],
                                    [
                                        ['t' => 'Plain', 'c' => [
                                            ['t' => 'Str', 'c' => 'Head'],
                                        ]],
                                    ],
                                ]],
                            ],
                        ]],
                    ],
                ]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['body-attrs', [], []],
                        ['t' => 'RowHeadColumns', 'c' => 1],
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['body-row', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['body-cell', [], []],
                                        ['t' => 'AlignRight'],
                                        ['t' => 'RowSpan', 'c' => 2],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [
                                            ['t' => 'Para', 'c' => [
                                                ['t' => 'Str', 'c' => 'Body'],
                                            ]],
                                        ],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [
                    ['foot-attrs', [], []],
                    [
                        ['t' => 'Row', 'c' => [
                            ['foot-row', [], []],
                            [
                                ['t' => 'Cell', 'c' => [
                                    ['foot-cell', [], []],
                                    ['t' => 'AlignDefault'],
                                    ['t' => 'RowSpan', 'c' => 1],
                                    ['t' => 'ColSpan', 'c' => 1],
                                    [
                                        ['t' => 'Plain', 'c' => [
                                            ['t' => 'Str', 'c' => 'Foot'],
                                        ]],
                                    ],
                                ]],
                            ],
                        ]],
                    ],
                ]],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Plain', 'c' => [$citeInline]],
                $tableBlock,
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $plain = $document->children[0];
            $citation = $plain->children[0];
            $table = $document->children[1];
            $head = $table->children[0];
            $body = $table->children[1];
            $foot = $table->children[2];
            $headRow = $head->children[0];
            $headCell = $headRow->children[0];
            $bodyRow = $body->children[0];
            $bodyCell = $bodyRow->children[0];
            $footRow = $foot->children[0];
            $footCell = $footRow->children[0];

            $t->same('Plain', $plain->attr('constructor'), "{$source} plain constructor");
            $t->same($packet['blocks'][0], $plain->attr('native'), "{$source} plain native payload");
            $t->same('Cite', $citation->attr('constructor'), "{$source} cite constructor");
            $t->same($citeInline, $citation->attr('native'), "{$source} cite native payload");
            $t->same('Citation', $citation->attr('citationConstructor'), "{$source} citation record constructor");
            $t->same($citationRecord, $citation->attr('citationNative'), "{$source} citation native payload");
            $t->same('suppress_author', $citation->attr('mode'), "{$source} citation mode");
            $t->same('SuppressAuthor', $citation->attr('citationModeConstructor'), "{$source} citation mode constructor");
            $t->same($citationRecord['citationMode'], $citation->attr('citationModeNative'), "{$source} citation mode native payload");
            $t->same('Table', $table->attr('constructor'), "{$source} table constructor");
            $t->same($tableBlock, $table->attr('native'), "{$source} table native payload");
            $t->same('TableHead', $head->attr('constructor'), "{$source} table head constructor");
            $t->same('TableBody', $body->attr('constructor'), "{$source} table body constructor");
            $t->same('TableFoot', $foot->attr('constructor'), "{$source} table foot constructor");
            $t->same('Row', $headRow->attr('constructor'), "{$source} head row constructor");
            $t->same('Cell', $headCell->attr('constructor'), "{$source} head cell constructor");
            $t->same('Row', $bodyRow->attr('constructor'), "{$source} body row constructor");
            $t->same('Cell', $bodyCell->attr('constructor'), "{$source} body cell constructor");
            $t->same('Row', $footRow->attr('constructor'), "{$source} foot row constructor");
            $t->same('Cell', $footCell->attr('constructor'), "{$source} foot cell constructor");
            $t->same(1, $body->attr('rowHeadColumns'), "{$source} row head columns");
            $t->same(2, $bodyCell->attr('rowspan'), "{$source} body cell rowspan");
            $t->same('right', $bodyCell->attr('align'), "{$source} body cell alignment");
        }
    },
    'records pandoc citation mode helper constructors on json and native ast nodes' => static function (TestRunner $t): void {
        $records = [
            [
                'citationId' => 'source-normal',
                'citationPrefix' => [],
                'citationSuffix' => [],
                'citationMode' => ['t' => 'NormalCitation'],
                'citationNoteNum' => 0,
                'citationHash' => 11,
            ],
            [
                'citationId' => 'source-author',
                'citationPrefix' => [],
                'citationSuffix' => [],
                'citationMode' => ['t' => 'AuthorInText'],
                'citationNoteNum' => 0,
                'citationHash' => 22,
            ],
            [
                'citationId' => 'source-suppressed',
                'citationPrefix' => [
                    ['t' => 'Str', 'c' => 'compare'],
                ],
                'citationSuffix' => [],
                'citationMode' => ['t' => 'SuppressAuthor'],
                'citationNoteNum' => 0,
                'citationHash' => 33,
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Cite', 'c' => [
                        $records,
                        [
                            ['t' => 'Str', 'c' => '[@source-normal;'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '@source-author;'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'compare'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '-@source-suppressed]'],
                        ],
                    ]],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $cluster = $document->children[0]->children[0];
            $citations = $cluster->children;

            $t->same('citation_group', $cluster->type, "{$source} cite cluster type");
            $t->same(['source-normal', 'source-author', 'source-suppressed'], array_map(static fn (AstNode $citation): string => $citation->attr('id'), $citations), "{$source} citation ids");
            $t->same(['normal', 'author_in_text', 'suppress_author'], array_map(static fn (AstNode $citation): string => $citation->attr('mode'), $citations), "{$source} citation modes");
            $t->same(['NormalCitation', 'AuthorInText', 'SuppressAuthor'], array_map(static fn (AstNode $citation): string => $citation->attr('citationModeConstructor'), $citations), "{$source} citation mode constructors");
            $t->same($records[0]['citationMode'], $citations[0]->attr('citationModeNative'), "{$source} normal citation mode native payload");
            $t->same($records[1]['citationMode'], $citations[1]->attr('citationModeNative'), "{$source} author citation mode native payload");
            $t->same($records[2]['citationMode'], $citations[2]->attr('citationModeNative'), "{$source} suppress citation mode native payload");
            $t->same('compare -@source-suppressed', $citations[2]->attr('text'), "{$source} suppressed citation source text");
        }

        $jsonPacket = (new PandocJsonWriter())->toArray($documents['json']);
        $nativePacket = json_decode((new NativeWriter())->write($documents['native']), true, 512, JSON_THROW_ON_ERROR);

        $t->same(['NormalCitation', 'AuthorInText', 'SuppressAuthor'], array_map(static fn (array $record): string => $record['citationMode']['t'], $jsonPacket['blocks'][0]['c'][0]['c'][0]), 'json writer citation mode constructors');
        $t->same(['NormalCitation', 'AuthorInText', 'SuppressAuthor'], array_map(static fn (array $record): string => $record['citationMode']['t'], $nativePacket['blocks'][0]['c'][0]['c'][0]), 'native writer citation mode constructors');
    },
    'records pandoc meta constructor provenance on json and native ast documents' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => ['t' => 'MetaMap', 'c' => [
                'title' => ['t' => 'MetaInlines', 'c' => [
                    ['t' => 'Str', 'c' => 'Constructor'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'metadata'],
                ]],
                'draft' => ['t' => 'MetaBool', 'c' => false],
                'review' => ['t' => 'MetaMap', 'c' => [
                    'queue' => ['t' => 'MetaString', 'c' => 'json-native'],
                    'flags' => ['t' => 'MetaList', 'c' => [
                        ['t' => 'MetaString', 'c' => 'core'],
                        ['t' => 'MetaBool', 'c' => true],
                    ]],
                ]],
                'body' => ['t' => 'MetaBlocks', 'c' => [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'review'],
                    ]],
                ]],
            ]],
            'blocks' => [],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $constructors = $document->attr('metaConstructors');
            $nativeValues = $document->attr('metaNativeValues');

            $t->same('MetaMap', $document->attr('metaConstructor'), "{$source} top-level meta constructor");
            $t->same($packet['meta'], $document->attr('metaNative'), "{$source} top-level meta native payload");
            $t->same('MetaInlines', $constructors['title']['_constructor'], "{$source} title meta constructor");
            $t->same('MetaBool', $constructors['draft']['_constructor'], "{$source} bool meta constructor");
            $t->same('MetaMap', $constructors['review']['_constructor'], "{$source} map meta constructor");
            $t->same('MetaString', $constructors['review']['items']['queue']['_constructor'], "{$source} nested string meta constructor");
            $t->same('MetaList', $constructors['review']['items']['flags']['_constructor'], "{$source} nested list meta constructor");
            $t->same('MetaString', $constructors['review']['items']['flags']['items'][0]['_constructor'], "{$source} nested list item string constructor");
            $t->same('MetaBool', $constructors['review']['items']['flags']['items'][1]['_constructor'], "{$source} nested list item bool constructor");
            $t->same('MetaBlocks', $constructors['body']['_constructor'], "{$source} blocks meta constructor");
            $t->same($packet['meta']['c']['title'], $nativeValues['title'], "{$source} title meta native value");
            $t->same($packet['meta']['c']['draft'], $nativeValues['draft'], "{$source} bool meta native value");
            $t->same($packet['meta']['c']['review'], $nativeValues['review'], "{$source} nested meta native value");
            $t->same($packet['meta']['c']['body'], $nativeValues['body'], "{$source} block meta native value");
        }

        $jsonPacket = (new PandocJsonWriter())->toArray($documents['json']);
        $nativePacket = json_decode((new NativeWriter())->write($documents['native']), true, 512, JSON_THROW_ON_ERROR);

        $t->same($packet['meta']['c'], $jsonPacket['meta']);
        $t->same($packet['meta']['c'], $nativePacket['meta']);
    },
    'records empty pandoc MetaMap constructor envelopes on json and native ast documents' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => ['t' => 'MetaMap', 'c' => []],
            'blocks' => [],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $provenance = $document->attr('metaConstructorProvenance');
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same('MetaMap', $document->attr('metaConstructor'), "{$source} records top-level empty MetaMap constructor");
            $t->same($packet['meta'], $document->attr('metaNative'), "{$source} records top-level empty MetaMap native payload");
            $t->same('MetaMap', $provenance['/']['constructor'], "{$source} indexes root MetaMap constructor provenance");
            $t->same($packet['meta'], $provenance['/']['native'], "{$source} indexes root MetaMap native payload");
            $t->same([], $jsonPacket['meta'], "{$source} json writer canonicalizes empty metadata map");
            $t->same([], $nativePacket['meta'], "{$source} native writer canonicalizes empty metadata map");
        }
    },
    'preserves metadata native value payloads through json and native writers until edited' => static function (TestRunner $t): void {
        $titleNative = ['t' => 'MetaString', 'c' => 'Source title', 'reviewQueue' => 'title-source'];
        $reviewNative = ['t' => 'MetaMap', 'c' => [
            'status' => ['t' => 'MetaString', 'c' => 'queued', 'reviewQueue' => 'status-source'],
            'draft' => ['t' => 'MetaBool', 'c' => false],
        ], 'reviewQueue' => 'review-source'];
        $bodyNative = ['t' => 'MetaBlocks', 'c' => [
            ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Body'],
            ]],
        ], 'reviewQueue' => 'body-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => ['t' => 'MetaMap', 'c' => [
                'title' => $titleNative,
                'review' => $reviewNative,
                'body' => $bodyNative,
            ]],
            'blocks' => [],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $nativeValues = $document->attr('metaNativeValues');
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same($titleNative, $nativeValues['title'], "{$source} title native value payload");
            $t->same($reviewNative, $nativeValues['review'], "{$source} map native value payload");
            $t->same($bodyNative, $nativeValues['body'], "{$source} block native value payload");
            $t->same($titleNative, $jsonPacket['meta']['title'], "{$source} json writer preserves title value payload");
            $t->same($reviewNative, $jsonPacket['meta']['review'], "{$source} json writer preserves map value payload");
            $t->same($bodyNative, $jsonPacket['meta']['body'], "{$source} json writer preserves block value payload");
            $t->same($titleNative, $nativePacket['meta']['title'], "{$source} native writer preserves title value payload");
            $t->same($reviewNative, $nativePacket['meta']['review'], "{$source} native writer preserves map value payload");
            $t->same($bodyNative, $nativePacket['meta']['body'], "{$source} native writer preserves block value payload");

            $meta = $document->attr('meta');
            $meta['title'] = 'Edited title';
            $editedDocument = new AstNode('document', array_replace($document->attrs, ['meta' => $meta]), $document->children);
            $editedJson = (new PandocJsonWriter())->toArray($editedDocument);
            $editedNative = json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR);

            $t->same(['t' => 'MetaString', 'c' => 'Edited title'], $editedJson['meta']['title'], "{$source} json writer regenerates edited title payload");
            $t->same(['t' => 'MetaString', 'c' => 'Edited title'], $editedNative['meta']['title'], "{$source} native writer regenerates edited title payload");
            $t->same($reviewNative, $editedJson['meta']['review'], "{$source} json writer keeps unchanged map payload");
            $t->same($reviewNative, $editedNative['meta']['review'], "{$source} native writer keeps unchanged map payload");
            $t->same($bodyNative, $editedJson['meta']['body'], "{$source} json writer keeps unchanged block payload");
            $t->same($bodyNative, $editedNative['meta']['body'], "{$source} native writer keeps unchanged block payload");
        }
    },
    'preserves nested metadata native payloads when rebuilding edited containers' => static function (TestRunner $t): void {
        $queueNative = ['t' => 'MetaString', 'c' => 'json-native', 'reviewQueue' => 'queue-source'];
        $flagNative = ['t' => 'MetaBool', 'c' => true, 'reviewQueue' => 'flag-source'];
        $slashKeyNative = ['t' => 'MetaString', 'c' => 'escaped-key', 'reviewQueue' => 'slash-key-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => ['t' => 'MetaMap', 'c' => [
                'review' => ['t' => 'MetaMap', 'c' => [
                    'queue' => $queueNative,
                    'flags' => ['t' => 'MetaList', 'c' => [
                        $flagNative,
                        ['t' => 'MetaString', 'c' => 'stale', 'reviewQueue' => 'stale-flag-source'],
                    ], 'reviewQueue' => 'flags-source'],
                    'owner/team' => $slashKeyNative,
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
                    'queue' => 'json-native',
                    'flags' => [
                        'type' => 'list',
                        'items' => [
                            true,
                            'edited',
                        ],
                    ],
                    'owner/team' => 'escaped-key',
                    'added' => 'new-field',
                ],
            ];
            $editedDocument = new AstNode('document', array_replace($document->attrs, ['meta' => $meta]), $document->children);

            $provenance = $document->attr('metaConstructorProvenance');
            $jsonPacket = (new PandocJsonWriter())->toArray($editedDocument);
            $nativePacket = json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR);

            $t->same($queueNative, $provenance['/review/queue']['native'], "{$source} reader indexes nested string metadata native payload");
            $t->same($slashKeyNative, $provenance['/review/owner~1team']['native'], "{$source} reader escapes slash metadata paths");

            foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $writer => $encoded) {
                $encodedReview = $encoded['meta']['review'];
                $encodedFlags = $encodedReview['c']['flags'];

                $t->same('MetaMap', $encodedReview['t'], "{$source} {$writer} writer rebuilds edited metadata map");
                $t->same(false, array_key_exists('reviewQueue', $encodedReview), "{$source} {$writer} writer drops stale edited map sidecar");
                $t->same($queueNative, $encodedReview['c']['queue'], "{$source} {$writer} writer preserves unchanged nested string sidecar");
                $t->same($slashKeyNative, $encodedReview['c']['owner/team'], "{$source} {$writer} writer preserves escaped-key nested sidecar");
                $t->same('MetaList', $encodedFlags['t'], "{$source} {$writer} writer rebuilds edited metadata list");
                $t->same(false, array_key_exists('reviewQueue', $encodedFlags), "{$source} {$writer} writer drops stale edited list sidecar");
                $t->same($flagNative, $encodedFlags['c'][0], "{$source} {$writer} writer preserves unchanged nested list item sidecar");
                $t->same(['t' => 'MetaString', 'c' => 'edited'], $encodedFlags['c'][1], "{$source} {$writer} writer regenerates edited list item");
                $t->same(['t' => 'MetaString', 'c' => 'new-field'], $encodedReview['c']['added'], "{$source} {$writer} writer emits new map field");
            }
        }
    },
    'preserves nested metadata map-list payloads while regenerating edited values' => static function (TestRunner $t): void {
        $statusNative = ['t' => 'MetaString', 'c' => 'queued', 'reviewQueue' => 'status-source'];
        $firstAliasNative = ['t' => 'MetaString', 'c' => 'alpha', 'reviewQueue' => 'alias-alpha-source'];
        $secondAliasNative = ['t' => 'MetaString', 'c' => 'beta', 'reviewQueue' => 'alias-beta-source'];
        $aliasesNative = ['t' => 'MetaList', 'c' => [
            $firstAliasNative,
            $secondAliasNative,
        ], 'reviewQueue' => 'aliases-source'];
        $reviewNative = ['t' => 'MetaMap', 'c' => [
            'status' => $statusNative,
            'aliases' => $aliasesNative,
        ], 'reviewQueue' => 'review-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => ['t' => 'MetaMap', 'c' => [
                'review' => $reviewNative,
            ]],
            'blocks' => [],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $meta = $document->attr('meta');
            if (($meta['review']['type'] ?? null) === 'map') {
                $meta['review']['items']['aliases']['items'][1] = 'gamma';
            } else {
                $meta['review']['c']['aliases']['c'][1]['c'] = 'gamma';
            }
            $editedDocument = new AstNode('document', array_replace($document->attrs, ['meta' => $meta]), $document->children);

            foreach ([
                'json writer' => (new PandocJsonWriter())->toArray($editedDocument),
                'native writer' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $review = $encoded['meta']['review'];
                $aliases = $review['c']['aliases'];

                $t->same('MetaMap', $review['t'], "{$source} {$writer} regenerates edited map constructor");
                $t->same(false, array_key_exists('reviewQueue', $review), "{$source} {$writer} drops edited map sidecar");
                $t->same($statusNative, $review['c']['status'], "{$source} {$writer} preserves unchanged nested map value sidecar");
                $t->same('MetaList', $aliases['t'], "{$source} {$writer} regenerates edited list constructor");
                $t->same(false, array_key_exists('reviewQueue', $aliases), "{$source} {$writer} drops edited list sidecar");
                $t->same($firstAliasNative, $aliases['c'][0], "{$source} {$writer} preserves unchanged nested list item sidecar");
                $t->same(['t' => 'MetaString', 'c' => 'gamma'], $aliases['c'][1], "{$source} {$writer} regenerates edited nested list item");
                $t->same(false, array_key_exists('reviewQueue', $aliases['c'][1]), "{$source} {$writer} drops stale edited list item sidecar");
            }
        }
    },
    'preserves metadata child native payloads when rebuilding typed meta wrappers' => static function (TestRunner $t): void {
        $headlineStr = ['t' => 'Str', 'c' => 'Alpha', 'reviewQueue' => 'headline-str-source'];
        $headlineSpace = ['t' => 'Space', 'reviewQueue' => 'headline-space-source'];
        $headlineCode = ['t' => 'Code', 'c' => [
            ['headline-code', ['review'], [['data-source', 'meta']]],
            'ticket-42',
        ], 'reviewQueue' => 'headline-code-source'];
        $bodyPara = ['t' => 'Para', 'c' => [
            ['t' => 'Str', 'c' => 'Body'],
        ], 'reviewQueue' => 'body-para-source'];
        $bodyRule = ['t' => 'HorizontalRule', 'reviewQueue' => 'body-rule-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'headline' => ['t' => 'MetaInlines', 'c' => [
                    $headlineStr,
                    $headlineSpace,
                    $headlineCode,
                ], 'reviewQueue' => 'headline-wrapper-source'],
                'body' => ['t' => 'MetaBlocks', 'c' => [
                    $bodyPara,
                    $bodyRule,
                ], 'reviewQueue' => 'body-wrapper-source'],
            ],
            'blocks' => [],
        ];

        $document = (new PandocJsonReader())->readPacket($packet);
        $meta = $document->attr('meta');
        $headline = $meta['headline'];
        $body = $meta['body'];
        $meta['headline'] = [
            'type' => 'inlines',
            'children' => [
                ...$headline['children'],
                new AstNode('space'),
                new AstNode('text', ['text' => 'edited']),
            ],
        ];
        $meta['body'] = [
            'type' => 'blocks',
            'children' => [
                ...$body['children'],
                new AstNode('null_block'),
            ],
        ];
        $editedDocument = new AstNode('document', array_replace($document->attrs, ['meta' => $meta]), $document->children);

        $packets = [
            'json' => (new PandocJsonWriter())->toArray($editedDocument),
            'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
        ];

        foreach ($packets as $writer => $encoded) {
            $encodedHeadline = $encoded['meta']['headline'];
            $encodedBody = $encoded['meta']['body'];

            $t->same('MetaInlines', $encodedHeadline['t'], "{$writer} writer rebuilds edited inline metadata wrapper");
            $t->same(false, array_key_exists('reviewQueue', $encodedHeadline), "{$writer} writer drops stale inline metadata wrapper sidecar");
            $t->same($headlineStr, $encodedHeadline['c'][0], "{$writer} writer preserves inline metadata text payload");
            $t->same($headlineSpace, $encodedHeadline['c'][1], "{$writer} writer preserves inline metadata space payload");
            $t->same($headlineCode, $encodedHeadline['c'][2], "{$writer} writer preserves inline metadata code payload");
            $t->same(['t' => 'Space'], $encodedHeadline['c'][3], "{$writer} writer emits appended metadata space");
            $t->same(['t' => 'Str', 'c' => 'edited'], $encodedHeadline['c'][4], "{$writer} writer emits appended metadata text");

            $t->same('MetaBlocks', $encodedBody['t'], "{$writer} writer rebuilds edited block metadata wrapper");
            $t->same(false, array_key_exists('reviewQueue', $encodedBody), "{$writer} writer drops stale block metadata wrapper sidecar");
            $t->same($bodyPara, $encodedBody['c'][0], "{$writer} writer preserves block metadata paragraph payload");
            $t->same($bodyRule, $encodedBody['c'][1], "{$writer} writer preserves block metadata rule payload");
            $t->same(['t' => 'Null'], $encodedBody['c'][2], "{$writer} writer emits appended metadata null block");
        }
    },
    'records pandoc helper constructor provenance on json and native ast nodes' => static function (TestRunner $t): void {
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['helper-table', [], []],
                ['t' => 'Caption', 'c' => [null, []]],
                [
                    [['t' => 'AlignRight'], ['t' => 'ColWidth', 'c' => 0.4]],
                    [['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']],
                ],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => 2],
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignCenter'],
                                        ['t' => 'RowSpan', 'c' => 3],
                                        ['t' => 'ColSpan', 'c' => 2],
                                        [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Cell']]]],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'OrderedList', 'c' => [
                    [7, ['t' => 'UpperRoman'], ['t' => 'TwoParens']],
                    [[['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Item']]]]],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Quoted', 'c' => [
                        ['t' => 'SingleQuote'],
                        [['t' => 'Str', 'c' => 'quoted']],
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Math', 'c' => [['t' => 'InlineMath'], 'x+1']],
                    ['t' => 'Space'],
                    ['t' => 'Math', 'c' => [['t' => 'DisplayMath'], 'y=2']],
                ]],
                $tableBlock,
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $orderedList = $document->children[0];
            $paragraph = $document->children[1];
            $table = $document->children[2];
            $quoted = array_values(array_filter(
                $paragraph->children,
                static fn (AstNode $node): bool => $node->type === 'quoted'
            ))[0];
            $mathNodes = array_values(array_filter(
                $paragraph->children,
                static fn (AstNode $node): bool => $node->type === 'math'
            ));
            $body = $table->children[0];
            $cell = $body->children[0]->children[0];

            $t->same('upper_roman', $orderedList->attr('style'), "{$source} ordered list style");
            $t->same('UpperRoman', $orderedList->attr('listStyleConstructor'), "{$source} list style constructor");
            $t->same('two_parens', $orderedList->attr('delimiter'), "{$source} ordered list delimiter");
            $t->same('TwoParens', $orderedList->attr('listDelimiterConstructor'), "{$source} list delimiter constructor");
            $t->same('single', $quoted->attr('kind'), "{$source} quote kind");
            $t->same('SingleQuote', $quoted->attr('quoteTypeConstructor'), "{$source} quote type constructor");
            $t->same(['t' => 'SingleQuote'], $quoted->attr('quoteTypeNative'), "{$source} quote type native payload");
            $t->same(false, $mathNodes[0]->attr('display'), "{$source} inline math display flag");
            $t->same('InlineMath', $mathNodes[0]->attr('mathTypeConstructor'), "{$source} inline math constructor");
            $t->same(['t' => 'InlineMath'], $mathNodes[0]->attr('mathTypeNative'), "{$source} inline math native payload");
            $t->same(true, $mathNodes[1]->attr('display'), "{$source} display math flag");
            $t->same('DisplayMath', $mathNodes[1]->attr('mathTypeConstructor'), "{$source} display math constructor");
            $t->same(['t' => 'DisplayMath'], $mathNodes[1]->attr('mathTypeNative'), "{$source} display math native payload");
            $t->same(['right', 'default'], $table->attr('alignments'), "{$source} table alignments");
            $t->same([0.4, null], $table->attr('widths'), "{$source} table widths");
            $t->same(['AlignRight', 'AlignDefault'], $table->attr('alignmentConstructors'), "{$source} table alignment constructors");
            $t->same(['ColWidth', 'ColWidthDefault'], $table->attr('columnWidthConstructors'), "{$source} table width constructors");
            $t->same(2, $body->attr('rowHeadColumns'), "{$source} row head columns");
            $t->same('RowHeadColumns', $body->attr('rowHeadColumnsConstructor'), "{$source} row head columns constructor");
            $t->same('center', $cell->attr('align'), "{$source} cell alignment");
            $t->same('AlignCenter', $cell->attr('alignmentConstructor'), "{$source} cell alignment constructor");
            $t->same(3, $cell->attr('rowspan'), "{$source} cell row span");
            $t->same('RowSpan', $cell->attr('rowSpanConstructor'), "{$source} cell row span constructor");
            $t->same(2, $cell->attr('colspan'), "{$source} cell column span");
            $t->same('ColSpan', $cell->attr('colSpanConstructor'), "{$source} cell column span constructor");
        }
    },
    'records pandoc table helper native payloads on json and native ast nodes' => static function (TestRunner $t): void {
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['helper-payload-table', [], []],
                ['t' => 'Caption', 'c' => [null, []]],
                [
                    [['t' => 'AlignRight'], ['t' => 'ColWidth', 'c' => 0.4]],
                    [['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']],
                ],
                ['t' => 'TableHead', 'c' => [
                    ['', [], []],
                    [
                        ['t' => 'Row', 'c' => [
                            ['', [], []],
                            [
                                ['t' => 'Cell', 'c' => [
                                    ['', [], []],
                                    ['t' => 'AlignCenter'],
                                    ['t' => 'RowSpan', 'c' => 2],
                                    ['t' => 'ColSpan', 'c' => 2],
                                    [],
                                ]],
                            ],
                        ]],
                    ],
                ]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => 1],
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignLeft'],
                                        1,
                                        ['t' => 'ColSpan', 'c' => 2],
                                        [],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $head = $table->children[0];
            $body = $table->children[1];
            $headCell = $head->children[0]->children[0];
            $bodyCell = $body->children[0]->children[0];

            $t->same([['t' => 'AlignRight'], ['t' => 'AlignDefault']], $table->attr('alignmentNatives'), "{$source} table alignment native payloads");
            $t->same([['t' => 'ColWidth', 'c' => 0.4], ['t' => 'ColWidthDefault']], $table->attr('columnWidthNatives'), "{$source} table width native payloads");
            $t->same(['t' => 'RowHeadColumns', 'c' => 1], $body->attr('rowHeadColumnsNative'), "{$source} row-head native payload");
            $t->same(['t' => 'AlignCenter'], $headCell->attr('alignmentNative'), "{$source} head cell alignment native payload");
            $t->same(['t' => 'RowSpan', 'c' => 2], $headCell->attr('rowSpanNative'), "{$source} head cell rowspan native payload");
            $t->same(['t' => 'ColSpan', 'c' => 2], $headCell->attr('colSpanNative'), "{$source} head cell colspan native payload");
            $t->same(['t' => 'AlignLeft'], $bodyCell->attr('alignmentNative'), "{$source} body cell alignment native payload");
            $t->same(1, $bodyCell->attr('rowSpanNative'), "{$source} body cell legacy rowspan payload");
            $t->same(['t' => 'ColSpan', 'c' => 2], $bodyCell->attr('colSpanNative'), "{$source} body cell colspan native payload");
        }

        $nativeFromJson = json_decode((new NativeWriter())->write($documents['json']), true, 512, JSON_THROW_ON_ERROR);
        $nativeFromNative = json_decode((new NativeWriter())->write($documents['native']), true, 512, JSON_THROW_ON_ERROR);

        $t->same($tableBlock, $nativeFromJson['blocks'][0]);
        $t->same($tableBlock, $nativeFromNative['blocks'][0]);
    },
    'preserves table column spec sidecars through safe json and native table rebuilds' => static function (TestRunner $t): void {
        $leftAlignment = ['t' => 'AlignLeft', 'c' => [], 'reviewQueue' => 'left-align-source'];
        $leftWidth = ['t' => 'ColWidth', 'c' => [0.33], 'reviewQueue' => 'left-width-source'];
        $defaultAlignment = ['t' => 'AlignDefault', 'c' => [], 'reviewQueue' => 'default-align-source'];
        $defaultWidth = ['t' => 'ColWidthDefault', 'c' => [], 'reviewQueue' => 'default-width-source'];
        $rightAlignment = ['t' => 'AlignRight', 'c' => [], 'reviewQueue' => 'right-align-source'];
        $rightWidth = ['t' => 'ColWidth', 'c' => 0.67, 'reviewQueue' => 'right-width-source'];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['colspec-sidecar-table', ['review-table'], [['data-source', 'json-native']]],
                ['t' => 'Caption', 'c' => [null, []]],
                [
                    [$leftAlignment, $leftWidth],
                    [$defaultAlignment, $defaultWidth],
                    [$rightAlignment, $rightWidth],
                ],
                ['t' => 'TableHead', 'c' => [
                    ['', [], []],
                    [
                        ['t' => 'Row', 'c' => [
                            ['', [], []],
                            [
                                ['t' => 'Cell', 'c' => [
                                    ['', [], []],
                                    ['t' => 'AlignDefault'],
                                    ['t' => 'RowSpan', 'c' => 1],
                                    ['t' => 'ColSpan', 'c' => 1],
                                    [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Left']]]],
                                ]],
                                ['t' => 'Cell', 'c' => [
                                    ['', [], []],
                                    ['t' => 'AlignDefault'],
                                    ['t' => 'RowSpan', 'c' => 1],
                                    ['t' => 'ColSpan', 'c' => 1],
                                    [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Default']]]],
                                ]],
                                ['t' => 'Cell', 'c' => [
                                    ['', [], []],
                                    ['t' => 'AlignDefault'],
                                    ['t' => 'RowSpan', 'c' => 1],
                                    ['t' => 'ColSpan', 'c' => 1],
                                    [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Right']]]],
                                ]],
                            ],
                        ]],
                    ],
                ]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => 0],
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'A']]]],
                                    ]],
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'B']]]],
                                    ]],
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'C']]]],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
            'reviewQueue' => 'table-wrapper-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];

            $t->same([$leftAlignment, $defaultAlignment, $rightAlignment], $table->attr('alignmentNatives'), "{$source} records alignment sidecars");
            $t->same([$leftWidth, $defaultWidth, $rightWidth], $table->attr('columnWidthNatives'), "{$source} records width sidecars");

            $rebuiltTable = new AstNode('table', array_replace($table->attrs, [
                'id' => 'rebuilt-colspec-sidecar-table',
            ]), $table->children);
            $editedWidthTable = new AstNode('table', array_replace($table->attrs, [
                'widths' => [0.33, null, 0.5],
            ]), $table->children);

            foreach ([
                "{$source} json rebuild" => (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [$rebuiltTable])),
                "{$source} native rebuild" => json_decode((new NativeWriter())->write(new AstNode('document', $document->attrs, [$rebuiltTable])), true, 512, JSON_THROW_ON_ERROR),
            ] as $label => $encoded) {
                $colSpecs = $encoded['blocks'][0]['c'][2];

                $t->same('rebuilt-colspec-sidecar-table', $encoded['blocks'][0]['c'][0][0], "{$label} rebuilds table attrs");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$label} drops stale table wrapper sidecar");
                $t->same($leftAlignment, $colSpecs[0][0], "{$label} preserves left alignment sidecar");
                $t->same($leftWidth, $colSpecs[0][1], "{$label} preserves left width sidecar");
                $t->same($defaultAlignment, $colSpecs[1][0], "{$label} preserves default alignment sidecar");
                $t->same($defaultWidth, $colSpecs[1][1], "{$label} preserves default width sidecar");
                $t->same($rightAlignment, $colSpecs[2][0], "{$label} preserves right alignment sidecar");
                $t->same($rightWidth, $colSpecs[2][1], "{$label} preserves right width sidecar");
            }

            foreach ([
                "{$source} json width edit" => (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [$editedWidthTable])),
                "{$source} native width edit" => json_decode((new NativeWriter())->write(new AstNode('document', $document->attrs, [$editedWidthTable])), true, 512, JSON_THROW_ON_ERROR),
            ] as $label => $encoded) {
                $colSpecs = $encoded['blocks'][0]['c'][2];

                $t->same($leftWidth, $colSpecs[0][1], "{$label} preserves unchanged numeric width sidecar");
                $t->same($defaultWidth, $colSpecs[1][1], "{$label} preserves unchanged default width sidecar");
                $t->same($rightAlignment, $colSpecs[2][0], "{$label} preserves unchanged edited-column alignment sidecar");
                $t->same(['t' => 'ColWidth', 'c' => 0.5], $colSpecs[2][1], "{$label} regenerates edited width payload");
                $t->same(false, array_key_exists('reviewQueue', $colSpecs[2][1]), "{$label} drops stale edited width sidecar");
            }
        }
    },
    'accepts single wrapped table column spec tuple payloads through json and native readers' => static function (TestRunner $t): void {
        $leftAlignment = ['t' => 'AlignLeft', 'reviewQueue' => 'single-colspec-align-source'];
        $leftWidth = ['t' => 'ColWidth', 'c' => [0.25], 'reviewQueue' => 'single-colspec-width-source'];
        $defaultAlignment = ['t' => 'AlignDefault', 'reviewQueue' => 'direct-colspec-align-source'];
        $defaultWidth = ['t' => 'ColWidthDefault', 'reviewQueue' => 'direct-colspec-width-source'];
        $wrappedSpec = [[$leftAlignment, $leftWidth]];
        $directSpec = [$defaultAlignment, $defaultWidth];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['single-colspec-table', ['json-native'], [['data-source', 'column-spec']]],
                ['t' => 'Caption', 'c' => [['t' => 'Nothing'], []]],
                [$wrappedSpec, $directSpec],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
            'reviewQueue' => 'single-colspec-table-source',
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
            $attrs = $table->attrs;
            unset($attrs['constructor'], $attrs['native']);
            $rebuilt = new AstNode('table', array_replace($attrs, [
                'id' => 'rebuilt-single-colspec-table',
            ]), $table->children);
            $edited = new AstNode('table', array_replace($attrs, [
                'widths' => [0.5, null],
            ]), $table->children);

            $t->same(['left', 'default'], $table->attr('alignments'), "{$source} column spec alignments");
            $t->same([0.25, null], $table->attr('widths'), "{$source} column spec widths");
            $t->same([$wrappedSpec, $directSpec], $table->attr('columnSpecNatives'), "{$source} records original column spec tuple payloads");
            $t->same([$leftAlignment, $defaultAlignment], $table->attr('alignmentNatives'), "{$source} records unwrapped alignment sidecars");
            $t->same([$leftWidth, $defaultWidth], $table->attr('columnWidthNatives'), "{$source} records unwrapped width sidecars");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [$rebuilt])),
                'native' => json_decode((new NativeWriter())->write(new AstNode('document', $document->attrs, [$rebuilt])), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $colSpecs = $encoded['blocks'][0]['c'][2];

                $t->same('rebuilt-single-colspec-table', $encoded['blocks'][0]['c'][0][0], "{$source} {$writer} rebuilds table attrs");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} {$writer} drops stale table wrapper sidecar");
                $t->same($wrappedSpec, $colSpecs[0], "{$source} {$writer} preserves single wrapped column spec");
                $t->same($directSpec, $colSpecs[1], "{$source} {$writer} preserves direct column spec");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [$edited])),
                'native' => json_decode((new NativeWriter())->write(new AstNode('document', $document->attrs, [$edited])), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $editedSpec = $encoded['blocks'][0]['c'][2][0];

                $t->same($leftAlignment, $editedSpec[0], "{$source} {$writer} preserves edited-column alignment sidecar");
                $t->same(['t' => 'ColWidth', 'c' => 0.5], $editedSpec[1], "{$source} {$writer} regenerates edited column width");
                $t->same(false, array_key_exists('reviewQueue', $editedSpec[1]), "{$source} {$writer} drops stale edited width sidecar");
            }
        }
    },
    'records pandoc attr tuple provenance on json and native ast nodes' => static function (TestRunner $t): void {
        $headerAttr = ['heading-id', ['level'], [['data-source', 'json-native']]];
        $codeAttr = ['code-id', ['php'], [['data-token', 'code']]];
        $linkAttr = ['link-id', ['source-link'], [['data-link', 'source']]];
        $imageAttr = ['image-id', ['asset'], [['data-media', 'diagram']]];
        $spanAttr = ['span-id', ['review-span'], [['data-span', 'meta']]];
        $divAttr = ['div-id', ['wrapper'], [['data-div', 'true']]];
        $tableAttr = ['table-id', ['review-table'], [['data-table', 'summary']]];
        $bodyAttr = ['body-id', ['tbody'], [['data-body', 'review']]];
        $rowAttr = ['row-id', ['source-row'], [['data-row', '1']]];
        $cellAttr = ['cell-id', ['metric-cell'], [['data-cell', 'metric']]];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Header', 'c' => [
                    2,
                    $headerAttr,
                    [['t' => 'Str', 'c' => 'Heading']],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Code', 'c' => [$codeAttr, 'echo 1;']],
                    ['t' => 'Space'],
                    ['t' => 'Link', 'c' => [$linkAttr, [['t' => 'Str', 'c' => 'source']], ['https://example.test/source', 'Source']]],
                    ['t' => 'Space'],
                    ['t' => 'Image', 'c' => [$imageAttr, [['t' => 'Str', 'c' => 'diagram']], ['media/diagram.png', 'Diagram']]],
                    ['t' => 'Space'],
                    ['t' => 'Span', 'c' => [$spanAttr, [['t' => 'Str', 'c' => 'span']]]],
                ]],
                ['t' => 'Div', 'c' => [
                    $divAttr,
                    [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Wrapped']]]],
                ]],
                ['t' => 'Table', 'c' => [
                    $tableAttr,
                    ['t' => 'Caption', 'c' => [null, []]],
                    [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                    ['t' => 'TableHead', 'c' => [['', [], []], []]],
                    [
                        ['t' => 'TableBody', 'c' => [
                            $bodyAttr,
                            ['t' => 'RowHeadColumns', 'c' => 1],
                            [],
                            [
                                ['t' => 'Row', 'c' => [
                                    $rowAttr,
                                    [
                                        ['t' => 'Cell', 'c' => [
                                            $cellAttr,
                                            ['t' => 'AlignRight'],
                                            ['t' => 'RowSpan', 'c' => 1],
                                            ['t' => 'ColSpan', 'c' => 2],
                                            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Metric']]]],
                                        ]],
                                    ],
                                ]],
                            ],
                        ]],
                    ],
                    ['t' => 'TableFoot', 'c' => [['', [], []], []]],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $heading = $document->children[0];
            $paragraph = $document->children[1];
            $div = $document->children[2];
            $table = $document->children[3];
            $body = $table->children[0];
            $row = $body->children[0];
            $cell = $row->children[0];

            $t->same('Attr', $heading->attr('attrConstructor'), "{$source} heading attr constructor");
            $t->same($headerAttr, $heading->attr('attrNative'), "{$source} heading attr native tuple");
            $t->same($codeAttr, $paragraph->children[0]->attr('attrNative'), "{$source} code attr native tuple");
            $t->same($linkAttr, $paragraph->children[2]->attr('attrNative'), "{$source} link attr native tuple");
            $t->same($imageAttr, $paragraph->children[4]->attr('attrNative'), "{$source} image attr native tuple");
            $t->same($spanAttr, $paragraph->children[6]->attr('attrNative'), "{$source} span attr native tuple");
            $t->same($divAttr, $div->attr('attrNative'), "{$source} div attr native tuple");
            $t->same($tableAttr, $table->attr('attrNative'), "{$source} table attr native tuple");
            $t->same($bodyAttr, $body->attr('attrNative'), "{$source} table body attr native tuple");
            $t->same($rowAttr, $row->attr('attrNative'), "{$source} table row attr native tuple");
            $t->same($cellAttr, $cell->attr('attrNative'), "{$source} table cell attr native tuple");
            $t->same(['data-cell' => 'metric'], $cell->attr('attributes'), "{$source} table cell attributes");
        }

        $jsonPacket = (new PandocJsonWriter())->toArray($documents['json']);
        $nativePacket = json_decode((new NativeWriter())->write($documents['native']), true, 512, JSON_THROW_ON_ERROR);
        $jsonTableBody = $jsonPacket['blocks'][3]['c'][4][0];
        $jsonTableBodyPayload = $jsonTableBody['c'] ?? $jsonTableBody;
        $jsonTableRow = $jsonTableBodyPayload[3][0]['c'] ?? $jsonTableBodyPayload[3][0];
        $jsonTableCell = $jsonTableRow[1][0]['c'] ?? $jsonTableRow[1][0];
        $nativeTableBody = $nativePacket['blocks'][3]['c'][4][0]['c'] ?? $nativePacket['blocks'][3]['c'][4][0];
        $nativeTableRow = $nativeTableBody[3][0]['c'];
        $nativeTableCell = $nativeTableRow[1][0]['c'];

        $t->same($jsonPacket['blocks'][0], $nativePacket['blocks'][0]);
        $t->same($jsonPacket['blocks'][1], $nativePacket['blocks'][1]);
        $t->same($jsonPacket['blocks'][2], $nativePacket['blocks'][2]);
        $t->same($headerAttr, $jsonPacket['blocks'][0]['c'][1]);
        $t->same($codeAttr, $jsonPacket['blocks'][1]['c'][0]['c'][0]);
        $t->same($linkAttr, $jsonPacket['blocks'][1]['c'][2]['c'][0]);
        $t->same($imageAttr, $jsonPacket['blocks'][1]['c'][4]['c'][0]);
        $t->same($spanAttr, $jsonPacket['blocks'][1]['c'][6]['c'][0]);
        $t->same($divAttr, $jsonPacket['blocks'][2]['c'][0]);
        $t->same($tableAttr, $jsonPacket['blocks'][3]['c'][0]);
        $t->same($bodyAttr, $jsonTableBodyPayload[0]);
        $t->same($rowAttr, $jsonTableRow[0]);
        $t->same($cellAttr, $jsonTableCell[0]);
        $t->same('TableBody', $nativePacket['blocks'][3]['c'][4][0]['t']);
        $t->same($tableAttr, $nativePacket['blocks'][3]['c'][0]);
        $t->same($bodyAttr, $nativeTableBody[0]);
        $t->same($rowAttr, $nativeTableRow[0]);
        $t->same($cellAttr, $nativeTableCell[0]);
    },
    'preserves single wrapped tagged attr constructor content across json and native ast' => static function (TestRunner $t): void {
        $headerTuple = ['wrapped-heading', ['review'], [['data-source', 'wrapped']]];
        $codeTuple = ['wrapped-code', ['php'], [['data-code', 'source']]];
        $spanTuple = ['wrapped-span', ['inline'], [['data-span', 'source']]];
        $headerAttr = ['t' => 'Attr', 'c' => [$headerTuple], 'reviewQueue' => 'header-attr-source'];
        $codeAttr = ['t' => 'Attr', 'c' => [$codeTuple], 'reviewQueue' => 'code-attr-source'];
        $spanAttr = ['t' => 'Attr', 'c' => [$spanTuple], 'reviewQueue' => 'span-attr-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Header', 'c' => [
                    2,
                    $headerAttr,
                    [['t' => 'Str', 'c' => 'Wrapped']],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Code', 'c' => [$codeAttr, 'echo 1;']],
                    ['t' => 'Space'],
                    ['t' => 'Span', 'c' => [
                        $spanAttr,
                        [
                            ['t' => 'Str', 'c' => 'wrapped'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'span'],
                        ],
                    ]],
                ]],
            ],
        ];
        $withoutNative = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $heading = $document->children[0];
            $paragraph = $document->children[1];
            $code = $paragraph->children[0];
            $span = $paragraph->children[2];

            $t->same('wrapped-heading', $heading->attr('id'), "{$source} heading attr id");
            $t->same($headerAttr, $heading->attr('attrNative'), "{$source} heading keeps wrapped attr constructor");
            $t->same(['php'], $code->attr('classes'), "{$source} code attr classes");
            $t->same($codeAttr, $code->attr('attrNative'), "{$source} code keeps wrapped attr constructor");
            $t->same(['data-span' => 'source'], $span->attr('attributes'), "{$source} span attr key-values");
            $t->same($spanAttr, $span->attr('attrNative'), "{$source} span keeps wrapped attr constructor");

            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('heading', $withoutNative($heading), $heading->children),
                new AstNode('paragraph', [], [
                    new AstNode('code', $withoutNative($code)),
                    new AstNode('space'),
                    new AstNode('span', $withoutNative($span), $span->children),
                ]),
            ]);

            foreach ([
                "{$source} json" => (new PandocJsonWriter())->toArray($rebuilt),
                "{$source} native" => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($headerAttr, $encoded['blocks'][0]['c'][1], "{$writer} writer preserves wrapped heading attr constructor");
                $t->same($codeAttr, $encoded['blocks'][1]['c'][0]['c'][0], "{$writer} writer preserves wrapped code attr constructor");
                $t->same($spanAttr, $encoded['blocks'][1]['c'][2]['c'][0], "{$writer} writer preserves wrapped span attr constructor");
            }

            $editedHeading = new AstNode('heading', array_replace($withoutNative($heading), [
                'id' => 'edited-heading',
            ]), $heading->children);

            foreach ([
                "{$source} edited json" => (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [$editedHeading])),
                "{$source} edited native" => json_decode((new NativeWriter())->write(new AstNode('document', $document->attrs, [$editedHeading])), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $editedAttr = $encoded['blocks'][0]['c'][1];

                $t->same(['edited-heading', ['review'], [['data-source', 'wrapped']]], $editedAttr, "{$writer} regenerates edited wrapped attr as canonical tuple");
                $t->same(false, array_key_exists('t', $editedAttr), "{$writer} drops stale wrapped attr constructor");
            }
        }
    },
    'preserves compatible pandoc attr native tuples through json and native writers' => static function (TestRunner $t): void {
        $headerAttr = ['dup-heading', ['review', 'source'], [
            ['data-key', 'first'],
            ['data-key', 'second'],
            ['aria-label', 'Heading'],
        ]];
        $codeAttr = ['dup-code', ['php'], [
            ['data-code', 'before'],
            ['data-code', 'after'],
        ]];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Header', 'c' => [
                    2,
                    $headerAttr,
                    [['t' => 'Str', 'c' => 'Heading']],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Code', 'c' => [$codeAttr, 'echo 1;']],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $heading = $document->children[0];
            $code = $document->children[1]->children[0];

            $t->same($headerAttr, $heading->attr('attrNative'), "{$source} duplicate heading attr native tuple");
            $t->same(['data-key' => 'second', 'aria-label' => 'Heading'], $heading->attr('attributes'), "{$source} duplicate heading attrs normalize with last key");
            $t->same($codeAttr, $code->attr('attrNative'), "{$source} duplicate code attr native tuple");
            $t->same(['data-code' => 'after'], $code->attr('attributes'), "{$source} duplicate code attrs normalize with last key");
        }

        $manualDocument = new AstNode('document', ['pandocApiVersion' => [1, 23, 1]], [
            new AstNode('heading', [
                'level' => 2,
                'id' => 'dup-heading',
                'classes' => ['review', 'source'],
                'attributes' => ['data-key' => 'second', 'aria-label' => 'Heading'],
                'attrNative' => $headerAttr,
            ], [
                new AstNode('text', ['text' => 'Heading']),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('code', [
                    'id' => 'dup-code',
                    'classes' => ['php'],
                    'attributes' => ['data-code' => 'after'],
                    'attrNative' => $codeAttr,
                    'text' => 'echo 1;',
                ]),
            ]),
        ]);

        $jsonPacket = (new PandocJsonWriter())->toArray($manualDocument);
        $nativePacket = json_decode((new NativeWriter())->write($manualDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same($headerAttr, $jsonPacket['blocks'][0]['c'][1]);
        $t->same($codeAttr, $jsonPacket['blocks'][1]['c'][0]['c'][0]);
        $t->same($headerAttr, $nativePacket['blocks'][0]['c'][1]);
        $t->same($codeAttr, $nativePacket['blocks'][1]['c'][0]['c'][0]);

        $editedDocument = new AstNode('document', ['pandocApiVersion' => [1, 23, 1]], [
            new AstNode('heading', [
                'level' => 2,
                'id' => 'dup-heading',
                'classes' => ['review', 'source'],
                'attributes' => ['data-key' => 'edited', 'aria-label' => 'Heading'],
                'attrNative' => $headerAttr,
            ], [
                new AstNode('text', ['text' => 'Heading']),
            ]),
        ]);
        $editedJson = (new PandocJsonWriter())->toArray($editedDocument);
        $editedNative = json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR);
        $regeneratedAttr = ['dup-heading', ['review', 'source'], [
            ['data-key', 'edited'],
            ['aria-label', 'Heading'],
        ]];

        $t->same($regeneratedAttr, $editedJson['blocks'][0]['c'][1]);
        $t->same($regeneratedAttr, $editedNative['blocks'][0]['c'][1]);
    },
    'preserves untagged attr tuple sidecars through json and native writers' => static function (TestRunner $t): void {
        $headingAttr = [
            'sidecar-heading',
            ['review'],
            [['data-source', 'json-native']],
            'heading-attr-sidecar',
        ];
        $linkAttr = [
            'sidecar-link',
            ['source-link'],
            [['data-link', 'source']],
            ['sourceOrdinal' => 17],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Header', 'c' => [
                    2,
                    $headingAttr,
                    [['t' => 'Str', 'c' => 'Heading']],
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
        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $heading = $document->children[0];
            $link = $document->children[1]->children[0];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('heading', $withoutWrapperNative($heading), $heading->children),
                new AstNode('paragraph', [], [
                    new AstNode('link', $withoutWrapperNative($link), $link->children),
                ]),
            ]);

            $t->same($headingAttr, $heading->attr('attrNative'), "{$source} heading keeps full attr tuple sidecar");
            $t->same($linkAttr, $link->attr('attrNative'), "{$source} link keeps full attr tuple sidecar");
            $t->same('sidecar-heading', $heading->attr('id'), "{$source} heading id");
            $t->same(['review'], $heading->attr('classes'), "{$source} heading classes");
            $t->same(['data-link' => 'source'], $link->attr('attributes'), "{$source} link attributes");

            foreach ([
                "{$source} json" => (new PandocJsonWriter())->toArray($rebuilt),
                "{$source} native" => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($headingAttr, $encoded['blocks'][0]['c'][1], "{$writer} preserves heading attr sidecar");
                $t->same($linkAttr, $encoded['blocks'][1]['c'][0]['c'][0], "{$writer} preserves link attr sidecar");
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
    'records pandoc target tuple provenance on json and native ast nodes' => static function (TestRunner $t): void {
        $linkAttr = ['source-link', ['review-link'], [['data-origin', 'json']]];
        $imageAttr = ['cover-image', ['review-image'], [['data-origin', 'asset']]];
        $linkTarget = ['https://example.test/source?x=1#review', 'Source title'];
        $imageTarget = ['media/cover.png', 'Cover title'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Link', 'c' => [
                        $linkAttr,
                        [['t' => 'Str', 'c' => 'source']],
                        $linkTarget,
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Image', 'c' => [
                        $imageAttr,
                        [
                            ['t' => 'Str', 'c' => 'Cover'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'image'],
                        ],
                        $imageTarget,
                    ]],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $paragraph = $document->children[0];
            $link = $paragraph->children[0];
            $image = $paragraph->children[2];

            $t->same('link', $link->type, "{$source} link type");
            $t->same('Link', $link->attr('constructor'), "{$source} link constructor");
            $t->same($packet['blocks'][0]['c'][0], $link->attr('native'), "{$source} link native payload");
            $t->same($linkTarget, $link->attr('targetNative'), "{$source} link target tuple");
            $t->same($linkTarget[0], $link->attr('url'), "{$source} link url");
            $t->same($linkTarget[1], $link->attr('title'), "{$source} link title");
            $t->same('image', $image->type, "{$source} image type");
            $t->same('Image', $image->attr('constructor'), "{$source} image constructor");
            $t->same($packet['blocks'][0]['c'][2], $image->attr('native'), "{$source} image native payload");
            $t->same($imageTarget, $image->attr('targetNative'), "{$source} image target tuple");
            $t->same($imageTarget[0], $image->attr('url'), "{$source} image url");
            $t->same($imageTarget[1], $image->attr('title'), "{$source} image title");
            $t->same('Cover image', $image->attr('alt'), "{$source} image alt");
        }

        $jsonPacket = (new PandocJsonWriter())->toArray($documents['json']);
        $nativePacket = json_decode((new NativeWriter())->write($documents['native']), true, 512, JSON_THROW_ON_ERROR);

        $t->same($linkTarget, $jsonPacket['blocks'][0]['c'][0]['c'][2]);
        $t->same($imageTarget, $jsonPacket['blocks'][0]['c'][2]['c'][2]);
        $t->same($linkTarget, $nativePacket['blocks'][0]['c'][0]['c'][2]);
        $t->same($imageTarget, $nativePacket['blocks'][0]['c'][2]['c'][2]);
    },
    'preserves target tuple sidecars when rebuilding link and image constructors' => static function (TestRunner $t): void {
        $linkTarget = [
            'https://example.test/source?x=1#review',
            'Source title',
            ['reviewQueue' => 'link-target', 'sourceOrdinal' => 11],
        ];
        $imageTarget = [
            'media/cover.png',
            'Cover title',
            ['reviewQueue' => 'image-target', 'sourceOrdinal' => 12],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Link', 'c' => [
                        ['source-link', ['review-link'], [['data-origin', 'json']]],
                        [['t' => 'Str', 'c' => 'source']],
                        $linkTarget,
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Image', 'c' => [
                        ['cover-image', ['review-image'], [['data-origin', 'asset']]],
                        [['t' => 'Str', 'c' => 'Cover']],
                        $imageTarget,
                    ]],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $link = $document->children[0]->children[0];
            $image = $document->children[0]->children[2];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('link', array_replace($link->attrs, ['id' => 'edited-link']), $link->children),
                    new AstNode('space'),
                    new AstNode('image', array_replace($image->attrs, ['classes' => ['edited-image']]), $image->children),
                ]),
            ]);

            $t->same($linkTarget, $link->attr('targetNative'), "{$source} link preserves target tuple sidecar on read");
            $t->same($imageTarget, $image->attr('targetNative'), "{$source} image preserves target tuple sidecar on read");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedLink = $encoded['blocks'][0]['c'][0];
                $encodedImage = $encoded['blocks'][0]['c'][2];

                $t->same('edited-link', $encodedLink['c'][0][0], "{$source} {$writer} writer regenerates edited link attr");
                $t->same(['edited-image'], $encodedImage['c'][0][1], "{$source} {$writer} writer regenerates edited image attr");
                $t->same($linkTarget, $encodedLink['c'][2], "{$source} {$writer} writer preserves link target tuple sidecar");
                $t->same($imageTarget, $encodedImage['c'][2], "{$source} {$writer} writer preserves image target tuple sidecar");
            }

            $editedTarget = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('link', array_replace($link->attrs, ['url' => 'https://example.test/edited']), $link->children),
                    new AstNode('space'),
                    new AstNode('image', array_replace($image->attrs, ['title' => 'Edited title']), $image->children),
                ]),
            ]);
            $editedJson = (new PandocJsonWriter())->toArray($editedTarget);

            $t->same(['https://example.test/edited', 'Source title'], $editedJson['blocks'][0]['c'][0]['c'][2], "{$source} edited link target drops stale sidecar");
            $t->same(['media/cover.png', 'Edited title'], $editedJson['blocks'][0]['c'][2]['c'][2], "{$source} edited image target drops stale sidecar");
        }
    },
    'accepts single wrapped target tuple sidecars for link and image constructors' => static function (TestRunner $t): void {
        $linkTarget = [
            'https://example.test/source?x=1#review',
            'Source title',
            ['reviewQueue' => 'wrapped-link-target', 'sourceOrdinal' => 21],
        ];
        $imageTarget = [
            'media/cover.png',
            'Cover title',
            ['reviewQueue' => 'wrapped-image-target', 'sourceOrdinal' => 22],
        ];
        $wrappedLinkTarget = [$linkTarget];
        $wrappedImageTarget = [$imageTarget];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Link', 'c' => [
                        ['source-link', ['review-link'], [['data-origin', 'json']]],
                        [['t' => 'Str', 'c' => 'source']],
                        $wrappedLinkTarget,
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Image', 'c' => [
                        ['cover-image', ['review-image'], [['data-origin', 'asset']]],
                        [
                            ['t' => 'Str', 'c' => 'Cover'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'image'],
                        ],
                        $wrappedImageTarget,
                    ]],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $link = $document->children[0]->children[0];
            $image = $document->children[0]->children[2];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('link', array_replace($link->attrs, ['id' => 'edited-link']), $link->children),
                    new AstNode('space'),
                    new AstNode('image', array_replace($image->attrs, ['classes' => ['edited-image']]), $image->children),
                ]),
            ]);

            $t->same($wrappedLinkTarget, $link->attr('targetNative'), "{$source} link preserves wrapped target tuple sidecar on read");
            $t->same($linkTarget[0], $link->attr('url'), "{$source} link unwraps target url");
            $t->same($linkTarget[1], $link->attr('title'), "{$source} link unwraps target title");
            $t->same($wrappedImageTarget, $image->attr('targetNative'), "{$source} image preserves wrapped target tuple sidecar on read");
            $t->same($imageTarget[0], $image->attr('url'), "{$source} image unwraps target url");
            $t->same($imageTarget[1], $image->attr('title'), "{$source} image unwraps target title");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedLink = $encoded['blocks'][0]['c'][0];
                $encodedImage = $encoded['blocks'][0]['c'][2];

                $t->same($wrappedLinkTarget, $encodedLink['c'][2], "{$source} {$writer} writer preserves wrapped link target tuple sidecar");
                $t->same($wrappedImageTarget, $encodedImage['c'][2], "{$source} {$writer} writer preserves wrapped image target tuple sidecar");

                $editedTarget = new AstNode('document', $document->attrs, [
                    new AstNode('paragraph', [], [
                        new AstNode('link', array_replace($link->attrs, ['url' => 'https://example.test/edited']), $link->children),
                        new AstNode('space'),
                        new AstNode('image', array_replace($image->attrs, ['title' => 'Edited title']), $image->children),
                    ]),
                ]);
                $edited = $writer === 'json'
                    ? (new PandocJsonWriter())->toArray($editedTarget)
                    : json_decode((new NativeWriter())->write($editedTarget), true, 512, JSON_THROW_ON_ERROR);

                $t->same(['https://example.test/edited', 'Source title'], $edited['blocks'][0]['c'][0]['c'][2], "{$source} {$writer} edited link target drops wrapped sidecar");
                $t->same(['media/cover.png', 'Edited title'], $edited['blocks'][0]['c'][2]['c'][2], "{$source} {$writer} edited image target drops wrapped sidecar");
            }
        }
    },
    'accepts tagged target tuple constructors for link and image payloads' => static function (TestRunner $t): void {
        $linkTarget = [
            'https://example.test/tagged?x=1#review',
            'Tagged source',
            ['reviewQueue' => 'tagged-link-target', 'sourceOrdinal' => 31],
        ];
        $imageTarget = [
            'media/tagged-cover.png',
            'Tagged cover',
            ['reviewQueue' => 'tagged-image-target', 'sourceOrdinal' => 32],
        ];
        $linkTargetConstructor = [
            't' => 'Target',
            'c' => $linkTarget,
            'reviewQueue' => 'target-link-constructor',
        ];
        $imageTargetConstructor = [
            't' => 'Target',
            'c' => [$imageTarget],
            'reviewQueue' => 'target-image-constructor',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Link', 'c' => [
                        ['target-link', ['review-link'], [['data-origin', 'target']]],
                        [['t' => 'Str', 'c' => 'target']],
                        $linkTargetConstructor,
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Image', 'c' => [
                        ['target-image', ['review-image'], [['data-origin', 'asset']]],
                        [
                            ['t' => 'Str', 'c' => 'Tagged'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'cover'],
                        ],
                        $imageTargetConstructor,
                    ]],
                ]],
            ],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $link = $document->children[0]->children[0];
            $image = $document->children[0]->children[2];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('link', array_replace($link->attrs, ['classes' => ['rebuilt-link']]), $link->children),
                    new AstNode('space'),
                    new AstNode('image', array_replace($image->attrs, ['id' => 'rebuilt-image']), $image->children),
                ]),
            ]);

            $t->same('Target', $link->attr('targetConstructor'), "{$source} link records target constructor");
            $t->same($linkTargetConstructor, $link->attr('targetNative'), "{$source} link keeps target constructor payload");
            $t->same($linkTarget[0], $link->attr('url'), "{$source} link target url");
            $t->same($linkTarget[1], $link->attr('title'), "{$source} link target title");
            $t->same('Target', $image->attr('targetConstructor'), "{$source} image records target constructor");
            $t->same($imageTargetConstructor, $image->attr('targetNative'), "{$source} image keeps single-wrapped target constructor payload");
            $t->same($imageTarget[0], $image->attr('url'), "{$source} image target url");
            $t->same($imageTarget[1], $image->attr('title'), "{$source} image target title");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($packet['blocks'], $encoded['blocks'], "{$source} {$writer} writer preserves unchanged target constructors");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedLink = $encoded['blocks'][0]['c'][0];
                $encodedImage = $encoded['blocks'][0]['c'][2];

                $t->same(['rebuilt-link'], $encodedLink['c'][0][1], "{$source} {$writer} writer regenerates edited link attrs");
                $t->same('rebuilt-image', $encodedImage['c'][0][0], "{$source} {$writer} writer regenerates edited image attrs");
                $t->same($linkTargetConstructor, $encodedLink['c'][2], "{$source} {$writer} writer preserves tagged link target");
                $t->same($imageTargetConstructor, $encodedImage['c'][2], "{$source} {$writer} writer preserves tagged image target");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [
                    new AstNode('paragraph', [], [
                        new AstNode('link', array_replace($link->attrs, ['url' => 'https://example.test/edited']), $link->children),
                        new AstNode('space'),
                        new AstNode('image', array_replace($image->attrs, ['title' => 'Edited tagged cover']), $image->children),
                    ]),
                ])),
                'native' => json_decode((new NativeWriter())->write(new AstNode('document', $document->attrs, [
                    new AstNode('paragraph', [], [
                        new AstNode('link', array_replace($link->attrs, ['url' => 'https://example.test/edited']), $link->children),
                        new AstNode('space'),
                        new AstNode('image', array_replace($image->attrs, ['title' => 'Edited tagged cover']), $image->children),
                    ]),
                ])), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same(['https://example.test/edited', 'Tagged source'], $encoded['blocks'][0]['c'][0]['c'][2], "{$source} {$writer} edited link target drops tagged sidecar");
                $t->same(['media/tagged-cover.png', 'Edited tagged cover'], $encoded['blocks'][0]['c'][2]['c'][2], "{$source} {$writer} edited image target drops tagged sidecar");
            }
        }
    },
    'records quote and math native enum payloads on json and native ast nodes' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Quoted', 'c' => [
                        ['t' => 'SingleQuote'],
                        [['t' => 'Str', 'c' => 'quoted']],
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Math', 'c' => [
                        ['t' => 'InlineMath'],
                        'x + 1',
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Math', 'c' => [
                        ['t' => 'DisplayMath'],
                        'y = 2',
                    ]],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $paragraph = $document->children[0];
            $quoted = $paragraph->children[0];
            $inlineMath = $paragraph->children[2];
            $displayMath = $paragraph->children[4];

            $t->same('quoted', $quoted->type, "{$source} quoted type");
            $t->same('single', $quoted->attr('kind'), "{$source} quote kind");
            $t->same('SingleQuote', $quoted->attr('quoteTypeConstructor'), "{$source} quote constructor");
            $t->same(['t' => 'SingleQuote'], $quoted->attr('quoteTypeNative'), "{$source} quote native payload");
            $t->same('math', $inlineMath->type, "{$source} inline math type");
            $t->same(false, $inlineMath->attr('display'), "{$source} inline math display flag");
            $t->same('InlineMath', $inlineMath->attr('mathTypeConstructor'), "{$source} inline math constructor");
            $t->same(['t' => 'InlineMath'], $inlineMath->attr('mathTypeNative'), "{$source} inline math native payload");
            $t->same('math', $displayMath->type, "{$source} display math type");
            $t->same(true, $displayMath->attr('display'), "{$source} display math flag");
            $t->same('DisplayMath', $displayMath->attr('mathTypeConstructor'), "{$source} display math constructor");
            $t->same(['t' => 'DisplayMath'], $displayMath->attr('mathTypeNative'), "{$source} display math native payload");
        }
    },
    'preserves tagged raw format helper payloads through json and native writers' => static function (TestRunner $t): void {
        $rawBlockFormat = ['t' => 'Format', 'c' => ['html'], 'reviewQueue' => 'raw-block-format'];
        $rawInlineFormat = ['t' => 'Format', 'c' => 'latex', 'reviewQueue' => 'raw-inline-format'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'RawBlock', 'c' => [
                    $rawBlockFormat,
                    '<section data-review="yes">Source</section>',
                ], 'reviewQueue' => 'raw-block-source'],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Inline'],
                    ['t' => 'Space'],
                    ['t' => 'RawInline', 'c' => [$rawInlineFormat, '\\alpha'], 'reviewQueue' => 'raw-inline-source'],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $rawBlock = $document->children[0];
            $rawInline = null;
            foreach ($document->children[1]->children as $inline) {
                if ($inline->type === 'raw_tex_inline') {
                    $rawInline = $inline;
                    break;
                }
            }
            if (!$rawInline instanceof AstNode) {
                throw new \RuntimeException("{$source} raw inline not found");
            }
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same('raw_html', $rawBlock->type, "{$source} raw block type");
            $t->same('RawBlock', $rawBlock->attr('constructor'), "{$source} raw block constructor");
            $t->same('Format', $rawBlock->attr('formatConstructor'), "{$source} raw block format constructor");
            $t->same($rawBlockFormat, $rawBlock->attr('formatNative'), "{$source} raw block format native payload");
            $t->same('html', $rawBlock->attr('format'), "{$source} raw block format");
            $t->same('<section data-review="yes">Source</section>', $rawBlock->attr('text'), "{$source} raw block text");
            $t->same('raw_tex_inline', $rawInline->type, "{$source} raw inline type");
            $t->same('Format', $rawInline->attr('formatConstructor'), "{$source} raw inline format constructor");
            $t->same($rawInlineFormat, $rawInline->attr('formatNative'), "{$source} raw inline format native payload");
            $t->same('latex', $rawInline->attr('format'), "{$source} raw inline format");
            $t->same('\\alpha', $rawInline->attr('text'), "{$source} raw inline text");
            $t->same($packet['blocks'], $jsonPacket['blocks'], "{$source} json writer preserves raw format payloads");
            $t->same($packet['blocks'], $nativePacket['blocks'], "{$source} native writer preserves raw format payloads");

            $editedDocument = new AstNode('document', $document->attrs, [
                new AstNode('raw_html', array_replace($rawBlock->attrs, [
                    'text' => '<aside>Edited</aside>',
                    'html' => '<aside>Edited</aside>',
                ])),
                new AstNode('paragraph', [], [
                    new AstNode('raw_tex_inline', array_replace($rawInline->attrs, [
                        'text' => '\\beta',
                        'tex' => '\\beta',
                    ])),
                ]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($editedDocument),
                'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $editedPacket) {
                $editedBlock = $editedPacket['blocks'][0];
                $editedInline = $editedPacket['blocks'][1]['c'][0];

                $t->same($rawBlockFormat, $editedBlock['c'][0], "{$source} {$writer} writer preserves edited raw block format helper");
                $t->same('<aside>Edited</aside>', $editedBlock['c'][1], "{$source} {$writer} writer regenerates edited raw block text");
                $t->same(false, array_key_exists('reviewQueue', $editedBlock), "{$source} {$writer} writer drops stale raw block sidecar");
                $t->same($rawInlineFormat, $editedInline['c'][0], "{$source} {$writer} writer preserves edited raw inline format helper");
                $t->same('\\beta', $editedInline['c'][1], "{$source} {$writer} writer regenerates edited raw inline text");
                $t->same(false, array_key_exists('reviewQueue', $editedInline), "{$source} {$writer} writer drops stale raw inline sidecar");
            }
        }

        $rawBlock = $documents['json']->children[0];
        $retaggedDocument = new AstNode('document', [], [
            new AstNode('raw_block', array_replace($rawBlock->attrs, [
                'format' => 'markdown',
                'text' => '**edited**',
            ])),
        ]);
        $retaggedJson = (new PandocJsonWriter())->toArray($retaggedDocument);
        $retaggedNative = json_decode((new NativeWriter())->write($retaggedDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same('markdown', $retaggedJson['blocks'][0]['c'][0]);
        $t->same('**edited**', $retaggedJson['blocks'][0]['c'][1]);
        $t->same('markdown', $retaggedNative['blocks'][0]['c'][0]);
        $t->same('**edited**', $retaggedNative['blocks'][0]['c'][1]);
    },
    'maps html-family raw aliases through json native and wordpress handoff' => static function (TestRunner $t): void {
        $xhtml = '<section data-boundary="xhtml"><p>Alias block</p></section>';
        $html5 = '<span data-boundary="html5">Alias inline</span>';
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'RawBlock', 'c' => ['xhtml', $xhtml]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Before'],
                    ['t' => 'Space'],
                    ['t' => 'RawInline', 'c' => ['html5', $html5]],
                    ['t' => 'Space'],
                    ['t' => 'RawInline', 'c' => ['opml', '<outline text="disabled"/>']],
                    ['t' => 'Str', 'c' => 'after'],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $rawBlock = $document->children[0] ?? new AstNode('missing');
            $paragraph = $document->children[1] ?? new AstNode('missing');
            $rawInline = null;
            $disabledInline = null;
            foreach ($paragraph->children as $inline) {
                if ($inline->type === 'raw_html_inline' && $inline->attr('format') === 'html5') {
                    $rawInline = $inline;
                }
                if ($inline->type === 'raw_inline' && $inline->attr('format') === 'opml') {
                    $disabledInline = $inline;
                }
            }
            if (!$rawInline instanceof AstNode || !$disabledInline instanceof AstNode) {
                throw new \RuntimeException("{$source} raw alias inline coverage not found");
            }
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
            $markdown = (new MarkdownWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('raw_html', $rawBlock->type, "{$source} block alias hydrates as raw html");
            $t->same('xhtml', $rawBlock->attr('format'), "{$source} block alias format is preserved");
            $t->same($xhtml, $rawBlock->attr('html'), "{$source} block alias html is preserved");
            $t->same('raw_html_inline', $rawInline->type, "{$source} inline alias hydrates as raw html");
            $t->same('html5', $rawInline->attr('format'), "{$source} inline alias format is preserved");
            $t->same($html5, $rawInline->attr('html'), "{$source} inline alias html is preserved");
            $t->same('raw_inline', $disabledInline->type, "{$source} unsupported raw inline stays generic");
            $t->same($packet['blocks'], $jsonPacket['blocks'], "{$source} json writer round-trips raw aliases");
            $t->same($packet['blocks'], $nativePacket['blocks'], "{$source} native writer round-trips raw aliases");
            $t->contains($xhtml, $markdown);
            $t->contains($html5, $markdown);
            $t->true(!str_contains($markdown, '<outline'), "{$source} markdown keeps unsupported raw disabled");
            $t->contains('<!-- wp:html -->' . "\n" . $xhtml . "\n" . '<!-- /wp:html -->', $blocks);
            $t->contains('<p>Before ' . $html5 . ' after</p>', $blocks);
            $t->true(!str_contains($blocks, '<outline'), "{$source} wordpress keeps unsupported raw disabled");
        }
    },
    'records ordered list style and delimiter native enum payloads on json and native ast' => static function (TestRunner $t): void {
        $styles = [
            ['DefaultStyle', 'default'],
            ['Decimal', 'decimal'],
            ['Example', 'example'],
            ['LowerRoman', 'lower_roman'],
            ['UpperRoman', 'upper_roman'],
            ['LowerAlpha', 'lower_alpha'],
            ['UpperAlpha', 'upper_alpha'],
        ];
        $delimiters = [
            ['DefaultDelim', 'default'],
            ['Period', 'period'],
            ['OneParen', 'one_paren'],
            ['TwoParens', 'two_parens'],
        ];
        $blocks = [];
        foreach ($styles as $index => [$styleConstructor, $_style]) {
            [$delimiterConstructor] = $delimiters[$index % count($delimiters)];
            $blocks[] = [
                't' => 'OrderedList',
                'c' => [
                    [$index + 1, ['t' => $styleConstructor], ['t' => $delimiterConstructor]],
                    [[]],
                ],
            ];
        }
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => $blocks,
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            foreach ($styles as $index => [$styleConstructor, $style]) {
                [$delimiterConstructor, $delimiter] = $delimiters[$index % count($delimiters)];
                $list = $document->children[$index];

                $t->same('ordered_list', $list->type, "{$source} ordered list type {$index}");
                $t->same('OrderedList', $list->attr('constructor'), "{$source} ordered list constructor {$index}");
                $t->same($blocks[$index], $list->attr('native'), "{$source} ordered list native payload {$index}");
                $t->same($index + 1, $list->attr('start'), "{$source} ordered list start {$index}");
                $t->same($style, $list->attr('style'), "{$source} ordered list style {$index}");
                $t->same($styleConstructor, $list->attr('listStyleConstructor'), "{$source} ordered list style constructor {$index}");
                $t->same(['t' => $styleConstructor], $list->attr('listStyleNative'), "{$source} ordered list style native {$index}");
                $t->same($delimiter, $list->attr('delimiter'), "{$source} ordered list delimiter {$index}");
                $t->same($delimiterConstructor, $list->attr('listDelimiterConstructor'), "{$source} ordered list delimiter constructor {$index}");
                $t->same(['t' => $delimiterConstructor], $list->attr('listDelimiterNative'), "{$source} ordered list delimiter native {$index}");
            }
        }

        $jsonPacket = (new PandocJsonWriter())->toArray($documents['json']);
        $nativePacket = json_decode((new NativeWriter())->write($documents['native']), true, 512, JSON_THROW_ON_ERROR);

        $t->same($blocks, $jsonPacket['blocks']);
        $t->same($blocks, $nativePacket['blocks']);
    },
    'preserves current tagged helper payload shapes through pandoc json writer' => static function (TestRunner $t): void {
        $styleNative = ['t' => 'UpperAlpha', 'c' => []];
        $delimiterNative = ['t' => 'TwoParens', 'c' => []];
        $quoteTypeNative = ['t' => 'DoubleQuote', 'c' => []];
        $mathTypeNative = ['t' => 'DisplayMath', 'c' => []];
        $citationModeNative = ['t' => 'AuthorInText', 'c' => []];
        $tableAlignmentNative = ['t' => 'AlignCenter', 'c' => []];
        $columnWidthNative = ['t' => 'ColWidth', 'c' => [0.6]];
        $rowHeadColumnsNative = ['t' => 'RowHeadColumns', 'c' => [1]];
        $cellAlignmentNative = ['t' => 'AlignLeft', 'c' => []];
        $rowSpanNative = ['t' => 'RowSpan', 'c' => [2]];
        $colSpanNative = ['t' => 'ColSpan', 'c' => [3]];
        $citationRecord = [
            'citationId' => 'source-helper',
            'citationPrefix' => [],
            'citationSuffix' => [],
            'citationMode' => $citationModeNative,
            'citationNoteNum' => 0,
            'citationHash' => 99,
        ];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['helper-payload-shapes', [], []],
                ['t' => 'Caption', 'c' => [null, []]],
                [[$tableAlignmentNative, $columnWidthNative]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        $rowHeadColumnsNative,
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        $cellAlignmentNative,
                                        $rowSpanNative,
                                        $colSpanNative,
                                        [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Cell']]]],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'OrderedList', 'c' => [
                    [4, $styleNative, $delimiterNative],
                    [[['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Item']]]]],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Quoted', 'c' => [$quoteTypeNative, [['t' => 'Str', 'c' => 'quoted']]]],
                    ['t' => 'Space'],
                    ['t' => 'Math', 'c' => [$mathTypeNative, 'x + y']],
                    ['t' => 'Space'],
                    ['t' => 'Cite', 'c' => [[$citationRecord], [['t' => 'Str', 'c' => '@source-helper']]]],
                ]],
                $tableBlock,
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $body = $jsonPacket['blocks'][2]['c'][4][0];
            $bodyPayload = $body['c'] ?? $body;
            $row = $bodyPayload[3][0]['c'] ?? $bodyPayload[3][0];
            $cell = $row[1][0]['c'] ?? $row[1][0];

            $t->same($styleNative, $jsonPacket['blocks'][0]['c'][0][1], "{$source} json writer list style native payload");
            $t->same($delimiterNative, $jsonPacket['blocks'][0]['c'][0][2], "{$source} json writer list delimiter native payload");
            $t->same($quoteTypeNative, $jsonPacket['blocks'][1]['c'][0]['c'][0], "{$source} json writer quote native payload");
            $t->same($mathTypeNative, $jsonPacket['blocks'][1]['c'][2]['c'][0], "{$source} json writer math native payload");
            $t->same($citationModeNative, $jsonPacket['blocks'][1]['c'][4]['c'][0][0]['citationMode'], "{$source} json writer citation mode native payload");
            $t->same($tableAlignmentNative, $jsonPacket['blocks'][2]['c'][2][0][0], "{$source} json writer column alignment native payload");
            $t->same($columnWidthNative, $jsonPacket['blocks'][2]['c'][2][0][1], "{$source} json writer column width native payload");
            $t->same($rowHeadColumnsNative, $bodyPayload[1], "{$source} json writer row-head native payload");
            $t->same($cellAlignmentNative, $cell[1], "{$source} json writer cell alignment native payload");
            $t->same($rowSpanNative, $cell[2], "{$source} json writer rowspan native payload");
            $t->same($colSpanNative, $cell[3], "{$source} json writer colspan native payload");
        }

        $document = $documents['json'];
        $orderedList = $document->children[0];
        $editedDocument = new AstNode('document', $document->attrs, [
            new AstNode('ordered_list', array_replace($orderedList->attrs, [
                'style' => 'decimal',
            ]), $orderedList->children),
        ]);
        $editedPacket = (new PandocJsonWriter())->toArray($editedDocument);

        $t->same(['t' => 'Decimal'], $editedPacket['blocks'][0]['c'][0][1], 'json writer regenerates stale list style helper payloads');
    },
    'preserves current native constructor payloads through pandoc json writer until edited' => static function (TestRunner $t): void {
        $blockCodeAttr = [
            'review-code-block',
            ['source-order'],
            [
                ['data-review', 'first'],
                ['data-origin', 'json-filter'],
                ['data-review', 'second'],
            ],
        ];
        $codeAttr = [
            'ticket-code',
            ['review-code'],
            [
                ['data-ticket', 'first'],
                ['data-ticket', 'second'],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'CodeBlock', 'c' => [$blockCodeAttr, "echo source\n"]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Code', 'c' => [$codeAttr, 'ticket-42']],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $encoded = (new PandocJsonWriter())->toArray($document);

            $t->same($packet['blocks'], $encoded['blocks'], "{$source} unchanged current constructor payloads");
        }

        $jsonDocument = $documents['json'];
        $code = $jsonDocument->children[1]->children[0];
        $inlineOnlyPacket = (new PandocJsonWriter())->toArray(new AstNode('document', [], [
            new AstNode('paragraph', [], [$code]),
        ]));

        $t->same($packet['blocks'][1]['c'][0], $inlineOnlyPacket['blocks'][0]['c'][0], 'standalone current inline native payload is reusable');

        $editedCodeBlock = new AstNode('code_block', array_replace($jsonDocument->children[0]->attrs, [
            'text' => "echo edited\n",
        ]));
        $editedBlockPacket = (new PandocJsonWriter())->toArray(new AstNode('document', [], [$editedCodeBlock]));

        $t->same("echo edited\n", $editedBlockPacket['blocks'][0]['c'][1], 'edited code block text regenerates the block constructor');
        $t->same($blockCodeAttr, $editedBlockPacket['blocks'][0]['c'][0], 'edited code block may still preserve compatible attr tuple payloads');
        $t->same("echo source\n", $packet['blocks'][0]['c'][1], 'source code block payload remains distinct from edited output');

        $editedCode = new AstNode('code', array_replace($code->attrs, [
            'text' => 'ticket-43',
        ]));
        $editedInlinePacket = (new PandocJsonWriter())->toArray(new AstNode('document', [], [
            new AstNode('paragraph', [], [$editedCode]),
        ]));

        $t->same('ticket-43', $editedInlinePacket['blocks'][0]['c'][0]['c'][1], 'edited code text regenerates the inline constructor');
        $t->same($codeAttr, $editedInlinePacket['blocks'][0]['c'][0]['c'][0], 'edited code may still preserve compatible attr tuple payloads');
        $t->same('ticket-42', $packet['blocks'][1]['c'][0]['c'][1], 'source code inline payload remains distinct from edited output');
    },
    'preserves json-reader native constructor payloads through native writer when rebuilding wrappers' => static function (TestRunner $t): void {
        $blockquoteBlock = [
            't' => 'BlockQuote',
            'c' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Quoted'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'source'],
                ]],
            ],
            'reviewQueue' => 'blockquote-source',
            'sourceOrdinal' => 61,
        ];
        $linkInline = [
            't' => 'Link',
            'c' => [
                ['source-link', ['review-link'], [['data-link', 'source']]],
                [
                    ['t' => 'Str', 'c' => 'Source'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'link'],
                ],
                ['https://example.test/source', 'Source title'],
            ],
            'reviewQueue' => 'link-source',
            'sourceOrdinal' => 62,
        ];
        $imageInline = [
            't' => 'Image',
            'c' => [
                ['source-image', ['review-image'], [['data-image', 'source']]],
                [
                    ['t' => 'Str', 'c' => 'Source'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'image'],
                ],
                ['media/source.png', 'Source image'],
            ],
            'reviewQueue' => 'image-source',
            'sourceOrdinal' => 63,
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                $blockquoteBlock,
                ['t' => 'Para', 'c' => [
                    $linkInline,
                    ['t' => 'Space'],
                    $imageInline,
                ]],
            ],
        ];

        $document = (new PandocJsonReader())->readPacket($packet);
        $blockquote = $document->children[0];
        $paragraph = $document->children[1];
        $link = $paragraph->children[0];
        $image = $paragraph->children[2];
        $rebuilt = new AstNode('document', $document->attrs, [
            $blockquote,
            new AstNode('paragraph', [], [
                $link,
                new AstNode('space'),
                $image,
            ]),
        ]);

        $nativePacket = json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR);

        $t->same($blockquoteBlock, $nativePacket['blocks'][0]);
        $t->same($linkInline, $nativePacket['blocks'][1]['c'][0]);
        $t->same($imageInline, $nativePacket['blocks'][1]['c'][2]);
        $t->same(['text', 'space', 'text'], array_map(static fn (AstNode $node): string => $node->type, $link->children));
        $t->same(['text', 'space', 'text'], array_map(static fn (AstNode $node): string => $node->type, $image->children));

        $editedBlockquote = new AstNode('blockquote', $blockquote->attrs, [
            new AstNode('paragraph', $blockquote->children[0]->attrs, [
                new AstNode('text', ['text' => 'Edited']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'source']),
            ]),
        ]);
        $editedLink = new AstNode('link', $link->attrs, [
            new AstNode('text', ['text' => 'Edited']),
            new AstNode('space'),
            new AstNode('text', ['text' => 'link']),
        ]);
        $editedPacket = json_decode((new NativeWriter())->write(new AstNode('document', [], [
            $editedBlockquote,
            new AstNode('paragraph', [], [$editedLink]),
        ])), true, 512, JSON_THROW_ON_ERROR);

        $t->same('BlockQuote', $editedPacket['blocks'][0]['t']);
        $t->same('Edited', $editedPacket['blocks'][0]['c'][0]['c'][0]['c']);
        $t->same(false, array_key_exists('reviewQueue', $editedPacket['blocks'][0]));
        $t->same('Link', $editedPacket['blocks'][1]['c'][0]['t']);
        $t->same('Edited', $editedPacket['blocks'][1]['c'][0]['c'][1][0]['c']);
        $t->same(false, array_key_exists('reviewQueue', $editedPacket['blocks'][1]['c'][0]));
    },
    'preserves native-reader-only inline payloads when rebuilding wrappers' => static function (TestRunner $t): void {
        $codeAttr = [
            'native-code',
            ['php'],
            [['data-source', 'native-reader']],
            ['reviewQueue' => 'code-attr-sidecar'],
        ];
        $spanAttr = [
            'native-span',
            ['review-span'],
            [['data-span', 'native-reader']],
            ['reviewQueue' => 'span-attr-sidecar'],
        ];
        $codeInline = [
            't' => 'Code',
            'c' => [$codeAttr, 'echo 1;'],
            'reviewQueue' => 'code-inline-source',
        ];
        $spanInline = [
            't' => 'Span',
            'c' => [
                $spanAttr,
                [
                    ['t' => 'Str', 'c' => 'native'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'span'],
                ],
            ],
            'reviewQueue' => 'span-inline-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    $codeInline,
                    ['t' => 'Space'],
                    $spanInline,
                ]],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR));
        $paragraph = $document->children[0];
        $code = $paragraph->children[0];
        $span = $paragraph->children[2];
        $rebuilt = new AstNode('document', $document->attrs, [
            new AstNode('paragraph', [], [
                $code,
                new AstNode('space'),
                $span,
            ]),
        ]);

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($rebuilt),
            'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
        ] as $writer => $encoded) {
            $t->same($codeAttr, $code->attr('attrNative'), "{$writer} source code Attr tuple");
            $t->same($spanAttr, $span->attr('attrNative'), "{$writer} source span Attr tuple");
            $t->same($codeInline, $encoded['blocks'][0]['c'][0], "{$writer} writer preserves native-reader-only code inline payload");
            $t->same($spanInline, $encoded['blocks'][0]['c'][2], "{$writer} writer preserves native-reader-only span inline payload");
        }

        $editedCode = new AstNode('code', array_replace($code->attrs, [
            'text' => 'echo 2;',
        ]));
        $editedPacket = json_decode((new NativeWriter())->write(new AstNode('document', $document->attrs, [
            new AstNode('paragraph', [], [$editedCode]),
        ])), true, 512, JSON_THROW_ON_ERROR);
        $editedInline = $editedPacket['blocks'][0]['c'][0];

        $t->same('Code', $editedInline['t']);
        $t->same('echo 2;', $editedInline['c'][1]);
        $t->same($codeAttr, $editedInline['c'][0]);
        $t->same(false, array_key_exists('reviewQueue', $editedInline), 'edited native-reader-only inline payload drops stale wrapper sidecar');
    },
    'preserves native block attr sidecar payloads when rebuilding wrappers' => static function (TestRunner $t): void {
        $headingAttr = [
            'native-heading',
            ['review-heading'],
            [['data-source', 'native-reader']],
            ['reviewQueue' => 'heading-attr-sidecar'],
        ];
        $codeBlockAttr = [
            'native-code-block',
            ['php'],
            [['data-source', 'native-reader']],
            ['reviewQueue' => 'code-block-attr-sidecar'],
        ];
        $divAttr = [
            'native-div',
            ['review-div'],
            [['data-source', 'native-reader']],
            ['reviewQueue' => 'div-attr-sidecar'],
        ];
        $headingBlock = [
            't' => 'Header',
            'c' => [
                2,
                $headingAttr,
                [
                    ['t' => 'Str', 'c' => 'Native'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'heading'],
                ],
            ],
            'reviewQueue' => 'heading-source',
        ];
        $codeBlock = [
            't' => 'CodeBlock',
            'c' => [$codeBlockAttr, "echo 1;\n"],
            'reviewQueue' => 'code-block-source',
        ];
        $divBlock = [
            't' => 'Div',
            'c' => [
                $divAttr,
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Str', 'c' => 'Native'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'div'],
                    ]],
                ],
            ],
            'reviewQueue' => 'div-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$headingBlock, $codeBlock, $divBlock],
        ];

        $document = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR));
        $heading = $document->children[0];
        $code = $document->children[1];
        $div = $document->children[2];
        $rebuilt = new AstNode('document', $document->attrs, [
            new AstNode('heading', $heading->attrs, $heading->children),
            new AstNode('code_block', $code->attrs),
            new AstNode('div', $div->attrs, $div->children),
        ]);

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($rebuilt),
            'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
        ] as $writer => $encoded) {
            $t->same($headingAttr, $heading->attr('attrNative'), "{$writer} source heading Attr tuple");
            $t->same($codeBlockAttr, $code->attr('attrNative'), "{$writer} source code block Attr tuple");
            $t->same($divAttr, $div->attr('attrNative'), "{$writer} source div Attr tuple");
            $t->same($headingBlock, $encoded['blocks'][0], "{$writer} writer preserves native heading block payload");
            $t->same($codeBlock, $encoded['blocks'][1], "{$writer} writer preserves native code block payload");
            $t->same($divBlock, $encoded['blocks'][2], "{$writer} writer preserves native div block payload");
        }

        $editedHeading = new AstNode('heading', array_replace($heading->attrs, [
            'text' => 'Edited heading',
        ]), [
            new AstNode('text', ['text' => 'Edited']),
            new AstNode('space'),
            new AstNode('text', ['text' => 'heading']),
        ]);
        $editedCode = new AstNode('code_block', array_replace($code->attrs, [
            'text' => "echo 2;\n",
        ]));
        $editedDivParagraph = new AstNode('paragraph', $div->children[0]->attrs, [
            new AstNode('text', ['text' => 'Edited']),
            new AstNode('space'),
            new AstNode('text', ['text' => 'div']),
        ]);
        $editedDiv = new AstNode('div', $div->attrs, [$editedDivParagraph]);
        $editedDocument = new AstNode('document', $document->attrs, [$editedHeading, $editedCode, $editedDiv]);

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($editedDocument),
            'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
        ] as $writer => $encoded) {
            $t->same('Edited', $encoded['blocks'][0]['c'][2][0]['c'], "{$writer} writer regenerates edited heading text");
            $t->same($headingAttr, $encoded['blocks'][0]['c'][1], "{$writer} writer preserves unchanged heading Attr sidecar");
            $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$writer} writer drops stale heading wrapper sidecar");
            $t->same("echo 2;\n", $encoded['blocks'][1]['c'][1], "{$writer} writer regenerates edited code block text");
            $t->same($codeBlockAttr, $encoded['blocks'][1]['c'][0], "{$writer} writer preserves unchanged code block Attr sidecar");
            $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][1]), "{$writer} writer drops stale code block wrapper sidecar");
            $t->same('Edited', $encoded['blocks'][2]['c'][1][0]['c'][0]['c'], "{$writer} writer regenerates edited div child text");
            $t->same($divAttr, $encoded['blocks'][2]['c'][0], "{$writer} writer preserves unchanged div Attr sidecar");
            $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][2]), "{$writer} writer drops stale div wrapper sidecar");
        }
    },
    'preserves current plain and paragraph native payloads through pandoc json writer until edited' => static function (TestRunner $t): void {
        $plainBlock = [
            't' => 'Plain',
            'c' => [
                ['t' => 'Str', 'c' => 'Plain'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'payload'],
            ],
            'sourcepos' => [['review', 'plain']],
            'reviewQueue' => 'plain-source',
        ];
        $paragraphBlock = [
            't' => 'Para',
            'c' => [
                ['t' => 'Str', 'c' => 'Para'],
                ['t' => 'Space'],
                ['t' => 'Strong', 'c' => [
                    ['t' => 'Str', 'c' => 'payload'],
                ]],
            ],
            'sourcepos' => [['review', 'paragraph']],
            'reviewQueue' => 'paragraph-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$plainBlock, $paragraphBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $plain = $document->children[0];
            $paragraph = $document->children[1];
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same('plain', $plain->type, "{$source} plain block type");
            $t->same('paragraph', $paragraph->type, "{$source} paragraph block type");
            $t->same($plainBlock, $plain->attr('native'), "{$source} plain native payload");
            $t->same($paragraphBlock, $paragraph->attr('native'), "{$source} paragraph native payload");
            $t->same($plainBlock, $jsonPacket['blocks'][0], "{$source} JSON writer preserves unchanged plain payload");
            $t->same($paragraphBlock, $jsonPacket['blocks'][1], "{$source} JSON writer preserves unchanged paragraph payload");
            $t->same($plainBlock, $nativePacket['blocks'][0], "{$source} native writer preserves unchanged plain payload");
            $t->same($paragraphBlock, $nativePacket['blocks'][1], "{$source} native writer preserves unchanged paragraph payload");
        }

        $jsonDocument = $documents['json'];
        $editedDocument = new AstNode('document', ['pandocApiVersion' => [1, 23, 1]], [
            new AstNode('plain', $jsonDocument->children[0]->attrs, [
                new AstNode('text', ['text' => 'Plain']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'changed']),
            ]),
            new AstNode('paragraph', $jsonDocument->children[1]->attrs, [
                new AstNode('text', ['text' => 'Para']),
                new AstNode('space'),
                new AstNode('strong', [], [
                    new AstNode('text', ['text' => 'changed']),
                ]),
            ]),
        ]);
        $editedJson = (new PandocJsonWriter())->toArray($editedDocument);
        $editedNative = json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR);

        foreach (['json' => $editedJson, 'native' => $editedNative] as $source => $encoded) {
            $t->same('Plain', $encoded['blocks'][0]['t'], "{$source} edited plain constructor");
            $t->same('changed', $encoded['blocks'][0]['c'][2]['c'], "{$source} edited plain text regenerates");
            $t->same(false, array_key_exists('sourcepos', $encoded['blocks'][0]), "{$source} edited plain drops stale source position");
            $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} edited plain drops stale review queue");
            $t->same('Para', $encoded['blocks'][1]['t'], "{$source} edited paragraph constructor");
            $t->same('changed', $encoded['blocks'][1]['c'][2]['c'][0]['c'], "{$source} edited paragraph text regenerates");
            $t->same(false, array_key_exists('sourcepos', $encoded['blocks'][1]), "{$source} edited paragraph drops stale source position");
            $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][1]), "{$source} edited paragraph drops stale review queue");
        }
    },
    'preserves native div plain block boundaries in wordpress handoff' => static function (TestRunner $t): void {
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Div', 'c' => [
                    ['boundary-div', [], [['data-source', 'native-boundary']]],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'First'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'plain'],
                        ]],
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Second'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'plain'],
                        ]],
                        ['t' => 'RawBlock', 'c' => ['html', '<section data-review="raw-boundary">Raw block</section>']],
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Str', 'c' => 'After'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'raw'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'boundary'],
                        ]],
                    ],
                ]],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $div = $document->children[0];
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('div', $div->type);
        $t->same('boundary-div', $div->attr('id'));
        $t->same(['plain', 'plain', 'raw_html', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $div->children));
        $t->same($native['blocks'], $roundTrip['blocks']);
        $t->contains('<div id="boundary-div" data-source="native-boundary">', $blocks);
        $t->contains('<p>First plain</p><p>Second plain</p>', $blocks);
        $t->contains('<p>Second plain</p><section data-review="raw-boundary">Raw block</section><p>After raw boundary</p>', $blocks);
        $t->true(!str_contains($blocks, 'First plainSecond plain'), 'Adjacent native Plain blocks should keep a visible WordPress boundary');
        $t->true(!str_contains($blocks, 'Second plain<section'), 'Native Plain before raw blocks should not collapse into raw HTML');
    },
    'preserves current header native payloads through json and native writers until edited' => static function (TestRunner $t): void {
        $headerAttr = [
            'review-heading',
            ['source-heading'],
            [
                ['data-review', 'first'],
                ['data-origin', 'json-filter'],
                ['data-review', 'second'],
            ],
        ];
        $headerBlock = [
            't' => 'Header',
            'c' => [
                3,
                $headerAttr,
                [
                    ['t' => 'Str', 'c' => 'Review'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'Heading'],
                ],
            ],
            'reviewQueue' => 'wp-import',
            'sourceOrdinal' => 42,
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$headerBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $heading = $document->children[0];
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same('heading', $heading->type, "{$source} header type");
            $t->same('Header', $heading->attr('constructor'), "{$source} header constructor");
            $t->same($headerBlock, $heading->attr('native'), "{$source} source-tagged header native payload");
            $t->same($headerAttr, $heading->attr('attrNative'), "{$source} duplicate header attr tuple");
            $t->same(['data-review' => 'second', 'data-origin' => 'json-filter'], $heading->attr('attributes'), "{$source} normalized duplicate header attrs");
            $t->same($headerBlock, $jsonPacket['blocks'][0], "{$source} JSON writer preserves unchanged source-tagged header payload");
            $t->same($headerBlock, $nativePacket['blocks'][0], "{$source} native writer preserves unchanged source-tagged header payload");
        }

        $heading = $documents['json']->children[0];
        $editedHeading = new AstNode('heading', array_replace($heading->attrs, [
            'level' => 4,
        ]), $heading->children);
        $editedDocument = new AstNode('document', ['pandocApiVersion' => [1, 23, 1]], [$editedHeading]);
        $editedJson = (new PandocJsonWriter())->toArray($editedDocument);
        $editedNative = json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR);

        foreach (['json' => $editedJson, 'native' => $editedNative] as $source => $encoded) {
            $t->same('Header', $encoded['blocks'][0]['t'], "{$source} edited header constructor");
            $t->same(4, $encoded['blocks'][0]['c'][0], "{$source} edited header level regenerates");
            $t->same($headerAttr, $encoded['blocks'][0]['c'][1], "{$source} edited header keeps compatible attr tuple");
            $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} edited header drops stale review provenance");
            $t->same(false, array_key_exists('sourceOrdinal', $encoded['blocks'][0]), "{$source} edited header drops stale source ordinal");
        }
    },
    'preserves current structural block native payloads through pandoc json writer until edited' => static function (TestRunner $t): void {
        $blockquoteBlock = [
            't' => 'BlockQuote',
            'c' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Quoted'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'source'],
                ]],
            ],
            'reviewQueue' => 'wp-import',
            'sourceOrdinal' => 51,
        ];
        $bulletItem = [
            ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Bullet'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'source'],
            ]],
        ];
        $bulletListBlock = [
            't' => 'BulletList',
            'c' => [$bulletItem],
            'reviewQueue' => 'wp-import',
            'sourceOrdinal' => 52,
        ];
        $orderedItem = [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Ordered'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'source'],
            ]],
        ];
        $orderedListBlock = [
            't' => 'OrderedList',
            'c' => [
                [7, ['t' => 'UpperAlpha', 'c' => []], ['t' => 'TwoParens', 'c' => []]],
                [$orderedItem],
            ],
            'reviewQueue' => 'wp-import',
            'sourceOrdinal' => 53,
        ];
        $definitionTerm = [
            ['t' => 'Str', 'c' => 'Source'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'term'],
        ];
        $definitionBodies = [
            [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Primary'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'definition'],
                ]],
            ],
            [
                ['t' => 'Plain', 'c' => [
                    ['t' => 'Str', 'c' => 'Alias'],
                ]],
            ],
        ];
        $definitionListBlock = [
            't' => 'DefinitionList',
            'c' => [
                [$definitionTerm, $definitionBodies],
            ],
            'reviewQueue' => 'wp-import',
            'sourceOrdinal' => 54,
        ];
        $lineBlock = [
            't' => 'LineBlock',
            'c' => [
                [
                    ['t' => 'Str', 'c' => 'Line'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'source'],
                ],
            ],
            'reviewQueue' => 'wp-import',
            'sourceOrdinal' => 55,
        ];
        $divAttr = [
            'source-div',
            ['review'],
            [
                ['data-source', 'first'],
                ['data-source', 'second'],
            ],
        ];
        $divBlock = [
            't' => 'Div',
            'c' => [
                $divAttr,
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Wrapped'],
                    ]],
                ],
            ],
            'reviewQueue' => 'wp-import',
            'sourceOrdinal' => 56,
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                $blockquoteBlock,
                $bulletListBlock,
                $orderedListBlock,
                $definitionListBlock,
                $lineBlock,
                $divBlock,
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $jsonPacket = (new PandocJsonWriter())->toArray($document);

            $t->same($packet['blocks'], $jsonPacket['blocks'], "{$source} JSON writer preserves unchanged structural block payloads");
        }

        $blockquote = $documents['json']->children[0];
        $paragraph = $blockquote->children[0];
        $editedText = new AstNode('text', array_replace($paragraph->children[0]->attrs, [
            'text' => 'Edited',
        ]));
        $editedParagraph = new AstNode('paragraph', $paragraph->attrs, [
            $editedText,
            $paragraph->children[1],
            $paragraph->children[2],
        ]);
        $editedDocument = new AstNode('document', ['pandocApiVersion' => [1, 23, 1]], [
            new AstNode('blockquote', $blockquote->attrs, [$editedParagraph]),
        ]);
        $editedPacket = (new PandocJsonWriter())->toArray($editedDocument);

        $t->same('BlockQuote', $editedPacket['blocks'][0]['t'], 'edited structural block constructor regenerates');
        $t->same('Edited', $editedPacket['blocks'][0]['c'][0]['c'][0]['c'], 'edited structural child content regenerates');
        $t->same(false, array_key_exists('reviewQueue', $editedPacket['blocks'][0]), 'edited structural block drops stale review provenance');
        $t->same(false, array_key_exists('sourceOrdinal', $editedPacket['blocks'][0]), 'edited structural block drops stale source ordinal');
    },
    'preserves current table and figure native payloads through pandoc json writer until edited' => static function (TestRunner $t): void {
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['source-table', ['review-table'], [['data-source', 'native']]],
                ['t' => 'Caption', 'c' => [
                    null,
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Source'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'table'],
                        ]],
                    ],
                ]],
                [[['t' => 'AlignCenter', 'c' => []], ['t' => 'ColWidth', 'c' => [0.75]]]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => [1]],
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignCenter', 'c' => []],
                                        ['t' => 'RowSpan', 'c' => [1]],
                                        ['t' => 'ColSpan', 'c' => [1]],
                                        [
                                            ['t' => 'Plain', 'c' => [
                                                ['t' => 'Str', 'c' => 'Metric'],
                                            ]],
                                        ],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
            'reviewQueue' => 'wp-import',
            'sourceOrdinal' => 71,
        ];
        $figureBlock = [
            't' => 'Figure',
            'c' => [
                ['source-figure', ['review-figure'], [['data-source', 'native']]],
                ['t' => 'Caption', 'c' => [
                    ['t' => 'Nothing'],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Source'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'figure'],
                        ]],
                    ],
                ]],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Image', 'c' => [
                            ['', ['review-image'], [['data-image', 'source']]],
                            [
                                ['t' => 'Str', 'c' => 'Source'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'image'],
                            ],
                            ['media/source.png', 'Source image'],
                        ]],
                    ]],
                ],
            ],
            'reviewQueue' => 'wp-import',
            'sourceOrdinal' => 72,
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock, $figureBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $table = $document->children[0];
            $figure = $document->children[1];

            $t->same('table', $table->type, "{$source} table node");
            $t->same('figure', $figure->type, "{$source} figure node");
            $t->same($tableBlock, $table->attr('native'), "{$source} table native payload");
            $t->same($figureBlock, $figure->attr('native'), "{$source} figure native payload");
            $t->same($packet['blocks'], $jsonPacket['blocks'], "{$source} JSON writer preserves unchanged table and figure payloads");
        }

        $table = $documents['json']->children[0];
        $figure = $documents['json']->children[1];
        $editedTable = new AstNode('table', array_replace($table->attrs, [
            'id' => 'edited-table',
        ]), $table->children);
        $editedFigure = new AstNode('figure', array_replace($figure->attrs, [
            'caption' => 'Edited figure',
            'captionInlines' => [
                new AstNode('text', ['text' => 'Edited']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'figure']),
            ],
            'captionBlocks' => [
                new AstNode('plain', [], [
                    new AstNode('text', ['text' => 'Edited']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'figure']),
                ]),
            ],
        ]), $figure->children);
        $editedPacket = (new PandocJsonWriter())->toArray(new AstNode('document', ['pandocApiVersion' => [1, 23, 1]], [
            $editedTable,
            $editedFigure,
        ]));

        $t->same('Table', $editedPacket['blocks'][0]['t']);
        $t->same('edited-table', $editedPacket['blocks'][0]['c'][0][0]);
        $t->same(false, array_key_exists('reviewQueue', $editedPacket['blocks'][0]), 'edited table drops stale review provenance');
        $t->same(false, array_key_exists('sourceOrdinal', $editedPacket['blocks'][0]), 'edited table drops stale source ordinal');
        $t->same('Figure', $editedPacket['blocks'][1]['t']);
        $t->same('Edited', $editedPacket['blocks'][1]['c'][1]['c'][1][0]['c'][0]['c']);
        $t->same(false, array_key_exists('reviewQueue', $editedPacket['blocks'][1]), 'edited figure drops stale review provenance');
        $t->same(false, array_key_exists('sourceOrdinal', $editedPacket['blocks'][1]), 'edited figure drops stale source ordinal');
    },
    'preserves sidecar-free current table and figure native payloads through json and native writers until edited' => static function (TestRunner $t): void {
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['sidecar-free-table', ['review-table'], [['data-source', 'native']]],
                ['t' => 'Caption', 'c' => [
                    null,
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Sidecar-free'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'table'],
                        ]],
                    ],
                ]],
                [[['t' => 'AlignCenter', 'c' => []], ['t' => 'ColWidth', 'c' => [0.5]]]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => [0]],
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignCenter', 'c' => []],
                                        ['t' => 'RowSpan', 'c' => [1]],
                                        ['t' => 'ColSpan', 'c' => [1]],
                                        [
                                            ['t' => 'Para', 'c' => [
                                                ['t' => 'Str', 'c' => 'Sidecar-free'],
                                                ['t' => 'Space'],
                                                ['t' => 'Str', 'c' => 'cell'],
                                            ]],
                                        ],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
        ];
        $figureBlock = [
            't' => 'Figure',
            'c' => [
                ['sidecar-free-figure', ['review-figure'], [['data-source', 'native']]],
                ['t' => 'Caption', 'c' => [
                    null,
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Sidecar-free'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'figure'],
                        ]],
                    ],
                ]],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Image', 'c' => [
                            ['', ['review-image'], [['data-image', 'source']]],
                            [
                                ['t' => 'Str', 'c' => 'Sidecar-free'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'image'],
                            ],
                            ['media/sidecar-free.png', 'Source image'],
                        ]],
                    ]],
                ],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock, $figureBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $figure = $document->children[1];
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same('table', $table->type, "{$source} sidecar-free table node");
            $t->same('figure', $figure->type, "{$source} sidecar-free figure node");
            $t->same($tableBlock, $table->attr('native'), "{$source} sidecar-free table native payload");
            $t->same($figureBlock, $figure->attr('native'), "{$source} sidecar-free figure native payload");
            $t->same($packet['blocks'], $jsonPacket['blocks'], "{$source} JSON writer preserves sidecar-free table and figure payloads");
            $t->same($packet['blocks'], $nativePacket['blocks'], "{$source} native writer preserves sidecar-free table and figure payloads");
        }

        $table = $documents['json']->children[0];
        $figure = $documents['json']->children[1];
        $body = $table->children[0];
        $row = $body->children[0];
        $cell = $row->children[0];
        $editedCell = new AstNode('table_cell', $cell->attrs, [
            new AstNode('text', ['text' => 'Edited']),
        ]);
        $editedRow = new AstNode('table_row', $row->attrs, [$editedCell]);
        $editedBody = new AstNode('table_body', $body->attrs, [$editedRow]);
        $editedTable = new AstNode('table', $table->attrs, [$editedBody]);
        $editedFigure = new AstNode('figure', array_replace($figure->attrs, [
            'caption' => 'Edited figure',
            'captionInlines' => [
                new AstNode('text', ['text' => 'Edited']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'figure']),
            ],
            'captionBlocks' => [
                new AstNode('plain', [], [
                    new AstNode('text', ['text' => 'Edited']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'figure']),
                ]),
            ],
        ]), $figure->children);
        $editedDocument = new AstNode('document', ['pandocApiVersion' => [1, 23, 1]], [
            $editedTable,
            $editedFigure,
        ]);

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($editedDocument),
            'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
        ] as $writer => $encoded) {
            $encodedTable = $encoded['blocks'][0];
            $encodedBody = $encodedTable['c'][4][0]['c'] ?? $encodedTable['c'][4][0];
            $encodedRow = $encodedBody[3][0]['c'] ?? $encodedBody[3][0];
            $encodedCell = $encodedRow[1][0]['c'] ?? $encodedRow[1][0];
            $encodedFigure = $encoded['blocks'][1];

            $t->same('Edited', $encodedCell[4][0]['c'][0]['c'], "{$writer} writer regenerates edited sidecar-free table cell");
            $t->same(false, $encodedTable === $tableBlock, "{$writer} writer does not reuse edited sidecar-free table payload");
            $t->same('Edited', $encodedFigure['c'][1]['c'][1][0]['c'][0]['c'], "{$writer} writer regenerates edited sidecar-free figure caption");
            $t->same(false, $encodedFigure === $figureBlock, "{$writer} writer does not reuse edited sidecar-free figure payload");
        }
    },
    'preserves figure child block payloads when rebuilding edited figure wrappers' => static function (TestRunner $t): void {
        $childDiv = [
            't' => 'Div',
            'c' => [
                ['review-child', ['wp-import'], [['data-source', 'figure-child']]],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Str', 'c' => 'Reviewed'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'child'],
                    ]],
                ],
            ],
            'reviewQueue' => 'figure-child-source',
            'sourceOrdinal' => 83,
        ];
        $figureBlock = [
            't' => 'Figure',
            'c' => [
                ['review-figure', ['wp-import'], [['data-source', 'figure']]],
                ['t' => 'Caption', 'c' => [
                    ['t' => 'Nothing'],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Original'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'caption'],
                        ]],
                    ],
                ]],
                [$childDiv],
            ],
            'reviewQueue' => 'figure-wrapper-source',
            'sourceOrdinal' => 82,
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$figureBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $figure = $document->children[0];
            $t->same('figure', $figure->type, "{$source} figure node");
            $t->same('div', $figure->children[0]->type, "{$source} figure child block node");
            $t->same($childDiv, $figure->children[0]->attr('native'), "{$source} figure child native payload");

            $editedFigure = new AstNode('figure', array_replace($figure->attrs, [
                'caption' => 'Edited caption',
                'captionInlines' => [
                    new AstNode('text', ['text' => 'Edited']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'caption']),
                ],
                'captionBlocks' => [
                    new AstNode('plain', [], [
                        new AstNode('text', ['text' => 'Edited']),
                        new AstNode('space'),
                        new AstNode('text', ['text' => 'caption']),
                    ]),
                ],
            ]), $figure->children);
            $editedDocument = new AstNode('document', $document->attrs, [$editedFigure]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($editedDocument),
                'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedFigure = $encoded['blocks'][0];

                $t->same('Figure', $encodedFigure['t'], "{$source} {$writer} writer regenerates edited figure constructor");
                $t->same('Edited', $encodedFigure['c'][1]['c'][1][0]['c'][0]['c'], "{$source} {$writer} writer regenerates edited caption");
                $t->same(false, array_key_exists('reviewQueue', $encodedFigure), "{$source} {$writer} writer drops stale figure wrapper sidecar");
                $t->same(false, array_key_exists('sourceOrdinal', $encodedFigure), "{$source} {$writer} writer drops stale figure wrapper ordinal");
                $t->same($childDiv, $encodedFigure['c'][2][0], "{$source} {$writer} writer preserves unchanged figure child block payload");
            }
        }
    },
    'preserves table row and cell native payloads when rebuilding table wrappers' => static function (TestRunner $t): void {
        $cellNative = [
            't' => 'Cell',
            'c' => [
                ['', [], []],
                ['t' => 'AlignCenter', 'c' => []],
                ['t' => 'RowSpan', 'c' => [1]],
                ['t' => 'ColSpan', 'c' => [1]],
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Metric'],
                    ]],
                ],
            ],
            'reviewQueue' => 'cell-source',
            'sourceOrdinal' => 82,
        ];
        $rowNative = [
            't' => 'Row',
            'c' => [
                ['', [], []],
                [$cellNative],
            ],
            'reviewQueue' => 'row-source',
            'sourceOrdinal' => 81,
        ];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['source-table', [], []],
                ['t' => 'Caption', 'c' => [null, []]],
                [[['t' => 'AlignCenter', 'c' => []], ['t' => 'ColWidthDefault']]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => [0]],
                        [],
                        [$rowNative],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
            'reviewQueue' => 'table-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $body = $table->children[0];
            $row = $body->children[0];
            $cell = $row->children[0];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('table', array_replace($table->attrs, ['id' => 'rebuilt-table']), $table->children),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];
                $encodedBody = $encodedTable['c'][4][0];
                $encodedBodyPayload = $encodedBody['c'] ?? $encodedBody;
                $encodedRow = $encodedBodyPayload[3][0];
                $encodedRowPayload = $encodedRow['c'] ?? $encodedRow;
                $encodedCell = $encodedRowPayload[1][0];

                $t->same('rebuilt-table', $encodedTable['c'][0][0], "{$source} {$writer} writer regenerates edited table attr");
                $t->same(false, array_key_exists('reviewQueue', $encodedTable), "{$source} {$writer} writer drops stale table sidecar");
                $t->same($rowNative, $encodedRow, "{$source} {$writer} writer preserves row helper native payload");
                $t->same($cellNative, $encodedCell, "{$source} {$writer} writer preserves cell helper native payload");
            }

            $editedCell = new AstNode('table_cell', $cell->attrs, [
                new AstNode('text', ['text' => 'Edited']),
            ]);
            $editedRow = new AstNode('table_row', $row->attrs, [$editedCell]);
            $editedBody = new AstNode('table_body', $body->attrs, [$editedRow]);
            $editedTable = new AstNode('table', array_replace($table->attrs, ['id' => 'edited-table']), [$editedBody]);
            $editedPacket = (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [$editedTable]));
            $editedBodyPayload = $editedPacket['blocks'][0]['c'][4][0]['c'] ?? $editedPacket['blocks'][0]['c'][4][0];
            $editedRowPayload = $editedBodyPayload[3][0];
            $editedRowContent = $editedRowPayload['c'] ?? $editedRowPayload;
            $editedCell = $editedRowContent[1][0];
            $editedCellPayload = $editedCell['c'] ?? $editedCell;

            $t->same('Edited', $editedCellPayload[4][0]['c'][0]['c'], "{$source} edited cell regenerates content");
            $t->same(false, array_key_exists('reviewQueue', $editedRowPayload), "{$source} edited cell drops stale row wrapper sidecar");
            $t->same('Cell', $editedCell['t'] ?? null, "{$source} edited cell keeps current Cell constructor");
            $t->same(false, array_key_exists('reviewQueue', $editedCell), "{$source} edited cell drops stale cell wrapper sidecar");
        }
    },
    'regenerates edited table cell attr and span helpers while preserving neighbor sidecars' => static function (TestRunner $t): void {
        $editedCellAttrNative = [
            't' => 'Attr',
            'c' => ['edit-cell', ['source-edit'], [['data-cell', 'edit-source']]],
            'reviewQueue' => 'edit-cell-attr-source',
            'sourceOrdinal' => 101,
        ];
        $editedCellAlignmentNative = [
            't' => 'AlignCenter',
            'reviewQueue' => 'edit-cell-align-source',
            'sourceOrdinal' => 102,
        ];
        $editedCellRowSpanNative = [
            't' => 'RowSpan',
            'c' => [1],
            'reviewQueue' => 'edit-cell-rowspan-source',
            'sourceOrdinal' => 103,
        ];
        $editedCellColSpanNative = [
            't' => 'ColSpan',
            'c' => [1],
            'reviewQueue' => 'edit-cell-colspan-source',
            'sourceOrdinal' => 104,
        ];
        $editedCellNative = [
            't' => 'Cell',
            'c' => [
                $editedCellAttrNative,
                $editedCellAlignmentNative,
                $editedCellRowSpanNative,
                $editedCellColSpanNative,
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Edited-target'],
                    ]],
                ],
            ],
            'reviewQueue' => 'edit-cell-wrapper-source',
            'sourceOrdinal' => 105,
        ];
        $neighborCellNative = [
            't' => 'Cell',
            'c' => [
                [
                    't' => 'Attr',
                    'c' => ['neighbor-cell', ['source-neighbor'], [['data-cell', 'neighbor-source']]],
                    'reviewQueue' => 'neighbor-cell-attr-source',
                    'sourceOrdinal' => 111,
                ],
                [
                    't' => 'AlignRight',
                    'reviewQueue' => 'neighbor-cell-align-source',
                    'sourceOrdinal' => 112,
                ],
                [
                    't' => 'RowSpan',
                    'c' => [1],
                    'reviewQueue' => 'neighbor-cell-rowspan-source',
                    'sourceOrdinal' => 113,
                ],
                [
                    't' => 'ColSpan',
                    'c' => [2],
                    'reviewQueue' => 'neighbor-cell-colspan-source',
                    'sourceOrdinal' => 114,
                ],
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Neighbor'],
                    ]],
                ],
            ],
            'reviewQueue' => 'neighbor-cell-wrapper-source',
            'sourceOrdinal' => 115,
        ];
        $rowNative = [
            't' => 'Row',
            'c' => [
                ['', [], []],
                [$editedCellNative, $neighborCellNative],
            ],
            'reviewQueue' => 'row-wrapper-source',
            'sourceOrdinal' => 120,
        ];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['source-table', [], []],
                ['t' => 'Caption', 'c' => [null, []]],
                [
                    [['t' => 'AlignCenter'], ['t' => 'ColWidthDefault']],
                    [['t' => 'AlignRight'], ['t' => 'ColWidthDefault']],
                    [['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']],
                ],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => [0]],
                        [],
                        [$rowNative],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
            'reviewQueue' => 'table-wrapper-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $body = $table->children[0];
            $row = $body->children[0];
            $editCell = $row->children[0];
            $neighborCell = $row->children[1];

            $t->same($editedCellAttrNative, $editCell->attr('attrNative'), "{$source} source edit cell attr sidecar captured");
            $t->same($editedCellRowSpanNative, $editCell->attr('rowSpanNative'), "{$source} source edit cell RowSpan sidecar captured");
            $t->same($editedCellColSpanNative, $editCell->attr('colSpanNative'), "{$source} source edit cell ColSpan sidecar captured");
            $t->same($neighborCellNative, $neighborCell->attr('native'), "{$source} source neighbor cell wrapper sidecar captured");

            $editedCell = new AstNode('table_cell', array_replace($editCell->attrs, [
                'id' => 'merged-cell',
                'classes' => ['merged-edit'],
                'attributes' => [
                    'data-cell' => 'merged-source',
                    'data-review' => 'span-attr',
                ],
                'rowspan' => 2,
                'colspan' => 3,
            ]), $editCell->children);
            $editedRow = new AstNode('table_row', $row->attrs, [$editedCell, $neighborCell]);
            $editedBody = new AstNode('table_body', $body->attrs, [$editedRow]);
            $editedTable = new AstNode('table', $table->attrs, [$editedBody]);
            $editedDocument = new AstNode('document', $document->attrs, [$editedTable]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($editedDocument),
                'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedBody = $encoded['blocks'][0]['c'][4][0];
                $encodedBodyPayload = $encodedBody['c'] ?? $encodedBody;
                $encodedRow = $encodedBodyPayload[3][0];
                $encodedRowPayload = $encodedRow['c'] ?? $encodedRow;
                $encodedCells = $encodedRowPayload[1];
                $editedCellPayload = $encodedCells[0]['c'] ?? $encodedCells[0];

                $t->same([
                    'merged-cell',
                    ['merged-edit'],
                    [
                        ['data-cell', 'merged-source'],
                        ['data-review', 'span-attr'],
                    ],
                ], $editedCellPayload[0], "{$source} {$writer} writer regenerates edited cell Attr tuple");
                $t->same($editedCellAlignmentNative, $editedCellPayload[1], "{$source} {$writer} writer preserves unchanged edited-cell alignment helper sidecar");
                $t->same(['t' => 'RowSpan', 'c' => 2], $editedCellPayload[2], "{$source} {$writer} writer regenerates edited RowSpan helper without stale sidecar");
                $t->same(['t' => 'ColSpan', 'c' => 3], $editedCellPayload[3], "{$source} {$writer} writer regenerates edited ColSpan helper without stale sidecar");
                $t->same('Edited-target', $editedCellPayload[4][0]['c'][0]['c'], "{$source} {$writer} writer keeps edited cell block content");
                $t->same(false, array_key_exists('reviewQueue', $encodedCells[0]), "{$source} {$writer} writer drops stale edited cell wrapper sidecar");
                $t->same($neighborCellNative, $encodedCells[1], "{$source} {$writer} writer preserves neighboring cell wrapper and helper sidecars");
            }
        }
    },
    'regenerates edited spanning table cell sidecars while preserving table helpers' => static function (TestRunner $t): void {
        $firstColumnAlign = [
            't' => 'AlignLeft',
            'reviewQueue' => 'first-column-align-source',
            'sourceOrdinal' => 201,
        ];
        $firstColumnWidth = [
            't' => 'ColWidth',
            'c' => 0.44,
            'reviewQueue' => 'first-column-width-source',
            'sourceOrdinal' => 202,
        ];
        $secondColumnAlign = [
            't' => 'AlignDefault',
            'reviewQueue' => 'second-column-align-source',
            'sourceOrdinal' => 203,
        ];
        $secondColumnWidth = [
            't' => 'ColWidthDefault',
            'reviewQueue' => 'second-column-width-source',
            'sourceOrdinal' => 204,
        ];
        $rowHeadColumns = [
            't' => 'RowHeadColumns',
            'c' => [1],
            'reviewQueue' => 'row-head-columns-source',
            'sourceOrdinal' => 205,
        ];
        $sourceCellAttr = [
            't' => 'Attr',
            'c' => ['span-source', ['source-cell'], [['data-state', 'original']]],
            'reviewQueue' => 'stale-span-cell-attr-source',
            'sourceOrdinal' => 210,
        ];
        $sourceCellAlign = [
            't' => 'AlignCenter',
            'reviewQueue' => 'span-cell-align-source',
            'sourceOrdinal' => 211,
        ];
        $sourceCellRowSpan = [
            't' => 'RowSpan',
            'c' => [2],
            'reviewQueue' => 'stale-rowspan-source',
            'sourceOrdinal' => 212,
        ];
        $sourceCellColSpan = [
            't' => 'ColSpan',
            'c' => [3],
            'reviewQueue' => 'stale-colspan-source',
            'sourceOrdinal' => 213,
        ];
        $sourceCell = [
            't' => 'Cell',
            'c' => [
                $sourceCellAttr,
                $sourceCellAlign,
                $sourceCellRowSpan,
                $sourceCellColSpan,
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Span'],
                    ]],
                ],
            ],
            'reviewQueue' => 'stale-span-cell-wrapper',
            'sourceOrdinal' => 214,
        ];
        $neighborCell = [
            't' => 'Cell',
            'c' => [
                [
                    't' => 'Attr',
                    'c' => ['stable-neighbor', ['kept'], [['data-neighbor', 'stable']]],
                    'reviewQueue' => 'neighbor-attr-source',
                    'sourceOrdinal' => 220,
                ],
                [
                    't' => 'AlignRight',
                    'reviewQueue' => 'neighbor-align-source',
                    'sourceOrdinal' => 221,
                ],
                [
                    't' => 'RowSpan',
                    'c' => [1],
                    'reviewQueue' => 'neighbor-rowspan-source',
                    'sourceOrdinal' => 222,
                ],
                [
                    't' => 'ColSpan',
                    'c' => [1],
                    'reviewQueue' => 'neighbor-colspan-source',
                    'sourceOrdinal' => 223,
                ],
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Neighbor'],
                    ]],
                ],
            ],
            'reviewQueue' => 'neighbor-cell-wrapper-source',
            'sourceOrdinal' => 224,
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [[
                't' => 'Table',
                'c' => [
                    ['span-helper-table', ['review'], [['data-table', 'source']]],
                    ['t' => 'Caption', 'c' => [null, []]],
                    [
                        [$firstColumnAlign, $firstColumnWidth],
                        [$secondColumnAlign, $secondColumnWidth],
                    ],
                    ['t' => 'TableHead', 'c' => [['', [], []], []]],
                    [
                        ['t' => 'TableBody', 'c' => [
                            ['span-body', ['review-body'], [['data-body', 'source']]],
                            $rowHeadColumns,
                            [],
                            [[
                                ['row-source', ['review-row'], [['data-row', 'source']]],
                                [$sourceCell, $neighborCell],
                            ]],
                        ]],
                    ],
                    ['t' => 'TableFoot', 'c' => [['', [], []], []]],
                ],
            ]],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $body = $table->children[0];
            $row = $body->children[0];
            $spanCell = $row->children[0];
            $neighbor = $row->children[1];

            $t->same($sourceCellAttr, $spanCell->attr('attrNative'), "{$source} reader records spanning cell Attr sidecar");
            $t->same($sourceCellRowSpan, $spanCell->attr('rowSpanNative'), "{$source} reader records spanning cell RowSpan sidecar");
            $t->same($sourceCellColSpan, $spanCell->attr('colSpanNative'), "{$source} reader records spanning cell ColSpan sidecar");
            $t->same($neighborCell, $neighbor->attr('native'), "{$source} reader records neighbor cell wrapper sidecar");

            $editedSpanCell = new AstNode('table_cell', array_replace($spanCell->attrs, [
                'id' => 'span-edited',
                'classes' => ['source-cell', 'edited-span'],
                'attributes' => [
                    'data-state' => 'edited',
                    'data-review' => 'span-sidecar',
                ],
                'rowspan' => 4,
                'colspan' => 2,
            ]), $spanCell->children);
            $editedDocument = new AstNode('document', $document->attrs, [
                new AstNode('table', $table->attrs, [
                    new AstNode('table_body', $body->attrs, [
                        new AstNode('table_row', $row->attrs, [$editedSpanCell, $neighbor]),
                    ]),
                ]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($editedDocument),
                'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];
                $encodedBody = $encodedTable['c'][4][0];
                $encodedBodyPayload = $encodedBody['c'] ?? $encodedBody;
                $encodedRow = $encodedBodyPayload[3][0];
                $encodedRowPayload = $encodedRow['c'] ?? $encodedRow;
                $encodedCells = $encodedRowPayload[1];
                $editedPayload = $encodedCells[0]['c'] ?? $encodedCells[0];

                $t->same($firstColumnAlign, $encodedTable['c'][2][0][0], "{$source} {$writer} writer preserves unchanged first column alignment sidecar");
                $t->same($firstColumnWidth, $encodedTable['c'][2][0][1], "{$source} {$writer} writer preserves unchanged first column width sidecar");
                $t->same($secondColumnAlign, $encodedTable['c'][2][1][0], "{$source} {$writer} writer preserves unchanged second column alignment sidecar");
                $t->same($secondColumnWidth, $encodedTable['c'][2][1][1], "{$source} {$writer} writer preserves unchanged second column width sidecar");
                $t->same($rowHeadColumns, $encodedBodyPayload[1], "{$source} {$writer} writer preserves unchanged row-head sidecar");
                $t->same('Cell', $encodedCells[0]['t'] ?? null, "{$source} {$writer} writer regenerates current edited spanning cell constructor");
                $t->same(false, array_key_exists('reviewQueue', $encodedCells[0]), "{$source} {$writer} writer drops stale edited spanning cell wrapper sidecar");
                $t->same(false, array_key_exists('sourceOrdinal', $encodedCells[0]), "{$source} {$writer} writer drops stale edited spanning cell wrapper ordinal");
                $t->same([
                    'span-edited',
                    ['source-cell', 'edited-span'],
                    [
                        ['data-state', 'edited'],
                        ['data-review', 'span-sidecar'],
                    ],
                ], $editedPayload[0], "{$source} {$writer} writer regenerates edited spanning cell Attr tuple");
                $t->same(false, array_key_exists('reviewQueue', $editedPayload[0]), "{$source} {$writer} writer drops stale edited spanning cell Attr sidecar");
                $t->same($sourceCellAlign, $editedPayload[1], "{$source} {$writer} writer preserves unchanged spanning cell alignment helper sidecar");
                $t->same(['t' => 'RowSpan', 'c' => 4], $editedPayload[2], "{$source} {$writer} writer regenerates edited RowSpan helper");
                $t->same(false, array_key_exists('reviewQueue', $editedPayload[2]), "{$source} {$writer} writer drops stale RowSpan sidecar");
                $t->same(false, array_key_exists('sourceOrdinal', $editedPayload[2]), "{$source} {$writer} writer drops stale RowSpan ordinal");
                $t->same(['t' => 'ColSpan', 'c' => 2], $editedPayload[3], "{$source} {$writer} writer regenerates edited ColSpan helper");
                $t->same(false, array_key_exists('reviewQueue', $editedPayload[3]), "{$source} {$writer} writer drops stale ColSpan sidecar");
                $t->same(false, array_key_exists('sourceOrdinal', $editedPayload[3]), "{$source} {$writer} writer drops stale ColSpan ordinal");
                $t->same($neighborCell, $encodedCells[1], "{$source} {$writer} writer preserves neighboring cell sidecars");
            }
        }
    },
    'preserves table section and body native payloads when rebuilding table wrappers' => static function (TestRunner $t): void {
        $cellNative = [
            't' => 'Cell',
            'c' => [
                ['cell-id', [], []],
                ['t' => 'AlignCenter', 'c' => []],
                ['t' => 'RowSpan', 'c' => [1]],
                ['t' => 'ColSpan', 'c' => [1]],
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Metric'],
                    ]],
                ],
            ],
            'reviewQueue' => 'cell-source',
            'sourceOrdinal' => 94,
        ];
        $rowNative = [
            't' => 'Row',
            'c' => [
                ['row-id', [], []],
                [$cellNative],
            ],
            'reviewQueue' => 'row-source',
            'sourceOrdinal' => 93,
        ];
        $headNative = [
            't' => 'TableHead',
            'c' => [
                ['head-id', [], []],
                [$rowNative],
            ],
            'reviewQueue' => 'head-source',
            'sourceOrdinal' => 91,
        ];
        $bodyNative = [
            't' => 'TableBody',
            'c' => [
                ['body-id', [], []],
                ['t' => 'RowHeadColumns', 'c' => [1]],
                [],
                [$rowNative],
            ],
            'reviewQueue' => 'body-source',
            'sourceOrdinal' => 92,
        ];
        $footNative = [
            't' => 'TableFoot',
            'c' => [
                ['foot-id', [], []],
                [$rowNative],
            ],
            'reviewQueue' => 'foot-source',
            'sourceOrdinal' => 95,
        ];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['source-table', [], []],
                ['t' => 'Caption', 'c' => [null, []]],
                [[['t' => 'AlignCenter', 'c' => []], ['t' => 'ColWidthDefault']]],
                $headNative,
                [$bodyNative],
                $footNative,
            ],
            'reviewQueue' => 'table-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $sections = [];
            foreach ($table->children as $child) {
                $sections[$child->type] = $child;
            }
            $head = $sections['table_head'];
            $body = $sections['table_body'];
            $foot = $sections['table_foot'];

            $t->same($headNative, $head->attr('native'), "{$source} reader preserves table head native payload");
            $t->same($bodyNative, $body->attr('native'), "{$source} reader preserves table body native payload");
            $t->same($footNative, $foot->attr('native'), "{$source} reader preserves table foot native payload");

            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('table', array_replace($table->attrs, ['id' => 'rebuilt-table']), $table->children),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];

                $t->same('rebuilt-table', $encodedTable['c'][0][0], "{$source} {$writer} writer regenerates edited table attr");
                $t->same(false, array_key_exists('reviewQueue', $encodedTable), "{$source} {$writer} writer drops stale table sidecar");
                $t->same($headNative, $encodedTable['c'][3], "{$source} {$writer} writer preserves table head helper native payload");
                $t->same($bodyNative, $encodedTable['c'][4][0], "{$source} {$writer} writer preserves table body helper native payload");
                $t->same($footNative, $encodedTable['c'][5], "{$source} {$writer} writer preserves table foot helper native payload");
            }

            $editedBody = new AstNode('table_body', array_replace($body->attrs, ['rowHeadColumns' => 2]), $body->children);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('table', array_replace($table->attrs, ['id' => 'edited-body-table']), [$head, $editedBody, $foot]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];
                $editedBody = $encodedTable['c'][4][0];
                $editedBodyPayload = $editedBody['c'] ?? $editedBody;

                $t->same('edited-body-table', $encodedTable['c'][0][0], "{$source} {$writer} edited body keeps edited table attr");
                $t->same(2, $editedBodyPayload[1]['c'], "{$source} {$writer} edited body regenerates row head columns");
                $t->same('TableBody', $editedBody['t'] ?? null, "{$source} {$writer} edited body keeps current TableBody constructor");
                $t->same(false, array_key_exists('reviewQueue', $editedBody), "{$source} {$writer} edited body drops stale body sidecar");
                $t->same($headNative, $encodedTable['c'][3], "{$source} {$writer} edited body preserves unchanged table head helper native payload");
                $t->same($footNative, $encodedTable['c'][5], "{$source} {$writer} edited body preserves unchanged table foot helper native payload");
            }
        }
    },
    'preserves table caption native payloads until caption text is edited' => static function (TestRunner $t): void {
        $shortCaptionNative = [
            't' => 'ShortCaption',
            'c' => [[
                ['t' => 'Str', 'c' => 'Short'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'queue'],
            ]],
            'reviewQueue' => 'short-caption-source',
            'sourceOrdinal' => 104,
        ];
        $shortMaybeNative = [
            't' => 'Just',
            'c' => $shortCaptionNative,
            'reviewQueue' => 'short-maybe-source',
            'sourceOrdinal' => 103,
        ];
        $longCaptionNative = [
            't' => 'Plain',
            'c' => [
                ['t' => 'Str', 'c' => 'Source'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'caption'],
            ],
            'reviewQueue' => 'long-caption-source',
            'sourceOrdinal' => 102,
        ];
        $captionNative = [
            't' => 'Caption',
            'c' => [
                $shortMaybeNative,
                [$longCaptionNative],
            ],
            'reviewQueue' => 'caption-source',
            'sourceOrdinal' => 101,
        ];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['caption-edge-table', [], []],
                $captionNative,
                [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => 0],
                        [],
                        [],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
            'reviewQueue' => 'table-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];
        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $captionBlocks = $table->attr('captionBlocks');

            $t->same($captionNative, $table->attr('captionNative'), "{$source} reader preserves table caption native payload");
            $t->same($shortMaybeNative, $table->attr('shortCaptionMaybeNative'), "{$source} reader preserves short maybe payload");
            $t->same($shortCaptionNative, $table->attr('shortCaptionNative'), "{$source} reader preserves short caption payload");
            $t->same($longCaptionNative, $captionBlocks[0]->attr('native'), "{$source} reader preserves long caption block payload");

            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('table', array_replace($table->attrs, ['id' => 'rebuilt-caption-table']), $table->children),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];

                $t->same('rebuilt-caption-table', $encodedTable['c'][0][0], "{$source} {$writer} writer regenerates edited table attr");
                $t->same(false, array_key_exists('reviewQueue', $encodedTable), "{$source} {$writer} writer drops stale table sidecar");
                $t->same($captionNative, $encodedTable['c'][1], "{$source} {$writer} writer preserves unchanged table caption payload");
            }

            $editedLongTable = new AstNode('table', array_replace($table->attrs, [
                'caption' => 'Edited caption',
                'captionBlocks' => [new AstNode('plain', [], [
                    new AstNode('text', ['text' => 'Edited']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'caption']),
                ])],
            ]), $table->children);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [$editedLongTable])),
                'native' => json_decode((new NativeWriter())->write(new AstNode('document', $document->attrs, [$editedLongTable])), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedCaption = $encoded['blocks'][0]['c'][1];

                $t->same('Caption', $encodedCaption['t'], "{$source} {$writer} edited long caption keeps constructor");
                $t->same(false, array_key_exists('reviewQueue', $encodedCaption), "{$source} {$writer} edited long caption drops stale caption sidecar");
                $t->same('short-maybe-source', $encodedCaption['c'][0]['reviewQueue'], "{$source} {$writer} edited long caption preserves unchanged short maybe sidecar");
                $t->same('short-caption-source', $encodedCaption['c'][0]['c']['reviewQueue'], "{$source} {$writer} edited long caption preserves unchanged short caption sidecar");
                $t->same('Edited', $encodedCaption['c'][1][0]['c'][0]['c'], "{$source} {$writer} edited long caption regenerates text");
                $t->same(false, array_key_exists('reviewQueue', $encodedCaption['c'][1][0]), "{$source} {$writer} edited long caption drops stale long block sidecar");
            }

            $editedShortTable = new AstNode('table', array_replace($table->attrs, [
                'shortCaption' => 'Edited queue',
                'shortCaptionInlines' => [
                    new AstNode('text', ['text' => 'Edited']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'queue']),
                ],
            ]), $table->children);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [$editedShortTable])),
                'native' => json_decode((new NativeWriter())->write(new AstNode('document', $document->attrs, [$editedShortTable])), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedCaption = $encoded['blocks'][0]['c'][1];

                $t->same('Caption', $encodedCaption['t'], "{$source} {$writer} edited short caption keeps constructor");
                $t->same(false, array_key_exists('reviewQueue', $encodedCaption), "{$source} {$writer} edited short caption drops stale caption sidecar");
                $t->same('Just', $encodedCaption['c'][0]['t'], "{$source} {$writer} edited short caption keeps maybe constructor");
                $t->same(false, array_key_exists('reviewQueue', $encodedCaption['c'][0]), "{$source} {$writer} edited short caption drops stale short maybe sidecar");
                $t->same('ShortCaption', $encodedCaption['c'][0]['c']['t'], "{$source} {$writer} edited short caption keeps helper constructor");
                $t->same(false, array_key_exists('reviewQueue', $encodedCaption['c'][0]['c']), "{$source} {$writer} edited short caption drops stale short caption sidecar");
                $t->same('Edited', $encodedCaption['c'][0]['c']['c'][0][0]['c'], "{$source} {$writer} edited short caption regenerates text");
                $t->same($longCaptionNative, $encodedCaption['c'][1][0], "{$source} {$writer} edited short caption preserves unchanged long block payload");
            }
        }
    },
    'preserves table body head row native payloads when rebuilding table wrappers' => static function (TestRunner $t): void {
        $headCellNative = [
            't' => 'Cell',
            'c' => [
                ['head-cell-id', [], []],
                ['t' => 'AlignCenter', 'c' => []],
                ['t' => 'RowSpan', 'c' => [1]],
                ['t' => 'ColSpan', 'c' => [1]],
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Head'],
                    ]],
                ],
            ],
            'reviewQueue' => 'head-cell-source',
        ];
        $headRowNative = [
            't' => 'Row',
            'c' => [
                ['head-row-id', [], []],
                [$headCellNative],
            ],
            'reviewQueue' => 'head-row-source',
        ];
        $bodyCellNative = [
            't' => 'Cell',
            'c' => [
                ['body-cell-id', [], []],
                ['t' => 'AlignLeft', 'c' => []],
                ['t' => 'RowSpan', 'c' => [1]],
                ['t' => 'ColSpan', 'c' => [1]],
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Body'],
                    ]],
                ],
            ],
            'reviewQueue' => 'body-cell-source',
        ];
        $bodyRowNative = [
            't' => 'Row',
            'c' => [
                ['body-row-id', [], []],
                [$bodyCellNative],
            ],
            'reviewQueue' => 'body-row-source',
        ];
        $bodyNative = [
            't' => 'TableBody',
            'c' => [
                ['body-with-head-rows', [], []],
                ['t' => 'RowHeadColumns', 'c' => [1]],
                [$headRowNative],
                [$bodyRowNative],
            ],
            'reviewQueue' => 'body-source',
        ];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['source-table', [], []],
                ['t' => 'Caption', 'c' => [null, []]],
                [[['t' => 'AlignLeft', 'c' => []], ['t' => 'ColWidthDefault']]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [$bodyNative],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
            'reviewQueue' => 'table-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $body = $table->children[0];
            $headRows = $body->attr('headRows');
            $headRow = $headRows[0];
            $headCell = $headRow->children[0];
            $bodyRow = $body->children[0];

            $t->same($bodyNative, $body->attr('native'), "{$source} reader preserves table body native payload");
            $t->same($headRowNative, $headRow->attr('native'), "{$source} reader preserves body head row native payload");
            $t->same($headCellNative, $headCell->attr('native'), "{$source} reader preserves body head cell native payload");

            $editedBody = new AstNode('table_body', array_replace($body->attrs, [
                'rowHeadColumns' => 2,
            ]), $body->children);
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('table', array_replace($table->attrs, ['id' => 'rebuilt-head-rows']), [$editedBody]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];
                $encodedBody = $encodedTable['c'][4][0];
                $encodedBodyPayload = $encodedBody['c'] ?? $encodedBody;

                $t->same('rebuilt-head-rows', $encodedTable['c'][0][0], "{$source} {$writer} writer regenerates edited table attr");
                $t->same(2, $encodedBodyPayload[1]['c'], "{$source} {$writer} writer regenerates edited row-head columns");
                $t->same(false, array_key_exists('reviewQueue', $encodedTable), "{$source} {$writer} writer drops stale table sidecar");
                $t->same(false, array_key_exists('reviewQueue', $encodedBody), "{$source} {$writer} writer drops stale body sidecar");
                $t->same($headRowNative, $encodedBodyPayload[2][0], "{$source} {$writer} writer preserves unchanged body head row payload");
                $t->same($bodyRowNative, $encodedBodyPayload[3][0], "{$source} {$writer} writer preserves unchanged body row payload");
            }

            $editedHeadCell = new AstNode('table_cell', $headCell->attrs, [
                new AstNode('text', ['text' => 'Edited']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'head']),
            ]);
            $editedHeadRow = new AstNode('table_row', $headRow->attrs, [$editedHeadCell]);
            $editedHeadBody = new AstNode('table_body', array_replace($body->attrs, [
                'headRows' => [$editedHeadRow],
            ]), [$bodyRow]);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('table', $table->attrs, [$editedHeadBody]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedBody = $encoded['blocks'][0]['c'][4][0];
                $encodedBodyPayload = $encodedBody['c'] ?? $encodedBody;
                $encodedHeadRow = $encodedBodyPayload[2][0];
                $encodedHeadRowPayload = $encodedHeadRow['c'] ?? $encodedHeadRow;
                $encodedHeadCell = $encodedHeadRowPayload[1][0];
                $encodedHeadCellPayload = $encodedHeadCell['c'] ?? $encodedHeadCell;

                $t->same('Edited', $encodedHeadCellPayload[4][0]['c'][0]['c'], "{$source} {$writer} writer regenerates edited body head cell content");
                $t->same(false, array_key_exists('reviewQueue', $encodedBody), "{$source} {$writer} writer drops stale edited body sidecar");
                $t->same(false, array_key_exists('reviewQueue', $encodedHeadRow), "{$source} {$writer} writer drops stale edited body head row sidecar");
                $t->same(false, array_key_exists('reviewQueue', $encodedHeadCell), "{$source} {$writer} writer drops stale edited body head cell sidecar");
                $t->same($bodyRowNative, $encodedBodyPayload[3][0], "{$source} {$writer} writer preserves unchanged body row payload after head edit");
            }
        }
    },
    'preserves table foot row and cell native payloads until foot cells are edited' => static function (TestRunner $t): void {
        $footCellNative = [
            't' => 'Cell',
            'c' => [
                ['foot-cell-id', ['total'], [['data-source', 'foot']]],
                ['t' => 'AlignRight', 'c' => []],
                ['t' => 'RowSpan', 'c' => [1]],
                ['t' => 'ColSpan', 'c' => [1]],
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Total'],
                    ]],
                ],
            ],
            'reviewQueue' => 'foot-cell-source',
            'sourceOrdinal' => 104,
        ];
        $footRowNative = [
            't' => 'Row',
            'c' => [
                ['foot-row-id', ['summary'], [['data-row', 'foot']]],
                [$footCellNative],
            ],
            'reviewQueue' => 'foot-row-source',
            'sourceOrdinal' => 103,
        ];
        $footNative = [
            't' => 'TableFoot',
            'c' => [
                ['foot-section-id', ['tfoot'], [['data-section', 'foot']]],
                [$footRowNative],
            ],
            'reviewQueue' => 'foot-section-source',
            'sourceOrdinal' => 102,
        ];
        $bodyNative = [
            't' => 'TableBody',
            'c' => [
                ['', [], []],
                ['t' => 'RowHeadColumns', 'c' => [0]],
                [],
                [],
            ],
        ];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['source-foot-table', [], []],
                ['t' => 'Caption', 'c' => [null, []]],
                [[['t' => 'AlignRight', 'c' => []], ['t' => 'ColWidthDefault']]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [$bodyNative],
                $footNative,
            ],
            'reviewQueue' => 'table-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];
        $sectionByType = static function (AstNode $table, string $type): AstNode {
            foreach ($table->children as $child) {
                if ($child->type === $type) {
                    return $child;
                }
            }

            throw new \RuntimeException("Missing {$type} section");
        };

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $body = $sectionByType($table, 'table_body');
            $foot = $sectionByType($table, 'table_foot');
            $footRow = $foot->children[0];
            $footCell = $footRow->children[0];

            $t->same($footNative, $foot->attr('native'), "{$source} reader preserves table foot native payload");
            $t->same($footRowNative, $footRow->attr('native'), "{$source} reader preserves table foot row native payload");
            $t->same($footCellNative, $footCell->attr('native'), "{$source} reader preserves table foot cell native payload");

            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('table', array_replace($table->attrs, ['id' => 'rebuilt-foot-table']), $table->children),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];
                $encodedFootPayload = $encodedTable['c'][5]['c'] ?? $encodedTable['c'][5];
                $encodedFootRow = $encodedFootPayload[1][0];
                $encodedFootRowPayload = $encodedFootRow['c'] ?? $encodedFootRow;
                $encodedFootCell = $encodedFootRowPayload[1][0];

                $t->same('rebuilt-foot-table', $encodedTable['c'][0][0], "{$source} {$writer} writer regenerates edited table attr");
                $t->same(false, array_key_exists('reviewQueue', $encodedTable), "{$source} {$writer} writer drops stale table sidecar");
                $t->same($footNative, $encodedTable['c'][5], "{$source} {$writer} writer preserves unchanged table foot native payload");
                $t->same($footRowNative, $encodedFootRow, "{$source} {$writer} writer preserves unchanged table foot row native payload");
                $t->same($footCellNative, $encodedFootCell, "{$source} {$writer} writer preserves unchanged table foot cell native payload");
            }

            $editedFootCell = new AstNode('table_cell', $footCell->attrs, [
                new AstNode('text', ['text' => 'Edited']),
            ]);
            $editedFootRow = new AstNode('table_row', $footRow->attrs, [$editedFootCell]);
            $editedFoot = new AstNode('table_foot', $foot->attrs, [$editedFootRow]);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('table', array_replace($table->attrs, ['id' => 'edited-foot-table']), [$body, $editedFoot]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];
                $encodedFoot = $encodedTable['c'][5];
                $encodedFootPayload = $encodedFoot['c'] ?? $encodedFoot;
                $encodedFootRow = $encodedFootPayload[1][0];
                $encodedFootRowPayload = $encodedFootRow['c'] ?? $encodedFootRow;
                $encodedFootCell = $encodedFootRowPayload[1][0];
                $encodedFootCellPayload = $encodedFootCell['c'] ?? $encodedFootCell;

                $t->same('edited-foot-table', $encodedTable['c'][0][0], "{$source} {$writer} edited foot keeps edited table attr");
                $t->same('Edited', $encodedFootCellPayload[4][0]['c'][0]['c'], "{$source} {$writer} edited foot cell regenerates content");
                $t->same(false, array_key_exists('reviewQueue', $encodedFoot), "{$source} {$writer} edited foot drops stale foot sidecar");
                $t->same(false, array_key_exists('reviewQueue', $encodedFootRow), "{$source} {$writer} edited foot drops stale row sidecar");
                $t->same(false, array_key_exists('reviewQueue', $encodedFootCell), "{$source} {$writer} edited foot drops stale cell sidecar");
            }
        }
    },
    'preserves current cite native payloads through pandoc json writer until edited' => static function (TestRunner $t): void {
        $citationRecord = [
            'reviewQueue' => 'wp-import',
            'citationHash' => 3001,
            'citationSuffix' => [
                ['t' => 'Str', 'c' => 'p.'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => '7'],
            ],
            'citationPrefix' => [
                ['t' => 'Str', 'c' => 'see'],
            ],
            'citationNoteNum' => 12,
            'citationMode' => ['t' => 'AuthorInText', 'c' => []],
            'citationId' => 'source-3001',
        ];
        $citeInline = ['t' => 'Cite', 'c' => [
            [$citationRecord],
            [
                ['t' => 'Str', 'c' => 'see'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => '@source-3001'],
                ['t' => 'Str', 'c' => ','],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'p.'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => '7'],
            ],
        ]];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [$citeInline]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $citation = $document->children[0]->children[0];
            $encoded = (new PandocJsonWriter())->toArray($document);

            $t->same('citation', $citation->type, "{$source} citation node");
            $t->same('Cite', $citation->attr('constructor'), "{$source} cite constructor");
            $t->same($citeInline, $citation->attr('native'), "{$source} cite native payload");
            $t->same($citationRecord, $citation->attr('citationNative'), "{$source} citation record native payload");
            $t->same('wp-import', $citation->attr('citationNative')['reviewQueue'], "{$source} inert citation record provenance");
            $t->same($citeInline, $encoded['blocks'][0]['c'][0], "{$source} unchanged cite payload is reused by JSON writer");
        }

        $citation = $documents['json']->children[0]->children[0];
        $editedCitation = new AstNode('citation', array_replace($citation->attrs, [
            'suffix' => [new AstNode('text', ['text' => 'p. 8'])],
            'text' => 'see @source-3001, p. 8',
        ]), [
            new AstNode('text', ['text' => 'see']),
            new AstNode('space'),
            new AstNode('text', ['text' => '@source-3001,']),
            new AstNode('space'),
            new AstNode('text', ['text' => 'p.']),
            new AstNode('space'),
            new AstNode('text', ['text' => '8']),
        ]);
        $editedPacket = (new PandocJsonWriter())->toArray(new AstNode('document', [], [
            new AstNode('paragraph', [], [$editedCitation]),
        ]));
        $editedCite = $editedPacket['blocks'][0]['c'][0];

        $t->same('Cite', $editedCite['t']);
        $t->same('source-3001', $editedCite['c'][0][0]['citationId']);
        $t->same('p. 8', $editedCite['c'][0][0]['citationSuffix'][0]['c']);
        $t->same(false, array_key_exists('reviewQueue', $editedCite['c'][0][0]), 'edited cite regenerates rather than reusing stale inert provenance');
        $t->same('see @source-3001, p. 8', implode('', array_map(static function (array $inline): string {
            return ($inline['t'] ?? '') === 'Space' ? ' ' : (string) ($inline['c'] ?? '');
        }, $editedCite['c'][1])));
    },
    'preserves citation record native payloads when rebuilding cite wrappers' => static function (TestRunner $t): void {
        $citationRecord = [
            'reviewQueue' => 'wp-import',
            'sourceOrdinal' => 17,
            'citationHash' => 3002,
            'citationSuffix' => [
                ['t' => 'Str', 'c' => 'p.'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => '9'],
            ],
            'citationPrefix' => [
                ['t' => 'Str', 'c' => 'see'],
            ],
            'citationNoteNum' => 3,
            'citationMode' => ['t' => 'NormalCitation', 'c' => []],
            'citationId' => 'source-3002',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Cite', 'c' => [
                        [$citationRecord],
                        [
                            ['t' => 'Str', 'c' => 'see'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '@source-3002,'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'p.'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '9'],
                        ],
                    ]],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $citation = $document->children[0]->children[0];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('citation_group', ['text' => '[see @source-3002, p. 9; @source-extra]'], [
                        $citation,
                        new AstNode('citation', [
                            'id' => 'source-extra',
                            'mode' => 'author_in_text',
                            'citationHash' => 55,
                        ]),
                    ]),
                ]),
            ]);
            $jsonPacket = (new PandocJsonWriter())->toArray($rebuilt);
            $nativePacket = json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR);

            foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $writer => $encoded) {
                $records = $encoded['blocks'][0]['c'][0]['c'][0];
                $t->same($citationRecord, $records[0], "{$source} {$writer} writer preserves moved citation record native payload");
                $t->same('wp-import', $records[0]['reviewQueue'], "{$source} {$writer} writer keeps inert citation record sidecar");
                $t->same('source-extra', $records[1]['citationId'], "{$source} {$writer} writer appends generated citation record");
                $t->same(false, array_key_exists('reviewQueue', $records[1]), "{$source} {$writer} writer does not invent sidecars");
            }

            $editedCitation = new AstNode('citation', array_replace($citation->attrs, [
                'suffix' => [
                    new AstNode('text', ['text' => 'p.']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => '10']),
                ],
                'text' => 'see @source-3002, p. 10',
            ]), $citation->children);
            $editedPacket = (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('citation_group', ['text' => '[see @source-3002, p. 10]'], [$editedCitation]),
                ]),
            ]));
            $editedRecord = $editedPacket['blocks'][0]['c'][0]['c'][0][0];

            $t->same('source-3002', $editedRecord['citationId'], "{$source} edited citation keeps id");
            $t->same('10', $editedRecord['citationSuffix'][2]['c'], "{$source} edited citation regenerates suffix");
            $t->same(false, array_key_exists('reviewQueue', $editedRecord), "{$source} edited citation drops stale record sidecar");
            $t->same(false, array_key_exists('sourceOrdinal', $editedRecord), "{$source} edited citation drops stale record ordinal");
        }
    },
    'regenerates edited citation note locator payloads instead of stale sidecars' => static function (TestRunner $t): void {
        $citationRecord = [
            'reviewQueue' => 'locator-note-source',
            'sourceOrdinal' => 42,
            'citationId' => 'source-locator-note',
            'citationPrefix' => [],
            'citationSuffix' => [],
            'citationMode' => ['t' => 'NormalCitation'],
            'citationNoteNum' => 5,
            'citationHash' => 800,
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Cite', 'c' => [
                        [$citationRecord],
                        [
                            ['t' => 'Str', 'c' => '@source-locator-note'],
                        ],
                    ]],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $citation = $document->children[0]->children[0];
            $editedCitation = new AstNode('citation', array_replace($citation->attrs, [
                'locator' => 'sec. 4',
                'citationNoteNum' => 6,
                'citationHash' => 801,
                'text' => '@source-locator-note, sec. 4',
            ]), [
                new AstNode('text', ['text' => '@source-locator-note,']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'sec.']),
                new AstNode('space'),
                new AstNode('text', ['text' => '4']),
            ]);
            $editedDocument = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [$editedCitation]),
            ]);

            $t->same(5, $citation->attr('citationNoteNum'), "{$source} reader preserves citation note number");
            $t->same($citationRecord, $citation->attr('citationNative'), "{$source} reader preserves citation record sidecar");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($editedDocument),
                'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedCite = $encoded['blocks'][0]['c'][0];
                $encodedRecord = $encodedCite['c'][0][0];

                $t->same('source-locator-note', $encodedRecord['citationId'], "{$source} {$writer} writer keeps edited citation id");
                $t->same([
                    ['t' => 'Str', 'c' => 'sec.'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => '4'],
                ], $encodedRecord['citationSuffix'], "{$source} {$writer} writer emits locator as citationSuffix");
                $t->same(6, $encodedRecord['citationNoteNum'], "{$source} {$writer} writer emits edited citation note number");
                $t->same(801, $encodedRecord['citationHash'], "{$source} {$writer} writer emits edited citation hash");
                $t->same(false, array_key_exists('reviewQueue', $encodedRecord), "{$source} {$writer} writer drops stale citation record sidecar");
                $t->same(false, array_key_exists('sourceOrdinal', $encodedRecord), "{$source} {$writer} writer drops stale citation record ordinal");
                $t->same([
                    ['t' => 'Str', 'c' => '@source-locator-note,'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'sec.'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => '4'],
                ], $encodedCite['c'][1], "{$source} {$writer} writer keeps edited cite source inlines");
            }
        }
    },
    'regenerates edited citation prefix suffix payloads instead of stale sidecars' => static function (TestRunner $t): void {
        $prefixInlines = [
            ['t' => 'Str', 'c' => 'see', 'reviewQueue' => 'prefix-word-source'],
            ['t' => 'Space'],
            ['t' => 'Emph', 'c' => [
                ['t' => 'Str', 'c' => 'archive', 'reviewQueue' => 'prefix-emphasis-text-source'],
            ], 'reviewQueue' => 'prefix-emphasis-source'],
        ];
        $suffixInlines = [
            ['t' => 'Str', 'c' => 'p.', 'reviewQueue' => 'suffix-label-source'],
            ['t' => 'Space'],
            ['t' => 'Code', 'c' => [
                ['source-suffix-code', ['locator'], [['data-source', 'citation-suffix']]],
                'A12',
            ], 'reviewQueue' => 'suffix-code-source'],
        ];
        $citationRecord = [
            'reviewQueue' => 'affix-record-source',
            'sourceOrdinal' => 43,
            'citationId' => 'source-affix',
            'citationPrefix' => $prefixInlines,
            'citationSuffix' => $suffixInlines,
            'citationMode' => ['t' => 'NormalCitation'],
            'citationNoteNum' => 7,
            'citationHash' => 900,
        ];
        $sourceInlines = [
            ['t' => 'Str', 'c' => 'see'],
            ['t' => 'Space'],
            ['t' => 'Emph', 'c' => [
                ['t' => 'Str', 'c' => 'archive'],
            ]],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '@source-affix,'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'p.'],
            ['t' => 'Space'],
            ['t' => 'Code', 'c' => [
                ['source-suffix-code', ['locator'], [['data-source', 'citation-suffix']]],
                'A12',
            ]],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Cite', 'c' => [
                        [$citationRecord],
                        $sourceInlines,
                    ]],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $citation = $document->children[0]->children[0];

            $t->same($citationRecord, $citation->attr('citationNative'), "{$source} reader preserves affix citation sidecar");
            $t->same($prefixInlines, $citation->attr('citationNative')['citationPrefix'], "{$source} reader preserves prefix inline sidecars");
            $t->same($suffixInlines, $citation->attr('citationNative')['citationSuffix'], "{$source} reader preserves suffix inline sidecars");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedRecord = $encoded['blocks'][0]['c'][0]['c'][0][0];

                $t->same($citationRecord, $encodedRecord, "{$source} {$writer} writer preserves unchanged citation affix sidecar");
                $t->same('prefix-word-source', $encodedRecord['citationPrefix'][0]['reviewQueue'], "{$source} {$writer} writer preserves unchanged prefix token sidecar");
                $t->same('prefix-emphasis-source', $encodedRecord['citationPrefix'][2]['reviewQueue'], "{$source} {$writer} writer preserves unchanged prefix wrapper sidecar");
                $t->same('suffix-code-source', $encodedRecord['citationSuffix'][2]['reviewQueue'], "{$source} {$writer} writer preserves unchanged suffix code sidecar");
            }

            $editedCitation = new AstNode('citation', array_replace($citation->attrs, [
                'prefix' => [
                    new AstNode('text', ['text' => 'cf.']),
                ],
                'suffix' => [
                    new AstNode('text', ['text' => 'ch.']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => '2']),
                ],
                'text' => 'cf. @source-affix, ch. 2',
            ]), [
                new AstNode('text', ['text' => 'cf.']),
                new AstNode('space'),
                new AstNode('text', ['text' => '@source-affix,']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'ch.']),
                new AstNode('space'),
                new AstNode('text', ['text' => '2']),
            ]);
            $editedDocument = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [$editedCitation]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($editedDocument),
                'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedCite = $encoded['blocks'][0]['c'][0];
                $encodedRecord = $encodedCite['c'][0][0];

                $t->same('source-affix', $encodedRecord['citationId'], "{$source} {$writer} writer keeps edited affix citation id");
                $t->same([['t' => 'Str', 'c' => 'cf.']], $encodedRecord['citationPrefix'], "{$source} {$writer} writer regenerates edited prefix");
                $t->same([
                    ['t' => 'Str', 'c' => 'ch.'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => '2'],
                ], $encodedRecord['citationSuffix'], "{$source} {$writer} writer regenerates edited suffix");
                $t->same(7, $encodedRecord['citationNoteNum'], "{$source} {$writer} writer preserves note number while regenerating affixes");
                $t->same(900, $encodedRecord['citationHash'], "{$source} {$writer} writer preserves citation hash while regenerating affixes");
                $t->same(false, array_key_exists('reviewQueue', $encodedRecord), "{$source} {$writer} writer drops stale citation record sidecar");
                $t->same(false, array_key_exists('sourceOrdinal', $encodedRecord), "{$source} {$writer} writer drops stale citation record ordinal");
                $t->same(false, array_key_exists('reviewQueue', $encodedRecord['citationPrefix'][0]), "{$source} {$writer} writer drops stale prefix sidecar");
                $t->same(false, array_key_exists('reviewQueue', $encodedRecord['citationSuffix'][0]), "{$source} {$writer} writer drops stale suffix sidecar");
                $t->same([
                    ['t' => 'Str', 'c' => 'cf.'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => '@source-affix,'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'ch.'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => '2'],
                ], $encodedCite['c'][1], "{$source} {$writer} writer keeps edited affix source inlines");
            }
        }
    },
    'preserves moved cite wrapper sidecars until citation records are edited' => static function (TestRunner $t): void {
        $sourceInlines = [
            ['t' => 'Str', 'c' => '[see'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '@smith1899;'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '@doe1901]'],
        ];
        $firstRecord = [
            'reviewQueue' => 'first-record-source',
            'citationId' => 'smith1899',
            'citationPrefix' => [
                ['t' => 'Str', 'c' => 'see'],
            ],
            'citationSuffix' => [],
            'citationMode' => ['t' => 'NormalCitation'],
            'citationNoteNum' => 1,
            'citationHash' => 1899,
        ];
        $secondRecord = [
            'reviewQueue' => 'second-record-source',
            'citationId' => 'doe1901',
            'citationPrefix' => [],
            'citationSuffix' => [],
            'citationMode' => ['t' => 'AuthorInText'],
            'citationNoteNum' => 2,
            'citationHash' => 1901,
        ];
        $citeInline = [
            't' => 'Cite',
            'c' => [
                [$firstRecord, $secondRecord],
                $sourceInlines,
            ],
            'reviewQueue' => 'cite-wrapper-source',
            'sourceOrdinal' => 88,
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Original'],
                    ['t' => 'Space'],
                    $citeInline,
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $clusters = array_values(array_filter(
                $document->children[0]->children,
                static fn (AstNode $child): bool => $child->type === 'citation_group'
            ));
            $t->same(1, count($clusters), "{$source} contains one citation cluster");
            $cluster = $clusters[0];
            $moved = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('text', ['text' => 'Moved']),
                    new AstNode('space'),
                    $cluster,
                ]),
            ]);
            $editedFirstCitation = new AstNode('citation', array_replace($cluster->children[0]->attrs, [
                'citationHash' => 1999,
            ]), $cluster->children[0]->children);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('citation_group', $cluster->attrs, [
                        $editedFirstCitation,
                        $cluster->children[1],
                    ]),
                ]),
            ]);

            $t->same('citation_group', $cluster->type, "{$source} citation cluster node");
            $t->same($citeInline, $cluster->attr('native'), "{$source} records cite wrapper native sidecar payload");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($moved),
                'native' => json_decode((new NativeWriter())->write($moved), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedCite = $encoded['blocks'][0]['c'][2];

                $t->same($citeInline, $encodedCite, "{$source} {$writer} writer preserves moved current cite wrapper sidecars");
                $t->same('cite-wrapper-source', $encodedCite['reviewQueue'], "{$source} {$writer} writer keeps cite wrapper provenance");
                $t->same(88, $encodedCite['sourceOrdinal'], "{$source} {$writer} writer keeps cite wrapper ordinal");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedCite = $encoded['blocks'][0]['c'][0];
                $encodedRecords = $encodedCite['c'][0];

                $t->same('Cite', $encodedCite['t'], "{$source} {$writer} edited cluster regenerates cite wrapper");
                $t->same(false, array_key_exists('reviewQueue', $encodedCite), "{$source} {$writer} edited cluster drops stale cite wrapper provenance");
                $t->same(false, array_key_exists('sourceOrdinal', $encodedCite), "{$source} {$writer} edited cluster drops stale cite wrapper ordinal");
                $t->same(1999, $encodedRecords[0]['citationHash'], "{$source} {$writer} edited cluster emits edited citation hash");
                $t->same(false, array_key_exists('reviewQueue', $encodedRecords[0]), "{$source} {$writer} edited cluster drops stale first-record provenance");
                $t->same('second-record-source', $encodedRecords[1]['reviewQueue'], "{$source} {$writer} edited cluster preserves unchanged second-record provenance");
            }
        }
    },
    'preserves citation mode hash note and id payload sidecars across metadata edits' => static function (TestRunner $t): void {
        $authorRecord = [
            'reviewQueue' => 'author-citation-source',
            'sourceOrdinal' => 101,
            'citationHash' => 4004,
            'citationSuffix' => [
                ['t' => 'Str', 'c' => 'sec.'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => '2'],
            ],
            'citationPrefix' => [],
            'citationNoteNum' => 4,
            'citationMode' => ['t' => 'AuthorInText', 'c' => []],
            'citationId' => 'author-source',
        ];
        $noteRecord = [
            'reviewQueue' => 'note-citation-source',
            'sourceOrdinal' => 102,
            'citationHash' => 9009,
            'citationSuffix' => [],
            'citationPrefix' => [
                ['t' => 'Str', 'c' => 'see'],
            ],
            'citationNoteNum' => 9,
            'citationMode' => ['t' => 'NormalCitation', 'c' => []],
            'citationId' => 'note-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Cite', 'c' => [
                        [$authorRecord, $noteRecord],
                        [
                            ['t' => 'Str', 'c' => '@author-source,'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'sec.'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '2;'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'see'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '@note-source'],
                        ],
                    ]],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $cluster = $document->children[0]->children[0];
            $authorCitation = $cluster->children[0];
            $noteCitation = $cluster->children[1];
            $editedAuthorCitation = new AstNode('citation', array_replace($authorCitation->attrs, [
                'id' => 'note-style-author-source',
                'text' => '@note-style-author-source, sec. 2',
                'mode' => 'normal',
                'citationModeConstructor' => 'NormalCitation',
                'citationModeNative' => ['t' => 'NormalCitation'],
                'citationNoteNum' => 5,
                'citationHash' => 5005,
            ]), [
                new AstNode('text', ['text' => '@note-style-author-source, sec. 2']),
            ]);
            $editedDocument = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', $document->children[0]->attrs, [
                    new AstNode('citation_group', $cluster->attrs, [
                        $editedAuthorCitation,
                        $noteCitation,
                    ]),
                ]),
            ]);

            $t->same('citation_group', $cluster->type, "{$source} citation group node");
            $t->same('author-source', $authorCitation->attr('id'), "{$source} author citation id");
            $t->same('author_in_text', $authorCitation->attr('mode'), "{$source} author citation mode");
            $t->same('AuthorInText', $authorCitation->attr('citationModeConstructor'), "{$source} author citation mode constructor");
            $t->same(4, $authorCitation->attr('citationNoteNum'), "{$source} author citation note number");
            $t->same(4004, $authorCitation->attr('citationHash'), "{$source} author citation hash");
            $t->same('author-citation-source', $authorCitation->attr('citationNative')['reviewQueue'], "{$source} author citation sidecar");
            $t->same(101, $authorCitation->attr('citationNative')['sourceOrdinal'], "{$source} author citation ordinal");
            $t->same('normal', $noteCitation->attr('mode'), "{$source} note citation mode");
            $t->same(9, $noteCitation->attr('citationNoteNum'), "{$source} note citation note number");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($editedDocument),
                'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $records = $encoded['blocks'][0]['c'][0]['c'][0];
                $editedRecord = $records[0];
                $unchangedRecord = $records[1];

                $t->same('note-style-author-source', $editedRecord['citationId'], "{$source} {$writer} edited citation id");
                $t->same('NormalCitation', $editedRecord['citationMode']['t'], "{$source} {$writer} edited citation mode");
                $t->same(5, $editedRecord['citationNoteNum'], "{$source} {$writer} edited citation note number");
                $t->same(5005, $editedRecord['citationHash'], "{$source} {$writer} edited citation hash");
                $t->same(false, array_key_exists('reviewQueue', $editedRecord), "{$source} {$writer} edited citation drops stale sidecar");
                $t->same(false, array_key_exists('sourceOrdinal', $editedRecord), "{$source} {$writer} edited citation drops stale ordinal");
                $t->same($noteRecord, $unchangedRecord, "{$source} {$writer} unchanged citation record sidecar");
                $t->same('note-citation-source', $unchangedRecord['reviewQueue'], "{$source} {$writer} unchanged citation queue");
                $t->same(102, $unchangedRecord['sourceOrdinal'], "{$source} {$writer} unchanged citation ordinal");
            }
        }
    },
    'regenerates nullary inline constructors with stale native content sidecars' => static function (TestRunner $t): void {
        $spaceInline = ['t' => 'Space', 'c' => ['stale'], 'reviewQueue' => 'space-source'];
        $softBreakInline = ['t' => 'SoftBreak', 'c' => 'stale', 'reviewQueue' => 'softbreak-source'];
        $lineBreakInline = ['t' => 'LineBreak', 'c' => 1, 'reviewQueue' => 'linebreak-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    $spaceInline,
                    $softBreakInline,
                    $lineBreakInline,
                ]],
            ],
        ];
        $expectedInlines = [
            ['t' => 'Space'],
            ['t' => 'SoftBreak'],
            ['t' => 'LineBreak'],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $nodes = $document->children[0]->children;
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
            $expectedJsonInlines = $source === 'native'
                ? [['t' => 'Str', 'c' => ' '], ['t' => 'SoftBreak'], ['t' => 'LineBreak']]
                : $expectedInlines;

            $t->same($source === 'native' ? ['text', 'softbreak', 'linebreak'] : ['space', 'softbreak', 'linebreak'], array_map(static fn (AstNode $node): string => $node->type, $nodes), "{$source} nullary inline node types");
            $t->same($source === 'native' ? [$spaceInline] : $spaceInline, $source === 'native' ? $nodes[0]->attr('nativeInlineParts') : $nodes[0]->attr('native'), "{$source} source space native sidecar");
            $t->same($softBreakInline, $nodes[1]->attr('native'), "{$source} source softbreak native sidecar");
            $t->same($lineBreakInline, $nodes[2]->attr('native'), "{$source} source linebreak native sidecar");
            $t->same($expectedJsonInlines, $jsonPacket['blocks'][0]['c'], "{$source} JSON writer regenerates nullary constructors");
            $t->same($expectedInlines, $nativePacket['blocks'][0]['c'], "{$source} native writer regenerates nullary constructors");
        }
    },
    'regenerates nested nullary block constructors with stale native content sidecars' => static function (TestRunner $t): void {
        $quoteRule = ['t' => 'HorizontalRule', 'c' => ['stale'], 'reviewQueue' => 'quote-rule-source'];
        $quoteNull = ['t' => 'Null', 'c' => ['stale'], 'reviewQueue' => 'quote-null-source'];
        $quoteValidRule = ['t' => 'HorizontalRule', 'reviewQueue' => 'quote-valid-rule-source'];
        $divRule = ['t' => 'HorizontalRule', 'c' => [], 'reviewQueue' => 'div-rule-source'];
        $divNull = ['t' => 'Null', 'c' => 'stale', 'reviewQueue' => 'div-null-source'];
        $divValidNull = ['t' => 'Null', 'reviewQueue' => 'div-valid-null-source'];
        $noteRule = ['t' => 'HorizontalRule', 'c' => [['t' => 'Str', 'c' => 'stale']], 'reviewQueue' => 'note-rule-source'];
        $noteNull = ['t' => 'Null', 'c' => [], 'reviewQueue' => 'note-null-source'];
        $noteValidRule = ['t' => 'HorizontalRule', 'reviewQueue' => 'note-valid-rule-source'];
        $captionRule = ['t' => 'HorizontalRule', 'c' => ['caption'], 'reviewQueue' => 'caption-rule-source'];
        $captionNull = ['t' => 'Null', 'c' => ['caption'], 'reviewQueue' => 'caption-null-source'];
        $captionValidNull = ['t' => 'Null', 'reviewQueue' => 'caption-valid-null-source'];
        $cellRule = ['t' => 'HorizontalRule', 'c' => ['cell'], 'reviewQueue' => 'cell-rule-source'];
        $cellNull = ['t' => 'Null', 'c' => ['cell'], 'reviewQueue' => 'cell-null-source'];
        $cellValidRule = ['t' => 'HorizontalRule', 'reviewQueue' => 'cell-valid-rule-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'BlockQuote', 'c' => [
                    $quoteRule,
                    $quoteNull,
                    $quoteValidRule,
                ], 'reviewQueue' => 'quote-source'],
                ['t' => 'Div', 'c' => [
                    ['nested-nullary', [], []],
                    [
                        $divRule,
                        $divNull,
                        $divValidNull,
                    ],
                ], 'reviewQueue' => 'div-source'],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'footnote'],
                    ['t' => 'Space'],
                    ['t' => 'Note', 'c' => [
                        $noteRule,
                        $noteNull,
                        $noteValidRule,
                    ], 'reviewQueue' => 'note-source'],
                ], 'reviewQueue' => 'para-source'],
                ['t' => 'Table', 'c' => [
                    ['nested-table', [], []],
                    ['t' => 'Caption', 'c' => [
                        ['t' => 'Nothing'],
                        [
                            $captionRule,
                            $captionNull,
                            $captionValidNull,
                        ],
                    ], 'reviewQueue' => 'caption-source'],
                    [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                    ['t' => 'TableHead', 'c' => [['', [], []], []]],
                    [
                        ['t' => 'TableBody', 'c' => [
                            ['', [], []],
                            ['t' => 'RowHeadColumns', 'c' => 0],
                            [],
                            [
                                ['t' => 'Row', 'c' => [
                                    ['', [], []],
                                    [
                                        ['t' => 'Cell', 'c' => [
                                            ['', [], []],
                                            ['t' => 'AlignDefault'],
                                            ['t' => 'RowSpan', 'c' => 1],
                                            ['t' => 'ColSpan', 'c' => 1],
                                            [
                                                $cellRule,
                                                $cellNull,
                                                $cellValidRule,
                                            ],
                                        ], 'reviewQueue' => 'cell-source'],
                                    ],
                                ], 'reviewQueue' => 'row-source'],
                            ],
                        ], 'reviewQueue' => 'body-source'],
                    ],
                    ['t' => 'TableFoot', 'c' => [['', [], []], []]],
                ], 'reviewQueue' => 'table-source'],
            ],
        ];
        $expectedQuoteBlocks = [
            ['t' => 'HorizontalRule'],
            ['t' => 'Null'],
            $quoteValidRule,
        ];
        $expectedDivBlocks = [
            ['t' => 'HorizontalRule'],
            ['t' => 'Null'],
            $divValidNull,
        ];
        $expectedNoteBlocks = [
            ['t' => 'HorizontalRule'],
            ['t' => 'Null'],
            $noteValidRule,
        ];
        $expectedCaptionBlocks = [
            ['t' => 'HorizontalRule'],
            ['t' => 'Null'],
            $captionValidNull,
        ];
        $expectedCellBlocks = [
            ['t' => 'HorizontalRule'],
            ['t' => 'Null'],
            $cellValidRule,
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];
        $childByType = static function (AstNode $parent, string $type): AstNode {
            foreach ($parent->children as $child) {
                if ($child->type === $type) {
                    return $child;
                }
            }

            throw new \RuntimeException("Missing {$type} child");
        };
        $inlineByConstructor = static function (array $inlines, string $constructor): array {
            foreach ($inlines as $inline) {
                if (is_array($inline) && ($inline['t'] ?? null) === $constructor) {
                    return $inline;
                }
            }

            throw new \RuntimeException("Missing {$constructor} inline constructor");
        };
        $taggedPayload = static function (array $value): array {
            $content = $value['c'] ?? null;

            return is_array($content) && array_is_list($content) ? $content : $value;
        };
        $tableCellBlocks = static function (array $encoded) use ($taggedPayload): array {
            $table = $encoded['blocks'][3];
            $body = $taggedPayload($table['c'][4][0]);
            $row = $taggedPayload($body[3][0]);
            $cell = $taggedPayload($row[1][0]);

            return $cell[4];
        };

        foreach ($documents as $source => $document) {
            $quote = $document->children[0];
            $div = $document->children[1];
            $paragraph = $document->children[2];
            $note = $childByType($paragraph, 'note');
            $table = $document->children[3];
            $body = $childByType($table, 'table_body');
            $cell = $body->children[0]->children[0];

            $t->same($quoteRule, $quote->children[0]->attr('native'), "{$source} records stale blockquote rule source");
            $t->same($quoteNull, $quote->children[1]->attr('native'), "{$source} records stale blockquote null source");
            $t->same($divRule, $div->children[0]->attr('native'), "{$source} records stale div rule source");
            $t->same($divNull, $div->children[1]->attr('native'), "{$source} records stale div null source");
            $t->same($noteRule, $note->children[0]->attr('native'), "{$source} records stale note rule source");
            $t->same($noteNull, $note->children[1]->attr('native'), "{$source} records stale note null source");
            $t->same($cellRule, $cell->children[0]->attr('native'), "{$source} records stale table cell rule source");
            $t->same($cellNull, $cell->children[1]->attr('native'), "{$source} records stale table cell null source");

            foreach ([
                'JSON writer' => (new PandocJsonWriter())->toArray($document),
                'native writer' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedNote = $inlineByConstructor($encoded['blocks'][2]['c'], 'Note');

                $t->same($expectedQuoteBlocks, $encoded['blocks'][0]['c'], "{$source} {$writer} regenerates blockquote nullary children");
                $t->same($expectedDivBlocks, $encoded['blocks'][1]['c'][1], "{$source} {$writer} regenerates div nullary children");
                $t->same($expectedNoteBlocks, $encodedNote['c'], "{$source} {$writer} regenerates note nullary children");
                $t->same($expectedCaptionBlocks, $encoded['blocks'][3]['c'][1]['c'][1], "{$source} {$writer} regenerates table caption nullary children");
                $t->same($expectedCellBlocks, $tableCellBlocks($encoded), "{$source} {$writer} regenerates table cell nullary children");
            }
        }
    },
    'preserves current structural inline native payloads through pandoc json writer until edited' => static function (TestRunner $t): void {
        $structuralInlines = [
            ['t' => 'Emph', 'c' => [['t' => 'Str', 'c' => 'emph']], 'reviewQueue' => 'emph-source'],
            ['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'strong']], 'reviewQueue' => 'strong-source'],
            ['t' => 'Underline', 'c' => [['t' => 'Str', 'c' => 'underline']], 'reviewQueue' => 'underline-source'],
            ['t' => 'Strikeout', 'c' => [['t' => 'Str', 'c' => 'strikeout']], 'reviewQueue' => 'strikeout-source'],
            ['t' => 'Superscript', 'c' => [['t' => 'Str', 'c' => 'superscript']], 'reviewQueue' => 'superscript-source'],
            ['t' => 'Subscript', 'c' => [['t' => 'Str', 'c' => 'subscript']], 'reviewQueue' => 'subscript-source'],
            ['t' => 'SmallCaps', 'c' => [['t' => 'Str', 'c' => 'smallcaps']], 'reviewQueue' => 'smallcaps-source'],
            ['t' => 'Quoted', 'c' => [['t' => 'SingleQuote'], [['t' => 'Str', 'c' => 'quoted']]], 'reviewQueue' => 'quoted-source'],
            ['t' => 'Span', 'c' => [
                ['source-span', ['review'], [['data-source', 'first'], ['data-source', 'second']]],
                [['t' => 'Str', 'c' => 'span']],
            ], 'reviewQueue' => 'span-source', 'sourceOrdinal' => 91],
            ['t' => 'Note', 'c' => [
                ['t' => 'Para', 'c' => [['t' => 'Str', 'c' => 'note']]],
            ], 'reviewQueue' => 'note-source'],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => $structuralInlines],
            ],
        ];
        $expectedTypes = [
            'emph',
            'strong',
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'small_caps',
            'quoted',
            'span',
            'note',
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $nodes = $document->children[0]->children;
            $standalonePacket = (new PandocJsonWriter())->toArray(new AstNode('document', [], [
                new AstNode('paragraph', [], $nodes),
            ]));

            $t->same($expectedTypes, array_map(static fn (AstNode $node): string => $node->type, $nodes), "{$source} structural inline node types");
            foreach ($nodes as $index => $node) {
                $t->same($structuralInlines[$index], $node->attr('native'), "{$source} structural inline native payload {$index}");
            }
            $t->same($structuralInlines, $standalonePacket['blocks'][0]['c'], "{$source} standalone structural inline payloads are reused");
        }

        $emph = $documents['json']->children[0]->children[0];
        $editedDocument = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('emph', $emph->attrs, [
                    new AstNode('text', ['text' => 'edited emph']),
                ]),
            ]),
        ]);
        $inlineText = static function (array $inlines): string {
            $text = '';
            foreach ($inlines as $inline) {
                $text .= match ($inline['t'] ?? '') {
                    'Str', 'Code', 'Math' => (string) ($inline['c'] ?? ''),
                    'Space', 'SoftBreak', 'LineBreak' => ' ',
                    default => '',
                };
            }

            return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        };

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($editedDocument),
            'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
        ] as $writer => $editedPacket) {
            $editedEmph = $editedPacket['blocks'][0]['c'][0];

            $t->same('Emph', $editedEmph['t'], "{$writer} edited structural inline constructor");
            $t->same('edited emph', $inlineText($editedEmph['c']), "{$writer} edited structural inline text");
            $t->same(false, array_key_exists('reviewQueue', $editedEmph), "{$writer} edited structural inline drops stale provenance");
        }
    },
    'preserves markdown note labels through json and native note sidecars' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Tagged note[^editor-note] and inline note.^[Inline audit note.]',
            '',
            '[^editor-note]: Labelled source note.',
        ]));
        $collectNotes = static function (AstNode $node) use (&$collectNotes): array {
            $notes = $node->type === 'note' ? [$node] : [];
            foreach ($node->children as $child) {
                array_push($notes, ...$collectNotes($child));
            }

            return $notes;
        };

        $sourceNotes = $collectNotes($document);
        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $jsonNotes = array_values(array_filter(
            $jsonPacket['blocks'][0]['c'],
            static fn (mixed $inline): bool => is_array($inline) && ($inline['t'] ?? null) === 'Note'
        ));
        $nativeNotes = array_values(array_filter(
            $nativePacket['blocks'][0]['c'],
            static fn (mixed $inline): bool => is_array($inline) && ($inline['t'] ?? null) === 'Note'
        ));

        $t->same('editor-note', $sourceNotes[0]->attr('label'));
        $t->same(null, $sourceNotes[1]->attr('label'));
        $t->same('editor-note', $jsonNotes[0]['noteLabel'] ?? null);
        $t->same(false, array_key_exists('noteLabel', $jsonNotes[1]));
        $t->same('editor-note', $nativeNotes[0]['noteLabel'] ?? null);
        $t->same(false, array_key_exists('noteLabel', $nativeNotes[1]));

        $roundTrips = [
            'json' => (new PandocJsonReader())->readPacket($jsonPacket),
            'native' => (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR)),
        ];
        foreach ($roundTrips as $source => $roundTrip) {
            $roundTripNotes = $collectNotes($roundTrip);

            $t->same('editor-note', $roundTripNotes[0]->attr('label'), "{$source} reader preserves note label sidecar");
            $t->same(null, $roundTripNotes[1]->attr('label'), "{$source} reader keeps inline note unlabelled");
        }
    },
    'preserves current tagged helper payload shapes through native writer after edits' => static function (TestRunner $t): void {
        $styleNative = ['t' => 'UpperAlpha', 'c' => []];
        $delimiterNative = ['t' => 'TwoParens', 'c' => []];
        $quoteTypeNative = ['t' => 'DoubleQuote', 'c' => []];
        $mathTypeNative = ['t' => 'DisplayMath', 'c' => []];
        $citationModeNative = ['t' => 'AuthorInText', 'c' => []];
        $tableAlignmentNative = ['t' => 'AlignCenter', 'c' => []];
        $columnWidthNative = ['t' => 'ColWidth', 'c' => [0.6]];
        $rowHeadColumnsNative = ['t' => 'RowHeadColumns', 'c' => [1]];
        $cellAlignmentNative = ['t' => 'AlignLeft', 'c' => []];
        $rowSpanNative = ['t' => 'RowSpan', 'c' => [2]];
        $colSpanNative = ['t' => 'ColSpan', 'c' => [3]];
        $citationRecord = [
            'citationId' => 'source-helper',
            'citationPrefix' => [],
            'citationSuffix' => [],
            'citationMode' => $citationModeNative,
            'citationNoteNum' => 0,
            'citationHash' => 99,
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'OrderedList', 'c' => [
                    [4, $styleNative, $delimiterNative],
                    [[['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Item']]]]],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Quoted', 'c' => [$quoteTypeNative, [['t' => 'Str', 'c' => 'quoted']]]],
                    ['t' => 'Space'],
                    ['t' => 'Math', 'c' => [$mathTypeNative, 'x + y']],
                    ['t' => 'Space'],
                    ['t' => 'Cite', 'c' => [[$citationRecord], [['t' => 'Str', 'c' => '@source-helper']]]],
                ]],
                ['t' => 'Table', 'c' => [
                    ['helper-payload-shapes', [], []],
                    ['t' => 'Caption', 'c' => [null, []]],
                    [[$tableAlignmentNative, $columnWidthNative]],
                    ['t' => 'TableHead', 'c' => [['', [], []], []]],
                    [
                        ['t' => 'TableBody', 'c' => [
                            ['', [], []],
                            $rowHeadColumnsNative,
                            [],
                            [
                                ['t' => 'Row', 'c' => [
                                    ['', [], []],
                                    [
                                        ['t' => 'Cell', 'c' => [
                                            ['', [], []],
                                            $cellAlignmentNative,
                                            $rowSpanNative,
                                            $colSpanNative,
                                            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Cell']]]],
                                        ]],
                                    ],
                                ]],
                            ],
                        ]],
                    ],
                    ['t' => 'TableFoot', 'c' => [['', [], []], []]],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $orderedList = $document->children[0];
            $paragraph = $document->children[1];
            $table = $document->children[2];
            $editedDocument = new AstNode('document', $document->attrs, [
                new AstNode('ordered_list', array_replace($orderedList->attrs, [
                    'start' => 5,
                ]), $orderedList->children),
                new AstNode('paragraph', $paragraph->attrs, [
                    ...$paragraph->children,
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'edited']),
                ]),
                new AstNode('table', array_replace($table->attrs, [
                    'id' => 'edited-helper-payload-shapes',
                ]), $table->children),
            ]);
            $nativePacket = json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR);
            $body = $nativePacket['blocks'][2]['c'][4][0];
            $bodyPayload = $body['c'] ?? $body;
            $row = $bodyPayload[3][0]['c'] ?? $bodyPayload[3][0];
            $cell = $row[1][0]['c'] ?? $row[1][0];

            $t->same(5, $nativePacket['blocks'][0]['c'][0][0], "{$source} native writer regenerates edited list start");
            $t->same($styleNative, $nativePacket['blocks'][0]['c'][0][1], "{$source} native writer list style native payload");
            $t->same($delimiterNative, $nativePacket['blocks'][0]['c'][0][2], "{$source} native writer list delimiter native payload");
            $t->same($quoteTypeNative, $nativePacket['blocks'][1]['c'][0]['c'][0], "{$source} native writer quote native payload");
            $t->same($mathTypeNative, $nativePacket['blocks'][1]['c'][2]['c'][0], "{$source} native writer math native payload");
            $t->same($citationModeNative, $nativePacket['blocks'][1]['c'][4]['c'][0][0]['citationMode'], "{$source} native writer citation mode native payload");
            $t->same('edited-helper-payload-shapes', $nativePacket['blocks'][2]['c'][0][0], "{$source} native writer regenerates edited table attr");
            $t->same($tableAlignmentNative, $nativePacket['blocks'][2]['c'][2][0][0], "{$source} native writer column alignment native payload");
            $t->same($columnWidthNative, $nativePacket['blocks'][2]['c'][2][0][1], "{$source} native writer column width native payload");
            $t->same($rowHeadColumnsNative, $bodyPayload[1], "{$source} native writer row-head native payload");
            $t->same($cellAlignmentNative, $cell[1], "{$source} native writer cell alignment native payload");
            $t->same($rowSpanNative, $cell[2], "{$source} native writer rowspan native payload");
            $t->same($colSpanNative, $cell[3], "{$source} native writer colspan native payload");
        }
    },
    'preserves tagged attr helper constructors through json and native writers' => static function (TestRunner $t): void {
        $headingAttr = ['t' => 'Attr', 'c' => ['attr-heading', ['review'], [['data-source', 'json']]]];
        $linkAttr = ['t' => 'Attr', 'c' => ['attr-link', ['external'], [['data-link', 'source']]]];
        $spanAttr = ['t' => 'Attr', 'c' => ['attr-span', ['inline'], [['data-span', 'source']]]];
        $codeBlockAttr = ['t' => 'Attr', 'c' => ['attr-code', ['block'], [['data-code', 'source']]]];
        $tableAttr = ['t' => 'Attr', 'c' => ['attr-table', ['wide'], [['data-table', 'source']]]];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Header', 'c' => [
                    2,
                    $headingAttr,
                    [['t' => 'Str', 'c' => 'Tagged attr']],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'See'],
                    ['t' => 'Space'],
                    ['t' => 'Link', 'c' => [
                        $linkAttr,
                        [['t' => 'Str', 'c' => 'source']],
                        ['https://example.test/source', 'Source'],
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Span', 'c' => [
                        $spanAttr,
                        [['t' => 'Str', 'c' => 'span']],
                    ]],
                ]],
                ['t' => 'CodeBlock', 'c' => [
                    $codeBlockAttr,
                    'echo tagged',
                ]],
                ['t' => 'Table', 'c' => [
                    $tableAttr,
                    ['t' => 'Caption', 'c' => [null, []]],
                    [[['t' => 'AlignDefault', 'c' => []], ['t' => 'ColWidthDefault', 'c' => []]]],
                    ['t' => 'TableHead', 'c' => [['', [], []], []]],
                    [],
                    ['t' => 'TableFoot', 'c' => [['', [], []], []]],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];
        $childByType = static function (AstNode $parent, string $type): AstNode {
            foreach ($parent->children as $child) {
                if ($child->type === $type) {
                    return $child;
                }
            }

            throw new \RuntimeException("Missing {$type} child");
        };
        $inlineByConstructor = static function (array $inlines, string $constructor): array {
            foreach ($inlines as $inline) {
                if (is_array($inline) && ($inline['t'] ?? null) === $constructor) {
                    return $inline;
                }
            }

            throw new \RuntimeException("Missing {$constructor} inline constructor");
        };

        foreach ($documents as $source => $document) {
            $heading = $document->children[0];
            $paragraph = $document->children[1];
            $link = $childByType($paragraph, 'link');
            $span = $childByType($paragraph, 'span');
            $codeBlock = $document->children[2];
            $table = $document->children[3];

            $t->same('attr-heading', $heading->attr('id'), "{$source} reads tagged header attr id");
            $t->same(['review'], $heading->attr('classes'), "{$source} reads tagged header classes");
            $t->same(['data-source' => 'json'], $heading->attr('attributes'), "{$source} reads tagged header key-values");
            $t->same($headingAttr, $heading->attr('attrNative'), "{$source} retains tagged header attr native");
            $t->same($linkAttr, $link->attr('attrNative'), "{$source} retains tagged link attr native");
            $t->same($spanAttr, $span->attr('attrNative'), "{$source} retains tagged span attr native");
            $t->same($codeBlockAttr, $codeBlock->attr('attrNative'), "{$source} retains tagged code block attr native");
            $t->same($tableAttr, $table->attr('attrNative'), "{$source} retains tagged table attr native");

            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $writer => $encoded) {
                $linkInline = $inlineByConstructor($encoded['blocks'][1]['c'], 'Link');
                $spanInline = $inlineByConstructor($encoded['blocks'][1]['c'], 'Span');

                $t->same($headingAttr, $encoded['blocks'][0]['c'][1], "{$source} {$writer} writer preserves header Attr constructor");
                $t->same($linkAttr, $linkInline['c'][0], "{$source} {$writer} writer preserves link Attr constructor");
                $t->same($spanAttr, $spanInline['c'][0], "{$source} {$writer} writer preserves span Attr constructor");
                $t->same($codeBlockAttr, $encoded['blocks'][2]['c'][0], "{$source} {$writer} writer preserves code block Attr constructor");
                $t->same($tableAttr, $encoded['blocks'][3]['c'][0], "{$source} {$writer} writer preserves table Attr constructor");
            }

            $editedHeading = new AstNode('heading', array_replace($heading->attrs, [
                'id' => 'edited-heading',
            ]), $heading->children);
            $editedDocument = new AstNode('document', $document->attrs, [$editedHeading]);
            $editedJson = (new PandocJsonWriter())->toArray($editedDocument);
            $editedNative = json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR);

            $t->same(['edited-heading', ['review'], [['data-source', 'json']]], $editedJson['blocks'][0]['c'][1], "{$source} json writer regenerates edited Attr tuple");
            $t->same(['edited-heading', ['review'], [['data-source', 'json']]], $editedNative['blocks'][0]['c'][1], "{$source} native writer regenerates edited Attr tuple");
        }
    },
    'preserves native-reader tagged attr sidecars when regenerating edited constructors' => static function (TestRunner $t): void {
        $codeBlockAttr = ['t' => 'Attr', 'c' => [
            'native-code',
            ['php'],
            [['data-source', 'native-reader']],
            ['reviewQueue' => 'code-attr-sidecar'],
        ]];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'CodeBlock', 'c' => [$codeBlockAttr, 'echo 1;'], 'reviewQueue' => 'code-block-source'],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR));
        $codeBlock = $document->children[0];
        $editedDocument = new AstNode('document', $document->attrs, [
            new AstNode('code_block', array_replace($codeBlock->attrs, [
                'text' => 'echo 2;',
            ])),
        ]);

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($editedDocument),
            'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
        ] as $writer => $encoded) {
            $encodedCode = $encoded['blocks'][0];

            $t->same('CodeBlock', $encodedCode['t'], "{$writer} writer regenerates code block constructor");
            $t->same($codeBlockAttr, $encodedCode['c'][0], "{$writer} writer preserves tagged Attr sidecar");
            $t->same('echo 2;', $encodedCode['c'][1], "{$writer} writer emits edited code text");
            $t->same(false, array_key_exists('reviewQueue', $encodedCode), "{$writer} writer drops stale block wrapper sidecar");
        }

        $editedAttrDocument = new AstNode('document', $document->attrs, [
            new AstNode('code_block', array_replace($codeBlock->attrs, [
                'id' => 'edited-code',
                'text' => 'echo 1;',
            ])),
        ]);
        $editedAttrJson = (new PandocJsonWriter())->toArray($editedAttrDocument);
        $editedAttrNative = json_decode((new NativeWriter())->write($editedAttrDocument), true, 512, JSON_THROW_ON_ERROR);
        $regeneratedAttr = ['edited-code', ['php'], [['data-source', 'native-reader']]];

        $t->same($regeneratedAttr, $editedAttrJson['blocks'][0]['c'][0], 'json writer regenerates stale tagged Attr sidecar after attr edit');
        $t->same($regeneratedAttr, $editedAttrNative['blocks'][0]['c'][0], 'native writer regenerates stale tagged Attr sidecar after attr edit');
    },
    'preserves pandoc json tagged attr sidecars when regenerating edited constructors' => static function (TestRunner $t): void {
        $codeBlockAttr = ['t' => 'Attr', 'c' => [
            'json-code',
            ['php'],
            [['data-source', 'json-reader']],
            ['reviewQueue' => 'code-attr-sidecar', 'sourceOrdinal' => 9],
        ]];
        $codeBlock = [
            't' => 'CodeBlock',
            'c' => [$codeBlockAttr, 'echo 1;'],
            'reviewQueue' => 'code-block-source',
            'sourceOrdinal' => 8,
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$codeBlock],
        ];

        $document = (new PandocJsonReader())->readPacket($packet);
        $code = $document->children[0];
        $unchanged = (new PandocJsonWriter())->toArray($document);

        $t->same('code_block', $code->type);
        $t->same('json-code', $code->attr('id'));
        $t->same(['php'], $code->attr('classes'));
        $t->same(['data-source' => 'json-reader'], $code->attr('attributes'));
        $t->same($codeBlockAttr, $code->attr('attrNative'));
        $t->same($codeBlock, $unchanged['blocks'][0], 'json writer preserves unchanged tagged Attr sidecar payload');

        $editedTextDocument = new AstNode('document', $document->attrs, [
            new AstNode('code_block', array_replace($code->attrs, [
                'text' => 'echo 2;',
            ])),
        ]);

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($editedTextDocument),
            'native' => json_decode((new NativeWriter())->write($editedTextDocument), true, 512, JSON_THROW_ON_ERROR),
        ] as $writer => $encoded) {
            $encodedCode = $encoded['blocks'][0];

            $t->same('CodeBlock', $encodedCode['t'], "{$writer} writer regenerates edited code block constructor");
            $t->same($codeBlockAttr, $encodedCode['c'][0], "{$writer} writer preserves json-reader tagged Attr sidecar");
            $t->same('echo 2;', $encodedCode['c'][1], "{$writer} writer emits edited code text");
            $t->same(false, array_key_exists('reviewQueue', $encodedCode), "{$writer} writer drops stale code block sidecar");
            $t->same(false, array_key_exists('sourceOrdinal', $encodedCode), "{$writer} writer drops stale code block ordinal");
        }

        $editedAttrDocument = new AstNode('document', $document->attrs, [
            new AstNode('code_block', array_replace($code->attrs, [
                'id' => 'edited-json-code',
            ])),
        ]);
        $editedAttrJson = (new PandocJsonWriter())->toArray($editedAttrDocument);
        $editedAttrNative = json_decode((new NativeWriter())->write($editedAttrDocument), true, 512, JSON_THROW_ON_ERROR);
        $regeneratedAttr = ['edited-json-code', ['php'], [['data-source', 'json-reader']]];

        $t->same($regeneratedAttr, $editedAttrJson['blocks'][0]['c'][0], 'json writer regenerates json-reader tagged Attr sidecar after attr edit');
        $t->same($regeneratedAttr, $editedAttrNative['blocks'][0]['c'][0], 'native writer regenerates json-reader tagged Attr sidecar after attr edit');
    },
    'emits text-only shared ast block constructors through pandoc json and native writers' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], [
            new AstNode('plain', ['text' => 'Plain']),
            new AstNode('paragraph', ['text' => 'Paragraph']),
            new AstNode('heading', ['level' => 2, 'text' => 'Heading']),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', ['text' => 'Bullet']),
            ]),
            new AstNode('definition_list', [], [
                new AstNode('definition_item', [], [
                    new AstNode('definition_term', ['text' => 'Term']),
                    new AstNode('definition', ['text' => 'Definition']),
                ]),
            ]),
            new AstNode('line_block', [], [
                new AstNode('line', ['text' => 'Line']),
            ]),
        ]);
        $expectedBlocks = [
            ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Plain']]],
            ['t' => 'Para', 'c' => [['t' => 'Str', 'c' => 'Paragraph']]],
            ['t' => 'Header', 'c' => [2, ['', [], []], [['t' => 'Str', 'c' => 'Heading']]]],
            ['t' => 'BulletList', 'c' => [
                [
                    ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Bullet']]],
                ],
            ]],
            ['t' => 'DefinitionList', 'c' => [
                [
                    [['t' => 'Str', 'c' => 'Term']],
                    [
                        [
                            ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Definition']]],
                        ],
                    ],
                ],
            ]],
            ['t' => 'LineBlock', 'c' => [
                [['t' => 'Str', 'c' => 'Line']],
            ]],
        ];

        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same($expectedBlocks, $jsonPacket['blocks'], 'pandoc json writer emits text-only block constructors');
        $t->same($expectedBlocks, $nativePacket['blocks'], 'native writer emits text-only block constructors');
    },
    'writes remaining shared ast constructors through pandoc json and native writers' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [
                'review' => ['type' => 'map', 'items' => [
                    'draft' => false,
                    'aliases' => ['type' => 'list', 'items' => ['json', 'native']],
                    'inline' => ['type' => 'inlines', 'children' => [
                        new AstNode('span', ['id' => 'meta-span'], [
                            new AstNode('text', ['text' => 'meta']),
                        ]),
                    ]],
                    'body' => ['type' => 'blocks', 'children' => [
                        new AstNode('plain', [], [
                            new AstNode('text', ['text' => 'meta-block']),
                        ]),
                    ]],
                ]],
            ],
        ], [
            new AstNode('plain', [], [
                new AstNode('quoted', ['kind' => 'single'], [
                    new AstNode('text', ['text' => 'single']),
                ]),
                new AstNode('space'),
                new AstNode('math', ['display' => false, 'text' => 'x+1']),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('raw_html_inline', ['html' => '<span>raw</span>']),
                new AstNode('space'),
                new AstNode('raw_tex', ['tex' => '\\alpha']),
                new AstNode('space'),
                new AstNode('raw_inline', ['format' => 'opml', 'text' => '<outline/>']),
                new AstNode('space'),
                new AstNode('link', [
                    'id' => 'link-id',
                    'url' => 'https://example.test/source',
                    'title' => 'Source',
                ], [
                    new AstNode('text', ['text' => 'link']),
                ]),
                new AstNode('space'),
                new AstNode('image', [
                    'classes' => ['asset'],
                    'url' => 'media/image.png',
                    'title' => 'Image title',
                    'alt' => 'Alt text',
                ]),
                new AstNode('space'),
                new AstNode('note', [], [
                    new AstNode('plain', [], [
                        new AstNode('text', ['text' => 'note']),
                    ]),
                ]),
                new AstNode('space'),
                new AstNode('citation_group', [], [
                    new AstNode('citation', [
                        'id' => 'source-a',
                        'prefix' => [new AstNode('text', ['text' => 'see'])],
                        'citationHash' => 10,
                    ]),
                    new AstNode('citation', [
                        'id' => 'source-b',
                        'mode' => 'author_in_text',
                    ]),
                    new AstNode('citation', [
                        'id' => 'source-c',
                        'mode' => 'suppress_author',
                        'suffix' => [
                            new AstNode('text', ['text' => 'p.']),
                            new AstNode('space'),
                            new AstNode('text', ['text' => '2']),
                        ],
                    ]),
                ]),
            ]),
            new AstNode('raw_tex', ['tex' => '\\clearpage']),
            new AstNode('raw_block', ['format' => 'opml', 'text' => '<outline/>']),
            new AstNode('table', [
                'alignments' => ['left'],
                'widths' => [0.25],
            ], [
                new AstNode('table_body', ['rowHeadColumns' => 1], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['rowspan' => 2, 'colspan' => 3], [
                            new AstNode('text', ['text' => 'Cell']),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $paragraphInlines = $jsonPacket['blocks'][1]['c'];
        $citationRecords = $paragraphInlines[12]['c'][0];
        $tableBlock = $jsonPacket['blocks'][4];
        $tableBody = $tableBlock['c'][4][0];
        $tableBodyPayload = $tableBody['c'] ?? $tableBody;
        $tableRow = $tableBodyPayload[3][0]['c'] ?? $tableBodyPayload[3][0];
        $tableCell = $tableRow[1][0]['c'] ?? $tableRow[1][0];

        $t->same($jsonPacket['meta'], $nativePacket['meta']);
        $t->same($jsonPacket['blocks'], $nativePacket['blocks']);
        $t->same(['Plain', 'Para', 'RawBlock', 'RawBlock', 'Table'], array_map(static fn (array $block): string => $block['t'], $jsonPacket['blocks']));
        $t->same('MetaMap', $jsonPacket['meta']['review']['t']);
        $t->same('MetaBool', $jsonPacket['meta']['review']['c']['draft']['t']);
        $t->same('MetaList', $jsonPacket['meta']['review']['c']['aliases']['t']);
        $t->same('MetaInlines', $jsonPacket['meta']['review']['c']['inline']['t']);
        $t->same('Span', $jsonPacket['meta']['review']['c']['inline']['c'][0]['t']);
        $t->same('MetaBlocks', $jsonPacket['meta']['review']['c']['body']['t']);
        $t->same('Plain', $jsonPacket['meta']['review']['c']['body']['c'][0]['t']);
        $t->same('SingleQuote', $jsonPacket['blocks'][0]['c'][0]['c'][0]['t']);
        $t->same('InlineMath', $jsonPacket['blocks'][0]['c'][2]['c'][0]['t']);
        $t->same([
            'RawInline',
            'Space',
            'RawInline',
            'Space',
            'RawInline',
            'Space',
            'Link',
            'Space',
            'Image',
            'Space',
            'Note',
            'Space',
            'Cite',
        ], array_map(static fn (array $inline): string => $inline['t'], $paragraphInlines));
        $t->same(['html', '<span>raw</span>'], $paragraphInlines[0]['c']);
        $t->same(['latex', '\\alpha'], $paragraphInlines[2]['c']);
        $t->same(['opml', '<outline/>'], $paragraphInlines[4]['c']);
        $t->same(['link-id', [], []], $paragraphInlines[6]['c'][0]);
        $t->same(['media/image.png', 'Image title'], $paragraphInlines[8]['c'][2]);
        $t->same('Plain', $paragraphInlines[10]['c'][0]['t']);
        $t->same('NormalCitation', $citationRecords[0]['citationMode']['t']);
        $t->same('AuthorInText', $citationRecords[1]['citationMode']['t']);
        $t->same('SuppressAuthor', $citationRecords[2]['citationMode']['t']);
        $t->same(['latex', '\\clearpage'], $jsonPacket['blocks'][2]['c']);
        $t->same(['opml', '<outline/>'], $jsonPacket['blocks'][3]['c']);
        $t->same('AlignLeft', $tableBlock['c'][2][0][0]['t']);
        $t->same(['t' => 'ColWidth', 'c' => 0.25], $tableBlock['c'][2][0][1]);
        $t->same('TableBody', $tableBody['t'] ?? null);
        $t->same(['t' => 'RowHeadColumns', 'c' => 1], $tableBodyPayload[1]);
        $t->same(['t' => 'RowSpan', 'c' => 2], $tableCell[2]);
        $t->same(['t' => 'ColSpan', 'c' => 3], $tableCell[3]);

        $jsonRoundTrip = (new PandocJsonReader())->readPacket($jsonPacket);
        $nativeRoundTrip = (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR));
        $t->same('citation_group', $jsonRoundTrip->children[1]->children[12]->type);
        $t->same('citation_group', $nativeRoundTrip->children[1]->children[12]->type);
        $t->same('table', $jsonRoundTrip->children[4]->type);
        $t->same('table', $nativeRoundTrip->children[4]->type);
        $t->same(3, $jsonRoundTrip->children[4]->children[0]->children[0]->children[0]->attr('colspan'));
        $t->same(3, $nativeRoundTrip->children[4]->children[0]->children[0]->children[0]->attr('colspan'));
    },
    'maps json native constructor matrix cases through reader writer stacks' => static function (TestRunner $t): void {
        $cases = [
            'metadata constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [
                        'review' => ['t' => 'MetaMap', 'c' => [
                            'flags' => ['t' => 'MetaList', 'c' => [
                                ['t' => 'MetaString', 'c' => 'json-native'],
                                ['t' => 'MetaBool', 'c' => true],
                            ]],
                            'inline' => ['t' => 'MetaInlines', 'c' => [
                                ['t' => 'Span', 'c' => [
                                    ['matrix-inline', ['meta'], []],
                                    [['t' => 'Str', 'c' => 'inline']],
                                ]],
                            ]],
                            'body' => ['t' => 'MetaBlocks', 'c' => [
                                ['t' => 'BlockQuote', 'c' => [
                                    ['t' => 'Para', 'c' => [['t' => 'Str', 'c' => 'body']]],
                                ]],
                            ]],
                        ]],
                    ],
                    'blocks' => [],
                ],
                'types' => [],
                'tags' => [],
            ],
            'top-level document constructor' => [
                'packet' => [
                    't' => 'Pandoc',
                    'pandoc-api-version' => [1, 24, 2],
                    'c' => [
                        ['t' => 'MetaMap', 'c' => [
                            'source' => ['t' => 'MetaString', 'c' => 'document-matrix'],
                        ]],
                        [
                            ['t' => 'Para', 'c' => [
                                ['t' => 'Str', 'c' => 'Document'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'matrix'],
                            ], 'reviewQueue' => 'document-matrix-paragraph'],
                        ],
                    ],
                    'reviewQueue' => 'document-matrix-source',
                ],
                'expectedMeta' => [
                    'source' => ['t' => 'MetaString', 'c' => 'document-matrix'],
                ],
                'expectedBlocks' => [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Str', 'c' => 'Document'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'matrix'],
                    ], 'reviewQueue' => 'document-matrix-paragraph'],
                ],
                'documentConstructor' => 'Pandoc',
                'types' => ['paragraph'],
                'tags' => ['Para'],
            ],
            'nullary block constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'HorizontalRule'],
                        ['t' => 'Null'],
                    ],
                ],
                'types' => ['horizontal_rule', 'null_block'],
                'tags' => ['HorizontalRule', 'Null'],
            ],
            'leaf block constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Plain'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'matrix'],
                        ]],
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Str', 'c' => 'Paragraph'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'matrix'],
                        ]],
                        ['t' => 'Header', 'c' => [
                            3,
                            ['matrix-heading', ['review'], [['data-source', 'matrix']]],
                            [
                                ['t' => 'Str', 'c' => 'Heading'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'matrix'],
                            ],
                        ]],
                        ['t' => 'CodeBlock', 'c' => [
                            ['matrix-code-block', ['php'], [['data-source', 'matrix']]],
                            "echo 1;\n",
                        ]],
                    ],
                ],
                'types' => ['plain', 'paragraph', 'heading', 'code_block'],
                'tags' => ['Plain', 'Para', 'Header', 'CodeBlock'],
            ],
            'structural block constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'BlockQuote', 'c' => [
                            ['t' => 'Para', 'c' => [
                                ['t' => 'Str', 'c' => 'Quoted'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'matrix'],
                            ]],
                        ]],
                        ['t' => 'Div', 'c' => [
                            ['matrix-div', ['review'], [['data-source', 'matrix']]],
                            [
                                ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Wrapped']]],
                            ],
                        ]],
                    ],
                ],
                'types' => ['blockquote', 'div'],
                'tags' => ['BlockQuote', 'Div'],
            ],
            'list constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'OrderedList', 'c' => [
                            [4, ['t' => 'UpperRoman'], ['t' => 'OneParen']],
                            [[
                                ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Ordered']]],
                            ]],
                        ]],
                        ['t' => 'BulletList', 'c' => [[
                            ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Bullet']]],
                        ]]],
                    ],
                ],
                'types' => ['ordered_list', 'bullet_list'],
                'tags' => ['OrderedList', 'BulletList'],
            ],
            'definition and line constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'DefinitionList', 'c' => [[
                            [
                                ['t' => 'Str', 'c' => 'Term'],
                            ],
                            [[
                                ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Definition']]],
                            ]],
                        ]]],
                        ['t' => 'LineBlock', 'c' => [[
                            ['t' => 'Str', 'c' => 'Line'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'one'],
                        ]]],
                    ],
                ],
                'types' => ['definition_list', 'line_block'],
                'tags' => ['DefinitionList', 'LineBlock'],
            ],
            'raw format constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'RawBlock', 'c' => [
                            ['t' => 'Format', 'c' => 'html'],
                            '<section>raw</section>',
                        ]],
                        ['t' => 'Para', 'c' => [
                            ['t' => 'RawInline', 'c' => [
                                ['t' => 'Format', 'c' => 'latex'],
                                '\\alpha',
                            ]],
                        ]],
                    ],
                ],
                'types' => ['raw_html', 'paragraph'],
                'tags' => ['RawBlock', 'Para'],
            ],
            'inline formatting constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Str', 'c' => 'Inline'],
                            ['t' => 'Space'],
                            ['t' => 'Emph', 'c' => [['t' => 'Str', 'c' => 'em']]],
                            ['t' => 'Space'],
                            ['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'strong']]],
                            ['t' => 'Space'],
                            ['t' => 'Underline', 'c' => [['t' => 'Str', 'c' => 'under']]],
                            ['t' => 'Space'],
                            ['t' => 'Strikeout', 'c' => [['t' => 'Str', 'c' => 'old']]],
                            ['t' => 'Space'],
                            ['t' => 'Superscript', 'c' => [['t' => 'Str', 'c' => '2']]],
                            ['t' => 'Subscript', 'c' => [['t' => 'Str', 'c' => 'n']]],
                            ['t' => 'SmallCaps', 'c' => [['t' => 'Str', 'c' => 'caps']]],
                            ['t' => 'Quoted', 'c' => [
                                ['t' => 'SingleQuote'],
                                [['t' => 'Str', 'c' => 'quote']],
                            ]],
                            ['t' => 'Math', 'c' => [
                                ['t' => 'InlineMath'],
                                'x + y',
                            ]],
                            ['t' => 'Space'],
                            ['t' => 'Math', 'c' => [
                                ['t' => 'DisplayMath'],
                                'x = y',
                            ]],
                            ['t' => 'SoftBreak'],
                            ['t' => 'LineBreak'],
                        ]],
                    ],
                ],
                'types' => ['paragraph'],
                'tags' => ['Para'],
            ],
            'target and attr inline constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Code', 'c' => [
                                ['matrix-code', ['php'], [['data-source', 'matrix']]],
                                'echo 1;',
                            ]],
                            ['t' => 'Space'],
                            ['t' => 'Link', 'c' => [
                                ['matrix-link', ['source'], [['data-kind', 'link']]],
                                [['t' => 'Str', 'c' => 'link']],
                                ['https://example.test/source', 'Source'],
                            ]],
                            ['t' => 'Space'],
                            ['t' => 'Image', 'c' => [
                                ['matrix-image', ['asset'], [['data-kind', 'image']]],
                                [['t' => 'Str', 'c' => 'alt']],
                                ['media/image.png', 'Image'],
                            ]],
                            ['t' => 'Space'],
                            ['t' => 'Span', 'c' => [
                                ['matrix-span', ['review'], [['data-kind', 'span']]],
                                [['t' => 'Str', 'c' => 'span']],
                            ]],
                        ]],
                    ],
                ],
                'types' => ['paragraph'],
                'tags' => ['Para'],
            ],
            'citation constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Cite', 'c' => [
                                [
                                    [
                                        'citationId' => 'doe2026',
                                        'citationPrefix' => [['t' => 'Str', 'c' => 'see']],
                                        'citationSuffix' => [],
                                        'citationMode' => ['t' => 'NormalCitation'],
                                        'citationNoteNum' => 1,
                                        'citationHash' => 101,
                                    ],
                                    [
                                        'citationId' => 'roe2026',
                                        'citationPrefix' => [],
                                        'citationSuffix' => [['t' => 'Str', 'c' => 'p. 2']],
                                        'citationMode' => ['t' => 'AuthorInText'],
                                        'citationNoteNum' => 2,
                                        'citationHash' => 102,
                                    ],
                                    [
                                        'citationId' => 'noauthor2026',
                                        'citationPrefix' => [],
                                        'citationSuffix' => [],
                                        'citationMode' => ['t' => 'SuppressAuthor'],
                                        'citationNoteNum' => 3,
                                        'citationHash' => 103,
                                    ],
                                ],
                                [
                                    ['t' => 'Str', 'c' => '@doe2026'],
                                    ['t' => 'Space'],
                                    ['t' => 'Str', 'c' => '@roe2026'],
                                ],
                            ]],
                        ]],
                    ],
                ],
                'types' => ['paragraph'],
                'tags' => ['Para'],
            ],
            'note constructor' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Str', 'c' => 'Before'],
                            ['t' => 'Space'],
                            ['t' => 'Note', 'c' => [
                                ['t' => 'Plain', 'c' => [
                                    ['t' => 'Str', 'c' => 'Footnote'],
                                    ['t' => 'Space'],
                                    ['t' => 'Str', 'c' => 'matrix'],
                                ]],
                                ['t' => 'HorizontalRule'],
                            ], 'noteLabel' => 'matrix-note'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'after'],
                        ]],
                    ],
                ],
                'types' => ['paragraph'],
                'tags' => ['Para'],
            ],
            'table and figure caption constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'Table', 'c' => [
                            ['matrix-table', ['review'], []],
                            ['t' => 'Caption', 'c' => [
                                ['t' => 'Nothing'],
                                [],
                            ]],
                            [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                            ['t' => 'TableHead', 'c' => [['', [], []], []]],
                            [['t' => 'TableBody', 'c' => [
                                ['', [], []],
                                ['t' => 'RowHeadColumns', 'c' => 0],
                                [],
                                [['t' => 'Row', 'c' => [
                                    ['', [], []],
                                    [['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Cell']]]],
                                    ]]],
                                ]]],
                            ]]],
                            ['t' => 'TableFoot', 'c' => [['', [], []], []]],
                        ]],
                        ['t' => 'Figure', 'c' => [
                            ['matrix-figure', ['review'], []],
                            ['t' => 'Caption', 'c' => [
                                ['t' => 'Just', 'c' => ['t' => 'ShortCaption', 'c' => [[
                                    ['t' => 'Str', 'c' => 'Short'],
                                ]]]],
                                [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Long']]]],
                            ]],
                            [
                                ['t' => 'Para', 'c' => [
                                    ['t' => 'Image', 'c' => [
                                        ['', [], []],
                                        [['t' => 'Str', 'c' => 'Alt']],
                                        ['media/figure.png', 'Figure'],
                                    ]],
                                ]],
                            ],
                        ]],
                    ],
                ],
                'types' => ['table', 'figure'],
                'tags' => ['Table', 'Figure'],
            ],
            'table local leaf block constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'Table', 'c' => [
                            ['matrix-leaf-table', ['review'], [['data-source', 'matrix']]],
                            ['t' => 'Caption', 'c' => [
                                ['t' => 'Nothing'],
                                [
                                    ['t' => 'CodeBlock', 'c' => [
                                        ['', ['bash'], [['data-kind', 'caption-code']]],
                                        "wp option get siteurl\n",
                                    ]],
                                    ['t' => 'RawBlock', 'c' => [
                                        ['t' => 'Format', 'c' => 'html'],
                                        '<p>caption raw</p>',
                                    ]],
                                ],
                            ]],
                            [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                            ['t' => 'TableHead', 'c' => [['', [], []], []]],
                            [['t' => 'TableBody', 'c' => [
                                ['', [], []],
                                ['t' => 'RowHeadColumns', 'c' => 0],
                                [],
                                [['t' => 'Row', 'c' => [
                                    ['', [], []],
                                    [['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [
                                            ['t' => 'CodeBlock', 'c' => [
                                                ['', ['php'], [['data-kind', 'cell-code']]],
                                                "echo 'cell';\n",
                                            ]],
                                            ['t' => 'RawBlock', 'c' => [
                                                ['t' => 'Format', 'c' => 'html'],
                                                '<span>cell raw</span>',
                                            ]],
                                        ],
                                    ]]],
                                ]]],
                            ]]],
                            ['t' => 'TableFoot', 'c' => [['', [], []], []]],
                        ]],
                    ],
                ],
                'types' => ['table'],
                'tags' => ['Table'],
            ],
            'figure local leaf block constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'Figure', 'c' => [
                            ['matrix-leaf-figure', ['review'], [['data-source', 'matrix']]],
                            ['t' => 'Caption', 'c' => [
                                ['t' => 'Nothing'],
                                [
                                    ['t' => 'Plain', 'c' => [
                                        ['t' => 'Str', 'c' => 'Figure'],
                                        ['t' => 'Space'],
                                        ['t' => 'Str', 'c' => 'leaf'],
                                    ]],
                                ],
                            ]],
                            [
                                ['t' => 'CodeBlock', 'c' => [
                                    ['', ['bash'], [['data-kind', 'figure-code']]],
                                    "wp post list --post_type=attachment\n",
                                ]],
                                ['t' => 'RawBlock', 'c' => [
                                    ['t' => 'Format', 'c' => 'html'],
                                    '<aside data-figure="raw">review</aside>',
                                ]],
                            ],
                        ]],
                    ],
                ],
                'types' => ['figure'],
                'tags' => ['Figure'],
            ],
            'native fallback constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'VendorBlock', 'c' => [
                            'source' => 'constructor-matrix',
                            'payload' => [['t' => 'Str', 'c' => 'opaque block']],
                        ], 'reviewQueue' => 'vendor-block-source'],
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Str', 'c' => 'Before'],
                            ['t' => 'Space'],
                            ['t' => 'VendorInline', 'c' => [
                                'name' => 'review-anchor',
                                'value' => 42,
                            ], 'reviewQueue' => 'vendor-inline-source'],
                        ]],
                    ],
                ],
                'types' => ['native_block', 'paragraph'],
                'tags' => ['VendorBlock', 'Para'],
            ],
            'note local leaf block constructors' => [
                'packet' => [
                    'pandoc-api-version' => [1, 23, 1],
                    'meta' => [],
                    'blocks' => [
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Str', 'c' => 'Note'],
                            ['t' => 'Space'],
                            ['t' => 'Note', 'c' => [
                                ['t' => 'CodeBlock', 'c' => [
                                    ['', ['php'], [['data-kind', 'note-code']]],
                                    "echo 'note';\n",
                                ]],
                                ['t' => 'RawBlock', 'c' => [
                                    ['t' => 'Format', 'c' => 'html'],
                                    '<aside data-note="raw">review</aside>',
                                ]],
                            ]],
                        ]],
                    ],
                ],
                'types' => ['paragraph'],
                'tags' => ['Para'],
            ],
        ];

        foreach ($cases as $caseName => $case) {
            $packet = $case['packet'];
            $expectedMeta = $case['expectedMeta'] ?? $packet['meta'];
            $expectedBlocks = $case['expectedBlocks'] ?? $packet['blocks'];

            foreach ([
                'json' => (new PandocJsonReader())->readPacket($packet),
                'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
            ] as $source => $document) {
                $jsonPacket = (new PandocJsonWriter())->toArray($document);
                $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

                $t->same($case['types'], array_map(static fn (AstNode $node): string => $node->type, $document->children), "{$caseName} {$source} ast block types");
                $t->same($case['tags'], array_map(static fn (array $block): string => (string) $block['t'], $jsonPacket['blocks']), "{$caseName} {$source} json block tags");
                $t->same($case['tags'], array_map(static fn (array $block): string => (string) $block['t'], $nativePacket['blocks']), "{$caseName} {$source} native block tags");
                $t->same($expectedMeta, $jsonPacket['meta'], "{$caseName} {$source} json metadata constructors");
                $t->same($expectedMeta, $nativePacket['meta'], "{$caseName} {$source} native metadata constructors");
                $t->same($expectedBlocks, $jsonPacket['blocks'], "{$caseName} {$source} json block constructors");
                $t->same($expectedBlocks, $nativePacket['blocks'], "{$caseName} {$source} native block constructors");
                if (isset($case['documentConstructor'])) {
                    $t->same($case['documentConstructor'], $document->attr('documentConstructor'), "{$caseName} {$source} document constructor");
                    $t->same($packet, $document->attr('documentNative'), "{$caseName} {$source} document native payload");
                }
            }
        }
    },
    'maps json native constructor completeness summaries through reader writer stacks' => static function (TestRunner $t): void {
        $plainBlock = ['t' => 'Plain', 'c' => [
            ['t' => 'Str', 'c' => 'Plain'],
            ['t' => 'Space'],
            ['t' => 'Code', 'c' => [['', ['php'], []], 'summary']],
        ]];
        $paraBlock = ['t' => 'Para', 'c' => [
            ['t' => 'Str', 'c' => 'Paragraph'],
            ['t' => 'Space'],
            ['t' => 'Emph', 'c' => [
                ['t' => 'Str', 'c' => 'summary'],
            ]],
        ]];
        $headerBlock = ['t' => 'Header', 'c' => [
            2,
            ['constructor-heading', ['review'], [['data-kind', 'heading']]],
            [
                ['t' => 'Str', 'c' => 'Heading'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'summary'],
            ],
        ]];
        $codeBlock = ['t' => 'CodeBlock', 'c' => [
            ['constructor-code', ['php'], [['data-kind', 'code-block']]],
            "echo 1;\n",
        ]];
        $rawBlock = ['t' => 'RawBlock', 'c' => [
            ['t' => 'Format', 'c' => 'html'],
            '<aside>raw block</aside>',
        ]];
        $quotedInline = ['t' => 'Quoted', 'c' => [
            ['t' => 'DoubleQuote'],
            [['t' => 'Str', 'c' => 'quoted']],
        ]];
        $mathInline = ['t' => 'Math', 'c' => [
            ['t' => 'DisplayMath'],
            'x = y',
        ]];
        $rawInline = ['t' => 'RawInline', 'c' => [
            ['t' => 'Format', 'c' => 'latex'],
            '\\alpha',
        ]];
        $breakBlock = ['t' => 'Para', 'c' => [
            ['t' => 'Str', 'c' => 'Soft'],
            ['t' => 'SoftBreak'],
            ['t' => 'Str', 'c' => 'line'],
            ['t' => 'LineBreak'],
            ['t' => 'Str', 'c' => 'hard'],
        ]];
        $linkInline = ['t' => 'Link', 'c' => [
            ['constructor-link', ['source'], [['data-kind', 'link']]],
            [['t' => 'Str', 'c' => 'source']],
            ['https://example.test/source', 'Source title'],
        ]];
        $nativeBlock = ['t' => 'VendorBlock', 'c' => [
            'source' => 'constructor-completeness',
            'payload' => [['t' => 'Str', 'c' => 'opaque']],
        ]];
        $nativeInline = ['t' => 'VendorInline', 'c' => [
            'name' => 'constructor-completeness-anchor',
            'value' => 42,
        ]];
        $tableCell = ['t' => 'Cell', 'c' => [
            ['constructor-cell', ['body'], [['data-kind', 'cell']]],
            ['t' => 'AlignRight'],
            ['t' => 'RowSpan', 'c' => 2],
            ['t' => 'ColSpan', 'c' => 3],
            [['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Cell'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'summary'],
            ]]],
        ]];
        $tableBlock = ['t' => 'Table', 'c' => [
            ['constructor-table', ['review'], []],
            ['t' => 'Caption', 'c' => [
                ['t' => 'Nothing'],
                [],
            ]],
            [[['t' => 'AlignRight'], ['t' => 'ColWidth', 'c' => 0.4]]],
            ['t' => 'TableHead', 'c' => [['', [], []], []]],
            [['t' => 'TableBody', 'c' => [
                ['', [], []],
                ['t' => 'RowHeadColumns', 'c' => 1],
                [],
                [['t' => 'Row', 'c' => [
                    ['', [], []],
                    [$tableCell],
                ]]],
            ]]],
            ['t' => 'TableFoot', 'c' => [['', [], []], []]],
        ]];

        $cases = [
            'plain text summary' => [
                'packet' => ['pandoc-api-version' => [1, 23, 1], 'meta' => [], 'blocks' => [$plainBlock]],
                'path' => [0],
                'type' => 'plain',
                'constructor' => 'Plain',
                'native' => $plainBlock,
                'text' => 'Plain summary',
            ],
            'paragraph text summary' => [
                'packet' => ['pandoc-api-version' => [1, 23, 1], 'meta' => [], 'blocks' => [$paraBlock]],
                'path' => [0],
                'type' => 'paragraph',
                'constructor' => 'Para',
                'native' => $paraBlock,
                'text' => 'Paragraph summary',
            ],
            'header text summary' => [
                'packet' => ['pandoc-api-version' => [1, 23, 1], 'meta' => [], 'blocks' => [$headerBlock]],
                'path' => [0],
                'type' => 'heading',
                'constructor' => 'Header',
                'native' => $headerBlock,
                'text' => 'Heading summary',
                'assert' => static function (TestRunner $t, AstNode $node, string $label): void {
                    $t->same(2, $node->attr('level'), "{$label} level");
                    $t->same('constructor-heading', $node->attr('id'), "{$label} attr id");
                },
            ],
            'code block text summary' => [
                'packet' => ['pandoc-api-version' => [1, 23, 1], 'meta' => [], 'blocks' => [$codeBlock]],
                'path' => [0],
                'type' => 'code_block',
                'constructor' => 'CodeBlock',
                'native' => $codeBlock,
                'text' => "echo 1;\n",
                'assert' => static function (TestRunner $t, AstNode $node, string $label): void {
                    $t->same(['php'], $node->attr('classes'), "{$label} code classes");
                    $t->same('constructor-code', $node->attr('id'), "{$label} code id");
                },
            ],
            'raw block format helper' => [
                'packet' => ['pandoc-api-version' => [1, 23, 1], 'meta' => [], 'blocks' => [$rawBlock]],
                'path' => [0],
                'type' => 'raw_html',
                'constructor' => 'RawBlock',
                'native' => $rawBlock,
                'text' => '<aside>raw block</aside>',
                'assert' => static function (TestRunner $t, AstNode $node, string $label) use ($rawBlock): void {
                    $t->same('html', $node->attr('format'), "{$label} raw format");
                    $t->same($rawBlock['c'][0], $node->attr('formatNative'), "{$label} format native");
                },
            ],
            'quoted inline helper' => [
                'packet' => ['pandoc-api-version' => [1, 23, 1], 'meta' => [], 'blocks' => [['t' => 'Para', 'c' => [$quotedInline]]]],
                'path' => [0, 0],
                'type' => 'quoted',
                'constructor' => 'Quoted',
                'native' => $quotedInline,
                'assert' => static function (TestRunner $t, AstNode $node, string $label) use ($quotedInline): void {
                    $t->same('double', $node->attr('kind'), "{$label} quote kind");
                    $t->same('DoubleQuote', $node->attr('quoteTypeConstructor'), "{$label} quote helper constructor");
                    $t->same($quotedInline['c'][0], $node->attr('quoteTypeNative'), "{$label} quote helper native");
                },
            ],
            'math inline helper' => [
                'packet' => ['pandoc-api-version' => [1, 23, 1], 'meta' => [], 'blocks' => [['t' => 'Para', 'c' => [$mathInline]]]],
                'path' => [0, 0],
                'type' => 'math',
                'constructor' => 'Math',
                'native' => $mathInline,
                'text' => 'x = y',
                'assert' => static function (TestRunner $t, AstNode $node, string $label) use ($mathInline): void {
                    $t->same(true, $node->attr('display'), "{$label} display math");
                    $t->same('DisplayMath', $node->attr('mathTypeConstructor'), "{$label} math helper constructor");
                    $t->same($mathInline['c'][0], $node->attr('mathTypeNative'), "{$label} math helper native");
                },
            ],
            'raw inline format helper' => [
                'packet' => ['pandoc-api-version' => [1, 23, 1], 'meta' => [], 'blocks' => [['t' => 'Para', 'c' => [$rawInline]]]],
                'path' => [0, 0],
                'type' => 'raw_tex_inline',
                'constructor' => 'RawInline',
                'native' => $rawInline,
                'text' => '\\alpha',
                'assert' => static function (TestRunner $t, AstNode $node, string $label) use ($rawInline): void {
                    $t->same('latex', $node->attr('format'), "{$label} raw inline format");
                    $t->same($rawInline['c'][0], $node->attr('formatNative'), "{$label} raw inline format native");
                },
            ],
            'inline break summary constructors' => [
                'packet' => ['pandoc-api-version' => [1, 23, 1], 'meta' => [], 'blocks' => [$breakBlock]],
                'path' => [0],
                'type' => 'paragraph',
                'constructor' => 'Para',
                'native' => $breakBlock,
                'text' => "Soft line\nhard",
                'assert' => static function (TestRunner $t, AstNode $node, string $label) use ($breakBlock): void {
                    $t->same(['text', 'softbreak', 'text', 'linebreak', 'text'], array_map(static fn (AstNode $child): string => $child->type, $node->children), "{$label} inline break child types");
                    $t->same('SoftBreak', $node->children[1]->attr('constructor'), "{$label} softbreak constructor");
                    $t->same($breakBlock['c'][1], $node->children[1]->attr('native'), "{$label} softbreak native");
                    $t->same('LineBreak', $node->children[3]->attr('constructor'), "{$label} linebreak constructor");
                    $t->same($breakBlock['c'][3], $node->children[3]->attr('native'), "{$label} linebreak native");
                },
            ],
            'target attr inline helper' => [
                'packet' => ['pandoc-api-version' => [1, 23, 1], 'meta' => [], 'blocks' => [['t' => 'Para', 'c' => [$linkInline]]]],
                'path' => [0, 0],
                'type' => 'link',
                'constructor' => 'Link',
                'native' => $linkInline,
                'assert' => static function (TestRunner $t, AstNode $node, string $label) use ($linkInline): void {
                    $t->same('constructor-link', $node->attr('id'), "{$label} link id");
                    $t->same('https://example.test/source', $node->attr('url'), "{$label} link url");
                    $t->same($linkInline['c'][2], $node->attr('targetNative'), "{$label} link target native");
                },
            ],
            'native block fallback constructor' => [
                'packet' => ['pandoc-api-version' => [1, 23, 1], 'meta' => [], 'blocks' => [$nativeBlock]],
                'path' => [0],
                'type' => 'native_block',
                'constructor' => 'VendorBlock',
                'native' => $nativeBlock,
                'assert' => static function (TestRunner $t, AstNode $node, string $label) use ($nativeBlock): void {
                    $t->same('constructor-completeness', $node->attr('native')['c']['source'] ?? null, "{$label} fallback source");
                    $t->same($nativeBlock['c']['payload'], $node->attr('native')['c']['payload'] ?? null, "{$label} fallback payload");
                },
            ],
            'native inline fallback constructor' => [
                'packet' => ['pandoc-api-version' => [1, 23, 1], 'meta' => [], 'blocks' => [['t' => 'Para', 'c' => [$nativeInline]]]],
                'path' => [0, 0],
                'type' => 'native_inline',
                'constructor' => 'VendorInline',
                'native' => $nativeInline,
                'assert' => static function (TestRunner $t, AstNode $node, string $label) use ($nativeInline): void {
                    $t->same('constructor-completeness-anchor', $node->attr('native')['c']['name'] ?? null, "{$label} fallback name");
                    $t->same($nativeInline['c']['value'], $node->attr('native')['c']['value'] ?? null, "{$label} fallback value");
                },
            ],
            'table span helper constructors' => [
                'packet' => ['pandoc-api-version' => [1, 23, 1], 'meta' => [], 'blocks' => [$tableBlock]],
                'path' => [0, 0, 0, 0],
                'type' => 'table_cell',
                'constructor' => 'Cell',
                'native' => $tableCell,
                'text' => 'Cell summary',
                'assert' => static function (TestRunner $t, AstNode $node, string $label) use ($tableCell): void {
                    $t->same('right', $node->attr('align'), "{$label} cell alignment");
                    $t->same(2, $node->attr('rowspan'), "{$label} row span");
                    $t->same(3, $node->attr('colspan'), "{$label} col span");
                    $t->same($tableCell['c'][2], $node->attr('rowSpanNative'), "{$label} row span native");
                    $t->same($tableCell['c'][3], $node->attr('colSpanNative'), "{$label} col span native");
                },
            ],
        ];

        $nodeAt = static function (AstNode $document, array $path): AstNode {
            $node = $document;
            foreach ($path as $index) {
                $next = $node->children[$index] ?? null;
                $node = $next instanceof AstNode ? $next : new AstNode('missing');
            }

            return $node;
        };

        foreach ($cases as $caseName => $case) {
            $packet = $case['packet'];

            foreach ([
                'json' => (new PandocJsonReader())->readPacket($packet),
                'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
            ] as $source => $document) {
                $node = $nodeAt($document, $case['path']);
                $jsonPacket = (new PandocJsonWriter())->toArray($document);
                $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
                $label = "{$caseName} {$source}";

                $t->same($case['type'], $node->type, "{$label} node type");
                $t->same($case['constructor'], $node->attr('constructor'), "{$label} constructor attr");
                $t->same($case['native'], $node->attr('native'), "{$label} native payload");
                if (array_key_exists('text', $case)) {
                    $t->same($case['text'], $node->attr('text'), "{$label} text summary");
                }
                $t->same($packet['blocks'], $jsonPacket['blocks'], "{$label} JSON writer preserves constructors");
                $t->same($packet['blocks'], $nativePacket['blocks'], "{$label} native writer preserves constructors");

                $assert = $case['assert'] ?? null;
                if ($assert instanceof \Closure) {
                    $assert($t, $node, $label);
                }
            }
        }
    },
    'maps json native helper constructor variant completeness through reader writer stacks' => static function (TestRunner $t): void {
        $listCases = [
            'default list helpers' => [
                'styleNative' => ['t' => 'DefaultStyle'],
                'delimiterNative' => ['t' => 'DefaultDelim'],
                'style' => 'default',
                'delimiter' => 'default',
            ],
            'decimal period list helpers' => [
                'styleNative' => ['t' => 'Decimal'],
                'delimiterNative' => ['t' => 'Period'],
                'style' => 'decimal',
                'delimiter' => 'period',
            ],
            'example two-parens list helpers' => [
                'styleNative' => ['t' => 'Example'],
                'delimiterNative' => ['t' => 'TwoParens'],
                'style' => 'example',
                'delimiter' => 'two_parens',
            ],
            'lower-roman one-paren list helpers' => [
                'styleNative' => ['t' => 'LowerRoman'],
                'delimiterNative' => ['t' => 'OneParen'],
                'style' => 'lower_roman',
                'delimiter' => 'one_paren',
            ],
            'upper-roman period list helpers' => [
                'styleNative' => ['t' => 'UpperRoman'],
                'delimiterNative' => ['t' => 'Period'],
                'style' => 'upper_roman',
                'delimiter' => 'period',
            ],
            'lower-alpha default-delim list helpers' => [
                'styleNative' => ['t' => 'LowerAlpha'],
                'delimiterNative' => ['t' => 'DefaultDelim'],
                'style' => 'lower_alpha',
                'delimiter' => 'default',
            ],
            'upper-alpha one-paren list helpers' => [
                'styleNative' => ['t' => 'UpperAlpha'],
                'delimiterNative' => ['t' => 'OneParen'],
                'style' => 'upper_alpha',
                'delimiter' => 'one_paren',
            ],
        ];

        foreach ($listCases as $caseName => $case) {
            $packet = [
                'pandoc-api-version' => [1, 23, 1],
                'meta' => [],
                'blocks' => [
                    ['t' => 'OrderedList', 'c' => [
                        [3, $case['styleNative'], $case['delimiterNative']],
                        [[
                            ['t' => 'Plain', 'c' => [
                                ['t' => 'Str', 'c' => $caseName],
                            ]],
                        ]],
                    ]],
                ],
            ];

            foreach ([
                'json' => (new PandocJsonReader())->readPacket($packet),
                'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
            ] as $source => $document) {
                $list = $document->children[0];
                $jsonPacket = (new PandocJsonWriter())->toArray($document);
                $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
                $label = "{$caseName} {$source}";

                $t->same('ordered_list', $list->type, "{$label} shared AST list type");
                $t->same($case['style'], $list->attr('style'), "{$label} list style value");
                $t->same($case['delimiter'], $list->attr('delimiter'), "{$label} list delimiter value");
                $t->same($case['styleNative'], $list->attr('listStyleNative'), "{$label} list style native helper");
                $t->same($case['delimiterNative'], $list->attr('listDelimiterNative'), "{$label} list delimiter native helper");
                $t->same($packet['blocks'], $jsonPacket['blocks'], "{$label} JSON writer preserves list helpers");
                $t->same($packet['blocks'], $nativePacket['blocks'], "{$label} native writer preserves list helpers");
            }
        }

        $inlineHelperPacket = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Quoted', 'c' => [
                        ['t' => 'SingleQuote'],
                        [['t' => 'Str', 'c' => 'single']],
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Quoted', 'c' => [
                        ['t' => 'DoubleQuote'],
                        [['t' => 'Str', 'c' => 'double']],
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Math', 'c' => [
                        ['t' => 'InlineMath'],
                        'x + 1',
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Math', 'c' => [
                        ['t' => 'DisplayMath'],
                        'y = 2',
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Cite', 'c' => [
                        [
                            [
                                'citationId' => 'helper-normal',
                                'citationPrefix' => [],
                                'citationSuffix' => [],
                                'citationMode' => ['t' => 'NormalCitation'],
                                'citationNoteNum' => 1,
                                'citationHash' => 101,
                            ],
                            [
                                'citationId' => 'helper-author',
                                'citationPrefix' => [],
                                'citationSuffix' => [],
                                'citationMode' => ['t' => 'AuthorInText'],
                                'citationNoteNum' => 2,
                                'citationHash' => 202,
                            ],
                            [
                                'citationId' => 'helper-suppress',
                                'citationPrefix' => [],
                                'citationSuffix' => [],
                                'citationMode' => ['t' => 'SuppressAuthor'],
                                'citationNoteNum' => 3,
                                'citationHash' => 303,
                            ],
                        ],
                        [
                            ['t' => 'Str', 'c' => '@helper-normal'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '@helper-author'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '@helper-suppress'],
                        ],
                    ]],
                ]],
            ],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($inlineHelperPacket),
            'native' => (new NativeReader())->read(json_encode($inlineHelperPacket, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $paragraph = $document->children[0];
            $singleQuote = $paragraph->children[0];
            $doubleQuote = $paragraph->children[2];
            $inlineMath = $paragraph->children[4];
            $displayMath = $paragraph->children[6];
            $citationGroup = $paragraph->children[8];
            $citations = $citationGroup->children;
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
            $separatorType = $source === 'native' ? 'text' : 'space';

            $t->same(['quoted', $separatorType, 'quoted', $separatorType, 'math', $separatorType, 'math', $separatorType, 'citation_group'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), "{$source} inline helper variant shared AST types");
            $t->same(['single', 'double'], [$singleQuote->attr('kind'), $doubleQuote->attr('kind')], "{$source} quote helper variant values");
            $t->same(['SingleQuote', 'DoubleQuote'], [$singleQuote->attr('quoteTypeConstructor'), $doubleQuote->attr('quoteTypeConstructor')], "{$source} quote helper variant constructors");
            $t->same([['t' => 'SingleQuote'], ['t' => 'DoubleQuote']], [$singleQuote->attr('quoteTypeNative'), $doubleQuote->attr('quoteTypeNative')], "{$source} quote helper variant native payloads");
            $t->same([false, true], [$inlineMath->attr('display'), $displayMath->attr('display')], "{$source} math helper variant values");
            $t->same(['InlineMath', 'DisplayMath'], [$inlineMath->attr('mathTypeConstructor'), $displayMath->attr('mathTypeConstructor')], "{$source} math helper variant constructors");
            $t->same([['t' => 'InlineMath'], ['t' => 'DisplayMath']], [$inlineMath->attr('mathTypeNative'), $displayMath->attr('mathTypeNative')], "{$source} math helper variant native payloads");
            $t->same(['normal', 'author_in_text', 'suppress_author'], array_map(static fn (AstNode $citation): string => $citation->attr('mode'), $citations), "{$source} citation mode helper variant values");
            $t->same(['NormalCitation', 'AuthorInText', 'SuppressAuthor'], array_map(static fn (AstNode $citation): string => $citation->attr('citationModeConstructor'), $citations), "{$source} citation mode helper variant constructors");
            $t->same([
                ['t' => 'NormalCitation'],
                ['t' => 'AuthorInText'],
                ['t' => 'SuppressAuthor'],
            ], array_map(static fn (AstNode $citation): array => $citation->attr('citationModeNative'), $citations), "{$source} citation mode helper variant native payloads");
            $t->same($inlineHelperPacket['blocks'], $jsonPacket['blocks'], "{$source} JSON writer preserves inline helper variants");
            $t->same($inlineHelperPacket['blocks'], $nativePacket['blocks'], "{$source} native writer preserves inline helper variants");
        }

        $tablePacket = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [[
                't' => 'Table',
                'c' => [
                    ['variant-table', ['constructor-variant'], [['data-kind', 'helper-matrix']]],
                    ['t' => 'Caption', 'c' => [['t' => 'Nothing'], []]],
                    [
                        [
                            ['t' => 'AlignDefault'],
                            ['t' => 'ColWidthDefault'],
                        ],
                        [
                            ['t' => 'AlignLeft'],
                            ['t' => 'ColWidth', 'c' => 0.25],
                        ],
                        [
                            ['t' => 'AlignRight'],
                            ['t' => 'ColWidth', 'c' => [0.5]],
                        ],
                        [
                            ['t' => 'AlignCenter'],
                            ['t' => 'ColWidth', 'c' => 0.75],
                        ],
                    ],
                    ['t' => 'TableHead', 'c' => [
                        ['', [], []],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 4],
                                        [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Head']]]],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                    [[
                        't' => 'TableBody',
                        'c' => [
                            ['', [], []],
                            ['t' => 'RowHeadColumns', 'c' => [2]],
                            [[
                                't' => 'Row',
                                'c' => [
                                    ['', [], []],
                                    [
                                        ['t' => 'Cell', 'c' => [
                                            ['', [], []],
                                            ['t' => 'AlignRight'],
                                            ['t' => 'RowSpan', 'c' => [1]],
                                            ['t' => 'ColSpan', 'c' => [1]],
                                            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Subhead']]]],
                                        ]],
                                    ],
                                ],
                            ]],
                            [[
                                't' => 'Row',
                                'c' => [
                                    ['', [], []],
                                    [
                                        ['t' => 'Cell', 'c' => [
                                            ['', [], []],
                                            ['t' => 'AlignLeft'],
                                            ['t' => 'RowSpan', 'c' => 2],
                                            ['t' => 'ColSpan', 'c' => 2],
                                            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Body']]]],
                                        ]],
                                    ],
                                ],
                            ]],
                        ],
                    ]],
                    ['t' => 'TableFoot', 'c' => [
                        ['', [], []],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignCenter'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Foot']]]],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
            ]],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($tablePacket),
            'native' => (new NativeReader())->read(json_encode($tablePacket, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $table = $document->children[0];
            $body = $table->children[1];
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same('table', $table->type, "{$source} table helper matrix shared AST type");
            $t->same(['default', 'left', 'right', 'center'], $table->attr('alignments'), "{$source} table alignment variants");
            $t->same([null, 0.25, 0.5, 0.75], $table->attr('widths'), "{$source} table width variants");
            $t->same(['AlignDefault', 'AlignLeft', 'AlignRight', 'AlignCenter'], $table->attr('alignmentConstructors'), "{$source} table alignment constructors");
            $t->same(['ColWidthDefault', 'ColWidth', 'ColWidth', 'ColWidth'], $table->attr('columnWidthConstructors'), "{$source} table width constructors");
            $t->same(2, $body->attr('rowHeadColumns'), "{$source} table row-head helper value");
            $t->same(['t' => 'RowHeadColumns', 'c' => [2]], $body->attr('rowHeadColumnsNative'), "{$source} table row-head helper native");
            $t->same($tablePacket['blocks'], $jsonPacket['blocks'], "{$source} JSON writer preserves table helper variants");
            $t->same($tablePacket['blocks'], $nativePacket['blocks'], "{$source} native writer preserves table helper variants");
        }
    },
    'flushes mixed inline children inside block containers for json and native writers' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [
                'fixture' => 'lanes/pandoc/fixtures/wordpress-import-markdown.md:blockquotes-divs-footnotes',
            ],
        ], [
            new AstNode('blockquote', [], [
                new AstNode('text', ['text' => 'Reviewer checklist:']),
                new AstNode('code_block', [
                    'classes' => ['php'],
                    'text' => 'wp_update_post($post);',
                ]),
                new AstNode('ordered_list', [], [
                    new AstNode('list_item', ['text' => 'Confirm source quote']),
                    new AstNode('list_item', ['text' => 'Publish block version']),
                ]),
                new AstNode('text', ['text' => 'Nested reviewer approval stays attached.']),
            ]),
            new AstNode('div', ['classes' => ['legacy-import']], [
                new AstNode('text', ['text' => 'Migration audit']),
                new AstNode('bullet_list', [], [
                    new AstNode('list_item', ['text' => 'Preserve div-wrapped glossary notes']),
                ]),
                new AstNode('text', ['text' => 'Raw HTML boundary complete.']),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Footnote audit']),
                new AstNode('space'),
                new AstNode('note', [], [
                    new AstNode('text', ['text' => 'Source archive footnote keeps the reviewer trail.']),
                    new AstNode('code_block', ['text' => "do_action('import_note');"]),
                    new AstNode('text', ['text' => 'Confirm media IDs before publishing.']),
                ]),
            ]),
        ]);
        $plainText = static function (array $inlines): string {
            $text = '';
            foreach ($inlines as $inline) {
                $text .= match ($inline['t'] ?? '') {
                    'Str', 'Code', 'Math' => (string) ($inline['c'] ?? ''),
                    'Space', 'SoftBreak', 'LineBreak' => ' ',
                    default => '',
                };
            }

            return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        };
        $firstNodeOfType = static function (array $nodes, string $type): ?AstNode {
            foreach ($nodes as $node) {
                if ($node instanceof AstNode && $node->type === $type) {
                    return $node;
                }
            }

            return null;
        };

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($document),
            'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
        ] as $writer => $packet) {
            $quoteBlocks = $packet['blocks'][0]['c'];
            $divBlocks = $packet['blocks'][1]['c'][1];
            $noteInline = null;
            foreach ($packet['blocks'][2]['c'] as $inline) {
                if (($inline['t'] ?? null) === 'Note') {
                    $noteInline = $inline;
                    break;
                }
            }
            $t->true($noteInline !== null, "{$writer} paragraph includes note inline");
            $noteBlocks = is_array($noteInline['c'] ?? null) ? $noteInline['c'] : [];

            $t->same(['BlockQuote', 'Div', 'Para'], array_map(static fn (array $block): string => $block['t'], $packet['blocks']), "{$writer} top-level constructors");
            $t->same(['Plain', 'CodeBlock', 'OrderedList', 'Plain'], array_map(static fn (array $block): string => $block['t'], $quoteBlocks), "{$writer} blockquote mixed children become blocks");
            $t->same('Reviewer checklist:', $plainText($quoteBlocks[0]['c']), "{$writer} blockquote leading inline run flushed to Plain");
            $t->same('Nested reviewer approval stays attached.', $plainText($quoteBlocks[3]['c']), "{$writer} blockquote trailing inline run flushed to Plain");
            $t->same(['Plain', 'BulletList', 'Plain'], array_map(static fn (array $block): string => $block['t'], $divBlocks), "{$writer} div mixed children become blocks");
            $t->same('Migration audit', $plainText($divBlocks[0]['c']), "{$writer} div leading inline run flushed to Plain");
            $t->same('Raw HTML boundary complete.', $plainText($divBlocks[2]['c']), "{$writer} div trailing inline run flushed to Plain");
            $t->same(['Plain', 'CodeBlock', 'Plain'], array_map(static fn (array $block): string => $block['t'], $noteBlocks), "{$writer} note mixed children become blocks");
            $t->same('Source archive footnote keeps the reviewer trail.', $plainText($noteBlocks[0]['c']), "{$writer} note leading inline run flushed to Plain");
            $t->same('Confirm media IDs before publishing.', $plainText($noteBlocks[2]['c']), "{$writer} note trailing inline run flushed to Plain");
        }

        $jsonRoundTrip = (new PandocJsonReader())->readPacket((new PandocJsonWriter())->toArray($document));
        $nativeRoundTrip = (new NativeReader())->read((new NativeWriter())->write($document));
        $jsonNote = $firstNodeOfType($jsonRoundTrip->children[2]->children, 'note');
        $nativeNote = $firstNodeOfType($nativeRoundTrip->children[2]->children, 'note');

        $t->same(['plain', 'code_block', 'ordered_list', 'plain'], array_map(static fn (AstNode $block): string => $block->type, $jsonRoundTrip->children[0]->children));
        $t->same(['plain', 'bullet_list', 'plain'], array_map(static fn (AstNode $block): string => $block->type, $jsonRoundTrip->children[1]->children));
        $t->true($jsonNote instanceof AstNode, 'JSON round trip keeps note inline');
        $t->same(['plain', 'code_block', 'plain'], array_map(static fn (AstNode $block): string => $block->type, $jsonNote instanceof AstNode ? $jsonNote->children : []));
        $t->same(['plain', 'code_block', 'ordered_list', 'plain'], array_map(static fn (AstNode $block): string => $block->type, $nativeRoundTrip->children[0]->children));
        $t->same(['plain', 'bullet_list', 'plain'], array_map(static fn (AstNode $block): string => $block->type, $nativeRoundTrip->children[1]->children));
        $t->true($nativeNote instanceof AstNode, 'Native round trip keeps note inline');
        $t->same(['plain', 'code_block', 'plain'], array_map(static fn (AstNode $block): string => $block->type, $nativeNote instanceof AstNode ? $nativeNote->children : []));
    },
    'stress tests mixed metadata block containers through json and native writers' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [
                'review' => ['type' => 'map', 'items' => [
                    'inline' => ['type' => 'inlines', 'children' => [
                        new AstNode('span', [
                            'id' => 'meta-span',
                            'classes' => ['review-meta'],
                            'attributes' => ['data-source' => 'metadata'],
                        ], [
                            new AstNode('text', ['text' => 'MetaSpan']),
                        ]),
                        new AstNode('space'),
                        new AstNode('note', [], [
                            new AstNode('text', ['text' => 'NoteLead']),
                            new AstNode('blockquote', [], [
                                new AstNode('text', ['text' => 'NestedQuote']),
                            ]),
                            new AstNode('div', ['classes' => ['note-div']], [
                                new AstNode('text', ['text' => 'NestedDiv']),
                            ]),
                            new AstNode('text', ['text' => 'NoteTail']),
                        ]),
                    ]],
                    'body' => ['type' => 'blocks', 'children' => [
                        new AstNode('div', [
                            'id' => 'meta-div',
                            'classes' => ['review-body'],
                        ], [
                            new AstNode('text', ['text' => 'DivLead']),
                            new AstNode('blockquote', [], [
                                new AstNode('text', ['text' => 'DivQuote']),
                            ]),
                            new AstNode('text', ['text' => 'DivTail']),
                        ]),
                        new AstNode('blockquote', [], [
                            new AstNode('text', ['text' => 'QuoteLead']),
                            new AstNode('div', ['classes' => ['quote-div']], [
                                new AstNode('text', ['text' => 'QuoteDiv']),
                            ]),
                            new AstNode('text', ['text' => 'QuoteTail']),
                        ]),
                        new AstNode('table', [
                            'alignments' => ['left'],
                            'widths' => [0.5],
                        ], [
                            new AstNode('table_body', [], [
                                new AstNode('table_row', [], [
                                    new AstNode('table_cell', [], [
                                        new AstNode('text', ['text' => 'CellLead']),
                                        new AstNode('blockquote', [], [
                                            new AstNode('text', ['text' => 'CellQuote']),
                                        ]),
                                        new AstNode('div', ['classes' => ['cell-div']], [
                                            new AstNode('text', ['text' => 'CellDiv']),
                                        ]),
                                        new AstNode('text', ['text' => 'CellTail']),
                                    ]),
                                ]),
                            ]),
                        ]),
                    ]],
                ]],
            ],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'CitationFixture']),
                new AstNode('space'),
                new AstNode('citation_group', [], [
                    new AstNode('citation', [
                        'id' => 'source-a',
                        'prefix' => [new AstNode('text', ['text' => 'see'])],
                        'citationHash' => 10,
                    ]),
                    new AstNode('citation', [
                        'id' => 'source-b',
                        'mode' => 'author_in_text',
                    ]),
                    new AstNode('citation', [
                        'id' => 'source-c',
                        'mode' => 'suppress_author',
                        'suffix' => [
                            new AstNode('text', ['text' => 'p.']),
                            new AstNode('space'),
                            new AstNode('text', ['text' => '2']),
                        ],
                    ]),
                ]),
            ]),
        ]);
        $blockTypes = static fn (array $blocks): array => array_map(static fn (array $block): string => (string) ($block['t'] ?? ''), $blocks);
        $childTypes = static fn (AstNode $node): array => array_map(static fn (AstNode $child): string => $child->type, $node->children);
        $tableCellBlocks = static function (array $table): array {
            $body = $table['c'][4][0]['c'] ?? $table['c'][4][0];
            $row = $body[3][0]['c'] ?? $body[3][0];
            $cell = $row[1][0]['c'] ?? $row[1][0];

            return $cell[4];
        };
        $citationModes = static function (array $packet): array {
            $cites = array_values(array_filter(
                $packet['blocks'][0]['c'],
                static fn (array $inline): bool => ($inline['t'] ?? null) === 'Cite'
            ));

            return array_map(static fn (array $record): string => $record['citationMode']['t'], $cites[0]['c'][0] ?? []);
        };
        $firstChildOfType = static function (array $children, string $type): ?AstNode {
            foreach ($children as $child) {
                if ($child instanceof AstNode && $child->type === $type) {
                    return $child;
                }
            }

            return null;
        };

        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same($jsonPacket['meta'], $nativePacket['meta']);
        $t->same($jsonPacket['blocks'], $nativePacket['blocks']);

        foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $writer => $packet) {
            $review = $packet['meta']['review']['c'];
            $inlineMeta = $review['inline']['c'];
            $noteBlocks = $inlineMeta[2]['c'];
            $bodyBlocks = $review['body']['c'];
            $divBlocks = $bodyBlocks[0]['c'][1];
            $quoteBlocks = $bodyBlocks[1]['c'];
            $cellBlocks = $tableCellBlocks($bodyBlocks[2]);

            $t->same('MetaMap', $packet['meta']['review']['t'], "{$writer} review metadata is a MetaMap");
            $t->same('MetaInlines', $review['inline']['t'], "{$writer} inline metadata uses MetaInlines");
            $t->same(['Span', 'Space', 'Note'], array_map(static fn (array $inline): string => $inline['t'], $inlineMeta), "{$writer} metadata inline constructors");
            $t->same(['meta-span', ['review-meta'], [['data-source', 'metadata']]], $inlineMeta[0]['c'][0], "{$writer} metadata span attrs");
            $t->same(['Plain', 'BlockQuote', 'Div', 'Plain'], $blockTypes($noteBlocks), "{$writer} metadata note children are valid blocks");
            $t->same('NoteLead', $noteBlocks[0]['c'][0]['c'], "{$writer} note leading inline run flushed");
            $t->same('NoteTail', $noteBlocks[3]['c'][0]['c'], "{$writer} note trailing inline run flushed");
            $t->same('MetaBlocks', $review['body']['t'], "{$writer} block metadata uses MetaBlocks");
            $t->same(['Div', 'BlockQuote', 'Table'], $blockTypes($bodyBlocks), "{$writer} metadata body block constructors");
            $t->same(['Plain', 'BlockQuote', 'Plain'], $blockTypes($divBlocks), "{$writer} metadata div children are valid blocks");
            $t->same(['Plain', 'Div', 'Plain'], $blockTypes($quoteBlocks), "{$writer} metadata blockquote children are valid blocks");
            $t->same(['Plain', 'BlockQuote', 'Div', 'Plain'], $blockTypes($cellBlocks), "{$writer} metadata table cell children are valid blocks");
            $t->same('CellLead', $cellBlocks[0]['c'][0]['c'], "{$writer} table cell leading inline run flushed");
            $t->same('CellTail', $cellBlocks[3]['c'][0]['c'], "{$writer} table cell trailing inline run flushed");
            $t->same(['NormalCitation', 'AuthorInText', 'SuppressAuthor'], $citationModes($packet), "{$writer} cite fixture modes stay intact");
        }

        $jsonRoundTrip = (new PandocJsonReader())->readPacket($jsonPacket);
        $nativeRoundTrip = (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR));
        $jsonMeta = $jsonRoundTrip->attr('meta')['review']['items'];
        $jsonInlineMeta = $jsonMeta['inline']['children'];
        $jsonNote = $jsonInlineMeta[2];
        $jsonBodyBlocks = $jsonMeta['body']['children'];
        $jsonCell = $jsonBodyBlocks[2]->children[0]->children[0]->children[0];
        $nativeReview = $nativeRoundTrip->attr('meta')['review']['c'];

        $t->same(['span', 'space', 'note'], array_map(static fn (AstNode $child): string => $child->type, $jsonInlineMeta), 'JSON reader keeps metadata inline children');
        $t->same(['plain', 'blockquote', 'div', 'plain'], $childTypes($jsonNote), 'JSON reader keeps metadata note block boundaries');
        $t->same(['div', 'blockquote', 'table'], array_map(static fn (AstNode $block): string => $block->type, $jsonBodyBlocks), 'JSON reader keeps metadata body blocks');
        $t->same(['plain', 'blockquote', 'plain'], $childTypes($jsonBodyBlocks[0]), 'JSON reader keeps metadata div block boundaries');
        $t->same(['plain', 'div', 'plain'], $childTypes($jsonBodyBlocks[1]), 'JSON reader keeps metadata blockquote block boundaries');
        $t->same(['plain', 'blockquote', 'div', 'plain'], $childTypes($jsonCell), 'JSON reader keeps metadata table-cell block boundaries');
        $t->same('citation_group', $firstChildOfType($jsonRoundTrip->children[0]->children, 'citation_group')?->type, 'JSON reader keeps cite fixture');
        $t->same(['MetaInlines', 'MetaBlocks'], [$nativeReview['inline']['t'], $nativeReview['body']['t']], 'Native reader preserves metadata constructor wrappers');
        $t->same(['Plain', 'BlockQuote', 'Div', 'Plain'], $blockTypes($nativeReview['inline']['c'][2]['c']), 'Native reader keeps metadata note native block list');
        $t->same(['Plain', 'BlockQuote', 'Div', 'Plain'], $blockTypes($tableCellBlocks($nativeReview['body']['c'][2])), 'Native reader keeps metadata table-cell native block list');
        $t->same('citation_group', $firstChildOfType($nativeRoundTrip->children[0]->children, 'citation_group')?->type, 'Native reader keeps cite fixture');
    },
    'renders pandoc div attributes through wordpress html writer sanitizer' => static function (TestRunner $t): void {
        $packet = [
            'blocks' => [
                ['t' => 'Div', 'c' => [
                    [
                        'review-div',
                        ['html-writer', 'wp-review'],
                        [
                            ['data-review', 'attributes'],
                            ['aria-label', 'Reviewer region'],
                            ['xml:lang', 'en-GB'],
                            ['style', 'color:red'],
                            ['onclick', 'alert(1)'],
                            ['onmouseover', 'blocked'],
                        ],
                    ],
                    [
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Str', 'c' => 'Wrapped'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'content'],
                        ]],
                    ],
                ]],
            ],
        ];

        $document = (new PandocJsonReader())->readPacket($packet);
        $div = $document->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('div', $div->type);
        $t->same('review-div', $div->attr('id'));
        $t->same(['html-writer', 'wp-review'], $div->attr('classes'));
        $t->same('en-GB', $div->attr('attributes')['xml:lang'] ?? null);
        $t->contains('<div id="review-div" class="html-writer wp-review" data-review="attributes" aria-label="Reviewer region" xml:lang="en-GB"><p>Wrapped content</p></div>', $blocks);
        $t->true(!str_contains($blocks, 'style='), 'Unsafe style attributes must not render on Pandoc Div output');
        $t->true(!str_contains($blocks, 'onclick='), 'Unsafe event handler attributes must not render on Pandoc Div output');
        $t->true(!str_contains($blocks, 'onmouseover='), 'Unsafe mouse event attributes must not render on Pandoc Div output');
    },
    'renders pandoc json figure attributes through wordpress html writer sanitizer' => static function (TestRunner $t): void {
        $figureBlock = ['t' => 'Figure', 'c' => [
            [
                'json-figure',
                ['wp-import-figure'],
                [
                    ['data-review', 'figure'],
                    ['xml:lang', 'fr-CA'],
                    ['title', 'Escaped "figure" title'],
                    ['latex-placement', 'htbp'],
                    ['onclick', 'alert(1)'],
                    ['style', 'display:none'],
                ],
            ],
            [
                [
                    ['t' => 'Str', 'c' => 'Short'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'figure'],
                ],
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Reviewer'],
                        ['t' => 'Space'],
                        ['t' => 'Emph', 'c' => [
                            ['t' => 'Str', 'c' => 'figure'],
                        ]],
                    ]],
                ],
            ],
            [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Image', 'c' => [
                        ['', ['review-image'], [['data-image', 'source']]],
                        [
                            ['t' => 'Str', 'c' => 'Review'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'image'],
                        ],
                        ['media/review.png', 'Figure image'],
                    ]],
                ]],
            ],
        ]];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$figureBlock],
        ];

        $reader = new PandocJsonReader();
        $writer = new PandocJsonWriter();
        $document = $reader->readPacket($packet);
        $figure = $document->children[0];
        $image = $figure->children[0];
        $encoded = $writer->toArray($document);
        $roundTrip = $reader->readPacket($encoded)->children[0];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('figure', $figure->type);
        $t->same('json-figure', $figure->attr('id'));
        $t->same(['wp-import-figure'], $figure->attr('classes'));
        $t->same('fr-CA', $figure->attr('attributes')['xml:lang'] ?? null);
        $t->same('Reviewer figure', $figure->attr('caption'));
        $t->same('Short figure', $figure->attr('shortCaption'));
        $t->same('image', $image->type);
        $t->same(['review-image'], $image->attr('classes'));
        $t->same('Figure', $encoded['blocks'][0]['t']);
        $t->same($figureBlock['c'][0], $encoded['blocks'][0]['c'][0]);
        $t->same('Short', $encoded['blocks'][0]['c'][1][0][0]['c']);
        $t->same('Reviewer', $encoded['blocks'][0]['c'][1][1][0]['c'][0]['c']);
        $t->same('Para', $encoded['blocks'][0]['c'][2][0]['t']);
        $t->same('Reviewer figure', $roundTrip->attr('caption'));
        $t->contains(
            '<figure class="wp-block-image wp-import-figure" id="json-figure" data-review="figure" xml:lang="fr-CA" title="Escaped &quot;figure&quot; title" data-pandoc-latex-placement="htbp">',
            $blocks
        );
        $t->contains('<img src="media/review.png" alt="Review image" title="Figure image" class="review-image" data-image="source"/>', $blocks);
        $t->contains('<figcaption>Reviewer figure</figcaption>', $blocks);
        $t->true(!str_contains($blocks, 'onclick'), 'Unsafe event handlers must not render on Pandoc Figure output');
        $t->true(!str_contains($blocks, 'style="display:none"'), 'Unsafe style attributes must not render on Pandoc Figure output');
    },
    'round trips pandoc json figure caption metadata through shared figure ast' => static function (TestRunner $t): void {
        $figureBlock = [
            't' => 'Figure',
            'c' => [
                ['json-figure', ['wp-import'], [['data-source', 'json-filter']]],
                ['t' => 'Caption', 'c' => [
                    ['t' => 'ShortCaption', 'c' => [[
                        ['t' => 'Str', 'c' => 'Short'],
                        ['t' => 'Space'],
                        ['t' => 'Strong', 'c' => [
                            ['t' => 'Str', 'c' => 'figure'],
                        ]],
                    ]]],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Long'],
                            ['t' => 'Space'],
                            ['t' => 'Emph', 'c' => [
                                ['t' => 'Str', 'c' => 'caption'],
                            ]],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'source'],
                        ]],
                    ],
                ]],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Image', 'c' => [
                            ['', ['hero-image'], [['data-source', 'media-bag']]],
                            [
                                ['t' => 'Str', 'c' => 'Alt'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'text'],
                            ],
                            ['media/hero.png', 'Hero title'],
                        ]],
                    ]],
                ],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$figureBlock],
        ];

        $reader = new PandocJsonReader();
        $writer = new PandocJsonWriter();
        $document = $reader->readPacket($packet);
        $figure = $document->children[0];
        $image = $figure->children[0];
        $captionInlines = $figure->attr('captionInlines');
        $shortCaptionInlines = $figure->attr('shortCaptionInlines');
        $encoded = $writer->toArray($document);
        $roundTrip = $reader->readPacket($encoded);
        $blocks = (new WordPressBlockWriter())->write($document);
        $generated = $writer->toArray(new AstNode('document', [], [
            new AstNode('figure', [
                'caption' => 'Generated caption',
                'shortCaption' => 'Generated short',
                'id' => 'generated-figure',
                'classes' => ['wp-import'],
            ], [
                new AstNode('image', [
                    'url' => 'media/generated.png',
                    'title' => 'Generated title',
                    'alt' => 'Generated alt',
                ]),
            ]),
        ]));
        $generatedRoundTrip = $reader->readPacket($generated);

        $t->same('figure', $figure->type);
        $t->same('json-figure', $figure->attr('id'));
        $t->same(['wp-import'], $figure->attr('classes'));
        $t->same(['data-source' => 'json-filter'], $figure->attr('attributes'));
        $t->same('Long caption source', $figure->attr('caption'));
        $t->same('Short figure', $figure->attr('shortCaption'));
        $t->same(true, is_array($captionInlines));
        $t->same(['text', 'space', 'emph', 'space', 'text'], array_map(static fn (AstNode $node): string => $node->type, $captionInlines));
        $t->same(true, is_array($shortCaptionInlines));
        $t->same(['text', 'space', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $shortCaptionInlines));
        $t->same('image', $image->type);
        $t->same('media/hero.png', $image->attr('url'));
        $t->same('Hero title', $image->attr('title'));
        $t->same('Alt text', $image->attr('alt'));
        $t->same(['hero-image'], $image->attr('classes'));
        $t->same(['data-source' => 'media-bag'], $image->attr('attributes'));
        $t->same('Figure', $encoded['blocks'][0]['t']);
        $t->same($figureBlock['c'][0], $encoded['blocks'][0]['c'][0]);
        $t->same($figureBlock['c'][1], $encoded['blocks'][0]['c'][1]);
        $t->same('Short', $encoded['blocks'][0]['c'][1]['c'][0]['c'][0][0]['c']);
        $t->same('Long', $encoded['blocks'][0]['c'][1]['c'][1][0]['c'][0]['c']);
        $t->same('Para', $encoded['blocks'][0]['c'][2][0]['t']);
        $t->same('Image', $encoded['blocks'][0]['c'][2][0]['c'][0]['t']);
        $t->same('Alt', $encoded['blocks'][0]['c'][2][0]['c'][0]['c'][1][0]['c']);
        $t->same('figure', $roundTrip->children[0]->type);
        $t->same('Alt text', $roundTrip->children[0]->children[0]->attr('alt'));
        $t->contains('<figure class="wp-block-image wp-import" id="json-figure" data-source="json-filter"><img src="media/hero.png" alt="Alt text" title="Hero title" class="hero-image" data-source="media-bag"/><figcaption>Long caption source</figcaption></figure>', $blocks);
        $t->same('Figure', $generated['blocks'][0]['t']);
        $t->same('Caption', $generated['blocks'][0]['c'][1]['t']);
        $t->same('Just', $generated['blocks'][0]['c'][1]['c'][0]['t']);
        $t->same('ShortCaption', $generated['blocks'][0]['c'][1]['c'][0]['c']['t']);
        $t->same('Generated', $generated['blocks'][0]['c'][1]['c'][0]['c']['c'][0][0]['c']);
        $t->same('Generated', $generated['blocks'][0]['c'][1]['c'][1][0]['c'][0]['c']);
        $t->same('Generated', $generated['blocks'][0]['c'][2][0]['c'][0]['c'][1][0]['c']);
        $t->same('generated-figure', $generatedRoundTrip->children[0]->attr('id'));
        $t->same('Generated caption', $generatedRoundTrip->children[0]->attr('caption'));
        $t->same('Generated short', $generatedRoundTrip->children[0]->attr('shortCaption'));
        $t->same('Generated alt', $generatedRoundTrip->children[0]->children[0]->attr('alt'));
    },
    'normalizes legacy simple figure image paragraphs through json and native readers' => static function (TestRunner $t): void {
        $legacyImage = [
            't' => 'Image',
            'c' => [
                ['legacy-figure-image', ['asset'], [['data-source', 'legacy']]],
                [
                    ['t' => 'Str', 'c' => 'Legacy'],
                    ['t' => 'Space'],
                    ['t' => 'Emph', 'c' => [
                        ['t' => 'Str', 'c' => 'caption'],
                    ]],
                ],
                ['media/legacy.png', 'fig:Legacy title'],
            ],
            'reviewQueue' => 'legacy-image-source',
        ];
        $legacyParagraph = [
            't' => 'Para',
            'c' => [$legacyImage],
            'reviewQueue' => 'legacy-simple-figure-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$legacyParagraph],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $figure = $document->children[0];
            $image = $figure->children[0];
            $captionInlines = $figure->attr('captionInlines');
            $captionTypes = $source === 'native' ? ['text', 'emph'] : ['text', 'space', 'emph'];

            $t->same('figure', $figure->type, "{$source} legacy simple figure normalizes to figure");
            $t->same('Para', $figure->attr('constructor'), "{$source} retains source paragraph constructor");
            $t->same($legacyParagraph, $figure->attr('native'), "{$source} retains source paragraph native payload");
            $t->same('Legacy caption', $figure->attr('caption'), "{$source} maps image label as figure caption");
            $t->same(true, is_array($captionInlines), "{$source} exposes caption inlines");
            $t->same($captionTypes, array_map(static fn (AstNode $node): string => $node->type, $captionInlines), "{$source} preserves formatted caption inlines");
            $t->same('image', $image->type, "{$source} keeps image child");
            $t->same('Legacy caption', $image->attr('alt'), "{$source} keeps image alt text");
            $t->same('Legacy title', $image->attr('title'), "{$source} strips legacy fig title marker from shared image title");
            $t->same(['media/legacy.png', 'fig:Legacy title'], $image->attr('targetNative'), "{$source} keeps source target tuple");

            foreach ([
                'json writer' => (new PandocJsonWriter())->toArray($document),
                'native writer' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($legacyParagraph, $encoded['blocks'][0], "{$source} {$writer} preserves unchanged legacy simple figure payload");
            }
        }
    },
    'reads table just and figure nothing short caption constructors' => static function (TestRunner $t): void {
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['maybe-table', ['json-native'], []],
                ['t' => 'Caption', 'c' => [
                    ['t' => 'Just', 'c' => ['t' => 'ShortCaption', 'c' => [[
                        ['t' => 'Str', 'c' => 'Review'],
                        ['t' => 'Space'],
                        ['t' => 'Code', 'c' => [['', ['short-code'], [['data-kind', 'caption']]], 'Q1']],
                    ]]]],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Long'],
                            ['t' => 'Space'],
                            ['t' => 'Strong', 'c' => [
                                ['t' => 'Str', 'c' => 'caption'],
                            ]],
                        ]],
                    ],
                ]],
                [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [['t' => 'TableBody', 'c' => [
                    ['', [], []],
                    ['t' => 'RowHeadColumns', 'c' => 0],
                    [],
                    [['t' => 'Row', 'c' => [
                        ['', [], []],
                        [['t' => 'Cell', 'c' => [
                            ['', [], []],
                            ['t' => 'AlignDefault'],
                            ['t' => 'RowSpan', 'c' => 1],
                            ['t' => 'ColSpan', 'c' => 1],
                            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Ready']]]],
                        ]]],
                    ]]],
                ]]],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
        ];
        $figureBlock = [
            't' => 'Figure',
            'c' => [
                ['maybe-figure', [], []],
                ['t' => 'Caption', 'c' => [
                    ['t' => 'Nothing'],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Figure'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'caption'],
                        ]],
                    ],
                ]],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Image', 'c' => [
                            ['', [], []],
                            [['t' => 'Str', 'c' => 'Maybe'], ['t' => 'Space'], ['t' => 'Str', 'c' => 'image']],
                            ['media/maybe.png', 'Maybe title'],
                        ]],
                    ]],
                ],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock, $figureBlock],
        ];
        $document = (new PandocJsonReader())->readPacket($packet);
        $nativeDocument = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR));
        $encoded = (new PandocJsonWriter())->toArray($document);
        $nativeEncoded = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $table = $document->children[0];
        $figure = $document->children[1];
        $nativeTable = $nativeDocument->children[0];
        $nativeFigure = $nativeDocument->children[1];
        $tableShortCaptionInlines = $table->attr('shortCaptionInlines');

        $t->same('table', $table->type);
        $t->same('Caption', $table->attr('captionConstructor'));
        $t->same($tableBlock['c'][1], $table->attr('captionNative'));
        $t->same('Just', $table->attr('shortCaptionMaybeConstructor'));
        $t->same($tableBlock['c'][1]['c'][0], $table->attr('shortCaptionMaybeNative'));
        $t->same('ShortCaption', $table->attr('shortCaptionConstructor'));
        $t->same($tableBlock['c'][1]['c'][0]['c'], $table->attr('shortCaptionNative'));
        $t->same('Review Q1', $table->attr('shortCaption'));
        $t->same('Long caption', $table->attr('caption'));
        $t->same(['text', 'space', 'code'], array_map(static fn (AstNode $node): string => $node->type, $tableShortCaptionInlines));
        $t->same(['short-code'], $tableShortCaptionInlines[2]->attr('classes'));
        $t->same(['data-kind' => 'caption'], $tableShortCaptionInlines[2]->attr('attributes'));
        $t->same('figure', $figure->type);
        $t->same('Caption', $figure->attr('captionConstructor'));
        $t->same($figureBlock['c'][1], $figure->attr('captionNative'));
        $t->same('Nothing', $figure->attr('shortCaptionMaybeConstructor'));
        $t->same($figureBlock['c'][1]['c'][0], $figure->attr('shortCaptionMaybeNative'));
        $t->same(null, $figure->attr('shortCaptionConstructor'));
        $t->same('Figure caption', $figure->attr('caption'));
        $t->same('', $figure->attr('shortCaption', ''));
        $t->same('image', $figure->children[0]->type);
        $t->same('Maybe image', $figure->children[0]->attr('alt'));
        $t->same('Caption', $nativeTable->attr('captionConstructor'));
        $t->same($tableBlock['c'][1], $nativeTable->attr('captionNative'));
        $t->same('Just', $nativeTable->attr('shortCaptionMaybeConstructor'));
        $t->same($tableBlock['c'][1]['c'][0], $nativeTable->attr('shortCaptionMaybeNative'));
        $t->same('ShortCaption', $nativeTable->attr('shortCaptionConstructor'));
        $t->same($tableBlock['c'][1]['c'][0]['c'], $nativeTable->attr('shortCaptionNative'));
        $t->same('Caption', $nativeFigure->attr('captionConstructor'));
        $t->same($figureBlock['c'][1], $nativeFigure->attr('captionNative'));
        $t->same('Nothing', $nativeFigure->attr('shortCaptionMaybeConstructor'));
        $t->same($figureBlock['c'][1]['c'][0], $nativeFigure->attr('shortCaptionMaybeNative'));
        $t->same(null, $nativeFigure->attr('shortCaptionConstructor'));
        $t->same('Table', $encoded['blocks'][0]['t']);
        $t->same($tableBlock['c'][1], $encoded['blocks'][0]['c'][1]);
        $t->same($figureBlock['c'][1], $encoded['blocks'][1]['c'][1]);
        $t->same($tableBlock['c'][1], $nativeEncoded['blocks'][0]['c'][1]);
        $t->same($figureBlock['c'][1], $nativeEncoded['blocks'][1]['c'][1]);
        $t->same('Review', $encoded['blocks'][0]['c'][1]['c'][0]['c']['c'][0][0]['c']);
        $t->same('Code', $encoded['blocks'][0]['c'][1]['c'][0]['c']['c'][0][2]['t']);
        $t->same(['t' => 'Nothing'], $encoded['blocks'][1]['c'][1]['c'][0]);
        $t->same('Figure caption', (new PandocJsonReader())->readPacket($encoded)->children[1]->attr('caption'));

        $editedTable = new AstNode(
            'table',
            array_replace($table->attrs, [
                'shortCaption' => 'Edited queue',
                'shortCaptionInlines' => [new AstNode('text', ['text' => 'Edited']), new AstNode('space'), new AstNode('text', ['text' => 'queue'])],
            ]),
            $table->children
        );
        $editedDocument = new AstNode('document', $document->attrs, [$editedTable]);
        $editedJson = (new PandocJsonWriter())->toArray($editedDocument);
        $editedNative = json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same('Caption', $editedJson['blocks'][0]['c'][1]['t']);
        $t->same('Just', $editedJson['blocks'][0]['c'][1]['c'][0]['t']);
        $t->same('ShortCaption', $editedJson['blocks'][0]['c'][1]['c'][0]['c']['t']);
        $t->same('Edited', $editedJson['blocks'][0]['c'][1]['c'][0]['c']['c'][0][0]['c']);
        $t->same('Caption', $editedNative['blocks'][0]['c'][1]['t']);
        $t->same('Just', $editedNative['blocks'][0]['c'][1]['c'][0]['t']);
        $t->same('ShortCaption', $editedNative['blocks'][0]['c'][1]['c'][0]['c']['t']);
        $t->same('Edited', $editedNative['blocks'][0]['c'][1]['c'][0]['c']['c'][0][0]['c']);
    },
    'preserves tagged pandoc caption helper constructors after caption edits' => static function (TestRunner $t): void {
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['caption-table', [], []],
                ['t' => 'Caption', 'c' => [
                    ['t' => 'Just', 'c' => ['t' => 'ShortCaption', 'c' => [[
                        ['t' => 'Str', 'c' => 'Short'],
                    ]]]],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Original'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'table'],
                        ]],
                    ],
                ]],
                [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [['t' => 'TableBody', 'c' => [
                    ['', [], []],
                    ['t' => 'RowHeadColumns', 'c' => 0],
                    [],
                    [['t' => 'Row', 'c' => [
                        ['', [], []],
                        [['t' => 'Cell', 'c' => [
                            ['', [], []],
                            ['t' => 'AlignDefault'],
                            ['t' => 'RowSpan', 'c' => 1],
                            ['t' => 'ColSpan', 'c' => 1],
                            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Cell']]]],
                        ]]],
                    ]]],
                ]]],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
        ];
        $figureBlock = [
            't' => 'Figure',
            'c' => [
                ['caption-figure', [], []],
                ['t' => 'Caption', 'c' => [
                    ['t' => 'Nothing'],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Original'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'figure'],
                        ]],
                    ],
                ]],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Image', 'c' => [
                            ['', [], []],
                            [['t' => 'Str', 'c' => 'Figure']],
                            ['media/caption.png', 'Caption title'],
                        ]],
                    ]],
                ],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock, $figureBlock],
        ];
        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $figure = $document->children[1];
            $editedTable = new AstNode('table', array_replace($table->attrs, [
                'caption' => 'Edited table',
                'captionBlocks' => [new AstNode('plain', [], [
                    new AstNode('text', ['text' => 'Edited']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'table']),
                ])],
            ]), $table->children);
            $editedFigure = new AstNode('figure', array_replace($figure->attrs, [
                'shortCaption' => 'Figure short',
                'shortCaptionInlines' => [
                    new AstNode('text', ['text' => 'Figure']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'short']),
                ],
            ]), $figure->children);
            $editedDocument = new AstNode('document', $document->attrs, [$editedTable, $editedFigure]);
            $packets = [
                "{$source} json writer" => (new PandocJsonWriter())->toArray($editedDocument),
                "{$source} native writer" => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ];

            foreach ($packets as $label => $encoded) {
                $tableCaption = $encoded['blocks'][0]['c'][1];
                $figureCaption = $encoded['blocks'][1]['c'][1];

                $t->same('Caption', $tableCaption['t'], "{$label} table caption constructor");
                $t->same('Just', $tableCaption['c'][0]['t'], "{$label} table short maybe constructor");
                $t->same('ShortCaption', $tableCaption['c'][0]['c']['t'], "{$label} table short caption constructor");
                $t->same('Short', $tableCaption['c'][0]['c']['c'][0][0]['c'], "{$label} table preserved short caption text");
                $t->same('Edited', $tableCaption['c'][1][0]['c'][0]['c'], "{$label} table edited long caption text");
                $t->same('Caption', $figureCaption['t'], "{$label} figure caption constructor");
                $t->same('Just', $figureCaption['c'][0]['t'], "{$label} figure short maybe constructor");
                $t->same('ShortCaption', $figureCaption['c'][0]['c']['t'], "{$label} figure short caption constructor");
                $t->same('Figure', $figureCaption['c'][0]['c']['c'][0][0]['c'], "{$label} figure new short caption text");
                $t->same('Original', $figureCaption['c'][1][0]['c'][0]['c'], "{$label} figure preserved long caption text");
            }
        }
    },
    'preserves list wrapped short caption maybe constructors after caption edits' => static function (TestRunner $t): void {
        $shortCaption = ['t' => 'ShortCaption', 'c' => [[
            ['t' => 'Str', 'c' => 'Short'],
        ]], 'reviewQueue' => 'short-caption-source'];
        $shortMaybe = ['t' => 'Just', 'c' => [$shortCaption], 'reviewQueue' => 'short-maybe-source'];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['wrapped-short-table', ['json-native'], []],
                ['t' => 'Caption', 'c' => [
                    $shortMaybe,
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Original'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'caption'],
                        ]],
                    ],
                ], 'reviewQueue' => 'caption-source'],
                [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [['t' => 'TableBody', 'c' => [
                    ['', [], []],
                    ['t' => 'RowHeadColumns', 'c' => 0],
                    [],
                    [],
                ]]],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];
        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];

            $t->same('Just', $table->attr('shortCaptionMaybeConstructor'), "{$source} short caption maybe constructor");
            $t->same($shortMaybe, $table->attr('shortCaptionMaybeNative'), "{$source} short caption maybe native payload");
            $t->same('ShortCaption', $table->attr('shortCaptionConstructor'), "{$source} short caption constructor");
            $t->same($shortCaption, $table->attr('shortCaptionNative'), "{$source} short caption native payload");
            $t->same('Short', $table->attr('shortCaption'), "{$source} short caption text");

            $editedLongTable = new AstNode('table', array_replace($table->attrs, [
                'caption' => 'Edited caption',
                'captionBlocks' => [new AstNode('plain', [], [
                    new AstNode('text', ['text' => 'Edited']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'caption']),
                ])],
            ]), $table->children);
            $editedShortTable = new AstNode('table', array_replace($table->attrs, [
                'shortCaption' => 'Edited short',
                'shortCaptionInlines' => [
                    new AstNode('text', ['text' => 'Edited']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'short']),
                ],
            ]), $table->children);

            foreach ([
                "{$source} json long edit" => (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [$editedLongTable])),
                "{$source} native long edit" => json_decode((new NativeWriter())->write(new AstNode('document', $document->attrs, [$editedLongTable])), true, 512, JSON_THROW_ON_ERROR),
            ] as $label => $encoded) {
                $caption = $encoded['blocks'][0]['c'][1];
                $shortMaybePayload = $caption['c'][0];

                $t->same('Caption', $caption['t'], "{$label} caption constructor");
                $t->same('Just', $shortMaybePayload['t'], "{$label} short maybe constructor");
                $t->same(true, array_is_list($shortMaybePayload['c']), "{$label} short maybe keeps list wrapper");
                $t->same('short-maybe-source', $shortMaybePayload['reviewQueue'], "{$label} short maybe sidecar preserved");
                $t->same('ShortCaption', $shortMaybePayload['c'][0]['t'], "{$label} short caption constructor");
                $t->same('short-caption-source', $shortMaybePayload['c'][0]['reviewQueue'], "{$label} short caption sidecar preserved");
                $t->same('Short', $shortMaybePayload['c'][0]['c'][0][0]['c'], "{$label} short caption text preserved");
                $t->same('Edited', $caption['c'][1][0]['c'][0]['c'], "{$label} long caption regenerated");
            }

            foreach ([
                "{$source} json short edit" => (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [$editedShortTable])),
                "{$source} native short edit" => json_decode((new NativeWriter())->write(new AstNode('document', $document->attrs, [$editedShortTable])), true, 512, JSON_THROW_ON_ERROR),
            ] as $label => $encoded) {
                $shortMaybePayload = $encoded['blocks'][0]['c'][1]['c'][0];

                $t->same('Just', $shortMaybePayload['t'], "{$label} short maybe constructor");
                $t->same(true, array_is_list($shortMaybePayload['c']), "{$label} short maybe keeps list wrapper");
                $t->same('ShortCaption', $shortMaybePayload['c'][0]['t'], "{$label} short caption constructor");
                $t->same('Edited', $shortMaybePayload['c'][0]['c'][0][0]['c'], "{$label} edited short caption first token");
                $t->same('short', $shortMaybePayload['c'][0]['c'][0][2]['c'], "{$label} edited short caption last token");
                $t->same(false, array_key_exists('reviewQueue', $shortMaybePayload['c'][0]), "{$label} edited short caption drops stale sidecar");
            }
        }
    },
    'preserves single wrapped caption tuple constructors through json and native readers' => static function (TestRunner $t): void {
        $shortCaption = ['t' => 'ShortCaption', 'c' => [[
            ['t' => 'Str', 'c' => 'Wrapped'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'short'],
        ]], 'reviewQueue' => 'wrapped-short-caption-source'];
        $shortMaybe = ['t' => 'Just', 'c' => $shortCaption, 'reviewQueue' => 'wrapped-short-maybe-source'];
        $tableLongBlock = ['t' => 'Plain', 'c' => [
            ['t' => 'Str', 'c' => 'Wrapped'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'table'],
        ], 'reviewQueue' => 'wrapped-table-long-source'];
        $figureLongBlock = ['t' => 'Plain', 'c' => [
            ['t' => 'Str', 'c' => 'Wrapped'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'figure'],
        ], 'reviewQueue' => 'wrapped-figure-long-source'];
        $tableCaption = ['t' => 'Caption', 'c' => [[$shortMaybe, [$tableLongBlock]]], 'reviewQueue' => 'wrapped-table-caption-source'];
        $figureCaption = ['t' => 'Caption', 'c' => [[['t' => 'Nothing', 'reviewQueue' => 'wrapped-figure-short-source'], [$figureLongBlock]]], 'reviewQueue' => 'wrapped-figure-caption-source'];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['wrapped-caption-table', ['json-native'], []],
                $tableCaption,
                [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [['t' => 'TableBody', 'c' => [
                    ['', [], []],
                    ['t' => 'RowHeadColumns', 'c' => 0],
                    [],
                    [],
                ]]],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
        ];
        $figureBlock = [
            't' => 'Figure',
            'c' => [
                ['wrapped-caption-figure', ['json-native'], []],
                $figureCaption,
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Image', 'c' => [
                            ['', [], []],
                            [['t' => 'Str', 'c' => 'Figure']],
                            ['media/wrapped-caption.png', 'Wrapped figure'],
                        ]],
                    ]],
                ],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock, $figureBlock],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $table = $document->children[0];
            $figure = $document->children[1];

            $t->same($tableCaption, $table->attr('captionNative'), "{$source} table keeps wrapped caption native payload");
            $t->same($figureCaption, $figure->attr('captionNative'), "{$source} figure keeps wrapped caption native payload");
            $t->same('Wrapped short', $table->attr('shortCaption'), "{$source} table short caption text");
            $t->same('Wrapped table', $table->attr('caption'), "{$source} table long caption text");
            $t->same(null, $figure->attr('shortCaption'), "{$source} figure has no short caption");
            $t->same('Wrapped figure', $figure->attr('caption'), "{$source} figure long caption text");

            foreach ([
                "{$source} json unchanged" => (new PandocJsonWriter())->toArray($document),
                "{$source} native unchanged" => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $label => $encoded) {
                $t->same($tableCaption, $encoded['blocks'][0]['c'][1], "{$label} preserves wrapped table caption tuple");
                $t->same($figureCaption, $encoded['blocks'][1]['c'][1], "{$label} preserves wrapped figure caption tuple");
            }

            $editedTable = new AstNode('table', array_replace($table->attrs, [
                'caption' => 'Edited table',
                'captionBlocks' => [new AstNode('plain', [], [
                    new AstNode('text', ['text' => 'Edited']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'table']),
                ])],
            ]), $table->children);
            $editedFigure = new AstNode('figure', array_replace($figure->attrs, [
                'shortCaption' => 'Edited figure',
                'shortCaptionInlines' => [
                    new AstNode('text', ['text' => 'Edited']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'figure']),
                ],
            ]), $figure->children);

            foreach ([
                "{$source} json edited" => (new PandocJsonWriter())->toArray(new AstNode('document', $document->attrs, [$editedTable, $editedFigure])),
                "{$source} native edited" => json_decode((new NativeWriter())->write(new AstNode('document', $document->attrs, [$editedTable, $editedFigure])), true, 512, JSON_THROW_ON_ERROR),
            ] as $label => $encoded) {
                $editedTableCaption = $encoded['blocks'][0]['c'][1];
                $editedFigureCaption = $encoded['blocks'][1]['c'][1];

                $t->same('Caption', $editedTableCaption['t'], "{$label} edited table caption constructor");
                $t->same(true, array_is_list($editedTableCaption['c']) && count($editedTableCaption['c']) === 1, "{$label} edited table keeps wrapped caption tuple");
                $t->same(false, array_key_exists('reviewQueue', $editedTableCaption), "{$label} edited table drops stale caption sidecar");
                $t->same('wrapped-short-maybe-source', $editedTableCaption['c'][0][0]['reviewQueue'], "{$label} edited table preserves short maybe sidecar");
                $t->same('Wrapped', $editedTableCaption['c'][0][0]['c']['c'][0][0]['c'], "{$label} edited table preserves short caption text");
                $t->same('Edited', $editedTableCaption['c'][0][1][0]['c'][0]['c'], "{$label} edited table regenerates long caption text");
                $t->same('Caption', $editedFigureCaption['t'], "{$label} edited figure caption constructor");
                $t->same(true, array_is_list($editedFigureCaption['c']) && count($editedFigureCaption['c']) === 1, "{$label} edited figure keeps wrapped caption tuple");
                $t->same('Just', $editedFigureCaption['c'][0][0]['t'], "{$label} edited figure creates short maybe constructor");
                $t->same('ShortCaption', $editedFigureCaption['c'][0][0]['c']['t'], "{$label} edited figure creates short caption constructor");
                $t->same('Edited', $editedFigureCaption['c'][0][0]['c']['c'][0][0]['c'], "{$label} edited figure short caption text");
            }
        }
    },
    'writes generated caption maybe constructors through pandoc json and native writers' => static function (TestRunner $t): void {
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1]], [
            new AstNode('table', [
                'caption' => 'Long generated table',
                'shortCaption' => 'Short generated table',
            ], [
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'Cell']),
                        ]),
                    ]),
                ]),
            ]),
            new AstNode('figure', [
                'caption' => 'Long generated figure',
            ], [
                new AstNode('image', [
                    'url' => 'media/generated.png',
                    'alt' => 'Generated image',
                ]),
            ]),
        ]);

        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $source => $packet) {
            $tableCaption = $packet['blocks'][0]['c'][1];
            $figureCaption = $packet['blocks'][1]['c'][1];

            $t->same('Caption', $tableCaption['t'], "{$source} table caption constructor");
            $t->same('Just', $tableCaption['c'][0]['t'], "{$source} table short caption maybe constructor");
            $t->same('ShortCaption', $tableCaption['c'][0]['c']['t'], "{$source} table short caption constructor");
            $t->same('Short', $tableCaption['c'][0]['c']['c'][0][0]['c'], "{$source} table short caption first word");
            $t->same('Plain', $tableCaption['c'][1][0]['t'], "{$source} table long caption block constructor");
            $t->same('Long', $tableCaption['c'][1][0]['c'][0]['c'], "{$source} table long caption first word");
            $t->same('Caption', $figureCaption['t'], "{$source} figure caption constructor");
            $t->same('Nothing', $figureCaption['c'][0]['t'], "{$source} figure short caption maybe constructor");
            $t->same('Plain', $figureCaption['c'][1][0]['t'], "{$source} figure long caption block constructor");
            $t->same('Long', $figureCaption['c'][1][0]['c'][0]['c'], "{$source} figure long caption first word");
        }

        $jsonRoundTrip = (new PandocJsonReader())->readPacket($jsonPacket);
        $nativeRoundTrip = (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR));

        $t->same('Short generated table', $jsonRoundTrip->children[0]->attr('shortCaption'));
        $t->same('Long generated table', $nativeRoundTrip->children[0]->attr('caption'));
        $t->same('', $jsonRoundTrip->children[1]->attr('shortCaption', ''));
        $t->same('Long generated figure', $nativeRoundTrip->children[1]->attr('caption'));
    },
    'renders pandoc inline attributes through wordpress html writer sanitizer' => static function (TestRunner $t): void {
        $packet = [
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Span', 'c' => [
                        [
                            'inline-lang',
                            ['language'],
                            [
                                ['data-review', 'span'],
                                ['xml:lang', 'pl'],
                                ['style', 'color:red'],
                                ['onclick', 'blocked'],
                            ],
                        ],
                        [
                            ['t' => 'Str', 'c' => 'tekst'],
                        ],
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Link', 'c' => [
                        [
                            '',
                            ['source-link'],
                            [
                                ['data-review', 'link'],
                                ['xml:lang', 'en-US'],
                                ['style', 'color:blue'],
                                ['onfocus', 'blocked'],
                            ],
                        ],
                        [
                            ['t' => 'Str', 'c' => 'source'],
                        ],
                        ['https://example.test/source', 'Source title'],
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Code', 'c' => [
                        [
                            'code-frag',
                            ['php'],
                            [
                                ['data-review', 'code'],
                                ['xml:lang', 'en-US'],
                                ['style', 'color:green'],
                                ['onmouseover', 'blocked'],
                            ],
                        ],
                        'echo "x";',
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Image', 'c' => [
                        [
                            '',
                            ['inline-media'],
                            [
                                ['data-review', 'image'],
                                ['xml:lang', 'en-US'],
                                ['style', 'filter:blur(1px)'],
                                ['onerror', 'blocked'],
                            ],
                        ],
                        [
                            ['t' => 'Str', 'c' => 'Diagram'],
                        ],
                        ['/media/diagram.png', 'Diagram title'],
                    ]],
                ]],
            ],
        ];

        $document = (new PandocJsonReader())->readPacket($packet);
        $paragraph = $document->children[0];
        $span = $paragraph->children[0];
        $link = $paragraph->children[2];
        $code = $paragraph->children[4];
        $image = $paragraph->children[6];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('pl', $span->attr('attributes')['xml:lang'] ?? null);
        $t->same('en-US', $link->attr('attributes')['xml:lang'] ?? null);
        $t->same('en-US', $code->attr('attributes')['xml:lang'] ?? null);
        $t->same('en-US', $image->attr('attributes')['xml:lang'] ?? null);
        $t->contains('<span id="inline-lang" class="language" data-review="span" xml:lang="pl">tekst</span>', $blocks);
        $t->contains('<a href="https://example.test/source" title="Source title" class="source-link" data-review="link" xml:lang="en-US">source</a>', $blocks);
        $t->contains('<code id="code-frag" class="php" data-review="code" xml:lang="en-US">echo &quot;x&quot;;</code>', $blocks);
        $t->contains('<img src="/media/diagram.png" alt="Diagram" title="Diagram title" class="inline-media" data-review="image" xml:lang="en-US"/>', $blocks);
        $t->true(!str_contains($blocks, 'style='), 'Unsafe style attributes must not render on Pandoc inline output');
        $t->true(!str_contains($blocks, 'onclick='), 'Unsafe click handler attributes must not render on Pandoc inline output');
        $t->true(!str_contains($blocks, 'onfocus='), 'Unsafe focus handler attributes must not render on Pandoc inline output');
        $t->true(!str_contains($blocks, 'onmouseover='), 'Unsafe hover handler attributes must not render on Pandoc inline output');
        $t->true(!str_contains($blocks, 'onerror='), 'Unsafe image handler attributes must not render on Pandoc inline output');
    },
    'round trips pandoc json table captions through shared table ast' => static function (TestRunner $t): void {
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['json-table', ['wp-import-table'], [['data-source', 'json-filter']]],
                [
                    [
                        ['t' => 'Str', 'c' => 'Short'],
                        ['t' => 'Space'],
                        ['t' => 'Strong', 'c' => [
                            ['t' => 'Str', 'c' => 'caption'],
                        ]],
                    ],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Long'],
                            ['t' => 'Space'],
                            ['t' => 'Emph', 'c' => [
                                ['t' => 'Str', 'c' => 'caption'],
                            ]],
                            ['t' => 'Space'],
                            ['t' => 'Link', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Str', 'c' => 'reviewer'],
                                ],
                                ['https://example.test/review', 'Review'],
                            ]],
                        ]],
                    ],
                ],
                [
                    [['t' => 'AlignLeft'], ['t' => 'ColWidth', 'c' => 0.4]],
                    [['t' => 'AlignCenter'], ['t' => 'ColWidthDefault']],
                ],
                [
                    ['', [], []],
                    [
                        [
                            ['', [], []],
                            [
                                [
                                    ['', [], []],
                                    ['t' => 'AlignDefault'],
                                    1,
                                    1,
                                    [
                                        ['t' => 'Plain', 'c' => [
                                            ['t' => 'Str', 'c' => 'Metric'],
                                        ]],
                                    ],
                                ],
                                [
                                    ['', [], []],
                                    ['t' => 'AlignDefault'],
                                    1,
                                    1,
                                    [
                                        ['t' => 'Plain', 'c' => [
                                            ['t' => 'Str', 'c' => 'State'],
                                        ]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    [
                        ['', ['body-source'], []],
                        ['t' => 'RowHeadColumns', 'c' => 1],
                        [],
                        [
                            [
                                ['', [], []],
                                [
                                    [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        1,
                                        1,
                                        [
                                            ['t' => 'Plain', 'c' => [
                                                ['t' => 'Str', 'c' => 'Posts'],
                                            ]],
                                        ],
                                    ],
                                    [
                                        ['', [], []],
                                        ['t' => 'AlignRight'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [
                                            ['t' => 'Plain', 'c' => [
                                                ['t' => 'Str', 'c' => 'Ready'],
                                            ]],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    ['', [], []],
                    [],
                ],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];

        $reader = new PandocJsonReader();
        $writer = new PandocJsonWriter();
        $document = $reader->readPacket($packet);
        $table = $document->children[0];
        $body = $table->children[1];
        $captionBlocks = $table->attr('captionBlocks');
        $captionInlines = $table->attr('captionInlines');
        $shortCaptionInlines = $table->attr('shortCaptionInlines');
        $encoded = $writer->toArray($document);
        $roundTrip = $reader->readPacket($encoded);
        $blocks = (new WordPressBlockWriter())->write($document);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $generated = $writer->toArray(new AstNode('document', [], [
            new AstNode('table', [
                'caption' => 'Fallback long',
                'shortCaption' => 'Fallback short',
            ], [
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'Cell']),
                        ]),
                    ]),
                ]),
            ]),
        ]));
        $generatedRoundTrip = $reader->readPacket($generated);

        $t->same('table', $table->type);
        $t->same('json-table', $table->attr('id'));
        $t->same(['wp-import-table'], $table->attr('classes'));
        $t->same(['data-source' => 'json-filter'], $table->attr('attributes'));
        $t->same('Long caption reviewer', $table->attr('caption'));
        $t->same('Short caption', $table->attr('shortCaption'));
        $t->same(['left', 'center'], $table->attr('alignments'));
        $t->same([0.4, null], $table->attr('widths'));
        $t->same(['table_head', 'table_body'], array_map(static fn (AstNode $node): string => $node->type, $table->children));
        $t->same(1, $body->attr('rowHeadColumns'));
        $t->same(['body-source'], $body->attr('classes'));
        $t->same('right', $body->children[0]->children[1]->attr('align'));
        $t->same(true, is_array($captionBlocks));
        $t->same('plain', $captionBlocks[0]->type);
        $t->same(true, is_array($captionInlines));
        $t->same(['text', 'space', 'emph', 'space', 'link'], array_map(static fn (AstNode $node): string => $node->type, $captionInlines));
        $t->same(true, is_array($shortCaptionInlines));
        $t->same(['text', 'space', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $shortCaptionInlines));
        $t->same('Table', $encoded['blocks'][0]['t']);
        $t->same($tableBlock['c'][0], $encoded['blocks'][0]['c'][0]);
        $t->same('Short', $encoded['blocks'][0]['c'][1][0][0]['c']);
        $t->same('Long', $encoded['blocks'][0]['c'][1][1][0]['c'][0]['c']);
        $t->same('ColWidth', $encoded['blocks'][0]['c'][2][0][1]['t']);
        $t->same(0.4, $encoded['blocks'][0]['c'][2][0][1]['c']);
        $encodedBody = $encoded['blocks'][0]['c'][4][0]['c'] ?? $encoded['blocks'][0]['c'][4][0];
        $encodedBodyRow = $encodedBody[3][0]['c'] ?? $encodedBody[3][0];
        $encodedBodyCells = $encodedBodyRow[1];
        $encodedSecondCell = $encodedBodyCells[1]['c'] ?? $encodedBodyCells[1];
        $t->same(['t' => 'RowHeadColumns', 'c' => 1], $encodedBody[1]);
        $t->same(['t' => 'RowSpan', 'c' => 1], $encodedSecondCell[2]);
        $t->same(['t' => 'ColSpan', 'c' => 1], $encodedSecondCell[3]);
        $t->same('Short caption', $roundTrip->children[0]->attr('shortCaption'));
        $t->same('Long caption reviewer', $roundTrip->children[0]->attr('caption'));
        $t->contains('<figure class="wp-block-table" data-pandoc-short-caption="Short caption">', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Long <em>caption</em> <a href="https://example.test/review" title="Review">reviewer</a></figcaption>', $blocks);
        $t->same('captionBlocks', $packet['captions']['long']['source'] ?? null);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
        $t->same('Caption', $generated['blocks'][0]['c'][1]['t']);
        $t->same('Just', $generated['blocks'][0]['c'][1]['c'][0]['t']);
        $t->same('ShortCaption', $generated['blocks'][0]['c'][1]['c'][0]['c']['t']);
        $t->same('Fallback', $generated['blocks'][0]['c'][1]['c'][0]['c']['c'][0][0]['c']);
        $t->same('Fallback', $generated['blocks'][0]['c'][1]['c'][1][0]['c'][0]['c']);
        $generatedBody = $generated['blocks'][0]['c'][4][0]['c'] ?? $generated['blocks'][0]['c'][4][0];
        $generatedRow = $generatedBody[3][0]['c'] ?? $generatedBody[3][0];
        $generatedCell = $generatedRow[1][0]['c'] ?? $generatedRow[1][0];
        $t->same(['t' => 'RowHeadColumns', 'c' => 0], $generatedBody[1]);
        $t->same(['t' => 'RowSpan', 'c' => 1], $generatedCell[2]);
        $t->same(['t' => 'ColSpan', 'c' => 1], $generatedCell[3]);
        $t->same('Fallback short', $generatedRoundTrip->children[0]->attr('shortCaption'));
        $t->same('Fallback long', $generatedRoundTrip->children[0]->attr('caption'));
    },
    'writes table span helpers as tagged pandoc ast constructors' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], [
            new AstNode('table', [
                'alignments' => ['left', 'right'],
                'widths' => [0.5, null],
            ], [
                new AstNode('table_body', ['rowHeadColumns' => 2], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['rowspan' => 2], [
                            new AstNode('text', ['text' => 'Source']),
                        ]),
                        new AstNode('table_cell', ['align' => 'right', 'colspan' => 3], [
                            new AstNode('text', ['text' => 'Reviewed']),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $jsonBody = $jsonPacket['blocks'][0]['c'][4][0]['c'] ?? $jsonPacket['blocks'][0]['c'][4][0];
        $nativeBody = $nativePacket['blocks'][0]['c'][4][0]['c'] ?? $nativePacket['blocks'][0]['c'][4][0];
        $jsonRow = $jsonBody[3][0]['c'] ?? $jsonBody[3][0];
        $nativeRow = $nativeBody[3][0]['c'] ?? $nativeBody[3][0];
        $jsonCells = array_map(static fn (array $cell): array => $cell['c'] ?? $cell, $jsonRow[1]);
        $nativeCells = array_map(static fn (array $cell): array => $cell['c'] ?? $cell, $nativeRow[1]);
        $jsonRoundTrip = (new PandocJsonReader())->readPacket($jsonPacket)->children[0];
        $nativeRoundTrip = (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR))->children[0];

        $t->same(['t' => 'RowHeadColumns', 'c' => 2], $jsonBody[1]);
        $t->same(['t' => 'RowSpan', 'c' => 2], $jsonCells[0][2]);
        $t->same(['t' => 'ColSpan', 'c' => 1], $jsonCells[0][3]);
        $t->same(['t' => 'RowSpan', 'c' => 1], $jsonCells[1][2]);
        $t->same(['t' => 'ColSpan', 'c' => 3], $jsonCells[1][3]);
        $t->same($jsonBody[1], $nativeBody[1]);
        $t->same($jsonCells[0][2], $nativeCells[0][2]);
        $t->same($jsonCells[1][3], $nativeCells[1][3]);
        $t->same(2, $jsonRoundTrip->children[0]->attr('rowHeadColumns'));
        $t->same(2, $jsonRoundTrip->children[0]->children[0]->children[0]->attr('rowspan'));
        $t->same(3, $jsonRoundTrip->children[0]->children[0]->children[1]->attr('colspan'));
        $t->same(2, $nativeRoundTrip->children[0]->attr('rowHeadColumns'));
        $t->same(2, $nativeRoundTrip->children[0]->children[0]->children[0]->attr('rowspan'));
        $t->same(3, $nativeRoundTrip->children[0]->children[0]->children[1]->attr('colspan'));
    },
    'preserves table cell attr and span helper sidecars until edited boundaries' => static function (TestRunner $t): void {
        $firstCellAttr = ['t' => 'Attr', 'c' => [
            'source-cell',
            ['metric', 'review'],
            [['data-source', 'json'], ['data-state', 'raw']],
            ['reviewQueue' => 'first-cell-attr-source'],
        ]];
        $firstCellAlignment = ['t' => 'AlignRight', 'reviewQueue' => 'first-cell-align-source'];
        $firstCellRowSpan = ['t' => 'RowSpan', 'c' => [2], 'reviewQueue' => 'first-cell-rowspan-source'];
        $firstCellColSpan = ['t' => 'ColSpan', 'c' => 3, 'reviewQueue' => 'first-cell-colspan-source'];
        $firstCell = ['t' => 'Cell', 'c' => [
            $firstCellAttr,
            $firstCellAlignment,
            $firstCellRowSpan,
            $firstCellColSpan,
            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Source']]]],
        ], 'reviewQueue' => 'first-cell-source', 'sourceOrdinal' => 4];

        $secondCellAttr = ['t' => 'Attr', 'c' => [
            'stable-cell',
            ['kept'],
            [['data-source', 'neighbor']],
            ['reviewQueue' => 'second-cell-attr-source'],
        ]];
        $secondCell = ['t' => 'Cell', 'c' => [
            $secondCellAttr,
            ['t' => 'AlignCenter', 'reviewQueue' => 'second-cell-align-source'],
            ['t' => 'RowSpan', 'c' => 1, 'reviewQueue' => 'second-cell-rowspan-source'],
            ['t' => 'ColSpan', 'c' => 1, 'reviewQueue' => 'second-cell-colspan-source'],
            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Neighbor']]]],
        ], 'reviewQueue' => 'second-cell-source', 'sourceOrdinal' => 5];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [[
                't' => 'Table',
                'c' => [
                    ['', [], []],
                    ['t' => 'Caption', 'c' => [null, []]],
                    [
                        [['t' => 'AlignRight'], ['t' => 'ColWidthDefault']],
                        [['t' => 'AlignCenter'], ['t' => 'ColWidthDefault']],
                    ],
                    ['t' => 'TableHead', 'c' => [['', [], []], []]],
                    [
                        ['t' => 'TableBody', 'c' => [
                            ['', [], []],
                            ['t' => 'RowHeadColumns', 'c' => 0],
                            [],
                            [[['', [], []], [$firstCell, $secondCell]]],
                        ]],
                    ],
                    ['t' => 'TableFoot', 'c' => [['', [], []], []]],
                ],
            ]],
        ];
        $encodedCells = static function (array $encoded): array {
            $body = $encoded['blocks'][0]['c'][4][0];
            $bodyPayload = $body['c'] ?? $body;
            $row = $bodyPayload[3][0];
            $rowPayload = $row['c'] ?? $row;

            return $rowPayload[1];
        };
        $cellPayload = static fn (array $cell): array => $cell['c'] ?? $cell;

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $first = $document->children[0]->children[0]->children[0]->children[0];

            $t->same('source-cell', $first->attr('id'), "{$source} cell attr id");
            $t->same(['metric', 'review'], $first->attr('classes'), "{$source} cell attr classes");
            $t->same(['data-source' => 'json', 'data-state' => 'raw'], $first->attr('attributes'), "{$source} cell attr key-values");
            $t->same($firstCellAttr, $first->attr('attrNative'), "{$source} cell attr native sidecar");
            $t->same('right', $first->attr('align'), "{$source} cell alignment");
            $t->same($firstCellAlignment, $first->attr('alignmentNative'), "{$source} cell alignment native sidecar");
            $t->same(2, $first->attr('rowspan'), "{$source} cell rowspan");
            $t->same($firstCellRowSpan, $first->attr('rowSpanNative'), "{$source} cell rowspan native sidecar");
            $t->same(3, $first->attr('colspan'), "{$source} cell colspan");
            $t->same($firstCellColSpan, $first->attr('colSpanNative'), "{$source} cell colspan native sidecar");

            foreach ([
                "{$source} json" => (new PandocJsonWriter())->toArray($document),
                "{$source} native" => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $cells = $encodedCells($encoded);

                $t->same($firstCell, $cells[0], "{$writer} writer preserves unchanged first cell payload");
                $t->same($secondCell, $cells[1], "{$writer} writer preserves unchanged second cell payload");
            }

            $editedFirst = new AstNode('table_cell', array_replace($first->attrs, [
                'id' => 'edited-cell',
                'classes' => ['metric', 'review', 'edited'],
                'attributes' => ['data-source' => 'json', 'data-state' => 'edited'],
                'rowspan' => 4,
                'colspan' => 2,
            ]), $first->children);
            $table = $document->children[0];
            $body = $table->children[0];
            $row = $body->children[0];
            $editedRow = new AstNode('table_row', $row->attrs, [$editedFirst, $row->children[1]]);
            $editedBody = new AstNode('table_body', $body->attrs, [$editedRow]);
            $editedTable = new AstNode('table', $table->attrs, [$editedBody]);
            $editedDocument = new AstNode('document', $document->attrs, [$editedTable]);

            foreach ([
                "{$source} json edited" => (new PandocJsonWriter())->toArray($editedDocument),
                "{$source} native edited" => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $cells = $encodedCells($encoded);
                $editedPayload = $cellPayload($cells[0]);

                $t->same('Cell', $cells[0]['t'] ?? null, "{$writer} writer regenerates current edited cell constructor");
                $t->same(false, array_key_exists('reviewQueue', $cells[0]), "{$writer} writer drops stale edited cell sidecar");
                $t->same(false, array_key_exists('sourceOrdinal', $cells[0]), "{$writer} writer drops stale edited cell ordinal");
                $t->same(['edited-cell', ['metric', 'review', 'edited'], [['data-source', 'json'], ['data-state', 'edited']]], $editedPayload[0], "{$writer} writer regenerates edited cell Attr tuple");
                $t->same(false, array_key_exists('reviewQueue', $editedPayload[0]), "{$writer} writer drops stale edited Attr sidecar");
                $t->same($firstCellAlignment, $editedPayload[1], "{$writer} writer preserves unchanged cell alignment sidecar");
                $t->same(['t' => 'RowSpan', 'c' => 4], $editedPayload[2], "{$writer} writer regenerates edited cell RowSpan helper");
                $t->same(false, array_key_exists('reviewQueue', $editedPayload[2]), "{$writer} writer drops stale edited RowSpan sidecar");
                $t->same(['t' => 'ColSpan', 'c' => 2], $editedPayload[3], "{$writer} writer regenerates edited cell ColSpan helper");
                $t->same(false, array_key_exists('reviewQueue', $editedPayload[3]), "{$writer} writer drops stale edited ColSpan sidecar");
                $t->same($secondCell, $cells[1], "{$writer} writer keeps neighboring cell payload");
            }
        }
    },
    'preserves single wrapped table integer helpers through rebuilt json and native table wrappers' => static function (TestRunner $t): void {
        $rowHeadColumns = ['t' => 'RowHeadColumns', 'c' => [1], 'reviewQueue' => 'row-head-source'];
        $rowSpan = ['t' => 'RowSpan', 'c' => [2], 'reviewQueue' => 'row-span-source'];
        $colSpan = ['t' => 'ColSpan', 'c' => [3], 'reviewQueue' => 'col-span-source'];
        $alignment = ['t' => 'AlignRight', 'reviewQueue' => 'cell-align-source'];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['wrapped-integer-table', ['json-native'], [['data-source', 'constructor-completeness']]],
                ['t' => 'Caption', 'c' => [null, []]],
                [[['t' => 'AlignRight'], ['t' => 'ColWidth', 'c' => [0.5], 'reviewQueue' => 'width-source']]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['wrapped-body', ['body'], []],
                        $rowHeadColumns,
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['wrapped-row', ['row'], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['wrapped-cell', ['cell'], [['data-kind', 'metric']]],
                                        $alignment,
                                        $rowSpan,
                                        $colSpan,
                                        [['t' => 'Plain', 'c' => [
                                            ['t' => 'Str', 'c' => 'Wrapped'],
                                            ['t' => 'Space'],
                                            ['t' => 'Str', 'c' => 'cell'],
                                        ]]],
                                    ], 'reviewQueue' => 'cell-wrapper-source'],
                                ],
                            ], 'reviewQueue' => 'row-wrapper-source'],
                        ],
                    ], 'reviewQueue' => 'body-wrapper-source'],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
            'reviewQueue' => 'table-wrapper-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];
        $stripWrapper = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };
        $tableBodyPayload = static fn (array $encoded): array => $encoded['blocks'][0]['c'][4][0];
        $tableCellPayload = static function (array $encoded): array {
            $body = $encoded['blocks'][0]['c'][4][0];
            $bodyPayload = $body['c'] ?? $body;
            $row = $bodyPayload[3][0];
            $rowPayload = $row['c'] ?? $row;
            $cell = $rowPayload[1][0];

            return $cell['c'] ?? $cell;
        };

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $body = $table->children[0];
            $row = $body->children[0];
            $cell = $row->children[0];

            $t->same(1, $body->attr('rowHeadColumns'), "{$source} row head columns value");
            $t->same($rowHeadColumns, $body->attr('rowHeadColumnsNative'), "{$source} row head columns native payload");
            $t->same(2, $cell->attr('rowspan'), "{$source} row span value");
            $t->same($rowSpan, $cell->attr('rowSpanNative'), "{$source} row span native payload");
            $t->same(3, $cell->attr('colspan'), "{$source} col span value");
            $t->same($colSpan, $cell->attr('colSpanNative'), "{$source} col span native payload");

            $rebuiltCell = new AstNode('table_cell', $stripWrapper($cell), $cell->children);
            $rebuiltRow = new AstNode('table_row', $stripWrapper($row), [$rebuiltCell]);
            $rebuiltBody = new AstNode('table_body', $stripWrapper($body), [$rebuiltRow]);
            $rebuiltTable = new AstNode('table', $stripWrapper($table), [$rebuiltBody]);
            $rebuiltDocument = new AstNode('document', $document->attrs, [$rebuiltTable]);

            foreach ([
                "{$source} json" => (new PandocJsonWriter())->toArray($rebuiltDocument),
                "{$source} native" => json_decode((new NativeWriter())->write($rebuiltDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $bodyPayload = $tableBodyPayload($encoded);
                $bodyContent = $bodyPayload['c'] ?? $bodyPayload;
                $cellPayload = $tableCellPayload($encoded);

                $t->same('TableBody', $bodyPayload['t'] ?? null, "{$writer} writer rebuilds current table body constructor");
                $t->same($rowHeadColumns, $bodyContent[1], "{$writer} writer preserves single wrapped row head columns helper");
                $t->same($alignment, $cellPayload[1], "{$writer} writer preserves cell alignment helper");
                $t->same($rowSpan, $cellPayload[2], "{$writer} writer preserves single wrapped row span helper");
                $t->same($colSpan, $cellPayload[3], "{$writer} writer preserves single wrapped col span helper");

                $roundTrips = [
                    "{$writer} json round trip" => (new PandocJsonReader())->readPacket($encoded),
                    "{$writer} native round trip" => (new NativeReader())->read(json_encode($encoded, JSON_THROW_ON_ERROR)),
                ];
                foreach ($roundTrips as $roundTripLabel => $roundTrip) {
                    $roundTripBody = $roundTrip->children[0]->children[0];
                    $roundTripCell = $roundTripBody->children[0]->children[0];

                    $t->same(1, $roundTripBody->attr('rowHeadColumns'), "{$roundTripLabel} row head columns");
                    $t->same(2, $roundTripCell->attr('rowspan'), "{$roundTripLabel} row span");
                    $t->same(3, $roundTripCell->attr('colspan'), "{$roundTripLabel} col span");
                }
            }
        }
    },
    'preserves scalar table integer helpers through rebuilt json and native table wrappers' => static function (TestRunner $t): void {
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['scalar-integer-table', ['json-native'], [['data-source', 'constructor-completeness']]],
                ['t' => 'Caption', 'c' => [null, []]],
                [[['t' => 'AlignLeft'], ['t' => 'ColWidthDefault']]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        1,
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignRight'],
                                        2,
                                        [3],
                                        [['t' => 'Plain', 'c' => [
                                            ['t' => 'Str', 'c' => 'Scalar'],
                                            ['t' => 'Space'],
                                            ['t' => 'Str', 'c' => 'cell'],
                                        ]]],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
            'reviewQueue' => 'scalar-table-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];
        $stripWrapper = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };
        $tableBodyPayload = static fn (array $encoded): array => $encoded['blocks'][0]['c'][4][0];
        $tableCellPayload = static function (array $encoded): array {
            $body = $encoded['blocks'][0]['c'][4][0];
            $bodyPayload = $body['c'] ?? $body;
            $row = $bodyPayload[3][0];
            $rowPayload = $row['c'] ?? $row;
            $cell = $rowPayload[1][0];

            return $cell['c'] ?? $cell;
        };

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $body = $table->children[0];
            $row = $body->children[0];
            $cell = $row->children[0];

            $t->same(1, $body->attr('rowHeadColumns'), "{$source} scalar row head columns value");
            $t->same(1, $body->attr('rowHeadColumnsNative'), "{$source} scalar row head native payload");
            $t->same(2, $cell->attr('rowspan'), "{$source} scalar row span value");
            $t->same(2, $cell->attr('rowSpanNative'), "{$source} scalar row span native payload");
            $t->same(3, $cell->attr('colspan'), "{$source} single wrapped scalar col span value");
            $t->same([3], $cell->attr('colSpanNative'), "{$source} single wrapped scalar col span native payload");

            $rebuiltCell = new AstNode('table_cell', $stripWrapper($cell), $cell->children);
            $rebuiltRow = new AstNode('table_row', $stripWrapper($row), [$rebuiltCell]);
            $rebuiltBody = new AstNode('table_body', $stripWrapper($body), [$rebuiltRow]);
            $rebuiltTable = new AstNode('table', array_replace($stripWrapper($table), [
                'id' => 'rebuilt-scalar-integer-table',
            ]), [$rebuiltBody]);
            $rebuiltDocument = new AstNode('document', $document->attrs, [$rebuiltTable]);

            foreach ([
                "{$source} json" => (new PandocJsonWriter())->toArray($rebuiltDocument),
                "{$source} native" => json_decode((new NativeWriter())->write($rebuiltDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $bodyPayload = $tableBodyPayload($encoded);
                $bodyContent = $bodyPayload['c'] ?? $bodyPayload;
                $cellPayload = $tableCellPayload($encoded);

                $t->same('rebuilt-scalar-integer-table', $encoded['blocks'][0]['c'][0][0], "{$writer} writer rebuilds edited table attr");
                $t->same(1, $bodyContent[1], "{$writer} writer preserves scalar row head helper");
                $t->same(2, $cellPayload[2], "{$writer} writer preserves scalar row span helper");
                $t->same([3], $cellPayload[3], "{$writer} writer preserves single wrapped scalar col span helper");
            }
        }
    },
    'accepts single wrapped table helper tuples through rebuilt json and native writers' => static function (TestRunner $t): void {
        $headCell = ['t' => 'Cell', 'c' => [[
            ['head-cell', ['header'], [['data-role', 'column']]],
            ['t' => 'AlignCenter', 'reviewQueue' => 'head-align-source'],
            ['t' => 'RowSpan', 'c' => [1], 'reviewQueue' => 'head-rowspan-source'],
            ['t' => 'ColSpan', 'c' => [2], 'reviewQueue' => 'head-colspan-source'],
            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Head']]]],
        ]], 'reviewQueue' => 'head-cell-source'];
        $headRow = ['t' => 'Row', 'c' => [[
            ['head-row', ['header-row'], []],
            [$headCell],
        ]], 'reviewQueue' => 'head-row-source'];
        $tableHead = ['t' => 'TableHead', 'c' => [[
            ['head-section', ['thead'], []],
            [$headRow],
        ]], 'reviewQueue' => 'head-section-source'];

        $rowHeadColumns = ['t' => 'RowHeadColumns', 'c' => [1], 'reviewQueue' => 'body-row-head-source'];
        $bodyHeadCell = ['t' => 'Cell', 'c' => [[
            ['body-head-cell', ['row-head'], []],
            ['t' => 'AlignLeft'],
            ['t' => 'RowSpan', 'c' => 1],
            ['t' => 'ColSpan', 'c' => 1],
            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'BodyHead']]]],
        ]], 'reviewQueue' => 'body-head-cell-source'];
        $bodyHeadRow = ['t' => 'Row', 'c' => [[
            ['body-head-row', ['row-head'], []],
            [$bodyHeadCell],
        ]], 'reviewQueue' => 'body-head-row-source'];
        $bodyCell = ['t' => 'Cell', 'c' => [[
            ['body-cell', ['metric'], [['data-kind', 'body']]],
            ['t' => 'AlignRight', 'reviewQueue' => 'body-align-source'],
            ['t' => 'RowSpan', 'c' => [2], 'reviewQueue' => 'body-rowspan-source'],
            ['t' => 'ColSpan', 'c' => [1], 'reviewQueue' => 'body-colspan-source'],
            [['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Body'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'cell'],
            ]]],
        ]], 'reviewQueue' => 'body-cell-source'];
        $bodyRow = ['t' => 'Row', 'c' => [[
            ['body-row', ['data-row'], []],
            [$bodyCell],
        ]], 'reviewQueue' => 'body-row-source'];
        $tableBody = ['t' => 'TableBody', 'c' => [[
            ['body-section', ['tbody'], []],
            $rowHeadColumns,
            [$bodyHeadRow],
            [$bodyRow],
        ]], 'reviewQueue' => 'body-section-source'];

        $footCell = ['t' => 'Cell', 'c' => [[
            ['foot-cell', ['footer'], []],
            ['t' => 'AlignDefault'],
            ['t' => 'RowSpan', 'c' => 1],
            ['t' => 'ColSpan', 'c' => 2],
            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Foot']]]],
        ]], 'reviewQueue' => 'foot-cell-source'];
        $footRow = ['t' => 'Row', 'c' => [[
            ['foot-row', ['footer-row'], []],
            [$footCell],
        ]], 'reviewQueue' => 'foot-row-source'];
        $tableFoot = ['t' => 'TableFoot', 'c' => [[
            ['foot-section', ['tfoot'], []],
            [$footRow],
        ]], 'reviewQueue' => 'foot-section-source'];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['wrapped-table-tuples', ['json-native'], [['data-source', 'table-helper-tuples']]],
                ['t' => 'Caption', 'c' => [['t' => 'Nothing'], []]],
                [
                    [['t' => 'AlignCenter'], ['t' => 'ColWidthDefault']],
                    [['t' => 'AlignRight'], ['t' => 'ColWidth', 'c' => [0.25], 'reviewQueue' => 'width-source']],
                ],
                $tableHead,
                [$tableBody],
                $tableFoot,
            ],
            'reviewQueue' => 'table-wrapper-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];
        $stripWrapper = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $table = $document->children[0];
            $head = $table->children[0];
            $body = $table->children[1];
            $foot = $table->children[2];
            $headRows = $body->attr('headRows');
            $headCellNode = $head->children[0]->children[0];
            $bodyHeadCellNode = $headRows[0]->children[0];
            $bodyCellNode = $body->children[0]->children[0];
            $footCellNode = $foot->children[0]->children[0];
            $rebuiltDocument = new AstNode('document', $document->attrs, [
                new AstNode('table', $stripWrapper($table), $table->children),
            ]);

            $t->same($tableHead, $head->attr('native'), "{$source} table head native");
            $t->same($tableBody, $body->attr('native'), "{$source} table body native");
            $t->same($tableFoot, $foot->attr('native'), "{$source} table foot native");
            $t->same(1, $body->attr('rowHeadColumns'), "{$source} row head columns");
            $t->same($rowHeadColumns, $body->attr('rowHeadColumnsNative'), "{$source} row head native");
            $t->same('Head', $headCellNode->attr('text'), "{$source} head cell text");
            $t->same('BodyHead', $bodyHeadCellNode->attr('text'), "{$source} body head row text");
            $t->same('Body cell', $bodyCellNode->attr('text'), "{$source} body cell text");
            $t->same(2, $bodyCellNode->attr('rowspan'), "{$source} body cell row span");
            $t->same($bodyCell['c'][0][2], $bodyCellNode->attr('rowSpanNative'), "{$source} body cell row span native");
            $t->same('Foot', $footCellNode->attr('text'), "{$source} foot cell text");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuiltDocument),
                'native' => json_decode((new NativeWriter())->write($rebuiltDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $rebuiltTable = $encoded['blocks'][0];

                $t->same(false, array_key_exists('reviewQueue', $rebuiltTable), "{$source} {$writer} writer rebuilds top table wrapper");
                $t->same($tableHead, $rebuiltTable['c'][3], "{$source} {$writer} writer preserves wrapped table head");
                $t->same($tableBody, $rebuiltTable['c'][4][0], "{$source} {$writer} writer preserves wrapped table body");
                $t->same($tableFoot, $rebuiltTable['c'][5], "{$source} {$writer} writer preserves wrapped table foot");
                $roundTrip = $writer === 'json'
                    ? (new PandocJsonReader())->readPacket($encoded)
                    : (new NativeReader())->read(json_encode($encoded, JSON_THROW_ON_ERROR));
                $t->same('Body cell', $roundTrip->children[0]->children[1]->children[0]->children[0]->attr('text'), "{$source} {$writer} round trip body cell");
            }
        }
    },
    'preserves single wrapped table column specs through rebuilt json and native writers' => static function (TestRunner $t): void {
        $firstSpec = [[
            ['t' => 'AlignLeft', 'reviewQueue' => 'first-align-source'],
            ['t' => 'ColWidth', 'c' => [0.35], 'reviewQueue' => 'first-width-source'],
        ]];
        $secondSpec = [[
            ['t' => 'AlignDefault', 'reviewQueue' => 'second-align-source'],
            ['t' => 'ColWidthDefault', 'reviewQueue' => 'second-width-source'],
        ]];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['colspec-table', ['json-native'], [['data-source', 'single-wrapped-colspec']]],
                ['t' => 'Caption', 'c' => [null, []]],
                [$firstSpec, $secondSpec],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => 0],
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Left']]]],
                                    ]],
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Default']]]],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
            'reviewQueue' => 'table-wrapper-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
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
            $table = $document->children[0];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('table', $withoutWrapperNative($table), $table->children),
            ]);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('table', array_replace($withoutWrapperNative($table), [
                    'widths' => [0.5, null],
                ]), $table->children),
            ]);

            $t->same(['left', 'default'], $table->attr('alignments'), "{$source} reads wrapped column spec alignments");
            $t->same([0.35, null], $table->attr('widths'), "{$source} reads wrapped column spec widths");
            $t->same([$firstSpec, $secondSpec], $table->attr('columnSpecNatives'), "{$source} records wrapped column spec sidecars");
            $t->same($firstSpec[0][0], $table->attr('alignmentNatives')[0], "{$source} unwraps first alignment sidecar");
            $t->same($firstSpec[0][1], $table->attr('columnWidthNatives')[0], "{$source} unwraps first width sidecar");

            foreach ([
                "{$source} unchanged json" => (new PandocJsonWriter())->toArray($document),
                "{$source} unchanged native" => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same([$firstSpec, $secondSpec], $encoded['blocks'][0]['c'][2], "{$writer} writer preserves unchanged wrapped column specs");
            }

            foreach ([
                "{$source} rebuilt json" => (new PandocJsonWriter())->toArray($rebuilt),
                "{$source} rebuilt native" => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same([$firstSpec, $secondSpec], $encoded['blocks'][0]['c'][2], "{$writer} writer preserves wrapped column specs after table wrapper rebuild");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$writer} writer regenerates table wrapper");
            }

            foreach ([
                "{$source} edited json" => (new PandocJsonWriter())->toArray($edited),
                "{$source} edited native" => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $columnSpecs = $encoded['blocks'][0]['c'][2];

                $t->same([$firstSpec[0][0], ['t' => 'ColWidth', 'c' => 0.5]], $columnSpecs[0], "{$writer} writer regenerates edited width as a current colspec tuple");
                $t->same($secondSpec, $columnSpecs[1], "{$writer} writer preserves untouched wrapped default colspec");
            }
        }
    },
    'preserves single wrapped table section helper constructors through rebuilt writers' => static function (TestRunner $t): void {
        $headCell = ['t' => 'Cell', 'c' => [[
            ['head-cell', ['section'], [['data-kind', 'head']]],
            ['t' => 'AlignCenter', 'reviewQueue' => 'head-cell-align-source'],
            ['t' => 'RowSpan', 'c' => [1], 'reviewQueue' => 'head-cell-rowspan-source'],
            ['t' => 'ColSpan', 'c' => [1], 'reviewQueue' => 'head-cell-colspan-source'],
            [['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Head'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'cell'],
            ]]],
        ]], 'reviewQueue' => 'head-cell-wrapper-source'];
        $headRow = ['t' => 'Row', 'c' => [[
            ['head-row', ['section-row'], []],
            [$headCell],
        ]], 'reviewQueue' => 'head-row-wrapper-source'];
        $tableHead = ['t' => 'TableHead', 'c' => [[
            ['head-section', ['thead'], [['data-scope', 'header']]],
            [$headRow],
        ]], 'reviewQueue' => 'head-section-wrapper-source'];

        $rowHeadColumns = ['t' => 'RowHeadColumns', 'c' => [1], 'reviewQueue' => 'body-row-head-source'];
        $bodyCell = ['t' => 'Cell', 'c' => [[
            ['body-cell', ['section'], [['data-kind', 'body']]],
            ['t' => 'AlignLeft', 'reviewQueue' => 'body-cell-align-source'],
            ['t' => 'RowSpan', 'c' => [2], 'reviewQueue' => 'body-cell-rowspan-source'],
            ['t' => 'ColSpan', 'c' => [1], 'reviewQueue' => 'body-cell-colspan-source'],
            [['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Body'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'cell'],
            ]]],
        ]], 'reviewQueue' => 'body-cell-wrapper-source'];
        $bodyRow = ['t' => 'Row', 'c' => [[
            ['body-row', ['section-row'], []],
            [$bodyCell],
        ]], 'reviewQueue' => 'body-row-wrapper-source'];
        $tableBody = ['t' => 'TableBody', 'c' => [[
            ['body-section', ['tbody'], [['data-scope', 'body']]],
            $rowHeadColumns,
            [],
            [$bodyRow],
        ]], 'reviewQueue' => 'body-section-wrapper-source'];

        $footCell = ['t' => 'Cell', 'c' => [[
            ['foot-cell', ['section'], [['data-kind', 'foot']]],
            ['t' => 'AlignRight', 'reviewQueue' => 'foot-cell-align-source'],
            ['t' => 'RowSpan', 'c' => [1], 'reviewQueue' => 'foot-cell-rowspan-source'],
            ['t' => 'ColSpan', 'c' => [1], 'reviewQueue' => 'foot-cell-colspan-source'],
            [['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Foot'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'cell'],
            ]]],
        ]], 'reviewQueue' => 'foot-cell-wrapper-source'];
        $footRow = ['t' => 'Row', 'c' => [[
            ['foot-row', ['section-row'], []],
            [$footCell],
        ]], 'reviewQueue' => 'foot-row-wrapper-source'];
        $tableFoot = ['t' => 'TableFoot', 'c' => [[
            ['foot-section', ['tfoot'], [['data-scope', 'footer']]],
            [$footRow],
        ]], 'reviewQueue' => 'foot-section-wrapper-source'];

        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [[
                't' => 'Table',
                'c' => [
                    ['section-table', ['json-native'], [['data-source', 'single-wrap']]],
                    ['t' => 'Caption', 'c' => [null, []]],
                    [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                    $tableHead,
                    [$tableBody],
                    $tableFoot,
                ],
                'reviewQueue' => 'table-wrapper-source',
            ]],
        ];
        $stripBlockWrapper = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };
        $bodyPayload = static fn (array $encoded): array => $encoded['blocks'][0]['c'][4][0];
        $cellPayload = static function (array $body): array {
            $bodyContent = $body['c'] ?? $body;
            $row = $bodyContent[3][0];
            $rowContent = $row['c'] ?? $row;
            $cell = $rowContent[1][0];

            return $cell['c'] ?? $cell;
        };

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $table = $document->children[0];
            $head = $table->children[0];
            $body = $table->children[1];
            $foot = $table->children[2];
            $bodyRowNode = $body->children[0];
            $bodyCellNode = $bodyRowNode->children[0];

            $t->same('head-section', $head->attr('id'), "{$source} reads single wrapped TableHead attr");
            $t->same($tableHead, $head->attr('native'), "{$source} preserves TableHead native wrapper");
            $t->same($headRow, $head->children[0]->attr('native'), "{$source} preserves head Row native wrapper");
            $t->same($headCell, $head->children[0]->children[0]->attr('native'), "{$source} preserves head Cell native wrapper");
            $t->same(1, $body->attr('rowHeadColumns'), "{$source} reads single wrapped TableBody row head columns");
            $t->same($rowHeadColumns, $body->attr('rowHeadColumnsNative'), "{$source} preserves body RowHeadColumns helper");
            $t->same(2, $bodyCellNode->attr('rowspan'), "{$source} reads single wrapped Cell RowSpan");
            $t->same('Body cell', $bodyCellNode->attr('text'), "{$source} reads body cell text");
            $t->same($tableFoot, $foot->attr('native'), "{$source} preserves TableFoot native wrapper");

            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('table', $stripBlockWrapper($table), [$head, $body, $foot]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $tableParts = $encoded['blocks'][0]['c'];

                $t->same($tableHead, $tableParts[3], "{$source} {$writer} writer preserves single wrapped TableHead wrapper");
                $t->same($tableBody, $tableParts[4][0], "{$source} {$writer} writer preserves single wrapped TableBody wrapper");
                $t->same($tableFoot, $tableParts[5], "{$source} {$writer} writer preserves single wrapped TableFoot wrapper");
            }

            $editedBodyCell = new AstNode('table_cell', $bodyCellNode->attrs, [
                new AstNode('text', ['text' => 'Edited']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'body']),
            ]);
            $editedBodyRow = new AstNode('table_row', $bodyRowNode->attrs, [$editedBodyCell]);
            $editedBody = new AstNode('table_body', $body->attrs, [$editedBodyRow]);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('table', $stripBlockWrapper($table), [$head, $editedBody, $foot]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $tableParts = $encoded['blocks'][0]['c'];
                $editedBodyPayload = $bodyPayload($encoded);
                $editedCellPayload = $cellPayload($editedBodyPayload);

                $t->same($tableHead, $tableParts[3], "{$source} {$writer} edited writer keeps unchanged head wrapper");
                $t->same('TableBody', $editedBodyPayload['t'] ?? null, "{$source} {$writer} edited writer regenerates changed body constructor");
                $t->same(false, array_key_exists('reviewQueue', $editedBodyPayload), "{$source} {$writer} edited writer drops stale body wrapper sidecar");
                $t->same('Edited', $editedCellPayload[4][0]['c'][0]['c'], "{$source} {$writer} edited writer regenerates changed cell text");
                $t->same($tableFoot, $tableParts[5], "{$source} {$writer} edited writer keeps unchanged foot wrapper");
            }
        }
    },
    'preserves single wrapped table wrapper tuple constructors through regenerated table shells' => static function (TestRunner $t): void {
        $headCell = ['t' => 'Cell', 'c' => [[
            ['head-cell', ['cell'], [['data-kind', 'head']]],
            ['t' => 'AlignCenter', 'reviewQueue' => 'head-align-source'],
            ['t' => 'RowSpan', 'c' => [1], 'reviewQueue' => 'head-rowspan-source'],
            ['t' => 'ColSpan', 'c' => [1], 'reviewQueue' => 'head-colspan-source'],
            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Head']]]],
        ]], 'reviewQueue' => 'head-cell-wrapper-source'];
        $headRow = ['t' => 'Row', 'c' => [[
            ['head-row', ['row'], []],
            [$headCell],
        ]], 'reviewQueue' => 'head-row-wrapper-source'];
        $tableHead = ['t' => 'TableHead', 'c' => [[
            ['head-section', ['section'], []],
            [$headRow],
        ]], 'reviewQueue' => 'head-section-wrapper-source'];

        $bodyHeadCell = ['t' => 'Cell', 'c' => [[
            ['body-head-cell', ['cell'], []],
            ['t' => 'AlignLeft', 'reviewQueue' => 'body-head-align-source'],
            ['t' => 'RowSpan', 'c' => 1, 'reviewQueue' => 'body-head-rowspan-source'],
            ['t' => 'ColSpan', 'c' => 2, 'reviewQueue' => 'body-head-colspan-source'],
            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'BodyHead']]]],
        ]], 'reviewQueue' => 'body-head-cell-wrapper-source'];
        $bodyHeadRow = ['t' => 'Row', 'c' => [[
            ['body-head-row', ['row'], []],
            [$bodyHeadCell],
        ]], 'reviewQueue' => 'body-head-row-wrapper-source'];

        $bodyCell = ['t' => 'Cell', 'c' => [[
            ['body-cell', ['cell'], [['data-kind', 'metric']]],
            ['t' => 'AlignRight', 'reviewQueue' => 'body-align-source'],
            ['t' => 'RowSpan', 'c' => [2], 'reviewQueue' => 'body-rowspan-source'],
            ['t' => 'ColSpan', 'c' => 1, 'reviewQueue' => 'body-colspan-source'],
            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Body']]]],
        ]], 'reviewQueue' => 'body-cell-wrapper-source'];
        $bodyRow = ['t' => 'Row', 'c' => [[
            ['body-row', ['row'], []],
            [$bodyCell],
        ]], 'reviewQueue' => 'body-row-wrapper-source'];
        $tableBody = ['t' => 'TableBody', 'c' => [[
            ['body-section', ['section'], []],
            ['t' => 'RowHeadColumns', 'c' => [1], 'reviewQueue' => 'body-row-head-source'],
            [$bodyHeadRow],
            [$bodyRow],
        ]], 'reviewQueue' => 'body-section-wrapper-source'];

        $footCell = ['t' => 'Cell', 'c' => [[
            ['foot-cell', ['cell'], []],
            ['t' => 'AlignDefault', 'reviewQueue' => 'foot-align-source'],
            ['t' => 'RowSpan', 'c' => 1, 'reviewQueue' => 'foot-rowspan-source'],
            ['t' => 'ColSpan', 'c' => 1, 'reviewQueue' => 'foot-colspan-source'],
            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Foot']]]],
        ]], 'reviewQueue' => 'foot-cell-wrapper-source'];
        $footRow = ['t' => 'Row', 'c' => [[
            ['foot-row', ['row'], []],
            [$footCell],
        ]], 'reviewQueue' => 'foot-row-wrapper-source'];
        $tableFoot = ['t' => 'TableFoot', 'c' => [[
            ['foot-section', ['section'], []],
            [$footRow],
        ]], 'reviewQueue' => 'foot-section-wrapper-source'];

        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [[
                't' => 'Table',
                'c' => [
                    ['wrapped-table', ['review'], [['data-source', 'single-wrapped-tuples']]],
                    ['t' => 'Caption', 'c' => [null, []]],
                    [
                        [['t' => 'AlignCenter'], ['t' => 'ColWidthDefault']],
                        [['t' => 'AlignRight'], ['t' => 'ColWidth', 'c' => [0.4]]],
                    ],
                    $tableHead,
                    [$tableBody],
                    $tableFoot,
                ],
                'reviewQueue' => 'table-wrapper-source',
            ]],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $head = $table->children[0];
            $body = $table->children[1];
            $foot = $table->children[2];
            $headRowNode = $head->children[0];
            $headCellNode = $headRowNode->children[0];
            $headRows = $body->attr('headRows');
            $bodyHeadRowNode = $headRows[0];
            $bodyHeadCellNode = $bodyHeadRowNode->children[0];
            $bodyRowNode = $body->children[0];
            $bodyCellNode = $bodyRowNode->children[0];
            $footRowNode = $foot->children[0];
            $footCellNode = $footRowNode->children[0];

            $t->same($tableHead, $head->attr('native'), "{$source} table head native wrapper");
            $t->same($headRow, $headRowNode->attr('native'), "{$source} head row native wrapper");
            $t->same($headCell, $headCellNode->attr('native'), "{$source} head cell native wrapper");
            $t->same($tableBody, $body->attr('native'), "{$source} table body native wrapper");
            $t->same(1, $body->attr('rowHeadColumns'), "{$source} row head columns");
            $t->same($bodyHeadRow, $bodyHeadRowNode->attr('native'), "{$source} body head row native wrapper");
            $t->same($bodyHeadCell, $bodyHeadCellNode->attr('native'), "{$source} body head cell native wrapper");
            $t->same($bodyRow, $bodyRowNode->attr('native'), "{$source} body row native wrapper");
            $t->same(2, $bodyCellNode->attr('rowspan'), "{$source} body cell row span");
            $t->same($bodyCell, $bodyCellNode->attr('native'), "{$source} body cell native wrapper");
            $t->same($tableFoot, $foot->attr('native'), "{$source} table foot native wrapper");
            $t->same($footRow, $footRowNode->attr('native'), "{$source} foot row native wrapper");
            $t->same($footCell, $footCellNode->attr('native'), "{$source} foot cell native wrapper");

            $editedTable = new AstNode('table', array_replace($table->attrs, [
                'id' => 'edited-wrapped-table',
            ]), $table->children);
            $editedDocument = new AstNode('document', $document->attrs, [$editedTable]);

            foreach ([
                "{$source} json" => (new PandocJsonWriter())->toArray($editedDocument),
                "{$source} native" => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];
                $encodedTableContent = $encodedTable['c'];

                $t->same(false, array_key_exists('reviewQueue', $encodedTable), "{$writer} writer regenerates edited table shell");
                $t->same('edited-wrapped-table', $encodedTableContent[0][0], "{$writer} writer emits edited table id");
                $t->same($tableHead, $encodedTableContent[3], "{$writer} writer preserves single wrapped table head wrapper");
                $t->same($tableBody, $encodedTableContent[4][0], "{$writer} writer preserves single wrapped table body wrapper");
                $t->same($tableFoot, $encodedTableContent[5], "{$writer} writer preserves single wrapped table foot wrapper");

                $roundTrips = [
                    "{$writer} json round trip" => (new PandocJsonReader())->readPacket($encoded),
                    "{$writer} native round trip" => (new NativeReader())->read(json_encode($encoded, JSON_THROW_ON_ERROR)),
                ];
                foreach ($roundTrips as $roundTripLabel => $roundTrip) {
                    $roundTripBody = $roundTrip->children[0]->children[1];
                    $roundTripCell = $roundTripBody->children[0]->children[0];

                    $t->same(1, $roundTripBody->attr('rowHeadColumns'), "{$roundTripLabel} row head columns");
                    $t->same(2, $roundTripCell->attr('rowspan'), "{$roundTripLabel} row span");
                    $t->same(1, $roundTripCell->attr('colspan', 1), "{$roundTripLabel} col span");
                }
            }
        }
    },
    'writes shared short caption blocks as pandoc json caption inlines' => static function (TestRunner $t): void {
        $sourceTable = new AstNode('table', [
            'captionBlocks' => [
                new AstNode('plain', [], [
                    new AstNode('text', ['text' => 'JSON']),
                    new AstNode('space'),
                    new AstNode('strong', [], [
                        new AstNode('text', ['text' => 'long']),
                    ]),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'caption']),
                ]),
            ],
            'shortCaptionBlocks' => [
                new AstNode('paragraph', [], [
                    new AstNode('text', ['text' => 'Review']),
                    new AstNode('space'),
                    new AstNode('emph', [], [
                        new AstNode('text', ['text' => 'queue']),
                    ]),
                ]),
            ],
        ], [
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [], [
                        new AstNode('text', ['text' => 'Cell']),
                    ]),
                ]),
            ]),
        ]);
        $document = new AstNode('document', [], [$sourceTable]);
        $reader = new PandocJsonReader();
        $writer = new PandocJsonWriter();

        $sourcePacket = TableGeometry::reviewPacket($sourceTable, ['accessibility' => false]);
        $encoded = $writer->toArray($document);
        $roundTrip = $reader->readPacket($encoded);
        $table = $roundTrip->children[0];
        $roundTripPacket = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same('shortCaptionBlocks', $sourcePacket['captions']['short']['source'] ?? null);
        $t->same('Caption', $encoded['blocks'][0]['c'][1]['t']);
        $t->same('Just', $encoded['blocks'][0]['c'][1]['c'][0]['t']);
        $t->same('ShortCaption', $encoded['blocks'][0]['c'][1]['c'][0]['c']['t']);
        $t->same('Review', $encoded['blocks'][0]['c'][1]['c'][0]['c']['c'][0][0]['c']);
        $t->same('Space', $encoded['blocks'][0]['c'][1]['c'][0]['c']['c'][0][1]['t']);
        $t->same('Emph', $encoded['blocks'][0]['c'][1]['c'][0]['c']['c'][0][2]['t']);
        $t->same('Plain', $encoded['blocks'][0]['c'][1]['c'][1][0]['t']);
        $t->same('JSON long caption', $table->attr('caption'));
        $t->same('Review queue', $table->attr('shortCaption'));
        $t->same('shortCaptionInlines', $roundTripPacket['captions']['short']['source'] ?? null);
        $t->same(['text', 'space', 'emph'], $roundTripPacket['captions']['short']['inlineTypes'] ?? null);
    },
    'flushes mixed table caption and cell inline runs around nested blocks' => static function (TestRunner $t): void {
        $sourceTable = new AstNode('table', [
            'captionBlocks' => [
                new AstNode('text', ['text' => 'Lead']),
                new AstNode('space'),
                new AstNode('strong', [], [
                    new AstNode('text', ['text' => 'caption']),
                ]),
                new AstNode('bullet_list', [], [
                    new AstNode('list_item', [], [
                        new AstNode('text', ['text' => 'Nested']),
                        new AstNode('space'),
                        new AstNode('text', ['text' => 'caption']),
                    ]),
                ]),
                new AstNode('text', ['text' => 'Tail']),
                new AstNode('space'),
                new AstNode('emph', [], [
                    new AstNode('text', ['text' => 'caption']),
                ]),
            ],
        ], [
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [], [
                        new AstNode('text', ['text' => 'Cell']),
                        new AstNode('space'),
                        new AstNode('text', ['text' => 'intro']),
                        new AstNode('blockquote', [], [
                            new AstNode('paragraph', [], [
                                new AstNode('text', ['text' => 'Nested']),
                                new AstNode('space'),
                                new AstNode('text', ['text' => 'quote']),
                            ]),
                        ]),
                        new AstNode('text', ['text' => 'Cell']),
                        new AstNode('space'),
                        new AstNode('text', ['text' => 'outro']),
                    ]),
                ]),
            ]),
        ]);
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], [$sourceTable]);

        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $writer => $packet) {
            $captionBlocks = $packet['blocks'][0]['c'][1]['c'][1];
            $tableBody = $packet['blocks'][0]['c'][4][0]['c'] ?? $packet['blocks'][0]['c'][4][0];
            $tableRow = $tableBody[3][0]['c'] ?? $tableBody[3][0];
            $tableCell = $tableRow[1][0]['c'] ?? $tableRow[1][0];
            $cellBlocks = $tableCell[4];

            $t->same(['Plain', 'BulletList', 'Plain'], array_map(static fn (array $block): string => $block['t'], $captionBlocks), "{$writer} caption block boundary constructors");
            $t->same('Lead', $captionBlocks[0]['c'][0]['c'], "{$writer} caption leading inline run starts a Plain block");
            $t->same('Strong', $captionBlocks[0]['c'][2]['t'], "{$writer} caption leading inline formatting is preserved");
            $t->same('Tail', $captionBlocks[2]['c'][0]['c'], "{$writer} caption trailing inline run starts a Plain block");
            $t->same('Emph', $captionBlocks[2]['c'][2]['t'], "{$writer} caption trailing inline formatting is preserved");
            $t->same(['Plain', 'BlockQuote', 'Plain'], array_map(static fn (array $block): string => $block['t'], $cellBlocks), "{$writer} cell block boundary constructors");
            $t->same('Cell', $cellBlocks[0]['c'][0]['c'], "{$writer} cell leading inline run starts a Plain block");
            $t->same('Nested', $cellBlocks[1]['c'][0]['c'][0]['c'], "{$writer} cell nested block content is preserved");
            $t->same('Cell', $cellBlocks[2]['c'][0]['c'], "{$writer} cell trailing inline run starts a Plain block");
        }

        $jsonRoundTrip = (new PandocJsonReader())->readPacket($jsonPacket);
        $nativeRoundTrip = (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR));

        foreach (['json' => $jsonRoundTrip, 'native' => $nativeRoundTrip] as $source => $roundTrip) {
            $table = $roundTrip->children[0];
            $captionBlocks = $table->attr('captionBlocks');
            $body = $table->children[0];
            $cell = $body->children[0]->children[0];

            $t->same(['plain', 'bullet_list', 'plain'], array_map(static fn (AstNode $block): string => $block->type, $captionBlocks), "{$source} reader keeps mixed caption block boundaries");
            $t->same(['plain', 'blockquote', 'plain'], array_map(static fn (AstNode $block): string => $block->type, $cell->children), "{$source} reader keeps mixed cell block boundaries");
            $t->same("Lead caption\nNested caption\nTail caption", $table->attr('caption'), "{$source} reader preserves mixed caption text");
            $t->same("Cell intro\nNested quote\nCell outro", $cell->attr('text'), "{$source} reader preserves mixed cell text");
        }
    },
    'reads maybe wrapped pandoc json short caption constructors' => static function (TestRunner $t): void {
        $figureBlock = [
            't' => 'Figure',
            'c' => [
                ['json-maybe-figure', ['wp-import'], [['data-source', 'json-filter']]],
                ['t' => 'Caption', 'c' => [
                    ['t' => 'Just', 'c' => ['t' => 'ShortCaption', 'c' => [[
                        ['t' => 'Str', 'c' => 'Figure'],
                        ['t' => 'Space'],
                        ['t' => 'Emph', 'c' => [
                            ['t' => 'Str', 'c' => 'maybe'],
                        ]],
                    ]]]],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Figure'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'long'],
                        ]],
                    ],
                ]],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Image', 'c' => [
                            ['', [], []],
                            [['t' => 'Str', 'c' => 'Alt']],
                            ['media/figure.png', 'Figure title'],
                        ]],
                    ]],
                ],
            ],
        ];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['json-maybe-table', [], []],
                ['t' => 'Caption', 'c' => [
                    ['t' => 'Nothing'],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Table'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'long'],
                        ]],
                    ],
                ]],
                [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                [['', [], []], []],
                [
                    [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => 0],
                        [],
                        [
                            [
                                ['', [], []],
                                [
                                    [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [
                                            ['t' => 'Plain', 'c' => [
                                                ['t' => 'Str', 'c' => 'Cell'],
                                            ]],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [['', [], []], []],
            ],
        ];

        $reader = new PandocJsonReader();
        $writer = new PandocJsonWriter();
        $document = $reader->readPacket([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$figureBlock, $tableBlock],
        ]);
        $figure = $document->children[0];
        $table = $document->children[1];
        $figureShortInlines = $figure->attr('shortCaptionInlines');
        $encoded = $writer->toArray($document);
        $roundTrip = $reader->readPacket($encoded);

        $t->same('Figure maybe', $figure->attr('shortCaption'));
        $t->same(true, is_array($figureShortInlines));
        $t->same(['text', 'space', 'emph'], array_map(static fn (AstNode $node): string => $node->type, $figureShortInlines));
        $t->same('Figure long', $figure->attr('caption'));
        $t->same('Table long', $table->attr('caption'));
        $t->same(null, $table->attr('shortCaption'));
        $t->same('Figure', $encoded['blocks'][0]['t']);
        $t->same($figureBlock['c'][1], $encoded['blocks'][0]['c'][1]);
        $t->same($tableBlock['c'][1], $encoded['blocks'][1]['c'][1]);
        $t->same('Figure', $encoded['blocks'][0]['c'][1]['c'][0]['c']['c'][0][0]['c']);
        $t->same('Emph', $encoded['blocks'][0]['c'][1]['c'][0]['c']['c'][0][2]['t']);
        $t->same(['t' => 'Nothing'], $encoded['blocks'][1]['c'][1]['c'][0]);
        $t->same('Figure maybe', $roundTrip->children[0]->attr('shortCaption'));
        $t->same('Table long', $roundTrip->children[1]->attr('caption'));
        $t->same(null, $roundTrip->children[1]->attr('shortCaption'));
    },
    'maps pandoc definition lists into term and definition ast nodes' => static function (TestRunner $t): void {
        $packet = [
            'blocks' => [
                ['t' => 'DefinitionList', 'c' => [
                    [
                        [['t' => 'Str', 'c' => 'Source'], ['t' => 'Space'], ['t' => 'Str', 'c' => 'Glossary']],
                        [
                            [
                                ['t' => 'Para', 'c' => [
                                    ['t' => 'Str', 'c' => 'Imported'],
                                    ['t' => 'Space'],
                                    ['t' => 'Str', 'c' => 'term'],
                                ]],
                            ],
                            [
                                ['t' => 'Plain', 'c' => [
                                    ['t' => 'Str', 'c' => 'Alias'],
                                ]],
                            ],
                        ],
                    ],
                ]],
            ],
        ];

        $document = (new PandocJsonReader())->readPacket($packet);
        $list = $document->children[0];
        $item = $list->children[0];
        $encoded = (new PandocJsonWriter())->toArray($document);

        $t->same('definition_list', $list->type);
        $t->same('definition_item', $item->type);
        $t->same('definition_term', $item->children[0]->type);
        $t->same('Source Glossary', $item->children[0]->attr('text', 'Source Glossary'));
        $t->same('definition', $item->children[1]->type);
        $t->same('definition', $item->children[2]->type);
        $t->same('DefinitionList', $encoded['blocks'][0]['t']);
        $t->same('Source', $encoded['blocks'][0]['c'][0][0][0]['c']);
        $t->same('Para', $encoded['blocks'][0]['c'][0][1][0][0]['t']);
        $t->same('Plain', $encoded['blocks'][0]['c'][0][1][1][0]['t']);
    },
    'passes native definition term linebreaks through wordpress html handoff' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'DefinitionList', 'c' => [
                    [
                        [
                            ['t' => 'Str', 'c' => 'Cello'],
                            ['t' => 'LineBreak'],
                            ['t' => 'Str', 'c' => 'Violoncello'],
                        ],
                        [
                            [
                                ['t' => 'Para', 'c' => [
                                    ['t' => 'Str', 'c' => 'Low-voiced'],
                                    ['t' => 'Space'],
                                    ['t' => 'Str', 'c' => 'instrument.'],
                                ]],
                            ],
                        ],
                    ],
                ]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $term = $document->children[0]->children[0]->children[0];
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('definition_term', $term->type, "{$source} native definition term node");
            $t->same(['text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $term->children), "{$source} term inline shape");
            $t->contains('<dl><dt>Cello<br/>Violoncello</dt><dd>Low-voiced instrument.</dd></dl>', $blocks, "{$source} wordpress definition term handoff");
        }
    },
    'records pandoc list definition and line helper native payloads on json and native ast nodes' => static function (TestRunner $t): void {
        $bulletItem = [
            ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Bullet'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'source'],
            ]],
        ];
        $orderedItem = [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Ordered'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'source'],
            ]],
        ];
        $definitionTerm = [
            ['t' => 'Str', 'c' => 'Source'],
            ['t' => 'Space'],
            ['t' => 'Code', 'c' => [['term-code', ['native'], [['data-kind', 'term']]], 'Glossary']],
        ];
        $definitionBodies = [
            [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Primary'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'definition'],
                ]],
            ],
            [
                ['t' => 'Plain', 'c' => [
                    ['t' => 'Str', 'c' => 'Alias'],
                ]],
            ],
        ];
        $line = [
            ['t' => 'Str', 'c' => 'Line'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'source'],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'BulletList', 'c' => [$bulletItem]],
                ['t' => 'OrderedList', 'c' => [
                    [4, ['t' => 'LowerAlpha'], ['t' => 'Period']],
                    [$orderedItem],
                ]],
                ['t' => 'DefinitionList', 'c' => [
                    [$definitionTerm, $definitionBodies],
                ]],
                ['t' => 'LineBlock', 'c' => [$line]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $bullet = $document->children[0];
            $ordered = $document->children[1];
            $definitionList = $document->children[2];
            $definitionItem = $definitionList->children[0];
            $term = $definitionItem->children[0];
            $primaryDefinition = $definitionItem->children[1];
            $aliasDefinition = $definitionItem->children[2];
            $lineBlock = $document->children[3];

            $t->same('BulletList', $bullet->attr('constructor'), "{$source} bullet list constructor");
            $t->same($bulletItem, $bullet->children[0]->attr('listItemNative'), "{$source} bullet list item native payload");
            $t->same('OrderedList', $ordered->attr('constructor'), "{$source} ordered list constructor");
            $t->same($orderedItem, $ordered->children[0]->attr('listItemNative'), "{$source} ordered list item native payload");
            $t->same('DefinitionList', $definitionList->attr('constructor'), "{$source} definition list constructor");
            $t->same($packet['blocks'][2], $definitionList->attr('native'), "{$source} definition list native payload");
            $t->same([$definitionTerm, $definitionBodies], $definitionItem->attr('definitionItemNative'), "{$source} definition item native payload");
            $t->same($definitionTerm, $definitionItem->attr('definitionTermNative'), "{$source} definition item term native payload");
            $t->same($definitionBodies, $definitionItem->attr('definitionDefinitionsNative'), "{$source} definition bodies native payload");
            $t->same('Source Glossary', $term->attr('text'), "{$source} definition term text");
            $t->same($definitionTerm, $term->attr('definitionTermNative'), "{$source} definition term native payload");
            $t->same($definitionBodies[0], $primaryDefinition->attr('definitionNative'), "{$source} primary definition native payload");
            $t->same($definitionBodies[1], $aliasDefinition->attr('definitionNative'), "{$source} alias definition native payload");
            $t->same('LineBlock', $lineBlock->attr('constructor'), "{$source} line block constructor");
            $t->same('Line source', $lineBlock->children[0]->attr('text'), "{$source} line text");
            $t->same($line, $lineBlock->children[0]->attr('lineNative'), "{$source} line native payload");
        }

        $jsonPacket = (new PandocJsonWriter())->toArray($documents['json']);
        $nativePacket = json_decode((new NativeWriter())->write($documents['native']), true, 512, JSON_THROW_ON_ERROR);

        $t->same($packet['blocks'], $jsonPacket['blocks']);
        $t->same($packet['blocks'], $nativePacket['blocks']);
    },
    'preserves list definition and line helper payloads when rebuilding wrappers' => static function (TestRunner $t): void {
        $bulletItem = [
            ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Bullet'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'source'],
            ], 'reviewQueue' => 'bullet-item-source'],
        ];
        $orderedItem = [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Ordered'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'source'],
            ], 'reviewQueue' => 'ordered-item-source'],
        ];
        $definitionTerm = [
            ['t' => 'Str', 'c' => 'Source'],
            ['t' => 'Space'],
            ['t' => 'Code', 'c' => [['term-code', ['native'], [['data-kind', 'term']]], 'Glossary'], 'reviewQueue' => 'term-code-source'],
        ];
        $definitionBodies = [
            [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Primary'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'definition'],
                ], 'reviewQueue' => 'primary-definition-source'],
            ],
            [
                ['t' => 'Plain', 'c' => [
                    ['t' => 'Str', 'c' => 'Alias'],
                ], 'reviewQueue' => 'alias-definition-source'],
            ],
        ];
        $line = [
            ['t' => 'Str', 'c' => 'Line'],
            ['t' => 'Space', 'reviewQueue' => 'line-space-source'],
            ['t' => 'Str', 'c' => 'source'],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'BulletList', 'c' => [$bulletItem], 'reviewQueue' => 'bullet-wrapper-source'],
                ['t' => 'OrderedList', 'c' => [
                    [7, ['t' => 'UpperAlpha'], ['t' => 'Period']],
                    [$orderedItem],
                ], 'reviewQueue' => 'ordered-wrapper-source'],
                ['t' => 'DefinitionList', 'c' => [
                    [$definitionTerm, $definitionBodies],
                ], 'reviewQueue' => 'definition-wrapper-source'],
                ['t' => 'LineBlock', 'c' => [$line], 'reviewQueue' => 'line-wrapper-source'],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $bullet = $document->children[0];
            $ordered = $document->children[1];
            $definitionList = $document->children[2];
            $lineBlock = $document->children[3];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('bullet_list', [], $bullet->children),
                new AstNode('ordered_list', array_replace($ordered->attrs, ['start' => 8]), $ordered->children),
                new AstNode('definition_list', [], $definitionList->children),
                new AstNode('line_block', [], $lineBlock->children),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($bulletItem, $encoded['blocks'][0]['c'][0], "{$source} {$writer} writer preserves rebuilt bullet item payload");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} {$writer} writer drops stale bullet wrapper payload");
                $t->same(8, $encoded['blocks'][1]['c'][0][0], "{$source} {$writer} writer regenerates edited ordered list start");
                $t->same($orderedItem, $encoded['blocks'][1]['c'][1][0], "{$source} {$writer} writer preserves rebuilt ordered item payload");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][1]), "{$source} {$writer} writer drops stale ordered wrapper payload");
                $t->same($definitionTerm, $encoded['blocks'][2]['c'][0][0], "{$source} {$writer} writer preserves rebuilt definition term payload");
                $t->same($definitionBodies, $encoded['blocks'][2]['c'][0][1], "{$source} {$writer} writer preserves rebuilt definition body payloads");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][2]), "{$source} {$writer} writer drops stale definition wrapper payload");
                $t->same($line, $encoded['blocks'][3]['c'][0], "{$source} {$writer} writer preserves rebuilt line payload");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][3]), "{$source} {$writer} writer drops stale line wrapper payload");
            }

            $orderedItemNode = $ordered->children[0];
            $orderedParagraph = $orderedItemNode->children[0];
            $editedOrderedItem = new AstNode('list_item', $orderedItemNode->attrs, [
                new AstNode('paragraph', $orderedParagraph->attrs, [
                    new AstNode('text', ['text' => 'Edited']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'source']),
                ]),
            ]);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('ordered_list', array_replace($ordered->attrs, ['start' => 8]), [$editedOrderedItem]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $editedItem = $encoded['blocks'][0]['c'][1][0];

                $t->same('Edited', $editedItem[0]['c'][0]['c'], "{$source} {$writer} writer regenerates edited list item text");
                $t->same(false, array_key_exists('reviewQueue', $editedItem[0]), "{$source} {$writer} writer drops stale edited list item payload");
            }
        }
    },
    'accepts single wrapped list definition and line helper payloads through json and native readers' => static function (TestRunner $t): void {
        $quoteBlocks = [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Quoted'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'source'],
            ]],
        ];
        $bulletBlocks = [
            ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Bullet'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'wrapped'],
            ]],
        ];
        $orderedBlocks = [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Ordered'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'wrapped'],
            ]],
        ];
        $definitionTermInlines = [
            ['t' => 'Str', 'c' => 'Wrapped'],
            ['t' => 'Space'],
            ['t' => 'Code', 'c' => [
                ['definition-code', ['native'], [['data-source', 'definition']]],
                'term',
            ]],
        ];
        $definitionBlocks = [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Wrapped'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'definition'],
            ]],
        ];
        $lineInlines = [
            ['t' => 'Str', 'c' => 'Wrapped'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'line'],
        ];
        $bulletItem = [$bulletBlocks];
        $orderedItem = [$orderedBlocks];
        $definitionTerm = [$definitionTermInlines];
        $definitionBody = [$definitionBlocks];
        $definitionItemPayload = [$definitionTerm, [$definitionBody]];
        $definitionItem = [$definitionItemPayload];
        $line = [$lineInlines];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'BlockQuote', 'c' => [$quoteBlocks], 'reviewQueue' => 'blockquote-single-wrap-source'],
                ['t' => 'BulletList', 'c' => [$bulletItem], 'reviewQueue' => 'bullet-single-wrap-source'],
                ['t' => 'OrderedList', 'c' => [
                    [3, ['t' => 'LowerRoman'], ['t' => 'OneParen']],
                    [$orderedItem],
                ], 'reviewQueue' => 'ordered-single-wrap-source'],
                ['t' => 'DefinitionList', 'c' => [$definitionItem], 'reviewQueue' => 'definition-single-wrap-source'],
                ['t' => 'LineBlock', 'c' => [$line], 'reviewQueue' => 'line-single-wrap-source'],
            ],
        ];
        $withoutWrapperAttrs = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $blockquote = $document->children[0];
            $bullet = $document->children[1];
            $ordered = $document->children[2];
            $definitionList = $document->children[3];
            $definitionItemNode = $definitionList->children[0];
            $definitionTermNode = $definitionItemNode->children[0];
            $definitionNode = $definitionItemNode->children[1];
            $lineBlock = $document->children[4];
            $lineNode = $lineBlock->children[0];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('bullet_list', [], $bullet->children),
                new AstNode('ordered_list', $withoutWrapperAttrs($ordered), $ordered->children),
                new AstNode('definition_list', [], $definitionList->children),
                new AstNode('line_block', [], $lineBlock->children),
            ]);
            $editedDefinitionTerm = new AstNode('definition_term', $definitionTermNode->attrs, [
                new AstNode('text', ['text' => 'Edited']),
                new AstNode('space'),
                new AstNode('code', [
                    'id' => 'definition-code',
                    'classes' => ['native'],
                    'attributes' => ['data-source' => 'definition'],
                    'text' => 'term',
                ]),
            ]);
            $editedDefinitionItem = new AstNode('definition_item', $definitionItemNode->attrs, [
                $editedDefinitionTerm,
                ...array_slice($definitionItemNode->children, 1),
            ]);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('definition_list', [], [$editedDefinitionItem]),
            ]);

            $t->same(['blockquote', 'bullet_list', 'ordered_list', 'definition_list', 'line_block'], array_map(static fn (AstNode $node): string => $node->type, $document->children), "{$source} block helper node types");
            $t->same($packet['blocks'][0], $blockquote->attr('native'), "{$source} blockquote keeps single-wrapped block payload");
            $t->same($bulletItem, $bullet->children[0]->attr('listItemNative'), "{$source} bullet item keeps single-wrapped block list");
            $t->same('Bullet wrapped', $bullet->children[0]->children[0]->attr('text'), "{$source} bullet item text");
            $t->same($orderedItem, $ordered->children[0]->attr('listItemNative'), "{$source} ordered item keeps single-wrapped block list");
            $t->same('lower_roman', $ordered->attr('style'), "{$source} ordered style");
            $t->same($definitionItem, $definitionItemNode->attr('definitionItemNative'), "{$source} definition item keeps single-wrapped tuple");
            $t->same($definitionTerm, $definitionTermNode->attr('definitionTermNative'), "{$source} definition term keeps single-wrapped inlines");
            $t->same($definitionBody, $definitionNode->attr('definitionNative'), "{$source} definition body keeps single-wrapped block list");
            $t->same('Wrapped term', $definitionTermNode->attr('text'), "{$source} definition term text");
            $t->same('Wrapped definition', $definitionNode->children[0]->attr('text'), "{$source} definition body text");
            $t->same($line, $lineNode->attr('lineNative'), "{$source} line keeps single-wrapped inlines");
            $t->same('Wrapped line', $lineNode->attr('text'), "{$source} line text");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($packet['blocks'], $encoded['blocks'], "{$source} {$writer} writer preserves original single-wrapped helper payloads");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($bulletItem, $encoded['blocks'][0]['c'][0], "{$source} {$writer} rebuilt bullet item preserves single-wrap");
                $t->same($orderedItem, $encoded['blocks'][1]['c'][1][0], "{$source} {$writer} rebuilt ordered item preserves single-wrap");
                $t->same($definitionItem, $encoded['blocks'][2]['c'][0], "{$source} {$writer} rebuilt definition item preserves single-wrap");
                $t->same($line, $encoded['blocks'][3]['c'][0], "{$source} {$writer} rebuilt line preserves single-wrap");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedItem = $encoded['blocks'][0]['c'][0];

                $t->same(2, count($encodedItem), "{$source} {$writer} edited definition item emits direct tuple");
                $t->same('Edited', $encodedItem[0][0]['c'], "{$source} {$writer} edited definition term text");
                $t->same(false, $encodedItem === $definitionItem, "{$source} {$writer} edited definition item drops stale outer wrapper");
            }
        }
    },
    'accepts single wrapped definition list item tuples through json and native readers' => static function (TestRunner $t): void {
        $definitionTerm = [
            ['t' => 'Str', 'c' => 'Wrapped'],
            ['t' => 'Space'],
            ['t' => 'Code', 'c' => [['term-code', ['native'], [['data-kind', 'term']]], 'term']],
        ];
        $definitionBlocks = [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Wrapped'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'definition'],
            ]],
        ];
        $definitions = [$definitionBlocks];
        $definitionItem = [[$definitionTerm, $definitions]];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'DefinitionList', 'c' => [
                    $definitionItem,
                ], 'reviewQueue' => 'definition-list-wrapper-source'],
            ],
        ];
        $withoutWrapperNative = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $definitionList = $document->children[0];
            $item = $definitionList->children[0];
            $term = $item->children[0];
            $definition = $item->children[1];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('definition_list', $withoutWrapperNative($definitionList), [$item]),
            ]);
            $editedTerm = new AstNode('definition_term', array_replace($term->attrs, [
                'text' => 'Edited term',
            ]), [
                new AstNode('text', ['text' => 'Edited']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'term']),
            ]);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('definition_list', $withoutWrapperNative($definitionList), [
                    new AstNode('definition_item', $item->attrs, [$editedTerm, $definition]),
                ]),
            ]);

            $t->same('definition_list', $definitionList->type, "{$source} definition list type");
            $t->same($definitionItem, $item->attr('definitionItemNative'), "{$source} records wrapped definition item payload");
            $t->same($definitionTerm, $term->attr('definitionTermNative'), "{$source} records definition term payload");
            $t->same($definitions, $item->attr('definitionDefinitionsNative'), "{$source} records definitions payload");
            $t->same($definitionBlocks, $definition->attr('definitionNative'), "{$source} records definition block payload");
            $t->same('Wrapped term', $term->attr('text'), "{$source} normalizes wrapped definition term text");
            $t->same('Wrapped definition', $definition->children[0]->attr('text'), "{$source} normalizes wrapped definition text");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($definitionItem, $encoded['blocks'][0]['c'][0], "{$source} {$writer} writer preserves wrapped definition item tuple");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} {$writer} writer regenerates definition list wrapper");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedItem = $encoded['blocks'][0]['c'][0];

                $t->same(2, count($encodedItem), "{$source} {$writer} edited item drops stale tuple wrapper");
                $t->same('Edited', $encodedItem[0][0]['c'], "{$source} {$writer} edited term is regenerated");
            }
        }
    },
    'preserves single wrapped definition list item tuples when rebuilding wrappers' => static function (TestRunner $t): void {
        $definitionTerm = [
            ['t' => 'Str', 'c' => 'Wrapped'],
            ['t' => 'Space'],
            ['t' => 'Code', 'c' => [['definition-code', ['native'], [['data-kind', 'term']]], 'item']],
        ];
        $definitionBody = [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Wrapped'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'definition'],
            ]],
        ];
        $definitionItem = [[$definitionTerm, [$definitionBody]]];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'DefinitionList', 'c' => [$definitionItem], 'reviewQueue' => 'definition-list-wrapper-source'],
            ],
        ];
        $withoutWrapperNative = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $definitionList = $document->children[0];
            $item = $definitionList->children[0];
            $term = $item->children[0];
            $definition = $item->children[1];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('definition_list', $withoutWrapperNative($definitionList), $definitionList->children),
            ]);

            $t->same($definitionItem, $item->attr('definitionItemNative'), "{$source} records single wrapped definition item tuple");
            $t->same($definitionTerm, $term->attr('definitionTermNative'), "{$source} records item term payload");
            $t->same($definitionBody, $definition->attr('definitionNative'), "{$source} records item definition payload");
            $t->same('Wrapped item', $term->attr('text'), "{$source} term text");
            $t->same('Wrapped definition', $definition->children[0]->attr('text'), "{$source} definition text");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($definitionItem, $encoded['blocks'][0]['c'][0], "{$source} {$writer} writer preserves single wrapped definition item tuple");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} {$writer} writer regenerates definition list wrapper");
            }

            $editedTerm = new AstNode('definition_term', $term->attrs, [
                new AstNode('text', ['text' => 'Edited']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'item']),
            ]);
            $editedItem = new AstNode('definition_item', $item->attrs, [$editedTerm, $definition]);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('definition_list', $withoutWrapperNative($definitionList), [$editedItem]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedItem = $encoded['blocks'][0]['c'][0];

                $t->same(2, count($encodedItem), "{$source} {$writer} writer unwraps edited definition item tuple");
                $t->same('Edited', $encodedItem[0][0]['c'], "{$source} {$writer} writer regenerates edited term text");
                $t->same($definitionBody, $encodedItem[1][0], "{$source} {$writer} writer preserves unchanged definition body payload");
            }
        }
    },
    'preserves ordered list style and delimiter helper variants through regenerated writers' => static function (TestRunner $t): void {
        $styles = [
            ['constructor' => 'DefaultStyle', 'value' => 'default'],
            ['constructor' => 'Decimal', 'value' => 'decimal'],
            ['constructor' => 'Example', 'value' => 'example'],
            ['constructor' => 'LowerRoman', 'value' => 'lower_roman'],
            ['constructor' => 'UpperRoman', 'value' => 'upper_roman'],
            ['constructor' => 'LowerAlpha', 'value' => 'lower_alpha'],
            ['constructor' => 'UpperAlpha', 'value' => 'upper_alpha'],
        ];
        $delimiters = [
            ['constructor' => 'DefaultDelim', 'value' => 'default'],
            ['constructor' => 'Period', 'value' => 'period'],
            ['constructor' => 'OneParen', 'value' => 'one_paren'],
            ['constructor' => 'TwoParens', 'value' => 'two_parens'],
        ];

        $blocks = [];
        $expectedStarts = [];
        $expectedStyles = [];
        $expectedDelimiters = [];
        $expectedStyleNatives = [];
        $expectedDelimiterNatives = [];
        foreach ($styles as $styleIndex => $style) {
            foreach ($delimiters as $delimiterIndex => $delimiter) {
                $start = 10 + ($styleIndex * count($delimiters)) + $delimiterIndex;
                $label = strtolower($style['constructor'] . '-' . $delimiter['constructor']);
                $styleNative = ['t' => $style['constructor'], 'reviewQueue' => "style-{$label}"];
                $delimiterNative = ['t' => $delimiter['constructor'], 'reviewQueue' => "delimiter-{$label}"];

                $blocks[] = ['t' => 'OrderedList', 'c' => [
                    [$start, $styleNative, $delimiterNative],
                    [[
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => $style['constructor']],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => $delimiter['constructor']],
                        ]],
                    ]],
                ], 'reviewQueue' => "ordered-{$label}"];

                $expectedStarts[] = $start;
                $expectedStyles[] = $style['value'];
                $expectedDelimiters[] = $delimiter['value'];
                $expectedStyleNatives[] = $styleNative;
                $expectedDelimiterNatives[] = $delimiterNative;
            }
        }

        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => $blocks,
        ];
        $stripWrapper = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };
        $orderedAttrs = static fn (array $block): array => $block['c'][0];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $lists = $document->children;
            $t->same(count($blocks), count($lists), "{$source} reads all ordered list helper variants");
            $t->same($expectedStyles, array_map(static fn (AstNode $list): string => $list->attr('style'), $lists), "{$source} ordered list style values");
            $t->same($expectedDelimiters, array_map(static fn (AstNode $list): string => $list->attr('delimiter'), $lists), "{$source} ordered list delimiter values");
            $t->same($expectedStyleNatives, array_map(static fn (AstNode $list): array => $list->attr('listStyleNative'), $lists), "{$source} ordered list style sidecars");
            $t->same($expectedDelimiterNatives, array_map(static fn (AstNode $list): array => $list->attr('listDelimiterNative'), $lists), "{$source} ordered list delimiter sidecars");

            $rebuiltLists = array_map(
                static fn (AstNode $list): AstNode => new AstNode('ordered_list', $stripWrapper($list), $list->children),
                $lists
            );
            $rebuiltDocument = new AstNode('document', $document->attrs, $rebuiltLists);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuiltDocument),
                'native' => json_decode((new NativeWriter())->write($rebuiltDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedAttrs = array_map($orderedAttrs, $encoded['blocks']);

                $t->same($expectedStarts, array_map(static fn (array $attrs): int => $attrs[0], $encodedAttrs), "{$source} {$writer} writer regenerates ordered list starts");
                $t->same($expectedStyleNatives, array_map(static fn (array $attrs): array => $attrs[1], $encodedAttrs), "{$source} {$writer} writer preserves regenerated style helper sidecars");
                $t->same($expectedDelimiterNatives, array_map(static fn (array $attrs): array => $attrs[2], $encodedAttrs), "{$source} {$writer} writer preserves regenerated delimiter helper sidecars");

                $roundTrip = $writer === 'json'
                    ? (new PandocJsonReader())->readPacket($encoded)
                    : (new NativeReader())->read(json_encode($encoded, JSON_THROW_ON_ERROR));
                $t->same($expectedStyles, array_map(static fn (AstNode $list): string => $list->attr('style'), $roundTrip->children), "{$source} {$writer} round trip style values");
                $t->same($expectedDelimiters, array_map(static fn (AstNode $list): string => $list->attr('delimiter'), $roundTrip->children), "{$source} {$writer} round trip delimiter values");
            }
        }
    },
    'preserves scalar enum helper sidecars through regenerated json and native writers' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'OrderedList', 'c' => [
                    [5, 'LowerRoman', 'TwoParens'],
                    [[
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Scalar'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'list'],
                        ]],
                    ]],
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'Quoted', 'c' => [
                        'SingleQuote',
                        [['t' => 'Str', 'c' => 'scalar quote']],
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Math', 'c' => [
                        'DisplayMath',
                        'E = mc^2',
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Cite', 'c' => [
                        [[
                            'citationId' => 'scalar-cite',
                            'citationPrefix' => [],
                            'citationSuffix' => [],
                            'citationMode' => 'SuppressAuthor',
                            'citationNoteNum' => 7,
                            'citationHash' => 707,
                        ]],
                        [['t' => 'Str', 'c' => '@scalar-cite']],
                    ]],
                ]],
                ['t' => 'Table', 'c' => [
                    ['scalar-helper-table', ['json-native'], []],
                    ['t' => 'Caption', 'c' => [['t' => 'Nothing'], []]],
                    [['AlignCenter', 'ColWidthDefault']],
                    ['t' => 'TableHead', 'c' => [['', [], []], []]],
                    [[
                        't' => 'TableBody',
                        'c' => [
                            ['', [], []],
                            ['t' => 'RowHeadColumns', 'c' => 0],
                            [],
                            [[
                                't' => 'Row',
                                'c' => [
                                    ['', [], []],
                                    [[
                                        't' => 'Cell',
                                        'c' => [
                                            ['', [], []],
                                            'AlignRight',
                                            ['t' => 'RowSpan', 'c' => 1],
                                            ['t' => 'ColSpan', 'c' => 1],
                                            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Cell']]]],
                                        ],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                    ['t' => 'TableFoot', 'c' => [['', [], []], []]],
                ]],
            ],
        ];

        $stripWrapper = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };
        $rebuiltInline = static function (AstNode $node) use ($stripWrapper): AstNode {
            $attrs = $stripWrapper($node);
            unset($attrs['citationNative'], $attrs['citationRecordsNative']);

            return new AstNode($node->type, $attrs, $node->children);
        };
        $rebuiltTable = static function (AstNode $table) use ($stripWrapper): AstNode {
            $body = $table->children[0];
            $row = $body->children[0];
            $cell = $row->children[0];

            return new AstNode('table', $stripWrapper($table), [
                new AstNode('table_body', $stripWrapper($body), [
                    new AstNode('table_row', $stripWrapper($row), [
                        new AstNode('table_cell', $stripWrapper($cell), $cell->children),
                    ]),
                ]),
            ]);
        };

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $list = $document->children[0];
            $paragraph = $document->children[1];
            $table = $document->children[2];
            $cell = $table->children[0]->children[0]->children[0];

            $t->same('lower_roman', $list->attr('style'), "{$source} scalar list style");
            $t->same('two_parens', $list->attr('delimiter'), "{$source} scalar list delimiter");
            $t->same('LowerRoman', $list->attr('listStyleNative'), "{$source} scalar list style sidecar");
            $t->same('TwoParens', $list->attr('listDelimiterNative'), "{$source} scalar list delimiter sidecar");
            $t->same('single', $paragraph->children[0]->attr('kind'), "{$source} scalar quote kind");
            $t->same('SingleQuote', $paragraph->children[0]->attr('quoteTypeNative'), "{$source} scalar quote sidecar");
            $t->same(true, $paragraph->children[2]->attr('display'), "{$source} scalar math display");
            $t->same('DisplayMath', $paragraph->children[2]->attr('mathTypeNative'), "{$source} scalar math sidecar");
            $t->same('suppress_author', $paragraph->children[4]->attr('mode'), "{$source} scalar citation mode");
            $t->same('SuppressAuthor', $paragraph->children[4]->attr('citationModeNative'), "{$source} scalar citation sidecar");
            $t->same(['center'], $table->attr('alignments'), "{$source} scalar table alignment");
            $t->same([null], $table->attr('widths'), "{$source} scalar table width");
            $t->same(['AlignCenter'], $table->attr('alignmentNatives'), "{$source} scalar table alignment sidecar");
            $t->same(['ColWidthDefault'], $table->attr('columnWidthNatives'), "{$source} scalar table width sidecar");
            $t->same('right', $cell->attr('align'), "{$source} scalar cell alignment");
            $t->same('AlignRight', $cell->attr('alignmentNative'), "{$source} scalar cell alignment sidecar");

            $rebuiltDocument = new AstNode('document', $document->attrs, [
                new AstNode('ordered_list', $stripWrapper($list), $list->children),
                new AstNode('paragraph', $stripWrapper($paragraph), array_map(
                    static fn (AstNode $child): AstNode => $rebuiltInline($child),
                    $paragraph->children
                )),
                $rebuiltTable($table),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuiltDocument),
                'native' => json_decode((new NativeWriter())->write($rebuiltDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedListAttrs = $encoded['blocks'][0]['c'][0];
                $encodedInlines = $encoded['blocks'][1]['c'];
                $encodedCitation = $encodedInlines[4]['c'][0][0];
                $encodedTable = $encoded['blocks'][2];
                $encodedCell = $encodedTable['c'][4][0]['c'][3][0]['c'][1][0]['c'];

                $t->same('LowerRoman', $encodedListAttrs[1], "{$source} {$writer} preserves scalar list style");
                $t->same('TwoParens', $encodedListAttrs[2], "{$source} {$writer} preserves scalar list delimiter");
                $t->same('SingleQuote', $encodedInlines[0]['c'][0], "{$source} {$writer} preserves scalar quote helper");
                $t->same('DisplayMath', $encodedInlines[2]['c'][0], "{$source} {$writer} preserves scalar math helper");
                $t->same('SuppressAuthor', $encodedCitation['citationMode'], "{$source} {$writer} preserves scalar citation mode");
                $t->same(['AlignCenter', 'ColWidthDefault'], $encodedTable['c'][2][0], "{$source} {$writer} preserves scalar column spec helpers");
                $t->same('AlignRight', $encodedCell[1], "{$source} {$writer} preserves scalar cell alignment helper");
            }
        }
    },
    'preserves tagged ordered list attribute constructors through rebuilt writers' => static function (TestRunner $t): void {
        $styleNative = ['t' => 'LowerAlpha', 'reviewQueue' => 'list-attribute-style'];
        $delimiterNative = ['t' => 'TwoParens', 'reviewQueue' => 'list-attribute-delimiter'];
        $listAttributes = [
            't' => 'ListAttributes',
            'c' => [7, $styleNative, $delimiterNative],
            'reviewQueue' => 'list-attributes-source',
        ];
        $wrappedListAttributes = [
            't' => 'ListAttributes',
            'c' => [[4, ['t' => 'UpperRoman'], ['t' => 'Period']]],
            'reviewQueue' => 'wrapped-list-attributes-source',
        ];
        $blocks = [
            ['t' => 'OrderedList', 'c' => [
                $listAttributes,
                [[
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Tagged'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'attributes'],
                    ]],
                ]],
            ]],
            ['t' => 'OrderedList', 'c' => [
                $wrappedListAttributes,
                [[
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Wrapped'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'attributes'],
                    ]],
                ]],
            ]],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => $blocks,
        ];
        $stripWrapper = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $direct = $document->children[0];
            $wrapped = $document->children[1];

            $t->same('ordered_list', $direct->type, "{$source} direct list type");
            $t->same('ListAttributes', $direct->attr('listAttributesConstructor'), "{$source} direct list attributes constructor");
            $t->same($listAttributes, $direct->attr('listAttributesNative'), "{$source} direct list attributes native payload");
            $t->same(7, $direct->attr('start'), "{$source} direct list start");
            $t->same('lower_alpha', $direct->attr('style'), "{$source} direct list style");
            $t->same('two_parens', $direct->attr('delimiter'), "{$source} direct list delimiter");
            $t->same('ListAttributes', $wrapped->attr('listAttributesConstructor'), "{$source} wrapped list attributes constructor");
            $t->same($wrappedListAttributes, $wrapped->attr('listAttributesNative'), "{$source} wrapped list attributes native payload");
            $t->same(4, $wrapped->attr('start'), "{$source} wrapped list start");
            $t->same('upper_roman', $wrapped->attr('style'), "{$source} wrapped list style");
            $t->same('period', $wrapped->attr('delimiter'), "{$source} wrapped list delimiter");
            $t->same($blocks, (new PandocJsonWriter())->toArray($document)['blocks'], "{$source} json writer preserves unchanged list attributes payloads");
            $t->same($blocks, json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR)['blocks'], "{$source} native writer preserves unchanged list attributes payloads");

            $rebuiltDocument = new AstNode('document', $document->attrs, [
                new AstNode('ordered_list', array_replace($stripWrapper($direct), ['start' => 9]), $direct->children),
                new AstNode('ordered_list', $stripWrapper($wrapped), $wrapped->children),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuiltDocument),
                'native' => json_decode((new NativeWriter())->write($rebuiltDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $rebuiltListAttributes = $encoded['blocks'][0]['c'][0];
                $rebuiltWrappedAttributes = $encoded['blocks'][1]['c'][0];

                $t->same('ListAttributes', $rebuiltListAttributes['t'], "{$source} {$writer} writer keeps edited list attributes constructor");
                $t->same(9, $rebuiltListAttributes['c'][0], "{$source} {$writer} writer regenerates edited list start");
                $t->same($styleNative, $rebuiltListAttributes['c'][1], "{$source} {$writer} writer preserves style helper sidecar");
                $t->same($delimiterNative, $rebuiltListAttributes['c'][2], "{$source} {$writer} writer preserves delimiter helper sidecar");
                $t->same('list-attributes-source', $rebuiltListAttributes['reviewQueue'], "{$source} {$writer} writer preserves list attributes sidecar");
                $t->same($wrappedListAttributes, $rebuiltWrappedAttributes, "{$source} {$writer} writer keeps single wrapped list attributes payload");
            }
        }
    },
    'preserves single wrapped list definition and line helper payloads when rebuilding wrappers' => static function (TestRunner $t): void {
        $bulletBlocks = [
            ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Wrapped'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'bullet'],
            ]],
        ];
        $orderedBlocks = [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Wrapped'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'ordered'],
            ]],
        ];
        $definitionTermInlines = [
            ['t' => 'Str', 'c' => 'Wrapped'],
            ['t' => 'Space'],
            ['t' => 'Code', 'c' => [['term-code', ['native'], [['data-kind', 'term']]], 'term']],
        ];
        $definitionBlocks = [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Wrapped'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'definition'],
            ]],
        ];
        $lineInlines = [
            ['t' => 'Str', 'c' => 'Wrapped'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'line'],
        ];
        $bulletItem = [$bulletBlocks];
        $orderedItem = [$orderedBlocks];
        $definitionTerm = [$definitionTermInlines];
        $definitionBody = [$definitionBlocks];
        $line = [$lineInlines];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'BulletList', 'c' => [$bulletItem], 'reviewQueue' => 'bullet-wrapper-source'],
                ['t' => 'OrderedList', 'c' => [
                    [3, ['t' => 'LowerRoman'], ['t' => 'OneParen']],
                    [$orderedItem],
                ], 'reviewQueue' => 'ordered-wrapper-source'],
                ['t' => 'DefinitionList', 'c' => [
                    [$definitionTerm, [$definitionBody]],
                ], 'reviewQueue' => 'definition-wrapper-source'],
                ['t' => 'LineBlock', 'c' => [$line], 'reviewQueue' => 'line-wrapper-source'],
            ],
        ];
        $withoutWrapperNative = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $bullet = $document->children[0];
            $ordered = $document->children[1];
            $definitionList = $document->children[2];
            $lineBlock = $document->children[3];
            $definitionItem = $definitionList->children[0];
            $definitionTermNode = $definitionItem->children[0];
            $definitionNode = $definitionItem->children[1];
            $lineNode = $lineBlock->children[0];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('bullet_list', $withoutWrapperNative($bullet), $bullet->children),
                new AstNode('ordered_list', $withoutWrapperNative($ordered), $ordered->children),
                new AstNode('definition_list', $withoutWrapperNative($definitionList), $definitionList->children),
                new AstNode('line_block', $withoutWrapperNative($lineBlock), $lineBlock->children),
            ]);

            $t->same($bulletItem, $bullet->children[0]->attr('listItemNative'), "{$source} records single wrapped bullet item payload");
            $t->same($orderedItem, $ordered->children[0]->attr('listItemNative'), "{$source} records single wrapped ordered item payload");
            $t->same($definitionTerm, $definitionTermNode->attr('definitionTermNative'), "{$source} records single wrapped definition term payload");
            $t->same($definitionBody, $definitionNode->attr('definitionNative'), "{$source} records single wrapped definition body payload");
            $t->same($line, $lineNode->attr('lineNative'), "{$source} records single wrapped line payload");
            $t->same('Wrapped term', $definitionTermNode->attr('text'), "{$source} normalizes single wrapped definition term text");
            $t->same('Wrapped line', $lineNode->attr('text'), "{$source} normalizes single wrapped line text");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($bulletItem, $encoded['blocks'][0]['c'][0], "{$source} {$writer} writer preserves single wrapped bullet item");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][0]), "{$source} {$writer} writer regenerates bullet wrapper");
                $t->same($orderedItem, $encoded['blocks'][1]['c'][1][0], "{$source} {$writer} writer preserves single wrapped ordered item");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][1]), "{$source} {$writer} writer regenerates ordered wrapper");
                $t->same($definitionTerm, $encoded['blocks'][2]['c'][0][0], "{$source} {$writer} writer preserves single wrapped definition term");
                $t->same($definitionBody, $encoded['blocks'][2]['c'][0][1][0], "{$source} {$writer} writer preserves single wrapped definition body");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][2]), "{$source} {$writer} writer regenerates definition wrapper");
                $t->same($line, $encoded['blocks'][3]['c'][0], "{$source} {$writer} writer preserves single wrapped line");
                $t->same(false, array_key_exists('reviewQueue', $encoded['blocks'][3]), "{$source} {$writer} writer regenerates line wrapper");
            }
        }
    },
    'accepts single wrapped block and table helper constructor tuples from json and native ast' => static function (TestRunner $t): void {
        $headerBlock = ['t' => 'Header', 'c' => [[
            2,
            ['wrapped-heading', ['json-native'], [['data-kind', 'wrapped-block']]],
            [
                ['t' => 'Str', 'c' => 'Wrapped'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'heading'],
            ],
        ]], 'reviewQueue' => 'header-wrapper'];
        $codeBlock = ['t' => 'CodeBlock', 'c' => [[
            ['wrapped-code', ['php'], [['data-kind', 'wrapped-block']]],
            "echo 'wrapped';\n",
        ]], 'reviewQueue' => 'code-wrapper'];
        $rawBlock = ['t' => 'RawBlock', 'c' => [[
            ['t' => 'Format', 'c' => 'html'],
            '<aside>wrapped raw</aside>',
        ]], 'reviewQueue' => 'raw-wrapper'];
        $divBlock = ['t' => 'Div', 'c' => [[
            ['wrapped-div', ['review'], [['data-kind', 'wrapped-block']]],
            [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Wrapped'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'div'],
                ]],
            ],
        ]], 'reviewQueue' => 'div-wrapper'];
        $orderedBlock = ['t' => 'OrderedList', 'c' => [[
            [5, ['t' => 'UpperRoman'], ['t' => 'TwoParens']],
            [[
                ['t' => 'Plain', 'c' => [
                    ['t' => 'Str', 'c' => 'Wrapped'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'ordered'],
                ]],
            ]],
        ]], 'reviewQueue' => 'ordered-wrapper'];
        $definitionTerm = [
            ['t' => 'Str', 'c' => 'Wrapped'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'term'],
        ];
        $definitionBlocks = [
            ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Wrapped'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'definition'],
            ]],
        ];
        $definitionBlock = ['t' => 'DefinitionList', 'c' => [
            [[$definitionTerm, [$definitionBlocks]]],
        ], 'reviewQueue' => 'definition-wrapper'];
        $figureBlock = ['t' => 'Figure', 'c' => [[
            ['wrapped-figure', ['review'], [['data-kind', 'wrapped-block']]],
            ['t' => 'Caption', 'c' => [
                ['t' => 'Nothing'],
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Wrapped'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'figure'],
                    ]],
                ],
            ]],
            [
                ['t' => 'Plain', 'c' => [
                    ['t' => 'Str', 'c' => 'Figure'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'body'],
                ]],
            ],
        ]], 'reviewQueue' => 'figure-wrapper'];

        $cell = ['t' => 'Cell', 'c' => [[
            ['wrapped-cell', ['body'], [['data-kind', 'wrapped-cell']]],
            ['t' => 'AlignRight', 'reviewQueue' => 'cell-align'],
            ['t' => 'RowSpan', 'c' => [2], 'reviewQueue' => 'cell-rowspan'],
            ['t' => 'ColSpan', 'c' => [3], 'reviewQueue' => 'cell-colspan'],
            [
                ['t' => 'Plain', 'c' => [
                    ['t' => 'Str', 'c' => 'Wrapped'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'cell'],
                ]],
            ],
        ]], 'reviewQueue' => 'cell-wrapper'];
        $row = ['t' => 'Row', 'c' => [[
            ['wrapped-row', ['body'], [['data-kind', 'wrapped-row']]],
            [$cell],
        ]], 'reviewQueue' => 'row-wrapper'];
        $head = ['t' => 'TableHead', 'c' => [[
            ['wrapped-head', ['header'], [['data-kind', 'wrapped-head']]],
            [$row],
        ]], 'reviewQueue' => 'head-wrapper'];
        $body = ['t' => 'TableBody', 'c' => [[
            ['wrapped-body', ['body'], [['data-kind', 'wrapped-body']]],
            ['t' => 'RowHeadColumns', 'c' => [1], 'reviewQueue' => 'row-head-wrapper'],
            [],
            [$row],
        ]], 'reviewQueue' => 'body-wrapper'];
        $foot = ['t' => 'TableFoot', 'c' => [[
            ['wrapped-foot', ['footer'], [['data-kind', 'wrapped-foot']]],
            [],
        ]], 'reviewQueue' => 'foot-wrapper'];
        $tableBlock = ['t' => 'Table', 'c' => [[
            ['wrapped-table', ['review'], [['data-kind', 'wrapped-table']]],
            ['t' => 'Caption', 'c' => [
                ['t' => 'Nothing'],
                [],
            ]],
            [
                [
                    [
                        ['t' => 'AlignRight', 'reviewQueue' => 'col-align'],
                        ['t' => 'ColWidth', 'c' => [0.25], 'reviewQueue' => 'col-width'],
                    ],
                ],
            ],
            $head,
            [$body],
            $foot,
        ]], 'reviewQueue' => 'table-wrapper'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                $headerBlock,
                $codeBlock,
                $rawBlock,
                $divBlock,
                $orderedBlock,
                $definitionBlock,
                $figureBlock,
                $tableBlock,
            ],
        ];
        $withoutNativeWrapper = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $children = $document->children;
            $table = $children[7];
            $tableHead = $table->children[0];
            $tableBody = $table->children[1];
            $tableFoot = $table->children[2];
            $tableCell = $tableHead->children[0]->children[0];

            $t->same(['heading', 'code_block', 'raw_html', 'div', 'ordered_list', 'definition_list', 'figure', 'table'], array_map(static fn (AstNode $node): string => $node->type, $children), "{$source} wrapped block tuple types");
            $t->same('Wrapped heading', $children[0]->attr('text'), "{$source} wrapped heading text");
            $t->same(['php'], $children[1]->attr('classes'), "{$source} wrapped code attr classes");
            $t->same('<aside>wrapped raw</aside>', $children[2]->attr('html'), "{$source} wrapped raw block html");
            $t->same('wrapped-div', $children[3]->attr('id'), "{$source} wrapped div id");
            $t->same([5, 'upper_roman', 'two_parens'], [$children[4]->attr('start'), $children[4]->attr('style'), $children[4]->attr('delimiter')], "{$source} wrapped ordered list attributes");
            $t->same('Wrapped term', $children[5]->children[0]->children[0]->attr('text'), "{$source} wrapped definition item tuple");
            $t->same('Wrapped figure', $children[6]->attr('caption'), "{$source} wrapped figure caption");
            $t->same('wrapped-table', $table->attr('id'), "{$source} wrapped table id");
            $t->same('wrapped-head', $tableHead->attr('id'), "{$source} wrapped table head id");
            $t->same('wrapped-body', $tableBody->attr('id'), "{$source} wrapped table body id");
            $t->same('wrapped-foot', $tableFoot->attr('id'), "{$source} wrapped table foot id");
            $t->same([1, 2, 3], [$tableBody->attr('rowHeadColumns'), $tableCell->attr('rowspan'), $tableCell->attr('colspan')], "{$source} wrapped table integer helpers");
            $t->same([['right'], [0.25]], [$table->attr('alignments'), $table->attr('widths')], "{$source} wrapped table column spec");
            $t->same('Wrapped cell', $tableCell->attr('text'), "{$source} wrapped table cell text");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($packet['blocks'], $encoded['blocks'], "{$source} {$writer} preserves wrapped block tuple payloads");
            }

            $rebuiltTableDocument = new AstNode('document', $document->attrs, [
                new AstNode('table', $withoutNativeWrapper($table), $table->children),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuiltTableDocument),
                'native' => json_decode((new NativeWriter())->write($rebuiltTableDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $rebuiltTable = $encoded['blocks'][0];

                $t->same('Table', $rebuiltTable['t'], "{$source} {$writer} rebuilt table constructor");
                $t->same($head, $rebuiltTable['c'][3], "{$source} {$writer} preserves wrapped table head helper tuple");
                $t->same($body, $rebuiltTable['c'][4][0], "{$source} {$writer} preserves wrapped table body helper tuple");
                $t->same($foot, $rebuiltTable['c'][5], "{$source} {$writer} preserves wrapped table foot helper tuple");
                $t->same($row, $rebuiltTable['c'][3]['c'][0][1][0], "{$source} {$writer} preserves wrapped row helper tuple");
                $t->same($cell, $rebuiltTable['c'][3]['c'][0][1][0]['c'][0][1][0], "{$source} {$writer} preserves wrapped cell helper tuple");
            }
        }
    },
    'preserves empty table section native sidecars when rebuilding table wrappers' => static function (TestRunner $t): void {
        $headNative = [
            't' => 'TableHead',
            'c' => [
                ['', [], []],
                [],
            ],
            'reviewQueue' => 'empty-head-source',
            'sourceOrdinal' => 101,
        ];
        $cellNative = [
            't' => 'Cell',
            'c' => [
                ['', [], []],
                ['t' => 'AlignDefault'],
                ['t' => 'RowSpan', 'c' => 1],
                ['t' => 'ColSpan', 'c' => 1],
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Metric'],
                    ]],
                ],
            ],
        ];
        $rowNative = [
            't' => 'Row',
            'c' => [
                ['', [], []],
                [$cellNative],
            ],
        ];
        $bodyNative = [
            't' => 'TableBody',
            'c' => [
                ['', [], []],
                ['t' => 'RowHeadColumns', 'c' => 0],
                [],
                [$rowNative],
            ],
        ];
        $footNative = [
            't' => 'TableFoot',
            'c' => [
                ['', [], []],
                [],
            ],
            'reviewQueue' => 'empty-foot-source',
            'sourceOrdinal' => 102,
        ];
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['source-table', [], []],
                ['t' => 'Caption', 'c' => [null, []]],
                [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                $headNative,
                [$bodyNative],
                $footNative,
            ],
            'reviewQueue' => 'table-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $head = $table->children[0];
            $body = $table->children[1];
            $foot = $table->children[2];

            $t->same(['table_head', 'table_body', 'table_foot'], array_map(static fn (AstNode $node): string => $node->type, $table->children), "{$source} reader keeps empty sidecar sections");
            $t->same($headNative, $head->attr('native'), "{$source} reader preserves empty head native sidecar payload");
            $t->same($footNative, $foot->attr('native'), "{$source} reader preserves empty foot native sidecar payload");

            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('table', array_replace($table->attrs, ['id' => 'rebuilt-empty-sections']), $table->children),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];

                $t->same('rebuilt-empty-sections', $encodedTable['c'][0][0], "{$source} {$writer} writer regenerates edited table attr");
                $t->same(false, array_key_exists('reviewQueue', $encodedTable), "{$source} {$writer} writer drops stale table sidecar");
                $t->same($headNative, $encodedTable['c'][3], "{$source} {$writer} writer preserves empty table head helper sidecar");
                $t->same($bodyNative, $encodedTable['c'][4][0], "{$source} {$writer} writer preserves body helper payload");
                $t->same($footNative, $encodedTable['c'][5], "{$source} {$writer} writer preserves empty table foot helper sidecar");
            }

            $editedHead = new AstNode('table_head', array_replace($head->attrs, ['id' => 'edited-head']), $head->children);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('table', array_replace($table->attrs, ['id' => 'edited-empty-head']), [$editedHead, $body, $foot]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedTable = $encoded['blocks'][0];
                $editedHeadPayload = $encodedTable['c'][3];

                $t->same('edited-empty-head', $encodedTable['c'][0][0], "{$source} {$writer} edited head keeps edited table attr");
                $t->same('TableHead', $editedHeadPayload['t'], "{$source} {$writer} edited empty head regenerates head constructor");
                $t->same('edited-head', $editedHeadPayload['c'][0][0], "{$source} {$writer} edited empty head regenerates attr");
                $t->same(false, array_key_exists('reviewQueue', $editedHeadPayload), "{$source} {$writer} edited empty head drops stale sidecar");
                $t->same($footNative, $encodedTable['c'][5], "{$source} {$writer} edited empty head preserves empty foot sidecar");
            }
        }
    },
    'reads single-wrapped task list item checkbox sidecars from json and native ast' => static function (TestRunner $t): void {
        $uncheckedBlocks = [
            ['t' => 'Plain', 'c' => [
                ['t' => 'Str', 'c' => 'Prepare'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'packet'],
            ], 'taskChecked' => false, 'reviewQueue' => 'unchecked-task-source'],
        ];
        $checkedBlocks = [
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Ship'],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'packet'],
            ], 'taskChecked' => true, 'reviewQueue' => 'checked-task-source'],
        ];
        $items = [[$uncheckedBlocks], [$checkedBlocks]];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'BulletList', 'c' => $items, 'reviewQueue' => 'task-list-wrapper'],
            ],
        ];
        $stripWrapperAttrs = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $list = $document->children[0];
            $unchecked = $list->children[0];
            $checked = $list->children[1];

            $t->same('bullet_list', $list->type, "{$source} reads single-wrapped task list");
            $t->same(true, $list->attr('taskList'), "{$source} marks list as task list");
            $t->same(false, $unchecked->attr('taskChecked'), "{$source} reads unchecked sidecar through wrapper");
            $t->same(true, $checked->attr('taskChecked'), "{$source} reads checked sidecar through wrapper");
            $t->same([$uncheckedBlocks], $unchecked->attr('listItemNative'), "{$source} keeps unchecked single-wrapped item payload");
            $t->same([$checkedBlocks], $checked->attr('listItemNative'), "{$source} keeps checked single-wrapped item payload");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($document),
                'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($items, $encoded['blocks'][0]['c'], "{$source} {$writer} writer preserves single-wrapped task item payloads");
            }

            $editedUnchecked = new AstNode('list_item', $unchecked->attrs, [
                new AstNode('plain', [], [
                    new AstNode('text', ['text' => 'Review']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'packet']),
                ]),
            ]);
            $editedDocument = new AstNode('document', $document->attrs, [
                new AstNode('bullet_list', $stripWrapperAttrs($list), [$editedUnchecked, $checked]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($editedDocument),
                'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $editedItemBlocks = $encoded['blocks'][0]['c'][0];

                $t->same('Review', $editedItemBlocks[0]['c'][0]['c'], "{$source} {$writer} writer regenerates edited task item text");
                $t->same(false, $editedItemBlocks[0]['taskChecked'], "{$source} {$writer} writer keeps unchecked sidecar on edited item");
                $t->same(false, array_key_exists('reviewQueue', $editedItemBlocks[0]), "{$source} {$writer} writer drops stale edited task block sidecar");
                $t->same([$checkedBlocks], $encoded['blocks'][0]['c'][1], "{$source} {$writer} writer preserves unchanged checked wrapper");
            }
        }
    },
    'preserves task list checkbox sidecars through json and native list items' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('bullet_list', ['taskList' => true], [
                new AstNode('list_item', ['taskChecked' => false], [
                    $paragraph('Prepare media queue'),
                    new AstNode('bullet_list', ['taskList' => true], [
                        new AstNode('list_item', ['taskChecked' => true], [
                            $paragraph('Nested done'),
                        ]),
                    ]),
                ]),
                new AstNode('list_item', ['taskChecked' => true], [
                    $paragraph('Publish packet'),
                ]),
            ]),
        ]);

        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $source => $packet) {
            $outerItems = $packet['blocks'][0]['c'];
            $nestedItems = $outerItems[0][1]['c'];

            $t->same(false, $outerItems[0][0]['taskChecked'], "{$source} unchecked item sidecar");
            $t->same(true, $outerItems[1][0]['taskChecked'], "{$source} checked item sidecar");
            $t->same(true, $nestedItems[0][0]['taskChecked'], "{$source} nested checked item sidecar");
        }

        $roundTrips = [
            'json' => (new PandocJsonReader())->readPacket($jsonPacket),
            'native' => (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR)),
        ];

        foreach ($roundTrips as $source => $roundTrip) {
            $list = $roundTrip->children[0];
            $firstItem = $list->children[0];
            $nestedList = $firstItem->children[1];

            $t->same(true, $list->attr('taskList'), "{$source} bullet list task metadata");
            $t->same(false, $firstItem->attr('taskChecked'), "{$source} unchecked item metadata");
            $t->same(true, $list->children[1]->attr('taskChecked'), "{$source} checked item metadata");
            $t->same(true, $nestedList->attr('taskList'), "{$source} nested list task metadata");
            $t->same(true, $nestedList->children[0]->attr('taskChecked'), "{$source} nested checked item metadata");

            $markdown = (new MarkdownWriter())->write($roundTrip);
            $wordpress = (new WordPressBlockWriter())->write($roundTrip);
            $latex = (new LatexWriter())->write($roundTrip);

            $t->contains('- [ ] Prepare media queue', $markdown, "{$source} markdown unchecked marker");
            $t->contains('  - [x] Nested done', $markdown, "{$source} markdown nested checked marker");
            $t->contains('- [x] Publish packet', $markdown, "{$source} markdown checked marker");
            $t->contains('<ul class="task-list"><li><label><input type="checkbox" />Prepare media queue</label><ul class="task-list"><li><label><input type="checkbox" checked="" />Nested done</label></li></ul></li><li><label><input type="checkbox" checked="" />Publish packet</label></li></ul>', $wordpress, "{$source} wordpress checkbox handoff");
            $t->contains('\item[$\square$]', $latex, "{$source} latex unchecked task label");
            $t->contains('\item[$\boxtimes$]', $latex, "{$source} latex checked task label");
        }
    },
    'writes generated labeled note constructors through json and native writers' => static function (TestRunner $t): void {
        $noteBlocks = [
            new AstNode('plain', [], [
                new AstNode('text', ['text' => 'Review']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'source']),
            ]),
        ];
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Archive']),
                new AstNode('space'),
                new AstNode('note', ['label' => 'source-review-note'], $noteBlocks),
                new AstNode('space'),
                new AstNode('note', ['label' => 'invalid label'], [
                    new AstNode('paragraph', [], [
                        new AstNode('text', ['text' => 'Invalid']),
                        new AstNode('space'),
                        new AstNode('text', ['text' => 'label']),
                    ]),
                ]),
            ]),
        ]);

        $packets = [
            'json' => (new PandocJsonWriter())->toArray($document),
            'native' => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
        ];

        foreach ($packets as $writer => $packet) {
            $inlines = $packet['blocks'][0]['c'];
            $labeledNote = $inlines[2];
            $invalidNote = $inlines[4];

            $t->same('Para', $packet['blocks'][0]['t'], "{$writer} paragraph constructor");
            $t->same('Note', $labeledNote['t'], "{$writer} labeled note constructor");
            $t->same('source-review-note', $labeledNote['noteLabel'] ?? null, "{$writer} labeled note sidecar");
            $t->same('Plain', $labeledNote['c'][0]['t'], "{$writer} labeled note child block");
            $t->same('Review', $labeledNote['c'][0]['c'][0]['c'], "{$writer} labeled note child text");
            $t->same('Note', $invalidNote['t'], "{$writer} invalid-label note constructor");
            $t->same(false, array_key_exists('noteLabel', $invalidNote), "{$writer} rejects invalid generated note label");
            $t->same('Para', $invalidNote['c'][0]['t'], "{$writer} invalid-label note child block");
        }

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packets['json']),
            'native' => (new NativeReader())->read(json_encode($packets['native'], JSON_THROW_ON_ERROR)),
        ] as $source => $roundTrip) {
            $paragraph = $roundTrip->children[0];
            $notes = array_values(array_filter(
                $paragraph->children,
                static fn (AstNode $node): bool => $node->type === 'note'
            ));
            $labeledNote = $notes[0] ?? new AstNode('missing');
            $invalidNote = $notes[1] ?? new AstNode('missing');

            $t->same('note', $labeledNote->type, "{$source} labeled note round-trip type");
            $t->same('source-review-note', $labeledNote->attr('label'), "{$source} labeled note round-trip label");
            $t->same('plain', $labeledNote->children[0]->type, "{$source} labeled note round-trip block");
            $t->same('Review source', $labeledNote->children[0]->attr('text'), "{$source} labeled note round-trip text");
            $t->same('note', $invalidNote->type, "{$source} invalid-label note round-trip type");
            $t->same(null, $invalidNote->attr('label'), "{$source} invalid-label note omits label");
        }
    },
    'accepts single-wrapped cite citation record lists from json and native ast' => static function (TestRunner $t): void {
        $sourceInlines = [
            ['t' => 'Str', 'c' => '[see'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '@smith1899;'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '@doe1901]'],
        ];
        $singleSourceInlines = [
            ['t' => 'Str', 'c' => 'see'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '@smith1899'],
        ];
        $firstRecord = [
            'citationId' => 'smith1899',
            'citationPrefix' => [['t' => 'Str', 'c' => 'see']],
            'citationSuffix' => [],
            'citationMode' => ['t' => 'NormalCitation'],
            'citationNoteNum' => 0,
            'citationHash' => 1899,
            'reviewQueue' => 'first-citation-source',
        ];
        $secondRecord = [
            'citationId' => 'doe1901',
            'citationPrefix' => [],
            'citationSuffix' => [],
            'citationMode' => ['t' => 'AuthorInText'],
            'citationNoteNum' => 0,
            'citationHash' => 1901,
            'reviewQueue' => 'second-citation-source',
        ];
        $records = [$firstRecord, $secondRecord];
        $recordsNative = [$records];
        $singleRecords = [$firstRecord];
        $singleRecordsNative = [$singleRecords];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Cite', 'c' => [
                        $recordsNative,
                        $sourceInlines,
                    ]],
                    ['t' => 'Space'],
                    ['t' => 'Cite', 'c' => [
                        $singleRecordsNative,
                        $singleSourceInlines,
                    ]],
                ]],
            ],
        ];
        $stripWrapperAttrs = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $cluster = $document->children[0]->children[0];
            $singleCitation = $document->children[0]->children[2];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('citation_group', $stripWrapperAttrs($cluster), $cluster->children),
                    new AstNode('space'),
                    new AstNode('citation', $stripWrapperAttrs($singleCitation), $singleCitation->children),
                ]),
            ]);
            $editedFirstCitation = new AstNode('citation', array_replace($cluster->children[0]->attrs, [
                'citationHash' => 1999,
            ]), $cluster->children[0]->children);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('citation_group', $stripWrapperAttrs($cluster), [
                        $editedFirstCitation,
                        $cluster->children[1],
                    ]),
                ]),
            ]);

            $t->same('citation_group', $cluster->type, "{$source} single-wrapped multi-record cite becomes citation group");
            $t->same('citation', $singleCitation->type, "{$source} single-wrapped one-record cite becomes citation");
            $t->same($recordsNative, $cluster->attr('citationRecordsNative'), "{$source} group keeps original single-wrapped record list");
            $t->same($singleRecordsNative, $singleCitation->attr('citationRecordsNative'), "{$source} citation keeps original single-wrapped record list");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedClusterCite = $encoded['blocks'][0]['c'][0];
                $encodedSingleCite = $encoded['blocks'][0]['c'][2];

                $t->same('Cite', $encodedClusterCite['t'], "{$source} {$writer} group writer emits Cite constructor");
                $t->same('Cite', $encodedSingleCite['t'], "{$source} {$writer} citation writer emits Cite constructor");
                $t->same($recordsNative, $encodedClusterCite['c'][0], "{$source} {$writer} group writer preserves single-wrapped citation records");
                $t->same($singleRecordsNative, $encodedSingleCite['c'][0], "{$source} {$writer} citation writer preserves single-wrapped citation record");
                $t->same($sourceInlines, $encodedClusterCite['c'][1], "{$source} {$writer} group writer preserves cite source inlines");
                $t->same($singleSourceInlines, $encodedSingleCite['c'][1], "{$source} {$writer} citation writer preserves cite source inlines");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedRecords = $encoded['blocks'][0]['c'][0]['c'][0];

                $t->same(2, count($encodedRecords), "{$source} {$writer} edited group emits an unwrapped citation record list");
                $t->same(1999, $encodedRecords[0]['citationHash'], "{$source} {$writer} edited group emits edited citation hash");
                $t->same(false, array_key_exists('reviewQueue', $encodedRecords[0]), "{$source} {$writer} edited group drops stale edited citation sidecar");
                $t->same('second-citation-source', $encodedRecords[1]['reviewQueue'], "{$source} {$writer} edited group preserves unchanged citation sidecar");
            }
        }
    },
    'preserves tagged citation record constructors through json and native ast' => static function (TestRunner $t): void {
        $sourceInlines = [
            ['t' => 'Str', 'c' => '[see'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '@smith1899;'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '@doe1901]'],
        ];
        $firstRecordPayload = [
            'citationId' => 'smith1899',
            'citationPrefix' => [['t' => 'Str', 'c' => 'see']],
            'citationSuffix' => [['t' => 'Str', 'c' => 'p. 4']],
            'citationMode' => ['t' => 'NormalCitation'],
            'citationNoteNum' => 0,
            'citationHash' => 1899,
        ];
        $secondRecordPayload = [
            'citationId' => 'doe1901',
            'citationPrefix' => [],
            'citationSuffix' => [],
            'citationMode' => ['t' => 'AuthorInText'],
            'citationNoteNum' => 0,
            'citationHash' => 1901,
        ];
        $firstRecord = [
            't' => 'Citation',
            'c' => $firstRecordPayload,
            'reviewQueue' => 'first-tagged-citation-source',
        ];
        $secondRecord = [
            't' => 'Citation',
            'c' => [$secondRecordPayload],
            'reviewQueue' => 'second-tagged-citation-source',
        ];
        $recordsNative = [[$firstRecord, $secondRecord]];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Cite', 'c' => [
                        $recordsNative,
                        $sourceInlines,
                    ]],
                ]],
            ],
        ];
        $stripWrapperAttrs = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $cluster = $document->children[0]->children[0];
            $firstCitation = $cluster->children[0];
            $secondCitation = $cluster->children[1];
            $rebuilt = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('citation_group', $stripWrapperAttrs($cluster), $cluster->children),
                ]),
            ]);
            $editedFirstCitation = new AstNode('citation', array_replace($firstCitation->attrs, [
                'citationHash' => 1999,
            ]), $firstCitation->children);
            $edited = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('citation_group', $stripWrapperAttrs($cluster), [
                        $editedFirstCitation,
                        $secondCitation,
                    ]),
                ]),
            ]);

            $t->same('citation_group', $cluster->type, "{$source} tagged citation records become a citation group");
            $t->same($recordsNative, $cluster->attr('citationRecordsNative'), "{$source} keeps wrapped tagged citation record list");
            $t->same('Citation', $firstCitation->attr('citationConstructor'), "{$source} first citation constructor");
            $t->same($firstRecord, $firstCitation->attr('citationNative'), "{$source} first tagged citation native payload");
            $t->same('smith1899', $firstCitation->attr('id'), "{$source} first citation id");
            $t->same('see', $firstCitation->attr('prefix')[0]->attr('text'), "{$source} first citation prefix");
            $t->same('p. 4', $firstCitation->attr('suffix')[0]->attr('text'), "{$source} first citation suffix");
            $t->same('author_in_text', $secondCitation->attr('mode'), "{$source} second citation mode");
            $t->same($secondRecord, $secondCitation->attr('citationNative'), "{$source} second tagged citation native payload");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuilt),
                'native' => json_decode((new NativeWriter())->write($rebuilt), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedCite = $encoded['blocks'][0]['c'][0];

                $t->same('Cite', $encodedCite['t'], "{$source} {$writer} writer emits Cite constructor");
                $t->same($recordsNative, $encodedCite['c'][0], "{$source} {$writer} writer preserves tagged citation record wrappers");
                $t->same($sourceInlines, $encodedCite['c'][1], "{$source} {$writer} writer preserves citation source inlines");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($edited),
                'native' => json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedRecords = $encoded['blocks'][0]['c'][0]['c'][0];

                $t->same(1999, $encodedRecords[0]['citationHash'], "{$source} {$writer} edited citation regenerates plain record");
                $t->same(false, array_key_exists('t', $encodedRecords[0]), "{$source} {$writer} edited citation drops stale Citation wrapper");
                $t->same($secondRecord, $encodedRecords[1], "{$source} {$writer} writer preserves unchanged tagged citation wrapper");
            }
        }
    },
    'preserves single-wrapped citation affix lists when rebuilding cite records' => static function (TestRunner $t): void {
        $prefixInlines = [
            ['t' => 'Str', 'c' => 'see'],
            ['t' => 'Space'],
            ['t' => 'Emph', 'c' => [
                ['t' => 'Str', 'c' => 'review'],
            ]],
        ];
        $suffixInlines = [
            ['t' => 'Str', 'c' => 'p.'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '42'],
        ];
        $prefixNative = [$prefixInlines];
        $suffixNative = [$suffixInlines];
        $sourceInlines = [
            ['t' => 'Str', 'c' => '[see'],
            ['t' => 'Space'],
            ['t' => 'Emph', 'c' => [
                ['t' => 'Str', 'c' => 'review'],
            ]],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '@smith1899,'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'p.'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '42]'],
        ];
        $record = [
            'citationId' => 'smith1899',
            'citationPrefix' => $prefixNative,
            'citationSuffix' => $suffixNative,
            'citationMode' => ['t' => 'NormalCitation'],
            'citationNoteNum' => 4,
            'citationHash' => 1899,
            'reviewQueue' => 'citation-affix-record-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Cite', 'c' => [
                        [$record],
                        $sourceInlines,
                    ], 'reviewQueue' => 'citation-affix-wrapper-source'],
                ]],
            ],
        ];
        $emptySuffixPacket = $packet;
        $emptySuffixPacket['blocks'][0]['c'][0]['c'][0][0] = array_replace($record, [
            'citationSuffix' => [[]],
            'reviewQueue' => 'citation-empty-suffix-record-source',
        ]);
        $emptySuffixPacket['blocks'][0]['c'][0]['c'][1] = [
            ['t' => 'Str', 'c' => '[see'],
            ['t' => 'Space'],
            ['t' => 'Emph', 'c' => [
                ['t' => 'Str', 'c' => 'review'],
            ]],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '@smith1899]'],
        ];
        $stripWrapper = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };
        $encodedRecord = static fn (array $encoded): array => $encoded['blocks'][0]['c'][0]['c'][0][0];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $citation = $document->children[0]->children[0];
            $prefix = $citation->attr('prefix');
            $suffix = $citation->attr('suffix');
            $expectedPrefixTypes = $source === 'native' ? ['text', 'emph'] : ['text', 'space', 'emph'];
            $expectedSuffixTypes = $source === 'native' ? ['text'] : ['text', 'space', 'text'];

            $t->same('citation', $citation->type, "{$source} citation node type");
            $t->same($prefixNative, $citation->attr('citationNative')['citationPrefix'], "{$source} keeps single-wrapped prefix native");
            $t->same($suffixNative, $citation->attr('citationNative')['citationSuffix'], "{$source} keeps single-wrapped suffix native");
            $t->same($expectedPrefixTypes, array_map(static fn (AstNode $node): string => $node->type, $prefix), "{$source} prefix inline shape");
            $t->same($expectedSuffixTypes, array_map(static fn (AstNode $node): string => $node->type, $suffix), "{$source} suffix inline shape");
            $t->same('[see review @smith1899, p. 42]', $citation->attr('text'), "{$source} citation source text");

            $rebuiltDocument = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [
                    new AstNode('citation', $stripWrapper($citation), $citation->children),
                ]),
            ]);
            $editedPrefixCitation = new AstNode('citation', array_replace($stripWrapper($citation), [
                'prefix' => [new AstNode('text', ['text' => 'edited'])],
            ]), $citation->children);
            $editedDocument = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [$editedPrefixCitation]),
            ]);

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuiltDocument),
                'native' => json_decode((new NativeWriter())->write($rebuiltDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $rebuiltRecord = $encodedRecord($encoded);

                $t->same($prefixNative, $rebuiltRecord['citationPrefix'], "{$source} {$writer} writer preserves single-wrapped prefix");
                $t->same($suffixNative, $rebuiltRecord['citationSuffix'], "{$source} {$writer} writer preserves single-wrapped suffix");
                $t->same('citation-affix-record-source', $rebuiltRecord['reviewQueue'], "{$source} {$writer} writer preserves unchanged citation sidecar");
            }

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($editedDocument),
                'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $editedRecord = $encodedRecord($encoded);

                $t->same([['t' => 'Str', 'c' => 'edited']], $editedRecord['citationPrefix'], "{$source} {$writer} writer regenerates edited prefix");
                $t->same($suffixNative, $editedRecord['citationSuffix'], "{$source} {$writer} writer preserves unchanged suffix");
                $t->same(false, array_key_exists('reviewQueue', $editedRecord), "{$source} {$writer} writer drops stale edited citation sidecar");
            }
        }

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($emptySuffixPacket),
            'native' => (new NativeReader())->read(json_encode($emptySuffixPacket, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $citation = $document->children[0]->children[0];
            $editedPrefixCitation = new AstNode('citation', array_replace($stripWrapper($citation), [
                'prefix' => [new AstNode('text', ['text' => 'edited'])],
            ]), $citation->children);
            $editedDocument = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [$editedPrefixCitation]),
            ]);

            $t->same([[]], $citation->attr('citationSuffixNative'), "{$source} keeps empty single-wrapped suffix native");
            $t->same([], $citation->attr('suffix', []), "{$source} leaves empty single-wrapped suffix semantic list empty");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($editedDocument),
                'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $editedRecord = $encodedRecord($encoded);

                $t->same([[]], $editedRecord['citationSuffix'], "{$source} {$writer} writer preserves edited record empty single-wrapped suffix");
                $t->same(false, array_key_exists('reviewQueue', $editedRecord), "{$source} {$writer} writer drops stale empty suffix citation sidecar");
            }
        }
    },
    'preserves cite source inline constructors when regenerating citation wrappers' => static function (TestRunner $t): void {
        $sourceInlines = [
            ['t' => 'Str', 'c' => '[see'],
            ['t' => 'Space'],
            ['t' => 'Emph', 'c' => [
                ['t' => 'Str', 'c' => 'review'],
            ]],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '@smith1899;'],
            ['t' => 'Space'],
            ['t' => 'Code', 'c' => [
                ['source-cite-code', ['citation-source'], [['data-source', 'cite-source']]],
                '@doe1901',
            ]],
            ['t' => 'Str', 'c' => ']'],
        ];
        $firstRecord = [
            'citationId' => 'smith1899',
            'citationPrefix' => [['t' => 'Str', 'c' => 'see']],
            'citationSuffix' => [],
            'citationMode' => ['t' => 'NormalCitation'],
            'citationNoteNum' => 0,
            'citationHash' => 1899,
            'reviewQueue' => 'first-citation-source',
        ];
        $secondRecord = [
            'citationId' => 'doe1901',
            'citationPrefix' => [],
            'citationSuffix' => [],
            'citationMode' => ['t' => 'AuthorInText'],
            'citationNoteNum' => 0,
            'citationHash' => 1901,
            'reviewQueue' => 'second-citation-source',
        ];
        $citeInline = [
            't' => 'Cite',
            'c' => [
                [$firstRecord, $secondRecord],
                $sourceInlines,
            ],
            'reviewQueue' => 'cite-wrapper-source',
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [$citeInline]],
            ],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $cluster = $document->children[0]->children[0];
            $clusterSourceInlines = $cluster->attr('citationSourceInlines');
            $editedFirstCitation = new AstNode('citation', array_replace($cluster->children[0]->attrs, [
                'citationHash' => 1999,
            ]), $cluster->children[0]->children);
            $editedCluster = new AstNode('citation_group', $cluster->attrs, [
                $editedFirstCitation,
                $cluster->children[1],
            ]);
            $editedDocument = new AstNode('document', $document->attrs, [
                new AstNode('paragraph', $document->children[0]->attrs, [$editedCluster]),
            ]);

            $t->same('citation_group', $cluster->type, "{$source} citation group node");
            $t->same(true, is_array($clusterSourceInlines), "{$source} records cite source inlines");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($editedDocument),
                'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $encodedCite = $encoded['blocks'][0]['c'][0];
                $encodedSourceInlines = $encodedCite['c'][1];

                $t->same('Cite', $encodedCite['t'], "{$source} {$writer} writer regenerates cite constructor");
                $t->same(false, array_key_exists('reviewQueue', $encodedCite), "{$source} {$writer} writer drops stale cite wrapper sidecar");
                $t->same(1999, $encodedCite['c'][0][0]['citationHash'], "{$source} {$writer} writer emits edited citation hash");
                $t->same(false, array_key_exists('reviewQueue', $encodedCite['c'][0][0]), "{$source} {$writer} writer drops stale edited citation record sidecar");
                $t->same('second-citation-source', $encodedCite['c'][0][1]['reviewQueue'], "{$source} {$writer} writer preserves unchanged citation record sidecar");
                $t->same($sourceInlines, $encodedSourceInlines, "{$source} {$writer} writer preserves formatted cite source inline constructors");
                $t->same('Emph', $encodedSourceInlines[2]['t'], "{$source} {$writer} writer keeps emphasized source inline");
                $t->same('Code', $encodedSourceInlines[6]['t'], "{$source} {$writer} writer keeps code source inline");
            }
        }
    },
    'round trips pandoc json cite inlines with csl metadata for wordpress handoff' => static function (TestRunner $t): void {
        $packet = [
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Archive'],
                    ['t' => 'Space'],
                    ['t' => 'Cite', 'c' => [
                        [
                            [
                                'citationId' => 'smith1899',
                                'citationPrefix' => [
                                    ['t' => 'Str', 'c' => 'see'],
                                ],
                                'citationSuffix' => [
                                    ['t' => 'Str', 'c' => 'p.'],
                                    ['t' => 'Space'],
                                    ['t' => 'Str', 'c' => '7'],
                                ],
                                'citationMode' => ['t' => 'NormalCitation'],
                                'citationNoteNum' => 0,
                                'citationHash' => 1889,
                            ],
                            [
                                'citationId' => 'wp-team',
                                'citationPrefix' => [],
                                'citationSuffix' => [
                                    ['t' => 'Str', 'c' => 'ch.'],
                                    ['t' => 'Space'],
                                    ['t' => 'Str', 'c' => '2'],
                                ],
                                'citationMode' => ['t' => 'AuthorInText'],
                                'citationNoteNum' => 0,
                                'citationHash' => 2024,
                            ],
                            [
                                'citationId' => 'missing-source',
                                'citationPrefix' => [
                                    ['t' => 'Str', 'c' => 'compare'],
                                ],
                                'citationSuffix' => [],
                                'citationMode' => ['t' => 'SuppressAuthor'],
                                'citationNoteNum' => 0,
                                'citationHash' => 0,
                            ],
                        ],
                        [
                            ['t' => 'Str', 'c' => '[see'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '@smith1899,'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'p.'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '7;'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '@wp-team,'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'ch.'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '2;'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'compare'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '-@missing-source]'],
                        ],
                    ]],
                    ['t' => 'Str', 'c' => '.'],
                ]],
            ],
        ];

        $reader = new PandocJsonReader();
        $document = $reader->readPacket($packet);
        $cluster = $document->children[0]->children[2];

        $t->same('citation_group', $cluster->type);
        $t->same('[see @smith1899, p. 7; @wp-team, ch. 2; compare -@missing-source]', $cluster->attr('text'));
        $t->same(['citation', 'citation', 'citation'], array_map(static fn (AstNode $node): string => $node->type, $cluster->children));
        $t->same('smith1899', $cluster->children[0]->attr('id'));
        $t->same('see', $cluster->children[0]->attr('prefix')[0]->attr('text'));
        $t->same('p.', $cluster->children[0]->attr('suffix')[0]->attr('text'));
        $t->same('7', $cluster->children[0]->attr('suffix')[2]->attr('text'));
        $t->same(1889, $cluster->children[0]->attr('citationHash'));
        $t->same('author_in_text', $cluster->children[1]->attr('mode'));
        $t->same('suppress_author', $cluster->children[2]->attr('mode'));
        $t->same('compare -@missing-source', $cluster->children[2]->attr('text'));

        $processor = CitationCslProcessor::fromItems([
            [
                'id' => 'smith1899',
                'type' => 'book',
                'title' => 'Migration Patterns',
                'author' => [
                    ['family' => 'Smith', 'given' => 'Ada'],
                ],
                'issued' => ['date-parts' => [[1899]]],
            ],
            [
                'id' => 'wp-team',
                'type' => 'webpage',
                'title' => 'Reviewer Log',
                'author' => [
                    ['literal' => 'WordPress Migration Team'],
                ],
                'issued' => ['date-parts' => [[2024]]],
            ],
        ]);
        $processed = $processor->apply($document);
        $processedCluster = $processed->children[0]->children[2];

        $t->same('(see Smith 1899, p. 7; WordPress Migration Team (2024, ch. 2); compare -@missing-source)', $processedCluster->attr('rendered'));
        $t->same(['missing-source'], $processedCluster->attr('missingCslItems'));
        $t->same(['smith1899', 'wp-team', 'missing-source'], $processor->citationIds($document));
        $t->same(['missing-source'], $processor->missingCitationIds($document));

        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Archive (see Smith 1899, p. 7; WordPress Migration Team (2024, ch. 2); compare -@missing-source).</p>', $blocks);
        $t->contains('<dt>Smith 1899</dt><dd>Smith, Ada. Migration Patterns. 1899.</dd>', $blocks);
        $t->contains('<dt>WordPress Migration Team 2024</dt><dd>WordPress Migration Team. Reviewer Log. 2024.</dd>', $blocks);

        $encoded = (new PandocJsonWriter())->toArray($document);
        $roundTrip = $reader->readPacket($encoded);
        $encodedCite = $encoded['blocks'][0]['c'][2];
        $roundTripCluster = $roundTrip->children[0]->children[2];
        $t->same('Cite', $encodedCite['t']);
        $t->same('smith1899', $encodedCite['c'][0][0]['citationId']);
        $t->same('NormalCitation', $encodedCite['c'][0][0]['citationMode']['t']);
        $t->same('AuthorInText', $encodedCite['c'][0][1]['citationMode']['t']);
        $t->same('SuppressAuthor', $encodedCite['c'][0][2]['citationMode']['t']);
        $t->same('see', $encodedCite['c'][0][0]['citationPrefix'][0]['c']);
        $t->same('ch.', $encodedCite['c'][0][1]['citationSuffix'][0]['c']);
        $t->same('citation_group', $roundTripCluster->type);
        $t->same('wp-team', $roundTripCluster->children[1]->attr('id'));
        $t->same('missing-source', $roundTripCluster->children[2]->attr('id'));
    },
    'preserves mixed figure link raw payloads through json native and wordpress handoff' => static function (TestRunner $t): void {
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('figure', [
                'id' => 'json-native-mixed-figure',
                'classes' => ['json-native-mixed'],
                'caption' => 'Mixed figure review',
            ], [
                new AstNode('text', ['text' => 'Review']),
                new AstNode('space'),
                new AstNode('link', [
                    'url' => 'https://example.test/source',
                    'title' => 'Source packet',
                ], [
                    new AstNode('text', ['text' => 'source']),
                ]),
                new AstNode('space'),
                new AstNode('raw_html_inline', ['html' => '<span data-raw="inline">raw</span>']),
                new AstNode('code_block', ['text' => 'wp post get 42', 'classes' => ['bash']]),
                new AstNode('text', ['text' => 'Tail']),
                new AstNode('space'),
                new AstNode('raw_html_inline', ['html' => '<mark>done</mark>']),
            ]),
        ]);

        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $source => $packet) {
            $figure = $packet['blocks'][0];
            $figureBlocks = $figure['c'][2];

            $t->same('Figure', $figure['t'], "{$source} figure constructor");
            $t->same(['json-native-mixed-figure', ['json-native-mixed'], []], $figure['c'][0], "{$source} figure attrs");
            $t->same(['Plain', 'CodeBlock', 'Plain'], array_map(static fn (array $block): string => $block['t'], $figureBlocks), "{$source} mixed figure children flush to blocks");
            $t->same(['Str', 'Space', 'Link', 'Space', 'RawInline'], array_map(static fn (array $inline): string => $inline['t'], $figureBlocks[0]['c']), "{$source} leading inline run keeps link and raw inline");
            $t->same(['html', '<span data-raw="inline">raw</span>'], $figureBlocks[0]['c'][4]['c'], "{$source} leading raw inline payload");
            $t->same('wp post get 42', $figureBlocks[1]['c'][1], "{$source} nested code block payload");
            $t->same(['Str', 'Space', 'RawInline'], array_map(static fn (array $inline): string => $inline['t'], $figureBlocks[2]['c']), "{$source} trailing inline run keeps raw inline");
        }

        $roundTrips = [
            'json' => (new PandocJsonReader())->readPacket($jsonPacket),
            'native' => (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR)),
        ];

        foreach ($roundTrips as $source => $roundTrip) {
            $figure = $roundTrip->children[0];
            $leadingChildren = $figure->children[0]->children;
            $trailingChildren = $figure->children[2]->children;
            $leadingTypes = array_map(static fn (AstNode $child): string => $child->type, $leadingChildren);
            $trailingTypes = array_map(static fn (AstNode $child): string => $child->type, $trailingChildren);
            $leadingLinks = array_values(array_filter($leadingChildren, static fn (AstNode $child): bool => $child->type === 'link'));
            $leadingRaw = array_values(array_filter($leadingChildren, static fn (AstNode $child): bool => $child->type === 'raw_html_inline'));
            $trailingRaw = array_values(array_filter($trailingChildren, static fn (AstNode $child): bool => $child->type === 'raw_html_inline'));

            $t->same('figure', $figure->type, "{$source} round-trip figure node");
            $t->same('json-native-mixed-figure', $figure->attr('id'), "{$source} round-trip figure id");
            $t->same(['plain', 'code_block', 'plain'], array_map(static fn (AstNode $child): string => $child->type, $figure->children), "{$source} round-trip figure child blocks");
            $t->same(true, in_array('link', $leadingTypes, true), "{$source} leading link survives");
            $t->same(true, in_array('raw_html_inline', $leadingTypes, true), "{$source} leading raw inline survives");
            $t->same(true, in_array('raw_html_inline', $trailingTypes, true), "{$source} trailing raw inline survives");
            $t->same('https://example.test/source', $leadingLinks[0]->attr('url'), "{$source} leading link target");
            $t->same('<span data-raw="inline">raw</span>', $leadingRaw[0]->attr('html'), "{$source} leading raw inline html");
            $t->same('<mark>done</mark>', $trailingRaw[0]->attr('html'), "{$source} trailing raw inline html");
        }

        $blocks = (new WordPressBlockWriter())->write($roundTrips['json']);

        $t->contains('<!-- wp:html -->', $blocks);
        $t->contains('<figure id="json-native-mixed-figure" class="json-native-mixed">', $blocks);
        $t->contains('<p>Review <a href="https://example.test/source" title="Source packet">source</a> <span data-raw="inline">raw</span></p>', $blocks);
        $t->contains('<pre class="wp-block-code"><code class="language-bash">wp post get 42</code></pre>', $blocks);
        $t->contains('<p>Tail <mark>done</mark></p>', $blocks);
        $t->contains('<figcaption>Mixed figure review</figcaption>', $blocks);
    },
    'flushes mixed citation and metadata payloads around block containers' => static function (TestRunner $t): void {
        $citationGroup = static function (): AstNode {
            return new AstNode('citation_group', [
                'text' => '[see @archive-review, p. 4; @metadata-ticket]',
                'citationSourceInlines' => [
                    new AstNode('text', ['text' => '[see']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => '@archive-review,']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'p.']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => '4;']),
                    new AstNode('space'),
                    new AstNode('text', ['text' => '@metadata-ticket]']),
                ],
            ], [
                new AstNode('citation', [
                    'id' => 'archive-review',
                    'prefix' => [new AstNode('text', ['text' => 'see'])],
                    'suffix' => [
                        new AstNode('text', ['text' => 'p.']),
                        new AstNode('space'),
                        new AstNode('text', ['text' => '4']),
                    ],
                    'citationHash' => 4004,
                ]),
                new AstNode('citation', [
                    'id' => 'metadata-ticket',
                    'mode' => 'author_in_text',
                    'citationHash' => 2026,
                ]),
            ]);
        };
        $metadataSpan = static fn (string $text): AstNode => new AstNode('span', [
            'classes' => ['metadata-inline'],
            'attributes' => ['data-review' => 'cite-payload'],
        ], [
            new AstNode('text', ['text' => $text]),
        ]);
        $inlineTypes = static fn (array $nodes): array => array_map(
            static fn (AstNode $node): string => $node->type,
            $nodes
        );
        $compactInlineTypes = static function (array $nodes): array {
            $types = [];
            foreach ($nodes as $node) {
                if (!$node instanceof AstNode || $node->type === 'space') {
                    continue;
                }
                if ($node->type === 'text' && trim((string) $node->attr('text', '')) === '') {
                    continue;
                }
                $types[] = $node->type;
            }

            return $types;
        };
        $constructorTypes = static fn (array $nodes): array => array_map(
            static fn (array $node): string => $node['t'],
            $nodes
        );
        $firstInlineConstructor = static function (array $inlines, string $constructor): ?array {
            foreach ($inlines as $inline) {
                if (is_array($inline) && ($inline['t'] ?? null) === $constructor) {
                    return $inline;
                }
            }

            return null;
        };
        $firstChildOfType = static function (array $nodes, string $type): ?AstNode {
            foreach ($nodes as $node) {
                if ($node instanceof AstNode && $node->type === $type) {
                    return $node;
                }
            }

            return null;
        };
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [
                'reviewPayload' => ['type' => 'map', 'items' => [
                    'inlineRun' => ['type' => 'inlines', 'children' => [
                        new AstNode('text', ['text' => 'Metadata']),
                        new AstNode('space'),
                        $citationGroup(),
                        new AstNode('space'),
                        $metadataSpan('inline payload'),
                    ]],
                    'blockRun' => ['type' => 'blocks', 'children' => [
                        new AstNode('div', [
                            'id' => 'meta-block',
                            'classes' => ['review-meta'],
                            'attributes' => ['data-source' => 'json-native'],
                        ], [
                            new AstNode('text', ['text' => 'MetaLead']),
                            new AstNode('blockquote', [], [
                                new AstNode('paragraph', [], [
                                    new AstNode('text', ['text' => 'MetaNestedBlock']),
                                ]),
                            ]),
                            new AstNode('text', ['text' => 'MetaTail']),
                        ]),
                    ]],
                ]],
            ],
        ], [
            new AstNode('blockquote', [], [
                new AstNode('text', ['text' => 'QuoteLead']),
                new AstNode('space'),
                $citationGroup(),
                new AstNode('space'),
                $metadataSpan('inline metadata'),
                new AstNode('div', [
                    'id' => 'nested-review',
                    'classes' => ['metadata-block'],
                    'attributes' => ['data-origin' => 'filter'],
                ], [
                    new AstNode('text', ['text' => 'NestedLead']),
                    new AstNode('space'),
                    $citationGroup(),
                    new AstNode('code_block', [
                        'text' => 'wp post meta list 42',
                        'classes' => ['bash'],
                        'attributes' => ['data-command' => 'review'],
                    ]),
                    new AstNode('text', ['text' => 'NestedTail']),
                ]),
                new AstNode('text', ['text' => 'QuoteTail']),
                new AstNode('space'),
                $citationGroup(),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Footnote']),
                new AstNode('space'),
                new AstNode('note', ['label' => 'cite-meta-note'], [
                    new AstNode('text', ['text' => 'NoteLead']),
                    new AstNode('space'),
                    $citationGroup(),
                    new AstNode('blockquote', [], [
                        new AstNode('paragraph', [], [
                            $metadataSpan('nested metadata'),
                            new AstNode('space'),
                            $citationGroup(),
                        ]),
                    ]),
                    new AstNode('text', ['text' => 'NoteTail']),
                    new AstNode('space'),
                    $citationGroup(),
                ]),
            ]),
        ]);

        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $source => $packet) {
            $metaPayload = $packet['meta']['reviewPayload'];
            $metaInlineRun = $metaPayload['c']['inlineRun'];
            $metaBlockRun = $metaPayload['c']['blockRun'];
            $metaDivBlocks = $metaBlockRun['c'][0]['c'][1];
            $quoteBlocks = $packet['blocks'][0]['c'];
            $quoteLeadingCite = $quoteBlocks[0]['c'][2];
            $quoteLeadingSpan = $quoteBlocks[0]['c'][4];
            $nestedDivBlocks = $quoteBlocks[1]['c'][1];
            $noteInline = $firstInlineConstructor($packet['blocks'][1]['c'], 'Note');
            $t->true(is_array($noteInline), "{$source} paragraph includes note inline");
            $noteBlocks = is_array($noteInline) && is_array($noteInline['c'] ?? null) ? $noteInline['c'] : [];
            $noteNestedQuoteBlocks = $noteBlocks[1]['c'] ?? [];
            $noteNestedParaInlines = $noteNestedQuoteBlocks[0]['c'] ?? [];

            $t->same('MetaMap', $metaPayload['t'], "{$source} metadata map constructor");
            $t->same('MetaInlines', $metaInlineRun['t'], "{$source} metadata inline payload constructor");
            $t->same(['Str', 'Space', 'Cite', 'Space', 'Span'], $constructorTypes($metaInlineRun['c']), "{$source} metadata inline payload keeps Cite and Span");
            $t->same('MetaBlocks', $metaBlockRun['t'], "{$source} metadata block payload constructor");
            $t->same(['Div'], $constructorTypes($metaBlockRun['c']), "{$source} metadata block payload keeps Div");
            $t->same(['Plain', 'BlockQuote', 'Plain'], $constructorTypes($metaDivBlocks), "{$source} metadata Div children flush to blocks");
            $t->same(['BlockQuote', 'Para'], $constructorTypes($packet['blocks']), "{$source} top-level block constructors");
            $t->same(['Plain', 'Div', 'Plain'], $constructorTypes($quoteBlocks), "{$source} quote children flush around nested Div");
            $t->same(['Str', 'Space', 'Cite', 'Space', 'Span'], $constructorTypes($quoteBlocks[0]['c']), "{$source} quote leading inline run keeps Cite and Span");
            $t->same('Cite', $quoteLeadingCite['t'], "{$source} quote leading cite constructor");
            $t->same('archive-review', $quoteLeadingCite['c'][0][0]['citationId'], "{$source} quote leading citation id");
            $t->same(['metadata-inline'], $quoteLeadingSpan['c'][0][1], "{$source} quote leading metadata span classes");
            $t->same(['Plain', 'CodeBlock', 'Plain'], $constructorTypes($nestedDivBlocks), "{$source} nested Div children flush around CodeBlock");
            $t->same(['Str', 'Space', 'Cite'], $constructorTypes($nestedDivBlocks[0]['c']), "{$source} nested Div leading citation run");
            $t->same('wp post meta list 42', $nestedDivBlocks[1]['c'][1], "{$source} nested metadata block payload text");
            $t->same(['Str', 'Space', 'Cite'], $constructorTypes($quoteBlocks[2]['c']), "{$source} quote trailing citation run");
            $t->same(['Plain', 'BlockQuote', 'Plain'], $constructorTypes($noteBlocks), "{$source} note children flush around nested BlockQuote");
            $t->same(['Str', 'Space', 'Cite'], $constructorTypes($noteBlocks[0]['c']), "{$source} note leading citation run");
            $t->same(['Para'], $constructorTypes($noteNestedQuoteBlocks), "{$source} note nested quote keeps block list");
            $t->same(['Span', 'Space', 'Cite'], $constructorTypes($noteNestedParaInlines), "{$source} note nested metadata inline run");
            $t->same('cite-meta-note', $noteInline['noteLabel'] ?? null, "{$source} note label sidecar");
        }

        $roundTrips = [
            'json' => (new PandocJsonReader())->readPacket($jsonPacket),
            'native' => (new NativeReader())->read(json_encode($nativePacket, JSON_THROW_ON_ERROR)),
        ];

        $metadataRoundTripShape = static function (array $metaPayload) use ($inlineTypes, $constructorTypes): array {
            if (($metaPayload['type'] ?? null) === 'map') {
                $metaItems = $metaPayload['items'];
                $metaInlineChildren = $metaItems['inlineRun']['children'];
                $metaBlockDiv = $metaItems['blockRun']['children'][0];

                return [
                    'type' => 'map',
                    'inlineTypes' => $inlineTypes($metaInlineChildren),
                    'blockTypes' => $inlineTypes($metaBlockDiv->children),
                    'citationId' => $metaInlineChildren[2]->children[1]->attr('id'),
                ];
            }

            $metaItems = $metaPayload['c'];
            $metaInlineRun = $metaItems['inlineRun'];
            $metaBlockRun = $metaItems['blockRun'];

            return [
                'type' => $metaPayload['t'] ?? null,
                'inlineTypes' => $constructorTypes($metaInlineRun['c']),
                'blockTypes' => $constructorTypes($metaBlockRun['c'][0]['c'][1]),
                'citationId' => $metaInlineRun['c'][2]['c'][0][1]['citationId'],
            ];
        };

        foreach ($roundTrips as $source => $roundTrip) {
            $metaPayload = $roundTrip->attr('meta')['reviewPayload'];
            $metaShape = $metadataRoundTripShape($metaPayload);
            $quote = $roundTrip->children[0];
            $quoteLeading = $quote->children[0];
            $quoteLeadingCitation = $firstChildOfType($quoteLeading->children, 'citation_group');
            $quoteLeadingSpan = $firstChildOfType($quoteLeading->children, 'span');
            $nestedDiv = $quote->children[1];
            $quoteTrailing = $quote->children[2];
            $note = $firstChildOfType($roundTrip->children[1]->children, 'note');
            $t->true($note instanceof AstNode, "{$source} round-trip keeps note inline");
            $noteChildren = $note instanceof AstNode ? $note->children : [];
            $noteNestedQuote = $noteChildren[1] ?? new AstNode('missing');
            $noteNestedPara = $noteNestedQuote->children[0] ?? new AstNode('missing');

            $t->same($source === 'native' ? 'MetaMap' : 'map', $metaShape['type'], "{$source} round-trip metadata map type");
            $t->same($source === 'native' ? ['Str', 'Space', 'Cite', 'Space', 'Span'] : ['text', 'space', 'citation_group', 'space', 'span'], $metaShape['inlineTypes'], "{$source} round-trip metadata inline payload");
            $t->same($source === 'native' ? ['Plain', 'BlockQuote', 'Plain'] : ['plain', 'blockquote', 'plain'], $metaShape['blockTypes'], "{$source} round-trip metadata block payload");
            $t->same('metadata-ticket', $metaShape['citationId'], "{$source} round-trip metadata citation id");
            $t->same(['plain', 'div', 'plain'], $inlineTypes($quote->children), "{$source} round-trip quote blocks");
            $t->same(['text', 'citation_group', 'span'], $compactInlineTypes($quoteLeading->children), "{$source} round-trip quote leading payload");
            $t->same('archive-review', $quoteLeadingCitation instanceof AstNode ? $quoteLeadingCitation->children[0]->attr('id') : null, "{$source} round-trip quote citation id");
            $t->same(['metadata-inline'], $quoteLeadingSpan instanceof AstNode ? $quoteLeadingSpan->attr('classes') : null, "{$source} round-trip quote metadata span class");
            $t->same(['plain', 'code_block', 'plain'], $inlineTypes($nestedDiv->children), "{$source} round-trip nested Div blocks");
            $t->same(['text', 'citation_group'], $compactInlineTypes($nestedDiv->children[0]->children), "{$source} round-trip nested Div leading citation run");
            $t->same('wp post meta list 42', $nestedDiv->children[1]->attr('text'), "{$source} round-trip nested metadata block text");
            $t->same(['text', 'citation_group'], $compactInlineTypes($quoteTrailing->children), "{$source} round-trip quote trailing citation run");
            $t->same('cite-meta-note', $note instanceof AstNode ? $note->attr('label') : null, "{$source} round-trip note label");
            $t->same(['plain', 'blockquote', 'plain'], $inlineTypes($noteChildren), "{$source} round-trip note blocks");
            $t->same(['text', 'citation_group'], $compactInlineTypes($noteChildren[0]->children), "{$source} round-trip note leading citation run");
            $t->same(['paragraph'], $inlineTypes($noteNestedQuote->children), "{$source} round-trip note nested quote block list");
            $t->same(['span', 'citation_group'], $compactInlineTypes($noteNestedPara->children), "{$source} round-trip note nested metadata inline payload");
        }
    },
    'summarizes native leaf block constructors inside captions and cells' => static function (TestRunner $t): void {
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['leaf-block-table', ['json-native'], []],
                ['t' => 'Caption', 'c' => [
                    ['t' => 'Nothing'],
                    [
                        ['t' => 'CodeBlock', 'c' => [['', ['bash'], []], 'wp option get siteurl']],
                        ['t' => 'RawBlock', 'c' => [['t' => 'Format', 'c' => 'html'], '<p>caption raw</p>']],
                    ],
                ]],
                [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => 0],
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignDefault'],
                                        ['t' => 'RowSpan', 'c' => 1],
                                        ['t' => 'ColSpan', 'c' => 1],
                                        [
                                            ['t' => 'CodeBlock', 'c' => [['', [], []], 'cell code']],
                                            ['t' => 'RawBlock', 'c' => [['t' => 'Format', 'c' => 'html'], '<span>cell raw</span>']],
                                        ],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [['', [], []], []]],
            ],
        ];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$tableBlock],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $table = $document->children[0];
            $body = $table->children[0];
            $cell = $body->children[0]->children[0];
            $captionBlocks = $table->attr('captionBlocks');

            $t->same('wp option get siteurl' . "\n" . '<p>caption raw</p>', $table->attr('caption'), "{$source} caption includes leaf block text");
            $t->same(true, is_array($captionBlocks), "{$source} caption blocks recorded");
            $t->same(['code_block', 'raw_html'], array_map(static fn (AstNode $node): string => $node->type, $captionBlocks), "{$source} caption leaf block nodes");
            $t->same('cell code' . "\n" . '<span>cell raw</span>', $cell->attr('text'), "{$source} cell text includes leaf block constructors");

            foreach ([
                "{$source} json writer" => (new PandocJsonWriter())->toArray($document),
                "{$source} native writer" => json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $t->same($tableBlock, $encoded['blocks'][0], "{$writer} preserves unchanged table payload");
            }
        }
    },
    'preserves nested scalar constructor payloads for text and table helpers' => static function (TestRunner $t): void {
        $textInline = ['t' => 'Str', 'c' => [['Nested scalar']], 'reviewQueue' => 'nested-str-source'];
        $columnWidth = ['t' => 'ColWidth', 'c' => [[0.5]], 'reviewQueue' => 'nested-width-source'];
        $rowHeadColumns = ['t' => 'RowHeadColumns', 'c' => [[1]], 'reviewQueue' => 'nested-row-head-source'];
        $rowSpan = ['t' => 'RowSpan', 'c' => [[2]], 'reviewQueue' => 'nested-rowspan-source'];
        $colSpan = ['t' => 'ColSpan', 'c' => [[3]], 'reviewQueue' => 'nested-colspan-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Para', 'c' => [$textInline]],
                ['t' => 'Table', 'c' => [
                    ['nested-scalar-table', ['json-native'], []],
                    ['t' => 'Caption', 'c' => [['t' => 'Nothing'], []]],
                    [[['t' => 'AlignDefault'], $columnWidth]],
                    ['t' => 'TableHead', 'c' => [['', [], []], []]],
                    [
                        ['t' => 'TableBody', 'c' => [
                            ['', [], []],
                            $rowHeadColumns,
                            [],
                            [
                                ['t' => 'Row', 'c' => [
                                    ['', [], []],
                                    [
                                        ['t' => 'Cell', 'c' => [
                                            ['', [], []],
                                            ['t' => 'AlignDefault'],
                                            $rowSpan,
                                            $colSpan,
                                            [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Nested cell']]]],
                                        ]],
                                    ],
                                ]],
                            ],
                        ]],
                    ],
                    ['t' => 'TableFoot', 'c' => [['', [], []], []]],
                ]],
            ],
        ];
        $withoutNativeWrapper = static function (AstNode $node): array {
            $attrs = $node->attrs;
            unset($attrs['constructor'], $attrs['native']);

            return $attrs;
        };
        $rebuiltDocument = static function (AstNode $document, bool $edited = false) use ($withoutNativeWrapper): AstNode {
            $text = $document->children[0]->children[0];
            $table = $document->children[1];
            $body = $table->children[0];
            $row = $body->children[0];
            $cell = $row->children[0];

            $textAttrs = $text->attrs;
            $tableAttrs = $withoutNativeWrapper($table);
            $bodyAttrs = $withoutNativeWrapper($body);
            $cellAttrs = $withoutNativeWrapper($cell);
            if ($edited) {
                $textAttrs['text'] = 'EditedScalar';
                $tableAttrs['widths'] = [0.25];
                $bodyAttrs['rowHeadColumns'] = 2;
                $cellAttrs['rowspan'] = 1;
                $cellAttrs['colspan'] = 1;
            }

            return new AstNode('document', $document->attrs, [
                new AstNode('paragraph', [], [new AstNode('text', $textAttrs)]),
                new AstNode('table', $tableAttrs, [
                    new AstNode('table_body', $bodyAttrs, [
                        new AstNode('table_row', $withoutNativeWrapper($row), [
                            new AstNode('table_cell', $cellAttrs, $cell->children),
                        ]),
                    ]),
                ]),
            ]);
        };
        $encodedCell = static function (array $packet): array {
            return $packet['blocks'][1]['c'][4][0]['c'][3][0]['c'][1][0];
        };

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $text = $document->children[0]->children[0];
            $table = $document->children[1];
            $body = $table->children[0];
            $cell = $body->children[0]->children[0];

            $t->same('Nested scalar', $text->attr('text'), "{$source} unwraps nested Str payload");
            $t->same($textInline, $text->attr('native'), "{$source} records nested Str native");
            $t->same([0.5], $table->attr('widths'), "{$source} unwraps nested ColWidth payload");
            $t->same([1, 2, 3], [$body->attr('rowHeadColumns'), $cell->attr('rowspan'), $cell->attr('colspan')], "{$source} unwraps nested integer helper payloads");

            foreach ([
                'json' => (new PandocJsonWriter())->toArray($rebuiltDocument($document)),
                'native' => json_decode((new NativeWriter())->write($rebuiltDocument($document)), true, 512, JSON_THROW_ON_ERROR),
            ] as $writer => $encoded) {
                $cellPayload = $encodedCell($encoded)['c'];

                $t->same($textInline, $encoded['blocks'][0]['c'][0], "{$source} {$writer} preserves nested Str payload");
                $t->same($columnWidth, $encoded['blocks'][1]['c'][2][0][1], "{$source} {$writer} preserves nested ColWidth payload");
                $t->same($rowHeadColumns, $encoded['blocks'][1]['c'][4][0]['c'][1], "{$source} {$writer} preserves nested RowHeadColumns payload");
                $t->same($rowSpan, $cellPayload[2], "{$source} {$writer} preserves nested RowSpan payload");
                $t->same($colSpan, $cellPayload[3], "{$source} {$writer} preserves nested ColSpan payload");

                $edited = $writer === 'json'
                    ? (new PandocJsonWriter())->toArray($rebuiltDocument($document, true))
                    : json_decode((new NativeWriter())->write($rebuiltDocument($document, true)), true, 512, JSON_THROW_ON_ERROR);
                $editedCellPayload = $encodedCell($edited)['c'];

                $t->same(['t' => 'Str', 'c' => 'EditedScalar'], $edited['blocks'][0]['c'][0], "{$source} {$writer} regenerates edited Str payload");
                $t->same(['t' => 'ColWidth', 'c' => 0.25], $edited['blocks'][1]['c'][2][0][1], "{$source} {$writer} regenerates edited ColWidth payload");
                $t->same(['t' => 'RowHeadColumns', 'c' => 2], $edited['blocks'][1]['c'][4][0]['c'][1], "{$source} {$writer} regenerates edited RowHeadColumns payload");
                $t->same(['t' => 'RowSpan', 'c' => 1], $editedCellPayload[2], "{$source} {$writer} regenerates edited RowSpan payload");
                $t->same(['t' => 'ColSpan', 'c' => 1], $editedCellPayload[3], "{$source} {$writer} regenerates edited ColSpan payload");
            }
        }
    },
    'maps textual native raw markdown and tex aliases into specific ast constructors' => static function (TestRunner $t): void {
        $nativeText = <<<'NATIVE'
[ RawBlock (Format "markdown") "**raw block**"
, RawBlock (Format "latex") "\\begin{review}\\end{review}"
, Para [ RawInline (Format "markdown+tex_math_dollars") "$x$" , Space , RawInline (Format "context") "\\startformula x \\stopformula" ]
]
NATIVE;

        $document = (new NativeReader())->read($nativeText);
        $rawMarkdownBlock = $document->children[0];
        $rawTexBlock = $document->children[1];
        $paragraph = $document->children[2];
        $rawMarkdownInline = $paragraph->children[0];
        $rawTexInline = $paragraph->children[2];
        $jsonDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $document->children);
        $packet = (new PandocJsonWriter())->toArray($jsonDocument);
        $roundTrip = (new PandocJsonReader())->readPacket($packet);

        $t->same('raw_markdown', $rawMarkdownBlock->type);
        $t->same('markdown', $rawMarkdownBlock->attr('format'));
        $t->same('**raw block**', $rawMarkdownBlock->attr('markdown'));
        $t->same('raw_tex', $rawTexBlock->type);
        $t->same('latex', $rawTexBlock->attr('format'));
        $t->same('\\begin{review}\\end{review}', $rawTexBlock->attr('tex'));
        $t->same('raw_markdown', $rawMarkdownInline->type);
        $t->same('markdown+tex_math_dollars', $rawMarkdownInline->attr('format'));
        $t->same('$x$', $rawMarkdownInline->attr('markdown'));
        $t->same('raw_tex_inline', $rawTexInline->type);
        $t->same('context', $rawTexInline->attr('format'));
        $t->same('\\startformula x \\stopformula', $rawTexInline->attr('tex'));
        $t->same([['t' => 'Format', 'c' => 'markdown'], '**raw block**'], $packet['blocks'][0]['c']);
        $t->same([['t' => 'Format', 'c' => 'latex'], '\\begin{review}\\end{review}'], $packet['blocks'][1]['c']);
        $t->same([['t' => 'Format', 'c' => 'markdown+tex_math_dollars'], '$x$'], $packet['blocks'][2]['c'][0]['c']);
        $t->same([['t' => 'Format', 'c' => 'context'], '\\startformula x \\stopformula'], $packet['blocks'][2]['c'][2]['c']);
        $t->same('raw_markdown', $roundTrip->children[0]->type);
        $t->same('raw_tex', $roundTrip->children[1]->type);
        $t->same('raw_markdown', $roundTrip->children[2]->children[0]->type);
        $t->same('raw_tex_inline', $roundTrip->children[2]->children[2]->type);
    },
    'preserves textual native str and space constructors through writers' => static function (TestRunner $t): void {
        $nativeText = <<<'NATIVE'
[ Para [ Str "Alpha Beta", Space, Str "Gamma" ] ]
NATIVE;
        $expectedInlines = [
            ['t' => 'Str', 'c' => 'Alpha Beta'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'Gamma'],
        ];

        $nativeDocument = (new NativeReader())->read($nativeText);
        $jsonDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $textChildren = $nativeDocument->children[0]->children;
        $jsonPacket = (new PandocJsonWriter())->toArray($jsonDocument);
        $nativePacket = json_decode((new NativeWriter())->write($jsonDocument), true, 512, JSON_THROW_ON_ERROR);
        $nativeRoundTripText = (new NativeWriter())->write($nativeDocument);
        $nativeRoundTripPacket = (new PandocJsonWriter())->toArray(new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], (new NativeReader())->read($nativeRoundTripText)->children));

        $t->same('pandoc-native-text', $nativeDocument->attr('nativeFormat'));
        $t->same(['text', 'text', 'text'], array_map(static fn (AstNode $node): string => $node->type, $textChildren));
        $t->same([['t' => 'Str', 'c' => 'Alpha Beta']], $textChildren[0]->attr('nativeInlineParts'));
        $t->same([['t' => 'Space']], $textChildren[1]->attr('nativeInlineParts'));
        $t->same($expectedInlines, $jsonPacket['blocks'][0]['c']);
        $t->same($expectedInlines, $nativePacket['blocks'][0]['c']);
        $t->contains('Str "Alpha Beta"', $nativeRoundTripText);
        $t->contains('Space', $nativeRoundTripText);
        $t->same($expectedInlines, $nativeRoundTripPacket['blocks'][0]['c']);

        $edited = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('text', array_replace($textChildren[0]->attrs, ['text' => 'Edited text'])),
            ]),
        ]);
        $editedJson = (new PandocJsonWriter())->toArray($edited);
        $editedNative = json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR);

        $t->same([['t' => 'Str', 'c' => 'Edited text']], $editedJson['blocks'][0]['c']);
        $t->same([['t' => 'Str', 'c' => 'Edited text']], $editedNative['blocks'][0]['c']);
    },
    'preserves textual native fallback constructors through writers' => static function (TestRunner $t): void {
        $nativeText = <<<'NATIVE'
[ VendorBlock "source" [VendorFlag True, VendorCount 3]
, Para [ Str "Before", Space, VendorInline "anchor" [VendorMark False, VendorScore 2.5], Space, Str "after" ]
, OpaqueLeaf
]
NATIVE;
        $expectedBlocks = [
            ['t' => 'VendorBlock', 'c' => [
                'source',
                [
                    ['t' => 'VendorFlag', 'c' => true],
                    ['t' => 'VendorCount', 'c' => 3],
                ],
            ]],
            ['t' => 'Para', 'c' => [
                ['t' => 'Str', 'c' => 'Before'],
                ['t' => 'Space'],
                ['t' => 'VendorInline', 'c' => [
                    'anchor',
                    [
                        ['t' => 'VendorMark', 'c' => false],
                        ['t' => 'VendorScore', 'c' => 2.5],
                    ],
                ]],
                ['t' => 'Space'],
                ['t' => 'Str', 'c' => 'after'],
            ]],
            ['t' => 'OpaqueLeaf'],
        ];

        $nativeDocument = (new NativeReader())->read($nativeText);
        $jsonDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $jsonPacket = (new PandocJsonWriter())->toArray($jsonDocument);
        $nativePacket = json_decode((new NativeWriter())->write($jsonDocument), true, 512, JSON_THROW_ON_ERROR);
        $nativeRoundTripText = (new NativeWriter())->write($nativeDocument);
        $nativeRoundTripPacket = (new PandocJsonWriter())->toArray(new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], (new NativeReader())->read($nativeRoundTripText)->children));

        $nativeBlock = $nativeDocument->children[0];
        $paragraph = $nativeDocument->children[1];
        $nativeInline = $paragraph->children[2];
        $nativeLeaf = $nativeDocument->children[2];

        $t->same('pandoc-native-text', $nativeDocument->attr('nativeFormat'));
        $t->same(['native_block', 'paragraph', 'native_block'], array_map(static fn (AstNode $node): string => $node->type, $nativeDocument->children));
        $t->same('VendorBlock', $nativeBlock->attr('constructor'));
        $t->same($expectedBlocks[0], $nativeBlock->attr('native'));
        $t->same($expectedBlocks[0]['c'], $nativeBlock->attr('nativeTextArguments'));
        $t->same('native_inline', $nativeInline->type);
        $t->same('VendorInline', $nativeInline->attr('constructor'));
        $t->same($expectedBlocks[1]['c'][2], $nativeInline->attr('native'));
        $t->same($expectedBlocks[1]['c'][2]['c'], $nativeInline->attr('nativeTextArguments'));
        $t->same('OpaqueLeaf', $nativeLeaf->attr('constructor'));
        $t->same($expectedBlocks[2], $nativeLeaf->attr('native'));
        $t->same([], $nativeLeaf->attr('nativeTextArguments'));
        $t->same($expectedBlocks, $jsonPacket['blocks']);
        $t->same($expectedBlocks, $nativePacket['blocks']);
        $t->contains('VendorBlock "source"', $nativeRoundTripText);
        $t->contains('VendorInline "anchor"', $nativeRoundTripText);
        $t->contains('OpaqueLeaf', $nativeRoundTripText);
        $t->same($expectedBlocks, $nativeRoundTripPacket['blocks']);
    },
    'serializes native text raw tex inline nodes through pandoc json writers' => static function (TestRunner $t): void {
        $nativeText = <<<'NATIVE'
Pandoc Meta {unMeta = fromList []} [ Para [ Str "Before", Space, RawInline (Format "tex") "\\alpha", Space, Str "after" ] ]
NATIVE;

        $nativeDocument = (new NativeReader())->read($nativeText);
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $manualDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('raw_tex_inline', ['tex' => '\\beta']),
            ]),
        ]);

        $inline = $nativeDocument->children[0]->children[2];
        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $manualPacket = (new PandocJsonWriter())->toArray($manualDocument);
        $roundTrip = (new PandocJsonReader())->readPacket($jsonPacket);

        $t->same('raw_tex_inline', $inline->type);
        $t->same('tex', $inline->attr('format'));
        $t->same(['t' => 'Format', 'c' => 'tex'], $inline->attr('formatNative'));
        $t->same('\\alpha', $inline->attr('tex'));
        $t->same(['t' => 'RawInline', 'c' => [['t' => 'Format', 'c' => 'tex'], '\\alpha']], $jsonPacket['blocks'][0]['c'][2]);
        $t->same($jsonPacket['blocks'][0]['c'][2], $nativePacket['blocks'][0]['c'][2]);
        $t->same(['t' => 'RawInline', 'c' => ['latex', '\\beta']], $manualPacket['blocks'][0]['c'][0]);
        $t->same('raw_tex_inline', $roundTrip->children[0]->children[2]->type);
        $t->same('\\alpha', $roundTrip->children[0]->children[2]->attr('tex'));
    },
    'serializes native text markdown raw format constructors through pandoc json writers' => static function (TestRunner $t): void {
        $nativeText = <<<'NATIVE'
Pandoc Meta {unMeta = fromList []} [ RawBlock (Format "markdown") "**raw block**", Para [ Str "Before", Space, RawInline (Format "gfm+raw_html") "<span>inline</span>", Space, RawInline (Format "latex") "\\beta" ] ]
NATIVE;

        $nativeDocument = (new NativeReader())->read($nativeText);
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $roundTrip = (new PandocJsonReader())->readPacket($jsonPacket);

        $rawBlock = $nativeDocument->children[0];
        $rawInline = $nativeDocument->children[1]->children[2];
        $rawTexInline = $nativeDocument->children[1]->children[4];

        $t->same('raw_markdown', $rawBlock->type);
        $t->same('markdown', $rawBlock->attr('format'));
        $t->same(['t' => 'Format', 'c' => 'markdown'], $rawBlock->attr('formatNative'));
        $t->same('**raw block**', $rawBlock->attr('markdown'));
        $t->same('raw_markdown', $rawInline->type);
        $t->same('gfm+raw_html', $rawInline->attr('format'));
        $t->same(['t' => 'Format', 'c' => 'gfm+raw_html'], $rawInline->attr('formatNative'));
        $t->same('<span>inline</span>', $rawInline->attr('markdown'));
        $t->same('raw_tex_inline', $rawTexInline->type);
        $t->same('latex', $rawTexInline->attr('format'));
        $t->same(['t' => 'Format', 'c' => 'latex'], $rawTexInline->attr('formatNative'));
        $t->same('\\beta', $rawTexInline->attr('tex'));
        $t->same(['t' => 'RawBlock', 'c' => [['t' => 'Format', 'c' => 'markdown'], '**raw block**']], $jsonPacket['blocks'][0]);
        $t->same(['t' => 'RawInline', 'c' => [['t' => 'Format', 'c' => 'gfm+raw_html'], '<span>inline</span>']], $jsonPacket['blocks'][1]['c'][2]);
        $t->same(['t' => 'RawInline', 'c' => [['t' => 'Format', 'c' => 'latex'], '\\beta']], $jsonPacket['blocks'][1]['c'][4]);
        $t->same($jsonPacket['blocks'][0], $nativePacket['blocks'][0]);
        $t->same($jsonPacket['blocks'][1]['c'][2], $nativePacket['blocks'][1]['c'][2]);
        $t->same('raw_markdown', $roundTrip->children[0]->type);
        $t->same('raw_markdown', $roundTrip->children[1]->children[2]->type);
        $t->same('raw_tex_inline', $roundTrip->children[1]->children[4]->type);
    },
    'preserves single wrapped raw format constructors through json and native stacks' => static function (TestRunner $t): void {
        $blockFormat = ['t' => 'Format', 'c' => [['html']], 'reviewQueue' => 'raw-block-format-source'];
        $inlineFormat = ['t' => 'Format', 'c' => [['latex']], 'reviewQueue' => 'raw-inline-format-source'];
        $genericFormat = ['t' => 'Format', 'c' => [['opml']], 'reviewQueue' => 'raw-generic-format-source'];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'RawBlock', 'c' => [
                    $blockFormat,
                    '<section data-review="format">raw</section>',
                ]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'RawInline', 'c' => [$inlineFormat, '\\alpha']],
                    ['t' => 'Space'],
                    ['t' => 'RawInline', 'c' => [$genericFormat, '<outline text="review"/>']],
                ]],
            ],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $rawBlock = $document->children[0];
            $paragraph = $document->children[1];
            $rawInline = $paragraph->children[0];
            $genericInline = $paragraph->children[2];
            $jsonPacket = (new PandocJsonWriter())->toArray($document);
            $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

            $t->same('raw_html', $rawBlock->type, "{$source} raw block type");
            $t->same('html', $rawBlock->attr('format'), "{$source} raw block format");
            $t->same($blockFormat, $rawBlock->attr('formatNative'), "{$source} raw block format native");
            $t->same('raw_tex_inline', $rawInline->type, "{$source} raw inline type");
            $t->same($inlineFormat, $rawInline->attr('formatNative'), "{$source} raw inline format native");
            $t->same('raw_inline', $genericInline->type, "{$source} generic raw inline type");
            $t->same('opml', $genericInline->attr('format'), "{$source} generic raw inline format");
            $t->same($genericFormat, $genericInline->attr('formatNative'), "{$source} generic raw inline native");
            $t->same($packet['blocks'], $jsonPacket['blocks'], "{$source} json writer preserves wrapped raw formats");
            $t->same($packet['blocks'], $nativePacket['blocks'], "{$source} native writer preserves wrapped raw formats");

            $editedBlockAttrs = $rawBlock->attrs;
            $editedBlockAttrs['format'] = 'markdown';
            $editedBlockAttrs['text'] = '**changed**';
            $editedBlockAttrs['markdown'] = '**changed**';
            unset($editedBlockAttrs['html']);

            $editedInlineAttrs = $rawInline->attrs;
            $editedInlineAttrs['format'] = 'html';
            $editedInlineAttrs['text'] = '<em>changed</em>';
            $editedInlineAttrs['html'] = '<em>changed</em>';
            unset($editedInlineAttrs['tex']);

            $edited = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
                new AstNode('raw_markdown', $editedBlockAttrs),
                new AstNode('paragraph', [], [
                    new AstNode('raw_html_inline', $editedInlineAttrs),
                ]),
            ]);
            $editedJson = (new PandocJsonWriter())->toArray($edited);
            $editedNative = json_decode((new NativeWriter())->write($edited), true, 512, JSON_THROW_ON_ERROR);

            $t->same('markdown', $editedJson['blocks'][0]['c'][0], "{$source} json writer drops stale raw block format sidecar");
            $t->same('markdown', $editedNative['blocks'][0]['c'][0], "{$source} native writer drops stale raw block format sidecar");
            $t->same('html', $editedJson['blocks'][1]['c'][0]['c'][0], "{$source} json writer drops stale raw inline format sidecar");
            $t->same('html', $editedNative['blocks'][1]['c'][0]['c'][0], "{$source} native writer drops stale raw inline format sidecar");
        }
    },
    'validates malformed pandoc json packets without shelling out' => static function (TestRunner $t): void {
        $reader = new PandocJsonReader();
        $writer = new PandocJsonWriter();
        $citePacket = static fn (array $records): array => [
            'blocks' => [[
                't' => 'Para',
                'c' => [[
                    't' => 'Cite',
                    'c' => [$records, []],
                ]],
            ]],
        ];

        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->read('{"meta":{}}'));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->readPacket(['blocks' => [['t' => 'Table', 'c' => []]]]));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->readPacket($citePacket([])));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->readPacket($citePacket([[
            'citationPrefix' => [],
            'citationSuffix' => [],
            'citationMode' => ['t' => 'NormalCitation'],
        ]])));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->readPacket($citePacket([[
            'citationId' => 'bad',
            'citationPrefix' => 'see',
            'citationSuffix' => [],
            'citationMode' => ['t' => 'NormalCitation'],
        ]])));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->readPacket($citePacket([[
            'citationId' => 'bad',
            'citationPrefix' => [],
            'citationSuffix' => [],
            'citationMode' => ['t' => 'NarrativeCitation'],
        ]])));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->readPacket($citePacket([[
            'citationId' => 'bad',
            'citationPrefix' => [],
            'citationSuffix' => [],
            'citationMode' => ['t' => 'NormalCitation'],
            'citationHash' => 'hash',
        ]])));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->readPacket([
            'blocks' => [[
                't' => 'Header',
                'c' => [2, ['bad-classes', [42], []], []],
            ]],
        ]));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->readPacket([
            'blocks' => [[
                't' => 'Header',
                'c' => [2, ['bad-attributes', [], [['data-source', 42]]], []],
            ]],
        ]));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->readPacket(['pandoc-api-version' => ['1'], 'blocks' => []]));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => (new NativeReader())->read(json_encode([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [[
                't' => 'Header',
                'c' => [2, ['bad-native-classes', [42], []], []],
            ]],
        ], JSON_THROW_ON_ERROR)));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => (new NativeReader())->read(json_encode([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [[
                't' => 'Header',
                'c' => [2, ['bad-native-attributes', [], [['data-source', 42]]], []],
            ]],
        ], JSON_THROW_ON_ERROR)));
        $t->throws(InvalidArgumentException::class, static fn (): string => $writer->write(new AstNode('paragraph')));
        $t->throws(InvalidArgumentException::class, static fn (): array => $writer->toArray(new AstNode('document', [], [new AstNode('unsupported_block')])));
        $t->throws(InvalidArgumentException::class, static fn (): array => $writer->toArray(new AstNode('document', [], [new AstNode('paragraph', [], [new AstNode('citation')])])));
    },
    'validates malformed native metadata constructors without shelling out' => static function (TestRunner $t): void {
        $reader = new NativeReader();

        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->read(json_encode([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'review' => ['t' => 'VendorMeta', 'c' => 'opaque'],
            ],
            'blocks' => [],
        ], JSON_THROW_ON_ERROR)));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->read(json_encode([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'review' => ['t' => 'MetaMap', 'c' => [
                    'queue' => ['t' => 'MetaString', 'c' => 'native-review'],
                    'bad' => ['t' => 'VendorMeta', 'c' => 'nested'],
                ]],
            ],
            'blocks' => [],
        ], JSON_THROW_ON_ERROR)));
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->read(json_encode([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'review' => ['t' => 'MetaList', 'c' => [
                    ['t' => 'MetaString', 'c' => 'native-review'],
                    ['t' => 'VendorMeta', 'c' => 'nested'],
                ]],
            ],
            'blocks' => [],
        ], JSON_THROW_ON_ERROR)));
    },
    'renders wordpress blocks from pandoc json filter input' => static function (TestRunner $t): void {
        $json = <<<'JSON'
{
  "pandoc-api-version": [1, 23, 1],
  "meta": {},
  "blocks": [
    {"t":"Header","c":[2,["json-review",["wp-import"],[["data-source","json-filter"]]], [{"t":"Str","c":"JSON"},{"t":"Space"},{"t":"Str","c":"Review"}]]},
    {"t":"Para","c":[
      {"t":"Str","c":"Filter"},
      {"t":"Space"},
      {"t":"Link","c":[["",[],[]],[{"t":"Str","c":"source"}],["https://example.test/source",""]]},
      {"t":"Space"},
      {"t":"Code","c":[["",["php"],[]],"wp_insert_post"]},
      {"t":"Space"},
      {"t":"Note","c":[{"t":"Para","c":[{"t":"Str","c":"Keep"},{"t":"Space"},{"t":"Str","c":"review"}]}]}
    ]}
  ]
}
JSON;

        $blocks = (new WordPressBlockWriter())->write((new PandocJsonReader())->read($json));

        $t->contains('<h2 id="json-review" class="wp-import" data-source="json-filter">JSON Review</h2>', $blocks);
        $t->contains('<a href="https://example.test/source">source</a>', $blocks);
        $t->contains('<code class="php">wp_insert_post</code>', $blocks);
        $t->contains('<section class="footnotes" role="doc-endnotes"><ol><li id="fn-1"><p>Keep review</p>', $blocks);
    },
    'emits stable json text that can be decoded and read again' => static function (TestRunner $t): void {
        $writer = new PandocJsonWriter();
        $reader = new PandocJsonReader();
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => 'https://example.test/uploads/source packet(1).jpg',
                    'title' => 'Source packet',
                ], [new AstNode('text', ['text' => 'Source screenshot'])]),
            ]),
        ]);

        $json = $writer->write($document);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $roundTrip = $reader->read($json);

        $t->contains("\"pandoc-api-version\": [\n        1,\n        23,\n        1\n    ]", $json);
        $t->same('Image', $decoded['blocks'][0]['c'][0]['t']);
        $t->same('https://example.test/uploads/source packet(1).jpg', $decoded['blocks'][0]['c'][0]['c'][2][0]);
        $t->same('image', $roundTrip->children[0]->children[0]->type);
        $t->same('Source screenshot', $roundTrip->children[0]->children[0]->children[0]->attr('text'));
    },
];
