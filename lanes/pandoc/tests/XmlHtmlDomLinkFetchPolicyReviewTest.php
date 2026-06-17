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
        $bad = $summary[3];

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
        $t->same(['empty-link-integrity', 'link-integrity-without-fetch-resource'], $author['linkFetchPolicyIssueCodes']);
        $t->same([
            ['code' => 'empty-link-integrity'],
            ['code' => 'link-integrity-without-fetch-resource', 'relTokens' => []],
        ], $author['linkIssues']);
        $t->same(false, $author['linkFetchPolicyValid']);

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
                . '<link crossorigin="credentialed" fetchpriority="urgent" href="/bad.css" referrerpolicy="unsafe-policy" rel="stylesheet">',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/link-fetch-policy-review.html', $document->children[0]->attr('part'));
    },
];
