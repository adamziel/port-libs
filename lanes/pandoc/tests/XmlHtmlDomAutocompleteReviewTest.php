<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html form control autocomplete token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="profile">'
                . '<input id="email" name="email" autocomplete="section-Checkout shipping email webauthn">'
                . '<input id="repeat" name="repeat" autocomplete="email EMAIL">'
                . '<input id="bad" name="bad" autocomplete="on email unknown-token bad&lt;token section-late">'
                . '<input id="empty" name="empty" autocomplete="">'
                . '</form>',
            'form control autocomplete review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-control-autocomplete-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $email = $form['children'][0];
        $repeat = $form['children'][1];
        $bad = $form['children'][2];
        $empty = $form['children'][3];

        $t->same('html-form-control-autocomplete-token-review', $email['autocompleteReviewPolicy']);
        $t->same('section-Checkout shipping email webauthn', $email['autocompleteRaw']);
        $t->same(['section-Checkout', 'shipping', 'email', 'webauthn'], $email['autocompleteTokens']);
        $t->same(['section-checkout', 'shipping', 'email', 'webauthn'], $email['autocompleteNormalizedTokens']);
        $t->same([
            'section-checkout' => 1,
            'shipping' => 1,
            'email' => 1,
            'webauthn' => 1,
        ], $email['autocompleteTokenCounts']);
        $t->same(4, $email['autocompleteTokenCount']);
        $t->same(4, $email['autocompleteUniqueTokenCount']);
        $t->same(['section-checkout', 'shipping', 'email', 'webauthn'], $email['autocompleteKnownTokens']);
        $t->same([], $email['autocompleteUnknownTokens']);
        $t->same([], $email['invalidAutocompleteTokens']);
        $t->same([], $email['duplicateAutocompleteTokens']);
        $t->same('section-checkout', $email['autocompleteSectionToken']);
        $t->same('shipping', $email['autocompleteGroupingToken']);
        $t->same('email', $email['autocompleteFieldName']);
        $t->same('webauthn', $email['autocompleteCredentialToken']);
        $t->same('section', $email['autocompleteTokenDetails'][0]['category']);
        $t->same('credential', $email['autocompleteTokenDetails'][3]['category']);
        $t->same('detail', $email['autocompleteState']);
        $t->same(true, $email['autocompleteValid']);
        $t->same([], $email['autocompleteIssueCodes']);
        $t->same(true, $email['autocompleteConforming']);
        $t->same(true, $email['autocompleteReviewOnlyNoAutofill']);

        $t->same(['email'], $repeat['autocompleteNormalizedTokens']);
        $t->same(['email' => 2], $repeat['autocompleteTokenCounts']);
        $t->same(['email'], $repeat['duplicateAutocompleteTokens']);
        $t->same(['duplicate-form-control-autocomplete-token'], $repeat['autocompleteIssueCodes']);
        $t->same(true, $repeat['autocompleteValid']);
        $t->same(false, $repeat['autocompleteConforming']);

        $t->same(['on', 'email', 'unknown-token', 'section-late'], $bad['autocompleteNormalizedTokens']);
        $t->same(['unknown-token'], $bad['autocompleteUnknownTokens']);
        $t->same(['bad<token'], $bad['invalidAutocompleteTokens']);
        $t->same(['on'], $bad['autocompleteStateTokens']);
        $t->same('section-late', $bad['autocompleteSectionToken']);
        $t->same('email', $bad['autocompleteFieldName']);
        $t->same(false, $bad['autocompleteValid']);
        $t->same([
            'invalid-form-control-autocomplete-token',
            'unknown-form-control-autocomplete-token',
            'state-form-control-autocomplete-token-with-details',
            'misplaced-form-control-autocomplete-section-token',
        ], $bad['autocompleteIssueCodes']);
        $t->same(false, $bad['autocompleteConforming']);

        $t->same('', $empty['autocompleteRaw']);
        $t->same([], $empty['autocompleteTokens']);
        $t->same(null, $empty['autocompleteState']);
        $t->same(false, $empty['autocompleteValid']);
        $t->same(['empty-form-control-autocomplete'], $empty['autocompleteIssueCodes']);

        $t->same(
            '<form id="profile"><input autocomplete="section-Checkout shipping email webauthn" id="email" name="email"><input autocomplete="email EMAIL" id="repeat" name="repeat"><input autocomplete="on email unknown-token bad&lt;token section-late" id="bad" name="bad"><input autocomplete="" id="empty" name="empty"></form>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/form-control-autocomplete-review.html', $document->children[0]->attr('part'));
        json_encode([$email, $repeat, $bad, $empty], JSON_THROW_ON_ERROR);
    },
];
