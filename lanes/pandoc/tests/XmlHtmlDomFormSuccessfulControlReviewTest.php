<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html form successful control value candidates for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="payload" action="/submit">'
                . '<input id="title" name="title" value="Draft">'
                . '<input id="token" type="hidden" name="token" value="abc123">'
                . '<input id="agree" type="checkbox" name="agree" checked>'
                . '<input id="skip" type="checkbox" name="skip" value="1">'
                . '<input id="state-draft" type="radio" name="state" value="draft">'
                . '<input id="state-review" type="radio" name="state" value="review" checked>'
                . '<select id="single" name="single"><option value="draft">Draft</option><option selected value="review">Review</option></select>'
                . '<select id="multi" name="multi" multiple><option selected value="a">A</option><option selected disabled value="b">B</option></select>'
                . '<textarea id="notes" name="notes">Line 1' . "\n" . 'Line 2</textarea>'
                . '<input id="upload" name="upload" type="file" accept=".png">'
                . '<input id="disabled" name="off" value="no" disabled>'
                . '<input id="nameless" value="missing">'
                . '<button id="send" name="send" value="1">Send</button>'
                . '<input id="reset" name="reset" type="reset" value="clear">'
                . '<output id="calc" name="calc">42</output>'
                . '</form><input id="remote" name="remote" value="outside" form="payload">',
            'form successful control review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-successful-control-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $upload = $form['successfulControls'][7];
        $multi = $form['successfulControls'][5];

        $t->same('html-form-successful-control-review', $form['formSuccessfulControlReviewPolicy']);
        $t->same(true, $form['formSuccessfulControlReviewOnlyNoSubmission']);
        $t->same(true, $form['formSuccessfulControlReviewOnlyNoFileRead']);
        $t->same(16, $form['controlCount']);
        $t->same(9, $form['successfulControlCount']);
        $t->same(8, $form['successfulValuePairCount']);
        $t->same(7, $form['unsuccessfulControlCount']);
        $t->same([
            'title',
            'token',
            'agree',
            'state',
            'single',
            'multi',
            'notes',
            'upload',
            'remote',
        ], $form['successfulControlNames']);
        $t->same([
            ['title', 'Draft', 'input-value-attribute'],
            ['token', 'abc123', 'input-value-attribute'],
            ['agree', 'on', 'checkbox-checked-value'],
            ['state', 'review', 'radio-checked-value'],
            ['single', 'review', 'select-option-value'],
            ['multi', 'a', 'select-option-value'],
            ['notes', "Line 1\nLine 2", 'textarea-text'],
            ['remote', 'outside', 'input-value-attribute'],
        ], array_map(
            static fn (array $pair): array => [$pair['name'], $pair['value'], $pair['source']],
            $form['successfulValuePairs']
        ));

        $t->same('upload', $upload['controlName']);
        $t->same('file', $upload['type']);
        $t->same(true, $upload['successful']);
        $t->same('file-list-not-inspected', $upload['valueSource']);
        $t->same(0, $upload['valueCount']);
        $t->same(['file-input-files-not-inspected'], $upload['issueCodes']);

        $t->same('multi', $multi['controlName']);
        $t->same(true, $multi['multiple']);
        $t->same(1, $multi['valueCount']);
        $t->same(['selected-disabled-option-excluded'], $multi['issueCodes']);
        $t->same('b', $multi['issues'][0]['value']);

        $t->same([
            'unchecked-checkable-control',
            'selected-disabled-option-excluded',
            'file-input-files-not-inspected',
            'disabled-successful-control',
            'missing-successful-control-name',
            'submitter-not-selected',
            'reset-input-not-submittable',
            'output-not-submittable',
        ], $form['formSuccessfulControlIssueCodes']);
        $t->same([
            'skip',
            'state-draft',
            'disabled',
            'nameless',
            'send',
            'reset',
            'calc',
        ], array_map(
            static fn (array $control): ?string => $control['id'],
            $form['unsuccessfulControls']
        ));

        $t->contains('<input accept=".png" id="upload" name="upload" type="file">', $html);
        $t->contains('form="payload"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-successful-control-review.html', $document->children[0]->attr('part'));
        json_encode($form, JSON_THROW_ON_ERROR);
    },
];
