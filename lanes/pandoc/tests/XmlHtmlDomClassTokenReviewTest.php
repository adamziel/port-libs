<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html class token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="panel" class="card  featured card data:row"><p class="">Empty</p><span class="one one two three">Copy</span><div>No class</div></section>',
            'class token review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/class-token-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $section = $summary[0];
        $empty = $section['children'][0];
        $span = $section['children'][1];
        $plain = $section['children'][2];

        $t->same('html-class-token-review', $section['classAttributeReviewPolicy']);
        $t->same('card  featured card data:row', $section['classRaw']);
        $t->same(['card', 'featured', 'card', 'data:row'], $section['classList']);
        $t->same(['card', 'featured', 'data:row'], $section['classNames']);
        $t->same(['card' => 2, 'featured' => 1, 'data:row' => 1], $section['classTokenCounts']);
        $t->same(['card'], $section['duplicateClassTokens']);
        $t->same(4, $section['classTokenCount']);
        $t->same(3, $section['uniqueClassTokenCount']);
        $t->same(1, $section['duplicateClassTokenCount']);
        $t->same(false, $section['classEmpty']);
        $t->same(true, $section['classHasDuplicates']);

        $t->same('html-class-token-review', $empty['classAttributeReviewPolicy']);
        $t->same('', $empty['classRaw']);
        $t->same([], $empty['classList']);
        $t->same([], $empty['classNames']);
        $t->same([], $empty['classTokenCounts']);
        $t->same([], $empty['duplicateClassTokens']);
        $t->same(0, $empty['classTokenCount']);
        $t->same(0, $empty['uniqueClassTokenCount']);
        $t->same(0, $empty['duplicateClassTokenCount']);
        $t->same(true, $empty['classEmpty']);
        $t->same(false, $empty['classHasDuplicates']);

        $t->same(['one', 'one', 'two', 'three'], $span['classList']);
        $t->same(['one', 'two', 'three'], $span['classNames']);
        $t->same(['one' => 2, 'two' => 1, 'three' => 1], $span['classTokenCounts']);
        $t->same(['one'], $span['duplicateClassTokens']);

        $t->true(!array_key_exists('classAttributeReviewPolicy', $plain));
        $t->true(!array_key_exists('classRaw', $plain));
        $t->same('<section class="card  featured card data:row" id="panel"><p class="">Empty</p><span class="one one two three">Copy</span><div>No class</div></section>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/class-token-review.html', $document->children[0]->attr('part'));
    },
];
