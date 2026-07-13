<?php

declare(strict_types=1);

use PortLibs\Pandoc\Html5Dom;
use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\XmlHtmlDom;
use PortLibs\Pandoc\XmlHtmlDomFragment;

return [
    'rejects live declarations after comment and attribute raw-text decoys' => static function (TestRunner $t): void {
        $commentDecoy = '<!-- <script> --> <!DOCTYPE html><p>safe</p>';
        $attributeDecoy = '<p data-probe="<script>">safe</p><!DOCTYPE html>';

        $t->throws(InvalidArgumentException::class, static fn () => XmlHtmlDom::loadHtmlFragment($commentDecoy, 'comment decoy HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn () => Html5Dom::parseHtmlFragment($commentDecoy));
        $t->throws(InvalidArgumentException::class, static fn () => Html5DomFragment::fromHtml($commentDecoy));
        $t->throws(InvalidArgumentException::class, static fn () => XmlHtmlDomFragment::parseHtml($commentDecoy));
        $t->throws(InvalidArgumentException::class, static fn () => XmlHtmlDom::loadHtmlFragment($attributeDecoy, 'attribute decoy HTML fragment'));
    },
    'keeps declaration-looking raw-text content inert' => static function (TestRunner $t): void {
        $html = '<script>const declaration = "<!DOCTYPE html>";</script><p>safe</p>';

        $dom = XmlHtmlDom::loadHtmlFragment($html, 'raw text declaration HTML fragment');
        $modern = Html5DomFragment::fromHtml($html);
        $legacy = XmlHtmlDomFragment::parseHtml($html);

        $t->contains('<p>safe</p>', XmlHtmlDom::serializeHtmlFragment($dom));
        $t->same('<p>safe</p>', $modern->serialize());
        $t->same('<p>safe</p>', $legacy->serializeHtml());
    },
];
