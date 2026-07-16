<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html media preload policy for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="metadata" controls preload="metadata"><source src="movie.mp4" type="video/mp4">Fallback</video>'
                . '<audio id="empty" controls preload src="empty.mp3">Empty preload</audio>'
                . '<video id="invalid" preload="soon">Invalid fallback</video>'
                . '<audio id="missing" autoplay src="chapter.mp3">Autoplay audio</audio>',
            'media preload policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/media-preload-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $metadata = $summary[0];
        $empty = $summary[1];
        $invalid = $summary[2];
        $missing = $summary[3];

        $t->same('media-preload-state-review', $metadata['mediaPreloadReviewPolicy']);
        $t->same('metadata', $metadata['preload']);
        $t->same('metadata', $metadata['preloadRaw']);
        $t->same('metadata', $metadata['preloadKeyword']);
        $t->same('metadata', $metadata['preloadState']);
        $t->same(true, $metadata['preloadValid']);
        $t->same(false, $metadata['preloadDefaulted']);
        $t->same(null, $metadata['preloadDefaultReason']);
        $t->same(false, $metadata['preloadAutoplayOverride']);
        $t->same([], $metadata['mediaPreloadIssueCodes']);

        $t->same('auto', $empty['preload']);
        $t->same('', $empty['preloadRaw']);
        $t->same('auto', $empty['preloadKeyword']);
        $t->same('auto', $empty['preloadState']);
        $t->same(true, $empty['preloadValid']);
        $t->same(true, $empty['preloadDefaulted']);
        $t->same('empty-attribute', $empty['preloadDefaultReason']);
        $t->same([], $empty['mediaPreloadIssueCodes']);

        $t->same('auto', $invalid['preload']);
        $t->same('soon', $invalid['preloadRaw']);
        $t->same(null, $invalid['preloadKeyword']);
        $t->same('auto', $invalid['preloadState']);
        $t->same(false, $invalid['preloadValid']);
        $t->same(true, $invalid['preloadDefaulted']);
        $t->same('invalid-token', $invalid['preloadDefaultReason']);
        $t->same(['invalid-media-preload-token'], $invalid['mediaPreloadIssueCodes']);
        $t->same(1, $invalid['mediaPreloadIssueCount']);

        $t->same('auto', $missing['preload']);
        $t->same(null, $missing['preloadRaw']);
        $t->same(null, $missing['preloadKeyword']);
        $t->same('auto', $missing['preloadState']);
        $t->same(null, $missing['preloadValid']);
        $t->same(true, $missing['preloadDefaulted']);
        $t->same('missing-attribute', $missing['preloadDefaultReason']);
        $t->same(true, $missing['preloadAutoplayOverride']);
        $t->same([], $missing['mediaPreloadIssueCodes']);

        $t->contains('preload="metadata"', $html);
        $t->contains('preload=""', $html);
        $t->contains('preload="soon"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/media-preload-policy-review.html', $document->children[0]->attr('part'));
    },
];
