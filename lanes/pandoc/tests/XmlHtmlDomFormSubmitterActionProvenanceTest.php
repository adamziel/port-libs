<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html form submitter action provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="checkout" action="https://forms.example.test/checkout" method="POST" enctype="multipart/form-data" target="_blank" novalidate>'
                . '<input name="title" value="Packet">'
                . '<input id="image-submit" type="image" name="buy" value="go" src="buy.png" formaction="javascript:steal()" formmethod="POST" formenctype="multipart/form-data" formtarget="_self" formnovalidate>'
                . '<button id="default-submit" name="default" value="1">Default</button></form>'
                . '<button id="dialog-submit" form="checkout" formaction="/review" formmethod="dialog" formenctype="text/plain" formtarget="review-frame">Review</button>'
                . '<button id="bad-submit" form="missing" formaction="mailto:ops@example.test" formmethod="TRACE" formenctype="application/json" formtarget="">Broken</button>',
            'form submitter action provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-submitter-action-provenance.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $imageSubmitter = $form['children'][1];
        $defaultSubmitter = $form['children'][2];
        $dialogSubmitter = $summary[1];
        $badSubmitter = $summary[2];

        $t->same('form-submitter-action-provenance-review', $imageSubmitter['formSubmitterActionReviewPolicy']);
        $t->same('input', $imageSubmitter['formSubmitterElement']);
        $t->same('image', $imageSubmitter['formSubmitterType']);
        $t->same('checkout', $imageSubmitter['formSubmitterOwnerId']);
        $t->same('ancestor', $imageSubmitter['formSubmitterOwnerSource']);
        $t->same('javascript:steal()', $imageSubmitter['formSubmitterActionRaw']);
        $t->same('formaction', $imageSubmitter['formSubmitterActionSource']);
        $t->same('absolute', $imageSubmitter['formSubmitterActionKind']);
        $t->same('javascript', $imageSubmitter['formSubmitterActionScheme']);
        $t->same(true, $imageSubmitter['formSubmitterActionUnsafe']);
        $t->same('POST', $imageSubmitter['formSubmitterMethodRaw']);
        $t->same('formmethod', $imageSubmitter['formSubmitterMethodSource']);
        $t->same('post', $imageSubmitter['formSubmitterEffectiveMethod']);
        $t->same(true, $imageSubmitter['formSubmitterMethodValid']);
        $t->same('multipart/form-data', $imageSubmitter['formSubmitterEnctypeRaw']);
        $t->same('multipart/form-data', $imageSubmitter['formSubmitterEffectiveEnctype']);
        $t->same('_self', $imageSubmitter['formSubmitterEffectiveTarget']);
        $t->same(true, $imageSubmitter['formSubmitterNoValidate']);
        $t->same('formnovalidate', $imageSubmitter['formSubmitterNoValidateSource']);
        $t->same(true, $imageSubmitter['formSubmitterWouldSubmit']);
        $t->same(true, $imageSubmitter['formSubmitterWouldSubmitNetworkRequest']);
        $t->same(true, $imageSubmitter['formSubmitterReviewOnlyNoNetworkRequest']);
        $t->same(['unsafe-form-submitter-action-url'], $imageSubmitter['formSubmitterActionIssueCodes']);
        $t->same(false, $imageSubmitter['formSubmitterActionValid']);

        $t->same('https://forms.example.test/checkout', $defaultSubmitter['formSubmitterActionRaw']);
        $t->same('form-action', $defaultSubmitter['formSubmitterActionSource']);
        $t->same('https', $defaultSubmitter['formSubmitterActionScheme']);
        $t->same('POST', $defaultSubmitter['formSubmitterMethodRaw']);
        $t->same('form-method', $defaultSubmitter['formSubmitterMethodSource']);
        $t->same('post', $defaultSubmitter['formSubmitterEffectiveMethod']);
        $t->same('multipart/form-data', $defaultSubmitter['formSubmitterEffectiveEnctype']);
        $t->same('_blank', $defaultSubmitter['formSubmitterEffectiveTarget']);
        $t->same(true, $defaultSubmitter['formSubmitterNoValidate']);
        $t->same('form-novalidate', $defaultSubmitter['formSubmitterNoValidateSource']);
        $t->same([], $defaultSubmitter['formSubmitterActionIssueCodes']);
        $t->same(true, $defaultSubmitter['formSubmitterActionValid']);

        $t->same('checkout', $dialogSubmitter['formSubmitterOwnerId']);
        $t->same('form-attribute', $dialogSubmitter['formSubmitterOwnerSource']);
        $t->same('/review', $dialogSubmitter['formSubmitterActionRaw']);
        $t->same('relative', $dialogSubmitter['formSubmitterActionKind']);
        $t->same('dialog', $dialogSubmitter['formSubmitterEffectiveMethod']);
        $t->same('text/plain', $dialogSubmitter['formSubmitterEffectiveEnctype']);
        $t->same('review-frame', $dialogSubmitter['formSubmitterEffectiveTarget']);
        $t->same(true, $dialogSubmitter['formSubmitterWouldSubmit']);
        $t->same(false, $dialogSubmitter['formSubmitterWouldSubmitNetworkRequest']);
        $t->same([], $dialogSubmitter['formSubmitterActionIssueCodes']);

        $t->same(false, $badSubmitter['formSubmitterOwnerFound']);
        $t->same(null, $badSubmitter['formSubmitterOwnerId']);
        $t->same('form-attribute', $badSubmitter['formSubmitterOwnerSource']);
        $t->same('mailto:ops@example.test', $badSubmitter['formSubmitterActionRaw']);
        $t->same('absolute', $badSubmitter['formSubmitterActionKind']);
        $t->same('mailto', $badSubmitter['formSubmitterActionScheme']);
        $t->same(false, $badSubmitter['formSubmitterActionUnsafe']);
        $t->same('TRACE', $badSubmitter['formSubmitterMethodRaw']);
        $t->same('get', $badSubmitter['formSubmitterEffectiveMethod']);
        $t->same(false, $badSubmitter['formSubmitterMethodValid']);
        $t->same('application/json', $badSubmitter['formSubmitterEnctypeRaw']);
        $t->same('application/x-www-form-urlencoded', $badSubmitter['formSubmitterEffectiveEnctype']);
        $t->same(false, $badSubmitter['formSubmitterEnctypeValid']);
        $t->same('', $badSubmitter['formSubmitterTargetRaw']);
        $t->same(null, $badSubmitter['formSubmitterEffectiveTarget']);
        $t->same(false, $badSubmitter['formSubmitterWouldSubmit']);
        $t->same(false, $badSubmitter['formSubmitterWouldSubmitNetworkRequest']);
        $t->same([
            'missing-form-owner',
            'non-http-form-submitter-action-url',
            'invalid-form-submitter-method',
            'invalid-form-submitter-enctype',
        ], $badSubmitter['formSubmitterActionIssueCodes']);
        $t->same(false, $badSubmitter['formSubmitterActionValid']);

        $t->contains('formaction="javascript:steal()"', $html);
        $t->contains('formaction="mailto:ops@example.test"', $html);
        $t->contains('formmethod="TRACE"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-submitter-action-provenance.html', $document->children[0]->attr('part'));
    },
];
