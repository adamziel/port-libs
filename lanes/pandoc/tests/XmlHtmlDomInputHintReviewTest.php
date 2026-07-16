<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html input hint keyboard provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="entry"><input id="amount" inputmode="Decimal" enterkeyhint="Done">'
                . '<textarea id="message" inputmode="search" enterkeyhint="send">Note</textarea>'
                . '<p id="editable" contenteditable="plaintext-only" inputmode="email" enterkeyhint="next">Edit</p>'
                . '<div id="generic" inputmode="tel">Call</div>'
                . '<p id="bad" inputmode="kana" enterkeyhint="compose">Bad</p></form>',
            'input hint keyboard review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/input-hint-keyboard-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $input = $form['children'][0];
        $textarea = $form['children'][1];
        $editable = $form['children'][2];
        $generic = $form['children'][3];
        $bad = $form['children'][4];

        $t->same('entry', $form['elementId']);

        $t->same('html-input-hint-keyboard-review', $input['inputHintReviewPolicy']);
        $t->same('input', $input['inputHintElement']);
        $t->same('text-entry-control', $input['inputHintHostKind']);
        $t->same('Decimal', $input['inputModeRaw']);
        $t->same('decimal', $input['inputModeKeyboardKind']);
        $t->same('Done', $input['enterKeyHintRaw']);
        $t->same('completion', $input['enterKeyHintActionKind']);
        $t->same([], $input['inputHintIssueCodes']);
        $t->same(true, $input['inputHintValid']);

        $t->same('text-entry-control', $textarea['inputHintHostKind']);
        $t->same('search', $textarea['inputModeKeyboardKind']);
        $t->same('submission', $textarea['enterKeyHintActionKind']);
        $t->same(true, $textarea['inputHintValid']);

        $t->same('editable-host', $editable['inputHintHostKind']);
        $t->same('plaintext-only', $editable['contentEditable']);
        $t->same('email-address', $editable['inputModeKeyboardKind']);
        $t->same('form-navigation', $editable['enterKeyHintActionKind']);
        $t->same(true, $editable['inputHintValid']);

        $t->same('generic-element', $generic['inputHintHostKind']);
        $t->same('telephone', $generic['inputModeKeyboardKind']);
        $t->true(!array_key_exists('enterKeyHintActionKind', $generic));
        $t->same(true, $generic['inputHintValid']);

        $t->same('generic-element', $bad['inputHintHostKind']);
        $t->same(null, $bad['inputMode']);
        $t->same(null, $bad['enterKeyHint']);
        $t->same(null, $bad['inputModeKeyboardKind']);
        $t->same(null, $bad['enterKeyHintActionKind']);
        $t->same(['invalid-html-inputmode-token', 'invalid-html-enterkeyhint-token'], $bad['inputHintIssueCodes']);
        $t->same(false, $bad['inputHintValid']);

        $t->same(
            '<form id="entry"><input enterkeyhint="Done" id="amount" inputmode="Decimal"><textarea enterkeyhint="send" id="message" inputmode="search">Note</textarea><p contenteditable="plaintext-only" enterkeyhint="next" id="editable" inputmode="email">Edit</p><div id="generic" inputmode="tel">Call</div><p enterkeyhint="compose" id="bad" inputmode="kana">Bad</p></form>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/input-hint-keyboard-review.html', $document->children[0]->attr('part'));
    },
];
