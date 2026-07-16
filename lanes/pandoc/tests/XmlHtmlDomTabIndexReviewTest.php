<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html tabindex focus order provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<nav id="jump-list">'
                . '<a id="jump" href="#main" tabindex="0">Jump</a>'
                . '<button id="promoted" tabindex="2">Pin</button>'
                . '<section id="panel" tabindex="-1">Panel</section>'
                . '<div id="bad" tabindex="later">Bad</div>'
                . '<input id="empty" tabindex disabled value="Off">'
                . '<p id="plain">Plain</p>'
                . '</nav>',
            'tabindex focus order review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/tabindex-focus-order-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $nav = $summary[0];
        $jump = $nav['children'][0];
        $promoted = $nav['children'][1];
        $panel = $nav['children'][2];
        $bad = $nav['children'][3];
        $empty = $nav['children'][4];
        $plain = $nav['children'][5];

        $t->same('html-tabindex-focus-order-review', $jump['tabIndexReviewPolicy']);
        $t->same('0', $jump['tabIndexRaw']);
        $t->same(0, $jump['tabIndex']);
        $t->same(true, $jump['tabIndexValid']);
        $t->same(true, $jump['tabIndexNativeFocusable']);
        $t->same(false, $jump['tabIndexDisabledFormControl']);
        $t->same(true, $jump['tabIndexFocusable']);
        $t->same(true, $jump['tabIndexSequentialFocusable']);
        $t->same(false, $jump['tabIndexProgrammaticOnly']);
        $t->same(false, $jump['tabIndexPositiveOrder']);
        $t->same(true, $jump['tabIndexDocumentOrder']);
        $t->same('document-order', $jump['tabIndexOrderBucket']);
        $t->same([], $jump['tabIndexIssueCodes']);

        $t->same(2, $promoted['tabIndex']);
        $t->same(true, $promoted['tabIndexNativeFocusable']);
        $t->same(true, $promoted['tabIndexSequentialFocusable']);
        $t->same(true, $promoted['tabIndexPositiveOrder']);
        $t->same('positive-order', $promoted['tabIndexOrderBucket']);
        $t->same(['positive-tabindex-focus-order'], $promoted['tabIndexIssueCodes']);
        $t->same([['code' => 'positive-tabindex-focus-order', 'tabIndex' => 2]], $promoted['tabIndexIssues']);

        $t->same(-1, $panel['tabIndex']);
        $t->same(false, $panel['tabIndexNativeFocusable']);
        $t->same(true, $panel['tabIndexFocusable']);
        $t->same(false, $panel['tabIndexSequentialFocusable']);
        $t->same(true, $panel['tabIndexProgrammaticOnly']);
        $t->same('programmatic-only', $panel['tabIndexOrderBucket']);
        $t->same([], $panel['tabIndexIssueCodes']);

        $t->same('later', $bad['tabIndexRaw']);
        $t->same(null, $bad['tabIndex']);
        $t->same(false, $bad['tabIndexValid']);
        $t->same(false, $bad['tabIndexNativeFocusable']);
        $t->same(false, $bad['tabIndexFocusable']);
        $t->same(false, $bad['tabIndexSequentialFocusable']);
        $t->same('invalid', $bad['tabIndexOrderBucket']);
        $t->same(['invalid-tabindex-integer'], $bad['tabIndexIssueCodes']);

        $t->same('', $empty['tabIndexRaw']);
        $t->same(false, $empty['tabIndexValid']);
        $t->same(false, $empty['tabIndexNativeFocusable']);
        $t->same(true, $empty['tabIndexDisabledFormControl']);
        $t->same(false, $empty['tabIndexFocusable']);
        $t->same(['empty-tabindex'], $empty['tabIndexIssueCodes']);

        $t->true(!array_key_exists('tabIndexReviewPolicy', $plain));
        $t->true(!array_key_exists('tabIndexRaw', $plain));

        $t->same(
            '<nav id="jump-list"><a href="#main" id="jump" tabindex="0">Jump</a><button id="promoted" tabindex="2">Pin</button><section id="panel" tabindex="-1">Panel</section><div id="bad" tabindex="later">Bad</div><input disabled id="empty" tabindex="" value="Off"><p id="plain">Plain</p></nav>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/tabindex-focus-order-review.html', $document->children[0]->attr('part'));
    },
];
