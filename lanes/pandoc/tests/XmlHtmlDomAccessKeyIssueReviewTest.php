<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html accesskey issue codes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<nav id="toolbar" accesskey="s s"><button id="save" accesskey="s k">Save</button>'
                . '<button id="send" accesskey="k">Send</button><p id="bad" accesskey="wide bad&lt;key">Bad</p>'
                . '<p id="empty" accesskey="  ">Empty</p></nav>',
            'accesskey issue-code review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/accesskey-issue-code-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $toolbar = $summary[0];
        $save = $toolbar['children'][0];
        $send = $toolbar['children'][1];
        $bad = $toolbar['children'][2];
        $empty = $toolbar['children'][3];

        $t->same('review', $toolbar['accessKeyReviewStatus']);
        $t->same([
            'duplicate-accesskey-token',
            'document-accesskey-conflict',
            'html-accesskey-collision',
        ], $toolbar['accessKeyIssueCodes']);
        $t->same(3, $toolbar['accessKeyIssueCount']);
        $t->same(['s'], $toolbar['duplicateAccessKeyTokens']);
        $t->same(['s'], $toolbar['accessKeyConflictKeys']);
        $t->same(['s'], $toolbar['accessKeyCollisionKeys']);

        $t->same('review', $save['accessKeyReviewStatus']);
        $t->same([
            'document-accesskey-conflict',
            'html-accesskey-collision',
        ], $save['accessKeyIssueCodes']);
        $t->same(2, $save['accessKeyIssueCount']);
        $t->same(['s', 'k'], $save['accessKeyCollisionKeys']);

        $t->same('review', $send['accessKeyReviewStatus']);
        $t->same([
            'document-accesskey-conflict',
            'html-accesskey-collision',
        ], $send['accessKeyIssueCodes']);
        $t->same(['k'], $send['accessKeyCollisionKeys']);

        $t->same('review', $bad['accessKeyReviewStatus']);
        $t->same(['invalid-accesskey-token'], $bad['accessKeyIssueCodes']);
        $t->same(1, $bad['accessKeyIssueCount']);
        $t->same(['wide', 'bad<key'], $bad['invalidAccessKeyTokens']);
        $t->same([], $bad['accessKeyConflictKeys']);

        $t->same('review', $empty['accessKeyReviewStatus']);
        $t->same(['empty-accesskey-token-list'], $empty['accessKeyIssueCodes']);
        $t->same(1, $empty['accessKeyIssueCount']);
        $t->same([], $empty['accessKeyTokens']);
        $t->same(false, $empty['accessKeyValid']);

        $t->same(
            '<nav accesskey="s s" id="toolbar"><button accesskey="s k" id="save">Save</button>'
                . '<button accesskey="k" id="send">Send</button><p accesskey="wide bad&lt;key" id="bad">Bad</p>'
                . '<p accesskey="  " id="empty">Empty</p></nav>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/accesskey-issue-code-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
