<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html media preload provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="movie" preload="Metadata" src="movie.mp4"></video>'
                . '<audio id="implicit" src="sample.mp3"></audio>'
                . '<video id="empty" preload src="empty.mp4"></video>'
                . '<audio id="bad" preload="soon" src="bad.mp3">Fallback</audio>',
            'media preload review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/media-preload-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $explicit = $summary[0];
        $implicit = $summary[1];
        $empty = $summary[2];
        $invalid = $summary[3];

        $t->same('html-media-preload-metadata-review', $explicit['mediaPreloadReviewPolicy']);
        $t->same('Metadata', $explicit['mediaPreloadRaw']);
        $t->same('metadata', $explicit['mediaPreloadKeyword']);
        $t->same('metadata', $explicit['mediaPreloadState']);
        $t->same('metadata', $explicit['preload']);
        $t->same(true, $explicit['mediaPreloadValid']);
        $t->same(false, $explicit['mediaPreloadDefaulted']);
        $t->same(null, $explicit['mediaPreloadDefaultReason']);
        $t->same([], $explicit['mediaPreloadIssueCodes']);

        $t->same(null, $implicit['mediaPreloadRaw']);
        $t->same(null, $implicit['mediaPreloadKeyword']);
        $t->same('auto', $implicit['mediaPreloadState']);
        $t->same('auto', $implicit['preload']);
        $t->same(null, $implicit['mediaPreloadValid']);
        $t->same(true, $implicit['mediaPreloadDefaulted']);
        $t->same('missing-value-default', $implicit['mediaPreloadDefaultReason']);

        $t->same('', $empty['mediaPreloadRaw']);
        $t->same(null, $empty['mediaPreloadKeyword']);
        $t->same('auto', $empty['mediaPreloadState']);
        $t->same('auto', $empty['preload']);
        $t->same(true, $empty['mediaPreloadValid']);
        $t->same(true, $empty['mediaPreloadDefaulted']);
        $t->same('empty-value-default', $empty['mediaPreloadDefaultReason']);
        $t->same([], $empty['mediaPreloadIssueCodes']);

        $t->same('soon', $invalid['mediaPreloadRaw']);
        $t->same(null, $invalid['mediaPreloadKeyword']);
        $t->same('auto', $invalid['mediaPreloadState']);
        $t->same('auto', $invalid['preload']);
        $t->same(false, $invalid['mediaPreloadValid']);
        $t->same(true, $invalid['mediaPreloadDefaulted']);
        $t->same('invalid-value-default', $invalid['mediaPreloadDefaultReason']);
        $t->same([
            ['code' => 'invalid-media-preload-token', 'preloadRaw' => 'soon'],
        ], $invalid['mediaPreloadIssues']);
        $t->same(['invalid-media-preload-token'], $invalid['mediaPreloadIssueCodes']);
        $t->same(1, $invalid['mediaPreloadIssueCount']);

        $t->contains('preload="Metadata"', $html);
        $t->contains('preload=""', $html);
        $t->contains('preload="soon"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/media-preload-review.html', $document->children[0]->attr('part'));
    },
];
