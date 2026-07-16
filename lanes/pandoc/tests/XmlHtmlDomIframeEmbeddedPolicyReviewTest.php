<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes iframe credentialless and embedded csp provenance for reviewer handoff' => static function (TestRunner $t): void {
        $trustedPolicy = "default-src 'self'; img-src https:; report-uri https://report.example.test/csp; script-src 'nonce-review'";
        $badPolicy = "default-src 'self'; default-src https://cdn.example.test; script-src 'unsafe-inline' data:; bad<directive value";
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<iframe id="trusted-frame" src="/frame.html" credentialless csp="'
                . htmlspecialchars($trustedPolicy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '" sandbox="allow-scripts">Frame fallback</iframe>'
                . '<iframe id="bad-frame" src="/bad.html" credentialless="false" csp="'
                . htmlspecialchars($badPolicy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '"></iframe>'
                . '<iframe id="empty-policy" src="/empty.html" csp=""></iframe>',
            'iframe embedded policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/iframe-embedded-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $trusted = $summary[0];
        $bad = $summary[1];
        $empty = $summary[2];

        $t->same('iframe', $trusted['embeddedResource']);
        $t->same(true, $trusted['credentialless']);
        $t->same($trustedPolicy, $trusted['csp']);
        $t->same('iframe-credentialless-attribute-review', $trusted['iframeCredentiallessReviewPolicy']);
        $t->same('iframe-credentialless-boolean-attribute-review', $trusted['iframeCredentiallessBooleanReviewPolicy']);
        $t->same('', $trusted['iframeCredentiallessRaw']);
        $t->same(true, $trusted['iframeCredentiallessEnabled']);
        $t->same(true, $trusted['iframeCredentiallessBooleanAttributeValid']);
        $t->same([], $trusted['iframeCredentiallessIssueCodes']);
        $t->same('iframe-csp-attribute-review', $trusted['iframeCspReviewPolicy']);
        $t->same('iframe-embedded-csp-policy-review', $trusted['iframeEmbeddedCspReviewPolicy']);
        $t->same($trustedPolicy, $trusted['iframeCspRaw']);
        $t->same(strlen($trustedPolicy), $trusted['iframeCspByteLength']);
        $t->same(hash('sha256', $trustedPolicy), $trusted['iframeCspSha256']);
        $t->same(4, $trusted['iframeCspDirectiveCount']);
        $t->same(['default-src', 'img-src', 'report-uri', 'script-src'], $trusted['iframeCspDirectiveNames']);
        $t->same(['fetch', 'reporting'], $trusted['iframeCspDirectiveKinds']);
        $t->same(['default-src', 'img-src', 'script-src'], $trusted['iframeCspFetchDirectiveNames']);
        $t->same(['https:'], $trusted['iframeCspSchemeSources']);
        $t->same(['https://report.example.test/csp'], $trusted['iframeCspNetworkSources']);
        $t->same(['https://report.example.test/csp'], $trusted['iframeCspReportEndpoints']);
        $t->same(1, $trusted['iframeCspNonceSourceCount']);
        $t->same([hash('sha256', 'review')], $trusted['iframeCspNonceSourceDigests']);
        $t->same([], $trusted['iframeCspIssueCodes']);
        $t->same(true, $trusted['iframeCspValid']);
        $t->same([], $trusted['iframePolicyIssueCodes']);

        $t->same(true, $bad['credentialless']);
        $t->same('false', $bad['iframeCredentiallessRaw']);
        $t->same(false, $bad['iframeCredentiallessBooleanAttributeValid']);
        $t->same(['noncanonical-iframe-credentialless-value'], $bad['iframeCredentiallessIssueCodes']);
        $t->same(4, $bad['iframeCspDirectiveCount']);
        $t->same(['default-src', 'script-src'], $bad['iframeCspDirectiveNames']);
        $t->same(['default-src' => 2, 'script-src' => 1], $bad['iframeCspDirectiveNameCounts']);
        $t->same(['default-src'], $bad['duplicateIframeCspDirectiveNames']);
        $t->same(['bad<directive'], $bad['invalidIframeCspDirectiveNames']);
        $t->same(['data:'], $bad['iframeCspSchemeSources']);
        $t->same(['https://cdn.example.test'], $bad['iframeCspNetworkSources']);
        $t->same(["'unsafe-inline'"], $bad['iframeCspUnsafeKeywords']);
        $t->same([
            'invalid-csp-directive-name',
            'duplicate-csp-directive',
            'unsafe-csp-keyword',
            'data-csp-source',
        ], $bad['iframeCspIssueCodes']);
        $t->same(false, $bad['iframeCspValid']);
        $t->same([
            'noncanonical-iframe-credentialless-value',
            'invalid-iframe-csp-policy',
        ], $bad['iframePolicyIssueCodes']);

        $t->same(false, $empty['credentialless']);
        $t->same('', $empty['csp']);
        $t->same(0, $empty['iframeCspDirectiveCount']);
        $t->same(['empty-iframe-csp'], $empty['iframeCspIssueCodes']);
        $t->same(false, $empty['iframeCspValid']);
        $t->same(['invalid-iframe-csp-policy'], $empty['iframePolicyIssueCodes']);
        $t->true(!array_key_exists('iframeCredentiallessReviewPolicy', $empty));

        $t->contains('credentialless', $html);
        $t->contains('csp=', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/iframe-embedded-policy-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
