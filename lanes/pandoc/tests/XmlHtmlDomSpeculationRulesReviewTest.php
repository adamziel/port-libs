<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes speculation rules target provenance for reviewer handoff' => static function (TestRunner $t): void {
        $source = json_encode([
            'prefetch' => [
                [
                    'source' => 'list',
                    'urls' => ['/next', 'https://cdn.example.test/data.json', 'javascript:steal()'],
                    'eagerness' => 'moderate',
                    'referrer_policy' => 'strict-origin',
                ],
                [
                    'source' => 'document',
                    'where' => ['selector_matches' => '.article-link'],
                    'eagerness' => 'immediate',
                    'requires' => ['anonymous-client-ip-when-cross-origin', 42],
                ],
                [
                    'source' => 'list',
                    'urls' => ['', ['bad' => 'array']],
                ],
            ],
            'prerender' => [
                [
                    'source' => 'list',
                    'urls' => ['/checkout'],
                    'eagerness' => 'urgent',
                    'referrer_policy' => 'bad-policy',
                ],
                'not-object',
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $invalidSource = json_encode([
            'prefetch' => ['source' => 'list', 'urls' => ['/nope']],
            'prerender' => 'bad-set',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script id="rules" type="speculationrules">' . $source . '</script>'
                . '<script id="invalid-rules" type="speculationrules">' . $invalidSource . '</script>',
            'speculation rules review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/speculation-rules-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $rules = $summary[0];
        $invalid = $summary[1];

        $t->same('script', $rules['name']);
        $t->same('speculationrules', $rules['scriptPayloadKind']);
        $t->same(true, $rules['scriptJsonParsed']);
        $t->same(['prefetch', 'prerender'], $rules['scriptJsonObjectKeys']);
        $t->same('speculation-rules-target-provenance-review', $rules['speculationRulesReviewPolicy']);
        $t->same(['prefetch', 'prerender'], $rules['speculationRuleSetNames']);
        $t->same(['prefetch' => 3, 'prerender' => 2], $rules['speculationRuleSetCounts']);
        $t->same(5, $rules['speculationRuleCount']);
        $t->same(6, $rules['speculationRuleUrlCount']);
        $t->same(['javascript:steal()'], $rules['unsafeSpeculationRuleUrls']);
        $t->same(false, $rules['speculationRulesBrowserExecution']);
        $t->same(false, $rules['speculationRulesFetchesResources']);
        $t->same(false, $rules['speculationRulesValid']);

        $list = $rules['speculationRuleRecords'][0];
        $documentRule = $rules['speculationRuleRecords'][1];
        $badUrls = $rules['speculationRuleRecords'][2];
        $badPrerender = $rules['speculationRuleRecords'][3];
        $nonObject = $rules['speculationRuleRecords'][4];

        $t->same('prefetch', $list['ruleSet']);
        $t->same('list', $list['source']);
        $t->same('list', $list['sourceKind']);
        $t->same(['/next', 'https://cdn.example.test/data.json', 'javascript:steal()'], $list['urls']);
        $t->same('relative', $list['urlRecords'][0]['kind']);
        $t->same('https', $list['urlRecords'][1]['scheme']);
        $t->same('javascript', $list['urlRecords'][2]['scheme']);
        $t->same(true, $list['urlRecords'][2]['unsafe']);
        $t->same(['unsafe-speculation-rule-url'], $list['issueCodes']);
        $t->same('moderate', $list['eagerness']);
        $t->same('strict-origin', $list['referrerPolicy']);
        $t->same(false, $list['valid']);

        $t->same('document', $documentRule['sourceKind']);
        $t->same(true, $documentRule['wherePresent']);
        $t->same('object', $documentRule['whereType']);
        $t->same(['anonymous-client-ip-when-cross-origin'], $documentRule['requiresTokens']);
        $t->same(1, $documentRule['requiresTokenCount']);
        $t->same([], $documentRule['issueCodes']);
        $t->same(true, $documentRule['valid']);

        $t->same([''], $badUrls['urls']);
        $t->same(['empty-speculation-rule-url'], $badUrls['urlRecords'][0]['issueCodes']);
        $t->same(['non-string-speculation-rule-url'], $badUrls['urlRecords'][1]['issueCodes']);
        $t->same([
            'empty-speculation-rule-url',
            'non-string-speculation-rule-url',
        ], $badUrls['issueCodes']);

        $t->same('prerender', $badPrerender['ruleSet']);
        $t->same('/checkout', $badPrerender['urls'][0]);
        $t->same(null, $badPrerender['eagerness']);
        $t->same(null, $badPrerender['referrerPolicy']);
        $t->same([
            'invalid-speculation-rule-eagerness',
            'invalid-speculation-rule-referrer-policy',
        ], $badPrerender['issueCodes']);

        $t->same('non-object', $nonObject['sourceKind']);
        $t->same(['non-object-speculation-rule'], $nonObject['issueCodes']);

        $t->same([
            'unsafe-speculation-rule-url',
            'empty-speculation-rule-url',
            'non-string-speculation-rule-url',
            'invalid-speculation-rule-eagerness',
            'invalid-speculation-rule-referrer-policy',
            'non-object-speculation-rule',
        ], $rules['speculationRuleIssueCodes']);
        $t->same([], $rules['scriptJsonDiagnostics']);

        $t->same(['speculationrules-prefetch-not-array', 'speculationrules-prerender-not-array'], $invalid['scriptJsonDiagnostics']);
        $t->same(['prefetch' => null, 'prerender' => null], $invalid['speculationRuleSetCounts']);
        $t->same([], $invalid['speculationRuleRecords']);
        $t->same([
            'invalid-speculation-rule-set',
        ], $invalid['speculationRuleIssueCodes']);
        $t->same(false, $invalid['speculationRulesValid']);

        $t->contains('"prefetch":[{"source":"list","urls":["/next"', $html);
        $t->contains('type="speculationrules"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/speculation-rules-review.html', $document->children[0]->attr('part'));
        json_encode($rules, JSON_THROW_ON_ERROR);
    },
];
