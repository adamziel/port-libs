<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html data value review metadata for reviewer handoff' => static function (TestRunner $t): void {
        $oversize = str_repeat('A', 257);
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>'
                . '<data id="sku" value=" SKU-42 ">Legacy SKU</data>'
                . '<data id="empty" value="">Empty value</data>'
                . '<data id="unsafe" value="bad&lt;tag">Unsafe value</data>'
                . '<data id="missing">Missing value</data>'
                . '<data id="oversize" value="' . $oversize . '">Oversize value</data>'
                . '</p>',
            'data value review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/data-value-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $sku = $paragraph['children'][0];
        $empty = $paragraph['children'][1];
        $unsafe = $paragraph['children'][2];
        $missing = $paragraph['children'][3];
        $tooLong = $paragraph['children'][4];

        $t->same('html-data-element-value-review', $sku['dataValueReviewPolicy']);
        $t->same('html-data-value-review', $sku['dataValueMetadataReviewPolicy']);
        $t->same(' SKU-42 ', $sku['dataValueRaw']);
        $t->same('SKU-42', $sku['dataValue']);
        $t->same('SKU-42', $sku['dataValueReviewerMetadataValue']);
        $t->same(true, $sku['dataValuePresent']);
        $t->same(false, $sku['dataValueEmpty']);
        $t->same(8, $sku['dataValueByteLength']);
        $t->same(6, $sku['dataValueNormalizedByteLength']);
        $t->same([], $sku['dataValueIssueCodes']);
        $t->same([], $sku['dataValueMetadataIssueCodes']);
        $t->same(0, $sku['dataValueIssueCount']);
        $t->same(0, $sku['dataValueMetadataIssueCount']);
        $t->same(true, $sku['dataValueUsable']);
        $t->same(true, $sku['dataValueMetadataValid']);
        $t->same(true, $sku['dataValueMetadataConforming']);

        $t->same('', $empty['dataValueRaw']);
        $t->same('', $empty['dataValue']);
        $t->same(null, $empty['dataValueReviewerMetadataValue']);
        $t->same(true, $empty['dataValuePresent']);
        $t->same(true, $empty['dataValueEmpty']);
        $t->same(['empty-data-element-value'], $empty['dataValueIssueCodes']);
        $t->same(['empty-data-value'], $empty['dataValueMetadataIssueCodes']);
        $t->same('value-attribute', $empty['dataValueMetadataIssues'][0]['source']);
        $t->same(0, $empty['dataValueMetadataIssues'][0]['byteLength']);
        $t->same(false, $empty['dataValueUsable']);
        $t->same(false, $empty['dataValueMetadataValid']);

        $t->same('bad<tag', $unsafe['dataValueRaw']);
        $t->same('bad<tag', $unsafe['dataValue']);
        $t->same(null, $unsafe['dataValueReviewerMetadataValue']);
        $t->same([], $unsafe['dataValueIssueCodes']);
        $t->same(true, $unsafe['dataValueUsable']);
        $t->same(['unsafe-data-value-token'], $unsafe['dataValueMetadataIssueCodes']);
        $t->same('value-attribute', $unsafe['dataValueMetadataIssues'][0]['source']);
        $t->same(7, $unsafe['dataValueMetadataIssues'][0]['normalizedByteLength']);
        $t->same(false, $unsafe['dataValueMetadataConforming']);

        $t->same(null, $missing['dataValueRaw']);
        $t->same(null, $missing['dataValue']);
        $t->same(false, $missing['dataValuePresent']);
        $t->same(false, $missing['dataValueEmpty']);
        $t->same(['missing-data-element-value'], $missing['dataValueIssueCodes']);
        $t->same(['missing-data-value'], $missing['dataValueMetadataIssueCodes']);
        $t->same('missing', $missing['dataValueMetadataIssues'][0]['source']);
        $t->same(1, $missing['dataValueIssueCount']);
        $t->same(1, $missing['dataValueMetadataIssueCount']);

        $t->same($oversize, $tooLong['dataValue']);
        $t->same(257, $tooLong['dataValueNormalizedByteLength']);
        $t->same([], $tooLong['dataValueIssueCodes']);
        $t->same(['oversize-data-value'], $tooLong['dataValueMetadataIssueCodes']);
        $t->same(257, $tooLong['dataValueMetadataIssues'][0]['normalizedByteLength']);
        $t->same(null, $tooLong['dataValueReviewerMetadataValue']);
        $t->same(false, $tooLong['dataValueMetadataValid']);

        $t->contains('value=" SKU-42 "', $html);
        $t->contains('value="bad&lt;tag"', $html);
        $t->contains('id="oversize"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/data-value-review.html', $document->children[0]->attr('part'));
        json_encode([$sku, $empty, $unsafe, $missing, $tooLong], JSON_THROW_ON_ERROR);
    },
];
