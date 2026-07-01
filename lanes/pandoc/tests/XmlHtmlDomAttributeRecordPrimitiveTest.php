<?php

declare(strict_types=1);

use PortLibs\Pandoc\XmlHtmlDom;

return [
    'exposes sorted xml attribute records with namespace provenance' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<pkg:package xmlns:pkg="urn:pkg" xmlns:rel="urn:rel" xmlns:w="urn:word" xml:lang="en" w:id="rId1" rel:target="/word/document.xml" plain="review">
  <w:child rel:id="rId2"/>
</pkg:package>
XML, 'XML attribute record primitive packet', preserveWhiteSpace: false);
        $root = $dom->documentElement;

        $t->true($root instanceof DOMElement);
        $records = $root instanceof DOMElement ? XmlHtmlDom::xmlAttributeRecords($root) : [];
        $attributes = $root instanceof DOMElement ? XmlHtmlDom::xmlAttributes($root) : [];

        $t->same(['plain', 'rel:target', 'w:id', 'xml:lang'], array_column($records, 'qualifiedName'));
        $t->same([
            'plain' => 'review',
            'rel:target' => '/word/document.xml',
            'w:id' => 'rId1',
            'xml:lang' => 'en',
        ], $attributes);

        $plain = $records[0];
        $relTarget = $records[1];
        $wordId = $records[2];
        $xmlLang = $records[3];

        $t->same('plain', $plain['localName']);
        $t->same(null, $plain['prefix']);
        $t->same(null, $plain['namespaceUri']);
        $t->same(false, $plain['namespaced']);
        $t->same(false, $plain['namespaceDeclaration']);

        $t->same('target', $relTarget['localName']);
        $t->same('rel', $relTarget['prefix']);
        $t->same('urn:rel', $relTarget['namespaceUri']);
        $t->same('/word/document.xml', $relTarget['value']);
        $t->same(true, $relTarget['namespaced']);

        $t->same('id', $wordId['localName']);
        $t->same('w', $wordId['prefix']);
        $t->same('urn:word', $wordId['namespaceUri']);
        $t->same('rId1', $wordId['value']);

        $t->same('lang', $xmlLang['localName']);
        $t->same('xml', $xmlLang['prefix']);
        $t->same('http://www.w3.org/XML/1998/namespace', $xmlLang['namespaceUri']);
        $t->same('en', $xmlLang['value']);

        $child = $root instanceof DOMElement ? XmlHtmlDom::firstDescendantElement($root, 'child', 'urn:word') : null;
        $t->true($child instanceof DOMElement);
        $t->same(['rel:id' => 'rId2'], $child instanceof DOMElement ? XmlHtmlDom::xmlAttributes($child) : []);

        json_encode($records, JSON_THROW_ON_ERROR);
    },
];
