<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html meta content security policy directives for reviewer handoff' => static function (TestRunner $t): void {
        $policy = "default-src 'self'; script-src 'nonce-review' 'unsafe-inline' https://cdn.example.test; img-src https: data:; report-uri https://report.example.test/csp; default-src https://backup.example.test; bad<directive value";
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<meta http-equiv="Content-Security-Policy" content="' . htmlspecialchars($policy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                . '<p>Body</p>',
            'content security policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $serializedPolicy = str_replace(
            '&#039;',
            '&apos;',
            htmlspecialchars($policy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/content-security-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $meta = $summary[0];
        $paragraph = $summary[1];

        $t->same('meta', $meta['name']);
        $t->same('meta-content-security-policy-review', $meta['contentSecurityPolicyReviewPolicy']);
        $t->same('content-security-policy', $meta['contentSecurityPolicyHttpEquiv']);
        $t->same($policy, $meta['contentSecurityPolicyRaw']);
        $t->same(strlen($policy), $meta['contentSecurityPolicyByteLength']);
        $t->same(hash('sha256', $policy), $meta['contentSecurityPolicySha256']);
        $t->same(6, $meta['cspDirectiveCount']);
        $t->same([
            'default-src',
            'script-src',
            'img-src',
            'report-uri',
        ], $meta['cspDirectiveNames']);
        $t->same([
            'default-src' => 2,
            'script-src' => 1,
            'img-src' => 1,
            'report-uri' => 1,
        ], $meta['cspDirectiveNameCounts']);
        $t->same(['fetch', 'reporting'], $meta['cspDirectiveKinds']);
        $t->same(['default-src', 'script-src', 'img-src'], $meta['cspFetchDirectiveNames']);
        $t->same(['default-src'], $meta['duplicateCspDirectiveNames']);
        $t->same(['bad<directive'], $meta['invalidCspDirectiveNames']);
        $t->same([], $meta['invalidCspSourceTokens']);
        $t->same(['https:', 'data:'], $meta['cspSchemeSources']);
        $t->same([
            'https://cdn.example.test',
            'https://report.example.test/csp',
            'https://backup.example.test',
        ], $meta['cspNetworkSources']);
        $t->same(['https://report.example.test/csp'], $meta['cspReportEndpoints']);
        $t->same(["'unsafe-inline'"], $meta['cspUnsafeKeywords']);
        $t->same(1, $meta['cspNonceSourceCount']);
        $t->same([hash('sha256', 'review')], $meta['cspNonceSourceDigests']);
        $t->same([], $meta['cspHashSourceAlgorithms']);

        $script = $meta['cspDirectives'][1];
        $invalid = $meta['cspDirectives'][5];
        $t->same('script-src', $script['name']);
        $t->same('fetch', $script['kind']);
        $t->same(["'nonce-review'", "'unsafe-inline'", 'https://cdn.example.test'], $script['values']);
        $t->same(true, $script['valid']);
        $t->same(null, $invalid['name']);
        $t->same('invalid', $invalid['kind']);
        $t->same(['invalid-csp-directive-name'], $invalid['issueCodes']);
        $t->same(false, $invalid['valid']);

        $t->same([
            'invalid-csp-directive-name',
            'duplicate-csp-directive',
            'unsafe-csp-keyword',
            'data-csp-source',
        ], $meta['cspIssueCodes']);
        $t->same(false, $meta['contentSecurityPolicyValid']);
        $t->same('Body', $paragraph['text']);
        $t->same(
            '<meta content="' . $serializedPolicy . '" http-equiv="Content-Security-Policy"><p>Body</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/content-security-policy-review.html', $document->children[0]->attr('part'));
        json_encode($meta, JSON_THROW_ON_ERROR);
    },
];
