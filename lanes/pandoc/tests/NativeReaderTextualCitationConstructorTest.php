<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'maps textual native cite constructors into json native citation nodes' => static function (TestRunner $t): void {
        $nativeText = <<<'NATIVE'
[ Para
  [ Str "Review"
  , Space
  , Cite
      [ Citation { citationId = "smith1899"
                 , citationPrefix = [ Str "see" ]
                 , citationSuffix = [ Str "p." , Space , Str "7" ]
                 , citationMode = NormalCitation
                 , citationNoteNum = 3
                 , citationHash = 1899
                 }
      , Citation { citationId = "doe1901"
                 , citationPrefix = []
                 , citationSuffix = []
                 , citationMode = AuthorInText
                 , citationNoteNum = 4
                 , citationHash = 1901
                 }
      ]
      [ Str "[see" , Space , Str "@smith1899," , Space , Str "p." , Space , Str "7;" , Space , Str "@doe1901]" ]
  , Space
  , Cite
      [ Citation { citationId = "anonymous2026"
                 , citationPrefix = []
                 , citationSuffix = [ Str "appendix" ]
                 , citationMode = SuppressAuthor
                 , citationNoteNum = 5
                 , citationHash = 2026
                 }
      ]
      [ Str "-@anonymous2026," , Space , Str "appendix" ]
  ]
]
NATIVE;

        $nativeDocument = (new NativeReader())->read($nativeText);
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $paragraph = $document->children[0];
        $cluster = $paragraph->children[2];
        $singleCitation = $paragraph->children[4];
        $jsonPacket = (new PandocJsonWriter())->toArray($document);
        $nativePacket = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $roundTrip = (new PandocJsonReader())->readPacket($jsonPacket);
        $expectedClusterRecordsNative = [
            ['t' => 'Citation', 'c' => [
                'citationId' => 'smith1899',
                'citationPrefix' => [['t' => 'Str', 'c' => 'see']],
                'citationSuffix' => [
                    ['t' => 'Str', 'c' => 'p.'],
                    ['t' => 'Space'],
                    ['t' => 'Str', 'c' => '7'],
                ],
                'citationMode' => ['t' => 'NormalCitation'],
                'citationNoteNum' => 3,
                'citationHash' => 1899,
            ]],
            ['t' => 'Citation', 'c' => [
                'citationId' => 'doe1901',
                'citationPrefix' => [],
                'citationSuffix' => [],
                'citationMode' => ['t' => 'AuthorInText'],
                'citationNoteNum' => 4,
                'citationHash' => 1901,
            ]],
        ];
        $expectedClusterSourceNative = [
            ['t' => 'Str', 'c' => '[see'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '@smith1899,'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'p.'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '7;'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '@doe1901]'],
        ];
        $expectedSingleRecordNative = ['t' => 'Citation', 'c' => [
            'citationId' => 'anonymous2026',
            'citationPrefix' => [],
            'citationSuffix' => [['t' => 'Str', 'c' => 'appendix']],
            'citationMode' => ['t' => 'SuppressAuthor'],
            'citationNoteNum' => 5,
            'citationHash' => 2026,
        ]];
        $expectedSingleSourceNative = [
            ['t' => 'Str', 'c' => '-@anonymous2026,'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'appendix'],
        ];

        $t->same('citation_group', $cluster->type);
        $t->same('[see @smith1899, p. 7; @doe1901]', $cluster->attr('text'));
        $t->same('Cite', $cluster->attr('constructor'));
        $t->same(['t' => 'Cite', 'c' => [$expectedClusterRecordsNative, $expectedClusterSourceNative]], $cluster->attr('native'));
        $t->same($expectedClusterRecordsNative, $cluster->attr('citationRecordsNative'));
        $t->same(['smith1899', 'doe1901'], array_map(static fn (AstNode $node): string => $node->attr('id'), $cluster->children));
        $t->same('Citation', $cluster->children[0]->attr('citationConstructor'));
        $t->same($expectedClusterRecordsNative[0], $cluster->children[0]->attr('citationNative'));
        $t->same($expectedClusterRecordsNative[0]['c']['citationPrefix'], $cluster->children[0]->attr('citationPrefixNative'));
        $t->same($expectedClusterRecordsNative[0]['c']['citationSuffix'], $cluster->children[0]->attr('citationSuffixNative'));
        $t->same('see', $cluster->children[0]->attr('prefix')[0]->attr('text'));
        $t->same('p. 7', $cluster->children[0]->attr('suffix')[0]->attr('text') . ' ' . $cluster->children[0]->attr('suffix')[2]->attr('text'));
        $t->same('author_in_text', $cluster->children[1]->attr('mode'));
        $t->same(3, $cluster->children[0]->attr('citationNoteNum'));
        $t->same(1901, $cluster->children[1]->attr('citationHash'));
        $t->same('citation', $singleCitation->type);
        $t->same('Cite', $singleCitation->attr('constructor'));
        $t->same(['t' => 'Cite', 'c' => [[$expectedSingleRecordNative], $expectedSingleSourceNative]], $singleCitation->attr('native'));
        $t->same([$expectedSingleRecordNative], $singleCitation->attr('citationRecordsNative'));
        $t->same($expectedSingleRecordNative, $singleCitation->attr('citationNative'));
        $t->same('anonymous2026', $singleCitation->attr('id'));
        $t->same('suppress_author', $singleCitation->attr('mode'));
        $t->same('-@anonymous2026, appendix', $singleCitation->attr('text'));

        foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $writer => $packet) {
            $clusterCite = $packet['blocks'][0]['c'][2];
            $singleCite = $packet['blocks'][0]['c'][4];
            $firstClusterRecord = $clusterCite['c'][0][0]['c'];
            $secondClusterRecord = $clusterCite['c'][0][1]['c'];
            $singleRecord = $singleCite['c'][0][0]['c'];

            $t->same('Cite', $clusterCite['t'], "{$writer} emits group Cite constructor");
            $t->same($expectedClusterRecordsNative[0], $clusterCite['c'][0][0], "{$writer} keeps first tagged Citation payload");
            $t->same($expectedClusterRecordsNative[1], $clusterCite['c'][0][1], "{$writer} keeps second tagged Citation payload");
            $t->same('NormalCitation', $firstClusterRecord['citationMode']['t'], "{$writer} keeps normal citation mode");
            $t->same('AuthorInText', $secondClusterRecord['citationMode']['t'], "{$writer} keeps author-in-text mode");
            $t->same(3, $firstClusterRecord['citationNoteNum'], "{$writer} keeps first note number");
            $t->same(1901, $secondClusterRecord['citationHash'], "{$writer} keeps second citation hash");
            $t->same('[see', $clusterCite['c'][1][0]['c'], "{$writer} keeps group display inlines");
            $t->same('Cite', $singleCite['t'], "{$writer} emits single Cite constructor");
            $t->same($expectedSingleRecordNative, $singleCite['c'][0][0], "{$writer} keeps single tagged Citation payload");
            $t->same('SuppressAuthor', $singleRecord['citationMode']['t'], "{$writer} keeps suppress-author mode");
            $t->same('appendix', $singleRecord['citationSuffix'][0]['c'], "{$writer} keeps single suffix");
        }

        $editedFirstCitation = new AstNode('citation', array_replace($cluster->children[0]->attrs, [
            'citationHash' => 1999,
            'hash' => 1999,
        ]), $cluster->children[0]->children);
        $editedDocument = new AstNode('document', $document->attrs, [
            new AstNode('paragraph', $paragraph->attrs, [
                $paragraph->children[0],
                $paragraph->children[1],
                new AstNode('citation_group', $cluster->attrs, [
                    $editedFirstCitation,
                    $cluster->children[1],
                ]),
            ]),
        ]);
        $editedRecord = (new PandocJsonWriter())->toArray($editedDocument)['blocks'][0]['c'][2]['c'][0][0];
        $t->same(false, array_key_exists('t', $editedRecord), 'edited citation regenerates stale tagged record');
        $t->same(1999, $editedRecord['citationHash'], 'edited citation writes regenerated hash');

        $t->same('citation_group', $roundTrip->children[0]->children[2]->type);
        $t->same('citation', $roundTrip->children[0]->children[4]->type);
        $t->same('anonymous2026', $roundTrip->children[0]->children[4]->attr('id'));
    },
    'rejects malformed textual native cite constructors before writer handoff' => static function (TestRunner $t): void {
        $reader = new NativeReader();

        $t->throws(\InvalidArgumentException::class, static fn (): AstNode => $reader->read('[ Para [ Cite [] [ Str "@missing" ] ] ]'));
        $t->throws(\InvalidArgumentException::class, static fn (): AstNode => $reader->read('[ Para [ Cite [ Citation { citationId = "", citationPrefix = [], citationSuffix = [], citationMode = NormalCitation, citationNoteNum = 0, citationHash = 0 } ] [ Str "@missing" ] ] ]'));
    },
];
