<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html video poster resource provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="local" poster="images/poster.jpg" src="movie.mp4"></video>'
                . '<video id="remote" poster="https://cdn.example.test/poster.webp"></video>'
                . '<video id="empty" poster></video>'
                . '<video id="unsafe" poster="javascript:alert(1)"></video>'
                . '<video id="ftp" poster="ftp://media.example.test/poster.png"></video>'
                . '<audio id="audio" poster="ignored.jpg" src="sample.mp3"></audio>',
            'video poster review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/video-poster-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $local = $summary[0];
        $remote = $summary[1];
        $empty = $summary[2];
        $unsafe = $summary[3];
        $ftp = $summary[4];
        $audio = $summary[5];

        $t->same('video-poster-resource-provenance-review', $local['videoPosterReviewPolicy']);
        $t->same('images/poster.jpg', $local['poster']);
        $t->same('images/poster.jpg', $local['videoPosterRaw']);
        $t->same(true, $local['videoPosterPresent']);
        $t->same('relative', $local['videoPosterKind']);
        $t->same(null, $local['videoPosterScheme']);
        $t->same(false, $local['videoPosterUnsafe']);
        $t->same(true, $local['videoPosterReviewOnlyNoResourceFetch']);
        $t->same([], $local['videoPosterIssueCodes']);
        $t->same(0, $local['videoPosterIssueCount']);
        $t->same(true, $local['videoPosterValid']);

        $t->same('absolute', $remote['videoPosterKind']);
        $t->same('https', $remote['videoPosterScheme']);
        $t->same(false, $remote['videoPosterUnsafe']);
        $t->same([], $remote['videoPosterIssues']);
        $t->same(true, $remote['videoPosterValid']);

        $t->same('', $empty['videoPosterRaw']);
        $t->same('empty', $empty['videoPosterKind']);
        $t->same(false, $empty['videoPosterUnsafe']);
        $t->same([['code' => 'empty-video-poster']], $empty['videoPosterIssues']);
        $t->same(['empty-video-poster'], $empty['videoPosterIssueCodes']);
        $t->same(false, $empty['videoPosterValid']);

        $t->same('javascript:alert(1)', $unsafe['videoPosterRaw']);
        $t->same('absolute', $unsafe['videoPosterKind']);
        $t->same('javascript', $unsafe['videoPosterScheme']);
        $t->same(true, $unsafe['videoPosterUnsafe']);
        $t->same([
            ['code' => 'unsafe-video-poster-url', 'poster' => 'javascript:alert(1)', 'scheme' => 'javascript'],
        ], $unsafe['videoPosterIssues']);
        $t->same(['unsafe-video-poster-url'], $unsafe['videoPosterIssueCodes']);
        $t->same(false, $unsafe['videoPosterValid']);

        $t->same('absolute', $ftp['videoPosterKind']);
        $t->same('ftp', $ftp['videoPosterScheme']);
        $t->same(false, $ftp['videoPosterUnsafe']);
        $t->same([
            ['code' => 'non-http-video-poster-url', 'poster' => 'ftp://media.example.test/poster.png', 'scheme' => 'ftp'],
        ], $ftp['videoPosterIssues']);
        $t->same(['non-http-video-poster-url'], $ftp['videoPosterIssueCodes']);
        $t->same(false, $ftp['videoPosterValid']);

        $t->same('audio', $audio['media']);
        $t->true(!array_key_exists('videoPosterReviewPolicy', $audio));
        $t->true(!array_key_exists('videoPosterRaw', $audio));

        $t->same(
            '<video id="local" poster="images/poster.jpg" src="movie.mp4"></video>'
                . '<video id="remote" poster="https://cdn.example.test/poster.webp"></video>'
                . '<video id="empty" poster=""></video>'
                . '<video id="unsafe" poster="javascript:alert(1)"></video>'
                . '<video id="ftp" poster="ftp://media.example.test/poster.png"></video>'
                . '<audio id="audio" poster="ignored.jpg" src="sample.mp3"></audio>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/video-poster-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
