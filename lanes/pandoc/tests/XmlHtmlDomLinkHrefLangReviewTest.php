<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html link hreflang language tags for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<link rel="alternate" href="/en/" hreflang="EN-us">'
                . '<link rel="alternate" href="/sr/" hreflang="sr-Cyrl-rs">'
                . '<link rel="alternate" href="/default/" hreflang="x-default">'
                . '<link rel="alternate" href="/bad/" hreflang="bad tag">'
                . '<link rel="alternate" href="/empty/" hreflang="">'
                . '<link rel="canonical" href="/canonical/">'
                . '<p>Body</p>',
            'link hreflang review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/link-hreflang-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $english = $summary[0];
        $serbian = $summary[1];
        $default = $summary[2];
        $bad = $summary[3];
        $empty = $summary[4];
        $canonical = $summary[5];
        $paragraph = $summary[6];

        $t->same('link-hreflang-language-tag-review', $english['linkHrefLangReviewPolicy']);
        $t->same('EN-us', $english['linkHrefLangRaw']);
        $t->same('en-US', $english['linkHrefLang']);
        $t->same('en-US', $english['linkHrefLangCanonical']);
        $t->same(true, $english['linkHrefLangValid']);
        $t->same(false, $english['linkHrefLangEmpty']);
        $t->same(['EN', 'us'], $english['linkHrefLangSubtags']);
        $t->same('en', $english['linkHrefLangPrimarySubtag']);
        $t->same('US', $english['linkHrefLangRegionSubtag']);
        $t->same([], $english['linkHrefLangIssueCodes']);

        $t->same('sr-Cyrl-RS', $serbian['linkHrefLangCanonical']);
        $t->same('sr', $serbian['linkHrefLangPrimarySubtag']);
        $t->same('Cyrl', $serbian['linkHrefLangScriptSubtag']);
        $t->same('RS', $serbian['linkHrefLangRegionSubtag']);
        $t->same(true, $serbian['linkHrefLangTagValid']);

        $t->same('x-default', $default['linkHrefLangCanonical']);
        $t->same('x', $default['linkHrefLangPrimarySubtag']);
        $t->same(['default'], $default['linkHrefLangPrivateUseSubtags']);
        $t->same([], $default['linkIssues']);

        $t->same('bad tag', $bad['linkHrefLangRaw']);
        $t->same('bad tag', $bad['linkHrefLang']);
        $t->same(null, $bad['linkHrefLangCanonical']);
        $t->same(false, $bad['linkHrefLangValid']);
        $t->same([
            'language-tag-ascii-whitespace',
            'invalid-language-subtag',
            'invalid-primary-language-subtag',
        ], $bad['linkHrefLangTagIssueCodes']);
        $t->same(['invalid-link-hreflang'], $bad['linkHrefLangIssueCodes']);
        $t->same([
            [
                'code' => 'invalid-link-hreflang',
                'raw' => 'bad tag',
                'languageIssueCodes' => [
                    'language-tag-ascii-whitespace',
                    'invalid-language-subtag',
                    'invalid-primary-language-subtag',
                ],
            ],
        ], $bad['linkIssues']);

        $t->same('', $empty['linkHrefLangRaw']);
        $t->same(null, $empty['linkHrefLang']);
        $t->same(null, $empty['linkHrefLangCanonical']);
        $t->same(false, $empty['linkHrefLangValid']);
        $t->same(true, $empty['linkHrefLangEmpty']);
        $t->same(['empty-language-tag'], $empty['linkHrefLangTagIssueCodes']);
        $t->same(['empty-link-hreflang'], $empty['linkHrefLangIssueCodes']);
        $t->same([['code' => 'empty-link-hreflang']], $empty['linkIssues']);

        $t->same(null, $canonical['linkHrefLangRaw']);
        $t->same(null, $canonical['linkHrefLangValid']);
        $t->same([], $canonical['linkHrefLangIssueCodes']);
        $t->same('Body', $paragraph['text']);

        $t->contains('<link href="/en/" hreflang="EN-us" rel="alternate">', $html);
        $t->contains('<link href="/bad/" hreflang="bad tag" rel="alternate">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/link-hreflang-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
