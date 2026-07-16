<?php

declare(strict_types=1);

use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\XmlHtmlDomFragment;

return [
    'recovers declaration-looking raw text in html fragment facades' => static function (TestRunner $t): void {
        $html = '<script>{"doctype":"<!DOCTYPE html>"}</script>'
            . '<style>body:before{content:"<!ENTITY reviewer SYSTEM file>"}</style>'
            . '<template><!DOCTYPE html><p>template</p></template>'
            . '<iframe><!DOCTYPE html></iframe>'
            . '<p>safe</p>';

        $html5Fragment = Html5DomFragment::fromHtml($html);
        $html5Summary = $html5Fragment->summary();

        $t->same('<p>template</p><p>safe</p>', $html5Fragment->serialize());
        $t->same('templatesafe', $html5Fragment->textContent());
        $t->same(2, $html5Summary['topLevelNodes']);
        $t->same(['p'], $html5Summary['elementNames']);
        $t->same(['iframe', 'script', 'style', 'template'], $html5Summary['blockedTags']);
        $t->same([], $html5Summary['filteredAttributes']);

        $fragment = XmlHtmlDomFragment::parseHtml($html);

        $t->same('<p>template</p><p>safe</p>', $fragment->serializeHtml());
        $t->same('templatesafe', $fragment->textContent());
        $t->same(['p', 'p'], $fragment->elementNames());
        $t->same([
            ['code' => 'dropped-active-element', 'element' => 'script'],
            ['code' => 'dropped-active-element', 'element' => 'style'],
            ['code' => 'unwrapped-template-element', 'element' => 'template'],
            ['code' => 'dropped-active-element', 'element' => 'iframe'],
        ], $fragment->diagnostics());
    },
    'keeps live declarations rejected outside protected html fragment text' => static function (TestRunner $t): void {
        $safeRawText = '<script>{"doctype":"<!DOCTYPE html>"}</script><p>safe</p>';

        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml($safeRawText . '<!ENTITY reviewer SYSTEM "file:///etc/passwd">'));
        $t->throws(InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseHtml($safeRawText . '<!DOCTYPE html>'));
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml($safeRawText . '<?review href=file?>'));
        $t->throws(InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseHtml($safeRawText . '<?review href=file?>'));
    },
];
