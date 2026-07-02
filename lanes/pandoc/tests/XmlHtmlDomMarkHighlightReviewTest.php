<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html mark highlight diagnostics for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p id="packet">Alpha <mark id="hit">Matched <em>term</em></mark> '
                . '<q id="quoted">Quoted <mark id="quoted-hit"><mark id="inner">inner</mark> claim</mark></q> '
                . '<mark id="empty"></mark></p>',
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

        $hit = $byId['hit'];
        $quotedHit = $byId['quoted-hit'];
        $inner = $byId['inner'];
        $empty = $byId['empty'];

        $t->same('mark', $hit['name']);
        $t->same('mark', $hit['textSemantic']);
        $t->same('html-mark-highlight-review', $hit['highlightReviewPolicy']);
        $t->same('Matched term', $hit['highlightText']);
        $t->same(strlen('Matched term'), $hit['highlightTextLength']);
        $t->same(hash('sha256', 'Matched term'), $hit['highlightTextSha256']);
        $t->same(false, $hit['highlightEmpty']);
        $t->same('p', $hit['highlightParentElement']);
        $t->same(['p'], $hit['highlightAncestorElements']);
        $t->same(false, $hit['highlightInQuote']);
        $t->same(false, $hit['highlightInsideHighlight']);
        $t->same(0, $hit['highlightNestedCount']);
        $t->same([], $hit['highlightIssueCodes']);
        $t->same(true, $hit['highlightValid']);

        $t->same('inner claim', $quotedHit['highlightText']);
        $t->same('q', $quotedHit['highlightParentElement']);
        $t->same(['q', 'p'], $quotedHit['highlightAncestorElements']);
        $t->same(true, $quotedHit['highlightInQuote']);
        $t->same(['q'], $quotedHit['highlightQuoteAncestorElements']);
        $t->same(1, $quotedHit['highlightNestedCount']);
        $t->same(['inner'], $quotedHit['highlightNestedTexts']);
        $t->same(['nested-highlight'], $quotedHit['highlightIssueCodes']);
        $t->same(false, $quotedHit['highlightValid']);

        $t->same('inner', $inner['highlightText']);
        $t->same('mark', $inner['highlightParentElement']);
        $t->same(['mark', 'q', 'p'], $inner['highlightAncestorElements']);
        $t->same(true, $inner['highlightInsideHighlight']);
        $t->same(['highlight-inside-highlight'], $inner['highlightIssueCodes']);
        $t->same(false, $inner['highlightValid']);

        $t->same('', $empty['highlightText']);
        $t->same(true, $empty['highlightEmpty']);
        $t->same(['empty-highlight'], $empty['highlightIssueCodes']);
        $t->same(false, $empty['highlightValid']);

        $t->same(
            '<p id="packet">Alpha <mark id="hit">Matched <em>term</em></mark> <q id="quoted">Quoted <mark id="quoted-hit"><mark id="inner">inner</mark> claim</mark></q> <mark id="empty"></mark></p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/mark-highlight-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
