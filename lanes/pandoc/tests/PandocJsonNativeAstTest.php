<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;
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
        $t->throws(InvalidArgumentException::class, static fn (): array => $writer->toArray(new AstNode('document', [], [new AstNode('table')])));
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

        $t->contains('<h2 id="json-review" class="wp-import">JSON Review</h2>', $blocks);
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
