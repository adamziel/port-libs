<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html dir validity and inherited fallback for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="root" dir="RTL"><p id="invalid" dir="sideways"><span id="child">Child</span></p><p id="auto" dir="Auto">Auto</p><p id="empty" dir="">Empty</p><section id="plain">Plain</section></article>',
            'direction validity review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/direction-validity-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $invalid = $article['children'][0];
        $child = $invalid['children'][0];
        $auto = $article['children'][1];
        $empty = $article['children'][2];
        $plain = $article['children'][3];

        $t->same('RTL', $article['dirRaw']);
        $t->same('rtl', $article['direction']);
        $t->same(true, $article['directionValid']);
        $t->same(false, $article['directionInvalidValueIgnored']);
        $t->same('rtl', $article['effectiveDirection']);
        $t->same(false, $article['directionInherited']);

        $t->same('sideways', $invalid['dirRaw']);
        $t->same(null, $invalid['direction']);
        $t->same(false, $invalid['directionValid']);
        $t->same(true, $invalid['directionInvalidValueIgnored']);
        $t->same('rtl', $invalid['effectiveDirection']);
        $t->same(true, $invalid['directionInherited']);
        $t->same('root', $invalid['directionSourceElementId']);

        $t->same('rtl', $child['effectiveDirection']);
        $t->same(true, $child['directionInherited']);
        $t->same('root', $child['directionSourceElementId']);

        $t->same('Auto', $auto['dirRaw']);
        $t->same('auto', $auto['direction']);
        $t->same(true, $auto['directionValid']);
        $t->same(false, $auto['directionInherited']);

        $t->same('', $empty['dirRaw']);
        $t->same(null, $empty['direction']);
        $t->same(false, $empty['directionValid']);
        $t->same(true, $empty['directionInvalidValueIgnored']);
        $t->same('rtl', $empty['effectiveDirection']);

        $t->true(!array_key_exists('dirRaw', $plain));
        $t->same('rtl', $plain['effectiveDirection']);
        $t->same(true, $plain['directionInherited']);

        $t->same(
            '<article dir="RTL" id="root"><p dir="sideways" id="invalid"><span id="child">Child</span></p><p dir="Auto" id="auto">Auto</p><p dir="" id="empty">Empty</p><section id="plain">Plain</section></article>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/direction-validity-review.html', $document->children[0]->attr('part'));
    },
];
