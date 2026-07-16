<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html nonce provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script id="boot" nonce="shared-token">console.log(1)</script>'
                . '<style id="theme" nonce="shared-token">body{color:red}</style>'
                . '<p id="empty" nonce="">Empty</p>'
                . '<section id="spaced" nonce=" token with space ">Copy</section>',
            'nonce provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/nonce-provenance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $script = $summary[0];
        $style = $summary[1];
        $empty = $summary[2];
        $spaced = $summary[3];

        $t->same('html-nonce-token-review', $script['nonceReviewPolicy']);
        $t->same(true, $script['noncePresent']);
        $t->same(12, $script['nonceLength']);
        $t->same(12, $script['nonceTrimmedLength']);
        $t->same(false, $script['nonceEmpty']);
        $t->same(false, $script['nonceContainsWhitespace']);
        $t->same(hash('sha256', 'shared-token'), $script['nonceSha256']);
        $t->same(2, $script['nonceSameValueElementCount']);
        $t->same(['script', 'style'], $script['nonceSameValueElementNames']);
        $t->same(['boot', 'theme'], $script['nonceSameValueElementIds']);
        $t->same(['element' => 'script', 'id' => 'boot', 'source' => 'self'], $script['nonceSameValueElements'][0]);
        $t->same(['element' => 'style', 'id' => 'theme', 'source' => 'same-fragment'], $script['nonceSameValueElements'][1]);
        $t->same(true, $script['nonceSharedInFragment']);
        $t->same(['shared-nonce-in-fragment'], $script['nonceReviewCodes']);
        $t->true(!array_key_exists('nonceRaw', $script));

        $t->same(hash('sha256', 'shared-token'), $style['nonceSha256']);
        $t->same(['script', 'style'], $style['nonceSameValueElementNames']);
        $t->same(['element' => 'script', 'id' => 'boot', 'source' => 'same-fragment'], $style['nonceSameValueElements'][0]);
        $t->same(['element' => 'style', 'id' => 'theme', 'source' => 'self'], $style['nonceSameValueElements'][1]);

        $t->same(0, $empty['nonceLength']);
        $t->same(0, $empty['nonceTrimmedLength']);
        $t->same(true, $empty['nonceEmpty']);
        $t->same(false, $empty['nonceContainsWhitespace']);
        $t->same(false, $empty['nonceSharedInFragment']);
        $t->same(['empty-nonce'], $empty['nonceReviewCodes']);

        $t->same(18, $spaced['nonceLength']);
        $t->same(16, $spaced['nonceTrimmedLength']);
        $t->same(false, $spaced['nonceEmpty']);
        $t->same(true, $spaced['nonceContainsWhitespace']);
        $t->same(hash('sha256', ' token with space '), $spaced['nonceSha256']);
        $t->same(['whitespace-nonce'], $spaced['nonceReviewCodes']);

        $t->same(
            '<script id="boot" nonce="shared-token">console.log(1)</script><style id="theme" nonce="shared-token">body{color:red}</style><p id="empty" nonce="">Empty</p><section id="spaced" nonce=" token with space ">Copy</section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/nonce-provenance-review.html', $document->children[0]->attr('part'));
    },
];
