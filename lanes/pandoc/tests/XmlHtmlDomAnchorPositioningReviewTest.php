<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'records mapped html anchor positioning review case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedXmlHtmlDomAnchorPositioningReviewCases'] ?? null);
        $t->same(45, $manifest['xmlHtmlDomAnchorPositioningReviewAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedXmlHtmlDomAnchorPositioningReviewCases'] ?? null);
        $t->same(45, $manifest['benchmarkDenominator']['breakdown']['xmlHtmlDomAnchorPositioningReviewAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedXmlHtmlDomAnchorPositioningReviewCases'] ?? null);
        $t->same(45, $manifest['benchmarkDenominator']['inventory']['xmlHtmlDomAnchorPositioningReviewAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedXmlHtmlDomAnchorPositioningReviewCases'] ?? null);
        $t->same(45, $manifest['inventory']['xmlHtmlDomAnchorPositioningReviewAssertions'] ?? null);
    },

    'summarizes html anchor positioning targets for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<h2 id="card-title">Review Card</h2>'
                . '<div id="overlay" anchor="card-title">Overlay</div>'
                . '<div id="missing" anchor="missing-anchor">Missing</div>'
                . '<div id="empty" anchor="">Empty</div>'
                . '<div id="bad" anchor="bad target">Bad</div>',
            'anchor positioning review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/anchor-positioning-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $target = $summary[0];
        $overlay = $summary[1];
        $missing = $summary[2];
        $empty = $summary[3];
        $bad = $summary[4];

        $t->same(5, count($summary));
        $t->same('card-title', $target['elementId']);

        $t->same('html-anchor-positioning-target-review', $overlay['anchorPositioningReviewPolicy']);
        $t->same('card-title', $overlay['anchorRaw']);
        $t->same('card-title', $overlay['anchorTarget']);
        $t->same(true, $overlay['anchorTargetValid']);
        $t->same(true, $overlay['anchorTargetFound']);
        $t->same('element', $overlay['anchorTargetKind']);
        $t->same(true, $overlay['anchorReferencesTarget']);
        $t->same([], $overlay['anchorIssueCodes']);
        $t->same([], $overlay['anchorIssues']);
        $t->same('h2', $overlay['anchorTargetElement']['tag'] ?? null);
        $t->same('card-title', $overlay['anchorTargetElement']['id'] ?? null);
        $t->same('Review Card', $overlay['anchorTargetElement']['text'] ?? null);
        $t->same('h2', $overlay['anchorTargetElementName']);
        $t->same('card-title', $overlay['anchorTargetElementId']);
        $t->same('Review Card', $overlay['anchorTargetElementText']);

        $t->same('missing-anchor', $missing['anchorTarget']);
        $t->same(true, $missing['anchorTargetValid']);
        $t->same(false, $missing['anchorTargetFound']);
        $t->same('missing-target', $missing['anchorTargetKind']);
        $t->same(false, $missing['anchorReferencesTarget']);
        $t->same(['missing-html-anchor-positioning-target-element'], $missing['anchorIssueCodes']);

        $t->same(null, $empty['anchorTarget']);
        $t->same(false, $empty['anchorTargetValid']);
        $t->same('missing-reference', $empty['anchorTargetKind']);
        $t->same(['missing-html-anchor-positioning-target'], $empty['anchorIssueCodes']);

        $t->same('bad target', $bad['anchorRaw']);
        $t->same('bad target', $bad['anchorTarget']);
        $t->same(false, $bad['anchorTargetValid']);
        $t->same('invalid-reference', $bad['anchorTargetKind']);
        $t->same(['invalid-html-anchor-positioning-target'], $bad['anchorIssueCodes']);
        $t->same('bad target', $bad['anchorIssues'][0]['anchorRaw'] ?? null);

        $t->contains('<div anchor="card-title" id="overlay">Overlay</div>', $html);
        $t->contains('<div anchor="" id="empty">Empty</div>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/anchor-positioning-review.html', $document->children[0]->attr('part'));
    },
];
