<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html autonomous custom element tag provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<review-card id="card" part="card"><h2>Review</h2></review-card>'
                . '<font-face id="reserved">Legacy</font-face>'
                . '<x-panel id="upgrade" is="BadWidget"><p>Nested</p></x-panel>',
            'custom element tag review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/custom-element-tag-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $reviewCard = $summary[0];
        $reserved = $summary[1];
        $customizedBuiltIn = $summary[2];

        $t->same('review-card', $reviewCard['name']);
        $t->same('html-custom-element-tag-review', $reviewCard['customElementTagReviewPolicy']);
        $t->same('review-card', $reviewCard['customElementTagName']);
        $t->same(true, $reviewCard['customElementTagValid']);
        $t->same(false, $reviewCard['customElementTagReservedName']);
        $t->same([], $reviewCard['customElementTagIssueCodes']);
        $t->same(true, $reviewCard['autonomousCustomElement']);
        $t->same(['card'], $reviewCard['partNames']);

        $t->same('font-face', $reserved['name']);
        $t->same('font-face', $reserved['customElementTagName']);
        $t->same(false, $reserved['customElementTagValid']);
        $t->same(true, $reserved['customElementTagReservedName']);
        $t->same(['reserved-custom-element-name'], $reserved['customElementTagIssueCodes']);
        $t->same(false, $reserved['autonomousCustomElement']);

        $t->same('x-panel', $customizedBuiltIn['name']);
        $t->same('x-panel', $customizedBuiltIn['customElementTagName']);
        $t->same(true, $customizedBuiltIn['customElementTagValid']);
        $t->same('BadWidget', $customizedBuiltIn['isRaw']);
        $t->same('BadWidget', $customizedBuiltIn['customElementName']);
        $t->same(false, $customizedBuiltIn['customElementValid']);

        $t->same(
            '<review-card id="card" part="card"><h2>Review</h2></review-card>'
                . '<font-face id="reserved">Legacy</font-face>'
                . '<x-panel id="upgrade" is="BadWidget"><p>Nested</p></x-panel>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/custom-element-tag-review.html', $document->children[0]->attr('part'));
    },
];
