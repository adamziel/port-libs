<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html search region form and control rollups for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<search id="catalog-search" aria-label="Catalog search">'
                . '<form id="keyword-search" action="/find" method="post"><label for="q">Query</label>'
                . '<input id="q" name="q" type="search" value="gtk"><button id="go" name="go" value="1">Go</button></form>'
                . '<form id="refine-search" action="/filter"><label for="category">Category</label>'
                . '<select id="category" name="category"><option selected>Docs</option></select>'
                . '<textarea id="note" name="note">review</textarea>'
                . '<input id="visual" name="visual" type="image" src="go.png" alt="Visual search"></form>'
                . '</search>',
            'search region control rollup review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/search-region-control-rollup-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $search = $summary[0];
        $keyword = $search['searchForms'][0];
        $refine = $search['searchForms'][1];

        $t->same('html-search-region-control-rollup', $search['searchReviewPolicy']);
        $t->same(['keyword-search', 'refine-search'], $search['searchFormIds']);
        $t->same(['/find', '/filter'], $search['searchFormActions']);
        $t->same(['post', 'get'], $search['searchFormMethods']);
        $t->same([2, 3], $search['searchFormControlCounts']);
        $t->same(5, $search['searchControlCount']);
        $t->same(['input', 'button', 'select', 'textarea'], $search['searchControlTags']);
        $t->same(['q', 'go', 'category', 'note', 'visual'], $search['searchControlNames']);
        $t->same(['search', 'submit', 'image'], $search['searchControlTypes']);
        $t->same(2, $search['searchSubmitterCount']);
        $t->same(['go', 'visual'], $search['searchSubmitterNames']);

        $t->same(2, $keyword['controlCount']);
        $t->same(['q', 'go'], $keyword['controlNames']);
        $t->same(['search', 'submit'], $keyword['controlTypes']);
        $t->same(1, $keyword['submitterCount']);
        $t->same(['go'], $keyword['submitterNames']);

        $t->same(3, $refine['controlCount']);
        $t->same(['category', 'note', 'visual'], $refine['controlNames']);
        $t->same(['image'], $refine['controlTypes']);
        $t->same(1, $refine['submitterCount']);
        $t->same(['visual'], $refine['submitterNames']);

        $t->contains('type="image"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/search-region-control-rollup-review.html', $document->children[0]->attr('part'));
        json_encode($search, JSON_THROW_ON_ERROR);
    },
];
