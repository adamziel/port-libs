<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes iframe allow duplicate directives and policy validity for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<iframe id="checkout" src="checkout.html" sandbox="allow-forms allow-scripts" allow="payment *; fullscreen *; payment &#039;self&#039;; clipboard-write https://pay.example.test" referrerpolicy="Origin" loading="EAGER">Checkout</iframe>'
                . '<iframe id="map" src="map.html" allow="geolocation https://maps.example.test; geolocation https://backup.example.test; bad&lt;feature *" loading="soon"></iframe>'
                . '<iframe id="plain" src="plain.html" allow="fullscreen *; clipboard-read &#039;self&#039;" referrerpolicy="same-origin" loading="lazy"></iframe>',
            'iframe allow duplicate policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/iframe-allow-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $checkout = $summary[0];
        $map = $summary[1];
        $plain = $summary[2];

        $t->same('iframe-policy-metadata-review', $checkout['iframePolicyReview']);
        $t->same(['payment', 'fullscreen', 'clipboard-write'], $checkout['allowFeatures']);
        $t->same(['payment' => 2, 'fullscreen' => 1, 'clipboard-write' => 1], $checkout['allowFeatureCounts']);
        $t->same(['payment'], $checkout['duplicateAllowFeatures']);
        $t->same(1, $checkout['duplicateAllowDirectiveCount']);
        $t->same(0, $checkout['invalidAllowDirectiveCount']);
        $t->same(false, $checkout['allowPolicyValid']);
        $t->same(['duplicate-iframe-allow-directive'], $checkout['iframePolicyIssueCodes']);
        $t->same(1, $checkout['iframePolicyIssueCount']);
        $t->same(false, $checkout['iframePolicyValid']);
        $t->same('origin', $checkout['referrerPolicy']);
        $t->same('eager', $checkout['loadingState']);

        $t->same(['geolocation'], $map['allowFeatures']);
        $t->same(['geolocation' => 2], $map['allowFeatureCounts']);
        $t->same(['geolocation'], $map['duplicateAllowFeatures']);
        $t->same(['bad<feature *'], $map['invalidAllowDirectives']);
        $t->same([
            'invalid-iframe-allow-directive',
            'duplicate-iframe-allow-directive',
            'invalid-iframe-loading-state',
        ], $map['iframePolicyIssueCodes']);
        $t->same(3, $map['iframePolicyIssueCount']);
        $t->same(false, $map['iframePolicyValid']);

        $t->same(['fullscreen', 'clipboard-read'], $plain['allowFeatures']);
        $t->same(['fullscreen' => 1, 'clipboard-read' => 1], $plain['allowFeatureCounts']);
        $t->same([], $plain['duplicateAllowFeatures']);
        $t->same(0, $plain['duplicateAllowDirectiveCount']);
        $t->same([], $plain['iframePolicyIssueCodes']);
        $t->same(0, $plain['iframePolicyIssueCount']);
        $t->same(true, $plain['iframePolicyValid']);
        $t->same(true, $plain['allowPolicyValid']);

        $t->contains('allow="payment *; fullscreen *; payment &apos;self&apos;; clipboard-write https://pay.example.test"', $html);
        $t->contains('allow="geolocation https://maps.example.test; geolocation https://backup.example.test; bad&lt;feature *"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/iframe-allow-policy-review.html', $document->children[0]->attr('part'));
    },
];
