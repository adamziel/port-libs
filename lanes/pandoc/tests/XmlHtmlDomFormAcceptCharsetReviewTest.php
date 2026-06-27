<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html form accept charset provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="valid" accept-charset=" UTF-8 "><input name="title" value="Packet"></form>'
                . '<form id="default"><button>Submit</button></form>'
                . '<form id="legacy" accept-charset="UTF-8 ISO-8859-1 utf-8"><input name="legacy"></form>'
                . '<form id="bad" accept-charset="bad&lt;tag"><input name="bad"></form>',
            'form accept charset review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-accept-charset-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $valid = $summary[0];
        $default = $summary[1];
        $legacy = $summary[2];
        $bad = $summary[3];

        $t->same('form-accept-charset-review', $valid['acceptCharsetReviewPolicy']);
        $t->same(true, $valid['acceptCharsetPresent']);
        $t->same(' UTF-8 ', $valid['acceptCharsetRaw']);
        $t->same(['UTF-8'], $valid['acceptCharsets']);
        $t->same(['utf-8'], $valid['acceptCharsetNormalizedTokens']);
        $t->same(['utf-8'], $valid['acceptCharsetValidTokens']);
        $t->same([], $valid['invalidAcceptCharsetTokens']);
        $t->same([], $valid['unsupportedAcceptCharsets']);
        $t->same([], $valid['duplicateAcceptCharsets']);
        $t->same(['utf-8' => 1], $valid['acceptCharsetTokenCounts']);
        $t->same('utf-8', $valid['acceptCharsetState']);
        $t->same('utf-8', $valid['acceptCharsetEffectiveEncoding']);
        $t->same([], $valid['acceptCharsetIssueCodes']);
        $t->same(true, $valid['acceptCharsetValid']);

        $t->same(false, $default['acceptCharsetPresent']);
        $t->same(null, $default['acceptCharsetRaw']);
        $t->same([], $default['acceptCharsets']);
        $t->same([], $default['acceptCharsetNormalizedTokens']);
        $t->same('default-utf-8', $default['acceptCharsetState']);
        $t->same('utf-8', $default['acceptCharsetEffectiveEncoding']);
        $t->same([], $default['acceptCharsetIssueCodes']);
        $t->same(true, $default['acceptCharsetValid']);

        $t->same(['UTF-8', 'ISO-8859-1', 'utf-8'], $legacy['acceptCharsets']);
        $t->same(['utf-8', 'iso-8859-1'], $legacy['acceptCharsetNormalizedTokens']);
        $t->same(['utf-8'], $legacy['acceptCharsetValidTokens']);
        $t->same(['ISO-8859-1'], $legacy['unsupportedAcceptCharsets']);
        $t->same(['utf-8'], $legacy['duplicateAcceptCharsets']);
        $t->same(['utf-8' => 2, 'iso-8859-1' => 1], $legacy['acceptCharsetTokenCounts']);
        $t->same('utf-8-with-legacy-tokens', $legacy['acceptCharsetState']);
        $t->same([
            'multiple-form-accept-charset-tokens',
            'unsupported-form-accept-charset-token',
            'duplicate-form-accept-charset-token',
        ], $legacy['acceptCharsetIssueCodes']);
        $t->same(false, $legacy['acceptCharsetValid']);

        $t->same(['bad<tag'], $bad['acceptCharsets']);
        $t->same([], $bad['acceptCharsetNormalizedTokens']);
        $t->same(['bad<tag'], $bad['invalidAcceptCharsetTokens']);
        $t->same('invalid', $bad['acceptCharsetState']);
        $t->same(['invalid-form-accept-charset-token'], $bad['acceptCharsetIssueCodes']);
        $t->same(false, $bad['acceptCharsetValid']);

        $t->same(
            '<form accept-charset=" UTF-8 " id="valid"><input name="title" value="Packet"></form><form id="default"><button>Submit</button></form><form accept-charset="UTF-8 ISO-8859-1 utf-8" id="legacy"><input name="legacy"></form><form accept-charset="bad&lt;tag" id="bad"><input name="bad"></form>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/form-accept-charset-review.html', $document->children[0]->attr('part'));
    },
];
