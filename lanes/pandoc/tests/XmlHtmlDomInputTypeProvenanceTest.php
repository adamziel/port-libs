<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html input type defaulting provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="types" action="/submit"><input id="missing" name="title" value="Draft">'
                . '<input id="empty" type="" name="empty" formaction="/empty">'
                . '<input id="email" type=" EMAIL " name="email" value="editor@example.test" required>'
                . '<input id="invalid-submit" type="submitx" name="bad" value="Bad" formaction="/bad">'
                . '<input id="image" type="IMAGE" name="go" src="go.png" formaction="/image-submit"></form>',
            'input type provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/input-type-provenance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $missing = $form['children'][0];
        $empty = $form['children'][1];
        $email = $form['children'][2];
        $invalidSubmit = $form['children'][3];
        $image = $form['children'][4];

        $t->same('form', $form['formSubmission']);
        $t->same(5, $form['controlCount']);
        $t->same(['title', 'empty', 'email', 'bad', 'go'], $form['controlNames']);
        $t->same('text', $form['controls'][3]['type'] ?? null);
        $t->same('image', $form['controls'][4]['type'] ?? null);

        $t->same(null, $missing['inputTypeRaw']);
        $t->same('text', $missing['inputType']);
        $t->same('missing', $missing['inputTypeState']);
        $t->same(true, $missing['inputTypeValid']);
        $t->same(false, $missing['inputTypeKnown']);
        $t->same(true, $missing['inputTypeDefaulted']);
        $t->same(true, $missing['inputTypeMissingDefaulted']);
        $t->same(false, $missing['inputTypeInvalidValueDefaulted']);
        $t->true(!array_key_exists('submitter', $missing));

        $t->same('', $empty['inputTypeRaw']);
        $t->same('text', $empty['inputType']);
        $t->same('empty', $empty['inputTypeState']);
        $t->same(false, $empty['inputTypeValid']);
        $t->same(false, $empty['inputTypeKnown']);
        $t->same(true, $empty['inputTypeDefaulted']);
        $t->same(false, $empty['inputTypeMissingDefaulted']);
        $t->same(true, $empty['inputTypeInvalidValueDefaulted']);
        $t->true(!array_key_exists('submitter', $empty));
        $t->true(!array_key_exists('formSubmitterActionReviewPolicy', $empty));

        $t->same(' EMAIL ', $email['inputTypeRaw']);
        $t->same('email', $email['inputType']);
        $t->same('email', $email['inputTypeState']);
        $t->same(true, $email['inputTypeValid']);
        $t->same(true, $email['inputTypeKnown']);
        $t->same(false, $email['inputTypeDefaulted']);
        $t->same(false, $email['inputTypeInvalidValueDefaulted']);
        $t->true(!array_key_exists('submitter', $email));

        $t->same('submitx', $invalidSubmit['inputTypeRaw']);
        $t->same('text', $invalidSubmit['inputType']);
        $t->same('invalid', $invalidSubmit['inputTypeState']);
        $t->same(false, $invalidSubmit['inputTypeValid']);
        $t->same(false, $invalidSubmit['inputTypeKnown']);
        $t->same(true, $invalidSubmit['inputTypeDefaulted']);
        $t->same(true, $invalidSubmit['inputTypeInvalidValueDefaulted']);
        $t->true(!array_key_exists('submitter', $invalidSubmit));
        $t->true(!array_key_exists('formSubmitterActionReviewPolicy', $invalidSubmit));

        $t->same('IMAGE', $image['inputTypeRaw']);
        $t->same('image', $image['inputType']);
        $t->same('image', $image['inputTypeState']);
        $t->same(true, $image['inputTypeValid']);
        $t->same(true, $image['inputTypeKnown']);
        $t->same(false, $image['inputTypeDefaulted']);
        $t->same('image', $image['formSubmitterType']);
        $t->same('/image-submit', $image['formSubmitterActionRaw']);
        $t->same('relative', $image['formSubmitterActionKind']);

        $t->contains($html, $blocks);
        $t->same('/migration/input-type-provenance-review.html', $document->children[0]->attr('part'));
        $t->same(
            '<form action="/submit" id="types"><input id="missing" name="title" value="Draft">'
                . '<input formaction="/empty" id="empty" name="empty" type="">'
                . '<input id="email" name="email" required type=" EMAIL " value="editor@example.test">'
                . '<input formaction="/bad" id="invalid-submit" name="bad" type="submitx" value="Bad">'
                . '<input formaction="/image-submit" id="image" name="go" src="go.png" type="IMAGE"></form>',
            $html
        );
    },
];
