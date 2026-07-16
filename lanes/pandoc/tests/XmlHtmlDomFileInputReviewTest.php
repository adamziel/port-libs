<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html file input accept and capture provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="upload"><label for="avatar">Avatar</label>'
                . '<input id="avatar" name="avatar" type="file" accept=".png, image/jpeg, image/*" capture="environment" multiple>'
                . '<input id="doc" name="doc" type="file" accept=".PDF, application/pdf, text/plain" capture>'
                . '<input id="bad" name="bad" type="file" accept="bad token, text/html;level=1, .bad&lt;ext" capture="front">'
                . '<input id="wrong" name="wrong" type="text" accept="image/png" capture="user" value="not file"></form>',
            'file input accept capture review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/file-input-accept-capture-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $avatar = $form['children'][1];
        $doc = $form['children'][2];
        $bad = $form['children'][3];
        $wrong = $form['children'][4];

        $t->same('upload', $form['elementId']);
        $t->same('input', $avatar['formControl']);
        $t->same('file', $avatar['inputType']);
        $t->same(true, $avatar['multiple']);
        $t->same('html-file-input-accept-capture-review', $avatar['fileInputReviewPolicy']);
        $t->same(true, $avatar['fileInput']);
        $t->same('.png, image/jpeg, image/*', $avatar['fileInputAcceptRaw']);
        $t->same(['.png', 'image/jpeg', 'image/*'], $avatar['fileInputAcceptTokens']);
        $t->same(['.png'], $avatar['fileInputAcceptedExtensions']);
        $t->same(['image/jpeg'], $avatar['fileInputAcceptedMimeTypes']);
        $t->same(['image/*'], $avatar['fileInputAcceptedWildcardMimeTypes']);
        $t->same([], $avatar['fileInputInvalidAcceptTokens']);
        $t->same(true, $avatar['fileInputAcceptValid']);
        $t->same('environment', $avatar['fileInputCaptureRaw']);
        $t->same('environment', $avatar['fileInputCaptureState']);
        $t->same(true, $avatar['fileInputCaptureValid']);
        $t->same([], $avatar['fileInputIssueCodes']);
        $t->same(true, $avatar['fileInputValid']);

        $t->same('file', $doc['inputType']);
        $t->same(['.PDF', 'application/pdf', 'text/plain'], $doc['fileInputAcceptTokens']);
        $t->same(['.pdf'], $doc['fileInputAcceptedExtensions']);
        $t->same(['application/pdf', 'text/plain'], $doc['fileInputAcceptedMimeTypes']);
        $t->same('', $doc['fileInputCaptureRaw']);
        $t->same('capture', $doc['fileInputCaptureState']);
        $t->same(true, $doc['fileInputValid']);

        $t->same('file', $bad['inputType']);
        $t->same(['bad token', 'text/html;level=1', '.bad<ext'], $bad['fileInputAcceptTokens']);
        $t->same(['bad token', 'text/html;level=1', '.bad<ext'], $bad['fileInputInvalidAcceptTokens']);
        $t->same(false, $bad['fileInputAcceptValid']);
        $t->same('front', $bad['fileInputCaptureRaw']);
        $t->same(null, $bad['fileInputCaptureState']);
        $t->same(false, $bad['fileInputCaptureValid']);
        $t->same(['invalid-file-accept-token', 'invalid-file-capture-token'], $bad['fileInputIssueCodes']);
        $t->same(false, $bad['fileInputValid']);

        $t->same('text', $wrong['inputType']);
        $t->same(false, $wrong['fileInput']);
        $t->same(['image/png'], $wrong['fileInputAcceptedMimeTypes']);
        $t->same('user', $wrong['fileInputCaptureState']);
        $t->same(['non-file-input-upload-attribute'], $wrong['fileInputIssueCodes']);
        $t->same(false, $wrong['fileInputValid']);

        $t->same(
            '<form id="upload"><label for="avatar">Avatar</label><input accept=".png, image/jpeg, image/*" capture="environment" id="avatar" multiple name="avatar" type="file"><input accept=".PDF, application/pdf, text/plain" capture="" id="doc" name="doc" type="file"><input accept="bad token, text/html;level=1, .bad&lt;ext" capture="front" id="bad" name="bad" type="file"><input accept="image/png" capture="user" id="wrong" name="wrong" type="text" value="not file"></form>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/file-input-accept-capture-review.html', $document->children[0]->attr('part'));
    },
];
