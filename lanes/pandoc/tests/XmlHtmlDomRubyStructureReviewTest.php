<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html ruby implicit bases and structure diagnostics for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><ruby id="implicit"><span>&#28304;</span><rt>source</rt></ruby>'
                . '<ruby id="empty"><span></span><rt></rt></ruby>'
                . '<ruby id="orphan"><rt>missing base</rt></ruby>'
                . '<ruby id="missing-annotation"><rb>&#23383;</rb></ruby></p>',
            'ruby structure review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/ruby-structure-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $implicit = $paragraph['children'][0];
        $empty = $paragraph['children'][1];
        $orphan = $paragraph['children'][2];
        $missingAnnotation = $paragraph['children'][3];

        $t->same('html-ruby-annotation-structure-review', $implicit['rubyStructureReviewPolicy']);
        $t->same(["\u{6e90}"], $implicit['rubyBaseTexts']);
        $t->same([[
            'source' => 'implicit-element',
            'tag' => 'span',
            'text' => "\u{6e90}",
        ]], $implicit['rubyBaseRecords']);
        $t->same(["\u{6e90}"], $implicit['rubyImplicitBaseTexts']);
        $t->same(1, $implicit['rubyImplicitBaseCount']);
        $t->same(['source'], $implicit['rubyAnnotationTexts']);
        $t->same(true, $implicit['rubyStructureValid']);
        $t->same([], $implicit['rubyIssueCodes']);

        $t->same([], $empty['rubyBaseTexts']);
        $t->same([[
            'source' => 'implicit-element',
            'tag' => 'span',
            'text' => '',
        ]], $empty['rubyBaseRecords']);
        $t->same([''], $empty['rubyAnnotationTexts']);
        $t->same(false, $empty['rubyStructureValid']);
        $t->same(['empty-ruby-base', 'empty-ruby-annotation'], $empty['rubyIssueCodes']);
        $t->same([
            ['code' => 'empty-ruby-base', 'index' => 0, 'source' => 'implicit-element', 'tag' => 'span'],
            ['code' => 'empty-ruby-annotation', 'index' => 0, 'container' => null],
        ], $empty['rubyIssues']);

        $t->same([], $orphan['rubyBaseRecords']);
        $t->same(['missing base'], $orphan['rubyAnnotationTexts']);
        $t->same(false, $orphan['rubyStructureValid']);
        $t->same(['missing-ruby-base'], $orphan['rubyIssueCodes']);

        $t->same([[
            'source' => 'rb',
            'tag' => 'rb',
            'text' => "\u{5b57}",
        ]], $missingAnnotation['rubyBaseRecords']);
        $t->same([], $missingAnnotation['rubyAnnotations']);
        $t->same(false, $missingAnnotation['rubyStructureValid']);
        $t->same(['missing-ruby-annotation'], $missingAnnotation['rubyIssueCodes']);

        $t->same(
            '<p><ruby id="implicit"><span>' . "\u{6e90}" . '</span><rt>source</rt></ruby>'
                . '<ruby id="empty"><span></span><rt></rt></ruby>'
                . '<ruby id="orphan"><rt>missing base</rt></ruby>'
                . '<ruby id="missing-annotation"><rb>' . "\u{5b57}" . '</rb></ruby></p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/ruby-structure-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
