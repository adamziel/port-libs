<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
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
    'preserves table helper native payloads until semantic edits' => static function (TestRunner $t): void {
        $tableBlock = [
            't' => 'Table',
            'c' => [
                ['payload-table', [], []],
                ['t' => 'Caption', 'c' => [null, []]],
                [
                    [['t' => 'AlignLeft'], ['t' => 'ColWidth', 'c' => [0.33]]],
                    [['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']],
                ],
                ['t' => 'TableHead', 'c' => [['', [], []], []]],
                [
                    ['t' => 'TableBody', 'c' => [
                        ['', [], []],
                        ['t' => 'RowHeadColumns', 'c' => [2]],
                        [],
                        [
                            ['t' => 'Row', 'c' => [
                                ['', [], []],
                                [
                                    ['t' => 'Cell', 'c' => [
                                        ['', [], []],
                                        ['t' => 'AlignCenter'],
                                        ['t' => 'RowSpan', 'c' => [3]],
                                        ['t' => 'ColSpan', 'c' => [2]],
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

            $t->same([['t' => 'AlignLeft'], ['t' => 'AlignDefault']], $table->attr('alignmentNatives'), "{$source} table alignment native payloads");
            $t->same([['t' => 'ColWidth', 'c' => [0.33]], ['t' => 'ColWidthDefault']], $table->attr('columnWidthNatives'), "{$source} table width native payloads");
            $t->same(['t' => 'RowHeadColumns', 'c' => [2]], $body->attr('rowHeadColumnsNative'), "{$source} row head columns native payload");
            $t->same(['t' => 'AlignCenter'], $cell->attr('alignmentNative'), "{$source} cell alignment native payload");
            $t->same(['t' => 'RowSpan', 'c' => [3]], $cell->attr('rowSpanNative'), "{$source} cell row span native payload");
            $t->same(['t' => 'ColSpan', 'c' => [2]], $cell->attr('colSpanNative'), "{$source} cell column span native payload");
        }

        $jsonPacket = (new PandocJsonWriter())->toArray($documents['json']);
        $nativePacket = json_decode((new NativeWriter())->write($documents['native']), true, 512, JSON_THROW_ON_ERROR);
        $constructorContent = static function (mixed $value): mixed {
            if (is_array($value) && !array_is_list($value) && is_string($value['t'] ?? null)) {
                return $value['c'] ?? null;
            }

            return $value;
        };

        foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $source => $encoded) {
            $encodedTable = $encoded['blocks'][0];
            $encodedTableContent = $constructorContent($encodedTable);
            $encodedBody = $constructorContent($encodedTableContent[4][0]);
            $encodedRow = $constructorContent($encodedBody[3][0]);
            $encodedCell = $constructorContent($encodedRow[1][0]);

            $t->same($tableBlock['c'][2], $encodedTableContent[2], "{$source} writer preserves table column helper payloads");
            $t->same(['t' => 'RowHeadColumns', 'c' => [2]], $encodedBody[1], "{$source} writer preserves row head columns payload");
            $t->same(['t' => 'AlignCenter'], $encodedCell[1], "{$source} writer preserves cell alignment payload");
            $t->same(['t' => 'RowSpan', 'c' => [3]], $encodedCell[2], "{$source} writer preserves cell row span payload");
            $t->same(['t' => 'ColSpan', 'c' => [2]], $encodedCell[3], "{$source} writer preserves cell column span payload");
        }

        $table = $documents['json']->children[0];
        $body = $table->children[0];
        $row = $body->children[0];
        $cell = $row->children[0];
        $editedCell = new AstNode('table_cell', array_replace($cell->attrs, [
            'align' => 'right',
            'rowspan' => 4,
            'colspan' => 1,
        ]), $cell->children);
        $editedBody = new AstNode('table_body', array_replace($body->attrs, [
            'rowHeadColumns' => 1,
        ]), [
            new AstNode('table_row', $row->attrs, [$editedCell]),
        ]);
        $editedTable = new AstNode('table', array_replace($table->attrs, [
            'widths' => [0.5, null],
        ]), [$editedBody]);
        $editedDocument = new AstNode('document', $documents['json']->attrs, [$editedTable]);
        $editedPackets = [
            'json' => (new PandocJsonWriter())->toArray($editedDocument),
            'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
        ];

        foreach ($editedPackets as $source => $encoded) {
            $encodedTable = $encoded['blocks'][0];
            $encodedTableContent = $constructorContent($encodedTable);
            $encodedBody = $constructorContent($encodedTableContent[4][0]);
            $encodedRow = $constructorContent($encodedBody[3][0]);
            $encodedCell = $constructorContent($encodedRow[1][0]);

            $t->same(['t' => 'ColWidth', 'c' => 0.5], $encodedTableContent[2][0][1], "{$source} writer regenerates edited column width");
            $t->same(['t' => 'RowHeadColumns', 'c' => 1], $encodedBody[1], "{$source} writer regenerates edited row head columns");
            $t->same(['t' => 'AlignRight'], $encodedCell[1], "{$source} writer regenerates edited cell alignment");
            $t->same(['t' => 'RowSpan', 'c' => 4], $encodedCell[2], "{$source} writer regenerates edited cell row span");
            $t->same(['t' => 'ColSpan', 'c' => 1], $encodedCell[3], "{$source} writer regenerates edited cell column span");
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
        $jsonTableRow = $jsonTableBody[3][0];
        $jsonTableCell = $jsonTableRow[1][0];
        $nativeTableBody = $nativePacket['blocks'][3]['c'][4][0]['c'];
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
        $t->same($bodyAttr, $jsonTableBody[0]);
        $t->same($rowAttr, $jsonTableRow[0]);
        $t->same($cellAttr, $jsonTableCell[0]);
        $t->same('TableBody', $nativePacket['blocks'][3]['c'][4][0]['t']);
        $t->same($tableAttr, $nativePacket['blocks'][3]['c'][0]);
        $t->same($bodyAttr, $nativeTableBody[0]);
        $t->same($rowAttr, $nativeTableRow[0]);
        $t->same($cellAttr, $nativeTableCell[0]);
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
        $tableCell = $tableBody[3][0][1][0];

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
        $t->same(['t' => 'RowHeadColumns', 'c' => 1], $tableBody[1]);
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
        $t->same('Plain', $encoded['blocks'][0]['c'][2][0]['t']);
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
        $t->same('Plain', $encoded['blocks'][0]['c'][2][0]['t']);
        $t->same('Image', $encoded['blocks'][0]['c'][2][0]['c'][0]['t']);
        $t->same('Alt', $encoded['blocks'][0]['c'][2][0]['c'][0]['c'][1][0]['c']);
        $t->same('figure', $roundTrip->children[0]->type);
        $t->same('Alt text', $roundTrip->children[0]->children[0]->attr('alt'));
        $t->contains('<figure class="wp-block-image wp-import" id="json-figure" data-source="json-filter"><img src="media/hero.png" alt="Alt text" title="Hero title" class="hero-image" data-source="media-bag"/><figcaption>Long caption source</figcaption></figure>', $blocks);
        $t->same('Figure', $generated['blocks'][0]['t']);
        $t->same('Generated', $generated['blocks'][0]['c'][1][0][0]['c']);
        $t->same('Generated', $generated['blocks'][0]['c'][1][1][0]['c'][0]['c']);
        $t->same('Generated', $generated['blocks'][0]['c'][2][0]['c'][0]['c'][1][0]['c']);
        $t->same('generated-figure', $generatedRoundTrip->children[0]->attr('id'));
        $t->same('Generated caption', $generatedRoundTrip->children[0]->attr('caption'));
        $t->same('Generated short', $generatedRoundTrip->children[0]->attr('shortCaption'));
        $t->same('Generated alt', $generatedRoundTrip->children[0]->children[0]->attr('alt'));
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
        $t->same(['t' => 'RowHeadColumns', 'c' => 1], $encoded['blocks'][0]['c'][4][0][1]);
        $t->same(['t' => 'RowSpan', 'c' => 1], $encoded['blocks'][0]['c'][4][0][3][0][1][1][2]);
        $t->same(['t' => 'ColSpan', 'c' => 1], $encoded['blocks'][0]['c'][4][0][3][0][1][1][3]);
        $t->same('Short caption', $roundTrip->children[0]->attr('shortCaption'));
        $t->same('Long caption reviewer', $roundTrip->children[0]->attr('caption'));
        $t->contains('<figure class="wp-block-table" data-pandoc-short-caption="Short caption">', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Long <em>caption</em> <a href="https://example.test/review" title="Review">reviewer</a></figcaption>', $blocks);
        $t->same('captionBlocks', $packet['captions']['long']['source'] ?? null);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
        $t->same('Fallback', $generated['blocks'][0]['c'][1][0][0]['c']);
        $t->same('Fallback', $generated['blocks'][0]['c'][1][1][0]['c'][0]['c']);
        $t->same(['t' => 'RowHeadColumns', 'c' => 0], $generated['blocks'][0]['c'][4][0][1]);
        $t->same(['t' => 'RowSpan', 'c' => 1], $generated['blocks'][0]['c'][4][0][3][0][1][0][2]);
        $t->same(['t' => 'ColSpan', 'c' => 1], $generated['blocks'][0]['c'][4][0][3][0][1][0][3]);
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
        $jsonBody = $jsonPacket['blocks'][0]['c'][4][0];
        $nativeBody = $nativePacket['blocks'][0]['c'][4][0];
        $jsonCells = $jsonBody[3][0][1];
        $nativeCells = $nativeBody[3][0][1];
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
        $t->same('Review', $encoded['blocks'][0]['c'][1][0][0]['c']);
        $t->same('Space', $encoded['blocks'][0]['c'][1][0][1]['t']);
        $t->same('Emph', $encoded['blocks'][0]['c'][1][0][2]['t']);
        $t->same('Plain', $encoded['blocks'][0]['c'][1][1][0]['t']);
        $t->same('JSON long caption', $table->attr('caption'));
        $t->same('Review queue', $table->attr('shortCaption'));
        $t->same('shortCaptionInlines', $roundTripPacket['captions']['short']['source'] ?? null);
        $t->same(['text', 'space', 'emph'], $roundTripPacket['captions']['short']['inlineTypes'] ?? null);
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
        $t->throws(InvalidArgumentException::class, static fn (): AstNode => $reader->readPacket(['pandoc-api-version' => ['1'], 'blocks' => []]));
        $t->throws(InvalidArgumentException::class, static fn (): string => $writer->write(new AstNode('paragraph')));
        $t->throws(InvalidArgumentException::class, static fn (): array => $writer->toArray(new AstNode('document', [], [new AstNode('unsupported_block')])));
        $t->throws(InvalidArgumentException::class, static fn (): array => $writer->toArray(new AstNode('document', [], [new AstNode('paragraph', [], [new AstNode('citation')])])));
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
