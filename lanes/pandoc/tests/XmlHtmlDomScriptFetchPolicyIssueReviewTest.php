<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes script fetch policy issue codes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script id="bad" src="/bad.js" crossorigin="credentialed" fetchpriority="urgent" referrerpolicy="leaky" blocking="layout"></script>'
                . '<script id="good" src="/app.js" crossorigin referrerpolicy="origin-when-cross-origin" fetchpriority="low"></script>',
            'script fetch policy issue review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/script-fetch-policy-issue-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $bad = $summary[0];
        $good = $summary[1];

        $t->same('script-loading-metadata-review', $bad['scriptLoadingReviewPolicy']);
        $t->same('credentialed', $bad['crossorigin']);
        $t->same(null, $bad['scriptCrossoriginState']);
        $t->same(false, $bad['scriptCrossoriginValid']);
        $t->same('urgent', $bad['fetchpriority']);
        $t->same(null, $bad['scriptFetchPriority']);
        $t->same(false, $bad['scriptFetchPriorityValid']);
        $t->same('leaky', $bad['referrerpolicy']);
        $t->same(null, $bad['scriptReferrerPolicy']);
        $t->same(false, $bad['scriptReferrerPolicyValid']);
        $t->same(['layout' => 1], $bad['scriptBlockingTokenCounts']);
        $t->same(['layout'], $bad['invalidScriptBlockingTokens']);
        $t->same([
            'invalid-script-crossorigin',
            'invalid-script-fetchpriority',
            'invalid-script-referrerpolicy',
            'invalid-script-blocking-token',
        ], $bad['scriptLoadingIssueCodes']);
        $t->same([
            ['code' => 'invalid-script-crossorigin', 'crossoriginRaw' => 'credentialed'],
            ['code' => 'invalid-script-fetchpriority', 'fetchpriorityRaw' => 'urgent'],
            ['code' => 'invalid-script-referrerpolicy', 'referrerpolicyRaw' => 'leaky'],
            ['code' => 'invalid-script-blocking-token', 'token' => 'layout'],
        ], $bad['scriptLoadingIssues']);

        $t->same('', $good['crossorigin']);
        $t->same('anonymous', $good['scriptCrossoriginState']);
        $t->same(true, $good['scriptCrossoriginValid']);
        $t->same('origin-when-cross-origin', $good['scriptReferrerPolicy']);
        $t->same(true, $good['scriptReferrerPolicyValid']);
        $t->same('low', $good['scriptFetchPriority']);
        $t->same(true, $good['scriptFetchPriorityValid']);
        $t->same([], $good['scriptLoadingIssueCodes']);
        $t->same([], $good['scriptLoadingIssues']);

        $t->same(
            '<script blocking="layout" crossorigin="credentialed" fetchpriority="urgent" id="bad" referrerpolicy="leaky" src="/bad.js"></script>'
                . '<script crossorigin="" fetchpriority="low" id="good" referrerpolicy="origin-when-cross-origin" src="/app.js"></script>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/script-fetch-policy-issue-review.html', $document->children[0]->attr('part'));
        json_encode($bad, JSON_THROW_ON_ERROR);
    },
];
