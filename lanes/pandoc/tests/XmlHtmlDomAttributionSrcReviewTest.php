<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html hyperlink attributionsrc provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<a id="same-origin" href="/landing" attributionsrc>Same origin source</a>'
                . '<a id="multi" href="https://shop.example.test/buy" attributionsrc="https://metrics.example.test/register https://alt.example.test/register" referrerpolicy="strict-origin">Buy</a>'
                . '<map name="ad"><area id="bad" href="/slot" alt="Slot" attributionsrc="javascript:alert(1) mailto:ops@example.test /relative"></map>',
            'hyperlink attributionsrc review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hyperlink-attributionsrc-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $sameOrigin = $summary[0];
        $multi = $summary[1];
        $area = $summary[2]['children'][0];

        $t->same('a', $sameOrigin['hyperlink']);
        $t->same('hyperlink-attributionsrc-source-registration-review', $sameOrigin['hyperlinkAttributionSrcReviewPolicy']);
        $t->same('a', $sameOrigin['attributionSrcElement']);
        $t->same(true, $sameOrigin['attributionSrcRequested']);
        $t->same('', $sameOrigin['attributionSrcRaw']);
        $t->same(true, $sameOrigin['attributionSrcEmpty']);
        $t->same('navigation-source-origin', $sameOrigin['attributionSrcMode']);
        $t->same([], $sameOrigin['attributionSrcUrls']);
        $t->same(0, $sameOrigin['attributionSrcUrlCount']);
        $t->same([], $sameOrigin['attributionSrcIssueCodes']);
        $t->same(true, $sameOrigin['attributionSrcValid']);

        $t->same('a', $multi['hyperlink']);
        $t->same(false, $multi['attributionSrcEmpty']);
        $t->same('navigation-source-urls', $multi['attributionSrcMode']);
        $t->same([
            'https://metrics.example.test/register',
            'https://alt.example.test/register',
        ], $multi['attributionSrcUrls']);
        $t->same(2, $multi['attributionSrcUrlCount']);
        $t->same('absolute', $multi['attributionSrcUrlRecords'][0]['kind']);
        $t->same('https', $multi['attributionSrcUrlRecords'][0]['scheme']);
        $t->same(false, $multi['attributionSrcUrlRecords'][0]['unsafe']);
        $t->same('strict-origin', $multi['referrerPolicy']);
        $t->same(true, $multi['referrerPolicyValid']);
        $t->same([], $multi['attributionSrcIssueCodes']);
        $t->same(true, $multi['attributionSrcValid']);

        $t->same('area', $area['hyperlink']);
        $t->same('area', $area['attributionSrcElement']);
        $t->same('Slot', $area['label']);
        $t->same([
            'javascript:alert(1)',
            'mailto:ops@example.test',
            '/relative',
        ], $area['attributionSrcUrls']);
        $t->same(['javascript:alert(1)'], $area['unsafeAttributionSrcUrls']);
        $t->same(['mailto:ops@example.test'], $area['nonHttpAttributionSrcUrls']);
        $t->same(['unsafe-attributionsrc-url', 'non-http-attributionsrc-url'], $area['attributionSrcIssueCodes']);
        $t->same(false, $area['attributionSrcValid']);
        $t->same('relative', $area['attributionSrcUrlRecords'][2]['kind']);
        $t->same(null, $area['attributionSrcUrlRecords'][2]['scheme']);
        $t->same(false, $area['attributionSrcUrlRecords'][2]['unsafe']);

        $t->contains('attributionsrc=""', $html);
        $t->contains('attributionsrc="https://metrics.example.test/register https://alt.example.test/register"', $html);
        $t->contains('attributionsrc="javascript:alert(1) mailto:ops@example.test /relative"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/hyperlink-attributionsrc-review.html', $document->children[0]->attr('part'));
    },
];
