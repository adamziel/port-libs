<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html tabindex focus order provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="focus-order">'
                . '<input id="search" name="q" tabindex="1" value="query">'
                . '<button id="save" name="save" tabindex="2" value="go">Save</button>'
                . '<button id="disabled-save" tabindex="2" disabled>Disabled</button>'
                . '<a id="help" href="#details" tabindex="0">Help</a>'
                . '<section id="panel" tabindex="-1">Panel</section>'
                . '<p id="bad" tabindex="bogus">Bad</p>'
                . '</form><aside id="later" tabindex="2">Later</aside>',
            'tabindex focus order review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/tabindex-focus-order-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $search = $form['children'][0];
        $save = $form['children'][1];
        $disabled = $form['children'][2];
        $help = $form['children'][3];
        $panel = $form['children'][4];
        $bad = $form['children'][5];
        $later = $summary[1];

        $t->same('html-tabindex-focus-order-review', $search['tabIndexReviewPolicy']);
        $t->same('1', $search['tabIndexRaw']);
        $t->same(1, $search['tabIndex']);
        $t->same(true, $search['tabIndexValid']);
        $t->same('positive', $search['tabIndexState']);
        $t->same(true, $search['tabIndexFocusable']);
        $t->same(true, $search['tabIndexSequentialFocusCandidate']);
        $t->same(true, $search['tabIndexProgrammaticFocusCandidate']);
        $t->same(true, $search['tabIndexPositiveOrderCandidate']);
        $t->same(0, $search['tabIndexDocumentCandidateIndex']);
        $t->same(7, $search['tabIndexDocumentCandidateCount']);
        $t->same(['search', 'save', 'disabled-save', 'help', 'panel', 'bad', 'later'], $search['tabIndexCandidateIds']);
        $t->same(['search', 'save', 'later'], $search['tabIndexPositiveCandidateIds']);
        $t->same(['positive-html-tabindex-focus-order'], $search['tabIndexIssueCodes']);
        $t->same(false, $search['tabIndexBrowserFocusNavigation']);
        $t->same('metadata-only-no-browser-focus-navigation', $search['tabIndexReviewHandoffPolicy']);

        $t->same(1, $save['tabIndexDocumentCandidateIndex']);
        $t->same(3, $save['tabIndexSameValueCandidateCount']);
        $t->same(['save', 'disabled-save', 'later'], $save['tabIndexSameValueCandidateIds']);
        $t->same(true, $save['tabIndexDuplicatePositiveValue']);
        $t->same([
            'positive-html-tabindex-focus-order',
            'duplicate-positive-html-tabindex-value',
        ], $save['tabIndexIssueCodes']);
        $t->same('button', $save['tabIndexSameValueCandidates'][0]['tag'] ?? null);
        $t->same('save', $save['tabIndexSameValueCandidates'][0]['controlName'] ?? null);
        $t->same('go', $save['tabIndexSameValueCandidates'][0]['valueAttribute'] ?? null);
        $t->same('aside', $save['tabIndexSameValueCandidates'][2]['tag'] ?? null);

        $t->same(true, $disabled['tabIndexDisabledSuppressed']);
        $t->same(false, $disabled['tabIndexFocusable']);
        $t->same(false, $disabled['tabIndexSequentialFocusCandidate']);
        $t->same(false, $disabled['tabIndexProgrammaticFocusCandidate']);
        $t->same(false, $disabled['tabIndexPositiveOrderCandidate']);
        $t->same([
            'positive-html-tabindex-focus-order',
            'duplicate-positive-html-tabindex-value',
            'disabled-tabindex-focus-candidate',
        ], $disabled['tabIndexIssueCodes']);

        $t->same('zero', $help['tabIndexState']);
        $t->same(true, $help['tabIndexSequentialFocusCandidate']);
        $t->same(false, $help['tabIndexPositiveOrderCandidate']);
        $t->same(['help'], $help['tabIndexZeroCandidateIds']);
        $t->same([], $help['tabIndexIssueCodes']);
        $t->same('hyperlink', $help['tabIndexCandidates'][3]['kind'] ?? null);
        $t->same('#details', $help['tabIndexCandidates'][3]['href'] ?? null);

        $t->same('negative', $panel['tabIndexState']);
        $t->same(false, $panel['tabIndexSequentialFocusCandidate']);
        $t->same(true, $panel['tabIndexProgrammaticFocusCandidate']);
        $t->same(['panel'], $panel['tabIndexNegativeCandidateIds']);
        $t->same([], $panel['tabIndexIssueCodes']);

        $t->same('invalid', $bad['tabIndexState']);
        $t->same(null, $bad['tabIndex']);
        $t->same(false, $bad['tabIndexValid']);
        $t->same(false, $bad['tabIndexFocusable']);
        $t->same(['bad'], $bad['tabIndexInvalidCandidateIds']);
        $t->same(['invalid-html-tabindex'], $bad['tabIndexIssueCodes']);

        $t->same('positive', $later['tabIndexState']);
        $t->same(6, $later['tabIndexDocumentCandidateIndex']);
        $t->same(['search', 'save', 'later'], $later['tabIndexPositiveCandidateIds']);
        $t->same(['save', 'disabled-save', 'later'], $later['tabIndexSameValueCandidateIds']);
        $t->same(true, $later['tabIndexDuplicatePositiveValue']);
        $t->same('later', $later['tabIndexPositiveCandidates'][2]['id'] ?? null);

        $t->same(
            '<form id="focus-order"><input id="search" name="q" tabindex="1" value="query">'
                . '<button id="save" name="save" tabindex="2" value="go">Save</button>'
                . '<button disabled id="disabled-save" tabindex="2">Disabled</button>'
                . '<a href="#details" id="help" tabindex="0">Help</a>'
                . '<section id="panel" tabindex="-1">Panel</section>'
                . '<p id="bad" tabindex="bogus">Bad</p></form>'
                . '<aside id="later" tabindex="2">Later</aside>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/tabindex-focus-order-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
