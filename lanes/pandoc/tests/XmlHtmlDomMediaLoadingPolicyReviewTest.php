<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html media loading policy metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="movie" preload=" Metadata " crossorigin width="0640" height="0360" controls poster="poster.jpg"><source src="movie.mp4" type="video/mp4">Fallback</video>'
                . '<audio id="sample" preload="soon" crossorigin="credentialed" width="-1" height="bad" src="sample.mp3">Audio</audio>'
                . '<video id="plain" src="plain.mp4"></video>',
            'media loading policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/media-loading-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $video = $summary[0];
        $audio = $summary[1];
        $plain = $summary[2];

        $t->same('media-loading-policy-metadata-review', $video['mediaLoadingPolicyReview']);
        $t->same('metadata', $video['preload']);
        $t->same(' Metadata ', $video['mediaPreloadRaw']);
        $t->same('metadata', $video['mediaPreloadState']);
        $t->same(true, $video['mediaPreloadValid']);
        $t->same('', $video['mediaCrossoriginRaw']);
        $t->same('anonymous', $video['mediaCrossoriginState']);
        $t->same(true, $video['mediaCrossoriginValid']);
        $t->same('0640', $video['mediaWidthRaw']);
        $t->same('640', $video['mediaWidth']);
        $t->same(true, $video['mediaWidthValid']);
        $t->same('0360', $video['mediaHeightRaw']);
        $t->same('360', $video['mediaHeight']);
        $t->same(true, $video['mediaHeightValid']);
        $t->same([], $video['mediaLoadingIssues']);
        $t->same([], $video['mediaLoadingIssueCodes']);
        $t->same(0, $video['mediaLoadingIssueCount']);
        $t->same(true, $video['mediaLoadingPolicyValid']);

        $t->same('auto', $audio['preload']);
        $t->same('soon', $audio['mediaPreloadRaw']);
        $t->same(null, $audio['mediaPreloadState']);
        $t->same(false, $audio['mediaPreloadValid']);
        $t->same('credentialed', $audio['mediaCrossoriginRaw']);
        $t->same(null, $audio['mediaCrossoriginState']);
        $t->same(false, $audio['mediaCrossoriginValid']);
        $t->same('-1', $audio['mediaWidthRaw']);
        $t->same(null, $audio['mediaWidth']);
        $t->same(false, $audio['mediaWidthValid']);
        $t->same('bad', $audio['mediaHeightRaw']);
        $t->same(null, $audio['mediaHeight']);
        $t->same(false, $audio['mediaHeightValid']);
        $t->same([
            'invalid-media-preload',
            'invalid-media-crossorigin',
            'invalid-media-width',
            'invalid-media-height',
        ], $audio['mediaLoadingIssueCodes']);
        $t->same([
            ['code' => 'invalid-media-preload', 'value' => 'soon'],
            ['code' => 'invalid-media-crossorigin', 'value' => 'credentialed'],
            ['code' => 'invalid-media-width', 'value' => '-1'],
            ['code' => 'invalid-media-height', 'value' => 'bad'],
        ], $audio['mediaLoadingIssues']);
        $t->same(4, $audio['mediaLoadingIssueCount']);
        $t->same(false, $audio['mediaLoadingPolicyValid']);

        $t->same(null, $plain['mediaPreloadRaw']);
        $t->same(null, $plain['mediaPreloadState']);
        $t->same(null, $plain['mediaPreloadValid']);
        $t->same(null, $plain['mediaCrossoriginValid']);
        $t->same(null, $plain['mediaWidthValid']);
        $t->same(null, $plain['mediaHeightValid']);
        $t->same([], $plain['mediaLoadingIssueCodes']);
        $t->same(true, $plain['mediaLoadingPolicyValid']);

        $t->contains('preload=" Metadata "', $html);
        $t->contains('crossorigin=""', $html);
        $t->contains('width="0640"', $html);
        $t->contains('preload="soon"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/media-loading-policy-review.html', $document->children[0]->attr('part'));
    },
];
