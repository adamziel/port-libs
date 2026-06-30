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
    'round trips pandoc native json metadata constructors without loss' => static function (TestRunner $t): void {
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'title' => [
                    't' => 'MetaInlines',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Quarterly'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'Review'],
                    ],
                ],
                'author' => [
                    't' => 'MetaList',
                    'c' => [
                        [
                            't' => 'MetaInlines',
                            'c' => [
                                ['t' => 'Str', 'c' => 'Ada'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'Lovelace'],
                            ],
                        ],
                        [
                            't' => 'MetaInlines',
                            'c' => [
                                ['t' => 'Str', 'c' => 'Grace'],
                            ],
                        ],
                    ],
                ],
                'date' => [
                    't' => 'MetaInlines',
                    'c' => [
                        ['t' => 'Str', 'c' => '2026-06-11'],
                    ],
                ],
                'draft' => ['t' => 'MetaBool', 'c' => false],
                'tags' => [
                    't' => 'MetaList',
                    'c' => [
                        ['t' => 'MetaString', 'c' => 'wp-import'],
                        ['t' => 'MetaString', 'c' => 'native-ast'],
                    ],
                ],
                'review' => [
                    't' => 'MetaMap',
                    'c' => [
                        'source' => ['t' => 'MetaString', 'c' => 'native-json'],
                        'priority' => ['t' => 'MetaString', 'c' => 'high'],
                    ],
                ],
            ],
            'blocks' => [
                [
                    't' => 'Para',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Body'],
                    ],
                ],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $meta = $document->attr('meta');

        $t->same('document', $document->type);
        $t->same('pandoc-json', $document->attr('nativeFormat'));
        $t->same($native['pandoc-api-version'], $document->attr('pandocApiVersion'));
        $t->same($native['meta']['title'], $meta['title']);
        $t->same($native['meta']['author'], $meta['author']);
        $t->same($native['meta']['date'], $meta['date']);
        $t->same($native['meta']['draft'], $meta['draft']);
        $t->same($native['meta']['tags'], $meta['tags']);
        $t->same($native['meta']['review'], $meta['review']);
        $t->same('Quarterly Review', $meta['titleInlines'][0]->attr('text'));
        $t->same('Ada Lovelace', $meta['authorInlines'][0][0]->attr('text'));
        $t->same('Grace', $meta['authorInlines'][1][0]->attr('text'));
        $t->same('2026-06-11', $meta['dateInlines'][0]->attr('text'));
        $t->same('Para', $document->children[0]->attr('constructor'));
        $t->same($native['meta'], $roundTrip['meta']);
        $t->same($native['blocks'], $roundTrip['blocks']);
        $t->same($native, $roundTrip);
    },
    'writes shared metadata values as pandoc native meta constructors' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [
                'title' => 'Fallback title',
                'titleInlines' => [
                    new AstNode('text', ['text' => 'Native']),
                    new AstNode('space'),
                    new AstNode('emph', [], [new AstNode('text', ['text' => 'metadata'])]),
                ],
                'authorInlines' => [
                    [
                        new AstNode('text', ['text' => 'Ada']),
                        new AstNode('space'),
                        new AstNode('text', ['text' => 'Lovelace']),
                    ],
                    [
                        new AstNode('text', ['text' => 'Grace']),
                    ],
                ],
                'authors' => ['Fallback Author'],
                'dateInlines' => [
                    new AstNode('text', ['text' => '2026-06-10']),
                ],
                'draft' => false,
                'priority' => 3,
                'review' => ['type' => 'map', 'items' => [
                    'tags' => ['type' => 'list', 'items' => ['native', true, 2]],
                    'body' => ['type' => 'blocks', 'children' => [
                        new AstNode('paragraph', [], [
                            new AstNode('text', ['text' => 'Reviewer']),
                            new AstNode('space'),
                            new AstNode('text', ['text' => 'note']),
                        ]),
                    ]],
                    'inline' => new AstNode('strong', [], [
                        new AstNode('text', ['text' => 'inline']),
                    ]),
                    'nullable' => null,
                ]],
                'pretagged' => ['t' => 'MetaString', 'c' => 'kept'],
            ],
        ], [
            new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Body'])]),
        ]);

        $native = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $roundTrip = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $meta = $native['meta'];
        $review = $meta['review']['c'];

        $t->same('MetaInlines', $meta['title']['t']);
        $t->same('Native', $meta['title']['c'][0]['c']);
        $t->same('Emph', $meta['title']['c'][2]['t']);
        $t->same('MetaList', $meta['author']['t']);
        $t->same('Ada', $meta['author']['c'][0]['c'][0]['c']);
        $t->same('Grace', $meta['author']['c'][1]['c'][0]['c']);
        $t->same('MetaInlines', $meta['date']['t']);
        $t->same('2026-06-10', $meta['date']['c'][0]['c']);
        $t->same(false, array_key_exists('titleInlines', $meta));
        $t->same(false, array_key_exists('authorInlines', $meta));
        $t->same(false, array_key_exists('authors', $meta));
        $t->same(false, array_key_exists('dateInlines', $meta));
        $t->same('MetaBool', $meta['draft']['t']);
        $t->same(false, $meta['draft']['c']);
        $t->same('MetaString', $meta['priority']['t']);
        $t->same('3', $meta['priority']['c']);
        $t->same('MetaMap', $meta['review']['t']);
        $t->same('MetaList', $review['tags']['t']);
        $t->same('MetaString', $review['tags']['c'][0]['t']);
        $t->same('native', $review['tags']['c'][0]['c']);
        $t->same('MetaBool', $review['tags']['c'][1]['t']);
        $t->same(true, $review['tags']['c'][1]['c']);
        $t->same('2', $review['tags']['c'][2]['c']);
        $t->same('MetaBlocks', $review['body']['t']);
        $t->same('Para', $review['body']['c'][0]['t']);
        $t->same('MetaInlines', $review['inline']['t']);
        $t->same('Strong', $review['inline']['c'][0]['t']);
        $t->same('MetaString', $review['nullable']['t']);
        $t->same('', $review['nullable']['c']);
        $t->same(['t' => 'MetaString', 'c' => 'kept'], $meta['pretagged']);
        $roundTripMeta = $roundTrip->attr('meta');
        $t->same($meta['title'], $roundTripMeta['title']);
        $t->same($meta['author'], $roundTripMeta['author']);
        $t->same($meta['date'], $roundTripMeta['date']);
        $t->same($meta['draft'], $roundTripMeta['draft']);
        $t->same($meta['priority'], $roundTripMeta['priority']);
        $t->same($meta['review'], $roundTripMeta['review']);
        $t->same($meta['pretagged'], $roundTripMeta['pretagged']);
        $t->same('Native ', $roundTripMeta['titleInlines'][0]->attr('text'));
        $t->same('emph', $roundTripMeta['titleInlines'][1]->type);
        $t->same('metadata', $roundTripMeta['titleInlines'][1]->children[0]->attr('text'));
        $t->same('Ada Lovelace', $roundTripMeta['authorInlines'][0][0]->attr('text'));
        $t->same('Grace', $roundTripMeta['authorInlines'][1][0]->attr('text'));
        $t->same('2026-06-10', $roundTripMeta['dateInlines'][0]->attr('text'));
    },
    'normalizes legacy pandoc native json unMeta document arrays' => static function (TestRunner $t): void {
        $legacy = [
            [
                'unMeta' => [
                    'title' => [
                        't' => 'MetaInlines',
                        'c' => [
                            ['t' => 'Str', 'c' => 'Legacy'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'Native'],
                        ],
                    ],
                    'review' => [
                        't' => 'MetaMap',
                        'c' => [
                            'source' => ['t' => 'MetaString', 'c' => 'pre-1.18-filter'],
                        ],
                    ],
                ],
            ],
            [
                [
                    't' => 'Para',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Legacy'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'native'],
                    ],
                ],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($legacy, JSON_THROW_ON_ERROR));
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $meta = $document->attr('meta');

        $t->same('document', $document->type);
        $t->same('pandoc-json', $document->attr('nativeFormat'));
        $t->same($legacy[0]['unMeta']['title'], $meta['title']);
        $t->same($legacy[0]['unMeta']['review'], $meta['review']);
        $t->same('Legacy Native', $meta['titleInlines'][0]->attr('text'));
        $t->same('paragraph', $document->children[0]->type);
        $t->same('Legacy native', $document->children[0]->attr('text'));
        $t->same($legacy[0]['unMeta'], $roundTrip['meta']);
        $t->same($legacy[1], $roundTrip['blocks']);
        $t->same([1, 23, 1], $roundTrip['pandoc-api-version']);
    },
    'accepts native ast MetaMap metadata envelopes without losing literal unMeta keys' => static function (TestRunner $t): void {
        $reader = new NativeReader();
        $writer = new NativeWriter();
        $enveloped = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                't' => 'MetaMap',
                'c' => [
                    'title' => ['t' => 'MetaInlines', 'c' => [
                        ['t' => 'Str', 'c' => 'Constructor'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'metadata'],
                    ]],
                    'review' => ['t' => 'MetaMap', 'c' => [
                        'queue' => ['t' => 'MetaString', 'c' => 'native-import'],
                        'draft' => ['t' => 'MetaBool', 'c' => false],
                    ]],
                ],
            ],
            'blocks' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Metadata'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'body'],
                ]],
            ],
        ];

        $document = $reader->read(json_encode($enveloped, JSON_THROW_ON_ERROR));
        $roundTrip = json_decode($writer->write($document), true, 512, JSON_THROW_ON_ERROR);
        $legacyEnvelope = $reader->read(json_encode([
            'meta' => ['t' => 'MetaMap', 'c' => [
                'unMeta' => [
                    'source' => ['t' => 'MetaString', 'c' => 'legacy-envelope'],
                ],
            ]],
            'blocks' => [],
        ], JSON_THROW_ON_ERROR));
        $literalUnMeta = $reader->read(json_encode([
            'meta' => ['t' => 'MetaMap', 'c' => [
                'unMeta' => ['t' => 'MetaString', 'c' => 'literal-key'],
            ]],
            'blocks' => [],
        ], JSON_THROW_ON_ERROR));

        $meta = $document->attr('meta');
        $t->same($enveloped['meta']['c']['title'], $meta['title']);
        $t->same($enveloped['meta']['c']['review'], $meta['review']);
        $t->same('MetaInlines', $meta['title']['t']);
        $t->same('Constructor', $meta['title']['c'][0]['c']);
        $t->same('Constructor metadata', $meta['titleInlines'][0]->attr('text'));
        $t->same('MetaMap', $meta['review']['t']);
        $t->same('native-import', $meta['review']['c']['queue']['c']);
        $t->same(false, $meta['review']['c']['draft']['c']);
        $t->same('paragraph', $document->children[0]->type);
        $t->same($enveloped['meta']['c'], $roundTrip['meta']);
        $t->same(false, isset($roundTrip['meta']['t']));
        $t->same('legacy-envelope', $legacyEnvelope->attr('meta')['source']['c']);
        $t->same('MetaString', $literalUnMeta->attr('meta')['unMeta']['t']);
        $t->same('literal-key', $literalUnMeta->attr('meta')['unMeta']['c']);
    },
    'round trips markdown paragraph inlines through pandoc native ast json' => static function (TestRunner $t): void {
        $markdown = "Native *AST* **roundtrip** with `code` and [link](https://example.test/source)\nnext line.";
        $document = (new MarkdownReader())->read($markdown);

        $nativeJson = (new NativeWriter())->write($document);
        $native = json_decode($nativeJson, true, 512, JSON_THROW_ON_ERROR);
        $nativeInlineTypes = array_map(
            static fn (array $inline): string => $inline['t'],
            $native['blocks'][0]['c']
        );

        $roundTrip = (new NativeReader())->read($nativeJson);
        $roundTripMarkdown = (new MarkdownWriter())->write($roundTrip);

        $t->same('Para', $native['blocks'][0]['t']);
        $t->same([
            'Str',
            'Space',
            'Emph',
            'Space',
            'Strong',
            'Space',
            'Str',
            'Space',
            'Code',
            'Space',
            'Str',
            'Space',
            'Link',
            'SoftBreak',
            'Str',
            'Space',
            'Str',
        ], $nativeInlineTypes);
        $t->same('paragraph', $roundTrip->children[0]->type);
        $t->same('Native AST roundtrip with code and link next line.', $roundTrip->children[0]->attr('text'));
        $t->same($markdown, $roundTripMarkdown);
    },
    'writes shared inline constructors as pandoc native ast constructors' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('underline', [], [new AstNode('text', ['text' => 'under'])]),
                new AstNode('strikeout', [], [new AstNode('text', ['text' => 'old'])]),
                new AstNode('superscript', [], [new AstNode('text', ['text' => '2'])]),
                new AstNode('subscript', [], [new AstNode('text', ['text' => 'n'])]),
                new AstNode('small_caps', [], [new AstNode('text', ['text' => 'caps'])]),
                new AstNode('quoted', ['kind' => 'single'], [new AstNode('text', ['text' => 'quote'])]),
                new AstNode('math', ['display' => true, 'text' => 'E = mc^2']),
                new AstNode('raw_html_inline', ['html' => '<span data-review="raw">html</span>']),
                new AstNode('raw_tex', ['tex' => '\\alpha']),
                new AstNode('raw_markdown', ['format' => 'markdown+tex_math_dollars', 'markdown' => '$raw$']),
                new AstNode('raw_inline', ['format' => 'opml', 'text' => '<outline/>']),
                new AstNode('span', [
                    'id' => 'review-span',
                    'classes' => ['native-review'],
                    'attributes' => ['data-source' => 'shared-ast'],
                ], [new AstNode('text', ['text' => 'span'])]),
            ]),
        ]);

        $native = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $nativeInlines = $native['blocks'][0]['c'];
        $roundTrip = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $roundTripChildren = $roundTrip->children[0]->children;

        $t->same([
            'Underline',
            'Strikeout',
            'Superscript',
            'Subscript',
            'SmallCaps',
            'Quoted',
            'Math',
            'RawInline',
            'RawInline',
            'RawInline',
            'RawInline',
            'Span',
        ], array_map(static fn (array $inline): string => $inline['t'], $nativeInlines));
        $t->same('SingleQuote', $nativeInlines[5]['c'][0]['t']);
        $t->same('DisplayMath', $nativeInlines[6]['c'][0]['t']);
        $t->same('E = mc^2', $nativeInlines[6]['c'][1]);
        $t->same('html', $nativeInlines[7]['c'][0]);
        $t->same('<span data-review="raw">html</span>', $nativeInlines[7]['c'][1]);
        $t->same('latex', $nativeInlines[8]['c'][0]);
        $t->same('\\alpha', $nativeInlines[8]['c'][1]);
        $t->same('markdown+tex_math_dollars', $nativeInlines[9]['c'][0]);
        $t->same('$raw$', $nativeInlines[9]['c'][1]);
        $t->same('opml', $nativeInlines[10]['c'][0]);
        $t->same(['review-span', ['native-review'], [['data-source', 'shared-ast']]], $nativeInlines[11]['c'][0]);
        $t->same([
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'small_caps',
            'quoted',
            'math',
            'raw_html_inline',
            'raw_tex_inline',
            'raw_markdown',
            'raw_inline',
            'span',
        ], array_map(static fn (AstNode $node): string => $node->type, $roundTripChildren));
        $t->same('single', $roundTripChildren[5]->attr('kind'));
        $t->same(true, $roundTripChildren[6]->attr('display'));
        $t->same('<span data-review="raw">html</span>', $roundTripChildren[7]->attr('html'));
        $t->same('\\alpha', $roundTripChildren[8]->attr('tex'));
        $t->same('$raw$', $roundTripChildren[9]->attr('markdown'));
        $t->same('opml', $roundTripChildren[10]->attr('format'));
        $t->same('review-span', $roundTripChildren[11]->attr('id'));
        $t->same(['native-review'], $roundTripChildren[11]->attr('classes'));
        $t->same(['data-source' => 'shared-ast'], $roundTripChildren[11]->attr('attributes'));
    },
    'maps textual native markdown raw constructors like pandoc json raw aliases' => static function (TestRunner $t): void {
        $rawBlockText = "| A | B |\n| - | - |";
        $jsonPacket = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'RawBlock', 'c' => [['t' => 'Format', 'c' => 'markdown+pipe_tables'], $rawBlockText]],
                ['t' => 'Para', 'c' => [
                    ['t' => 'RawInline', 'c' => [['t' => 'Format', 'c' => 'gfm'], '**inline**']],
                    ['t' => 'Space'],
                    ['t' => 'RawInline', 'c' => [['t' => 'Format', 'c' => 'opml'], '<outline/>']],
                ]],
            ],
        ];
        $nativeText = <<<'NATIVE'
