<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html image loading metadata provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<img id="hero" src="hero.avif" alt="Hero" loading="lazy" decoding="async" fetchpriority="high" crossorigin="use-credentials" referrerpolicy="no-referrer">'
                . '<img id="plain" src="plain.png" alt="Plain">'
                . '<img id="bad" src="fallback.png" alt="Fallback" loading="Soon" decoding="fast" fetchpriority="urgent" crossorigin="credentialed" referrerpolicy="never">',
            'image loading metadata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/image-loading-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $hero = $summary[0];
        $plain = $summary[1];
        $bad = $summary[2];

        $t->same('image-loading-metadata-review', $hero['imageLoadingReviewPolicy']);
        $t->same([
            'loading',
            'decoding',
            'fetchpriority',
            'crossorigin',
            'referrerpolicy',
        ], $hero['imageLoadingPolicyAttributes']);
        $t->same('lazy', $hero['imageLoadingRaw']);
        $t->same('lazy', $hero['imageLoadingState']);
        $t->same(true, $hero['imageLoadingValid']);
        $t->same('async', $hero['imageDecodingRaw']);
        $t->same('async', $hero['imageDecodingState']);
        $t->same(true, $hero['imageDecodingValid']);
        $t->same('high', $hero['imageFetchPriorityRaw']);
        $t->same('high', $hero['imageFetchPriority']);
        $t->same(true, $hero['imageFetchPriorityValid']);
        $t->same('use-credentials', $hero['imageCrossoriginRaw']);
        $t->same('use-credentials', $hero['imageCrossoriginState']);
        $t->same(true, $hero['imageCrossoriginValid']);
        $t->same('no-referrer', $hero['imageReferrerPolicyRaw']);
        $t->same('no-referrer', $hero['imageReferrerPolicy']);
        $t->same(true, $hero['imageReferrerPolicyValid']);
        $t->same(true, $hero['imageLoadingReviewOnlyNoResourceFetch']);
        $t->same([], $hero['imageLoadingIssueCodes']);
        $t->same(0, $hero['imageLoadingIssueCount']);

        $t->same([], $plain['imageLoadingPolicyAttributes']);
        $t->same(null, $plain['imageLoadingRaw']);
        $t->same(null, $plain['imageLoadingState']);
        $t->same(null, $plain['imageLoadingValid']);
        $t->same(null, $plain['imageDecodingRaw']);
        $t->same(null, $plain['imageDecodingState']);
        $t->same(null, $plain['imageFetchPriorityRaw']);
        $t->same(null, $plain['imageFetchPriority']);
        $t->same(null, $plain['imageCrossoriginRaw']);
        $t->same(null, $plain['imageCrossoriginState']);
        $t->same(null, $plain['imageReferrerPolicyRaw']);
        $t->same(null, $plain['imageReferrerPolicy']);
        $t->same(true, $plain['imageLoadingReviewOnlyNoResourceFetch']);
        $t->same([], $plain['imageLoadingIssueCodes']);

        $t->same([
            'loading',
            'decoding',
            'fetchpriority',
            'crossorigin',
            'referrerpolicy',
        ], $bad['imageLoadingPolicyAttributes']);
        $t->same('Soon', $bad['imageLoadingRaw']);
        $t->same(null, $bad['imageLoadingState']);
        $t->same(false, $bad['imageLoadingValid']);
        $t->same('fast', $bad['imageDecodingRaw']);
        $t->same(null, $bad['imageDecodingState']);
        $t->same(false, $bad['imageDecodingValid']);
        $t->same('urgent', $bad['imageFetchPriorityRaw']);
        $t->same(null, $bad['imageFetchPriority']);
        $t->same(false, $bad['imageFetchPriorityValid']);
        $t->same('credentialed', $bad['imageCrossoriginRaw']);
        $t->same(null, $bad['imageCrossoriginState']);
        $t->same(false, $bad['imageCrossoriginValid']);
        $t->same('never', $bad['imageReferrerPolicyRaw']);
        $t->same(null, $bad['imageReferrerPolicy']);
        $t->same(false, $bad['imageReferrerPolicyValid']);
        $t->same([
            'invalid-image-loading',
            'invalid-image-decoding',
            'invalid-image-fetchpriority',
            'invalid-image-crossorigin',
            'invalid-image-referrerpolicy',
        ], $bad['imageLoadingIssueCodes']);
        $t->same(5, $bad['imageLoadingIssueCount']);

        $t->same(
            '<img alt="Hero" crossorigin="use-credentials" decoding="async" fetchpriority="high" id="hero" loading="lazy" referrerpolicy="no-referrer" src="hero.avif">'
                . '<img alt="Plain" id="plain" src="plain.png">'
                . '<img alt="Fallback" crossorigin="credentialed" decoding="fast" fetchpriority="urgent" id="bad" loading="Soon" referrerpolicy="never" src="fallback.png">',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/image-loading-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
