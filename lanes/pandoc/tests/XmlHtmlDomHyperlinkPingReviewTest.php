<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html hyperlink ping side effects for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><a id="audit" href="/report" rel="noopener" ping="https://audit.example.test/log /local https://audit.example.test/log javascript:alert(1) mailto:ops@example.test">Report</a></p>'
                . '<map name="zones"><area id="hotspot" alt="Hotspot" href="/map" ping="/area /area"></map>',
            'hyperlink ping side-effect review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hyperlink-ping-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $anchor = $summary[0]['children'][0];
        $area = $summary[1]['children'][0];

        $t->same('hyperlink-ping-side-effect-review', $anchor['pingReviewPolicy']);
        $t->same(true, $anchor['pingSideEffect']);
        $t->same(5, $anchor['pingUrlCount']);
        $t->same([
            'https://audit.example.test/log' => 2,
            '/local' => 1,
            'javascript:alert(1)' => 1,
            'mailto:ops@example.test' => 1,
        ], $anchor['pingUrlTokenCounts']);
        $t->same(['https://audit.example.test/log'], $anchor['duplicatePingUrls']);
        $t->same(['javascript:alert(1)'], $anchor['unsafePingUrls']);
        $t->same(['mailto:ops@example.test'], $anchor['nonHttpPingUrls']);
        $t->same(true, $anchor['pingUrlRecords'][0]['duplicate']);
        $t->same(false, $anchor['pingUrlRecords'][1]['duplicate']);
        $t->same('absolute', $anchor['pingUrlRecords'][0]['kind']);
        $t->same('relative', $anchor['pingUrlRecords'][1]['kind']);
        $t->same('javascript', $anchor['pingUrlRecords'][3]['scheme']);
        $t->same([
            'unsafe-ping-url',
            'non-http-ping-url',
            'duplicate-ping-url',
        ], $anchor['pingIssueCodes']);
        $t->same(false, $anchor['pingValid']);
        $t->same([
            ['code' => 'unsafe-ping-url', 'url' => 'javascript:alert(1)', 'scheme' => 'javascript'],
            ['code' => 'non-http-ping-url', 'url' => 'mailto:ops@example.test', 'scheme' => 'mailto'],
            ['code' => 'duplicate-ping-url', 'url' => 'https://audit.example.test/log', 'count' => 2],
        ], $anchor['navigationIssues']);
        $t->same([
            'unsafe-ping-url',
            'non-http-ping-url',
            'duplicate-ping-url',
        ], $anchor['navigationIssueCodes']);

        $t->same('area', $area['hyperlinkNavigationReview']);
        $t->same('hyperlink-ping-side-effect-review', $area['pingReviewPolicy']);
        $t->same(['/area' => 2], $area['pingUrlTokenCounts']);
        $t->same(['/area'], $area['duplicatePingUrls']);
        $t->same(['duplicate-ping-url'], $area['pingIssueCodes']);
        $t->same(false, $area['pingValid']);
        $t->same(['duplicate-ping-url'], $area['navigationIssueCodes']);

        $t->contains('ping="https://audit.example.test/log /local https://audit.example.test/log javascript:alert(1) mailto:ops@example.test"', $html);
        $t->contains('ping="/area /area"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/hyperlink-ping-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
