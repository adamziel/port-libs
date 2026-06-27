<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes script blocking token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script id="critical" src="/critical.js" blocking="render render custom" fetchpriority="high"></script>'
                . '<script id="inline" blocking="layout">console.log("inline");</script>'
                . '<script id="data" type="speculationrules" blocking>{"prefetch":[{"source":"list","urls":["/next"]}]}</script>'
                . '<script id="plain" src="/plain.js"></script>',
            'script blocking review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/script-blocking-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $critical = $summary[0];
        $inline = $summary[1];
        $data = $summary[2];
        $plain = $summary[3];

        $t->same('script-loading-metadata-review', $critical['scriptLoadingReviewPolicy']);
        $t->same('render render custom', $critical['blockingRaw']);
        $t->same(['render', 'render', 'custom'], $critical['blockingTokens']);
        $t->same(['render' => 2, 'custom' => 1], $critical['scriptBlockingTokenCounts']);
        $t->same(['render'], $critical['duplicateScriptBlockingTokens']);
        $t->same(['custom'], $critical['invalidScriptBlockingTokens']);
        $t->same(false, $critical['scriptBlockingAllTokensValid']);
        $t->same([
            ['code' => 'invalid-script-blocking-token', 'token' => 'custom'],
            ['code' => 'duplicate-script-blocking-token', 'token' => 'render', 'count' => 2],
        ], $critical['scriptLoadingIssues']);
        $t->same([
            'invalid-script-blocking-token',
            'duplicate-script-blocking-token',
        ], $critical['scriptLoadingIssueCodes']);
        $t->same('external', $critical['scriptSourceKind']);
        $t->same('parser-blocking-classic', $critical['scriptLoadingMode']);
        $t->same('high', $critical['scriptFetchPriority']);

        $t->same('layout', $inline['blockingRaw']);
        $t->same(['layout' => 1], $inline['scriptBlockingTokenCounts']);
        $t->same([], $inline['duplicateScriptBlockingTokens']);
        $t->same(['layout'], $inline['invalidScriptBlockingTokens']);
        $t->same(false, $inline['scriptBlockingAllTokensValid']);
        $t->same(['invalid-script-blocking-token'], $inline['scriptLoadingIssueCodes']);
        $t->same('inline', $inline['scriptSourceKind']);
        $t->same('inline-executable', $inline['scriptLoadingMode']);

        $t->same('', $data['blockingRaw']);
        $t->same([], $data['blockingTokens']);
        $t->same([], $data['scriptBlockingTokenCounts']);
        $t->same([], $data['duplicateScriptBlockingTokens']);
        $t->same([], $data['invalidScriptBlockingTokens']);
        $t->same(true, $data['scriptBlockingAllTokensValid']);
        $t->same([], $data['scriptLoadingIssueCodes']);
        $t->same('speculationrules', $data['scriptPayloadKind']);
        $t->same('inert-data-block', $data['scriptLoadingMode']);
        $t->same(true, $data['scriptJsonParsed']);

        $t->same(null, $plain['blockingRaw']);
        $t->same([], $plain['blockingTokens']);
        $t->same([], $plain['scriptBlockingTokenCounts']);
        $t->same([], $plain['duplicateScriptBlockingTokens']);
        $t->same([], $plain['invalidScriptBlockingTokens']);
        $t->same(true, $plain['scriptBlockingAllTokensValid']);
        $t->same([], $plain['scriptLoadingIssues']);
        $t->same([], $plain['scriptLoadingIssueCodes']);

        $t->same(
            '<script blocking="render render custom" fetchpriority="high" id="critical" src="/critical.js"></script>'
                . '<script blocking="layout" id="inline">console.log("inline");</script>'
                . '<script blocking="" id="data" type="speculationrules">{"prefetch":[{"source":"list","urls":["/next"]}]}</script>'
                . '<script id="plain" src="/plain.js"></script>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/script-blocking-review.html', $document->children[0]->attr('part'));
        json_encode($critical, JSON_THROW_ON_ERROR);
    },
];
