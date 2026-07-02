<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'records mapped html meta refresh review case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedXmlHtmlDomMetaRefreshReviewCases'] ?? null);
        $t->same(55, $manifest['xmlHtmlDomMetaRefreshReviewAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedXmlHtmlDomMetaRefreshReviewCases'] ?? null);
        $t->same(55, $manifest['benchmarkDenominator']['breakdown']['xmlHtmlDomMetaRefreshReviewAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedXmlHtmlDomMetaRefreshReviewCases'] ?? null);
        $t->same(55, $manifest['benchmarkDenominator']['inventory']['xmlHtmlDomMetaRefreshReviewAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedXmlHtmlDomMetaRefreshReviewCases'] ?? null);
        $t->same(55, $manifest['inventory']['xmlHtmlDomMetaRefreshReviewAssertions'] ?? null);
    },

    'summarizes html meta refresh navigation for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<meta http-equiv="refresh" content="0; url=/next">'
                . '<meta http-equiv="refresh" content="3.5; URL=&quot;javascript:alert(1)&quot;">'
                . '<meta http-equiv="refresh" content="later; path=/nope">'
                . '<meta http-equiv="refresh" content="">'
                . '<p>Body</p>',
            'meta refresh review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/meta-refresh-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $safe = $summary[0];
        $unsafe = $summary[1];
        $invalid = $summary[2];
        $empty = $summary[3];

        $t->same('meta', $safe['documentMetadata']);
        $t->same('refresh', $safe['httpEquiv']);
        $t->same('0; url=/next', $safe['content']);
        $t->same([
            'contentRaw' => '0; url=/next',
            'delayRaw' => '0',
            'delay' => 0.0,
            'urlRaw' => '/next',
            'url' => '/next',
        ], $safe['refresh']);
        $t->same('html-meta-refresh-navigation-review', $safe['metaRefreshReviewPolicy']);
        $t->same('0; url=/next', $safe['metaRefreshRaw']);
        $t->same(0.0, $safe['metaRefreshDelay']);
        $t->same(true, $safe['metaRefreshDelayValid']);
        $t->same(true, $safe['metaRefreshImmediate']);
        $t->same('/next', $safe['metaRefreshUrl']);
        $t->same('relative', $safe['metaRefreshUrlKind']);
        $t->same(null, $safe['metaRefreshUrlScheme']);
        $t->same(false, $safe['metaRefreshUrlUnsafe']);
        $t->same(true, $safe['metaRefreshRedirect']);
        $t->same([], $safe['metaRefreshIssueCodes']);
        $t->same(true, $safe['metaRefreshValid']);

        $t->same('html-meta-refresh-navigation-review', $unsafe['metaRefreshReviewPolicy']);
        $t->same(3.5, $unsafe['metaRefreshDelay']);
        $t->same('"javascript:alert(1)"', $unsafe['metaRefreshUrlRaw']);
        $t->same('javascript:alert(1)', $unsafe['metaRefreshUrl']);
        $t->same(true, $unsafe['metaRefreshUrlQuoted']);
        $t->same('absolute', $unsafe['metaRefreshUrlKind']);
        $t->same('javascript', $unsafe['metaRefreshUrlScheme']);
        $t->same(true, $unsafe['metaRefreshUrlUnsafe']);
        $t->same(['unsafe-meta-refresh-url'], $unsafe['metaRefreshIssueCodes']);
        $t->same('javascript', $unsafe['metaRefreshIssues'][0]['scheme'] ?? null);
        $t->same(false, $unsafe['metaRefreshValid']);

        $t->same('later', $invalid['metaRefreshDelayRaw']);
        $t->same(null, $invalid['metaRefreshDelay']);
        $t->same(false, $invalid['metaRefreshDelayValid']);
        $t->same(null, $invalid['metaRefreshUrlRaw']);
        $t->same(null, $invalid['metaRefreshUrl']);
        $t->same('missing', $invalid['metaRefreshUrlKind']);
        $t->same([
            'invalid-meta-refresh-delay',
            'invalid-meta-refresh-url-parameter',
        ], $invalid['metaRefreshIssueCodes']);
        $t->same(false, $invalid['metaRefreshValid']);

        $t->same('', $empty['metaRefreshRaw']);
        $t->same(true, $empty['metaRefreshContentPresent']);
        $t->same(null, $empty['metaRefreshDelayRaw']);
        $t->same(null, $empty['metaRefreshDelayValid']);
        $t->same(false, $empty['metaRefreshRedirect']);
        $t->same(['empty-meta-refresh-content'], $empty['metaRefreshIssueCodes']);
        $t->same(false, $empty['metaRefreshValid']);

        $t->contains('content="0; url=/next"', $html);
        $t->contains('content="3.5; URL=&quot;javascript:alert(1)&quot;"', $html);
        $t->contains('content=""', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/meta-refresh-review.html', $document->children[0]->attr('part'));
    },
];
