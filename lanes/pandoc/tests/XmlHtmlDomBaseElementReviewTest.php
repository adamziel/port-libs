<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html base element provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<base href="https://source.example.test/docs/index.html" target="review-frame">'
                . '<base href="../ignored/" target="_blank">'
                . '<p><a href="./doc.html">doc</a></p>',
            'base element review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/base-element-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $base = $summary[0];
        $duplicate = $summary[1];
        $paragraph = $summary[2];
        $link = $paragraph['children'][0];

        $t->same('html-base-element-document-base-review', $base['baseReviewPolicy']);
        $t->same('https://source.example.test/docs/index.html', $base['baseHrefRaw']);
        $t->same('https://source.example.test/docs/index.html', $base['baseHrefTrimmed']);
        $t->same('absolute', $base['baseHrefKind']);
        $t->same('https', $base['baseHrefScheme']);
        $t->same(false, $base['baseHrefUnsafe']);
        $t->same(true, $base['baseHrefTrustedAbsolute']);
        $t->same(false, $base['baseHrefNeedsCallerBase']);
        $t->same(true, $base['baseHrefFirstActive']);
        $t->same(0, $base['baseHrefActiveIndex']);
        $t->same(false, $base['baseHrefDuplicateIgnored']);
        $t->same('review-frame', $base['baseTargetName']);
        $t->same(false, $base['baseTargetReserved']);
        $t->same(false, $base['baseTargetBlank']);
        $t->same(true, $base['baseTargetValid']);
        $t->same(true, $base['baseTargetFirstActive']);
        $t->same([], $base['baseIssueCodes']);
        $t->same(true, $base['baseValid']);
        $t->same(true, $base['baseReviewOnlyNoNavigation']);

        $t->same('../ignored/', $duplicate['baseHrefRaw']);
        $t->same('relative', $duplicate['baseHrefKind']);
        $t->same(true, $duplicate['baseHrefNeedsCallerBase']);
        $t->same(false, $duplicate['baseHrefFirstActive']);
        $t->same(1, $duplicate['baseHrefActiveIndex']);
        $t->same(true, $duplicate['baseHrefDuplicateIgnored']);
        $t->same('_blank', $duplicate['baseTargetName']);
        $t->same(true, $duplicate['baseTargetReserved']);
        $t->same(true, $duplicate['baseTargetBlank']);
        $t->same(false, $duplicate['baseTargetFirstActive']);
        $t->same(1, $duplicate['baseTargetActiveIndex']);
        $t->same(true, $duplicate['baseTargetDuplicateIgnored']);
        $t->same([
            'duplicate-base-href-ignored',
            'duplicate-base-target-ignored',
        ], $duplicate['baseIssueCodes']);
        $t->same(false, $duplicate['baseValid']);

        $t->same('p', $paragraph['name']);
        $t->same('a', $link['name']);
        $t->same('./doc.html', $link['hrefRaw']);
        $t->same('relative', $link['hrefKind']);
        $t->same('<base href="https://source.example.test/docs/index.html" target="review-frame"><base href="../ignored/" target="_blank"><p><a href="./doc.html">doc</a></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/base-element-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);

        $unsafe = XmlHtmlDom::summarizeHtmlFragment(XmlHtmlDom::loadHtmlFragment(
            '<base href="java&#10;script:alert(1)" target="review&#10;&lt;frame"><p>Body</p>',
            'unsafe base review fragment'
        ))[0];

        $t->same("java\nscript:alert(1)", $unsafe['baseHrefRaw']);
        $t->same('invalid', $unsafe['baseHrefKind']);
        $t->same(true, $unsafe['baseHrefUnsafe']);
        $t->same('_blank', $unsafe['baseTargetName']);
        $t->same(true, $unsafe['baseTargetReserved']);
        $t->same(true, $unsafe['baseTargetBlank']);
        $t->same(false, $unsafe['baseTargetValid']);
        $t->same(true, $unsafe['baseTargetNormalizedToBlank']);
        $t->same([
            'invalid-base-href',
            'unsafe-base-target-normalized-to-blank',
        ], $unsafe['baseIssueCodes']);

        $invalid = XmlHtmlDom::summarizeHtmlFragment(XmlHtmlDom::loadHtmlFragment(
            '<base href="" target="bad target"><p>Body</p>',
            'invalid base review fragment'
        ))[0];

        $t->same('', $invalid['baseHrefRaw']);
        $t->same(null, $invalid['baseHrefTrimmed']);
        $t->same('empty', $invalid['baseHrefKind']);
        $t->same(false, $invalid['baseHrefActiveCandidate']);
        $t->same('bad target', $invalid['baseTargetRaw']);
        $t->same(null, $invalid['baseTargetName']);
        $t->same(false, $invalid['baseTargetActiveCandidate']);
        $t->same([
            'empty-base-href',
            'invalid-base-target',
        ], $invalid['baseIssueCodes']);
    },
];
