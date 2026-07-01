<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html typed input value provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="typed"><label for="amount">Amount</label>'
                . '<input id="amount" name="amount" type="number" value="4.5" min="-1" max="10" step="0.5">'
                . '<input id="bad-number" name="badNumber" type="number" value="NaN" min="9" max="2" step="0">'
                . '<input id="slider" name="slider" type="range" value="50" min="0" max="100" step="any">'
                . '<input id="publish-date" name="publishDate" type="date" value="2026-07-01" min="2026-01-01" max="2026-12-31">'
                . '<input id="bad-date" name="badDate" type="date" value="2026-02-30" min="2026-12-31" max="2026-01-01">'
                . '<input id="meeting" name="meeting" type="datetime-local" value="2026-07-01 09:15" min="2026-07-01T08:00" max="2026-07-01T17:30">'
                . '<input id="global" name="global" type="datetime-local" value="2026-07-01T09:15Z">'
                . '<input id="cutoff" name="cutoff" type="time" value="09:15:30.125" min="08:00" max="17:30">'
                . '<input id="week" name="week" type="week" value="2026-W27" min="2026-W01" max="2026-W52">'
                . '<input id="plain" name="plain" type="text" value="2026-07-01" min="2026-01-01"></form>',
            'typed input value review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/typed-input-value-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $amount = $form['children'][1];
        $badNumber = $form['children'][2];
        $slider = $form['children'][3];
        $date = $form['children'][4];
        $badDate = $form['children'][5];
        $meeting = $form['children'][6];
        $global = $form['children'][7];
        $cutoff = $form['children'][8];
        $week = $form['children'][9];
        $plain = $form['children'][10];

        $t->same('typed', $form['elementId']);
        $t->same('number', $amount['inputType']);
        $t->same('html-typed-input-value-review', $amount['typedInputReviewPolicy']);
        $t->same('number', $amount['typedInputType']);
        $t->same('4.5', $amount['typedInputValueRaw']);
        $t->same(4.5, $amount['typedInputValue']);
        $t->same('number', $amount['typedInputValueKind']);
        $t->same('valid-number', $amount['typedInputValueState']);
        $t->same(true, $amount['typedInputValueValid']);
        $t->same('-1', $amount['typedInputMinRaw']);
        $t->same(-1.0, $amount['typedInputMin']);
        $t->same(true, $amount['typedInputMinValid']);
        $t->same('10', $amount['typedInputMaxRaw']);
        $t->same(10.0, $amount['typedInputMax']);
        $t->same(true, $amount['typedInputMaxValid']);
        $t->same(true, $amount['typedInputRangeValid']);
        $t->same('0.5', $amount['typedInputStepRaw']);
        $t->same(0.5, $amount['typedInputStep']);
        $t->same(true, $amount['typedInputStepValid']);
        $t->same([], $amount['typedInputIssueCodes']);
        $t->same(true, $amount['typedInputValid']);

        $t->same('number', $badNumber['typedInputType']);
        $t->same('NaN', $badNumber['typedInputValueRaw']);
        $t->same(null, $badNumber['typedInputValue']);
        $t->same('invalid', $badNumber['typedInputValueState']);
        $t->same(false, $badNumber['typedInputValueValid']);
        $t->same(9.0, $badNumber['typedInputMin']);
        $t->same(2.0, $badNumber['typedInputMax']);
        $t->same(false, $badNumber['typedInputRangeValid']);
        $t->same('0', $badNumber['typedInputStepRaw']);
        $t->same(null, $badNumber['typedInputStep']);
        $t->same(false, $badNumber['typedInputStepValid']);
        $t->same([
            'invalid-typed-input-value',
            'typed-input-min-exceeds-max',
            'invalid-typed-input-step',
        ], $badNumber['typedInputIssueCodes']);
        $t->same(false, $badNumber['typedInputValid']);

        $t->same('range', $slider['typedInputType']);
        $t->same(50.0, $slider['typedInputValue']);
        $t->same(0.0, $slider['typedInputMin']);
        $t->same(100.0, $slider['typedInputMax']);
        $t->same(true, $slider['typedInputRangeValid']);
        $t->same('any', $slider['typedInputStep']);
        $t->same(true, $slider['typedInputStepValid']);
        $t->same([], $slider['typedInputIssueCodes']);

        $t->same('date', $date['typedInputType']);
        $t->same('2026-07-01', $date['typedInputValue']);
        $t->same('date', $date['typedInputValueKind']);
        $t->same('valid-date', $date['typedInputValueState']);
        $t->same('2026-01-01', $date['typedInputMin']);
        $t->same('date', $date['typedInputMinKind']);
        $t->same('2026-12-31', $date['typedInputMax']);
        $t->same(true, $date['typedInputRangeValid']);
        $t->same([], $date['typedInputIssueCodes']);

        $t->same('date', $badDate['typedInputType']);
        $t->same('2026-02-30', $badDate['typedInputValueRaw']);
        $t->same(null, $badDate['typedInputValue']);
        $t->same('date', $badDate['typedInputValueKind']);
        $t->same(false, $badDate['typedInputValueValid']);
        $t->same('2026-12-31', $badDate['typedInputMin']);
        $t->same('2026-01-01', $badDate['typedInputMax']);
        $t->same(false, $badDate['typedInputRangeValid']);
        $t->same([
            'invalid-typed-input-value',
            'typed-input-min-exceeds-max',
        ], $badDate['typedInputIssueCodes']);

        $t->same('datetime-local', $meeting['typedInputType']);
        $t->same('2026-07-01 09:15', $meeting['typedInputValueRaw']);
        $t->same('2026-07-01T09:15', $meeting['typedInputValue']);
        $t->same('local-datetime', $meeting['typedInputValueKind']);
        $t->same('valid-local-datetime', $meeting['typedInputValueState']);
        $t->same('2026-07-01T08:00', $meeting['typedInputMin']);
        $t->same('2026-07-01T17:30', $meeting['typedInputMax']);
        $t->same(true, $meeting['typedInputRangeValid']);
        $t->same([], $meeting['typedInputIssueCodes']);

        $t->same('datetime-local', $global['typedInputType']);
        $t->same('2026-07-01T09:15Z', $global['typedInputValueRaw']);
        $t->same(null, $global['typedInputValue']);
        $t->same('local-datetime', $global['typedInputValueKind']);
        $t->same(false, $global['typedInputValueValid']);
        $t->same(['invalid-typed-input-value'], $global['typedInputIssueCodes']);

        $t->same('time', $cutoff['typedInputType']);
        $t->same('09:15:30.125', $cutoff['typedInputValue']);
        $t->same('time', $cutoff['typedInputValueKind']);
        $t->same('08:00', $cutoff['typedInputMin']);
        $t->same('17:30', $cutoff['typedInputMax']);
        $t->same(true, $cutoff['typedInputRangeValid']);

        $t->same('week', $week['typedInputType']);
        $t->same('2026-W27', $week['typedInputValue']);
        $t->same('week', $week['typedInputValueKind']);
        $t->same('2026-W01', $week['typedInputMin']);
        $t->same('2026-W52', $week['typedInputMax']);
        $t->same(true, $week['typedInputRangeValid']);

        $t->same('text', $plain['inputType']);
        $t->same('2026-07-01', $plain['value']);
        $t->true(!array_key_exists('typedInputReviewPolicy', $plain));
        $t->true(!array_key_exists('typedInputValue', $plain));
        $t->true(!array_key_exists('typedInputIssueCodes', $plain));

        $t->same(
            '<form id="typed"><label for="amount">Amount</label><input id="amount" max="10" min="-1" name="amount" step="0.5" type="number" value="4.5"><input id="bad-number" max="2" min="9" name="badNumber" step="0" type="number" value="NaN"><input id="slider" max="100" min="0" name="slider" step="any" type="range" value="50"><input id="publish-date" max="2026-12-31" min="2026-01-01" name="publishDate" type="date" value="2026-07-01"><input id="bad-date" max="2026-01-01" min="2026-12-31" name="badDate" type="date" value="2026-02-30"><input id="meeting" max="2026-07-01T17:30" min="2026-07-01T08:00" name="meeting" type="datetime-local" value="2026-07-01 09:15"><input id="global" name="global" type="datetime-local" value="2026-07-01T09:15Z"><input id="cutoff" max="17:30" min="08:00" name="cutoff" type="time" value="09:15:30.125"><input id="week" max="2026-W52" min="2026-W01" name="week" type="week" value="2026-W27"><input id="plain" min="2026-01-01" name="plain" type="text" value="2026-07-01"></form>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/typed-input-value-review.html', $document->children[0]->attr('part'));
    },
];
