<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html address contact link provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<address id="contact">Reach '
                . '<a id="mail" href="mailto:docs%2Breview@example.test?subject=Docs" rel="author">Docs Team</a> '
                . '<a id="phone" href="tel:+15550100;ext=7">Call</a> '
                . '<a id="site" href="https://example.test/contact">Site</a> '
                . '<a id="bad" href="javascript:alert(1)">Bad</a> '
                . '<a id="empty" href="">Empty</a> '
                . '<a id="plain">Plain</a> '
                . '<a id="blank" href="mailto:?subject=Missing"></a>'
                . '</address>',
            'address contact review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/address-contact-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $address = $summary[0];
        $byId = [];
        foreach ($address['contactLinks'] as $contactLink) {
            $id = $contactLink['id'] ?? null;
            if (is_string($id) && $id !== '') {
                $byId[$id] = $contactLink;
            }
        }

        $mail = $byId['mail'];
        $phone = $byId['phone'];
        $site = $byId['site'];
        $bad = $byId['bad'];
        $empty = $byId['empty'];
        $plain = $byId['plain'];
        $blank = $byId['blank'];

        $t->same('address', $address['name']);
        $t->same('html-address-contact-link-review', $address['addressContactReviewPolicy']);
        $t->same(7, $address['contactLinkCount']);
        $t->same([
            'email',
            'telephone',
            'url',
            'url',
            'empty',
            'missing',
            'email',
        ], $address['contactLinkKinds']);
        $t->same(['docs+review@example.test'], $address['contactEmailAddresses']);
        $t->same(['+15550100;ext=7'], $address['contactTelephoneNumbers']);
        $t->same(['javascript:alert(1)'], $address['unsafeContactHrefs']);
        $t->same([
            'unsafe-address-contact-href',
            'empty-address-contact-href',
            'missing-address-contact-href',
            'missing-address-mailto-recipient',
            'empty-address-contact-label',
        ], $address['contactLinkIssueCodes']);
        $t->same(5, $address['contactLinkIssueCount']);
        $t->same(false, $address['contactLinksValid']);

        $t->same('mailto:docs%2Breview@example.test?subject=Docs', $mail['href']);
        $t->same('email', $mail['contactKind']);
        $t->same('docs+review@example.test', $mail['contactValue']);
        $t->same('absolute', $mail['contactHrefKind']);
        $t->same('mailto', $mail['contactHrefScheme']);
        $t->same(false, $mail['contactHrefUnsafe']);
        $t->same(['author'], $mail['relTokens']);

        $t->same('telephone', $phone['contactKind']);
        $t->same('+15550100;ext=7', $phone['contactValue']);
        $t->same('tel', $phone['contactHrefScheme']);
        $t->same('url', $site['contactKind']);
        $t->same('https', $site['contactHrefScheme']);
        $t->same('url', $bad['contactKind']);
        $t->same('javascript', $bad['contactHrefScheme']);
        $t->same(true, $bad['contactHrefUnsafe']);
        $t->same('empty', $empty['contactKind']);
        $t->same(null, $empty['contactValue']);
        $t->same('missing', $plain['contactKind']);
        $t->same(null, $plain['href']);
        $t->same('email', $blank['contactKind']);
        $t->same(null, $blank['contactValue']);

        $t->same('unsafe-address-contact-href', $address['contactLinkIssues'][0]['code']);
        $t->same('javascript', $address['contactLinkIssues'][0]['scheme']);
        $t->same('missing-address-mailto-recipient', $address['contactLinkIssues'][3]['code']);
        $t->same('empty-address-contact-label', $address['contactLinkIssues'][4]['code']);

        $t->contains('<a href="mailto:docs%2Breview@example.test?subject=Docs" id="mail" rel="author">Docs Team</a>', $html);
        $t->contains('<a href="mailto:?subject=Missing" id="blank"></a>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/address-contact-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
