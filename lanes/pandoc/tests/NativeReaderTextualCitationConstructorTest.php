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

        $firstPrefixNative = [['t' => 'Str', 'c' => 'see']];
        $firstSuffixNative = [
            ['t' => 'Str', 'c' => 'p.'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => '7'],
        ];
        $firstRecordNative = [
            'citationId' => 'smith1899',
            'citationPrefix' => $firstPrefixNative,
            'citationSuffix' => $firstSuffixNative,
            'citationMode' => ['t' => 'NormalCitation'],
            'citationNoteNum' => 3,
            'citationHash' => 1899,
        ];
        $secondRecordNative = [
            'citationId' => 'doe1901',
            'citationPrefix' => [],
            'citationSuffix' => [],
            'citationMode' => ['t' => 'AuthorInText'],
            'citationNoteNum' => 4,
            'citationHash' => 1901,
        ];
        $singleSuffixNative = [['t' => 'Str', 'c' => 'appendix']];
        $singleRecordNative = [
            'citationId' => 'anonymous2026',
            'citationPrefix' => [],
            'citationSuffix' => $singleSuffixNative,
            'citationMode' => ['t' => 'SuppressAuthor'],
            'citationNoteNum' => 5,
            'citationHash' => 2026,
        ];
        $clusterDisplayNative = [
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
        $singleDisplayNative = [
            ['t' => 'Str', 'c' => '-@anonymous2026,'],
            ['t' => 'Space'],
            ['t' => 'Str', 'c' => 'appendix'],
        ];
        $clusterNative = ['t' => 'Cite', 'c' => [[$firstRecordNative, $secondRecordNative], $clusterDisplayNative]];
        $singleNative = ['t' => 'Cite', 'c' => [[$singleRecordNative], $singleDisplayNative]];

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
        $jsonNativeRoundTrip = (new NativeReader())->read(json_encode($jsonPacket, JSON_THROW_ON_ERROR));

        $t->same('citation_group', $cluster->type);
        $t->same('[see @smith1899, p. 7; @doe1901]', $cluster->attr('text'));
        $t->same('Cite', $cluster->attr('constructor'));
        $t->same($clusterNative, $cluster->attr('native'));
        $t->same([$firstRecordNative, $secondRecordNative], $cluster->attr('citationRecordsNative'));
        $t->same(['smith1899', 'doe1901'], array_map(static fn (AstNode $node): string => $node->attr('id'), $cluster->children));
        $t->same('Citation', $cluster->children[0]->attr('citationConstructor'));
        $t->same($firstRecordNative, $cluster->children[0]->attr('citationNative'));
        $t->same($secondRecordNative, $cluster->children[1]->attr('citationNative'));
        $t->same($firstPrefixNative, $cluster->children[0]->attr('citationPrefixNative'));
        $t->same($firstSuffixNative, $cluster->children[0]->attr('citationSuffixNative'));
        $t->same('see', $cluster->children[0]->attr('prefix')[0]->attr('text'));
        $t->same('p. 7', $cluster->children[0]->attr('suffix')[0]->attr('text') . ' ' . $cluster->children[0]->attr('suffix')[2]->attr('text'));
        $t->same('author_in_text', $cluster->children[1]->attr('mode'));
        $t->same(3, $cluster->children[0]->attr('citationNoteNum'));
        $t->same(1901, $cluster->children[1]->attr('citationHash'));
        $t->same('citation', $singleCitation->type);
        $t->same('Cite', $singleCitation->attr('constructor'));
        $t->same($singleNative, $singleCitation->attr('native'));
        $t->same([$singleRecordNative], $singleCitation->attr('citationRecordsNative'));
        $t->same('Citation', $singleCitation->attr('citationConstructor'));
        $t->same($singleRecordNative, $singleCitation->attr('citationNative'));
        $t->same($singleSuffixNative, $singleCitation->attr('citationSuffixNative'));
        $t->same('anonymous2026', $singleCitation->attr('id'));
        $t->same('suppress_author', $singleCitation->attr('mode'));
        $t->same('-@anonymous2026, appendix', $singleCitation->attr('text'));

        foreach (['json' => $jsonPacket, 'native' => $nativePacket] as $writer => $packet) {
            $clusterCite = $packet['blocks'][0]['c'][2];
            $singleCite = $packet['blocks'][0]['c'][4];

            $t->same($clusterNative, $clusterCite, "{$writer} emits group Cite sidecar");
            $t->same($singleNative, $singleCite, "{$writer} emits single Cite sidecar");
            $t->same('NormalCitation', $clusterCite['c'][0][0]['citationMode']['t'], "{$writer} keeps normal citation mode");
            $t->same('AuthorInText', $clusterCite['c'][0][1]['citationMode']['t'], "{$writer} keeps author-in-text mode");
            $t->same(3, $clusterCite['c'][0][0]['citationNoteNum'], "{$writer} keeps first note number");
            $t->same(1901, $clusterCite['c'][0][1]['citationHash'], "{$writer} keeps second citation hash");
            $t->same('[see', $clusterCite['c'][1][0]['c'], "{$writer} keeps group display inlines");
            $t->same('SuppressAuthor', $singleCite['c'][0][0]['citationMode']['t'], "{$writer} keeps suppress-author mode");
            $t->same('appendix', $singleCite['c'][0][0]['citationSuffix'][0]['c'], "{$writer} keeps single suffix");
        }

        $t->same('citation_group', $roundTrip->children[0]->children[2]->type);
        $t->same('citation', $roundTrip->children[0]->children[4]->type);
        $t->same('anonymous2026', $roundTrip->children[0]->children[4]->attr('id'));

        $jsonNativeCites = array_values(array_filter(
            $jsonNativeRoundTrip->children[0]->children,
            static fn (AstNode $node): bool => in_array($node->type, ['citation_group', 'citation'], true)
        ));

        $t->same('citation_group', $jsonNativeCites[0]->type);
        $t->same('citation', $jsonNativeCites[1]->type);
        $t->same($clusterNative, $jsonNativeCites[0]->attr('native'));
        $t->same($singleNative, $jsonNativeCites[1]->attr('native'));
    },
    'rejects malformed textual native cite constructors before writer handoff' => static function (TestRunner $t): void {
        $reader = new NativeReader();

        $t->throws(\InvalidArgumentException::class, static fn (): AstNode => $reader->read('[ Para [ Cite [] [ Str "@missing" ] ] ]'));
        $t->throws(\InvalidArgumentException::class, static fn (): AstNode => $reader->read('[ Para [ Cite [ Citation { citationId = "", citationPrefix = [], citationSuffix = [], citationMode = NormalCitation, citationNoteNum = 0, citationHash = 0 } ] [ Str "@missing" ] ] ]'));
    },
];
