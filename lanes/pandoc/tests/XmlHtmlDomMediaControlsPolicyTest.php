<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html media controls policy for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="movie" controls playsinline disablepictureinpicture disableremoteplayback controlslist="NoDownload nofullscreen nodownload noremoteplayback"><source src="movie.mp4" type="video/mp4">Fallback</video>'
                . '<audio id="sample" controls src="sample.mp3" controlslist="nodownload bad-token nodownload">Audio</audio>'
                . '<video id="plain" src="plain.mp4"></video>',
            'media controls policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/media-controls-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $video = $summary[0];
        $audio = $summary[1];
        $plain = $summary[2];

        $t->same('html-media-controls-policy-review', $video['mediaPolicyReview']);
        $t->same(true, $video['playsInline']);
        $t->same(true, $video['disablePictureInPicture']);
        $t->same(true, $video['disableRemotePlayback']);
        $t->same('NoDownload nofullscreen nodownload noremoteplayback', $video['controlsListRaw']);
        $t->same(['nodownload', 'nofullscreen', 'nodownload', 'noremoteplayback'], $video['controlsListTokens']);
        $t->same(['nodownload', 'nofullscreen', 'noremoteplayback'], $video['controlsListValidTokens']);
        $t->same([], $video['invalidControlsListTokens']);
        $t->same(['nodownload'], $video['duplicateControlsListTokens']);
        $t->same(['nodownload' => 2, 'nofullscreen' => 1, 'noremoteplayback' => 1], $video['controlsListTokenCounts']);
        $t->same(4, $video['controlsListTokenCount']);
        $t->same(3, $video['controlsListValidTokenCount']);
        $t->same('nodownload nofullscreen noremoteplayback', $video['controlsListNormalized']);
        $t->same(true, $video['controlsListValid']);
        $t->same(['duplicate-media-controlslist-token'], $video['mediaPolicyIssueCodes']);
        $t->same(1, $video['mediaPolicyIssueCount']);

        $t->same('html-media-controls-policy-review', $audio['mediaPolicyReview']);
        $t->same(false, $audio['playsInline']);
        $t->same(false, $audio['disablePictureInPicture']);
        $t->same(false, $audio['disableRemotePlayback']);
        $t->same('nodownload bad-token nodownload', $audio['controlsListRaw']);
        $t->same(['nodownload', 'bad-token', 'nodownload'], $audio['controlsListTokens']);
        $t->same(['nodownload'], $audio['controlsListValidTokens']);
        $t->same(['bad-token'], $audio['invalidControlsListTokens']);
        $t->same(['nodownload'], $audio['duplicateControlsListTokens']);
        $t->same(['nodownload' => 2], $audio['controlsListTokenCounts']);
        $t->same(false, $audio['controlsListValid']);
        $t->same([
            'invalid-media-controlslist-token',
            'duplicate-media-controlslist-token',
        ], $audio['mediaPolicyIssueCodes']);
        $t->same(2, $audio['mediaPolicyIssueCount']);

        $t->same('html-media-controls-policy-review', $plain['mediaPolicyReview']);
        $t->same(false, $plain['playsInline']);
        $t->same(false, $plain['disablePictureInPicture']);
        $t->same(false, $plain['disableRemotePlayback']);
        $t->same(null, $plain['controlsListRaw']);
        $t->same([], $plain['controlsListTokens']);
        $t->same([], $plain['controlsListValidTokens']);
        $t->same([], $plain['invalidControlsListTokens']);
        $t->same([], $plain['duplicateControlsListTokens']);
        $t->same(null, $plain['controlsListNormalized']);
        $t->same(null, $plain['controlsListValid']);
        $t->same([], $plain['mediaPolicyIssueCodes']);

        $t->contains('controlslist="NoDownload nofullscreen nodownload noremoteplayback"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/media-controls-policy-review.html', $document->children[0]->attr('part'));
    },
];
