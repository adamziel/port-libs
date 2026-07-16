<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes iframe credentialless and csp policy provenance for reviewer handoff' => static function (TestRunner $t): void {
        $validPolicy = "default-src 'self'; frame-ancestors https://host.example.test; report-uri https://report.example.test/csp";
        $invalidPolicy = 'default-src data:; default-src https://backup.example.test; bad<directive value';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<iframe id="isolated" src="frame.html" credentialless csp="'
                . htmlspecialchars($validPolicy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '">Frame fallback</iframe>'
                . '<iframe id="noncanonical" src="bad.html" credentialless="false" csp="'
                . htmlspecialchars($invalidPolicy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '"></iframe>'
                . '<iframe id="empty-policy" credentialless="credentialless" csp=""></iframe>',
            'iframe credentialless csp review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $serializedValidPolicy = str_replace(
            '&#039;',
            '&apos;',
            htmlspecialchars($validPolicy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
        $serializedInvalidPolicy = htmlspecialchars($invalidPolicy, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/iframe-credentialless-csp-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $isolated = $summary[0];
        $noncanonical = $summary[1];
        $empty = $summary[2];

        $t->same('iframe', $isolated['embeddedResource']);
        $t->same(true, $isolated['credentialless']);
        $t->same('', $isolated['credentiallessRaw']);
        $t->same('iframe-credentialless-attribute-review', $isolated['iframeCredentiallessReviewPolicy']);
        $t->same('iframe-credentialless-storage-isolation-review', $isolated['iframeCredentiallessStorageIsolationReviewPolicy']);
        $t->same('cross-origin-embedder-credentialless', $isolated['iframeCredentiallessIsolationMode']);
        $t->same(false, $isolated['iframeCredentiallessNetworkFetchedByPortLibs']);
        $t->same(true, $isolated['iframeCredentiallessCanonicalBoolean']);
        $t->same([], $isolated['iframeCredentiallessIssueCodes']);
        $t->same('meta-content-security-policy-review', $isolated['contentSecurityPolicyReviewPolicy']);
        $t->same('iframe-csp-attribute-review', $isolated['iframeCspReviewPolicy']);
        $t->same('iframe-content-security-policy-attribute-review', $isolated['iframeCspAttributeReviewPolicy']);
        $t->same('iframe-csp-attribute', $isolated['contentSecurityPolicySource']);
        $t->same('csp', $isolated['contentSecurityPolicyAttribute']);
        $t->same($validPolicy, $isolated['contentSecurityPolicyRaw']);
        $t->same(strlen($validPolicy), $isolated['iframeCspByteLength']);
        $t->same(hash('sha256', $validPolicy), $isolated['iframeCspSha256']);
        $t->same(3, $isolated['iframeCspDirectiveCount']);
        $t->same(['default-src', 'frame-ancestors', 'report-uri'], $isolated['iframeCspDirectiveNames']);
        $t->same(['default-src'], $isolated['iframeCspFetchDirectiveNames']);
        $t->same(['https://host.example.test', 'https://report.example.test/csp'], $isolated['iframeCspNetworkSources']);
        $t->same(['https://report.example.test/csp'], $isolated['iframeCspReportEndpoints']);
        $t->same([], $isolated['iframeCspIssueCodes']);
        $t->same(true, $isolated['iframeCspValid']);
        $t->same(false, $isolated['iframeCspEnforcedByPortLibs']);
        $t->same([], $isolated['iframePolicyIssueCodes']);
        $t->same('Frame fallback', $isolated['fallbackText']);

        $t->same('false', $noncanonical['credentiallessRaw']);
        $t->same(true, $noncanonical['iframeCredentiallessRequested']);
        $t->same(false, $noncanonical['iframeCredentiallessCanonicalBoolean']);
        $t->same(['noncanonical-iframe-credentialless-value'], $noncanonical['iframeCredentiallessIssueCodes']);
        $t->same(false, $noncanonical['contentSecurityPolicyValid']);
        $t->same([
            'invalid-csp-directive-name',
            'duplicate-csp-directive',
            'data-csp-source',
        ], $noncanonical['cspIssueCodes']);
        $t->same(['default-src'], $noncanonical['duplicateCspDirectiveNames']);
        $t->same(['bad<directive'], $noncanonical['invalidCspDirectiveNames']);
        $t->same(['data:'], $noncanonical['cspSchemeSources']);
        $t->same([
            'noncanonical-iframe-credentialless-value',
            'invalid-iframe-csp-policy',
        ], $noncanonical['iframePolicyIssueCodes']);

        $t->same('credentialless', $empty['credentiallessRaw']);
        $t->same(true, $empty['iframeCredentiallessCanonicalBoolean']);
        $t->same('', $empty['contentSecurityPolicyRaw']);
        $t->same(['missing-meta-csp-content'], $empty['cspIssueCodes']);
        $t->same(['empty-iframe-csp'], $empty['iframeCspIssueCodes']);
        $t->same(false, $empty['iframeCspValid']);
        $t->same(['invalid-iframe-csp-policy'], $empty['iframePolicyIssueCodes']);

        $t->contains('credentialless', $html);
        $t->contains('credentialless="false"', $html);
        $t->contains('csp="' . $serializedValidPolicy . '"', $html);
        $t->contains('csp="' . $serializedInvalidPolicy . '"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/iframe-credentialless-csp-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
