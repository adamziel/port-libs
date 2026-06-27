<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html image submitter provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="checkout" action="/checkout"><label for="buy">Buy now</label>'
                . '<input id="buy" type="image" name="buy" value="go" src="submit.png" alt="Buy packet" width="64" height="32" formaction="/image-submit">'
                . '<input id="unsafe" type="image" src="javascript:steal()" width="-1" height="bad">'
                . '<input id="empty-alt" type="image" alt=""></form>',
            'input image submitter review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/input-image-submitter-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $buy = $form['children'][1];
        $unsafe = $form['children'][2];
        $emptyAlt = $form['children'][3];

        $t->same('checkout', $form['elementId']);
        $t->same('input', $buy['formControl']);
        $t->same('image', $buy['inputType']);
        $t->same('html-input-image-submitter-review', $buy['inputImageSubmitterReviewPolicy']);
        $t->same(true, $buy['inputImageSubmitter']);
        $t->same('submit.png', $buy['inputImageSrcRaw']);
        $t->same(true, $buy['inputImageSrcPresent']);
        $t->same('relative', $buy['inputImageSrcKind']);
        $t->same(null, $buy['inputImageSrcScheme']);
        $t->same(false, $buy['inputImageSrcUnsafe']);
        $t->same('Buy packet', $buy['inputImageAltRaw']);
        $t->same('Buy packet', $buy['inputImageAltText']);
        $t->same(true, $buy['inputImageAltPresent']);
        $t->same(false, $buy['inputImageAltEmpty']);
        $t->same('64', $buy['inputImageWidthRaw']);
        $t->same(64, $buy['inputImageWidth']);
        $t->same(true, $buy['inputImageWidthValid']);
        $t->same('32', $buy['inputImageHeightRaw']);
        $t->same(32, $buy['inputImageHeight']);
        $t->same(true, $buy['inputImageHeightValid']);
        $t->same('buy', $buy['inputImageNameRaw']);
        $t->same('buy', $buy['inputImageCoordinateNamePrefix']);
        $t->same(['buy.x', 'buy.y'], $buy['inputImageCoordinateParameterNames']);
        $t->same('go', $buy['inputImageValueRaw']);
        $t->same([], $buy['inputImageIssueCodes']);
        $t->same(true, $buy['inputImageValid']);
        $t->same([
            'form' => null,
            'formAction' => '/image-submit',
            'formMethod' => null,
            'formEnctype' => null,
            'formTarget' => null,
            'formNoValidate' => false,
        ], $buy['submitter']);
        $t->same('form-submitter-action-provenance-review', $buy['formSubmitterActionReviewPolicy']);
        $t->same('image', $buy['formSubmitterType']);
        $t->same('formaction', $buy['formSubmitterActionSource']);
        $t->same('/image-submit', $buy['formSubmitterActionRaw']);
        $t->same(['Buy now'], $buy['labels']);

        $t->same('javascript:steal()', $unsafe['inputImageSrcRaw']);
        $t->same('absolute', $unsafe['inputImageSrcKind']);
        $t->same('javascript', $unsafe['inputImageSrcScheme']);
        $t->same(true, $unsafe['inputImageSrcUnsafe']);
        $t->same(null, $unsafe['inputImageAltRaw']);
        $t->same('x', $unsafe['inputImageCoordinateParameterNames'][0]);
        $t->same('-1', $unsafe['inputImageWidthRaw']);
        $t->same(null, $unsafe['inputImageWidth']);
        $t->same(false, $unsafe['inputImageWidthValid']);
        $t->same('bad', $unsafe['inputImageHeightRaw']);
        $t->same(null, $unsafe['inputImageHeight']);
        $t->same(false, $unsafe['inputImageHeightValid']);
        $t->same([
            'unsafe-input-image-src',
            'missing-input-image-alt',
            'invalid-input-image-width',
            'invalid-input-image-height',
        ], $unsafe['inputImageIssueCodes']);
        $t->same(false, $unsafe['inputImageValid']);

        $t->same(null, $emptyAlt['inputImageSrcRaw']);
        $t->same('missing', $emptyAlt['inputImageSrcKind']);
        $t->same('', $emptyAlt['inputImageAltRaw']);
        $t->same('', $emptyAlt['inputImageAltText']);
        $t->same(true, $emptyAlt['inputImageAltPresent']);
        $t->same(true, $emptyAlt['inputImageAltEmpty']);
        $t->same(null, $emptyAlt['inputImageWidthValid']);
        $t->same(null, $emptyAlt['inputImageHeightValid']);
        $t->same(['missing-input-image-src', 'empty-input-image-alt'], $emptyAlt['inputImageIssueCodes']);
        $t->same(false, $emptyAlt['inputImageValid']);

        $t->same(
            '<form action="/checkout" id="checkout"><label for="buy">Buy now</label><input alt="Buy packet" formaction="/image-submit" height="32" id="buy" name="buy" src="submit.png" type="image" value="go" width="64"><input height="bad" id="unsafe" src="javascript:steal()" type="image" width="-1"><input alt="" id="empty-alt" type="image"></form>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/input-image-submitter-review.html', $document->children[0]->attr('part'));
    },
];
