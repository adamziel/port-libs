<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html mark highlight review metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article>'
                . '<h2><mark id="heading" data-review="title">Release notes</mark></h2>'
                . '<p>Use <mark id="plain" title="review focus">portable HTML</mark>'
                . ' <a href="/diff"><mark id="link">diff target</mark></a>'
                . ' <mark id="outer">outer <mark id="inner">inner</mark></mark>'
                . ' <mark id="empty">   </mark>'
                . ' <ins><mark id="revision">added</mark></ins>'
                . '</p>'
                . '</article>',
            'mark highlight review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/mark-highlight-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $byId = [];
        $collect = static function (array $nodes) use (&$collect, &$byId): void {
            foreach ($nodes as $node) {
                $id = $node['attributes']['id'] ?? null;
                if (is_string($id) && $id !== '') {
                    $byId[$id] = $node;
                }
                if (isset($node['children']) && is_array($node['children'])) {
                    $collect($node['children']);
                }
            }
        };
        $collect($summary);

        $plain = $byId['plain'];
        $heading = $byId['heading'];
        $link = $byId['link'];
        $outer = $byId['outer'];
        $inner = $byId['inner'];
        $empty = $byId['empty'];
        $revision = $byId['revision'];

        $t->same('article', $summary[0]['name']);
        $t->same('mark', $plain['name']);
        $t->same('mark', $plain['textSemantic']);
        $t->same('mark', $plain['semanticTag']);
        $t->same('portable HTML', $plain['semanticText']);
        $t->same('html-mark-highlight-review', $plain['markHighlightReviewPolicy']);
        $t->same('mark', $plain['markHighlightElement']);
        $t->same('portable HTML', $plain['markHighlightTextRaw']);
        $t->same('portable HTML', $plain['markHighlightText']);
        $t->same(strlen('portable HTML'), $plain['markHighlightTextByteLength']);
        $t->same(strlen('portable HTML'), $plain['markHighlightNormalizedTextByteLength']);
        $t->same(hash('sha256', 'portable HTML'), $plain['markHighlightTextSha256']);
        $t->same('inline-highlight', $plain['markHighlightContext']);
        $t->same('p', $plain['markHighlightParentElement']);
        $t->same(false, $plain['markHighlightHasNestedHighlights']);
        $t->same(0, $plain['markHighlightNestedCount']);
        $t->same([], $plain['markHighlightNestedTexts']);
        $t->same(true, $plain['markHighlightValid']);
        $t->same([], $plain['markHighlightIssueCodes']);
        $t->same(0, $plain['markHighlightIssueCount']);
        $t->same([], $plain['markHighlightIssues']);

        $t->same('heading-highlight', $heading['markHighlightContext']);
        $t->same('h2', $heading['markHighlightParentElement']);
        $t->same('Release notes', $heading['markHighlightText']);
        $t->same('link-highlight', $link['markHighlightContext']);
        $t->same('a', $link['markHighlightParentElement']);
        $t->same('revision-highlight', $revision['markHighlightContext']);
        $t->same('ins', $revision['markHighlightParentElement']);

        $t->same('outer inner', $outer['markHighlightTextRaw']);
        $t->same('outer inner', $outer['markHighlightText']);
        $t->same(true, $outer['markHighlightHasNestedHighlights']);
        $t->same(1, $outer['markHighlightNestedCount']);
        $t->same(['inner'], $outer['markHighlightNestedTexts']);
        $t->same(false, $outer['markHighlightValid']);
        $t->same(['nested-mark-highlight'], $outer['markHighlightIssueCodes']);
        $t->same([['code' => 'nested-mark-highlight', 'count' => 1]], $outer['markHighlightIssues']);

        $t->same('nested-highlight', $inner['markHighlightContext']);
        $t->same('mark', $inner['markHighlightParentElement']);
        $t->same('inner', $inner['markHighlightText']);
        $t->same(true, $inner['markHighlightValid']);
        $t->same([], $inner['markHighlightIssueCodes']);

        $t->same('   ', $empty['markHighlightTextRaw']);
        $t->same('', $empty['markHighlightText']);
        $t->same(3, $empty['markHighlightTextByteLength']);
        $t->same(0, $empty['markHighlightNormalizedTextByteLength']);
        $t->same(false, $empty['markHighlightValid']);
        $t->same(['empty-mark-highlight'], $empty['markHighlightIssueCodes']);
        $t->same([['code' => 'empty-mark-highlight']], $empty['markHighlightIssues']);

        $t->contains('<mark', $html);
        $t->contains('data-review="title"', $html);
        $t->contains('<ins><mark id="revision">added</mark></ins>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/mark-highlight-review.html', $document->children[0]->attr('part'));
        json_encode([$plain, $heading, $link, $outer, $inner, $empty, $revision], JSON_THROW_ON_ERROR);
    },
];
