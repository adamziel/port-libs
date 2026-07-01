<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html meta viewport directives for reviewer handoff' => static function (TestRunner $t): void {
        $viewport = 'width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content, width=320, mystery=yes, bad<name=value, shrink-to-fit=no';
        $invalidViewport = 'width=0, height=device-width, initial-scale=wide, interactive-widget=resize';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<meta name="Viewport" content="' . htmlspecialchars($viewport, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                . '<meta name="viewport" content="">'
                . '<meta name="viewport">'
                . '<meta name="viewport" content="' . htmlspecialchars($invalidViewport, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                . '<p>Body</p>',
            'meta viewport review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/meta-viewport-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $review = $summary[0];
        $empty = $summary[1];
        $missing = $summary[2];
        $invalid = $summary[3];
        $paragraph = $summary[4];

        $t->same('meta', $review['documentMetadata']);
        $t->same('Viewport', $review['nameAttribute']);
        $t->same('meta-viewport-directive-review', $review['metaViewportReviewPolicy']);
        $t->same($viewport, $review['metaViewportRaw']);
        $t->same(strlen($viewport), $review['metaViewportByteLength']);
        $t->same(hash('sha256', $viewport), $review['metaViewportSha256']);
        $t->same(false, $review['metaViewportSemicolonSeparated']);
        $t->same(10, $review['metaViewportDirectiveCount']);
        $t->same([
            'width',
            'initial-scale',
            'maximum-scale',
            'user-scalable',
            'viewport-fit',
            'interactive-widget',
            'mystery',
            'shrink-to-fit',
        ], $review['metaViewportDirectiveNames']);
        $t->same([
            'width' => 2,
            'initial-scale' => 1,
            'maximum-scale' => 1,
            'user-scalable' => 1,
            'viewport-fit' => 1,
            'interactive-widget' => 1,
            'mystery' => 1,
            'shrink-to-fit' => 1,
        ], $review['metaViewportDirectiveNameCounts']);
        $t->same(['width'], $review['duplicateMetaViewportDirectiveNames']);
        $t->same(['mystery'], $review['unknownMetaViewportDirectiveNames']);
        $t->same(['bad<name'], $review['invalidMetaViewportDirectiveNames']);
        $t->same([], $review['invalidMetaViewportDirectiveValues']);
        $t->same([
            'width' => '320',
            'initial-scale' => '1.0',
            'maximum-scale' => '1',
            'user-scalable' => 'no',
            'viewport-fit' => 'cover',
            'interactive-widget' => 'resizes-content',
            'shrink-to-fit' => 'no',
        ], $review['metaViewportKnownParameters']);

        $firstWidth = $review['metaViewportDirectives'][0];
        $secondWidth = $review['metaViewportDirectives'][6];
        $unknown = $review['metaViewportDirectives'][7];
        $invalidName = $review['metaViewportDirectives'][8];
        $t->same('width', $firstWidth['name']);
        $t->same('device-width', $firstWidth['keywordValue']);
        $t->same(true, $firstWidth['valid']);
        $t->same('320', $secondWidth['valueRaw']);
        $t->same(320, $secondWidth['integerValue']);
        $t->same(true, $secondWidth['valid']);
        $t->same('mystery', $unknown['name']);
        $t->same(false, $unknown['recognized']);
        $t->same(['unknown-meta-viewport-directive'], $unknown['issueCodes']);
        $t->same(null, $invalidName['name']);
        $t->same(['invalid-meta-viewport-directive-name'], $invalidName['issueCodes']);

        $t->same('320', $review['metaViewportWidthRaw']);
        $t->same(320, $review['metaViewportWidth']);
        $t->same(null, $review['metaViewportHeight']);
        $t->same('1.0', $review['metaViewportInitialScaleRaw']);
        $t->same(1.0, $review['metaViewportInitialScale']);
        $t->same('1', $review['metaViewportMaximumScaleRaw']);
        $t->same(1.0, $review['metaViewportMaximumScale']);
        $t->same('no', $review['metaViewportUserScalableRaw']);
        $t->same(false, $review['metaViewportUserScalable']);
        $t->same(true, $review['metaViewportUserScalableDisabled']);
        $t->same('cover', $review['metaViewportViewportFit']);
        $t->same('resizes-content', $review['metaViewportInteractiveWidget']);
        $t->same(false, $review['metaViewportShrinkToFit']);
        $t->same(true, $review['metaViewportZoomRestricted']);
        $t->same([
            'unknown-meta-viewport-directive',
            'invalid-meta-viewport-directive-name',
            'duplicate-meta-viewport-directive',
            'meta-viewport-user-scalable-disabled',
            'meta-viewport-maximum-scale-below-accessibility-minimum',
        ], $review['metaViewportIssueCodes']);
        $t->same(false, $review['metaViewportValid']);

        $t->same('', $empty['metaViewportRaw']);
        $t->same(0, $empty['metaViewportDirectiveCount']);
        $t->same(['empty-meta-viewport-content'], $empty['metaViewportIssueCodes']);
        $t->same(false, $empty['metaViewportValid']);

        $t->same(null, $missing['metaViewportRaw']);
        $t->same(0, $missing['metaViewportByteLength']);
        $t->same(['missing-meta-viewport-content'], $missing['metaViewportIssueCodes']);
        $t->same(false, $missing['metaViewportValid']);

        $t->same(4, $invalid['metaViewportDirectiveCount']);
        $t->same('0', $invalid['metaViewportWidthRaw']);
        $t->same(null, $invalid['metaViewportWidth']);
        $t->same('device-width', $invalid['metaViewportHeightRaw']);
        $t->same(null, $invalid['metaViewportHeight']);
        $t->same('wide', $invalid['metaViewportInitialScaleRaw']);
        $t->same(null, $invalid['metaViewportInitialScale']);
        $t->same([
            ['name' => 'width', 'valueRaw' => '0', 'issueCodes' => ['invalid-meta-viewport-width']],
            ['name' => 'height', 'valueRaw' => 'device-width', 'issueCodes' => ['invalid-meta-viewport-height']],
            ['name' => 'initial-scale', 'valueRaw' => 'wide', 'issueCodes' => ['invalid-meta-viewport-scale']],
            ['name' => 'interactive-widget', 'valueRaw' => 'resize', 'issueCodes' => ['invalid-meta-viewport-interactive-widget']],
        ], $invalid['invalidMetaViewportDirectiveValues']);
        $t->same([
            'invalid-meta-viewport-width',
            'invalid-meta-viewport-height',
            'invalid-meta-viewport-scale',
            'invalid-meta-viewport-interactive-widget',
        ], $invalid['metaViewportIssueCodes']);
        $t->same(false, $invalid['metaViewportValid']);

        $t->same('Body', $paragraph['text']);
        $t->contains('name="Viewport"', $html);
        $t->contains('bad&lt;name=value', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/meta-viewport-review.html', $document->children[0]->attr('part'));
        json_encode($review, JSON_THROW_ON_ERROR);
        json_encode($invalid, JSON_THROW_ON_ERROR);
    },
];
