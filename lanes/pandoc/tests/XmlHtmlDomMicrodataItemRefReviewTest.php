<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes microdata itemref idref provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="event" itemscope itemtype="https://schema.org/Event" itemref="headline author author missing bad&lt;tag">'
                . '<h1 id="headline" itemprop="name headline">Launch</h1></article>'
                . '<p id="author" itemprop="author">Ada</p>'
                . '<p id="plain">Plain</p>',
            'microdata itemref review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/microdata-itemref-idref-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $headline = $article['children'][0];
        $author = $summary[1];
        $plain = $summary[2];

        $t->same('item', $article['microdata']);
        $t->same('html-microdata-itemref-idref-review', $article['itemRefReviewPolicy']);
        $t->same('headline author author missing bad<tag', $article['itemRefRaw']);
        $t->same(['headline', 'author', 'author', 'missing', 'bad<tag'], $article['itemRefTokens']);
        $t->same(['headline', 'author', 'missing'], $article['itemRefIds']);
        $t->same(['bad<tag'], $article['invalidItemRefIds']);
        $t->same(['author'], $article['duplicateItemRefIds']);
        $t->same(false, $article['itemRefValid']);
        $t->same(['headline', 'author'], $article['itemRefResolvedIds']);
        $t->same(['missing'], $article['itemRefMissingIds']);
        $t->same(3, $article['itemRefReferenceCount']);
        $t->same(2, $article['itemRefResolvedCount']);
        $t->same(1, $article['itemRefMissingCount']);
        $t->same(['invalid-itemref-id', 'duplicate-itemref-id', 'missing-itemref-target'], $article['itemRefIssueCodes']);
        $t->same(false, $article['itemRefReferencesComplete']);

        $t->same('headline', $article['itemRefReferences'][0]['id']);
        $t->same('resolved', $article['itemRefReferences'][0]['status']);
        $t->same('h1', $article['itemRefReferences'][0]['target']['tag']);
        $t->same(['name', 'headline'], $article['itemRefReferences'][0]['target']['itemProperties']);
        $t->same('author', $article['itemRefReferences'][1]['id']);
        $t->same('p', $article['itemRefReferences'][1]['target']['tag']);
        $t->same('Ada', $article['itemRefReferences'][1]['target']['text']);
        $t->same('missing', $article['itemRefReferences'][2]['id']);
        $t->same('missing', $article['itemRefReferences'][2]['status']);
        $t->same(null, $article['itemRefReferences'][2]['target']);
        $t->same('headline', $article['itemRefTargetElements'][0]['id']);
        $t->same(['name', 'headline'], $article['itemRefTargetElements'][0]['itemProperties']);
        $t->same('author', $article['itemRefTargetElements'][1]['id']);
        $t->same(['author'], $article['itemRefTargetElements'][1]['itemProperties']);

        $t->same('property', $headline['microdata']);
        $t->same(['name', 'headline'], $headline['itemProperties']);
        $t->same('property', $author['microdata']);
        $t->same(['author'], $author['itemProperties']);
        $t->true(!array_key_exists('microdata', $plain));

        $t->same(
            '<article id="event" itemref="headline author author missing bad&lt;tag" itemscope itemtype="https://schema.org/Event"><h1 id="headline" itemprop="name headline">Launch</h1></article>'
                . '<p id="author" itemprop="author">Ada</p>'
                . '<p id="plain">Plain</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/microdata-itemref-idref-review.html', $document->children[0]->attr('part'));
        json_encode($article['itemRefReferences'], JSON_THROW_ON_ERROR);
    },
];
