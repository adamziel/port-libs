<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html iframe csp policy provenance for reviewer handoff' => static function (TestRunner $t): void {
        $safePolicy = "default-src 'none'; img-src https:; frame-ancestors 'none'";
        $invalidPolicy = "default-src 'self'; script-src 'unsafe-inline' data:; default-src https://backup.example.test; bad<directive value";
        $safeEscaped = htmlspecialchars($safePolicy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $invalidEscaped = htmlspecialchars($invalidPolicy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $dom = XmlHtmlDom::loadHtmlFragment(
            '<iframe id="review-frame" src="/frame.html" csp="' . $safeEscaped . '" sandbox="allow-scripts"></iframe>'
                . '<iframe id="bad-frame" src="https://widgets.example.test/embed.html" csp="' . $invalidEscaped . '"></iframe>'
                . '<iframe id="empty-frame" csp=""></iframe>'
                . '<iframe id="plain-frame" src="/plain.html"></iframe>',
            'iframe csp review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/iframe-csp-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $safe = $summary[0];
        $bad = $summary[1];
        $empty = $summary[2];
        $plain = $summary[3];

        $t->same('iframe', $safe['name']);
        $t->same($safePolicy, $safe['csp']);
        $t->same('iframe-content-security-policy-review', $safe['iframeCspReviewPolicy']);
        $t->same('iframe-content-security-policy-review', $safe['contentSecurityPolicyReviewPolicy']);
        $t->same('csp', $safe['contentSecurityPolicyHttpEquiv']);
        $t->same('csp', $safe['iframeCspSourceAttribute']);
        $t->same($safePolicy, $safe['iframeCspRaw']);
        $t->same(strlen($safePolicy), $safe['iframeCspByteLength']);
        $t->same(hash('sha256', $safePolicy), $safe['iframeCspSha256']);
        $t->same(3, $safe['iframeCspDirectiveCount']);
        $t->same(['default-src', 'img-src', 'frame-ancestors'], $safe['iframeCspDirectiveNames']);
        $t->same(['fetch', 'navigation'], $safe['iframeCspDirectiveKinds']);
        $t->same(['default-src', 'img-src'], $safe['iframeCspFetchDirectiveNames']);
        $t->same(['https:'], $safe['iframeCspSchemeSources']);
        $t->same([], $safe['iframeCspNetworkSources']);
        $t->same([], $safe['iframeCspReportEndpoints']);
        $t->same([], $safe['iframeCspUnsafeKeywords']);
        $t->same([], $safe['iframeCspIssueCodes']);
        $t->same(true, $safe['iframeCspValid']);
        $t->same(true, $safe['iframeCspReviewOnlyNoFrameFetch']);
        $t->same(false, $safe['iframeCspBrowserEnforcement']);
        $t->same([], $safe['iframePolicyIssueCodes']);

        $t->same($invalidPolicy, $bad['iframeCspRaw']);
        $t->same(['default-src', 'script-src'], $bad['iframeCspDirectiveNames']);
        $t->same(['default-src' => 2, 'script-src' => 1], $bad['iframeCspDirectiveNameCounts']);
        $t->same(['default-src'], $bad['duplicateCspDirectiveNames']);
        $t->same(['bad<directive'], $bad['invalidCspDirectiveNames']);
        $t->same(['data:'], $bad['iframeCspSchemeSources']);
        $t->same(["'unsafe-inline'"], $bad['iframeCspUnsafeKeywords']);
        $t->same([
            'invalid-csp-directive-name',
            'duplicate-csp-directive',
            'unsafe-csp-keyword',
            'data-csp-source',
        ], $bad['iframeCspIssueCodes']);
        $t->same(false, $bad['iframeCspValid']);
        $t->same(['invalid-iframe-csp'], $bad['iframePolicyIssueCodes']);

        $t->same('', $empty['iframeCspRaw']);
        $t->same([], $empty['iframeCspDirectiveNames']);
        $t->same(['missing-iframe-csp-content'], $empty['iframeCspIssueCodes']);
        $t->same(false, $empty['iframeCspValid']);
        $t->same(['invalid-iframe-csp'], $empty['iframePolicyIssueCodes']);

        $t->same(null, $plain['csp']);
        $t->same(false, isset($plain['iframeCspReviewPolicy']));
        $t->same([], $plain['iframePolicyIssueCodes']);

        $t->contains('csp="default-src &apos;none&apos;; img-src https:; frame-ancestors &apos;none&apos;"', $html);
        $t->contains('bad&lt;directive value', $html);
        $t->contains('csp=""', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/iframe-csp-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
