<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html meta color policy provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<meta name="theme-color" content=" #0A84FF " media=" (prefers-color-scheme: dark) ">'
                . '<meta name="theme-color" content="rgb(255,255,255)">'
                . '<meta name="theme-color" content="url(javascript:alert(1))">'
                . '<meta name="theme-color" content="#123456" media="screen and (background: url(javascript:alert(1)))">'
                . '<meta name="theme-color">'
                . '<meta name="color-scheme" content=" Light Dark only Light ">'
                . '<meta name="color-scheme" content="normal dark">'
                . '<meta name="color-scheme" content="dark bad-token">'
                . '<p>Body</p>',
            'meta color policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/meta-color-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $darkTheme = $summary[0];
        $rgbTheme = $summary[1];
        $unsafeTheme = $summary[2];
        $unsafeMedia = $summary[3];
        $missingTheme = $summary[4];
        $scheme = $summary[5];
        $mixedScheme = $summary[6];
        $badScheme = $summary[7];
        $paragraph = $summary[8];

        $t->same('meta-theme-color-review', $darkTheme['themeColorReviewPolicy']);
        $t->same(' #0A84FF ', $darkTheme['themeColorRaw']);
        $t->same('#0A84FF', $darkTheme['themeColorNormalized']);
        $t->same('hex', $darkTheme['themeColorKind']);
        $t->same(true, $darkTheme['themeColorPresent']);
        $t->same(' (prefers-color-scheme: dark) ', $darkTheme['themeColorMediaRaw']);
        $t->same('(prefers-color-scheme: dark)', $darkTheme['themeColorMediaCondition']);
        $t->same(true, $darkTheme['themeColorMediaValid']);
        $t->same(false, $darkTheme['themeColorBrowserColorEvaluation']);
        $t->same(false, $darkTheme['themeColorNetworkFetch']);
        $t->same([], $darkTheme['themeColorIssueCodes']);
        $t->same(true, $darkTheme['themeColorValid']);

        $t->same('rgb(255, 255, 255)', $rgbTheme['themeColorNormalized']);
        $t->same('function', $rgbTheme['themeColorKind']);
        $t->same(null, $rgbTheme['themeColorMediaValid']);
        $t->same([], $rgbTheme['themeColorIssueCodes']);
        $t->same(true, $rgbTheme['themeColorValid']);

        $t->same('url(javascript:alert(1))', $unsafeTheme['themeColorNormalized']);
        $t->same('invalid', $unsafeTheme['themeColorKind']);
        $t->same(['unsafe-theme-color-value'], $unsafeTheme['themeColorIssueCodes']);
        $t->same(false, $unsafeTheme['themeColorValid']);

        $t->same('#123456', $unsafeMedia['themeColorNormalized']);
        $t->same('screen and (background: url(javascript:alert(1)))', $unsafeMedia['themeColorMediaCondition']);
        $t->same(false, $unsafeMedia['themeColorMediaValid']);
        $t->same(['unsafe-theme-color-media-condition'], $unsafeMedia['themeColorIssueCodes']);
        $t->same(false, $unsafeMedia['themeColorValid']);

        $t->same(null, $missingTheme['themeColorRaw']);
        $t->same(null, $missingTheme['themeColorNormalized']);
        $t->same(null, $missingTheme['themeColorKind']);
        $t->same(false, $missingTheme['themeColorPresent']);
        $t->same(['missing-theme-color-content'], $missingTheme['themeColorIssueCodes']);
        $t->same(false, $missingTheme['themeColorValid']);

        $t->same('meta-color-scheme-review', $scheme['colorSchemeReviewPolicy']);
        $t->same(['Light', 'Dark', 'only', 'Light'], $scheme['colorSchemeTokens']);
        $t->same(['light', 'dark', 'only'], $scheme['colorSchemeSupportedTokens']);
        $t->same(['light', 'dark'], $scheme['colorSchemePreferredSchemes']);
        $t->same(false, $scheme['colorSchemeNormal']);
        $t->same(true, $scheme['colorSchemeOnly']);
        $t->same(['light'], $scheme['duplicateColorSchemeTokens']);
        $t->same(['duplicate-color-scheme-token'], $scheme['colorSchemeIssueCodes']);
        $t->same(false, $scheme['colorSchemeBrowserEvaluation']);
        $t->same(false, $scheme['colorSchemeNetworkFetch']);
        $t->same(false, $scheme['colorSchemeValid']);

        $t->same(['normal', 'dark'], $mixedScheme['colorSchemeSupportedTokens']);
        $t->same(true, $mixedScheme['colorSchemeNormal']);
        $t->same(['normal-color-scheme-with-other-tokens'], $mixedScheme['colorSchemeIssueCodes']);
        $t->same(false, $mixedScheme['colorSchemeValid']);

        $t->same(['dark', 'bad-token'], $badScheme['colorSchemeTokens']);
        $t->same(['dark'], $badScheme['colorSchemeSupportedTokens']);
        $t->same(['bad-token'], $badScheme['invalidColorSchemeTokens']);
        $t->same(['unsupported-color-scheme-token'], $badScheme['colorSchemeIssueCodes']);
        $t->same(false, $badScheme['colorSchemeValid']);

        $t->same('Body', $paragraph['text']);
        $t->same(
            '<meta content=" #0A84FF " media=" (prefers-color-scheme: dark) " name="theme-color">'
                . '<meta content="rgb(255,255,255)" name="theme-color">'
                . '<meta content="url(javascript:alert(1))" name="theme-color">'
                . '<meta content="#123456" media="screen and (background: url(javascript:alert(1)))" name="theme-color">'
                . '<meta name="theme-color">'
                . '<meta content=" Light Dark only Light " name="color-scheme">'
                . '<meta content="normal dark" name="color-scheme">'
                . '<meta content="dark bad-token" name="color-scheme">'
                . '<p>Body</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/meta-color-policy-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
