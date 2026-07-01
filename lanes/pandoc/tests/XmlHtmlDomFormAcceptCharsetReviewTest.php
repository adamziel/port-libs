<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html form accept-charset token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="mixed" accept-charset="UTF-8 utf-8 ISO-8859-1 bad&lt;token"><input name="title" value="Draft"></form>'
                . '<form id="legacy" accept-charset="Shift_JIS windows-1252"></form>'
                . '<form id="empty" accept-charset=""></form>'
                . '<form id="missing"></form>',
            'form accept charset review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-accept-charset-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $mixed = $summary[0];
        $legacy = $summary[1];
        $empty = $summary[2];
        $missing = $summary[3];

        $t->same('html-form-accept-charset-review', $mixed['formAcceptCharsetReviewPolicy']);
        $t->same('UTF-8 utf-8 ISO-8859-1 bad<token', $mixed['acceptCharsetRaw']);
        $t->same(['UTF-8', 'utf-8', 'ISO-8859-1', 'bad<token'], $mixed['acceptCharsets']);
        $t->same(['UTF-8', 'utf-8', 'ISO-8859-1', 'bad<token'], $mixed['acceptCharsetTokens']);
        $t->same(['utf-8', 'utf-8', 'iso-8859-1', 'bad<token'], $mixed['acceptCharsetNormalizedTokens']);
        $t->same(['utf-8' => 2, 'iso-8859-1' => 1, 'bad<token' => 1], $mixed['acceptCharsetTokenCounts']);
        $t->same(4, $mixed['acceptCharsetTokenCount']);
        $t->same(3, $mixed['acceptCharsetUniqueTokenCount']);
        $t->same(true, $mixed['acceptCharsetUtf8Present']);
        $t->same(['ISO-8859-1'], $mixed['acceptCharsetLegacyTokens']);
        $t->same(['bad<token'], $mixed['invalidAcceptCharsetTokens']);
        $t->same(['utf-8'], $mixed['duplicateAcceptCharsetTokens']);
        $t->same([
            'duplicate-form-accept-charset-token',
            'legacy-form-accept-charset-token',
            'invalid-form-accept-charset-token',
        ], $mixed['acceptCharsetIssueCodes']);
        $t->same(false, $mixed['acceptCharsetConforming']);
        $t->same(true, $mixed['acceptCharsetReviewOnlyNoTranscoding']);

        $t->same(['Shift_JIS', 'windows-1252'], $legacy['acceptCharsetTokens']);
        $t->same(['shift_jis', 'windows-1252'], $legacy['acceptCharsetNormalizedTokens']);
        $t->same(false, $legacy['acceptCharsetUtf8Present']);
        $t->same(['Shift_JIS', 'windows-1252'], $legacy['acceptCharsetLegacyTokens']);
        $t->same([], $legacy['invalidAcceptCharsetTokens']);
        $t->same([
            'legacy-form-accept-charset-token',
            'missing-utf8-form-accept-charset',
        ], $legacy['acceptCharsetIssueCodes']);

        $t->same('', $empty['acceptCharsetRaw']);
        $t->same([], $empty['acceptCharsetTokens']);
        $t->same(0, $empty['acceptCharsetTokenCount']);
        $t->same(false, $empty['acceptCharsetUtf8Present']);
        $t->same(['empty-form-accept-charset'], $empty['acceptCharsetIssueCodes']);
        $t->same(false, $empty['acceptCharsetConforming']);

        $t->same(null, $missing['acceptCharsetRaw']);
        $t->same([], $missing['acceptCharsets']);
        $t->true(!array_key_exists('formAcceptCharsetReviewPolicy', $missing));

        $t->contains('accept-charset="UTF-8 utf-8 ISO-8859-1 bad&lt;token"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-accept-charset-review.html', $document->children[0]->attr('part'));
        json_encode([$mixed, $legacy, $empty], JSON_THROW_ON_ERROR);
    },
];
