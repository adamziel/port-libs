<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html form length constraints for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="lengths">'
                . '<input id="slug" name="slug" minlength="3" maxlength="12" value="post-42">'
                . '<input id="short" name="short" minlength="5" value="cat">'
                . '<textarea id="long" name="long" maxlength="4">review</textarea>'
                . '<textarea id="bad-range" name="bad-range" minlength="10" maxlength="5">draft</textarea>'
                . '<input id="number" name="number" type="number" maxlength="2" value="42">'
                . '<input id="disabled" name="disabled" minlength="5" value="" disabled>'
                . '<input id="readonly" name="readonly" minlength="5" value="" readonly>'
                . '</form>',
            'form length constraint review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-length-constraint-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $slug = $form['children'][0];
        $short = $form['children'][1];
        $long = $form['children'][2];
        $badRange = $form['children'][3];
        $number = $form['children'][4];
        $disabled = $form['children'][5];
        $readonly = $form['children'][6];

        $t->same('form-control-length-constraint-review', $slug['lengthReviewPolicy']);
        $t->same('input', $slug['lengthControlElement']);
        $t->same('text', $slug['lengthControlType']);
        $t->same('slug', $slug['lengthControlId']);
        $t->same('slug', $slug['lengthControlName']);
        $t->same(true, $slug['lengthConstraintApplies']);
        $t->same('3', $slug['lengthMinRaw']);
        $t->same(3, $slug['lengthMin']);
        $t->same(true, $slug['lengthMinValid']);
        $t->same('12', $slug['lengthMaxRaw']);
        $t->same(12, $slug['lengthMax']);
        $t->same(true, $slug['lengthMaxValid']);
        $t->same(true, $slug['lengthRangeValid']);
        $t->same('value-attribute', $slug['lengthStaticValueSource']);
        $t->same('post-42', $slug['lengthStaticValue']);
        $t->same(7, $slug['lengthStaticValueLength']);
        $t->same(false, $slug['lengthTooShort']);
        $t->same(false, $slug['lengthTooLong']);
        $t->same(false, $slug['lengthWouldBlockStaticSubmission']);
        $t->same(true, $slug['lengthReviewOnlyNoFormSubmission']);
        $t->same([], $slug['lengthIssueCodes']);
        $t->same(true, $slug['lengthValid']);

        $t->same(3, $short['lengthStaticValueLength']);
        $t->same(true, $short['lengthTooShort']);
        $t->same(false, $short['lengthTooLong']);
        $t->same(true, $short['lengthWouldBlockStaticSubmission']);
        $t->same(['static-value-too-short'], $short['lengthIssueCodes']);
        $t->same(false, $short['lengthValid']);

        $t->same('textarea', $long['lengthControlElement']);
        $t->same(null, $long['lengthControlType']);
        $t->same('textarea-text', $long['lengthStaticValueSource']);
        $t->same('review', $long['lengthStaticValue']);
        $t->same(6, $long['lengthStaticValueLength']);
        $t->same(false, $long['lengthTooShort']);
        $t->same(true, $long['lengthTooLong']);
        $t->same(['static-value-too-long'], $long['lengthIssueCodes']);

        $t->same(false, $badRange['lengthRangeValid']);
        $t->same(true, $badRange['lengthTooShort']);
        $t->same(false, $badRange['lengthTooLong']);
        $t->same([
            'invalid-length-constraint-range',
            'static-value-too-short',
        ], $badRange['lengthIssueCodes']);

        $t->same('number', $number['lengthControlType']);
        $t->same(false, $number['lengthConstraintApplies']);
        $t->same(false, $number['lengthTooLong']);
        $t->same(false, $number['lengthWouldBlockStaticSubmission']);
        $t->same(['length-constraint-unsupported-control'], $number['lengthIssueCodes']);

        $t->same(true, $disabled['lengthEffectiveDisabled']);
        $t->same(true, $disabled['lengthTooShort']);
        $t->same(false, $disabled['lengthWouldBlockStaticSubmission']);
        $t->same(['length-constraint-disabled-control'], $disabled['lengthIssueCodes']);

        $t->same(true, $readonly['lengthReadonly']);
        $t->same(true, $readonly['lengthTooShort']);
        $t->same(false, $readonly['lengthWouldBlockStaticSubmission']);
        $t->same(['length-constraint-readonly-control'], $readonly['lengthIssueCodes']);

        $t->contains('<input id="short" minlength="5" name="short" value="cat">', $html);
        $t->contains('<textarea id="long" maxlength="4" name="long">review</textarea>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-length-constraint-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
