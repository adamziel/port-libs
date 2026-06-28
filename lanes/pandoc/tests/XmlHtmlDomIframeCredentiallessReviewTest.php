<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html iframe credentialless policy provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<iframe id="isolated" src="https://widgets.example.test/embed.html" credentialless sandbox="allow-scripts allow-same-origin" referrerpolicy="no-referrer" loading="lazy">Fallback</iframe>'
                . '<iframe id="keyword" src="/local.html" credentialless="credentialless"></iframe>'
                . '<iframe id="noncanonical" src="/legacy.html" credentialless="false" sandbox="allow-popups"></iframe>'
                . '<iframe id="plain" src="/plain.html"></iframe>',
            'iframe credentialless review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/iframe-credentialless-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $isolated = $summary[0];
        $keyword = $summary[1];
        $noncanonical = $summary[2];
        $plain = $summary[3];

        $t->same('iframe-credentialless-storage-partition-review', $isolated['iframeCredentiallessReviewPolicy']);
        $t->same(true, $isolated['credentialless']);
        $t->same('', $isolated['credentiallessRaw']);
        $t->same(true, $isolated['iframeCredentialless']);
        $t->same('', $isolated['iframeCredentiallessRaw']);
        $t->same(true, $isolated['iframeCredentiallessCanonical']);
        $t->same(true, $isolated['iframeCredentiallessEphemeralContext']);
        $t->same(false, $isolated['iframeCredentiallessNetworkCredentials']);
        $t->same(false, $isolated['iframeCredentiallessCookieAccess']);
        $t->same(false, $isolated['iframeCredentiallessStorageAccess']);
        $t->same(true, $isolated['iframeCredentiallessReviewOnlyNoNetworkRequest']);
        $t->same(true, $isolated['iframeCredentiallessSandboxPresent']);
        $t->same(true, $isolated['iframeCredentiallessSandboxAllowsScripts']);
        $t->same(true, $isolated['iframeCredentiallessSandboxAllowsSameOrigin']);
        $t->same(true, $isolated['iframeCredentiallessSandboxAllowsScriptsAndSameOrigin']);
        $t->same([], $isolated['iframeCredentiallessIssueCodes']);
        $t->same([], $isolated['iframeCredentiallessIssues']);
        $t->same(true, $isolated['iframeCredentiallessValid']);
        $t->same(['iframe-sandbox-allows-scripts-same-origin'], $isolated['iframePolicyIssueCodes']);

        $t->same(true, $keyword['credentialless']);
        $t->same('credentialless', $keyword['credentiallessRaw']);
        $t->same('credentialless', $keyword['iframeCredentiallessRaw']);
        $t->same(true, $keyword['iframeCredentiallessCanonical']);
        $t->same(false, $keyword['iframeCredentiallessSandboxPresent']);
        $t->same(false, $keyword['iframeCredentiallessSandboxAllowsScripts']);
        $t->same(false, $keyword['iframeCredentiallessSandboxAllowsSameOrigin']);
        $t->same(false, $keyword['iframeCredentiallessSandboxAllowsScriptsAndSameOrigin']);
        $t->same([], $keyword['iframeCredentiallessIssueCodes']);
        $t->same([], $keyword['iframeCredentiallessIssues']);
        $t->same(true, $keyword['iframeCredentiallessValid']);
        $t->same([], $keyword['iframePolicyIssueCodes']);

        $t->same(true, $noncanonical['credentialless']);
        $t->same('false', $noncanonical['credentiallessRaw']);
        $t->same('false', $noncanonical['iframeCredentiallessRaw']);
        $t->same(true, $noncanonical['iframeCredentialless']);
        $t->same(false, $noncanonical['iframeCredentiallessCanonical']);
        $t->same(true, $noncanonical['iframeCredentiallessSandboxPresent']);
        $t->same(false, $noncanonical['iframeCredentiallessSandboxAllowsScripts']);
        $t->same(false, $noncanonical['iframeCredentiallessSandboxAllowsSameOrigin']);
        $t->same(false, $noncanonical['iframeCredentiallessSandboxAllowsScriptsAndSameOrigin']);
        $t->same(['noncanonical-iframe-credentialless-value'], $noncanonical['iframeCredentiallessIssueCodes']);
        $t->same([
            [
                'code' => 'noncanonical-iframe-credentialless-value',
                'credentiallessRaw' => 'false',
            ],
        ], $noncanonical['iframeCredentiallessIssues']);
        $t->same(false, $noncanonical['iframeCredentiallessValid']);
        $t->same(['noncanonical-iframe-credentialless-value'], $noncanonical['iframePolicyIssueCodes']);

        $t->same(false, $plain['credentialless']);
        $t->same(null, $plain['credentiallessRaw']);
        $t->same(false, isset($plain['iframeCredentiallessReviewPolicy']));
        $t->same(false, isset($plain['iframeCredentialless']));
        $t->same([], $plain['iframePolicyIssueCodes']);

        $t->contains('<iframe credentialless id="isolated"', $html);
        $t->contains('<iframe credentialless id="keyword"', $html);
        $t->contains('credentialless="false"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/iframe-credentialless-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
