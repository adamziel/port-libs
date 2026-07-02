<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'records mapped xml html dom output for reference rollup case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedXmlHtmlDomOutputForReferenceRollupCases'] ?? null);
        $t->same(57, $manifest['xmlHtmlDomOutputForReferenceRollupAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedXmlHtmlDomOutputForReferenceRollupCases'] ?? null);
        $t->same(57, $manifest['benchmarkDenominator']['breakdown']['xmlHtmlDomOutputForReferenceRollupAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedXmlHtmlDomOutputForReferenceRollupCases'] ?? null);
        $t->same(57, $manifest['benchmarkDenominator']['inventory']['xmlHtmlDomOutputForReferenceRollupAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedXmlHtmlDomOutputForReferenceRollupCases'] ?? null);
        $t->same(57, $manifest['inventory']['xmlHtmlDomOutputForReferenceRollupAssertions'] ?? null);
    },

    'rolls up html output for reference targets without browser state' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="calc">'
                . '<input id="price" name="price" type="number" value="5.00">'
                . '<input id="qty" name="qty" type="number" value="2">'
                . '<select id="tax" name="tax"><option value="none">None</option><option selected value="local">Local</option></select>'
                . '<textarea id="notes" name="notes">Manual override</textarea>'
                . '<span id="note">Not a control</span>'
                . '<output id="total" name="total" for="price qty tax price note missing bad&lt;tag notes">Ready</output>'
                . '</form>',
            'output for reference rollup fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/output-for-reference-rollup.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $output = $summary[0]['children'][5];

        $t->same('output', $output['formControl']);
        $t->same('output-for-idref-review', $output['forReferenceReviewPolicy']);
        $t->same(8, $output['forReferenceTokenCount']);
        $t->same(7, $output['forReferenceValidTokenCount']);
        $t->same(['price', 'qty', 'tax', 'note', 'missing', 'notes'], $output['forReferenceIds']);
        $t->same(6, $output['forReferenceIdCount']);
        $t->same(['bad<tag'], $output['invalidForReferenceTokens']);
        $t->same(1, $output['invalidForReferenceTokenCount']);
        $t->same(['price'], $output['duplicateForReferenceIds']);
        $t->same(1, $output['duplicateForReferenceIdCount']);
        $t->same(['price', 'qty', 'tax', 'notes'], $output['resolvedForReferenceIds']);
        $t->same(4, $output['resolvedForReferenceIdCount']);
        $t->same(['missing'], $output['missingForReferenceIds']);
        $t->same(1, $output['missingForReferenceIdCount']);
        $t->same(['note'], $output['nonControlForReferenceIds']);
        $t->same(1, $output['nonControlForReferenceIdCount']);
        $t->same([
            'invalid-token' => 1,
            'missing-target' => 1,
            'non-control-target' => 1,
            'resolved-control' => 5,
        ], $output['forReferenceStateCounts']);
        $t->same(['input', 'select', 'span', 'textarea'], $output['forReferenceTargetTags']);
        $t->same(4, $output['forControlReferenceCount']);
        $t->same(['price', 'qty', 'tax', 'notes'], $output['forControlNames']);
        $t->same(['input:number', 'select', 'textarea'], $output['forControlTypes']);
        $t->same(4, $output['forReferenceIssueCount']);
        $t->same([
            'duplicate-output-for-reference-token',
            'non-control-output-for-target',
            'missing-output-for-target',
            'invalid-output-for-reference-token',
        ], $output['forReferenceIssueCodes']);
        $t->same(false, $output['forReferencesResolved']);

        $t->same('resolved-control', $output['forReferences'][0]['state']);
        $t->same('number', $output['forReferences'][0]['target']['inputType']);
        $t->same('5.00', $output['forReferences'][0]['target']['value']);
        $t->same('select', $output['forReferences'][2]['target']['tag']);
        $t->same(['local'], $output['forReferences'][2]['target']['selectedValues']);
        $t->same(true, $output['forReferences'][3]['duplicateToken']);
        $t->same(0, $output['forReferences'][3]['firstIndex']);
        $t->same('non-control-target', $output['forReferences'][4]['state']);
        $t->same('span', $output['forReferences'][4]['target']['tag']);
        $t->same('missing-target', $output['forReferences'][5]['state']);
        $t->same('invalid-token', $output['forReferences'][6]['state']);
        $t->same('textarea', $output['forReferences'][7]['target']['tag']);
        $t->same('Manual override', $output['forReferences'][7]['target']['value']);

        $t->same('duplicate-output-for-reference-token', $output['forReferenceIssues'][0]['code']);
        $t->same('price', $output['forReferenceIssues'][0]['token']);
        $t->same(3, $output['forReferenceIssues'][0]['index']);
        $t->same('non-control-output-for-target', $output['forReferenceIssues'][1]['code']);
        $t->same('note', $output['forReferenceIssues'][1]['token']);
        $t->same('missing-output-for-target', $output['forReferenceIssues'][2]['code']);
        $t->same('missing', $output['forReferenceIssues'][2]['token']);
        $t->same('invalid-output-for-reference-token', $output['forReferenceIssues'][3]['code']);
        $t->same('bad<tag', $output['forReferenceIssues'][3]['token']);

        $t->contains('for="price qty tax price note missing bad&lt;tag notes"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/output-for-reference-rollup.html', $document->children[0]->attr('part'));
    },
];
