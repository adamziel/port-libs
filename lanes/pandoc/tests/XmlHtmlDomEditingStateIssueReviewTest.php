<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html contenteditable and spellcheck issue metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="editor" contenteditable="plaintext-only" spellcheck="false">'
                . '<p id="body"><span id="bad" contenteditable="maybe" spellcheck="sometimes">Bad</span>'
                . '<span id="off" contenteditable="false" spellcheck="true"><em id="locked-child">Locked</em></span></p>'
                . '</article>',
            'editing state issue review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/editing-state-issue-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $editor = $summary[0];
        $body = $editor['children'][0];
        $bad = $body['children'][0];
        $off = $body['children'][1];
        $locked = $off['children'][0];

        $t->same('html-contenteditable-state-review', $editor['contentEditableReviewPolicy']);
        $t->same('ok', $editor['contentEditableReviewStatus']);
        $t->same('plain-text', $editor['contentEditableMode']);
        $t->same([], $editor['contentEditableIssueCodes']);
        $t->same(0, $editor['contentEditableIssueCount']);
        $t->same(true, $editor['contentEditableReviewOnlyNoEditingEngine']);

        $t->same('html-spellcheck-state-review', $editor['spellcheckReviewPolicy']);
        $t->same('ok', $editor['spellcheckReviewStatus']);
        $t->same(false, $editor['spellcheckCheckingEnabled']);
        $t->same([], $editor['spellcheckIssueCodes']);
        $t->same(true, $editor['spellcheckReviewOnlyNoSpellcheckingService']);

        $t->true(!array_key_exists('contentEditableReviewPolicy', $body));
        $t->true(!array_key_exists('spellcheckReviewPolicy', $body));
        $t->same('plaintext-only', $body['effectiveContentEditable']);
        $t->same(false, $body['effectiveSpellcheck']);

        $t->same('review', $bad['contentEditableReviewStatus']);
        $t->same(null, $bad['contentEditableMode']);
        $t->same(true, $bad['contentEditableInvalidValueDefaulted']);
        $t->same(['invalid-html-contenteditable-token'], $bad['contentEditableIssueCodes']);
        $t->same('plaintext-only', $bad['effectiveContentEditable']);
        $t->same(true, $bad['contentEditableInherited']);

        $t->same('review', $bad['spellcheckReviewStatus']);
        $t->same(null, $bad['spellcheckCheckingEnabled']);
        $t->same(true, $bad['spellcheckInvalidValueDefaulted']);
        $t->same(['invalid-html-spellcheck-token'], $bad['spellcheckIssueCodes']);
        $t->same(false, $bad['effectiveSpellcheck']);
        $t->same(true, $bad['spellcheckInherited']);

        $t->same('not-editable', $off['contentEditableMode']);
        $t->same(false, $off['effectiveContentEditable']);
        $t->same(false, $off['contentEditableInherited']);
        $t->same(true, $off['spellcheckCheckingEnabled']);
        $t->same(true, $off['effectiveSpellcheck']);
        $t->same(false, $off['spellcheckInherited']);

        $t->same(false, $locked['effectiveContentEditable']);
        $t->same(true, $locked['contentEditableInherited']);
        $t->same(true, $locked['effectiveSpellcheck']);
        $t->same(true, $locked['spellcheckInherited']);

        $t->same(
            '<article contenteditable="plaintext-only" id="editor" spellcheck="false"><p id="body"><span contenteditable="maybe" id="bad" spellcheck="sometimes">Bad</span><span contenteditable="false" id="off" spellcheck="true"><em id="locked-child">Locked</em></span></p></article>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/editing-state-issue-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
