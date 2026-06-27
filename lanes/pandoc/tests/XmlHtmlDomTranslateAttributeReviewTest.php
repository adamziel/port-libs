<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html translate attribute provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="packet" translate="no">'
                . '<p id="allow" translate="yes">Allow <span id="allow-child">Child</span></p>'
                . '<aside id="empty" translate="">Empty</aside>'
                . '<section id="invalid" translate="maybe"><em id="invalid-child">Invalid child</em></section>'
                . '<div id="plain">Plain</div></article>',
            'translate attribute review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/translate-attribute-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $root = $summary[0];
        $allow = $root['children'][0];
        $allowChild = $allow['children'][1];
        $empty = $root['children'][1];
        $invalid = $root['children'][2];
        $invalidChild = $invalid['children'][0];
        $plain = $root['children'][3];

        $t->same('html-translate-attribute-review', $root['translateAttributeReviewPolicy']);
        $t->same('no', $root['translateRaw']);
        $t->same('no', $root['translateKeyword']);
        $t->same(false, $root['translate']);
        $t->same('no', $root['translateState']);
        $t->same(true, $root['translateValid']);
        $t->same(false, $root['translateInvalidValueIgnored']);
        $t->same([], $root['translateIssueCodes']);
        $t->same(false, $root['effectiveTranslate']);
        $t->same(false, $root['translateInherited']);
        $t->same('self-translate', $root['translateSource']);

        $t->same('yes', $allow['translateRaw']);
        $t->same('yes', $allow['translateKeyword']);
        $t->same(true, $allow['translate']);
        $t->same('yes', $allow['translateState']);
        $t->same(true, $allow['effectiveTranslate']);
        $t->same(false, $allow['translateInherited']);
        $t->same(true, $allowChild['effectiveTranslate']);
        $t->same(true, $allowChild['translateInherited']);
        $t->same('allow', $allowChild['translateSourceElementId']);
        $t->true(!array_key_exists('translateAttributeReviewPolicy', $allowChild));

        $t->same('', $empty['translateRaw']);
        $t->same('yes', $empty['translateKeyword']);
        $t->same(true, $empty['translate']);
        $t->same('yes', $empty['translateState']);
        $t->same(true, $empty['translateValid']);
        $t->same(true, $empty['effectiveTranslate']);

        $t->same('maybe', $invalid['translateRaw']);
        $t->same(null, $invalid['translateKeyword']);
        $t->same(null, $invalid['translate']);
        $t->same('invalid', $invalid['translateState']);
        $t->same(false, $invalid['translateValid']);
        $t->same(true, $invalid['translateInvalidValueIgnored']);
        $t->same(['invalid-html-translate-token'], $invalid['translateIssueCodes']);
        $t->same([['code' => 'invalid-html-translate-token', 'translateRaw' => 'maybe']], $invalid['translateIssues']);
        $t->same(false, $invalid['effectiveTranslate']);
        $t->same(true, $invalid['translateInherited']);
        $t->same('packet', $invalid['translateSourceElementId']);
        $t->same(false, $invalidChild['effectiveTranslate']);
        $t->same('packet', $invalidChild['translateSourceElementId']);

        $t->same(false, $plain['effectiveTranslate']);
        $t->same(true, $plain['translateInherited']);
        $t->same('packet', $plain['translateSourceElementId']);
        $t->true(!array_key_exists('translateAttributeReviewPolicy', $plain));

        $t->same(
            '<article id="packet" translate="no"><p id="allow" translate="yes">Allow <span id="allow-child">Child</span></p><aside id="empty" translate="">Empty</aside><section id="invalid" translate="maybe"><em id="invalid-child">Invalid child</em></section><div id="plain">Plain</div></article>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/translate-attribute-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
