<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html checkbox and radio checked state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="survey">'
                . '<input id="agree" name="agree" type="checkbox" required>'
                . '<input id="subscribe" name="subscribe" type="checkbox" checked value="yes">'
                . '<input id="archived" name="archived" type="checkbox" checked disabled value="1">'
                . '<input id="size-s" name="size" type="radio" value="s" required>'
                . '<input id="size-m" name="size" type="radio" value="m" checked>'
                . '<input id="size-l" name="size" type="radio" value="l" checked>'
                . '<input id="tone" name="tone" type="radio" required>'
                . '</form>',
            'checkable input state review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/checkable-input-state-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $agree = $form['children'][0];
        $subscribe = $form['children'][1];
        $archived = $form['children'][2];
        $sizeSmall = $form['children'][3];
        $sizeMedium = $form['children'][4];
        $tone = $form['children'][6];

        $t->same('html-checkable-input-state-review', $agree['checkableInputReviewPolicy']);
        $t->same(true, $agree['checkableInputReviewOnlyNoBrowserStateMutation']);
        $t->same('checkbox', $agree['checkableInputType']);
        $t->same(null, $agree['checkableInputValueRaw']);
        $t->same('on', $agree['checkableInputValue']);
        $t->same(true, $agree['checkableInputValueDefaulted']);
        $t->same(false, $agree['checkableInputChecked']);
        $t->same(true, $agree['checkableInputRequired']);
        $t->same(false, $agree['checkableInputSuccessful']);
        $t->same(null, $agree['checkableInputSuccessfulValue']);
        $t->same(['required-checkbox-unchecked'], $agree['checkableInputIssueCodes']);
        $t->same(false, $agree['checkableInputStateValid']);

        $t->same('checkbox', $subscribe['checkableInputType']);
        $t->same('yes', $subscribe['checkableInputValueRaw']);
        $t->same('yes', $subscribe['checkableInputValue']);
        $t->same(false, $subscribe['checkableInputValueDefaulted']);
        $t->same(true, $subscribe['checkableInputChecked']);
        $t->same(true, $subscribe['checkableInputSuccessful']);
        $t->same('yes', $subscribe['checkableInputSuccessfulValue']);
        $t->same([], $subscribe['checkableInputIssueCodes']);
        $t->same(true, $subscribe['checkableInputStateValid']);

        $t->same(true, $archived['checkableInputChecked']);
        $t->same(true, $archived['checkableInputEffectiveDisabled']);
        $t->same(false, $archived['checkableInputSuccessful']);
        $t->same(['checked-disabled-checkable-input'], $archived['checkableInputIssueCodes']);
        $t->same(false, $archived['checkableInputStateValid']);

        $t->same('radio', $sizeSmall['checkableInputType']);
        $t->same('html-radio-group-state-review', $sizeSmall['radioGroupReviewPolicy']);
        $t->same('size', $sizeSmall['radioGroupNameRaw']);
        $t->same('survey', $sizeSmall['radioGroupFormOwnerId']);
        $t->same(3, $sizeSmall['radioGroupControlCount']);
        $t->same(3, $sizeSmall['radioGroupEnabledControlCount']);
        $t->same(2, $sizeSmall['radioGroupCheckedCount']);
        $t->same(2, $sizeSmall['radioGroupEnabledCheckedCount']);
        $t->same(['m', 'l'], $sizeSmall['radioGroupCheckedValues']);
        $t->same(true, $sizeSmall['radioGroupRequired']);
        $t->same(false, $sizeSmall['radioGroupValueMissing']);
        $t->same(true, $sizeSmall['radioGroupMultipleChecked']);
        $t->same(0, $sizeSmall['radioGroupCurrentIndex']);
        $t->same(['multiple-checked-radio-group'], $sizeSmall['radioGroupIssueCodes']);
        $t->same(['multiple-checked-radio-group'], $sizeSmall['checkableInputIssueCodes']);
        $t->same(false, $sizeSmall['checkableInputStateValid']);

        $t->same(true, $sizeMedium['checkableInputChecked']);
        $t->same(true, $sizeMedium['checkableInputSuccessful']);
        $t->same('m', $sizeMedium['checkableInputSuccessfulValue']);
        $t->same(1, $sizeMedium['radioGroupCurrentIndex']);
        $t->same(['size-m', 'size-l'], $sizeMedium['radioGroupCheckedIds']);

        $t->same('radio', $tone['checkableInputType']);
        $t->same(null, $tone['checkableInputValueRaw']);
        $t->same('on', $tone['checkableInputValue']);
        $t->same(true, $tone['checkableInputValueDefaulted']);
        $t->same(1, $tone['radioGroupControlCount']);
        $t->same(0, $tone['radioGroupCheckedCount']);
        $t->same(true, $tone['radioGroupRequired']);
        $t->same(true, $tone['radioGroupValueMissing']);
        $t->same(['required-radio-group-missing-value'], $tone['radioGroupIssueCodes']);
        $t->same(['required-radio-group-missing-value'], $tone['checkableInputIssueCodes']);
        $t->same(false, $tone['checkableInputStateValid']);

        $t->contains('type="checkbox"', $html);
        $t->contains('type="radio"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/checkable-input-state-review.html', $document->children[0]->attr('part'));
        json_encode([$agree, $subscribe, $archived, $sizeSmall, $sizeMedium, $tone], JSON_THROW_ON_ERROR);
    },
];
