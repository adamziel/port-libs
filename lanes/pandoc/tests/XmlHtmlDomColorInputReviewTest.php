<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html color input value provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="palette"><label for="brand">Brand</label>'
                . '<input id="brand" name="brand" type="color" value="#Aa00Ff">'
                . '<input id="missing" name="missing" type="color">'
                . '<input id="bad" name="bad" type="color" value="rebeccapurple">'
                . '<input id="short" name="short" type="color" value="#fff">'
                . '<input id="text" name="text" type="text" value="#112233"></form>',
            'color input value review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/color-input-value-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $brand = $form['children'][1];
        $missing = $form['children'][2];
        $bad = $form['children'][3];
        $short = $form['children'][4];
        $text = $form['children'][5];

        $t->same('palette', $form['elementId']);

        $t->same('color', $brand['inputType']);
        $t->same('html-color-input-value-review', $brand['colorInputReviewPolicy']);
        $t->same(true, $brand['colorInput']);
        $t->same('#Aa00Ff', $brand['colorValueRaw']);
        $t->same('#aa00ff', $brand['colorValue']);
        $t->same('valid-simple-color', $brand['colorValueState']);
        $t->same(true, $brand['colorValueValid']);
        $t->same(false, $brand['colorValueDefaulted']);
        $t->same(null, $brand['colorValueDefaultReason']);
        $t->same([170, 0, 255], $brand['colorValueRgb']);
        $t->same(170, $brand['colorValueRed']);
        $t->same(0, $brand['colorValueGreen']);
        $t->same(255, $brand['colorValueBlue']);
        $t->same([], $brand['colorInputIssueCodes']);
        $t->same(true, $brand['colorInputValid']);

        $t->same('color', $missing['inputType']);
        $t->same(null, $missing['colorValueRaw']);
        $t->same('#000000', $missing['colorValue']);
        $t->same('missing', $missing['colorValueState']);
        $t->same(true, $missing['colorValueValid']);
        $t->same(true, $missing['colorValueDefaulted']);
        $t->same('missing-value-default', $missing['colorValueDefaultReason']);
        $t->same([0, 0, 0], $missing['colorValueRgb']);
        $t->same([], $missing['colorInputIssueCodes']);
        $t->same(true, $missing['colorInputValid']);

        $t->same('color', $bad['inputType']);
        $t->same('rebeccapurple', $bad['colorValueRaw']);
        $t->same('#000000', $bad['colorValue']);
        $t->same('invalid-simple-color', $bad['colorValueState']);
        $t->same(false, $bad['colorValueValid']);
        $t->same(true, $bad['colorValueDefaulted']);
        $t->same('invalid-value-default', $bad['colorValueDefaultReason']);
        $t->same([0, 0, 0], $bad['colorValueRgb']);
        $t->same(['invalid-color-input-value'], $bad['colorInputIssueCodes']);
        $t->same(false, $bad['colorInputValid']);

        $t->same('color', $short['inputType']);
        $t->same('#fff', $short['colorValueRaw']);
        $t->same('#000000', $short['colorValue']);
        $t->same('invalid-simple-color', $short['colorValueState']);
        $t->same(false, $short['colorValueValid']);
        $t->same(['invalid-color-input-value'], $short['colorInputIssueCodes']);

        $t->same('text', $text['inputType']);
        $t->same('#112233', $text['value']);
        $t->true(!array_key_exists('colorInputReviewPolicy', $text));
        $t->true(!array_key_exists('colorValue', $text));
        $t->true(!array_key_exists('colorInputIssueCodes', $text));

        $t->same(
            '<form id="palette"><label for="brand">Brand</label><input id="brand" name="brand" type="color" value="#Aa00Ff"><input id="missing" name="missing" type="color"><input id="bad" name="bad" type="color" value="rebeccapurple"><input id="short" name="short" type="color" value="#fff"><input id="text" name="text" type="text" value="#112233"></form>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/color-input-value-review.html', $document->children[0]->attr('part'));
    },
];
