<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes video poster resource metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="safe" poster=" cover.jpg " controls>Safe fallback</video>'
                . '<video id="remote" poster="https://cdn.example.test/poster.jpg"></video>'
                . '<video id="bad" poster="javascript:alert(1)"></video>'
                . '<video id="empty" poster=""></video>'
                . '<video id="none" src="clip.mp4"></video>',
            'video poster resource review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $resources = XmlHtmlDom::summarizeHtmlFragmentResourceUrls($dom, 'https://source.example.test/articles/page.html');
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/video-poster-resource-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $safe = $summary[0];
        $remote = $summary[1];
        $bad = $summary[2];
        $empty = $summary[3];
        $none = $summary[4];

        $t->same('video-poster-resource-metadata-review', $safe['videoPosterReviewPolicy']);
        $t->same(' cover.jpg ', $safe['videoPosterRaw']);
        $t->same('cover.jpg', $safe['videoPosterUrl']);
        $t->same('relative', $safe['videoPosterUrlKind']);
        $t->same(null, $safe['videoPosterUrlScheme']);
        $t->same(false, $safe['videoPosterUrlUnsafe']);
        $t->same(false, $safe['videoPosterRemoteUrl']);
        $t->same([], $safe['videoPosterIssueCodes']);
        $t->same(true, $safe['videoPosterValid']);

        $t->same('https://cdn.example.test/poster.jpg', $remote['videoPosterUrl']);
        $t->same('absolute', $remote['videoPosterUrlKind']);
        $t->same('https', $remote['videoPosterUrlScheme']);
        $t->same(true, $remote['videoPosterRemoteUrl']);
        $t->same(true, $remote['videoPosterValid']);

        $t->same('javascript:alert(1)', $bad['videoPosterRaw']);
        $t->same('absolute', $bad['videoPosterUrlKind']);
        $t->same('javascript', $bad['videoPosterUrlScheme']);
        $t->same(true, $bad['videoPosterUrlUnsafe']);
        $t->same(['unsafe-video-poster-url'], $bad['videoPosterIssueCodes']);
        $t->same([['code' => 'unsafe-video-poster-url', 'scheme' => 'javascript']], $bad['videoPosterIssues']);
        $t->same(false, $bad['videoPosterValid']);

        $t->same('', $empty['videoPosterRaw']);
        $t->same('', $empty['videoPosterUrl']);
        $t->same('empty', $empty['videoPosterUrlKind']);
        $t->same(['empty-video-poster-url'], $empty['videoPosterIssueCodes']);
        $t->same(false, $empty['videoPosterValid']);

        $t->same(null, $none['videoPosterRaw']);
        $t->same(null, $none['videoPosterUrl']);
        $t->same('missing', $none['videoPosterUrlKind']);
        $t->same([], $none['videoPosterIssueCodes']);
        $t->same(null, $none['videoPosterValid']);

        $posterResources = array_values(array_filter(
            $resources['resources'],
            static fn (array $resource): bool => ($resource['role'] ?? null) === 'video-poster'
        ));
        $t->same(4, count($posterResources));
        $t->same('poster', $posterResources[0]['attribute']);
        $t->same(' cover.jpg ', $posterResources[0]['value']);
        $t->same('cover.jpg', $posterResources[0]['normalizedValue']);
        $t->same(true, $posterResources[0]['resolved']);
        $t->same('https://source.example.test/articles/cover.jpg', $posterResources[0]['resolvedUrl']);
        $t->same(true, $posterResources[1]['resolved']);
        $t->same('https://cdn.example.test/poster.jpg', $posterResources[1]['resolvedUrl']);
        $t->same(['unsafe-resource-url'], $posterResources[2]['issueCodes']);
        $t->same(['empty-resource-url'], $posterResources[3]['issueCodes']);
        $t->same(['poster', 'src'], $resources['resourceAttributes']);
        $t->same(5, $resources['resourceCount']);

        $t->contains('poster="javascript:alert(1)"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/video-poster-resource-review.html', $document->children[0]->attr('part'));
        json_encode([$safe, $remote, $bad, $empty, $none, $resources], JSON_THROW_ON_ERROR);
    },
];
