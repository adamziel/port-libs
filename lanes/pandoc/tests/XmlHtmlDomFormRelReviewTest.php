<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html form rel token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="checkout" action="/pay" target="_blank" rel="noopener noreferrer external search"><button>Pay</button></form>'
                . '<form id="default"><input name="q" value="pandoc"></form>'
                . '<form id="bad" rel="tag bad&lt;tag opener noopener opener"><button>Bad</button></form>',
            'form rel review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-rel-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $checkout = $summary[0];
        $default = $summary[1];
        $bad = $summary[2];

        $t->same('form-rel-token-review', $checkout['formRelReviewPolicy']);
        $t->same('noopener noreferrer external search', $checkout['formRelRaw']);
        $t->same(true, $checkout['formRelPresent']);
        $t->same(['noopener', 'noreferrer', 'external', 'search'], $checkout['formRelTokens']);
        $t->same(['noopener', 'noreferrer', 'external', 'search'], $checkout['formRelKnownTokens']);
        $t->same([], $checkout['formRelUnknownTokens']);
        $t->same([
            'noopener' => 1,
            'noreferrer' => 1,
            'external' => 1,
            'search' => 1,
        ], $checkout['formRelTokenCounts']);
        $t->same([], $checkout['duplicateFormRelTokens']);
        $t->same([], $checkout['invalidFormRelTokens']);
        $t->same(false, $checkout['formRelRequestsOpener']);
        $t->same(true, $checkout['formRelRequestsNoopener']);
        $t->same(true, $checkout['formRelRequestsNoreferrer']);
        $t->same(true, $checkout['formRelEffectiveNoopener']);
        $t->same(['noopener', 'noreferrer', 'external'], $checkout['formRelNavigationHints']);
        $t->same([], $checkout['formRelIssueCodes']);
        $t->same(true, $checkout['formRelValid']);

        $t->same(false, $default['formRelPresent']);
        $t->same(null, $default['formRelRaw']);
        $t->same([], $default['formRelTokens']);
        $t->same([], $default['formRelKnownTokens']);
        $t->same([], $default['formRelNavigationHints']);
        $t->same([], $default['formRelIssueCodes']);
        $t->same(true, $default['formRelValid']);

        $t->same('tag bad<tag opener noopener opener', $bad['formRelRaw']);
        $t->same(['tag', 'opener', 'noopener'], $bad['formRelTokens']);
        $t->same(['opener', 'noopener'], $bad['formRelKnownTokens']);
        $t->same(['tag'], $bad['formRelUnknownTokens']);
        $t->same(['opener'], $bad['duplicateFormRelTokens']);
        $t->same(['bad<tag'], $bad['invalidFormRelTokens']);
        $t->same(['tag' => 1, 'opener' => 2, 'noopener' => 1], $bad['formRelTokenCounts']);
        $t->same(true, $bad['formRelRequestsOpener']);
        $t->same(true, $bad['formRelRequestsNoopener']);
        $t->same(false, $bad['formRelRequestsNoreferrer']);
        $t->same(true, $bad['formRelEffectiveNoopener']);
        $t->same(['opener', 'noopener'], $bad['formRelNavigationHints']);
        $t->same([
            'invalid-form-rel-token',
            'duplicate-form-rel-token',
            'unknown-form-rel-token',
            'conflicting-form-rel-opener-policy',
        ], $bad['formRelIssueCodes']);
        $t->same(false, $bad['formRelValid']);

        $t->same(
            '<form action="/pay" id="checkout" rel="noopener noreferrer external search" target="_blank"><button>Pay</button></form>'
                . '<form id="default"><input name="q" value="pandoc"></form>'
                . '<form id="bad" rel="tag bad&lt;tag opener noopener opener"><button>Bad</button></form>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/form-rel-review.html', $document->children[0]->attr('part'));
    },
];
