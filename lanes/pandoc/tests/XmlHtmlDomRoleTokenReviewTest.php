<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html role token recovery for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="doc" role="doc-chapter region REGION bad&lt;role">'
                . '<p id="empty" role="">Empty</p>'
                . '<aside id="fallback" role="presentation none presentation">Aside</aside>'
                . '<span id="plain">Plain</span>'
                . '</section>',
            'role token review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/role-token-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $section = $summary[0];
        $empty = $section['children'][0];
        $fallback = $section['children'][1];
        $plain = $section['children'][2];

        $t->same('html-role-token-list-review', $section['roleAttributeReviewPolicy']);
        $t->same('doc-chapter region REGION bad<role', $section['roleRaw']);
        $t->same(['doc-chapter', 'region', 'REGION', 'bad<role'], $section['roles']);
        $t->same(['doc-chapter', 'region'], $section['roleNames']);
        $t->same(['doc-chapter' => 1, 'region' => 2], $section['roleTokenCounts']);
        $t->same(['region'], $section['duplicateRoleTokens']);
        $t->same(['bad<role'], $section['invalidRoleTokens']);
        $t->same(4, $section['roleTokenCount']);
        $t->same(2, $section['roleNameCount']);
        $t->same(1, $section['duplicateRoleTokenCount']);
        $t->same(1, $section['invalidRoleTokenCount']);
        $t->same('doc-chapter', $section['primaryRole']);
        $t->same(false, $section['roleEmpty']);
        $t->same(true, $section['roleHasDuplicates']);
        $t->same(false, $section['roleValid']);

        $t->same('', $empty['roleRaw']);
        $t->same([], $empty['roles']);
        $t->same([], $empty['roleNames']);
        $t->same([], $empty['roleTokenCounts']);
        $t->same([], $empty['duplicateRoleTokens']);
        $t->same([], $empty['invalidRoleTokens']);
        $t->same(null, $empty['primaryRole']);
        $t->same(true, $empty['roleEmpty']);
        $t->same(false, $empty['roleValid']);

        $t->same(['presentation', 'none', 'presentation'], $fallback['roles']);
        $t->same(['presentation', 'none'], $fallback['roleNames']);
        $t->same(['presentation' => 2, 'none' => 1], $fallback['roleTokenCounts']);
        $t->same(['presentation'], $fallback['duplicateRoleTokens']);
        $t->same([], $fallback['invalidRoleTokens']);
        $t->same('presentation', $fallback['primaryRole']);
        $t->same(true, $fallback['roleHasDuplicates']);
        $t->same(true, $fallback['roleValid']);

        $t->true(!array_key_exists('roleRaw', $plain));
        $t->true(!array_key_exists('roles', $plain));
        $t->same(
            '<section id="doc" role="doc-chapter region REGION bad&lt;role"><p id="empty" role="">Empty</p><aside id="fallback" role="presentation none presentation">Aside</aside><span id="plain">Plain</span></section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/role-token-review.html', $document->children[0]->attr('part'));
        json_encode([$section, $empty, $fallback], JSON_THROW_ON_ERROR);
    },
];
