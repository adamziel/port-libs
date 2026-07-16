<?php

declare(strict_types=1);

use PortLibs\Pandoc\XmlHtmlDomFragment;

return [
    'filters html fragment multi url attributes and ping side effects' => static function (TestRunner $t): void {
        $fragment = XmlHtmlDomFragment::parseHtml(
            '<section>'
            . '<a href="/safe" ping="https://tracker.example.test/ping">Tracked</a>'
            . '<img src="/media/fallback.png" srcset="/media/fallback.png 1x, javascript:alert(1) 2x, data:text/html;base64,PHNj 3x" alt="Fallback">'
            . '<blockquote cite="vbscript:alert(1)">Quote</blockquote>'
            . '<div background="javascript:alert(1)" longdesc="https://example.test/review">Media</div>'
            . '<video poster="java&#10;script:alert(1)"><source src="/media/video.mp4" srcset="/media/video.mp4 1x"></video>'
            . '</section>'
        );

        $root = $fragment->children()[0];
        $children = $root->children;
        $link = $children[0];
        $image = $children[1];
        $quote = $children[2];
        $div = $children[3];
        $video = $children[4];
        $source = $video->children[0];
        $html = $fragment->serializeHtml();

        $t->same(['section', 'a', 'img', 'blockquote', 'div', 'video', 'source'], $fragment->elementNames());
        $t->same('TrackedQuoteMedia', $fragment->textContent());
        $t->same('<section><a href="/safe">Tracked</a><img src="/media/fallback.png" alt="Fallback"><blockquote>Quote</blockquote><div longdesc="https://example.test/review">Media</div><video><source src="/media/video.mp4" srcset="/media/video.mp4 1x"></video></section>', $html);
        $t->same(['href' => '/safe'], $link->attr('attributes'));
        $t->same(['src' => '/media/fallback.png', 'alt' => 'Fallback'], $image->attr('attributes'));
        $t->same([], $quote->attr('attributes'));
        $t->same(['longdesc' => 'https://example.test/review'], $div->attr('attributes'));
        $t->same([], $video->attr('attributes'));
        $t->same(['src' => '/media/video.mp4', 'srcset' => '/media/video.mp4 1x'], $source->attr('attributes'));
        $t->same([
            ['code' => 'dropped-side-effect-attribute', 'element' => 'a', 'attribute' => 'ping'],
            ['code' => 'dropped-unsafe-url', 'element' => 'img', 'attribute' => 'srcset'],
            ['code' => 'dropped-unsafe-url', 'element' => 'blockquote', 'attribute' => 'cite'],
            ['code' => 'dropped-unsafe-url', 'element' => 'div', 'attribute' => 'background'],
            ['code' => 'dropped-unsafe-url', 'element' => 'video', 'attribute' => 'poster'],
        ], $fragment->diagnostics());
        $t->true(!str_contains($html, 'ping='), 'Expected ping side-effect URLs to be stripped from compact fragments');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe URL schemes to be stripped from compact fragments');
        $t->true(!str_contains($html, 'vbscript:'), 'Expected legacy script URL schemes to be stripped from compact fragments');
        $t->true(!str_contains($html, 'data:text/html'), 'Expected active data URL candidates to be stripped from compact fragments');
    },
];
