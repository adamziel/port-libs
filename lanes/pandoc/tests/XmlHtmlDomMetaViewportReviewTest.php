<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html meta viewport layout intent for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">'
                . '<meta name="viewport" content="width=640, width=bad, user-scalable=no, maximum-scale=0, shrink-to-fit=no, bad token=1, minimum-scale=0.5, height=device-height">'
                . '<meta name="viewport">'
                . '<p>Body</p>',
            'meta viewport review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/meta-viewport-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $valid = $summary[0];
        $invalid = $summary[1];
        $missing = $summary[2];
        $paragraph = $summary[3];

        $t->same('meta-viewport-layout-intent-review', $valid['metaViewportReviewPolicy']);
        $t->same('width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content', $valid['metaViewportRaw']);
        $t->same(4, $valid['metaViewportDirectiveCount']);
        $t->same(['width', 'initial-scale', 'viewport-fit', 'interactive-widget'], $valid['metaViewportDirectiveNames']);
        $t->same(['width' => 1, 'initial-scale' => 1, 'viewport-fit' => 1, 'interactive-widget' => 1], $valid['metaViewportDirectiveNameCounts']);
        $t->same(['device-width'], $valid['metaViewportDirectiveValues']['width']);
        $t->same('device-width', $valid['metaViewportWidth']);
        $t->same('device', $valid['metaViewportWidthKind']);
        $t->same(1.0, $valid['metaViewportInitialScale']);
        $t->same('cover', $valid['metaViewportFit']);
        $t->same('resizes-content', $valid['metaViewportInteractiveWidget']);
        $t->same(false, $valid['metaViewportBrowserLayoutEvaluation']);
        $t->same([], $valid['metaViewportIssueCodes']);
        $t->same(true, $valid['metaViewportValid']);
        $t->same('initial-scale', $valid['metaViewportDirectives'][1]['name']);
        $t->same('number', $valid['metaViewportDirectives'][1]['valueKind']);
        $t->same('1', $valid['metaViewportDirectives'][1]['normalizedValue']);
        $t->same(true, $valid['metaViewportDirectives'][1]['valueValid']);

        $t->same(8, $invalid['metaViewportDirectiveCount']);
        $t->same(['width', 'user-scalable', 'maximum-scale', 'shrink-to-fit', 'bad token', 'minimum-scale', 'height'], $invalid['metaViewportDirectiveNames']);
        $t->same(['width' => 2, 'user-scalable' => 1, 'maximum-scale' => 1, 'shrink-to-fit' => 1, 'bad token' => 1, 'minimum-scale' => 1, 'height' => 1], $invalid['metaViewportDirectiveNameCounts']);
        $t->same(['width'], $invalid['duplicateMetaViewportDirectives']);
        $t->same(['shrink-to-fit'], $invalid['unknownMetaViewportDirectives']);
        $t->same(['640', 'bad'], $invalid['metaViewportDirectiveValues']['width']);
        $t->same('640', $invalid['metaViewportWidth']);
        $t->same('integer', $invalid['metaViewportWidthKind']);
        $t->same('device-height', $invalid['metaViewportHeight']);
        $t->same('device', $invalid['metaViewportHeightKind']);
        $t->same(0.5, $invalid['metaViewportMinimumScale']);
        $t->same(null, $invalid['metaViewportMaximumScale']);
        $t->same(false, $invalid['metaViewportUserScalable']);
        $t->same('no', $invalid['metaViewportUserScalableToken']);
        $t->same([
            'invalid-meta-viewport-width',
            'invalid-meta-viewport-maximum-scale',
            'unknown-meta-viewport-directive',
            'invalid-meta-viewport-directive-name',
            'duplicate-meta-viewport-directive',
        ], $invalid['metaViewportIssueCodes']);
        $t->same(false, $invalid['metaViewportValid']);

        $t->same('width', $invalid['metaViewportDirectives'][0]['name']);
        $t->same(true, $invalid['metaViewportDirectives'][0]['duplicate']);
        $t->same(['duplicate-meta-viewport-directive'], $invalid['metaViewportDirectives'][0]['issueCodes']);
        $t->same('width', $invalid['metaViewportDirectives'][1]['name']);
        $t->same('invalid', $invalid['metaViewportDirectives'][1]['valueKind']);
        $t->same(false, $invalid['metaViewportDirectives'][1]['valueValid']);
        $t->same(['invalid-meta-viewport-width', 'duplicate-meta-viewport-directive'], $invalid['metaViewportDirectives'][1]['issueCodes']);
        $t->same('shrink-to-fit', $invalid['metaViewportDirectives'][4]['name']);
        $t->same(false, $invalid['metaViewportDirectives'][4]['known']);
        $t->same(['unknown-meta-viewport-directive'], $invalid['metaViewportDirectives'][4]['issueCodes']);
        $t->same('bad token', $invalid['metaViewportDirectives'][5]['name']);
        $t->same(false, $invalid['metaViewportDirectives'][5]['validName']);
        $t->same(['invalid-meta-viewport-directive-name'], $invalid['metaViewportDirectives'][5]['issueCodes']);

        $t->same(null, $missing['metaViewportRaw']);
        $t->same(0, $missing['metaViewportDirectiveCount']);
        $t->same(['missing-meta-viewport-content'], $missing['metaViewportIssueCodes']);
        $t->same(false, $missing['metaViewportValid']);
        $t->same(false, $missing['metaViewportBrowserLayoutEvaluation']);

        $t->same('Body', $paragraph['text']);
        $t->same(
            '<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content" name="viewport">'
                . '<meta content="width=640, width=bad, user-scalable=no, maximum-scale=0, shrink-to-fit=no, bad token=1, minimum-scale=0.5, height=device-height" name="viewport">'
                . '<meta name="viewport"><p>Body</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/meta-viewport-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