[ RawBlock (Format "markdown+pipe_tables") "| A | B |\n| - | - |"
, Para [ RawInline (Format "gfm") "**inline**" , Space , RawInline (Format "opml") "<outline/>" ]
]
NATIVE;

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($jsonPacket),
            'native text' => (new NativeReader())->read($nativeText),
        ];

        foreach ($documents as $source => $document) {
            $rawBlock = $document->children[0];
            $paragraph = $document->children[1];
            $markdownInline = $paragraph->children[0];
            $genericInline = $paragraph->children[2];

            $t->same('raw_markdown', $rawBlock->type, "{$source} block markdown raw alias");
            $t->same('markdown+pipe_tables', $rawBlock->attr('format'), "{$source} block raw format");
            $t->same($rawBlockText, $rawBlock->attr('markdown'), "{$source} block markdown payload");
            $t->same('raw_markdown', $markdownInline->type, "{$source} inline markdown raw alias");
            $t->same('gfm', $markdownInline->attr('format'), "{$source} inline raw format");
            $t->same('**inline**', $markdownInline->attr('markdown'), "{$source} inline markdown payload");
            $t->same('raw_inline', $genericInline->type, "{$source} unsupported raw inline remains generic");
            $t->same('opml', $genericInline->attr('format'), "{$source} generic inline format");
        }

        $nativeDocument = $documents['native text'];
        $jsonRoundTrip = (new NativeReader())->read((new NativeWriter())->write(new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children)));
        $textRoundTrip = (new NativeReader())->read((new NativeWriter(['blocksOnly' => true]))->write($nativeDocument));

        foreach (['json writer' => $jsonRoundTrip, 'native text writer' => $textRoundTrip] as $source => $roundTrip) {
            $t->same('raw_markdown', $roundTrip->children[0]->type, "{$source} preserves markdown raw block alias");
            $t->same('raw_markdown', $roundTrip->children[1]->children[0]->type, "{$source} preserves markdown raw inline alias");
            $t->same('raw_inline', $roundTrip->children[1]->children[2]->type, "{$source} preserves generic raw inline");
        }
    },
    'normalizes textual native cite constructors for pandoc json writer handoff' => static function (TestRunner $t): void {
        $nativeText = <<<'NATIVE'
[ Para
  [ Cite
      [ Citation { citationId = "doe1901" , citationPrefix = [] , citationSuffix = [] , citationMode = AuthorInText , citationNoteNum = 0 , citationHash = 1901 } ]
      [ Str "@doe1901" ]
  , Space
  , Cite
      [ Citation { citationId = "smith1899" , citationPrefix = [ Str "see" ] , citationSuffix = [ Str "p." , Space , Str "7" ] , citationMode = NormalCitation , citationNoteNum = 0 , citationHash = 1899 }
      , Citation { citationId = "roe1902" , citationPrefix = [] , citationSuffix = [] , citationMode = SuppressAuthor , citationNoteNum = 0 , citationHash = 1902 }
      ]
      [ Str "[see" , Space , Str "@smith1899," , Space , Str "p." , Space , Str "7;" , Space , Str "-@roe1902]" ]
  ]
]
NATIVE;

        $document = (new NativeReader())->read($nativeText);
        $paragraph = $document->children[0];
        $single = $paragraph->children[0];
        $cluster = $paragraph->children[2];
        $json = (new PandocJsonWriter())->toArray($document);
        $jsonRoundTrip = (new NativeReader())->read(json_encode($json, JSON_THROW_ON_ERROR));
        $nativeJson = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $blocksOnlyRoundTrip = (new NativeReader())->read((new NativeWriter(['blocksOnly' => true]))->write($document));

        $t->same('citation', $single->type);
        $t->same('doe1901', $single->attr('id'));
        $t->same('author_in_text', $single->attr('mode'));
        $t->same('@doe1901', $single->attr('text'));
        $t->same('citation_group', $cluster->type);
        $t->same('[see @smith1899, p. 7; -@roe1902]', $cluster->attr('text'));
        $t->same(['smith1899', 'roe1902'], array_map(static fn (AstNode $node): string => $node->attr('id'), $cluster->children));
        $t->same('see', $cluster->children[0]->attr('prefix')[0]->attr('text'));
        $t->same('p.', $cluster->children[0]->attr('suffix')[0]->attr('text'));
        $t->same('suppress_author', $cluster->children[1]->attr('mode'));

        $t->same('Cite', $json['blocks'][0]['c'][0]['t']);
        $t->same('doe1901', $json['blocks'][0]['c'][0]['c'][0][0]['citationId']);
        $t->same('AuthorInText', $json['blocks'][0]['c'][0]['c'][0][0]['citationMode']['t']);
        $t->same('Cite', $json['blocks'][0]['c'][2]['t']);
        $t->same(['smith1899', 'roe1902'], array_column($json['blocks'][0]['c'][2]['c'][0], 'citationId'));
        $t->same('SuppressAuthor', $json['blocks'][0]['c'][2]['c'][0][1]['citationMode']['t']);
        $t->same('Cite', $nativeJson['blocks'][0]['c'][0]['t']);
        $t->same('Cite', $nativeJson['blocks'][0]['c'][2]['t']);
        $t->same('citation', $jsonRoundTrip->children[0]->children[0]->type);
        $t->same('citation_group', $jsonRoundTrip->children[0]->children[2]->type);
        $t->same('citation_group', $blocksOnlyRoundTrip->children[0]->children[2]->type);
    },
    'preserves raw html through markdown writer serialization boundaries' => static function (TestRunner $t): void {
        $rawHtmlDocument = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Before']),
                new AstNode('space'),
                new AstNode('raw_html_inline', ['html' => '<span data-review="reader-raw">HTML</span>']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'after.']),
            ]),
        ]);

        $t->same(
            'Before <span data-review="reader-raw">HTML</span> after.',
            (new MarkdownWriter())->write($rawHtmlDocument)
        );

        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                [
                    't' => 'Para',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Before'],
                        ['t' => 'Space'],
                        ['t' => 'RawInline', 'c' => ['html', '<mark data-review="native-raw">raw</mark>']],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'after.'],
                    ],
                ],
                ['t' => 'RawBlock', 'c' => ['html', '<aside data-review="native-block">Block raw</aside>']],
            ],
        ];
        $nativeDocument = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $nativeMarkdown = (new MarkdownWriter())->write($nativeDocument);
        $blocks = (new WordPressBlockWriter())->write($nativeDocument);

        $t->same(
            ['text', 'raw_html_inline', 'text'],
            array_map(static fn (AstNode $node): string => $node->type, $nativeDocument->children[0]->children)
        );
        $t->same(implode("\n\n", [
            'Before <mark data-review="native-raw">raw</mark> after.',
            '<aside data-review="native-block">Block raw</aside>',
        ]), $nativeMarkdown);
        $t->contains('<p>Before <mark data-review="native-raw">raw</mark> after.</p>', $blocks);

        $generatedDocument = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Generated']),
                new AstNode('space'),
                new AstNode('raw_inline', ['format' => 'html', 'text' => '<span data-review="generic-raw">inline</span>']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'boundary.']),
            ]),
            new AstNode('raw_block', ['format' => 'html', 'text' => '<aside data-review="generic-block">Block</aside>']),
        ]);

        $t->same(implode("\n\n", [
            'Generated <span data-review="generic-raw">inline</span> boundary.',
            '<aside data-review="generic-block">Block</aside>',
        ]), (new MarkdownWriter())->write($generatedDocument));
    },
    'round trips native image and note inline constructors through shared ast' => static function (TestRunner $t): void {
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                [
                    't' => 'Para',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Screenshot'],
                        ['t' => 'Space'],
                        ['t' => 'Image', 'c' => [
                            ['native-image', ['asset'], [['data-source', 'media-bag']]],
                            [
                                ['t' => 'Str', 'c' => 'Alt'],
                                ['t' => 'Space'],
                                ['t' => 'Strong', 'c' => [
                                    ['t' => 'Str', 'c' => 'text'],
                                ]],
                            ],
                            ['media/diagram.png', 'Diagram title'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Note', 'c' => [
                            ['t' => 'Para', 'c' => [
                                ['t' => 'Str', 'c' => 'Footnote'],
                                ['t' => 'Space'],
                                ['t' => 'Link', 'c' => [
                                    ['', [], []],
                                    [
                                        ['t' => 'Str', 'c' => 'source'],
                                    ],
                                    ['https://example.test/source', 'Source'],
                                ]],
                            ]],
                        ]],
                    ],
                ],
            ],
        ];

        $reader = new NativeReader();
        $writer = new NativeWriter();
        $document = $reader->read(json_encode($native, JSON_THROW_ON_ERROR));
        $paragraph = $document->children[0];
        $image = $paragraph->children[1];
        $note = $paragraph->children[3];
        $roundTrip = json_decode($writer->write($document), true, 512, JSON_THROW_ON_ERROR);
        $generatedDocument = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Generated']),
                new AstNode('space'),
                new AstNode('image', [
                    'id' => 'generated-image',
                    'classes' => ['review-asset'],
                    'attributes' => ['data-source' => 'writer'],
                    'url' => 'media/generated.png',
                    'title' => 'Generated title',
                ], [
                    new AstNode('text', ['text' => 'Generated']),
                    new AstNode('space'),
                    new AstNode('strong', [], [new AstNode('text', ['text' => 'alt'])]),
                ]),
                new AstNode('space'),
                new AstNode('note', [], [
                    new AstNode('paragraph', [], [
                        new AstNode('text', ['text' => 'Generated']),
                        new AstNode('space'),
                        new AstNode('text', ['text' => 'note']),
                    ]),
                ]),
            ]),
        ]);
        $generated = json_decode($writer->write($generatedDocument), true, 512, JSON_THROW_ON_ERROR);
        $generatedRoundTrip = $reader->read(json_encode($generated, JSON_THROW_ON_ERROR));

        $t->same(['text', 'image', 'text', 'note'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('native-image', $image->attr('id'));
        $t->same(['asset'], $image->attr('classes'));
        $t->same(['data-source' => 'media-bag'], $image->attr('attributes'));
        $t->same('media/diagram.png', $image->attr('url'));
        $t->same('Diagram title', $image->attr('title'));
        $t->same('Alt text', $image->attr('alt'));
        $t->same(['text', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $image->children));
        $t->same('paragraph', $note->children[0]->type);
        $t->same('link', $note->children[0]->children[1]->type);
        $t->same('https://example.test/source', $note->children[0]->children[1]->attr('url'));
        $t->same($native['blocks'], $roundTrip['blocks']);
        $t->same('Image', $generated['blocks'][0]['c'][2]['t']);
        $t->same(['generated-image', ['review-asset'], [['data-source', 'writer']]], $generated['blocks'][0]['c'][2]['c'][0]);
        $t->same('Strong', $generated['blocks'][0]['c'][2]['c'][1][2]['t']);
        $t->same(['media/generated.png', 'Generated title'], $generated['blocks'][0]['c'][2]['c'][2]);
        $t->same('Note', $generated['blocks'][0]['c'][4]['t']);
        $t->same('Para', $generated['blocks'][0]['c'][4]['c'][0]['t']);
        $t->same('image', $generatedRoundTrip->children[0]->children[1]->type);
        $t->same('Generated alt', $generatedRoundTrip->children[0]->children[1]->attr('alt'));
        $t->same('note', $generatedRoundTrip->children[0]->children[3]->type);
    },
    'regenerates edited known native constructors instead of stale native payloads' => static function (TestRunner $t): void {
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                [
                    't' => 'Para',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Diagram'],
                        ['t' => 'Space'],
                        ['t' => 'Image', 'c' => [
                            ['old-image', ['asset'], [['data-source', 'old']]],
                            [
                                ['t' => 'Str', 'c' => 'Old'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'alt'],
                            ],
                            ['media/old.png', 'Old title'],
                        ]],
                    ],
                ],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $paragraph = $document->children[0];
        $image = $paragraph->children[1];
        $editedImage = new AstNode('image', array_replace($image->attrs, [
            'id' => 'new-image',
            'classes' => ['asset', 'reviewed'],
            'attributes' => ['data-source' => 'edited'],
            'url' => 'media/new.png',
            'title' => 'New title',
            'alt' => 'New alt',
        ]), [
            new AstNode('text', ['text' => 'New']),
            new AstNode('space'),
            new AstNode('strong', [], [new AstNode('text', ['text' => 'alt'])]),
        ]);
        $editedDocument = new AstNode('document', $document->attrs, [
            new AstNode('paragraph', $paragraph->attrs, [
                new AstNode('text', ['text' => 'Diagram']),
                new AstNode('space'),
                $editedImage,
            ]),
        ]);

        $encoded = json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR);
        $encodedImage = $encoded['blocks'][0]['c'][2];
        $roundTrip = (new NativeReader())->read(json_encode($encoded, JSON_THROW_ON_ERROR));
        $roundTripImage = $roundTrip->children[0]->children[1];

        $t->same('Para', $encoded['blocks'][0]['t']);
        $t->same('Image', $encodedImage['t']);
        $t->same(['new-image', ['asset', 'reviewed'], [['data-source', 'edited']]], $encodedImage['c'][0]);
        $t->same('New', $encodedImage['c'][1][0]['c']);
        $t->same('Strong', $encodedImage['c'][1][2]['t']);
        $t->same(['media/new.png', 'New title'], $encodedImage['c'][2]);
        $t->same('image', $roundTripImage->type);
        $t->same('new-image', $roundTripImage->attr('id'));
        $t->same('media/new.png', $roundTripImage->attr('url'));
        $t->same('New alt', $roundTripImage->attr('alt'));
    },
    'maps native raw block and citation constructors into shared ast' => static function (TestRunner $t): void {
        $rawBlock = ['t' => 'RawBlock', 'c' => ['html', '<section data-review="native-raw">Native raw</section>']];
        $citeInline = ['t' => 'Cite', 'c' => [
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
                    'citationHash' => 1899,
                ],
                [
                    'citationId' => 'doe1901',
                    'citationPrefix' => [],
                    'citationSuffix' => [],
                    'citationMode' => ['t' => 'AuthorInText'],
                    'citationNoteNum' => 0,
                    'citationHash' => 1901,
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
                ['t' => 'Str', 'c' => '@doe1901]'],
            ],
        ]];
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                $rawBlock,
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Archive'],
                    ['t' => 'Space'],
                    $citeInline,
                ]],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $raw = $document->children[0];
        $paragraph = $document->children[1];
        $cluster = $paragraph->children[1];
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $processed = CitationCslProcessor::fromItems([
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
                'id' => 'doe1901',
                'type' => 'article-journal',
                'title' => 'Import Notes',
                'author' => [
                    ['family' => 'Doe', 'given' => 'Grace'],
                ],
                'issued' => ['date-parts' => [[1901]]],
            ],
        ])->apply($document);
        $blocks = (new WordPressBlockWriter())->write($processed);

        $t->same('raw_html', $raw->type);
        $t->same('<section data-review="native-raw">Native raw</section>', $raw->attr('html'));
        $t->same('citation_group', $cluster->type);
        $t->same('[see @smith1899, p. 7; @doe1901]', $cluster->attr('text'));
        $t->same(['smith1899', 'doe1901'], array_map(static fn (AstNode $node): string => $node->attr('id'), $cluster->children));
        $t->same('see', $cluster->children[0]->attr('prefix')[0]->attr('text'));
        $t->same('author_in_text', $cluster->children[1]->attr('mode'));
        $t->same($native['blocks'], $roundTrip['blocks']);
        $t->contains('<section data-review="native-raw">Native raw</section>', $blocks);
        $t->contains('(see Smith 1899, p. 7; Doe (1901))', $blocks);

        $generatedDocument = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('raw_html', ['html' => '<aside data-review="generated">Generated raw</aside>']),
            new AstNode('paragraph', [], [
                new AstNode('citation_group', [], [
                    new AstNode('citation', [
                        'id' => 'smith1899',
                        'prefix' => [new AstNode('text', ['text' => 'see'])],
                        'suffix' => [
                            new AstNode('text', ['text' => 'p.']),
                            new AstNode('space'),
                            new AstNode('text', ['text' => '8']),
                        ],
                        'citationHash' => 99,
                    ]),
                    new AstNode('citation', [
                        'id' => 'doe1901',
                        'mode' => 'author_in_text',
                    ]),
                ]),
                new AstNode('space'),
                new AstNode('image', [
                    'url' => 'media/generated.png',
                    'title' => 'Generated title',
                    'alt' => 'Generated image',
                    'classes' => ['generated-media'],
                ]),
            ]),
        ]);
        $generated = json_decode((new NativeWriter())->write($generatedDocument), true, 512, JSON_THROW_ON_ERROR);
        $generatedRoundTrip = (new NativeReader())->read(json_encode($generated, JSON_THROW_ON_ERROR));
        $generatedInlines = $generated['blocks'][1]['c'];

        $t->same('RawBlock', $generated['blocks'][0]['t']);
        $t->same(['html', '<aside data-review="generated">Generated raw</aside>'], $generated['blocks'][0]['c']);
        $t->same(['Cite', 'Space', 'Image'], array_map(static fn (array $inline): string => $inline['t'], $generatedInlines));
        $t->same('see', $generatedInlines[0]['c'][0][0]['citationPrefix'][0]['c']);
        $t->same('p.', $generatedInlines[0]['c'][0][0]['citationSuffix'][0]['c']);
        $t->same('AuthorInText', $generatedInlines[0]['c'][0][1]['citationMode']['t']);
        $t->same('Generated', $generatedInlines[2]['c'][1][0]['c']);
        $t->same('citation_group', $generatedRoundTrip->children[1]->children[0]->type);
        $t->same('image', $generatedRoundTrip->children[1]->children[2]->type);
    },
    'maps core block constructors through pandoc native ast json' => static function (TestRunner $t): void {
        $nativeBlocks = [
            ['t' => 'Header', 'c' => [
                2,
                ['native-heading', ['review'], [['data-kind', 'header']]],
                [
                    ['t' => 'Str', 'c' => 'Review'],
                    ['t' => 'Space'],
                    ['t' => 'Strong', 'c' => [
                        ['t' => 'Str', 'c' => 'source'],
                    ]],
                ],
            ]],
            ['t' => 'CodeBlock', 'c' => [
                ['cli', ['bash'], [['data-review', 'code']]],
                "wp post get 42\nwp post meta list 42",
            ]],
            ['t' => 'BlockQuote', 'c' => [
                ['t' => 'Para', 'c' => [
                    ['t' => 'Str', 'c' => 'Quoted'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => 'source'],
                ]],
            ]],
            ['t' => 'BulletList', 'c' => [
                [
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'Check'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'media'],
                    ]],
                ],
            ]],
            ['t' => 'OrderedList', 'c' => [
                [3, ['t' => 'UpperAlpha'], ['t' => 'OneParen']],
                [
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Review'],
                        ]],
                    ],
                ],
            ]],
            ['t' => 'DefinitionList', 'c' => [
                [
                    [
                        ['t' => 'Str', 'c' => 'source'],
                        ['t' => 'Space'],
                        ['t' => 'Code', 'c' => [
                            ['', ['term-code'], [['data-term', 'source']]],
                            'packet',
                        ]],
                    ],
                    [
                        [
                            ['t' => 'Para', 'c' => [
                                ['t' => 'Str', 'c' => 'keeps'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'definition'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'text'],
                            ]],
                            ['t' => 'CodeBlock', 'c' => [
                                ['', ['bash'], []],
                                'wp post meta get 42 _source',
                            ]],
                        ],
                        [
                            ['t' => 'Plain', 'c' => [
                                ['t' => 'Str', 'c' => 'alternate'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'definition'],
                            ]],
                        ],
                    ],
                ],
            ]],
            ['t' => 'LineBlock', 'c' => [
                [
                    ['t' => 'Str', 'c' => 'Address'],
                    ['t' => 'Space'],
                    ['t' => 'Emph', 'c' => [
                        ['t' => 'Str', 'c' => 'line'],
                    ]],
                ],
                [
                    ['t' => 'Code', 'c' => [
                        ['', [], []],
                        'fallback',
                    ]],
                ],
            ]],
            ['t' => 'Div', 'c' => [
                ['packet', ['native-review'], [['data-source', 'core-blocks']]],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Str', 'c' => 'Wrapped'],
                    ]],
                ],
            ]],
            ['t' => 'HorizontalRule'],
            ['t' => 'Null'],
        ];
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => $nativeBlocks,
        ];

        $reader = new NativeReader();
        $writer = new NativeWriter();
        $document = $reader->read(json_encode($native, JSON_THROW_ON_ERROR));
        $roundTrip = json_decode($writer->write($document), true, 512, JSON_THROW_ON_ERROR);
        $definitionItem = $document->children[5]->children[0];
        $lineBlock = $document->children[6];

        $t->same([
            'heading',
            'code_block',
            'blockquote',
            'bullet_list',
            'ordered_list',
            'definition_list',
            'line_block',
            'div',
            'horizontal_rule',
            'null_block',
        ], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same(2, $document->children[0]->attr('level'));
        $t->same('native-heading', $document->children[0]->attr('id'));
        $t->same('Review source', $document->children[0]->attr('text'));
        $t->same(['bash'], $document->children[1]->attr('classes'));
        $t->same("wp post get 42\nwp post meta list 42", $document->children[1]->attr('text'));
        $t->same('Quoted source', $document->children[2]->children[0]->attr('text'));
        $t->same('Check media', $document->children[3]->children[0]->children[0]->attr('text'));
        $t->same(3, $document->children[4]->attr('start'));
        $t->same('upper_alpha', $document->children[4]->attr('style'));
        $t->same('one_paren', $document->children[4]->attr('delimiter'));
        $t->same('source packet', $definitionItem->children[0]->attr('text'));
        $t->same(['term-code'], $definitionItem->children[0]->children[1]->attr('classes'));
        $t->same('wp post meta get 42 _source', $definitionItem->children[1]->children[1]->attr('text'));
        $t->same('Address line', $lineBlock->children[0]->attr('text'));
        $t->same('fallback', $lineBlock->children[1]->attr('text'));
        $t->same('packet', $document->children[7]->attr('id'));
        $t->same(['data-source' => 'core-blocks'], $document->children[7]->attr('attributes'));
        $t->same($nativeBlocks, $roundTrip['blocks']);

        $generatedDocument = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('heading', ['level' => 3, 'id' => 'generated-heading', 'classes' => ['core-block']], [
                new AstNode('text', ['text' => 'Generated']),
                new AstNode('space'),
                new AstNode('emph', [], [new AstNode('text', ['text' => 'block'])]),
            ]),
            new AstNode('code_block', ['text' => 'wp media import file.png', 'classes' => ['bash']]),
            new AstNode('blockquote', [], [
                new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Generated quote'])]),
            ]),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Generated bullet'])])]),
            ]),
            new AstNode('ordered_list', ['start' => 5, 'style' => 'lower_roman', 'delimiter' => 'period'], [
                new AstNode('list_item', [], [new AstNode('plain', [], [new AstNode('text', ['text' => 'Generated order'])])]),
            ]),
            new AstNode('definition_list', [], [
                new AstNode('definition_item', [], [
                    new AstNode('definition_term', [], [new AstNode('strong', [], [new AstNode('text', ['text' => 'term'])])]),
                    new AstNode('definition', [], [
                        new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Generated definition'])]),
                        new AstNode('code_block', ['text' => 'wp option get siteurl']),
                    ]),
                ]),
            ]),
            new AstNode('line_block', [], [
                new AstNode('line', [], [new AstNode('text', ['text' => 'First line'])]),
                new AstNode('line', ['text' => 'Fallback line']),
            ]),
            new AstNode('div', ['id' => 'generated-div', 'attributes' => ['data-review' => 'native-core']], [
                new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Generated div'])]),
            ]),
            new AstNode('horizontal_rule'),
            new AstNode('null_block'),
        ]);
        $generated = json_decode($writer->write($generatedDocument), true, 512, JSON_THROW_ON_ERROR);
        $generatedRoundTrip = $reader->read(json_encode($generated, JSON_THROW_ON_ERROR));

        $t->same([
            'Header',
            'CodeBlock',
            'BlockQuote',
            'BulletList',
            'OrderedList',
            'DefinitionList',
            'LineBlock',
            'Div',
            'HorizontalRule',
            'Null',
        ], array_map(static fn (array $block): string => $block['t'], $generated['blocks']));
        $t->same(['generated-heading', ['core-block'], []], $generated['blocks'][0]['c'][1]);
        $t->same('Emph', $generated['blocks'][0]['c'][2][2]['t']);
        $t->same([5, ['t' => 'LowerRoman'], ['t' => 'Period']], $generated['blocks'][4]['c'][0]);
        $t->same('Strong', $generated['blocks'][5]['c'][0][0][0]['t']);
        $t->same('CodeBlock', $generated['blocks'][5]['c'][0][1][0][1]['t']);
        $t->same('Fallback', $generated['blocks'][6]['c'][1][0]['c']);
        $t->same(['generated-div', [], [['data-review', 'native-core']]], $generated['blocks'][7]['c'][0]);
        $t->same('heading', $generatedRoundTrip->children[0]->type);
        $t->same('Generated block', $generatedRoundTrip->children[0]->attr('text'));
        $t->same('definition_list', $generatedRoundTrip->children[5]->type);
        $t->same('Fallback line', $generatedRoundTrip->children[6]->children[1]->attr('text'));
        $t->same('null_block', $generatedRoundTrip->children[9]->type);
    },
    'maps native ast figure constructors through shared figure ast' => static function (TestRunner $t): void {
        $nativeFigure = [
            't' => 'Figure',
            'c' => [
                ['native-figure', ['wp-import'], [['data-source', 'native-json']]],
                [
                    [
                        ['t' => 'Str', 'c' => 'Short'],
                        ['t' => 'Space'],
                        ['t' => 'Strong', 'c' => [
                            ['t' => 'Str', 'c' => 'figure'],
                        ]],
                    ],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Long'],
                            ['t' => 'Space'],
                            ['t' => 'Emph', 'c' => [
                                ['t' => 'Str', 'c' => 'caption'],
                            ]],
                        ]],
                    ],
                ],
                [
                    ['t' => 'Para', 'c' => [
                        ['t' => 'Image', 'c' => [
                            ['native-image', ['asset'], [['data-image', 'source']]],
                            [
                                ['t' => 'Str', 'c' => 'Alt'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'text'],
                            ],
                            ['media/figure.png', 'Figure title'],
                        ]],
                    ]],
                ],
            ],
        ];
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$nativeFigure],
        ];

        $reader = new NativeReader();
        $writer = new NativeWriter();
        $document = $reader->read(json_encode($native, JSON_THROW_ON_ERROR));
        $figure = $document->children[0];
        $image = $figure->children[0];
        $captionInlines = $figure->attr('captionInlines');
        $shortCaptionInlines = $figure->attr('shortCaptionInlines');
        $roundTrip = json_decode($writer->write($document), true, 512, JSON_THROW_ON_ERROR);

        $generatedDocument = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('figure', [
                'id' => 'generated-figure',
                'classes' => ['native-review'],
                'attributes' => ['data-source' => 'writer'],
                'caption' => 'Generated caption',
                'shortCaption' => 'Generated short',
            ], [
                new AstNode('image', [
                    'classes' => ['generated-asset'],
                    'url' => 'media/generated.png',
                    'title' => 'Generated title',
                    'alt' => 'Generated image',
                ]),
            ]),
        ]);
        $generated = json_decode($writer->write($generatedDocument), true, 512, JSON_THROW_ON_ERROR);
        $generatedRoundTrip = $reader->read(json_encode($generated, JSON_THROW_ON_ERROR));

        $t->same('figure', $figure->type);
        $t->same('native-figure', $figure->attr('id'));
        $t->same(['wp-import'], $figure->attr('classes'));
        $t->same(['data-source' => 'native-json'], $figure->attr('attributes'));
        $t->same('Long caption', $figure->attr('caption'));
        $t->same('Short figure', $figure->attr('shortCaption'));
        $t->same(['text', 'emph'], array_map(static fn (AstNode $node): string => $node->type, $captionInlines));
        $t->same(['text', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $shortCaptionInlines));
        $t->same('image', $image->type);
        $t->same('native-image', $image->attr('id'));
        $t->same('Alt text', $image->attr('alt'));
        $t->same('media/figure.png', $image->attr('url'));
        $t->same($nativeFigure, $roundTrip['blocks'][0]);
        $t->same('Figure', $generated['blocks'][0]['t']);
        $t->same(['generated-figure', ['native-review'], [['data-source', 'writer']]], $generated['blocks'][0]['c'][0]);
        $t->same('Caption', $generated['blocks'][0]['c'][1]['t']);
        $t->same('Just', $generated['blocks'][0]['c'][1]['c'][0]['t']);
        $t->same('ShortCaption', $generated['blocks'][0]['c'][1]['c'][0]['c']['t']);
        $t->same('Generated', $generated['blocks'][0]['c'][1]['c'][0]['c']['c'][0][0]['c']);
        $t->same('Generated', $generated['blocks'][0]['c'][1]['c'][1][0]['c'][0]['c']);
        $t->same('Image', $generated['blocks'][0]['c'][2][0]['c'][0]['t']);
        $t->same('figure', $generatedRoundTrip->children[0]->type);
        $t->same('generated-figure', $generatedRoundTrip->children[0]->attr('id'));
        $t->same('Generated caption', $generatedRoundTrip->children[0]->attr('caption'));
        $t->same('image', $generatedRoundTrip->children[0]->children[0]->type);
    },
    'maps native ast table captions into shared table metadata' => static function (TestRunner $t): void {
        $nativeTable = [
            't' => 'Table',
            'c' => [
                ['native-table', ['native-review'], [['data-source', 'batch-52']]],
                [
                    [
                        ['t' => 'Str', 'c' => 'Short'],
                        ['t' => 'Space'],
                        ['t' => 'Strong', 'c' => [
                            ['t' => 'Str', 'c' => 'queue'],
                        ]],
                    ],
                    [
                        ['t' => 'Para', 'c' => [
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
                    [['t' => 'AlignRight'], ['t' => 'ColWidth', 'c' => 0.25]],
                    [['t' => 'AlignLeft'], ['t' => 'ColWidthDefault']],
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
                        1,
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
                                        ['t' => 'AlignCenter'],
                                        1,
                                        1,
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

        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$nativeTable],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $table = $document->children[0];
        $body = $table->children[1];
        $captionBlocks = $table->attr('captionBlocks');
        $captionInlines = $table->attr('captionInlines');
        $shortCaptionInlines = $table->attr('shortCaptionInlines');
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $markdown = (new MarkdownWriter())->write($document);
        $latex = (new LatexWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same('table', $table->type);
        $t->same('Table', $table->attr('constructor'));
        $t->same('native-table', $table->attr('id'));
        $t->same(['native-review'], $table->attr('classes'));
        $t->same(['data-source' => 'batch-52'], $table->attr('attributes'));
        $t->same('Long caption reviewer', $table->attr('caption'));
        $t->same('Short queue', $table->attr('shortCaption'));
        $t->same(['right', 'left'], $table->attr('alignments'));
        $t->same([0.25, null], $table->attr('widths'));
        $t->same(['table_head', 'table_body'], array_map(static fn ($node): string => $node->type, $table->children));
        $t->same(1, $body->attr('rowHeadColumns'));
        $t->same('center', $body->children[0]->children[1]->attr('align'));
        $t->same(true, is_array($captionBlocks));
        $t->same('paragraph', $captionBlocks[0]->type);
        $t->same(true, is_array($captionInlines));
        $t->same(['text', 'emph', 'text', 'link'], array_map(static fn ($node): string => $node->type, $captionInlines));
        $t->same(true, is_array($shortCaptionInlines));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $shortCaptionInlines));
        $t->same($nativeTable, $roundTrip['blocks'][0]);
        $t->contains('<table id="native-table" class="native-review" data-source="batch-52">', $markdown);
        $t->contains('<caption data-pandoc-short-caption="Short queue"><p>Long <em>caption</em> <a href="https://example.test/review" title="Review">reviewer</a></p></caption>', $markdown);
        $t->contains('<tbody class="body-source">', $markdown);
        $t->contains('<th scope="row" style="text-align:right">Posts</th><td style="text-align:center">Ready</td>', $markdown);
        $t->contains('\caption[Short \textbf{queue}]{Long \emph{caption} \href{https://example.test/review}{reviewer}}\label{native-table}', $latex);
        $t->contains('<table id="native-table" class="native-review" data-source="batch-52">', $blocks);
        $t->contains('<figcaption class="wp-element-caption"><p>Long <em>caption</em> <a href="https://example.test/review" title="Review">reviewer</a></p></figcaption>', $blocks);
        $t->same('captionBlocks', $packet['captions']['long']['source'] ?? null);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
    },
    'accepts constructor wrapped native table captions for review handoff' => static function (TestRunner $t): void {
        $nativeTable = [
            't' => 'Table',
            'c' => [
                ['', ['constructor-caption'], []],
                [
                    't' => 'Caption',
                    'c' => [
                        [
                            't' => 'Just',
                            'c' => [
                                't' => 'ShortCaption',
                                'c' => [[
                                    ['t' => 'Str', 'c' => 'Queue'],
                                    ['t' => 'Space'],
                                    ['t' => 'Emph', 'c' => [
                                        ['t' => 'Str', 'c' => 'short'],
                                    ]],
                                ]],
                            ],
                        ],
                        [
                            ['t' => 'Para', 'c' => [
                                ['t' => 'Str', 'c' => 'Wrapped'],
                                ['t' => 'Space'],
                                ['t' => 'Strong', 'c' => [
                                    ['t' => 'Str', 'c' => 'caption'],
                                ]],
                            ]],
                            ['t' => 'Plain', 'c' => [
                                ['t' => 'Str', 'c' => 'Second'],
                                ['t' => 'Space'],
                                ['t' => 'Str', 'c' => 'line'],
                            ]],
                        ],
                    ],
                ],
                [
                    [['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']],
                ],
                ['t' => 'TableHead', 'c' => [
                    ['', [], []],
                    [],
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
                                        [
                                            ['t' => 'Plain', 'c' => [
                                                ['t' => 'Str', 'c' => 'Cell'],
                                            ]],
                                        ],
                                    ]],
                                ],
                            ]],
                        ],
                    ]],
                ],
                ['t' => 'TableFoot', 'c' => [
                    ['', [], []],
                    [],
                ]],
            ],
        ];
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$nativeTable],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $table = $document->children[0];
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same('table', $table->type);
        $t->same(['constructor-caption'], $table->attr('classes'));
        $t->same('Queue short', $table->attr('shortCaption'));
        $t->same('Wrapped caption' . "\n" . 'Second line', $table->attr('caption'));
        $t->same(['paragraph', 'plain'], array_map(static fn (AstNode $node): string => $node->type, $table->attr('captionBlocks')));
        $t->same([], $table->attr('captionInlines', []));
        $t->same(['text', 'emph'], array_map(static fn (AstNode $node): string => $node->type, $table->attr('shortCaptionInlines')));
        $t->same('captionBlocks', $packet['captions']['long']['source'] ?? null);
        $t->same(2, $packet['captions']['long']['blockCount'] ?? null);
        $t->same(['paragraph', 'plain'], $packet['captions']['long']['blockTypes'] ?? null);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
        $t->same(['text', 'emph'], $packet['captions']['short']['inlineTypes'] ?? null);
        $t->contains('data-pandoc-short-caption="Queue short"', $blocks);
        $t->contains('<strong>caption</strong>', $blocks);
        $t->same($nativeTable, $roundTrip['blocks'][0]);
    },
    'maps wrapped native ast short captions into shared table metadata' => static function (TestRunner $t): void {
        $nativeTable = [
            't' => 'Table',
            'c' => [
                ['', [], []],
                [
                    't' => 'Caption',
                    'c' => [
                        [
                            [
                                't' => 'ShortCaption',
                                'c' => [
                                    ['t' => 'Str', 'c' => 'Audit'],
                                    ['t' => 'Space'],
                                    ['t' => 'Code', 'c' => [
                                        ['', ['queue-code'], [['data-kind', 'short']]],
                                        'Q1',
                                    ]],
                                ],
                            ],
                        ],
                        [
                            ['t' => 'Plain', 'c' => [
                                ['t' => 'Str', 'c' => 'Long'],
                                ['t' => 'Space'],
                                ['t' => 'Strong', 'c' => [
                                    ['t' => 'Str', 'c' => 'caption'],
                                ]],
                            ]],
                        ],
                    ],
                ],
                [
                    [['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']],
                ],
                [
                    ['', [], []],
                    [],
                ],
                [
                    [
                        ['', [], []],
                        0,
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

        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$nativeTable],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $table = $document->children[0];
        $shortCaptionInlines = $table->attr('shortCaptionInlines');
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same('Long caption', $table->attr('caption'));
        $t->same('Audit Q1', $table->attr('shortCaption'));
        $t->same(true, is_array($shortCaptionInlines));
        $t->same(['text', 'code'], array_map(static fn ($node): string => $node->type, $shortCaptionInlines));
        $t->same(['queue-code'], $shortCaptionInlines[1]->attr('classes'));
        $t->same(['data-kind' => 'short'], $shortCaptionInlines[1]->attr('attributes'));
        $t->same($nativeTable, $roundTrip['blocks'][0]);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
        $t->same(['text', 'code'], $packet['captions']['short']['inlineTypes'] ?? null);
    },
    'maps tuple native ast short caption constructors into shared table metadata' => static function (TestRunner $t): void {
        $nativeTable = [
            't' => 'Table',
            'c' => [
                ['', ['native-short-caption'], []],
                [
                    [
                        't' => 'ShortCaption',
                        'c' => [[
                            ['t' => 'Str', 'c' => 'Reviewer'],
                            ['t' => 'Space'],
                            ['t' => 'Strong', 'c' => [
                                ['t' => 'Str', 'c' => 'queue'],
                            ]],
                        ]],
                    ],
                    [
                        ['t' => 'Plain', 'c' => [
                            ['t' => 'Str', 'c' => 'Detailed'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'caption'],
                        ]],
                    ],
                ],
                [[['t' => 'AlignDefault'], ['t' => 'ColWidthDefault']]],
                [
                    ['', [], []],
                    [],
                ],
                [
                    [
                        ['', [], []],
                        0,
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

        $document = (new NativeReader())->read(json_encode([
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$nativeTable],
        ], JSON_THROW_ON_ERROR));
        $table = $document->children[0];
        $shortCaptionInlines = $table->attr('shortCaptionInlines');
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $blocks = (new WordPressBlockWriter())->write($document);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same('table', $table->type);
        $t->same('Reviewer queue', $table->attr('shortCaption'));
        $t->same('Detailed caption', $table->attr('caption'));
        $t->same(true, is_array($shortCaptionInlines));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $shortCaptionInlines));
        $t->same('ShortCaption', $roundTrip['blocks'][0]['c'][1][0]['t']);
        $t->same('Reviewer', $roundTrip['blocks'][0]['c'][1][0]['c'][0][0]['c']);
        $t->contains('data-pandoc-short-caption="Reviewer queue"', $blocks);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
    },
    'writes shared table captions as pandoc native ast json' => static function (TestRunner $t): void {
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('table', [
                'id' => 'writer-table',
                'classes' => ['native-generated'],
                'attributes' => ['data-source' => 'writer'],
                'captionInlines' => [
                    new AstNode('text', ['text' => 'Generated']),
                    new AstNode('space'),
                    new AstNode('emph', [], [
                        new AstNode('text', ['text' => 'caption']),
                    ]),
                ],
                'shortCaptionInlines' => [
                    new AstNode('text', ['text' => 'Short']),
                    new AstNode('space'),
                    new AstNode('strong', [], [
                        new AstNode('text', ['text' => 'view']),
                    ]),
                ],
                'alignments' => ['left', 'right'],
                'widths' => [0.3, null],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [
                            new AstNode('text', ['text' => 'Metric']),
                        ]),
                        new AstNode('table_cell', ['align' => 'right'], [
                            new AstNode('text', ['text' => 'Value']),
                        ]),
                    ]),
                ]),
                new AstNode('table_body', ['rowHeadColumns' => 1], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [
                            new AstNode('strong', [], [
                                new AstNode('text', ['text' => 'Posts']),
                            ]),
                        ]),
                        new AstNode('table_cell', ['align' => 'center'], [
                            new AstNode('text', ['text' => 'Ready']),
                        ]),
                    ]),
                ]),
            ]),
        ]);

        $native = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $roundTrip = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $table = $roundTrip->children[0];

        $t->same('Table', $native['blocks'][0]['t']);
        $t->same(['writer-table', ['native-generated'], [['data-source', 'writer']]], $native['blocks'][0]['c'][0]);
        $t->same('Caption', $native['blocks'][0]['c'][1]['t']);
        $t->same('Just', $native['blocks'][0]['c'][1]['c'][0]['t']);
        $t->same('ShortCaption', $native['blocks'][0]['c'][1]['c'][0]['c']['t']);
        $t->same('Short', $native['blocks'][0]['c'][1]['c'][0]['c']['c'][0][0]['c']);
        $t->same('Generated', $native['blocks'][0]['c'][1]['c'][1][0]['c'][0]['c']);
        $t->same('AlignLeft', $native['blocks'][0]['c'][2][0][0]['t']);
        $t->same('ColWidth', $native['blocks'][0]['c'][2][0][1]['t']);
        $t->same(0.3, $native['blocks'][0]['c'][2][0][1]['c']);
        $nativeBody = $native['blocks'][0]['c'][4][0]['c'] ?? $native['blocks'][0]['c'][4][0];
        $nativeBodyRow = $nativeBody[3][0]['c'] ?? $nativeBody[3][0];
        $nativeSecondCell = $nativeBodyRow[1][1]['c'] ?? $nativeBodyRow[1][1];
        $t->same(['t' => 'RowHeadColumns', 'c' => 1], $nativeBody[1]);
        $t->same(['t' => 'RowSpan', 'c' => 1], $nativeSecondCell[2]);
        $t->same(['t' => 'ColSpan', 'c' => 1], $nativeSecondCell[3]);
        $t->same('table', $table->type);
        $t->same('Generated caption', $table->attr('caption'));
        $t->same('Short view', $table->attr('shortCaption'));
        $t->same(['left', 'right'], $table->attr('alignments'));
        $t->same([0.3, null], $table->attr('widths'));
        $t->same('center', $table->children[1]->children[0]->children[1]->attr('align'));
    },
    'writes shared ast table captions as native ast table constructors' => static function (TestRunner $t): void {
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [
            new AstNode('table', [
                'id' => 'native-writer-table',
                'classes' => ['wp-import-table'],
                'attributes' => ['data-source' => 'shared-ast'],
                'captionBlocks' => [
                    new AstNode('paragraph', [], [
                        new AstNode('text', ['text' => 'Shared']),
                        new AstNode('space'),
                        new AstNode('emph', [], [new AstNode('text', ['text' => 'caption'])]),
                        new AstNode('space'),
                        new AstNode('link', [
                            'url' => 'https://example.test/native',
                            'title' => 'Native review',
                        ], [new AstNode('text', ['text' => 'handoff'])]),
                    ]),
                ],
                'shortCaptionInlines' => [
                    new AstNode('text', ['text' => 'Review']),
                    new AstNode('space'),
                    new AstNode('strong', [], [new AstNode('text', ['text' => 'slice'])]),
                ],
                'alignments' => ['left', 'right'],
                'widths' => [0.33, null],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Field'])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'State'])]),
                    ]),
                ]),
                new AstNode('table_body', ['rowHeadColumns' => 1], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['text' => 'Posts']),
                        new AstNode('table_cell', ['align' => 'right', 'text' => 'Ready']),
                    ]),
                ]),
                new AstNode('table_foot', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', ['colspan' => 2], [new AstNode('text', ['text' => 'Reviewed'])]),
                    ]),
                ]),
            ]),
        ]);

        $native = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $tableBlock = $native['blocks'][0];
        $roundTrip = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $table = $roundTrip->children[0];
        $blocks = (new WordPressBlockWriter())->write($roundTrip);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same('Table', $tableBlock['t']);
        $t->same(['native-writer-table', ['wp-import-table'], [['data-source', 'shared-ast']]], $tableBlock['c'][0]);
        $t->same('Caption', $tableBlock['c'][1]['t']);
        $t->same('Just', $tableBlock['c'][1]['c'][0]['t']);
        $t->same('ShortCaption', $tableBlock['c'][1]['c'][0]['c']['t']);
        $t->same('Review', $tableBlock['c'][1]['c'][0]['c']['c'][0][0]['c']);
        $t->same('Strong', $tableBlock['c'][1]['c'][0]['c']['c'][0][2]['t']);
        $t->same('Shared', $tableBlock['c'][1]['c'][1][0]['c'][0]['c']);
        $t->same('Emph', $tableBlock['c'][1]['c'][1][0]['c'][2]['t']);
        $t->same('Link', $tableBlock['c'][1]['c'][1][0]['c'][4]['t']);
        $t->same('AlignLeft', $tableBlock['c'][2][0][0]['t']);
        $t->same('ColWidth', $tableBlock['c'][2][0][1]['t']);
        $t->same(0.33, $tableBlock['c'][2][0][1]['c']);
        $t->same('AlignRight', $tableBlock['c'][2][1][0]['t']);
        $t->same('ColWidthDefault', $tableBlock['c'][2][1][1]['t']);
        $tableBody = $tableBlock['c'][4][0]['c'] ?? $tableBlock['c'][4][0];
        $tableBodyRow = $tableBody[3][0]['c'] ?? $tableBody[3][0];
        $tableSecondCell = $tableBodyRow[1][1]['c'] ?? $tableBodyRow[1][1];
        $tableFoot = $tableBlock['c'][5]['c'] ?? $tableBlock['c'][5];
        $tableFootRow = $tableFoot[1][0]['c'] ?? $tableFoot[1][0];
        $tableFootCell = $tableFootRow[1][0]['c'] ?? $tableFootRow[1][0];
        $t->same(['t' => 'RowHeadColumns', 'c' => 1], $tableBody[1]);
        $t->same('Ready', $tableSecondCell[4][0]['c'][0]['c']);
        $t->same('AlignRight', $tableSecondCell[1]['t']);
        $t->same(['t' => 'RowSpan', 'c' => 1], $tableSecondCell[2]);
        $t->same(['t' => 'ColSpan', 'c' => 1], $tableSecondCell[3]);
        $t->same(['t' => 'ColSpan', 'c' => 2], $tableFootCell[3]);
        $t->same('Shared caption handoff', $table->attr('caption'));
        $t->same('Review slice', $table->attr('shortCaption'));
        $t->same(['table_head', 'table_body', 'table_foot'], array_map(static fn (AstNode $node): string => $node->type, $table->children));
        $t->same('Ready', $table->children[1]->children[0]->children[1]->attr('text'));
        $t->contains('<figure class="wp-block-table" data-pandoc-short-caption="Review slice">', $blocks);
        $t->contains('<table id="native-writer-table" class="wp-import-table" data-source="shared-ast">', $blocks);
        $t->contains('<figcaption class="wp-element-caption"><p>Shared <em>caption</em> <a href="https://example.test/native" title="Native review">handoff</a></p></figcaption>', $blocks);
        $t->same('captionBlocks', $packet['captions']['long']['source'] ?? null);
        $t->same('shortCaptionInlines', $packet['captions']['short']['source'] ?? null);
    },
    'writes shared table short caption blocks as pandoc native ast inlines' => static function (TestRunner $t): void {
        $sourceTable = new AstNode('table', [
            'captionBlocks' => [
                new AstNode('paragraph', [], [
                    new AstNode('text', ['text' => 'Block']),
                    new AstNode('space'),
                    new AstNode('strong', [], [
                        new AstNode('text', ['text' => 'long']),
                    ]),
                    new AstNode('space'),
                    new AstNode('text', ['text' => 'caption']),
                ]),
            ],
            'shortCaptionBlocks' => [
                new AstNode('plain', [], [
                    new AstNode('text', ['text' => 'Queue']),
                    new AstNode('space'),
                    new AstNode('emph', [], [
                        new AstNode('text', ['text' => 'short']),
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
        $document = new AstNode('document', ['pandocApiVersion' => [1, 23, 1], 'meta' => []], [$sourceTable]);

        $sourcePacket = TableGeometry::reviewPacket($sourceTable, ['accessibility' => false]);
        $markdown = (new MarkdownWriter())->write($document);
        $latex = (new LatexWriter())->write($document);
        $native = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $roundTrip = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $table = $roundTrip->children[0];
        $roundTripPacket = TableGeometry::reviewPacket($table, ['accessibility' => false]);

        $t->same('shortCaptionBlocks', $sourcePacket['captions']['short']['source'] ?? null);
        $t->contains(': [Queue *short*] Block **long** caption', $markdown);
        $t->contains('\caption[Queue \emph{short}]{Block \textbf{long} caption}\\\\', $latex);
        $t->same('Caption', $native['blocks'][0]['c'][1]['t']);
        $t->same('Just', $native['blocks'][0]['c'][1]['c'][0]['t']);
        $t->same('ShortCaption', $native['blocks'][0]['c'][1]['c'][0]['c']['t']);
        $t->same('Queue', $native['blocks'][0]['c'][1]['c'][0]['c']['c'][0][0]['c']);
        $t->same('Space', $native['blocks'][0]['c'][1]['c'][0]['c']['c'][0][1]['t']);
        $t->same('Emph', $native['blocks'][0]['c'][1]['c'][0]['c']['c'][0][2]['t']);
        $t->same('Para', $native['blocks'][0]['c'][1]['c'][1][0]['t']);
        $t->same('Block long caption', $table->attr('caption'));
        $t->same('Queue short', $table->attr('shortCaption'));
        $t->same('shortCaptionInlines', $roundTripPacket['captions']['short']['source'] ?? null);
        $t->same(['text', 'emph'], $roundTripPacket['captions']['short']['inlineTypes'] ?? null);
    },
    'maps native inline command constructors into latex writer output' => static function (TestRunner $t): void {
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                [
                    't' => 'Para',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Review'],
                        ['t' => 'Space'],
                        ['t' => 'Underline', 'c' => [
                            ['t' => 'Str', 'c' => 'required'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Strikeout', 'c' => [
                            ['t' => 'Str', 'c' => 'stale'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Superscript', 'c' => [
                            ['t' => 'Str', 'c' => '2'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Subscript', 'c' => [
                            ['t' => 'Str', 'c' => 'n'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'SmallCaps', 'c' => [
                            ['t' => 'Str', 'c' => 'caps'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Quoted', 'c' => [
                            ['t' => 'SingleQuote'],
                            [
                                ['t' => 'Str', 'c' => 'quoted'],
                            ],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'RawInline', 'c' => ['latex', '\\LaTeX{}']],
                        ['t' => 'Space'],
                        ['t' => 'Math', 'c' => [
                            ['t' => 'InlineMath'],
                            'x^2',
                        ]],
                    ],
                ],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $paragraph = $document->children[0];

        $t->same([
            'text',
            'underline',
            'text',
            'strikeout',
            'text',
            'superscript',
            'text',
            'subscript',
            'text',
            'small_caps',
            'text',
            'quoted',
            'text',
            'raw_tex_inline',
            'text',
            'math',
        ], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same(
            'Review \underline{required} \sout{stale} \textsuperscript{2} \textsubscript{n} \textsc{caps} `quoted\' \LaTeX{} $x^2$',
            (new LatexWriter())->write($document)
        );
    },
];
