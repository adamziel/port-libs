<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html hyperlink navigation issue codes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><a id="bad-nav" href="javascript:alert(1)" target="_blank" rel="opener opener bad&lt;tag" download="report.pdf" ping="https://audit.example.test/ping mailto:ops@example.test javascript:alert(1)" referrerpolicy="unsafe-policy">Bad navigation</a></p>'
                . '<p><a id="empty-ping" href="/safe" ping rel="noopener">Empty ping</a></p>'
                . '<p><a id="safe" href="/safe" ping="/audit" rel="noopener" referrerpolicy="strict-origin">Safe navigation</a></p>',
            'hyperlink navigation issue code review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hyperlink-navigation-issue-codes.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $bad = $summary[0]['children'][0];
        $emptyPing = $summary[1]['children'][0];
        $safe = $summary[2]['children'][0];

        $t->same('a', $bad['hyperlinkNavigationReview']);
        $t->same('javascript', $bad['hrefScheme']);
        $t->same(true, $bad['hrefUnsafe']);
        $t->same(true, $bad['targetBlank']);
        $t->same(true, $bad['targetOpenerAllowed']);
        $t->same('report.pdf', $bad['downloadSuggestedFilename']);
        $t->same(['opener'], $bad['hyperlinkRelTokens']);
        $t->same(['opener' => 2], $bad['hyperlinkRelTokenCounts']);
        $t->same(['opener'], $bad['duplicateHyperlinkRelTokens']);
        $t->same(['bad<tag'], $bad['invalidHyperlinkRelTokens']);
        $t->same(null, $bad['referrerPolicy']);
        $t->same(false, $bad['referrerPolicyValid']);
        $t->same(true, $bad['pingRequested']);
        $t->same(false, $bad['pingRawEmpty']);
        $t->same(true, $bad['pingSideEffect']);
        $t->same(3, $bad['pingUrlCount']);
        $t->same(['mailto:ops@example.test'], $bad['nonHttpPingUrls']);
        $t->same(['javascript:alert(1)'], $bad['unsafePingUrls']);
        $t->same('absolute', $bad['pingUrlRecords'][0]['kind']);
        $t->same('https', $bad['pingUrlRecords'][0]['scheme']);
        $t->same('absolute', $bad['pingUrlRecords'][1]['kind']);
        $t->same('mailto', $bad['pingUrlRecords'][1]['scheme']);
        $t->same('javascript', $bad['pingUrlRecords'][2]['scheme']);
        $t->same([
            'unsafe-href',
            'target-blank-explicit-opener',
            'invalid-rel-token',
            'duplicate-rel-token',
            'invalid-referrer-policy',
            'non-http-ping-url',
            'unsafe-ping-url',
        ], $bad['navigationIssueCodes']);
        $t->same(7, $bad['navigationIssueCount']);
        $t->same(false, $bad['hyperlinkNavigationValid']);

        $t->same(true, $emptyPing['pingRequested']);
        $t->same(true, $emptyPing['pingRawEmpty']);
        $t->same(false, $emptyPing['pingSideEffect']);
        $t->same(0, $emptyPing['pingUrlCount']);
        $t->same(['empty-ping-url-list'], $emptyPing['navigationIssueCodes']);
        $t->same(1, $emptyPing['navigationIssueCount']);
        $t->same(false, $emptyPing['hyperlinkNavigationValid']);

        $t->same(true, $safe['pingRequested']);
        $t->same(false, $safe['pingRawEmpty']);
        $t->same(true, $safe['pingSideEffect']);
        $t->same('/audit', $safe['pingUrlRecords'][0]['url']);
        $t->same('relative', $safe['pingUrlRecords'][0]['kind']);
        $t->same('strict-origin', $safe['referrerPolicy']);
        $t->same([], $safe['navigationIssueCodes']);
        $t->same(0, $safe['navigationIssueCount']);
        $t->same(true, $safe['hyperlinkNavigationValid']);

        $t->contains('ping=""', $html);
        $t->contains('ping="/audit"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/hyperlink-navigation-issue-codes.html', $document->children[0]->attr('part'));
    },
];
