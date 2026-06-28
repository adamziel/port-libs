<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html meta referrer policy provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<meta name="Referrer" content=" Strict-Origin-When-Cross-Origin ">'
                . '<meta name="referrer" content="never">'
                . '<meta name="referrer" content="always">'
                . '<meta name="referrer" content="origin-when-crossorigin">'
                . '<meta name="referrer" content="default">'
                . '<meta name="referrer" content="bogus">'
                . '<meta name="referrer" content="">'
                . '<meta name="referrer">'
                . '<p>Body</p>',
            'meta referrer policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/meta-referrer-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $strict = $summary[0];
        $never = $summary[1];
        $always = $summary[2];
        $legacyCrossOrigin = $summary[3];
        $default = $summary[4];
        $invalid = $summary[5];
        $empty = $summary[6];
        $missing = $summary[7];
        $paragraph = $summary[8];

        $t->same('meta', $strict['documentMetadata']);
        $t->same('Referrer', $strict['nameAttribute']);
        $t->same('meta-referrer-policy-review', $strict['metaReferrerReviewPolicy']);
        $t->same(' Strict-Origin-When-Cross-Origin ', $strict['metaReferrerRaw']);
        $t->same('strict-origin-when-cross-origin', $strict['metaReferrerNormalized']);
        $t->same(null, $strict['metaReferrerLegacyValue']);
        $t->same(false, $strict['metaReferrerLegacyMapped']);
        $t->same('strict-origin-when-cross-origin', $strict['metaReferrerPolicy']);
        $t->same('referrer-policy', $strict['metaReferrerPolicySource']);
        $t->same(false, $strict['metaReferrerDefaultPolicyRequested']);
        $t->same(true, $strict['metaReferrerApplies']);
        $t->same([], $strict['metaReferrerIssueCodes']);
        $t->same(true, $strict['metaReferrerValid']);

        $t->same('never', $never['metaReferrerLegacyValue']);
        $t->same(true, $never['metaReferrerLegacyMapped']);
        $t->same('no-referrer', $never['metaReferrerPolicy']);
        $t->same('legacy-alias', $never['metaReferrerPolicySource']);
        $t->same(true, $never['metaReferrerApplies']);

        $t->same('always', $always['metaReferrerLegacyValue']);
        $t->same('unsafe-url', $always['metaReferrerPolicy']);
        $t->same(true, $always['metaReferrerValid']);

        $t->same('origin-when-crossorigin', $legacyCrossOrigin['metaReferrerLegacyValue']);
        $t->same('origin-when-cross-origin', $legacyCrossOrigin['metaReferrerPolicy']);
        $t->same(true, $legacyCrossOrigin['metaReferrerValid']);

        $t->same('default', $default['metaReferrerLegacyValue']);
        $t->same('default-referrer-policy', $default['metaReferrerPolicy']);
        $t->same(true, $default['metaReferrerDefaultPolicyRequested']);
        $t->same(true, $default['metaReferrerApplies']);

        $t->same('bogus', $invalid['metaReferrerRaw']);
        $t->same(null, $invalid['metaReferrerPolicy']);
        $t->same('invalid-token', $invalid['metaReferrerPolicySource']);
        $t->same(false, $invalid['metaReferrerApplies']);
        $t->same([['code' => 'invalid-meta-referrer-policy', 'contentRaw' => 'bogus']], $invalid['metaReferrerIssues']);
        $t->same(['invalid-meta-referrer-policy'], $invalid['metaReferrerIssueCodes']);
        $t->same(false, $invalid['metaReferrerValid']);

        $t->same('', $empty['metaReferrerRaw']);
        $t->same('', $empty['metaReferrerNormalized']);
        $t->same(null, $empty['metaReferrerPolicy']);
        $t->same(false, $empty['metaReferrerApplies']);
        $t->same(['empty-meta-referrer-content'], $empty['metaReferrerIssueCodes']);

        $t->same(null, $missing['metaReferrerRaw']);
        $t->same(null, $missing['metaReferrerNormalized']);
        $t->same(null, $missing['metaReferrerPolicy']);
        $t->same(false, $missing['metaReferrerApplies']);
        $t->same(['missing-meta-referrer-content'], $missing['metaReferrerIssueCodes']);

        $t->same('Body', $paragraph['text']);
        $t->contains('<meta content="never" name="referrer">', $html);
        $t->contains('<meta name="referrer">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/meta-referrer-policy-review.html', $document->children[0]->attr('part'));
    },
];
