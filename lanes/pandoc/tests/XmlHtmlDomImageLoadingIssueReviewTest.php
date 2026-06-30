<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html image loading issue records for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<img id="valid" src="hero.avif" alt="Hero" loading=" Lazy " decoding="ASYNC" fetchpriority="HIGH" crossorigin="" referrerpolicy="strict-origin">'
                . '<img id="invalid" src="fallback.png" alt="Fallback" loading="Soon" decoding="fast" fetchpriority="urgent" crossorigin="credentialed" referrerpolicy="never">',
            'image loading issue review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/image-loading-issue-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $valid = $summary[0];
        $invalid = $summary[1];

        $t->same('image-loading-metadata-review', $valid['imageLoadingReviewPolicy']);
        $t->same('lazy', $valid['imageLoadingState']);
        $t->same('async', $valid['imageDecodingState']);
        $t->same('high', $valid['imageFetchPriority']);
        $t->same('anonymous', $valid['imageCrossoriginState']);
        $t->same('strict-origin', $valid['imageReferrerPolicy']);
        $t->same([], $valid['imageLoadingIssues']);
        $t->same([], $valid['imageLoadingIssueCodes']);
        $t->same(0, $valid['imageLoadingIssueCount']);

        $t->same(null, $invalid['imageLoadingState']);
        $t->same(false, $invalid['imageLoadingValid']);
        $t->same(null, $invalid['imageDecodingState']);
        $t->same(false, $invalid['imageDecodingValid']);
        $t->same(null, $invalid['imageFetchPriority']);
        $t->same(false, $invalid['imageFetchPriorityValid']);
        $t->same(null, $invalid['imageCrossoriginState']);
        $t->same(false, $invalid['imageCrossoriginValid']);
        $t->same(null, $invalid['imageReferrerPolicy']);
        $t->same(false, $invalid['imageReferrerPolicyValid']);
        $t->same([
            ['code' => 'invalid-image-loading', 'loadingRaw' => 'Soon'],
            ['code' => 'invalid-image-decoding', 'decodingRaw' => 'fast'],
            ['code' => 'invalid-image-fetchpriority', 'fetchpriorityRaw' => 'urgent'],
            ['code' => 'invalid-image-crossorigin', 'crossoriginRaw' => 'credentialed'],
            ['code' => 'invalid-image-referrerpolicy', 'referrerpolicyRaw' => 'never'],
        ], $invalid['imageLoadingIssues']);
        $t->same([
            'invalid-image-loading',
            'invalid-image-decoding',
            'invalid-image-fetchpriority',
            'invalid-image-crossorigin',
            'invalid-image-referrerpolicy',
        ], $invalid['imageLoadingIssueCodes']);
        $t->same(5, $invalid['imageLoadingIssueCount']);

        $t->same(
            '<img alt="Hero" crossorigin="" decoding="ASYNC" fetchpriority="HIGH" id="valid" loading=" Lazy " referrerpolicy="strict-origin" src="hero.avif">'
                . '<img alt="Fallback" crossorigin="credentialed" decoding="fast" fetchpriority="urgent" id="invalid" loading="Soon" referrerpolicy="never" src="fallback.png">',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/image-loading-issue-review.html', $document->children[0]->attr('part'));
    },
];
