<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves textual native cite constructor sidecars' => static function (TestRunner $t): void {
        $nativeText = <<<'NATIVE'
[ Para
  [ Cite
      [ Citation { citationId = "smith1899" , citationPrefix = [ Str "see" ] , citationSuffix = [ Str "p." , Space , Str "7" ] , citationMode = NormalCitation , citationNoteNum = 3 , citationHash = 1899 }
      , Citation { citationId = "doe1901" , citationPrefix = [] , citationSuffix = [] , citationMode = AuthorInText , citationNoteNum = 4 , citationHash = 1901 }
      ]
      [ Str "[see" , Space , Str "@smith1899," , Space , Str "p." , Space , Str "7;" , Space , Str "@doe1901]" ]
  , Space
  , Cite
      [ Citation { citationId = "anonymous2026" , citationPrefix = [] , citationSuffix = [ Str "appendix" ] , citationMode = SuppressAuthor , citationNoteNum = 5 , citationHash = 2026 } ]
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

        $document = (new NativeReader())->read($nativeText);
        $paragraph = $document->children[0];
        $cluster = $paragraph->children[0];
        $singleCitation = $paragraph->children[2];
        $json = (new PandocJsonWriter())->toArray($document);
        $nativeJson = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $jsonRoundTrip = (new NativeReader())->read(json_encode($json, JSON_THROW_ON_ERROR));

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

        $t->same($clusterNative, $json['blocks'][0]['c'][0]);
        $t->same($singleNative, $json['blocks'][0]['c'][2]);
        $t->same($clusterNative, $nativeJson['blocks'][0]['c'][0]);
        $t->same($singleNative, $nativeJson['blocks'][0]['c'][2]);
        $t->same($clusterNative, $jsonRoundTrip->children[0]->children[0]->attr('native'));
        $t->same($singleNative, $jsonRoundTrip->children[0]->children[2]->attr('native'));
    },
];
