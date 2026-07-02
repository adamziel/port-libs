<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html autofocus order suppression for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="draft"><input id="title" name="title" value="Draft" autofocus>'
                . '<button id="save" disabled autofocus>Save</button>'
                . '<textarea id="body" name="body" autofocus>Body</textarea></form>'
                . '<a id="asset" href="/asset" autofocus>Asset</a>'
                . '<section id="panel" tabindex="-1" autofocus>Panel body</section>',
            'autofocus order suppression review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/autofocus-order-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $first = $form['children'][0];
        $disabled = $form['children'][1];
        $textarea = $form['children'][2];
        $link = $summary[1];
        $panel = $summary[2];

        $t->same('document-autofocus-candidate-review', $first['autofocusReviewPolicy']);
        $t->same('review', $first['autofocusReviewStatus']);
        $t->same(5, $first['autofocusCandidateCount']);
        $t->same(0, $first['autofocusIndex']);
        $t->same(true, $first['autofocusFirst']);
        $t->same(true, $first['autofocusFirstCandidateSelected']);
        $t->same(false, $first['autofocusSuppressedByEarlierCandidate']);
        $t->same(['multiple-autofocus-candidates'], $first['autofocusIssueCodes']);
        $t->same(1, $first['autofocusIssueCount']);
        $t->same([], $first['autofocusOrderIssueCodes']);
        $t->same(0, $first['autofocusOrderIssueCount']);
        $t->same(false, $first['autofocusBrowserFocusApplied']);
        $t->same(true, $first['autofocusReviewOnlyNoBrowserFocus']);
        $t->same('metadata-only-no-browser-focus', $first['autofocusReviewHandoffPolicy']);
        $t->same(['title', 'save', 'body', 'asset', 'panel'], $first['autofocusCandidateIds']);
        $t->same(['input', 'button', 'textarea', 'a', 'section'], $first['autofocusCandidateElementNames']);
        $t->same('title', $first['autofocusFirstCandidate']['id'] ?? null);
        $t->same('title', $first['autofocusCurrentCandidate']['id'] ?? null);
        $t->same(null, $first['autofocusPreviousCandidate']);

        $t->same(1, $disabled['autofocusIndex']);
        $t->same(false, $disabled['autofocusFirstCandidateSelected']);
        $t->same(true, $disabled['autofocusSuppressedByEarlierCandidate']);
        $t->same(['autofocus-suppressed-by-earlier-candidate'], $disabled['autofocusOrderIssueCodes']);
        $t->same(1, $disabled['autofocusOrderIssueCount']);
        $t->same('title', $disabled['autofocusPreviousCandidate']['id'] ?? null);
        $t->same('save', $disabled['autofocusCurrentCandidate']['id'] ?? null);
        $t->same('form-control', $disabled['autofocusCurrentCandidate']['kind'] ?? null);
        $t->same(true, $disabled['autofocusCurrentCandidate']['effectiveDisabled'] ?? null);
        $t->same('Save', $disabled['autofocusCurrentCandidate']['label'] ?? null);

        $t->same(2, $textarea['autofocusIndex']);
        $t->same('body', $textarea['autofocusCurrentCandidate']['id'] ?? null);
        $t->same('textarea', $textarea['autofocusCurrentCandidate']['tag'] ?? null);
        $t->same('Body', $textarea['autofocusCurrentCandidate']['value'] ?? null);
        $t->same(true, $textarea['autofocusSuppressedByEarlierCandidate']);

        $t->same(3, $link['autofocusIndex']);
        $t->same('hyperlink', $link['autofocusCurrentCandidate']['kind'] ?? null);
        $t->same('asset', $link['autofocusCurrentCandidate']['id'] ?? null);
        $t->same(true, $link['autofocusSuppressedByEarlierCandidate']);

        $t->same(4, $panel['autofocusIndex']);
        $t->same('tabindex', $panel['autofocusCurrentCandidate']['kind'] ?? null);
        $t->same(-1, $panel['autofocusCurrentCandidate']['tabIndex'] ?? null);
        $t->same(['autofocus-suppressed-by-earlier-candidate'], $panel['autofocusOrderIssueCodes']);

        $t->same(
            '<form id="draft"><input autofocus id="title" name="title" value="Draft"><button autofocus disabled id="save">Save</button><textarea autofocus id="body" name="body">Body</textarea></form>'
                . '<a autofocus href="/asset" id="asset">Asset</a><section autofocus id="panel" tabindex="-1">Panel body</section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/autofocus-order-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
