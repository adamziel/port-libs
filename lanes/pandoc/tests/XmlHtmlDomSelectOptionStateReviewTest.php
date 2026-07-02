<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html select option state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="prefs">'
                . '<select id="required" name="status" required><option value="">Choose</option><option value="draft">Draft</option></select>'
                . '<select id="country" name="country" required><option value="">Choose</option><option selected value="ca">Canada</option></select>'
                . '<select id="dupe" name="dupe"><option selected value="a">A</option><option selected disabled value="b">B</option></select>'
                . '<select id="multi" name="multi" multiple required><optgroup label="Closed" disabled><option selected value="archived">Archived</option></optgroup><option value="open">Open</option></select>'
                . '</form>',
            'select option state review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/select-option-state-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $required = $form['children'][0];
        $country = $form['children'][1];
        $dupe = $form['children'][2];
        $multi = $form['children'][3];

        $t->same('select', $required['formControl']);
        $t->same('html-select-option-state-review', $required['selectOptionStateReviewPolicy']);
        $t->same(true, $required['selectReviewOnlyNoBrowserStateMutation']);
        $t->same(false, $required['selectMultiple']);
        $t->same(true, $required['selectRequired']);
        $t->same(false, $required['selectEffectiveDisabled']);
        $t->same(2, $required['selectOptionCount']);
        $t->same(2, $required['selectEnabledOptionCount']);
        $t->same(0, $required['selectDisabledOptionCount']);
        $t->same(0, $required['selectExplicitSelectedOptionCount']);
        $t->same([], $required['selectedValues']);
        $t->same([], $required['selectExplicitSelectedValues']);
        $t->same('first-option-fallback', $required['selectSelectionSource']);
        $t->same(1, $required['selectEffectiveSelectedOptionCount']);
        $t->same([''], $required['selectEffectiveSelectedValues']);
        $t->same(['Choose'], $required['selectEffectiveSelectedLabels']);
        $t->same(['Choose'], $required['selectEffectiveSelectedTexts']);
        $t->same([], $required['selectSuccessfulValueCandidates']);
        $t->same(true, $required['selectRequiredValueMissing']);
        $t->same(1, $required['selectIssueCount']);
        $t->same(['required-select-missing-value' => 1], $required['selectIssueCodeCounts']);
        $t->same(['required-select-missing-value' => ['']], $required['selectIssueValuesByCode']);
        $t->same([], $required['selectIssueGroupsByCode']);
        $t->same(['required-select-missing-value' => ['first-option-fallback']], $required['selectIssueSelectionSourcesByCode']);
        $t->same(['required-select-missing-value'], $required['selectIssueCodes']);
        $t->same(false, $required['selectOptionStateValid']);

        $t->same(['ca'], $country['selectedValues']);
        $t->same(['ca'], $country['selectExplicitSelectedValues']);
        $t->same('selected-attribute', $country['selectSelectionSource']);
        $t->same(['ca'], $country['selectEffectiveSelectedValues']);
        $t->same(['ca'], $country['selectSuccessfulValueCandidates']);
        $t->same(false, $country['selectRequiredValueMissing']);
        $t->same(0, $country['selectIssueCount']);
        $t->same([], $country['selectIssueCodeCounts']);
        $t->same([], $country['selectIssueValuesByCode']);
        $t->same([], $country['selectIssueGroupsByCode']);
        $t->same([], $country['selectIssueSelectionSourcesByCode']);
        $t->same([], $country['selectIssueCodes']);
        $t->same(true, $country['selectOptionStateValid']);

        $t->same(['a', 'b'], $dupe['selectedValues']);
        $t->same('conflicting-selected-attributes', $dupe['selectSelectionSource']);
        $t->same(2, $dupe['selectExplicitSelectedOptionCount']);
        $t->same(['a', 'b'], $dupe['selectEffectiveSelectedValues']);
        $t->same(1, $dupe['selectSelectedDisabledOptionCount']);
        $t->same(['b'], $dupe['selectSelectedDisabledValues']);
        $t->same(2, $dupe['selectIssueCount']);
        $t->same([
            'multiple-selected-options-for-single-select' => 1,
            'selected-disabled-option' => 1,
        ], $dupe['selectIssueCodeCounts']);
        $t->same([
            'multiple-selected-options-for-single-select' => ['a', 'b'],
            'selected-disabled-option' => ['b'],
        ], $dupe['selectIssueValuesByCode']);
        $t->same([], $dupe['selectIssueGroupsByCode']);
        $t->same([], $dupe['selectIssueSelectionSourcesByCode']);
        $t->same([
            'multiple-selected-options-for-single-select',
            'selected-disabled-option',
        ], $dupe['selectIssueCodes']);
        $t->same(false, $dupe['selectOptionStateValid']);

        $t->same(true, $multi['selectMultiple']);
        $t->same(true, $multi['selectRequired']);
        $t->same(2, $multi['selectOptionCount']);
        $t->same(1, $multi['selectEnabledOptionCount']);
        $t->same(1, $multi['selectDisabledOptionCount']);
        $t->same(['archived'], $multi['selectExplicitSelectedValues']);
        $t->same(['archived'], $multi['selectEffectiveSelectedValues']);
        $t->same([], $multi['selectSuccessfulValueCandidates']);
        $t->same(true, $multi['selectRequiredValueMissing']);
        $t->same(2, $multi['selectIssueCount']);
        $t->same([
            'required-select-missing-value' => 1,
            'selected-disabled-option' => 1,
        ], $multi['selectIssueCodeCounts']);
        $t->same([
            'required-select-missing-value' => ['archived'],
            'selected-disabled-option' => ['archived'],
        ], $multi['selectIssueValuesByCode']);
        $t->same(['selected-disabled-option' => ['Closed']], $multi['selectIssueGroupsByCode']);
        $t->same(['required-select-missing-value' => ['selected-attribute']], $multi['selectIssueSelectionSourcesByCode']);
        $t->same([
            'selected-disabled-option',
            'required-select-missing-value',
        ], $multi['selectIssueCodes']);
        $t->same([
            'code' => 'selected-disabled-option',
            'value' => 'archived',
            'label' => 'Archived',
            'group' => 'Closed',
            'groupDisabled' => true,
        ], $multi['selectIssues'][0]);
        $t->same(false, $multi['selectOptionStateValid']);

        $t->contains('id="multi"', $html);
        $t->contains('multiple', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/select-option-state-review.html', $document->children[0]->attr('part'));
        json_encode([$required, $country, $dupe, $multi], JSON_THROW_ON_ERROR);
    },
];
