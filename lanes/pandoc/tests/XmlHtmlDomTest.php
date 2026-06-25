<?php

declare(strict_types=1);

use PortLibs\Pandoc\XmlHtmlDom;

return [
    'loads safe XML documents and rejects unrepresentable namespace sources' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(
            '<pkg xmlns="urn:packet"><item xml:lang="en">Review &amp; Import</item></pkg>',
            'review packet XML'
        );
        $root = $dom->documentElement;
        $item = $dom->getElementsByTagNameNS('urn:packet', 'item')->item(0);

        $t->true($root instanceof DOMElement);
        $t->same('pkg', $root->localName);
        $t->same('urn:packet', $root->namespaceURI);
        $t->true($item instanceof DOMElement);
        $t->same('en', $item instanceof DOMElement ? $item->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang') : null);
        $t->same('Review & Import', $item instanceof DOMElement ? $item->textContent : null);

        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument('<pkg><item></pkg>', 'broken XML'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument('<pkg><bad:item/></pkg>', 'unbound prefix XML'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument('<!DOCTYPE pkg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><pkg>&xxe;</pkg>', 'unsafe XML'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument('<?xml-stylesheet href="https://example.invalid/review.xsl"?><pkg><item>review</item></pkg>', 'stylesheet XML'));
    },
    'summarizes XML namespace collisions and default namespace transitions without reader parity claims' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<doc xmlns="urn:root" xmlns:a="urn:item-a" xmlns:b="urn:item-b" xmlns:rootAlias="urn:root" xmlns:attrA="urn:attr-a" xmlns:attrB="urn:attr-b" attrA:code="A0">
  <item attrA:code="A1" code="plain-root">Root item</item>
  <rootAlias:item>Root alias item</rootAlias:item>
  <a:item attrB:code="B1">A item</a:item>
  <group xmlns="urn:group" attrA:code="A2">
    <item attrB:code="B2">Group item</item>
    <item xmlns="" code="plain-reset">Reset item</item>
    <alias-scope xmlns:a="urn:item-b"><a:item attrB:code="B4">Scoped prefix item</a:item></alias-scope>
  </group>
  <b:item attrA:code="A3" attrB:code="B3">B item</b:item>
</doc>
XML, 'XML namespace collision packet', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeXmlNamespaceUsage($dom);
        $prefixFrequencies = [];
        foreach ($packet['namespacePrefixFrequencies'] as $row) {
            $prefixFrequencies[$row['prefix']] = $row;
        }
        $uriFrequencies = [];
        foreach ($packet['namespaceUriFrequencies'] as $row) {
            $uriFrequencies[$row['namespaceUri']] = $row;
        }
        $sameUriAliases = [];
        foreach ($packet['sameUriMultiplePrefixes'] as $alias) {
            $sameUriAliases[$alias['namespaceUri']] = $alias;
        }
        $samePrefixAliases = [];
        foreach ($packet['samePrefixMultipleUris'] as $alias) {
            $samePrefixAliases[$alias['prefix']] = $alias;
        }

        $t->same('xml-html5-generic-dom', $packet['formatFamily']);
        $t->same('xml-namespace-usage-diagnostics-review-only', $packet['reviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same([
            'direct-reader-unsupported',
            'element-local-name-namespace-collisions',
            'attribute-local-name-namespace-collisions',
            'default-namespace-transitions',
            'default-namespace-usage',
            'namespace-uri-multiple-prefixes',
            'namespace-prefix-multiple-uris',
        ], $packet['directReaderDiagnosticCodes']);
        $t->same(7, $packet['directReaderDiagnosticCount']);
        $t->same(false, $packet['directReaderDiagnostics'][0]['directReaderParity'] ?? null);
        $t->same(true, $packet['directReaderDiagnostics'][1]['coveredByPacket'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][1]['details']['collisionCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][2]['details']['collisionCount'] ?? null);
        $t->same(3, $packet['directReaderDiagnostics'][3]['details']['transitionCount'] ?? null);
        $t->same(5, $packet['directReaderDiagnostics'][4]['details']['useCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][5]['details']['aliasCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][6]['details']['aliasCount'] ?? null);

        $t->same('doc', $packet['rootName']);
        $t->same('doc', $packet['rootQualifiedName']);
        $t->same('urn:root', $packet['rootNamespaceUri']);
        $t->same(10, $packet['elementCount']);
        $t->same(10, $packet['attributeCount']);
        $t->same(9, $packet['namespaceDeclarationCount']);
        $t->same([
            'urn:attr-a',
            'urn:attr-b',
            'urn:group',
            'urn:item-a',
            'urn:item-b',
            'urn:root',
        ], $packet['namespaceUris']);
        $t->same(6, $packet['namespaceUriCount']);
        $t->same(7, $packet['namespacePrefixFrequencyCount']);
        $t->same(7, $packet['namespaceUriFrequencyCount']);
        $t->same(['urn:item-a', 'urn:item-b'], $prefixFrequencies['a']['namespaceUris'] ?? null);
        $t->same(2, $prefixFrequencies['a']['useCount'] ?? null);
        $t->same(['urn:group', 'urn:root'], $prefixFrequencies['default']['namespaceUris'] ?? null);
        $t->same(5, $prefixFrequencies['default']['useCount'] ?? null);
        $t->same(['urn:attr-a'], $prefixFrequencies['attrA']['namespaceUris'] ?? null);
        $t->same(4, $prefixFrequencies['attrA']['attributeUseCount'] ?? null);
        $t->same(['default', 'rootAlias'], $uriFrequencies['urn:root']['prefixes'] ?? null);
        $t->same(3, $uriFrequencies['urn:root']['useCount'] ?? null);
        $t->same(['a', 'b'], $uriFrequencies['urn:item-b']['prefixes'] ?? null);
        $t->same(['none'], $uriFrequencies['']['prefixes'] ?? null);
        $t->same(3, $uriFrequencies['']['useCount'] ?? null);
        $t->same(['a', 'b'], $sameUriAliases['urn:item-b']['prefixes'] ?? null);
        $t->same(['default', 'rootAlias'], $sameUriAliases['urn:root']['prefixes'] ?? null);
        $t->same(['urn:item-a', 'urn:item-b'], $samePrefixAliases['a']['namespaceUris'] ?? null);
        $t->same(['urn:group', 'urn:root'], $samePrefixAliases['default']['namespaceUris'] ?? null);

        $t->same(1, $packet['elementNamespaceCollisionCount']);
        $elementCollision = $packet['elementNamespaceCollisions'][0] ?? [];
        $t->same('item', $elementCollision['localName'] ?? null);
        $t->same([
            '',
            'urn:group',
            'urn:item-a',
            'urn:item-b',
            'urn:root',
        ], $elementCollision['namespaceUris'] ?? null);
        $t->same(5, $elementCollision['namespaceCount'] ?? null);
        $t->same(7, $elementCollision['useCount'] ?? null);
        $t->same(['a:item', 'b:item', 'item', 'rootAlias:item'], $elementCollision['qualifiedNames'] ?? null);
        $t->same([
            ['namespaceUri' => '', 'useCount' => 1, 'qualifiedNames' => ['item']],
            ['namespaceUri' => 'urn:group', 'useCount' => 1, 'qualifiedNames' => ['item']],
            ['namespaceUri' => 'urn:item-a', 'useCount' => 1, 'qualifiedNames' => ['a:item']],
            ['namespaceUri' => 'urn:item-b', 'useCount' => 2, 'qualifiedNames' => ['a:item', 'b:item']],
            ['namespaceUri' => 'urn:root', 'useCount' => 2, 'qualifiedNames' => ['item', 'rootAlias:item']],
        ], $elementCollision['namespaceUses'] ?? null);

        $t->same(1, $packet['attributeNamespaceCollisionCount']);
        $attributeCollision = $packet['attributeNamespaceCollisions'][0] ?? [];
        $t->same('code', $attributeCollision['localName'] ?? null);
        $t->same(['', 'urn:attr-a', 'urn:attr-b'], $attributeCollision['namespaceUris'] ?? null);
        $t->same(3, $attributeCollision['namespaceCount'] ?? null);
        $t->same(10, $attributeCollision['useCount'] ?? null);
        $t->same(['attrA:code', 'attrB:code', 'code'], $attributeCollision['qualifiedNames'] ?? null);
        $t->same([
            ['namespaceUri' => '', 'useCount' => 2, 'qualifiedNames' => ['code']],
            ['namespaceUri' => 'urn:attr-a', 'useCount' => 4, 'qualifiedNames' => ['attrA:code']],
            ['namespaceUri' => 'urn:attr-b', 'useCount' => 4, 'qualifiedNames' => ['attrB:code']],
        ], $attributeCollision['namespaceUses'] ?? null);

        $t->same(5, $packet['defaultNamespaceUseCount']);
        $t->same(['urn:group', 'urn:root'], $packet['defaultNamespaceUris']);
        $t->same(2, $packet['defaultNamespaceUriCount']);
        $t->same(3, $packet['defaultNamespaceTransitionCount']);
        $t->same([
            [
                'path' => '/doc[1]',
                'element' => 'doc',
                'fromNamespaceUri' => null,
                'toNamespaceUri' => 'urn:root',
            ],
            [
                'path' => '/doc[1]/group[1]',
                'element' => 'group',
                'fromNamespaceUri' => 'urn:root',
                'toNamespaceUri' => 'urn:group',
            ],
            [
                'path' => '/doc[1]/group[1]/item[2]',
                'element' => 'item',
                'fromNamespaceUri' => 'urn:group',
                'toNamespaceUri' => null,
            ],
        ], $packet['defaultNamespaceTransitions']);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
];
