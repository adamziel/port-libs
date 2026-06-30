<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html required value metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="required-review">'
                . '<input id="title" name="title" required value="">'
                . '<input id="filled" name="filled" required value="Ready">'
                . '<textarea id="body" name="body" required></textarea>'
                . '<input id="agree" name="agree" type="checkbox" required>'
                . '<input id="confirm" name="confirm" type="checkbox" required checked value="yes">'
                . '<input id="asset" name="asset" type="file" required>'
                . '<input id="token" name="token" type="hidden" required value="">'
                . '<input id="locked" name="locked" required disabled>'
                . '<input id="slug" name="slug" required readonly value="">'
                . '<input id="ship-a" name="ship" type="radio" required value="A">'
                . '<input id="ship-b" name="ship" type="radio" value="B" checked>'
                . '<input id="empty-a" name="empty" type="radio" required value="X">'
                . '<input id="empty-b" name="empty" type="radio" value="Y">'
                . '</form>',
            'required value review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/required-value-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $title = $form['children'][0];
        $filled = $form['children'][1];
        $body = $form['children'][2];
        $agree = $form['children'][3];
        $confirm = $form['children'][4];
        $asset = $form['children'][5];
        $token = $form['children'][6];
        $locked = $form['children'][7];
        $slug = $form['children'][8];
        $ship = $form['children'][9];
        $empty = $form['children'][11];
        $plainRadio = $form['children'][12];

        $t->same('form-control-required-value-review', $title['requiredValueReviewPolicy']);
        $t->same('input', $title['requiredValueElement']);
        $t->same('text', $title['requiredValueInputType']);
        $t->same('title', $title['requiredValueControlId']);
        $t->same('title', $title['requiredValueControlName']);
        $t->same(true, $title['requiredValueApplies']);
        $t->same('value-attribute', $title['requiredValueSource']);
        $t->same('', $title['requiredValueStaticValue']);
        $t->same(0, $title['requiredValueStaticValueLength']);
        $t->same(false, $title['requiredValuePresent']);
        $t->same(true, $title['requiredValueMissing']);
        $t->same(true, $title['requiredValueWouldBlockStaticSubmission']);
        $t->same(true, $title['requiredValueReviewOnlyNoFormSubmission']);
        $t->same(['required-control-value-missing'], $title['requiredValueIssueCodes']);
        $t->same(false, $title['requiredValueValid']);

        $t->same('Ready', $filled['requiredValueStaticValue']);
        $t->same(5, $filled['requiredValueStaticValueLength']);
        $t->same(true, $filled['requiredValuePresent']);
        $t->same(false, $filled['requiredValueMissing']);
        $t->same(false, $filled['requiredValueWouldBlockStaticSubmission']);
        $t->same([], $filled['requiredValueIssueCodes']);
        $t->same(true, $filled['requiredValueValid']);

        $t->same('textarea', $body['requiredValueElement']);
        $t->same(null, $body['requiredValueInputType']);
        $t->same('textarea-text', $body['requiredValueSource']);
        $t->same('', $body['requiredValueStaticValue']);
        $t->same(['required-control-value-missing'], $body['requiredValueIssueCodes']);

        $t->same('checkbox', $agree['requiredValueInputType']);
        $t->same('checked-state', $agree['requiredValueSource']);
        $t->same(null, $agree['requiredValueStaticValue']);
        $t->same(false, $agree['requiredValuePresent']);
        $t->same(true, $agree['requiredValueMissing']);
        $t->same(['required-control-value-missing'], $agree['requiredValueIssueCodes']);

        $t->same('yes', $confirm['requiredValueStaticValue']);
        $t->same(true, $confirm['requiredValuePresent']);
        $t->same(false, $confirm['requiredValueMissing']);
        $t->same([], $confirm['requiredValueIssueCodes']);

        $t->same('file', $asset['requiredValueInputType']);
        $t->same('file-input-static-selection', $asset['requiredValueSource']);
        $t->same(null, $asset['requiredValueStaticValue']);
        $t->same(false, $asset['requiredValuePresent']);
        $t->same(true, $asset['requiredValueMissing']);
        $t->same(true, $asset['requiredValueWouldBlockStaticSubmission']);
        $t->same(['required-control-value-missing'], $asset['requiredValueIssueCodes']);

        $t->same('hidden', $token['requiredValueInputType']);
        $t->same(false, $token['requiredValueApplies']);
        $t->same('unsupported-control', $token['requiredValueSource']);
        $t->same(null, $token['requiredValueMissing']);
        $t->same(false, $token['requiredValueWouldBlockStaticSubmission']);
        $t->same(['required-control-unsupported'], $token['requiredValueIssueCodes']);

        $t->same(true, $locked['requiredValueEffectiveDisabled']);
        $t->same(true, $locked['requiredValueMissing']);
        $t->same(false, $locked['requiredValueWouldBlockStaticSubmission']);
        $t->same(['required-control-disabled'], $locked['requiredValueIssueCodes']);

        $t->same(true, $slug['requiredValueReadonly']);
        $t->same(true, $slug['requiredValueMissing']);
        $t->same(false, $slug['requiredValueWouldBlockStaticSubmission']);
        $t->same(['required-control-readonly'], $slug['requiredValueIssueCodes']);

        $t->same('radio', $ship['requiredValueInputType']);
        $t->same('radio-group-checked-state', $ship['requiredValueSource']);
        $t->same('B', $ship['requiredValueStaticValue']);
        $t->same(true, $ship['requiredValuePresent']);
        $t->same(false, $ship['requiredValueMissing']);
        $t->same(false, $ship['requiredValueWouldBlockStaticSubmission']);
        $t->same([], $ship['requiredValueIssueCodes']);
        $t->same('ship', $ship['requiredValueRadioGroupName']);
        $t->same(2, $ship['requiredValueRadioGroupSize']);
        $t->same(1, $ship['requiredValueRadioGroupRequiredCount']);
        $t->same(true, $ship['requiredValueRadioGroupChecked']);
        $t->same(['ship-b'], $ship['requiredValueRadioGroupCheckedIds']);
        $t->same(['B'], $ship['requiredValueRadioGroupCheckedValues']);
        $t->same(true, $ship['requiredValueRadioGroupControls'][0]['current']);
        $t->same(false, $ship['requiredValueRadioGroupControls'][1]['current']);

        $t->same('empty', $empty['requiredValueRadioGroupName']);
        $t->same(false, $empty['requiredValueRadioGroupChecked']);
        $t->same([], $empty['requiredValueRadioGroupCheckedIds']);
        $t->same([], $empty['requiredValueRadioGroupCheckedValues']);
        $t->same(true, $empty['requiredValueWouldBlockStaticSubmission']);
        $t->same(['required-control-value-missing'], $empty['requiredValueIssueCodes']);
        $t->true(!array_key_exists('requiredValueReviewPolicy', $plainRadio));

        $t->contains('<input id="title" name="title" required value="">', $html);
        $t->contains('<input checked id="ship-b" name="ship" type="radio" value="B">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/required-value-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
