<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html image-map issue-code rollups for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>'
                . '<img id="resolved" src="diagram.png" alt="Diagram" usemap="#figures">'
                . '<img id="missing" src="missing.png" alt="Missing" usemap="#missing">'
                . '<img id="duplicate" src="duplicate.png" alt="Duplicate" usemap="#dupe">'
                . '<img id="invalid" src="invalid.png" alt="Invalid" usemap="bad target">'
                . '</p>'
                . '<map name="figures">'
                . '<area shape="rect" coords="0,0,10,10" href="diagram.png#rect" alt="Rectangle">'
                . '<area shape="circle" coords="5,5,0" href="diagram.png#circle" alt="Bad circle">'
                . '<area shape="default" coords="99,99" href="diagram.png#default" alt="Default">'
                . '<area shape="rect" coords="1,1,3,4" href="diagram.png#after-default" alt="After default">'
                . '</map>'
                . '<map name="dupe"><area href="dupe-one.html" alt="Dup one"></map>'
                . '<map name="dupe"><area href="dupe-two.html" alt="Dup two"></map>'
                . '<map name="bad target"><area href="bad.html" alt="Bad"></map>'
                . '<map name="unused"><area href="unused.html" alt="Unused"></map>',
            'image map issue-code rollup review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/image-map-issue-code-rollup-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $resolvedImage = $summary[0]['children'][0];
        $missingImage = $summary[0]['children'][1];
        $duplicateImage = $summary[0]['children'][2];
        $invalidImage = $summary[0]['children'][3];
        $map = $summary[1];
        $circleArea = $map['areas'][1];
        $defaultArea = $map['areas'][2];
        $afterDefaultArea = $map['areas'][3];
        $firstDuplicateMap = $summary[2];
        $secondDuplicateMap = $summary[3];
        $invalidMap = $summary[4];
        $unusedMap = $summary[5];

        $t->same('resolved', $resolvedImage['useMapAssociationState']);
        $t->same([], $resolvedImage['useMapIssueCodes']);
        $t->same([], $resolvedImage['useMapIssues']);
        $t->same('missing-map', $missingImage['useMapAssociationState']);
        $t->same(['missing-image-map'], $missingImage['useMapIssueCodes']);
        $t->same([['code' => 'missing-image-map', 'mapName' => 'missing']], $missingImage['useMapIssues']);
        $t->same('duplicate-map-name', $duplicateImage['useMapAssociationState']);
        $t->same(['duplicate-map-name'], $duplicateImage['useMapIssueCodes']);
        $t->same([['code' => 'duplicate-map-name', 'mapName' => 'dupe', 'count' => 2]], $duplicateImage['useMapIssues']);
        $t->same('invalid-reference', $invalidImage['useMapAssociationState']);
        $t->same(['invalid-usemap-reference'], $invalidImage['useMapIssueCodes']);
        $t->same([['code' => 'invalid-usemap-reference', 'useMapRaw' => 'bad target']], $invalidImage['useMapIssues']);

        $t->same('referenced', $map['imageMapAssociationState']);
        $t->same([], $map['imageMapIssueCodes']);
        $t->same([], $map['imageMapIssues']);
        $t->same(3, $map['areaGeometryIssueCount']);
        $t->same([
            'invalid-circle-area-radius',
            'default-area-coords-ignored',
            'default-area-precedes-specific-area',
        ], $map['areaGeometryIssueCodes']);
        $t->same(false, $circleArea['areaGeometryValid']);
        $t->same([['code' => 'invalid-circle-area-radius', 'radius' => 0.0]], $circleArea['areaGeometryIssues']);
        $t->same(true, $defaultArea['areaGeometryValid']);
        $t->same([['code' => 'default-area-coords-ignored']], $defaultArea['areaGeometryIssues']);
        $t->same(true, $afterDefaultArea['areaGeometryValid']);
        $t->same([
            'code' => 'default-area-precedes-specific-area',
            'defaultAreaIndex' => 2,
            'coveredAreaIndexes' => [3],
        ], $map['defaultAreaPrecedenceIssue']);

        $t->same('duplicate-map-name', $firstDuplicateMap['imageMapAssociationState']);
        $t->same(['duplicate-map-name'], $firstDuplicateMap['imageMapIssueCodes']);
        $t->same([['code' => 'duplicate-map-name', 'mapName' => 'dupe', 'count' => 2]], $firstDuplicateMap['imageMapIssues']);
        $t->same('duplicate-map-name', $secondDuplicateMap['imageMapAssociationState']);
        $t->same(['duplicate-map-name'], $secondDuplicateMap['imageMapIssueCodes']);
        $t->same('invalid-map-name', $invalidMap['imageMapAssociationState']);
        $t->same(['invalid-map-name'], $invalidMap['imageMapIssueCodes']);
        $t->same([['code' => 'invalid-map-name', 'mapNameRaw' => 'bad target']], $invalidMap['imageMapIssues']);
        $t->same('unreferenced', $unusedMap['imageMapAssociationState']);
        $t->same(['unreferenced-image-map'], $unusedMap['imageMapIssueCodes']);
        $t->same([['code' => 'unreferenced-image-map', 'mapName' => 'unused']], $unusedMap['imageMapIssues']);

        $t->contains('<img alt="Diagram" id="resolved" src="diagram.png" usemap="#figures">', $html);
        $t->contains('<map name="figures">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/image-map-issue-code-rollup-review.html', $document->children[0]->attr('part'));
    },
];
