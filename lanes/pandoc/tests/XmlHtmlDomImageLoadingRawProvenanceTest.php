<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html image loading raw provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<img id="hero" src="hero.jpg" alt="Hero" loading=" Lazy " decoding="ASYNC" fetchpriority="HIGH" crossorigin="" referrerpolicy="strict-origin">'
                . '<img id="bad" src="bad.jpg" alt="Bad" loading="soon" decoding="fast" fetchpriority="urgent" crossorigin="credentialed" referrerpolicy="never">'
                . '<img id="plain" src="plain.jpg" alt="Plain">',
            'image loading raw provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/image-loading-raw-provenance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $hero = $summary[0];
        $bad = $summary[1];
        $plain = $summary[2];

        $t->same('image-loading-metadata-review', $hero['imageLoadingReviewPolicy']);
        $t->same(' Lazy ', $hero['imageLoadingRaw']);
        $t->same('lazy', $hero['imageLoadingState']);
        $t->same(true, $hero['imageLoadingValid']);
        $t->same('ASYNC', $hero['imageDecodingRaw']);
        $t->same('async', $hero['imageDecodingState']);
        $t->same('HIGH', $hero['imageFetchPriorityRaw']);
        $t->same('high', $hero['imageFetchPriority']);
        $t->same('', $hero['imageCrossoriginRaw']);
        $t->same('anonymous', $hero['imageCrossoriginState']);
        $t->same('strict-origin', $hero['imageReferrerPolicyRaw']);
        $t->same('strict-origin', $hero['imageReferrerPolicy']);
        $t->same([], $hero['imageLoadingIssues']);
        $t->same([], $hero['imageLoadingIssueCodes']);
        $t->same(true, $hero['imageLoadingPolicyValid']);

        $t->same('soon', $bad['imageLoadingRaw']);
        $t->same(null, $bad['imageLoadingState']);
        $t->same(false, $bad['imageLoadingValid']);
        $t->same('fast', $bad['imageDecodingRaw']);
        $t->same(null, $bad['imageDecodingState']);
        $t->same('urgent', $bad['imageFetchPriorityRaw']);
        $t->same(null, $bad['imageFetchPriority']);
        $t->same('credentialed', $bad['imageCrossoriginRaw']);
        $t->same(null, $bad['imageCrossoriginState']);
        $t->same('never', $bad['imageReferrerPolicyRaw']);
        $t->same(null, $bad['imageReferrerPolicy']);
        $t->same([
            ['code' => 'invalid-image-loading', 'value' => 'soon'],
            ['code' => 'invalid-image-decoding', 'value' => 'fast'],
            ['code' => 'invalid-image-fetchpriority', 'value' => 'urgent'],
            ['code' => 'invalid-image-crossorigin', 'value' => 'credentialed'],
            ['code' => 'invalid-image-referrerpolicy', 'value' => 'never'],
        ], $bad['imageLoadingIssues']);
        $t->same([
            'invalid-image-loading',
            'invalid-image-decoding',
            'invalid-image-fetchpriority',
            'invalid-image-crossorigin',
            'invalid-image-referrerpolicy',
        ], $bad['imageLoadingIssueCodes']);
        $t->same(false, $bad['imageLoadingPolicyValid']);

        $t->same(null, $plain['imageLoadingRaw']);
        $t->same(null, $plain['imageLoadingValid']);
        $t->same(null, $plain['imageDecodingRaw']);
        $t->same(null, $plain['imageFetchPriorityRaw']);
        $t->same(null, $plain['imageCrossoriginRaw']);
        $t->same(null, $plain['imageReferrerPolicyRaw']);
        $t->same([], $plain['imageLoadingIssueCodes']);
        $t->same(true, $plain['imageLoadingPolicyValid']);

        $t->contains('loading=" Lazy "', $html);
        $t->contains('fetchpriority="HIGH"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/image-loading-raw-provenance-review.html', $document->children[0]->attr('part'));
    },
];
