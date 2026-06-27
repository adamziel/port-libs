<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html link render blocking token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<link id="theme" rel="stylesheet" href="/theme.css" blocking="render render paint">'
                . '<link id="font" rel="preload" href="/font.woff2" as="font" blocking="render">'
                . '<link id="author" rel="author" href="/about" blocking="render">'
                . '<link id="empty" rel="stylesheet" href="/empty.css" blocking>'
                . '<link id="plain" rel="stylesheet" href="/plain.css">',
            'link render blocking review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/link-render-blocking-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $theme = $summary[0];
        $font = $summary[1];
        $author = $summary[2];
        $empty = $summary[3];
        $plain = $summary[4];

        $t->same('link-render-blocking-token-review', $theme['linkBlockingReviewPolicy']);
        $t->same(true, $theme['linkBlockingAttributePresent']);
        $t->same(['render', 'render', 'paint'], $theme['linkBlockingTokens']);
        $t->same(['render' => 2, 'paint' => 1], $theme['linkBlockingTokenCounts']);
        $t->same(['render'], $theme['duplicateLinkBlockingTokens']);
        $t->same(['paint'], $theme['invalidLinkBlockingTokens']);
        $t->same(true, $theme['linkRenderBlockingTokenPresent']);
        $t->same(true, $theme['linkRenderBlockingResourceCandidate']);
        $t->same('declared-render-blocking-resource', $theme['linkBlockingReviewKind']);
        $t->same([
            ['code' => 'invalid-link-blocking-token', 'blockingToken' => 'paint', 'count' => 1],
            ['code' => 'duplicate-link-blocking-token', 'blockingToken' => 'render', 'count' => 2],
        ], $theme['linkBlockingIssues']);
        $t->same(['invalid-link-blocking-token', 'duplicate-link-blocking-token'], $theme['linkBlockingIssueCodes']);
        $t->same(2, $theme['linkBlockingIssueCount']);
        $t->same(false, $theme['linkBlockingValid']);
        $t->same($theme['linkBlockingIssueCodes'], $theme['linkLoadingIssueCodes']);

        $t->same(['render'], $font['linkBlockingTokens']);
        $t->same([], $font['linkBlockingIssueCodes']);
        $t->same(0, $font['linkBlockingIssueCount']);
        $t->same(true, $font['linkBlockingValid']);
        $t->same('declared-render-blocking-resource', $font['linkBlockingReviewKind']);

        $t->same(['render'], $author['linkBlockingTokens']);
        $t->same(true, $author['linkRenderBlockingTokenPresent']);
        $t->same(false, $author['linkRenderBlockingResourceCandidate']);
        $t->same('declared-render-blocking-non-resource', $author['linkBlockingReviewKind']);
        $t->same([], $author['linkBlockingIssues']);
        $t->same(true, $author['linkBlockingValid']);

        $t->same(true, $empty['linkBlockingAttributePresent']);
        $t->same([], $empty['linkBlockingTokens']);
        $t->same('empty-blocking-attribute', $empty['linkBlockingReviewKind']);
        $t->same([], $empty['linkBlockingIssueCodes']);
        $t->same(true, $empty['linkBlockingValid']);

        $t->same(false, $plain['linkBlockingAttributePresent']);
        $t->same([], $plain['linkBlockingTokens']);
        $t->same('not-declared', $plain['linkBlockingReviewKind']);
        $t->same(null, $plain['linkBlockingValid']);
        $t->same([], $plain['linkBlockingIssueCodes']);

        $t->contains('blocking="render render paint"', $html);
        $t->contains('<link blocking="" href="/empty.css"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/link-render-blocking-review.html', $document->children[0]->attr('part'));
        json_encode($theme, JSON_THROW_ON_ERROR);
    },
];
