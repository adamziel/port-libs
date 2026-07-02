<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html media fetch policy metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="movie" crossorigin="Anonymous" src="/media/movie.mp4"><source src="/media/movie.webm" type="video/webm" media="(min-width: 40em)">Fallback</video>'
                . '<audio id="bad" crossorigin="credentialed" src="javascript:alert(1)"><source type="audio/ogg"><source src="" type="audio/mp3"></audio>'
                . '<video id="dynamic">Dynamic source</video>',
            'media fetch policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/media-fetch-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $movie = $summary[0];
        $bad = $summary[1];
        $dynamic = $summary[2];

        $t->same('html-media-fetch-policy-review', $movie['mediaFetchPolicyReviewPolicy']);
        $t->same('video', $movie['mediaFetchElement']);
        $t->same('Anonymous', $movie['mediaCrossoriginRaw']);
        $t->same('anonymous', $movie['mediaCrossoriginState']);
        $t->same(true, $movie['mediaCrossoriginValid']);
        $t->same(1, $movie['mediaSourceElementCount']);
        $t->same(2, $movie['mediaFetchCandidateCount']);
        $t->same(2, $movie['mediaFetchUrlCount']);
        $t->same(['/media/movie.mp4', '/media/movie.webm'], $movie['mediaFetchCandidateUrls']);
        $t->same([], $movie['mediaFetchUnsafeUrls']);
        $t->same([], $movie['mediaFetchIssues']);
        $t->same([], $movie['mediaFetchIssueCodes']);
        $t->same(0, $movie['mediaFetchIssueCount']);
        $t->same(true, $movie['mediaFetchValid']);
        $t->same(true, $movie['mediaFetchReviewOnlyNoResourceFetch']);
        $t->same('metadata-only-no-media-fetch', $movie['mediaFetchReviewHandoffPolicy']);
        $t->same('video', $movie['mediaFetchCandidates'][0]['element']);
        $t->same('src-attribute', $movie['mediaFetchCandidates'][0]['source']);
        $t->same('relative', $movie['mediaFetchCandidates'][0]['urlKind']);
        $t->same(false, $movie['mediaFetchCandidates'][0]['urlUnsafe']);
        $t->same('source', $movie['mediaFetchCandidates'][1]['element']);
        $t->same('source-element', $movie['mediaFetchCandidates'][1]['source']);
        $t->same(0, $movie['mediaFetchCandidates'][1]['sourceIndex']);
        $t->same('video/webm', $movie['mediaFetchCandidates'][1]['type']);
        $t->same('(min-width: 40em)', $movie['mediaFetchCandidates'][1]['media']);

        $t->same('credentialed', $bad['mediaCrossoriginRaw']);
        $t->same(null, $bad['mediaCrossoriginState']);
        $t->same(false, $bad['mediaCrossoriginValid']);
        $t->same(2, $bad['mediaSourceElementCount']);
        $t->same(3, $bad['mediaFetchCandidateCount']);
        $t->same(1, $bad['mediaFetchUrlCount']);
        $t->same(['javascript:alert(1)'], $bad['mediaFetchUnsafeUrls']);
        $t->same('absolute', $bad['mediaFetchCandidates'][0]['urlKind']);
        $t->same('javascript', $bad['mediaFetchCandidates'][0]['urlScheme']);
        $t->same(true, $bad['mediaFetchCandidates'][0]['urlUnsafe']);
        $t->same(null, $bad['mediaFetchCandidates'][1]['url']);
        $t->same('missing', $bad['mediaFetchCandidates'][1]['urlKind']);
        $t->same('audio/ogg', $bad['mediaFetchCandidates'][1]['type']);
        $t->same('', $bad['mediaFetchCandidates'][2]['url']);
        $t->same('empty', $bad['mediaFetchCandidates'][2]['urlKind']);
        $t->same([
            ['code' => 'invalid-media-crossorigin', 'crossoriginRaw' => 'credentialed'],
            ['code' => 'unsafe-media-src-url', 'element' => 'audio', 'source' => 'src-attribute', 'url' => 'javascript:alert(1)', 'scheme' => 'javascript'],
            ['code' => 'missing-media-source-src', 'element' => 'source', 'source' => 'source-element', 'sourceIndex' => 0],
            ['code' => 'empty-media-src-url', 'element' => 'source', 'source' => 'source-element', 'sourceIndex' => 1],
        ], $bad['mediaFetchIssues']);
        $t->same([
            'invalid-media-crossorigin',
            'unsafe-media-src-url',
            'missing-media-source-src',
            'empty-media-src-url',
        ], $bad['mediaFetchIssueCodes']);
        $t->same(4, $bad['mediaFetchIssueCount']);
        $t->same(false, $bad['mediaFetchValid']);

        $t->same(null, $dynamic['mediaCrossoriginRaw']);
        $t->same(null, $dynamic['mediaCrossoriginValid']);
        $t->same(0, $dynamic['mediaSourceElementCount']);
        $t->same(0, $dynamic['mediaFetchCandidateCount']);
        $t->same([], $dynamic['mediaFetchCandidateUrls']);
        $t->same([], $dynamic['mediaFetchIssues']);
        $t->same(true, $dynamic['mediaFetchValid']);

        $t->contains('crossorigin="Anonymous"', $html);
        $t->contains('src="javascript:alert(1)"', $html);
        $t->contains('<source type="audio/ogg">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/media-fetch-policy-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
