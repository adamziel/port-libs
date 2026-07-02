<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes iframe credentialless and csp policy provenance for reviewer handoff' => static function (TestRunner $t): void {
        $policy = "default-src 'self'; frame-ancestors 'none'; script-src 'nonce-frame'";
        $badPolicy = "default-src data:; script-src 'unsafe-inline'; bad<directive value";
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<iframe id="review-frame" src="chapter.xhtml" credentialless csp="' . htmlspecialchars($policy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Frame fallback</iframe>'
                . '<iframe id="bad-frame" src="bad.xhtml" csp="' . htmlspecialchars($badPolicy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Bad fallback</iframe>',
            'iframe credential policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $serializedPolicy = str_replace(
            '&#039;',
            '&apos;',
            htmlspecialchars($policy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
        $serializedBadPolicy = str_replace(
            '&#039;',
            '&apos;',
            htmlspecialchars($badPolicy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/iframe-credential-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $frame = $summary[0];
        $bad = $summary[1];

        $t->same('iframe', $frame['embeddedResource']);
        $t->same(true, $frame['credentialless']);
        $t->same('', $frame['credentiallessRaw']);
        $t->same('iframe-credentialless-storage-partition-review', $frame['iframeCredentiallessReviewPolicy']);
        $t->same('', $frame['iframeCredentiallessRaw']);
        $t->same(true, $frame['iframeCredentialless']);
        $t->same(true, $frame['iframeCredentiallessCanonical']);
        $t->same(true, $frame['iframeCredentiallessEphemeralContext']);
        $t->same(false, $frame['iframeCredentiallessNetworkCredentials']);
        $t->same(false, $frame['iframeCredentiallessCookieAccess']);
        $t->same(false, $frame['iframeCredentiallessStorageAccess']);
        $t->same(true, $frame['iframeCredentiallessReviewOnlyNoNetworkRequest']);
        $t->same([], $frame['iframeCredentiallessIssueCodes']);
        $t->same($policy, $frame['csp']);
        $t->same('iframe-content-security-policy-review', $frame['iframeCspReviewPolicy']);
        $t->same($policy, $frame['iframeCspRaw']);
        $t->same(strlen($policy), $frame['iframeCspByteLength']);
        $t->same(hash('sha256', $policy), $frame['iframeCspSha256']);
        $t->same('iframe-content-security-policy-review', $frame['contentSecurityPolicyReviewPolicy']);
        $t->same('csp', $frame['contentSecurityPolicyHttpEquiv']);
        $t->same(['default-src', 'frame-ancestors', 'script-src'], $frame['cspDirectiveNames']);
        $t->same(['fetch', 'navigation'], $frame['cspDirectiveKinds']);
        $t->same(['default-src', 'script-src'], $frame['cspFetchDirectiveNames']);
        $t->same(1, $frame['cspNonceSourceCount']);
        $t->same([hash('sha256', 'frame')], $frame['cspNonceSourceDigests']);
        $t->same([], $frame['iframeCspIssueCodes']);
        $t->same(true, $frame['iframeCspValid']);
        $t->same(true, $frame['iframeCspReviewOnlyNoFrameFetch']);
        $t->same(false, $frame['iframeCspBrowserEnforcement']);
        $t->same([], $frame['iframePolicyIssueCodes']);
        $t->same(0, $frame['iframePolicyIssueCount']);
        $t->same(true, $frame['iframePolicyValid']);
        $t->same('Frame fallback', $frame['fallbackText']);

        $t->same(false, $bad['credentialless']);
        $t->same(null, $bad['credentiallessRaw']);
        $t->same($badPolicy, $bad['csp']);
        $t->same(['invalid-csp-directive-name', 'unsafe-csp-keyword', 'data-csp-source'], $bad['iframeCspIssueCodes']);
        $t->same(false, $bad['iframeCspValid']);
        $t->same(['invalid-csp-directive-name'], $bad['cspDirectives'][2]['issueCodes']);
        $t->same(['invalid-iframe-csp'], $bad['iframePolicyIssueCodes']);
        $t->same(1, $bad['iframePolicyIssueCount']);
        $t->same(false, $bad['iframePolicyValid']);

        $t->contains('credentialless', $html);
        $t->contains('csp="' . $serializedPolicy . '"', $html);
        $t->contains('csp="' . $serializedBadPolicy . '"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/iframe-credential-policy-review.html', $document->children[0]->attr('part'));
        json_encode([$frame, $bad], JSON_THROW_ON_ERROR);
    },
];
