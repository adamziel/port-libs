<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html translate token diagnostics for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="locked" translate="no"><p id="body"><span id="bad" translate="maybe">Bad</span><span id="empty" translate="">Empty</span><span id="open" translate="yes"><em id="open-child">Open</em></span></p></article>'
                . '<section id="plain"><p id="plain-child">Plain</p></section>',
            'translate token diagnostics review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $packet = XmlHtmlDom::summarizeHtmlFragmentReviewPacket($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/translate-token-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $body = $article['children'][0];
        $bad = $body['children'][0];
        $empty = $body['children'][1];
        $open = $body['children'][2];
        $openChild = $open['children'][0];
        $plainChild = $summary[1]['children'][0];

        $t->same('html-translate-token-inheritance-review', $article['translateReviewPolicy']);
        $t->same('no', $article['translateRaw']);
        $t->same('no', $article['translateKeyword']);
        $t->same(false, $article['translate']);
        $t->same(true, $article['translateValid']);
        $t->same(false, $article['translateInvalidValueIgnored']);
        $t->same([], $article['translateIssueCodes']);
        $t->same(false, $article['effectiveTranslate']);
        $t->same(false, $article['translateInherited']);
        $t->same('self-translate', $article['translateSource']);

        $t->true(!array_key_exists('translateRaw', $body));
        $t->same(false, $body['effectiveTranslate']);
        $t->same(true, $body['translateInherited']);
        $t->same('locked', $body['translateSourceElementId']);

        $t->same('maybe', $bad['translateRaw']);
        $t->same(null, $bad['translateKeyword']);
        $t->same(null, $bad['translate']);
        $t->same(false, $bad['translateValid']);
        $t->same(true, $bad['translateInvalidValueIgnored']);
        $t->same(['invalid-html-translate-value'], $bad['translateIssueCodes']);
        $t->same(false, $bad['effectiveTranslate']);
        $t->same(true, $bad['translateInherited']);
        $t->same('locked', $bad['translateSourceElementId']);

        $t->same('', $empty['translateRaw']);
        $t->same('yes', $empty['translateKeyword']);
        $t->same(true, $empty['translate']);
        $t->same(true, $empty['translateValid']);
        $t->same(true, $empty['effectiveTranslate']);
        $t->same(false, $empty['translateInherited']);

        $t->same('yes', $open['translateRaw']);
        $t->same('yes', $open['translateKeyword']);
        $t->same(true, $open['translate']);
        $t->same(true, $open['effectiveTranslate']);
        $t->same(false, $open['translateInherited']);
        $t->same(true, $openChild['effectiveTranslate']);
        $t->same(true, $openChild['translateInherited']);
        $t->same('open', $openChild['translateSourceElementId']);
        $t->true(!array_key_exists('effectiveTranslate', $plainChild));

        $t->same(4, $packet['translateAttributeCount']);
        $t->same(1, $packet['invalidTranslateAttributeCount']);
        $t->same(6, $packet['effectiveTranslateElementCount']);
        $t->same(3, $packet['effectiveTranslateEnabledElementCount']);
        $t->same(3, $packet['effectiveTranslateDisabledElementCount']);
        $t->same(3, $packet['inheritedTranslateElementCount']);
        $t->same('span', $packet['nodes'][0]['children'][0]['children'][0]['name']);
        $t->same(['invalid-html-translate-value'], $packet['nodes'][0]['children'][0]['children'][0]['translateIssueCodes']);

        $t->contains('<span id="empty" translate="">Empty</span>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/translate-token-review.html', $document->children[0]->attr('part'));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
];
