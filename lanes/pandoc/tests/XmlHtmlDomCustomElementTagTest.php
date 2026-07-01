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
        $t->same('html-customized-built-in-element-review', $customizedBuiltIn['customElementUpgradeReviewPolicy']);
        $t->same('x-panel', $customizedBuiltIn['customElementUpgradeHostElement']);
        $t->same('BadWidget', $customizedBuiltIn['customElementUpgradeName']);
        $t->same(false, $customizedBuiltIn['customElementUpgradeReservedName']);
        $t->same(['invalid-custom-element-name'], $customizedBuiltIn['customElementUpgradeIssueCodes']);
        $t->same(false, $customizedBuiltIn['customElementUpgradeValid']);
        $t->same(false, $customizedBuiltIn['customizedBuiltInElement']);

        $t->same(
            '<review-card id="card" part="card"><h2>Review</h2></review-card>'
                . '<font-face id="reserved">Legacy</font-face>'
                . '<x-panel id="upgrade" is="BadWidget"><p>Nested</p></x-panel>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/custom-element-tag-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html customized built in element upgrade provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<button id="valid" is="review-button">Save</button>'
                . '<button id="reserved" is="font-face">Reserved</button>'
                . '<button id="empty" is="">Empty</button>',
            'customized built in element review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/customized-built-in-element-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $valid = $summary[0];
        $reserved = $summary[1];
        $empty = $summary[2];

        $t->same('html-customized-built-in-element-review', $valid['customElementUpgradeReviewPolicy']);
        $t->same('button', $valid['customElementUpgradeHostElement']);
        $t->same('review-button', $valid['isRaw']);
        $t->same('review-button', $valid['customElementName']);
        $t->same('review-button', $valid['customElementUpgradeName']);
        $t->same(false, $valid['customElementUpgradeReservedName']);
        $t->same([], $valid['customElementUpgradeIssueCodes']);
        $t->same(true, $valid['customElementValid']);
        $t->same(true, $valid['customElementUpgradeValid']);
        $t->same(true, $valid['customizedBuiltInElement']);

        $t->same('font-face', $reserved['isRaw']);
        $t->same('font-face', $reserved['customElementName']);
        $t->same(false, $reserved['customElementValid']);
        $t->same(true, $reserved['customElementUpgradeReservedName']);
        $t->same(['reserved-custom-element-name'], $reserved['customElementUpgradeIssueCodes']);
        $t->same(false, $reserved['customElementUpgradeValid']);
        $t->same(false, $reserved['customizedBuiltInElement']);

        $t->same('', $empty['isRaw']);
        $t->same(null, $empty['customElementName']);
        $t->same(false, $empty['customElementValid']);
        $t->same(false, $empty['customElementUpgradeReservedName']);
        $t->same(['invalid-custom-element-name'], $empty['customElementUpgradeIssueCodes']);
        $t->same(false, $empty['customElementUpgradeValid']);
        $t->same(false, $empty['customizedBuiltInElement']);

        $t->same(
            '<button id="valid" is="review-button">Save</button>'
                . '<button id="reserved" is="font-face">Reserved</button>'
                . '<button id="empty" is="">Empty</button>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/customized-built-in-element-review.html', $document->children[0]->attr('part'));
    },
];
