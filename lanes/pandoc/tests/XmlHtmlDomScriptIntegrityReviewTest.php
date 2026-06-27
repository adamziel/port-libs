<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes script subresource integrity provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script id="boot" src="/app.js" integrity="sha384-good sha512-better" crossorigin="anonymous" referrerpolicy="strict-origin" fetchpriority="high"></script>'
                . '<script id="inline" integrity="sha256-inline">console.log(1)</script>'
                . '<script id="bad" src="/bad.js" integrity="sha1-old naked sha384-good sha384-good"></script>'
                . '<script id="empty" src="/empty.js" integrity></script>',
            'script subresource integrity review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/script-integrity-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $boot = $summary[0];
        $inline = $summary[1];
        $bad = $summary[2];
        $empty = $summary[3];

        $t->same('script-subresource-integrity-review', $boot['scriptIntegrityReviewPolicy']);
        $t->same('sha384-good sha512-better', $boot['scriptIntegrityRaw']);
        $t->same(['sha384-good', 'sha512-better'], $boot['scriptIntegrityTokens']);
        $t->same(2, $boot['scriptIntegrityTokenCount']);
        $t->same(true, $boot['scriptIntegrityAppliesToResource']);
        $t->same(['sha384', 'sha512'], $boot['scriptIntegrityHashAlgorithms']);
        $t->same([], $boot['unsupportedScriptIntegrityAlgorithms']);
        $t->same([], $boot['duplicateScriptIntegrityTokens']);
        $t->same('sha384', $boot['scriptIntegrityTokenRecords'][0]['algorithm']);
        $t->same(true, $boot['scriptIntegrityTokenRecords'][0]['algorithmSupported']);
        $t->same(true, $boot['scriptIntegrityTokenRecords'][0]['hashPresent']);
        $t->same([], $boot['scriptIntegrityIssueCodes']);
        $t->same(true, $boot['scriptIntegrityValid']);
        $t->same('anonymous', $boot['scriptCrossoriginState']);
        $t->same('strict-origin', $boot['scriptReferrerPolicy']);
        $t->same('high', $boot['scriptFetchPriority']);

        $t->same('sha256-inline', $inline['scriptIntegrityRaw']);
        $t->same(false, $inline['scriptIntegrityAppliesToResource']);
        $t->same(['sha256'], $inline['scriptIntegrityHashAlgorithms']);
        $t->same(['script-integrity-without-external-executable-source'], $inline['scriptIntegrityIssueCodes']);
        $t->same(false, $inline['scriptIntegrityValid']);
        $t->same('inline', $inline['scriptIntegrityIssues'][0]['sourceKind']);
        $t->same('classic', $inline['scriptIntegrityIssues'][0]['payloadKind']);

        $t->same(['sha1-old', 'naked', 'sha384-good', 'sha384-good'], $bad['scriptIntegrityTokens']);
        $t->same(['sha1', 'sha384'], $bad['scriptIntegrityHashAlgorithms']);
        $t->same(['sha1'], $bad['unsupportedScriptIntegrityAlgorithms']);
        $t->same(['sha384-good'], $bad['duplicateScriptIntegrityTokens']);
        $t->same(null, $bad['scriptIntegrityTokenRecords'][1]['algorithm']);
        $t->same(false, $bad['scriptIntegrityTokenRecords'][1]['valid']);
        $t->same([
            'unsupported-script-integrity-algorithm',
            'malformed-script-integrity-token',
            'duplicate-script-integrity-token',
        ], $bad['scriptIntegrityIssueCodes']);
        $t->same(false, $bad['scriptIntegrityValid']);

        $t->same('', $empty['scriptIntegrityRaw']);
        $t->same([], $empty['scriptIntegrityTokens']);
        $t->same(0, $empty['scriptIntegrityTokenCount']);
        $t->same(true, $empty['scriptIntegrityEmpty']);
        $t->same(['empty-script-integrity'], $empty['scriptIntegrityIssueCodes']);
        $t->same(false, $empty['scriptIntegrityValid']);

        $t->contains('integrity="sha384-good sha512-better"', $html);
        $t->contains('integrity="sha1-old naked sha384-good sha384-good"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/script-integrity-review.html', $document->children[0]->attr('part'));
        json_encode($bad, JSON_THROW_ON_ERROR);
    },
];
