<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html focus navigation conflicts for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="shell">'
                . '<button id="save" accesskey="s s" autofocus tabindex="1">Save</button>'
                . '<a id="search" href="/find" accesskey="s f" autofocus tabindex="-1">Search</a>'
                . '<input id="name" name="customer" accesskey="long {bad}" autofocus value="Ada">'
                . '<div id="panel" tabindex="40000">Panel</div>'
                . '</section>',
            'focus navigation review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/focus-navigation-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $section = $summary[0];
        $button = $section['children'][0];
        $link = $section['children'][1];
        $input = $section['children'][2];
        $panel = $section['children'][3];

        $t->same('shell', $section['elementId']);

        $t->same('html-focus-navigation-review', $button['focusNavigationReviewPolicy']);
        $t->same(['accesskey', 'autofocus', 'tabindex'], $button['focusNavigationAttributes']);
        $t->same(['duplicate-accesskey-token', 'document-accesskey-conflict', 'positive-tabindex-focus-order', 'multiple-autofocus-candidates'], $button['focusNavigationIssueCodes']);
        $t->same('s s', $button['accessKeyRaw']);
        $t->same(['s', 's'], $button['accessKeyTokens']);
        $t->same(['s'], $button['accessKeys']);
        $t->same(['s'], $button['duplicateAccessKeyTokens']);
        $t->same(['duplicate-accesskey-token', 'document-accesskey-conflict'], $button['accessKeyIssueCodes']);
        $t->same(true, $button['accessKeyValid']);
        $t->same(2, $button['accessKeyDocumentAssignmentCount']);
        $t->same(['s'], $button['accessKeyConflictKeys']);
        $t->same(true, $button['accessKeyHasConflict']);
        $t->same('save', $button['accessKeyConflicts'][0]['id']);
        $t->same('search', $button['accessKeyConflicts'][1]['id']);
        $t->same('html-accesskey-collision-review', $button['accessKeyCollisionReviewPolicy']);
        $t->same(['s'], $button['accessKeyCollisionKeys']);
        $t->same(1, $button['accessKeyCollisionCount']);
        $t->same(['save', 'search'], $button['accessKeyCollisions'][0]['candidateIds']);
        $t->same(1, $button['tabIndex']);
        $t->same(true, $button['tabIndexValid']);
        $t->same(['positive-tabindex-focus-order'], $button['tabIndexIssueCodes']);
        $t->same('document-autofocus-candidate-review', $button['autofocusReviewPolicy']);
        $t->same(3, $button['autofocusCandidateCount']);
        $t->same(0, $button['autofocusIndex']);
        $t->same(true, $button['autofocusFirst']);
        $t->same(true, $button['autofocusConflict']);
        $t->same(['save', 'search', 'name'], $button['autofocusCandidateIds']);
        $t->same(['button', 'a', 'input'], $button['autofocusCandidateElementNames']);
        $t->same(['multiple-autofocus-candidates'], $button['autofocusIssueCodes']);
        $t->same([], $button['autofocusOrderIssueCodes']);

        $t->same(['accesskey', 'autofocus', 'tabindex'], $link['focusNavigationAttributes']);
        $t->same(['document-accesskey-conflict', 'multiple-autofocus-candidates', 'autofocus-suppressed-by-earlier-candidate'], $link['focusNavigationIssueCodes']);
        $t->same(['s', 'f'], $link['accessKeys']);
        $t->same(3, $link['accessKeyDocumentAssignmentCount']);
        $t->same(['s'], $link['accessKeyConflictKeys']);
        $t->same(['document-accesskey-conflict'], $link['accessKeyIssueCodes']);
        $t->same(-1, $link['tabIndex']);
        $t->same(1, $link['autofocusIndex']);
        $t->same(false, $link['autofocusFirst']);
        $t->same(true, $link['autofocusSuppressedByEarlierCandidate']);
        $t->same('save', $link['autofocusPreviousCandidate']['id']);
        $t->same(['autofocus-suppressed-by-earlier-candidate'], $link['autofocusOrderIssueCodes']);

        $t->same(['accesskey', 'autofocus'], $input['focusNavigationAttributes']);
        $t->same(['invalid-accesskey-token', 'multiple-autofocus-candidates', 'autofocus-suppressed-by-earlier-candidate'], $input['focusNavigationIssueCodes']);
        $t->same(['long', '{bad}'], $input['accessKeyTokens']);
        $t->same([], $input['accessKeys']);
        $t->same(['long', '{bad}'], $input['invalidAccessKeyTokens']);
        $t->same(['invalid-accesskey-token'], $input['accessKeyIssueCodes']);
        $t->same(false, $input['accessKeyValid']);
        $t->same(0, $input['accessKeyDocumentAssignmentCount']);
        $t->same(2, $input['autofocusIndex']);
        $t->same('search', $input['autofocusPreviousCandidate']['id']);

        $t->same(['tabindex'], $panel['focusNavigationAttributes']);
        $t->same(['positive-tabindex-focus-order'], $panel['focusNavigationIssueCodes']);
        $t->same(40000, $panel['tabIndex']);
        $t->same(true, $panel['tabIndexValid']);
        $t->same(['positive-tabindex-focus-order'], $panel['tabIndexIssueCodes']);

        $t->same(
            '<section id="shell"><button accesskey="s s" autofocus id="save" tabindex="1">Save</button><a accesskey="s f" autofocus href="/find" id="search" tabindex="-1">Search</a><input accesskey="long {bad}" autofocus id="name" name="customer" value="Ada"><div id="panel" tabindex="40000">Panel</div></section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/focus-navigation-review.html', $document->children[0]->attr('part'));
    },
];
