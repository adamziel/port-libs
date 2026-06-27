<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html link fetch policy provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<link rel="preload modulepreload" href="/app.js" as="script" crossorigin="Anonymous" fetchpriority="HIGH" referrerpolicy="strict-origin" integrity="sha384-good sha512-better">'
                . '<link rel="preload" href="/font.woff2" as="font" crossorigin fetchpriority="low">'
                . '<link rel="author" href="/about" integrity="">'
                . '<link rel="preload" href="/bad.js" as="script" integrity="sha1-old naked sha384-good sha384-good">'
                . '<link rel="stylesheet" href="/bad.css" crossorigin="credentialed" fetchpriority="urgent" referrerpolicy="unsafe-policy">',
            'link fetch policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/link-fetch-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $module = $summary[0];
        $font = $summary[1];
        $author = $summary[2];
        $badIntegrity = $summary[3];
        $bad = $summary[4];

        $t->same('link-fetch-policy-review', $module['linkFetchPolicyReviewPolicy']);
        $t->same('Anonymous', $module['linkCrossoriginRaw']);
        $t->same('anonymous', $module['linkCrossoriginState']);
        $t->same(true, $module['linkCrossoriginValid']);
        $t->same('HIGH', $module['linkFetchPriorityRaw']);
        $t->same('high', $module['linkFetchPriority']);
        $t->same(true, $module['linkFetchPriorityValid']);
        $t->same('strict-origin', $module['linkReferrerPolicy']);
        $t->same(true, $module['linkReferrerPolicyValid']);
        $t->same('sha384-good sha512-better', $module['linkIntegrityRaw']);
        $t->same(['sha384-good', 'sha512-better'], $module['linkIntegrityTokens']);
        $t->same(2, $module['linkIntegrityTokenCount']);
        $t->same(true, $module['linkIntegrityPresent']);
        $t->same(false, $module['linkIntegrityEmpty']);
        $t->same(true, $module['linkIntegrityAppliesToResource']);
        $t->same(['sha384', 'sha512'], $module['linkIntegrityHashAlgorithms']);
        $t->same([], $module['unsupportedLinkIntegrityAlgorithms']);
        $t->same([], $module['duplicateLinkIntegrityTokens']);
        $t->same('sha384', $module['linkIntegrityTokenRecords'][0]['algorithm']);
        $t->same(true, $module['linkIntegrityTokenRecords'][0]['algorithmSupported']);
        $t->same(true, $module['linkIntegrityTokenRecords'][0]['hashPresent']);
        $t->same([], $module['linkIntegrityIssueCodes']);
        $t->same(true, $module['linkIntegrityValid']);
        $t->same([], $module['linkFetchPolicyIssueCodes']);
        $t->same(true, $module['linkFetchPolicyValid']);

        $t->same('', $font['linkCrossoriginRaw']);
        $t->same('anonymous', $font['linkCrossoriginState']);
        $t->same('low', $font['linkFetchPriority']);
        $t->same(null, $font['linkReferrerPolicyValid']);
        $t->same(false, $font['linkIntegrityPresent']);
        $t->same([], $font['linkFetchPolicyIssueCodes']);
        $t->same(true, $font['linkFetchPolicyValid']);

        $t->same('', $author['linkIntegrityRaw']);
        $t->same([], $author['linkIntegrityTokens']);
        $t->same(0, $author['linkIntegrityTokenCount']);
        $t->same(true, $author['linkIntegrityPresent']);
        $t->same(true, $author['linkIntegrityEmpty']);
        $t->same(false, $author['linkIntegrityAppliesToResource']);
        $t->same([], $author['linkIntegrityHashAlgorithms']);
        $t->same(['empty-link-integrity', 'link-integrity-without-fetch-resource'], $author['linkIntegrityIssueCodes']);
        $t->same(false, $author['linkIntegrityValid']);
        $t->same(['empty-link-integrity', 'link-integrity-without-fetch-resource'], $author['linkFetchPolicyIssueCodes']);
        $t->same([
            ['code' => 'empty-link-integrity'],
            ['code' => 'link-integrity-without-fetch-resource', 'relTokens' => []],
        ], $author['linkIssues']);
        $t->same(false, $author['linkFetchPolicyValid']);

        $t->same(['sha1-old', 'naked', 'sha384-good', 'sha384-good'], $badIntegrity['linkIntegrityTokens']);
        $t->same(['sha1', 'sha384'], $badIntegrity['linkIntegrityHashAlgorithms']);
        $t->same(['sha1'], $badIntegrity['unsupportedLinkIntegrityAlgorithms']);
        $t->same(['sha384-good'], $badIntegrity['duplicateLinkIntegrityTokens']);
        $t->same(null, $badIntegrity['linkIntegrityTokenRecords'][1]['algorithm']);
        $t->same(false, $badIntegrity['linkIntegrityTokenRecords'][1]['valid']);
        $t->same([
            'unsupported-link-integrity-algorithm',
            'malformed-link-integrity-token',
            'duplicate-link-integrity-token',
        ], $badIntegrity['linkIntegrityIssueCodes']);
        $t->same(false, $badIntegrity['linkIntegrityValid']);
        $t->same($badIntegrity['linkIntegrityIssueCodes'], $badIntegrity['linkFetchPolicyIssueCodes']);
        $t->same(false, $badIntegrity['linkFetchPolicyValid']);

        $t->same(null, $bad['linkCrossoriginState']);
        $t->same(false, $bad['linkCrossoriginValid']);
        $t->same(null, $bad['linkFetchPriority']);
        $t->same(false, $bad['linkFetchPriorityValid']);
        $t->same(null, $bad['linkReferrerPolicy']);
        $t->same(false, $bad['linkReferrerPolicyValid']);
        $t->same(['invalid-link-crossorigin', 'invalid-link-fetchpriority', 'invalid-link-referrerpolicy'], $bad['linkFetchPolicyIssueCodes']);
        $t->same(false, $bad['linkFetchPolicyValid']);

        $t->same(
            '<link as="script" crossorigin="Anonymous" fetchpriority="HIGH" href="/app.js" integrity="sha384-good sha512-better" referrerpolicy="strict-origin" rel="preload modulepreload">'
                . '<link as="font" crossorigin="" fetchpriority="low" href="/font.woff2" rel="preload">'
                . '<link href="/about" integrity="" rel="author">'
                . '<link as="script" href="/bad.js" integrity="sha1-old naked sha384-good sha384-good" rel="preload">'
                . '<link crossorigin="credentialed" fetchpriority="urgent" href="/bad.css" referrerpolicy="unsafe-policy" rel="stylesheet">',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/link-fetch-policy-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html link subresource integrity token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<link rel="modulepreload" href="/chunks/review.js" integrity="sha512-good sha512-good sha1-old naked" crossorigin="anonymous">',
            'link subresource integrity token review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/link-integrity-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $link = $summary[0];

        $t->same('link-fetch-policy-review', $link['linkFetchPolicyReviewPolicy']);
        $t->same('modulepreload', $link['linkPrimaryResourceKind']);
        $t->same(true, $link['linkIntegrityAppliesToResource']);
        $t->same(['sha512-good', 'sha512-good', 'sha1-old', 'naked'], $link['linkIntegrityTokens']);
        $t->same(['sha512', 'sha1'], $link['linkIntegrityHashAlgorithms']);
        $t->same(['sha1'], $link['unsupportedLinkIntegrityAlgorithms']);
        $t->same(['sha512-good'], $link['duplicateLinkIntegrityTokens']);
        $t->same([
            'duplicate-link-integrity-token',
            'unsupported-link-integrity-algorithm',
            'malformed-link-integrity-token',
        ], $link['linkIntegrityIssueCodes']);
        $t->same(false, $link['linkIntegrityValid']);
        $t->same($link['linkIntegrityIssueCodes'], $link['linkFetchPolicyIssueCodes']);
        $t->same('sha512', $link['linkIntegrityTokenRecords'][0]['algorithm']);
        $t->same(false, $link['linkIntegrityTokenRecords'][3]['valid']);
        $t->contains('integrity="sha512-good sha512-good sha1-old naked"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/link-integrity-review.html', $document->children[0]->attr('part'));
        json_encode($link, JSON_THROW_ON_ERROR);
    },
];
