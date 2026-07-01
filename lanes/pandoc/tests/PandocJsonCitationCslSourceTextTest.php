<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'keeps pandoc json cite source text stable for missing prefixed suppress-author csl fallback' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [[
                't' => 'Para',
                'c' => [
                    ['t' => 'Str', 'c' => 'Archive'],
                    ['t' => 'Space'],
                    ['t' => 'Cite', 'c' => [
                        [
                            [
                                'citationId' => 'smith1899',
                                'citationPrefix' => [['t' => 'Str', 'c' => 'see']],
                                'citationSuffix' => [],
                                'citationMode' => ['t' => 'NormalCitation'],
                                'citationNoteNum' => 0,
                                'citationHash' => 1889,
                            ],
                            [
                                'citationId' => 'missing-source',
                                'citationPrefix' => [['t' => 'Str', 'c' => 'compare']],
                                'citationSuffix' => [],
                                'citationMode' => ['t' => 'SuppressAuthor'],
                                'citationNoteNum' => 0,
                                'citationHash' => 0,
                            ],
                        ],
                        [
                            ['t' => 'Str', 'c' => '[see'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '@smith1899;'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'compare'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => '-@missing-source]'],
                        ],
                    ]],
                    ['t' => 'Str', 'c' => '.'],
                ],
            ]],
        ];

        $reader = new PandocJsonReader();
        $document = $reader->readPacket($packet);
        $cluster = $document->children[0]->children[2];
        $processor = CitationCslProcessor::fromItems([[
            'id' => 'smith1899',
            'type' => 'book',
            'title' => 'Migration Patterns',
            'author' => [['family' => 'Smith', 'given' => 'Ada']],
            'issued' => ['date-parts' => [[1899]]],
        ]]);

        $t->same('citation_group', $cluster->type);
        $t->same('compare -@missing-source', $cluster->children[1]->attr('text'));
        $t->same('(see Smith 1899; compare -@missing-source)', $processor->renderCitationCluster($cluster->children));

        $processed = $processor->apply($document);
        $processedCluster = $processed->children[0]->children[2];
        $t->same('(see Smith 1899; compare -@missing-source)', $processedCluster->attr('rendered'));
        $t->true(!str_contains((string) $processedCluster->attr('rendered'), 'compare compare'));
        $t->same(['missing-source'], $processedCluster->attr('missingCslItems'));

        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Archive (see Smith 1899; compare -@missing-source).</p>', $blocks);
        $t->true(!str_contains($blocks, 'compare compare'));

        $encoded = (new PandocJsonWriter())->toArray($document);
        $roundTrip = $reader->readPacket($encoded);
        $roundTripCluster = $roundTrip->children[0]->children[2];
        $t->same('SuppressAuthor', $encoded['blocks'][0]['c'][2]['c'][0][1]['citationMode']['t']);
        $t->same('compare', $encoded['blocks'][0]['c'][2]['c'][0][1]['citationPrefix'][0]['c']);
        $t->same('compare -@missing-source', $roundTripCluster->children[1]->attr('text'));

        $manualMissing = new AstNode('citation', [
            'id' => 'manual-missing',
            'text' => '@manual-missing',
            'prefix' => 'see',
        ], [
            new AstNode('text', ['text' => '@manual-missing']),
        ]);
        $t->same('(see @manual-missing)', $processor->renderCitationCluster([$manualMissing]));
    },
];
