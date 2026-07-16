<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html edit assistance policy for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="editor" contenteditable autocorrect="off" writingsuggestions="false" virtualkeyboardpolicy="manual">Draft <span id="inline" autocorrect="ON" writingsuggestions="TRUE" virtualkeyboardpolicy="AUTO">Inline</span></article>'
                . '<input id="lookup" value="Ada" autocorrect writingsuggestions virtualkeyboardpolicy>'
                . '<p id="bad" autocorrect="maybe" writingsuggestions="maybe" virtualkeyboardpolicy="onscreen">Fallback</p>',
            'edit assistance policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/edit-assistance-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $inline = $article['children'][1];
        $input = $summary[1];
        $bad = $summary[2];

        $t->same('html-edit-assistance-policy-review', $article['editAssistanceReviewPolicy']);
        $t->same('article', $article['editAssistanceElement']);
        $t->same('editable-host', $article['editAssistanceHostKind']);
        $t->same(['autocorrect', 'writingsuggestions', 'virtualkeyboardpolicy'], $article['editAssistanceAttributes']);
        $t->same('off', $article['editAutocorrectState']);
        $t->same(false, $article['editAutocorrectEnabled']);
        $t->same(false, $article['editWritingSuggestionsEnabled']);
        $t->same('manual', $article['editVirtualKeyboardPolicy']);
        $t->same('manual', $article['editVirtualKeyboardControlMode']);
        $t->same([], $article['editAssistanceIssueCodes']);
        $t->same(true, $article['editAssistanceValid']);

        $t->same('editable-descendant', $inline['editAssistanceHostKind']);
        $t->same('on', $inline['editAutocorrectState']);
        $t->same(true, $inline['editAutocorrectEnabled']);
        $t->same(true, $inline['editWritingSuggestionsEnabled']);
        $t->same('auto', $inline['editVirtualKeyboardPolicy']);
        $t->same('automatic', $inline['editVirtualKeyboardControlMode']);
        $t->same(true, $inline['editAssistanceValid']);

        $t->same('text-entry-control', $input['editAssistanceHostKind']);
        $t->same('', $input['autocorrectRaw']);
        $t->same('on', $input['editAutocorrectState']);
        $t->same(true, $input['editAutocorrectEnabled']);
        $t->same(true, $input['editWritingSuggestionsEnabled']);
        $t->same('auto', $input['editVirtualKeyboardPolicy']);
        $t->same('automatic', $input['editVirtualKeyboardControlMode']);

        $t->same('generic-element', $bad['editAssistanceHostKind']);
        $t->same(null, $bad['editAutocorrectState']);
        $t->same(null, $bad['editAutocorrectEnabled']);
        $t->same(null, $bad['editWritingSuggestionsEnabled']);
        $t->same(null, $bad['editVirtualKeyboardPolicy']);
        $t->same(null, $bad['editVirtualKeyboardControlMode']);
        $t->same([
            'invalid-html-autocorrect-token',
            'invalid-html-writingsuggestions-token',
            'invalid-html-virtualkeyboardpolicy-token',
        ], $bad['editAssistanceIssueCodes']);
        $t->same(false, $bad['editAssistanceValid']);

        $t->same(
            '<article autocorrect="off" contenteditable="" id="editor" virtualkeyboardpolicy="manual" writingsuggestions="false">Draft <span autocorrect="ON" id="inline" virtualkeyboardpolicy="AUTO" writingsuggestions="TRUE">Inline</span></article>'
                . '<input autocorrect="" id="lookup" value="Ada" virtualkeyboardpolicy="" writingsuggestions="">'
                . '<p autocorrect="maybe" id="bad" virtualkeyboardpolicy="onscreen" writingsuggestions="maybe">Fallback</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/edit-assistance-policy-review.html', $document->children[0]->attr('part'));
    },
];
