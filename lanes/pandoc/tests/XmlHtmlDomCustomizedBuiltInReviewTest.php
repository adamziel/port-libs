<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html customized built-in provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<button id="save" is="x-save-button" value="ok">Save</button>'
                . '<ul id="toc" is="x-toc-list"><li>Intro</li></ul>'
                . '<p id="bad" is="BadWidget">Bad</p>'
                . '<section id="reserved" is="font-face">Reserved</section>'
                . '<x-panel id="auto" is="x-panel-upgrade">Panel</x-panel>',
            'customized built-in review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/customized-built-in-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $button = $summary[0];
        $list = $summary[1];
        $bad = $summary[2];
        $reserved = $summary[3];
        $autonomous = $summary[4];

        $t->same('html-customized-built-in-upgrade-review', $button['customizedBuiltInReviewPolicy']);
        $t->same('button', $button['customizedBuiltInHostName']);
        $t->same('built-in-element', $button['customizedBuiltInHostKind']);
        $t->same(false, $button['customizedBuiltInHostIsAutonomous']);
        $t->same(true, $button['customizedBuiltInHostIsBuiltIn']);
        $t->same('x-save-button', $button['customizedBuiltInNameRaw']);
        $t->same('x-save-button', $button['customizedBuiltInName']);
        $t->same(true, $button['customizedBuiltInNameValid']);
        $t->same(false, $button['customizedBuiltInReservedName']);
        $t->same(true, $button['customizedBuiltInElement']);
        $t->same(true, $button['customizedBuiltInWouldUpgrade']);
        $t->same(true, $button['customizedBuiltInReviewOnlyNoRegistryLookup']);
        $t->same([], $button['customizedBuiltInIssueCodes']);
        $t->same(true, $button['customizedBuiltInValid']);
        $t->same('button', $button['formControl']);
        $t->same('x-save-button', $button['customElementName']);
        $t->same(true, $button['customElementValid']);

        $t->same('ul', $list['customizedBuiltInHostName']);
        $t->same('x-toc-list', $list['customizedBuiltInName']);
        $t->same(true, $list['customizedBuiltInValid']);
        $t->same('unordered', $list['list']);

        $t->same('BadWidget', $bad['isRaw']);
        $t->same('BadWidget', $bad['customizedBuiltInName']);
        $t->same(false, $bad['customizedBuiltInNameValid']);
        $t->same(false, $bad['customizedBuiltInValid']);
        $t->same(['invalid-custom-element-name'], $bad['customizedBuiltInIssueCodes']);
        $t->same([['code' => 'invalid-custom-element-name', 'isRaw' => 'BadWidget']], $bad['customizedBuiltInIssues']);

        $t->same('font-face', $reserved['customizedBuiltInName']);
        $t->same(false, $reserved['customizedBuiltInNameValid']);
        $t->same(true, $reserved['customizedBuiltInReservedName']);
        $t->same(['reserved-custom-element-name'], $reserved['customizedBuiltInIssueCodes']);

        $t->same('x-panel', $autonomous['name']);
        $t->same('html-custom-element-tag-review', $autonomous['customElementTagReviewPolicy']);
        $t->same('autonomous-custom-element', $autonomous['customizedBuiltInHostKind']);
        $t->same(true, $autonomous['customizedBuiltInHostIsAutonomous']);
        $t->same(false, $autonomous['customizedBuiltInHostIsBuiltIn']);
        $t->same('x-panel-upgrade', $autonomous['customizedBuiltInName']);
        $t->same(true, $autonomous['customizedBuiltInNameValid']);
        $t->same(false, $autonomous['customizedBuiltInElement']);
        $t->same(false, $autonomous['customizedBuiltInWouldUpgrade']);
        $t->same(false, $autonomous['customizedBuiltInValid']);
        $t->same(['is-attribute-on-autonomous-custom-element'], $autonomous['customizedBuiltInIssueCodes']);

        $t->same(
            '<button id="save" is="x-save-button" value="ok">Save</button>'
                . '<ul id="toc" is="x-toc-list"><li>Intro</li></ul>'
                . '<p id="bad" is="BadWidget">Bad</p>'
                . '<section id="reserved" is="font-face">Reserved</section>'
                . '<x-panel id="auto" is="x-panel-upgrade">Panel</x-panel>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/customized-built-in-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
