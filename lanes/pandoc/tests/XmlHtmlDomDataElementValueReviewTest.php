<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html data element value diagnostics for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>Records '
                . '<data id="sku" value=" SKU-42 ">Packet 42</data> '
                . '<data id="same" value="Same text">Same text</data> '
                . '<data id="empty" value="  ">Empty value</data> '
                . '<data id="missing" data-review="no-machine-value">Missing value</data>'
                . '</p>',
            'data element value diagnostics review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/data-element-value-diagnostics-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $sku = $paragraph['children'][1];
        $same = $paragraph['children'][3];
        $empty = $paragraph['children'][5];
        $missing = $paragraph['children'][7];

        $t->same('html-data-element-value-review', $sku['dataValueReviewPolicy']);
        $t->same('Packet 42', $sku['dataText']);
        $t->same(' SKU-42 ', $sku['dataValueRaw']);
        $t->same('SKU-42', $sku['dataValue']);
        $t->same(true, $sku['dataValuePresent']);
        $t->same(false, $sku['dataValueEmpty']);
        $t->same(false, $sku['dataValueTextMatches']);
        $t->same(true, $sku['dataValueUsable']);
        $t->same([], $sku['dataValueIssueCodes']);
        $t->same([], $sku['dataValueIssues']);

        $t->same('Same text', $same['dataValue']);
        $t->same(true, $same['dataValueTextMatches']);
        $t->same(true, $same['dataValueUsable']);
        $t->same([], $same['dataValueIssueCodes']);

        $t->same('  ', $empty['dataValueRaw']);
        $t->same('', $empty['dataValue']);
        $t->same(true, $empty['dataValuePresent']);
        $t->same(true, $empty['dataValueEmpty']);
        $t->same(false, $empty['dataValueTextMatches']);
        $t->same(false, $empty['dataValueUsable']);
        $t->same(['empty-data-element-value'], $empty['dataValueIssueCodes']);
        $t->same([['code' => 'empty-data-element-value']], $empty['dataValueIssues']);

        $t->same(null, $missing['dataValueRaw']);
        $t->same(null, $missing['dataValue']);
        $t->same(false, $missing['dataValuePresent']);
        $t->same(false, $missing['dataValueEmpty']);
        $t->same(false, $missing['dataValueTextMatches']);
        $t->same(false, $missing['dataValueUsable']);
        $t->same(['missing-data-element-value'], $missing['dataValueIssueCodes']);
        $t->same([['code' => 'missing-data-element-value']], $missing['dataValueIssues']);
        $t->same(['review' => 'no-machine-value'], $missing['dataset']);

        $t->same(
            '<p>Records <data id="sku" value=" SKU-42 ">Packet 42</data> <data id="same" value="Same text">Same text</data> <data id="empty" value="  ">Empty value</data> <data data-review="no-machine-value" id="missing">Missing value</data></p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/data-element-value-diagnostics-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
