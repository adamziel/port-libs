<?php

declare(strict_types=1);

use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html datalist association issue rollups for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form>'
                . '<input id="resolved" name="color" list="colors">'
                . '<input id="missing" name="format" list="missing-options">'
                . '<input id="invalid" name="bad" list="bad id">'
                . '<input id="duplicate" name="archive" list="dupe-options">'
                . '<datalist id="colors"><option value="red" label="Red"></option><option>Blue</option></datalist>'
                . '<datalist id="dupe-options"><option value="csv"></option></datalist>'
                . '<datalist id="dupe-options"><option value="tsv"></option></datalist>'
                . '</form>',
            'datalist association issue rollup review fragment'
        );

        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $form = $summary[0];
        $resolved = $form['children'][0];
        $missing = $form['children'][1];
        $invalid = $form['children'][2];
        $duplicate = $form['children'][3];

        $t->same('input-list-datalist-idref-review', $resolved['datalistReviewPolicy']);
        $t->same('resolved', $resolved['datalistAssociationState']);
        $t->same(0, $resolved['datalistIssueCount']);
        $t->same([], $resolved['datalistIssueCodeCounts']);
        $t->same([], $resolved['datalistIssueReferenceIds']);
        $t->same([], $resolved['datalistIssueReferenceRaws']);
        $t->same([], $resolved['datalistDuplicateTargetCounts']);
        $t->same([], $resolved['datalistIssueCodes']);

        $t->same('missing-datalist', $missing['datalistAssociationState']);
        $t->same(1, $missing['datalistIssueCount']);
        $t->same(['missing-datalist-target' => 1], $missing['datalistIssueCodeCounts']);
        $t->same(['missing-options'], $missing['datalistIssueReferenceIds']);
        $t->same([], $missing['datalistIssueReferenceRaws']);
        $t->same([], $missing['datalistDuplicateTargetCounts']);
        $t->same(['missing-datalist-target'], $missing['datalistIssueCodes']);

        $t->same('invalid-reference', $invalid['datalistAssociationState']);
        $t->same(1, $invalid['datalistIssueCount']);
        $t->same(['invalid-datalist-list-reference' => 1], $invalid['datalistIssueCodeCounts']);
        $t->same([], $invalid['datalistIssueReferenceIds']);
        $t->same(['bad id'], $invalid['datalistIssueReferenceRaws']);
        $t->same([], $invalid['datalistDuplicateTargetCounts']);
        $t->same(['invalid-datalist-list-reference'], $invalid['datalistIssueCodes']);

        $t->same('duplicate-datalist', $duplicate['datalistAssociationState']);
        $t->same(1, $duplicate['datalistIssueCount']);
        $t->same(['duplicate-datalist-target-id' => 1], $duplicate['datalistIssueCodeCounts']);
        $t->same(['dupe-options'], $duplicate['datalistIssueReferenceIds']);
        $t->same([], $duplicate['datalistIssueReferenceRaws']);
        $t->same(['dupe-options' => 2], $duplicate['datalistDuplicateTargetCounts']);
        $t->same(['duplicate-datalist-target-id'], $duplicate['datalistIssueCodes']);

        json_encode([$resolved, $missing, $invalid, $duplicate], JSON_THROW_ON_ERROR);
    },
];
