<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html hidden subtree provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="draft" hidden="until-found"><h2 id="heading">Draft</h2><p id="note"><span id="inline">Inline note</span></p></section>'
                . '<aside id="legacy" hidden="collapse"><p id="legacy-child">Legacy hidden</p></aside>'
                . '<div id="visible"><p id="visible-child">Visible</p></div>',
            'hidden subtree review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hidden-subtree-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $draft = $summary[0];
        $heading = $draft['children'][0];
        $note = $draft['children'][1];
        $inline = $note['children'][0];
        $legacy = $summary[1];
        $legacyChild = $legacy['children'][0];
        $visible = $summary[2];
        $visibleChild = $visible['children'][0];

        $t->same('html-hidden-state-review', $draft['hiddenReviewPolicy']);
        $t->same('until-found', $draft['hiddenRaw']);
        $t->same('until-found', $draft['hiddenKeyword']);
        $t->same('until-found', $draft['hiddenState']);
        $t->same(true, $draft['hiddenUntilFound']);
        $t->same([], $draft['hiddenIssueCodes']);
        $t->same('html-hidden-subtree-review', $draft['hiddenSubtreeReviewPolicy']);
        $t->same(true, $draft['effectiveHidden']);
        $t->same('until-found', $draft['effectiveHiddenState']);
        $t->same(true, $draft['effectiveHiddenValid']);
        $t->same(false, $draft['effectiveHiddenInvalidValueDefaulted']);
        $t->same(true, $draft['effectiveHiddenUntilFound']);
        $t->same(false, $draft['hiddenInherited']);
        $t->same('self-hidden', $draft['hiddenSource']);

        $t->true(!array_key_exists('hiddenReviewPolicy', $heading));
        $t->same(true, $heading['effectiveHidden']);
        $t->same(true, $heading['hiddenInherited']);
        $t->same('ancestor-hidden', $heading['hiddenSource']);
        $t->same('section', $heading['hiddenSourceElement']);
        $t->same('draft', $heading['hiddenSourceElementId']);
        $t->same(true, $note['effectiveHiddenUntilFound']);
        $t->same('draft', $inline['hiddenSourceElementId']);

        $t->same('collapse', $legacy['hiddenRaw']);
        $t->same(null, $legacy['hiddenKeyword']);
        $t->same('hidden', $legacy['hiddenState']);
        $t->same(false, $legacy['hiddenValid']);
        $t->same(true, $legacy['hiddenInvalidValueDefaulted']);
        $t->same(['invalid-html-hidden-token'], $legacy['hiddenIssueCodes']);
        $t->same(false, $legacy['effectiveHiddenUntilFound']);
        $t->same(false, $legacy['effectiveHiddenValid']);
        $t->same(true, $legacy['effectiveHiddenInvalidValueDefaulted']);
        $t->same('hidden', $legacyChild['effectiveHiddenState']);
        $t->same(false, $legacyChild['effectiveHiddenValid']);
        $t->same(true, $legacyChild['hiddenInherited']);
        $t->same('legacy', $legacyChild['hiddenSourceElementId']);

        $t->true(!array_key_exists('effectiveHidden', $visible));
        $t->true(!array_key_exists('hiddenSubtreeReviewPolicy', $visibleChild));
        $t->contains('hidden="until-found"', $html);
        $t->contains('hidden="collapse"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/hidden-subtree-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
    'summarizes nested effective hidden validity for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="outer" hidden="until-found"><p id="child">Child <span id="leaf">leaf</span></p><section id="invalid" hidden="collapsed"><em id="inside-invalid">Invalid</em></section></article>'
                . '<aside id="self" hidden>Self hidden</aside>'
                . '<section id="plain"><p id="plain-child">Visible</p></section>',
            'hidden subtree nested validity review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hidden-subtree-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $child = $article['children'][0];
        $invalid = $article['children'][1];
        $insideInvalid = $invalid['children'][0];
        $self = $summary[1];
        $plainChild = $summary[2]['children'][0];

        $t->same('until-found', $article['hiddenRaw']);
        $t->same('until-found', $article['hiddenState']);
        $t->same('html-hidden-state-review', $article['hiddenReviewPolicy']);
        $t->same('html-hidden-subtree-review', $article['hiddenSubtreeReviewPolicy']);
        $t->same('until-found', $article['effectiveHiddenState']);
        $t->same(true, $article['effectiveHidden']);
        $t->same(true, $article['effectiveHiddenValid']);
        $t->same(false, $article['effectiveHiddenInvalidValueDefaulted']);
        $t->same(true, $article['effectiveHiddenUntilFound']);
        $t->same(false, $article['hiddenInherited']);
        $t->same('self-hidden', $article['hiddenSource']);
        $t->same('article', $article['hiddenSourceElement']);
        $t->same('outer', $article['hiddenSourceElementId']);

        $t->true(!array_key_exists('hiddenRaw', $child));
        $t->same('until-found', $child['effectiveHiddenState']);
        $t->same(true, $child['effectiveHiddenValid']);
        $t->same(true, $child['hiddenInherited']);
        $t->same('ancestor-hidden', $child['hiddenSource']);
        $t->same('article', $child['hiddenSourceElement']);
        $t->same('outer', $child['hiddenSourceElementId']);

        $t->same('collapsed', $invalid['hiddenRaw']);
        $t->same('hidden', $invalid['hiddenState']);
        $t->same(false, $invalid['hiddenValid']);
        $t->same(true, $invalid['hiddenInvalidValueDefaulted']);
        $t->same('hidden', $invalid['effectiveHiddenState']);
        $t->same(false, $invalid['effectiveHiddenValid']);
        $t->same(true, $invalid['effectiveHiddenInvalidValueDefaulted']);
        $t->same(false, $invalid['hiddenInherited']);
        $t->same('self-hidden', $invalid['hiddenSource']);

        $t->same('collapsed', $insideInvalid['effectiveHiddenRaw']);
        $t->same('hidden', $insideInvalid['effectiveHiddenState']);
        $t->same(false, $insideInvalid['effectiveHiddenValid']);
        $t->same(true, $insideInvalid['effectiveHiddenInvalidValueDefaulted']);
        $t->same(true, $insideInvalid['hiddenInherited']);
        $t->same('section', $insideInvalid['hiddenSourceElement']);
        $t->same('invalid', $insideInvalid['hiddenSourceElementId']);

        $t->same('', $self['hiddenRaw']);
        $t->same('hidden', $self['hiddenState']);
        $t->same(true, $self['effectiveHiddenValid']);
        $t->same(false, $self['effectiveHiddenUntilFound']);
        $t->same(false, $self['hiddenInherited']);

        $t->true(!array_key_exists('effectiveHiddenState', $plainChild));
        $t->contains($html, $blocks);
        $t->same('/migration/hidden-subtree-review.html', $document->children[0]->attr('part'));
    },
];
