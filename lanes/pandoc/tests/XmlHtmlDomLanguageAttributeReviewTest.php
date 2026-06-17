<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html language attribute conflicts and invalid fallback for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="root" lang="EN-us">'
                . '<p id="conflict" lang="en" xml:lang="fr">Conflict</p>'
                . '<p id="invalid" lang="bad tag"><span id="child">Child</span></p>'
                . '<section id="xml-only" xml:lang="DE-de"><em id="term">Term</em></section>'
                . '<aside id="empty" lang="">Empty</aside>'
                . '</article>',
            'language attribute review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/language-attribute-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $root = $summary[0];
        $conflict = $root['children'][0];
        $invalid = $root['children'][1];
        $child = $invalid['children'][0];
        $xmlOnly = $root['children'][2];
        $term = $xmlOnly['children'][0];
        $empty = $root['children'][3];

        $t->same('html-language-tag-review', $root['languageReviewPolicy']);
        $t->same('html-language-attribute-review', $root['languageAttributeReviewPolicy']);
        $t->same('lang', $root['languageSourceAttribute']);
        $t->same('EN-us', $root['languageRaw']);
        $t->same('EN-us', $root['language']);
        $t->same('en-US', $root['languageNormalized']);
        $t->same(true, $root['languageValid']);
        $t->same([], $root['languageAttributeIssueCodes']);
        $t->same('EN-us', $root['effectiveLanguageRaw']);
        $t->same('en-US', $root['effectiveLanguage']);
        $t->same(false, $root['languageInherited']);

        $t->same('en', $conflict['languageNormalized']);
        $t->same('fr', $conflict['xmlLanguageNormalized']);
        $t->same(true, $conflict['languageValid']);
        $t->same(true, $conflict['xmlLanguageValid']);
        $t->same(true, $conflict['languageAttributeConflict']);
        $t->same(['conflicting-language-attributes'], $conflict['languageAttributeIssueCodes']);
        $t->same('en', $conflict['effectiveLanguage']);
        $t->same(false, $conflict['languageInherited']);

        $t->same('bad tag', $invalid['languageRaw']);
        $t->same(null, $invalid['languageNormalized']);
        $t->same(false, $invalid['languageValid']);
        $t->same(false, $invalid['languageAttributeConflict']);
        $t->same(['invalid-lang-attribute'], $invalid['languageAttributeIssueCodes']);
        $t->same('en-US', $invalid['effectiveLanguage']);
        $t->same(true, $invalid['languageInherited']);
        $t->same('root', $invalid['languageSourceElementId']);
        $t->same('en-US', $child['effectiveLanguage']);
        $t->same(true, $child['languageInherited']);

        $t->same('xml:lang', $xmlOnly['languageSourceAttribute']);
        $t->same('DE-de', $xmlOnly['languageRaw']);
        $t->same('DE-de', $xmlOnly['xmlLanguageRaw']);
        $t->same('de-DE', $xmlOnly['languageNormalized']);
        $t->same('de-DE', $xmlOnly['xmlLanguageNormalized']);
        $t->same(true, $xmlOnly['languageValid']);
        $t->same(true, $xmlOnly['xmlLanguageValid']);
        $t->same('de-DE', $xmlOnly['effectiveLanguage']);
        $t->same(false, $xmlOnly['languageInherited']);
        $t->same('de-DE', $term['effectiveLanguage']);
        $t->same(true, $term['languageInherited']);
        $t->same('xml-only', $term['languageSourceElementId']);

        $t->same('', $empty['languageRaw']);
        $t->same(null, $empty['languageNormalized']);
        $t->same(false, $empty['languageValid']);
        $t->same(['empty-lang-attribute'], $empty['languageAttributeIssueCodes']);
        $t->same('en-US', $empty['effectiveLanguage']);
        $t->same(true, $empty['languageInherited']);

        $t->same(
            '<article id="root" lang="EN-us"><p id="conflict" lang="en" xml:lang="fr">Conflict</p><p id="invalid" lang="bad tag"><span id="child">Child</span></p><section id="xml-only" xml:lang="DE-de"><em id="term">Term</em></section><aside id="empty" lang="">Empty</aside></article>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/language-attribute-review.html', $document->children[0]->attr('part'));
    },
];
