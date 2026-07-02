<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html client image map association issue codes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><img id="resolved" src="diagram.png" alt="Diagram" usemap="#figures">'
                . '<img id="missing" src="missing.png" alt="Missing" usemap="#missing">'
                . '<img id="duplicate" src="duplicate.png" alt="Duplicate" usemap="#dupe">'
                . '<img id="invalid" src="invalid.png" alt="Invalid" usemap="bad target"></p>'
                . '<map name="figures">'
                . '<area shape="rect" coords="0,0,10,10" href="/rect" alt="Rectangle">'
                . '<area shape="default" href="/default" alt="Default">'
                . '<area shape="circle" coords="4,4,0" href="/circle" alt="Bad circle">'
                . '</map>'
                . '<map name="dupe"><area href="/one" alt="One"></map>'
                . '<map name="dupe"><area href="/two" alt="Two"></map>'
                . '<map name="unused"><area shape="poly" coords="0,0,1,1" href="/unused" alt="Unused"></map>'
                . '<map name="bad target"><area href="/bad" alt="Bad"></map>',
            'client image map association review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/image-map-association-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $resolved = $summary[0]['children'][0];
        $missing = $summary[0]['children'][1];
        $duplicate = $summary[0]['children'][2];
        $invalid = $summary[0]['children'][3];
        $figures = $summary[1];
        $firstDuplicateMap = $summary[2];
        $unused = $summary[4];
        $invalidMap = $summary[5];

        $t->same('html-image-usemap-association-review', $resolved['useMapAssociationReviewPolicy']);
        $t->same('resolved', $resolved['useMapAssociationState']);
        $t->same(1, $resolved['useMapTargetCount']);
        $t->same(3, $resolved['useMapAreaCount']);
        $t->same(['/rect', '/default', '/circle'], $resolved['useMapAreaHrefs']);
        $t->same(['Rectangle', 'Default', 'Bad circle'], $resolved['useMapAreaLabels']);
        $t->same([], $resolved['useMapIssues']);
        $t->same([], $resolved['useMapIssueCodes']);
        $t->same(0, $resolved['useMapIssueCount']);
        $t->same(true, $resolved['useMapAssociationValid']);
        $t->same(true, $resolved['useMapReviewOnlyNoBrowserHitTesting']);

        $t->same('missing-map', $missing['useMapAssociationState']);
        $t->same(0, $missing['useMapTargetCount']);
        $t->same(['missing-image-map'], $missing['useMapIssueCodes']);
        $t->same(1, $missing['useMapIssueCount']);
        $t->same(false, $missing['useMapAssociationValid']);

        $t->same('duplicate-map-name', $duplicate['useMapAssociationState']);
        $t->same(2, $duplicate['useMapTargetCount']);
        $t->same(['/one', '/two'], $duplicate['useMapAreaHrefs']);
        $t->same(['duplicate-map-name'], $duplicate['useMapIssueCodes']);
        $t->same(false, $duplicate['useMapAssociationValid']);

        $t->same('invalid-reference', $invalid['useMapAssociationState']);
        $t->same(['invalid-usemap-reference'], $invalid['useMapIssueCodes']);
        $t->same([['code' => 'invalid-usemap-reference', 'useMapRaw' => 'bad target']], $invalid['useMapIssues']);
        $t->same(false, $invalid['useMapAssociationValid']);

        $t->same('html-client-image-map-association-review', $figures['imageMapReviewPolicy']);
        $t->same('review', $figures['imageMapReviewStatus']);
        $t->same('referenced', $figures['imageMapAssociationState']);
        $t->same(1, $figures['imageMapReferenceCount']);
        $t->same(['diagram.png'], $figures['imageMapReferenceSources']);
        $t->same([], $figures['imageMapIssues']);
        $t->same([], $figures['imageMapIssueCodes']);
        $t->same(0, $figures['imageMapIssueCount']);
        $t->same([
            'invalid-circle-area-radius',
            'default-area-precedes-specific-area',
        ], $figures['areaGeometryIssueCodes']);
        $t->same(2, $figures['areaGeometryIssueCount']);
        $t->same(false, $figures['areaGeometryValid']);
        $t->same(false, $figures['imageMapValid']);
        $t->same(true, $figures['imageMapReviewOnlyNoBrowserHitTesting']);

        $t->same('duplicate-map-name', $firstDuplicateMap['imageMapAssociationState']);
        $t->same(['duplicate-map-name'], $firstDuplicateMap['imageMapIssueCodes']);
        $t->same(1, $firstDuplicateMap['imageMapIssueCount']);
        $t->same(false, $firstDuplicateMap['imageMapValid']);

        $t->same('unreferenced', $unused['imageMapAssociationState']);
        $t->same(['unreferenced-image-map'], $unused['imageMapIssueCodes']);
        $t->same(['invalid-area-coord-count'], $unused['areaGeometryIssueCodes']);
        $t->same(false, $unused['imageMapValid']);

        $t->same('invalid-map-name', $invalidMap['imageMapAssociationState']);
        $t->same(['invalid-map-name'], $invalidMap['imageMapIssueCodes']);
        $t->same(false, $invalidMap['imageMapValid']);

        $t->contains('usemap="#figures"', $html);
        $t->contains('<map name="figures">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/image-map-association-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
