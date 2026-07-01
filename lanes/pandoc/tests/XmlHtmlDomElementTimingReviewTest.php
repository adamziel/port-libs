<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html elementtiming tokens for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="hero" elementtiming="hero-card">Hero copy</section>'
                . '<img id="poster" src="poster.jpg" alt="Poster" elementtiming=" poster-image ">'
                . '<video id="clip" poster="clip.jpg" elementtiming="clip-poster"></video>'
                . '<p id="empty" elementtiming="">Empty</p>'
                . '<span id="bad" elementtiming="bad&lt;token">Bad</span>'
                . '<a id="link" href="/target" elementtiming="link-target">Link</a>',
            'elementtiming token review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/elementtiming-token-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $hero = $summary[0];
        $poster = $summary[1];
        $clip = $summary[2];
        $empty = $summary[3];
        $bad = $summary[4];
        $link = $summary[5];

        $t->same('html-elementtiming-token-review', $hero['elementTimingReviewPolicy']);
        $t->same('section', $hero['elementTimingElement']);
        $t->same('hero-card', $hero['elementTimingRaw']);
        $t->same('hero-card', $hero['elementTimingToken']);
        $t->same(true, $hero['elementTimingValid']);
        $t->same(9, $hero['elementTimingByteLength']);
        $t->same(false, $hero['elementTimingWhitespaceTrimmed']);
        $t->same('text', $hero['elementTimingObservedKind']);
        $t->same(9, $hero['elementTimingTextLength']);
        $t->same(null, $hero['elementTimingResourceAttribute']);
        $t->same(null, $hero['elementTimingResourceUrl']);
        $t->same([], $hero['elementTimingIssueCodes']);

        $t->same(' poster-image ', $poster['elementTimingRaw']);
        $t->same('poster-image', $poster['elementTimingToken']);
        $t->same(true, $poster['elementTimingWhitespaceTrimmed']);
        $t->same('image', $poster['elementTimingObservedKind']);
        $t->same(0, $poster['elementTimingTextLength']);
        $t->same('src', $poster['elementTimingResourceAttribute']);
        $t->same('poster.jpg', $poster['elementTimingResourceUrl']);

        $t->same('media', $clip['elementTimingObservedKind']);
        $t->same('poster', $clip['elementTimingResourceAttribute']);
        $t->same('clip.jpg', $clip['elementTimingResourceUrl']);

        $t->same('', $empty['elementTimingRaw']);
        $t->same(null, $empty['elementTimingToken']);
        $t->same(false, $empty['elementTimingValid']);
        $t->same(0, $empty['elementTimingTokenByteLength']);
        $t->same('text', $empty['elementTimingObservedKind']);
        $t->same([['code' => 'empty-elementtiming-token']], $empty['elementTimingIssues']);
        $t->same(['empty-elementtiming-token'], $empty['elementTimingIssueCodes']);

        $t->same('bad<token', $bad['elementTimingRaw']);
        $t->same(null, $bad['elementTimingToken']);
        $t->same(false, $bad['elementTimingValid']);
        $t->same([['code' => 'invalid-elementtiming-token', 'value' => 'bad<token']], $bad['elementTimingIssues']);
        $t->same(['invalid-elementtiming-token'], $bad['elementTimingIssueCodes']);

        $t->same('resource', $link['elementTimingObservedKind']);
        $t->same('href', $link['elementTimingResourceAttribute']);
        $t->same('/target', $link['elementTimingResourceUrl']);
        $t->same(true, $link['elementTimingValid']);

        $t->contains('elementtiming="hero-card"', $html);
        $t->contains('elementtiming=" poster-image "', $html);
        $t->contains('elementtiming="bad&lt;token"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/elementtiming-token-review.html', $document->children[0]->attr('part'));
    },
];
