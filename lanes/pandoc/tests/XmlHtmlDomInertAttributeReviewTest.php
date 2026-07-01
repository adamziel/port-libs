<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html inert boolean attribute review for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="modal" inert><p id="body"><button id="save">Save</button><span id="local" inert="soft-lock"><em id="local-child">Child</em></span></p></article>'
                . '<section id="named" inert="inert"><p id="named-child">Named</p></section>'
                . '<aside id="falsey" inert="false"><span id="falsey-child">Falsey</span></aside>'
                . '<p id="active">Active</p>',
            'inert boolean attribute review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/inert-attribute-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $modal = $summary[0];
        $body = $modal['children'][0];
        $save = $body['children'][0];
        $local = $body['children'][1];
        $localChild = $local['children'][0];
        $named = $summary[1];
        $namedChild = $named['children'][0];
        $falsey = $summary[2];
        $falseyChild = $falsey['children'][0];
        $active = $summary[3];

        $t->same('html-inert-boolean-attribute-review', $modal['inertReviewPolicy']);
        $t->same('', $modal['inertRaw']);
        $t->same(true, $modal['inert']);
        $t->same(true, $modal['inertBooleanAttribute']);
        $t->same(false, $modal['inertHasValue']);
        $t->same(true, $modal['inertValueConforming']);
        $t->same([], $modal['inertIssueCodes']);
        $t->same('', $modal['effectiveInertRaw']);
        $t->same(true, $modal['effectiveInert']);
        $t->same(true, $modal['effectiveInertBooleanAttribute']);
        $t->same(false, $modal['effectiveInertHasValue']);
        $t->same(true, $modal['effectiveInertValueConforming']);
        $t->same([], $modal['effectiveInertIssueCodes']);
        $t->same(false, $modal['inertInherited']);
        $t->same('self-inert', $modal['inertSource']);
        $t->same('article', $modal['inertSourceElement']);
        $t->same('modal', $modal['inertSourceElementId']);

        $t->true(!array_key_exists('inertRaw', $body));
        $t->same(true, $body['effectiveInert']);
        $t->same(true, $body['inertInherited']);
        $t->same('ancestor-inert', $body['inertSource']);
        $t->same('modal', $body['inertSourceElementId']);
        $t->same(true, $body['effectiveInertValueConforming']);
        $t->same([], $body['effectiveInertIssueCodes']);

        $t->true(!array_key_exists('inertRaw', $save));
        $t->same(true, $save['effectiveInert']);
        $t->same(true, $save['inertInherited']);
        $t->same('modal', $save['inertSourceElementId']);

        $t->same('soft-lock', $local['inertRaw']);
        $t->same(true, $local['inert']);
        $t->same(true, $local['inertHasValue']);
        $t->same(false, $local['inertValueConforming']);
        $t->same(['non-conforming-inert-boolean-value'], $local['inertIssueCodes']);
        $t->same('soft-lock', $local['effectiveInertRaw']);
        $t->same(true, $local['effectiveInert']);
        $t->same(false, $local['effectiveInertValueConforming']);
        $t->same(['non-conforming-inert-boolean-value'], $local['effectiveInertIssueCodes']);
        $t->same(false, $local['inertInherited']);
        $t->same('self-inert', $local['inertSource']);
        $t->same('local', $local['inertSourceElementId']);

        $t->true(!array_key_exists('inertRaw', $localChild));
        $t->same(true, $localChild['effectiveInert']);
        $t->same(true, $localChild['inertInherited']);
        $t->same(false, $localChild['effectiveInertValueConforming']);
        $t->same(['non-conforming-inert-boolean-value'], $localChild['effectiveInertIssueCodes']);
        $t->same('local', $localChild['inertSourceElementId']);

        $t->same('inert', $named['inertRaw']);
        $t->same(true, $named['inertHasValue']);
        $t->same(true, $named['inertValueConforming']);
        $t->same([], $named['inertIssueCodes']);
        $t->same(true, $namedChild['effectiveInert']);
        $t->same(true, $namedChild['inertInherited']);
        $t->same(true, $namedChild['effectiveInertValueConforming']);
        $t->same('named', $namedChild['inertSourceElementId']);

        $t->same('false', $falsey['inertRaw']);
        $t->same(true, $falsey['inert']);
        $t->same(true, $falsey['effectiveInert']);
        $t->same(false, $falsey['inertValueConforming']);
        $t->same(['non-conforming-inert-boolean-value'], $falsey['inertIssueCodes']);
        $t->same(false, $falsey['effectiveInertValueConforming']);
        $t->same(['non-conforming-inert-boolean-value'], $falsey['effectiveInertIssueCodes']);
        $t->same(true, $falseyChild['effectiveInert']);
        $t->same(false, $falseyChild['effectiveInertValueConforming']);
        $t->same(['non-conforming-inert-boolean-value'], $falseyChild['effectiveInertIssueCodes']);
        $t->same('falsey', $falseyChild['inertSourceElementId']);

        $t->same('active', $active['elementId']);
        $t->true(!array_key_exists('inertReviewPolicy', $active));
        $t->true(!array_key_exists('effectiveInert', $active));

        $t->contains('<article id="modal" inert>', $html);
        $t->contains('<span id="local" inert="soft-lock">', $html);
        $t->contains('<section id="named" inert>', $html);
        $t->contains('<aside id="falsey" inert="false">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/inert-attribute-review.html', $document->children[0]->attr('part'));
    },
];
