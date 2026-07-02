<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html hyperlink hreflang review metadata' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><a id="canonical" href="/en-us" hreflang="EN-us">US English</a>'
                . '<a id="fallback" href="/fallback" hreflang="x-default">Fallback</a></p>'
                . '<p><a id="empty" href="/empty" hreflang="">Empty</a>'
                . '<a id="invalid" href="/bad" hreflang="bad tag">Bad</a></p>'
                . '<map name="languages"><area alt="French Canada" href="/fr-ca" hreflang="fr-ca"></map>',
            'hyperlink hreflang review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hyperlink-hreflang-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $canonical = $summary[0]['children'][0];
        $fallback = $summary[0]['children'][1];
        $empty = $summary[1]['children'][0];
        $invalid = $summary[1]['children'][1];
        $area = $summary[2]['areas'][0];

        $t->same('a', $canonical['hyperlinkHreflangReview']);
        $t->same('html-hyperlink-hreflang-language-tag-review', $canonical['hyperlinkHreflangReviewPolicy']);
        $t->same(true, $canonical['hreflangRequested']);
        $t->same('EN-us', $canonical['hreflangRaw']);
        $t->same('en-US', $canonical['hreflangNormalized']);
        $t->same(true, $canonical['hreflangValid']);
        $t->same([], $canonical['hreflangIssueCodes']);
        $t->same(0, $canonical['hreflangIssueCount']);

        $t->same('x-default', $fallback['hreflangRaw']);
        $t->same('x-default', $fallback['hreflangNormalized']);
        $t->same(true, $fallback['hreflangValid']);

        $t->same('', $empty['hreflangRaw']);
        $t->same(null, $empty['hreflangNormalized']);
        $t->same(false, $empty['hreflangValid']);
        $t->same(['empty-hyperlink-hreflang'], $empty['hreflangIssueCodes']);
        $t->same([['code' => 'empty-hyperlink-hreflang']], $empty['hreflangIssues']);
        $t->same(1, $empty['hreflangIssueCount']);

        $t->same('bad tag', $invalid['hreflangRaw']);
        $t->same(null, $invalid['hreflangNormalized']);
        $t->same(false, $invalid['hreflangValid']);
        $t->same(['invalid-hyperlink-hreflang'], $invalid['hreflangIssueCodes']);
        $t->same([
            ['code' => 'invalid-hyperlink-hreflang', 'hreflangRaw' => 'bad tag'],
        ], $invalid['hreflangIssues']);
        $t->same(1, $invalid['hreflangIssueCount']);

        $t->same('area', $area['hyperlinkHreflangReview']);
        $t->same('fr-ca', $area['hreflangRaw']);
        $t->same('fr-CA', $area['hreflangNormalized']);
        $t->same(true, $area['hreflangValid']);
        $t->same([], $area['hreflangIssueCodes']);

        $t->contains('hreflang="EN-us"', $html);
        $t->contains('hreflang=""', $html);
        $t->contains('hreflang="bad tag"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/hyperlink-hreflang-review.html', $document->children[0]->attr('part'));
    },
];
