<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html inert subtree metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="modal" inert><button id="save">Save</button><a id="link" href="/next">Next</a></article>'
                . '<section id="keyword" inert="inert"><span id="keyword-child">Child</span></section>'
                . '<section id="noncanonical" inert="false"><button id="blocked">Blocked</button></section>'
                . '<section id="active"><button id="active-button">Active</button></section>',
            'inert subtree review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/inert-subtree-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $modal = $summary[0];
        $save = $modal['children'][0];
        $link = $modal['children'][1];
        $keyword = $summary[1];
        $keywordChild = $keyword['children'][0];
        $noncanonical = $summary[2];
        $blocked = $noncanonical['children'][0];
        $active = $summary[3];
        $activeButton = $active['children'][0];

        $t->same('html-inert-subtree-review', $modal['inertReviewPolicy']);
        $t->same('', $modal['inertRaw']);
        $t->same(true, $modal['inert']);
        $t->same(true, $modal['inertCanonical']);
        $t->same(false, $modal['inertNoncanonicalValue']);
        $t->same(true, $modal['inertSuppressesFocus']);
        $t->same(true, $modal['inertSuppressesUserInteraction']);
        $t->same(true, $modal['inertRemovesFromAccessibilityTree']);
        $t->same(false, $modal['inertBrowserEventDispatch']);
        $t->same('metadata-only-no-browser-inert-processing', $modal['inertReviewHandoffPolicy']);
        $t->same('article', $modal['inertElement']);
        $t->same('modal', $modal['inertElementId']);
        $t->same([], $modal['inertIssues']);
        $t->same([], $modal['inertIssueCodes']);
        $t->same(true, $modal['inertValid']);
        $t->same('html-inert-subtree-review', $modal['inertSubtreeReviewPolicy']);
        $t->same('', $modal['effectiveInertRaw']);
        $t->same(true, $modal['effectiveInert']);
        $t->same(false, $modal['inertInherited']);
        $t->same('self-inert', $modal['inertSource']);
        $t->same('article', $modal['inertSourceElement']);
        $t->same('modal', $modal['inertSourceElementId']);

        $t->same(true, $save['effectiveInert']);
        $t->same(true, $save['effectiveInertSuppressesFocus']);
        $t->same(true, $save['effectiveInertSuppressesUserInteraction']);
        $t->same(true, $save['effectiveInertRemovesFromAccessibilityTree']);
        $t->same(true, $save['inertInherited']);
        $t->same('ancestor-inert', $save['inertSource']);
        $t->same('article', $save['inertSourceElement']);
        $t->same('modal', $save['inertSourceElementId']);
        $t->same(true, $link['effectiveInert']);
        $t->same('ancestor-inert', $link['inertSource']);

        $t->same('inert', $keyword['inertRaw']);
        $t->same(true, $keyword['inertCanonical']);
        $t->same(true, $keyword['inertValid']);
        $t->same(true, $keywordChild['effectiveInert']);
        $t->same('keyword', $keywordChild['inertSourceElementId']);

        $t->same('false', $noncanonical['inertRaw']);
        $t->same(true, $noncanonical['inert']);
        $t->same(false, $noncanonical['inertCanonical']);
        $t->same(true, $noncanonical['inertNoncanonicalValue']);
        $t->same([
            [
                'code' => 'noncanonical-html-inert-value',
                'inertRaw' => 'false',
            ],
        ], $noncanonical['inertIssues']);
        $t->same(['noncanonical-html-inert-value'], $noncanonical['inertIssueCodes']);
        $t->same(false, $noncanonical['inertValid']);
        $t->same(true, $blocked['effectiveInert']);
        $t->same('noncanonical', $blocked['inertSourceElementId']);

        $t->true(!array_key_exists('inertReviewPolicy', $active));
        $t->true(!array_key_exists('effectiveInert', $active));
        $t->true(!array_key_exists('effectiveInert', $activeButton));

        $t->contains('<article id="modal" inert>', $html);
        $t->contains('<section id="keyword" inert>', $html);
        $t->contains('<section id="noncanonical" inert="false">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/inert-subtree-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
