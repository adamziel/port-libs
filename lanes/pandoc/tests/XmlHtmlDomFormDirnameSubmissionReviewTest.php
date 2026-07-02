<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html form dirname directionality submission pairs for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="dir-form" dir="rtl">'
                . '<input id="title" name="title" value="Hello" dirname="title.dir">'
                . '<input id="query" type="search" name="q" dir="auto" value="&#x05D0; review" dirname="q.dir">'
                . '<textarea id="notes" name="notes" dirname="notes.dir">&#x05D1; note</textarea>'
                . '<input id="amount" type="number" name="amount" value="7" dirname="amount.dir">'
                . '<input id="bad" name="bad" value="x" dirname="bad name">'
                . '<input id="disabled" name="off" value="x" dirname="off.dir" disabled>'
                . '</form>',
            'form dirname directionality review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-dirname-direction-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $title = $form['children'][0];
        $query = $form['children'][1];
        $notes = $form['children'][2];
        $amount = $form['children'][3];
        $bad = $form['children'][4];

        $t->same('html-form-directionality-submission-review', $title['dirnameReviewPolicy']);
        $t->same('title.dir', $title['dirname']);
        $t->same(true, $title['dirnameValid']);
        $t->same(true, $title['dirnameEligibleControl']);
        $t->same('text', $title['dirnameInputType']);
        $t->same('rtl', $title['dirnameDirection']);
        $t->same(true, $title['dirnameWouldSubmit']);
        $t->same([], $title['dirnameIssueCodes']);

        $t->same('search', $query['dirnameInputType']);
        $t->same('rtl', $query['dirnameDirection']);
        $t->same(true, $query['dirnameWouldSubmit']);

        $t->same('textarea', $notes['formControl']);
        $t->same(null, $notes['dirnameInputType']);
        $t->same('notes.dir', $notes['dirname']);
        $t->same('rtl', $notes['dirnameDirection']);
        $t->same(true, $notes['dirnameWouldSubmit']);

        $t->same('number', $amount['dirnameInputType']);
        $t->same(false, $amount['dirnameEligibleControl']);
        $t->same(null, $amount['dirnameDirection']);
        $t->same(['dirname-control-type-not-submitted'], $amount['dirnameIssueCodes']);
        $t->same(false, $amount['dirnameWouldSubmit']);

        $t->same('bad name', $bad['dirnameRaw']);
        $t->same(false, $bad['dirnameValid']);
        $t->same('bad name', $bad['dirname']);
        $t->same(['invalid-dirname-field-name'], $bad['dirnameIssueCodes']);
        $t->same(false, $bad['dirnameWouldSubmit']);

        $t->same('html-form-successful-control-review', $form['formSuccessfulControlReviewPolicy']);
        $t->same(5, $form['successfulControlCount']);
        $t->same(8, $form['successfulValuePairCount']);
        $t->same(3, $form['successfulDirectionPairCount']);
        $t->same([
            ['title.dir', 'rtl', 'dirname-direction'],
            ['q.dir', 'rtl', 'dirname-direction'],
            ['notes.dir', 'rtl', 'dirname-direction'],
        ], array_map(
            static fn (array $pair): array => [$pair['name'], $pair['value'], $pair['source']],
            $form['successfulDirectionPairs']
        ));
        $t->same([
            ['title', 'Hello', 'input-value-attribute'],
            ['title.dir', 'rtl', 'dirname-direction'],
            ['q.dir', 'rtl', 'dirname-direction'],
            ['notes.dir', 'rtl', 'dirname-direction'],
            ['amount', '7', 'input-value-attribute'],
            ['bad', 'x', 'input-value-attribute'],
        ], array_values(array_filter(
            array_map(
                static fn (array $pair): array => [$pair['name'], $pair['value'], $pair['source']],
                $form['successfulValuePairs']
            ),
            static fn (array $pair): bool => in_array($pair[0], ['title', 'title.dir', 'q.dir', 'notes.dir', 'amount', 'bad'], true)
        )));
        $t->same([
            'dirname-control-type-not-submitted',
            'invalid-dirname-field-name',
            'disabled-successful-control',
        ], $form['formSuccessfulControlIssueCodes']);
        $t->same(false, $form['successfulControls'][3]['dirnameSubmitted']);
        $t->same(false, $form['successfulControls'][4]['dirnameSubmitted']);
        $t->same('off', $form['unsuccessfulControls'][0]['controlName']);

        $t->contains('dirname="title.dir"', $html);
        $t->contains('dirname="bad name"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-dirname-direction-review.html', $document->children[0]->attr('part'));
        json_encode($form, JSON_THROW_ON_ERROR);
    },
];
