<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html link expect target provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<link id="hero-expect" rel="expect" href="#hero" blocking="render" media="screen">'
                . '<link id="missing-expect" rel="expect" href="#missing">'
                . '<link id="external-expect" rel="expect" href="/other.html#hero" blocking="render">'
                . '<link id="bad-expect" rel="expect" href="#bad target" blocking="paint">'
                . '<section id="hero"><h2>Hero</h2></section>',
            'link expect target review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/link-expect-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $hero = $summary[0];
        $missing = $summary[1];
        $external = $summary[2];
        $bad = $summary[3];

        $t->same('link-expect-internal-resource-target-review', $hero['linkExpectReviewPolicy']);
        $t->same(['expect'], $hero['linkRelTokens']);
        $t->same(['expect'], $hero['linkResourceRelTokens']);
        $t->same(['expect'], $hero['linkResourceKinds']);
        $t->same('expect', $hero['linkPrimaryResourceKind']);
        $t->same(true, $hero['linkRenderBlockingResourceCandidate']);
        $t->same('#hero', $hero['linkExpectHrefRaw']);
        $t->same('fragment', $hero['linkExpectHrefKind']);
        $t->same(true, $hero['linkExpectSameDocumentFragment']);
        $t->same(true, $hero['linkExpectRenderBlockingTokenPresent']);
        $t->same(true, $hero['linkExpectPotentiallyRenderBlocking']);
        $t->same(false, $hero['linkExpectParserStackKnown']);
        $t->same(false, $hero['linkExpectBrowserExecution']);
        $t->same('hero', $hero['linkExpectTarget']);
        $t->same(true, $hero['linkExpectTargetValid']);
        $t->same(true, $hero['linkExpectTargetFound']);
        $t->same(1, $hero['linkExpectTargetCount']);
        $t->same('id', $hero['linkExpectTargetKind']);
        $t->same('section', $hero['linkExpectTargetElement']['tag'] ?? null);
        $t->same('hero', $hero['linkExpectTargetElement']['id'] ?? null);
        $t->same('Hero', $hero['linkExpectTargetElement']['text'] ?? null);
        $t->same([], $hero['linkExpectIssueCodes']);
        $t->same(true, $hero['linkExpectValid']);

        $t->same('#missing', $missing['linkExpectHrefRaw']);
        $t->same(false, $missing['linkExpectRenderBlockingTokenPresent']);
        $t->same(false, $missing['linkExpectPotentiallyRenderBlocking']);
        $t->same('missing', $missing['linkExpectTarget']);
        $t->same(false, $missing['linkExpectTargetFound']);
        $t->same('missing-target', $missing['linkExpectTargetKind']);
        $t->same([
            'missing-link-expect-render-blocking',
            'missing-hyperlink-fragment-target',
        ], $missing['linkExpectIssueCodes']);
        $t->same(false, $missing['linkExpectValid']);

        $t->same('/other.html#hero', $external['linkExpectHrefRaw']);
        $t->same('relative', $external['linkExpectHrefKind']);
        $t->same(false, $external['linkExpectSameDocumentFragment']);
        $t->same(true, $external['linkExpectRenderBlockingTokenPresent']);
        $t->same(false, $external['linkExpectPotentiallyRenderBlocking']);
        $t->same(null, $external['linkExpectTarget']);
        $t->same('non-fragment-href', $external['linkExpectTargetKind']);
        $t->same(['non-fragment-link-expect-href'], $external['linkExpectIssueCodes']);
        $t->same(false, $external['linkExpectValid']);

        $t->same('#bad target', $bad['linkExpectHrefRaw']);
        $t->same('fragment', $bad['linkExpectHrefKind']);
        $t->same(false, $bad['linkExpectRenderBlockingTokenPresent']);
        $t->same(false, $bad['linkExpectPotentiallyRenderBlocking']);
        $t->same(false, $bad['linkExpectTargetValid']);
        $t->same('invalid-reference', $bad['linkExpectTargetKind']);
        $t->same([
            'missing-link-expect-render-blocking',
            'invalid-hyperlink-fragment-target',
        ], $bad['linkExpectIssueCodes']);
        $t->same(['invalid-link-blocking-token'], $bad['linkLoadingIssueCodes']);
        $t->same(false, $bad['linkExpectValid']);

        $t->same(
            '<link blocking="render" href="#hero" id="hero-expect" media="screen" rel="expect">'
                . '<link href="#missing" id="missing-expect" rel="expect">'
                . '<link blocking="render" href="/other.html#hero" id="external-expect" rel="expect">'
                . '<link blocking="paint" href="#bad target" id="bad-expect" rel="expect">'
                . '<section id="hero"><h2>Hero</h2></section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/link-expect-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
