<?php

declare(strict_types=1);

use PortLibs\Pandoc\Html5Dom;
use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\XmlHtml5Dom;
use PortLibs\Pandoc\XmlHtmlDom;
use PortLibs\Pandoc\XmlHtmlDomFragment;

return [
    'recovers declaration-looking XML CDATA text across DOM loaders' => static function (TestRunner $t): void {
        $payload = '<!DOCTYPE pkg [<!ENTITY reviewer SYSTEM "file:///etc/passwd">]><?review href="file"?>';
        $xml = '<pkg><payload><![CDATA[' . $payload . ']]></payload><item>safe</item></pkg>';

        $legacy = XmlHtmlDom::loadXmlDocument($xml, 'CDATA declaration XML document', preserveWhiteSpace: false);
        $legacyRoot = XmlHtmlDom::rootElement($legacy, 'pkg');
        $legacyPayload = $legacyRoot instanceof DOMElement ? XmlHtmlDom::firstChildElement($legacyRoot, 'payload') : null;
        $legacyItem = $legacyRoot instanceof DOMElement ? XmlHtmlDom::firstChildElement($legacyRoot, 'item') : null;

        $t->true($legacyRoot instanceof DOMElement);
        $t->true($legacyPayload instanceof DOMElement);
        $t->same($payload, $legacyPayload instanceof DOMElement ? $legacyPayload->textContent : null);
        $t->same('safe', $legacyItem instanceof DOMElement ? $legacyItem->textContent : null);

        $facade = XmlHtml5Dom::parseXmlDocument($xml, 'facade CDATA declaration XML document');
        $facadePayload = $facade->getElementsByTagName('payload')->item(0);

        $t->true($facadePayload instanceof DOMElement);
        $t->same($payload, $facadePayload instanceof DOMElement ? $facadePayload->textContent : null);

        $fragment = Html5Dom::parseXmlFragment(
            '<record><![CDATA[' . $payload . ']]></record><!-- <?review href="file"?> -->'
        );
        $record = Html5Dom::firstChildElement($fragment, 'record');
        $serialized = Html5Dom::serializeXmlChildren($fragment);

        $t->true($record instanceof DOMElement);
        $t->same($payload, $record instanceof DOMElement ? $record->textContent : null);
        $t->contains('<![CDATA[' . $payload . ']]>', $serialized);
        $t->contains('<!-- <?review href="file"?> -->', $serialized);

        $legacyFragment = XmlHtmlDomFragment::parseXml('<pkg><payload><![CDATA[' . $payload . ']]></payload></pkg>');
        $html5Fragment = Html5DomFragment::fromXml('<pkg><payload><![CDATA[' . $payload . ']]></payload></pkg>');

        $t->same($payload, $legacyFragment->textContent());
        $t->same($payload, $html5Fragment->textContent());
        $t->contains('&lt;!DOCTYPE pkg', $legacyFragment->serializeXml());
        $t->contains('&lt;?review href="file"?&gt;', $html5Fragment->serialize());
        $t->same([], $legacyFragment->diagnostics());
        $t->same([], $html5Fragment->diagnostics());
    },
    'keeps unsafe live XML declarations bounded outside closed CDATA sections' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument('<!DOCTYPE pkg [<!ENTITY reviewer "safe reviewer">]><pkg>&reviewer;</pkg>', 'live internal entity XML document');
        $t->same('safe reviewer', $dom->documentElement instanceof DOMElement ? $dom->documentElement->textContent : null);
        $piDom = XmlHtmlDom::loadXmlDocument('<pkg><?review href="file"?><item>safe</item></pkg>', 'live PI XML document');
        $t->same('safe', $piDom->documentElement instanceof DOMElement ? $piDom->documentElement->textContent : null);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument('<!DOCTYPE pkg [<!ENTITY reviewer SYSTEM "file:///etc/passwd">]><pkg>&reviewer;</pkg>', 'live external entity XML document'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<pkg><![CDATA[<!DOCTYPE pkg</pkg>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseXmlDocument('<pkg><?review href="file"?></pkg>'));
        $t->throws(RuntimeException::class, static fn (): DOMDocument => Html5Dom::parseXmlDocument('<pkg><![CDATA[<?review href="file"</pkg>'));
        $facadePiDom = XmlHtml5Dom::parseXmlDocument('<?review href="file"?><pkg><item>safe</item></pkg>', 'live facade PI XML document');
        $t->same('safe', $facadePiDom->documentElement instanceof DOMElement ? $facadePiDom->documentElement->textContent : null);
        $t->throws(InvalidArgumentException::class, static fn (): XmlHtmlDomFragment => XmlHtmlDomFragment::parseXml('<pkg><![CDATA[<!ENTITY reviewer SYSTEM "file:///etc/passwd"</pkg>'));
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromXml('<pkg><![CDATA[<?review href="file"</pkg>'));
    },
];
