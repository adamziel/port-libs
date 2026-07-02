<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html link icon sizes token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<link id="icon" rel="icon" href="/favicon.png" sizes="ANY 16x16 32X32 16x16 bad 0x32 48x0">'
                . '<link id="stylesheet" rel="stylesheet" href="/site.css" sizes="64x64">'
                . '<link id="empty" rel="icon" href="/empty.ico" sizes="">',
            'link icon sizes review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/link-icon-sizes-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $icon = $summary[0];
        $stylesheet = $summary[1];
        $empty = $summary[2];

        $t->same('link-icon-sizes-token-review', $icon['linkSizesReviewPolicy']);
        $t->same('ANY 16x16 32X32 16x16 bad 0x32 48x0', $icon['linkSizesRaw']);
        $t->same(['ANY', '16x16', '32X32', '16x16', 'bad', '0x32', '48x0'], $icon['linkSizesTokens']);
        $t->same(['any', '16x16', '32x32', '16x16'], $icon['linkValidSizeTokens']);
        $t->same(['any', '16x16', '32x32'], $icon['linkUniqueSizeTokens']);
        $t->same(['bad', '0x32', '48x0'], $icon['invalidLinkSizeTokens']);
        $t->same(['16x16'], $icon['duplicateLinkSizeTokens']);
        $t->same(true, $icon['linkSizesAppliesToIcon']);
        $t->same(true, $icon['linkIconSizeAny']);
        $t->same(3, $icon['linkIconSizeDimensionCount']);
        $t->same(['width' => 16, 'height' => 16, 'token' => '16x16'], $icon['linkIconSizeDimensions'][0]);
        $t->same(['width' => 32, 'height' => 32, 'token' => '32x32'], $icon['linkIconSizeDimensions'][1]);
        $t->same([
            'invalid-link-size-token',
            'duplicate-link-size-token',
        ], $icon['linkSizesIssueCodes']);
        $t->same(false, $icon['linkSizesValid']);

        $t->same(false, $stylesheet['linkSizesAppliesToIcon']);
        $t->same(['64x64'], $stylesheet['linkValidSizeTokens']);
        $t->same(['link-sizes-without-icon-rel'], $stylesheet['linkSizesIssueCodes']);
        $t->same(false, $stylesheet['linkSizesValid']);

        $t->same([], $empty['linkSizesTokens']);
        $t->same([], $empty['linkValidSizeTokens']);
        $t->same(['empty-link-sizes'], $empty['linkSizesIssueCodes']);
        $t->same(false, $empty['linkSizesValid']);

        $t->contains('sizes="ANY 16x16 32X32 16x16 bad 0x32 48x0"', $html);
        $t->contains('sizes="64x64"', $html);
        $t->contains('sizes=""', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/link-icon-sizes-review.html', $document->children[0]->attr('part'));
    },
];
