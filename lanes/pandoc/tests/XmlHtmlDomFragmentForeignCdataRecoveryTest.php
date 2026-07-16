<?php

declare(strict_types=1);

use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\XmlHtmlDom;
use PortLibs\Pandoc\XmlHtmlDomFragment;

return [
    'recovers declaration-looking foreign html cdata in legacy html fragments' => static function (TestRunner $t): void {
        $svgPayload = '<!DOCTYPE svg><?review href=file?><source>';
        $mathPayload = '<!ENTITY reviewer SYSTEM file>';
        $html = '<svg><desc><![CDATA[' . $svgPayload . ']]></desc></svg>'
            . '<math><annotation encoding="application/x-tex"><![CDATA[' . $mathPayload . ']]></annotation></math>'
            . '<p>safe</p>';

        $fragment = XmlHtmlDomFragment::parseHtml($html);

        $t->same($svgPayload . $mathPayload . 'safe', $fragment->textContent());
        $t->same(['svg', 'desc', 'math', 'annotation', 'p'], $fragment->elementNames());
        $t->same([], $fragment->diagnostics());
        $t->same(
            '<svg><desc>&lt;!DOCTYPE svg&gt;&lt;?review href=file?&gt;&lt;source&gt;</desc></svg>'
                . '<math><annotation encoding="application/x-tex">&lt;!ENTITY reviewer SYSTEM file&gt;</annotation></math>'
                . '<p>safe</p>',
            $fragment->serializeHtml()
        );

        $sharedDom = XmlHtmlDom::loadHtmlFragment($html, 'foreign CDATA declaration HTML fragment');
        $html5Fragment = Html5DomFragment::fromHtml($html);

        $t->contains('&lt;!DOCTYPE svg&gt;', XmlHtmlDom::serializeHtmlFragment($sharedDom));
        $t->contains('&lt;?review href=file?&gt;', $html5Fragment->serialize());
    },
    'keeps live declarations rejected outside foreign html cdata' => static function (TestRunner $t): void {
        $safeForeignCdata = '<svg><desc><![CDATA[<!DOCTYPE svg><?review href=file?>]]></desc></svg>';

        $t->throws(InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseHtml($safeForeignCdata . '<!DOCTYPE html>'));
        $t->throws(InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseHtml($safeForeignCdata . '<?review href=file?>'));
        $t->throws(InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseHtml('<svg><desc><![CDATA[<!DOCTYPE svg><?review href=file?</desc></svg>'));
    },
];
