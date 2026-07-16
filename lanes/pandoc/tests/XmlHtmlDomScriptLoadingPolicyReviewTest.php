<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html script loading issue diagnostics for reviewer handoff' => static function (TestRunner $t): void {
        $speculationRulesSource = '{"prefetch":[{"source":"list","urls":["/next"]}]}';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script id="classic" src="/app.js" crossorigin="credentialed" fetchpriority="urgent" referrerpolicy="unsafe-policy" blocking="render render bad-token"></script>'
                . '<script id="module" type="module" src="/app.mjs" async crossorigin="use-credentials" fetchpriority="low" referrerpolicy="strict-origin" blocking="render"></script>'
                . '<script id="rules" type="speculationrules" blocking="render render custom">' . $speculationRulesSource . '</script>'
                . '<script id="inline">console.log("ok")</script>',
            'script loading policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/script-loading-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $classic = $summary[0];
        $module = $summary[1];
        $rules = $summary[2];
        $inline = $summary[3];

        $t->same('script-loading-metadata-review', $classic['scriptLoadingReviewPolicy']);
        $t->same('parser-blocking-classic', $classic['scriptLoadingMode']);
        $t->same('credentialed', $classic['scriptCrossoriginRaw']);
        $t->same(null, $classic['scriptCrossoriginState']);
        $t->same(false, $classic['scriptCrossoriginValid']);
        $t->same('urgent', $classic['scriptFetchPriorityRaw']);
        $t->same(null, $classic['scriptFetchPriority']);
        $t->same(false, $classic['scriptFetchPriorityValid']);
        $t->same('unsafe-policy', $classic['scriptReferrerPolicyRaw']);
        $t->same(null, $classic['scriptReferrerPolicy']);
        $t->same(false, $classic['scriptReferrerPolicyValid']);
        $t->same(true, $classic['scriptBlockingAttributePresent']);
        $t->same(['render', 'render', 'bad-token'], $classic['scriptBlockingTokens']);
        $t->same(['render' => 2, 'bad-token' => 1], $classic['scriptBlockingTokenCounts']);
        $t->same(['render'], $classic['duplicateScriptBlockingTokens']);
        $t->same(['bad-token'], $classic['invalidScriptBlockingTokens']);
        $t->same(true, $classic['scriptRenderBlockingTokenPresent']);
        $t->same(false, $classic['scriptBlockingAllTokensValid']);
        $t->same([
            'invalid-script-crossorigin',
            'invalid-script-fetchpriority',
            'invalid-script-referrerpolicy',
            'invalid-script-blocking-token',
            'duplicate-script-blocking-token',
        ], $classic['scriptLoadingIssueCodes']);
        $t->same([
            ['code' => 'invalid-script-crossorigin', 'value' => 'credentialed'],
            ['code' => 'invalid-script-fetchpriority', 'value' => 'urgent'],
            ['code' => 'invalid-script-referrerpolicy', 'value' => 'unsafe-policy'],
            ['code' => 'invalid-script-blocking-token', 'token' => 'bad-token'],
            ['code' => 'duplicate-script-blocking-token', 'token' => 'render', 'count' => 2],
        ], $classic['scriptLoadingIssues']);
        $t->same(false, $classic['scriptLoadingPolicyValid']);

        $t->same('async-module', $module['scriptLoadingMode']);
        $t->same('use-credentials', $module['scriptCrossoriginRaw']);
        $t->same('use-credentials', $module['scriptCrossoriginState']);
        $t->same(true, $module['scriptCrossoriginValid']);
        $t->same('low', $module['scriptFetchPriorityRaw']);
        $t->same('low', $module['scriptFetchPriority']);
        $t->same(true, $module['scriptFetchPriorityValid']);
        $t->same('strict-origin', $module['scriptReferrerPolicyRaw']);
        $t->same('strict-origin', $module['scriptReferrerPolicy']);
        $t->same(true, $module['scriptReferrerPolicyValid']);
        $t->same(true, $module['scriptBlockingAttributePresent']);
        $t->same(['render'], $module['scriptBlockingTokens']);
        $t->same(['render' => 1], $module['scriptBlockingTokenCounts']);
        $t->same([], $module['duplicateScriptBlockingTokens']);
        $t->same([], $module['invalidScriptBlockingTokens']);
        $t->same(true, $module['scriptRenderBlockingTokenPresent']);
        $t->same(true, $module['scriptBlockingAllTokensValid']);
        $t->same([], $module['scriptLoadingIssues']);
        $t->same([], $module['scriptLoadingIssueCodes']);
        $t->same(true, $module['scriptLoadingPolicyValid']);

        $t->same('speculationrules', $rules['scriptPayloadKind']);
        $t->same('inert-data-block', $rules['scriptLoadingMode']);
        $t->same(null, $rules['scriptCrossoriginRaw']);
        $t->same(null, $rules['scriptFetchPriorityRaw']);
        $t->same(null, $rules['scriptReferrerPolicyRaw']);
        $t->same(true, $rules['scriptBlockingAttributePresent']);
        $t->same(['render', 'render', 'custom'], $rules['scriptBlockingTokens']);
        $t->same(['render' => 2, 'custom' => 1], $rules['scriptBlockingTokenCounts']);
        $t->same(['render'], $rules['duplicateScriptBlockingTokens']);
        $t->same(['custom'], $rules['invalidScriptBlockingTokens']);
        $t->same(true, $rules['scriptRenderBlockingTokenPresent']);
        $t->same(false, $rules['scriptBlockingAllTokensValid']);
        $t->same([
            'invalid-script-blocking-token',
            'duplicate-script-blocking-token',
        ], $rules['scriptLoadingIssueCodes']);
        $t->same(true, $rules['scriptJsonParsed']);
        $t->same('object', $rules['scriptJsonType']);
        $t->same(['prefetch'], $rules['scriptJsonObjectKeys']);
        $t->same(['prefetch'], $rules['speculationRuleSetNames']);
        $t->same(['prefetch' => 1], $rules['speculationRuleSetCounts']);

        $t->same('inline-executable', $inline['scriptLoadingMode']);
        $t->same(null, $inline['scriptCrossoriginRaw']);
        $t->same(null, $inline['scriptFetchPriorityRaw']);
        $t->same(null, $inline['scriptReferrerPolicyRaw']);
        $t->same(false, $inline['scriptBlockingAttributePresent']);
        $t->same([], $inline['scriptBlockingTokens']);
        $t->same(false, $inline['scriptRenderBlockingTokenPresent']);
        $t->same([], $inline['scriptLoadingIssues']);
        $t->same(true, $inline['scriptLoadingPolicyValid']);

        $t->contains('blocking="render render bad-token"', $html);
        $t->contains('blocking="render render custom"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/script-loading-policy-review.html', $document->children[0]->attr('part'));
    },
];
