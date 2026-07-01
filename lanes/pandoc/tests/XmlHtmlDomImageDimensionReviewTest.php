<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html image dimension attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<img id="hero" src="hero.jpg" alt="Hero" width="0640" height="0360">'
                . '<img id="icon" src="icon.svg" alt="Icon" width="0" height="024">'
                . '<img id="partial" src="partial.jpg" alt="Partial" width="320">'
                . '<img id="bad" src="bad.jpg" alt="Bad" width="-1" height="auto">'
                . '<img id="plain" src="plain.jpg" alt="Plain">',
            'image dimension review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/image-dimension-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $hero = $summary[0];
        $icon = $summary[1];
        $partial = $summary[2];
        $bad = $summary[3];
        $plain = $summary[4];

        $t->same('html-image-dimension-attribute-review', $hero['imageDimensionReviewPolicy']);
        $t->same(true, $hero['imageDimensionReviewOnlyNoFetch']);
        $t->same('0640', $hero['imageWidthRaw']);
        $t->same('640', $hero['imageWidth']);
        $t->same(true, $hero['imageWidthValid']);
        $t->same('0360', $hero['imageHeightRaw']);
        $t->same('360', $hero['imageHeight']);
        $t->same(true, $hero['imageHeightValid']);
        $t->same('complete', $hero['imageDimensionPairState']);
        $t->same('16:9', $hero['imageDimensionAspectRatio']);
        $t->same(16, $hero['imageDimensionAspectRatioWidth']);
        $t->same(9, $hero['imageDimensionAspectRatioHeight']);
        $t->same(true, $hero['imageDimensionAspectRatioAvailable']);
        $t->same([], $hero['imageDimensionIssueCodes']);
        $t->same(true, $hero['imageDimensionValid']);

        $t->same('0', $icon['imageWidth']);
        $t->same('24', $icon['imageHeight']);
        $t->same('complete', $icon['imageDimensionPairState']);
        $t->same(null, $icon['imageDimensionAspectRatio']);
        $t->same(false, $icon['imageDimensionAspectRatioAvailable']);
        $t->same(['zero-image-width'], $icon['imageDimensionIssueCodes']);
        $t->same(false, $icon['imageDimensionValid']);

        $t->same('320', $partial['imageWidth']);
        $t->same(null, $partial['imageHeightRaw']);
        $t->same(null, $partial['imageHeightValid']);
        $t->same('partial', $partial['imageDimensionPairState']);
        $t->same([
            ['code' => 'partial-image-dimensions', 'missing' => 'height'],
        ], $partial['imageDimensionIssues']);
        $t->same(['partial-image-dimensions'], $partial['imageDimensionIssueCodes']);

        $t->same('-1', $bad['imageWidthRaw']);
        $t->same(null, $bad['imageWidth']);
        $t->same(false, $bad['imageWidthValid']);
        $t->same('auto', $bad['imageHeightRaw']);
        $t->same(null, $bad['imageHeight']);
        $t->same(false, $bad['imageHeightValid']);
        $t->same('invalid', $bad['imageDimensionPairState']);
        $t->same([
            ['code' => 'invalid-image-width', 'value' => '-1'],
            ['code' => 'invalid-image-height', 'value' => 'auto'],
        ], $bad['imageDimensionIssues']);
        $t->same([
            'invalid-image-width',
            'invalid-image-height',
        ], $bad['imageDimensionIssueCodes']);
        $t->same(false, $bad['imageDimensionValid']);

        $t->same(null, $plain['imageWidthRaw']);
        $t->same(null, $plain['imageHeightRaw']);
        $t->same('missing', $plain['imageDimensionPairState']);
        $t->same(null, $plain['imageDimensionAspectRatio']);
        $t->same([], $plain['imageDimensionIssueCodes']);
        $t->same(true, $plain['imageDimensionValid']);

        $t->contains('width="0640"', $html);
        $t->contains('height="0360"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/image-dimension-review.html', $document->children[0]->attr('part'));
        json_encode([$hero, $icon, $partial, $bad, $plain], JSON_THROW_ON_ERROR);
    },
];
