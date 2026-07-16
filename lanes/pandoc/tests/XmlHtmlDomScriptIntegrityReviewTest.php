<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html script integrity provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script id="module" type="module" src="/app.mjs" integrity="sha384-good sha512-better"></script>'
                . '<script id="classic" src="/legacy.js" integrity="sha256-ok bad&lt;token"></script>'
                . '<script id="empty" src="/empty.js" integrity=""></script>'
                . '<script id="inline" integrity="sha384-inline">console.log("inline")</script>'
                . '<script id="json" type="application/json" src="/data.json" integrity="sha384-json">{"ok":true}</script>'
                . '<script id="plain" src="/plain.js"></script>',
            'script integrity review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/script-integrity-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $module = $summary[0];
        $classic = $summary[1];
        $empty = $summary[2];
        $inline = $summary[3];
        $json = $summary[4];
        $plain = $summary[5];

        $t->same('script-subresource-integrity-review', $module['scriptIntegrityReviewPolicy']);
        $t->same('sha384-good sha512-better', $module['scriptIntegrityRaw']);
        $t->same(['sha384-good', 'sha512-better'], $module['scriptIntegrityTokens']);
        $t->same(2, $module['scriptIntegrityTokenCount']);
        $t->same(['sha384', 'sha512'], $module['scriptIntegrityAlgorithms']);
        $t->same(['sha384' => 1, 'sha512' => 1], $module['scriptIntegrityAlgorithmCounts']);
        $t->same([], $module['invalidScriptIntegrityTokens']);
        $t->same(true, $module['scriptIntegrityAppliesToExternalExecutable']);
        $t->same([], $module['scriptIntegrityIssues']);
        $t->same([], $module['scriptIntegrityIssueCodes']);
        $t->same(true, $module['scriptIntegrityValid']);

        $t->same('sha256-ok bad<token', $classic['scriptIntegrityRaw']);
        $t->same(['sha256-ok', 'bad<token'], $classic['scriptIntegrityTokens']);
        $t->same(['sha256'], $classic['scriptIntegrityAlgorithms']);
        $t->same(['sha256' => 1], $classic['scriptIntegrityAlgorithmCounts']);
        $t->same(['bad<token'], $classic['invalidScriptIntegrityTokens']);
        $t->same(true, $classic['scriptIntegrityAppliesToExternalExecutable']);
        $t->same([
            ['code' => 'invalid-script-integrity-token', 'token' => 'bad<token'],
        ], $classic['scriptIntegrityIssues']);
        $t->same(['invalid-script-integrity-token'], $classic['scriptIntegrityIssueCodes']);
        $t->same(false, $classic['scriptIntegrityValid']);

        $t->same('', $empty['scriptIntegrityRaw']);
        $t->same([], $empty['scriptIntegrityTokens']);
        $t->same(0, $empty['scriptIntegrityTokenCount']);
        $t->same(true, $empty['scriptIntegrityEmpty']);
        $t->same(true, $empty['scriptIntegrityAppliesToExternalExecutable']);
        $t->same(['empty-script-integrity'], $empty['scriptIntegrityIssueCodes']);
        $t->same(false, $empty['scriptIntegrityValid']);

        $t->same('inline', $inline['scriptSourceKind']);
        $t->same('classic', $inline['scriptPayloadKind']);
        $t->same(['sha384'], $inline['scriptIntegrityAlgorithms']);
        $t->same(false, $inline['scriptIntegrityAppliesToExternalExecutable']);
        $t->same([
            [
                'code' => 'script-integrity-without-external-executable',
                'scriptPayloadKind' => 'classic',
                'scriptSourceKind' => 'inline',
            ],
        ], $inline['scriptIntegrityIssues']);
        $t->same(['script-integrity-without-external-executable'], $inline['scriptIntegrityIssueCodes']);
        $t->same(false, $inline['scriptIntegrityValid']);

        $t->same('json-data', $json['scriptPayloadKind']);
        $t->same('external', $json['scriptSourceKind']);
        $t->same(['sha384'], $json['scriptIntegrityAlgorithms']);
        $t->same(false, $json['scriptIntegrityAppliesToExternalExecutable']);
        $t->same(['script-integrity-without-external-executable'], $json['scriptIntegrityIssueCodes']);
        $t->same(false, $json['scriptIntegrityValid']);

        $t->true(!array_key_exists('scriptIntegrityReviewPolicy', $plain));
        $t->true(!array_key_exists('scriptIntegrityTokens', $plain));

        $t->contains('integrity="sha384-good sha512-better"', $html);
        $t->contains('integrity="sha256-ok bad&lt;token"', $html);
        $t->contains('integrity=""', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/script-integrity-review.html', $document->children[0]->attr('part'));
    },
];
