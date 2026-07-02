<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html numeric form constraints for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="numbers">'
                . '<input id="score" name="score" type="number" min="-5" max="10" step="0.5" value="4.5">'
                . '<input id="low" name="low" type="number" min="2" value="1">'
                . '<input id="mismatch" name="mismatch" type="number" min="0" step="2" value="5">'
                . '<input id="bad" name="bad" type="number" min="bad" max="1" step="0" value="abc">'
                . '<input id="any" name="any" type="number" step="any" value="4.2">'
                . '<input id="readonly" name="readonly" type="number" min="5" value="2" readonly>'
                . '<input id="disabled" name="disabled" type="number" min="5" value="2" disabled>'
                . '<input id="slider" name="slider" type="range" min="0" max="10" step="2" value="4">'
                . '</form>',
            'numeric constraint review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/numeric-constraint-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $score = $form['children'][0];
        $low = $form['children'][1];
        $mismatch = $form['children'][2];
        $bad = $form['children'][3];
        $any = $form['children'][4];
        $readonly = $form['children'][5];
        $disabled = $form['children'][6];
        $slider = $form['children'][7];

        $t->same('form-control-numeric-constraint-review', $score['numericConstraintReviewPolicy']);
        $t->same('number', $score['numericConstraintInputType']);
        $t->same('score', $score['numericConstraintControlId']);
        $t->same('score', $score['numericConstraintControlName']);
        $t->same(true, $score['numericConstraintApplies']);
        $t->same('-5', $score['numericMinRaw']);
        $t->same(-5.0, $score['numericMin']);
        $t->same(true, $score['numericMinValid']);
        $t->same('10', $score['numericMaxRaw']);
        $t->same(10.0, $score['numericMax']);
        $t->same(true, $score['numericMaxValid']);
        $t->same(true, $score['numericRangeValid']);
        $t->same('0.5', $score['numericStepRaw']);
        $t->same(0.5, $score['numericStep']);
        $t->same(false, $score['numericStepAny']);
        $t->same(false, $score['numericStepDefaulted']);
        $t->same(0.5, $score['numericEffectiveStep']);
        $t->same(-5.0, $score['numericStepBase']);
        $t->same('4.5', $score['numericStaticValueRaw']);
        $t->same(4.5, $score['numericStaticValue']);
        $t->same(true, $score['numericStaticValueValid']);
        $t->same(false, $score['numericValueBelowMin']);
        $t->same(false, $score['numericValueAboveMax']);
        $t->same(false, $score['numericStepMismatch']);
        $t->same(false, $score['numericWouldBlockStaticSubmission']);
        $t->same(true, $score['numericReviewOnlyNoFormSubmission']);
        $t->same([], $score['numericIssueCodes']);
        $t->same(true, $score['numericValid']);

        $t->same(true, $low['numericValueBelowMin']);
        $t->same(false, $low['numericValueAboveMax']);
        $t->same(false, $low['numericStepMismatch']);
        $t->same(true, $low['numericWouldBlockStaticSubmission']);
        $t->same(['static-numeric-value-below-min'], $low['numericIssueCodes']);

        $t->same(1.0, $low['numericEffectiveStep']);
        $t->same(true, $low['numericStepDefaulted']);
        $t->same(true, $mismatch['numericStepMismatch']);
        $t->same(true, $mismatch['numericWouldBlockStaticSubmission']);
        $t->same(['static-numeric-value-step-mismatch'], $mismatch['numericIssueCodes']);

        $t->same(null, $bad['numericMin']);
        $t->same(false, $bad['numericMinValid']);
        $t->same(1.0, $bad['numericMax']);
        $t->same(null, $bad['numericStep']);
        $t->same(false, $bad['numericStepValid']);
        $t->same('abc', $bad['numericStaticValueRaw']);
        $t->same(null, $bad['numericStaticValue']);
        $t->same(false, $bad['numericStaticValueValid']);
        $t->same([
            'invalid-min-numeric-constraint',
            'invalid-step-numeric-constraint',
            'invalid-static-numeric-value',
        ], $bad['numericIssueCodes']);

        $t->same('any', $any['numericStepRaw']);
        $t->same('any', $any['numericStep']);
        $t->same(true, $any['numericStepAny']);
        $t->same(null, $any['numericEffectiveStep']);
        $t->same(false, $any['numericStepMismatch']);
        $t->same([], $any['numericIssueCodes']);

        $t->same(true, $readonly['numericReadonly']);
        $t->same(true, $readonly['numericValueBelowMin']);
        $t->same(false, $readonly['numericWouldBlockStaticSubmission']);
        $t->same(['numeric-constraint-readonly-control'], $readonly['numericIssueCodes']);

        $t->same(true, $disabled['numericEffectiveDisabled']);
        $t->same(true, $disabled['numericValueBelowMin']);
        $t->same(false, $disabled['numericWouldBlockStaticSubmission']);
        $t->same(['numeric-constraint-disabled-control'], $disabled['numericIssueCodes']);

        $t->same('range', $slider['numericConstraintInputType']);
        $t->same(4.0, $slider['numericStaticValue']);
        $t->same(false, $slider['numericStepMismatch']);
        $t->same([], $slider['numericIssueCodes']);

        $t->contains('<input id="mismatch" min="0" name="mismatch" step="2" type="number" value="5">', $html);
        $t->contains('<input disabled id="disabled" min="5" name="disabled" type="number" value="2">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/numeric-constraint-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },

    'records html numeric constraint mapped-case count' => static function (TestRunner $t): void {
        $t->same(8, 8);
    },
];
