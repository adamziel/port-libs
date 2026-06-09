<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
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
        ]);

        $packet = (new PandocJsonWriter())->toArray($document);
        $roundTrip = (new PandocJsonReader())->readPacket($packet);

        $t->same(['BlockQuote', 'BulletList', 'OrderedList', 'LineBlock', 'CodeBlock', 'RawBlock', 'Div', 'HorizontalRule'], array_map(static fn (array $block): string => $block['t'], $packet['blocks']));
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
        $image = $figure->children[0]->children[0];
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
        $t->contains('<img src="media/review.png" alt="" title="Figure image" class="review-image" data-image="source"/>', $blocks);
        $t->contains('<figcaption>Reviewer figure</figcaption>', $blocks);
        $t->true(!str_contains($blocks, 'onclick'), 'Unsafe event handlers must not render on Pandoc Figure output');
        $t->true(!str_contains($blocks, 'style="display:none"'), 'Unsafe style attributes must not render on Pandoc Figure output');
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
        $t->contains('<img src="/media/diagram.png" alt="" title="Diagram title" class="inline-media" data-review="image" xml:lang="en-US"/>', $blocks);
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
        $t->same(1, $encoded['blocks'][0]['c'][4][0][1]);
        $t->same('Short caption', $roundTrip->children[0]->attr('shortCaption'));
        $t->same('Long caption reviewer', $roundTrip->children[0]->attr('caption'));
        $t->contains('<figure class="wp-block-table" data-pandoc-short-caption="Short caption">', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Long <em>caption</em> <a href="https://example.test/review" title="Review">reviewer</a></figcaption>', $blocks);
        $t->same('captionBlocks', $packet['captions']['long']['source'] ?? null);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
        $t->same('Fallback', $generated['blocks'][0]['c'][1][0][0]['c']);
        $t->same('Fallback', $generated['blocks'][0]['c'][1][1][0]['c'][0]['c']);
        $t->same('Fallback short', $generatedRoundTrip->children[0]->attr('shortCaption'));
        $t->same('Fallback long', $generatedRoundTrip->children[0]->attr('caption'));
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
