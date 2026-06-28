<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html autocomplete detail tokens for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="profile">'
                . '<input id="email" name="email" autocomplete="section-review shipping email">'
                . '<input id="phone" name="phone" autocomplete="billing mobile tel">'
                . '<input id="passkey" name="login" autocomplete="section-login username webauthn">'
                . '<input id="keyword" name="remember" autocomplete="off">'
                . '<input id="bad-keyword" name="mixed" autocomplete="on email">'
                . '<input id="bad-order" name="bad" autocomplete="shipping section-late email">'
                . '<input id="bad-contact" name="display" autocomplete="work name">'
                . '<input id="bad-webauthn" name="fallback" autocomplete="webauthn username email username">'
                . '</form>',
            'autocomplete detail token review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/autocomplete-detail-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $controls = [];
        foreach ($form['children'] as $control) {
            $controls[(string) $control['elementId']] = $control;
        }

        $email = $controls['email'];
        $phone = $controls['phone'];
        $passkey = $controls['passkey'];
        $keyword = $controls['keyword'];
        $badKeyword = $controls['bad-keyword'];
        $badOrder = $controls['bad-order'];
        $badContact = $controls['bad-contact'];
        $badWebauthn = $controls['bad-webauthn'];

        $t->same('html-autocomplete-detail-token-review', $email['autocompleteReviewPolicy']);
        $t->same(['section-review', 'shipping', 'email'], $email['autocompleteNormalizedTokens']);
        $t->same('detail', $email['autocompleteMode']);
        $t->same('section-review', $email['autocompleteSectionToken']);
        $t->same('shipping', $email['autocompleteAddressType']);
        $t->same(null, $email['autocompleteContactType']);
        $t->same('email', $email['autocompleteFieldName']);
        $t->same(false, $email['autocompleteWebAuthn']);
        $t->same(['section', 'address-type', 'field-name'], array_column($email['autocompleteTokenDetails'], 'role'));
        $t->same([], $email['autocompleteIssueCodes']);
        $t->same(true, $email['autocompleteSemanticValid']);

        $t->same('billing', $phone['autocompleteAddressType']);
        $t->same('mobile', $phone['autocompleteContactType']);
        $t->same('tel', $phone['autocompleteFieldName']);
        $t->same(['address-type', 'contact-type', 'field-name'], array_column($phone['autocompleteTokenDetails'], 'role'));
        $t->same(true, $phone['autocompleteSemanticValid']);

        $t->same('section-login', $passkey['autocompleteSectionToken']);
        $t->same('username', $passkey['autocompleteFieldName']);
        $t->same(true, $passkey['autocompleteWebAuthn']);
        $t->same(['section', 'field-name', 'webauthn'], array_column($passkey['autocompleteTokenDetails'], 'role'));
        $t->same(true, $passkey['autocompleteSemanticValid']);

        $t->same('off', $keyword['autocompleteState']);
        $t->same('off', $keyword['autocompleteMode']);
        $t->same(null, $keyword['autocompleteFieldName']);
        $t->same([], $keyword['autocompleteIssueCodes']);
        $t->same(true, $keyword['autocompleteSemanticValid']);

        $t->same(['keyword', 'field-name'], array_column($badKeyword['autocompleteTokenDetails'], 'role'));
        $t->same(['autocomplete-keyword-with-detail-tokens'], $badKeyword['autocompleteIssueCodes']);
        $t->same(false, $badKeyword['autocompleteSemanticValid']);

        $t->same('section-late', $badOrder['autocompleteSectionToken']);
        $t->same('shipping', $badOrder['autocompleteAddressType']);
        $t->same(['misordered-autocomplete-section-token', 'misordered-autocomplete-address-type'], $badOrder['autocompleteIssueCodes']);
        $t->same(false, $badOrder['autocompleteSemanticValid']);

        $t->same('work', $badContact['autocompleteContactType']);
        $t->same('name', $badContact['autocompleteFieldName']);
        $t->same(['autocomplete-contact-type-with-non-contact-field'], $badContact['autocompleteIssueCodes']);
        $t->same(false, $badContact['autocompleteSemanticValid']);

        $t->same('username', $badWebauthn['autocompleteFieldName']);
        $t->same(true, $badWebauthn['autocompleteWebAuthn']);
        $t->same(['username'], $badWebauthn['duplicateAutocompleteTokens']);
        $t->same([
            'duplicate-autocomplete-token',
            'multiple-autocomplete-field-names',
            'misordered-autocomplete-webauthn-token',
        ], $badWebauthn['autocompleteIssueCodes']);
        $t->same(false, $badWebauthn['autocompleteSemanticValid']);

        $t->contains('autocomplete="section-login username webauthn"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/autocomplete-detail-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
