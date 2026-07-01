<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html link render blocking token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<link rel="stylesheet preload" href="/critical.css" as="style" blocking="render render custom">'
                . '<link rel="preconnect" href="https://fonts.example" blocking="layout">'
                . '<link rel="author" href="/about" blocking>'
                . '<link rel="stylesheet" href="/plain.css">',
            'link render blocking review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/link-render-blocking-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $critical = $summary[0];
        $preconnect = $summary[1];
        $emptyBlocking = $summary[2];
        $plain = $summary[3];

        $t->same('link-render-blocking-token-review', $critical['linkBlockingReviewPolicy']);
        $t->same('render render custom', $critical['blockingRaw']);
        $t->same(['render', 'render', 'custom'], $critical['linkBlockingTokens']);
        $t->same(['render' => 2, 'custom' => 1], $critical['linkBlockingTokenCounts']);
        $t->same(['render'], $critical['duplicateLinkBlockingTokens']);
        $t->same(['custom'], $critical['invalidLinkBlockingTokens']);
        $t->same(true, $critical['linkRenderBlockingTokenPresent']);
        $t->same(true, $critical['linkRenderBlockingResourceCandidate']);
        $t->same('declared-render-blocking-resource', $critical['linkBlockingReviewKind']);
        $t->same([
            ['code' => 'invalid-link-blocking-token', 'blockingToken' => 'custom', 'count' => 1],
            ['code' => 'duplicate-link-blocking-token', 'blockingToken' => 'render', 'count' => 2],
        ], $critical['linkIssues']);

        $t->same(['layout' => 1], $preconnect['linkBlockingTokenCounts']);
        $t->same(['layout'], $preconnect['invalidLinkBlockingTokens']);
        $t->same('declared-non-render-token', $preconnect['linkBlockingReviewKind']);
        $t->same([], $emptyBlocking['linkBlockingTokens']);
        $t->same('empty-blocking-attribute', $emptyBlocking['linkBlockingReviewKind']);
        $t->same(false, $plain['linkBlockingAttributePresent']);
        $t->same('not-declared', $plain['linkBlockingReviewKind']);
        $t->contains($html, $blocks);
        $t->same('/migration/link-render-blocking-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html style render blocking token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<style id="critical" media="screen" blocking="render bad-token render">body{color:blue}</style>'
                . '<style id="plain">body{color:black}</style>',
            'style render blocking review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/style-render-blocking-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $critical = $summary[0];
        $plain = $summary[1];

        $t->same('style-loading-policy-metadata-review', $critical['styleLoadingPolicyReview']);
        $t->same(true, $critical['styleBlockingAttributePresent']);
        $t->same(['render', 'bad-token', 'render'], $critical['styleBlockingTokens']);
        $t->same(['render' => 2, 'bad-token' => 1], $critical['styleBlockingTokenCounts']);
        $t->same(['render'], $critical['duplicateStyleBlockingTokens']);
        $t->same(['bad-token'], $critical['invalidStyleBlockingTokens']);
        $t->same(true, $critical['styleRenderBlockingTokenPresent']);
        $t->same(false, $critical['styleBlockingAllTokensValid']);
        $t->same([
            ['code' => 'invalid-style-blocking-token', 'token' => 'bad-token'],
            ['code' => 'duplicate-style-blocking-token', 'token' => 'render', 'count' => 2],
        ], $critical['styleLoadingIssues']);
        $t->same([
            'invalid-style-blocking-token',
            'duplicate-style-blocking-token',
        ], $critical['styleLoadingIssueCodes']);
        $t->same(false, $critical['styleLoadingPolicyValid']);

        $t->same(false, $plain['styleBlockingAttributePresent']);
        $t->same([], $plain['styleBlockingTokens']);
        $t->same([], $plain['styleBlockingTokenCounts']);
        $t->same(false, $plain['styleRenderBlockingTokenPresent']);
        $t->same(true, $plain['styleLoadingPolicyValid']);
        $t->contains($html, $blocks);
        $t->same('/migration/style-render-blocking-review.html', $document->children[0]->attr('part'));
    },
];
