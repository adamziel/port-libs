<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html base url and target provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<base id="primary" href="https://example.test/docs/" target=" Review_Frame ">'
                . '<p><a id="chapter" href="chapter.html">Chapter</a><a id="intro-link" href="#intro">Intro</a><a id="external" href="https://other.example.test/">External</a></p>'
                . '<link id="preload" rel="preload" href="/asset.css">'
                . '<map name="zones"><area id="hotspot" alt="Hotspot" href="//cdn.example.test/map"></map>'
                . '<base id="ignored-href" href="javascript:alert(1)">'
                . '<base id="ignored-target" target="bad&lt;frame">',
            'base url target review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/base-url-target-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $primary = $summary[0];
        $ignoredHref = $summary[4];
        $ignoredTarget = $summary[5];

        $t->same('base', $primary['documentMetadata']);
        $t->same('html-base-url-target-review', $primary['baseReviewPolicy']);
        $t->same('https://example.test/docs/', $primary['baseHrefRaw']);
        $t->same(true, $primary['baseHrefPresent']);
        $t->same('absolute', $primary['baseHrefKind']);
        $t->same('https', $primary['baseHrefScheme']);
        $t->same(false, $primary['baseHrefUnsafe']);
        $t->same(true, $primary['baseHrefEffective']);
        $t->same(false, $primary['baseHrefIgnored']);
        $t->same(' Review_Frame ', $primary['baseTargetRaw']);
        $t->same('Review_Frame', $primary['baseTargetName']);
        $t->same(true, $primary['baseTargetValid']);
        $t->same(false, $primary['baseTargetReserved']);
        $t->same(false, $primary['baseTargetBlank']);
        $t->same(true, $primary['baseTargetEffective']);
        $t->same(false, $primary['baseTargetIgnored']);
        $t->same(3, $primary['baseDocumentBaseCount']);
        $t->same(0, $primary['baseDocumentBaseIndex']);
        $t->same(2, $primary['baseDocumentHrefBaseCount']);
        $t->same(2, $primary['baseDocumentTargetBaseCount']);
        $t->same(0, $primary['baseDocumentEffectiveHrefIndex']);
        $t->same(0, $primary['baseDocumentEffectiveTargetIndex']);
        $t->same('https://example.test/docs/', $primary['baseDocumentEffectiveHref']);
        $t->same(' Review_Frame ', $primary['baseDocumentEffectiveTarget']);
        $t->same(true, $primary['baseHrefAffectsRelativeLinks']);
        $t->same(4, $primary['baseHrefAffectedCandidateCount']);
        $t->same(['chapter', 'intro-link', 'preload', 'hotspot'], $primary['baseHrefAffectedCandidateIds']);
        $t->same('a', $primary['baseHrefAffectedCandidates'][0]['tag']);
        $t->same('relative', $primary['baseHrefAffectedCandidates'][0]['hrefKind']);
        $t->same('fragment', $primary['baseHrefAffectedCandidates'][1]['hrefKind']);
        $t->same(['preload'], $primary['baseHrefAffectedCandidates'][2]['relTokens']);
        $t->same('scheme-relative', $primary['baseHrefAffectedCandidates'][3]['hrefKind']);
        $t->same('Hotspot', $primary['baseHrefAffectedCandidates'][3]['alt']);
        $t->same(false, $primary['baseHrefAffectedCandidateOverflow']);
        $t->same([], $primary['baseIssueCodes']);
        $t->same(true, $primary['baseValid']);
        $t->same(true, $primary['baseReviewOnlyNoUrlResolution']);

        $t->same('javascript:alert(1)', $ignoredHref['baseHrefRaw']);
        $t->same('javascript', $ignoredHref['baseHrefScheme']);
        $t->same(true, $ignoredHref['baseHrefUnsafe']);
        $t->same(false, $ignoredHref['baseHrefEffective']);
        $t->same(true, $ignoredHref['baseHrefIgnored']);
        $t->same(false, $ignoredHref['baseHrefAffectsRelativeLinks']);
        $t->same([
            'unsafe-base-href',
            'duplicate-base-href-ignored',
        ], $ignoredHref['baseIssueCodes']);
        $t->same(false, $ignoredHref['baseValid']);

        $t->same('bad<frame', $ignoredTarget['baseTargetRaw']);
        $t->same('bad<frame', $ignoredTarget['baseTargetName']);
        $t->same(false, $ignoredTarget['baseTargetValid']);
        $t->same('_blank', $ignoredTarget['baseTargetFallbackName']);
        $t->same(false, $ignoredTarget['baseTargetEffective']);
        $t->same(true, $ignoredTarget['baseTargetIgnored']);
        $t->same([
            'invalid-base-target',
            'duplicate-base-target-ignored',
        ], $ignoredTarget['baseIssueCodes']);
        $t->same(false, $ignoredTarget['baseValid']);

        $t->contains('<base href="https://example.test/docs/" id="primary" target=" Review_Frame ">', $html);
        $t->contains('<base href="javascript:alert(1)" id="ignored-href">', $html);
        $t->contains('<base id="ignored-target" target="bad&lt;frame">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/base-url-target-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
