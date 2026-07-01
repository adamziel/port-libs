<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html meta theme-color content and media for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<meta name="Theme-Color" content=" #0A84FF " media=" (prefers-color-scheme: dark) ">'
                . '<meta name="theme-color" content="rgb(255,255,255)">'
                . '<meta name="theme-color" content="rebeccapurple">'
                . '<meta name="theme-color" content="url(javascript:alert(1))">'
                . '<meta name="theme-color" content="#123456" media="screen and (background: url(javascript:alert(1)))">'
                . '<meta name="theme-color" content="">'
                . '<meta name="theme-color">'
                . '<p>Body</p>',
            'meta theme-color review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/meta-theme-color-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $dark = $summary[0];
        $rgb = $summary[1];
        $named = $summary[2];
        $unsafeContent = $summary[3];
        $unsafeMedia = $summary[4];
        $empty = $summary[5];
        $missing = $summary[6];
        $paragraph = $summary[7];

        $t->same('meta', $dark['documentMetadata']);
        $t->same('Theme-Color', $dark['nameAttribute']);
        $t->same('meta-theme-color-review', $dark['metaThemeColorReviewPolicy']);
        $t->same(' #0A84FF ', $dark['metaThemeColorRaw']);
        $t->same('#0A84FF', $dark['metaThemeColor']);
        $t->same('hex', $dark['metaThemeColorKind']);
        $t->same(true, $dark['metaThemeColorContentValid']);
        $t->same(' (prefers-color-scheme: dark) ', $dark['metaThemeColorMediaRaw']);
        $t->same('(prefers-color-scheme: dark)', $dark['metaThemeColorMedia']);
        $t->same(true, $dark['metaThemeColorMediaValid']);
        $t->same(true, $dark['metaThemeColorRenderable']);
        $t->same([], $dark['metaThemeColorIssueCodes']);
        $t->same(true, $dark['metaThemeColorValid']);

        $t->same('rgb(255,255,255)', $rgb['metaThemeColorRaw']);
        $t->same('rgb(255, 255, 255)', $rgb['metaThemeColor']);
        $t->same('functional', $rgb['metaThemeColorKind']);
        $t->same(null, $rgb['metaThemeColorMediaRaw']);
        $t->same(null, $rgb['metaThemeColorMedia']);
        $t->same(null, $rgb['metaThemeColorMediaValid']);
        $t->same([], $rgb['metaThemeColorIssueCodes']);

        $t->same('rebeccapurple', $named['metaThemeColor']);
        $t->same('named', $named['metaThemeColorKind']);
        $t->same(true, $named['metaThemeColorValid']);

        $t->same('url(javascript:alert(1))', $unsafeContent['metaThemeColorRaw']);
        $t->same(null, $unsafeContent['metaThemeColor']);
        $t->same(null, $unsafeContent['metaThemeColorKind']);
        $t->same(false, $unsafeContent['metaThemeColorContentValid']);
        $t->same(false, $unsafeContent['metaThemeColorRenderable']);
        $t->same(['unsafe-meta-theme-color-content'], $unsafeContent['metaThemeColorIssueCodes']);
        $t->same(false, $unsafeContent['metaThemeColorValid']);

        $t->same('#123456', $unsafeMedia['metaThemeColor']);
        $t->same('screen and (background: url(javascript:alert(1)))', $unsafeMedia['metaThemeColorMediaRaw']);
        $t->same(null, $unsafeMedia['metaThemeColorMedia']);
        $t->same(false, $unsafeMedia['metaThemeColorMediaValid']);
        $t->same(true, $unsafeMedia['metaThemeColorRenderable']);
        $t->same(['unsafe-meta-theme-color-media'], $unsafeMedia['metaThemeColorIssueCodes']);
        $t->same(false, $unsafeMedia['metaThemeColorValid']);

        $t->same('', $empty['metaThemeColorRaw']);
        $t->same(null, $empty['metaThemeColor']);
        $t->same(['empty-meta-theme-color-content'], $empty['metaThemeColorIssueCodes']);
        $t->same(false, $empty['metaThemeColorRenderable']);

        $t->same(null, $missing['metaThemeColorRaw']);
        $t->same(null, $missing['metaThemeColor']);
        $t->same(['missing-meta-theme-color-content'], $missing['metaThemeColorIssueCodes']);
        $t->same(false, $missing['metaThemeColorRenderable']);

        $t->same('Body', $paragraph['text']);
        $t->contains('name="Theme-Color"', $html);
        $t->contains('media=" (prefers-color-scheme: dark) "', $html);
        $t->contains('url(javascript:alert(1))', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/meta-theme-color-review.html', $document->children[0]->attr('part'));
        json_encode($dark, JSON_THROW_ON_ERROR);
        json_encode($unsafeMedia, JSON_THROW_ON_ERROR);
    },
];
