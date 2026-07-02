<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html spellcheck token review for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="root" spellcheck="false"><p id="child"><span id="invalid" spellcheck="maybe"></span><span id="enabled" spellcheck="TRUE"><em id="enabled-child"></em></span><input id="empty" spellcheck value="Draft"></p></article>'
                . '<section id="plain"><p id="plain-child"><span id="plain-grand">Plain</span></p></section>',
            'spellcheck token review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/spellcheck-token-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $root = $summary[0];
        $child = $root['children'][0];
        $invalid = $child['children'][0];
        $enabled = $child['children'][1];
        $enabledChild = $enabled['children'][0];
        $empty = $child['children'][2];
        $plainGrand = $summary[1]['children'][0]['children'][0];

        $t->same('html-spellcheck-state-review', $root['spellcheckReviewPolicy']);
        $t->same('html-spellcheck-attribute-review', $root['spellcheckAttributeReviewPolicy']);
        $t->same('false', $root['spellcheckRaw']);
        $t->same(false, $root['spellcheck']);
        $t->same('false', $root['spellcheckKeyword']);
        $t->same(true, $root['spellcheckValid']);
        $t->same(false, $root['spellcheckEmptyValueDefaulted']);
        $t->same(false, $root['spellcheckInvalidValueDefaulted']);
        $t->same([], $root['spellcheckIssues']);
        $t->same([], $root['spellcheckIssueCodes']);
        $t->same('html-spellcheck-attribute-review', $root['effectiveSpellcheckReviewPolicy']);
        $t->same('false', $root['effectiveSpellcheckRaw']);
        $t->same(false, $root['effectiveSpellcheck']);
        $t->same('false', $root['effectiveSpellcheckKeyword']);
        $t->same([], $root['effectiveSpellcheckIssueCodes']);
        $t->same(false, $root['spellcheckInherited']);
        $t->same('self-spellcheck', $root['spellcheckSource']);
        $t->same('article', $root['spellcheckSourceElement']);
        $t->same('root', $root['spellcheckSourceElementId']);

        $t->true(!array_key_exists('spellcheckRaw', $child));
        $t->same(false, $child['effectiveSpellcheck']);
        $t->same('false', $child['effectiveSpellcheckKeyword']);
        $t->same(true, $child['spellcheckInherited']);
        $t->same('ancestor-spellcheck', $child['spellcheckSource']);
        $t->same('root', $child['spellcheckSourceElementId']);

        $t->same('maybe', $invalid['spellcheckRaw']);
        $t->same(null, $invalid['spellcheck']);
        $t->same(null, $invalid['spellcheckKeyword']);
        $t->same(false, $invalid['spellcheckValid']);
        $t->same(true, $invalid['spellcheckInvalidValueDefaulted']);
        $t->same([['code' => 'invalid-spellcheck-token', 'spellcheckRaw' => 'maybe']], $invalid['spellcheckIssues']);
        $t->same(['invalid-html-spellcheck-token'], $invalid['spellcheckIssueCodes']);
        $t->same(['invalid-spellcheck-token'], $invalid['spellcheckAttributeIssueCodes']);
        $t->same(false, $invalid['effectiveSpellcheck']);
        $t->same('false', $invalid['effectiveSpellcheckKeyword']);
        $t->same([], $invalid['effectiveSpellcheckIssueCodes']);
        $t->same(true, $invalid['spellcheckInherited']);
        $t->same('root', $invalid['spellcheckSourceElementId']);

        $t->same('TRUE', $enabled['spellcheckRaw']);
        $t->same(true, $enabled['spellcheck']);
        $t->same('true', $enabled['spellcheckKeyword']);
        $t->same(true, $enabled['spellcheckValid']);
        $t->same(false, $enabled['spellcheckInherited']);
        $t->same('self-spellcheck', $enabled['spellcheckSource']);
        $t->same('TRUE', $enabled['effectiveSpellcheckRaw']);
        $t->same(true, $enabled['effectiveSpellcheck']);
        $t->same('true', $enabled['effectiveSpellcheckKeyword']);

        $t->same(true, $enabledChild['effectiveSpellcheck']);
        $t->same(true, $enabledChild['spellcheckInherited']);
        $t->same('span', $enabledChild['spellcheckSourceElement']);
        $t->same('enabled', $enabledChild['spellcheckSourceElementId']);

        $t->same('', $empty['spellcheckRaw']);
        $t->same(true, $empty['spellcheck']);
        $t->same('true', $empty['spellcheckKeyword']);
        $t->same(true, $empty['spellcheckEmptyValueDefaulted']);
        $t->same(false, $empty['spellcheckInvalidValueDefaulted']);
        $t->same([], $empty['spellcheckIssueCodes']);
        $t->same(true, $empty['effectiveSpellcheck']);
        $t->same(false, $empty['spellcheckInherited']);

        $t->true(!array_key_exists('effectiveSpellcheck', $plainGrand));
        $t->contains('spellcheck="false"', $html);
        $t->contains('spellcheck="maybe"', $html);
        $t->contains('spellcheck="TRUE"', $html);
        $t->contains('spellcheck=""', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/spellcheck-token-review.html', $document->children[0]->attr('part'));
    },
];
