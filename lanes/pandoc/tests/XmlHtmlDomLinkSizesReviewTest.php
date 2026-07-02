<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html link icon sizes metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<link rel="icon" href="/favicon.ico" sizes="16x16 32X32 any 32x32">'
                . '<link rel="apple-touch-icon" href="/touch.png" sizes="180x180">'
                . '<link rel="icon" href="/bad.png" sizes="0x32 16 x16 10xbad">'
                . '<link rel="icon" href="/empty.png" sizes>'
                . '<link rel="stylesheet" href="/site.css" sizes="any">',
            'link icon sizes review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/link-icon-sizes-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $icon = $summary[0];
        $touchIcon = $summary[1];
        $bad = $summary[2];
        $empty = $summary[3];
        $stylesheet = $summary[4];

        $t->same('html-link-icon-sizes-token-review', $icon['linkSizesReviewPolicy']);
        $t->same(true, $icon['linkSizesAttributePresent']);
        $t->same('16x16 32X32 any 32x32', $icon['linkSizesRaw']);
        $t->same(['16x16', '32X32', 'any', '32x32'], $icon['linkSizesTokens']);
        $t->same(true, $icon['linkSizesAppliesToIconRel']);
        $t->same(['icon'], $icon['linkSizesIconRelTokens']);
        $t->same(true, $icon['linkSizesAnyPresent']);
        $t->same(3, $icon['linkSizesDimensionCount']);
        $t->same([
            ['token' => '16x16', 'normalized' => '16x16', 'width' => 16, 'height' => 16],
            ['token' => '32X32', 'normalized' => '32x32', 'width' => 32, 'height' => 32],
            ['token' => '32x32', 'normalized' => '32x32', 'width' => 32, 'height' => 32],
        ], $icon['linkSizesDimensions']);
        $t->same('dimension', $icon['linkSizesTokenRecords'][0]['kind']);
        $t->same('32x32', $icon['linkSizesTokenRecords'][1]['normalized']);
        $t->same('any', $icon['linkSizesTokenRecords'][2]['kind']);
        $t->same(['32x32'], $icon['duplicateLinkSizeTokens']);
        $t->same([], $icon['invalidLinkSizeTokens']);
        $t->same(['duplicate-link-size-token'], $icon['linkSizesIssueCodes']);
        $t->same([
            ['code' => 'duplicate-link-size-token', 'token' => '32x32', 'count' => 2],
        ], $icon['linkSizesIssues']);
        $t->same(false, $icon['linkSizesValid']);

        $t->same(['apple-touch-icon'], $touchIcon['linkResourceRelTokens']);
        $t->same(['icon'], $touchIcon['linkResourceKinds']);
        $t->same(true, $touchIcon['linkHrefRequired']);
        $t->same(['apple-touch-icon'], $touchIcon['linkSizesIconRelTokens']);
        $t->same([['token' => '180x180', 'normalized' => '180x180', 'width' => 180, 'height' => 180]], $touchIcon['linkSizesDimensions']);
        $t->same([], $touchIcon['linkSizesIssueCodes']);
        $t->same(true, $touchIcon['linkSizesValid']);

        $t->same(['0x32', '16', 'x16', '10xbad'], $bad['invalidLinkSizeTokens']);
        $t->same('invalid', $bad['linkSizesTokenRecords'][0]['kind']);
        $t->same(false, $bad['linkSizesTokenRecords'][0]['valid']);
        $t->same(['invalid-link-size-token'], $bad['linkSizesIssueCodes']);
        $t->same(false, $bad['linkSizesValid']);

        $t->same('', $empty['linkSizesRaw']);
        $t->same([], $empty['linkSizesTokens']);
        $t->same(['empty-link-sizes'], $empty['linkSizesIssueCodes']);
        $t->same(false, $empty['linkSizesValid']);

        $t->same(['stylesheet'], $stylesheet['linkResourceRelTokens']);
        $t->same(['stylesheet'], $stylesheet['linkResourceKinds']);
        $t->same(false, $stylesheet['linkSizesAppliesToIconRel']);
        $t->same([], $stylesheet['linkSizesIconRelTokens']);
        $t->same(['link-sizes-without-icon-rel'], $stylesheet['linkSizesIssueCodes']);
        $t->same([
            ['code' => 'link-sizes-without-icon-rel', 'relTokens' => ['stylesheet']],
        ], $stylesheet['linkSizesIssues']);
        $t->same(false, $stylesheet['linkSizesValid']);

        $t->contains('sizes="16x16 32X32 any 32x32"', $html);
        $t->contains('rel="apple-touch-icon"', $html);
        $t->contains('sizes=""', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/link-icon-sizes-review.html', $document->children[0]->attr('part'));
    },
];
