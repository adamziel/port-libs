<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html microdata relationship duplicates and itemref targets for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="review" itemscope="itemscope" itemtype="https://schema.org/Article https://schema.org/Article bad&lt;type" itemid="" itemref="headline extra extra nested missing bad&lt;target">'
                . '<h1 id="headline" itemprop="headline headline bad&lt;prop">Title</h1>'
                . '<p id="summary" itemprop="description">Summary</p>'
                . '<div id="nested" itemprop="author" itemscope itemtype="https://schema.org/Person"><span itemprop="name">Ada</span></div>'
                . '</article><span id="extra" itemprop="keywords">xml dom</span>',
            'microdata relationship review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/microdata-relationship-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $headline = $article['children'][0];
        $nested = $article['children'][2];
        $extra = $summary[1];

        $t->same('html-microdata-relationship-review', $article['microdataReviewPolicy']);
        $t->same('item', $article['microdata']);
        $t->same(['itemref', 'itemscope', 'itemtype', 'itemid'], $article['microdataAttributes']);
        $t->same('itemscope', $article['itemScopeRaw']);
        $t->same(true, $article['itemScopeHasValue']);
        $t->same(true, $article['itemScopeValueConforming']);
        $t->same([], $article['itemScopeIssueCodes']);

        $t->same([
            'https://schema.org/Article',
            'https://schema.org/Article',
            'bad<type',
        ], $article['itemTypeTokens']);
        $t->same(['https://schema.org/Article'], $article['itemTypes']);
        $t->same(['https://schema.org/Article' => 2], $article['itemTypeTokenCounts']);
        $t->same(['bad<type'], $article['invalidItemTypes']);
        $t->same(['https://schema.org/Article'], $article['duplicateItemTypes']);
        $t->same(['invalid-itemtype-token', 'duplicate-itemtype-token'], $article['itemTypeIssueCodes']);

        $t->same('', $article['itemIdRaw']);
        $t->same(null, $article['itemId']);
        $t->same(['empty-itemid'], $article['itemIdIssueCodes']);
        $t->same(false, $article['itemIdValid']);

        $t->same(['headline', 'extra', 'extra', 'nested', 'missing', 'bad<target'], $article['itemRefTokens']);
        $t->same(['headline', 'extra', 'nested', 'missing'], $article['itemRefIds']);
        $t->same([
            'headline' => 1,
            'extra' => 2,
            'nested' => 1,
            'missing' => 1,
        ], $article['itemRefTokenCounts']);
        $t->same(['bad<target'], $article['invalidItemRefIds']);
        $t->same(['extra'], $article['duplicateItemRefIds']);
        $t->same(['headline', 'extra', 'nested'], $article['itemRefResolvedIds']);
        $t->same(['missing'], $article['itemRefMissingIds']);
        $t->same(4, $article['itemRefTargetCount']);
        $t->same(3, $article['itemRefResolvedTargetCount']);
        $t->same(1, $article['itemRefMissingTargetCount']);
        $t->same(['h1', 'span', 'div'], $article['itemRefTargetElements']);
        $t->same(['property', 'item'], $article['itemRefTargetMicrodataKinds']);
        $t->same([
            'invalid-itemref-token',
            'duplicate-itemref-token',
            'missing-itemref-target',
        ], $article['itemRefIssueCodes']);

        $t->same('resolved-property', $article['itemRefTargets'][0]['status']);
        $t->same('headline', $article['itemRefTargets'][0]['id']);
        $t->same('h1', $article['itemRefTargets'][0]['element']);
        $t->same(['headline'], $article['itemRefTargets'][0]['itemProperties']);
        $t->same('resolved-item-property', $article['itemRefTargets'][2]['status']);
        $t->same('nested', $article['itemRefTargets'][2]['id']);
        $t->same(true, $article['itemRefTargets'][2]['itemScope']);
        $t->same(['author'], $article['itemRefTargets'][2]['itemProperties']);
        $t->same(['https://schema.org/Person'], $article['itemRefTargets'][2]['itemTypes']);
        $t->same('Ada', $article['itemRefTargets'][2]['text']);
        $t->same('missing-target', $article['itemRefTargets'][3]['status']);
        $t->same(null, $article['itemRefTargets'][3]['element']);

        $t->same([
            'invalid-itemtype-token',
            'duplicate-itemtype-token',
            'empty-itemid',
            'invalid-itemref-token',
            'duplicate-itemref-token',
            'missing-itemref-target',
        ], $article['microdataIssueCodes']);
        $t->same(false, $article['microdataValid']);

        $t->same(['headline', 'headline', 'bad<prop'], $headline['itemPropTokens']);
        $t->same(['headline' => 2], $headline['itemPropTokenCounts']);
        $t->same(['bad<prop'], $headline['invalidItemProperties']);
        $t->same(['headline'], $headline['duplicateItemProperties']);
        $t->same(['invalid-itemprop-token', 'duplicate-itemprop-token'], $headline['itemPropIssueCodes']);
        $t->same(['invalid-itemprop-token', 'duplicate-itemprop-token'], $headline['microdataIssueCodes']);

        $t->same('item', $nested['microdata']);
        $t->same('author', $nested['itemPropRaw']);
        $t->same(['author'], $nested['itemProperties']);
        $t->same([], $nested['microdataIssueCodes']);
        $t->same(true, $nested['microdataValid']);

        $t->same('property', $extra['microdata']);
        $t->same(['keywords'], $extra['itemProperties']);
        $t->same([], $extra['microdataIssueCodes']);
        $t->same(true, $extra['microdataValid']);

        $t->contains('itemref="headline extra extra nested missing bad&lt;target"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/microdata-relationship-review.html', $document->children[0]->attr('part'));
    },
];
