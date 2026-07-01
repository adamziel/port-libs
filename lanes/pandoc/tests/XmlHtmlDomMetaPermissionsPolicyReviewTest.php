<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html meta permissions policy provenance for reviewer handoff' => static function (TestRunner $t): void {
        $policy = 'geolocation=(self "https://maps.example.test"); camera=(); microphone=(*); geolocation=(self); bad<feature=(self); fullscreen=(bad<origin)';
        $legacyPolicy = "camera 'none'; geolocation 'self' https://maps.example.test; fullscreen *";
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<meta http-equiv="Permissions-Policy" content="' . htmlspecialchars($policy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                . '<meta http-equiv="Feature-Policy" content="' . htmlspecialchars($legacyPolicy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                . '<meta http-equiv="Permissions-Policy" content="">'
                . '<meta http-equiv="Permissions-Policy">'
                . '<p>Body</p>',
            'meta permissions policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/meta-permissions-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $modern = $summary[0];
        $legacy = $summary[1];
        $empty = $summary[2];
        $missing = $summary[3];
        $paragraph = $summary[4];

        $t->same('meta', $modern['documentMetadata']);
        $t->same('permissions-policy', $modern['httpEquiv']);
        $t->same('meta-permissions-policy-review', $modern['permissionsPolicyReviewPolicy']);
        $t->same('permissions-policy', $modern['permissionsPolicyHttpEquiv']);
        $t->same($policy, $modern['permissionsPolicyRaw']);
        $t->same(strlen($policy), $modern['permissionsPolicyByteLength']);
        $t->same(hash('sha256', $policy), $modern['permissionsPolicySha256']);
        $t->same(false, $modern['permissionsPolicyLegacySyntax']);
        $t->same(6, $modern['permissionsPolicyDirectiveCount']);
        $t->same([
            'geolocation',
            'camera',
            'microphone',
            'fullscreen',
        ], $modern['permissionsPolicyDirectiveNames']);
        $t->same([
            'geolocation' => 2,
            'camera' => 1,
            'microphone' => 1,
            'fullscreen' => 1,
        ], $modern['permissionsPolicyDirectiveNameCounts']);
        $t->same(['geolocation'], $modern['duplicatePermissionsPolicyDirectiveNames']);
        $t->same(['bad<feature'], $modern['invalidPermissionsPolicyDirectiveNames']);
        $t->same(['bad<origin'], $modern['invalidPermissionsPolicyAllowListTokens']);
        $t->same(['geolocation', 'camera', 'microphone', 'fullscreen'], $modern['permissionsPolicyFeatures']);
        $t->same(['camera'], $modern['permissionsPolicyDisabledFeatures']);
        $t->same(['microphone'], $modern['permissionsPolicyWildcardFeatures']);
        $t->same(['geolocation'], $modern['permissionsPolicySelfFeatures']);
        $t->same(['https://maps.example.test'], $modern['permissionsPolicyOriginAllowListTokens']);

        $geo = $modern['permissionsPolicyDirectives'][0];
        $camera = $modern['permissionsPolicyDirectives'][1];
        $invalidName = $modern['permissionsPolicyDirectives'][4];
        $invalidToken = $modern['permissionsPolicyDirectives'][5];
        $t->same('permissions-policy', $geo['syntax']);
        $t->same('geolocation', $geo['name']);
        $t->same('(self "https://maps.example.test")', $geo['allowListRaw']);
        $t->same(['self', '"https://maps.example.test"'], $geo['allowList']);
        $t->same(true, $geo['valid']);
        $t->same('camera', $camera['name']);
        $t->same([], $camera['allowList']);
        $t->same(true, $camera['allowListEmpty']);
        $t->same(null, $invalidName['name']);
        $t->same(['invalid-permissions-policy-directive-name'], $invalidName['issueCodes']);
        $t->same(false, $invalidName['valid']);
        $t->same('fullscreen', $invalidToken['name']);
        $t->same(['bad<origin'], $invalidToken['invalidAllowListTokens']);
        $t->same(['invalid-permissions-policy-allowlist-token'], $invalidToken['issueCodes']);
        $t->same(false, $invalidToken['valid']);
        $t->same([
            'invalid-permissions-policy-directive-name',
            'invalid-permissions-policy-allowlist-token',
            'duplicate-permissions-policy-directive',
            'wildcard-permissions-policy-allowlist',
        ], $modern['permissionsPolicyIssueCodes']);
        $t->same(false, $modern['permissionsPolicyValid']);

        $t->same('feature-policy', $legacy['permissionsPolicyHttpEquiv']);
        $t->same($legacyPolicy, $legacy['permissionsPolicyRaw']);
        $t->same(true, $legacy['permissionsPolicyLegacySyntax']);
        $t->same(3, $legacy['permissionsPolicyDirectiveCount']);
        $t->same(['camera', 'geolocation', 'fullscreen'], $legacy['permissionsPolicyDirectiveNames']);
        $t->same(['camera'], $legacy['permissionsPolicyDisabledFeatures']);
        $t->same(['fullscreen'], $legacy['permissionsPolicyWildcardFeatures']);
        $t->same(['geolocation'], $legacy['permissionsPolicySelfFeatures']);
        $t->same(['https://maps.example.test'], $legacy['permissionsPolicyOriginAllowListTokens']);
        $t->same('legacy-feature-policy', $legacy['permissionsPolicyDirectives'][0]['syntax']);
        $t->same(["'none'"], $legacy['permissionsPolicyDirectives'][0]['allowList']);
        $t->same(['wildcard-permissions-policy-allowlist'], $legacy['permissionsPolicyIssueCodes']);
        $t->same(false, $legacy['permissionsPolicyValid']);

        $t->same('', $empty['permissionsPolicyRaw']);
        $t->same(0, $empty['permissionsPolicyByteLength']);
        $t->same(0, $empty['permissionsPolicyDirectiveCount']);
        $t->same(['empty-meta-permissions-policy-content'], $empty['permissionsPolicyIssueCodes']);
        $t->same(false, $empty['permissionsPolicyValid']);

        $t->same(null, $missing['permissionsPolicyRaw']);
        $t->same(0, $missing['permissionsPolicyByteLength']);
        $t->same(0, $missing['permissionsPolicyDirectiveCount']);
        $t->same(['missing-meta-permissions-policy-content'], $missing['permissionsPolicyIssueCodes']);
        $t->same(false, $missing['permissionsPolicyValid']);

        $t->same('Body', $paragraph['text']);
        $t->contains('http-equiv="Permissions-Policy"', $html);
        $t->contains('http-equiv="Feature-Policy"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/meta-permissions-policy-review.html', $document->children[0]->attr('part'));
        json_encode($modern, JSON_THROW_ON_ERROR);
        json_encode($legacy, JSON_THROW_ON_ERROR);
    },
];
