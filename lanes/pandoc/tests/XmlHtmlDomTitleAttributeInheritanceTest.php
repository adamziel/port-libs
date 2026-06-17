<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html title advisory inheritance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="root" title="  Review   Packet  ">'
                . '<p id="child">Child</p>'
                . '<section id="empty" title=""><span id="blocked">Blocked</span></section>'
                . '<aside id="own" title=" Local advisory ">Own</aside>'
                . '<p id="plain">Plain</p>'
                . '</article>'
                . '<div id="outside"><span id="outside-child">Outside</span></div>',
            'title advisory inheritance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/title-advisory-inheritance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $child = $article['children'][0];
        $empty = $article['children'][1];
        $blocked = $empty['children'][0];
        $own = $article['children'][2];
        $plain = $article['children'][3];
        $outside = $summary[1];
        $outsideChild = $outside['children'][0];

        $t->same('  Review   Packet  ', $article['titleAttribute']);
        $t->same('html-title-advisory-inheritance-review', $article['titleAttributeReviewPolicy']);
        $t->same('  Review   Packet  ', $article['effectiveTitleAttributeRaw']);
        $t->same('Review Packet', $article['effectiveTitleAttribute']);
        $t->same(false, $article['titleAttributeEmpty']);
        $t->same(false, $article['titleAttributeInherited']);
        $t->same('self-title', $article['titleAttributeSource']);
        $t->same('article', $article['titleAttributeSourceElement']);
        $t->same('root', $article['titleAttributeSourceElementId']);
        $t->same(false, $article['titleAttributeInheritanceBlocked']);

        $t->true(!array_key_exists('titleAttribute', $child));
        $t->same('Review Packet', $child['effectiveTitleAttribute']);
        $t->same(true, $child['titleAttributeInherited']);
        $t->same('ancestor-title', $child['titleAttributeSource']);
        $t->same('root', $child['titleAttributeSourceElementId']);

        $t->same('', $empty['titleAttribute']);
        $t->same('', $empty['effectiveTitleAttributeRaw']);
        $t->same(null, $empty['effectiveTitleAttribute']);
        $t->same(true, $empty['titleAttributeEmpty']);
        $t->same(false, $empty['titleAttributeInherited']);
        $t->same(true, $empty['titleAttributeInheritanceBlocked']);

        $t->true(!array_key_exists('titleAttribute', $blocked));
        $t->same('', $blocked['effectiveTitleAttributeRaw']);
        $t->same(null, $blocked['effectiveTitleAttribute']);
        $t->same(true, $blocked['titleAttributeEmpty']);
        $t->same(true, $blocked['titleAttributeInherited']);
        $t->same('empty', $blocked['titleAttributeSourceElementId']);
        $t->same(true, $blocked['titleAttributeInheritanceBlocked']);

        $t->same(' Local advisory ', $own['titleAttribute']);
        $t->same('Local advisory', $own['effectiveTitleAttribute']);
        $t->same(false, $own['titleAttributeInherited']);
        $t->same('self-title', $own['titleAttributeSource']);

        $t->same('Review Packet', $plain['effectiveTitleAttribute']);
        $t->same(true, $plain['titleAttributeInherited']);
        $t->true(!array_key_exists('titleAttributeReviewPolicy', $outside));
        $t->true(!array_key_exists('effectiveTitleAttribute', $outsideChild));

        $t->same(
            '<article id="root" title="  Review   Packet  "><p id="child">Child</p><section id="empty" title=""><span id="blocked">Blocked</span></section><aside id="own" title=" Local advisory ">Own</aside><p id="plain">Plain</p></article><div id="outside"><span id="outside-child">Outside</span></div>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/title-advisory-inheritance-review.html', $document->children[0]->attr('part'));
        json_encode([$article, $child, $empty, $blocked, $own, $plain], JSON_THROW_ON_ERROR);
    },
];
