<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'loads safe XML documents and preserves namespace attributes' => static function (TestRunner $t): void {
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
        $t->same('en', $item->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        $t->same('Review & Import', $item->textContent);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument('<pkg><item></pkg>', 'broken XML'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument('<!DOCTYPE pkg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><pkg>&xxe;</pkg>', 'unsafe XML'));
    },
    'allows XML declarations but rejects XML processing instructions' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(
            '<?xml version="1.0" encoding="UTF-8"?><pkg><item>Review packet</item></pkg>',
            'declared review packet XML',
            preserveWhiteSpace: false
        );

        $t->true($dom->documentElement instanceof DOMElement);
        $t->same('pkg', $dom->documentElement->tagName);
        $t->same('Review packet', $dom->documentElement->textContent);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument(
            '<?xml-stylesheet href="https://example.invalid/review.xsl"?><pkg><item>review</item></pkg>',
            'stylesheet XML'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadXmlDocument(
            '<?xml version="1.0"?><pkg><?review href="file:///etc/passwd"?><item>review</item></pkg>',
            'review PI XML'
        ));
    },
    'queries namespaced XML DOM nodes for package reader handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<pkg:package xmlns:pkg="urn:pkg" xmlns:w="urn:word" xmlns:rel="urn:relationship" rel:id="root">
  <pkg:metadata>
    <w:title xml:lang="en">  Review
      Packet </w:title>
    <w:title xml:lang="fr">Ignored</w:title>
  </pkg:metadata>
  <pkg:body>
    <w:p rel:id="rId1"> First <w:r> run </w:r></w:p>
    <pkg:p>Package paragraph</pkg:p>
  </pkg:body>
</pkg:package>
XML, 'package reader XML');
        $root = XmlHtmlDom::rootElement($dom, 'package', 'urn:pkg');
        $metadata = $root instanceof DOMElement ? XmlHtmlDom::firstChildElement($root, 'metadata', 'urn:pkg') : null;
        $body = $root instanceof DOMElement ? XmlHtmlDom::firstChildElement($root, 'body', 'urn:pkg') : null;
        $titles = $root instanceof DOMElement ? XmlHtmlDom::descendantElements($root, 'title', 'urn:word') : [];
        $paragraph = $body instanceof DOMElement ? XmlHtmlDom::firstDescendantElement($body, 'p', 'urn:word') : null;

        $t->true($root instanceof DOMElement);
        $t->true(XmlHtmlDom::elementMatches($root, 'package', 'urn:pkg'));
        $t->true(XmlHtmlDom::elementMatches($root, null, 'urn:pkg'));
        $t->true(!XmlHtmlDom::elementMatches($root, 'package', 'urn:word'));
        $t->same($root, XmlHtmlDom::rootElement($dom, null, 'urn:pkg'));
        $t->same(null, XmlHtmlDom::rootElement($dom, 'package', 'urn:word'));
        $t->true($metadata instanceof DOMElement);
        $t->true($body instanceof DOMElement);
        $t->same(2, count($titles));
        $t->same('Review Packet', XmlHtmlDom::normalizedText($titles[0]));
        $t->same('en', XmlHtmlDom::attribute($titles[0], 'lang', 'http://www.w3.org/XML/1998/namespace'));
        $t->same('root', XmlHtmlDom::attribute($root, 'id', 'urn:relationship'));
        $t->same(null, XmlHtmlDom::attribute($root, 'missing', 'urn:relationship'));
        $t->same(0, count($root instanceof DOMElement ? XmlHtmlDom::childElements($root, 'p', 'urn:word') : []));
        $t->true($paragraph instanceof DOMElement);
        $t->same('rId1', $paragraph instanceof DOMElement ? XmlHtmlDom::attribute($paragraph, 'id', 'urn:relationship') : null);
        $t->same('First run', $paragraph instanceof DOMElement ? XmlHtmlDom::normalizedText($paragraph) : null);
    },
    'summarizes XML namespace declaration provenance without reader parity claims' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<pkg:package xmlns="urn:packet" xmlns:pkg="urn:pkg" xmlns:rel="urn:relationship" rel:id="root">
  <pkg:metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:title>Namespace Review</dc:title>
  </pkg:metadata>
  <pkg:body xmlns:rel="urn:relationship-v2">
    <pkg:item rel:id="local">Payload</pkg:item>
  </pkg:body>
</pkg:package>
XML, 'namespace declaration review XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeXmlNamespaceDeclarations($dom);

        $t->same('xml-html5-jats-dom', $packet['formatFamily']);
        $t->same('xml', $packet['format']);
        $t->same('xml-namespace-declaration-review-only', $packet['reviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same([
            'direct-reader-unsupported',
            'namespace-declarations-review-only',
            'namespace-prefix-reuse-review-only',
            'namespace-prefix-conflict-review-only',
        ], $packet['directReaderDiagnosticCodes']);
        $t->same(4, $packet['directReaderDiagnosticCount']);
        $t->same(false, $packet['directReaderDiagnostics'][0]['directReaderParity'] ?? null);
        $t->same(true, $packet['directReaderDiagnostics'][1]['coveredByPacket'] ?? null);
        $t->same(5, $packet['directReaderDiagnostics'][1]['details']['namespaceDeclarationCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][2]['details']['namespacePrefixReuseCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][3]['details']['namespacePrefixConflictCount'] ?? null);

        $t->same('package', $packet['rootName']);
        $t->same('pkg:package', $packet['rootQualifiedName']);
        $t->same('pkg', $packet['rootNamespacePrefix']);
        $t->same('urn:pkg', $packet['rootNamespaceUri']);
        $t->same(5, $packet['elementCount']);
        $t->same(2, $packet['maxDepth']);
        $t->same(5, $packet['namespacedElementCount']);
        $t->same(2, $packet['namespacedAttributeCount']);
        $t->same(5, $packet['namespaceDeclarationCount']);
        $t->same(4, $packet['namespacePrefixCount']);
        $t->same(5, $packet['namespaceUriCount']);
        $t->same(['', 'dc', 'pkg', 'rel'], $packet['namespacePrefixes']);
        $t->same([
            'http://purl.org/dc/elements/1.1/',
            'urn:packet',
            'urn:pkg',
            'urn:relationship',
            'urn:relationship-v2',
        ], $packet['namespaceUris']);

        $t->same(['urn:packet'], $packet['namespaceDeclarationsByPrefix']['']['namespaceUris'] ?? null);
        $t->same(['/pkg:package'], $packet['namespaceDeclarationsByPrefix']['pkg']['elementPaths'] ?? null);
        $t->same(2, $packet['namespaceDeclarationsByPrefix']['rel']['declarationCount'] ?? null);
        $t->same([
            'urn:relationship',
            'urn:relationship-v2',
        ], $packet['namespaceDeclarationsByPrefix']['rel']['namespaceUris'] ?? null);
        $t->same([
            '/pkg:package',
            '/pkg:package/pkg:body[1]',
        ], $packet['namespaceDeclarationsByPrefix']['rel']['elementPaths'] ?? null);
        $t->same(1, $packet['namespacePrefixReuseCount']);
        $t->same('rel', $packet['namespacePrefixReuses'][0]['prefix'] ?? null);
        $t->same(1, $packet['namespacePrefixConflictCount']);
        $t->same(true, $packet['hasNamespacePrefixConflicts']);
        $t->same('rel', $packet['namespacePrefixConflicts'][0]['prefix'] ?? null);
        $t->same(false, $packet['namespacePrefixConflicts'][0]['defaultNamespace'] ?? null);
        $t->same(2, $packet['namespacePrefixConflicts'][0]['declarationCount'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes XML namespace scope collisions for direct reader review' => static function (TestRunner $t): void {
        $xml = <<<'XML'
<root xmlns="urn:root" xmlns:p="urn:one" xmlns:a="urn:shared">
  <p:section xmlns="urn:section" xmlns:p="urn:two" xmlns:q="urn:two" xmlns:b="urn:shared">
    <item p:attr="value"/>
  </p:section>
  <plain xmlns="">No namespace</plain>
</root>
XML;
        $dom = XmlHtmlDom::loadXmlDocument($xml, 'namespace scope XML', preserveWhiteSpace: false);
        $summary = XmlHtmlDom::summarizeXmlNamespaceScopes($dom, $xml);

        $t->same('xml-namespace-scope', $summary['namespaceReview']);
        $t->same(false, $summary['directReaderParity']);
        $t->same('provided-source', $summary['sourceMode']);
        $t->same('root', $summary['rootName']);
        $t->same('urn:root', $summary['rootNamespaceUri']);
        $t->same(['a', 'b', 'p', 'q', 'xml'], $summary['namespacePrefixes']);
        $t->same(8, $summary['namespaceDeclarationCount']);
        $t->same(3, $summary['defaultNamespaceDeclarationCount']);
        $t->same(4, $summary['namespaceScopeElementCount']);
        $t->same(3, $summary['defaultNamespaceScopeCount']);
        $t->same(false, $summary['namespaceScopeTruncated']);
        $t->same(['prefix-redefinition', 'duplicate-prefix-declarations', 'duplicate-uri-bindings'], $summary['namespaceDiagnosticCodes']);
        $t->same(3, $summary['namespaceDiagnosticCount']);
        $t->same(2, $summary['prefixRedefinitionCount']);
        $t->same(['default', 'p'], array_map(static fn (array $item): string => (string) $item['prefix'], $summary['prefixRedefinitions']));
        $t->same(2, $summary['duplicatePrefixSummaryCount']);
        $t->same(2, $summary['duplicateUriSummaryCount']);
        $t->same(['a', 'b'], $summary['duplicateUriSummaries'][0]['prefixes'] ?? null);
        $t->same(['p', 'q'], $summary['duplicateUriSummaries'][1]['prefixes'] ?? null);
        $t->same('root[1]/p:section[1]', $summary['namespaceScopes'][1]['elementPath'] ?? null);
        $t->same('urn:section', $summary['namespaceScopes'][1]['defaultNamespaceUri'] ?? null);
        $t->same('urn:two', $summary['namespaceScopes'][1]['prefixBindings']['p'] ?? null);
        $t->same('urn:two', $summary['namespaceScopes'][2]['prefixBindings']['p'] ?? null);
        $t->same(null, $summary['namespaceScopes'][3]['defaultNamespaceUri'] ?? null);
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
    'diagnoses reserved XML namespace declaration misuse without reader parity claims' => static function (TestRunner $t): void {
        $xml = <<<'XML'
<root xmlns="http://www.w3.org/XML/1998/namespace" xmlns:xml="urn:not-xml" xmlns:bad="http://www.w3.org/XML/1998/namespace" xmlns:xmlns="urn:not-xmlns" xmlns:z="http://www.w3.org/2000/xmlns/">
  <child/>
</root>
XML;
        $dom = XmlHtmlDom::loadXmlDocument($xml, 'reserved namespace misuse XML', preserveWhiteSpace: false);
        $summary = XmlHtmlDom::summarizeXmlNamespaceScopes($dom, $xml);
        $codes = implode(';', $summary['namespaceDiagnosticCodes']);

        $t->same(false, $summary['directReaderParity']);
        $t->same(5, $summary['namespaceDeclarationCount']);
        $t->same(5, $summary['reservedNamespaceDiagnosticCount']);
        $t->contains('reserved-xml-prefix-rebound', $codes);
        $t->contains('reserved-xml-uri-as-default', $codes);
        $t->contains('reserved-xml-uri-bound-to-non-xml-prefix', $codes);
        $t->contains('reserved-xmlns-prefix-declared', $codes);
        $t->contains('reserved-xmlns-uri-declared', $codes);
        $t->same(false, $summary['namespaceDiagnostics'][0]['directReaderParity'] ?? null);
        json_encode($summary, JSON_THROW_ON_ERROR);
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
        foreach ($packet['namespacePrefixFrequencies'] as $frequency) {
            $prefixFrequencies[$frequency['prefix']] = $frequency;
        }
        $prefixFrequencySummaries = [];
        foreach ($packet['namespacePrefixFrequencySummaries'] as $frequency) {
            $prefixFrequencySummaries[$frequency['prefix']] = $frequency;
        }
        $uriFrequencies = [];
        foreach ($packet['namespaceUriFrequencies'] as $frequency) {
            $uriFrequencies[$frequency['namespaceUri']] = $frequency;
        }
        $uriFrequencySummaries = [];
        foreach ($packet['namespaceUriFrequencySummaries'] as $frequency) {
            $uriFrequencySummaries[$frequency['namespaceUri']] = $frequency;
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
        $t->same(7, $packet['namespacePrefixFrequencySummaryCount']);
        $t->same(7, $packet['namespaceUriFrequencyCount']);
        $t->same(7, $packet['namespaceUriFrequencySummaryCount']);
        $t->same(5, $packet['defaultNamespaceUseCount']);
        $t->same(['urn:group', 'urn:root'], $packet['defaultNamespaceUris']);
        $t->same(2, $packet['defaultNamespaceUriCount']);
        $t->same('default', $packet['namespacePrefixFrequencySummaries'][0]['prefix'] ?? null);
        $t->same(6, $packet['namespacePrefixFrequencySummaries'][0]['useCount'] ?? null);
        $t->same('urn:attr-a', $packet['namespaceUriFrequencySummaries'][0]['namespaceUri'] ?? null);
        $t->same(4, $packet['namespaceUriFrequencySummaries'][0]['useCount'] ?? null);
        $t->same(['urn:item-a', 'urn:item-b'], $prefixFrequencies['a']['namespaceUris'] ?? null);
        $t->same(2, $prefixFrequencies['a']['namespaceUriCount'] ?? null);
        $t->same(2, $prefixFrequencies['a']['useCount'] ?? null);
        $t->same(['a:item'], $prefixFrequencies['a']['qualifiedNames'] ?? null);
        $t->same(2, $prefixFrequencySummaries['a']['useCount'] ?? null);
        $t->same(['', 'urn:group', 'urn:root'], $prefixFrequencies['default']['namespaceUris'] ?? null);
        $t->same(6, $prefixFrequencies['default']['useCount'] ?? null);
        $t->same(['alias-scope', 'doc', 'group', 'item'], $prefixFrequencies['default']['qualifiedNames'] ?? null);
        $t->same(['urn:attr-a'], $prefixFrequencies['attrA']['namespaceUris'] ?? null);
        $t->same(4, $prefixFrequencies['attrA']['attributeUseCount'] ?? null);
        $t->same(['attrA:code'], $prefixFrequencies['attrA']['qualifiedNames'] ?? null);
        $t->same(['default', 'rootAlias'], $uriFrequencies['urn:root']['prefixes'] ?? null);
        $t->same(3, $uriFrequencies['urn:root']['useCount'] ?? null);
        $t->same(['doc', 'item', 'rootAlias:item'], $uriFrequencies['urn:root']['qualifiedNames'] ?? null);
        $t->same(['a', 'b'], $uriFrequencies['urn:item-b']['prefixes'] ?? null);
        $t->same(2, $uriFrequencies['urn:item-b']['useCount'] ?? null);
        $t->same(['default', 'none'], $uriFrequencies['']['prefixes'] ?? null);
        $t->same(3, $uriFrequencies['']['useCount'] ?? null);
        $t->same(3, $uriFrequencySummaries['urn:root']['useCount'] ?? null);
        $t->same(2, $packet['sameUriMultiplePrefixCount']);
        $t->same(['a', 'b'], $sameUriAliases['urn:item-b']['prefixes'] ?? null);
        $t->same(['default', 'rootAlias'], $sameUriAliases['urn:root']['prefixes'] ?? null);
        $t->same(2, $packet['samePrefixMultipleUriCount']);
        $t->same(['urn:item-a', 'urn:item-b'], $samePrefixAliases['a']['namespaceUris'] ?? null);
        $t->same(['', 'urn:group', 'urn:root'], $samePrefixAliases['default']['namespaceUris'] ?? null);

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
    'summarizes XML namespace usage counts and source diagnostics without reader parity claims' => static function (TestRunner $t): void {
        $xml = <<<'XML'
<doc xmlns="urn:root" xmlns:a="urn:item-a" xmlns:b="urn:item-b" xmlns:rootAlias="urn:root" xmlns:attrA="urn:attr-a" xmlns:attrB="urn:attr-b" xmlns:unused="urn:unused" attrA:code="A0" xml:lang="en">
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
XML;
        $dom = XmlHtmlDom::loadXmlDocument($xml, 'XML namespace usage source packet', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeXmlNamespaceUsage($dom, $xml);
        $elementUsage = [];
        foreach ($packet['elementNamespaceUsageSummaries'] as $summary) {
            $elementUsage[$summary['localName']] = $summary;
        }
        $attributeUsage = [];
        foreach ($packet['attributeNamespaceUsageSummaries'] as $summary) {
            $attributeUsage[$summary['localName']] = $summary;
        }

        $t->same(false, $packet['directReaderParity']);
        $t->same('xml-namespace-usage', $packet['namespaceReview']);
        $t->same('xml-namespace-scope', $packet['namespaceScopeReview']);
        $t->same('provided-source', $packet['namespaceUsageSourceMode']);
        $t->same([
            'direct-reader-unsupported',
            'element-local-name-namespace-collisions',
            'attribute-local-name-namespace-collisions',
            'default-namespace-transitions',
            'default-namespace-usage',
            'namespace-uri-multiple-prefixes',
            'namespace-prefix-multiple-uris',
            'unused-namespace-declarations',
            'reserved-namespace-usage',
        ], $packet['directReaderDiagnosticCodes']);
        $t->same(10, $packet['elementCount']);
        $t->same(11, $packet['attributeCount']);
        $t->same(9, $packet['namespacedElementUseCount']);
        $t->same(1, $packet['unnamespacedElementUseCount']);
        $t->same(9, $packet['namespacedAttributeUseCount']);
        $t->same(2, $packet['unnamespacedAttributeUseCount']);
        $t->same(10, $packet['namespaceDeclarationCount']);
        $t->same(['a', 'attrA', 'attrB', 'b', 'rootAlias', 'unused', 'xml'], $packet['declaredNamespacePrefixes']);
        $t->same(4, $packet['elementNamespaceUsageSummaryCount']);
        $t->same(2, $packet['attributeNamespaceUsageSummaryCount']);
        $t->same(['', 'urn:group', 'urn:item-a', 'urn:item-b', 'urn:root'], $elementUsage['item']['namespaceUris'] ?? null);
        $t->same(7, $elementUsage['item']['useCount'] ?? null);
        $t->same([
            ['namespaceUri' => '', 'useCount' => 1, 'qualifiedNames' => ['item']],
            ['namespaceUri' => 'urn:group', 'useCount' => 1, 'qualifiedNames' => ['item']],
            ['namespaceUri' => 'urn:item-a', 'useCount' => 1, 'qualifiedNames' => ['a:item']],
            ['namespaceUri' => 'urn:item-b', 'useCount' => 2, 'qualifiedNames' => ['a:item', 'b:item']],
            ['namespaceUri' => 'urn:root', 'useCount' => 2, 'qualifiedNames' => ['item', 'rootAlias:item']],
        ], $elementUsage['item']['namespaceUses'] ?? null);
        $t->same(['', 'urn:attr-a', 'urn:attr-b'], $attributeUsage['code']['namespaceUris'] ?? null);
        $t->same(10, $attributeUsage['code']['useCount'] ?? null);
        $t->same([
            ['namespaceUri' => '', 'useCount' => 2, 'qualifiedNames' => ['code']],
            ['namespaceUri' => 'urn:attr-a', 'useCount' => 4, 'qualifiedNames' => ['attrA:code']],
            ['namespaceUri' => 'urn:attr-b', 'useCount' => 4, 'qualifiedNames' => ['attrB:code']],
        ], $attributeUsage['code']['namespaceUses'] ?? null);
        $t->same(['http://www.w3.org/XML/1998/namespace'], $attributeUsage['lang']['namespaceUris'] ?? null);
        $t->same(['xml:lang'], $attributeUsage['lang']['qualifiedNames'] ?? null);
        $t->same(0, $packet['unboundNamespacePrefixUseCount']);
        $t->same([], $packet['unboundNamespacePrefixes']);
        $t->same(1, $packet['unusedNamespaceDeclarationCount']);
        $t->same('unused', $packet['unusedNamespaceDeclarations'][0]['prefixLabel'] ?? null);
        $t->same('urn:unused', $packet['unusedNamespaceDeclarations'][0]['uri'] ?? null);
        $t->same(0, $packet['unusedNamespaceDeclarations'][0]['usedCount'] ?? null);
        $t->same(1, $packet['reservedNamespaceUseCount']);
        $t->same(['reserved-xml-prefix-use'], $packet['reservedNamespaceUseCodes']);
        $t->same('xml:lang', $packet['reservedNamespaceUses'][0]['name'] ?? null);
        $t->same(false, $packet['reservedNamespaceUses'][0]['directReaderParity'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes unbound XML namespace prefix source diagnostics without parsing through validators' => static function (TestRunner $t): void {
        $summary = XmlHtmlDom::summarizeXmlNamespaceSourceUsage(
            '<doc xmlns="urn:root"><bad:item missing:code="1"/><xml:item xmlns:xmlns="urn:bad" xmlns:bad="http://www.w3.org/2000/xmlns/" bad:code="2"/></doc>'
        );

        $t->same('xml-namespace-source-usage-review-only', $summary['reviewPolicy']);
        $t->same(false, $summary['directReaderParity']);
        $t->same('provided-source', $summary['sourceMode']);
        $t->same(3, $summary['namespaceSourceElementUseCount']);
        $t->same(2, $summary['namespaceSourceAttributeUseCount']);
        $t->same(3, $summary['namespaceSourceDeclarationCount']);
        $t->same(2, $summary['unboundNamespacePrefixUseCount']);
        $t->same(['bad', 'missing'], $summary['unboundNamespacePrefixes']);
        $t->same('bad:item', $summary['unboundNamespacePrefixUses'][0]['name'] ?? null);
        $t->same('element', $summary['unboundNamespacePrefixUses'][0]['kind'] ?? null);
        $t->same('missing:code', $summary['unboundNamespacePrefixUses'][1]['name'] ?? null);
        $t->same('attribute', $summary['unboundNamespacePrefixUses'][1]['kind'] ?? null);
        $t->same(1, $summary['unusedNamespaceDeclarationCount']);
        $t->same('xmlns', $summary['unusedNamespaceDeclarations'][0]['prefixLabel'] ?? null);
        $t->same('urn:bad', $summary['unusedNamespaceDeclarations'][0]['uri'] ?? null);
        $t->same(2, $summary['reservedNamespaceUseCount']);
        $t->same(['reserved-xml-prefix-use', 'reserved-xmlns-uri-use'], $summary['reservedNamespaceUseCodes']);
        $t->same('xml:item', $summary['reservedNamespaceUses'][0]['name'] ?? null);
        $t->same('bad:code', $summary['reservedNamespaceUses'][1]['name'] ?? null);
        $t->same(false, $summary['reservedNamespaceUses'][1]['directReaderParity'] ?? null);
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
    'bounds XML namespace prefix and URI frequency summaries by highest use count' => static function (TestRunner $t): void {
        $namespaceDeclarations = [];
        $items = [];
        for ($index = 0; $index < 30; ++$index) {
            $prefix = 'p' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $namespaceDeclarations[] = 'xmlns:' . $prefix . '="urn:' . $prefix . '"';
            $items[] = '<' . $prefix . ':item ' . $prefix . ':code="' . $index . '"/>';
        }

        $dom = XmlHtmlDom::loadXmlDocument(
            '<doc xmlns="urn:doc" xmlns:hot="urn:hot" '
                . implode(' ', $namespaceDeclarations)
                . '><hot:a/><hot:b/><hot:c/><hot:d/>'
                . implode('', $items)
                . '</doc>',
            'XML namespace frequency packet',
            preserveWhiteSpace: false
        );
        $packet = XmlHtmlDom::summarizeXmlNamespaceUsage($dom);

        $t->same(25, $packet['namespacePrefixFrequencySummaryCount']);
        $t->same(25, count($packet['namespacePrefixFrequencySummaries']));
        $t->same(25, $packet['namespaceUriFrequencySummaryCount']);
        $t->same(25, count($packet['namespaceUriFrequencySummaries']));
        $t->same('hot', $packet['namespacePrefixFrequencySummaries'][0]['prefix'] ?? null);
        $t->same(4, $packet['namespacePrefixFrequencySummaries'][0]['useCount'] ?? null);
        $t->same(['urn:hot'], $packet['namespacePrefixFrequencySummaries'][0]['namespaceUris'] ?? null);
        $t->same('urn:hot', $packet['namespaceUriFrequencySummaries'][0]['namespaceUri'] ?? null);
        $t->same(4, $packet['namespaceUriFrequencySummaries'][0]['useCount'] ?? null);
        $t->same(['hot'], $packet['namespaceUriFrequencySummaries'][0]['prefixes'] ?? null);
        $t->same(false, $packet['directReaderParity']);
        $t->same('xml-namespace-usage-diagnostics-review-only', $packet['reviewPolicy']);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes jats and bits front matter plus body and back matter diagnostics without reader parity claims' => static function (TestRunner $t): void {
        $jats = XmlHtmlDom::loadXmlDocument(<<<'XML'
<jats:article xmlns:jats="http://jats.nlm.nih.gov" xmlns:xlink="http://www.w3.org/1999/xlink" id="article-root" xml:id="xml-root" article-type="research-article" dtd-version="1.3" xml:lang="en">
  <front>
    <journal-meta>
      <journal-title-group><journal-title>Journal &amp; Review</journal-title></journal-title-group>
      <publisher><publisher-name>Port Libs Press</publisher-name></publisher>
    </journal-meta>
    <article-meta>
      <article-id pub-id-type="doi">10.5555/review.42</article-id>
      <title-group>
        <article-title>Import <italic>Safety</italic> Study</article-title>
        <subtitle>Escaping &amp; attributes</subtitle>
      </title-group>
      <contrib-group>
        <contrib contrib-type="author"><name><surname>Zed</surname><given-names>Ada</given-names></name><xref ref-type="aff" rid="aff1"/></contrib>
        <contrib contrib-type="editor"><collab>Review Board</collab></contrib>
      </contrib-group>
      <aff id="aff1"><label>1</label><institution>Port Libs Lab</institution></aff>
      <pub-date date-type="pub"><year>2026</year><month>06</month><day>12</day></pub-date>
      <abstract><p>Native PHP <bold>review</bold> packet.</p></abstract>
      <kwd-group><kwd>XML</kwd><kwd>JATS</kwd></kwd-group>
    </article-meta>
  </front>
  <body>
    <sec id="s1" sec-type="intro"><title>Scope</title><p>Body <xref ref-type="bibr" rid="r1 r2 missing-ref">[1, 2]</xref> <xref ref-type="fig" rid="f1 missing-fig">Fig. 1</xref>.</p><sec id="s1-1" sec-type="methods"><title>Nested</title><p>Nested paragraph <xref ref-type="table" rid="t1">Table 1</xref>.</p></sec></sec>
    <fig id="f1"><label>Figure 1</label><caption><p>Figure caption</p></caption><graphic id="g-local" xlink:href="figures/f1.png" mimetype="image" mime-subtype="png" specific-use="print"/><media id="m-external" xlink:href="https://cdn.example.test/video.mp4" mimetype="video" mime-subtype="mp4"/><graphic id="g-missing" mimetype="image" mime-subtype="svg"/></fig>
    <table-wrap id="t1"><label>Table 1</label><caption><title>Quarterly review</title><p>Table <italic>caption</italic> details.</p></caption><table>
      <thead><tr><th>Area</th><th>Status</th></tr></thead>
      <tbody>
        <tr id="row1"><th scope="row">Scope</th><td colspan="2">Ready</td></tr>
        <tr id="row2"><td>Cells</td><td rowspan="2">Preserved</td></tr>
      </tbody>
    </table></table-wrap>
  </body>
  <back>
    <ref-list id="refs"><title>References</title>
      <ref id="r1">
        <label>1</label>
        <mixed-citation publication-type="journal">
          <person-group person-group-type="author"><name><surname>Rivera</surname><given-names>Sam</given-names></name></person-group>
          <article-title>Reference Study</article-title>
          <source>Journal of Review</source>
          <year>2024</year>
          <pub-id pub-id-type="doi">10.5555/ref.1</pub-id>
        </mixed-citation>
      </ref>
      <ref id="r2">
        <label>2</label>
        <element-citation publication-type="book">
          <person-group person-group-type="editor"><string-name>Editor Team</string-name></person-group>
          <source>Review Handbook</source>
          <year>2023</year>
          <pub-id pub-id-type="isbn">978-1-55555-100-7</pub-id>
        </element-citation>
      </ref>
    </ref-list>
  </back>
</jats:article>
XML, 'JATS article XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($jats);

        $t->same('xml-html5-jats-dom', $packet['formatFamily']);
        $t->same('jats', $packet['format']);
        $t->same('jats-bits-front-matter-and-body-diagnostics-review-only', $packet['reviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same('unsupported', $packet['directReaderParityStatus']);
        $t->same('bounded-review-packet-only', $packet['unsupportedDirectReaderReason']);
        $t->contains('full Pandoc direct reader parity is not implemented', $packet['unsupportedDirectReaderDetail']);
        $t->same([
            'direct-reader-unsupported',
            'body-sections-review-only',
            'references-review-only',
            'reference-citation-text-policy',
            'reference-author-date-policy',
            'reference-identifier-policy',
            'bibliography-xrefs-unresolved',
            'figures-review-only',
            'figure-title-metadata-missing',
            'figure-media-references-review-only',
            'figure-media-target-missing',
            'figure-media-external-reference-unsupported',
            'table-wraps-review-only',
        ], $packet['directReaderDiagnosticCodes']);
        $t->same(13, $packet['directReaderDiagnosticCount']);
        $t->same(false, $packet['directReaderDiagnostics'][0]['directReaderParity'] ?? null);
        $t->same(true, $packet['directReaderDiagnostics'][0]['coveredByPacket'] ?? null);
        $t->same('jats', $packet['directReaderDiagnostics'][0]['details']['format'] ?? null);
        $t->same('jats:article', $packet['directReaderDiagnostics'][0]['details']['rootQualifiedName'] ?? null);
        $t->same('http://jats.nlm.nih.gov', $packet['directReaderDiagnostics'][0]['details']['rootNamespaceUri'] ?? null);
        $t->same('jats', $packet['directReaderDiagnostics'][0]['details']['rootPrefix'] ?? null);
        $t->same('article-root', $packet['directReaderDiagnostics'][0]['details']['rootId'] ?? null);
        $t->same('xml-root', $packet['directReaderDiagnostics'][0]['details']['rootXmlId'] ?? null);
        $t->same('en', $packet['directReaderDiagnostics'][0]['details']['rootLanguage'] ?? null);
        $t->same(['article-type', 'dtd-version', 'id', 'xml:id', 'xml:lang'], $packet['directReaderDiagnostics'][0]['details']['rootAttributeNames'] ?? null);
        $t->same('research-article', $packet['directReaderDiagnostics'][0]['details']['rootAttributes']['article-type'] ?? null);
        $t->same(false, $packet['directReaderDiagnostics'][1]['coveredByPacket'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][1]['details']['sectionCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][2]['details']['referenceCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][2]['details']['resolvedBibrXrefCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][2]['details']['unresolvedBibrXrefCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][2]['details']['safeReferenceLabelCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][2]['details']['safeReferenceAuthorCount'] ?? null);
        $t->same(0, $packet['directReaderDiagnostics'][2]['details']['safeReferenceDateCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][2]['details']['safeReferenceYearCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][2]['details']['referenceIdentifierCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][2]['details']['referenceIdentifierSourceSummaryCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][2]['details']['resolvedBibrIdentifierTargetCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][2]['details']['blockedCitationTextPayloadCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][3]['details']['safeReferenceLabelCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][3]['details']['blockedCitationTextPayloadCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][4]['details']['safeReferenceAuthorCount'] ?? null);
        $t->same(0, $packet['directReaderDiagnostics'][4]['details']['safeReferenceDateCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][4]['details']['safeReferenceYearCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][4]['details']['blockedCitationTextPayloadCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][5]['details']['referenceIdentifierCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][5]['details']['referenceIdentifierSourceSummaryCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][5]['details']['resolvedBibrIdentifierTargetCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][6]['details']['unresolvedBibrXrefCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][7]['details']['figureCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][7]['details']['withLabelCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][7]['details']['withCaptionCount'] ?? null);
        $t->same(0, $packet['directReaderDiagnostics'][7]['details']['withTitleCount'] ?? null);
        $t->same(0, $packet['directReaderDiagnostics'][7]['details']['missingLabelCount'] ?? null);
        $t->same(0, $packet['directReaderDiagnostics'][7]['details']['missingCaptionCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][7]['details']['missingTitleCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][8]['details']['missingTitleCount'] ?? null);
        $t->same(3, $packet['directReaderDiagnostics'][9]['details']['mediaReferenceCount'] ?? null);
        $t->same(false, $packet['directReaderDiagnostics'][9]['details']['payloadBytesExposed'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][10]['details']['missingTargetCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][11]['details']['externalReferenceCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][12]['details']['tableWrapCount'] ?? null);
        $t->same('xml-namespace-scope', $packet['namespaceReview']);
        $t->same(2, $packet['namespaceDeclarationCount']);
        $t->same(0, $packet['namespaceDiagnosticCount']);
        $t->same(false, $packet['namespaceSummary']['directReaderParity'] ?? null);
        $t->same(['jats', 'xlink', 'xml'], $packet['namespaceSummary']['namespacePrefixes'] ?? null);
        $t->same('article', $packet['rootName']);
        $t->same('jats:article', $packet['rootProvenance']['qualifiedName']);
        $t->same('http://jats.nlm.nih.gov', $packet['rootProvenance']['namespaceUri']);
        $t->same('jats', $packet['rootProvenance']['prefix']);
        $t->same('article-root', $packet['rootProvenance']['id']);
        $t->same('xml-root', $packet['rootProvenance']['xmlId']);
        $t->same('en', $packet['rootProvenance']['language']);
        $t->same(5, $packet['rootProvenance']['attributeCount']);
        $t->same(['article-type', 'dtd-version', 'id', 'xml:id', 'xml:lang'], $packet['rootProvenance']['attributeNames']);
        $t->same('research-article', $packet['documentType']);
        $t->same('1.3', $packet['dtdVersion']);
        $t->same('en', $packet['language']);
        $t->same('article-root', $packet['rootAttributes']['id'] ?? null);
        $t->same('xml-root', $packet['rootAttributes']['xml:id'] ?? null);
        $t->same('en', $packet['rootAttributes']['xml:lang'] ?? null);
        $t->same('article-meta', $packet['metadataRoot']);
        $t->same('Import Safety Study', $packet['title']);
        $t->same(['element' => 'article-title', 'text' => 'Import Safety Study'], $packet['titleMetadata']);
        $t->same('Escaping & attributes', $packet['subtitle']);
        $t->same(['element' => 'subtitle', 'text' => 'Escaping & attributes'], $packet['subtitleMetadata']);
        $t->same('Journal & Review', $packet['journalTitle']);
        $t->same('Port Libs Press', $packet['publisherName']);
        $t->same([['element' => 'article-id', 'type' => 'doi', 'value' => '10.5555/review.42']], $packet['articleIds']);
        $t->same(1, $packet['identifierCount']);
        $t->same('Native PHP review packet.', $packet['abstractText']);
        $t->same(['XML', 'JATS'], $packet['keywords']);
        $t->same(2, $packet['contributorCount']);
        $t->same(['Ada Zed', 'Review Board'], $packet['contributorNames']);
        $t->same(['author', 'editor'], $packet['contributorRoles']);
        $t->same(['aff1'], $packet['contributors'][0]['xrefTargets'] ?? null);
        $t->same('2026-06-12', $packet['publicationDates'][0]['iso'] ?? null);
        $t->same(true, $packet['hasBody']);
        $t->same('body', $packet['bodyRoot']);
        $t->same(2, $packet['sectionCount']);
        $t->same(['Scope', 'Nested'], $packet['sectionTitles']);
        $t->same([['Scope'], ['Scope', 'Nested']], $packet['sectionTitlePaths']);
        $t->same(['Scope', 'Scope > Nested'], $packet['sectionTitlePathText']);
        $t->same('s1', $packet['sections'][0]['id'] ?? null);
        $t->same(1, $packet['sections'][0]['depth'] ?? null);
        $t->same('intro', $packet['sections'][0]['type'] ?? null);
        $t->same(['Scope'], $packet['sections'][0]['titlePath'] ?? null);
        $t->same('Scope', $packet['sections'][0]['titlePathText'] ?? null);
        $t->same(1, $packet['sections'][0]['directParagraphCount'] ?? null);
        $t->same(2, $packet['sections'][0]['paragraphCount'] ?? null);
        $t->same(1, $packet['sections'][0]['childSectionCount'] ?? null);
        $t->same('s1', $packet['sections'][1]['parentId'] ?? null);
        $t->same(2, $packet['sections'][1]['depth'] ?? null);
        $t->same('methods', $packet['sections'][1]['type'] ?? null);
        $t->same(['Scope', 'Nested'], $packet['sections'][1]['titlePath'] ?? null);
        $t->same('Scope > Nested', $packet['sections'][1]['titlePathText'] ?? null);
        $t->same('body', $packet['bodySummary']['bodyRoot'] ?? null);
        $t->same(4, $packet['bodySummary']['paragraphCount'] ?? null);
        $t->same(2, $packet['bodySummary']['sectionDepthMax'] ?? null);
        $t->same(['intro', 'methods'], $packet['bodySummary']['sectionTypes'] ?? null);
        $t->same(3, $packet['bodySummary']['xrefCount'] ?? null);
        $t->same(['missing-ref', 'missing-fig'], $packet['bodySummary']['unresolvedXrefTargets'] ?? null);
        $t->same(['f1', 'missing-fig'], $packet['bodySummary']['figureReferenceTargets'] ?? null);
        $t->same(['t1'], $packet['bodySummary']['tableWrapReferenceTargets'] ?? null);
        $t->same(['aff1', 'r1', 'r2', 'missing-ref', 'f1', 'missing-fig', 't1'], $packet['xrefTargets']);
        $t->same(4, $packet['xrefCount']);
        $t->same(['missing-ref', 'missing-fig'], $packet['unresolvedXrefTargets']);
        $t->same('bibr', $packet['xrefs'][1]['refType'] ?? null);
        $t->same(['missing-ref'], $packet['xrefs'][1]['missingTargets'] ?? null);
        $t->same('fig', $packet['xrefs'][2]['refType'] ?? null);
        $t->same(['missing-fig'], $packet['xrefs'][2]['missingTargets'] ?? null);
        $t->same(['r1', 'r2'], $packet['referenceIds']);
        $t->same('jats-bits-ref-list-metadata-only', $packet['referenceReviewPolicy']);
        $t->same('safe-labels-only-block-citation-text-payloads', $packet['referenceCitationTextPolicy']);
        $t->same(1, $packet['referenceListCount']);
        $t->same('refs', $packet['referenceLists'][0]['id'] ?? null);
        $t->same('References', $packet['referenceLists'][0]['title'] ?? null);
        $t->same(['r1', 'r2'], $packet['referenceLists'][0]['referenceIds'] ?? null);
        $t->same(2, $packet['referenceCount']);
        $t->same([['id' => 'r1', 'label' => '1'], ['id' => 'r2', 'label' => '2']], $packet['safeReferenceLabels']);
        $t->same(2, $packet['safeReferenceLabelCount']);
        $t->same('safe-reference-author-date-year-summaries-block-citation-text-payloads', $packet['referenceAuthorDateReviewPolicy']);
        $t->same([
            ['id' => 'r1', 'authorNames' => ['Sam Rivera']],
            ['id' => 'r2', 'authorNames' => ['Editor Team']],
        ], $packet['safeReferenceAuthors']);
        $t->same(2, $packet['safeReferenceAuthorCount']);
        $t->same([], $packet['safeReferenceDates']);
        $t->same(0, $packet['safeReferenceDateCount']);
        $t->same([
            ['id' => 'r1', 'years' => ['2024']],
            ['id' => 'r2', 'years' => ['2023']],
        ], $packet['safeReferenceYears']);
        $t->same(2, $packet['safeReferenceYearCount']);
        $t->same(2, $packet['blockedCitationTextPayloadCount']);
        $t->same(['mixed-citation', 'element-citation'], $packet['blockedCitationTextPayloadElementNames']);
        $t->same(2, $packet['referenceMetadataSummaryCount']);
        $t->same(['r1', 'r2'], $packet['resolvedReferenceIds']);
        $t->same(2, $packet['resolvedBibrXrefCount']);
        $t->same(['missing-ref'], $packet['unresolvedReferenceIds']);
        $t->same(1, $packet['unresolvedBibrXrefCount']);
        $t->same([], $packet['unreferencedReferenceIds']);
        $t->same('1', $packet['references'][0]['label'] ?? null);
        $t->same(1, $packet['references'][0]['referenceCount'] ?? null);
        $t->same('resolved-by-bibr-xref', $packet['references'][0]['status'] ?? null);
        $t->same(true, $packet['references'][0]['metadataOnly'] ?? null);
        $t->same(1, $packet['references'][0]['inboundBibrXrefCount'] ?? null);
        $t->same(1, $packet['references'][0]['blockedCitationTextPayloadCount'] ?? null);
        $t->same([['role' => 'author', 'name' => 'Sam Rivera', 'source' => 'person-group']], $packet['references'][0]['authors'] ?? null);
        $t->same(['Sam Rivera'], $packet['references'][0]['authorNames'] ?? null);
        $t->same(1, $packet['references'][0]['authorCount'] ?? null);
        $t->same(['2024'], $packet['references'][0]['years'] ?? null);
        $t->same(1, $packet['references'][0]['yearCount'] ?? null);
        $t->same([], $packet['references'][0]['dates'] ?? null);
        $t->same(0, $packet['references'][0]['dateCount'] ?? null);
        $t->same('2', $packet['references'][1]['label'] ?? null);
        $t->same(1, $packet['references'][1]['referenceCount'] ?? null);
        $t->same('resolved-by-bibr-xref', $packet['references'][1]['status'] ?? null);
        $t->same(1, $packet['references'][1]['blockedCitationTextPayloadCount'] ?? null);
        $t->same([['role' => 'editor', 'name' => 'Editor Team', 'source' => 'person-group']], $packet['references'][1]['authors'] ?? null);
        $t->same(['Editor Team'], $packet['references'][1]['authorNames'] ?? null);
        $t->same(1, $packet['references'][1]['authorCount'] ?? null);
        $t->same(['2023'], $packet['references'][1]['years'] ?? null);
        $t->same(1, $packet['references'][1]['yearCount'] ?? null);
        $t->same('r1', $packet['bibliographyXrefs'][0]['targetId'] ?? null);
        $t->same('resolved', $packet['bibliographyXrefs'][0]['status'] ?? null);
        $t->same('missing-ref', $packet['bibliographyXrefs'][2]['targetId'] ?? null);
        $t->same('unresolved', $packet['bibliographyXrefs'][2]['status'] ?? null);
        $t->same(true, $packet['hasBackMatter']);
        $t->same('back', $packet['backMatterRoot']);
        $t->same(1, $packet['backMatterReferenceListCount']);
        $t->same('refs', $packet['backMatterReferenceLists'][0]['id'] ?? null);
        $t->same('References', $packet['backMatterReferenceLists'][0]['title'] ?? null);
        $t->same(['r1', 'r2'], $packet['backMatterReferenceLists'][0]['referenceIds'] ?? null);
        $t->same(2, $packet['backMatterReferenceLists'][0]['referenceCount'] ?? null);
        $t->same(['r1', 'r2'], $packet['backMatterReferenceIds']);
        $t->same(2, $packet['backMatterReferenceCount']);
        $t->same('1', $packet['backMatterReferences'][0]['label'] ?? null);
        $t->same(['journal'], $packet['backMatterReferences'][0]['citationTypes'] ?? null);
        $t->same(['author'], $packet['backMatterReferences'][0]['personGroupTypes'] ?? null);
        $t->same('Reference Study', $packet['backMatterReferences'][0]['articleTitle'] ?? null);
        $t->same('Journal of Review', $packet['backMatterReferences'][0]['source'] ?? null);
        $t->same('2024', $packet['backMatterReferences'][0]['year'] ?? null);
        $t->same([['element' => 'pub-id', 'type' => 'doi', 'value' => '10.5555/ref.1']], $packet['backMatterReferences'][0]['pubIds'] ?? null);
        $t->same(1, $packet['backMatterReferences'][1]['elementCitationCount'] ?? null);
        $t->same(['book'], $packet['backMatterReferences'][1]['citationTypes'] ?? null);
        $t->same(['editor'], $packet['backMatterReferences'][1]['personGroupTypes'] ?? null);
        $t->same('Review Handbook', $packet['backMatterReferences'][1]['source'] ?? null);
        $t->same([['element' => 'pub-id', 'type' => 'isbn', 'value' => '978-1-55555-100-7']], $packet['backMatterReferences'][1]['pubIds'] ?? null);
        $t->same(1, $packet['citationXrefCount']);
        $t->same('bibr', $packet['citationXrefs'][0]['refType'] ?? null);
        $t->same('r1 r2 missing-ref', $packet['citationXrefs'][0]['ridRaw'] ?? null);
        $t->same(['r1', 'r2', 'missing-ref'], $packet['citationTargetIds']);
        $t->same(['r1', 'r2'], $packet['resolvedCitationReferenceIds']);
        $t->same(['missing-ref'], $packet['missingCitationReferenceIds']);
        $t->same([
            'jats-bits-direct-reader-parity-not-implemented',
            'jats-bits-back-matter-review-only',
            'jats-bits-reference-summary-review-only',
            'jats-bits-citation-xref-summary-review-only',
            'jats-bits-citation-target-missing',
        ], $packet['diagnostics']);
        $t->same(['f1'], $packet['figureIds']);
        $t->same(1, $packet['figureCount']);
        $t->same([
            'total' => 1,
            'withLabel' => 1,
            'withCaption' => 1,
            'withTitle' => 0,
            'missingLabel' => 0,
            'missingCaption' => 0,
            'missingTitle' => 1,
            'incomplete' => 1,
        ], $packet['figureMetadataCounts']);
        $t->same(['Figure 1'], $packet['figureLabels']);
        $t->same([], $packet['figureTitles']);
        $t->same(['Figure caption'], $packet['figureCaptionTexts']);
        $t->same('Figure 1', $packet['figures'][0]['label'] ?? null);
        $t->same('Figure caption', $packet['figures'][0]['caption'] ?? null);
        $t->same('Figure caption', $packet['figures'][0]['captionText'] ?? null);
        $t->same(['Figure caption'], $packet['figures'][0]['captionParagraphs'] ?? null);
        $t->same(['title'], $packet['figures'][0]['missingMetadata'] ?? null);
        $t->same(['figures/f1.png'], $packet['figures'][0]['graphicHrefs'] ?? null);
        $t->same(3, $packet['figures'][0]['mediaReferenceCount'] ?? null);
        $t->same(['missing-target', 'unsupported-external-reference'], $packet['figures'][0]['mediaIssueCodes'] ?? null);
        $t->same(1, $packet['figures'][0]['referenceCount'] ?? null);
        $t->same([], $packet['unreferencedFigureIds']);
        $t->same(3, $packet['figureMediaReferenceCount']);
        $t->same(['missing-target', 'unsupported-external-reference'], $packet['figureMediaIssueCodes']);
        $t->same(2, $packet['figureMediaIssueCount']);
        $t->same(false, $packet['figureMediaPayloadBytesExposed']);
        $t->same('graphic', $packet['figureMediaReferences'][0]['element'] ?? null);
        $t->same('g-local', $packet['figureMediaReferences'][0]['id'] ?? null);
        $t->same('xlink:href', $packet['figureMediaReferences'][0]['hrefAttribute'] ?? null);
        $t->same('figures/f1.png', $packet['figureMediaReferences'][0]['target'] ?? null);
        $t->same('f1.png', $packet['figureMediaReferences'][0]['targetBasename'] ?? null);
        $t->same('internal', $packet['figureMediaReferences'][0]['targetKind'] ?? null);
        $t->same('image/png', $packet['figureMediaReferences'][0]['contentType'] ?? null);
        $t->same(false, $packet['figureMediaReferences'][0]['payloadBytesExposed'] ?? null);
        $t->same([], $packet['figureMediaReferences'][0]['issues'] ?? null);
        $t->same('media', $packet['figureMediaReferences'][1]['element'] ?? null);
        $t->same('external', $packet['figureMediaReferences'][1]['targetKind'] ?? null);
        $t->same('video/mp4', $packet['figureMediaReferences'][1]['contentType'] ?? null);
        $t->same(['unsupported-external-reference'], $packet['figureMediaReferences'][1]['issues'] ?? null);
        $t->same('graphic', $packet['figureMediaReferences'][2]['element'] ?? null);
        $t->same(null, $packet['figureMediaReferences'][2]['target'] ?? null);
        $t->same('missing', $packet['figureMediaReferences'][2]['targetKind'] ?? null);
        $t->same(['missing-target'], $packet['figureMediaReferences'][2]['issues'] ?? null);
        $t->same(['t1'], $packet['tableWrapIds']);
        $t->same(1, $packet['tableLabelCount']);
        $t->same(1, $packet['tableCaptionCount']);
        $t->same(1, $packet['tableCaptionTitleCount']);
        $t->same([
            'jats-bits-table-label-review-only',
            'jats-bits-table-caption-review-only',
            'jats-bits-table-caption-title-review-only',
            'jats-bits-table-caption-paragraphs-review-only',
        ], $packet['tableCaptionDiagnostics']);
        $t->same(1, $packet['tableBodyCount']);
        $t->same(2, $packet['tableBodyRowCount']);
        $t->same(4, $packet['tableBodyCellCount']);
        $t->same([
            'jats-bits-table-body-summary-review-only',
            'jats-bits-table-cell-summary-review-only',
        ], $packet['tableBodyDiagnostics']);
        $t->same('Table 1', $packet['tableWraps'][0]['label'] ?? null);
        $t->same('Quarterly review Table caption details.', $packet['tableWraps'][0]['caption'] ?? null);
        $t->same('Quarterly review Table caption details.', $packet['tableWraps'][0]['captionText'] ?? null);
        $t->same('Quarterly review', $packet['tableWraps'][0]['captionTitle'] ?? null);
        $t->same(['Table caption details.'], $packet['tableWraps'][0]['captionParagraphs'] ?? null);
        $t->same(1, $packet['tableWraps'][0]['captionParagraphCount'] ?? null);
        $t->same([
            'jats-bits-table-label-review-only',
            'jats-bits-table-caption-review-only',
            'jats-bits-table-caption-title-review-only',
            'jats-bits-table-caption-paragraphs-review-only',
        ], $packet['tableWraps'][0]['metadataDiagnostics'] ?? null);
        $t->same(3, $packet['tableWraps'][0]['rowCount'] ?? null);
        $t->same(1, $packet['tableWraps'][0]['tableCount'] ?? null);
        $t->same(1, $packet['tableWraps'][0]['tbodyCount'] ?? null);
        $t->same(2, $packet['tableWraps'][0]['bodyRowCount'] ?? null);
        $t->same(4, $packet['tableWraps'][0]['bodyCellCount'] ?? null);
        $t->same('row1', $packet['tableWraps'][0]['bodyRows'][0]['id'] ?? null);
        $t->same(['Scope', 'Ready'], $packet['tableWraps'][0]['bodyRows'][0]['texts'] ?? null);
        $t->same('th', $packet['tableWraps'][0]['bodyRows'][0]['cells'][0]['name'] ?? null);
        $t->same('row', $packet['tableWraps'][0]['bodyRows'][0]['cells'][0]['scope'] ?? null);
        $t->same(2, $packet['tableWraps'][0]['bodyRows'][0]['cells'][1]['colspan'] ?? null);
        $t->same(2, $packet['tableWraps'][0]['bodyRows'][1]['cells'][1]['rowspan'] ?? null);
        $t->same(['jats-bits-table-body-summary-review-only'], $packet['tableWraps'][0]['diagnostics'] ?? null);
        $t->same(1, $packet['tableWraps'][0]['referenceCount'] ?? null);
        $t->same([], $packet['unreferencedTableWrapIds']);
        $t->same(0, $packet['bookPartCount']);
        json_encode($packet, JSON_THROW_ON_ERROR);

        $bits = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book book-type="monograph" xml:lang="fr">
  <book-meta>
    <book-id book-id-type="isbn">978-1-55555-042-0</book-id>
    <title-group><book-title>Review Book</book-title><subtitle>Bounded XML metadata</subtitle></title-group>
    <contrib-group><contrib contrib-type="editor"><string-name>Camille Editor</string-name></contrib></contrib-group>
    <pub-date pub-type="ppub"><year>2025</year></pub-date>
  </book-meta>
  <book-body><book-part id="ch1" book-part-type="chapter"><book-part-meta><title-group><title>Chapter One</title></title-group></book-part-meta><body><sec id="ch1s1"><title>Inside</title><p>Chapter body cites <xref ref-type="bibr" rid="bref1">the guide</xref>.</p></sec></body></book-part><table-wrap id="bt1"><label>Table B1</label><caption><title>Book totals</title><p>Book table</p></caption><table><tbody><tr><td>Book cell</td></tr></tbody></table></table-wrap></book-body>
  <book-back>
    <ref-list id="book-refs"><title>Book References</title>
      <ref id="bref1"><mixed-citation publication-type="book"><source>Bounded XML Guide</source><year>2024</year></mixed-citation></ref>
    </ref-list>
  </book-back>
</book>
XML, 'BITS book XML', preserveWhiteSpace: false);
        $bitsPacket = XmlHtmlDom::summarizeJatsFrontMatter($bits, 'bits');

        $t->same('bits', $bitsPacket['format']);
        $t->same('book', $bitsPacket['rootName']);
        $t->same('monograph', $bitsPacket['documentType']);
        $t->same('fr', $bitsPacket['language']);
        $t->same('book-meta', $bitsPacket['metadataRoot']);
        $t->same('Review Book', $bitsPacket['title']);
        $t->same(['element' => 'book-title', 'text' => 'Review Book'], $bitsPacket['titleMetadata']);
        $t->same('Bounded XML metadata', $bitsPacket['subtitle']);
        $t->same(['element' => 'subtitle', 'text' => 'Bounded XML metadata'], $bitsPacket['subtitleMetadata']);
        $t->same([['element' => 'book-id', 'type' => 'isbn', 'value' => '978-1-55555-042-0']], $bitsPacket['bookIds']);
        $t->same(['Camille Editor'], $bitsPacket['contributorNames']);
        $t->same('2025', $bitsPacket['publicationDates'][0]['iso'] ?? null);
        $t->same('book-body', $bitsPacket['bodyRoot']);
        $t->same(['Inside'], $bitsPacket['sectionTitles']);
        $t->same(1, $bitsPacket['bodySummary']['bookPartCount'] ?? null);
        $t->same('chapter', $bitsPacket['bookParts'][0]['type'] ?? null);
        $t->same('Chapter One', $bitsPacket['bookParts'][0]['title'] ?? null);
        $t->same('body', $bitsPacket['bookParts'][0]['bodyRoot'] ?? null);
        $t->same(1, $bitsPacket['bookParts'][0]['sectionCount'] ?? null);
        $t->same(['bt1'], $bitsPacket['tableWrapIds']);
        $t->same(1, $bitsPacket['tableLabelCount']);
        $t->same(1, $bitsPacket['tableCaptionCount']);
        $t->same(1, $bitsPacket['tableCaptionTitleCount']);
        $t->same('Book totals', $bitsPacket['tableWraps'][0]['captionTitle'] ?? null);
        $t->same(['Book table'], $bitsPacket['tableWraps'][0]['captionParagraphs'] ?? null);
        $t->same(1, $bitsPacket['tableBodyRowCount']);
        $t->same('Book cell', $bitsPacket['tableWraps'][0]['bodyRows'][0]['cells'][0]['text'] ?? null);
        $t->same(1, $bitsPacket['bookPartCount']);
        $t->same('book-back', $bitsPacket['backMatterRoot']);
        $t->same(['bref1'], $bitsPacket['backMatterReferenceIds']);
        $t->same('Bounded XML Guide', $bitsPacket['backMatterReferences'][0]['source'] ?? null);
        $t->same(['bref1'], $bitsPacket['resolvedCitationReferenceIds']);
        $t->same([], $bitsPacket['missingCitationReferenceIds']);
        $t->same(false, $bitsPacket['directReaderParity']);
        $t->same('xml-namespace-scope', $bitsPacket['namespaceReview']);
        $t->same(0, $bitsPacket['namespaceDiagnosticCount']);
        $t->same('unsupported', $bitsPacket['directReaderParityStatus']);
        $t->same('bounded-review-packet-only', $bitsPacket['unsupportedDirectReaderReason']);
        $t->same(1, $bitsPacket['referenceListCount']);
        $t->same(1, $bitsPacket['referenceCount']);
        $t->same(['bref1'], $bitsPacket['resolvedReferenceIds']);
        $t->same([], $bitsPacket['unresolvedReferenceIds']);
        $t->same('safe-reference-author-date-year-summaries-block-citation-text-payloads', $bitsPacket['referenceAuthorDateReviewPolicy']);
        $t->same([], $bitsPacket['safeReferenceAuthors']);
        $t->same(0, $bitsPacket['safeReferenceAuthorCount']);
        $t->same([], $bitsPacket['safeReferenceDates']);
        $t->same(0, $bitsPacket['safeReferenceDateCount']);
        $t->same([['id' => 'bref1', 'years' => ['2024']]], $bitsPacket['safeReferenceYears']);
        $t->same(1, $bitsPacket['safeReferenceYearCount']);
        $t->same(['2024'], $bitsPacket['references'][0]['years'] ?? null);
        $t->same(1, $bitsPacket['references'][0]['yearCount'] ?? null);
        $t->same([
            'direct-reader-unsupported',
            'references-review-only',
            'reference-citation-text-policy',
            'reference-author-date-policy',
            'reference-identifier-policy',
            'reference-identifiers-missing',
            'table-wraps-review-only',
            'book-parts-review-only',
        ], $bitsPacket['directReaderDiagnosticCodes']);
        $t->same(8, $bitsPacket['directReaderDiagnosticCount']);
        $t->same('bits', $bitsPacket['directReaderDiagnostics'][0]['details']['format'] ?? null);
        $t->same(false, $bitsPacket['directReaderDiagnostics'][1]['coveredByPacket'] ?? null);
        $t->same(1, $bitsPacket['directReaderDiagnostics'][1]['details']['referenceCount'] ?? null);
        $t->same(0, $bitsPacket['directReaderDiagnostics'][1]['details']['safeReferenceLabelCount'] ?? null);
        $t->same(0, $bitsPacket['directReaderDiagnostics'][1]['details']['safeReferenceAuthorCount'] ?? null);
        $t->same(0, $bitsPacket['directReaderDiagnostics'][1]['details']['safeReferenceDateCount'] ?? null);
        $t->same(1, $bitsPacket['directReaderDiagnostics'][1]['details']['safeReferenceYearCount'] ?? null);
        $t->same(0, $bitsPacket['directReaderDiagnostics'][1]['details']['referenceIdentifierCount'] ?? null);
        $t->same(1, $bitsPacket['directReaderDiagnostics'][1]['details']['referencesMissingIdentifierCount'] ?? null);
        $t->same(1, $bitsPacket['directReaderDiagnostics'][1]['details']['blockedCitationTextPayloadCount'] ?? null);
        $t->same(0, $bitsPacket['directReaderDiagnostics'][2]['details']['safeReferenceLabelCount'] ?? null);
        $t->same(1, $bitsPacket['directReaderDiagnostics'][2]['details']['blockedCitationTextPayloadCount'] ?? null);
        $t->same(0, $bitsPacket['directReaderDiagnostics'][3]['details']['safeReferenceAuthorCount'] ?? null);
        $t->same(0, $bitsPacket['directReaderDiagnostics'][3]['details']['safeReferenceDateCount'] ?? null);
        $t->same(1, $bitsPacket['directReaderDiagnostics'][3]['details']['safeReferenceYearCount'] ?? null);
        $t->same(0, $bitsPacket['directReaderDiagnostics'][4]['details']['referenceIdentifierCount'] ?? null);
        $t->same(1, $bitsPacket['directReaderDiagnostics'][4]['details']['referencesMissingIdentifierCount'] ?? null);
        $t->same(1, $bitsPacket['directReaderDiagnostics'][5]['details']['referencesMissingIdentifierCount'] ?? null);
        $t->same(false, $bitsPacket['directReaderDiagnostics'][6]['coveredByPacket'] ?? null);
        $t->same(1, $bitsPacket['directReaderDiagnostics'][6]['details']['tableWrapCount'] ?? null);
        $t->same(false, $bitsPacket['directReaderDiagnostics'][7]['coveredByPacket'] ?? null);
        $t->same(1, $bitsPacket['directReaderDiagnostics'][7]['details']['bookPartCount'] ?? null);
        $t->throws(InvalidArgumentException::class, static fn (): array => XmlHtmlDom::summarizeJatsFrontMatter($jats, 'xml'));
        json_encode($bitsPacket, JSON_THROW_ON_ERROR);
    },
    'summarizes jats bits abstract and keyword group metadata for reviewer handoff' => static function (TestRunner $t): void {
        $jats = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article" xml:lang="en">
  <front>
    <article-meta>
      <title-group><article-title>Abstract Keyword Review</article-title></title-group>
      <abstract id="abs-main" abstract-type="summary" xml:lang="en">
        <title>Summary</title>
        <p>Main abstract.</p>
        <sec id="abs-objective" sec-type="objective"><title>Objective</title><p>Assess tail metadata.</p></sec>
      </abstract>
      <trans-abstract id="abs-es" xml:lang="es">
        <title>Resumen</title>
        <p>Resumen traducido.</p>
      </trans-abstract>
      <kwd-group id="kg-author" kwd-group-type="author" xml:lang="en">
        <title>Keywords</title>
        <kwd>DOM</kwd>
        <kwd>JATS</kwd>
        <compound-kwd>
          <compound-kwd-part content-type="subject">XML</compound-kwd-part>
          <compound-kwd-part content-type="qualifier">review</compound-kwd-part>
        </compound-kwd>
      </kwd-group>
      <kwd-group id="kg-translated" kwd-group-type="translated" xml:lang="es">
        <kwd>Revision</kwd>
      </kwd-group>
    </article-meta>
  </front>
  <body><sec id="body"><title>Body</title><p>Body text.</p></sec></body>
</article>
XML, 'JATS abstract keyword metadata XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($jats);

        $t->same('jats-bits-abstract-metadata-review-only', $packet['abstractReviewPolicy']);
        $t->same(2, $packet['abstractCount']);
        $t->same(['summary'], $packet['abstractTypes']);
        $t->same(['en', 'es'], $packet['abstractLanguages']);
        $t->same(1, $packet['structuredAbstractCount']);
        $compatibility = XmlHtmlDom::summarizeJatsFrontMatter(XmlHtmlDom::loadXmlDocument(<<<'XML'
<article><front><article-meta><abstract><p>Native PHP review packet.</p></abstract></article-meta></front></article>
XML, 'JATS abstract compatibility XML', preserveWhiteSpace: false));
        $t->same('Native PHP review packet.', $compatibility['abstractText']);

        $summary = $packet['abstracts'][0];
        $translation = $packet['abstracts'][1];
        $t->same('abstract', $summary['element']);
        $t->same('abs-main', $summary['id']);
        $t->same('summary', $summary['type']);
        $t->same('en', $summary['language']);
        $t->same('Summary', $summary['title']);
        $t->same('Summary Main abstract. Objective Assess tail metadata.', $summary['text']);
        $t->same(['Main abstract.'], $summary['paragraphs']);
        $t->same(1, $summary['paragraphCount']);
        $t->same(1, $summary['sectionCount']);
        $t->same(['Objective'], $summary['sectionTitles']);
        $t->same('abs-objective', $summary['sections'][0]['id'] ?? null);
        $t->same('objective', $summary['sections'][0]['type'] ?? null);
        $t->same(['Assess tail metadata.'], $summary['sections'][0]['paragraphs'] ?? null);
        $t->same(true, $summary['structured']);
        $t->true(($summary['sourceLine'] ?? 0) > 0);
        $t->same('trans-abstract', $translation['element']);
        $t->same('abs-es', $translation['id']);
        $t->same('es', $translation['language']);
        $t->same('Resumen', $translation['title']);
        $t->same(false, $translation['structured']);

        $t->same('jats-bits-keyword-group-metadata-review-only', $packet['keywordReviewPolicy']);
        $t->same(['DOM', 'JATS', 'Revision'], $packet['keywords']);
        $t->same(2, $packet['keywordGroupCount']);
        $t->same(['author', 'translated'], $packet['keywordGroupTypes']);
        $t->same(['en', 'es'], $packet['keywordGroupLanguages']);
        $t->same(3, $packet['keywordCount']);
        $t->same(1, $packet['compoundKeywordCount']);
        $t->same(2, $packet['compoundKeywordPartCount']);
        $authorKeywords = $packet['keywordGroups'][0];
        $translatedKeywords = $packet['keywordGroups'][1];
        $t->same('kg-author', $authorKeywords['id']);
        $t->same('author', $authorKeywords['type']);
        $t->same('Keywords', $authorKeywords['title']);
        $t->same(['DOM', 'JATS'], $authorKeywords['keywords']);
        $t->same(2, $authorKeywords['keywordCount']);
        $t->same('XML review', $authorKeywords['compoundKeywords'][0]['text'] ?? null);
        $t->same(2, $authorKeywords['compoundKeywords'][0]['partCount'] ?? null);
        $t->same([
            ['type' => 'subject', 'text' => 'XML'],
            ['type' => 'qualifier', 'text' => 'review'],
        ], $authorKeywords['compoundKeywords'][0]['parts'] ?? null);
        $t->same('kg-translated', $translatedKeywords['id']);
        $t->same(['Revision'], $translatedKeywords['keywords']);

        $bits = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book book-type="monograph" xml:lang="en">
  <book-meta>
    <title-group><book-title>BITS Abstracts</book-title></title-group>
    <abstract id="book-abstract" content-type="overview"><p>Book abstract.</p></abstract>
    <kwd-group content-type="subject"><kwd>BITS</kwd></kwd-group>
  </book-meta>
  <book-body/>
</book>
XML, 'BITS abstract keyword metadata XML', preserveWhiteSpace: false);
        $bitsPacket = XmlHtmlDom::summarizeJatsFrontMatter($bits, 'bits');
        $t->same('bits', $bitsPacket['format']);
        $t->same(1, $bitsPacket['abstractCount']);
        $t->same(['overview'], $bitsPacket['abstractTypes']);
        $t->same('book-abstract', $bitsPacket['abstracts'][0]['id'] ?? null);
        $t->same('Book abstract.', $bitsPacket['abstracts'][0]['text'] ?? null);
        $t->same(1, $bitsPacket['keywordGroupCount']);
        $t->same(['subject'], $bitsPacket['keywordGroupTypes']);
        $t->same(['BITS'], $bitsPacket['keywords']);
        json_encode($packet, JSON_THROW_ON_ERROR);
        json_encode($bitsPacket, JSON_THROW_ON_ERROR);
    },
    'summarizes jats figure permissions and media license diagnostics without payload exposure' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article" dtd-version="1.3" xml:lang="en" xmlns:xlink="http://www.w3.org/1999/xlink">
  <front><article-meta><title-group><article-title>Figure Permissions</article-title></title-group></article-meta></front>
  <body>
    <fig id="f1">
      <label>Fig. 1</label>
      <caption><p>Figure</p></caption>
      <permissions>
        <copyright-statement>Copyright 2026 Port Libs</copyright-statement>
        <copyright-year>2026</copyright-year>
        <copyright-holder>Port Libs</copyright-holder>
        <license license-type="open-access" xlink:href="https://creativecommons.org/licenses/by/4.0/"><license-p>CC BY 4.0</license-p></license>
        <license-ref xlink:href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0 deed</license-ref>
      </permissions>
      <graphic id="g1" xlink:href="figures/chart.tif" mimetype="image" mime-subtype="tiff"/>
      <graphic id="g2" xlink:href="figures/duplicate.tif">
        <permissions>
          <license xlink:href="https://creativecommons.org/licenses/by/4.0/"><license-p>CC BY 4.0</license-p></license>
          <license xlink:href="https://creativecommons.org/licenses/by/4.0/"><license-p>CC BY 4.0</license-p></license>
        </permissions>
      </graphic>
      <graphic id="g-unsafe" xlink:href="figures/unsafe.png">
        <permissions id="media-unsafe">
          <license-ref xlink:href="javascript:alert(1)">Unsafe script license</license-ref>
        </permissions>
      </graphic>
    </fig>
    <fig id="f-targets">
      <label>Fig. 2</label>
      <caption><p>License target diagnostics</p></caption>
      <permissions id="perm-targets">
        <copyright-year>2025</copyright-year>
        <copyright-holder>Target Holder</copyright-holder>
        <license><license-p>Terms <ext-link ext-link-type="uri" xlink:href="https://licenses.example.test/terms">external terms</ext-link></license-p></license>
        <license-ref>Missing target reference</license-ref>
      </permissions>
      <graphic id="g-targets" xlink:href="figures/targets.png"/>
    </fig>
    <fig id="f-missing"><caption><p>No license</p></caption><graphic xlink:href="figures/missing.png"/></fig>
  </body>
</article>
XML, 'JATS figure permissions XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($dom);

        $t->same(false, $packet['payloadBytesExposed']);
        $t->same(false, $packet['directReaderParity']);
        $t->same(['f1', 'f-targets', 'f-missing'], $packet['figureIds']);
        $t->same('jats-bits-figure-permissions-metadata-only', $packet['figurePermissionReviewPolicy']);
        $t->same(false, $packet['figurePermissionPayloadBytesExposed']);
        $t->same(3, $packet['figurePermissionSummaryCount']);
        $t->same([
            'figures/chart.tif',
            'figures/duplicate.tif',
            'figures/unsafe.png',
            'figures/targets.png',
            'figures/missing.png',
        ], $packet['figureMediaTargets']);
        $t->same(5, $packet['figureMediaTargetCount']);
        $t->same([
            'duplicate-license',
            'duplicate-license-target',
            'missing-license',
            'missing-license-target',
            'unsafe-license-target',
        ], $packet['figurePermissionIssueCodes']);
        $t->same(6, $packet['figurePermissionIssueCount']);
        $t->same(8, $packet['figurePermissionLicenseTargetCount']);
        $t->same([
            'duplicate-license-target',
            'missing-license-target',
            'unsafe-license-target',
        ], $packet['figurePermissionLicenseTargetIssueCodes']);
        $t->same(4, $packet['figurePermissionLicenseTargetIssueCount']);
        $t->same('figure', $packet['figurePermissionLicenseTargets'][0]['scope'] ?? null);
        $t->same('f1', $packet['figurePermissionLicenseTargets'][0]['figureId'] ?? null);
        $t->same('media', $packet['figurePermissionLicenseTargets'][2]['scope'] ?? null);
        $t->same('figures/duplicate.tif', $packet['figurePermissionLicenseTargets'][2]['mediaTarget'] ?? null);
        $t->same('unsafe', $packet['figurePermissionLicenseTargets'][4]['targetKind'] ?? null);
        $t->same('f-targets', $packet['figurePermissionLicenseTargets'][5]['figureId'] ?? null);
        $t->same(false, $packet['figureMediaPayloadBytesExposed']);
        $t->same([], $packet['figureMediaReferences'][0]['issues']);
        $t->same(['duplicate-license', 'duplicate-license-target'], $packet['figureMediaReferences'][1]['permissionIssueCodes']);
        $t->same(['unsafe-license-target'], $packet['figureMediaReferences'][2]['permissionIssueCodes']);

        $licensedFigure = $packet['figurePermissionSummaries'][0];
        $targetFigure = $packet['figurePermissionSummaries'][1];
        $missingFigure = $packet['figurePermissionSummaries'][2];
        $t->same('f1', $licensedFigure['figureId']);
        $t->same('Fig. 1', $licensedFigure['label']);
        $t->same('Figure', $licensedFigure['captionText']);
        $t->same([
            'figures/chart.tif',
            'figures/duplicate.tif',
            'figures/unsafe.png',
        ], $licensedFigure['mediaTargets']);
        $t->same(3, $licensedFigure['mediaTargetCount']);
        $t->same(3, $licensedFigure['mediaCount']);
        $t->same(1, $licensedFigure['permissionCount']);
        $t->same(1, $licensedFigure['licenseCount']);
        $t->same(1, $licensedFigure['licenseRefCount']);
        $t->same(1, $licensedFigure['copyrightStatementCount']);
        $t->same(false, $licensedFigure['payloadBytesExposed']);
        $t->same(2, $licensedFigure['licenseTargetCount']);
        $t->same(['duplicate-license-target'], $licensedFigure['licenseTargetIssueCodes']);
        $t->same('Copyright 2026 Port Libs', $licensedFigure['permissions'][0]['copyrightStatements'][0] ?? null);
        $t->same(['2026'], $licensedFigure['permissions'][0]['copyrightYears'] ?? null);
        $t->same(['Port Libs'], $licensedFigure['permissions'][0]['copyrightHolders'] ?? null);
        $t->same('open-access', $licensedFigure['permissions'][0]['licenses'][0]['licenseType'] ?? null);
        $t->same('https://creativecommons.org/licenses/by/4.0/', $licensedFigure['permissions'][0]['licenses'][0]['href'] ?? null);
        $t->same(['CC BY 4.0'], $licensedFigure['permissions'][0]['licenses'][0]['licenseParagraphs'] ?? null);
        $t->same('https://creativecommons.org/licenses/by/4.0/', $licensedFigure['permissions'][0]['licenseRefs'][0]['href'] ?? null);
        $t->same('graphic', $licensedFigure['media'][0]['element']);
        $t->same('g1', $licensedFigure['media'][0]['id']);
        $t->same('figures/chart.tif', $licensedFigure['media'][0]['target']);
        $t->same('image', $licensedFigure['media'][0]['mimeType']);
        $t->same('tiff', $licensedFigure['media'][0]['mimeSubtype']);
        $t->same(false, $licensedFigure['media'][0]['payloadBytesExposed']);
        $t->same(2, $licensedFigure['media'][1]['licenseCount']);
        $t->same('duplicate-license', $licensedFigure['media'][1]['issues'][0]['code'] ?? null);
        $t->same('media', $licensedFigure['media'][1]['issues'][0]['scope'] ?? null);
        $t->same('f1', $licensedFigure['media'][1]['issues'][0]['figureId'] ?? null);
        $t->same('figures/duplicate.tif', $licensedFigure['media'][1]['issues'][0]['mediaTarget'] ?? null);
        $t->same(2, $licensedFigure['media'][1]['issues'][0]['count'] ?? null);
        $t->same('media-unsafe', $licensedFigure['media'][2]['permissions'][0]['id'] ?? null);
        $t->same('unsafe', $licensedFigure['media'][2]['licenseTargets'][0]['targetKind'] ?? null);
        $t->same('javascript', $licensedFigure['media'][2]['licenseTargets'][0]['targetScheme'] ?? null);
        $t->same('unsafe-license-target', $licensedFigure['media'][2]['issues'][0]['code'] ?? null);
        $t->same('f-targets', $targetFigure['figureId']);
        $t->same(['2025'], $targetFigure['permissions'][0]['copyrightYears'] ?? null);
        $t->same(['Target Holder'], $targetFigure['permissions'][0]['copyrightHolders'] ?? null);
        $t->same(['missing', 'external'], $targetFigure['permissions'][0]['licenseTargetKinds'] ?? null);
        $t->same('ext-link', $targetFigure['licenseTargets'][1]['element'] ?? null);
        $t->same('external', $targetFigure['licenseTargets'][1]['targetKind'] ?? null);
        $t->same('license-ref', $targetFigure['licenseTargets'][2]['element'] ?? null);
        $t->same('missing', $targetFigure['licenseTargets'][2]['targetKind'] ?? null);
        $t->same(['missing-license-target'], $targetFigure['licenseTargetIssueCodes']);
        $t->same('f-missing', $missingFigure['figureId']);
        $t->same(['figures/missing.png'], $missingFigure['mediaTargets']);
        $t->same(0, $missingFigure['licenseCount']);
        $t->same('missing-license', $missingFigure['issues'][0]['code'] ?? null);
        $t->same('figure', $missingFigure['issues'][0]['scope'] ?? null);
        $t->same(['figures/missing.png'], $missingFigure['issues'][0]['mediaTargets'] ?? null);
        $t->same(false, $missingFigure['media'][0]['payloadBytesExposed']);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes jats bits funding and acknowledgment review diagnostics without citation payload text' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article">
  <front>
    <article-meta>
      <title-group><article-title>Funding Review</article-title></title-group>
      <funding-group id="fg1">
        <award-group id="ag1">
          <funding-source id="fs1" source-type="agency">National Science Foundation <institution-id institution-id-type="fundref">10.13039/100000001</institution-id></funding-source>
          <award-id id="award-a" award-id-type="grant">R01-42</award-id>
          <award-id id="award-a-copy" award-id-type="grant">R01-42</award-id>
          <xref ref-type="bibr" rid="r1">funding ref</xref>
        </award-group>
        <award-group id="ag2">
          <funding-source id="fs2">Missing Award Council</funding-source>
          <xref ref-type="bibr" rid="missing-ref">missing ref</xref>
        </award-group>
      </funding-group>
    </article-meta>
  </front>
  <body><sec id="s1"><title>Funding</title><p>Body cites <xref ref-type="bibr" rid="r1">[1]</xref>.</p></sec></body>
  <back>
    <ack id="ack1"><title>Acknowledgments</title><p>We thank reviewers <xref ref-type="bibr" rid="r1">[1]</xref> and <xref ref-type="bibr" rid="r-missing">[missing]</xref>.</p></ack>
    <ref-list id="refs"><ref id="r1"><label>1</label><mixed-citation>Blocked Citation Payload With Secret Grant Text</mixed-citation></ref></ref-list>
  </back>
</article>
XML, 'JATS funding acknowledgment XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($dom);

        $t->same(false, $packet['directReaderParity']);
        $t->same([
            'direct-reader-unsupported',
            'body-sections-review-only',
            'references-review-only',
            'reference-citation-text-policy',
            'reference-identifier-policy',
            'reference-identifiers-missing',
            'bibliography-xrefs-unresolved',
            'funding-review-only',
            'acknowledgments-review-only',
        ], $packet['directReaderDiagnosticCodes']);
        $t->same(1, $packet['directReaderDiagnostics'][7]['details']['fundingGroupCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][7]['details']['awardGroupCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][8]['details']['acknowledgmentCount'] ?? null);
        $t->same(['r1', 'missing-ref', 'r-missing'], $packet['xrefTargets']);
        $t->same(['r1'], $packet['referenceIds']);
        $t->same(1, $packet['referenceCount']);
        $t->same(3, $packet['references'][0]['referenceCount'] ?? null);
        $t->same(['mixed-citation'], $packet['references'][0]['citationElementNames'] ?? null);
        $t->same(1, $packet['references'][0]['blockedCitationTextPayloadCount'] ?? null);
        $t->same(['missing-ref', 'r-missing'], $packet['unresolvedReferenceIds']);
        $t->same(3, $packet['resolvedBibrXrefCount']);
        $t->same(2, $packet['unresolvedBibrXrefCount']);
        $t->same(5, $packet['citationXrefCount']);
        $t->same(['r1'], $packet['resolvedCitationReferenceIds']);
        $t->same(['missing-ref', 'r-missing'], $packet['missingCitationReferenceIds']);
        $t->same(1, $packet['fundingGroupCount']);
        $t->same('fg1', $packet['fundingGroups'][0]['id'] ?? null);
        $t->same(['fs1', 'fs2'], $packet['fundingGroups'][0]['fundingSourceIds'] ?? null);
        $t->same(['ag1', 'ag2'], $packet['fundingGroups'][0]['awardGroupIds'] ?? null);
        $t->same(['r1'], $packet['fundingGroups'][0]['linkedReferenceIds'] ?? null);
        $t->same(['missing-ref'], $packet['fundingGroups'][0]['missingReferenceIds'] ?? null);
        $t->same(2, $packet['fundingSourceCount']);
        $t->same('fs1', $packet['fundingSources'][0]['id'] ?? null);
        $t->same('agency', $packet['fundingSources'][0]['sourceType'] ?? null);
        $t->same(['10.13039/100000001'], $packet['fundingSources'][0]['identifierValues'] ?? null);
        $t->same(2, $packet['awardGroupCount']);
        $t->same('ag1', $packet['awardGroups'][0]['id'] ?? null);
        $t->same('fg1', $packet['awardGroups'][0]['fundingGroupId'] ?? null);
        $t->same(['R01-42', 'R01-42'], $packet['awardGroups'][0]['awardIds'] ?? null);
        $t->same(['r1'], $packet['awardGroups'][0]['linkedReferenceIds'] ?? null);
        $t->same('ag2', $packet['awardGroups'][1]['id'] ?? null);
        $t->same(0, $packet['awardGroups'][1]['awardIdCount'] ?? null);
        $t->same(['missing-ref'], $packet['awardGroups'][1]['missingReferenceIds'] ?? null);
        $t->same(['R01-42', 'R01-42'], $packet['awardIds']);
        $t->same(['R01-42'], $packet['duplicateAwardIds']);
        $t->same([
            'duplicate-award-id',
            'duplicate-award-source-pair',
            'missing-award-id',
            'missing-funding-reference-backlink',
        ], $packet['fundingDiagnosticCodes']);
        $t->same(2, $packet['fundingDiagnostics'][0]['count'] ?? null);
        $t->same(['award-a', 'award-a-copy'], $packet['fundingDiagnostics'][0]['recordIds'] ?? null);
        $t->same('duplicate-award-source-pair', $packet['fundingDiagnostics'][1]['code'] ?? null);
        $t->same(['award-a', 'award-a-copy'], $packet['fundingDiagnostics'][1]['recordIds'] ?? null);
        $t->same('award-group', $packet['fundingDiagnostics'][2]['container'] ?? null);
        $t->same('ag2', $packet['fundingDiagnostics'][2]['id'] ?? null);
        $t->same('fg1', $packet['fundingDiagnostics'][2]['fundingGroupId'] ?? null);
        $t->same('missing-funding-reference-backlink', $packet['fundingDiagnostics'][3]['code'] ?? null);
        $t->same('missing-ref', $packet['fundingDiagnostics'][3]['referenceId'] ?? null);
        $t->same('jats-bits-funding-reference-backlinks-metadata-only', $packet['fundingReferenceBacklinkReviewPolicy']);
        $t->same(2, $packet['fundingReferenceBacklinkCount']);
        $t->same(1, $packet['missingFundingReferenceBacklinkCount']);
        $t->same(0, $packet['duplicateFundingReferenceBacklinkCount']);
        $fundingBacklinksById = [];
        foreach ($packet['fundingReferenceBacklinks'] as $backlink) {
            $fundingBacklinksById[(string) $backlink['referenceId']] = $backlink;
        }
        $t->same(true, $fundingBacklinksById['r1']['found'] ?? null);
        $t->same(false, $fundingBacklinksById['r1']['duplicate'] ?? null);
        $t->same(1, $fundingBacklinksById['r1']['linkCount'] ?? null);
        $t->same(['fg1'], $fundingBacklinksById['r1']['fundingGroupIds'] ?? null);
        $t->same(['ag1'], $fundingBacklinksById['r1']['awardGroupIds'] ?? null);
        $t->same(['R01-42'], $fundingBacklinksById['r1']['awardIds'] ?? null);
        $t->same(['fs1'], $fundingBacklinksById['r1']['fundingSourceIds'] ?? null);
        $t->same(true, $fundingBacklinksById['r1']['citationTextBlocked'] ?? null);
        $t->same(1, preg_match('/^[a-f0-9]{64}$/', (string) ($fundingBacklinksById['r1']['textSha256'] ?? '')));
        $t->same(true, $fundingBacklinksById['r1']['links'][0]['linkTextBlocked'] ?? null);
        $t->same(hash('sha256', 'funding ref'), $fundingBacklinksById['r1']['links'][0]['linkTextSha256'] ?? null);
        $t->same(false, $fundingBacklinksById['missing-ref']['found'] ?? null);
        $t->same(null, $fundingBacklinksById['missing-ref']['textSha256'] ?? null);
        $t->same(1, $packet['acknowledgmentCount']);
        $t->same(['ack1'], $packet['acknowledgmentIds']);
        $t->same('Acknowledgments', $packet['acknowledgments'][0]['title'] ?? null);
        $t->same(1, $packet['acknowledgments'][0]['paragraphCount'] ?? null);
        $t->same(true, $packet['acknowledgments'][0]['textBlocked'] ?? null);
        $t->same(['r1'], $packet['acknowledgments'][0]['linkedReferenceIds'] ?? null);
        $t->same(['r-missing'], $packet['acknowledgments'][0]['missingReferenceIds'] ?? null);
        $t->same(['r1'], $packet['acknowledgmentLinkedReferenceIds']);
        $t->same(['r-missing'], $packet['acknowledgmentMissingReferenceIds']);

        $encodedPacket = json_encode($packet, JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encodedPacket, 'Blocked Citation Payload With Secret Grant Text'), 'Expected citation payload text to stay blocked from the bounded review packet');
    },
    'summarizes jats bits award source linkage diagnostics without citation payload text' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article">
  <front>
    <article-meta>
      <title-group><article-title>Award Linkage Review</article-title></title-group>
      <funding-group id="fg-linkage">
        <award-group id="ag-nih">
          <funding-source id="fs-nih" source-type="agency">
            <institution id="inst-nih" institution-type="funder">National Institutes of Health</institution>
            <institution-id institution-id-type="ROR">https://ror.org/01cwqze88</institution-id>
            <source-id source-id-type="fundref">10.13039/100000002</source-id>
            <named-content content-type="funder-name">NIH Office of Extramural Research</named-content>
          </funding-source>
          <award-id id="award-r01" award-id-type="grant">R01-AI-123</award-id>
          <award-id id="award-r01-copy" pub-id-type="grant-number">R01-AI-123</award-id>
          <grant-num id="award-u01" specific-use="cooperative-agreement">U01-HL-456</grant-num>
          <xref ref-type="bibr" rid="funding-ref">funding reference</xref>
        </award-group>
        <award-group id="ag-missing-source">
          <award-id id="award-k99">K99-789</award-id>
          <xref ref-type="bibr" rid="missing-funding-ref">missing reference</xref>
        </award-group>
        <award-group id="ag-missing-award">
          <funding-source id="fs-orphan"><institution>Orphan Funder</institution></funding-source>
        </award-group>
      </funding-group>
    </article-meta>
  </front>
  <body><sec><title>Body</title><p>Body citation <xref ref-type="bibr" rid="funding-ref">[funding]</xref>.</p></sec></body>
  <back>
    <ref-list><ref id="funding-ref"><label>F1</label><mixed-citation>Blocked Award Source Citation Secret</mixed-citation></ref></ref-list>
  </back>
</article>
XML, 'JATS award source linkage XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($dom);

        $t->same(false, $packet['directReaderParity']);
        $t->same(3, $packet['awardGroupCount']);
        $t->same(2, $packet['fundingSourceCount']);
        $t->same(['fs-nih', 'fs-orphan'], $packet['fundingSourceIds']);
        $t->same(2, $packet['fundingSourceIdentifierCount']);
        $t->same(['ROR', 'fundref'], $packet['fundingSourceIdentifierTypes']);
        $t->same('fs-nih', $packet['fundingSourceIdentifierRecords'][0]['fundingSourceId'] ?? null);
        $t->same('institution-id', $packet['fundingSourceIdentifierRecords'][0]['element'] ?? null);
        $t->same('https://ror.org/01cwqze88', $packet['fundingSourceIdentifierRecords'][0]['value'] ?? null);
        $t->same('source-id', $packet['fundingSourceIdentifierRecords'][1]['element'] ?? null);
        $t->same('10.13039/100000002', $packet['fundingSourceIdentifierRecords'][1]['value'] ?? null);
        $t->same(2, $packet['fundingInstitutionCount']);
        $t->same(['National Institutes of Health', 'Orphan Funder'], $packet['fundingInstitutionNames']);
        $t->same(['National Institutes of Health', 'NIH Office of Extramural Research'], $packet['fundingSources'][0]['funderNames'] ?? null);
        $t->same(['R01-AI-123', 'R01-AI-123', 'U01-HL-456', 'K99-789'], $packet['awardIds']);
        $t->same(4, $packet['awardIdCount']);
        $t->same('award-id', $packet['awardIdRecords'][0]['element'] ?? null);
        $t->same('award-id-type', $packet['awardIdRecords'][0]['typeSourceAttribute'] ?? null);
        $t->same('pub-id-type', $packet['awardIdRecords'][1]['typeSourceAttribute'] ?? null);
        $t->same('grant-num', $packet['awardIdRecords'][2]['element'] ?? null);
        $t->same('specific-use', $packet['awardIdRecords'][2]['typeSourceAttribute'] ?? null);
        $t->same('ag-nih', $packet['awardIdRecords'][2]['awardGroupId'] ?? null);
        $t->same(3, $packet['awardSourcePairCount']);
        $t->same('R01-AI-123', $packet['awardSourcePairs'][0]['awardId'] ?? null);
        $t->same('fs-nih', $packet['awardSourcePairs'][0]['fundingSourceId'] ?? null);
        $t->same(['https://ror.org/01cwqze88', '10.13039/100000002'], $packet['awardSourcePairs'][0]['fundingSourceIdentifierValues'] ?? null);
        $t->same(['National Institutes of Health'], $packet['awardSourcePairs'][0]['fundingSourceInstitutionNames'] ?? null);
        $t->same(['funding-ref'], $packet['awardSourcePairs'][0]['linkedReferenceIds'] ?? null);
        $t->same([], $packet['awardSourcePairs'][0]['missingReferenceIds'] ?? null);
        $t->same(1, $packet['duplicateAwardSourcePairCount']);
        $t->same('R01-AI-123', $packet['duplicateAwardSourcePairs'][0]['awardId'] ?? null);
        $t->same('fs-nih', $packet['duplicateAwardSourcePairs'][0]['fundingSourceId'] ?? null);
        $t->same(2, $packet['duplicateAwardSourcePairs'][0]['count'] ?? null);
        $t->same(['award-r01', 'award-r01-copy'], $packet['duplicateAwardSourcePairs'][0]['awardRecordIds'] ?? null);
        $t->same(['funding-ref'], $packet['duplicateAwardSourcePairs'][0]['linkedReferenceIds'] ?? null);
        $t->same([
            'duplicate-award-id',
            'duplicate-award-source-pair',
            'missing-award-id',
            'missing-funding-source',
            'missing-funding-reference-backlink',
        ], $packet['fundingDiagnosticCodes']);
        $t->same('duplicate-award-source-pair', $packet['fundingDiagnostics'][1]['code'] ?? null);
        $t->same(['award-r01', 'award-r01-copy'], $packet['fundingDiagnostics'][1]['recordIds'] ?? null);
        $t->same('ag-missing-award', $packet['fundingDiagnostics'][2]['id'] ?? null);
        $t->same('ag-missing-source', $packet['fundingDiagnostics'][3]['id'] ?? null);
        $t->same(['K99-789'], $packet['fundingDiagnostics'][3]['awardIds'] ?? null);
        $t->same(['missing-funding-ref'], $packet['fundingDiagnostics'][3]['missingReferenceIds'] ?? null);
        $t->same('missing-funding-reference-backlink', $packet['fundingDiagnostics'][4]['code'] ?? null);
        $t->same('missing-funding-ref', $packet['fundingDiagnostics'][4]['referenceId'] ?? null);
        $t->same(2, $packet['fundingReferenceBacklinkCount']);
        $t->same(1, $packet['missingFundingReferenceBacklinkCount']);
        $t->same(0, $packet['duplicateFundingReferenceBacklinkCount']);
        $t->same(['funding-ref'], $packet['resolvedCitationReferenceIds']);
        $t->same(['missing-funding-ref'], $packet['missingCitationReferenceIds']);

        $encodedPacket = json_encode($packet, JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encodedPacket, 'Blocked Award Source Citation Secret'), 'Expected citation payload text to stay blocked from award/source linkage diagnostics');
    },
    'diagnoses jats bits funding conflict metadata with sanitized reference summaries' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article">
  <front>
    <article-meta>
      <title-group><article-title>Funding Conflict Review</article-title></title-group>
      <funding-group id="fg-conflict">
        <award-group id="ag-alpha">
          <funding-source id="fs-alpha" source-type="agency">
            <institution-id institution-id-type="ROR">https://ror.org/conflict</institution-id>
            <source-id source-id-type="fundref">10.13039/alpha</source-id>
            <institution>Alpha Foundation</institution>
          </funding-source>
          <award-id id="award-alpha">PL-42</award-id>
          <xref id="funding-xref" ref-type="bibr" rid="fund-ref missing-ref">funding reference</xref>
        </award-group>
        <award-group id="ag-beta">
          <funding-source id="fs-beta" source-type="agency">
            <institution-id institution-id-type="ROR">https://ror.org/conflict</institution-id>
            <institution>Beta Institute</institution>
          </funding-source>
          <award-id id="award-beta">PL-42</award-id>
          <xref id="funding-xref-repeat" ref-type="bibr" rid="fund-ref">repeat funding reference</xref>
        </award-group>
        <award-group id="ag-sibling">
          <funding-source id="fs-sibling">Sibling Source</funding-source>
          <institution-id institution-id-type="ROR">https://ror.org/sibling</institution-id>
          <award-id id="award-sibling">SIBLING-1</award-id>
        </award-group>
        <award-group id="ag-nameless">
          <funding-source id="fs-nameless"><institution-id institution-id-type="ROR">https://ror.org/no-name</institution-id></funding-source>
          <award-id id="award-nameless">NONAME-1</award-id>
        </award-group>
        <award-group id="ag-missing-source">
          <award-id id="award-orphan">ORPHAN-1</award-id>
        </award-group>
        <award-group id="ag-missing-award">
          <funding-source id="fs-orphan">Source Without Award</funding-source>
        </award-group>
      </funding-group>
    </article-meta>
  </front>
  <body><sec><title>Body</title><p>Body only.</p></sec></body>
  <back>
    <ref-list><ref id="fund-ref"><label>F1</label><mixed-citation>Blocked Conflict Citation Secret</mixed-citation></ref></ref-list>
  </back>
</article>
XML, 'JATS funding conflict metadata XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($dom);

        $t->same(false, $packet['directReaderParity']);
        $t->same(6, $packet['awardGroupCount']);
        $t->same(5, $packet['fundingSourceCount']);
        $t->same(4, $packet['fundingSourceIdentifierCount']);
        $t->same(1, $packet['duplicateFunderIdentifierCount']);
        $t->same('https://ror.org/conflict', $packet['duplicateFunderIdentifiers'][0]['value'] ?? null);
        $t->same(['fs-alpha', 'fs-beta'], $packet['duplicateFunderIdentifiers'][0]['fundingSourceIds'] ?? null);
        $t->same(1, $packet['conflictingAwardSourcePairCount']);
        $t->same('PL-42', $packet['conflictingAwardSourcePairs'][0]['awardId'] ?? null);
        $t->same(['fs-alpha', 'fs-beta'], $packet['conflictingAwardSourcePairs'][0]['fundingSourceIds'] ?? null);
        $t->same(['ag-alpha', 'ag-beta'], $packet['conflictingAwardSourcePairs'][0]['awardGroupIds'] ?? null);
        $t->same(2, $packet['fundingInstitutionSourceMismatchCount']);
        $t->same(['missing-institution-name', 'outside-funding-source'], array_map(
            static fn (array $mismatch): string => (string) $mismatch['reason'],
            $packet['fundingInstitutionSourceMismatches']
        ));
        $t->same('fs-nameless', $packet['fundingInstitutionSourceMismatches'][0]['fundingSourceId'] ?? null);
        $t->same('ag-sibling', $packet['fundingInstitutionSourceMismatches'][1]['awardGroupId'] ?? null);
        $t->same(2, $packet['fundingLinkedReferenceCount']);
        $t->same(['fund-ref', 'missing-ref'], $packet['fundingLinkedReferences'][0]['targets'] ?? null);
        $t->same(['fund-ref'], $packet['fundingLinkedReferences'][0]['linkedReferenceIds'] ?? null);
        $t->same(['missing-ref'], $packet['fundingLinkedReferences'][0]['missingReferenceIds'] ?? null);
        $t->same(true, $packet['fundingLinkedReferences'][0]['linkTextBlocked'] ?? null);
        $t->same(['PL-42'], $packet['fundingLinkedReferences'][0]['awardIds'] ?? null);
        $t->same(['PL-42'], $packet['fundingLinkedReferences'][0]['conflictingAwardIds'] ?? null);
        $t->same(['fs-alpha'], $packet['fundingLinkedReferences'][0]['fundingSourceIds'] ?? null);
        $t->same(true, $packet['fundingLinkedReferences'][0]['fundingSourceTextDigests'][0]['textBlocked'] ?? null);
        $t->same('F1', $packet['fundingLinkedReferences'][0]['references'][0]['label'] ?? null);
        $t->same(true, $packet['fundingLinkedReferences'][0]['references'][0]['citationTextBlocked'] ?? null);
        $t->same(false, $packet['fundingLinkedReferences'][0]['references'][1]['found'] ?? null);
        $t->same(['fund-ref'], $packet['fundingLinkedReferences'][1]['targets'] ?? null);
        $t->same(['fs-beta'], $packet['fundingLinkedReferences'][1]['fundingSourceIds'] ?? null);
        $t->same(['PL-42'], $packet['fundingLinkedReferences'][1]['conflictingAwardIds'] ?? null);
        $t->same(2, $packet['fundingReferenceBacklinkCount']);
        $t->same(1, $packet['missingFundingReferenceBacklinkCount']);
        $t->same(1, $packet['duplicateFundingReferenceBacklinkCount']);
        $conflictBacklinksById = [];
        foreach ($packet['fundingReferenceBacklinks'] as $backlink) {
            $conflictBacklinksById[(string) $backlink['referenceId']] = $backlink;
        }
        $t->same(true, $conflictBacklinksById['fund-ref']['found'] ?? null);
        $t->same(true, $conflictBacklinksById['fund-ref']['duplicate'] ?? null);
        $t->same(2, $conflictBacklinksById['fund-ref']['linkCount'] ?? null);
        $t->same(['fg-conflict'], $conflictBacklinksById['fund-ref']['fundingGroupIds'] ?? null);
        $t->same(['ag-alpha', 'ag-beta'], $conflictBacklinksById['fund-ref']['awardGroupIds'] ?? null);
        $t->same(['PL-42'], $conflictBacklinksById['fund-ref']['awardIds'] ?? null);
        $t->same(['fs-alpha', 'fs-beta'], $conflictBacklinksById['fund-ref']['fundingSourceIds'] ?? null);
        $t->same(['PL-42'], $conflictBacklinksById['fund-ref']['conflictingAwardIds'] ?? null);
        $t->same(1, $conflictBacklinksById['fund-ref']['awardSourceConflictCount'] ?? null);
        $t->same(true, $conflictBacklinksById['fund-ref']['citationTextBlocked'] ?? null);
        $t->same(1, preg_match('/^[a-f0-9]{64}$/', (string) ($conflictBacklinksById['fund-ref']['textSha256'] ?? '')));
        $t->same(hash('sha256', 'funding reference'), $conflictBacklinksById['fund-ref']['links'][0]['linkTextSha256'] ?? null);
        $t->same(hash('sha256', 'repeat funding reference'), $conflictBacklinksById['fund-ref']['links'][1]['linkTextSha256'] ?? null);
        $t->same(false, $conflictBacklinksById['missing-ref']['found'] ?? null);
        $t->same([
            'duplicate-award-id',
            'duplicate-funder-identifier',
            'conflicting-award-source-pair',
            'institution-id-funding-source-mismatch',
            'missing-award-id',
            'missing-funding-source',
            'missing-funding-reference-backlink',
            'duplicate-funding-reference-backlink',
        ], array_values(array_unique($packet['fundingDiagnosticCodes'])));
        $t->same(9, $packet['fundingDiagnosticCount']);

        $encodedPacket = json_encode($packet, JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encodedPacket, 'Blocked Conflict Citation Secret'), 'Expected citation payload text to stay blocked from funding conflict diagnostics');
    },
    'orders jats funding statement backlink collisions with bounded duplicate provenance' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article">
  <front>
    <article-meta>
      <title-group><article-title>JATS Funding Statement Collision Review</article-title></title-group>
      <funding-group id="fg-statement-collision">
        <award-group id="ag-alpha">
          <funding-source id="fs-alpha"><institution>Alpha JATS Foundation</institution></funding-source>
          <award-id id="award-alpha">JATS-COLLIDE</award-id>
          <funding-statement>Alpha statement secret payload <xref id="xref-alpha" ref-type="bibr" rid="m-missing r-shared">alpha funding link</xref> remains hidden.</funding-statement>
        </award-group>
        <award-group id="ag-beta">
          <funding-source id="fs-beta"><institution>Beta JATS Institute</institution></funding-source>
          <award-id id="award-beta">JATS-COLLIDE</award-id>
          <funding-statement>Beta statement secret payload <xref id="xref-beta" ref-type="bibr" rid="r-shared">beta funding link</xref> remains hidden.</funding-statement>
        </award-group>
        <award-group id="ag-gamma">
          <funding-source id="fs-gamma"><institution>Gamma JATS Council</institution></funding-source>
          <award-id id="award-gamma">JATS-OTHER</award-id>
          <funding-statement>Gamma statement secret payload <xref id="xref-gamma" ref-type="bibr" rid="z-found">gamma funding link</xref> remains hidden.</funding-statement>
        </award-group>
      </funding-group>
    </article-meta>
  </front>
  <body><sec><title>Body</title><p>Body remains outside funding review.</p></sec></body>
  <back>
    <ref-list>
      <ref id="r-shared"><label>S</label><mixed-citation>Shared JATS Citation Secret</mixed-citation></ref>
      <ref id="z-found"><label>Z</label><mixed-citation>Other JATS Citation Secret</mixed-citation></ref>
    </ref-list>
  </back>
</article>
XML, 'JATS funding statement collision XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($dom);
        $encodedPacket = json_encode($packet, JSON_THROW_ON_ERROR);
        $backlinks = $packet['fundingReferenceBacklinks'];

        $alphaStatementText = 'Alpha statement secret payload alpha funding link remains hidden.';
        $betaStatementText = 'Beta statement secret payload beta funding link remains hidden.';
        $gammaStatementText = 'Gamma statement secret payload gamma funding link remains hidden.';
        $sharedCitationText = 'SShared JATS Citation Secret';

        $t->same(false, $packet['directReaderParity']);
        $t->same(3, $packet['awardGroupCount']);
        $t->same(3, $packet['fundingLinkedReferenceCount']);
        $t->same(3, $packet['fundingReferenceBacklinkCount']);
        $t->same(1, $packet['missingFundingReferenceBacklinkCount']);
        $t->same(1, $packet['duplicateFundingReferenceBacklinkCount']);
        $t->same(['m-missing', 'r-shared', 'z-found'], array_column($backlinks, 'referenceId'));
        $t->same(false, $backlinks[0]['found'] ?? null);
        $t->same(false, $backlinks[0]['duplicate'] ?? null);
        $t->same(['ag-alpha'], $backlinks[0]['awardGroupIds'] ?? null);
        $t->same(['JATS-COLLIDE'], $backlinks[0]['awardIds'] ?? null);
        $t->same(['fs-alpha'], $backlinks[0]['fundingSourceIds'] ?? null);
        $t->same(null, $backlinks[0]['textSha256'] ?? null);
        $t->same(hash('sha256', 'alpha funding link'), $backlinks[0]['links'][0]['linkTextSha256'] ?? null);
        $t->same(true, $backlinks[1]['found'] ?? null);
        $t->same(true, $backlinks[1]['duplicate'] ?? null);
        $t->same(2, $backlinks[1]['linkCount'] ?? null);
        $t->same(['ag-alpha', 'ag-beta'], $backlinks[1]['awardGroupIds'] ?? null);
        $t->same(['JATS-COLLIDE'], $backlinks[1]['awardIds'] ?? null);
        $t->same(['fs-alpha', 'fs-beta'], $backlinks[1]['fundingSourceIds'] ?? null);
        $t->same(['JATS-COLLIDE'], $backlinks[1]['conflictingAwardIds'] ?? null);
        $t->same(1, $backlinks[1]['awardSourceConflictCount'] ?? null);
        $t->same(2, count($backlinks[1]['duplicateLinkProvenance'] ?? []));
        $t->same('xref-alpha', $backlinks[1]['duplicateLinkProvenance'][0]['id'] ?? null);
        $t->same(['fs-alpha'], $backlinks[1]['duplicateLinkProvenance'][0]['fundingSourceIds'] ?? null);
        $t->same('xref-beta', $backlinks[1]['duplicateLinkProvenance'][1]['id'] ?? null);
        $t->same(['fs-beta'], $backlinks[1]['duplicateLinkProvenance'][1]['fundingSourceIds'] ?? null);
        $t->same(hash('sha256', 'beta funding link'), $backlinks[1]['duplicateLinkProvenance'][1]['linkTextSha256'] ?? null);
        $t->same(strlen($sharedCitationText), $backlinks[1]['textLength'] ?? null);
        $t->same(hash('sha256', $sharedCitationText), $backlinks[1]['textSha256'] ?? null);
        $t->same(true, $backlinks[1]['citationTextBlocked'] ?? null);
        $t->same('z-found', $backlinks[2]['referenceId'] ?? null);
        $t->same(false, $backlinks[2]['duplicate'] ?? null);
        $t->same([], $backlinks[2]['duplicateLinkProvenance'] ?? null);
        $t->same(strlen($alphaStatementText), $packet['awardGroups'][0]['fundingStatementTextLength'] ?? null);
        $t->same(hash('sha256', $alphaStatementText), $packet['awardGroups'][0]['fundingStatementTextSha256'] ?? null);
        $t->same(strlen($betaStatementText), $packet['awardGroups'][1]['fundingStatementTextLength'] ?? null);
        $t->same(hash('sha256', $betaStatementText), $packet['awardGroups'][1]['fundingStatementTextSha256'] ?? null);
        $t->same(strlen($gammaStatementText), $packet['awardGroups'][2]['fundingStatementTextLength'] ?? null);
        $t->same(hash('sha256', $gammaStatementText), $packet['awardGroups'][2]['fundingStatementTextSha256'] ?? null);
        $t->same([
            'duplicate-award-id',
            'conflicting-award-source-pair',
            'missing-funding-reference-backlink',
            'duplicate-funding-reference-backlink',
        ], $packet['fundingDiagnosticCodes']);
        $t->same('m-missing', $packet['fundingDiagnostics'][2]['referenceId'] ?? null);
        $t->same('r-shared', $packet['fundingDiagnostics'][3]['referenceId'] ?? null);
        $t->same(2, count($packet['fundingDiagnostics'][3]['duplicateLinkProvenance'] ?? []));
        $t->true(!str_contains($encodedPacket, 'secret payload'), 'Expected funding statement text to stay blocked from JATS packet JSON');
        $t->true(!str_contains($encodedPacket, 'Shared JATS Citation Secret'), 'Expected citation text to stay blocked from JATS packet JSON');
    },
    'orders bits funding backlink collisions with duplicate provenance and bounded payloads' => static function (TestRunner $t): void {
        $bits = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book book-type="edited-book" dtd-version="2.1" xml:lang="en">
  <book-meta>
    <book-title-group><book-title>Funding Collision Review</book-title></book-title-group>
    <funding-group id="fg-collision">
      <award-group id="ag-alpha">
        <funding-source id="fs-alpha"><institution>Alpha Foundation</institution></funding-source>
        <award-id id="award-alpha">COLLIDE-1</award-id>
        <funding-statement>Alpha funding secret-one payload <xref id="xref-alpha" ref-type="bibr" rid="z-missing r-shared">shared funding citation</xref> remains hidden.</funding-statement>
      </award-group>
      <award-group id="ag-beta">
        <funding-source id="fs-beta"><institution>Beta Foundation</institution></funding-source>
        <award-id id="award-beta">COLLIDE-1</award-id>
        <funding-statement>Beta funding secret-two payload <xref id="xref-beta" ref-type="bibr" rid="r-shared">second shared citation</xref> remains hidden.</funding-statement>
      </award-group>
      <award-group id="ag-gamma">
        <funding-source id="fs-gamma"><institution>Gamma Foundation</institution></funding-source>
        <award-id id="award-gamma">OTHER-2</award-id>
        <funding-statement>Gamma funding secret-three payload <xref id="xref-gamma" ref-type="bibr" rid="a-found">single citation</xref> remains hidden.</funding-statement>
      </award-group>
    </funding-group>
  </book-meta>
  <book-body><book-part><body><sec><title>Body</title><p>Review body.</p></sec></body></book-part></book-body>
  <back>
    <ref-list>
      <ref id="a-found"><label>A</label><mixed-citation>Single Found Citation Payload must stay hidden</mixed-citation></ref>
      <ref id="r-shared"><label>S</label><mixed-citation>Shared Citation Payload must stay hidden</mixed-citation></ref>
    </ref-list>
  </back>
</book>
XML, 'BITS funding backlink collision XML', preserveWhiteSpace: false);

        $packet = XmlHtmlDom::summarizeJatsFrontMatter($bits, 'bits');
        $encodedPacket = json_encode($packet, JSON_THROW_ON_ERROR);
        $backlinks = $packet['fundingReferenceBacklinks'];

        $statementOneText = 'Alpha funding secret-one payload shared funding citation remains hidden.';
        $statementTwoText = 'Beta funding secret-two payload second shared citation remains hidden.';
        $statementThreeText = 'Gamma funding secret-three payload single citation remains hidden.';
        $sharedCitationText = 'SShared Citation Payload must stay hidden';

        $t->same('bits', $packet['format']);
        $t->same(false, $packet['directReaderParity']);
        $t->same(3, $packet['awardGroupCount']);
        $t->same(3, $packet['fundingReferenceBacklinkCount']);
        $t->same(1, $packet['missingFundingReferenceBacklinkCount']);
        $t->same(1, $packet['duplicateFundingReferenceBacklinkCount']);
        $t->same(['z-missing', 'r-shared', 'a-found'], array_column($backlinks, 'referenceId'));
        $t->same(false, $backlinks[0]['found'] ?? null);
        $t->same(false, $backlinks[0]['duplicate'] ?? null);
        $t->same(['fg-collision'], $backlinks[0]['fundingGroupIds'] ?? null);
        $t->same(['ag-alpha'], $backlinks[0]['awardGroupIds'] ?? null);
        $t->same(['COLLIDE-1'], $backlinks[0]['awardIds'] ?? null);
        $t->same(['fs-alpha'], $backlinks[0]['fundingSourceIds'] ?? null);
        $t->same(hash('sha256', 'shared funding citation'), $backlinks[0]['links'][0]['linkTextSha256'] ?? null);
        $t->same(null, $backlinks[0]['textSha256'] ?? null);
        $t->same(true, $backlinks[1]['found'] ?? null);
        $t->same(true, $backlinks[1]['duplicate'] ?? null);
        $t->same(2, $backlinks[1]['linkCount'] ?? null);
        $t->same(['fg-collision'], $backlinks[1]['fundingGroupIds'] ?? null);
        $t->same(['ag-alpha', 'ag-beta'], $backlinks[1]['awardGroupIds'] ?? null);
        $t->same(['COLLIDE-1'], $backlinks[1]['awardIds'] ?? null);
        $t->same(['fs-alpha', 'fs-beta'], $backlinks[1]['fundingSourceIds'] ?? null);
        $t->same(['COLLIDE-1'], $backlinks[1]['conflictingAwardIds'] ?? null);
        $t->same(1, $backlinks[1]['awardSourceConflictCount'] ?? null);
        $t->same(2, count($backlinks[1]['duplicateLinkProvenance'] ?? []));
        $t->same('xref-alpha', $backlinks[1]['duplicateLinkProvenance'][0]['id'] ?? null);
        $t->same(['fs-alpha'], $backlinks[1]['duplicateLinkProvenance'][0]['fundingSourceIds'] ?? null);
        $t->same('xref-beta', $backlinks[1]['duplicateLinkProvenance'][1]['id'] ?? null);
        $t->same(['fs-beta'], $backlinks[1]['duplicateLinkProvenance'][1]['fundingSourceIds'] ?? null);
        $t->same(hash('sha256', 'second shared citation'), $backlinks[1]['duplicateLinkProvenance'][1]['linkTextSha256'] ?? null);
        $t->same(strlen($sharedCitationText), $backlinks[1]['textLength'] ?? null);
        $t->same(hash('sha256', $sharedCitationText), $backlinks[1]['textSha256'] ?? null);
        $t->same(true, $backlinks[1]['citationTextBlocked'] ?? null);
        $t->same('a-found', $backlinks[2]['referenceId'] ?? null);
        $t->same(true, $backlinks[2]['found'] ?? null);
        $t->same(false, $backlinks[2]['duplicate'] ?? null);
        $t->same([], $backlinks[2]['duplicateLinkProvenance'] ?? null);
        $t->same(true, $packet['awardGroups'][0]['fundingStatementTextBlocked'] ?? null);
        $t->same(strlen($statementOneText), $packet['awardGroups'][0]['fundingStatementTextLength'] ?? null);
        $t->same(hash('sha256', $statementOneText), $packet['awardGroups'][0]['fundingStatementTextSha256'] ?? null);
        $t->same(strlen($statementTwoText), $packet['awardGroups'][1]['fundingStatementTextLength'] ?? null);
        $t->same(hash('sha256', $statementTwoText), $packet['awardGroups'][1]['fundingStatementTextSha256'] ?? null);
        $t->same(strlen($statementThreeText), $packet['awardGroups'][2]['fundingStatementTextLength'] ?? null);
        $t->same(hash('sha256', $statementThreeText), $packet['awardGroups'][2]['fundingStatementTextSha256'] ?? null);
        $t->same([
            'duplicate-award-id',
            'conflicting-award-source-pair',
            'missing-funding-reference-backlink',
            'duplicate-funding-reference-backlink',
        ], $packet['fundingDiagnosticCodes']);
        $t->same('z-missing', $packet['fundingDiagnostics'][2]['referenceId'] ?? null);
        $t->same('r-shared', $packet['fundingDiagnostics'][3]['referenceId'] ?? null);
        $t->same(2, count($packet['fundingDiagnostics'][3]['duplicateLinkProvenance'] ?? []));
        $t->true(!str_contains($encodedPacket, 'secret-one payload'), 'Expected funding statement text to stay blocked from BITS packet JSON');
        $t->true(!str_contains($encodedPacket, 'Shared Citation Payload'), 'Expected citation text to stay blocked from BITS packet JSON');
    },
    'summarizes jats bits publication metadata links history permissions and serial identifiers' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article" xmlns:xlink="http://www.w3.org/1999/xlink">
  <front>
    <journal-meta>
      <journal-id journal-id-type="nlm-ta">PLib Rev</journal-id>
      <journal-title-group>
        <journal-title>Port Libs Review</journal-title>
        <abbrev-journal-title abbrev-type="publisher">PLR</abbrev-journal-title>
      </journal-title-group>
      <issn publication-format="print">1111-2222</issn>
      <issn publication-format="electronic">3333-4444</issn>
      <issn-l>1111-2222</issn-l>
      <publisher id="pub-journal"><publisher-name>Port Libs Press</publisher-name><publisher-loc>New York</publisher-loc></publisher>
    </journal-meta>
    <article-meta>
      <article-id pub-id-type="doi">10.5555/pubmeta.42</article-id>
      <title-group><article-title>Publication Metadata Review</article-title></title-group>
      <volume>12</volume>
      <issue>3</issue>
      <issue-title>Special Matrix Chunk</issue-title>
      <fpage>10</fpage>
      <lpage>20</lpage>
      <page-range>10-20</page-range>
      <elocation-id>e42</elocation-id>
      <pub-date date-type="epub"><year>2026</year><month>06</month><day>14</day></pub-date>
      <history>
        <date date-type="received"><year>2026</year><month>01</month><day>02</day></date>
        <date date-type="accepted"><year>2026</year><month>05</month><day>09</day></date>
      </history>
      <permissions id="perm-article">
        <copyright-statement>Copyright 2026 Port Libs</copyright-statement>
        <copyright-year>2026</copyright-year>
        <copyright-holder>Port Libs</copyright-holder>
        <license license-type="open-access" xlink:href="https://creativecommons.org/licenses/by/4.0/"><license-p>CC BY 4.0</license-p></license>
        <license license-type="open-access" xlink:href="https://creativecommons.org/licenses/by/4.0/"><license-p>CC BY 4.0 duplicate</license-p></license>
        <license-ref xlink:href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0 reference</license-ref>
      </permissions>
      <self-uri content-type="pdf" mimetype="application" mime-subtype="pdf" xlink:href="article.pdf">PDF</self-uri>
      <related-article related-article-type="corrected-article" xlink:href="https://doi.org/10.5555/original"/>
      <related-object object-id-type="doi" xlink:href="10.5555/dataset.1">Dataset</related-object>
    </article-meta>
  </front>
  <body><sec><title>Body</title><p>Body.</p></sec></body>
</article>
XML, 'JATS publication metadata XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($dom);

        $t->same(false, $packet['directReaderParity']);
        $t->same('jats-bits-publication-metadata-review-only', $packet['publicationMetadataReviewPolicy']);
        $t->same([
            'serial-identifiers',
            'publisher-provenance',
            'front-matter-permissions',
            'history-dates',
            'article-link-targets',
            'issue-page-metadata',
        ], $packet['publicationMetadataMappedCaseKinds']);
        $t->same(6, $packet['publicationMetadataMappedCaseCount']);
        $t->same([
            'publication-serial-identifiers-review-only',
            'publication-publisher-provenance-review-only',
            'publication-permissions-review-only',
            'duplicate-license',
            'publication-history-dates-review-only',
            'publication-link-targets-review-only',
            'publication-issue-page-metadata-review-only',
        ], $packet['publicationMetadataDiagnosticCodes']);
        $t->same(7, $packet['publicationMetadataDiagnosticCount']);
        $t->same(4, $packet['serialIdentifierRecordCount']);
        $t->same(['nlm-ta', 'print', 'electronic'], $packet['serialIdentifierTypes']);
        $t->same('journal-id', $packet['serialIdentifierRecords'][0]['element'] ?? null);
        $t->same('PLib Rev', $packet['serialIdentifierRecords'][0]['value'] ?? null);
        $t->same('print', $packet['serialIdentifierRecords'][1]['type'] ?? null);
        $t->same('1111-2222', $packet['serialIdentifierRecords'][1]['value'] ?? null);
        $t->same(2, $packet['serialTitleRecordCount']);
        $t->same('Port Libs Review', $packet['serialTitleRecords'][0]['value'] ?? null);
        $t->same('publisher', $packet['serialTitleRecords'][1]['type'] ?? null);
        $t->same(1, $packet['publisherRecordCount']);
        $t->same(['Port Libs Press'], $packet['publisherNames']);
        $t->same('New York', $packet['publisherRecords'][0]['location'] ?? null);
        $t->same(7, $packet['issuePageMetadataFieldCount']);
        $t->same('12', $packet['issuePageMetadata']['volume'] ?? null);
        $t->same('3', $packet['issuePageMetadata']['issue'] ?? null);
        $t->same('Special Matrix Chunk', $packet['issuePageMetadata']['issueTitle'] ?? null);
        $t->same('10', $packet['issuePageMetadata']['firstPage'] ?? null);
        $t->same('20', $packet['issuePageMetadata']['lastPage'] ?? null);
        $t->same('10-20', $packet['issuePageMetadata']['pageRange'] ?? null);
        $t->same('e42', $packet['issuePageMetadata']['elocationId'] ?? null);
        $t->same(2, $packet['historyDateCount']);
        $t->same('received', $packet['historyDates'][0]['type'] ?? null);
        $t->same('2026-01-02', $packet['historyDates'][0]['iso'] ?? null);
        $t->same('accepted', $packet['historyDates'][1]['type'] ?? null);
        $t->same('2026-05-09', $packet['historyDates'][1]['iso'] ?? null);
        $t->same(3, $packet['articleLinkRecordCount']);
        $t->same(['article.pdf', 'https://doi.org/10.5555/original', '10.5555/dataset.1'], $packet['articleLinkTargets']);
        $t->same('relative', $packet['articleLinkRecords'][0]['targetKind'] ?? null);
        $t->same('application', $packet['articleLinkRecords'][0]['mimeType'] ?? null);
        $t->same('pdf', $packet['articleLinkRecords'][0]['mimeSubtype'] ?? null);
        $t->same('absolute-uri', $packet['articleLinkRecords'][1]['targetKind'] ?? null);
        $t->same('corrected-article', $packet['articleLinkRecords'][1]['type'] ?? null);
        $t->same('relative', $packet['articleLinkRecords'][2]['targetKind'] ?? null);
        $t->same('doi', $packet['articleLinkRecords'][2]['type'] ?? null);
        $t->same(1, $packet['frontMatterPermissionCount']);
        $t->same(2, $packet['frontMatterLicenseCount']);
        $t->same(1, $packet['frontMatterLicenseRefCount']);
        $t->same(1, $packet['frontMatterCopyrightStatementCount']);
        $t->same(['duplicate-license'], $packet['frontMatterPermissionIssueCodes']);
        $t->same('front-matter', $packet['frontMatterPermissionIssues'][0]['scope'] ?? null);
        $t->same('href:https://creativecommons.org/licenses/by/4.0/', $packet['frontMatterPermissionIssues'][0]['licenseKey'] ?? null);
        $t->same(2, $packet['frontMatterPermissionIssues'][0]['count'] ?? null);

        $bits = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book book-type="monograph">
  <book-meta>
    <title-group><book-title>BITS Publication Metadata</book-title></title-group>
    <isbn content-type="print">978-1-55555-200-4</isbn>
    <publisher id="pub-book"><publisher-name>Book Press</publisher-name><publisher-loc>London</publisher-loc></publisher>
  </book-meta>
  <book-body><book-part><body><sec><title>Chapter</title><p>Body.</p></sec></body></book-part></book-body>
</book>
XML, 'BITS publication metadata XML', preserveWhiteSpace: false);
        $bitsPacket = XmlHtmlDom::summarizeJatsFrontMatter($bits, 'bits');

        $t->same(false, $bitsPacket['directReaderParity']);
        $t->same(1, $bitsPacket['serialTitleRecordCount']);
        $t->same('book-title', $bitsPacket['serialTitleRecords'][0]['element'] ?? null);
        $t->same('BITS Publication Metadata', $bitsPacket['serialTitleRecords'][0]['value'] ?? null);
        $t->same(1, $bitsPacket['serialIdentifierRecordCount']);
        $t->same('isbn', $bitsPacket['serialIdentifierRecords'][0]['element'] ?? null);
        $t->same('print', $bitsPacket['serialIdentifierRecords'][0]['type'] ?? null);
        $t->same('978-1-55555-200-4', $bitsPacket['serialIdentifierRecords'][0]['value'] ?? null);
        $t->same(['Book Press'], $bitsPacket['publisherNames']);
        $t->same('London', $bitsPacket['publisherRecords'][0]['location'] ?? null);
        $t->same(['serial-identifiers', 'publisher-provenance'], $bitsPacket['publicationMetadataMappedCaseKinds']);
        $t->same(2, $bitsPacket['publicationMetadataMappedCaseCount']);
        json_encode([$packet, $bitsPacket], JSON_THROW_ON_ERROR);
    },
    'summarizes jats bits figure label caption and title metadata diagnostics' => static function (TestRunner $t): void {
        $jats = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="review">
  <front><article-meta><title-group><article-title>Figure metadata</article-title></title-group></article-meta></front>
  <body>
    <fig id="complete"><label>Fig. 1</label><caption><title>Workflow</title><p>Caption body.</p></caption></fig>
    <fig id="caption-only"><caption><p>Caption only.</p></caption></fig>
  </body>
</article>
XML, 'JATS figure metadata XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($jats);

        $t->same(false, $packet['directReaderParity']);
        $t->same([
            'direct-reader-unsupported',
            'figures-review-only',
            'figure-label-metadata-missing',
            'figure-title-metadata-missing',
        ], $packet['directReaderDiagnosticCodes']);
        $t->same(['complete', 'caption-only'], $packet['figureIds']);
        $t->same(2, $packet['figureCount']);
        $t->same([
            'total' => 2,
            'withLabel' => 1,
            'withCaption' => 2,
            'withTitle' => 1,
            'missingLabel' => 1,
            'missingCaption' => 0,
            'missingTitle' => 1,
            'incomplete' => 1,
        ], $packet['figureMetadataCounts']);
        $t->same(['Fig. 1'], $packet['figureLabels']);
        $t->same(['Workflow'], $packet['figureTitles']);
        $t->same(['Workflow Caption body.', 'Caption only.'], $packet['figureCaptionTexts']);
        $t->same('complete', $packet['figures'][0]['id'] ?? null);
        $t->same('Fig. 1', $packet['figures'][0]['label'] ?? null);
        $t->same('Workflow', $packet['figures'][0]['title'] ?? null);
        $t->same('Workflow Caption body.', $packet['figures'][0]['captionText'] ?? null);
        $t->same(['Caption body.'], $packet['figures'][0]['captionParagraphs'] ?? null);
        $t->same([], $packet['figures'][0]['missingMetadata'] ?? null);
        $t->same('caption-only', $packet['figures'][1]['id'] ?? null);
        $t->same(['label', 'title'], $packet['figures'][1]['missingMetadata'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][2]['details']['missingLabelCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][3]['details']['missingTitleCount'] ?? null);

        $bits = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book book-type="collection">
  <book-meta><title-group><book-title>BITS figures</book-title></title-group></book-meta>
  <book-body><fig id="bits-complete"><label>Figure B</label><caption><title>BITS workflow</title><p>BITS caption body.</p></caption></fig></book-body>
</book>
XML, 'BITS figure metadata XML', preserveWhiteSpace: false);
        $bitsPacket = XmlHtmlDom::summarizeJatsFrontMatter($bits, 'bits');

        $t->same(false, $bitsPacket['directReaderParity']);
        $t->same([
            'direct-reader-unsupported',
            'figures-review-only',
        ], $bitsPacket['directReaderDiagnosticCodes']);
        $t->same(['bits-complete'], $bitsPacket['figureIds']);
        $t->same(['Figure B'], $bitsPacket['figureLabels']);
        $t->same(['BITS workflow'], $bitsPacket['figureTitles']);
        $t->same(['BITS workflow BITS caption body.'], $bitsPacket['figureCaptionTexts']);
        $t->same([], $bitsPacket['figures'][0]['missingMetadata'] ?? null);
        $t->same(0, $bitsPacket['figureMetadataCounts']['missingLabel'] ?? null);
        json_encode([$packet, $bitsPacket], JSON_THROW_ON_ERROR);
    },
    'summarizes jats figure caption metadata diagnostics and xref links' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article" xmlns:xlink="http://www.w3.org/1999/xlink">
  <front><article-meta><title-group><article-title>Figure Review</article-title></title-group></article-meta></front>
  <body>
    <p>See <xref ref-type="fig" rid="fig-dup-a fig-dup-b">duplicate figures</xref> and <xref rid="fig-missing-caption">captionless panel</xref>.</p>
    <fig id="fig-missing-caption">
      <label>Fig. A</label>
      <graphic xlink:href="figures/missing-caption.png" mimetype="image" mime-subtype="png"/>
    </fig>
    <fig id="fig-dup-a">
      <label>Fig. D</label>
      <caption><p>Caption without a title.</p></caption>
      <alt-text>Duplicate panel A</alt-text>
    </fig>
    <fig id="fig-dup-b">
      <label>Fig. D</label>
      <caption><title>Resolved title</title><p>Caption with title.</p></caption>
      <alt-text>Duplicate panel B</alt-text>
    </fig>
  </body>
</article>
XML, 'JATS figure metadata XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($dom);

        $t->same(false, $packet['directReaderParity']);
        $t->same([
            'direct-reader-unsupported',
            'figures-review-only',
            'figure-caption-metadata-missing',
            'figure-title-metadata-missing',
            'figure-label-duplicate',
            'figure-media-references-review-only',
        ], $packet['directReaderDiagnosticCodes']);
        $t->same(1, $packet['directReaderDiagnostics'][2]['details']['missingCaptionCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][3]['details']['missingTitleCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][4]['details']['duplicateLabelFigureCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][5]['details']['mediaReferenceCount'] ?? null);
        $t->same(false, $packet['directReaderDiagnostics'][5]['details']['payloadBytesExposed'] ?? null);
        $t->same(['fig-missing-caption', 'fig-dup-a', 'fig-dup-b'], $packet['figureIds']);
        $t->same([
            'total' => 3,
            'withLabel' => 3,
            'withCaption' => 2,
            'withTitle' => 1,
            'missingLabel' => 0,
            'missingCaption' => 1,
            'missingTitle' => 2,
            'incomplete' => 2,
        ], $packet['figureMetadataCounts']);
        $t->same(['missing-caption', 'missing-title', 'duplicate-label'], $packet['figureMetadataIssueCodes']);
        $t->same(5, $packet['figureMetadataIssueCount']);
        $t->same('fig-missing-caption', $packet['figureMetadataIssues'][0]['figureId'] ?? null);
        $t->same('missing-caption', $packet['figureMetadataIssues'][0]['code'] ?? null);
        $t->same('article/body/fig', $packet['figureMetadataIssues'][0]['sourcePosition']['path'] ?? null);
        $t->same('missing-title', $packet['figureMetadataIssues'][1]['code'] ?? null);
        $t->same('duplicate-label', $packet['figureMetadataIssues'][3]['code'] ?? null);
        $t->same('fig/label', $packet['figureMetadataIssues'][3]['sourcePosition']['path'] ?? null);
        $t->same([
            ['label' => 'Fig. D', 'figureIds' => ['fig-dup-a', 'fig-dup-b'], 'figureCount' => 2],
        ], $packet['duplicateFigureLabels']);
        $t->same(1, $packet['duplicateFigureLabelCount']);
        $t->same('Fig. A', $packet['figures'][0]['label'] ?? null);
        $t->same(['missing-caption', 'missing-title'], $packet['figures'][0]['metadataIssueCodes'] ?? null);
        $t->same(null, $packet['figures'][0]['metadataPositions']['caption'] ?? null);
        $t->same('fig/label', $packet['figures'][0]['metadataPositions']['label']['path'] ?? null);
        $t->same('missing-caption.png', $packet['figureMediaReferences'][0]['targetBasename'] ?? null);
        $t->same('internal', $packet['figureMediaReferences'][0]['targetKind'] ?? null);
        $t->same(false, $packet['figureMediaReferences'][0]['payloadBytesExposed'] ?? null);
        $t->same('Duplicate panel A', $packet['figures'][1]['altText'] ?? null);
        $t->same(['missing-title'], $packet['figures'][1]['metadataIssueCodes'] ?? null);
        $t->same('fig/alt-text', $packet['figures'][1]['metadataPositions']['altText']['path'] ?? null);
        $t->same('Resolved title', $packet['figures'][2]['title'] ?? null);
        $t->same('fig/caption/title', $packet['figures'][2]['metadataPositions']['title']['path'] ?? null);
        $t->same(2, $packet['figureXrefLinkCount']);
        $t->same(['fig-dup-a', 'fig-dup-b'], $packet['figureXrefLinks'][0]['figureIds'] ?? null);
        $t->same('fig', $packet['figureXrefLinks'][0]['refType'] ?? null);
        $t->same('duplicate figures', $packet['figureXrefLinks'][0]['text'] ?? null);
        $t->same('article/body/p/xref', $packet['figureXrefLinks'][0]['sourcePosition']['path'] ?? null);
        $t->same(['fig-missing-caption'], $packet['figureXrefLinks'][1]['figureIds'] ?? null);
        $t->same(['fig-dup-a', 'fig-dup-b', 'fig-missing-caption'], $packet['figureXrefTargetIds']);
        $t->same(3, $packet['figureXrefTargetCount']);
        $t->same(false, $packet['figureMediaPayloadBytesExposed']);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes jats and bits inline xref citation diagnostics for reviewer handoff' => static function (TestRunner $t): void {
        $jats = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article">
  <front>
    <article-meta>
      <title-group><article-title>Citation diagnostics</article-title></title-group>
      <contrib-group><contrib><name><surname>Zed</surname></name><xref ref-type="aff" rid="aff1"/></contrib></contrib-group>
    </article-meta>
  </front>
  <body>
    <sec id="s1"><title>Scope</title><p>Body <xref ref-type="bibr" rid="r1 r-missing">[1]</xref> and <xref ref-type="fig" rid="f1">Figure 1</xref>.</p></sec>
    <fig id="f1"><caption><p>Figure</p></caption></fig>
  </body>
  <back>
    <ref-list><ref id="r1"><label>1</label><mixed-citation>Review source.</mixed-citation></ref></ref-list>
  </back>
</article>
XML, 'JATS inline citation diagnostics XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($jats);

        $t->same(['aff1', 'r1', 'r-missing', 'f1'], $packet['xrefTargets']);
        $t->same(['r1'], $packet['referenceIds']);
        $t->same(['r1'], $packet['backReferenceIds']);
        $t->same(1, $packet['backReferenceCount']);
        $t->same(2, $packet['inlineXrefDiagnosticCount']);
        $t->same(['r1'], $packet['inlineXrefLocalReferenceIds']);
        $t->same(1, $packet['inlineXrefLocalReferenceCount']);
        $t->same(['r-missing', 'f1'], $packet['inlineXrefUnsupportedTargetIds']);
        $t->same(2, $packet['inlineXrefUnsupportedTargetCount']);
        $t->same('bibr', $packet['inlineXrefDiagnostics'][0]['refType'] ?? null);
        $t->same('r1 r-missing', $packet['inlineXrefDiagnostics'][0]['ridRaw'] ?? null);
        $t->same(['r1', 'r-missing'], $packet['inlineXrefDiagnostics'][0]['targets'] ?? null);
        $t->same('[1]', $packet['inlineXrefDiagnostics'][0]['text'] ?? null);
        $t->same(['r1'], $packet['inlineXrefDiagnostics'][0]['localBackReferenceIds'] ?? null);
        $t->same(1, $packet['inlineXrefDiagnostics'][0]['unsupportedTargetCount'] ?? null);
        $t->same('r-missing', $packet['inlineXrefDiagnostics'][0]['unsupportedTargets'][0]['id'] ?? null);
        $t->same(null, $packet['inlineXrefDiagnostics'][0]['unsupportedTargets'][0]['targetElement'] ?? null);
        $t->same('missing-local-target', $packet['inlineXrefDiagnostics'][0]['unsupportedTargets'][0]['reason'] ?? null);
        $t->same(['jats-inline-xref-local-back-reference', 'jats-inline-xref-unsupported-citation-target'], $packet['inlineXrefDiagnostics'][0]['diagnostics'] ?? null);
        $t->same('fig', $packet['inlineXrefDiagnostics'][1]['refType'] ?? null);
        $t->same('f1', $packet['inlineXrefDiagnostics'][1]['unsupportedTargets'][0]['id'] ?? null);
        $t->same('fig', $packet['inlineXrefDiagnostics'][1]['unsupportedTargets'][0]['targetElement'] ?? null);
        $t->same('target-is-not-back-reference', $packet['inlineXrefDiagnostics'][1]['unsupportedTargets'][0]['reason'] ?? null);

        $bits = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book book-type="monograph">
  <book-meta><title-group><book-title>Review Book</book-title></title-group></book-meta>
  <book-body>
    <book-part id="ch1">
      <book-part-meta><title-group><title>Chapter One</title></title-group></book-part-meta>
      <body><p>See <xref ref-type="bibr" rid="book-ref1 appendix-ref">[A]</xref>.</p></body>
    </book-part>
  </book-body>
  <book-back><ref-list><ref id="book-ref1"><mixed-citation>Camille, Review Book.</mixed-citation></ref></ref-list></book-back>
</book>
XML, 'BITS inline citation diagnostics XML', preserveWhiteSpace: false);
        $bitsPacket = XmlHtmlDom::summarizeJatsFrontMatter($bits, 'bits');

        $t->same(['book-ref1'], $bitsPacket['backReferenceIds']);
        $t->same(1, $bitsPacket['inlineXrefDiagnosticCount']);
        $t->same(['book-ref1'], $bitsPacket['inlineXrefLocalReferenceIds']);
        $t->same(['appendix-ref'], $bitsPacket['inlineXrefUnsupportedTargetIds']);
        $t->same('book-ref1 appendix-ref', $bitsPacket['inlineXrefDiagnostics'][0]['ridRaw'] ?? null);
        $t->same('[A]', $bitsPacket['inlineXrefDiagnostics'][0]['text'] ?? null);
        $t->same('appendix-ref', $bitsPacket['inlineXrefDiagnostics'][0]['unsupportedTargets'][0]['id'] ?? null);
        $t->same('missing-local-target', $bitsPacket['inlineXrefDiagnostics'][0]['unsupportedTargets'][0]['reason'] ?? null);
        json_encode([$packet, $bitsPacket], JSON_THROW_ON_ERROR);
    },
    'summarizes jats and bits figure table reference diagnostics without reader parity claims' => static function (TestRunner $t): void {
        $jats = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article">
  <front>
    <article-meta><title-group><article-title>Relationship review</article-title></title-group></article-meta>
  </front>
  <body>
    <sec id="s1">
      <title>Results</title>
      <p>
        See <xref ref-type="fig" rid="f1">Figure 1</xref>,
        <xref ref-type="table" rid="t1">Table 1</xref>, and
        <xref ref-type="bibr" rid="r1">Smith</xref>.
        <xref ref-type="fig" rid="missing-fig">Missing figure</xref>
        <xref ref-type="bibr" rid="t1">Wrong reference target</xref>
        <xref ref-type="table">No table rid</xref>
      </p>
    </sec>
    <fig id="f1"><label>Fig 1</label><caption><p>Review chart</p></caption></fig>
    <table-wrap id="t1"><label>Table 1</label><caption><p>Review totals</p></caption><table><tbody><tr><td>Total</td></tr></tbody></table></table-wrap>
  </body>
  <back><ref-list><ref id="r1"><label>1</label><mixed-citation>Smith 2026.</mixed-citation></ref></ref-list></back>
</article>
XML, 'JATS relationship XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($jats);
        $relationships = $packet['relationshipDiagnostics'];

        $t->same('jats-bits-relationship-diagnostics-review-only', $relationships['reviewPolicy']);
        $t->same(false, $relationships['directReaderParity']);
        $t->same(6, $relationships['xrefCount']);
        $t->same(4, $relationships['resolvedXrefCount']);
        $t->same(1, $relationships['unresolvedXrefCount']);
        $t->same(1, $relationships['unresolvedXrefTargetCount']);
        $t->same(1, $relationships['missingRidXrefCount']);
        $t->same(0, $relationships['multiTargetXrefCount']);
        $t->same(1, $relationships['typeMismatchCount']);
        $t->same(3, $relationships['diagnosticCount']);
        $t->same(3, $packet['relationshipDiagnosticCount']);
        $t->same(1, $packet['unresolvedXrefTargetCount']);
        $t->same(['fig' => 1, 'ref' => 1, 'table-wrap' => 2], $relationships['targetTypeCounts']);
        $t->same([[
            'id' => 'f1',
            'label' => 'Fig 1',
            'xrefCount' => 1,
            'captionText' => 'Review chart',
        ]], $relationships['figureTargets']);
        $t->same([[
            'id' => 't1',
            'label' => 'Table 1',
            'xrefCount' => 2,
            'captionText' => 'Review totals',
        ]], $relationships['tableWrapTargets']);
        $t->same('r1', $relationships['referenceTargets'][0]['id'] ?? null);
        $t->same('1', $relationships['referenceTargets'][0]['label'] ?? null);
        $t->same(1, $relationships['referenceTargets'][0]['xrefCount'] ?? null);
        $t->same(12, $relationships['referenceTargets'][0]['referenceTextLength'] ?? null);
        $t->same(1, preg_match('/^[a-f0-9]{64}$/', (string) ($relationships['referenceTargets'][0]['referenceTextSha256'] ?? '')));
        $t->true(!array_key_exists('referenceText', $relationships['referenceTargets'][0]), 'Expected relationship reference target summaries to avoid raw citation text');
        $t->same([
            'unresolved-jats-xref-target',
            'jats-xref-ref-type-target-mismatch',
            'missing-jats-xref-rid',
        ], array_column($relationships['diagnostics'], 'type'));
        $t->same('missing-fig', $relationships['diagnostics'][0]['targetId']);
        $t->same(['ref'], $relationships['diagnostics'][1]['expectedTargetNames']);
        $t->same('table-wrap', $relationships['diagnostics'][1]['actualTargetName']);
        $t->same('missing-xref-rid', $relationships['xrefRecords'][5]['issues'][0]);
        $t->same(['t1'], $relationships['xrefRecords'][4]['resolvedTargetIds']);
        $t->same(['xref-ref-type-target-mismatch'], $relationships['xrefRecords'][4]['issues']);
        json_encode($packet, JSON_THROW_ON_ERROR);

        $bits = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book book-type="monograph">
  <book-meta><title-group><book-title>BITS relationship review</book-title></title-group></book-meta>
  <book-body>
    <book-part id="chapter-1">
      <body>
        <p>See <xref ref-type="fig" rid="bf1">figure A</xref> and <xref ref-type="table" rid="bt1">table A</xref>.</p>
        <fig id="bf1"><caption><p>BITS chart</p></caption></fig>
        <table-wrap id="bt1"><caption><p>BITS totals</p></caption></table-wrap>
      </body>
    </book-part>
  </book-body>
</book>
XML, 'BITS relationship XML', preserveWhiteSpace: false);
        $bitsRelationships = XmlHtmlDom::summarizeJatsFrontMatter($bits, 'bits')['relationshipDiagnostics'];

        $t->same(2, $bitsRelationships['xrefCount']);
        $t->same(0, $bitsRelationships['diagnosticCount']);
        $t->same(['fig' => 1, 'table-wrap' => 1], $bitsRelationships['targetTypeCounts']);
        $t->same('BITS chart', $bitsRelationships['figureTargets'][0]['captionText'] ?? null);
        $t->same('BITS totals', $bitsRelationships['tableWrapTargets'][0]['captionText'] ?? null);
    },
    'summarizes docbook structural media diagnostics without reader parity claims' => static function (TestRunner $t): void {
        $docbook = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article xmlns="http://docbook.org/ns/docbook" version="5.2" xml:lang="en" xml:id="book-root">
  <title>DocBook Review Guide</title>
  <section xml:id="intro">
    <title>Intro</title>
    <para>See <xref linkend="warn-media missing-target"/> and <link linkend="fig-cover">cover</link>.</para>
    <warning xml:id="warn-media">
      <title>Warning</title>
      <para>Careful review.</para>
      <programlisting>rm -rf /tmp/example</programlisting>
    </warning>
    <tip id="legacy-tip"><para>Legacy id target.</para></tip>
    <figure xml:id="fig-cover">
      <title>Cover</title>
      <mediaobject xml:id="media-cover" role="thumbnail">
        <title>Cover media title</title>
        <caption><para>Cover image import</para></caption>
        <imageobject role="thumbnail"><imagedata fileref="images/cover.png" format="PNG" width="640px" depth="480px" contentwidth="320px" align="center"/></imageobject>
        <textobject role="alt"><phrase>Cover image alt text</phrase></textobject>
        <videoobject><videodata fileref="movie.mp4"/></videoobject>
      </mediaobject>
    </figure>
    <inlinemediaobject xml:id="inline-cover" role="thumbnail">
      <imageobject role="thumbnail"><imagedata fileref="images/cover.png" format="PNG"/></imageobject>
      <textobject role="alt"><phrase>Inline cover alt text</phrase></textobject>
    </inlinemediaobject>
    <mediaobject xml:id="poster-media" role="detail">
      <imageobject role="detail"><imagedata fileref="images/poster.png" format="PNG"/></imageobject>
    </mediaobject>
    <calloutlist><callout arearefs="co1"><para>Unsupported callout list.</para></callout></calloutlist>
  </section>
</article>
XML, 'DocBook review XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeDocBookReviewPacket($docbook);

        $t->same('xml-html5-docbook-dom', $packet['formatFamily']);
        $t->same('docbook', $packet['format']);
        $t->same('docbook-structural-media-review-only', $packet['reviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same([
            'direct-reader-unsupported',
            'structural-blocks-review-only',
            'admonitions-review-only',
            'figures-review-only',
            'mediaobjects-review-only',
            'image-references-review-only',
            'media-target-manifest-review-only',
            'linkend-targets-review-only',
            'unsupported-children-review-only',
        ], $packet['directReaderDiagnosticCodes']);
        $t->same(9, $packet['directReaderDiagnosticCount']);
        $t->same(false, $packet['directReaderDiagnostics'][0]['directReaderParity'] ?? null);
        $t->same(true, $packet['directReaderDiagnostics'][0]['coveredByPacket'] ?? null);
        $t->same(false, $packet['directReaderDiagnostics'][1]['coveredByPacket'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][2]['details']['admonitionCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][3]['details']['figureCount'] ?? null);
        $t->same(3, $packet['directReaderDiagnostics'][4]['details']['mediaObjectCount'] ?? null);
        $t->same(3, $packet['directReaderDiagnostics'][5]['details']['imageDataRefCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][6]['details']['mediaTargetManifestCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][6]['details']['repeatedMediaTargetCount'] ?? null);
        $t->same(0, $packet['directReaderDiagnostics'][6]['details']['missingMediaTargetMetadataCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][6]['details']['mediaObjectAssociationDiagnosticCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][6]['details']['mediaLinkendReferenceCount'] ?? null);
        $t->same(false, $packet['directReaderDiagnostics'][6]['details']['payloadBytesExposed'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][7]['details']['linkendReferenceCount'] ?? null);
        $t->true(($packet['directReaderDiagnostics'][8]['details']['unsupportedChildDiagnosticCount'] ?? 0) >= 3);
        $t->same('article', $packet['rootName']);
        $t->same('http://docbook.org/ns/docbook', $packet['rootNamespace']);
        $t->same('5.2', $packet['version']);
        $t->same('en', $packet['language']);
        $t->same('book-root', $packet['rootAttributes']['xml:id'] ?? null);
        $t->same('DocBook Review Guide', $packet['title']);
        $t->same(true, $packet['bounded']);
        $t->same(25, $packet['maxReviewItems']);
        $t->true($packet['structuralBlockCount'] >= 8);
        $t->true(in_array('warning', $packet['structuralBlockNames'], true));
        $t->true(in_array('figure', $packet['structuralBlockNames'], true));
        $t->same(false, $packet['structuralBlocksTruncated']);
        $t->same(2, $packet['admonitionCount']);
        $t->same(['warning', 'tip'], $packet['admonitionTypes']);
        $t->same('warn-media', $packet['admonitions'][0]['xmlId'] ?? null);
        $t->same('Warning', $packet['admonitions'][0]['title'] ?? null);
        $t->same(1, $packet['admonitions'][0]['paragraphCount'] ?? null);
        $t->same('legacy-tip', $packet['admonitions'][1]['id'] ?? null);
        $t->same(1, $packet['figureCount']);
        $t->same(['fig-cover'], $packet['figureXmlIds']);
        $t->same('Cover', $packet['figures'][0]['title'] ?? null);
        $t->same(1, $packet['figures'][0]['mediaObjectCount'] ?? null);
        $t->same(3, $packet['mediaObjectCount']);
        $t->same(['thumbnail', 'alt', 'detail'], $packet['mediaObjectRoles']);
        $t->same(['Cover image import'], $packet['mediaCaptionTexts']);
        $t->same(['Cover image alt text', 'Inline cover alt text'], $packet['mediaTextAlternativeTexts']);
        $t->same('mediaobject', $packet['mediaObjects'][0]['type'] ?? null);
        $t->same('fig-cover', $packet['mediaObjects'][0]['parentFigureXmlId'] ?? null);
        $t->same('thumbnail', $packet['mediaObjects'][0]['role'] ?? null);
        $t->same(['thumbnail', 'alt'], $packet['mediaObjects'][0]['roles'] ?? null);
        $t->same('Cover media title', $packet['mediaObjects'][0]['title'] ?? null);
        $t->same('Cover image import', $packet['mediaObjects'][0]['caption'] ?? null);
        $t->same('Cover image import', $packet['mediaObjects'][0]['captionText'] ?? null);
        $t->same('caption', $packet['mediaObjects'][0]['captionSource'] ?? null);
        $t->same(true, $packet['mediaObjects'][0]['hasCaption'] ?? null);
        $t->same(true, $packet['mediaObjects'][0]['hasTextAlternative'] ?? null);
        $t->same(['Cover image alt text'], $packet['mediaObjects'][0]['textAlternativeTexts'] ?? null);
        $t->same(['images/cover.png', 'movie.mp4'], $packet['mediaObjects'][0]['targetRefs'] ?? null);
        $t->same([['role' => 'thumbnail', 'target' => 'images/cover.png'], ['role' => 'thumbnail', 'target' => 'movie.mp4']], $packet['mediaObjects'][0]['roleTargetPairs'] ?? null);
        $t->same('inlinemediaobject', $packet['mediaObjects'][1]['type'] ?? null);
        $t->same('inline-cover', $packet['mediaObjects'][1]['xmlId'] ?? null);
        $t->same(false, $packet['mediaObjects'][1]['hasCaption'] ?? null);
        $t->same(true, $packet['mediaObjects'][1]['hasTextAlternative'] ?? null);
        $t->same(['Inline cover alt text'], $packet['mediaObjects'][1]['textAlternativeTexts'] ?? null);
        $t->same(['images/cover.png'], $packet['mediaObjects'][1]['targetRefs'] ?? null);
        $t->same('poster-media', $packet['mediaObjects'][2]['xmlId'] ?? null);
        $t->same(false, $packet['mediaObjects'][2]['hasCaption'] ?? null);
        $t->same(false, $packet['mediaObjects'][2]['hasTextAlternative'] ?? null);
        $t->same(['images/poster.png'], $packet['mediaObjects'][2]['targetRefs'] ?? null);
        $t->same(3, $packet['mediaTargetManifestCount']);
        $t->same('images/cover.png', $packet['mediaTargetManifest'][0]['target'] ?? null);
        $t->same(['thumbnail'], $packet['mediaTargetManifest'][0]['roles'] ?? null);
        $t->same(['media-cover', 'inline-cover'], $packet['mediaTargetManifest'][0]['mediaObjectXmlIds'] ?? null);
        $t->same(['mediaobject', 'inlinemediaobject'], $packet['mediaTargetManifest'][0]['mediaElements'] ?? null);
        $t->same(['Cover media title'], $packet['mediaTargetManifest'][0]['titleTexts'] ?? null);
        $t->same(['Cover image import'], $packet['mediaTargetManifest'][0]['captionTexts'] ?? null);
        $t->same(['Cover image alt text', 'Inline cover alt text'], $packet['mediaTargetManifest'][0]['textAlternatives'] ?? null);
        $t->same(2, $packet['mediaTargetManifest'][0]['occurrenceCount'] ?? null);
        $t->same('movie.mp4', $packet['mediaTargetManifest'][1]['target'] ?? null);
        $t->same(1, $packet['mediaTargetManifest'][1]['occurrenceCount'] ?? null);
        $t->same('images/poster.png', $packet['mediaTargetManifest'][2]['target'] ?? null);
        $t->same(1, $packet['repeatedMediaRoleTargetPairCount']);
        $t->same('thumbnail', $packet['repeatedMediaRoleTargetPairs'][0]['role'] ?? null);
        $t->same('images/cover.png', $packet['repeatedMediaRoleTargetPairs'][0]['target'] ?? null);
        $t->same(['media-cover', 'inline-cover'], $packet['repeatedMediaRoleTargetPairs'][0]['mediaObjectXmlIds'] ?? null);
        $t->same([
            'docbook-media-missing-caption',
            'docbook-media-missing-caption',
            'docbook-media-missing-alt-text',
            'docbook-media-repeated-role-target',
        ], $packet['mediaDiagnosticCodes']);
        $t->same(4, $packet['mediaDiagnosticCount']);
        $t->same('inline-cover', $packet['mediaDiagnostics'][0]['details']['xmlId'] ?? null);
        $t->same('poster-media', $packet['mediaDiagnostics'][1]['details']['xmlId'] ?? null);
        $t->same(['images/poster.png'], $packet['mediaDiagnostics'][2]['details']['targetRefs'] ?? null);
        $t->same('images/cover.png', $packet['mediaDiagnostics'][3]['details']['target'] ?? null);
        $t->same(3, $packet['imageDataRefCount']);
        $t->same('images/cover.png', $packet['imageDataRefs'][0]['fileref'] ?? null);
        $t->same('PNG', $packet['imageDataRefs'][0]['format'] ?? null);
        $t->same('640px', $packet['imageDataRefs'][0]['width'] ?? null);
        $t->same('480px', $packet['imageDataRefs'][0]['depth'] ?? null);
        $t->same('320px', $packet['imageDataRefs'][0]['contentwidth'] ?? null);
        $t->same('center', $packet['imageDataRefs'][0]['align'] ?? null);
        $t->same('images/cover.png', $packet['imageDataRefs'][1]['fileref'] ?? null);
        $t->same('PNG', $packet['imageDataRefs'][1]['format'] ?? null);
        $t->same('images/poster.png', $packet['imageDataRefs'][2]['fileref'] ?? null);
        $t->same(2, $packet['mediaImageTargetManifestCount']);
        $t->same('images/cover.png', $packet['mediaImageTargetManifest'][0]['target'] ?? null);
        $t->same(['image/png'], $packet['mediaImageTargetManifest'][0]['contentTypes'] ?? null);
        $t->same(2, $packet['mediaImageTargetManifest'][0]['imageDataCount'] ?? null);
        $t->same(true, $packet['mediaImageTargetManifest'][0]['repeated'] ?? null);
        $t->same(['media-cover', 'inline-cover'], $packet['mediaImageTargetManifest'][0]['mediaObjectIds'] ?? null);
        $t->same('images/poster.png', $packet['mediaImageTargetManifest'][1]['target'] ?? null);
        $t->same(['image/png'], $packet['mediaImageTargetManifest'][1]['contentTypes'] ?? null);
        $t->same(1, $packet['repeatedMediaTargetCount']);
        $t->same('images/cover.png', $packet['repeatedMediaTargets'][0]['target'] ?? null);
        $t->same(0, $packet['missingMediaTargetMetadataCount']);
        $t->same(['docbook-media-imageobject-without-textobject'], $packet['mediaObjectAssociationDiagnosticCodes']);
        $t->same(1, $packet['mediaObjectAssociationDiagnosticCount']);
        $t->same(['fig-cover', 'media-cover', 'inline-cover', 'poster-media'], $packet['mediaIdTargets']);
        $t->same(1, $packet['mediaLinkendReferenceCount']);
        $t->same('fig-cover', $packet['mediaLinkendReferences'][0]['target'] ?? null);
        $t->same(['images/cover.png'], $packet['mediaLinkendReferences'][0]['targetImageDataRefs'] ?? null);
        $t->same(['book-root', 'intro', 'warn-media', 'fig-cover', 'media-cover', 'inline-cover', 'poster-media'], $packet['xmlIdTargets']);
        $t->same(['legacy-tip'], $packet['idTargets']);
        $t->same(8, $packet['targetSummaryCount']);
        $t->same(2, $packet['linkendReferenceCount']);
        $t->same(['warn-media', 'missing-target'], $packet['linkendReferences'][0]['targets'] ?? null);
        $t->same(['warn-media'], $packet['linkendReferences'][0]['resolvedTargets'] ?? null);
        $t->same(['missing-target'], $packet['linkendReferences'][0]['missingTargets'] ?? null);
        $t->same(['warn-media', 'missing-target', 'fig-cover'], $packet['linkendTargets']);
        $t->same(['missing-target'], $packet['missingLinkendTargets']);
        $t->true($packet['unsupportedChildDiagnosticCount'] >= 4);
        $t->true(in_array('programlisting', $packet['unsupportedChildNames'], true));
        $t->true(in_array('videoobject', $packet['unsupportedChildNames'], true));
        $t->true(in_array('calloutlist', $packet['unsupportedChildNames'], true));
        $t->same('docbook-unsupported-child', $packet['unsupportedChildDiagnostics'][0]['code'] ?? null);
        $t->same(false, $packet['unsupportedChildDiagnostics'][0]['directReaderParity'] ?? null);
        $t->same(false, $packet['unsupportedChildDiagnostics'][0]['coveredByPacket'] ?? null);
        $t->throws(InvalidArgumentException::class, static fn (): array => XmlHtmlDom::summarizeDocBookReviewPacket($docbook, 'xml'));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes docbook media role caption and target diagnostics' => static function (TestRunner $t): void {
        $docbook = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article xmlns="http://docbook.org/ns/docbook" version="5.2">
  <title>Media Roles</title>
  <section xml:id="media-roles">
    <title>Media roles</title>
    <mediaobject xml:id="hero-media" role="screenshot">
      <title>Hero media title</title>
      <caption><para>Hero screenshot import</para></caption>
      <imageobject role="screenshot"><imagedata fileref="images/hero.png" format="PNG"/></imageobject>
      <textobject role="alt"><phrase>Hero screenshot alt text</phrase></textobject>
    </mediaobject>
    <inlinemediaobject xml:id="hero-inline" role="screenshot">
      <imageobject role="screenshot"><imagedata fileref="images/hero.png" format="PNG"/></imageobject>
      <textobject role="alt"><phrase>Inline hero alt text</phrase></textobject>
    </inlinemediaobject>
    <mediaobject xml:id="poster-media" role="poster">
      <imageobject role="poster"><imagedata fileref="images/poster.png" format="PNG"/></imageobject>
    </mediaobject>
  </section>
</article>
XML, 'DocBook media role caption XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeDocBookReviewPacket($docbook, 'docbook5');

        $t->same(false, $packet['directReaderParity']);
        $t->same('docbook-structural-media-review-only', $packet['reviewPolicy']);
        $t->same(3, $packet['mediaObjectCount']);
        $t->same(['screenshot', 'alt', 'poster'], $packet['mediaObjectRoles']);
        $t->same(['Hero screenshot import'], $packet['mediaCaptionTexts']);
        $t->same(['Hero screenshot alt text', 'Inline hero alt text'], $packet['mediaTextAlternativeTexts']);
        $t->same('mediaobject', $packet['mediaObjects'][0]['type'] ?? null);
        $t->same('hero-media', $packet['mediaObjects'][0]['xmlId'] ?? null);
        $t->same('screenshot', $packet['mediaObjects'][0]['role'] ?? null);
        $t->same(['screenshot', 'alt'], $packet['mediaObjects'][0]['roles'] ?? null);
        $t->same('Hero media title', $packet['mediaObjects'][0]['title'] ?? null);
        $t->same('Hero screenshot import', $packet['mediaObjects'][0]['captionText'] ?? null);
        $t->same(['Hero screenshot alt text'], $packet['mediaObjects'][0]['textAlternativeTexts'] ?? null);
        $t->same(['images/hero.png'], $packet['mediaObjects'][0]['targetRefs'] ?? null);
        $t->same('inlinemediaobject', $packet['mediaObjects'][1]['type'] ?? null);
        $t->same(false, $packet['mediaObjects'][1]['hasCaption'] ?? null);
        $t->same(true, $packet['mediaObjects'][1]['hasTextAlternative'] ?? null);
        $t->same(false, $packet['mediaObjects'][2]['hasCaption'] ?? null);
        $t->same(false, $packet['mediaObjects'][2]['hasTextAlternative'] ?? null);
        $t->same(2, $packet['mediaTargetManifestCount']);
        $t->same('images/hero.png', $packet['mediaTargetManifest'][0]['target'] ?? null);
        $t->same(['hero-media', 'hero-inline'], $packet['mediaTargetManifest'][0]['mediaObjectXmlIds'] ?? null);
        $t->same(['mediaobject', 'inlinemediaobject'], $packet['mediaTargetManifest'][0]['mediaElements'] ?? null);
        $t->same(['Hero media title'], $packet['mediaTargetManifest'][0]['titleTexts'] ?? null);
        $t->same(['Hero screenshot import'], $packet['mediaTargetManifest'][0]['captionTexts'] ?? null);
        $t->same(['Hero screenshot alt text', 'Inline hero alt text'], $packet['mediaTargetManifest'][0]['textAlternatives'] ?? null);
        $t->same(2, $packet['mediaTargetManifest'][0]['occurrenceCount'] ?? null);
        $t->same('images/poster.png', $packet['mediaTargetManifest'][1]['target'] ?? null);
        $t->same(1, $packet['repeatedMediaRoleTargetPairCount']);
        $t->same('screenshot', $packet['repeatedMediaRoleTargetPairs'][0]['role'] ?? null);
        $t->same('images/hero.png', $packet['repeatedMediaRoleTargetPairs'][0]['target'] ?? null);
        $t->same(['hero-media', 'hero-inline'], $packet['repeatedMediaRoleTargetPairs'][0]['mediaObjectXmlIds'] ?? null);
        $t->same([
            'docbook-media-missing-caption',
            'docbook-media-missing-caption',
            'docbook-media-missing-alt-text',
            'docbook-media-repeated-role-target',
        ], $packet['mediaDiagnosticCodes']);
        $t->same('hero-inline', $packet['mediaDiagnostics'][0]['details']['xmlId'] ?? null);
        $t->same('poster-media', $packet['mediaDiagnostics'][2]['details']['xmlId'] ?? null);
        $t->same('images/hero.png', $packet['mediaDiagnostics'][3]['details']['target'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes docbook media target manifests without reader parity claims' => static function (TestRunner $t): void {
        $docbook = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" version="5.2" xml:id="media-root">
  <title>Media Target Review</title>
  <para>See <xref linkend="fig-diagram"/> and <link linkend="media-repeat">reused media</link>.</para>
  <figure xml:id="fig-diagram">
    <title>Diagram</title>
    <mediaobject xml:id="media-diagram" role="screenshot">
      <imageobject xml:id="image-primary"><imagedata fileref="assets/diagram.png" format="PNG"/></imageobject>
      <imageobject xml:id="image-alternate"><imagedata fileref="alt/diagram.png" format="image/png"/></imageobject>
      <textobject xml:id="diagram-text"><phrase>Diagram fallback text</phrase></textobject>
    </mediaobject>
  </figure>
  <mediaobject xml:id="media-repeat">
    <imageobject><imagedata fileref="assets/diagram.png" format="PNG"/></imageobject>
    <alt>Repeated diagram fallback</alt>
  </mediaobject>
  <inlinemediaobject xml:id="inline-icon">
    <imageobject xml:id="icon-image"><imagedata xlink:href="assets/icon.svg" format="SVG"/></imageobject>
  </inlinemediaobject>
  <mediaobject xml:id="missing-media">
    <imageobject xml:id="missing-image"><imagedata format="JPEG"/></imageobject>
  </mediaobject>
  <mediaobject xml:id="text-only-media">
    <textobject xml:id="text-only-object"><phrase>Text-only fallback</phrase></textobject>
  </mediaobject>
</article>
XML, 'DocBook media target XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeDocBookReviewPacket($docbook);

        $t->same(false, $packet['directReaderParity']);
        $t->true(in_array('media-target-manifest-review-only', $packet['directReaderDiagnosticCodes'], true));
        $mediaDiagnostic = array_values(array_filter(
            $packet['directReaderDiagnostics'],
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? null) === 'media-target-manifest-review-only'
        ))[0] ?? [];
        $t->same('media-target-manifest-review-only', $mediaDiagnostic['code'] ?? null);
        $t->same(false, $mediaDiagnostic['directReaderParity'] ?? null);
        $t->same(false, $mediaDiagnostic['coveredByPacket'] ?? null);
        $t->same(3, $mediaDiagnostic['details']['mediaTargetManifestCount'] ?? null);
        $t->same(1, $mediaDiagnostic['details']['repeatedMediaTargetCount'] ?? null);
        $t->same(1, $mediaDiagnostic['details']['missingMediaTargetMetadataCount'] ?? null);
        $t->same(4, $mediaDiagnostic['details']['mediaObjectAssociationDiagnosticCount'] ?? null);
        $t->same(2, $mediaDiagnostic['details']['mediaLinkendReferenceCount'] ?? null);
        $t->same(false, $mediaDiagnostic['details']['payloadBytesExposed'] ?? null);

        $t->same(3, $packet['mediaImageTargetManifestCount']);
        $t->same('assets/diagram.png', $packet['mediaImageTargetManifest'][0]['target'] ?? null);
        $t->same(['fileref'], $packet['mediaImageTargetManifest'][0]['targetSources'] ?? null);
        $t->same('diagram.png', $packet['mediaImageTargetManifest'][0]['targetBasename'] ?? null);
        $t->same(['image/png'], $packet['mediaImageTargetManifest'][0]['contentTypes'] ?? null);
        $t->same(2, $packet['mediaImageTargetManifest'][0]['imageDataCount'] ?? null);
        $t->same(true, $packet['mediaImageTargetManifest'][0]['repeated'] ?? null);
        $t->same(['media-diagram', 'media-repeat'], $packet['mediaImageTargetManifest'][0]['mediaObjectIds'] ?? null);
        $t->same(false, $packet['mediaImageTargetManifest'][0]['payloadBytesExposed'] ?? null);
        $t->same('alt/diagram.png', $packet['mediaImageTargetManifest'][1]['target'] ?? null);
        $t->same('assets/icon.svg', $packet['mediaImageTargetManifest'][2]['target'] ?? null);
        $t->same(['xlink:href'], $packet['mediaImageTargetManifest'][2]['targetSources'] ?? null);
        $t->same(['image/svg+xml'], $packet['mediaImageTargetManifest'][2]['contentTypes'] ?? null);

        $t->same(1, $packet['repeatedMediaTargetCount']);
        $t->same('assets/diagram.png', $packet['repeatedMediaTargets'][0]['target'] ?? null);
        $t->same(2, $packet['repeatedMediaTargets'][0]['imageDataCount'] ?? null);
        $t->same(1, $packet['missingMediaTargetMetadataCount']);
        $t->same('docbook-media-target-missing-metadata', $packet['missingMediaTargetMetadata'][0]['code'] ?? null);
        $t->same('missing-media', $packet['missingMediaTargetMetadata'][0]['parentMediaObjectId'] ?? null);
        $t->same('image/jpeg', $packet['missingMediaTargetMetadata'][0]['contentType'] ?? null);
        $t->same(false, $packet['missingMediaTargetMetadata'][0]['payloadBytesExposed'] ?? null);

        $t->same(2, $packet['mediaTargetBasenameGroupCount']);
        $t->same('diagram.png', $packet['mediaTargetBasenameGroups'][0]['targetBasename'] ?? null);
        $t->same(['assets/diagram.png', 'alt/diagram.png'], $packet['mediaTargetBasenameGroups'][0]['targets'] ?? null);
        $t->same(3, $packet['mediaTargetBasenameGroups'][0]['imageDataCount'] ?? null);
        $t->same(2, $packet['mediaTargetBasenameGroups'][0]['targetCount'] ?? null);
        $t->same('icon.svg', $packet['mediaTargetBasenameGroups'][1]['targetBasename'] ?? null);
        $t->same(2, $packet['mediaTargetContentTypeGroupCount']);
        $t->same('image/png', $packet['mediaTargetContentTypeGroups'][0]['contentType'] ?? null);
        $t->same(['assets/diagram.png', 'alt/diagram.png'], $packet['mediaTargetContentTypeGroups'][0]['targets'] ?? null);
        $t->same(['diagram.png'], $packet['mediaTargetContentTypeGroups'][0]['targetBasenames'] ?? null);
        $t->same(['format'], $packet['mediaTargetContentTypeGroups'][0]['contentTypeSources'] ?? null);
        $t->same(3, $packet['mediaTargetContentTypeGroups'][0]['imageDataCount'] ?? null);

        $t->same(5, $packet['mediaObjectAssociationCount']);
        $t->same('media-diagram', $packet['mediaObjectAssociations'][0]['id'] ?? null);
        $t->same(true, $packet['mediaObjectAssociations'][0]['hasAccessibleText'] ?? null);
        $t->same(['Diagram fallback text'], $packet['mediaObjectAssociations'][0]['textObjectTexts'] ?? null);
        $t->same('inline-icon', $packet['mediaObjectAssociations'][2]['id'] ?? null);
        $t->same(false, $packet['mediaObjectAssociations'][2]['hasAccessibleText'] ?? null);
        $t->same(1, $packet['mediaObjectAssociations'][3]['missingTargetCount'] ?? null);
        $t->same('text-only-media', $packet['mediaObjectAssociations'][4]['id'] ?? null);
        $t->same(0, $packet['mediaObjectAssociations'][4]['imageObjectCount'] ?? null);
        $t->same(1, $packet['mediaObjectAssociations'][4]['textObjectCount'] ?? null);
        $t->same([
            'docbook-media-imageobject-without-textobject',
            'docbook-media-target-missing-metadata',
            'docbook-media-textobject-without-imageobject',
        ], $packet['mediaObjectAssociationDiagnosticCodes']);
        $t->same(4, $packet['mediaObjectAssociationDiagnosticCount']);
        $t->same('inline-icon', $packet['mediaObjectAssociationDiagnostics'][0]['details']['mediaObjectId'] ?? null);
        $t->same('missing-media', $packet['mediaObjectAssociationDiagnostics'][1]['details']['mediaObjectId'] ?? null);
        $t->same('missing-media', $packet['mediaObjectAssociationDiagnostics'][2]['details']['mediaObjectId'] ?? null);
        $t->same('docbook-media-target-missing-metadata', $packet['mediaObjectAssociationDiagnostics'][2]['code'] ?? null);
        $t->same('text-only-media', $packet['mediaObjectAssociationDiagnostics'][3]['details']['mediaObjectId'] ?? null);

        $t->true(in_array('fig-diagram', $packet['mediaIdTargets'], true));
        $t->true(in_array('media-repeat', $packet['mediaIdTargets'], true));
        $t->true(in_array('image-primary', $packet['mediaIdTargets'], true));
        $t->same(2, $packet['mediaLinkendReferenceCount']);
        $t->same('fig-diagram', $packet['mediaLinkendReferences'][0]['target'] ?? null);
        $t->same('figure', $packet['mediaLinkendReferences'][0]['targetKind'] ?? null);
        $t->same(['assets/diagram.png', 'alt/diagram.png'], $packet['mediaLinkendReferences'][0]['targetImageDataRefs'] ?? null);
        $t->same('media-repeat', $packet['mediaLinkendReferences'][1]['target'] ?? null);
        $t->same('media', $packet['mediaLinkendReferences'][1]['targetKind'] ?? null);
        $t->same(['assets/diagram.png'], $packet['mediaLinkendReferences'][1]['targetImageDataRefs'] ?? null);
        $t->same(false, $packet['mediaLinkendReferences'][1]['payloadBytesExposed'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes docbook bibliography reference diagnostics without reader parity claims' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book xmlns="http://docbook.org/ns/docbook" version="5.2" xml:lang="en">
  <info><title>Reviewer Reference Packet</title></info>
  <chapter xml:id="ch1">
    <title>Body References</title>
    <para>See <xref linkend="ref-good"/> plus <citation>[ref-missing]</citation> and <link linkend="ref-dup">duplicate reference</link>.</para>
  </chapter>
  <bibliography xml:id="refs">
    <title>Works Cited</title>
    <biblioentry xml:id="ref-good" id="ref-good-legacy">
      <title>Portable Imports</title>
      <title>Portable Imports</title>
      <author><personname><firstname>Ada</firstname><surname>Zed</surname></personname></author>
      <author><personname><firstname>Ada</firstname><surname>Zed</surname></personname></author>
      <editor><personname><firstname>Nia</firstname><surname>Editor</surname></personname></editor>
      <pubdate>2026</pubdate>
      <year>2026</year>
      <publisher><publishername>Port Libs Press</publishername></publisher>
      <mediaobject><imageobject/></mediaobject>
    </biblioentry>
    <bibliomixed xml:id="ref-dup">
      <title>Mixed Reference</title>
      <editor><personname><firstname>Bob</firstname><surname>Mix</surname></personname></editor>
      <year>2025</year>
      <publishername>Mixed Press</publishername>
    </bibliomixed>
    <biblioentry xml:id="ref-dup">
      <title>Duplicate Reference</title>
      <date>2024-05</date>
      <publisher><publishername>Duplicate Press</publishername></publisher>
    </biblioentry>
    <biblioentry id="ref-metadata-gaps">
      <date>2023</date>
    </biblioentry>
    <bibliodiv xml:id="legacy">
      <title>Legacy References</title>
      <simpara>unsupported div text</simpara>
    </bibliodiv>
  </bibliography>
</book>
XML, 'DocBook bibliography XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeDocBookBibliography($dom);

        $t->same('xml-html5-docbook-dom', $packet['formatFamily']);
        $t->same('docbook', $packet['format']);
        $t->same('docbook-bibliography-reference-review-only', $packet['reviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same([
            'direct-reader-unsupported',
            'bibliography-review-only',
            'bibliography-id-duplicates',
            'reference-targets-missing',
            'bibliography-entry-metadata-missing',
            'bibliography-entry-metadata-duplicates',
            'unsupported-bibliography-children',
        ], $packet['directReaderDiagnosticCodes']);
        $t->same(7, $packet['directReaderDiagnosticCount']);
        $t->same(false, $packet['directReaderDiagnostics'][0]['directReaderParity'] ?? null);
        $t->same(true, $packet['directReaderDiagnostics'][0]['coveredByPacket'] ?? null);
        $t->same('docbook', $packet['directReaderDiagnostics'][0]['details']['format'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][1]['details']['bibliographyCount'] ?? null);
        $t->same(4, $packet['directReaderDiagnostics'][1]['details']['entryCount'] ?? null);
        $t->same(['ref-dup'], $packet['directReaderDiagnostics'][2]['details']['duplicateIds'] ?? null);
        $t->same(['ref-missing'], $packet['directReaderDiagnostics'][3]['details']['missingTargets'] ?? null);
        $t->same(true, in_array('docbook-bibliography-entry-missing-contributor', $packet['directReaderDiagnostics'][4]['details']['missingMetadataCodes'] ?? [], true));
        $t->same(true, in_array('docbook-bibliography-entry-conflicting-id', $packet['directReaderDiagnostics'][5]['details']['duplicateMetadataCodes'] ?? [], true));
        $t->same(2, $packet['directReaderDiagnostics'][6]['details']['unsupportedChildCount'] ?? null);
        $t->same('book', $packet['rootName']);
        $t->same('5.2', $packet['docbookVersion']);
        $t->same('en', $packet['language']);
        $t->same('en', $packet['rootAttributes']['xml:lang'] ?? null);
        $t->same(1, $packet['bibliographyCount']);
        $t->same(['refs'], $packet['bibliographyIds']);
        $t->same('Works Cited', $packet['bibliographies'][0]['title'] ?? null);
        $t->same(['ref-good', 'ref-dup', 'ref-metadata-gaps'], $packet['bibliographies'][0]['entryIds'] ?? null);
        $t->same(4, $packet['bibliographies'][0]['entryCount'] ?? null);
        $t->same(4, $packet['bibliographyEntryCount']);
        $t->same(['ref-good', 'ref-dup', 'ref-metadata-gaps'], $packet['bibliographyEntryIds']);
        $t->same(['ref-good', 'ref-dup', 'ref-metadata-gaps'], $packet['biblioentryIds']);
        $t->same(['ref-dup'], $packet['bibliomixedIds']);
        $t->same(['refs' => 1, 'ref-good' => 1, 'ref-dup' => 2, 'ref-metadata-gaps' => 1, 'legacy' => 1], $packet['bibliographicIdOccurrences']);
        $t->same(['ref-dup'], $packet['duplicateBibliographyIds']);
        $t->same('biblioentry', $packet['bibliographyEntries'][0]['element'] ?? null);
        $t->same('ref-good', $packet['bibliographyEntries'][0]['id'] ?? null);
        $t->same('ref-good', $packet['bibliographyEntries'][0]['xmlId'] ?? null);
        $t->same('ref-good-legacy', $packet['bibliographyEntries'][0]['idAttribute'] ?? null);
        $t->same('xml:id+id', $packet['bibliographyEntries'][0]['idSource'] ?? null);
        $t->same(true, $packet['bibliographyEntries'][0]['idConflict'] ?? null);
        $t->same('Portable Imports', $packet['bibliographyEntries'][0]['title'] ?? null);
        $t->same(2, $packet['bibliographyEntries'][0]['titleCount'] ?? null);
        $t->same([
            ['element' => 'title', 'value' => 'Portable Imports'],
            ['element' => 'title', 'value' => 'Portable Imports'],
        ], $packet['bibliographyEntries'][0]['titleMetadata'] ?? null);
        $t->same(['Ada Zed'], $packet['bibliographyEntries'][0]['authors'] ?? null);
        $t->same(2, $packet['bibliographyEntries'][0]['authorCount'] ?? null);
        $t->same(['Nia Editor'], $packet['bibliographyEntries'][0]['editors'] ?? null);
        $t->same(['Ada Zed', 'Nia Editor'], $packet['bibliographyEntries'][0]['contributorNames'] ?? null);
        $t->same(['author', 'editor'], $packet['bibliographyEntries'][0]['contributorRoles'] ?? null);
        $t->same('Port Libs Press', $packet['bibliographyEntries'][0]['publisher'] ?? null);
        $t->same(['Port Libs Press'], $packet['bibliographyEntries'][0]['publisherNames'] ?? null);
        $t->same([
            ['element' => 'pubdate', 'value' => '2026'],
            ['element' => 'year', 'value' => '2026'],
        ], $packet['bibliographyEntries'][0]['yearLikeMetadata'] ?? null);
        $t->same(['2026'], $packet['bibliographyEntries'][0]['yearLikeValues'] ?? null);
        $t->same(['2026'], $packet['bibliographyEntries'][0]['dateValues'] ?? null);
        $t->same('bibliomixed', $packet['bibliographyEntries'][1]['element'] ?? null);
        $t->same('Mixed Reference', $packet['bibliographyEntries'][1]['title'] ?? null);
        $t->same([], $packet['bibliographyEntries'][1]['authors'] ?? null);
        $t->same(['Bob Mix'], $packet['bibliographyEntries'][1]['editors'] ?? null);
        $t->same('Mixed Press', $packet['bibliographyEntries'][1]['publisher'] ?? null);
        $t->same(['2025'], $packet['bibliographyEntries'][1]['yearLikeValues'] ?? null);
        $t->same(['2024-05'], $packet['bibliographyEntries'][2]['yearLikeValues'] ?? null);
        $t->same('ref-metadata-gaps', $packet['bibliographyEntries'][3]['id'] ?? null);
        $t->same(null, $packet['bibliographyEntries'][3]['xmlId'] ?? null);
        $t->same('ref-metadata-gaps', $packet['bibliographyEntries'][3]['idAttribute'] ?? null);
        $t->same('id', $packet['bibliographyEntries'][3]['idSource'] ?? null);
        $t->same(true, in_array('docbook-bibliography-entry-missing-title', $packet['bibliographyEntryMetadataDiagnosticCodes'], true));
        $t->same(true, in_array('docbook-bibliography-entry-missing-publisher', $packet['bibliographyEntryMetadataDiagnosticCodes'], true));
        $t->same(true, in_array('docbook-bibliography-entry-duplicate-title', $packet['bibliographyEntryMetadataDiagnosticCodes'], true));
        $t->same(true, in_array('docbook-bibliography-entry-duplicate-contributor', $packet['bibliographyEntryMetadataDiagnosticCodes'], true));
        $t->same(true, in_array('docbook-bibliography-entry-duplicate-date', $packet['bibliographyEntryMetadataDiagnosticCodes'], true));
        $t->same(true, in_array('docbook-bibliography-entry-conflicting-id', $packet['bibliographyEntryMetadataDiagnosticCodes'], true));
        $t->same(4, count($packet['missingBibliographyEntryMetadataDiagnostics']));
        $t->same(4, count($packet['duplicateBibliographyEntryMetadataDiagnostics']));
        $t->same(['ref-good', 'ref-dup', 'ref-missing'], $packet['referenceLinkTargets']);
        $t->same(3, $packet['referenceLinkTargetCount']);
        $t->same(['ref-good', 'ref-dup'], $packet['linkendTargets']);
        $t->same(['ref-good', 'ref-dup'], $packet['xrefTargets']);
        $t->same(['ref-missing'], $packet['citationTargets']);
        $t->same('duplicate-id', $packet['referenceTargetSummaries'][1]['status'] ?? null);
        $t->same(['link'], $packet['referenceTargetSummaries'][1]['elements'] ?? null);
        $t->same('missing', $packet['referenceTargetSummaries'][2]['status'] ?? null);
        $t->same(['ref-missing'], $packet['missingReferenceTargets']);
        $t->same(1, $packet['missingReferenceTargetCount']);
        $t->same('xref', $packet['referenceLinks'][0]['element'] ?? null);
        $t->same('ref-good', $packet['referenceLinks'][0]['target'] ?? null);
        $t->same('citation-text', $packet['referenceLinks'][2]['targetSource'] ?? null);
        $t->same(2, $packet['unsupportedBibliographyChildCount']);
        $t->same('biblioentry', $packet['unsupportedBibliographyChildren'][0]['parentElement'] ?? null);
        $t->same('ref-good', $packet['unsupportedBibliographyChildren'][0]['parentId'] ?? null);
        $t->same('mediaobject', $packet['unsupportedBibliographyChildren'][0]['childName'] ?? null);
        $t->same('bibliodiv', $packet['unsupportedBibliographyChildren'][1]['parentElement'] ?? null);
        $t->same('legacy', $packet['unsupportedBibliographyChildren'][1]['parentId'] ?? null);
        $t->same('simpara', $packet['unsupportedBibliographyChildren'][1]['childName'] ?? null);
        $t->same('unsupported div text', $packet['unsupportedBibliographyChildren'][1]['childText'] ?? null);
        $t->throws(InvalidArgumentException::class, static fn (): array => XmlHtmlDom::summarizeDocBookBibliography(new DOMDocument()));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes docbook structure review packets without direct reader parity claims' => static function (TestRunner $t): void {
        $docbook = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" version="5.2" xml:lang="en">
  <info>
    <title>DocBook Review Article</title>
    <subtitle>Structure packet</subtitle>
    <author><personname><firstname>Ada</firstname><surname>Review</surname></personname></author>
    <editor><orgname>Editorial Board</orgname></editor>
    <biblioid class="doi">10.5555/docbook.42</biblioid>
    <abstract><para>Native PHP review packet.</para></abstract>
  </info>
  <section xml:id="intro" role="scope">
    <title>Scope</title>
    <para>Body <xref linkend="fig1"/> text.</para>
    <note xml:id="n1"><title>Review Note</title><para>Check this.</para></note>
    <figure xml:id="fig1">
      <title>Figure A</title>
      <mediaobject><imageobject><imagedata fileref="images/a.png"/></imageobject></mediaobject>
    </figure>
    <informaltable xml:id="tbl1"><tgroup cols="1"><tbody><row><entry>Cell</entry></row></tbody></tgroup></informaltable>
    <section xml:id="nested"><title>Nested</title><simpara>Nested text.</simpara></section>
  </section>
  <bibliography><biblioentry xml:id="ref1"><title>Reference</title></biblioentry></bibliography>
  <para><link linkend="ref1">Reference</link><link xlink:href="https://example.invalid/review">Remote</link></para>
</article>
XML, 'DocBook 5 structure XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeDocBookStructure($docbook, 'docbook5');

        $t->same('xml-html5-docbook-dom', $packet['formatFamily']);
        $t->same('docbook5', $packet['format']);
        $t->same('docbook-structure-review-only', $packet['reviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same(['docbook-direct-reader-incomplete', 'docbook-body-conversion-review-only'], $packet['unsupportedDiagnostics']);
        $t->same('article', $packet['rootName']);
        $t->same('5.2', $packet['docbookVersion']);
        $t->same('http://docbook.org/ns/docbook', $packet['namespaceUri']);
        $t->same('en', $packet['language']);
        $t->same('en', $packet['rootAttributes']['xml:lang'] ?? null);
        $t->same('info', $packet['metadataRoot']);
        $t->same('DocBook Review Article', $packet['title']);
        $t->same('Structure packet', $packet['subtitle']);
        $t->same('Native PHP review packet.', $packet['abstractText']);
        $t->same([['element' => 'biblioid', 'type' => 'doi', 'value' => '10.5555/docbook.42']], $packet['identifiers']);
        $t->same(1, $packet['identifierCount']);
        $t->same(['Ada Review', 'Editorial Board'], $packet['contributorNames']);
        $t->same(['author', 'editor'], $packet['contributorRoles']);
        $t->same(2, $packet['sectionCount']);
        $t->same(['Scope', 'Nested'], $packet['sectionTitles']);
        $t->same('intro', $packet['sections'][0]['id'] ?? null);
        $t->same('scope', $packet['sections'][0]['role'] ?? null);
        $t->same(3, $packet['sections'][0]['paragraphCount'] ?? null);
        $t->same(1, $packet['sections'][0]['directParagraphCount'] ?? null);
        $t->same(1, $packet['sections'][0]['childSectionCount'] ?? null);
        $t->same(1, $packet['sections'][0]['figureCount'] ?? null);
        $t->same(1, $packet['sections'][0]['tableCount'] ?? null);
        $t->same(1, $packet['sections'][0]['admonitionCount'] ?? null);
        $t->same(['fig1'], $packet['figureIds']);
        $t->same(1, $packet['figureCount']);
        $t->same('Figure A', $packet['figures'][0]['title'] ?? null);
        $t->same('Figure A', $packet['figures'][0]['captionText'] ?? null);
        $t->same(['images/a.png'], $packet['figures'][0]['imageDataRefs'] ?? null);
        $t->same(['tbl1'], $packet['tableIds']);
        $t->same(1, $packet['tableCount']);
        $t->same(['n1'], $packet['admonitionIds']);
        $t->same('note', $packet['admonitions'][0]['type'] ?? null);
        $t->same(['fig1', 'ref1'], $packet['xrefTargets']);
        $t->same(2, $packet['xrefCount']);
        $t->same(2, $packet['captionCrossReferenceCount']);
        $t->same([], $packet['missingCaptionTargets']);
        $t->same([], $packet['captionDiagnosticCodes']);
        $t->same(2, $packet['mediaTargetManifestCount']);
        $t->same('fig1', $packet['mediaTargetManifest'][0]['id'] ?? null);
        $t->same(1, $packet['mediaTargetManifest'][0]['referenceCount'] ?? null);
        $t->same(['https://example.invalid/review'], $packet['externalTargets']);
        $t->same(1, $packet['bibliographyCount']);
        $t->same(1, $packet['bibliographyEntryCount']);
        $t->same(1, $packet['mediaObjectCount']);
        $t->same(1, $packet['imageObjectCount']);
        $t->same(['images/a.png'], $packet['imageDataRefs']);
        json_encode($packet, JSON_THROW_ON_ERROR);

        $docbook4 = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book lang="de">
  <bookinfo>
    <title>Legacy Book</title>
    <isbn>978-1-55555-042-0</isbn>
    <editor><firstname>Eva</firstname><surname>Alt</surname></editor>
  </bookinfo>
  <chapter id="ch1"><title>Chapter One</title><para>Text.</para></chapter>
</book>
XML, 'DocBook 4 structure XML', preserveWhiteSpace: false);
        $legacyPacket = XmlHtmlDom::summarizeDocBookStructure($docbook4, 'docbook4');

        $t->same('docbook4', $legacyPacket['format']);
        $t->same('book', $legacyPacket['rootName']);
        $t->same(null, $legacyPacket['namespaceUri']);
        $t->same('de', $legacyPacket['language']);
        $t->same('bookinfo', $legacyPacket['metadataRoot']);
        $t->same('Legacy Book', $legacyPacket['title']);
        $t->same([['element' => 'isbn', 'type' => null, 'value' => '978-1-55555-042-0']], $legacyPacket['identifiers']);
        $t->same(['Eva Alt'], $legacyPacket['contributorNames']);
        $t->same(1, $legacyPacket['sectionCount']);
        $t->same('chapter', $legacyPacket['sections'][0]['element'] ?? null);
        $t->same('ch1', $legacyPacket['sections'][0]['id'] ?? null);
        $t->same(1, $legacyPacket['sections'][0]['paragraphCount'] ?? null);
        $t->throws(InvalidArgumentException::class, static fn (): array => XmlHtmlDom::summarizeDocBookStructure($docbook, 'jats'));
        $t->throws(InvalidArgumentException::class, static fn (): array => XmlHtmlDom::summarizeDocBookStructure($docbook = XmlHtmlDom::loadXmlDocument('<topic><title>Nope</title></topic>', 'non docbook XML')));
        json_encode($legacyPacket, JSON_THROW_ON_ERROR);
    },
    'summarizes docbook section title metadata without reader parity claims' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(
            (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-docbook-sections.xml'),
            'DocBook section metadata XML',
            preserveWhiteSpace: false
        );
        $packet = XmlHtmlDom::summarizeDocBookSectionMetadata($dom);

        $t->same('xml-html5-jats-dom', $packet['formatFamily']);
        $t->same('docbook', $packet['format']);
        $t->same('docbook-section-title-xref-metadata-review-only', $packet['reviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same('article', $packet['rootName']);
        $t->same('5.0', $packet['documentVersion']);
        $t->same('en', $packet['language']);
        $t->same('en', $packet['rootAttributes']['xml:lang'] ?? null);
        $t->same('WordPress DocBook Review Packet', $packet['title']);
        $t->same('info-title', $packet['titleSource']);
        $t->same('Section title metadata', $packet['subtitle']);
        $t->same(5, $packet['sectionCount']);
        $t->same([
            'Import Overview',
            'Review Queue',
            'Legacy Section',
            'Duplicate Queue Identifier',
        ], $packet['sectionTitles']);

        $overview = $packet['sections'][0] ?? [];
        $queue = $packet['sections'][1] ?? [];
        $untitled = $packet['sections'][2] ?? [];
        $legacy = $packet['sections'][3] ?? [];
        $duplicate = $packet['sections'][4] ?? [];

        $t->same('section', $overview['element'] ?? null);
        $t->same('overview', $overview['id'] ?? null);
        $t->same('overview', $overview['xmlId'] ?? null);
        $t->same('summary', $overview['role'] ?? null);
        $t->same('1', $overview['label'] ?? null);
        $t->same('en-US', $overview['language'] ?? null);
        $t->same(1, $overview['level'] ?? null);
        $t->same(2, $overview['paragraphCount'] ?? null);
        $t->same(2, $overview['childSectionCount'] ?? null);

        $t->same('queue', $queue['id'] ?? null);
        $t->same('Review Queue', $queue['title'] ?? null);
        $t->same('info-title', $queue['titleSource'] ?? null);
        $t->same('Metadata from info', $queue['subtitle'] ?? null);
        $t->same(2, $queue['level'] ?? null);

        $t->same('simplesect', $untitled['element'] ?? null);
        $t->same('untitled', $untitled['id'] ?? null);
        $t->same(null, $untitled['title'] ?? null);
        $t->same(null, $untitled['titleSource'] ?? null);
        $t->same(2, $untitled['level'] ?? null);

        $t->same('sect1', $legacy['element'] ?? null);
        $t->same('legacy-section', $legacy['id'] ?? null);
        $t->same('legacy', $legacy['role'] ?? null);
        $t->same(1, $legacy['level'] ?? null);

        $t->same('queue', $duplicate['id'] ?? null);
        $t->same([
            'docbook-section-missing-title',
            'docbook-section-duplicate-id',
            'docbook-xref-target-duplicate-section-id',
            'docbook-xref-target-missing-anchor',
            'docbook-xref-target-unsafe',
        ], $packet['diagnosticCodes']);
        $t->same(1, $packet['missingTitleCount']);
        $t->same(1, $packet['duplicateIdCount']);
        $t->same('untitled', $packet['diagnostics'][0]['sectionId'] ?? null);
        $t->same('queue', $packet['diagnostics'][1]['sectionId'] ?? null);
        $t->same(1, $packet['diagnostics'][1]['firstSectionIndex'] ?? null);
        $t->throws(InvalidArgumentException::class, static fn (): array => XmlHtmlDom::summarizeDocBookSectionMetadata(
            XmlHtmlDom::loadXmlDocument('<topic><title>Not DocBook</title></topic>', 'non DocBook XML')
        ));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes jats and bits bibliography reference diagnostics without citation text leakage' => static function (TestRunner $t): void {
        $jats = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article" xml:lang="en" xmlns:xlink="http://www.w3.org/1999/xlink">
  <front><article-meta><title-group><article-title>Reference Diagnostics</article-title></title-group></article-meta></front>
  <body>
    <sec id="refs-sec"><title>References</title><p>See <xref ref-type="bibr" rid="ref-a ref-missing">[1, missing]</xref>.</p></sec>
  </body>
  <back>
    <ref-list id="refs"><title>References</title>
      <ref id="ref-a">
        <label>1</label>
        <element-citation publication-type="journal">
          <person-group person-group-type="author"><name><surname>Ng</surname><given-names>Lin</given-names></name></person-group>
          <article-title>Bounded Citation Metadata</article-title>
          <source>Journal of Native Ports</source>
          <year>2026</year>
          <date-in-citation content-type="publication-date" iso-8601-date="2026-06-12"><year>2026</year><month>06</month><day>12</day></date-in-citation>
          <pub-id pub-id-type="doi">10.5555/native.1</pub-id>
          <pub-id pub-id-type="pmid">12345678</pub-id>
          <pub-id pub-id-type="pmcid">PMC123456</pub-id>
          <uri>https://example.invalid/ref-a</uri>
        </element-citation>
      </ref>
      <ref id="ref-b">
        <label>2</label>
        <mixed-citation publication-type="book">Blocked raw book citation payload <collab>Review Group</collab><source>Back Matter Handbook</source><year>2025</year><pub-id pub-id-type="doi">https://doi.org/10.5555/native.1</pub-id><isbn>978-1-55555-999-9</isbn><ext-link ext-link-type="uri" xlink:href="https://example.invalid/book">Book</ext-link></mixed-citation>
      </ref>
      <ref><mixed-citation>Blocked unidentified citation payload</mixed-citation></ref>
    </ref-list>
  </back>
</article>
XML, 'JATS bibliography diagnostics XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($jats);

        $t->same(false, $packet['directReaderParity']);
        $t->same([
            'direct-reader-unsupported',
            'body-sections-review-only',
            'references-review-only',
            'reference-citation-text-policy',
            'reference-author-date-policy',
            'reference-identifier-policy',
            'reference-identifiers-duplicate',
            'reference-identifiers-missing',
            'bibliography-xrefs-unresolved',
        ], $packet['directReaderDiagnosticCodes']);
        $t->same(1, $packet['referenceListCount']);
        $t->same('refs', $packet['referenceLists'][0]['id'] ?? null);
        $t->same('References', $packet['referenceLists'][0]['title'] ?? null);
        $t->same(3, $packet['referenceLists'][0]['referenceCount'] ?? null);
        $t->same(['ref-a', 'ref-b'], $packet['referenceLists'][0]['referenceIds'] ?? null);
        $t->same(3, $packet['referenceCount']);
        $t->same(3, $packet['referenceMetadataSummaryCount']);
        $t->same(1, $packet['missingIdReferenceCount']);
        $t->same('safe-labels-only-block-citation-text-payloads', $packet['referenceCitationTextPolicy']);
        $t->same([['id' => 'ref-a', 'label' => '1'], ['id' => 'ref-b', 'label' => '2']], $packet['safeReferenceLabels']);
        $t->same(2, $packet['safeReferenceLabelCount']);
        $t->same('safe-reference-author-date-year-summaries-block-citation-text-payloads', $packet['referenceAuthorDateReviewPolicy']);
        $t->same([
            ['id' => 'ref-a', 'authorNames' => ['Lin Ng']],
            ['id' => 'ref-b', 'authorNames' => ['Review Group']],
        ], $packet['safeReferenceAuthors']);
        $t->same(2, $packet['safeReferenceAuthorCount']);
        $t->same([
            [
                'id' => 'ref-a',
                'dates' => [[
                    'element' => 'date-in-citation',
                    'type' => 'publication-date',
                    'year' => '2026',
                    'month' => '06',
                    'day' => '12',
                    'iso' => '2026-06-12',
                ]],
            ],
        ], $packet['safeReferenceDates']);
        $t->same(1, $packet['safeReferenceDateCount']);
        $t->same([
            ['id' => 'ref-a', 'years' => ['2026']],
            ['id' => 'ref-b', 'years' => ['2025']],
        ], $packet['safeReferenceYears']);
        $t->same(2, $packet['safeReferenceYearCount']);
        $t->same('safe-reference-title-source-summaries-block-citation-text-payloads', $packet['referenceTitleSourceReviewPolicy']);
        $t->same(1, $packet['safeReferenceTitleFieldCount']);
        $t->same(2, $packet['safeReferenceSourceCount']);
        $t->same(['journal', 'book'], $packet['referenceSourceTypes']);
        $t->same(2, $packet['referencesMissingTitleCount']);
        $t->same(1, $packet['referencesMissingSourceCount']);
        $t->same('safe-reference-identifier-provenance-only-block-citation-text-payloads', $packet['referenceIdentifierReviewPolicy']);
        $t->same(7, $packet['referenceIdentifierCount']);
        $t->same(['doi', 'pmid', 'pmcid', 'uri', 'isbn'], $packet['referenceIdentifierTypes']);
        $t->same([
            ['sourceElement' => 'ext-link', 'sourceAttribute' => 'xlink:href', 'sourceType' => 'uri', 'type' => 'uri', 'count' => 1],
            ['sourceElement' => 'isbn', 'sourceAttribute' => null, 'sourceType' => null, 'type' => 'isbn', 'count' => 1],
            ['sourceElement' => 'pub-id', 'sourceAttribute' => null, 'sourceType' => 'doi', 'type' => 'doi', 'count' => 2],
            ['sourceElement' => 'pub-id', 'sourceAttribute' => null, 'sourceType' => 'pmcid', 'type' => 'pmcid', 'count' => 1],
            ['sourceElement' => 'pub-id', 'sourceAttribute' => null, 'sourceType' => 'pmid', 'type' => 'pmid', 'count' => 1],
            ['sourceElement' => 'uri', 'sourceAttribute' => null, 'sourceType' => null, 'type' => 'uri', 'count' => 1],
        ], $packet['referenceIdentifierSourceSummaries']);
        $t->same(6, $packet['referenceIdentifierSourceSummaryCount']);
        $t->same([
            'type' => 'doi',
            'value' => '10.5555/native.1',
            'normalizedValue' => '10.5555/native.1',
            'referenceIds' => ['ref-a', 'ref-b'],
            'referenceCount' => 2,
            'sourceCount' => 2,
        ], $packet['duplicateReferenceIdentifiers'][0] ?? null);
        $t->same(1, $packet['duplicateReferenceIdentifierCount']);
        $t->same(1, $packet['referencesMissingIdentifierCount']);
        $t->same(null, $packet['referencesMissingIdentifiers'][0]['id'] ?? null);
        $t->same('missing-id', $packet['referencesMissingIdentifiers'][0]['status'] ?? null);
        $t->same(['mixed-citation'], $packet['referencesMissingIdentifiers'][0]['citationElementNames'] ?? null);
        $t->same(3, $packet['blockedCitationTextPayloadCount']);
        $t->same(['element-citation', 'mixed-citation'], $packet['blockedCitationTextPayloadElementNames']);
        $t->same(2, $packet['bibliographyXrefCount']);
        $t->same(1, $packet['resolvedBibrXrefCount']);
        $t->same(1, $packet['unresolvedBibrXrefCount']);
        $t->same(['ref-a'], $packet['resolvedReferenceIds']);
        $t->same(['ref-missing'], $packet['unresolvedReferenceIds']);
        $t->same(['ref-b'], $packet['unreferencedReferenceIds']);
        $t->same(1, $packet['unreferencedReferenceCount']);
        $t->same('ref-a', $packet['bibliographyXrefs'][0]['targetId'] ?? null);
        $t->same('resolved', $packet['bibliographyXrefs'][0]['status'] ?? null);
        $t->same(12, $packet['bibliographyXrefs'][0]['sourceTextLength'] ?? null);
        $t->same(4, $packet['bibliographyXrefs'][0]['targetIdentifierCount'] ?? null);
        $t->same(['doi', 'pmid', 'pmcid', 'uri'], $packet['bibliographyXrefs'][0]['targetIdentifierTypes'] ?? null);
        $t->same('ref-missing', $packet['bibliographyXrefs'][1]['targetId'] ?? null);
        $t->same('unresolved', $packet['bibliographyXrefs'][1]['status'] ?? null);
        $t->same(0, $packet['bibliographyXrefs'][1]['targetIdentifierCount'] ?? null);
        $t->same([
            'targetId' => 'ref-a',
            'targetIdentifierCount' => 4,
            'targetIdentifierTypes' => ['doi', 'pmid', 'pmcid', 'uri'],
            'targetIdentifiers' => $packet['bibliographyXrefs'][0]['targetIdentifiers'],
        ], $packet['resolvedBibrIdentifierTargets'][0] ?? null);
        $t->same(1, $packet['resolvedBibrIdentifierTargetCount']);
        $t->same(1, $packet['directReaderDiagnostics'][2]['details']['resolvedBibrXrefCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][2]['details']['unresolvedBibrXrefCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][2]['details']['safeReferenceLabelCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][2]['details']['safeReferenceAuthorCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][2]['details']['safeReferenceDateCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][2]['details']['safeReferenceYearCount'] ?? null);
        $t->same(7, $packet['directReaderDiagnostics'][2]['details']['referenceIdentifierCount'] ?? null);
        $t->same(6, $packet['directReaderDiagnostics'][2]['details']['referenceIdentifierSourceSummaryCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][2]['details']['resolvedBibrIdentifierTargetCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][2]['details']['duplicateReferenceIdentifierCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][2]['details']['referencesMissingIdentifierCount'] ?? null);
        $t->same(3, $packet['directReaderDiagnostics'][2]['details']['blockedCitationTextPayloadCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][3]['details']['safeReferenceLabelCount'] ?? null);
        $t->same(3, $packet['directReaderDiagnostics'][3]['details']['blockedCitationTextPayloadCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][4]['details']['safeReferenceAuthorCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][4]['details']['safeReferenceDateCount'] ?? null);
        $t->same(2, $packet['directReaderDiagnostics'][4]['details']['safeReferenceYearCount'] ?? null);
        $t->same(3, $packet['directReaderDiagnostics'][4]['details']['blockedCitationTextPayloadCount'] ?? null);
        $t->same(7, $packet['directReaderDiagnostics'][5]['details']['referenceIdentifierCount'] ?? null);
        $t->same(6, $packet['directReaderDiagnostics'][5]['details']['referenceIdentifierSourceSummaryCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][5]['details']['referencesMissingIdentifierCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][6]['details']['duplicateReferenceIdentifierCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][7]['details']['referencesMissingIdentifierCount'] ?? null);
        $t->same(1, $packet['directReaderDiagnostics'][8]['details']['unresolvedBibrXrefCount'] ?? null);

        $resolved = $packet['references'][0];
        $t->same('ref-a', $resolved['id'] ?? null);
        $t->same('1', $resolved['label'] ?? null);
        $t->same('resolved-by-bibr-xref', $resolved['status'] ?? null);
        $t->same(true, $resolved['metadataOnly'] ?? null);
        $t->same(['element-citation'], $resolved['citationElementNames'] ?? null);
        $t->same(1, $resolved['blockedCitationTextPayloadCount'] ?? null);
        $t->same(['journal'], $resolved['publicationTypes'] ?? null);
        $t->same([
            ['element' => 'pub-id', 'type' => 'doi', 'value' => '10.5555/native.1'],
            ['element' => 'pub-id', 'type' => 'pmid', 'value' => '12345678'],
            ['element' => 'pub-id', 'type' => 'pmcid', 'value' => 'PMC123456'],
        ], $resolved['publicationIds'] ?? null);
        $t->same('Bounded Citation Metadata', $resolved['articleTitle'] ?? null);
        $t->same('Journal of Native Ports', $resolved['sourceTitle'] ?? null);
        $t->same('2026', $resolved['year'] ?? null);
        $t->same(['2026'], $resolved['years'] ?? null);
        $t->same(1, $resolved['yearCount'] ?? null);
        $t->same([[
            'element' => 'date-in-citation',
            'type' => 'publication-date',
            'year' => '2026',
            'month' => '06',
            'day' => '12',
            'iso' => '2026-06-12',
        ]], $resolved['dates'] ?? null);
        $t->same(1, $resolved['dateCount'] ?? null);
        $t->same([['role' => 'author', 'name' => 'Lin Ng', 'source' => 'person-group']], $resolved['authors'] ?? null);
        $t->same(['Lin Ng'], $resolved['authorNames'] ?? null);
        $t->same(1, $resolved['authorCount'] ?? null);
        $t->same(4, $resolved['identifierCount'] ?? null);
        $t->same(['doi', 'pmid', 'pmcid', 'uri'], $resolved['identifierTypes'] ?? null);
        $t->same([
            'type' => 'doi',
            'value' => '10.5555/native.1',
            'normalizedValue' => '10.5555/native.1',
            'sourceElement' => 'pub-id',
            'sourceAttribute' => null,
            'sourceType' => 'doi',
            'sourceCitationElement' => 'element-citation',
        ], $resolved['identifiers'][0] ?? null);
        $t->same([
            'type' => 'pmid',
            'value' => '12345678',
            'normalizedValue' => '12345678',
            'sourceElement' => 'pub-id',
            'sourceAttribute' => null,
            'sourceType' => 'pmid',
            'sourceCitationElement' => 'element-citation',
        ], $resolved['identifiers'][1] ?? null);
        $t->same('pmcid', $resolved['identifiers'][2]['type'] ?? null);
        $t->same('PMC123456', $resolved['identifiers'][2]['normalizedValue'] ?? null);
        $t->same('uri', $resolved['identifiers'][3]['type'] ?? null);
        $t->same(['author'], $resolved['personGroupTypes'] ?? null);
        $t->same(1, $resolved['nameCount'] ?? null);
        $t->same(1, $resolved['uriCount'] ?? null);
        $t->same(0, $resolved['extLinkCount'] ?? null);
        $t->true(($resolved['textLength'] ?? 0) > 0);
        $t->same(1, preg_match('/^[a-f0-9]{64}$/', (string) ($resolved['textSha256'] ?? '')));
        $t->true(!array_key_exists('text', $resolved), 'Expected reference summaries to avoid raw citation text');

        $unreferenced = $packet['references'][1];
        $t->same('ref-b', $unreferenced['id'] ?? null);
        $t->same('unreferenced', $unreferenced['status'] ?? null);
        $t->same(['mixed-citation'], $unreferenced['citationElementNames'] ?? null);
        $t->same(1, $unreferenced['blockedCitationTextPayloadCount'] ?? null);
        $t->same([['role' => 'collab', 'name' => 'Review Group', 'source' => 'collab']], $unreferenced['authors'] ?? null);
        $t->same(['Review Group'], $unreferenced['authorNames'] ?? null);
        $t->same(1, $unreferenced['authorCount'] ?? null);
        $t->same(['2025'], $unreferenced['years'] ?? null);
        $t->same(1, $unreferenced['yearCount'] ?? null);
        $t->same(3, $unreferenced['identifierCount'] ?? null);
        $t->same(['doi', 'isbn', 'uri'], $unreferenced['identifierTypes'] ?? null);
        $t->same('10.5555/native.1', $unreferenced['identifiers'][0]['normalizedValue'] ?? null);
        $t->same('9781555559999', $unreferenced['identifiers'][1]['normalizedValue'] ?? null);
        $t->same('xlink:href', $unreferenced['identifiers'][2]['sourceAttribute'] ?? null);
        $t->same(1, $unreferenced['collabCount'] ?? null);
        $t->same(1, $unreferenced['extLinkCount'] ?? null);
        $t->same('missing-id', $packet['references'][2]['status'] ?? null);
        $t->same(1, $packet['references'][2]['blockedCitationTextPayloadCount'] ?? null);
        $encodedPacket = json_encode($packet, JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encodedPacket, 'Blocked raw book citation payload'), 'Expected raw mixed-citation payload text to stay blocked');
        $t->true(!str_contains($encodedPacket, 'Blocked unidentified citation payload'), 'Expected raw unidentified citation payload text to stay blocked');

        $bits = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book book-type="edited-book" xml:lang="en">
  <book-meta><book-id book-id-type="isbn">978-1-55555-111-3</book-id><title-group><book-title>BITS Bibliography</book-title></title-group></book-meta>
  <book-body>
    <book-part id="bp1"><book-part-meta><title-group><title>Chapter</title></title-group></book-part-meta><body><p>See <xref ref-type="bibr" rid="book-ref lost-ref">bibliography</xref>.</p></body></book-part>
  </book-body>
  <back><ref-list id="bibliography"><title>Bibliography</title><ref id="book-ref"><mixed-citation publication-type="book"><person-group person-group-type="author"><collab>BITS Editors</collab></person-group><source>Native BITS References</source><year>2024</year><date-in-citation content-type="publication-date"><year>2024</year><month>05</month></date-in-citation><isbn>978-1-55555-222-9</isbn><uri>https://example.invalid/bits-ref</uri></mixed-citation></ref></ref-list></back>
</book>
XML, 'BITS bibliography diagnostics XML', preserveWhiteSpace: false);
        $bitsPacket = XmlHtmlDom::summarizeJatsFrontMatter($bits, 'bits');

        $t->same(false, $bitsPacket['directReaderParity']);
        $t->same([
            'direct-reader-unsupported',
            'references-review-only',
            'reference-citation-text-policy',
            'reference-author-date-policy',
            'reference-identifier-policy',
            'bibliography-xrefs-unresolved',
            'book-parts-review-only',
        ], $bitsPacket['directReaderDiagnosticCodes']);
        $t->same('bibliography', $bitsPacket['referenceLists'][0]['id'] ?? null);
        $t->same(['book-ref'], $bitsPacket['referenceIds']);
        $t->same(['book-ref'], $bitsPacket['resolvedReferenceIds']);
        $t->same(['lost-ref'], $bitsPacket['unresolvedReferenceIds']);
        $t->same(0, $bitsPacket['safeReferenceLabelCount']);
        $t->same([['id' => 'book-ref', 'authorNames' => ['BITS Editors']]], $bitsPacket['safeReferenceAuthors']);
        $t->same(1, $bitsPacket['safeReferenceAuthorCount']);
        $t->same(1, $bitsPacket['safeReferenceDateCount']);
        $t->same([['id' => 'book-ref', 'years' => ['2024']]], $bitsPacket['safeReferenceYears']);
        $t->same(1, $bitsPacket['safeReferenceYearCount']);
        $t->same(0, $bitsPacket['safeReferenceTitleFieldCount']);
        $t->same(1, $bitsPacket['safeReferenceSourceCount']);
        $t->same(['book'], $bitsPacket['referenceSourceTypes']);
        $t->same(1, $bitsPacket['referencesMissingTitleCount']);
        $t->same(0, $bitsPacket['referencesMissingSourceCount']);
        $t->same(2, $bitsPacket['referenceIdentifierCount']);
        $t->same(['isbn', 'uri'], $bitsPacket['referenceIdentifierTypes']);
        $t->same(0, $bitsPacket['referencesMissingIdentifierCount']);
        $t->same(1, $bitsPacket['blockedCitationTextPayloadCount']);
        $t->same(['mixed-citation'], $bitsPacket['blockedCitationTextPayloadElementNames']);
        $t->same('resolved-by-bibr-xref', $bitsPacket['references'][0]['status'] ?? null);
        $t->same('Native BITS References', $bitsPacket['references'][0]['sourceTitle'] ?? null);
        $t->same(['BITS Editors'], $bitsPacket['references'][0]['authorNames'] ?? null);
        $t->same(2, $bitsPacket['references'][0]['identifierCount'] ?? null);
        $t->same('isbn', $bitsPacket['references'][0]['identifiers'][0]['type'] ?? null);
        $t->same('9781555552229', $bitsPacket['references'][0]['identifiers'][0]['normalizedValue'] ?? null);
        $t->same('uri', $bitsPacket['references'][0]['identifiers'][1]['type'] ?? null);
        $t->same([[
            'element' => 'date-in-citation',
            'type' => 'publication-date',
            'year' => '2024',
            'month' => '05',
            'day' => null,
            'iso' => '2024-05',
        ]], $bitsPacket['references'][0]['dates'] ?? null);
        $t->same(1, $bitsPacket['bookPartCount']);
        json_encode($bitsPacket, JSON_THROW_ON_ERROR);
    },
    'summarizes jats reference title source diagnostics without citation payload text leakage' => static function (TestRunner $t): void {
        $jats = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article" xml:lang="en" xmlns:xlink="http://www.w3.org/1999/xlink">
  <front><article-meta><title-group><article-title>Reference Title Source Diagnostics</article-title></title-group></article-meta></front>
  <body><sec id="body"><title>Body</title><p>See <xref ref-type="bibr" rid="ref-a ref-b ref-missing">citations</xref>.</p></sec></body>
  <back>
    <ref-list id="refs"><title>References</title>
      <ref id="ref-a">
        <label>1</label>
        <element-citation publication-type="journal">
          <article-title>Shared Diagnostic Title</article-title>
          <source>Shared Source</source>
          <pub-id pub-id-type="doi">10.5555/title.1</pub-id>
        </element-citation>
      </ref>
      <ref id="ref-b">
        <label>2</label>
        <mixed-citation publication-type="book">Blocked chapter citation payload <chapter-title>Chapter Diagnostics</chapter-title><source>Back Matter Manual</source><isbn>978-1-55555-333-5</isbn></mixed-citation>
      </ref>
      <ref id="ref-c">
        <label>3</label>
        <mixed-citation publication-type="web">Blocked duplicate citation payload <article-title>Shared Diagnostic Title</article-title><source>Shared Source</source><uri>https://example.invalid/ref-c</uri></mixed-citation>
      </ref>
      <ref id="ref-d">
        <label>4</label>
        <mixed-citation publication-type="web">Blocked missing title source payload <pub-id pub-id-type="doi">10.5555/missing.4</pub-id></mixed-citation>
      </ref>
    </ref-list>
  </back>
</article>
XML, 'JATS title source diagnostics XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($jats);

        $t->same(false, $packet['directReaderParity']);
        $t->same('safe-reference-title-source-summaries-block-citation-text-payloads', $packet['referenceTitleSourceReviewPolicy']);
        $t->same(4, $packet['referenceCount']);
        $t->same(3, $packet['safeReferenceTitleFieldCount']);
        $t->same(3, $packet['safeReferenceSourceCount']);
        $t->same(['journal', 'book', 'web'], $packet['referenceSourceTypes']);
        $t->same([
            ['sourceType' => 'book', 'referenceCount' => 1],
            ['sourceType' => 'journal', 'referenceCount' => 1],
            ['sourceType' => 'web', 'referenceCount' => 2],
        ], $packet['referenceSourceTypeSummaries']);
        $t->same([
            'reference-title-source-policy',
            'reference-citation-target-linkage',
            'reference-titles-duplicate',
            'reference-sources-duplicate',
            'reference-titles-missing',
            'reference-sources-missing',
        ], $packet['referenceTitleSourceDiagnosticCodes']);
        $t->same(6, $packet['referenceTitleSourceDiagnosticCount']);

        $t->same('ref-a', $packet['safeReferenceTitles'][0]['id'] ?? null);
        $t->same([[
            'element' => 'article-title',
            'value' => 'Shared Diagnostic Title',
            'sourceType' => 'journal',
            'sourceCitationElement' => 'element-citation',
        ]], $packet['safeReferenceTitles'][0]['titleFields'] ?? null);
        $t->same('ref-b', $packet['safeReferenceTitles'][1]['id'] ?? null);
        $t->same([[
            'element' => 'chapter-title',
            'value' => 'Chapter Diagnostics',
            'sourceType' => 'book',
            'sourceCitationElement' => 'mixed-citation',
        ]], $packet['safeReferenceTitles'][1]['titleFields'] ?? null);
        $t->same([[
            'element' => 'source',
            'value' => 'Back Matter Manual',
            'sourceType' => 'book',
            'sourceCitationElement' => 'mixed-citation',
        ]], $packet['safeReferenceSources'][1]['sourceFields'] ?? null);

        $t->same('Shared Diagnostic Title', $packet['duplicateReferenceTitles'][0]['value'] ?? null);
        $t->same(['ref-a', 'ref-c'], $packet['duplicateReferenceTitles'][0]['referenceIds'] ?? null);
        $t->same(['article-title'], $packet['duplicateReferenceTitles'][0]['elements'] ?? null);
        $t->same(['journal', 'web'], $packet['duplicateReferenceTitles'][0]['sourceTypes'] ?? null);
        $t->same(1, $packet['duplicateReferenceTitleCount']);
        $t->same('Shared Source', $packet['duplicateReferenceSources'][0]['value'] ?? null);
        $t->same(['ref-a', 'ref-c'], $packet['duplicateReferenceSources'][0]['referenceIds'] ?? null);
        $t->same(['source'], $packet['duplicateReferenceSources'][0]['elements'] ?? null);
        $t->same(1, $packet['duplicateReferenceSourceCount']);

        $t->same([[
            'id' => 'ref-d',
            'status' => 'unreferenced',
            'inboundBibrXrefCount' => 0,
            'citationElementNames' => ['mixed-citation'],
        ]], $packet['referencesMissingTitles']);
        $t->same(1, $packet['referencesMissingTitleCount']);
        $t->same([[
            'id' => 'ref-d',
            'status' => 'unreferenced',
            'inboundBibrXrefCount' => 0,
            'citationElementNames' => ['mixed-citation'],
        ]], $packet['referencesMissingSources']);
        $t->same(1, $packet['referencesMissingSourceCount']);
        $t->same([
            'resolvedReferenceIds' => ['ref-a', 'ref-b'],
            'resolvedBibrXrefCount' => 2,
            'unresolvedReferenceIds' => ['ref-missing'],
            'unresolvedBibrXrefCount' => 1,
            'unreferencedReferenceIds' => ['ref-c', 'ref-d'],
            'unreferencedReferenceCount' => 2,
        ], $packet['citationTargetLinkage']);

        $t->same(['Shared Diagnostic Title'], $packet['references'][0]['articleTitles'] ?? null);
        $t->same([], $packet['references'][0]['chapterTitles'] ?? null);
        $t->same(['Shared Source'], $packet['references'][0]['sourceTitles'] ?? null);
        $t->same('journal', $packet['references'][0]['referenceSourceType'] ?? null);
        $t->same([], $packet['references'][1]['articleTitles'] ?? null);
        $t->same(['Chapter Diagnostics'], $packet['references'][1]['chapterTitles'] ?? null);
        $t->same(['Back Matter Manual'], $packet['references'][1]['sourceTitles'] ?? null);
        $t->same('book', $packet['references'][1]['referenceSourceType'] ?? null);
        $t->same(0, $packet['references'][3]['titleFieldCount'] ?? null);
        $t->same(0, $packet['references'][3]['sourceFieldCount'] ?? null);
        $t->same('web', $packet['references'][3]['referenceSourceType'] ?? null);

        $t->same(4, $packet['referenceTitleSourceDiagnostics'][0]['details']['referenceCount'] ?? null);
        $t->same(3, $packet['referenceTitleSourceDiagnostics'][0]['details']['safeReferenceTitleFieldCount'] ?? null);
        $t->same(3, $packet['referenceTitleSourceDiagnostics'][0]['details']['safeReferenceSourceCount'] ?? null);
        $t->same(2, $packet['referenceTitleSourceDiagnostics'][1]['details']['resolvedBibrXrefCount'] ?? null);
        $t->same(1, $packet['referenceTitleSourceDiagnostics'][2]['details']['duplicateReferenceTitleCount'] ?? null);
        $t->same(1, $packet['referenceTitleSourceDiagnostics'][3]['details']['duplicateReferenceSourceCount'] ?? null);
        $t->same(1, $packet['referenceTitleSourceDiagnostics'][4]['details']['referencesMissingTitleCount'] ?? null);
        $t->same(1, $packet['referenceTitleSourceDiagnostics'][5]['details']['referencesMissingSourceCount'] ?? null);

        $encodedPacket = json_encode($packet, JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encodedPacket, 'Blocked chapter citation payload'), 'Expected raw chapter mixed-citation payload text to stay blocked');
        $t->true(!str_contains($encodedPacket, 'Blocked duplicate citation payload'), 'Expected raw duplicate mixed-citation payload text to stay blocked');
        $t->true(!str_contains($encodedPacket, 'Blocked missing title source payload'), 'Expected raw missing title/source citation payload text to stay blocked');
    },
    'diagnoses jats and bits section id label language metadata review packets' => static function (TestRunner $t): void {
        $jats = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="review-article" xml:lang="en">
  <front><article-meta><title-group><article-title>Section Metadata Review</article-title></title-group></article-meta></front>
  <body>
    <sec id="dup" xml:lang="en-US"><label>1</label><title>Intro</title><p>Lead section.</p></sec>
    <sec id="dup" lang="fr"><label>1bis</label><title>Deux</title><title>Duplicate title</title><p>French section.</p></sec>
    <sec><p>Untitled imported section.</p></sec>
  </body>
</article>
XML, 'JATS section metadata XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeJatsFrontMatter($jats);
        $diagnostics = [];
        foreach ($packet['sectionMetadataDiagnostics'] as $diagnostic) {
            $diagnostics[$diagnostic['code']] = $diagnostic;
        }

        $t->same(false, $packet['directReaderParity']);
        $t->same([
            'direct-reader-unsupported',
            'body-sections-review-only',
            'section-metadata-diagnostics-review-only',
        ], $packet['directReaderDiagnosticCodes']);
        $t->same(3, $packet['directReaderDiagnosticCount']);
        $t->same(6, $packet['sectionMetadataDiagnosticCount']);
        $t->same([
            'section-id-missing',
            'section-id-duplicate',
            'section-labels-review-only',
            'section-languages-review-only',
            'section-title-missing',
            'section-title-duplicate',
        ], $packet['sectionMetadataDiagnosticCodes']);
        $t->same(3, $packet['sectionCount']);
        $t->same(['Intro', 'Deux'], $packet['sectionTitles']);
        $t->same(['dup'], $packet['sectionIds']);
        $t->same(['1', '1bis'], $packet['sectionLabels']);
        $t->same(['en-US', 'fr'], $packet['sectionLanguages']);
        $t->same('dup', $packet['sections'][0]['id'] ?? null);
        $t->same('1', $packet['sections'][0]['label'] ?? null);
        $t->same('en-US', $packet['sections'][0]['language'] ?? null);
        $t->same(1, $packet['sections'][0]['titleCount'] ?? null);
        $t->same(2, $packet['sections'][1]['titleCount'] ?? null);
        $t->same(['Deux', 'Duplicate title'], $packet['sections'][1]['titleTexts'] ?? null);
        $t->same(null, $packet['sections'][2]['id'] ?? null);
        $t->same(0, $packet['sections'][2]['titleCount'] ?? null);
        $t->same(1, $diagnostics['section-id-missing']['details']['missingSectionIdCount'] ?? null);
        $t->same(['section[3]'], $diagnostics['section-id-missing']['details']['sections'] ?? null);
        $t->same(['dup'], $diagnostics['section-id-duplicate']['details']['duplicateSectionIds'] ?? null);
        $t->same(['section[1]#dup', 'section[2]#dup'], $diagnostics['section-id-duplicate']['details']['duplicates'][0]['sections'] ?? null);
        $t->same(['1', '1bis'], $diagnostics['section-labels-review-only']['details']['labels'] ?? null);
        $t->same(['en-US', 'fr'], $diagnostics['section-languages-review-only']['details']['languages'] ?? null);
        $t->same(['section[3]'], $diagnostics['section-title-missing']['details']['sections'] ?? null);
        $t->same(1, $diagnostics['section-title-duplicate']['details']['duplicateSectionTitleCount'] ?? null);
        $t->same('section[2]#dup', $diagnostics['section-title-duplicate']['details']['sections'][0]['section'] ?? null);
        $t->same(2, $diagnostics['section-title-duplicate']['details']['sections'][0]['titleCount'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);

        $bits = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book book-type="handbook" xml:lang="es">
  <book-meta><title-group><book-title>Bits Section Review</book-title></title-group></book-meta>
  <book-body>
    <sec id="b1" xml:lang="es-MX"><label>Cap. 1</label><title>Inicio</title><p>Review body.</p></sec>
  </book-body>
</book>
XML, 'BITS section metadata XML', preserveWhiteSpace: false);
        $bitsPacket = XmlHtmlDom::summarizeJatsFrontMatter($bits, 'bits');

        $t->same('bits', $bitsPacket['format']);
        $t->same(false, $bitsPacket['directReaderParity']);
        $t->same(['b1'], $bitsPacket['sectionIds']);
        $t->same(['Cap. 1'], $bitsPacket['sectionLabels']);
        $t->same(['es-MX'], $bitsPacket['sectionLanguages']);
        $t->same([
            'section-labels-review-only',
            'section-languages-review-only',
        ], $bitsPacket['sectionMetadataDiagnosticCodes']);
        $t->same([
            'direct-reader-unsupported',
            'body-sections-review-only',
            'section-metadata-diagnostics-review-only',
        ], $bitsPacket['directReaderDiagnosticCodes']);
        json_encode($bitsPacket, JSON_THROW_ON_ERROR);
    },
    'summarizes docbook section title and xref target diagnostics without reader parity claims' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(
            (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-docbook-sections.xml'),
            'DocBook section metadata XML',
            preserveWhiteSpace: false
        );
        $packet = XmlHtmlDom::summarizeDocBookSectionMetadata($dom);

        $t->same('xml-html5-jats-dom', $packet['formatFamily']);
        $t->same('docbook', $packet['format']);
        $t->same('docbook-section-title-xref-metadata-review-only', $packet['reviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same('article', $packet['rootName']);
        $t->same('5.0', $packet['documentVersion']);
        $t->same('en', $packet['language']);
        $t->same('en', $packet['rootAttributes']['xml:lang'] ?? null);
        $t->same('WordPress DocBook Review Packet', $packet['title']);
        $t->same('info-title', $packet['titleSource']);
        $t->same('Section title metadata', $packet['subtitle']);
        $t->same(5, $packet['sectionCount']);
        $t->same(['overview', 'queue', 'untitled', 'legacy-section', 'queue'], $packet['sectionIds']);
        $t->same([
            'Import Overview',
            'Review Queue',
            'Legacy Section',
            'Duplicate Queue Identifier',
        ], $packet['sectionTitles']);

        $overview = $packet['sections'][0] ?? [];
        $queue = $packet['sections'][1] ?? [];
        $untitled = $packet['sections'][2] ?? [];
        $legacy = $packet['sections'][3] ?? [];
        $duplicate = $packet['sections'][4] ?? [];

        $t->same('section', $overview['element'] ?? null);
        $t->same('overview', $overview['id'] ?? null);
        $t->same('overview', $overview['xmlId'] ?? null);
        $t->same('summary', $overview['role'] ?? null);
        $t->same('1', $overview['label'] ?? null);
        $t->same('en-US', $overview['language'] ?? null);
        $t->same(1, $overview['level'] ?? null);
        $t->same(2, $overview['paragraphCount'] ?? null);
        $t->same(2, $overview['childSectionCount'] ?? null);

        $t->same('queue', $queue['id'] ?? null);
        $t->same('Review Queue', $queue['title'] ?? null);
        $t->same('info-title', $queue['titleSource'] ?? null);
        $t->same('Metadata from info', $queue['subtitle'] ?? null);
        $t->same(2, $queue['level'] ?? null);

        $t->same('simplesect', $untitled['element'] ?? null);
        $t->same('untitled', $untitled['id'] ?? null);
        $t->same(null, $untitled['title'] ?? null);
        $t->same(null, $untitled['titleSource'] ?? null);
        $t->same(2, $untitled['level'] ?? null);

        $t->same('sect1', $legacy['element'] ?? null);
        $t->same('legacy-section', $legacy['id'] ?? null);
        $t->same('legacy', $legacy['role'] ?? null);
        $t->same(1, $legacy['level'] ?? null);
        $t->same('queue', $duplicate['id'] ?? null);

        $targets = $packet['xrefLinkTargets'];
        $t->same(4, $packet['xrefLinkTargetCount']);
        $t->same(['resolved-section', 'duplicate-section-id', 'missing-anchor', 'unsafe-target'], $packet['xrefLinkTargetStatuses']);
        $t->same([
            'resolved-section' => 1,
            'duplicate-section-id' => 1,
            'missing-anchor' => 1,
            'unsafe-target' => 1,
        ], $packet['xrefLinkTargetStatusCounts']);
        $t->same(1, $packet['xrefLinkResolvedCount']);
        $t->same(1, $packet['xrefLinkDuplicateCount']);
        $t->same(1, $packet['xrefLinkMissingCount']);
        $t->same(1, $packet['xrefLinkUnsafeCount']);

        $t->same('xref', $targets[0]['element'] ?? null);
        $t->same('linkend', $targets[0]['attribute'] ?? null);
        $t->same('legacy-section', $targets[0]['target'] ?? null);
        $t->same('resolved-section', $targets[0]['status'] ?? null);
        $t->same([3], $targets[0]['targetSectionIndexes'] ?? null);
        $t->same(['Legacy Section'], $targets[0]['targetSectionTitles'] ?? null);
        $t->same([], $targets[0]['diagnostics'] ?? null);

        $t->same('link', $targets[1]['element'] ?? null);
        $t->same('linkend', $targets[1]['attribute'] ?? null);
        $t->same('queue', $targets[1]['target'] ?? null);
        $t->same('duplicate-section-id', $targets[1]['status'] ?? null);
        $t->same([1, 4], $targets[1]['targetSectionIndexes'] ?? null);
        $t->same(['Review Queue', 'Duplicate Queue Identifier'], $targets[1]['targetSectionTitles'] ?? null);
        $t->same(['docbook-xref-target-duplicate-section-id'], $targets[1]['diagnostics'] ?? null);

        $t->same('xlink:href', $targets[2]['attribute'] ?? null);
        $t->same('#missing-anchor', $targets[2]['rawTarget'] ?? null);
        $t->same('missing-anchor', $targets[2]['target'] ?? null);
        $t->same('missing-anchor', $targets[2]['status'] ?? null);
        $t->same(['docbook-xref-target-missing-anchor'], $targets[2]['diagnostics'] ?? null);

        $t->same('xlink:href', $targets[3]['attribute'] ?? null);
        $t->same('javascript:alert(1)', $targets[3]['rawTarget'] ?? null);
        $t->same(null, $targets[3]['target'] ?? null);
        $t->same('unsafe-target', $targets[3]['status'] ?? null);
        $t->same('non-local-href', $targets[3]['unsafeReason'] ?? null);
        $t->same(['docbook-xref-target-unsafe'], $targets[3]['diagnostics'] ?? null);

        $t->same([
            'docbook-section-missing-title',
            'docbook-section-duplicate-id',
            'docbook-xref-target-duplicate-section-id',
            'docbook-xref-target-missing-anchor',
            'docbook-xref-target-unsafe',
        ], $packet['diagnosticCodes']);
        $t->same(1, $packet['missingTitleCount']);
        $t->same(1, $packet['duplicateIdCount']);
        $t->same('untitled', $packet['diagnostics'][0]['sectionId'] ?? null);
        $t->same('queue', $packet['diagnostics'][1]['sectionId'] ?? null);
        $t->same(1, $packet['diagnostics'][1]['firstSectionIndex'] ?? null);
        $t->same('docbook-xref-target-duplicate-section-id', $packet['diagnostics'][2]['code'] ?? null);
        $t->same([1, 4], $packet['diagnostics'][2]['targetSectionIndexes'] ?? null);
        $t->same('docbook-xref-target-missing-anchor', $packet['diagnostics'][3]['code'] ?? null);
        $t->same('docbook-xref-target-unsafe', $packet['diagnostics'][4]['code'] ?? null);
        $t->same('non-local-href', $packet['diagnostics'][4]['unsafeReason'] ?? null);
        $t->throws(InvalidArgumentException::class, static fn (): array => XmlHtmlDom::summarizeDocBookSectionMetadata(
            XmlHtmlDom::loadXmlDocument('<topic><title>Not DocBook</title></topic>', 'non DocBook XML')
        ));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes docbook media caption cross-reference review packets' => static function (TestRunner $t): void {
        $docbook = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article xmlns="http://docbook.org/ns/docbook" version="5.2" xml:lang="en">
  <info><title>Caption Crosslinks</title></info>
  <section xml:id="media-review" role="media-review">
    <title>Media Review</title>
    <para>See <xref linkend="fig-hero"/> and <link linkend="cap-hero">caption text</link> and <xref linkend="missing-caption"/>.</para>
    <figure xml:id="fig-hero" role="screenshot">
      <title>Hero Figure</title>
      <mediaobject xml:id="media-hero" role="screenshot">
        <imageobject><imagedata fileref="images/hero.png"/></imageobject>
        <caption xml:id="cap-hero"><para>Hero screenshot import</para></caption>
      </mediaobject>
    </figure>
    <figure xml:id="fig-hero-repeat" role="screenshot">
      <title>Hero Figure Duplicate</title>
      <mediaobject xml:id="media-hero-repeat" role="screenshot">
        <imageobject><imagedata fileref="images/hero-repeat.png"/></imageobject>
        <caption><para>Hero screenshot import</para></caption>
      </mediaobject>
    </figure>
    <mediaobject xml:id="media-loose" role="poster">
      <imageobject><imagedata fileref="images/poster.png"/></imageobject>
    </mediaobject>
    <para>Captionless <xref linkend="media-loose"/>.</para>
  </section>
  <bibliography>
    <biblioentry xml:id="ref-hero">
      <title>Hero Reference</title>
      <para>Bibliography <link linkend="fig-hero">figure</link> <xref linkend="media-hero"/></para>
    </biblioentry>
  </bibliography>
</article>
XML, 'DocBook media caption cross-reference XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeDocBookStructure($docbook, 'docbook5');

        $t->same(false, $packet['directReaderParity']);
        $t->same('docbook-structure-review-only', $packet['reviewPolicy']);
        $t->same(['fig-hero', 'cap-hero', 'missing-caption', 'media-loose', 'media-hero'], $packet['xrefTargets']);
        $t->same(6, $packet['xrefCount']);
        $t->same(6, $packet['captionCrossReferenceCount']);
        $t->same('fig-hero', $packet['xrefs'][0]['target'] ?? null);
        $t->same('figure', $packet['xrefs'][0]['targetKind'] ?? null);
        $t->same('Hero Figure', $packet['xrefs'][0]['targetTitle'] ?? null);
        $t->same('Hero screenshot import', $packet['xrefs'][0]['targetCaptionText'] ?? null);
        $t->same(['images/hero.png'], $packet['xrefs'][0]['targetImageDataRefs'] ?? null);
        $t->same('cap-hero', $packet['xrefs'][1]['target'] ?? null);
        $t->same('caption', $packet['xrefs'][1]['targetKind'] ?? null);
        $t->same('Hero screenshot import', $packet['xrefs'][1]['targetCaptionText'] ?? null);
        $t->same(false, $packet['xrefs'][2]['resolved'] ?? null);
        $t->same(['missing-caption'], $packet['xrefs'][2]['missingTargets'] ?? null);
        $t->same('media-loose', $packet['xrefs'][3]['target'] ?? null);
        $t->same('media', $packet['xrefs'][3]['targetKind'] ?? null);
        $t->same(null, $packet['xrefs'][3]['targetCaptionText'] ?? null);
        $t->same(['missing-caption'], $packet['missingCaptionTargets']);
        $t->same(1, $packet['missingCaptionTargetCount']);
        $t->same([
            'missing-caption-target',
            'captionless-media-target',
            'repeated-role-caption-pair',
        ], $packet['captionDiagnosticCodes']);
        $t->same(3, $packet['captionDiagnosticCount']);
        $t->same('missing-caption', $packet['captionDiagnostics'][0]['details']['target'] ?? null);
        $t->same('media-loose', $packet['captionDiagnostics'][1]['details']['target'] ?? null);
        $t->same(1, $packet['repeatedRoleCaptionPairCount']);
        $t->same('screenshot', $packet['repeatedRoleCaptionPairs'][0]['role'] ?? null);
        $t->same('Hero screenshot import', $packet['repeatedRoleCaptionPairs'][0]['captionText'] ?? null);
        $t->same(['media-hero', 'media-hero-repeat'], $packet['repeatedRoleCaptionPairs'][0]['targetIds'] ?? null);
        $t->same(['images/hero.png', 'images/hero-repeat.png'], $packet['repeatedRoleCaptionPairs'][0]['imageDataRefs'] ?? null);
        $t->same(2, $packet['bibliographyMediaCaptionCrosslinkCount']);
        $t->same('ref-hero', $packet['bibliographyMediaCaptionCrosslinks'][0]['sourceBibliographyId'] ?? null);
        $t->same('fig-hero', $packet['bibliographyMediaCaptionCrosslinks'][0]['target'] ?? null);
        $t->same('media-hero', $packet['bibliographyMediaCaptionCrosslinks'][1]['target'] ?? null);
        $t->same(5, $packet['mediaTargetManifestCount']);
        $t->same('fig-hero', $packet['mediaTargetManifest'][0]['id'] ?? null);
        $t->same(2, $packet['mediaTargetManifest'][0]['referenceCount'] ?? null);
        $t->same(['images/hero.png'], $packet['mediaTargetManifest'][0]['imageDataRefs'] ?? null);
        $t->same('media-hero', $packet['mediaTargetManifest'][1]['id'] ?? null);
        $t->same(1, $packet['mediaTargetManifest'][1]['referenceCount'] ?? null);
        $t->same('media-loose', $packet['mediaTargetManifest'][4]['id'] ?? null);
        $t->same(['images/poster.png'], $packet['mediaTargetManifest'][4]['imageDataRefs'] ?? null);
        $t->same(['fig-hero', 'media-hero', 'fig-hero-repeat', 'media-hero-repeat', 'media-loose', 'ref-hero'], $packet['captionTargetIds']);
        $t->same(1, $packet['bibliographyCount']);
        $t->same(1, $packet['bibliographyEntryCount']);
        $t->same(3, $packet['mediaObjectCount']);
        $t->same(3, $packet['imageObjectCount']);
        $t->same(['images/hero.png', 'images/hero-repeat.png', 'images/poster.png'], $packet['imageDataRefs']);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes docbook bibliography media crosslink diagnostics' => static function (TestRunner $t): void {
        $docbook = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article xmlns="http://docbook.org/ns/docbook" version="5.2" xml:lang="en">
  <info><title>Bibliography Media Crosslinks</title></info>
  <section xml:id="media-targets">
    <title>Media Targets</title>
    <figure xml:id="fig-photo">
      <title>Plate A</title>
      <mediaobject><imageobject><imagedata fileref="media/plate-a.png"/></imageobject></mediaobject>
    </figure>
    <mediaobject xml:id="dup-media"><imageobject><imagedata fileref="media/dup-a.png"/></imageobject></mediaobject>
    <mediaobject xml:id="dup-media"><imageobject><imagedata fileref="media/dup-b.png"/></imageobject></mediaobject>
  </section>
  <bibliography xml:id="refs">
    <biblioentry xml:id="ref-media">
      <author><personname><firstname>Mira</firstname><surname>Lens</surname></personname></author>
      <title>Media Study</title>
      <pubdate>2025</pubdate>
      <para>See <xref linkend="fig-photo missing-media dup-media fig-photo"/>.</para>
    </biblioentry>
    <bibliomixed xml:id="ref-inline">
      <author><personname><firstname>Ira</firstname><surname>Inline</surname></personname></author>
      <citetitle>Inline Media Appendix</citetitle>
      <year>2024</year>
      <mediaobject xml:id="bib-media"><imageobject><imagedata fileref="media/bib-inline.png"/></imageobject></mediaobject>
    </bibliomixed>
  </bibliography>
</article>
XML, 'DocBook bibliography media crosslink XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeDocBookStructure($docbook, 'docbook5');

        $t->same(false, $packet['directReaderParity']);
        $t->same(2, $packet['bibliographyEntryCount']);
        $t->same('ref-media', $packet['bibliographyEntries'][0]['id'] ?? null);
        $t->same('Media Study', $packet['bibliographyEntries'][0]['title'] ?? null);
        $t->same(['Mira Lens'], $packet['bibliographyEntries'][0]['contributorNames'] ?? null);
        $t->same(['2025'], $packet['bibliographyEntries'][0]['yearLikeValues'] ?? null);
        $t->same('ref-inline', $packet['bibliographyEntries'][1]['id'] ?? null);
        $t->same('Inline Media Appendix', $packet['bibliographyEntries'][1]['title'] ?? null);
        $t->same(['Ira Inline'], $packet['bibliographyEntries'][1]['contributorNames'] ?? null);
        $t->same(['2024'], $packet['bibliographyEntries'][1]['yearLikeValues'] ?? null);

        $t->same(1, $packet['bibliographyMediaObjectCount']);
        $t->same('mediaobject', $packet['bibliographyMediaObjects'][0]['element'] ?? null);
        $t->same('bib-media', $packet['bibliographyMediaObjects'][0]['id'] ?? null);
        $t->same('bibliomixed', $packet['bibliographyMediaObjects'][0]['bibliographyBlockElement'] ?? null);
        $t->same('ref-inline', $packet['bibliographyMediaObjects'][0]['entryId'] ?? null);
        $t->same('Inline Media Appendix', $packet['bibliographyMediaObjects'][0]['entryTitle'] ?? null);
        $t->same(['Ira Inline'], $packet['bibliographyMediaObjects'][0]['entryContributorNames'] ?? null);
        $t->same(['2024'], $packet['bibliographyMediaObjects'][0]['entryYearLikeValues'] ?? null);
        $t->same(['media/bib-inline.png'], $packet['bibliographyMediaObjects'][0]['imageDataRefs'] ?? null);

        $crosslinks = $packet['bibliographyMediaCrosslinks'];
        $t->same(2, $crosslinks['entryCount']);
        $t->same(['ref-media'], $crosslinks['entriesWithMediaLinks']);
        $t->same(1, $crosslinks['resolvedCount']);
        $t->same(1, $crosslinks['missingCount']);
        $t->same(2, $crosslinks['duplicateCount']);
        $t->same([
            'missing-bibliography-media-target',
            'duplicate-bibliography-media-crosslink',
            'duplicate-bibliography-media-target-id',
        ], $packet['bibliographyMediaCrosslinkDiagnosticCodes']);

        $t->same('ref-media', $crosslinks['resolved'][0]['entryId'] ?? null);
        $t->same('Media Study', $crosslinks['resolved'][0]['entryTitle'] ?? null);
        $t->same('2025', $crosslinks['resolved'][0]['entryYear'] ?? null);
        $t->same(['Mira Lens'], $crosslinks['resolved'][0]['entryContributorNames'] ?? null);
        $t->same('fig-photo', $crosslinks['resolved'][0]['targetId'] ?? null);
        $t->same('figure', $crosslinks['resolved'][0]['targetElement'] ?? null);
        $t->same('Plate A', $crosslinks['resolved'][0]['targetTitle'] ?? null);
        $t->same(['media/plate-a.png'], $crosslinks['resolved'][0]['targetImageDataRefs'] ?? null);
        $t->same(['media/plate-a.png'], $crosslinks['resolved'][0]['mediaTargetManifestRefs'] ?? null);

        $t->same('missing-bibliography-media-target', $crosslinks['missing'][0]['code'] ?? null);
        $t->same('missing-media', $crosslinks['missing'][0]['targetId'] ?? null);
        $t->same('ref-media', $crosslinks['missing'][0]['entryId'] ?? null);
        $t->same('duplicate-bibliography-media-crosslink', $crosslinks['duplicates'][0]['code'] ?? null);
        $t->same('fig-photo', $crosslinks['duplicates'][0]['targetId'] ?? null);
        $t->same(2, $crosslinks['duplicates'][0]['occurrences'] ?? null);
        $t->same(['media/plate-a.png'], $crosslinks['duplicates'][0]['mediaTargetManifestRefs'] ?? null);
        $t->same('duplicate-bibliography-media-target-id', $crosslinks['duplicates'][1]['code'] ?? null);
        $t->same('dup-media', $crosslinks['duplicates'][1]['targetId'] ?? null);
        $t->same(2, $crosslinks['duplicates'][1]['targetCount'] ?? null);
        $t->same(['mediaobject', 'mediaobject'], $crosslinks['duplicates'][1]['targetElements'] ?? null);
        $t->same(['media/dup-a.png', 'media/dup-b.png'], $crosslinks['duplicates'][1]['mediaTargetManifestRefs'] ?? null);
        $t->true(in_array('bib-media', array_column($packet['mediaTargetManifest'], 'id'), true));
        $t->true(in_array('media/bib-inline.png', $packet['imageDataRefs'], true));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'recovers HTML5 fragments with list autoclose and void elements' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p data-id="42">Intro<br>Next<img src="cover.png?x=1&amp;y=2" alt="Cover"></p><ul><li>One<li>Two</ul>',
            'review HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(2, count($summary));
        $t->same('p', $summary[0]['name']);
        $t->same(['data-id' => '42'], $summary[0]['attributes']);
        $t->same('br', $summary[0]['children'][1]['name']);
        $t->same('img', $summary[0]['children'][3]['name']);
        $t->same(['alt' => 'Cover', 'src' => 'cover.png?x=1&y=2'], $summary[0]['children'][3]['attributes']);
        $t->same('ul', $summary[1]['name']);
        $t->same('li', $summary[1]['children'][0]['name']);
        $t->same('One', $summary[1]['children'][0]['text']);
        $t->same('Two', $summary[1]['children'][1]['text']);
        $t->same('<p data-id="42">Intro<br>Next<img alt="Cover" src="cover.png?x=1&amp;y=2"></p><ul><li>One</li><li>Two</li></ul>', $html);
    },
    'summarizes html fragment tree packet for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            'Lead <section id="root" class="alpha beta alpha" data-package-part="word/document.xml" aria-label="Review">'
                . '<p>Alpha<br>Beta<img src="cover.png" alt="Cover"></p>'
                . '<!--nested--><script type="application/json">{"ok":true}</script>'
                . '</section><!--tail--><hr>',
            'fragment tree packet review'
        );
        $packet = XmlHtmlDom::summarizeHtmlFragmentReviewPacket($dom);

        $t->same('xml-html5-dom', $packet['formatFamily']);
        $t->same('html', $packet['format']);
        $t->same('html-fragment-tree-summary-review', $packet['fragmentTreeReviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same(['html-fragment-tree-summary-review-only'], $packet['directReaderDiagnosticCodes']);
        $t->same(4, $packet['topLevelNodeCount']);
        $t->same(12, $packet['nodeCount']);
        $t->same(6, $packet['elementCount']);
        $t->same(4, $packet['textNodeCount']);
        $t->same(2, $packet['commentCount']);
        $t->same(3, $packet['maxDepth']);
        $t->same(['section', 'hr'], $packet['topLevelElementNames']);
        $t->same(['section', 'p', 'br', 'img', 'script', 'hr'], $packet['elementNames']);
        $t->same([
            'section' => 1,
            'p' => 1,
            'br' => 1,
            'img' => 1,
            'script' => 1,
            'hr' => 1,
        ], $packet['elementNameCounts']);
        $t->same(3, $packet['voidElementCount']);
        $t->same(['br', 'img', 'hr'], $packet['voidElementNames']);
        $t->same(1, $packet['rawTextElementCount']);
        $t->same(['script'], $packet['rawTextElementNames']);
        $t->same(1, $packet['activeContentElementCount']);
        $t->same(['script'], $packet['activeContentElementNames']);
        $t->same(1, $packet['elementIdCount']);
        $t->same(['root'], $packet['elementIds']);
        $t->same([], $packet['duplicateElementIds']);
        $t->same(3, $packet['classTokenCount']);
        $t->same(['alpha', 'beta'], $packet['classNames']);
        $t->same(1, $packet['dataAttributeCount']);
        $t->same(['data-package-part'], $packet['dataAttributeNames']);
        $t->same(1, $packet['ariaAttributeCount']);
        $t->same(['aria-label'], $packet['ariaAttributeNames']);
        $t->same('text', $packet['nodes'][0]['type']);
        $t->same('Lead ', $packet['nodes'][0]['text']);
        $t->same('section', $packet['nodes'][1]['name']);
        $t->same('script', $packet['nodes'][1]['children'][2]['name']);
        $t->same('hr', $packet['nodes'][3]['name']);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes html break and separator elements for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>Alpha<br id="hard">Beta<wbr data-source="wrap">Gamma</p><hr id="rule" class="review-separator">',
            'break element review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/break-elements-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $hardBreak = $paragraph['children'][1];
        $wordBreak = $paragraph['children'][3];
        $rule = $summary[1];

        $t->same('p', $paragraph['name']);
        $t->same('AlphaBetaGamma', $paragraph['text']);
        $t->same('br', $hardBreak['name']);
        $t->same('line-break', $hardBreak['breakElement']);
        $t->same('br', $hardBreak['breakTag']);
        $t->same("\n", $hardBreak['textEquivalent']);
        $t->same(true, $hardBreak['hardBreak']);
        $t->same('hard', $hardBreak['elementId']);
        $t->same('wbr', $wordBreak['name']);
        $t->same('word-break-opportunity', $wordBreak['breakElement']);
        $t->same('', $wordBreak['textEquivalent']);
        $t->same(true, $wordBreak['softBreakOpportunity']);
        $t->same(['source' => 'wrap'], $wordBreak['dataset']);
        $t->same('hr', $rule['name']);
        $t->same('thematic-break', $rule['breakElement']);
        $t->same(true, $rule['blockSeparator']);
        $t->same(['review-separator'], $rule['classList']);
        $t->same('<p>Alpha<br id="hard">Beta<wbr data-source="wrap">Gamma</p><hr class="review-separator" id="rule">', $html);
        $t->contains('<wbr data-source="wrap">', $blocks);
        $t->contains('<hr class="review-separator" id="rule">', $blocks);
        $t->same('/migration/break-elements-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html global attributes and dataset state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="packet" class="alpha  beta alpha" lang="en-US" dir="RTL" title="Review &amp; Source" data-review-id="A-42" data-package-part="word/document.xml" hidden="until-found" translate="no" contenteditable="plaintext-only" draggable="true" spellcheck="false" tabindex="-1" role="doc-chapter region" aria-label="Packet Section"><p class="child">Body</p></section>'
                . '<p data-review-stage="preflight" dir="sideways" translate="maybe" contenteditable="maybe" draggable="maybe" spellcheck="maybe">Fallback</p>'
                . '<table id="review-table" class="data-grid" data-package-part="word/tables.xml"><tr><td>Cell</td></tr></table>',
            'global attribute review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $section = $summary[0];
        $fallback = $summary[1];
        $table = $summary[2];

        $t->same('packet', $section['elementId']);
        $t->same('alpha  beta alpha', $section['classRaw']);
        $t->same(['alpha', 'beta', 'alpha'], $section['classList']);
        $t->same('en-US', $section['languageRaw']);
        $t->same('en-US', $section['language']);
        $t->same('RTL', $section['dirRaw']);
        $t->same('rtl', $section['direction']);
        $t->same('Review & Source', $section['titleAttribute']);
        $t->same('until-found', $section['hiddenRaw']);
        $t->same('until-found', $section['hiddenState']);
        $t->same('no', $section['translateRaw']);
        $t->same(false, $section['translate']);
        $t->same('plaintext-only', $section['contentEditable']);
        $t->same(true, $section['draggable']);
        $t->same(false, $section['spellcheck']);
        $t->same('-1', $section['tabIndexRaw']);
        $t->same(-1, $section['tabIndex']);
        $t->same('doc-chapter region', $section['roleRaw']);
        $t->same(['doc-chapter', 'region'], $section['roles']);
        $t->same(['aria-label' => 'Packet Section'], $section['ariaAttributes']);
        $t->same([
            'data-package-part' => 'word/document.xml',
            'data-review-id' => 'A-42',
        ], $section['dataAttributes']);
        $t->same([
            'packagePart' => 'word/document.xml',
            'reviewId' => 'A-42',
        ], $section['dataset']);
        $t->same('child', $section['children'][0]['classRaw']);
        $t->same(['child'], $section['children'][0]['classList']);

        $t->same('sideways', $fallback['dirRaw']);
        $t->same(null, $fallback['direction']);
        $t->same('maybe', $fallback['translateRaw']);
        $t->same(null, $fallback['translate']);
        $t->same(null, $fallback['contentEditable']);
        $t->same(null, $fallback['draggable']);
        $t->same(null, $fallback['spellcheck']);
        $t->same(['reviewStage' => 'preflight'], $fallback['dataset']);

        $t->same('review-table', $table['elementId']);
        $t->same(['data-grid'], $table['classList']);
        $t->same(['packagePart' => 'word/tables.xml'], $table['dataset']);
        $t->same('table', $table['tablePart']);
        $t->same(
            '<section aria-label="Packet Section" class="alpha  beta alpha" contenteditable="plaintext-only" data-package-part="word/document.xml" data-review-id="A-42" dir="RTL" draggable="true" hidden="until-found" id="packet" lang="en-US" role="doc-chapter region" spellcheck="false" tabindex="-1" title="Review &amp; Source" translate="no"><p class="child">Body</p></section>'
                . '<p contenteditable="maybe" data-review-stage="preflight" dir="sideways" draggable="maybe" spellcheck="maybe" translate="maybe">Fallback</p>'
                . '<table class="data-grid" data-package-part="word/tables.xml" id="review-table"><tr><td>Cell</td></tr></table>',
            $html
        );
    },
    'summarizes html class token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="packet" class=" alpha beta alpha gamma beta ">Review</section>'
                . '<p id="empty" class="   ">Empty</p>'
                . '<span id="single" class="note">Note</span>',
            'class token provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/class-token-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $repeated = $summary[0];
        $empty = $summary[1];
        $single = $summary[2];

        $t->same('html-class-token-review', $repeated['classReviewPolicy']);
        $t->same(' alpha beta alpha gamma beta ', $repeated['classRaw']);
        $t->same(['alpha', 'beta', 'alpha', 'gamma', 'beta'], $repeated['classList']);
        $t->same(5, $repeated['classTokenCount']);
        $t->same(['alpha', 'beta', 'gamma'], $repeated['classUniqueTokens']);
        $t->same(3, $repeated['classUniqueTokenCount']);
        $t->same(['alpha' => 2, 'beta' => 2, 'gamma' => 1], $repeated['classTokenCounts']);
        $t->same(['alpha', 'beta'], $repeated['duplicateClassTokens']);
        $t->same(true, $repeated['classHasDuplicateTokens']);
        $t->same(false, $repeated['classEmpty']);
        $t->same(['duplicate-html-class-token'], $repeated['classIssueCodes']);

        $t->same('   ', $empty['classRaw']);
        $t->same([], $empty['classList']);
        $t->same(0, $empty['classTokenCount']);
        $t->same([], $empty['classUniqueTokens']);
        $t->same(0, $empty['classUniqueTokenCount']);
        $t->same([], $empty['duplicateClassTokens']);
        $t->same(false, $empty['classHasDuplicateTokens']);
        $t->same(true, $empty['classEmpty']);
        $t->same(['empty-html-class-attribute'], $empty['classIssueCodes']);

        $t->same(['note'], $single['classList']);
        $t->same(['note' => 1], $single['classTokenCounts']);
        $t->same([], $single['duplicateClassTokens']);
        $t->same([], $single['classIssueCodes']);

        $t->same(
            '<section class=" alpha beta alpha gamma beta " id="packet">Review</section>'
                . '<p class="   " id="empty">Empty</p>'
                . '<span class="note" id="single">Note</span>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/class-token-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html direction token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="ltr" dir="LTR">Left</section>'
                . '<section id="auto" dir=" auto ">Auto</section>'
                . '<section id="bad" dir="sideways"><span id="bad-child">Bad child</span></section>'
                . '<section id="empty" dir="">Empty</section>',
            'direction token provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/direction-token-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $ltr = $summary[0];
        $auto = $summary[1];
        $bad = $summary[2];
        $badChild = $bad['children'][0];
        $empty = $summary[3];

        $t->same('html-dir-token-review', $ltr['directionTokenReviewPolicy']);
        $t->same('LTR', $ltr['dirRaw']);
        $t->same('ltr', $ltr['direction']);
        $t->same('ltr', $ltr['directionKeyword']);
        $t->same(true, $ltr['directionValid']);
        $t->same(false, $ltr['directionAuto']);
        $t->same(false, $ltr['directionInvalidValueIgnored']);
        $t->same('LTR', $ltr['effectiveDirectionRaw']);
        $t->same('ltr', $ltr['effectiveDirection']);
        $t->same(false, $ltr['directionInherited']);

        $t->same(' auto ', $auto['dirRaw']);
        $t->same('auto', $auto['direction']);
        $t->same('auto', $auto['directionKeyword']);
        $t->same(true, $auto['directionValid']);
        $t->same(true, $auto['directionAuto']);
        $t->same(false, $auto['directionInvalidValueIgnored']);
        $t->same(' auto ', $auto['effectiveDirectionRaw']);
        $t->same('auto', $auto['effectiveDirection']);
        $t->same(false, $auto['directionInherited']);

        $t->same('sideways', $bad['dirRaw']);
        $t->same(null, $bad['direction']);
        $t->same(null, $bad['directionKeyword']);
        $t->same(false, $bad['directionValid']);
        $t->same(false, $bad['directionAuto']);
        $t->same(true, $bad['directionInvalidValueIgnored']);
        $t->true(!array_key_exists('effectiveDirection', $bad));
        $t->true(!array_key_exists('effectiveDirection', $badChild));

        $t->same('', $empty['dirRaw']);
        $t->same(null, $empty['direction']);
        $t->same(false, $empty['directionValid']);
        $t->same(true, $empty['directionInvalidValueIgnored']);
        $t->true(!array_key_exists('effectiveDirection', $empty));

        $t->same(
            '<section dir="LTR" id="ltr">Left</section>'
                . '<section dir=" auto " id="auto">Auto</section>'
                . '<section dir="sideways" id="bad"><span id="bad-child">Bad child</span></section>'
                . '<section dir="" id="empty">Empty</section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/direction-token-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html title advisory inheritance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="doc" title="Package note"><section id="child"><p id="leaf">Leaf</p><p id="empty" title=""><span id="reset-child">Reset</span></p><p id="self" title="Local note">Local</p></section></article>'
                . '<aside id="plain"><span id="plain-child">Plain</span></aside>',
            'title advisory inheritance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/title-advisory-inheritance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $section = $article['children'][0];
        $leaf = $section['children'][0];
        $empty = $section['children'][1];
        $resetChild = $empty['children'][0];
        $self = $section['children'][2];
        $aside = $summary[1];
        $plainChild = $aside['children'][0];

        $t->same('Package note', $article['titleAttribute']);
        $t->same('Package note', $article['effectiveTitleRaw']);
        $t->same('Package note', $article['effectiveTitle']);
        $t->same(false, $article['titleInherited']);
        $t->same('self-title', $article['titleSource']);
        $t->same('article', $article['titleSourceElement']);
        $t->same('doc', $article['titleSourceElementId']);
        $t->same(true, $article['titleAdvisoryAvailable']);
        $t->same(false, $article['titleAdvisoryReset']);

        $t->true(!array_key_exists('titleAttribute', $section));
        $t->same('Package note', $section['effectiveTitle']);
        $t->same(true, $section['titleInherited']);
        $t->same('ancestor-title', $section['titleSource']);
        $t->same('article', $section['titleSourceElement']);
        $t->same('doc', $section['titleSourceElementId']);
        $t->same('Package note', $leaf['effectiveTitle']);
        $t->same(true, $leaf['titleInherited']);

        $t->same('', $empty['titleAttribute']);
        $t->same('', $empty['effectiveTitleRaw']);
        $t->same(null, $empty['effectiveTitle']);
        $t->same(false, $empty['titleInherited']);
        $t->same('self-title', $empty['titleSource']);
        $t->same(false, $empty['titleAdvisoryAvailable']);
        $t->same(true, $empty['titleAdvisoryReset']);

        $t->same('', $resetChild['effectiveTitleRaw']);
        $t->same(null, $resetChild['effectiveTitle']);
        $t->same(true, $resetChild['titleInherited']);
        $t->same('p', $resetChild['titleSourceElement']);
        $t->same('empty', $resetChild['titleSourceElementId']);
        $t->same(false, $resetChild['titleAdvisoryAvailable']);
        $t->same(true, $resetChild['titleAdvisoryReset']);

        $t->same('Local note', $self['titleAttribute']);
        $t->same('Local note', $self['effectiveTitle']);
        $t->same(false, $self['titleInherited']);
        $t->same(true, $self['titleAdvisoryAvailable']);

        $t->true(!array_key_exists('effectiveTitle', $aside));
        $t->true(!array_key_exists('effectiveTitle', $plainChild));
        $t->same(
            '<article id="doc" title="Package note"><section id="child"><p id="leaf">Leaf</p><p id="empty" title=""><span id="reset-child">Reset</span></p><p id="self" title="Local note">Local</p></section></article><aside id="plain"><span id="plain-child">Plain</span></aside>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/title-advisory-inheritance-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html nonce token metadata without raw token leakage for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script id="valid-script" type="module" src="app.js" nonce="n0Nc3Token-42_=="></script>'
                . '<style id="spaced-style" nonce=" token with space ">body { color: red; }</style>'
                . '<div id="empty-nonce" nonce>Empty nonce</div>'
                . '<section id="invalid-nonce" nonce="bad token!">Bad nonce</section>',
            'nonce token provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/nonce-token-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $validScript = $summary[0];
        $spacedStyle = $summary[1];
        $empty = $summary[2];
        $invalid = $summary[3];

        $t->same('html-nonce-token-review', $validScript['nonceReviewPolicy']);
        $t->same(true, $validScript['noncePresent']);
        $t->same(false, $validScript['nonceEmpty']);
        $t->same(16, $validScript['nonceLength']);
        $t->same(16, $validScript['nonceTrimmedLength']);
        $t->same('10573ca3c4d712298c6c17e9122065efea17a3ddd2ace404202448ef92ea2285', $validScript['nonceSha256']);
        $t->same(false, $validScript['nonceHasAsciiWhitespace']);
        $t->same(true, $validScript['nonceBase64Candidate']);
        $t->same(true, $validScript['nonceValid']);
        $t->same([], $validScript['nonceIssueCodes']);
        $t->true(!array_key_exists('nonceRaw', $validScript));

        $t->same('html-nonce-token-review', $spacedStyle['nonceReviewPolicy']);
        $t->same(false, $spacedStyle['nonceEmpty']);
        $t->same(18, $spacedStyle['nonceLength']);
        $t->same(16, $spacedStyle['nonceTrimmedLength']);
        $t->same('ccf0adf4e4a4e3097b285e391e09a92614e66d2d8c3aca177fef82d233558ada', $spacedStyle['nonceSha256']);
        $t->same(true, $spacedStyle['nonceHasAsciiWhitespace']);
        $t->same(false, $spacedStyle['nonceBase64Candidate']);
        $t->same(false, $spacedStyle['nonceValid']);
        $t->same(['html-nonce-ascii-whitespace', 'html-nonce-non-base64-candidate'], $spacedStyle['nonceIssueCodes']);
        $t->true(!array_key_exists('nonceRaw', $spacedStyle));

        $t->same('html-nonce-token-review', $empty['nonceReviewPolicy']);
        $t->same(true, $empty['nonceEmpty']);
        $t->same(0, $empty['nonceLength']);
        $t->same(0, $empty['nonceTrimmedLength']);
        $t->same('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $empty['nonceSha256']);
        $t->same(false, $empty['nonceHasAsciiWhitespace']);
        $t->same(false, $empty['nonceBase64Candidate']);
        $t->same(false, $empty['nonceValid']);
        $t->same(['empty-html-nonce'], $empty['nonceIssueCodes']);

        $t->same('html-nonce-token-review', $invalid['nonceReviewPolicy']);
        $t->same(false, $invalid['nonceEmpty']);
        $t->same(10, $invalid['nonceLength']);
        $t->same(10, $invalid['nonceTrimmedLength']);
        $t->same('567de50b1142dad0f2c59469e6fe5907f0ef587761127cc64c17d1bec72dfe22', $invalid['nonceSha256']);
        $t->same(true, $invalid['nonceHasAsciiWhitespace']);
        $t->same(false, $invalid['nonceBase64Candidate']);
        $t->same(false, $invalid['nonceValid']);
        $t->same(['html-nonce-ascii-whitespace', 'html-nonce-non-base64-candidate'], $invalid['nonceIssueCodes']);

        $t->same(
            '<script id="valid-script" nonce="n0Nc3Token-42_==" src="app.js" type="module"></script>'
                . '<style id="spaced-style" nonce=" token with space ">body { color: red; }</style>'
                . '<div id="empty-nonce" nonce="">Empty nonce</div>'
                . '<section id="invalid-nonce" nonce="bad token!">Bad nonce</section>',
            $html
        );
        $t->contains('nonce="n0Nc3Token-42_=="', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/nonce-token-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html style attribute declarations for reviewer handoff' => static function (TestRunner $t): void {
        $styleRaw = 'color: red; --review-tone: highlight; background-image: url(cover;v1.png); color: blue !important; broken; : missing-property; bad<prop: value; padding: ';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="styled" title="Styled packet" style="' . htmlspecialchars($styleRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Styled</section>'
                . '<p id="plain" style="  ">Plain</p>',
            'style attribute review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/style-attribute-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $styled = $summary[0];
        $plain = $summary[1];

        $t->same('styled', $styled['elementId']);
        $t->same($styleRaw, $styled['styleRaw']);
        $t->same('html-style-attribute-declaration-review', $styled['styleAttributeReviewPolicy']);
        $t->same(strlen($styleRaw), $styled['styleByteLength']);
        $t->same(hash('sha256', $styleRaw), $styled['styleSha256']);
        $t->same(4, $styled['styleDeclarationCount']);
        $t->same(4, $styled['invalidStyleDeclarationCount']);
        $t->same(['color', '--review-tone', 'background-image'], $styled['stylePropertyNames']);
        $t->same(['color' => 2, '--review-tone' => 1, 'background-image' => 1], $styled['stylePropertyCounts']);
        $t->same(['color'], $styled['duplicateStyleProperties']);
        $t->same(['--review-tone'], $styled['customStyleProperties']);
        $t->same(['color'], $styled['importantStyleProperties']);
        $t->same([
            'index' => 0,
            'raw' => 'color: red',
            'propertyRaw' => 'color',
            'property' => 'color',
            'value' => 'red',
            'important' => false,
        ], $styled['styleDeclarations'][0]);
        $t->same([
            'index' => 1,
            'raw' => '--review-tone: highlight',
            'propertyRaw' => '--review-tone',
            'property' => '--review-tone',
            'value' => 'highlight',
            'important' => false,
        ], $styled['styleDeclarations'][1]);
        $t->same('url(cover;v1.png)', $styled['styleDeclarations'][2]['value']);
        $t->same([
            'index' => 3,
            'raw' => 'color: blue !important',
            'propertyRaw' => 'color',
            'property' => 'color',
            'value' => 'blue',
            'important' => true,
        ], $styled['styleDeclarations'][3]);
        $t->same([
            'missing-style-declaration-colon',
            'missing-style-property',
            'invalid-style-property',
            'missing-style-value',
        ], $styled['styleDeclarationIssueCodes']);
        $t->same([
            ['index' => 4, 'raw' => 'broken', 'code' => 'missing-style-declaration-colon'],
            ['index' => 5, 'raw' => ': missing-property', 'code' => 'missing-style-property'],
            ['index' => 6, 'raw' => 'bad<prop: value', 'code' => 'invalid-style-property', 'propertyRaw' => 'bad<prop'],
            ['index' => 7, 'raw' => 'padding:', 'code' => 'missing-style-value', 'propertyRaw' => 'padding', 'property' => 'padding'],
        ], $styled['invalidStyleDeclarations']);

        $t->same('  ', $plain['styleRaw']);
        $t->same(0, $plain['styleDeclarationCount']);
        $t->same(0, $plain['invalidStyleDeclarationCount']);
        $t->same([], $plain['stylePropertyNames']);

        $t->same(
            '<section id="styled" style="' . htmlspecialchars($styleRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" title="Styled packet">Styled</section>'
                . '<p id="plain" style="  ">Plain</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/style-attribute-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html draggable enumerated auto state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p id="forced" title="Forced text" draggable="true">Forced</p>'
                . '<img id="auto-image" src="cover.png" alt="Cover" draggable="auto">'
                . '<a id="auto-link" href="chapter.html" draggable="">Chapter</a>'
                . '<object id="auto-object" data="diagram.svg" type="image/svg+xml" draggable="maybe">Fallback</object>'
                . '<section id="plain" draggable="auto">Plain</section>'
                . '<button id="off" draggable="false">Off</button>',
            'draggable enumerated state review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/draggable-state-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $forced = $summary[0];
        $image = $summary[1];
        $link = $summary[2];
        $object = $summary[3];
        $plain = $summary[4];
        $off = $summary[5];

        $t->same('forced', $forced['elementId']);
        $t->same('true', $forced['draggableRaw']);
        $t->same(true, $forced['draggable']);
        $t->same('true', $forced['draggableState']);
        $t->same(true, $forced['draggableValid']);
        $t->same(false, $forced['draggableInvalidValueDefaulted']);
        $t->same(true, $forced['effectiveDraggable']);
        $t->same('attribute-true', $forced['draggableSource']);
        $t->true(!array_key_exists('draggableAutoReason', $forced));

        $t->same('img', $image['name']);
        $t->same('auto', $image['draggableRaw']);
        $t->same('auto', $image['draggable']);
        $t->same('auto', $image['draggableState']);
        $t->same(true, $image['draggableValid']);
        $t->same(false, $image['draggableInvalidValueDefaulted']);
        $t->same(true, $image['effectiveDraggable']);
        $t->same('attribute-auto', $image['draggableSource']);
        $t->same('image-element', $image['draggableAutoReason']);

        $t->same('', $link['draggableRaw']);
        $t->same('auto', $link['draggableState']);
        $t->same(false, $link['draggableValid']);
        $t->same(true, $link['effectiveDraggable']);
        $t->same('hyperlink-element', $link['draggableAutoReason']);
        $t->same('chapter.html', $link['href']);
        $t->same('a', $link['hyperlink']);

        $t->same('object', $object['embeddedResource']);
        $t->same('maybe', $object['draggableRaw']);
        $t->same('auto', $object['draggableState']);
        $t->same(false, $object['draggableValid']);
        $t->same(true, $object['effectiveDraggable']);
        $t->same('declared-image-object', $object['draggableAutoReason']);
        $t->same('diagram.svg', $object['data']);
        $t->same('Fallback', $object['fallbackText']);

        $t->same('plain', $plain['elementId']);
        $t->same('auto', $plain['draggableRaw']);
        $t->same('auto', $plain['draggable']);
        $t->same('auto', $plain['draggableState']);
        $t->same(true, $plain['draggableValid']);
        $t->same(false, $plain['effectiveDraggable']);
        $t->same('element-default', $plain['draggableAutoReason']);

        $t->same('false', $off['draggableRaw']);
        $t->same(false, $off['draggable']);
        $t->same('false', $off['draggableState']);
        $t->same(true, $off['draggableValid']);
        $t->same(false, $off['draggableInvalidValueDefaulted']);
        $t->same(false, $off['effectiveDraggable']);
        $t->same('attribute-false', $off['draggableSource']);
        $t->true(!array_key_exists('draggableAutoReason', $off));

        $t->same('<p draggable="true" id="forced" title="Forced text">Forced</p><img alt="Cover" draggable="auto" id="auto-image" src="cover.png"><a draggable="" href="chapter.html" id="auto-link">Chapter</a><object data="diagram.svg" draggable="maybe" id="auto-object" type="image/svg+xml">Fallback</object><section draggable="auto" id="plain">Plain</section><button draggable="false" id="off">Off</button>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/draggable-state-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html draggable token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="drag" draggable="true">Drag</section>'
                . '<section id="blocked" draggable="false">Blocked</section>'
                . '<section id="auto" draggable="AUTO">Auto</section>'
                . '<section id="empty" draggable>Empty</section>'
                . '<section id="invalid" draggable="maybe">Invalid</section>',
            'draggable token provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/draggable-token-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $drag = $summary[0];
        $blocked = $summary[1];
        $auto = $summary[2];
        $empty = $summary[3];
        $invalid = $summary[4];

        $t->same('true', $drag['draggableRaw']);
        $t->same('true', $drag['draggableKeyword']);
        $t->same(true, $drag['draggable']);
        $t->same('true', $drag['draggableState']);
        $t->same(true, $drag['draggableValid']);
        $t->same(false, $drag['draggableInvalidValueDefaulted']);

        $t->same('false', $blocked['draggableRaw']);
        $t->same('false', $blocked['draggableKeyword']);
        $t->same(false, $blocked['draggable']);
        $t->same('false', $blocked['draggableState']);
        $t->same(true, $blocked['draggableValid']);

        $t->same('AUTO', $auto['draggableRaw']);
        $t->same('auto', $auto['draggableKeyword']);
        $t->same('auto', $auto['draggable']);
        $t->same('auto', $auto['draggableState']);
        $t->same(true, $auto['draggableValid']);
        $t->same(false, $auto['draggableInvalidValueDefaulted']);

        $t->same('', $empty['draggableRaw']);
        $t->same(null, $empty['draggableKeyword']);
        $t->same(null, $empty['draggable']);
        $t->same('auto', $empty['draggableState']);
        $t->same(false, $empty['draggableValid']);
        $t->same(true, $empty['draggableInvalidValueDefaulted']);

        $t->same('maybe', $invalid['draggableRaw']);
        $t->same(null, $invalid['draggableKeyword']);
        $t->same(null, $invalid['draggable']);
        $t->same('auto', $invalid['draggableState']);
        $t->same(false, $invalid['draggableValid']);
        $t->same(true, $invalid['draggableInvalidValueDefaulted']);

        $t->same(
            '<section draggable="true" id="drag">Drag</section>'
                . '<section draggable="false" id="blocked">Blocked</section>'
                . '<section draggable="AUTO" id="auto">Auto</section>'
                . '<section draggable="" id="empty">Empty</section>'
                . '<section draggable="maybe" id="invalid">Invalid</section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/draggable-token-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html language tag validity for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="primary" lang="EN-us">Hello</section>'
                . '<p id="xml" xml:lang="fr-Latn-ca">Bonjour</p>'
                . '<aside id="blank" lang="  ">Blank</aside>'
                . '<div id="bad" lang="en US">Bad</div>'
                . '<article id="mixed" lang="en" xml:lang="fr">Mixed</article>',
            'language tag review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/language-tag-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $primary = $summary[0];
        $xml = $summary[1];
        $blank = $summary[2];
        $bad = $summary[3];
        $mixed = $summary[4];

        $t->same('html-language-tag-review', $primary['languageReviewPolicy']);
        $t->same('lang', $primary['languageAttribute']);
        $t->same('EN-us', $primary['languageRaw']);
        $t->same('EN-us', $primary['language']);
        $t->same('en-US', $primary['languageNormalized']);
        $t->same(true, $primary['languageValid']);
        $t->same([], $primary['languageIssueCodes']);
        $t->same('en-US', $primary['effectiveLanguageNormalized']);
        $t->same(true, $primary['effectiveLanguageValid']);

        $t->same('xml:lang', $xml['languageAttribute']);
        $t->same('fr-Latn-ca', $xml['languageRaw']);
        $t->same('fr-Latn-CA', $xml['languageNormalized']);
        $t->same(true, $xml['languageValid']);
        $t->same('self-xml:lang', $xml['languageSource']);
        $t->same('fr-Latn-CA', $xml['effectiveLanguageNormalized']);
        $t->same(true, $xml['effectiveLanguageValid']);

        $t->same('  ', $blank['languageRaw']);
        $t->same('', $blank['language']);
        $t->same(null, $blank['languageNormalized']);
        $t->same(false, $blank['languageValid']);
        $t->same(['empty-html-language-tag'], $blank['languageIssueCodes']);
        $t->true(!array_key_exists('effectiveLanguage', $blank));

        $t->same('en US', $bad['languageRaw']);
        $t->same(null, $bad['languageNormalized']);
        $t->same(false, $bad['languageValid']);
        $t->same(['invalid-html-language-tag'], $bad['languageIssueCodes']);
        $t->same('en US', $bad['effectiveLanguage']);
        $t->same(null, $bad['effectiveLanguageNormalized']);
        $t->same(false, $bad['effectiveLanguageValid']);

        $t->same('lang', $mixed['languageAttribute']);
        $t->same('en', $mixed['languageNormalized']);
        $t->same(true, $mixed['languageValid']);
        $t->same(true, $mixed['languageAttributeConflict']);
        $t->same(['conflicting-language-attributes'], $mixed['languageAttributeIssueCodes']);
        $t->same(['mismatched-html-language-declarations'], $mixed['languageIssueCodes']);
        $t->same(['lang' => 'en', 'xml:lang' => 'fr'], $mixed['languageDeclaredTags']);
        $t->same(['lang' => 'en', 'xml:lang' => 'fr'], $mixed['languageDeclaredNormalized']);
        $t->same(true, $mixed['languageDeclarationMismatch']);
        $t->same('en', $mixed['effectiveLanguage']);
        $t->same('en', $mixed['effectiveLanguageNormalized']);
        $t->same(true, $mixed['effectiveLanguageValid']);

        $t->same(
            '<section id="primary" lang="EN-us">Hello</section>'
                . '<p id="xml" xml:lang="fr-Latn-ca">Bonjour</p>'
                . '<aside id="blank" lang="  ">Blank</aside>'
                . '<div id="bad" lang="en US">Bad</div>'
                . '<article id="mixed" lang="en" xml:lang="fr">Mixed</article>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/language-tag-review.html', $document->children[0]->attr('part'));
    },
    'summarizes inherited html language and direction for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="root" lang="en-US" dir="LTR"><section id="chapter"><p id="body" dir="sideways">Body <span id="quote" lang="fr-CA" dir="auto">Citation</span></p></section></article>'
                . '<aside id="bad-dir" dir="sideways"><span id="neutral">No inherited direction</span></aside>'
                . '<div id="xml-scope" xml:lang="de-DE"><em id="term">Begriff</em></div>',
            'language direction inheritance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/language-direction-inheritance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $section = $article['children'][0];
        $paragraph = $section['children'][0];
        $quote = $paragraph['children'][1];
        $aside = $summary[1];
        $neutral = $aside['children'][0];
        $xmlScope = $summary[2];
        $term = $xmlScope['children'][0];

        $t->same('article', $article['name']);
        $t->same('en-US', $article['effectiveLanguageRaw']);
        $t->same('en-US', $article['effectiveLanguage']);
        $t->same(false, $article['languageInherited']);
        $t->same('self-lang', $article['languageSource']);
        $t->same('LTR', $article['effectiveDirectionRaw']);
        $t->same('ltr', $article['effectiveDirection']);
        $t->same(false, $article['directionInherited']);

        $t->same('section', $section['name']);
        $t->same('en-US', $section['effectiveLanguage']);
        $t->same(true, $section['languageInherited']);
        $t->same('ancestor-lang', $section['languageSource']);
        $t->same('article', $section['languageSourceElement']);
        $t->same('root', $section['languageSourceElementId']);
        $t->same('ltr', $section['effectiveDirection']);
        $t->same(true, $section['directionInherited']);
        $t->same('article', $section['directionSourceElement']);

        $t->same('p', $paragraph['name']);
        $t->same('sideways', $paragraph['dirRaw']);
        $t->same(null, $paragraph['direction']);
        $t->same('ltr', $paragraph['effectiveDirection']);
        $t->same(true, $paragraph['directionInherited']);
        $t->same('root', $paragraph['directionSourceElementId']);
        $t->same('en-US', $paragraph['effectiveLanguage']);

        $t->same('span', $quote['name']);
        $t->same('fr-CA', $quote['effectiveLanguage']);
        $t->same(false, $quote['languageInherited']);
        $t->same('auto', $quote['effectiveDirection']);
        $t->same(false, $quote['directionInherited']);
        $t->same('self-dir', $quote['directionSource']);

        $t->same('aside', $aside['name']);
        $t->same('sideways', $aside['dirRaw']);
        $t->same(null, $aside['direction']);
        $t->true(!array_key_exists('effectiveDirection', $aside));
        $t->same('span', $neutral['name']);
        $t->true(!array_key_exists('effectiveDirection', $neutral));
        $t->true(!array_key_exists('effectiveLanguage', $neutral));

        $t->same('de-DE', $xmlScope['effectiveLanguage']);
        $t->same('self-xml:lang', $xmlScope['languageSource']);
        $t->same('de-DE', $term['effectiveLanguage']);
        $t->same(true, $term['languageInherited']);
        $t->same('div', $term['languageSourceElement']);
        $t->same('xml-scope', $term['languageSourceElementId']);
        $t->contains('<article dir="LTR" id="root" lang="en-US">', $html);
        $t->contains('xml:lang="de-DE"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/language-direction-inheritance-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html language tag provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="root" lang="en-US"><p id="canonical" lang=" EN-us ">Canonical</p>'
                . '<p id="private" lang="x-review">Private</p>'
                . '<p id="bad" lang="bad_tag">Bad <span id="bad-child">Child</span></p>'
                . '<p id="empty" lang="">Empty</p>'
                . '<div id="xml" xml:lang="de-DE"><span id="xml-child">Kind</span></div></article>',
            'language tag provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/language-tag-provenance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $canonical = $article['children'][0];
        $private = $article['children'][1];
        $bad = $article['children'][2];
        $badChild = $bad['children'][1];
        $empty = $article['children'][3];
        $xmlScope = $article['children'][4];
        $xmlChild = $xmlScope['children'][0];

        $t->same('html-language-tag-review', $canonical['languageReviewPolicy']);
        $t->same('lang', $canonical['languageSourceAttribute']);
        $t->same(' EN-us ', $canonical['languageRaw']);
        $t->same('EN-us', $canonical['language']);
        $t->same('en-US', $canonical['languageTag']);
        $t->same(true, $canonical['languageValid']);
        $t->same(false, $canonical['languageInvalidValueIgnored']);
        $t->same('en-US', $canonical['effectiveLanguage']);
        $t->same(false, $canonical['languageInherited']);
        $t->same('self-lang', $canonical['languageSource']);

        $t->same('x-review', $private['languageTag']);
        $t->same(true, $private['languageValid']);
        $t->same(false, $private['languageEmptyValueIgnored']);
        $t->same('x-review', $private['effectiveLanguage']);

        $t->same('bad_tag', $bad['languageRaw']);
        $t->same(null, $bad['languageTag']);
        $t->same(false, $bad['languageValid']);
        $t->same(true, $bad['languageInvalidValueIgnored']);
        $t->same(false, $bad['languageEmptyValueIgnored']);
        $t->same('en-US', $bad['effectiveLanguage']);
        $t->same(true, $bad['languageInherited']);
        $t->same('ancestor-lang', $bad['languageSource']);
        $t->same('root', $bad['languageSourceElementId']);
        $t->same('en-US', $badChild['effectiveLanguage']);
        $t->same(true, $badChild['languageInherited']);

        $t->same('', $empty['languageRaw']);
        $t->same(null, $empty['languageTag']);
        $t->same(false, $empty['languageValid']);
        $t->same(true, $empty['languageEmptyValueIgnored']);
        $t->same(false, $empty['languageInvalidValueIgnored']);
        $t->same('en-US', $empty['effectiveLanguage']);

        $t->same('xml:lang', $xmlScope['languageSourceAttribute']);
        $t->same('de-DE', $xmlScope['languageTag']);
        $t->same(true, $xmlScope['languageValid']);
        $t->same('self-xml:lang', $xmlScope['languageSource']);
        $t->same('de-DE', $xmlChild['effectiveLanguage']);
        $t->same(true, $xmlChild['languageInherited']);
        $t->same('div', $xmlChild['languageSourceElement']);
        $t->contains('lang=" EN-us "', $html);
        $t->contains('xml:lang="de-DE"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/language-tag-provenance-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html language tag canonicalization detail for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="packet" lang="EN-latn-us-x-review"><p id="child">Child</p><p id="override" xml:lang="fr-ca">French</p>'
                . '<p id="private" lang="x-legacy-Import">Private</p><p id="bad-space" lang="en bad">Bad</p><p id="bad-underscore" lang="pt_BR">Bad region</p>'
                . '<section id="empty" lang=""><span id="empty-child">Empty child</span></section></article>',
            'language tag canonicalization review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/language-tag-canonicalization-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $child = $article['children'][0];
        $override = $article['children'][1];
        $private = $article['children'][2];
        $badSpace = $article['children'][3];
        $badUnderscore = $article['children'][4];
        $empty = $article['children'][5];
        $emptyChild = $empty['children'][0];

        $t->same('html-language-tag-review', $article['languageTagReviewPolicy']);
        $t->same('EN-latn-us-x-review', $article['languageTagRaw']);
        $t->same('en-Latn-US-x-review', $article['languageTag']);
        $t->same('en-Latn-US-x-review', $article['languageTagCanonical']);
        $t->same(['EN', 'latn', 'us', 'x', 'review'], $article['languageTagSubtags']);
        $t->same('en', $article['languageTagPrimarySubtag']);
        $t->same('Latn', $article['languageTagScriptSubtag']);
        $t->same('US', $article['languageTagRegionSubtag']);
        $t->same(['review'], $article['languageTagPrivateUseSubtags']);
        $t->same(true, $article['languageTagValid']);
        $t->same([], $article['languageTagIssueCodes']);
        $t->same('en-Latn-US-x-review', $article['effectiveLanguageTagCanonical']);
        $t->same(false, $article['languageInherited']);
        $t->same('self-lang', $article['languageSource']);

        $t->same(true, $child['languageInherited']);
        $t->same('ancestor-lang', $child['languageSource']);
        $t->same('packet', $child['languageSourceElementId']);
        $t->same('en-Latn-US-x-review', $child['effectiveLanguageTagCanonical']);
        $t->same(['review'], $child['effectiveLanguageTagPrivateUseSubtags']);

        $t->same('fr-ca', $override['languageTagRaw']);
        $t->same('fr-CA', $override['languageTag']);
        $t->same('fr-CA', $override['languageTagCanonical']);
        $t->same('CA', $override['languageTagRegionSubtag']);
        $t->same('self-xml:lang', $override['languageSource']);
        $t->same(false, $override['languageInherited']);
        $t->same(true, $override['effectiveLanguageTagValid']);

        $t->same('x-legacy-Import', $private['languageTagRaw']);
        $t->same('x-legacy-import', $private['languageTag']);
        $t->same('x-legacy-import', $private['languageTagCanonical']);
        $t->same('x', $private['languageTagPrimarySubtag']);
        $t->same(['legacy', 'import'], $private['languageTagPrivateUseSubtags']);
        $t->same(true, $private['languageTagValid']);

        $t->same('en bad', $badSpace['languageTagRaw']);
        $t->same(null, $badSpace['languageTagCanonical']);
        $t->same(false, $badSpace['languageTagValid']);
        $t->same([
            'language-tag-ascii-whitespace',
            'invalid-language-subtag',
            'invalid-primary-language-subtag',
        ], $badSpace['languageTagIssueCodes']);
        $t->same(true, $badSpace['languageInherited']);
        $t->same('en-Latn-US-x-review', $badSpace['effectiveLanguageTagCanonical']);

        $t->same('pt_BR', $badUnderscore['languageTagRaw']);
        $t->same(null, $badUnderscore['languageTagCanonical']);
        $t->same(false, $badUnderscore['languageTagValid']);
        $t->same([
            'language-tag-underscore-separator',
            'invalid-language-subtag',
            'invalid-primary-language-subtag',
        ], $badUnderscore['languageTagIssueCodes']);
        $t->same(true, $badUnderscore['languageInherited']);
        $t->same('en-Latn-US-x-review', $badUnderscore['effectiveLanguageTagCanonical']);

        $t->same('', $empty['languageRaw']);
        $t->same(null, $empty['languageTag']);
        $t->same(false, $empty['languageTagValid']);
        $t->same(['empty-language-tag'], $empty['languageTagIssueCodes']);
        $t->same(true, $empty['languageInherited']);
        $t->same('en-Latn-US-x-review', $empty['effectiveLanguageTagCanonical']);
        $t->same(true, $emptyChild['languageInherited']);
        $t->same('en-Latn-US-x-review', $emptyChild['effectiveLanguageTagCanonical']);

        $t->same('<article id="packet" lang="EN-latn-us-x-review"><p id="child">Child</p><p id="override" xml:lang="fr-ca">French</p><p id="private" lang="x-legacy-Import">Private</p><p id="bad-space" lang="en bad">Bad</p><p id="bad-underscore" lang="pt_BR">Bad region</p><section id="empty" lang=""><span id="empty-child">Empty child</span></section></article>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/language-tag-canonicalization-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html language tag alias recovery for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="root" lang="en-US"><p id="child">Child</p>'
                . '<section id="invalid" lang="bad_tag"><span id="invalid-child">Bad</span></section>'
                . '<aside id="empty" lang=""><em id="empty-child">Empty</em></aside>'
                . '<div id="xml" xml:lang="sr-Cyrl-rs">XML</div>'
                . '<b id="private" lang="x-private-review">Private</b></article>'
                . '<p id="outside">Outside</p>',
            'language tag alias recovery review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/language-tag-alias-recovery-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $child = $article['children'][0];
        $invalid = $article['children'][1];
        $invalidChild = $invalid['children'][0];
        $empty = $article['children'][2];
        $xml = $article['children'][3];
        $private = $article['children'][4];
        $outside = $summary[1];

        $t->same('html-language-tag-review', $article['languageTagReviewPolicy']);
        $t->same('lang', $article['languageAttribute']);
        $t->same('en-US', $article['languageRaw']);
        $t->same('en-US', $article['language']);
        $t->same('en-US', $article['languageTag']);
        $t->same('en', $article['languagePrimarySubtag']);
        $t->same(['en', 'US'], $article['languageSubtags']);
        $t->same(true, $article['languageTagValid']);
        $t->same(false, $article['languageTagEmpty']);
        $t->same([], $article['languageTagIssueCodes']);
        $t->same('en-US', $article['effectiveLanguageTag']);
        $t->same('lang', $article['effectiveLanguageAttribute']);
        $t->same(false, $article['languageInherited']);

        $t->same(false, array_key_exists('languageTagReviewPolicy', $child));
        $t->same('en-US', $child['effectiveLanguageTag']);
        $t->same('en', $child['effectiveLanguagePrimarySubtag']);
        $t->same(['en', 'US'], $child['effectiveLanguageSubtags']);
        $t->same(true, $child['languageInherited']);
        $t->same('root', $child['languageSourceElementId']);

        $t->same('bad_tag', $invalid['languageRaw']);
        $t->same(null, $invalid['languageTag']);
        $t->same(false, $invalid['languageTagValid']);
        $t->same([
            'language-tag-underscore-separator',
            'invalid-language-subtag',
            'invalid-primary-language-subtag',
        ], $invalid['languageTagIssueCodes']);
        $t->same('en-US', $invalid['effectiveLanguageTag']);
        $t->same(true, $invalid['languageInherited']);
        $t->same('root', $invalid['languageSourceElementId']);
        $t->same('en-US', $invalidChild['effectiveLanguageTag']);
        $t->same('root', $invalidChild['languageSourceElementId']);

        $t->same(true, $empty['languageTagEmpty']);
        $t->same(false, $empty['languageTagValid']);
        $t->same(['empty-language-tag'], $empty['languageTagIssueCodes']);
        $t->same('en-US', $empty['effectiveLanguageTag']);

        $t->same('xml:lang', $xml['languageAttribute']);
        $t->same('sr-Cyrl-RS', $xml['languageTag']);
        $t->same('sr', $xml['languagePrimarySubtag']);
        $t->same(['sr', 'Cyrl', 'RS'], $xml['languageSubtags']);
        $t->same('xml:lang', $xml['effectiveLanguageAttribute']);
        $t->same('sr-Cyrl-RS', $xml['effectiveLanguageTag']);

        $t->same('x-private-review', $private['languageTag']);
        $t->same('x', $private['languagePrimarySubtag']);
        $t->same(['x', 'private', 'review'], $private['languageSubtags']);
        $t->same(true, $private['languageTagValid']);

        $t->same(false, array_key_exists('effectiveLanguageTag', $outside));
        $t->same(
            '<article id="root" lang="en-US"><p id="child">Child</p><section id="invalid" lang="bad_tag"><span id="invalid-child">Bad</span></section><aside id="empty" lang=""><em id="empty-child">Empty</em></aside><div id="xml" xml:lang="sr-Cyrl-rs">XML</div><b id="private" lang="x-private-review">Private</b></article><p id="outside">Outside</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/language-tag-alias-recovery-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html dir auto first strong character resolution for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="auto-ar" dir="auto">123 العربية</section>'
                . '<section id="auto-ltr" dir="auto">42 Alpha עברית</section>'
                . '<section id="auto-neutral" dir="auto">123 - ?</section>'
                . '<article id="auto-parent" dir="auto">123 שלום <span id="auto-child">Alpha child</span></article>',
            'dir auto first strong character review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/dir-auto-first-strong-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $arabic = $summary[0];
        $latin = $summary[1];
        $neutral = $summary[2];
        $parent = $summary[3];
        $child = $parent['children'][1];

        $t->same('section', $arabic['name']);
        $t->same('auto', $arabic['direction']);
        $t->same('auto', $arabic['effectiveDirection']);
        $t->same('rtl', $arabic['effectiveDirectionResolved']);
        $t->same('html-dir-auto-first-strong-character-review', $arabic['dirAutoReviewPolicy']);
        $t->same('rtl', $arabic['dirAutoResolvedDirection']);
        $t->same(true, $arabic['dirAutoResolved']);
        $t->same(false, $arabic['dirAutoNeutral']);
        $t->same(false, $arabic['dirAutoInherited']);
        $t->same('ا', $arabic['dirAutoFirstStrongCharacter']);
        $t->same('AL', $arabic['dirAutoFirstStrongBidiClass']);
        $t->same(4, $arabic['dirAutoFirstStrongCharacterOffset']);
        $t->same(4, $arabic['dirAutoFirstStrongByteOffset']);
        $t->same('auto-ar', $arabic['dirAutoSourceElementId']);

        $t->same('ltr', $latin['effectiveDirectionResolved']);
        $t->same('ltr', $latin['dirAutoResolvedDirection']);
        $t->same('A', $latin['dirAutoFirstStrongCharacter']);
        $t->same('L', $latin['dirAutoFirstStrongBidiClass']);
        $t->same(3, $latin['dirAutoFirstStrongCharacterOffset']);
        $t->same(3, $latin['dirAutoFirstStrongByteOffset']);

        $t->same('auto', $neutral['effectiveDirection']);
        $t->same(null, $neutral['effectiveDirectionResolved']);
        $t->same(null, $neutral['dirAutoResolvedDirection']);
        $t->same(false, $neutral['dirAutoResolved']);
        $t->same(true, $neutral['dirAutoNeutral']);
        $t->same(null, $neutral['dirAutoFirstStrongCharacter']);
        $t->same(null, $neutral['dirAutoFirstStrongBidiClass']);
        $t->same(null, $neutral['dirAutoFirstStrongCharacterOffset']);
        $t->same(null, $neutral['dirAutoFirstStrongByteOffset']);

        $t->same('article', $parent['name']);
        $t->same('rtl', $parent['effectiveDirectionResolved']);
        $t->same('ש', $parent['dirAutoFirstStrongCharacter']);
        $t->same('R', $parent['dirAutoFirstStrongBidiClass']);
        $t->same(4, $parent['dirAutoFirstStrongCharacterOffset']);
        $t->same(4, $parent['dirAutoFirstStrongByteOffset']);

        $t->same('span', $child['name']);
        $t->same('auto', $child['effectiveDirection']);
        $t->same('rtl', $child['effectiveDirectionResolved']);
        $t->same(true, $child['directionInherited']);
        $t->same('ancestor-dir', $child['directionSource']);
        $t->same('article', $child['directionSourceElement']);
        $t->same('auto-parent', $child['directionSourceElementId']);
        $t->same(true, $child['dirAutoInherited']);
        $t->same('auto-parent', $child['dirAutoSourceElementId']);
        $t->same('rtl', $child['dirAutoResolvedDirection']);
        $t->same('ש', $child['dirAutoFirstStrongCharacter']);
        $t->same('R', $child['dirAutoFirstStrongBidiClass']);

        $t->contains('<section dir="auto" id="auto-ar">123 العربية</section>', $html);
        $t->contains('<article dir="auto" id="auto-parent">123 שלום <span id="auto-child">Alpha child</span></article>', $blocks);
        $t->same('/migration/dir-auto-first-strong-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
    'summarizes inherited html spellcheck state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="checked" spellcheck="false"><p id="body"><span id="invalid" spellcheck="maybe">Invalid</span><span id="enabled" spellcheck="true"><em id="enabled-child">Enabled</em></span></p></article>'
                . '<section id="plain"><p id="plain-child">Plain</p></section>',
            'spellcheck inheritance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/spellcheck-inheritance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $body = $article['children'][0];
        $invalid = $body['children'][0];
        $enabled = $body['children'][1];
        $enabledChild = $enabled['children'][0];
        $plainChild = $summary[1]['children'][0];

        $t->same('article', $article['name']);
        $t->same('false', $article['spellcheckRaw']);
        $t->same(false, $article['spellcheck']);
        $t->same(true, $article['spellcheckValid']);
        $t->same('false', $article['effectiveSpellcheckRaw']);
        $t->same(false, $article['effectiveSpellcheck']);
        $t->same(false, $article['spellcheckInherited']);
        $t->same('self-spellcheck', $article['spellcheckSource']);

        $t->true(!array_key_exists('spellcheckRaw', $body));
        $t->same(false, $body['effectiveSpellcheck']);
        $t->same(true, $body['spellcheckInherited']);
        $t->same('article', $body['spellcheckSourceElement']);
        $t->same('checked', $body['spellcheckSourceElementId']);

        $t->same('maybe', $invalid['spellcheckRaw']);
        $t->same(null, $invalid['spellcheck']);
        $t->same(false, $invalid['spellcheckValid']);
        $t->same(false, $invalid['effectiveSpellcheck']);
        $t->same(true, $invalid['spellcheckInherited']);
        $t->same('checked', $invalid['spellcheckSourceElementId']);

        $t->same('true', $enabled['spellcheckRaw']);
        $t->same(true, $enabled['spellcheck']);
        $t->same(true, $enabled['spellcheckValid']);
        $t->same(true, $enabled['effectiveSpellcheck']);
        $t->same(false, $enabled['spellcheckInherited']);
        $t->same('self-spellcheck', $enabled['spellcheckSource']);

        $t->same(true, $enabledChild['effectiveSpellcheck']);
        $t->same(true, $enabledChild['spellcheckInherited']);
        $t->same('span', $enabledChild['spellcheckSourceElement']);
        $t->same('enabled', $enabledChild['spellcheckSourceElementId']);
        $t->true(!array_key_exists('effectiveSpellcheck', $plainChild));
        $t->contains('<article id="checked" spellcheck="false">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/spellcheck-inheritance-review.html', $document->children[0]->attr('part'));
    },
    'summarizes inherited html inert state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="modal-shell" inert><section id="body"><button id="cta">Save</button><span id="override" inert="false"><em id="nested">Nested</em></span></section></article>'
                . '<section id="active"><button id="active-button">Active</button></section>',
            'inert inheritance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/inert-inheritance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $body = $article['children'][0];
        $button = $body['children'][0];
        $override = $body['children'][1];
        $nested = $override['children'][0];
        $activeButton = $summary[1]['children'][0];

        $t->same('article', $article['name']);
        $t->same('', $article['inertRaw']);
        $t->same(true, $article['inert']);
        $t->same('', $article['effectiveInertRaw']);
        $t->same(true, $article['effectiveInert']);
        $t->same(false, $article['inertInherited']);
        $t->same('self-inert', $article['inertSource']);

        $t->true(!array_key_exists('inertRaw', $body));
        $t->same(true, $body['effectiveInert']);
        $t->same(true, $body['inertInherited']);
        $t->same('article', $body['inertSourceElement']);
        $t->same('modal-shell', $body['inertSourceElementId']);

        $t->same(true, $button['effectiveInert']);
        $t->same(true, $button['inertInherited']);
        $t->same('modal-shell', $button['inertSourceElementId']);

        $t->same('false', $override['inertRaw']);
        $t->same(true, $override['inert']);
        $t->same('false', $override['effectiveInertRaw']);
        $t->same(true, $override['effectiveInert']);
        $t->same(false, $override['inertInherited']);
        $t->same('self-inert', $override['inertSource']);

        $t->same(true, $nested['effectiveInert']);
        $t->same(true, $nested['inertInherited']);
        $t->same('span', $nested['inertSourceElement']);
        $t->same('override', $nested['inertSourceElementId']);
        $t->true(!array_key_exists('effectiveInert', $activeButton));
        $t->contains('<article id="modal-shell" inert>', $html);
        $t->contains('inert="false"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/inert-inheritance-review.html', $document->children[0]->attr('part'));
    },
    'summarizes inherited html contenteditable state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="editor" contenteditable="plaintext-only"><p id="body">Body <span id="bad" contenteditable="maybe">Bad</span><span id="off" contenteditable="false"><em id="locked-child">Locked</em></span></p></article>'
                . '<section id="plain"><p id="plain-child">Plain</p></section>',
            'contenteditable inheritance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/contenteditable-inheritance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $editor = $summary[0];
        $body = $editor['children'][0];
        $bad = $body['children'][1];
        $off = $body['children'][2];
        $locked = $off['children'][0];
        $plainChild = $summary[1]['children'][0];

        $t->same('article', $editor['name']);
        $t->same('plaintext-only', $editor['contentEditableRaw']);
        $t->same('plaintext-only', $editor['contentEditable']);
        $t->same(true, $editor['contentEditableValid']);
        $t->same('plaintext-only', $editor['effectiveContentEditableRaw']);
        $t->same('plaintext-only', $editor['effectiveContentEditable']);
        $t->same(false, $editor['contentEditableInherited']);
        $t->same('self-contenteditable', $editor['contentEditableSource']);

        $t->true(!array_key_exists('contentEditableRaw', $body));
        $t->same('plaintext-only', $body['effectiveContentEditable']);
        $t->same(true, $body['contentEditableInherited']);
        $t->same('article', $body['contentEditableSourceElement']);
        $t->same('editor', $body['contentEditableSourceElementId']);

        $t->same('maybe', $bad['contentEditableRaw']);
        $t->same(null, $bad['contentEditable']);
        $t->same(false, $bad['contentEditableValid']);
        $t->same('plaintext-only', $bad['effectiveContentEditable']);
        $t->same(true, $bad['contentEditableInherited']);
        $t->same('editor', $bad['contentEditableSourceElementId']);

        $t->same('false', $off['contentEditableRaw']);
        $t->same(false, $off['contentEditable']);
        $t->same(true, $off['contentEditableValid']);
        $t->same(false, $off['effectiveContentEditable']);
        $t->same(false, $off['contentEditableInherited']);
        $t->same('self-contenteditable', $off['contentEditableSource']);

        $t->same(false, $locked['effectiveContentEditable']);
        $t->same(true, $locked['contentEditableInherited']);
        $t->same('span', $locked['contentEditableSourceElement']);
        $t->same('off', $locked['contentEditableSourceElementId']);
        $t->true(!array_key_exists('effectiveContentEditable', $plainChild));

        $t->same('<article contenteditable="plaintext-only" id="editor"><p id="body">Body <span contenteditable="maybe" id="bad">Bad</span><span contenteditable="false" id="off"><em id="locked-child">Locked</em></span></p></article><section id="plain"><p id="plain-child">Plain</p></section>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/contenteditable-inheritance-review.html', $document->children[0]->attr('part'));
    },
    'summarizes inherited html translate state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="locked" translate="no"><p id="body"><span id="invalid" translate="maybe">Invalid</span><span id="open" translate="yes"><em id="open-child">Open</em></span></p></article>'
                . '<section id="plain"><p id="plain-child">Plain</p></section>',
            'translate inheritance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/translate-inheritance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $body = $article['children'][0];
        $invalid = $body['children'][0];
        $open = $body['children'][1];
        $openChild = $open['children'][0];
        $plain = $summary[1];
        $plainChild = $plain['children'][0];

        $t->same('article', $article['name']);
        $t->same('no', $article['translateRaw']);
        $t->same(false, $article['translate']);
        $t->same(true, $article['translateValid']);
        $t->same('no', $article['effectiveTranslateRaw']);
        $t->same(false, $article['effectiveTranslate']);
        $t->same(false, $article['translateInherited']);
        $t->same('self-translate', $article['translateSource']);

        $t->true(!array_key_exists('translateRaw', $body));
        $t->same(false, $body['effectiveTranslate']);
        $t->same(true, $body['translateInherited']);
        $t->same('article', $body['translateSourceElement']);
        $t->same('locked', $body['translateSourceElementId']);

        $t->same('maybe', $invalid['translateRaw']);
        $t->same(null, $invalid['translate']);
        $t->same(false, $invalid['translateValid']);
        $t->same(false, $invalid['effectiveTranslate']);
        $t->same(true, $invalid['translateInherited']);
        $t->same('locked', $invalid['translateSourceElementId']);

        $t->same('yes', $open['translateRaw']);
        $t->same(true, $open['translate']);
        $t->same(true, $open['translateValid']);
        $t->same(true, $open['effectiveTranslate']);
        $t->same(false, $open['translateInherited']);
        $t->same('self-translate', $open['translateSource']);

        $t->same(true, $openChild['effectiveTranslate']);
        $t->same(true, $openChild['translateInherited']);
        $t->same('span', $openChild['translateSourceElement']);
        $t->same('open', $openChild['translateSourceElementId']);
        $t->true(!array_key_exists('effectiveTranslate', $plainChild));
        $t->contains('<article id="locked" translate="no">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/translate-inheritance-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html inert custom-token inheritance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="modal" inert><p id="body"><button id="save">Save</button><span id="local" inert="soft-lock"><em id="local-child">Child</em></span></p></article>'
                . '<section id="active"><p id="active-child">Active</p></section>',
            'inert custom-token inheritance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/inert-custom-token-inheritance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $body = $article['children'][0];
        $button = $body['children'][0];
        $local = $body['children'][1];
        $localChild = $local['children'][0];
        $active = $summary[1];
        $activeChild = $active['children'][0];

        $t->same('article', $article['name']);
        $t->same('', $article['inertRaw']);
        $t->same(true, $article['inert']);
        $t->same('', $article['effectiveInertRaw']);
        $t->same(true, $article['effectiveInert']);
        $t->same(false, $article['inertInherited']);
        $t->same('self-inert', $article['inertSource']);

        $t->true(!array_key_exists('inertRaw', $body));
        $t->same(true, $body['effectiveInert']);
        $t->same(true, $body['inertInherited']);
        $t->same('article', $body['inertSourceElement']);
        $t->same('modal', $body['inertSourceElementId']);

        $t->same(true, $button['effectiveInert']);
        $t->same(true, $button['inertInherited']);
        $t->same('modal', $button['inertSourceElementId']);

        $t->same('soft-lock', $local['inertRaw']);
        $t->same(true, $local['inert']);
        $t->same('soft-lock', $local['effectiveInertRaw']);
        $t->same(false, $local['inertInherited']);
        $t->same('self-inert', $local['inertSource']);

        $t->same(true, $localChild['effectiveInert']);
        $t->same(true, $localChild['inertInherited']);
        $t->same('span', $localChild['inertSourceElement']);
        $t->same('local', $localChild['inertSourceElementId']);
        $t->true(!array_key_exists('effectiveInert', $active));
        $t->true(!array_key_exists('effectiveInert', $activeChild));
        $t->same('<article id="modal" inert><p id="body"><button id="save">Save</button><span id="local" inert="soft-lock"><em id="local-child">Child</em></span></p></article><section id="active"><p id="active-child">Active</p></section>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/inert-custom-token-inheritance-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html dropzone tokens for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="drop" draggable="true" dropzone="copy string:text/plain file:image/png string:text/html">Drop files</section>'
                . '<p id="bad-drop" dropzone="execute string:javascript file:bad mime link move">Fallback</p>'
                . '<div id="multi-effect" dropzone="copy move string:text/plain">Multi</div>',
            'dropzone attribute review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);

        $drop = $summary[0];
        $fallback = $summary[1];
        $multiple = $summary[2];

        $t->same('html-dropzone-attribute-review', $drop['dropZoneReviewPolicy']);
        $t->same(['copy', 'string:text/plain', 'file:image/png', 'string:text/html'], $drop['dropZoneTokens']);
        $t->same(['copy'], $drop['dropZoneEffects']);
        $t->same(['text/plain', 'text/html'], $drop['dropZoneStringTypes']);
        $t->same(['image/png'], $drop['dropZoneFileTypes']);
        $t->same([], $drop['invalidDropZoneTokens']);
        $t->same(false, $drop['dropZoneMultipleEffects']);
        $t->same(true, $drop['dropZoneValid']);
        $t->same('string', $drop['dropZoneItems'][1]['kind']);
        $t->same('text/plain', $drop['dropZoneItems'][1]['value']);

        $t->same(['execute', 'string:javascript', 'file:bad', 'mime', 'link', 'move'], $fallback['dropZoneTokens']);
        $t->same(['link', 'move'], $fallback['dropZoneEffects']);
        $t->same(['execute', 'string:javascript', 'file:bad', 'mime'], $fallback['invalidDropZoneTokens']);
        $t->same(true, $fallback['dropZoneMultipleEffects']);
        $t->same(false, $fallback['dropZoneValid']);

        $t->same([], $multiple['invalidDropZoneTokens']);
        $t->same(['copy', 'move'], $multiple['dropZoneEffects']);
        $t->same(true, $multiple['dropZoneMultipleEffects']);
        $t->same(false, $multiple['dropZoneValid']);
    },
    'summarizes html hidden token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="bare" hidden>Bare</section>'
                . '<section id="keyword" hidden="hidden">Keyword</section>'
                . '<section id="found" hidden="until-found">Found</section>'
                . '<section id="case" hidden="UNTIL-FOUND">Case</section>'
                . '<section id="invalid" hidden="collapse">Invalid</section>',
            'hidden token provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hidden-token-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $bare = $summary[0];
        $keyword = $summary[1];
        $found = $summary[2];
        $case = $summary[3];
        $invalid = $summary[4];

        $t->same('', $bare['hiddenRaw']);
        $t->same('hidden', $bare['hiddenKeyword']);
        $t->same('hidden', $bare['hiddenState']);
        $t->same(true, $bare['hiddenValid']);
        $t->same(false, $bare['hiddenInvalidValueDefaulted']);

        $t->same('hidden', $keyword['hiddenRaw']);
        $t->same('hidden', $keyword['hiddenKeyword']);
        $t->same('hidden', $keyword['hiddenState']);
        $t->same(true, $keyword['hiddenValid']);

        $t->same('until-found', $found['hiddenRaw']);
        $t->same('until-found', $found['hiddenKeyword']);
        $t->same('until-found', $found['hiddenState']);
        $t->same(true, $found['hiddenValid']);
        $t->same(false, $found['hiddenInvalidValueDefaulted']);

        $t->same('UNTIL-FOUND', $case['hiddenRaw']);
        $t->same('until-found', $case['hiddenKeyword']);
        $t->same('until-found', $case['hiddenState']);
        $t->same(true, $case['hiddenValid']);

        $t->same('collapse', $invalid['hiddenRaw']);
        $t->same(null, $invalid['hiddenKeyword']);
        $t->same('hidden', $invalid['hiddenState']);
        $t->same(false, $invalid['hiddenValid']);
        $t->same(true, $invalid['hiddenInvalidValueDefaulted']);
        $t->same(
            '<section hidden id="bare">Bare</section>'
                . '<section hidden id="keyword">Keyword</section>'
                . '<section hidden="until-found" id="found">Found</section>'
                . '<section hidden="UNTIL-FOUND" id="case">Case</section>'
                . '<section hidden="collapse" id="invalid">Invalid</section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/hidden-token-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html writing assistance attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="editor" contenteditable autocorrect="off" writingsuggestions="false" virtualkeyboardpolicy="manual">Draft</section>'
                . '<input id="lookup" value="Ada" autocorrect writingsuggestions virtualkeyboardpolicy>'
                . '<p autocorrect="maybe" writingsuggestions="maybe" virtualkeyboardpolicy="onscreen">Fallback</p>',
            'writing assistance attribute review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/writing-assistance-attribute-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $editor = $summary[0];
        $lookup = $summary[1];
        $fallback = $summary[2];

        $t->same('section', $editor['name']);
        $t->same('editor', $editor['elementId']);
        $t->same('off', $editor['autocorrectRaw']);
        $t->same('off', $editor['autocorrect']);
        $t->same(true, $editor['autocorrectValid']);
        $t->same('false', $editor['writingSuggestionsRaw']);
        $t->same(false, $editor['writingSuggestions']);
        $t->same(true, $editor['writingSuggestionsValid']);
        $t->same('manual', $editor['virtualKeyboardPolicyRaw']);
        $t->same('manual', $editor['virtualKeyboardPolicy']);
        $t->same(true, $editor['virtualKeyboardPolicyValid']);

        $t->same('input', $lookup['name']);
        $t->same('lookup', $lookup['elementId']);
        $t->same('', $lookup['autocorrectRaw']);
        $t->same('on', $lookup['autocorrect']);
        $t->same(true, $lookup['autocorrectValid']);
        $t->same('', $lookup['writingSuggestionsRaw']);
        $t->same(true, $lookup['writingSuggestions']);
        $t->same(true, $lookup['writingSuggestionsValid']);
        $t->same('', $lookup['virtualKeyboardPolicyRaw']);
        $t->same('auto', $lookup['virtualKeyboardPolicy']);
        $t->same(true, $lookup['virtualKeyboardPolicyValid']);

        $t->same('p', $fallback['name']);
        $t->same('maybe', $fallback['autocorrectRaw']);
        $t->same(null, $fallback['autocorrect']);
        $t->same(false, $fallback['autocorrectValid']);
        $t->same('maybe', $fallback['writingSuggestionsRaw']);
        $t->same(null, $fallback['writingSuggestions']);
        $t->same(false, $fallback['writingSuggestionsValid']);
        $t->same('onscreen', $fallback['virtualKeyboardPolicyRaw']);
        $t->same(null, $fallback['virtualKeyboardPolicy']);
        $t->same(false, $fallback['virtualKeyboardPolicyValid']);
        $t->same(
            '<section autocorrect="off" contenteditable="" id="editor" virtualkeyboardpolicy="manual" writingsuggestions="false">Draft</section>'
                . '<input autocorrect="" id="lookup" value="Ada" virtualkeyboardpolicy="" writingsuggestions="">'
                . '<p autocorrect="maybe" virtualkeyboardpolicy="onscreen" writingsuggestions="maybe">Fallback</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/writing-assistance-attribute-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html microdata attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="review" itemscope itemtype="https://schema.org/Article ./Local bad&lt;tag" itemid="./articles/42" itemref="headline author missing bad&lt;tag">'
                . '<h1 id="headline" itemprop="headline schema:name bad&lt;tag headline">Title</h1><p id="author" itemprop="author">Ada</p></article>'
                . '<span itemtype="javascript:alert(1)" itemid=" bad id ">Loose</span>',
            'microdata attribute review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/microdata-attribute-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $headline = $article['children'][0];
        $author = $article['children'][1];
        $invalid = $summary[1];

        $t->same('item', $article['microdata']);
        $t->same('', $article['itemScopeRaw']);
        $t->same(true, $article['itemScope']);
        $t->same('https://schema.org/Article ./Local bad<tag', $article['itemTypeRaw']);
        $t->same(['https://schema.org/Article', './Local', 'bad<tag'], $article['itemTypeTokens']);
        $t->same(['https://schema.org/Article', './Local'], $article['itemTypes']);
        $t->same(['bad<tag'], $article['invalidItemTypes']);
        $t->same(false, $article['itemTypeValid']);
        $t->same('./articles/42', $article['itemIdRaw']);
        $t->same('./articles/42', $article['itemId']);
        $t->same(true, $article['itemIdValid']);
        $t->same('headline author missing bad<tag', $article['itemRefRaw']);
        $t->same(['headline', 'author', 'missing', 'bad<tag'], $article['itemRefTokens']);
        $t->same(['headline', 'author', 'missing'], $article['itemRefIds']);
        $t->same(['bad<tag'], $article['invalidItemRefIds']);
        $t->same(false, $article['itemRefValid']);
        $t->same(['headline', 'author'], $article['itemRefResolvedIds']);
        $t->same(['missing'], $article['itemRefMissingIds']);

        $t->same('property', $headline['microdata']);
        $t->same('headline schema:name bad<tag headline', $headline['itemPropRaw']);
        $t->same(['headline', 'schema:name', 'bad<tag', 'headline'], $headline['itemPropTokens']);
        $t->same(['headline', 'schema:name'], $headline['itemProperties']);
        $t->same(['bad<tag'], $headline['invalidItemProperties']);
        $t->same(false, $headline['itemPropValid']);
        $t->same('author', $author['itemPropRaw']);
        $t->same(['author'], $author['itemProperties']);
        $t->same(true, $author['itemPropValid']);

        $t->same('metadata', $invalid['microdata']);
        $t->same(['javascript:alert(1)'], $invalid['itemTypeTokens']);
        $t->same([], $invalid['itemTypes']);
        $t->same(['javascript:alert(1)'], $invalid['invalidItemTypes']);
        $t->same(false, $invalid['itemTypeValid']);
        $t->same(' bad id ', $invalid['itemIdRaw']);
        $t->same('bad id', $invalid['itemId']);
        $t->same(false, $invalid['itemIdValid']);
        $t->same('<article id="review" itemid="./articles/42" itemref="headline author missing bad&lt;tag" itemscope itemtype="https://schema.org/Article ./Local bad&lt;tag"><h1 id="headline" itemprop="headline schema:name bad&lt;tag headline">Title</h1><p id="author" itemprop="author">Ada</p></article><span itemid=" bad id " itemtype="javascript:alert(1)">Loose</span>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/microdata-attribute-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html microdata property values for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article itemscope itemtype="https://schema.org/Article">'
                . '<meta itemprop="headline" content="  Hidden title  ">'
                . '<a itemprop="url" href="./article.html?draft=1">Article</a>'
                . '<img itemprop="image" src="cover.png" alt="Cover">'
                . '<data itemprop="wordCount" value="1234">1,234 words</data>'
                . '<time itemprop="datePublished" datetime="2026-06-17">June 17</time>'
                . '<p itemprop="description">  Summary <strong>text</strong>  </p>'
                . '<meta itemprop="empty" content="">'
                . '</article>',
            'microdata property value review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/microdata-property-value-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $headline = $article['children'][0];
        $url = $article['children'][1];
        $image = $article['children'][2];
        $wordCount = $article['children'][3];
        $date = $article['children'][4];
        $description = $article['children'][5];
        $empty = $article['children'][6];

        $t->same('headline', $headline['itemPropRaw']);
        $t->same('attribute', $headline['itemValueSource']);
        $t->same('content', $headline['itemValueSourceAttribute']);
        $t->same('  Hidden title  ', $headline['itemValueRaw']);
        $t->same('Hidden title', $headline['itemValue']);
        $t->same(true, $headline['itemValueValid']);

        $t->same('href', $url['itemValueSourceAttribute']);
        $t->same('./article.html?draft=1', $url['itemValue']);
        $t->same('src', $image['itemValueSourceAttribute']);
        $t->same('cover.png', $image['itemValue']);
        $t->same('value', $wordCount['itemValueSourceAttribute']);
        $t->same('1234', $wordCount['itemValue']);
        $t->same('datetime', $date['itemValueSourceAttribute']);
        $t->same('2026-06-17', $date['itemValue']);
        $t->same('text', $description['itemValueSource']);
        $t->same(null, $description['itemValueSourceAttribute']);
        $t->same('Summary text', $description['itemValue']);
        $t->same('content', $empty['itemValueSourceAttribute']);
        $t->same('', $empty['itemValueRaw']);
        $t->same(null, $empty['itemValue']);
        $t->same(false, $empty['itemValueValid']);

        $t->same(
            '<article itemscope itemtype="https://schema.org/Article"><meta content="  Hidden title  " itemprop="headline"><a href="./article.html?draft=1" itemprop="url">Article</a><img alt="Cover" itemprop="image" src="cover.png"><data itemprop="wordCount" value="1234">1,234 words</data><time datetime="2026-06-17" itemprop="datePublished">June 17</time><p itemprop="description">  Summary <strong>text</strong>  </p><meta content="" itemprop="empty"></article>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/microdata-property-value-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html rdfa semantic attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="review-rdfa" vocab="https://schema.org/" typeof="Article ReviewNewsArticle bad&lt;term" about="./articles/42" prefix="dc: http://purl.org/dc/terms/ schema: https://schema.org/ bad-prefix javascript:alert(1) dangling:" inlist="inlist">'
                . '<h1 property="headline schema:name bad&lt;prop" content="RDFa title">Visible Title</h1>'
                . '<a rel="author next javascript:alert(1)" rev="reviewedBy" resource="#author" href="/authors/ada">Ada</a>'
                . '<span property="datePublished" datatype="xsd:date" content="2026-06-12">June 12</span>'
                . '<span about=" bad id " typeof="javascript:alert(1)">Invalid</span></article>',
            'RDFa semantic review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/rdfa-semantic-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $heading = $article['children'][0];
        $link = $article['children'][1];
        $date = $article['children'][2];
        $invalid = $article['children'][3];

        $t->same('article', $article['name']);
        $t->same('review-rdfa', $article['elementId']);
        $t->same('resource', $article['rdfa']);
        $t->same(['about', 'inlist', 'prefix', 'typeof', 'vocab'], $article['rdfaAttributes']);
        $t->same('https://schema.org/', $article['rdfaVocab']);
        $t->same(true, $article['rdfaVocabValid']);
        $t->same([
            'dc' => 'http://purl.org/dc/terms/',
            'schema' => 'https://schema.org/',
        ], $article['rdfaPrefixes']);
        $t->same([
            ['raw' => 'dc: http://purl.org/dc/terms/', 'prefix' => 'dc', 'iri' => 'http://purl.org/dc/terms/', 'valid' => true],
            ['raw' => 'schema: https://schema.org/', 'prefix' => 'schema', 'iri' => 'https://schema.org/', 'valid' => true],
            ['raw' => 'bad-prefix javascript:alert(1)', 'prefix' => null, 'iri' => 'javascript:alert(1)', 'valid' => false],
            ['raw' => 'dangling:', 'prefix' => 'dangling', 'iri' => null, 'valid' => false],
        ], $article['rdfaPrefixMappings']);
        $t->same(['bad-prefix javascript:alert(1)', 'dangling:'], $article['invalidRdfaPrefixMappings']);
        $t->same(false, $article['rdfaPrefixValid']);
        $t->same(['Article', 'ReviewNewsArticle', 'bad<term'], $article['rdfaTypeofTokens']);
        $t->same(['Article', 'ReviewNewsArticle'], $article['rdfaTypes']);
        $t->same(['bad<term'], $article['invalidRdfaTypes']);
        $t->same(false, $article['rdfaTypeofValid']);
        $t->same('./articles/42', $article['rdfaAbout']);
        $t->same(true, $article['rdfaAboutValid']);
        $t->same('inlist', $article['rdfaInListRaw']);
        $t->same(true, $article['rdfaInList']);

        $t->same('property', $heading['rdfa']);
        $t->same(['content', 'property'], $heading['rdfaAttributes']);
        $t->same(['headline', 'schema:name', 'bad<prop'], $heading['rdfaPropertyTokens']);
        $t->same(['headline', 'schema:name'], $heading['rdfaProperties']);
        $t->same(['bad<prop'], $heading['invalidRdfaProperties']);
        $t->same(false, $heading['rdfaPropertyValid']);
        $t->same('RDFa title', $heading['rdfaContent']);
        $t->same(true, $heading['rdfaContentValid']);
        $t->same('heading', $heading['documentOutline']);

        $t->same('relationship', $link['rdfa']);
        $t->same(['rel', 'resource', 'rev'], $link['rdfaAttributes']);
        $t->same(['author', 'next', 'javascript:alert(1)'], $link['rdfaRelTokens']);
        $t->same(['author', 'next'], $link['rdfaRelations']);
        $t->same(['javascript:alert(1)'], $link['invalidRdfaRelations']);
        $t->same(false, $link['rdfaRelValid']);
        $t->same(['reviewedBy'], $link['rdfaReverseRelations']);
        $t->same(true, $link['rdfaRevValid']);
        $t->same('#author', $link['rdfaResource']);
        $t->same(true, $link['rdfaResourceValid']);
        $t->same('a', $link['hyperlink']);

        $t->same('property', $date['rdfa']);
        $t->same(['datePublished'], $date['rdfaProperties']);
        $t->same('xsd:date', $date['rdfaDatatype']);
        $t->same(true, $date['rdfaDatatypeValid']);
        $t->same('2026-06-12', $date['rdfaContent']);
        $t->same(true, $date['rdfaContentValid']);

        $t->same('resource', $invalid['rdfa']);
        $t->same(['javascript:alert(1)'], $invalid['invalidRdfaTypes']);
        $t->same(false, $invalid['rdfaTypeofValid']);
        $t->same('bad id', $invalid['rdfaAbout']);
        $t->same(false, $invalid['rdfaAboutValid']);

        $t->same('<article about="./articles/42" id="review-rdfa" inlist="inlist" prefix="dc: http://purl.org/dc/terms/ schema: https://schema.org/ bad-prefix javascript:alert(1) dangling:" typeof="Article ReviewNewsArticle bad&lt;term" vocab="https://schema.org/"><h1 content="RDFa title" property="headline schema:name bad&lt;prop">Visible Title</h1><a href="/authors/ada" rel="author next javascript:alert(1)" resource="#author" rev="reviewedBy">Ada</a><span content="2026-06-12" datatype="xsd:date" property="datePublished">June 12</span><span about=" bad id " typeof="javascript:alert(1)">Invalid</span></article>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/rdfa-semantic-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html aria reference attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="region" role="region" aria-labelledby="title missing title" aria-describedby="desc help dup" aria-controls="panel,ghost" aria-owns="row1 row1 row2" aria-details="details">'
                . '<h2 id="title">Title</h2><p id="desc">Description</p><p id="help">Help</p><p id="dup">First duplicate</p><p id="dup">Second duplicate</p><div id="panel"></div><aside id="details"></aside><span id="row1"></span></section>'
                . '<button id="active" aria-activedescendant="item-1 item-2" aria-errormessage="error" aria-flowto="next-step missing-flow">Next</button><span id="item-1"></span><p id="next-step"></p>',
            'ARIA reference review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/aria-reference-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $region = $summary[0];
        $button = $summary[1];

        $t->same('region', $region['elementId']);
        $t->same(['region'], $region['roles']);
        $t->same([
            'aria-controls' => 'panel,ghost',
            'aria-describedby' => 'desc help dup',
            'aria-details' => 'details',
            'aria-labelledby' => 'title missing title',
            'aria-owns' => 'row1 row1 row2',
        ], $region['ariaAttributes']);
        $t->same(['aria-controls', 'aria-describedby', 'aria-details', 'aria-labelledby', 'aria-owns'], $region['ariaReferenceAttributes']);
        $t->same(5, $region['ariaReferenceCount']);
        $t->same(false, $region['ariaReferencesResolved']);
        $t->same([
            'invalid-aria-reference-token',
            'duplicate-aria-reference-target-id',
            'missing-aria-reference-target',
            'duplicate-aria-reference-token',
        ], $region['ariaReferenceIssueCodes']);
        $t->same(['title', 'missing'], $region['ariaReferences']['aria-labelledby']['ids']);
        $t->same(['title'], $region['ariaReferences']['aria-labelledby']['duplicateIds']);
        $t->same(['title'], $region['ariaReferences']['aria-labelledby']['presentIds']);
        $t->same(['missing'], $region['ariaReferences']['aria-labelledby']['missingIds']);
        $t->same(['Title', 'Title'], array_map(
            static fn (array $target): string => $target['text'],
            $region['ariaReferences']['aria-labelledby']['targets']
        ));
        $t->same('missing-target', $region['ariaReferences']['aria-labelledby']['references'][1]['state']);
        $t->same(true, $region['ariaReferences']['aria-labelledby']['references'][2]['duplicateToken']);
        $t->same(['missing-aria-reference-target', 'duplicate-aria-reference-token'], $region['ariaReferences']['aria-labelledby']['issueCodes']);
        $t->same(['desc', 'help', 'dup'], $region['ariaReferences']['aria-describedby']['presentIds']);
        $t->same([], $region['ariaReferences']['aria-describedby']['missingIds']);
        $t->same(['dup'], $region['ariaReferences']['aria-describedby']['duplicateTargetIds']);
        $t->same(['Description', 'Help', 'First duplicate', 'Second duplicate'], array_map(
            static fn (array $target): string => $target['text'],
            $region['ariaReferences']['aria-describedby']['targets']
        ));
        $t->same('duplicate-target-id', $region['ariaReferences']['aria-describedby']['references'][2]['targetState']);
        $t->same(['duplicate-aria-reference-target-id'], $region['ariaReferences']['aria-describedby']['issueCodes']);
        $t->same(false, $region['ariaReferences']['aria-describedby']['resolved']);
        $t->same(['panel,ghost'], $region['ariaReferences']['aria-controls']['invalidTokens']);
        $t->same([], $region['ariaReferences']['aria-controls']['ids']);
        $t->same(false, $region['ariaReferences']['aria-controls']['valid']);
        $t->same(['details'], $region['ariaReferences']['aria-details']['presentIds']);
        $t->same('aside', $region['ariaReferences']['aria-details']['references'][0]['targetElementName']);
        $t->same('details', $region['ariaReferences']['aria-details']['references'][0]['targetId']);
        $t->same(true, $region['ariaReferences']['aria-details']['resolved']);
        $t->same(['row1', 'row2'], $region['ariaReferences']['aria-owns']['ids']);
        $t->same(['row1'], $region['ariaReferences']['aria-owns']['duplicateIds']);
        $t->same(['row2'], $region['ariaReferences']['aria-owns']['missingIds']);

        $t->same('active', $button['elementId']);
        $t->same(['aria-activedescendant', 'aria-errormessage', 'aria-flowto'], $button['ariaReferenceAttributes']);
        $t->same(['item-1', 'item-2'], $button['ariaReferences']['aria-activedescendant']['ids']);
        $t->same(['item-1'], $button['ariaReferences']['aria-activedescendant']['presentIds']);
        $t->same(['item-2'], $button['ariaReferences']['aria-activedescendant']['missingIds']);
        $t->same(false, $button['ariaReferences']['aria-activedescendant']['valid']);
        $t->same(false, $button['ariaReferences']['aria-activedescendant']['resolved']);
        $t->same(['missing-aria-reference-target', 'multiple-aria-reference-tokens'], $button['ariaReferences']['aria-activedescendant']['issueCodes']);
        $t->same(['error'], $button['ariaReferences']['aria-errormessage']['missingIds']);
        $t->same(true, $button['ariaReferences']['aria-errormessage']['valid']);
        $t->same(['next-step'], $button['ariaReferences']['aria-flowto']['presentIds']);
        $t->same(['missing-flow'], $button['ariaReferences']['aria-flowto']['missingIds']);
        $t->same(false, $button['ariaReferences']['aria-flowto']['resolved']);
        $t->same(false, $button['ariaReferencesResolved']);
        $t->same(['item-1', 'next-step'], $button['ariaReferenceTargetIds']);
        $t->same(['item-2', 'error', 'missing-flow'], $button['ariaReferenceMissingIds']);

        $t->same('<section aria-controls="panel,ghost" aria-describedby="desc help dup" aria-details="details" aria-labelledby="title missing title" aria-owns="row1 row1 row2" id="region" role="region"><h2 id="title">Title</h2><p id="desc">Description</p><p id="help">Help</p><p id="dup">First duplicate</p><p id="dup">Second duplicate</p><div id="panel"></div><aside id="details"></aside><span id="row1"></span></section><button aria-activedescendant="item-1 item-2" aria-errormessage="error" aria-flowto="next-step missing-flow" id="active">Next</button><span id="item-1"></span><p id="next-step"></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/aria-reference-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html duplicate id provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="panel" aria-labelledby="title duplicate missing">'
                . '<h2 id="title">Title</h2><p id="duplicate">First duplicate</p><p id="duplicate">Second duplicate</p>'
                . '<p id="bad id">Bad id</p><p id="">Empty id</p></section>'
                . '<aside id="duplicate">Outside duplicate</aside>',
            'HTML duplicate id review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/html-id-uniqueness-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $panel = $summary[0];
        $title = $panel['children'][0];
        $firstDuplicate = $panel['children'][1];
        $secondDuplicate = $panel['children'][2];
        $invalid = $panel['children'][3];
        $empty = $panel['children'][4];
        $outsideDuplicate = $summary[1];

        $t->same('html-document-id-uniqueness-review', $panel['idReviewPolicy']);
        $t->same('panel', $panel['elementId']);
        $t->same('panel', $panel['elementIdNormalized']);
        $t->same(true, $panel['elementIdValid']);
        $t->same(false, $panel['elementIdDuplicate']);
        $t->same(1, $panel['elementIdOccurrenceCount']);
        $t->same(7, $panel['documentIdCount']);
        $t->same(5, $panel['documentValidIdCount']);
        $t->same(2, $panel['documentInvalidIdCount']);
        $t->same(1, $panel['documentDuplicateIdCount']);
        $t->same(['duplicate'], $panel['documentDuplicateIds']);
        $t->same([], $panel['idIssueCodes']);

        $t->same('title', $title['elementId']);
        $t->same(true, $title['elementIdValid']);
        $t->same(false, $title['elementIdDuplicate']);

        $t->same('duplicate', $firstDuplicate['elementId']);
        $t->same(true, $firstDuplicate['elementIdDuplicate']);
        $t->same(3, $firstDuplicate['elementIdOccurrenceCount']);
        $t->same(['duplicate-html-id'], $firstDuplicate['idIssueCodes']);
        $t->same('duplicate', $firstDuplicate['idIssues'][0]['id'] ?? null);
        $t->same(3, $firstDuplicate['idIssues'][0]['count'] ?? null);
        $t->same([2, 3, 6], array_map(
            static fn (array $occurrence): int => (int) $occurrence['index'],
            $firstDuplicate['elementIdOccurrences']
        ));
        $t->same([true, false, false], array_map(
            static fn (array $occurrence): bool => (bool) $occurrence['current'],
            $firstDuplicate['elementIdOccurrences']
        ));

        $t->same(true, $secondDuplicate['elementIdDuplicate']);
        $t->same([2, 3, 6], array_map(
            static fn (array $occurrence): int => (int) $occurrence['index'],
            $secondDuplicate['elementIdOccurrences']
        ));
        $t->same([false, true, false], array_map(
            static fn (array $occurrence): bool => (bool) $occurrence['current'],
            $secondDuplicate['elementIdOccurrences']
        ));

        $t->same('bad id', $invalid['elementId']);
        $t->same('bad id', $invalid['elementIdNormalized']);
        $t->same(false, $invalid['elementIdValid']);
        $t->same(false, $invalid['elementIdDuplicate']);
        $t->same(0, $invalid['elementIdOccurrenceCount']);
        $t->same(['invalid-html-id'], $invalid['idIssueCodes']);
        $t->same('bad id', $invalid['idIssues'][0]['idRaw'] ?? null);

        $t->same('', $empty['elementId']);
        $t->same(null, $empty['elementIdNormalized']);
        $t->same(false, $empty['elementIdValid']);
        $t->same(['invalid-html-id'], $empty['idIssueCodes']);
        $t->same('', $empty['idIssues'][0]['idRaw'] ?? null);

        $t->same('duplicate', $outsideDuplicate['elementId']);
        $t->same(true, $outsideDuplicate['elementIdDuplicate']);
        $t->same(3, $outsideDuplicate['elementIdOccurrenceCount']);
        $t->same([2, 3, 6], array_map(
            static fn (array $occurrence): int => (int) $occurrence['index'],
            $outsideDuplicate['documentDuplicateIdOccurrences']
        ));
        $t->same([false, false, true], array_map(
            static fn (array $occurrence): bool => (bool) $occurrence['current'],
            $outsideDuplicate['elementIdOccurrences']
        ));
        $t->same([
            ['index' => 4, 'tag' => 'p', 'idRaw' => 'bad id', 'id' => 'bad id', 'valid' => false, 'current' => false, 'text' => 'Bad id'],
            ['index' => 5, 'tag' => 'p', 'idRaw' => '', 'id' => null, 'valid' => false, 'current' => false, 'text' => 'Empty id'],
        ], $outsideDuplicate['documentInvalidIdOccurrences']);

        $t->same('<section aria-labelledby="title duplicate missing" id="panel"><h2 id="title">Title</h2><p id="duplicate">First duplicate</p><p id="duplicate">Second duplicate</p><p id="bad id">Bad id</p><p id="">Empty id</p></section><aside id="duplicate">Outside duplicate</aside>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/html-id-uniqueness-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html focus navigation attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="focus-region" accesskey="s x s" autofocus="autofocus" tabindex="3"><button id="save" accesskey="k Enter" tabindex="-2">Save</button></section>'
                . '<p id="invalid-focus" accesskey="wide key" tabindex="bogus">No focus</p>',
            'focus navigation review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/focus-navigation-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $section = $summary[0];
        $button = $section['children'][0];
        $invalid = $summary[1];

        $t->same('focus-region', $section['elementId']);
        $t->same('s x s', $section['accessKeyRaw']);
        $t->same(['s', 'x', 's'], $section['accessKeyTokens']);
        $t->same(['s', 'x'], $section['accessKeys']);
        $t->same([], $section['invalidAccessKeyTokens']);
        $t->same(true, $section['accessKeyValid']);
        $t->same('autofocus', $section['autofocusRaw']);
        $t->same(true, $section['autofocus']);
        $t->same('3', $section['tabIndexRaw']);
        $t->same(3, $section['tabIndex']);
        $t->same(true, $section['tabIndexValid']);

        $t->same('button', $button['name']);
        $t->same('button', $button['formControl']);
        $t->same('save', $button['elementId']);
        $t->same('k Enter', $button['accessKeyRaw']);
        $t->same(['k', 'Enter'], $button['accessKeyTokens']);
        $t->same(['k'], $button['accessKeys']);
        $t->same(['Enter'], $button['invalidAccessKeyTokens']);
        $t->same(false, $button['accessKeyValid']);
        $t->same('-2', $button['tabIndexRaw']);
        $t->same(-2, $button['tabIndex']);
        $t->same(true, $button['tabIndexValid']);

        $t->same('invalid-focus', $invalid['elementId']);
        $t->same('wide key', $invalid['accessKeyRaw']);
        $t->same(['wide', 'key'], $invalid['accessKeyTokens']);
        $t->same([], $invalid['accessKeys']);
        $t->same(['wide', 'key'], $invalid['invalidAccessKeyTokens']);
        $t->same(false, $invalid['accessKeyValid']);
        $t->same('bogus', $invalid['tabIndexRaw']);
        $t->same(null, $invalid['tabIndex']);
        $t->same(false, $invalid['tabIndexValid']);

        $t->same('<section accesskey="s x s" autofocus id="focus-region" tabindex="3"><button accesskey="k Enter" id="save" tabindex="-2">Save</button></section><p accesskey="wide key" id="invalid-focus" tabindex="bogus">No focus</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/focus-navigation-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html accesskey document conflicts for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<nav id="toolbar" accesskey="s n s"><button id="save" accesskey="s">Save</button>'
                . '<button id="next" accesskey="n">Next</button><button id="solo" accesskey="x">Solo</button></nav>'
                . '<p id="bad-keys" accesskey="Enter zz bad&lt;token">Bad</p>',
            'accesskey document conflict review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/accesskey-conflict-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $toolbar = $summary[0];
        $save = $toolbar['children'][0];
        $next = $toolbar['children'][1];
        $solo = $toolbar['children'][2];
        $bad = $summary[1];

        $t->same('document-accesskey-assignment-review', $toolbar['accessKeyReviewPolicy']);
        $t->same(['s', 'n', 's'], $toolbar['accessKeyTokens']);
        $t->same(['s', 'n'], $toolbar['accessKeys']);
        $t->same(['s'], $toolbar['duplicateAccessKeyTokens']);
        $t->same([], $toolbar['invalidAccessKeyTokens']);
        $t->same(true, $toolbar['accessKeyValid']);
        $t->same(4, $toolbar['accessKeyDocumentAssignmentCount']);
        $t->same(['s', 'n'], $toolbar['accessKeyConflictKeys']);
        $t->same(2, $toolbar['accessKeyConflictCount']);
        $t->same(true, $toolbar['accessKeyHasConflict']);
        $t->same('toolbar', $toolbar['accessKeyDocumentAssignments'][0]['id'] ?? null);
        $t->same('s', $toolbar['accessKeyDocumentAssignments'][0]['key'] ?? null);
        $t->same(true, $toolbar['accessKeyDocumentAssignments'][0]['current'] ?? null);
        $t->same('save', $toolbar['accessKeyDocumentAssignments'][2]['id'] ?? null);
        $t->same('s', $toolbar['accessKeyDocumentAssignments'][2]['key'] ?? null);
        $t->same(false, $toolbar['accessKeyDocumentAssignments'][2]['current'] ?? null);
        $t->same(4, count($toolbar['accessKeyConflicts']));

        $t->same(['s'], $save['accessKeys']);
        $t->same([], $save['duplicateAccessKeyTokens']);
        $t->same(2, $save['accessKeyDocumentAssignmentCount']);
        $t->same(['s'], $save['accessKeyConflictKeys']);
        $t->same(true, $save['accessKeyHasConflict']);
        $t->same('toolbar', $save['accessKeyDocumentAssignments'][0]['id'] ?? null);
        $t->same(false, $save['accessKeyDocumentAssignments'][0]['current'] ?? null);
        $t->same('save', $save['accessKeyDocumentAssignments'][1]['id'] ?? null);
        $t->same(true, $save['accessKeyDocumentAssignments'][1]['current'] ?? null);

        $t->same(['n'], $next['accessKeys']);
        $t->same(['n'], $next['accessKeyConflictKeys']);
        $t->same('toolbar', $next['accessKeyDocumentAssignments'][0]['id'] ?? null);
        $t->same('next', $next['accessKeyDocumentAssignments'][1]['id'] ?? null);
        $t->same(true, $next['accessKeyDocumentAssignments'][1]['current'] ?? null);

        $t->same(['x'], $solo['accessKeys']);
        $t->same(1, $solo['accessKeyDocumentAssignmentCount']);
        $t->same([], $solo['accessKeyConflictKeys']);
        $t->same(false, $solo['accessKeyHasConflict']);

        $t->same(['Enter', 'zz', 'bad<token'], $bad['accessKeyTokens']);
        $t->same([], $bad['accessKeys']);
        $t->same(['Enter', 'zz', 'bad<token'], $bad['invalidAccessKeyTokens']);
        $t->same([], $bad['duplicateAccessKeyTokens']);
        $t->same(false, $bad['accessKeyValid']);
        $t->same(0, $bad['accessKeyDocumentAssignmentCount']);
        $t->same(false, $bad['accessKeyHasConflict']);

        $t->same(
            '<nav accesskey="s n s" id="toolbar"><button accesskey="s" id="save">Save</button>'
                . '<button accesskey="n" id="next">Next</button><button accesskey="x" id="solo">Solo</button></nav>'
                . '<p accesskey="Enter zz bad&lt;token" id="bad-keys">Bad</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/accesskey-conflict-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html accesskey collision provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<nav id="toolbar" accesskey="s"><button id="save" accesskey="s k">Save</button><button id="send" accesskey="k">Send</button><a id="skip" href="#main" accesskey="s">Skip</a><button id="wide" accesskey="wide x">Wide</button><button id="solo" accesskey="z">Solo</button></nav>'
                . '<main id="main" accesskey="x">Main</main>',
            'accesskey collision review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/accesskey-collision-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $toolbar = $summary[0];
        $save = $toolbar['children'][0];
        $send = $toolbar['children'][1];
        $skip = $toolbar['children'][2];
        $wide = $toolbar['children'][3];
        $solo = $toolbar['children'][4];
        $main = $summary[1];

        $t->same('document-accesskey-assignment-review', $toolbar['accessKeyReviewPolicy']);
        $t->same('html-accesskey-collision-review', $toolbar['accessKeyCollisionReviewPolicy']);
        $t->same(['s'], $toolbar['accessKeyCollisionKeys']);
        $t->same(1, $toolbar['accessKeyCollisionCount']);
        $t->same(true, $toolbar['accessKeyHasCollision']);
        $t->same(['toolbar', 'save', 'skip'], $toolbar['accessKeyCollisions'][0]['candidateIds']);
        $t->same(0, $toolbar['accessKeyCollisions'][0]['currentIndex']);
        $t->same('save', $toolbar['accessKeyCollisions'][0]['candidates'][1]['id']);
        $t->same(false, $toolbar['accessKeyCollisions'][0]['candidates'][1]['current']);

        $t->same(['s', 'k'], $save['accessKeys']);
        $t->same(['s', 'k'], $save['accessKeyCollisionKeys']);
        $t->same(2, $save['accessKeyCollisionCount']);
        $t->same(['toolbar', 'save', 'skip'], $save['accessKeyCollisions'][0]['candidateIds']);
        $t->same(1, $save['accessKeyCollisions'][0]['currentIndex']);
        $t->same('k', $save['accessKeyCollisions'][1]['key']);
        $t->same(['save', 'send'], $save['accessKeyCollisions'][1]['candidateIds']);
        $t->same(0, $save['accessKeyCollisions'][1]['currentIndex']);

        $t->same(['k'], $send['accessKeyCollisionKeys']);
        $t->same(1, $send['accessKeyCollisionCount']);
        $t->same(1, $send['accessKeyCollisions'][0]['currentIndex']);
        $t->same('button', $send['accessKeyCollisions'][0]['candidates'][0]['tag']);
        $t->same('Save', $send['accessKeyCollisions'][0]['candidates'][0]['text']);

        $t->same(['s'], $skip['accessKeyCollisionKeys']);
        $t->same(2, $skip['accessKeyCollisions'][0]['currentIndex']);

        $t->same(['wide', 'x'], $wide['accessKeyTokens']);
        $t->same(['x'], $wide['accessKeys']);
        $t->same(['wide'], $wide['invalidAccessKeyTokens']);
        $t->same(false, $wide['accessKeyValid']);
        $t->same(['x'], $wide['accessKeyCollisionKeys']);
        $t->same(['wide', 'main'], $wide['accessKeyCollisions'][0]['candidateIds']);
        $t->same(false, $wide['accessKeyCollisions'][0]['candidates'][0]['accessKeyValid']);

        $t->same(['x'], $main['accessKeyCollisionKeys']);
        $t->same(1, $main['accessKeyCollisions'][0]['currentIndex']);
        $t->same('wide', $main['accessKeyCollisions'][0]['candidates'][0]['id']);
        $t->true(!array_key_exists('accessKeyCollisionReviewPolicy', $solo));

        $t->same(
            '<nav accesskey="s" id="toolbar"><button accesskey="s k" id="save">Save</button><button accesskey="k" id="send">Send</button><a accesskey="s" href="#main" id="skip">Skip</a><button accesskey="wide x" id="wide">Wide</button><button accesskey="z" id="solo">Solo</button></nav><main accesskey="x" id="main">Main</main>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/accesskey-collision-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html autofocus candidate conflicts for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="focus-form"><input id="title" name="title" value="Draft" autofocus><button id="save" disabled autofocus>Save</button></form>'
                . '<section id="panel" tabindex="-1" autofocus>Panel body</section>',
            'autofocus conflict review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/autofocus-conflict-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $title = $form['children'][0];
        $save = $form['children'][1];
        $panel = $summary[1];

        $t->same('document-autofocus-candidate-review', $title['autofocusReviewPolicy']);
        $t->same(3, $title['autofocusCandidateCount']);
        $t->same(0, $title['autofocusIndex']);
        $t->same(true, $title['autofocusFirst']);
        $t->same(true, $title['autofocusConflict']);
        $t->same(['multiple-autofocus-candidates'], $title['autofocusIssueCodes']);
        $t->same(['title', 'save', 'panel'], $title['autofocusCandidateIds']);
        $t->same('input', $title['autofocusFirstCandidate']['tag'] ?? null);
        $t->same('title', $title['autofocusFirstCandidate']['id'] ?? null);
        $t->same(true, $title['autofocusFirstCandidate']['current'] ?? null);
        $t->same('form-control', $title['autofocusCandidates'][0]['kind'] ?? null);
        $t->same('text', $title['autofocusCandidates'][0]['inputType'] ?? null);
        $t->same('title', $title['autofocusCandidates'][0]['controlName'] ?? null);
        $t->same('Draft', $title['autofocusCandidates'][0]['value'] ?? null);

        $t->same(1, $save['autofocusIndex']);
        $t->same(false, $save['autofocusFirst']);
        $t->same(true, $save['autofocusCandidates'][1]['current'] ?? null);
        $t->same('form-control', $save['autofocusCandidates'][1]['kind'] ?? null);
        $t->same('submit', $save['autofocusCandidates'][1]['buttonType'] ?? null);
        $t->same(true, $save['autofocusCandidates'][1]['effectiveDisabled'] ?? null);
        $t->same('Save', $save['autofocusCandidates'][1]['label'] ?? null);

        $t->same(2, $panel['autofocusIndex']);
        $t->same(false, $panel['autofocusFirst']);
        $t->same('tabindex', $panel['autofocusCandidates'][2]['kind'] ?? null);
        $t->same(true, $panel['autofocusCandidates'][2]['current'] ?? null);
        $t->same(-1, $panel['tabIndex']);
        $t->same(-1, $panel['autofocusCandidates'][2]['tabIndex'] ?? null);
        $t->same('Panel body', $panel['autofocusCandidates'][2]['text'] ?? null);
        $t->same(['multiple-autofocus-candidates'], $panel['autofocusIssueCodes']);

        $t->same('<form id="focus-form"><input autofocus id="title" name="title" value="Draft"><button autofocus disabled id="save">Save</button></form><section autofocus id="panel" tabindex="-1">Panel body</section>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/autofocus-conflict-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html autofocus document order for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="review-form"><input id="first" name="title" autofocus value="Title">'
                . '<button id="second" type="button" autofocus>Second</button></form>'
                . '<textarea id="third" name="body" autofocus>Body</textarea>',
            'autofocus document order review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/autofocus-order-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $first = $summary[0]['children'][0];
        $second = $summary[0]['children'][1];
        $third = $summary[1];

        $t->same('document-autofocus-candidate-review', $first['autofocusReviewPolicy']);
        $t->same(3, $first['autofocusCandidateCount']);
        $t->same(0, $first['autofocusIndex']);
        $t->same(true, $first['autofocusFirstCandidateSelected']);
        $t->same(false, $first['autofocusSuppressedByEarlierCandidate']);
        $t->same('first', $first['autofocusCurrentCandidate']['id'] ?? null);
        $t->same(null, $first['autofocusPreviousCandidate']);
        $t->same([], $first['autofocusOrderIssueCodes']);
        $t->same(['input', 'button', 'textarea'], $first['autofocusCandidateElementNames']);
        $t->same(['first', 'second', 'third'], $first['autofocusCandidateIds']);
        $t->same(true, $first['autofocusCandidates'][0]['current'] ?? null);
        $t->same(false, $first['autofocusCandidates'][1]['current'] ?? null);
        $t->same('first', $first['autofocusFirstCandidate']['id'] ?? null);

        $t->same(1, $second['autofocusIndex']);
        $t->same(false, $second['autofocusFirstCandidateSelected']);
        $t->same(true, $second['autofocusSuppressedByEarlierCandidate']);
        $t->same('second', $second['autofocusCurrentCandidate']['id'] ?? null);
        $t->same('first', $second['autofocusPreviousCandidate']['id'] ?? null);
        $t->same('input', $second['autofocusPreviousCandidate']['tag'] ?? null);
        $t->same(['autofocus-suppressed-by-earlier-candidate'], $second['autofocusOrderIssueCodes']);
        $t->same(true, $second['autofocusCandidates'][1]['current'] ?? null);
        $t->same('button', $second['autofocusCandidates'][1]['buttonType'] ?? null);
        $t->same('Second', $second['autofocusCandidates'][1]['text'] ?? null);

        $t->same(2, $third['autofocusIndex']);
        $t->same(true, $third['autofocusSuppressedByEarlierCandidate']);
        $t->same('third', $third['autofocusCurrentCandidate']['id'] ?? null);
        $t->same('second', $third['autofocusPreviousCandidate']['id'] ?? null);
        $t->same('button', $third['autofocusPreviousCandidate']['tag'] ?? null);
        $t->same(['autofocus-suppressed-by-earlier-candidate'], $third['autofocusOrderIssueCodes']);
        $t->same('form-control', $third['autofocusCandidates'][2]['kind'] ?? null);
        $t->same('Body', $third['autofocusCandidates'][2]['value'] ?? null);
        $t->same(['input', 'button', 'textarea'], $third['autofocusCandidateElementNames']);

        $t->same(
            '<form id="review-form"><input autofocus id="first" name="title" value="Title">'
                . '<button autofocus id="second" type="button">Second</button></form>'
                . '<textarea autofocus id="third" name="body">Body</textarea>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/autofocus-order-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html inert and custom element export attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="widget-host" inert part="card title card" exportparts="title:review-title, icon, bad:mapping:extra, invalid name:alias" slot="primary-panel" is="review-widget"><button part="action primary" slot="controls" inert>Save</button></section>'
                . '<p part="valid invalid=name" slot="bad slot" is="InvalidWidget">Fallback</p>',
            'inert custom element review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/custom-element-attributes-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $host = $summary[0];
        $button = $host['children'][0];
        $fallback = $summary[1];

        $t->same('widget-host', $host['elementId']);
        $t->same('', $host['inertRaw']);
        $t->same(true, $host['inert']);
        $t->same('primary-panel', $host['slotRaw']);
        $t->same('primary-panel', $host['slotName']);
        $t->same(true, $host['slotValid']);
        $t->same('card title card', $host['partRaw']);
        $t->same(['card', 'title', 'card'], $host['partTokens']);
        $t->same(['card', 'title'], $host['partNames']);
        $t->same([], $host['invalidPartTokens']);
        $t->same(true, $host['partValid']);
        $t->same('title:review-title, icon, bad:mapping:extra, invalid name:alias', $host['exportPartsRaw']);
        $t->same(['title', 'icon'], $host['exportPartNames']);
        $t->same(['review-title', 'icon'], $host['exportPartAliases']);
        $t->same(['bad:mapping:extra', 'invalid name:alias'], $host['invalidExportParts']);
        $t->same(false, $host['exportPartsValid']);
        $t->same([
            ['raw' => 'title:review-title', 'source' => 'title', 'alias' => 'review-title', 'renamed' => true, 'valid' => true],
            ['raw' => 'icon', 'source' => 'icon', 'alias' => 'icon', 'renamed' => false, 'valid' => true],
            ['raw' => 'bad:mapping:extra', 'source' => 'bad', 'alias' => 'mapping', 'renamed' => false, 'valid' => false],
            ['raw' => 'invalid name:alias', 'source' => 'invalid name', 'alias' => 'alias', 'renamed' => false, 'valid' => false],
        ], $host['exportParts']);
        $t->same('review-widget', $host['isRaw']);
        $t->same('review-widget', $host['customElementName']);
        $t->same(true, $host['customElementValid']);

        $t->same(true, $button['inert']);
        $t->same('controls', $button['slotName']);
        $t->same(['action', 'primary'], $button['partNames']);
        $t->same(true, $button['partValid']);

        $t->same('bad slot', $fallback['slotRaw']);
        $t->same('bad slot', $fallback['slotName']);
        $t->same(false, $fallback['slotValid']);
        $t->same(['valid', 'invalid=name'], $fallback['partTokens']);
        $t->same(['invalid=name'], $fallback['invalidPartTokens']);
        $t->same(false, $fallback['partValid']);
        $t->same('InvalidWidget', $fallback['isRaw']);
        $t->same('InvalidWidget', $fallback['customElementName']);
        $t->same(false, $fallback['customElementValid']);

        $t->same('<section exportparts="title:review-title, icon, bad:mapping:extra, invalid name:alias" id="widget-host" inert is="review-widget" part="card title card" slot="primary-panel"><button inert part="action primary" slot="controls">Save</button></section><p is="InvalidWidget" part="valid invalid=name" slot="bad slot">Fallback</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/custom-element-attributes-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html slot assignment and fallback review metadata' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="host"><slot name="headline"><strong>Fallback headline</strong></slot><slot name="actions"></slot><slot><em>Default fallback</em></slot><slot name="missing"><span>Missing fallback</span></slot><h2 id="title" slot="headline">Review <em>headline</em></h2><button id="save" slot="actions">Save</button><button id="cancel" slot="actions">Cancel</button><p id="default">Default body</p><p id="invalid-slot" slot="bad slot">Invalid</p></section>',
            'slot assignment review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/slot-assignment-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $host = $summary[0];
        $headline = $host['children'][0];
        $actions = $host['children'][1];
        $default = $host['children'][2];
        $missing = $host['children'][3];
        $title = $host['children'][4];
        $invalid = $host['children'][8];

        $t->same('host', $host['elementId']);
        $t->same('slot', $headline['name']);
        $t->same('slot', $headline['slotElement']);
        $t->same('flat-parent-slot-assignment-review', $headline['slotReviewPolicy']);
        $t->same('headline', $headline['slotElementNameRaw']);
        $t->same('headline', $headline['slotElementName']);
        $t->same(false, $headline['slotDefault']);
        $t->same(true, $headline['slotElementNameValid']);
        $t->same('section', $headline['slotAssignmentScope']);
        $t->same('host', $headline['slotAssignmentScopeId']);
        $t->same(1, $headline['slotAssignedElementCount']);
        $t->same(['h2'], $headline['slotAssignedElementNames']);
        $t->same(['title'], $headline['slotAssignedElementIds']);
        $t->same([
            'tag' => 'h2',
            'id' => 'title',
            'slotRaw' => 'headline',
            'slotName' => 'headline',
            'slotValid' => true,
            'text' => 'Review headline',
        ], $headline['slotAssignedElements'][0]);
        $t->same('Fallback headline', $headline['slotFallbackText']);
        $t->same(['strong'], $headline['slotFallbackElementNames']);
        $t->same(true, $headline['slotHasFallback']);
        $t->same(false, $headline['slotFallbackActive']);

        $t->same('actions', $actions['slotElementName']);
        $t->same(2, $actions['slotAssignedElementCount']);
        $t->same(['button', 'button'], $actions['slotAssignedElementNames']);
        $t->same(['save', 'cancel'], $actions['slotAssignedElementIds']);
        $t->same(false, $actions['slotHasFallback']);

        $t->same(null, $default['slotElementNameRaw']);
        $t->same('', $default['slotElementName']);
        $t->same(true, $default['slotDefault']);
        $t->same(1, $default['slotAssignedElementCount']);
        $t->same(['p'], $default['slotAssignedElementNames']);
        $t->same(['default'], $default['slotAssignedElementIds']);
        $t->same('Default body', $default['slotAssignedElements'][0]['text'] ?? null);
        $t->same('Default fallback', $default['slotFallbackText']);
        $t->same(false, $default['slotFallbackActive']);

        $t->same('missing', $missing['slotElementName']);
        $t->same(0, $missing['slotAssignedElementCount']);
        $t->same('Missing fallback', $missing['slotFallbackText']);
        $t->same(true, $missing['slotFallbackActive']);

        $t->same('headline', $title['slotRaw']);
        $t->same('headline', $title['slotName']);
        $t->same(true, $title['slotValid']);
        $t->same('bad slot', $invalid['slotRaw']);
        $t->same(false, $invalid['slotValid']);

        $t->same('<section id="host"><slot name="headline"><strong>Fallback headline</strong></slot><slot name="actions"></slot><slot><em>Default fallback</em></slot><slot name="missing"><span>Missing fallback</span></slot><h2 id="title" slot="headline">Review <em>headline</em></h2><button id="save" slot="actions">Save</button><button id="cancel" slot="actions">Cancel</button><p id="default">Default body</p><p id="invalid-slot" slot="bad slot">Invalid</p></section>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/slot-assignment-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html input hint attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="entry" autocapitalize="on"><input id="amount" inputmode="Decimal" enterkeyhint="Done" autocapitalize="characters">'
                . '<textarea id="message" inputmode="search" enterkeyhint="send" autocapitalize="off">Note</textarea></form>'
                . '<p id="fallback" inputmode="kana" enterkeyhint="compose" autocapitalize="maybe">Fallback</p>',
            'input hint review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/input-hints-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $input = $form['children'][0];
        $textarea = $form['children'][1];
        $fallback = $summary[1];

        $t->same('entry', $form['elementId']);
        $t->same('on', $form['autocapitalizeRaw']);
        $t->same('sentences', $form['autocapitalize']);
        $t->same(true, $form['autocapitalizeValid']);

        $t->same('input', $input['formControl']);
        $t->same('Decimal', $input['inputModeRaw']);
        $t->same('decimal', $input['inputMode']);
        $t->same(true, $input['inputModeValid']);
        $t->same('Done', $input['enterKeyHintRaw']);
        $t->same('done', $input['enterKeyHint']);
        $t->same(true, $input['enterKeyHintValid']);
        $t->same('characters', $input['autocapitalizeRaw']);
        $t->same('characters', $input['autocapitalize']);
        $t->same(true, $input['autocapitalizeValid']);

        $t->same('textarea', $textarea['formControl']);
        $t->same('search', $textarea['inputMode']);
        $t->same(true, $textarea['inputModeValid']);
        $t->same('send', $textarea['enterKeyHint']);
        $t->same(true, $textarea['enterKeyHintValid']);
        $t->same('none', $textarea['autocapitalize']);
        $t->same(true, $textarea['autocapitalizeValid']);

        $t->same('kana', $fallback['inputModeRaw']);
        $t->same(null, $fallback['inputMode']);
        $t->same(false, $fallback['inputModeValid']);
        $t->same('compose', $fallback['enterKeyHintRaw']);
        $t->same(null, $fallback['enterKeyHint']);
        $t->same(false, $fallback['enterKeyHintValid']);
        $t->same('maybe', $fallback['autocapitalizeRaw']);
        $t->same(null, $fallback['autocapitalize']);
        $t->same(false, $fallback['autocapitalizeValid']);

        $t->same('<form autocapitalize="on" id="entry"><input autocapitalize="characters" enterkeyhint="Done" id="amount" inputmode="Decimal"><textarea autocapitalize="off" enterkeyhint="send" id="message" inputmode="search">Note</textarea></form><p autocapitalize="maybe" enterkeyhint="compose" id="fallback" inputmode="kana">Fallback</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/input-hints-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html list marker and item ordinal metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<ol id="steps" start="3" reversed type="A"><li value="7">Inspect<li>Repair<ol start="-2" type="i"><li value="-1">Nested</ol></ol>'
                . '<ul id="bullets" type="square"><li>Loose</li></ul><menu id="actions"><li value="4">Action</li></menu>'
                . '<ol id="invalid" start="abc"><li value="bad">Invalid</li></ol>',
            'list metadata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $steps = $summary[0];
        $inspect = $steps['children'][0];
        $repair = $steps['children'][1];
        $nested = $repair['children'][1];
        $nestedItem = $nested['children'][0];
        $bullets = $summary[1];
        $loose = $bullets['children'][0];
        $menu = $summary[2];
        $action = $menu['children'][0];
        $invalid = $summary[3];
        $invalidItem = $invalid['children'][0];

        $t->same('ordered', $steps['list']);
        $t->same(true, $steps['reversed']);
        $t->same('3', $steps['startRaw']);
        $t->same(3, $steps['start']);
        $t->same('A', $steps['markerType']);
        $t->same(true, $inspect['listItem']);
        $t->same('7', $inspect['valueRaw']);
        $t->same(7, $inspect['value']);
        $t->same('ordered', $nested['list']);
        $t->same(false, $nested['reversed']);
        $t->same('-2', $nested['startRaw']);
        $t->same(-2, $nested['start']);
        $t->same('i', $nested['markerType']);
        $t->same('-1', $nestedItem['valueRaw']);
        $t->same(-1, $nestedItem['value']);
        $t->same('unordered', $bullets['list']);
        $t->same('square', $bullets['markerType']);
        $t->same(true, $loose['listItem']);
        $t->same(null, $loose['valueRaw']);
        $t->same(null, $loose['value']);
        $t->same('menu', $menu['list']);
        $t->same('4', $action['valueRaw']);
        $t->same(4, $action['value']);
        $t->same('ordered', $invalid['list']);
        $t->same('abc', $invalid['startRaw']);
        $t->same(1, $invalid['start']);
        $t->same('bad', $invalidItem['valueRaw']);
        $t->same(null, $invalidItem['value']);
        $t->same('<ol id="steps" reversed start="3" type="A"><li value="7">Inspect</li><li>Repair<ol start="-2" type="i"><li value="-1">Nested</li></ol></li></ol><ul id="bullets" type="square"><li>Loose</li></ul><menu id="actions"><li value="4">Action</li></menu><ol id="invalid" start="abc"><li value="bad">Invalid</li></ol>', $html);
    },
    'summarizes html menu command controls for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<menu id="packet-actions" type="toolbar"><li><button id="open-panel" type="button" commandfor="review-panel" command="show-popover">Open panel</button></li>'
                . '<li><input type="submit" name="decision" value="Approve"></li><li><button disabled>Publish</button></li><li><span>Informational note</span></li></menu>'
                . '<aside id="review-panel" popover="manual">Panel</aside>',
            'menu command review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/menu-command-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $menu = $summary[0];
        $open = $menu['menuCommandControls'][0];
        $approve = $menu['menuCommandControls'][1];
        $publish = $menu['menuCommandControls'][2];

        $t->same('menu', $menu['list']);
        $t->same('toolbar', $menu['markerType']);
        $t->same('command-list', $menu['menuReview']);
        $t->same(4, $menu['menuItemCount']);
        $t->same(3, $menu['menuCommandItemCount']);
        $t->same(3, $menu['menuCommandControlCount']);
        $t->same(['Open panel', 'Approve', 'Publish'], $menu['menuCommandLabels']);
        $t->same('Open panel', $menu['menuItems'][0]['commandLabels'][0] ?? null);
        $t->same(0, $menu['menuItems'][3]['commandControlCount']);

        $t->same('button', $open['tag']);
        $t->same('button', $open['type']);
        $t->same('show-popover', $open['command']);
        $t->same('review-panel', $open['commandFor']);
        $t->same(true, $open['commandInvokesTarget']);
        $t->same('popover', $open['commandTargetKind']);
        $t->same('manual', $open['commandTarget']['popoverState'] ?? null);
        $t->same(false, $open['submitButton']);

        $t->same('input', $approve['tag']);
        $t->same('submit', $approve['type']);
        $t->same('decision', $approve['name']);
        $t->same('Approve', $approve['label']);
        $t->same(true, $approve['submitButton']);

        $t->same(true, $publish['disabled']);
        $t->same(true, $publish['effectiveDisabled']);
        $t->same(true, $publish['submitButton']);
        $t->same('<menu id="packet-actions" type="toolbar"><li><button command="show-popover" commandfor="review-panel" id="open-panel" type="button">Open panel</button></li><li><input name="decision" type="submit" value="Approve"></li><li><button disabled>Publish</button></li><li><span>Informational note</span></li></menu><aside id="review-panel" popover="manual">Panel</aside>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/menu-command-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html definition list term and description groups for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<dl id="glossary"><dt>Term <em>one</em></dt><dt>Alias</dt><dd>Definition <strong>primary</strong></dd><dd>Secondary note</dd><dt>Next</dt><dd><p>Nested text</p><dl><dt>Inner</dt><dd>Inside</dd></dl></dd></dl>'
                . '<dl id="orphan"><dd>Leading definition</dd><dt>Recovered term</dt><dd>Recovered body</dd></dl>',
            'definition list review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/definition-list-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $glossary = $summary[0];
        $term = $glossary['children'][0];
        $alias = $glossary['children'][1];
        $definition = $glossary['children'][2];
        $nestedDefinition = $glossary['children'][5];
        $nestedList = $nestedDefinition['children'][1];
        $orphan = $summary[1];

        $t->same('dl', $glossary['name']);
        $t->same('dl', $glossary['definitionList']);
        $t->same(3, $glossary['termCount']);
        $t->same(3, $glossary['definitionCount']);
        $t->same(2, $glossary['itemCount']);
        $t->same(['Term one', 'Alias', 'Next'], $glossary['terms']);
        $t->same(['Definition primary', 'Secondary note', 'Nested textInnerInside'], $glossary['definitions']);
        $t->same(['Term one', 'Alias'], $glossary['items'][0]['terms']);
        $t->same(['Definition primary', 'Secondary note'], $glossary['items'][0]['definitions']);
        $t->same(2, $glossary['items'][0]['termCount']);
        $t->same(2, $glossary['items'][0]['definitionCount']);
        $t->same(['Next'], $glossary['items'][1]['terms']);
        $t->same(['Nested textInnerInside'], $glossary['items'][1]['definitions']);

        $t->same('dt', $term['name']);
        $t->same('term', $term['definitionListPart']);
        $t->same('Term one', $term['termText']);
        $t->same('Alias', $alias['termText']);
        $t->same('dd', $definition['name']);
        $t->same('definition', $definition['definitionListPart']);
        $t->same('Definition primary', $definition['definitionText']);
        $t->same('dl', $nestedList['definitionList']);
        $t->same(['Inner'], $nestedList['terms']);
        $t->same(['Inside'], $nestedList['definitions']);

        $t->same('dl', $orphan['definitionList']);
        $t->same(2, $orphan['itemCount']);
        $t->same([], $orphan['items'][0]['terms']);
        $t->same(['Leading definition'], $orphan['items'][0]['definitions']);
        $t->same(['Recovered term'], $orphan['items'][1]['terms']);
        $t->same(['Recovered body'], $orphan['items'][1]['definitions']);

        $t->same('<dl id="glossary"><dt>Term <em>one</em></dt><dt>Alias</dt><dd>Definition <strong>primary</strong></dd><dd>Secondary note</dd><dt>Next</dt><dd><p>Nested text</p><dl><dt>Inner</dt><dd>Inside</dd></dl></dd></dl><dl id="orphan"><dd>Leading definition</dd><dt>Recovered term</dt><dd>Recovered body</dd></dl>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/definition-list-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html ordered list effective ordinal provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<ol id="reverse" reversed><li>Review</li><li value="9">Patch</li><li>Verify</li></ol>'
                . '<ol id="forward" start="4"><li>Draft</li><li value="-2">Pinned</li><li>Next</li></ol>'
                . '<ul id="plain"><li value="5">Loose</li></ul>',
            'ordered list effective ordinal review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/list-ordinal-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $reverse = $summary[0];
        $reverseReview = $reverse['children'][0];
        $reversePatch = $reverse['children'][1];
        $reverseVerify = $reverse['children'][2];
        $forward = $summary[1];
        $forwardDraft = $forward['children'][0];
        $forwardPinned = $forward['children'][1];
        $forwardNext = $forward['children'][2];
        $plainLoose = $summary[2]['children'][0];

        $t->same('ordered', $reverse['list']);
        $t->same(true, $reverse['reversed']);
        $t->same(3, $reverseReview['listOrdinal']);
        $t->same('reversed-count', $reverseReview['listOrdinalSource']);
        $t->same('9', $reversePatch['valueRaw']);
        $t->same(9, $reversePatch['listOrdinal']);
        $t->same('value-attribute', $reversePatch['listOrdinalSource']);
        $t->same(8, $reverseVerify['listOrdinal']);
        $t->same('previous-value', $reverseVerify['listOrdinalSource']);
        $t->same('ordered', $forward['list']);
        $t->same(4, $forward['start']);
        $t->same(4, $forwardDraft['listOrdinal']);
        $t->same('start-attribute', $forwardDraft['listOrdinalSource']);
        $t->same(-2, $forwardPinned['listOrdinal']);
        $t->same('value-attribute', $forwardPinned['listOrdinalSource']);
        $t->same(-1, $forwardNext['listOrdinal']);
        $t->same('previous-value', $forwardNext['listOrdinalSource']);
        $t->same('unordered', $summary[2]['list']);
        $t->same(null, $plainLoose['listOrdinal']);
        $t->same(null, $plainLoose['listOrdinalSource']);
        $t->same('<ol id="reverse" reversed><li>Review</li><li value="9">Patch</li><li>Verify</li></ol><ol id="forward" start="4"><li>Draft</li><li value="-2">Pinned</li><li>Next</li></ol><ul id="plain"><li value="5">Loose</li></ul>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/list-ordinal-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html heading and sectioning outline metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="story"><header><h1>Primary <em>Title</em></h1></header><section id="chapter"><h2>Chapter</h2><p>Body</p></section></article>'
                . '<nav aria-label="Contents"><div><h3>Navigation</h3></div><a href="#chapter">Chapter</a></nav>'
                . '<aside id="notes"><section id="nested-note"><h4>Nested note</h4></section></aside>'
                . '<main id="main"><p>No title</p></main>',
            'outline review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/outline-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $articleHeading = $article['children'][0]['children'][0];
        $section = $article['children'][1];
        $sectionHeading = $section['children'][0];
        $nav = $summary[1];
        $navHeading = $nav['children'][0]['children'][0];
        $aside = $summary[2];
        $nestedSection = $aside['children'][0];
        $main = $summary[3];

        $t->same('article', $article['name']);
        $t->same('article', $article['documentOutline']);
        $t->same('article', $article['outlineRoot']);
        $t->same('Primary Title', $article['sectionHeadingText']);
        $t->same('h1', $article['sectionHeadingTag']);
        $t->same(1, $article['sectionHeadingLevel']);
        $t->same('heading', $articleHeading['documentOutline']);
        $t->same(true, $articleHeading['heading']);
        $t->same('h1', $articleHeading['headingTag']);
        $t->same(1, $articleHeading['headingLevel']);
        $t->same('Primary Title', $articleHeading['headingText']);

        $t->same('section', $section['documentOutline']);
        $t->same('section', $section['outlineRoot']);
        $t->same('Chapter', $section['sectionHeadingText']);
        $t->same('h2', $section['sectionHeadingTag']);
        $t->same(2, $section['sectionHeadingLevel']);
        $t->same('heading', $sectionHeading['documentOutline']);
        $t->same(2, $sectionHeading['headingLevel']);
        $t->same('Chapter', $sectionHeading['headingText']);

        $t->same('navigation', $nav['documentOutline']);
        $t->same('nav', $nav['outlineRoot']);
        $t->same('Navigation', $nav['sectionHeadingText']);
        $t->same('h3', $nav['sectionHeadingTag']);
        $t->same(3, $nav['sectionHeadingLevel']);
        $t->same(['aria-label' => 'Contents'], $nav['ariaAttributes']);
        $t->same('heading', $navHeading['documentOutline']);
        $t->same(3, $navHeading['headingLevel']);

        $t->same('aside', $aside['documentOutline']);
        $t->same('aside', $aside['outlineRoot']);
        $t->same(null, $aside['sectionHeadingText']);
        $t->same(null, $aside['sectionHeadingTag']);
        $t->same(null, $aside['sectionHeadingLevel']);
        $t->same('section', $nestedSection['documentOutline']);
        $t->same('Nested note', $nestedSection['sectionHeadingText']);
        $t->same(4, $nestedSection['sectionHeadingLevel']);

        $t->same('main', $main['documentOutline']);
        $t->same('main', $main['outlineRoot']);
        $t->same(null, $main['sectionHeadingText']);
        $t->same(null, $main['sectionHeadingLevel']);
        $t->same('<article id="story"><header><h1>Primary <em>Title</em></h1></header><section id="chapter"><h2>Chapter</h2><p>Body</p></section></article><nav aria-label="Contents"><div><h3>Navigation</h3></div><a href="#chapter">Chapter</a></nav><aside id="notes"><section id="nested-note"><h4>Nested note</h4></section></aside><main id="main"><p>No title</p></main>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/outline-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html hgroup heading metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="packet"><hgroup id="title-group"><p class="eyebrow">Review packet</p><h2>Draft ingestion summary</h2><h1>Migration <em>Plan</em></h1><p>ODT and HTML checkpoints</p></hgroup><p>Body</p></section>',
            'hgroup outline review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hgroup-outline-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $section = $summary[0];
        $hgroup = $section['children'][0];
        $secondaryHeading = $hgroup['children'][1];
        $mainHeading = $hgroup['children'][2];

        $t->same('section', $section['documentOutline']);
        $t->same('Migration Plan', $section['sectionHeadingText']);
        $t->same('h1', $section['sectionHeadingTag']);
        $t->same(1, $section['sectionHeadingLevel']);

        $t->same('hgroup', $hgroup['name']);
        $t->same('heading-group', $hgroup['documentOutline']);
        $t->same('hgroup', $hgroup['headingGroup']);
        $t->same('Review packetDraft ingestion summaryMigration PlanODT and HTML checkpoints', $hgroup['headingGroupText']);
        $t->same('Migration Plan', $hgroup['headingGroupHeadingText']);
        $t->same('h1', $hgroup['headingGroupHeadingTag']);
        $t->same(1, $hgroup['headingGroupHeadingLevel']);
        $t->same(2, $hgroup['headingGroupHeadingCount']);
        $t->same(['Draft ingestion summary', 'Migration Plan'], $hgroup['headingGroupHeadingTexts']);
        $t->same([
            ['tag' => 'h2', 'level' => 2, 'text' => 'Draft ingestion summary'],
            ['tag' => 'h1', 'level' => 1, 'text' => 'Migration Plan'],
        ], $hgroup['headingGroupHeadings']);
        $t->same(2, $hgroup['headingGroupSubtitleCount']);
        $t->same(['Review packet', 'ODT and HTML checkpoints'], $hgroup['headingGroupSubtitleTexts']);

        $t->same('heading', $secondaryHeading['documentOutline']);
        $t->same(2, $secondaryHeading['headingLevel']);
        $t->same('heading', $mainHeading['documentOutline']);
        $t->same(1, $mainHeading['headingLevel']);
        $t->same('<section id="packet"><hgroup id="title-group"><p class="eyebrow">Review packet</p><h2>Draft ingestion summary</h2><h1>Migration <em>Plan</em></h1><p>ODT and HTML checkpoints</p></hgroup><p>Body</p></section>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/hgroup-outline-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html search and address landmark metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<search id="site-search" aria-label="Site search"><form id="search-form" role="search" action="/find" method="post">'
                . '<label for="q">Search terms</label><input id="q" name="q" type="search" value="pandoc">'
                . '<button name="go" value="1">Go</button></form></search>'
                . '<address id="contact">Maintained by <a href="mailto:docs@example.test" rel="author">Docs Team</a> '
                . '<a href="/legal" rel="help external">Legal</a></address>',
            'search and address landmark review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/search-address-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $search = $summary[0];
        $form = $search['searchForms'][0];
        $input = $search['searchControls'][0];
        $button = $search['searchControls'][1];
        $address = $summary[1];
        $email = $address['contactLinks'][0];
        $legal = $address['contactLinks'][1];

        $t->same('search', $search['name']);
        $t->same('search', $search['landmark']);
        $t->same('search', $search['searchRegion']);
        $t->same('Search termsGo', $search['searchText']);
        $t->same(1, $search['searchFormCount']);
        $t->same('search-form', $form['id']);
        $t->same('/find', $form['action']);
        $t->same('post', $form['method']);
        $t->same('search', $form['role']);
        $t->same(2, count($form['controls']));
        $t->same('input', $input['control']);
        $t->same('q', $input['id']);
        $t->same('q', $input['controlName']);
        $t->same('search', $input['type']);
        $t->same('pandoc', $input['value']);
        $t->same(['Search terms'], $input['label']);
        $t->same('button', $button['control']);
        $t->same('go', $button['controlName']);
        $t->same('submit', $button['type']);
        $t->same('Go', $button['text']);
        $t->same('address', $address['name']);
        $t->same('address', $address['contactInfo']);
        $t->same('Maintained by Docs Team Legal', $address['contactText']);
        $t->same(2, $address['contactLinkCount']);
        $t->same('mailto:docs@example.test', $email['href']);
        $t->same('Docs Team', $email['label']);
        $t->same(['author'], $email['relTokens']);
        $t->same('/legal', $legal['href']);
        $t->same(['help', 'external'], $legal['relTokens']);
        $t->same(['mailto:docs@example.test', '/legal'], $address['contactHrefs']);
        $t->same(['mailto:docs@example.test'], $address['contactEmailHrefs']);
        $t->same('<search aria-label="Site search" id="site-search"><form action="/find" id="search-form" method="post" role="search"><label for="q">Search terms</label><input id="q" name="q" type="search" value="pandoc"><button name="go" value="1">Go</button></form></search><address id="contact">Maintained by <a href="mailto:docs@example.test" rel="author">Docs Team</a> <a href="/legal" rel="help external">Legal</a></address>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/search-address-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html text-level semantic elements for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><abbr title="HyperText Markup Language">HTML</abbr> <dfn title="Review term">term</dfn> <mark>note</mark> '
                . '<code>printf()</code> <kbd>Ctrl+S</kbd> <samp>saved</samp> <var>x</var> <small>fine print</small> '
                . '<sub>2</sub><sup>n</sup> <bdi dir="auto">Review ID</bdi> <bdo dir="rtl">source</bdo> <u>spelling</u> <s>old</s></p>',
            'text-level semantic review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/text-semantics-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $abbr = $paragraph['children'][0];
        $dfn = $paragraph['children'][2];
        $mark = $paragraph['children'][4];
        $code = $paragraph['children'][6];
        $kbd = $paragraph['children'][8];
        $samp = $paragraph['children'][10];
        $var = $paragraph['children'][12];
        $small = $paragraph['children'][14];
        $sub = $paragraph['children'][16];
        $sup = $paragraph['children'][17];
        $bdi = $paragraph['children'][19];
        $bdo = $paragraph['children'][21];
        $u = $paragraph['children'][23];
        $s = $paragraph['children'][25];

        $t->same('p', $paragraph['name']);
        $t->same('HTML term note printf() Ctrl+S saved x fine print 2n Review ID source spelling old', $paragraph['text']);
        $t->same('abbreviation', $abbr['textSemantic']);
        $t->same('HyperText Markup Language', $abbr['abbreviationTitle']);
        $t->same('definition', $dfn['textSemantic']);
        $t->same('term', $dfn['definitionTerm']);
        $t->same('Review term', $dfn['definitionTitle']);
        $t->same('mark', $mark['textSemantic']);
        $t->same('code', $code['textSemantic']);
        $t->same('keyboard-input', $kbd['textSemantic']);
        $t->same('sample-output', $samp['textSemantic']);
        $t->same('variable', $var['textSemantic']);
        $t->same('side-comment', $small['textSemantic']);
        $t->same('subscript', $sub['textSemantic']);
        $t->same('superscript', $sup['textSemantic']);
        $t->same('bidirectional-isolate', $bdi['textSemantic']);
        $t->same('auto', $bdi['textDirection']);
        $t->same('bidirectional-override', $bdo['textSemantic']);
        $t->same('rtl', $bdo['textDirection']);
        $t->same('unarticulated-annotation', $u['textSemantic']);
        $t->same('struck-text', $s['textSemantic']);
        $t->same('<p><abbr title="HyperText Markup Language">HTML</abbr> <dfn title="Review term">term</dfn> <mark>note</mark> <code>printf()</code> <kbd>Ctrl+S</kbd> <samp>saved</samp> <var>x</var> <small>fine print</small> <sub>2</sub><sup>n</sup> <bdi dir="auto">Review ID</bdi> <bdo dir="rtl">source</bdo> <u>spelling</u> <s>old</s></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/text-semantics-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html abbreviation and definition term provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<dfn id="title" title=" HyperText Markup Language ">HTML</dfn>'
                . '<dfn id="abbr"><abbr title="Cascading Style Sheets">CSS</abbr></dfn>'
                . '<dfn id="text"><abbr>DOM</abbr></dfn>'
                . '<abbr id="missing">API</abbr>'
                . '<abbr id="empty" title="">ID</abbr>'
                . '<abbr id="expanded" title="Application Programming Interface">API</abbr>',
            'definition term provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/definition-term-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $titleDefinition = $summary[0];
        $abbreviationDefinition = $summary[1];
        $textDefinition = $summary[2];
        $missingTitle = $summary[3];
        $emptyTitle = $summary[4];
        $expanded = $summary[5];

        $t->same('html-definition-term-source-review', $titleDefinition['definitionReviewPolicy']);
        $t->same('HTML', $titleDefinition['definitionTerm']);
        $t->same(' HyperText Markup Language ', $titleDefinition['definitionTitle']);
        $t->same('HyperText Markup Language', $titleDefinition['definitionResolvedTerm']);
        $t->same('title-attribute', $titleDefinition['definitionTermSource']);
        $t->same(true, $titleDefinition['definitionTitlePresent']);
        $t->same(false, $titleDefinition['definitionTitleEmpty']);
        $t->same(null, $titleDefinition['definitionChildAbbreviationTitle']);
        $t->same([], $titleDefinition['definitionIssueCodes']);

        $t->same('Cascading Style Sheets', $abbreviationDefinition['definitionResolvedTerm']);
        $t->same('child-abbr-title', $abbreviationDefinition['definitionTermSource']);
        $t->same(false, $abbreviationDefinition['definitionTitlePresent']);
        $t->same('CSS', $abbreviationDefinition['definitionChildAbbreviationText']);
        $t->same('Cascading Style Sheets', $abbreviationDefinition['definitionChildAbbreviationTitle']);
        $t->same([], $abbreviationDefinition['definitionIssueCodes']);

        $t->same('DOM', $textDefinition['definitionResolvedTerm']);
        $t->same('text-content', $textDefinition['definitionTermSource']);
        $t->same('DOM', $textDefinition['definitionChildAbbreviationText']);
        $t->same(null, $textDefinition['definitionChildAbbreviationTitle']);
        $t->same([], $textDefinition['definitionIssueCodes']);

        $t->same('html-abbreviation-title-review', $missingTitle['abbreviationReviewPolicy']);
        $t->same('API', $missingTitle['abbreviationText']);
        $t->same(false, $missingTitle['abbreviationTitlePresent']);
        $t->same(null, $missingTitle['abbreviationExpansion']);
        $t->same(['missing-abbreviation-title'], $missingTitle['abbreviationIssueCodes']);

        $t->same(true, $emptyTitle['abbreviationTitlePresent']);
        $t->same(true, $emptyTitle['abbreviationTitleEmpty']);
        $t->same(null, $emptyTitle['abbreviationExpansion']);
        $t->same(['empty-abbreviation-title'], $emptyTitle['abbreviationIssueCodes']);

        $t->same('Application Programming Interface', $expanded['abbreviationExpansion']);
        $t->same(false, $expanded['abbreviationTitleMatchesText']);
        $t->same([], $expanded['abbreviationIssueCodes']);

        $t->same('<dfn id="title" title=" HyperText Markup Language ">HTML</dfn><dfn id="abbr"><abbr title="Cascading Style Sheets">CSS</abbr></dfn><dfn id="text"><abbr>DOM</abbr></dfn><abbr id="missing">API</abbr><abbr id="empty" title="">ID</abbr><abbr id="expanded" title="Application Programming Interface">API</abbr>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/definition-term-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html bdi default direction isolation for reviewer handoff' => static function (TestRunner $t): void {
        $rtl = "\u{05E9}\u{05DC}\u{05D5}\u{05DD}";
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="rtl-shell" dir="rtl"><bdi id="sku">SKU-42</bdi><bdi id="hebrew">&#x05E9;&#x05DC;&#x05D5;&#x05DD;</bdi><bdi id="neutral">123</bdi><bdi id="explicit" dir="ltr">Explicit</bdi><span id="plain">Plain</span></section>',
            'bdi default direction isolation review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/bdi-default-direction-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $section = $summary[0];
        $sku = $section['children'][0];
        $hebrew = $section['children'][1];
        $neutral = $section['children'][2];
        $explicit = $section['children'][3];
        $plain = $section['children'][4];

        $t->same('rtl', $section['effectiveDirection']);
        $t->same('rtl', $section['effectiveDirectionResolved']);
        $t->same(false, $section['directionInherited']);
        $t->same('self-dir', $section['directionSource']);
        $t->same('bidirectional-isolate', $sku['textSemantic']);
        $t->same('auto', $sku['textDirection']);
        $t->same(true, $sku['textDirectionImplicitDefault']);
        $t->same('auto', $sku['effectiveDirectionRaw']);
        $t->same('auto', $sku['effectiveDirection']);
        $t->same('ltr', $sku['effectiveDirectionResolved']);
        $t->same(false, $sku['directionInherited']);
        $t->same('implicit-bdi-dir-auto', $sku['directionSource']);
        $t->same('bdi', $sku['directionSourceElement']);
        $t->same('sku', $sku['directionSourceElementId']);
        $t->same(true, $sku['directionImplicitDefault']);
        $t->same('bdi', $sku['directionImplicitDefaultElement']);
        $t->same('html-bdi-default-direction-review', $sku['bdiDirectionReviewPolicy']);
        $t->same(true, $sku['bdiDirectionDefaulted']);
        $t->same('html-dir-auto-first-strong-review', $sku['directionAutoReviewPolicy']);
        $t->same('first-strong-ltr', $sku['directionAutoState']);
        $t->same('ltr', $sku['directionAutoResolvedDirection']);
        $t->same('ltr', $sku['directionAutoFirstStrongDirection']);
        $t->same('S', $sku['directionAutoFirstStrongCharacter']);
        $t->same(0, $sku['directionAutoFirstStrongIndex']);
        $t->same(6, $sku['directionAutoTextLength']);
        $t->same(false, $sku['directionAutoInherited']);
        $t->same('html-dir-auto-first-strong-character-review', $sku['dirAutoReviewPolicy']);
        $t->same('ltr', $sku['dirAutoResolvedDirection']);
        $t->same(true, $sku['dirAutoResolved']);
        $t->same(false, $sku['dirAutoNeutral']);
        $t->same(false, $sku['dirAutoInherited']);
        $t->same('bdi', $sku['dirAutoSourceElement']);
        $t->same('sku', $sku['dirAutoSourceElementId']);
        $t->same('S', $sku['dirAutoFirstStrongCharacter']);
        $t->same('L', $sku['dirAutoFirstStrongBidiClass']);
        $t->same(0, $sku['dirAutoFirstStrongCharacterOffset']);
        $t->same(0, $sku['dirAutoFirstStrongByteOffset']);

        $t->same($rtl, $hebrew['text']);
        $t->same('auto', $hebrew['effectiveDirection']);
        $t->same('rtl', $hebrew['effectiveDirectionResolved']);
        $t->same('implicit-bdi-dir-auto', $hebrew['directionSource']);
        $t->same(true, $hebrew['directionImplicitDefault']);
        $t->same('html-bdi-default-direction-review', $hebrew['bdiDirectionReviewPolicy']);
        $t->same(true, $hebrew['bdiDirectionDefaulted']);
        $t->same('first-strong-rtl', $hebrew['directionAutoState']);
        $t->same('rtl', $hebrew['directionAutoResolvedDirection']);
        $t->same('rtl', $hebrew['directionAutoFirstStrongDirection']);
        $t->same("\u{05E9}", $hebrew['directionAutoFirstStrongCharacter']);
        $t->same(0, $hebrew['directionAutoFirstStrongIndex']);
        $t->same(false, $hebrew['directionAutoInherited']);
        $t->same('rtl', $hebrew['dirAutoResolvedDirection']);
        $t->same(true, $hebrew['dirAutoResolved']);
        $t->same(false, $hebrew['dirAutoNeutral']);
        $t->same(false, $hebrew['dirAutoInherited']);
        $t->same('bdi', $hebrew['dirAutoSourceElement']);
        $t->same('hebrew', $hebrew['dirAutoSourceElementId']);
        $t->same("\u{05E9}", $hebrew['dirAutoFirstStrongCharacter']);
        $t->same('R', $hebrew['dirAutoFirstStrongBidiClass']);
        $t->same(0, $hebrew['dirAutoFirstStrongCharacterOffset']);
        $t->same(0, $hebrew['dirAutoFirstStrongByteOffset']);

        $t->same('auto', $neutral['effectiveDirection']);
        $t->same(null, $neutral['effectiveDirectionResolved']);
        $t->same('implicit-bdi-dir-auto', $neutral['directionSource']);
        $t->same(true, $neutral['directionImplicitDefault']);
        $t->same('html-bdi-default-direction-review', $neutral['bdiDirectionReviewPolicy']);
        $t->same(true, $neutral['bdiDirectionDefaulted']);
        $t->same('no-strong-character', $neutral['directionAutoState']);
        $t->same('ltr', $neutral['directionAutoResolvedDirection']);
        $t->same(null, $neutral['directionAutoFirstStrongDirection']);
        $t->same(null, $neutral['directionAutoFirstStrongCharacter']);
        $t->same(null, $neutral['directionAutoFirstStrongIndex']);
        $t->same(null, $neutral['dirAutoResolvedDirection']);
        $t->same(false, $neutral['dirAutoResolved']);
        $t->same(true, $neutral['dirAutoNeutral']);
        $t->same(false, $neutral['dirAutoInherited']);
        $t->same('neutral', $neutral['dirAutoSourceElementId']);
        $t->same(null, $neutral['dirAutoFirstStrongCharacter']);
        $t->same(null, $neutral['dirAutoFirstStrongBidiClass']);
        $t->same(null, $neutral['dirAutoFirstStrongCharacterOffset']);
        $t->same(null, $neutral['dirAutoFirstStrongByteOffset']);

        $t->same('ltr', $explicit['textDirection']);
        $t->same('ltr', $explicit['effectiveDirectionRaw']);
        $t->same('ltr', $explicit['effectiveDirection']);
        $t->same('ltr', $explicit['effectiveDirectionResolved']);
        $t->same('self-dir', $explicit['directionSource']);
        $t->same(false, array_key_exists('bdiDirectionDefaulted', $explicit));
        $t->same(false, array_key_exists('dirAutoReviewPolicy', $explicit));

        $t->same('rtl', $plain['effectiveDirection']);
        $t->same('rtl', $plain['effectiveDirectionResolved']);
        $t->same(true, $plain['directionInherited']);
        $t->same('ancestor-dir', $plain['directionSource']);
        $t->same('rtl-shell', $plain['directionSourceElementId']);
        $t->same('<section dir="rtl" id="rtl-shell"><bdi id="sku">SKU-42</bdi><bdi id="hebrew">' . $rtl . '</bdi><bdi id="neutral">123</bdi><bdi dir="ltr" id="explicit">Explicit</bdi><span id="plain">Plain</span></section>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/bdi-default-direction-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html emphasis and importance semantics for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><em>Stress</em><strong>Important</strong><b>Keyword</b><i>Taxon</i></p>',
            'emphasis importance semantic review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/emphasis-semantics-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $emphasis = $paragraph['children'][0];
        $strong = $paragraph['children'][1];
        $attention = $paragraph['children'][2];
        $offset = $paragraph['children'][3];

        $t->same('p', $paragraph['name']);
        $t->same('StressImportantKeywordTaxon', $paragraph['text']);
        $t->same('em', $emphasis['semanticTag']);
        $t->same('stress-emphasis', $emphasis['textSemantic']);
        $t->same('Stress', $emphasis['semanticText']);
        $t->same('strong', $strong['semanticTag']);
        $t->same('strong-importance', $strong['textSemantic']);
        $t->same('Important', $strong['semanticText']);
        $t->same('b', $attention['semanticTag']);
        $t->same('bring-attention', $attention['textSemantic']);
        $t->same('Keyword', $attention['semanticText']);
        $t->same('i', $offset['semanticTag']);
        $t->same('idiomatic-offset', $offset['textSemantic']);
        $t->same('Taxon', $offset['semanticText']);
        $t->same('<p><em>Stress</em><strong>Important</strong><b>Keyword</b><i>Taxon</i></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/emphasis-semantics-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html ruby annotation provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><ruby id="term">base<rp>(</rp><rt>annotation</rt><rp>)</rp><rtc><rt>alternate</rt><rt>source</rt></rtc><rb>tail</rb><rt>tail-note</rt></ruby></p>',
            'ruby annotation review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/ruby-annotations-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $ruby = $paragraph['children'][0];
        $fallbackOpen = $ruby['children'][1];
        $firstAnnotation = $ruby['children'][2];
        $fallbackClose = $ruby['children'][3];
        $container = $ruby['children'][4];
        $containerAnnotation = $container['children'][0];
        $base = $ruby['children'][5];
        $tailAnnotation = $ruby['children'][6];

        $t->same('p', $paragraph['name']);
        $t->same('base(annotation)alternatesourcetailtail-note', $paragraph['text']);
        $t->same('ruby', $ruby['name']);
        $t->same('ruby', $ruby['ruby']);
        $t->same('term', $ruby['elementId']);
        $t->same('base(annotation)alternatesourcetailtail-note', $ruby['rubyText']);
        $t->same(['base', 'tail'], $ruby['rubyBaseTexts']);
        $t->same(2, $ruby['rubyBaseCount']);
        $t->same(['annotation', 'alternate', 'source', 'tail-note'], $ruby['rubyAnnotationTexts']);
        $t->same(4, $ruby['rubyAnnotationCount']);
        $t->same([
            ['container' => null, 'text' => 'annotation'],
            ['container' => 'rtc', 'text' => 'alternate'],
            ['container' => 'rtc', 'text' => 'source'],
            ['container' => null, 'text' => 'tail-note'],
        ], $ruby['rubyAnnotations']);
        $t->same(['(', ')'], $ruby['rubyFallbackTexts']);
        $t->same(2, $ruby['rubyFallbackCount']);

        $t->same('fallback-parenthesis', $fallbackOpen['rubyPart']);
        $t->same('(', $fallbackOpen['rubyFallbackText']);
        $t->same('annotation', $firstAnnotation['rubyPart']);
        $t->same('annotation', $firstAnnotation['rubyAnnotationText']);
        $t->same(')', $fallbackClose['rubyFallbackText']);
        $t->same('annotation-container', $container['rubyPart']);
        $t->same(['alternate', 'source'], $container['rubyAnnotationTexts']);
        $t->same(2, $container['rubyAnnotationCount']);
        $t->same('annotation', $containerAnnotation['rubyPart']);
        $t->same('alternate', $containerAnnotation['rubyAnnotationText']);
        $t->same('base', $base['rubyPart']);
        $t->same('tail', $base['rubyBaseText']);
        $t->same('tail-note', $tailAnnotation['rubyAnnotationText']);
        $t->same('<p><ruby id="term">base<rp>(</rp><rt>annotation</rt><rp>)</rp><rtc><rt>alternate</rt><rt>source</rt></rtc><rb>tail</rb><rt>tail-note</rt></ruby></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/ruby-annotations-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html data element value provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>SKU <data id="sku" value=" SKU-42 ">Packet <strong>42</strong></data> <data data-review="missing">No value</data></p>',
            'data element review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/data-element-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $valued = $paragraph['children'][1];
        $missing = $paragraph['children'][3];

        $t->same('p', $paragraph['name']);
        $t->same('SKU Packet 42 No value', $paragraph['text']);
        $t->same('data', $valued['name']);
        $t->same('data', $valued['dataElement']);
        $t->same('sku', $valued['elementId']);
        $t->same('Packet 42', $valued['dataText']);
        $t->same(' SKU-42 ', $valued['dataValueRaw']);
        $t->same('SKU-42', $valued['dataValue']);
        $t->same('value-attribute', $valued['dataValueSource']);
        $t->same('strong', $valued['children'][1]['name']);
        $t->same('data', $missing['dataElement']);
        $t->same('No value', $missing['dataText']);
        $t->same(null, $missing['dataValueRaw']);
        $t->same(null, $missing['dataValue']);
        $t->same('missing', $missing['dataValueSource']);
        $t->same(['review' => 'missing'], $missing['dataset']);
        $t->same('<p>SKU <data id="sku" value=" SKU-42 ">Packet <strong>42</strong></data> <data data-review="missing">No value</data></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/data-element-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html pre code block provenance for reviewer handoff' => static function (TestRunner $t): void {
        $codeText = "echo <review> & status;\nreturn true;\n";
        $plainText = "plain\ntext";
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<pre id="snippet" class="sourceCode numberSource php numberLines" data-startfrom="7"><code class="language-php">'
                . htmlspecialchars($codeText, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                . '</code></pre><pre data-note="plain">' . $plainText . '</pre>',
            'pre code block review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/pre-code-block-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $pre = $summary[0];
        $code = $pre['children'][0];
        $plain = $summary[1];

        $t->same('pre', $pre['name']);
        $t->same('pre', $pre['preformattedBlock']);
        $t->same('html-pre-code-block-review', $pre['preformattedReviewPolicy']);
        $t->same('nested-code', $pre['preformattedContentSource']);
        $t->same($codeText, $pre['preformattedText']);
        $t->same(strlen($codeText), $pre['preformattedTextLength']);
        $t->same(hash('sha256', $codeText), $pre['preformattedTextSha256']);
        $t->same(2, $pre['preformattedLineCount']);
        $t->same(true, $pre['preformattedTrailingNewline']);
        $t->same(['sourceCode', 'numberSource', 'php', 'numberLines'], $pre['preformattedClasses']);
        $t->same(true, $pre['preformattedCodeBlock']);
        $t->same(1, $pre['preformattedCodeElementCount']);
        $t->same($codeText, $pre['preformattedCodeText']);
        $t->same(2, $pre['preformattedCodeLineCount']);
        $t->same(true, $pre['preformattedCodeTrailingNewline']);
        $t->same(['language-php'], $pre['preformattedCodeClasses']);
        $t->same(2, $pre['preformattedContentLineCount']);
        $t->same(true, $pre['preformattedNumberedLines']);
        $t->same('php', $pre['preformattedLanguage']);
        $t->same('language-php', $pre['preformattedLanguageToken']);
        $t->same('code-class-language-prefix', $pre['preformattedLanguageSource']);
        $t->same('code', $code['name']);
        $t->same('code', $code['textSemantic']);
        $t->same('echo <review> & status; return true;', $code['semanticText']);

        $t->same('pre', $plain['name']);
        $t->same('pre-text', $plain['preformattedContentSource']);
        $t->same($plainText, $plain['preformattedText']);
        $t->same(2, $plain['preformattedLineCount']);
        $t->same(false, $plain['preformattedTrailingNewline']);
        $t->same(false, $plain['preformattedCodeBlock']);
        $t->same(0, $plain['preformattedCodeElementCount']);
        $t->same(null, $plain['preformattedCodeText']);
        $t->same(2, $plain['preformattedContentLineCount']);
        $t->same(null, $plain['preformattedLanguage']);
        $t->same(false, $plain['preformattedNumberedLines']);
        $t->same('<pre class="sourceCode numberSource php numberLines" data-startfrom="7" id="snippet"><code class="language-php">echo &lt;review&gt; &amp; status;' . "\n" . 'return true;' . "\n" . '</code></pre><pre data-note="plain">plain' . "\n" . 'text</pre>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/pre-code-block-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html time datetime provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article><time datetime=" 2026-06-11 ">June 11</time>'
                . '<time datetime="2026-06-11 18:45:30Z">Published</time>'
                . '<time datetime="2026-06-11T12:30">Local</time>'
                . '<time datetime="2026-W24">Week 24</time>'
                . '<time datetime="PT2H30M">Duration</time>'
                . '<time>2026-06</time>'
                . '<time datetime="2026-02-30">Bad date</time>'
                . '<time></time></article>',
            'time datetime review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/time-datetime-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $date = $article['children'][0];
        $global = $article['children'][1];
        $local = $article['children'][2];
        $week = $article['children'][3];
        $duration = $article['children'][4];
        $textFallback = $article['children'][5];
        $invalid = $article['children'][6];
        $missing = $article['children'][7];

        $t->same('article', $article['name']);
        $t->same('June 11PublishedLocalWeek 24Duration2026-06Bad date', $article['text']);
        $t->same('time', $date['time']);
        $t->same('June 11', $date['timeText']);
        $t->same(' 2026-06-11 ', $date['timeDatetimeRaw']);
        $t->same('datetime-attribute', $date['timeDatetimeSource']);
        $t->same('2026-06-11', $date['timeDatetime']);
        $t->same('date', $date['timeDatetimeKind']);
        $t->same(true, $date['timeDatetimeValid']);
        $t->same('2026-06-11T18:45:30Z', $global['timeDatetime']);
        $t->same('global-datetime', $global['timeDatetimeKind']);
        $t->same(true, $global['timeDatetimeValid']);
        $t->same('2026-06-11T12:30', $local['timeDatetime']);
        $t->same('local-datetime', $local['timeDatetimeKind']);
        $t->same(true, $local['timeDatetimeValid']);
        $t->same('2026-W24', $week['timeDatetime']);
        $t->same('week', $week['timeDatetimeKind']);
        $t->same(true, $week['timeDatetimeValid']);
        $t->same('PT2H30M', $duration['timeDatetime']);
        $t->same('duration', $duration['timeDatetimeKind']);
        $t->same(true, $duration['timeDatetimeValid']);
        $t->same('2026-06', $textFallback['timeText']);
        $t->same(null, $textFallback['timeDatetimeRaw']);
        $t->same('text', $textFallback['timeDatetimeSource']);
        $t->same('2026-06', $textFallback['timeDatetime']);
        $t->same('month', $textFallback['timeDatetimeKind']);
        $t->same(true, $textFallback['timeDatetimeValid']);
        $t->same('2026-02-30', $invalid['timeDatetimeRaw']);
        $t->same('datetime-attribute', $invalid['timeDatetimeSource']);
        $t->same(null, $invalid['timeDatetime']);
        $t->same('invalid', $invalid['timeDatetimeKind']);
        $t->same(false, $invalid['timeDatetimeValid']);
        $t->same('', $missing['timeText']);
        $t->same('missing', $missing['timeDatetimeSource']);
        $t->same('missing', $missing['timeDatetimeKind']);
        $t->same(false, $missing['timeDatetimeValid']);
        $t->same('<article><time datetime=" 2026-06-11 ">June 11</time><time datetime="2026-06-11 18:45:30Z">Published</time><time datetime="2026-06-11T12:30">Local</time><time datetime="2026-W24">Week 24</time><time datetime="PT2H30M">Duration</time><time>2026-06</time><time datetime="2026-02-30">Bad date</time><time></time></article>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/time-datetime-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html time value aliases for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><time datetime="14:05:30.125-0500" data-window="cutoff">Cutoff</time>'
                . '<time datetime="2026-06-11">Date</time>'
                . '<time>09:15Z</time>'
                . '<time datetime="bad&lt;tag">Invalid</time>'
                . '<time></time></p>',
            'time value alias review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/time-value-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $zonedTime = $paragraph['children'][0];
        $date = $paragraph['children'][1];
        $textTime = $paragraph['children'][2];
        $invalid = $paragraph['children'][3];
        $missing = $paragraph['children'][4];

        $t->same('p', $paragraph['name']);
        $t->same('CutoffDate09:15ZInvalid', $paragraph['text']);

        $t->same('time', $zonedTime['name']);
        $t->same(true, $zonedTime['timeElement']);
        $t->same('Cutoff', $zonedTime['timeText']);
        $t->same('14:05:30.125-0500', $zonedTime['timeDatetimeRaw']);
        $t->same('datetime-attribute', $zonedTime['timeDatetimeSource']);
        $t->same('14:05:30.125-05:00', $zonedTime['timeDatetime']);
        $t->same('global-time', $zonedTime['timeDatetimeKind']);
        $t->same(true, $zonedTime['timeDatetimeValid']);
        $t->same('14:05:30.125-0500', $zonedTime['timeValueRaw']);
        $t->same('14:05:30.125-05:00', $zonedTime['timeValue']);
        $t->same('global-time', $zonedTime['timeValueKind']);
        $t->same(true, $zonedTime['timeValueValid']);
        $t->same(['window' => 'cutoff'], $zonedTime['dataset']);

        $t->same('2026-06-11', $date['timeDatetime']);
        $t->same('date', $date['timeValueKind']);
        $t->same('2026-06-11', $date['timeValue']);
        $t->same(true, $date['timeValueValid']);

        $t->same(null, $textTime['timeDatetimeRaw']);
        $t->same('text', $textTime['timeDatetimeSource']);
        $t->same('09:15Z', $textTime['timeValueRaw']);
        $t->same('09:15Z', $textTime['timeValue']);
        $t->same('global-time', $textTime['timeValueKind']);
        $t->same(true, $textTime['timeValueValid']);

        $t->same('bad<tag', $invalid['timeValueRaw']);
        $t->same(null, $invalid['timeValue']);
        $t->same('invalid', $invalid['timeValueKind']);
        $t->same(false, $invalid['timeValueValid']);

        $t->same('missing', $missing['timeDatetimeSource']);
        $t->same('', $missing['timeValueRaw']);
        $t->same(null, $missing['timeValue']);
        $t->same(null, $missing['timeValueKind']);
        $t->same(false, $missing['timeValueValid']);

        $t->same('<p><time data-window="cutoff" datetime="14:05:30.125-0500">Cutoff</time><time datetime="2026-06-11">Date</time><time>09:15Z</time><time datetime="bad&lt;tag">Invalid</time><time></time></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/time-value-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html preformatted code block provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<pre id="snippet"><code class="language-php reviewer-snippet">echo &quot;Hi&quot;' . "\n" . 'return $value' . "\n" . '</code></pre>'
                . '<pre data-language="mermaid">graph LR' . "\n" . 'A--&gt;B</pre>',
            'preformatted code review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/preformatted-code-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $codeText = "echo \"Hi\"\nreturn \$value\n";
        $mermaidText = "graph LR\nA-->B";
        $pre = $summary[0];
        $code = $pre['children'][0];
        $plainPre = $summary[1];

        $t->same('pre', $pre['name']);
        $t->same('pre', $pre['preformatted']);
        $t->same('pre-code', $pre['codeBlock']);
        $t->same('code', $pre['codeBlockSourceElement']);
        $t->same($codeText, $pre['codeText']);
        $t->same(strlen($codeText), $pre['codeTextLength']);
        $t->same(hash('sha256', $codeText), $pre['codeTextSha256']);
        $t->same(false, $pre['codeStartsWithNewline']);
        $t->same(true, $pre['codeEndsWithNewline']);
        $t->same(2, $pre['codeLineCount']);
        $t->same('php', $pre['codeLanguage']);
        $t->same('class-token', $pre['codeLanguageSource']);
        $t->same('language-php', $pre['codeLanguageToken']);
        $t->same(['language-php', 'reviewer-snippet'], $pre['codeClassTokens']);

        $t->same('code', $code['name']);
        $t->same('code', $code['textSemantic']);
        $t->same('echo "Hi" return $value', $code['semanticText']);

        $t->same('pre', $plainPre['name']);
        $t->same('pre', $plainPre['codeBlock']);
        $t->same('pre', $plainPre['codeBlockSourceElement']);
        $t->same($mermaidText, $plainPre['codeText']);
        $t->same(2, $plainPre['codeLineCount']);
        $t->same('mermaid', $plainPre['codeLanguage']);
        $t->same('data-language', $plainPre['codeLanguageSource']);
        $t->same(null, $plainPre['codeLanguageToken']);
        $t->same([], $plainPre['codeClassTokens']);

        $t->contains("<code class=\"language-php reviewer-snippet\">{$codeText}</code>", $html);
        $t->contains('<pre data-language="mermaid">graph LR' . "\n" . 'A--&gt;B</pre>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/preformatted-code-review.html', $document->children[0]->attr('part'));
    },
    'serializes entities comments and boolean attributes for HTML blocks' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            'Text&nbsp;<span title="A &quot;quote&quot; &amp; source">source &lt;em&gt;</span><!--review--><input checked>',
            'entity HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same("Text\u{00A0}", $summary[0]['text']);
        $t->same('span', $summary[1]['name']);
        $t->same(['title' => 'A "quote" & source'], $summary[1]['attributes']);
        $t->same('source <em>', $summary[1]['text']);
        $t->same('comment', $summary[2]['type']);
        $t->same('review', $summary[2]['text']);
        $t->same('input', $summary[3]['name']);
        $t->same(['checked' => 'checked'], $summary[3]['attributes']);
        $t->same("Text\u{00A0}<span title=\"A &quot;quote&quot; &amp; source\">source &lt;em&gt;</span><!--review--><input checked>", $html);
    },
    'decodes bounded html5 math spacing references before raw block serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p data-math="&af;&it;&ic;">f&ApplyFunction;g&InvisibleTimes;h&MediumSpace;comma&InvisibleComma;zero&ZeroWidthSpace;neg&NegativeThinSpace;</p>'
                . '<p data-spacing="&NonBreakingSpace;&ThinSpace;&ThickSpace;&VeryThinSpace;&hairsp;">Spaces: non&NonBreakingSpace;thin&ThinSpace;alias&thinsp;thick&ThickSpace;very&VeryThinSpace;hair&hairsp;neg&NegativeVeryThinSpace;&NegativeMediumSpace;&NegativeThickSpace;</p>',
            'math spacing entity HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(2, count($summary));
        $t->same('p', $summary[0]['name']);
        $t->same(['data-math' => "\u{2061}\u{2062}\u{2063}"], $summary[0]['attributes']);
        $t->same("f\u{2061}g\u{2062}h\u{205F}comma\u{2063}zero\u{200B}neg\u{200B}", $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same("f\u{2061}g\u{2062}h\u{205F}comma\u{2063}zero\u{200B}neg\u{200B}", $summary[0]['children'][0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same(['data-spacing' => "\u{00A0}\u{2009}\u{205F}\u{200A}\u{200A}\u{200A}"], $summary[1]['attributes']);
        $t->same("Spaces: non\u{00A0}thin\u{2009}alias\u{2009}thick\u{205F}\u{200A}very\u{200A}hair\u{200A}neg\u{200B}\u{200B}\u{200B}", $summary[1]['text']);
        $t->same("Spaces: non\u{00A0}thin\u{2009}alias\u{2009}thick\u{205F}\u{200A}very\u{200A}hair\u{200A}neg\u{200B}\u{200B}\u{200B}", $summary[1]['children'][0]['text']);
        $t->same('<p data-math="' . "\u{2061}\u{2062}\u{2063}" . '">f' . "\u{2061}" . 'g' . "\u{2062}" . 'h' . "\u{205F}" . 'comma' . "\u{2063}" . 'zero' . "\u{200B}" . 'neg' . "\u{200B}" . '</p><p data-spacing="' . "\u{00A0}\u{2009}\u{205F}\u{200A}\u{200A}\u{200A}" . '">Spaces: non' . "\u{00A0}" . 'thin' . "\u{2009}" . 'alias' . "\u{2009}" . 'thick' . "\u{205F}\u{200A}" . 'very' . "\u{200A}" . 'hair' . "\u{200A}" . 'neg' . "\u{200B}\u{200B}\u{200B}" . '</p>', $html);
        $t->true(!str_contains($html, '&amp;ApplyFunction;'), 'Expected ApplyFunction to decode before raw HTML handoff');
        $t->true(!str_contains($html, '&amp;ZeroWidthSpace;'), 'Expected ZeroWidthSpace to decode before raw HTML handoff');
        $t->true(!str_contains($html, '&amp;NonBreakingSpace;'), 'Expected NonBreakingSpace to decode before raw HTML handoff');
        $t->true(!str_contains($html, '&amp;ThickSpace;'), 'Expected ThickSpace to decode before raw HTML handoff');
        $t->true(!str_contains($html, '&amp;NegativeMediumSpace;'), 'Expected negative spacing aliases to decode before raw HTML handoff');
    },
    'decodes safe semicolon html5 named references before raw block serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p data-math="&NotEqualTilde;&DoubleLongRightArrow;&realine;">'
                . '&CounterClockwiseContourIntegral;&LeftTriangleBar;&NotNestedGreaterGreater;&angmsdaa;&bnequiv;&nparsl;&suphsol;&rarrfs;&nGg;&gesles;&lesg;&angzarr;'
                . '</p><p data-core="&quot;&amp;&lt;">core &quot;&amp;&lt;</p>'
                . '<script type="application/json">{"literal":"&NotEqualTilde;"}</script>',
            'broad html5 named entity fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $attribute = "\u{2242}\u{0338}\u{27F9}\u{211B}";
        $text = "\u{2233}\u{29CF}\u{2AA2}\u{0338}\u{29A8}\u{2261}\u{20E5}\u{2AFD}\u{20E5}\u{27C9}\u{291E}\u{22D9}\u{0338}\u{2A94}\u{22DA}\u{FE00}\u{237C}";

        $t->same($attribute, $summary[0]['attributes']['data-math']);
        $t->same($text, $summary[0]['text']);
        $t->same(['data-core' => '"&<'], $summary[1]['attributes']);
        $t->same('core "&<', $summary[1]['text']);
        $t->same('{"literal":"&NotEqualTilde;"}', $summary[2]['text']);
        $t->same('<p data-math="' . $attribute . '">' . $text . '</p><p data-core="&quot;&amp;&lt;">core "&amp;&lt;</p><script type="application/json">{"literal":"&NotEqualTilde;"}</script>', $html);
        foreach (['NotEqualTilde', 'CounterClockwiseContourIntegral', 'NotNestedGreaterGreater', 'bnequiv', 'angzarr'] as $entityName) {
            $t->true(!str_contains($html, '&amp;' . $entityName . ';'), 'Expected HTML5 reference ' . $entityName . ' to decode before raw HTML handoff');
        }
        $t->contains('{"literal":"&NotEqualTilde;"}', $html);
    },
    'normalizes unsafe html comment boundaries before raw block serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<!--review---><p>Imported comment boundary</p><!--source -- boundary--><!--triple---tail--->',
            'comment boundary HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('comment', $summary[0]['type']);
        $t->same('review-', $summary[0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('comment', $summary[2]['type']);
        $t->same('source -- boundary', $summary[2]['text']);
        $t->same('comment', $summary[3]['type']);
        $t->same('triple---tail-', $summary[3]['text']);
        $t->same('<!--review- --><p>Imported comment boundary</p><!--source - - boundary--><!--triple- - -tail- -->', $html);
        $t->true(!str_contains($html, '--->'), 'Expected trailing hyphen comments to be padded before the closing delimiter');
        $t->true(!str_contains($html, 'source -- boundary'), 'Expected interior comment delimiters to be split before serialization');
        $t->true(!str_contains($html, 'triple---tail'), 'Expected overlapping comment delimiters to be split before serialization');
    },
    'summarizes html fragment comment provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<!-- <!DOCTYPE html><?review href="file"?> -->'
                . '<section id="s"><p>Body</p><!--source -- boundary--><!--line' . "\n" . 'two--></section>'
                . '<!--review--->',
            'comment provenance HTML fragment'
        );
        $packet = XmlHtmlDom::summarizeHtmlFragmentComments($dom);
        $comments = $packet['comments'];

        $t->same('html-fragment-comment-provenance', $packet['commentReviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same(['html-fragment-comment-review-only'], $packet['directReaderDiagnosticCodes']);
        $t->same(4, $packet['commentCount']);
        $t->same(1, $packet['declarationLikeCommentCount']);
        $t->same(2, $packet['unsafeBoundaryCommentCount']);
        $t->same(0, $packet['emptyCommentCount']);
        $t->same(1, $packet['multilineCommentCount']);
        $t->same(['declaration-like-comment-text', 'unsafe-comment-boundary'], $packet['commentIssueCodes']);

        $t->same('comment()[1]', $comments[0]['nodePath']);
        $t->same(null, $comments[0]['parentElement']);
        $t->same(' <!DOCTYPE html><?review href="file"?> ', $comments[0]['text']);
        $t->same(hash('sha256', ' <!DOCTYPE html><?review href="file"?> '), $comments[0]['textSha256']);
        $t->same(true, $comments[0]['containsDoctypeText']);
        $t->same(true, $comments[0]['containsProcessingInstructionText']);
        $t->same(true, $comments[0]['containsDeclarationLikeText']);
        $t->same(false, $comments[0]['unsafeBoundary']);
        $t->same(['declaration-like-comment-text'], $comments[0]['issueCodes']);

        $t->same('section[1]/comment()[1]', $comments[1]['nodePath']);
        $t->same('section', $comments[1]['parentElement']);
        $t->same('section[1]', $comments[1]['parentElementPath']);
        $t->same('source -- boundary', $comments[1]['text']);
        $t->same(true, $comments[1]['unsafeBoundary']);
        $t->same(true, $comments[1]['serializationTextChanged']);
        $t->same('source - - boundary', $comments[1]['safeSerializationText']);
        $t->same(['unsafe-comment-boundary'], $comments[1]['issueCodes']);

        $t->same('section[1]/comment()[2]', $comments[2]['nodePath']);
        $t->same("line\ntwo", $comments[2]['text']);
        $t->same(2, $comments[2]['textLineCount']);
        $t->same(true, $comments[2]['multiline']);
        $t->same([], $comments[2]['issueCodes']);

        $t->same('comment()[2]', $comments[3]['nodePath']);
        $t->same('review-', $comments[3]['text']);
        $t->same(true, $comments[3]['unsafeBoundary']);
        $t->same('review- ', $comments[3]['safeSerializationText']);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'serializes raw text elements and expanded html5 boolean attributes' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script defer src="review.js">if (a < b && c > d) { window.review = "&"; }</script>'
                . '<style disabled>.legacy > .target::before { content: "&"; }</style>',
            'raw text HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('script', $summary[0]['name']);
        $t->same(['defer' => 'defer', 'src' => 'review.js'], $summary[0]['attributes']);
        $t->same('if (a < b && c > d) { window.review = "&"; }', $summary[0]['text']);
        $t->same('style', $summary[1]['name']);
        $t->same(['disabled' => 'disabled'], $summary[1]['attributes']);
        $t->same('.legacy > .target::before { content: "&"; }', $summary[1]['text']);
        $t->same('<script defer src="review.js">if (a < b && c > d) { window.review = "&"; }</script><style disabled>.legacy > .target::before { content: "&"; }</style>', $html);
    },
    'diagnoses plaintext and unterminated raw text source boundaries' => static function (TestRunner $t): void {
        $rawDiagnostics = XmlHtmlDom::htmlRawTextBoundaryDiagnostics(
            "<section>\n<script>if (a < b) { alert(1); }\n<p>after</p>"
        );
        $plaintextDiagnostics = XmlHtmlDom::htmlRawTextBoundaryDiagnostics(
            '<plaintext>Reviewer <b>note</b></plaintext><p>after</p>'
        );

        $t->same(1, count($rawDiagnostics));
        $t->same('raw-text-boundary', $rawDiagnostics[0]['code'] ?? null);
        $t->same('script', $rawDiagnostics[0]['tag'] ?? null);
        $t->same('raw-text', $rawDiagnostics[0]['kind'] ?? null);
        $t->same('missing-end-tag-synthesized', $rawDiagnostics[0]['reason'] ?? null);
        $t->same('synthetic-eof', $rawDiagnostics[0]['closedBy'] ?? null);
        $t->same(2, $rawDiagnostics[0]['line'] ?? null);
        $t->same(1, $rawDiagnostics[0]['column'] ?? null);
        $t->same(1, count($plaintextDiagnostics));
        $t->same('plaintext-boundary', $plaintextDiagnostics[0]['code'] ?? null);
        $t->same('plaintext', $plaintextDiagnostics[0]['tag'] ?? null);
        $t->same('plaintext', $plaintextDiagnostics[0]['kind'] ?? null);
        $t->same('plaintext-consumes-fragment-tail', $plaintextDiagnostics[0]['reason'] ?? null);
        $t->same('fragment-eof', $plaintextDiagnostics[0]['closedBy'] ?? null);
        $t->same(true, $plaintextDiagnostics[0]['ignoredEndTag'] ?? null);
    },
    'summarizes html script and style active source provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script type="module" src="app.js" async defer crossorigin="anonymous" integrity="sha384-review" referrerpolicy="no-referrer" fetchpriority="high" blocking="render"></script>'
                . '<script nomodule>console.log("<review> & source");</script>'
                . '<style type="text/css" media="print" disabled blocking="render">body > .review { color: red; }</style>',
            'active content provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/active-content-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $externalScript = $summary[0];
        $inlineScript = $summary[1];
        $style = $summary[2];

        $t->same('script', $externalScript['name']);
        $t->same('script', $externalScript['activeContent']);
        $t->same('external', $externalScript['scriptSourceKind']);
        $t->same('app.js', $externalScript['src']);
        $t->same('module', $externalScript['scriptTypeRaw']);
        $t->same('module', $externalScript['scriptType']);
        $t->same(true, $externalScript['module']);
        $t->same('module', $externalScript['scriptPayloadKind']);
        $t->same(true, $externalScript['scriptExecutable']);
        $t->same(false, $externalScript['scriptDataBlock']);
        $t->same(true, $externalScript['scriptTypeKnown']);
        $t->same(true, $externalScript['async']);
        $t->same(true, $externalScript['defer']);
        $t->same(false, $externalScript['nomodule']);
        $t->same('anonymous', $externalScript['crossorigin']);
        $t->same('sha384-review', $externalScript['integrity']);
        $t->same('no-referrer', $externalScript['referrerpolicy']);
        $t->same('high', $externalScript['fetchpriority']);
        $t->same('render', $externalScript['blockingRaw']);
        $t->same(['render'], $externalScript['blockingTokens']);
        $t->same('script-loading-metadata-review', $externalScript['scriptLoadingReviewPolicy']);
        $t->same('async-module', $externalScript['scriptLoadingMode']);
        $t->same('anonymous', $externalScript['scriptCrossoriginState']);
        $t->same(true, $externalScript['scriptCrossoriginValid']);
        $t->same('no-referrer', $externalScript['scriptReferrerPolicy']);
        $t->same(true, $externalScript['scriptReferrerPolicyValid']);
        $t->same('high', $externalScript['scriptFetchPriority']);
        $t->same(true, $externalScript['scriptFetchPriorityValid']);
        $t->same(['render' => 1], $externalScript['scriptBlockingTokenCounts']);
        $t->same([], $externalScript['invalidScriptBlockingTokens']);
        $t->same('', $externalScript['scriptText']);
        $t->same(0, $externalScript['scriptTextLength']);
        $t->same(hash('sha256', ''), $externalScript['scriptTextSha256']);
        $t->same('external-script-source', $externalScript['activeReviewPolicy']);
        $t->same('script', $inlineScript['activeContent']);
        $t->same('inline', $inlineScript['scriptSourceKind']);
        $t->same(null, $inlineScript['src']);
        $t->same('classic', $inlineScript['scriptPayloadKind']);
        $t->same(true, $inlineScript['scriptExecutable']);
        $t->same(false, $inlineScript['scriptDataBlock']);
        $t->same('inline-executable', $inlineScript['scriptLoadingMode']);
        $t->same(false, $inlineScript['module']);
        $t->same(false, $inlineScript['async']);
        $t->same(false, $inlineScript['defer']);
        $t->same(true, $inlineScript['nomodule']);
        $t->same('console.log("<review> & source");', $inlineScript['scriptText']);
        $t->same(strlen('console.log("<review> & source");'), $inlineScript['scriptTextLength']);
        $t->same(hash('sha256', 'console.log("<review> & source");'), $inlineScript['scriptTextSha256']);
        $t->same('inline-script-source', $inlineScript['activeReviewPolicy']);
        $t->same('style', $style['name']);
        $t->same('style', $style['activeContent']);
        $t->same('inline', $style['styleSourceKind']);
        $t->same('text/css', $style['styleTypeRaw']);
        $t->same('text/css', $style['styleType']);
        $t->same('print', $style['media']);
        $t->same(true, $style['disabled']);
        $t->same('render', $style['blockingRaw']);
        $t->same(['render'], $style['blockingTokens']);
        $t->same('body > .review { color: red; }', $style['styleText']);
        $t->same(strlen('body > .review { color: red; }'), $style['styleTextLength']);
        $t->same(hash('sha256', 'body > .review { color: red; }'), $style['styleTextSha256']);
        $t->same('inline-style-source', $style['activeReviewPolicy']);
        $t->same('<script async blocking="render" crossorigin="anonymous" defer fetchpriority="high" integrity="sha384-review" referrerpolicy="no-referrer" src="app.js" type="module"></script><script nomodule>console.log("<review> & source");</script><style blocking="render" disabled media="print" type="text/css">body > .review { color: red; }</style>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/active-content-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html script attribution source provenance for reviewer handoff' => static function (TestRunner $t): void {
        $attributionSrc = 'https://report.example/register /local-report javascript:alert(1) mailto:ops@example.test';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script id="module-report" type="module" attributionsrc="' . $attributionSrc . '">import "./boot.js";</script>'
                . '<script id="boolean-report" src="tracker.js" attributionsrc></script>'
                . '<script id="plain" src="plain.js"></script>',
            'script attribution source review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/script-attribution-source-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $reporting = $summary[0];
        $empty = $summary[1];
        $plain = $summary[2];

        $t->same('script-attributionsrc-provenance-review', $reporting['scriptAttributionSrcReviewPolicy']);
        $t->same(true, $reporting['scriptAttributionSrcRequested']);
        $t->same($attributionSrc, $reporting['scriptAttributionSrcRaw']);
        $t->same(false, $reporting['scriptAttributionSrcEmpty']);
        $t->same([
            'https://report.example/register',
            '/local-report',
            'javascript:alert(1)',
            'mailto:ops@example.test',
        ], $reporting['scriptAttributionSrcUrls']);
        $t->same(4, $reporting['scriptAttributionSrcUrlCount']);
        $t->same('absolute', $reporting['scriptAttributionSrcUrlRecords'][0]['kind']);
        $t->same('https', $reporting['scriptAttributionSrcUrlRecords'][0]['scheme']);
        $t->same(false, $reporting['scriptAttributionSrcUrlRecords'][0]['unsafe']);
        $t->same('relative', $reporting['scriptAttributionSrcUrlRecords'][1]['kind']);
        $t->same('javascript', $reporting['scriptAttributionSrcUrlRecords'][2]['scheme']);
        $t->same(true, $reporting['scriptAttributionSrcUrlRecords'][2]['unsafe']);
        $t->same(['javascript:alert(1)'], $reporting['unsafeScriptAttributionSrcUrls']);
        $t->same(['mailto:ops@example.test'], $reporting['nonHttpScriptAttributionSrcUrls']);
        $t->same([
            ['code' => 'unsafe-script-attributionsrc-url', 'url' => 'javascript:alert(1)', 'scheme' => 'javascript'],
            ['code' => 'non-http-script-attributionsrc-url', 'url' => 'mailto:ops@example.test', 'scheme' => 'mailto'],
        ], $reporting['scriptAttributionSrcIssues']);
        $t->same('module', $reporting['scriptPayloadKind']);
        $t->same('inline', $reporting['scriptSourceKind']);

        $t->same('script-attributionsrc-provenance-review', $empty['scriptAttributionSrcReviewPolicy']);
        $t->same(true, $empty['scriptAttributionSrcRequested']);
        $t->same('', $empty['scriptAttributionSrcRaw']);
        $t->same(true, $empty['scriptAttributionSrcEmpty']);
        $t->same([], $empty['scriptAttributionSrcUrls']);
        $t->same(0, $empty['scriptAttributionSrcUrlCount']);
        $t->same([], $empty['scriptAttributionSrcUrlRecords']);
        $t->same([], $empty['unsafeScriptAttributionSrcUrls']);
        $t->same([], $empty['nonHttpScriptAttributionSrcUrls']);
        $t->same([
            ['code' => 'empty-script-attributionsrc'],
        ], $empty['scriptAttributionSrcIssues']);
        $t->same('external', $empty['scriptSourceKind']);

        $t->true(!array_key_exists('scriptAttributionSrcReviewPolicy', $plain));
        $t->same('external', $plain['scriptSourceKind']);
        $t->same('<script attributionsrc="https://report.example/register /local-report javascript:alert(1) mailto:ops@example.test" id="module-report" type="module">import "./boot.js";</script><script attributionsrc="" id="boolean-report" src="tracker.js"></script><script id="plain" src="plain.js"></script>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/script-attribution-source-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html active content nonce provenance for reviewer handoff' => static function (TestRunner $t): void {
        $scriptNonce = 'script-nonce-123';
        $scriptText = 'window.boot = true;';
        $styleText = 'body { color: red; }';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script id="boot" type="module" nonce="' . $scriptNonce . '">' . $scriptText . '</script>'
                . '<style id="theme" nonce="">' . $styleText . '</style>'
                . '<script id="plain">console.log("plain");</script>',
            'active content nonce provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/active-content-nonce-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $script = $summary[0];
        $style = $summary[1];
        $plain = $summary[2];

        $t->same('active-content-nonce-provenance-review', $script['activeContentNonceReviewPolicy']);
        $t->same(true, $script['activeContentNoncePresent']);
        $t->same(strlen($scriptNonce), $script['activeContentNonceByteLength']);
        $t->same(hash('sha256', $scriptNonce), $script['activeContentNonceSha256']);
        $t->same(false, $script['activeContentNonceEmpty']);
        $t->same($scriptNonce, $script['attributes']['nonce']);
        $t->true(!array_key_exists('nonceRaw', $script));
        $t->same(hash('sha256', $scriptText), $script['scriptTextSha256']);

        $t->same('active-content-nonce-provenance-review', $style['activeContentNonceReviewPolicy']);
        $t->same(true, $style['activeContentNoncePresent']);
        $t->same(0, $style['activeContentNonceByteLength']);
        $t->same(hash('sha256', ''), $style['activeContentNonceSha256']);
        $t->same(true, $style['activeContentNonceEmpty']);
        $t->same('', $style['attributes']['nonce']);
        $t->true(!array_key_exists('nonceRaw', $style));
        $t->same(hash('sha256', $styleText), $style['styleTextSha256']);

        $t->true(!array_key_exists('activeContentNoncePresent', $plain));
        $t->true(!array_key_exists('activeContentNonceSha256', $plain));
        $t->same('inline', $plain['scriptSourceKind']);
        $t->same('<script id="boot" nonce="script-nonce-123" type="module">window.boot = true;</script><style id="theme" nonce="">body { color: red; }</style><script id="plain">console.log("plain");</script>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/active-content-nonce-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html script import map and speculation rules json provenance' => static function (TestRunner $t): void {
        $importMapSource = '{"imports":{"app":"/assets/app.js","pkg/":"/vendor/pkg/"},"scopes":{"/admin/":{"app":"/admin/app.js"}},"integrity":{"app":"sha384-app"}}';
        $speculationRulesSource = '{"prefetch":[{"source":"list","urls":["/next"]}],"prerender":{"source":"document"}}';
        $badJsonSource = '{"broken":';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script type="importmap">' . $importMapSource . '</script>'
                . '<script type="speculationrules" blocking="render render bad-token">' . $speculationRulesSource . '</script>'
                . '<script type="application/json">' . $badJsonSource . '</script>',
            'script json provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/script-json-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $importMap = $summary[0];
        $speculationRules = $summary[1];
        $badJson = $summary[2];

        $t->same('importmap', $importMap['scriptPayloadKind']);
        $t->same(false, $importMap['scriptExecutable']);
        $t->same(true, $importMap['scriptDataBlock']);
        $t->same('inert-data-block', $importMap['scriptLoadingMode']);
        $t->same('script-json-inert-source-review', $importMap['scriptJsonReviewPolicy']);
        $t->same(true, $importMap['scriptJsonParsed']);
        $t->same('object', $importMap['scriptJsonType']);
        $t->same(['imports', 'scopes', 'integrity'], $importMap['scriptJsonObjectKeys']);
        $t->same(3, $importMap['scriptJsonObjectKeyCount']);
        $t->same([], $importMap['scriptJsonDiagnostics']);
        $t->same(2, $importMap['importMapImportsCount']);
        $t->same(1, $importMap['importMapScopesCount']);
        $t->same(1, $importMap['importMapIntegrityCount']);
        $t->same($importMapSource, $importMap['scriptText']);
        $t->same(hash('sha256', $importMapSource), $importMap['scriptTextSha256']);

        $t->same('speculationrules', $speculationRules['scriptPayloadKind']);
        $t->same(false, $speculationRules['scriptExecutable']);
        $t->same(true, $speculationRules['scriptDataBlock']);
        $t->same('inert-data-block', $speculationRules['scriptLoadingMode']);
        $t->same(['render', 'render', 'bad-token'], $speculationRules['blockingTokens']);
        $t->same(['render' => 2, 'bad-token' => 1], $speculationRules['scriptBlockingTokenCounts']);
        $t->same(['bad-token'], $speculationRules['invalidScriptBlockingTokens']);
        $t->same(true, $speculationRules['scriptJsonParsed']);
        $t->same('object', $speculationRules['scriptJsonType']);
        $t->same(['prefetch', 'prerender'], $speculationRules['scriptJsonObjectKeys']);
        $t->same(['speculationrules-prerender-not-array'], $speculationRules['scriptJsonDiagnostics']);
        $t->same(['prefetch', 'prerender'], $speculationRules['speculationRuleSetNames']);
        $t->same(['prefetch' => 1, 'prerender' => null], $speculationRules['speculationRuleSetCounts']);

        $t->same('json-data', $badJson['scriptPayloadKind']);
        $t->same(false, $badJson['scriptExecutable']);
        $t->same(true, $badJson['scriptDataBlock']);
        $t->same(false, $badJson['scriptJsonParsed']);
        $t->same(null, $badJson['scriptJsonType']);
        $t->same(['script-json-syntax-error'], $badJson['scriptJsonDiagnostics']);
        $t->contains('Syntax error', $badJson['scriptJsonError']);
        $t->same($badJsonSource, $badJson['scriptText']);

        $t->same(
            '<script type="importmap">' . $importMapSource . '</script><script blocking="render render bad-token" type="speculationrules">' . $speculationRulesSource . '</script><script type="application/json">' . $badJsonSource . '</script>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/script-json-review.html', $document->children[0]->attr('part'));
    },
    'preflights html declarations outside protected raw text serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script type="application/json">{"doctype":"<!DOCTYPE html>","pi":"<?review href=\"file\"?>"}</script>'
                . '<style>body:before{content:"<!ENTITY reviewer SYSTEM file>"}</style>'
                . '<textarea><!ENTITY reviewer SYSTEM "file:///etc/passwd"></textarea>'
                . '<template><?xml-stylesheet href="file"?></template>'
                . '<iframe><!DOCTYPE html></iframe>',
            'raw text declaration HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('script', $summary[0]['name']);
        $t->same('{"doctype":"<!DOCTYPE html>","pi":"<?review href=\"file\"?>"}', $summary[0]['text']);
        $t->same('body:before{content:"<!ENTITY reviewer SYSTEM file>"}', $summary[1]['text']);
        $t->same('<!ENTITY reviewer SYSTEM "file:///etc/passwd">', $summary[2]['text']);
        $t->same('<?xml-stylesheet href="file"?>', $summary[3]['text']);
        $t->same('<!DOCTYPE html>', $summary[4]['text']);
        $t->same(
            '<script type="application/json">{"doctype":"<!DOCTYPE html>","pi":"<?review href=\"file\"?>"}</script>'
                . '<style>body:before{content:"<!ENTITY reviewer SYSTEM file>"}</style>'
                . '<textarea>&lt;!ENTITY reviewer SYSTEM "file:///etc/passwd"&gt;</textarea>'
                . '<template>&lt;?xml-stylesheet href="file"?&gt;</template>'
                . '<iframe>&lt;!DOCTYPE html&gt;</iframe>',
            $html
        );
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<p>bad</p><!DOCTYPE html>', 'unsafe HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<p><?review href="file"?></p>', 'unsafe HTML fragment'));
    },
    'summarizes html select option state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<select name="review-status" multiple><option value="draft">Draft<option selected value="review">Review<optgroup label="Archive" disabled><option value="a1">Archive One<option selected>Archive Two</optgroup></select><p>after</p>',
            'select review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('select', $summary[0]['name']);
        $t->same('select', $summary[0]['formControl']);
        $t->same(['multiple' => 'multiple', 'name' => 'review-status'], $summary[0]['attributes']);
        $t->same(['review', 'Archive Two'], $summary[0]['selectedValues']);
        $t->same([
            ['value' => 'draft', 'label' => 'Draft', 'text' => 'Draft', 'selected' => false, 'disabled' => false],
            ['value' => 'review', 'label' => 'Review', 'text' => 'Review', 'selected' => true, 'disabled' => false],
            ['value' => 'a1', 'label' => 'Archive One', 'text' => 'Archive One', 'selected' => false, 'disabled' => true, 'group' => 'Archive', 'groupDisabled' => true],
            ['value' => 'Archive Two', 'label' => 'Archive Two', 'text' => 'Archive Two', 'selected' => true, 'disabled' => true, 'group' => 'Archive', 'groupDisabled' => true],
        ], $summary[0]['selectOptions']);
        $t->same('<select multiple name="review-status"><option value="draft">Draft</option><option selected value="review">Review</option><optgroup disabled label="Archive"><option value="a1">Archive One</option><option selected>Archive Two</option></optgroup></select><p>after</p>', $html);
    },
    'summarizes html option and optgroup nodes for direct fragment review' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<select id="status" name="status"><option value="draft" label="Draft status">Draft<option selected disabled>Review<optgroup label="Archive" disabled><option value="old">Old<option selected value="cold">Cold</optgroup></select><p>after</p>',
            'option optgroup review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/option-optgroup-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $select = $summary[0];
        $draft = $select['children'][0];
        $review = $select['children'][1];
        $archive = $select['children'][2];
        $old = $archive['children'][0];
        $cold = $archive['children'][1];

        $t->same('select', $select['formControl']);
        $t->same(['Review', 'cold'], $select['selectedValues']);
        $t->same('option', $draft['option']);
        $t->same('draft', $draft['optionValue']);
        $t->same('Draft status', $draft['optionLabel']);
        $t->same('Draft', $draft['optionText']);
        $t->same(false, $draft['selected']);
        $t->same(false, $draft['disabled']);
        $t->same(false, $draft['effectiveDisabled']);
        $t->same(null, $draft['optionGroupLabel']);

        $t->same('option', $review['option']);
        $t->same('Review', $review['optionValue']);
        $t->same('Review', $review['optionLabel']);
        $t->same(true, $review['selected']);
        $t->same(true, $review['disabled']);
        $t->same(true, $review['optionDisabled']);
        $t->same(true, $review['effectiveDisabled']);

        $t->same('optgroup', $archive['optionGroup']);
        $t->same('Archive', $archive['optionGroupLabel']);
        $t->same(true, $archive['disabled']);
        $t->same(2, $archive['optionCount']);
        $t->same(1, $archive['selectedOptionCount']);
        $t->same(['old', 'cold'], $archive['optionValues']);
        $t->same(['cold'], $archive['selectedValues']);
        $t->same([
            ['value' => 'old', 'label' => 'Old', 'text' => 'Old', 'selected' => false, 'disabled' => true],
            ['value' => 'cold', 'label' => 'Cold', 'text' => 'Cold', 'selected' => true, 'disabled' => true],
        ], $archive['options']);

        $t->same('Archive', $old['optionGroupLabel']);
        $t->same(true, $old['optionGroupDisabled']);
        $t->same(true, $old['effectiveDisabled']);
        $t->same('cold', $cold['optionValue']);
        $t->same(true, $cold['selected']);
        $t->contains($html, $blocks);
        $t->same('/migration/option-optgroup-review.html', $document->children[0]->attr('part'));
        $t->same('<select id="status" name="status"><option label="Draft status" value="draft">Draft</option><option disabled selected>Review</option><optgroup disabled label="Archive"><option value="old">Old</option><option selected value="cold">Cold</option></optgroup></select><p>after</p>', $html);
    },
    'summarizes html input textarea and button state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="review-form"><input name="title" value="Draft &amp; Source"><input type="checkbox" name="publish" checked disabled><textarea name="notes" readonly>Reviewer &amp; editor' . "\n" . 'note</textarea><button name="action" value="publish">Publish <strong>now</strong></button><button type="reset" disabled>Clear</button></form>',
            'form control review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $textInput = $form['children'][0];
        $checkbox = $form['children'][1];
        $textarea = $form['children'][2];
        $submitButton = $form['children'][3];
        $resetButton = $form['children'][4];

        $t->same('form', $form['name']);
        $t->same(['id' => 'review-form'], $form['attributes']);
        $t->same('input', $textInput['formControl']);
        $t->same('text', $textInput['inputType']);
        $t->same('Draft & Source', $textInput['value']);
        $t->same(false, $textInput['checked']);
        $t->same(false, $textInput['disabled']);
        $t->same('input', $checkbox['formControl']);
        $t->same('checkbox', $checkbox['inputType']);
        $t->same('', $checkbox['value']);
        $t->same(true, $checkbox['checked']);
        $t->same(true, $checkbox['disabled']);
        $t->same('textarea', $textarea['formControl']);
        $t->same("Reviewer & editor\nnote", $textarea['value']);
        $t->same(false, $textarea['disabled']);
        $t->same(true, $textarea['readonly']);
        $t->same('button', $submitButton['formControl']);
        $t->same('submit', $submitButton['buttonType']);
        $t->same('publish', $submitButton['value']);
        $t->same('Publish now', $submitButton['label']);
        $t->same(false, $submitButton['disabled']);
        $t->same('button', $resetButton['formControl']);
        $t->same('reset', $resetButton['buttonType']);
        $t->same('', $resetButton['value']);
        $t->same('Clear', $resetButton['label']);
        $t->same(true, $resetButton['disabled']);
        $t->same('<form id="review-form"><input name="title" value="Draft &amp; Source"><input checked disabled name="publish" type="checkbox"><textarea name="notes" readonly>Reviewer &amp; editor' . "\n" . 'note</textarea><button name="action" value="publish">Publish <strong>now</strong></button><button disabled type="reset">Clear</button></form>', $html);
    },
    'summarizes html button type defaulting provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="buttons" action="/submit"><button id="missing" name="decision" value="approve">Approve</button>'
                . '<button id="empty" type="" name="empty">Empty</button><button id="reset" type=" RESET " name="clear">Clear</button>'
                . '<button id="invalid" type="menu" name="bad" formaction="/bad">Bad</button>'
                . '<button id="command" type="bogus" commandfor="panel" command="show-popover">Show panel</button></form><section id="panel" popover>Panel</section>',
            'button type provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/button-type-provenance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $missing = $form['children'][0];
        $empty = $form['children'][1];
        $reset = $form['children'][2];
        $invalid = $form['children'][3];
        $command = $form['children'][4];

        $t->same('form', $form['formSubmission']);
        $t->same(5, $form['controlCount']);
        $t->same(['decision', 'empty', 'clear', 'bad'], $form['controlNames']);

        $t->same(null, $missing['buttonTypeRaw']);
        $t->same('submit', $missing['buttonType']);
        $t->same('missing', $missing['buttonTypeState']);
        $t->same(true, $missing['buttonTypeValid']);
        $t->same(false, $missing['buttonTypeKnown']);
        $t->same(true, $missing['buttonTypeDefaulted']);
        $t->same(true, $missing['buttonTypeMissingDefaulted']);
        $t->same(false, $missing['buttonTypeInvalidValueDefaulted']);
        $t->same(true, $missing['buttonSubmitButton']);
        $t->same('approve', $missing['value']);
        $t->same(null, $missing['submitter']['formAction']);

        $t->same('', $empty['buttonTypeRaw']);
        $t->same('submit', $empty['buttonType']);
        $t->same('empty', $empty['buttonTypeState']);
        $t->same(false, $empty['buttonTypeValid']);
        $t->same(true, $empty['buttonTypeDefaulted']);
        $t->same(false, $empty['buttonTypeMissingDefaulted']);
        $t->same(true, $empty['buttonTypeInvalidValueDefaulted']);
        $t->same(true, $empty['buttonSubmitButton']);

        $t->same(' RESET ', $reset['buttonTypeRaw']);
        $t->same('reset', $reset['buttonType']);
        $t->same('reset', $reset['buttonTypeState']);
        $t->same(true, $reset['buttonTypeValid']);
        $t->same(true, $reset['buttonTypeKnown']);
        $t->same(false, $reset['buttonTypeDefaulted']);
        $t->same(false, $reset['buttonSubmitButton']);
        $t->true(!array_key_exists('submitter', $reset));

        $t->same('menu', $invalid['buttonTypeRaw']);
        $t->same('submit', $invalid['buttonType']);
        $t->same('invalid', $invalid['buttonTypeState']);
        $t->same(false, $invalid['buttonTypeValid']);
        $t->same(false, $invalid['buttonTypeKnown']);
        $t->same(true, $invalid['buttonTypeDefaulted']);
        $t->same(true, $invalid['buttonTypeInvalidValueDefaulted']);
        $t->same(true, $invalid['buttonSubmitButton']);
        $t->same('/bad', $invalid['submitter']['formAction']);

        $t->same('bogus', $command['buttonTypeRaw']);
        $t->same('submit', $command['buttonType']);
        $t->same('invalid', $command['buttonTypeState']);
        $t->same(false, $command['buttonTypeValid']);
        $t->same(true, $command['buttonTypeDefaulted']);
        $t->same(false, $command['buttonSubmitButton']);
        $t->true(!array_key_exists('submitter', $command));
        $t->same('show-popover', $command['command']);
        $t->same('popover', $command['commandTargetKind']);
        $t->same(true, $command['commandInvokesTarget']);

        $t->same(
            '<form action="/submit" id="buttons"><button id="missing" name="decision" value="approve">Approve</button>'
                . '<button id="empty" name="empty" type="">Empty</button><button id="reset" name="clear" type=" RESET ">Clear</button>'
                . '<button formaction="/bad" id="invalid" name="bad" type="menu">Bad</button>'
                . '<button command="show-popover" commandfor="panel" id="command" type="bogus">Show panel</button></form><section id="panel" popover="">Panel</section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/button-type-provenance-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html form control constraint attributes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="constraints"><label for="slug">Slug</label><input id="slug" name="slug" type="text" value="post-42" minlength="3" maxlength="12" pattern="[a-z0-9-]+" autocomplete="section-review shipping url" dirname="slug.dir" required readonly size="24">'
                . '<input id="score" name="score" type="number" min="-5" max="10" step="0.5" value="4"><input id="any-step" name="any" type="number" min="bad" max="20" step="any">'
                . '<textarea id="summary" name="summary" minlength="10" maxlength="5" dirname="summary.dir" autocomplete="bad&lt;tag" required>Text</textarea>'
                . '<select id="choices" name="choices" multiple size="3" autocomplete="off"><option selected>A</option><option>B</option></select></form>',
            'form constraint review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-constraints-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $slug = $form['children'][1];
        $score = $form['children'][2];
        $anyStep = $form['children'][3];
        $textarea = $form['children'][4];
        $select = $form['children'][5];

        $t->same('form-control', $slug['constraintValidation']);
        $t->same(true, $slug['required']);
        $t->same(true, $slug['readonly']);
        $t->same('3', $slug['minLengthRaw']);
        $t->same(3, $slug['minLength']);
        $t->same(true, $slug['minLengthValid']);
        $t->same('12', $slug['maxLengthRaw']);
        $t->same(12, $slug['maxLength']);
        $t->same(true, $slug['maxLengthValid']);
        $t->same(true, $slug['lengthRangeValid']);
        $t->same('[a-z0-9-]+', $slug['patternRaw']);
        $t->same(strlen('[a-z0-9-]+'), $slug['patternLength']);
        $t->same('pattern-source-no-regex-execution', $slug['patternReviewPolicy']);
        $t->same('section-review shipping url', $slug['autocompleteRaw']);
        $t->same(['section-review', 'shipping', 'url'], $slug['autocompleteTokens']);
        $t->same(['section-review', 'shipping', 'url'], $slug['autocompleteNormalizedTokens']);
        $t->same('detail', $slug['autocompleteState']);
        $t->same(true, $slug['autocompleteValid']);
        $t->same('slug.dir', $slug['dirnameRaw']);
        $t->same('slug.dir', $slug['dirname']);
        $t->same(true, $slug['dirnameValid']);
        $t->same('24', $slug['controlSizeRaw']);
        $t->same(24, $slug['controlSize']);
        $t->same(true, $slug['controlSizeValid']);

        $t->same('number', $score['inputType']);
        $t->same('-5', $score['constraintMinRaw']);
        $t->same(-5.0, $score['constraintMin']);
        $t->same(true, $score['constraintMinValid']);
        $t->same('10', $score['constraintMaxRaw']);
        $t->same(10.0, $score['constraintMax']);
        $t->same(true, $score['constraintMaxValid']);
        $t->same(true, $score['constraintRangeValid']);
        $t->same('0.5', $score['constraintStepRaw']);
        $t->same(0.5, $score['constraintStep']);
        $t->same(true, $score['constraintStepValid']);

        $t->same('bad', $anyStep['constraintMinRaw']);
        $t->same(null, $anyStep['constraintMin']);
        $t->same(false, $anyStep['constraintMinValid']);
        $t->same(20.0, $anyStep['constraintMax']);
        $t->same(null, $anyStep['constraintRangeValid']);
        $t->same('any', $anyStep['constraintStep']);
        $t->same(true, $anyStep['constraintStepValid']);

        $t->same('textarea', $textarea['formControl']);
        $t->same(true, $textarea['required']);
        $t->same(10, $textarea['minLength']);
        $t->same(5, $textarea['maxLength']);
        $t->same(false, $textarea['lengthRangeValid']);
        $t->same('bad<tag', $textarea['autocompleteRaw']);
        $t->same(['bad<tag'], $textarea['invalidAutocompleteTokens']);
        $t->same(false, $textarea['autocompleteValid']);
        $t->same('summary.dir', $textarea['dirname']);
        $t->same(true, $textarea['dirnameValid']);

        $t->same('select', $select['formControl']);
        $t->same(true, $select['multiple']);
        $t->same('3', $select['controlSizeRaw']);
        $t->same(3, $select['controlSize']);
        $t->same('off', $select['autocompleteState']);
        $t->same(true, $select['autocompleteValid']);
        $t->same(['A'], $select['selectedValues']);

        $t->same('<form id="constraints"><label for="slug">Slug</label><input autocomplete="section-review shipping url" dirname="slug.dir" id="slug" maxlength="12" minlength="3" name="slug" pattern="[a-z0-9-]+" readonly required size="24" type="text" value="post-42"><input id="score" max="10" min="-5" name="score" step="0.5" type="number" value="4"><input id="any-step" max="20" min="bad" name="any" step="any" type="number"><textarea autocomplete="bad&lt;tag" dirname="summary.dir" id="summary" maxlength="5" minlength="10" name="summary" required>Text</textarea><select autocomplete="off" id="choices" multiple name="choices" size="3"><option selected>A</option><option>B</option></select></form>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-constraints-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html form submission state and submitter overrides for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="remote-review" action="https://forms.example.invalid/submit" method="POST" enctype="multipart/form-data" target="_blank" autocomplete="off" accept-charset="UTF-8 ISO-8859-1" novalidate><input name="title" value="Packet"><input type="image" src="submit.png" formaction="/image-submit" formmethod="POST" formenctype="multipart/form-data" formtarget="_parent" formnovalidate><button type="submit" formaction="/local-submit" formmethod="dialog" formenctype="text/plain" formtarget="_self" formnovalidate>Send</button></form>'
                . '<form id="invalid-method" method="TRACE" enctype="application/json" autocomplete="maybe"><button>Default</button></form>',
            'form submission review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $imageSubmitter = $form['children'][1];
        $buttonSubmitter = $form['children'][2];
        $fallbackForm = $summary[1];
        $defaultButton = $fallbackForm['children'][0];

        $t->same('form', $form['name']);
        $t->same('form', $form['formSubmission']);
        $t->same('https://forms.example.invalid/submit', $form['action']);
        $t->same('post', $form['method']);
        $t->same('multipart/form-data', $form['enctype']);
        $t->same('_blank', $form['target']);
        $t->same('off', $form['autocomplete']);
        $t->same(true, $form['novalidate']);
        $t->same('UTF-8 ISO-8859-1', $form['acceptCharsetRaw']);
        $t->same(['UTF-8', 'ISO-8859-1'], $form['acceptCharsets']);
        $t->same('image', $imageSubmitter['inputType']);
        $t->same([
            'form' => null,
            'formAction' => '/image-submit',
            'formMethod' => 'post',
            'formEnctype' => 'multipart/form-data',
            'formTarget' => '_parent',
            'formNoValidate' => true,
        ], $imageSubmitter['submitter']);
        $t->same('submit', $buttonSubmitter['buttonType']);
        $t->same([
            'form' => null,
            'formAction' => '/local-submit',
            'formMethod' => 'dialog',
            'formEnctype' => 'text/plain',
            'formTarget' => '_self',
            'formNoValidate' => true,
        ], $buttonSubmitter['submitter']);
        $t->same('get', $fallbackForm['method']);
        $t->same('application/x-www-form-urlencoded', $fallbackForm['enctype']);
        $t->same('on', $fallbackForm['autocomplete']);
        $t->same(false, $fallbackForm['novalidate']);
        $t->same(null, $fallbackForm['acceptCharsetRaw']);
        $t->same([], $fallbackForm['acceptCharsets']);
        $t->same([
            'form' => null,
            'formAction' => null,
            'formMethod' => null,
            'formEnctype' => null,
            'formTarget' => null,
            'formNoValidate' => false,
        ], $defaultButton['submitter']);
        $t->same('<form accept-charset="UTF-8 ISO-8859-1" action="https://forms.example.invalid/submit" autocomplete="off" enctype="multipart/form-data" id="remote-review" method="POST" novalidate target="_blank"><input name="title" value="Packet"><input formaction="/image-submit" formenctype="multipart/form-data" formmethod="POST" formnovalidate formtarget="_parent" src="submit.png" type="image"><button formaction="/local-submit" formenctype="text/plain" formmethod="dialog" formnovalidate formtarget="_self" type="submit">Send</button></form><form autocomplete="maybe" enctype="application/json" id="invalid-method" method="TRACE"><button>Default</button></form>', $html);
    },
    'summarizes html form reset controls for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="draft"><input id="title" name="title" value="Draft"><input id="publish" type="checkbox" name="publish" checked>'
                . '<textarea id="notes" name="notes">Original note</textarea><select id="state" name="state"><option value="draft" selected>Draft</option><option value="review">Review</option></select>'
                . '<button id="reset-button" type="reset">Clear form</button><input id="reset-input" type="reset" value="Reset all"></form>'
                . '<input id="remote" name="remote" value="Remote" form="draft"><button id="orphan-reset" type="reset" form="missing">Missing form</button>'
                . '<button id="disabled-reset" type="reset" disabled form="draft">Disabled reset</button>',
            'form reset control review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-reset-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $buttonReset = $form['children'][4];
        $inputReset = $form['children'][5];
        $orphanReset = $summary[2];
        $disabledReset = $summary[3];
        $expectedControls = [
            [
                'tag' => 'input',
                'id' => 'title',
                'controlName' => 'title',
                'formOwnerSource' => 'ancestor',
                'effectiveDisabled' => false,
                'type' => 'text',
                'defaultValue' => 'Draft',
            ],
            [
                'tag' => 'input',
                'id' => 'publish',
                'controlName' => 'publish',
                'formOwnerSource' => 'ancestor',
                'effectiveDisabled' => false,
                'type' => 'checkbox',
                'defaultValue' => '',
                'defaultChecked' => true,
            ],
            [
                'tag' => 'textarea',
                'id' => 'notes',
                'controlName' => 'notes',
                'formOwnerSource' => 'ancestor',
                'effectiveDisabled' => false,
                'defaultValue' => 'Original note',
            ],
            [
                'tag' => 'select',
                'id' => 'state',
                'controlName' => 'state',
                'formOwnerSource' => 'ancestor',
                'effectiveDisabled' => false,
                'defaultSelectedValues' => ['draft'],
                'optionCount' => 2,
            ],
            [
                'tag' => 'input',
                'id' => 'remote',
                'controlName' => 'remote',
                'formOwnerSource' => 'form-attribute',
                'effectiveDisabled' => false,
                'type' => 'text',
                'defaultValue' => 'Remote',
            ],
        ];

        $t->same(8, $form['controlCount']);
        $t->same(2, $form['externalControlCount']);
        $t->same(['title', 'publish', 'notes', 'state', 'remote'], $form['controlNames']);

        $t->same('form-reset-control-review', $buttonReset['formResetReviewPolicy']);
        $t->same('button', $buttonReset['formResetControl']);
        $t->same('draft', $buttonReset['formResetFormOwnerId']);
        $t->same(true, $buttonReset['formResetFormOwnerFound']);
        $t->same(true, $buttonReset['formResetWouldReset']);
        $t->same(false, $buttonReset['formResetEffectiveDisabled']);
        $t->same(5, $buttonReset['formResetControlCount']);
        $t->same(['title', 'publish', 'notes', 'state', 'remote'], $buttonReset['formResetControlNames']);
        $t->same(['input', 'input', 'textarea', 'select', 'input'], $buttonReset['formResetControlTags']);
        $t->same(['title', 'publish', 'notes', 'state', 'remote'], $buttonReset['formResetControlIds']);
        $t->same($expectedControls, $buttonReset['formResetControls']);

        $t->same('reset', $inputReset['inputType']);
        $t->same('form-reset-control-review', $inputReset['formResetReviewPolicy']);
        $t->same('input', $inputReset['formResetControl']);
        $t->same(['title', 'publish', 'notes', 'state', 'remote'], $inputReset['formResetControlIds']);
        $t->same(true, $inputReset['formResetWouldReset']);

        $t->same('form-reset-control-review', $orphanReset['formResetReviewPolicy']);
        $t->same(false, $orphanReset['formResetFormOwnerFound']);
        $t->same(false, $orphanReset['formResetWouldReset']);
        $t->same(0, $orphanReset['formResetControlCount']);
        $t->same(['missing-form-owner'], $orphanReset['formResetIssueCodes']);

        $t->same('draft', $disabledReset['formResetFormOwnerId']);
        $t->same(true, $disabledReset['formResetEffectiveDisabled']);
        $t->same(false, $disabledReset['formResetWouldReset']);
        $t->same(5, $disabledReset['formResetControlCount']);
        $t->same(['disabled-reset-control'], $disabledReset['formResetIssueCodes']);

        $t->same('<form id="draft"><input id="title" name="title" value="Draft"><input checked id="publish" name="publish" type="checkbox"><textarea id="notes" name="notes">Original note</textarea><select id="state" name="state"><option selected value="draft">Draft</option><option value="review">Review</option></select><button id="reset-button" type="reset">Clear form</button><input id="reset-input" type="reset" value="Reset all"></form><input form="draft" id="remote" name="remote" value="Remote"><button form="missing" id="orphan-reset" type="reset">Missing form</button><button disabled form="draft" id="disabled-reset" type="reset">Disabled reset</button>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-reset-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html form owner associations for remote controls' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="primary" action="/save" method="POST" enctype="multipart/form-data" target="_blank"><input id="inside" name="title" value="Draft"></form>'
                . '<label for="remote-title">Remote title</label><input id="remote-title" name="title" form="primary" value="Remote">'
                . '<select id="state" name="state" form="primary"><option value="draft">Draft<option selected value="review">Review</select>'
                . '<textarea id="orphan" name="notes" form="missing">Lost</textarea><button id="empty" form="">No form</button>'
                . '<form id="fallback"><button id="fallback-button" name="fallback" value="1">Fallback</button></form>',
            'remote form owner review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-owner-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $primary = $summary[0];
        $inside = $primary['children'][0];
        $remote = $summary[2];
        $select = $summary[3];
        $orphan = $summary[4];
        $empty = $summary[5];
        $fallback = $summary[6];
        $fallbackButton = $fallback['children'][0];

        $t->same('form', $primary['formSubmission']);
        $t->same(3, $primary['controlCount']);
        $t->same(2, $primary['externalControlCount']);
        $t->same(['title', 'state'], $primary['controlNames']);
        $t->same([
            [
                'tag' => 'input',
                'id' => 'inside',
                'controlName' => 'title',
                'formOwnerSource' => 'ancestor',
                'effectiveDisabled' => false,
                'type' => 'text',
                'value' => 'Draft',
                'checked' => false,
            ],
            [
                'tag' => 'input',
                'id' => 'remote-title',
                'controlName' => 'title',
                'formOwnerSource' => 'form-attribute',
                'effectiveDisabled' => false,
                'type' => 'text',
                'value' => 'Remote',
                'checked' => false,
            ],
            [
                'tag' => 'select',
                'id' => 'state',
                'controlName' => 'state',
                'formOwnerSource' => 'form-attribute',
                'effectiveDisabled' => false,
                'selectedValues' => ['review'],
            ],
        ], $primary['controls']);

        $t->same('ancestor', $inside['formOwnerSource']);
        $t->same(null, $inside['formOwnerRaw']);
        $t->same('primary', $inside['formOwnerId']);
        $t->same(true, $inside['formOwnerFound']);
        $t->same('/save', $inside['formOwnerAction']);
        $t->same('post', $inside['formOwnerMethod']);
        $t->same('multipart/form-data', $inside['formOwnerEnctype']);
        $t->same('_blank', $inside['formOwnerTarget']);

        $t->same('form-attribute', $remote['formOwnerSource']);
        $t->same('primary', $remote['formOwnerRaw']);
        $t->same('primary', $remote['formOwnerTargetId']);
        $t->same('primary', $remote['formOwnerId']);
        $t->same(['Remote title'], $remote['labels']);
        $t->same('/save', $remote['formOwnerAction']);

        $t->same('select', $select['formControl']);
        $t->same('form-attribute', $select['formOwnerSource']);
        $t->same(['review'], $select['selectedValues']);
        $t->same('post', $select['formOwnerMethod']);

        $t->same('missing-form-attribute', $orphan['formOwnerSource']);
        $t->same('missing', $orphan['formOwnerTargetId']);
        $t->same(false, $orphan['formOwnerFound']);
        $t->same(null, $orphan['formOwnerAction']);
        $t->same('missing-form-attribute', $empty['formOwnerSource']);
        $t->same(null, $empty['formOwnerTargetId']);
        $t->same(false, $empty['formOwnerFound']);

        $t->same(1, $fallback['controlCount']);
        $t->same(['fallback'], $fallback['controlNames']);
        $t->same('ancestor', $fallbackButton['formOwnerSource']);
        $t->same('fallback', $fallbackButton['formOwnerId']);
        $t->same('<form action="/save" enctype="multipart/form-data" id="primary" method="POST" target="_blank"><input id="inside" name="title" value="Draft"></form><label for="remote-title">Remote title</label><input form="primary" id="remote-title" name="title" value="Remote"><select form="primary" id="state" name="state"><option value="draft">Draft</option><option selected value="review">Review</option></select><textarea form="missing" id="orphan" name="notes">Lost</textarea><button form="" id="empty">No form</button><form id="fallback"><button id="fallback-button" name="fallback" value="1">Fallback</button></form>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-owner-review.html', $document->children[0]->attr('part'));
    },
    'diagnoses html form owner idref target provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="primary" action="/save" method="POST"><input id="inside" name="inside"></form>'
                . '<input id="remote" name="remote" form="primary">'
                . '<section id="panel">Panel</section><input id="non-form" form="panel">'
                . '<input id="missing-owner" form="missing">'
                . '<input id="empty-owner" form="">'
                . '<input id="invalid-owner" form="bad id">'
                . '<form id="dupe" action="/first"><button name="first">First</button></form>'
                . '<form id="dupe" action="/second"></form><button id="dupe-button" form="dupe">Duplicate</button>',
            'form owner idref provenance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-owner-idref-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $remote = $summary[1];
        $nonForm = $summary[3];
        $missing = $summary[4];
        $empty = $summary[5];
        $invalid = $summary[6];
        $dupeButton = $summary[9];

        $t->same('form-owner-idref-review', $remote['formOwnerReviewPolicy']);
        $t->same('primary', $remote['formOwnerTargetId']);
        $t->same(true, $remote['formOwnerTargetValid']);
        $t->same('resolved', $remote['formOwnerResolutionState']);
        $t->same(true, $remote['formOwnerResolved']);
        $t->same(1, $remote['formOwnerTargetCount']);
        $t->same(1, $remote['formOwnerFormTargetCount']);
        $t->same(['form'], $remote['formOwnerTargetElementNames']);
        $t->same([], $remote['formOwnerIssueCodes']);
        $t->same('form', $remote['formOwnerTargets'][0]['tag']);
        $t->same(true, $remote['formOwnerTargets'][0]['selectedFormOwner']);
        $t->same('/save', $remote['formOwnerTargets'][0]['action']);
        $t->same('post', $remote['formOwnerTargets'][0]['method']);

        $t->same('missing-form-attribute', $nonForm['formOwnerSource']);
        $t->same(false, $nonForm['formOwnerFound']);
        $t->same('non-form-target', $nonForm['formOwnerResolutionState']);
        $t->same(false, $nonForm['formOwnerResolved']);
        $t->same(1, $nonForm['formOwnerTargetCount']);
        $t->same(0, $nonForm['formOwnerFormTargetCount']);
        $t->same(['section'], $nonForm['formOwnerTargetElementNames']);
        $t->same(['non-form-owner-target'], $nonForm['formOwnerIssueCodes']);
        $t->same('section', $nonForm['formOwnerTargets'][0]['tag']);
        $t->same('Panel', $nonForm['formOwnerTargets'][0]['text']);

        $t->same('missing-target', $missing['formOwnerResolutionState']);
        $t->same(false, $missing['formOwnerResolved']);
        $t->same(0, $missing['formOwnerTargetCount']);
        $t->same(['missing-form-owner-target'], $missing['formOwnerIssueCodes']);

        $t->same(null, $empty['formOwnerTargetId']);
        $t->same(false, $empty['formOwnerTargetValid']);
        $t->same('empty-reference', $empty['formOwnerResolutionState']);
        $t->same(['empty-form-owner-reference'], $empty['formOwnerIssueCodes']);

        $t->same('bad id', $invalid['formOwnerTargetId']);
        $t->same(false, $invalid['formOwnerTargetValid']);
        $t->same('invalid-reference', $invalid['formOwnerResolutionState']);
        $t->same(['invalid-form-owner-reference'], $invalid['formOwnerIssueCodes']);

        $t->same('form-attribute', $dupeButton['formOwnerSource']);
        $t->same(true, $dupeButton['formOwnerFound']);
        $t->same('/first', $dupeButton['formOwnerAction']);
        $t->same('duplicate-target-id', $dupeButton['formOwnerResolutionState']);
        $t->same(false, $dupeButton['formOwnerResolved']);
        $t->same(2, $dupeButton['formOwnerTargetCount']);
        $t->same(2, $dupeButton['formOwnerFormTargetCount']);
        $t->same(['duplicate-form-owner-target-id'], $dupeButton['formOwnerIssueCodes']);
        $t->same([true, false], array_map(
            static fn (array $target): bool => (bool) $target['selectedFormOwner'],
            $dupeButton['formOwnerTargets']
        ));
        $t->same(['/first', '/second'], array_map(
            static fn (array $target): ?string => $target['action'] ?? null,
            $dupeButton['formOwnerTargets']
        ));

        json_encode([$remote, $nonForm, $missing, $empty, $invalid, $dupeButton], JSON_THROW_ON_ERROR);
        $t->contains($html, $blocks);
        $t->same('/migration/form-owner-idref-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html output control state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="calc-form"><input id="source-a" name="a" value="5"><button id="source-b" type="button">Add</button><label for="checksum">Checksum</label><label>Total <output id="checksum" name="checksum" for="source-a  source-b missing">Ready <strong>hash</strong></output></label></form>',
            'output control review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $output = $form['children'][3]['children'][1];

        $t->same('output', $output['name']);
        $t->same('output', $output['formControl']);
        $t->same(['Checksum', 'Total Ready hash'], $output['labels']);
        $t->same('Ready hash', $output['text']);
        $t->same('Ready hash', $output['value']);
        $t->same('source-a  source-b missing', $output['forRaw']);
        $t->same(['source-a', 'source-b', 'missing'], $output['forIds']);
        $t->same('<form id="calc-form"><input id="source-a" name="a" value="5"><button id="source-b" type="button">Add</button><label for="checksum">Checksum</label><label>Total <output for="source-a  source-b missing" id="checksum" name="checksum">Ready <strong>hash</strong></output></label></form>', $html);
    },
    'resolves html output for-token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="calc-form"><input id="source-a" name="a" value="5"><button id="source-b" type="button" name="add" value="+">Add</button><span id="note">Not a control</span><output id="checksum" name="checksum" for="source-a source-b source-a missing note bad<tag">Ready</output></form>',
            'output for-token review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/output-for-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $output = $form['children'][3];

        $t->same('output', $output['name']);
        $t->same('output-for-idref-review', $output['forReferenceReviewPolicy']);
        $t->same(['source-a', 'source-b', 'source-a', 'missing', 'note', 'bad<tag'], $output['forIds']);
        $t->same(['source-a', 'source-b', 'missing', 'note'], $output['forReferenceIds']);
        $t->same(['source-a', 'source-b'], $output['resolvedForReferenceIds']);
        $t->same(['source-a'], $output['duplicateForReferenceIds']);
        $t->same(['missing'], $output['missingForReferenceIds']);
        $t->same(['note'], $output['nonControlForReferenceIds']);
        $t->same(['bad<tag'], $output['invalidForReferenceTokens']);
        $t->same(2, $output['forControlReferenceCount']);
        $t->same(['a', 'add'], $output['forControlNames']);
        $t->same(false, $output['forReferencesResolved']);
        $t->same([
            'duplicate-output-for-reference-token',
            'missing-output-for-target',
            'non-control-output-for-target',
            'invalid-output-for-reference-token',
        ], $output['forReferenceIssueCodes']);

        $t->same('resolved-control', $output['forReferences'][0]['state']);
        $t->same('input', $output['forReferences'][0]['target']['tag']);
        $t->same('a', $output['forReferences'][0]['target']['controlName']);
        $t->same('text', $output['forReferences'][0]['target']['inputType']);
        $t->same('5', $output['forReferences'][0]['target']['value']);
        $t->same('resolved-control', $output['forReferences'][1]['state']);
        $t->same('button', $output['forReferences'][1]['target']['tag']);
        $t->same('button', $output['forReferences'][1]['target']['buttonType']);
        $t->same('Add', $output['forReferences'][1]['target']['label']);
        $t->same(true, $output['forReferences'][2]['duplicateToken']);
        $t->same(0, $output['forReferences'][2]['firstIndex']);
        $t->same('missing-target', $output['forReferences'][3]['state']);
        $t->same('non-control-target', $output['forReferences'][4]['state']);
        $t->same('span', $output['forReferences'][4]['target']['tag']);
        $t->same('Not a control', $output['forReferences'][4]['target']['text']);
        $t->same('invalid-token', $output['forReferences'][5]['state']);

        $t->same('duplicate-output-for-reference-token', $output['forReferenceIssues'][0]['code']);
        $t->same('source-a', $output['forReferenceIssues'][0]['token']);
        $t->same(2, $output['forReferenceIssues'][0]['index']);
        $t->same('missing-output-for-target', $output['forReferenceIssues'][1]['code']);
        $t->same('missing', $output['forReferenceIssues'][1]['token']);
        $t->same('non-control-output-for-target', $output['forReferenceIssues'][2]['code']);
        $t->same('span', $output['forReferenceIssues'][2]['targetName']);
        $t->same('invalid-output-for-reference-token', $output['forReferenceIssues'][3]['code']);
        $t->same('bad<tag', $output['forReferenceIssues'][3]['token']);
        $t->same('<form id="calc-form"><input id="source-a" name="a" value="5"><button id="source-b" name="add" type="button" value="+">Add</button><span id="note">Not a control</span><output for="source-a source-b source-a missing note bad&lt;tag" id="checksum" name="checksum">Ready</output></form>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/output-for-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html label control associations for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="labels"><label for="title">Title <span>required</span></label><input id="title" name="title" value="Draft"><label>Wrapped <textarea id="notes" name="notes">Note</textarea></label><label for="missing">Missing</label><label for="save">Explicit <button id="ignored" name="ignored">Ignored</button></label><button id="save" name="save" disabled>Save</button><label><input type="hidden" id="secret" name="secret" value="x"> Hidden</label></form>',
            'label association review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $explicitLabel = $form['children'][0];
        $wrappedLabel = $form['children'][2];
        $missingLabel = $form['children'][3];
        $overrideLabel = $form['children'][4];
        $hiddenLabel = $form['children'][6];

        $t->same('label', $explicitLabel['formLabel']);
        $t->same('Title required', $explicitLabel['labelText']);
        $t->same('title', $explicitLabel['forRaw']);
        $t->same('title', $explicitLabel['forId']);
        $t->same('for-attribute', $explicitLabel['labeledControlSource']);
        $t->same([
            'tag' => 'input',
            'id' => 'title',
            'controlName' => 'title',
            'effectiveDisabled' => false,
            'type' => 'text',
        ], $explicitLabel['labeledControl']);
        $t->same(0, $explicitLabel['nestedControlCount']);
        $t->same([], $explicitLabel['nestedControls']);

        $t->same('label', $wrappedLabel['formLabel']);
        $t->same('Wrapped Note', $wrappedLabel['labelText']);
        $t->same(null, $wrappedLabel['forRaw']);
        $t->same(null, $wrappedLabel['forId']);
        $t->same('descendant', $wrappedLabel['labeledControlSource']);
        $t->same([
            'tag' => 'textarea',
            'id' => 'notes',
            'controlName' => 'notes',
            'effectiveDisabled' => false,
        ], $wrappedLabel['labeledControl']);
        $t->same(1, $wrappedLabel['nestedControlCount']);
        $t->same([$wrappedLabel['labeledControl']], $wrappedLabel['nestedControls']);

        $t->same('missing-for-target', $missingLabel['labeledControlSource']);
        $t->same('missing', $missingLabel['forId']);
        $t->same(null, $missingLabel['labeledControl']);

        $t->same('for-attribute', $overrideLabel['labeledControlSource']);
        $t->same([
            'tag' => 'button',
            'id' => 'save',
            'controlName' => 'save',
            'effectiveDisabled' => true,
            'type' => 'submit',
        ], $overrideLabel['labeledControl']);
        $t->same(1, $overrideLabel['nestedControlCount']);
        $t->same([
            [
                'tag' => 'button',
                'id' => 'ignored',
                'controlName' => 'ignored',
                'effectiveDisabled' => false,
                'type' => 'submit',
            ],
        ], $overrideLabel['nestedControls']);

        $t->same('Hidden', $hiddenLabel['labelText']);
        $t->same('missing', $hiddenLabel['labeledControlSource']);
        $t->same(null, $hiddenLabel['labeledControl']);
        $t->same(0, $hiddenLabel['nestedControlCount']);
        $t->same('<form id="labels"><label for="title">Title <span>required</span></label><input id="title" name="title" value="Draft"><label>Wrapped <textarea id="notes" name="notes">Note</textarea></label><label for="missing">Missing</label><label for="save">Explicit <button id="ignored" name="ignored">Ignored</button></label><button disabled id="save" name="save">Save</button><label><input id="secret" name="secret" type="hidden" value="x"> Hidden</label></form>', $html);
    },
    'summarizes html form labels datalist and inherited disabled state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="import-form"><label for="format">Format</label><input id="format" name="format" list="format-options" required placeholder="Choose format"><datalist id="format-options"><option value="docx" label="Word"></option><option value="epub">EPUB</option><option>ODT</option></datalist><fieldset disabled><legend>Batch <button id="legend-action">Keep enabled</button></legend><label>Confirm <input id="confirm" name="confirm" type="checkbox" checked></label><select id="state" name="state" required><option value="draft">Draft</option></select><textarea id="notes" name="notes" placeholder="Reviewer note">Ready</textarea><button id="submit" type="submit" name="save" value="1">Save</button></fieldset></form>',
            'form label and datalist review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $form = $summary[0];
        $formatInput = $form['children'][1];
        $datalist = $form['children'][2];
        $fieldset = $form['children'][3];
        $legend = $fieldset['children'][0];
        $legendButton = $legend['children'][1];
        $confirmInput = $fieldset['children'][1]['children'][1];
        $stateSelect = $fieldset['children'][2];
        $notes = $fieldset['children'][3];
        $submitButton = $fieldset['children'][4];
        $expectedOptions = [
            ['value' => 'docx', 'label' => 'Word', 'text' => '', 'disabled' => false],
            ['value' => 'epub', 'label' => 'EPUB', 'text' => 'EPUB', 'disabled' => false],
            ['value' => 'ODT', 'label' => 'ODT', 'text' => 'ODT', 'disabled' => false],
        ];

        $t->same('form', $form['name']);
        $t->same('input', $formatInput['formControl']);
        $t->same(['Format'], $formatInput['labels']);
        $t->same(true, $formatInput['required']);
        $t->same('Choose format', $formatInput['placeholder']);
        $t->same(false, $formatInput['effectiveDisabled']);
        $t->same('format-options', $formatInput['list']);
        $t->same($expectedOptions, $formatInput['datalistOptions']);
        $t->same('datalist', $datalist['formControl']);
        $t->same($expectedOptions, $datalist['datalistOptions']);
        $t->same(['disabled' => 'disabled'], $fieldset['attributes']);
        $t->same('fieldset', $fieldset['formGroup']);
        $t->same(true, $fieldset['disabled']);
        $t->same('Batch Keep enabled', $fieldset['legendText']);
        $t->same(1, $fieldset['legendCount']);
        $t->same(5, $fieldset['controlCount']);
        $t->same(1, $fieldset['legendControlCount']);
        $t->same(['confirm', 'state', 'notes', 'save'], $fieldset['controlNames']);
        $t->same([
            [
                'tag' => 'button',
                'id' => 'legend-action',
                'controlName' => null,
                'effectiveDisabled' => false,
                'inFirstLegend' => true,
                'type' => 'submit',
            ],
            [
                'tag' => 'input',
                'id' => 'confirm',
                'controlName' => 'confirm',
                'effectiveDisabled' => true,
                'inFirstLegend' => false,
                'type' => 'checkbox',
            ],
            [
                'tag' => 'select',
                'id' => 'state',
                'controlName' => 'state',
                'effectiveDisabled' => true,
                'inFirstLegend' => false,
            ],
            [
                'tag' => 'textarea',
                'id' => 'notes',
                'controlName' => 'notes',
                'effectiveDisabled' => true,
                'inFirstLegend' => false,
            ],
            [
                'tag' => 'button',
                'id' => 'submit',
                'controlName' => 'save',
                'effectiveDisabled' => true,
                'inFirstLegend' => false,
                'type' => 'submit',
            ],
        ], $fieldset['controls']);
        $t->same('legend', $legend['formGroupPart']);
        $t->same('Batch Keep enabled', $legend['legendText']);
        $t->same(true, $legend['fieldsetDisabled']);
        $t->same(true, $legend['firstLegend']);
        $t->same('button', $legendButton['formControl']);
        $t->same(false, $legendButton['effectiveDisabled']);
        $t->same('input', $confirmInput['formControl']);
        $t->same(['Confirm'], $confirmInput['labels']);
        $t->same(true, $confirmInput['checked']);
        $t->same(false, $confirmInput['disabled']);
        $t->same(true, $confirmInput['effectiveDisabled']);
        $t->same('select', $stateSelect['formControl']);
        $t->same(true, $stateSelect['required']);
        $t->same(true, $stateSelect['effectiveDisabled']);
        $t->same('textarea', $notes['formControl']);
        $t->same('Reviewer note', $notes['placeholder']);
        $t->same(true, $notes['effectiveDisabled']);
        $t->same('button', $submitButton['formControl']);
        $t->same(true, $submitButton['effectiveDisabled']);
        $t->same('<form id="import-form"><label for="format">Format</label><input id="format" list="format-options" name="format" placeholder="Choose format" required><datalist id="format-options"><option label="Word" value="docx"></option><option value="epub">EPUB</option><option>ODT</option></datalist><fieldset disabled><legend>Batch <button id="legend-action">Keep enabled</button></legend><label>Confirm <input checked id="confirm" name="confirm" type="checkbox"></label><select id="state" name="state" required><option value="draft">Draft</option></select><textarea id="notes" name="notes" placeholder="Reviewer note">Ready</textarea><button id="submit" name="save" type="submit" value="1">Save</button></fieldset></form>', $html);
    },
    'summarizes html input datalist idref associations for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form>'
                . '<input id="color" name="color" list="colors">'
                . '<input id="missing" name="missing" list="missing-options">'
                . '<input id="invalid" name="invalid" list="bad id">'
                . '<input id="duplicate" name="duplicate" list="dupe-options">'
                . '<datalist id="colors"><option value="red" label="Red"></option><option>Blue</option></datalist>'
                . '<datalist id="dupe-options"><option value="csv"></option></datalist>'
                . '<datalist id="dupe-options"><option value="tsv"></option></datalist>'
                . '</form>',
            'input datalist association review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);

        $form = $summary[0];
        $resolved = $form['children'][0];
        $missing = $form['children'][1];
        $invalid = $form['children'][2];
        $duplicate = $form['children'][3];
        $expectedOptions = [
            ['value' => 'red', 'label' => 'Red', 'text' => '', 'disabled' => false],
            ['value' => 'Blue', 'label' => 'Blue', 'text' => 'Blue', 'disabled' => false],
        ];

        $t->same('input-list-datalist-idref-review', $resolved['datalistReviewPolicy']);
        $t->same('colors', $resolved['listReferenceRaw']);
        $t->same('colors', $resolved['listReferenceId']);
        $t->same(true, $resolved['listReferenceValid']);
        $t->same('resolved', $resolved['datalistAssociationState']);
        $t->same(true, $resolved['datalistResolved']);
        $t->same(1, $resolved['datalistTargetCount']);
        $t->same(2, $resolved['datalistOptionCount']);
        $t->same($expectedOptions, $resolved['datalistOptions']);
        $t->same('colors', $resolved['datalistTargets'][0]['id']);
        $t->same(['red', 'Blue'], $resolved['datalistTargets'][0]['optionValues']);
        $t->same([], $resolved['datalistIssues']);

        $t->same('missing-datalist', $missing['datalistAssociationState']);
        $t->same(false, $missing['datalistResolved']);
        $t->same(0, $missing['datalistTargetCount']);
        $t->same([], $missing['datalistOptions']);
        $t->same(['missing-datalist-target'], $missing['datalistIssueCodes']);
        $t->same('missing-options', $missing['datalistIssues'][0]['listReferenceId']);

        $t->same('invalid-reference', $invalid['datalistAssociationState']);
        $t->same(false, $invalid['listReferenceValid']);
        $t->same(0, $invalid['datalistTargetCount']);
        $t->same([], $invalid['datalistOptions']);
        $t->same(['invalid-datalist-list-reference'], $invalid['datalistIssueCodes']);
        $t->same('bad id', $invalid['datalistIssues'][0]['listReferenceRaw']);

        $t->same('duplicate-datalist', $duplicate['datalistAssociationState']);
        $t->same(false, $duplicate['datalistResolved']);
        $t->same(2, $duplicate['datalistTargetCount']);
        $t->same(1, $duplicate['datalistOptionCount']);
        $t->same([['value' => 'csv', 'label' => '', 'text' => '', 'disabled' => false]], $duplicate['datalistOptions']);
        $t->same(['csv'], $duplicate['datalistTargets'][0]['optionValues']);
        $t->same(['tsv'], $duplicate['datalistTargets'][1]['optionValues']);
        $t->same(['duplicate-datalist-target-id'], $duplicate['datalistIssueCodes']);
        $t->same(2, $duplicate['datalistIssues'][0]['count']);
        json_encode([$resolved, $missing, $invalid, $duplicate], JSON_THROW_ON_ERROR);
    },
    'summarizes html fieldset legend diagnostics and disabled control buckets for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form><fieldset id="outer" disabled><legend>Outer <button id="legend-action" name="legend-action">Go</button></legend><input id="title" name="title"><legend>Second <input id="second" name="second"></legend><fieldset id="inner"><legend>Inner</legend><textarea id="inner-note" name="inner-note">N</textarea></fieldset><button id="save" name="save">Save</button></fieldset><fieldset id="missing"><input id="missing-control" name="missing-control"></fieldset></form>',
            'fieldset legend diagnostics review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);

        $outer = $summary[0]['children'][0];
        $firstLegend = $outer['children'][0];
        $secondLegend = $outer['children'][2];
        $missing = $summary[0]['children'][1];

        $t->same('fieldset-legend-disabled-control-review', $outer['fieldsetReviewPolicy']);
        $t->same(['Outer Go', 'Second'], $outer['legendTexts']);
        $t->same(2, $outer['legendCount']);
        $t->same(1, $outer['enabledControlCount']);
        $t->same(4, $outer['disabledControlCount']);
        $t->same(['legend-action'], $outer['enabledControlNames']);
        $t->same(['title', 'second', 'inner-note', 'save'], $outer['disabledControlNames']);
        $t->same(1, $outer['nestedFieldsetCount']);
        $t->same('inner', $outer['nestedFieldsets'][0]['id']);
        $t->same(['multiple-fieldset-legends', 'nested-fieldset-review'], $outer['fieldsetIssueCodes']);
        $t->same(0, $firstLegend['fieldsetLegendIndex']);
        $t->same(2, $firstLegend['fieldsetLegendCount']);
        $t->same(false, $secondLegend['firstLegend']);
        $t->same(1, $secondLegend['fieldsetLegendIndex']);
        $t->same(['missing-fieldset-legend'], $missing['fieldsetIssueCodes']);
        $t->same(['missing-control'], $missing['enabledControlNames']);
    },
    'summarizes html progress and meter state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<label for="upload-progress">Upload</label><progress id="upload-progress" value="3" max="4">75%</progress><progress id="pending">Pending</progress><label>Quality <meter id="quality" value="0.82" min="0" max="1" low="0.4" high="0.9" optimum="0.95">82%</meter></label><meter id="clamped" value="12" min="2" max="10">Too high</meter>',
            'progress meter review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $progressLabel = $summary[0];
        $progress = $summary[1];
        $pending = $summary[2];
        $qualityLabel = $summary[3];
        $quality = $summary[3]['children'][1];
        $clamped = $summary[4];

        $t->same('label', $progressLabel['formLabel']);
        $t->same('for-attribute', $progressLabel['labeledControlSource']);
        $t->same('progress', $progressLabel['labeledControl']['tag']);
        $t->same('progress', $progressLabel['labeledControl']['measurement']);
        $t->same(3.0, $progressLabel['labeledControl']['value']);
        $t->same(4.0, $progressLabel['labeledControl']['max']);
        $t->same(0.75, $progressLabel['labeledControl']['position']);
        $t->same(false, $progressLabel['labeledControl']['indeterminate']);
        $t->same('progress', $progress['measurement']);
        $t->same(['Upload'], $progress['labels']);
        $t->same(3.0, $progress['value']);
        $t->same(4.0, $progress['max']);
        $t->same(0.75, $progress['position']);
        $t->same(false, $progress['indeterminate']);
        $t->same(null, $pending['value']);
        $t->same(null, $pending['position']);
        $t->same(true, $pending['indeterminate']);
        $t->same('label', $qualityLabel['formLabel']);
        $t->same('descendant', $qualityLabel['labeledControlSource']);
        $t->same('meter', $qualityLabel['labeledControl']['tag']);
        $t->same('meter', $qualityLabel['labeledControl']['measurement']);
        $t->same(0.82, $qualityLabel['labeledControl']['value']);
        $t->same(0.0, $qualityLabel['labeledControl']['min']);
        $t->same(1.0, $qualityLabel['labeledControl']['max']);
        $t->same(0.4, $qualityLabel['labeledControl']['low']);
        $t->same(0.9, $qualityLabel['labeledControl']['high']);
        $t->same(0.95, $qualityLabel['labeledControl']['optimum']);
        $t->same(1, $qualityLabel['nestedControlCount']);
        $t->same($qualityLabel['labeledControl'], $qualityLabel['nestedControls'][0]);
        $t->same('meter', $quality['measurement']);
        $t->same(['Quality 82%'], $quality['labels']);
        $t->same(0.82, $quality['value']);
        $t->same(0.0, $quality['min']);
        $t->same(1.0, $quality['max']);
        $t->same(0.4, $quality['low']);
        $t->same(0.9, $quality['high']);
        $t->same(0.95, $quality['optimum']);
        $t->same('meter', $clamped['measurement']);
        $t->same(10.0, $clamped['value']);
        $t->same(2.0, $clamped['min']);
        $t->same(10.0, $clamped['max']);
        $t->same('<label for="upload-progress">Upload</label><progress id="upload-progress" max="4" value="3">75%</progress><progress id="pending">Pending</progress><label>Quality <meter high="0.9" id="quality" low="0.4" max="1" min="0" optimum="0.95" value="0.82">82%</meter></label><meter id="clamped" max="10" min="2" value="12">Too high</meter>', $html);
    },
    'summarizes html disclosure state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<details id="packet" name="review" open><summary id="primary-summary">Package <span>review</span></summary><summary id="secondary-summary">Secondary label</summary><p>Body</p></details>'
                . '<details id="review-next" name=" review " open><summary>Next packet</summary></details>'
                . '<details id="missing-summary"><p>No summary</p></details>'
                . '<summary id="loose-summary">Loose label</summary>',
            'disclosure review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/disclosure-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $details = $summary[0];
        $detailsSummary = $details['children'][0];
        $secondarySummary = $details['children'][1];
        $secondDetails = $summary[1];
        $missingSummary = $summary[2];
        $looseSummary = $summary[3];

        $t->same('details', $details['name']);
        $t->same('details', $details['disclosure']);
        $t->same(true, $details['open']);
        $t->same('open', $details['detailsState']);
        $t->same('review', $details['detailsNameRaw']);
        $t->same('review', $details['detailsName']);
        $t->same(1, $details['detailsGroupIndex']);
        $t->same(2, $details['detailsGroupSize']);
        $t->same(2, $details['detailsGroupOpenCount']);
        $t->same(true, $details['detailsGroupOpenConflict']);
        $t->same('Package review', $details['summaryText']);
        $t->same('primary-summary', $details['primarySummaryId']);
        $t->same(2, $details['summaryElementCount']);
        $t->same([
            ['index' => 0, 'id' => 'primary-summary', 'text' => 'Package review', 'primary' => true, 'childElementCount' => 1],
            ['index' => 1, 'id' => 'secondary-summary', 'text' => 'Secondary label', 'primary' => false, 'childElementCount' => 0],
        ], $details['summaryElements']);
        $t->same('summary', $detailsSummary['name']);
        $t->same('summary', $detailsSummary['disclosure']);
        $t->same('Package review', $detailsSummary['label']);
        $t->same('packet', $detailsSummary['summaryForDetailsId']);
        $t->same('review', $detailsSummary['summaryForDetailsName']);
        $t->same(0, $detailsSummary['summaryIndex']);
        $t->same(true, $detailsSummary['summaryPrimary']);
        $t->same(1, $secondarySummary['summaryIndex']);
        $t->same(false, $secondarySummary['summaryPrimary']);

        $t->same(' review ', $secondDetails['detailsNameRaw']);
        $t->same('review', $secondDetails['detailsName']);
        $t->same(2, $secondDetails['detailsGroupIndex']);
        $t->same(true, $secondDetails['detailsGroupOpenConflict']);

        $t->same(false, $missingSummary['open']);
        $t->same('closed', $missingSummary['detailsState']);
        $t->same(null, $missingSummary['detailsName']);
        $t->same(0, $missingSummary['detailsGroupSize']);
        $t->same(null, $missingSummary['summaryText']);
        $t->same(0, $missingSummary['summaryElementCount']);
        $t->same([], $missingSummary['summaryElements']);

        $t->same('summary', $looseSummary['disclosure']);
        $t->same('Loose label', $looseSummary['label']);
        $t->same(null, $looseSummary['summaryForDetailsId']);
        $t->same(null, $looseSummary['summaryIndex']);
        $t->same(null, $looseSummary['summaryPrimary']);
        $t->same('<details id="packet" name="review" open><summary id="primary-summary">Package <span>review</span></summary><summary id="secondary-summary">Secondary label</summary><p>Body</p></details><details id="review-next" name=" review " open><summary>Next packet</summary></details><details id="missing-summary"><p>No summary</p></details><summary id="loose-summary">Loose label</summary>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/disclosure-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html dialog and popover state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<dialog id="confirm" open popover="manual" aria-modal="true"><form method="dialog"><button value="ok">OK</button><button popovertarget="details-popover" popovertargetaction="show">More</button></form></dialog>'
                . '<aside id="details-popover" popover="auto">Extra</aside>'
                . '<button popovertarget="bad target" popovertargetaction="dismiss">Bad</button>',
            'dialog popover review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $dialog = $summary[0];
        $form = $dialog['children'][0];
        $okButton = $form['children'][0];
        $moreButton = $form['children'][1];
        $popover = $summary[1];
        $invalidButton = $summary[2];

        $t->same('dialog', $dialog['name']);
        $t->same('dialog', $dialog['dialog']);
        $t->same(true, $dialog['dialogOpen']);
        $t->same('OKMore', $dialog['dialogText']);
        $t->same('manual', $dialog['popoverRaw']);
        $t->same('manual', $dialog['popoverState']);
        $t->same(true, $dialog['popoverValid']);
        $t->same(['aria-modal' => 'true'], $dialog['ariaAttributes']);
        $t->same('form', $form['formSubmission']);
        $t->same('dialog', $form['method']);
        $t->same('button', $okButton['formControl']);
        $t->same('button', $moreButton['formControl']);
        $t->same('details-popover', $moreButton['popoverTargetRaw']);
        $t->same('details-popover', $moreButton['popoverTarget']);
        $t->same(true, $moreButton['popoverTargetValid']);
        $t->same('show', $moreButton['popoverTargetActionRaw']);
        $t->same('show', $moreButton['popoverTargetAction']);
        $t->same(true, $moreButton['popoverTargetActionValid']);
        $t->same('auto', $popover['popoverRaw']);
        $t->same('auto', $popover['popoverState']);
        $t->same(true, $popover['popoverValid']);
        $t->same('bad target', $invalidButton['popoverTargetRaw']);
        $t->same(null, $invalidButton['popoverTarget']);
        $t->same(false, $invalidButton['popoverTargetValid']);
        $t->same('dismiss', $invalidButton['popoverTargetActionRaw']);
        $t->same(null, $invalidButton['popoverTargetAction']);
        $t->same(false, $invalidButton['popoverTargetActionValid']);
        $t->same('<dialog aria-modal="true" id="confirm" open popover="manual"><form method="dialog"><button value="ok">OK</button><button popovertarget="details-popover" popovertargetaction="show">More</button></form></dialog><aside id="details-popover" popover="auto">Extra</aside><button popovertarget="bad target" popovertargetaction="dismiss">Bad</button>', $html);
    },
    'summarizes html dialog state and method dialog controls for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<dialog id="review-dialog" open aria-labelledby="dialog-title"><h2 id="dialog-title">Review packet</h2>'
                . '<form id="review-close" method="dialog" action="/ignored"><button name="decision" value="approve">Approve</button>'
                . '<button value="cancel" formmethod="post">Cancel remotely</button><input type="submit" name="close" value="close"></form><p>Body</p></dialog>'
                . '<dialog id="closed"><form method="POST"><button value="noop">No close</button></form></dialog>',
            'dialog state review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/dialog-state-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $dialog = $summary[0];
        $dialogForm = $dialog['dialogMethodForms'][0];
        $approve = $dialogForm['submitters'][0];
        $remoteCancel = $dialogForm['submitters'][1];
        $inputClose = $dialogForm['submitters'][2];
        $closed = $summary[1];

        $t->same('dialog', $dialog['name']);
        $t->same('dialog', $dialog['dialog']);
        $t->same(true, $dialog['dialogOpen']);
        $t->same('open', $dialog['dialogState']);
        $t->same('Review packet', $dialog['dialogHeadingText']);
        $t->same('h2', $dialog['dialogHeadingTag']);
        $t->same(2, $dialog['dialogHeadingLevel']);
        $t->same(1, $dialog['dialogMethodFormCount']);
        $t->same('review-close', $dialogForm['id']);
        $t->same('dialog', $dialogForm['methodRaw']);
        $t->same('/ignored', $dialogForm['action']);
        $t->same(['approve', 'close'], $dialog['dialogCloseValues']);

        $t->same('button', $approve['tag']);
        $t->same('decision', $approve['name']);
        $t->same('approve', $approve['value']);
        $t->same('Approve', $approve['label']);
        $t->same('dialog', $approve['effectiveFormMethod']);
        $t->same(true, $approve['dialogCloses']);
        $t->same('post', $remoteCancel['formMethod']);
        $t->same('post', $remoteCancel['effectiveFormMethod']);
        $t->same(false, $remoteCancel['dialogCloses']);
        $t->same('input', $inputClose['tag']);
        $t->same('submit', $inputClose['type']);
        $t->same('close', $inputClose['name']);
        $t->same('close', $inputClose['value']);
        $t->same(false, $inputClose['effectiveDisabled']);

        $t->same('closed', $closed['elementId']);
        $t->same(false, $closed['dialogOpen']);
        $t->same('closed', $closed['dialogState']);
        $t->same(0, $closed['dialogMethodFormCount']);
        $t->same([], $closed['dialogCloseValues']);
        $t->same('<dialog aria-labelledby="dialog-title" id="review-dialog" open><h2 id="dialog-title">Review packet</h2><form action="/ignored" id="review-close" method="dialog"><button name="decision" value="approve">Approve</button><button formmethod="post" value="cancel">Cancel remotely</button><input name="close" type="submit" value="close"></form><p>Body</p></dialog><dialog id="closed"><form method="POST"><button value="noop">No close</button></form></dialog>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/dialog-state-review.html', $document->children[0]->attr('part'));
    },
    'summarizes implicit html popover target toggle action for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<button id="toggle-notes" type="button" popovertarget="notes">Toggle</button>'
                . '<button id="empty-action" popovertarget="notes" popovertargetaction="">Empty</button>'
                . '<button id="bad-action" popovertarget="notes" popovertargetaction="dismiss">Bad</button>'
                . '<aside id="notes" popover>Notes</aside>',
            'implicit popover target action review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $toggle = $summary[0];
        $empty = $summary[1];
        $bad = $summary[2];
        $notes = $summary[3];

        $t->same('notes', $toggle['popoverTargetRaw']);
        $t->same('notes', $toggle['popoverTarget']);
        $t->same(true, $toggle['popoverTargetValid']);
        $t->same(null, $toggle['popoverTargetActionRaw']);
        $t->same('toggle', $toggle['popoverTargetAction']);
        $t->same(true, $toggle['popoverTargetActionValid']);
        $t->same(true, $toggle['popoverTargetActionDefaulted']);

        $t->same('', $empty['popoverTargetActionRaw']);
        $t->same('toggle', $empty['popoverTargetAction']);
        $t->same(true, $empty['popoverTargetActionValid']);
        $t->same(false, $empty['popoverTargetActionDefaulted']);

        $t->same('dismiss', $bad['popoverTargetActionRaw']);
        $t->same(null, $bad['popoverTargetAction']);
        $t->same(false, $bad['popoverTargetActionValid']);
        $t->same(false, $bad['popoverTargetActionDefaulted']);

        $t->same('', $notes['popoverRaw']);
        $t->same('auto', $notes['popoverState']);
        $t->same(true, $notes['popoverValid']);
        $t->same(
            '<button id="toggle-notes" popovertarget="notes" type="button">Toggle</button>'
                . '<button id="empty-action" popovertarget="notes" popovertargetaction="">Empty</button>'
                . '<button id="bad-action" popovertarget="notes" popovertargetaction="dismiss">Bad</button>'
                . '<aside id="notes" popover="">Notes</aside>',
            $html
        );
    },
    'summarizes html popover target idref resolution for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<button id="show-panel" type="button" popovertarget="panel" popovertargetaction="show">Show</button>'
                . '<button id="missing-target" popovertarget="missing">Missing</button>'
                . '<button id="plain-target" popovertarget="plain">Plain</button>'
                . '<button id="invalid-state-target" popovertarget="bad-state">Bad state</button>'
                . '<button id="bad-id" popovertarget="bad target">Bad id</button>'
                . '<section id="panel" popover="manual"><h2>Panel</h2></section>'
                . '<section id="plain">Plain target</section>'
                . '<section id="bad-state" popover="invalid">Bad target</section>',
            'popover target idref review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/popover-target-idref-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $show = $summary[0];
        $missing = $summary[1];
        $plain = $summary[2];
        $invalidState = $summary[3];
        $badId = $summary[4];

        $t->same('popover-target-idref-review', $show['popoverTargetReviewPolicy']);
        $t->same('panel', $show['popoverTargetRaw']);
        $t->same('panel', $show['popoverTarget']);
        $t->same(true, $show['popoverTargetValid']);
        $t->same(true, $show['popoverTargetFound']);
        $t->same('popover', $show['popoverTargetKind']);
        $t->same('section', $show['popoverTargetElement']['tag'] ?? null);
        $t->same('panel', $show['popoverTargetElement']['id'] ?? null);
        $t->same('Panel', $show['popoverTargetElement']['text'] ?? null);
        $t->same('manual', $show['popoverTargetElement']['popoverState'] ?? null);
        $t->same(true, $show['popoverTargetElement']['popoverValid'] ?? null);
        $t->same([], $show['popoverTargetIssueCodes']);
        $t->same(true, $show['popoverTargetInvokesPopover']);
        $t->same('show', $show['popoverTargetAction']);

        $t->same('missing', $missing['popoverTarget']);
        $t->same(false, $missing['popoverTargetFound']);
        $t->same('missing-target', $missing['popoverTargetKind']);
        $t->same(['missing-popover-target'], $missing['popoverTargetIssueCodes']);
        $t->same(false, $missing['popoverTargetInvokesPopover']);

        $t->same('plain', $plain['popoverTarget']);
        $t->same(true, $plain['popoverTargetFound']);
        $t->same('element', $plain['popoverTargetKind']);
        $t->same(null, $plain['popoverTargetElement']['popoverRaw'] ?? null);
        $t->same(['non-popover-target'], $plain['popoverTargetIssueCodes']);

        $t->same('bad-state', $invalidState['popoverTarget']);
        $t->same('popover', $invalidState['popoverTargetKind']);
        $t->same('invalid', $invalidState['popoverTargetElement']['popoverRaw'] ?? null);
        $t->same(false, $invalidState['popoverTargetElement']['popoverValid'] ?? null);
        $t->same(['invalid-popover-target-state'], $invalidState['popoverTargetIssueCodes']);

        $t->same('bad target', $badId['popoverTargetRaw']);
        $t->same(null, $badId['popoverTarget']);
        $t->same(false, $badId['popoverTargetValid']);
        $t->same(false, $badId['popoverTargetFound']);
        $t->same('invalid-reference', $badId['popoverTargetKind']);
        $t->same(['invalid-popover-target-reference'], $badId['popoverTargetIssueCodes']);
        $t->same(false, $badId['popoverTargetInvokesPopover']);

        $t->same('<button id="show-panel" popovertarget="panel" popovertargetaction="show" type="button">Show</button><button id="missing-target" popovertarget="missing">Missing</button><button id="plain-target" popovertarget="plain">Plain</button><button id="invalid-state-target" popovertarget="bad-state">Bad state</button><button id="bad-id" popovertarget="bad target">Bad id</button><section id="panel" popover="manual"><h2>Panel</h2></section><section id="plain">Plain target</section><section id="bad-state" popover="invalid">Bad target</section>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/popover-target-idref-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html button command target provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<dialog id="confirm" open>Confirm body</dialog>'
                . '<div id="menu" popover="manual">Menu</div>'
                . '<section id="card">Card</section>'
                . '<button id="show-menu" commandfor="menu" command="show-popover">Show</button>'
                . '<button id="close-dialog" commandfor="confirm" command="request-close">Close</button>'
                . '<button id="custom-card" type="button" commandfor="card" command="--mark-reviewed">Mark</button>'
                . '<button id="missing-target" commandfor="missing" command="toggle-popover">Missing</button>'
                . '<button id="bad-command" commandfor="card" command="rotate">Bad command</button>'
                . '<button id="bad-id" commandfor="bad target" command="close">Bad target</button>'
                . '<button id="no-command" commandfor="menu">No command</button>',
            'button command target review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/button-command-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $showMenu = $summary[3];
        $closeDialog = $summary[4];
        $customCard = $summary[5];
        $missingTarget = $summary[6];
        $badCommand = $summary[7];
        $badId = $summary[8];
        $noCommand = $summary[9];

        $t->same('button-commandfor-target-review', $showMenu['buttonCommandReviewPolicy']);
        $t->same('submit', $showMenu['buttonType']);
        $t->same(false, $showMenu['buttonSubmitButton']);
        $t->true(!array_key_exists('submitter', $showMenu));
        $t->same('show-popover', $showMenu['commandRaw']);
        $t->same('show-popover', $showMenu['command']);
        $t->same('show-popover', $showMenu['commandState']);
        $t->same('popover', $showMenu['commandActionFamily']);
        $t->same(false, $showMenu['commandCustom']);
        $t->same(true, $showMenu['commandKnown']);
        $t->same('menu', $showMenu['commandFor']);
        $t->same(true, $showMenu['commandForValid']);
        $t->same(true, $showMenu['commandTargetFound']);
        $t->same('popover', $showMenu['commandTargetKind']);
        $t->same('div', $showMenu['commandTarget']['tag'] ?? null);
        $t->same('menu', $showMenu['commandTarget']['id'] ?? null);
        $t->same('manual', $showMenu['commandTarget']['popoverState'] ?? null);
        $t->same([], $showMenu['commandIssueCodes']);
        $t->same(true, $showMenu['commandInvokesTarget']);

        $t->same('request-close', $closeDialog['command']);
        $t->same('dialog', $closeDialog['commandActionFamily']);
        $t->same('dialog', $closeDialog['commandTargetKind']);
        $t->same('dialog', $closeDialog['commandTarget']['tag'] ?? null);
        $t->same(true, $closeDialog['commandTarget']['dialogOpen'] ?? null);
        $t->same('open', $closeDialog['commandTarget']['dialogState'] ?? null);
        $t->same(true, $closeDialog['commandInvokesTarget']);

        $t->same('button', $customCard['buttonType']);
        $t->same(false, $customCard['buttonSubmitButton']);
        $t->same('--mark-reviewed', $customCard['command']);
        $t->same('custom', $customCard['commandState']);
        $t->same('custom', $customCard['commandActionFamily']);
        $t->same(true, $customCard['commandCustom']);
        $t->same('element', $customCard['commandTargetKind']);
        $t->same('section', $customCard['commandTarget']['tag'] ?? null);
        $t->same(true, $customCard['commandInvokesTarget']);

        $t->same('missing', $missingTarget['commandFor']);
        $t->same(true, $missingTarget['commandForValid']);
        $t->same(false, $missingTarget['commandTargetFound']);
        $t->same('missing-target', $missingTarget['commandTargetKind']);
        $t->same(['missing-button-command-target'], $missingTarget['commandIssueCodes']);
        $t->same(false, $missingTarget['commandInvokesTarget']);

        $t->same('rotate', $badCommand['commandRaw']);
        $t->same(null, $badCommand['command']);
        $t->same('unknown', $badCommand['commandState']);
        $t->same(false, $badCommand['commandKnown']);
        $t->same(['unknown-button-command'], $badCommand['commandIssueCodes']);
        $t->same(false, $badCommand['commandInvokesTarget']);

        $t->same('bad target', $badId['commandForRaw']);
        $t->same('bad target', $badId['commandFor']);
        $t->same(false, $badId['commandForValid']);
        $t->same('invalid-reference', $badId['commandTargetKind']);
        $t->same(['invalid-button-commandfor-target'], $badId['commandIssueCodes']);

        $t->same(null, $noCommand['commandRaw']);
        $t->same('missing', $noCommand['commandState']);
        $t->same(['missing-button-command'], $noCommand['commandIssueCodes']);
        $t->same(false, $noCommand['commandInvokesTarget']);

        $t->same(
            '<dialog id="confirm" open>Confirm body</dialog>'
                . '<div id="menu" popover="manual">Menu</div>'
                . '<section id="card">Card</section>'
                . '<button command="show-popover" commandfor="menu" id="show-menu">Show</button>'
                . '<button command="request-close" commandfor="confirm" id="close-dialog">Close</button>'
                . '<button command="--mark-reviewed" commandfor="card" id="custom-card" type="button">Mark</button>'
                . '<button command="toggle-popover" commandfor="missing" id="missing-target">Missing</button>'
                . '<button command="rotate" commandfor="card" id="bad-command">Bad command</button>'
                . '<button command="close" commandfor="bad target" id="bad-id">Bad target</button>'
                . '<button commandfor="menu" id="no-command">No command</button>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/button-command-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html anchor positioning target provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<button id="badge">Badge anchor</button>'
                . '<aside id="tooltip" anchor="badge" popover>Tooltip copy</aside>'
                . '<div id="missing-panel" anchor="missing-anchor">Missing target</div>'
                . '<div id="bad-panel" anchor="bad target">Bad target</div>'
                . '<div id="empty-panel" anchor="">Empty target</div>',
            'anchor positioning review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/anchor-positioning-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $anchorElement = $summary[0];
        $tooltip = $summary[1];
        $missing = $summary[2];
        $bad = $summary[3];
        $empty = $summary[4];

        $t->same('button', $anchorElement['name']);
        $t->same('badge', $anchorElement['elementId']);
        $t->same('html-anchor-positioning-target-review', $tooltip['anchorPositioningReviewPolicy']);
        $t->same('badge', $tooltip['anchorRaw']);
        $t->same('badge', $tooltip['anchorTarget']);
        $t->same(true, $tooltip['anchorTargetValid']);
        $t->same(true, $tooltip['anchorTargetFound']);
        $t->same('element', $tooltip['anchorTargetKind']);
        $t->same('button', $tooltip['anchorTargetElement']['tag'] ?? null);
        $t->same('badge', $tooltip['anchorTargetElement']['id'] ?? null);
        $t->same('Badge anchor', $tooltip['anchorTargetElement']['text'] ?? null);
        $t->same([], $tooltip['anchorIssues']);
        $t->same([], $tooltip['anchorIssueCodes']);
        $t->same(true, $tooltip['anchorReferencesTarget']);
        $t->same('', $tooltip['popoverRaw']);
        $t->same('auto', $tooltip['popoverState']);

        $t->same('missing-anchor', $missing['anchorRaw']);
        $t->same('missing-anchor', $missing['anchorTarget']);
        $t->same(true, $missing['anchorTargetValid']);
        $t->same(false, $missing['anchorTargetFound']);
        $t->same('missing-target', $missing['anchorTargetKind']);
        $t->same(null, $missing['anchorTargetElement']);
        $t->same(['missing-html-anchor-positioning-target-element'], $missing['anchorIssueCodes']);
        $t->same(false, $missing['anchorReferencesTarget']);

        $t->same('bad target', $bad['anchorRaw']);
        $t->same('bad target', $bad['anchorTarget']);
        $t->same(false, $bad['anchorTargetValid']);
        $t->same(false, $bad['anchorTargetFound']);
        $t->same('invalid-reference', $bad['anchorTargetKind']);
        $t->same(['invalid-html-anchor-positioning-target'], $bad['anchorIssueCodes']);
        $t->same(false, $bad['anchorReferencesTarget']);

        $t->same('', $empty['anchorRaw']);
        $t->same(null, $empty['anchorTarget']);
        $t->same(false, $empty['anchorTargetValid']);
        $t->same(false, $empty['anchorTargetFound']);
        $t->same('missing-reference', $empty['anchorTargetKind']);
        $t->same(['missing-html-anchor-positioning-target'], $empty['anchorIssueCodes']);
        $t->same(false, $empty['anchorReferencesTarget']);

        $t->same(
            '<button id="badge">Badge anchor</button>'
                . '<aside anchor="badge" id="tooltip" popover="">Tooltip copy</aside>'
                . '<div anchor="missing-anchor" id="missing-panel">Missing target</div>'
                . '<div anchor="bad target" id="bad-panel">Bad target</div>'
                . '<div anchor="" id="empty-panel">Empty target</div>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/anchor-positioning-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html insertion and deletion revision metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><ins cite="./changes/insert.html" datetime="2026-06-11 12:30Z">Inserted <em>text</em></ins>'
                . '<del cite="https://example.test/revision#old" datetime="2026-06-10T09:15:30-0500">Removed</del>'
                . '<ins datetime="2026-02-30">Invalid date</ins></p>',
            'revision review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $paragraph = $summary[0];
        $inserted = $paragraph['children'][0];
        $deleted = $paragraph['children'][1];
        $invalid = $paragraph['children'][2];

        $t->same('p', $paragraph['name']);
        $t->same('ins', $inserted['name']);
        $t->same('insertion', $inserted['revision']);
        $t->same('ins', $inserted['revisionTag']);
        $t->same('./changes/insert.html', $inserted['revisionCite']);
        $t->same('2026-06-11 12:30Z', $inserted['revisionDatetimeRaw']);
        $t->same('2026-06-11T12:30Z', $inserted['revisionDatetime']);
        $t->same('global-datetime', $inserted['revisionDatetimeKind']);
        $t->same(true, $inserted['revisionDatetimeValid']);
        $t->same('Inserted text', $inserted['text']);
        $t->same('em', $inserted['children'][1]['name']);
        $t->same('del', $deleted['name']);
        $t->same('deletion', $deleted['revision']);
        $t->same('https://example.test/revision#old', $deleted['revisionCite']);
        $t->same('2026-06-10T09:15:30-05:00', $deleted['revisionDatetime']);
        $t->same('global-datetime', $deleted['revisionDatetimeKind']);
        $t->same(true, $deleted['revisionDatetimeValid']);
        $t->same('ins', $invalid['name']);
        $t->same('2026-02-30', $invalid['revisionDatetimeRaw']);
        $t->same(null, $invalid['revisionDatetime']);
        $t->same('invalid', $invalid['revisionDatetimeKind']);
        $t->same(false, $invalid['revisionDatetimeValid']);
        $t->same(
            '<p><ins cite="./changes/insert.html" datetime="2026-06-11 12:30Z">Inserted <em>text</em></ins><del cite="https://example.test/revision#old" datetime="2026-06-10T09:15:30-0500">Removed</del><ins datetime="2026-02-30">Invalid date</ins></p>',
            $html
        );
    },
    'summarizes html quote citation provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<blockquote cite="https://example.test/source#quote"><p>Quoted <em>source</em></p></blockquote>'
                . '<p>Inline <q cite="./inline.html">quoted <strong>claim</strong></q> and <q>uncited</q> from <cite>Packet Title</cite>.</p>',
            'quote citation review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $blockquote = $summary[0];
        $paragraph = $summary[1];
        $inlineQuote = $paragraph['children'][1];
        $uncitedQuote = $paragraph['children'][3];
        $citedWork = $paragraph['children'][5];

        $t->same('blockquote', $blockquote['name']);
        $t->same('block', $blockquote['quote']);
        $t->same('blockquote', $blockquote['quoteTag']);
        $t->same('https://example.test/source#quote', $blockquote['quoteCite']);
        $t->same('Quoted source', $blockquote['quoteText']);
        $t->same('p', $blockquote['children'][0]['name']);
        $t->same('q', $inlineQuote['name']);
        $t->same('inline', $inlineQuote['quote']);
        $t->same('q', $inlineQuote['quoteTag']);
        $t->same('./inline.html', $inlineQuote['quoteCite']);
        $t->same('quoted claim', $inlineQuote['quoteText']);
        $t->same('strong', $inlineQuote['children'][1]['name']);
        $t->same('q', $uncitedQuote['name']);
        $t->same(null, $uncitedQuote['quoteCite']);
        $t->same('uncited', $uncitedQuote['quoteText']);
        $t->same('cite', $citedWork['name']);
        $t->same('cite', $citedWork['citedWork']);
        $t->same('Packet Title', $citedWork['citedWorkText']);
        $t->same(
            '<blockquote cite="https://example.test/source#quote"><p>Quoted <em>source</em></p></blockquote><p>Inline <q cite="./inline.html">quoted <strong>claim</strong></q> and <q>uncited</q> from <cite>Packet Title</cite>.</p>',
            $html
        );
    },
    'summarizes html quote attribution and cite text rollups for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<blockquote id="packet-quote" cite=" https://example.test/review#source "><p>Imported <q cite=" ./inline.html ">inline <cite>Manual</cite></q> note.</p><footer>Source <cite>Reviewer Handbook</cite></footer></blockquote>'
                . '<p>Standalone <cite data-review="work">Packet Guide</cite></p>',
            'quote attribution review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $blockquote = $summary[0];
        $inlineQuote = $blockquote['children'][0]['children'][1];
        $inlineCitation = $inlineQuote['children'][1];
        $footer = $blockquote['children'][1];
        $footerCitation = $footer['children'][1];
        $standaloneCitation = $summary[1]['children'][1];

        $t->same('block', $blockquote['quote']);
        $t->same(' https://example.test/review#source ', $blockquote['quoteCite']);
        $t->same(' https://example.test/review#source ', $blockquote['quoteCiteRaw']);
        $t->same('https://example.test/review#source', $blockquote['quoteCiteNormalized']);
        $t->same('Imported inline Manual note.Source Reviewer Handbook', $blockquote['quoteText']);
        $t->same('Source Reviewer Handbook', $blockquote['attributionText']);
        $t->same(['Manual', 'Reviewer Handbook'], $blockquote['citationTexts']);
        $t->same(2, $blockquote['citationCount']);
        $t->same('footer', $footer['name']);

        $t->same('inline', $inlineQuote['quote']);
        $t->same(' ./inline.html ', $inlineQuote['quoteCiteRaw']);
        $t->same('./inline.html', $inlineQuote['quoteCiteNormalized']);
        $t->same('inline Manual', $inlineQuote['quoteText']);
        $t->same(null, $inlineQuote['attributionText']);
        $t->same(['Manual'], $inlineQuote['citationTexts']);
        $t->same(1, $inlineQuote['citationCount']);

        $t->same('cite', $inlineCitation['citedWork']);
        $t->same('Manual', $inlineCitation['citedWorkText']);
        $t->same('cite', $inlineCitation['citation']);
        $t->same('Manual', $inlineCitation['citationText']);
        $t->same('Reviewer Handbook', $footerCitation['citationText']);
        $t->same('Packet Guide', $standaloneCitation['citationText']);
        $t->same(['review' => 'work'], $standaloneCitation['dataset']);
        $t->same('<blockquote cite=" https://example.test/review#source " id="packet-quote"><p>Imported <q cite=" ./inline.html ">inline <cite>Manual</cite></q> note.</p><footer>Source <cite>Reviewer Handbook</cite></footer></blockquote><p>Standalone <cite data-review="work">Packet Guide</cite></p>', $html);
    },
    'summarizes html quote cite url review provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<blockquote cite=" https://example.test/source#quote "><p>Quoted source</p></blockquote>'
                . '<p><q cite="javascript:alert(1)">Unsafe citation</q><q cite="./notes.html#claim">Relative citation</q><q>Missing citation</q></p>',
            'quote cite URL review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/quote-cite-url-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $blockquote = $summary[0];
        $unsafeQuote = $summary[1]['children'][0];
        $relativeQuote = $summary[1]['children'][1];
        $missingQuote = $summary[1]['children'][2];

        $t->same('quote-cite', $blockquote['quoteCitationUrlReview']);
        $t->same(true, $blockquote['quoteCitePresent']);
        $t->same('absolute', $blockquote['quoteCiteKind']);
        $t->same('https', $blockquote['quoteCiteScheme']);
        $t->same(false, $blockquote['quoteCiteUnsafe']);
        $t->same([], $blockquote['quoteCiteIssueCodes']);
        $t->same('https://example.test/source#quote', $blockquote['quoteCiteNormalized']);

        $t->same('q', $unsafeQuote['name']);
        $t->same(true, $unsafeQuote['quoteCitePresent']);
        $t->same('absolute', $unsafeQuote['quoteCiteKind']);
        $t->same('javascript', $unsafeQuote['quoteCiteScheme']);
        $t->same(true, $unsafeQuote['quoteCiteUnsafe']);
        $t->same(['unsafe-quote-cite'], $unsafeQuote['quoteCiteIssueCodes']);
        $t->same([
            ['code' => 'unsafe-quote-cite', 'cite' => 'javascript:alert(1)', 'scheme' => 'javascript'],
        ], $unsafeQuote['quoteCiteIssues']);

        $t->same('relative', $relativeQuote['quoteCiteKind']);
        $t->same(null, $relativeQuote['quoteCiteScheme']);
        $t->same(false, $relativeQuote['quoteCiteUnsafe']);
        $t->same([], $relativeQuote['quoteCiteIssueCodes']);
        $t->same('./notes.html#claim', $relativeQuote['quoteCiteNormalized']);

        $t->same(false, $missingQuote['quoteCitePresent']);
        $t->same('missing', $missingQuote['quoteCiteKind']);
        $t->same(null, $missingQuote['quoteCiteScheme']);
        $t->same(false, $missingQuote['quoteCiteUnsafe']);
        $t->same([], $missingQuote['quoteCiteIssues']);

        $t->same('<blockquote cite=" https://example.test/source#quote "><p>Quoted source</p></blockquote><p><q cite="javascript:alert(1)">Unsafe citation</q><q cite="./notes.html#claim">Relative citation</q><q>Missing citation</q></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/quote-cite-url-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html media resource state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="preview" controls muted loop poster="cover.jpg" preload="metadata"><source src="movie.webm" type="video/webm"><source src="movie.mp4" type="video/mp4" media="(min-width: 40em)"><track default kind="captions" label="English" srclang="en" src="captions.vtt">Fallback <a href="movie.mp4">download</a></video>'
                . '<audio id="sample" autoplay preload="bogus" src="sample.mp3"><source src="sample.ogg" type="audio/ogg"><track kind="chapters" src="chapters.vtt" srclang="en" label="Chapters">Audio fallback</audio>',
            'media resource review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $video = $summary[0];
        $audio = $summary[1];

        $t->same('video', $video['media']);
        $t->same(true, $video['controls']);
        $t->same(false, $video['autoplay']);
        $t->same(true, $video['loop']);
        $t->same(true, $video['muted']);
        $t->same('metadata', $video['preload']);
        $t->same('cover.jpg', $video['poster']);
        $t->same([
            ['src' => 'movie.webm', 'type' => 'video/webm'],
            ['src' => 'movie.mp4', 'type' => 'video/mp4', 'media' => '(min-width: 40em)'],
        ], $video['sources']);
        $t->same([
            ['kind' => 'captions', 'src' => 'captions.vtt', 'srclang' => 'en', 'label' => 'English', 'default' => true],
        ], $video['tracks']);
        $t->same('Fallback download', $video['fallbackText']);
        $t->same('audio', $audio['media']);
        $t->same(false, $audio['controls']);
        $t->same(true, $audio['autoplay']);
        $t->same(false, $audio['loop']);
        $t->same(false, $audio['muted']);
        $t->same('auto', $audio['preload']);
        $t->same([
            ['src' => 'sample.mp3'],
            ['src' => 'sample.ogg', 'type' => 'audio/ogg'],
        ], $audio['sources']);
        $t->same([
            ['kind' => 'chapters', 'src' => 'chapters.vtt', 'srclang' => 'en', 'label' => 'Chapters', 'default' => false],
        ], $audio['tracks']);
        $t->same('Audio fallback', $audio['fallbackText']);
        $t->same('<video controls id="preview" loop muted poster="cover.jpg" preload="metadata"><source src="movie.webm" type="video/webm"><source media="(min-width: 40em)" src="movie.mp4" type="video/mp4"><track default kind="captions" label="English" src="captions.vtt" srclang="en">Fallback <a href="movie.mp4">download</a></video><audio autoplay id="sample" preload="bogus" src="sample.mp3"><source src="sample.ogg" type="audio/ogg"><track kind="chapters" label="Chapters" src="chapters.vtt" srclang="en">Audio fallback</audio>', $html);
    },
    'summarizes docbook inline media alt text and linkend diagnostics for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<mediaobject id="fig-chart"><imageobject><imagedata fileref="images/chart.svg#view" format="SVG"></imagedata></imageobject><textobject id="fig-chart-text"><phrase>Chart described in text</phrase></textobject><alt>Quarterly chart</alt><xref linkend="fig-caption"></xref></mediaobject>'
                . '<para id="fig-caption">Figure caption target</para>'
                . '<inlinemediaobject id="inline-logo"><imageobject><imagedata fileref="../assets/logo.png?rev=2" format="PNG"></imagedata></imageobject><alt>Logo mark</alt></inlinemediaobject>'
                . '<inlinemediaobject id="missing-alt"><imageobject><imagedata fileref="screens/missing-alt.tiff" format="image/tiff"></imagedata></imageobject><xref linkend="missing-id bad:token"></xref></inlinemediaobject>',
            'docbook inline media review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);

        $media = $summary[0];
        $imageObject = $media['children'][0];
        $imageData = $media['docBookImageData'][0];
        $textObject = $media['children'][1];
        $alt = $media['children'][2];
        $association = $media['docBookLinkendAssociations'][0];
        $inline = $summary[2];
        $missingAlt = $summary[3];

        $t->same('mediaobject', $media['docBookMediaObject']);
        $t->same(false, $media['docBookMediaInline']);
        $t->same('fig-chart', $media['docBookMediaId']);
        $t->same(['Quarterly chart'], $media['docBookAltTexts']);
        $t->same(['Chart described in text'], $media['docBookTextObjectTexts']);
        $t->same(['chart.svg'], $media['docBookImageTargetBasenames']);
        $t->same(['image/svg+xml'], $media['docBookImageContentTypes']);
        $t->same(false, $media['docBookMissingAlt']);
        $t->same([], $media['docBookMediaIssues']);

        $t->same('imageobject', $imageObject['docBookMediaPart']);
        $t->same(1, $imageObject['docBookImageDataCount']);
        $t->same('imagedata', $media['children'][0]['children'][0]['docBookMediaPart']);
        $t->same('images/chart.svg#view', $imageData['target']);
        $t->same('images/chart.svg', $imageData['targetPath']);
        $t->same('chart.svg', $imageData['targetBasename']);
        $t->same('svg', $imageData['targetExtension']);
        $t->same('image/svg+xml', $imageData['contentType']);
        $t->same('format', $imageData['contentTypeSource']);

        $t->same('textobject', $textObject['docBookMediaPart']);
        $t->same('Chart described in text', $textObject['docBookTextObjectText']);
        $t->same('alt', $alt['docBookMediaPart']);
        $t->same('Quarterly chart', $alt['docBookAltText']);
        $t->same('xref', $association['element']);
        $t->same('fig-caption', $association['linkendRaw']);
        $t->same(['fig-caption'], $association['resolvedIds']);
        $t->same([], $association['missingIds']);
        $t->same([], $association['invalidIds']);
        $t->same(true, $association['valid']);

        $t->same('inlinemediaobject', $inline['docBookMediaObject']);
        $t->same(true, $inline['docBookMediaInline']);
        $t->same('inline-logo', $inline['docBookMediaId']);
        $t->same(['logo.png'], $inline['docBookImageTargetBasenames']);
        $t->same('image/png', $inline['docBookImageData'][0]['contentType']);
        $t->same('../assets/logo.png', $inline['docBookImageData'][0]['targetPath']);
        $t->same(false, $inline['docBookMissingAlt']);

        $t->same('missing-alt', $missingAlt['docBookMediaId']);
        $t->same(true, $missingAlt['docBookMissingAlt']);
        $t->same(['missing-alt.tiff'], $missingAlt['docBookImageTargetBasenames']);
        $t->same('image/tiff', $missingAlt['docBookImageData'][0]['contentType']);
        $t->same([
            ['code' => 'missing-docbook-media-alt', 'media' => 'inlinemediaobject', 'imageDataCount' => 1],
            ['code' => 'invalid-docbook-linkend', 'element' => 'xref', 'linkendId' => 'bad:token'],
            ['code' => 'missing-docbook-linkend-target', 'element' => 'xref', 'linkendId' => 'missing-id'],
        ], $missingAlt['docBookMediaIssues']);
        $t->same(3, $missingAlt['docBookMediaIssueCount']);
        $t->same(['missing-id', 'bad:token'], $missingAlt['docBookLinkendAssociations'][0]['linkendIds']);
        $t->same(['missing-id'], $missingAlt['docBookLinkendAssociations'][0]['missingIds']);
        $t->same(['bad:token'], $missingAlt['docBookLinkendAssociations'][0]['invalidIds']);
        $t->same(false, $missingAlt['docBookLinkendAssociations'][0]['valid']);
    },
    'summarizes html media text track provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="review" controls>'
                . '<track default kind="CAPTIONS" srclang="EN-us" label="English captions" src="captions-en.vtt">'
                . '<track default kind="subtitles" label="No language" src="captions-missing.vtt">'
                . '<track kind="transcript" srclang="bad&lt;tag&gt;" label="" src="bad.vtt">'
                . '<track kind="metadata" srclang="x-review" label="Cue data" src="metadata.vtt">'
                . '</video>',
            'media text track review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/media-text-track-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $video = $summary[0];
        $tracks = $video['textTracks'];

        $t->same(4, $video['textTrackCount']);
        $t->same(['captions' => 1, 'metadata' => 1, 'subtitles' => 2], $video['textTrackKinds']);
        $t->same(['en-US', 'x-review'], $video['textTrackLanguages']);
        $t->same(['en-US'], $video['subtitleTextTrackLanguages']);
        $t->same(2, $video['defaultTextTrackCount']);
        $t->same(['English captions', 'No language'], $video['defaultTextTrackLabels']);
        $t->same(true, $video['defaultTextTrackConflict']);
        $t->same(1, $video['invalidTextTrackKindCount']);
        $t->same(1, $video['invalidTextTrackLanguageCount']);
        $t->same(2, $video['missingSubtitleLanguageCount']);
        $t->same([
            ['code' => 'multiple-default-tracks', 'count' => 2],
            ['code' => 'missing-text-track-language', 'trackIndex' => 1, 'kind' => 'subtitles', 'label' => 'No language', 'src' => 'captions-missing.vtt'],
            ['code' => 'invalid-text-track-kind', 'trackIndex' => 2, 'kindRaw' => 'transcript', 'normalizedKind' => 'subtitles'],
            ['code' => 'invalid-text-track-language', 'trackIndex' => 2, 'srclangRaw' => 'bad<tag>'],
            ['code' => 'missing-text-track-language', 'trackIndex' => 2, 'kind' => 'subtitles', 'label' => '', 'src' => 'bad.vtt'],
        ], $video['textTrackIssues']);
        $t->same([
            'index' => 0,
            'src' => 'captions-en.vtt',
            'kindRaw' => 'CAPTIONS',
            'kind' => 'captions',
            'kindValid' => true,
            'srclangRaw' => 'EN-us',
            'srclang' => 'en-US',
            'srclangValid' => true,
            'label' => 'English captions',
            'default' => true,
            'languageRequired' => true,
            'languageMissing' => false,
        ], $tracks[0]);
        $t->same(false, $tracks[2]['kindValid']);
        $t->same(false, $tracks[2]['srclangValid']);
        $t->same(true, $tracks[2]['languageMissing']);
        $t->contains($html, $blocks);
        $t->same('/migration/media-text-track-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html canvas fallback state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<canvas id="chart" width="640" height="360"><p>Quarterly <a href="chart-data.csv">data table</a></p><img src="chart.png" alt="Static chart"></canvas>'
                . '<canvas width="-1" height="bad">Fallback only</canvas>',
            'canvas fallback review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/canvas-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $canvas = $summary[0];
        $invalidCanvas = $summary[1];

        $t->same('canvas', $canvas['name']);
        $t->same('canvas', $canvas['embeddedResource']);
        $t->same('640', $canvas['width']);
        $t->same('360', $canvas['height']);
        $t->same(640, $canvas['bitmapWidth']);
        $t->same(360, $canvas['bitmapHeight']);
        $t->same(['p', 'img'], $canvas['fallbackElementNames']);
        $t->same(2, $canvas['fallbackElementCount']);
        $t->same('Quarterly data table', $canvas['fallbackText']);
        $t->same(strlen('Quarterly data table'), $canvas['fallbackTextLength']);
        $t->same(hash('sha256', 'Quarterly data table'), $canvas['fallbackTextSha256']);
        $t->same('canvas-fallback-source', $canvas['canvasReviewPolicy']);
        $t->same('a', $canvas['children'][0]['children'][1]['name']);
        $t->same('chart-data.csv', $canvas['children'][0]['children'][1]['href']);
        $t->same('image', $canvas['children'][1]['embeddedResource']);
        $t->same('chart.png', $canvas['children'][1]['src']);

        $t->same('canvas', $invalidCanvas['embeddedResource']);
        $t->same('-1', $invalidCanvas['width']);
        $t->same('bad', $invalidCanvas['height']);
        $t->same(300, $invalidCanvas['bitmapWidth']);
        $t->same(150, $invalidCanvas['bitmapHeight']);
        $t->same([], $invalidCanvas['fallbackElementNames']);
        $t->same(0, $invalidCanvas['fallbackElementCount']);
        $t->same('Fallback only', $invalidCanvas['fallbackText']);
        $t->same(strlen('Fallback only'), $invalidCanvas['fallbackTextLength']);
        $t->same(hash('sha256', 'Fallback only'), $invalidCanvas['fallbackTextSha256']);
        $t->same('<canvas height="360" id="chart" width="640"><p>Quarterly <a href="chart-data.csv">data table</a></p><img alt="Static chart" src="chart.png"></canvas><canvas height="bad" width="-1">Fallback only</canvas>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/canvas-fallback-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html embedded image and media source candidates for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<picture><source media="(min-width: 60em)" type="image/avif" srcset="hero.avif 1x, hero@2x.avif 2x"><source type="image/webp" srcset="hero.webp 800w"><img src="hero.jpg" srcset="hero-small.jpg 400w, hero-large.jpg 1200w" sizes="100vw" alt="Hero &amp; Source" loading="lazy" decoding="async"></picture>'
                . '<video controls poster="poster.jpg" preload="metadata"><source src="clip.webm" type="video/webm"><source src="clip.mp4" type="video/mp4" media="screen"><track kind="captions" srclang="en" label="English" src="captions.vtt" default></video>'
                . '<audio src="chapter.mp3" controls><source src="chapter.ogg" type="audio/ogg"></audio>'
                . '<iframe src="frame.html" srcdoc="&lt;p&gt;Preview&lt;/p&gt;" sandbox="allow-scripts allow-forms" allowfullscreen loading="lazy" referrerpolicy="no-referrer" width="640" height="360">Legacy frame fallback</iframe>'
                . '<embed src="plugin.swf" type="application/x-shockwave-flash" width="320" height="32"></embed>'
                . '<object data="diagram.svg" type="image/svg+xml" name="diagram" width="640" height="480"><param name="quality" value="high"><param name="review-url" value="packet.html" valuetype="ref" type="text/html">Object fallback</object>',
            'embedded media review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $picture = $summary[0];
        $image = $picture['image'];
        $video = $summary[1];
        $audio = $summary[2];
        $iframe = $summary[3];
        $embed = $summary[4];
        $object = $summary[5];

        $t->same('picture', $picture['embeddedResource']);
        $t->same(2, count($picture['pictureSources']));
        $t->same('image/avif', $picture['pictureSources'][0]['type']);
        $t->same('(min-width: 60em)', $picture['pictureSources'][0]['media']);
        $t->same('hero.avif', $picture['pictureSources'][0]['srcsetCandidates'][0]['url']);
        $t->same(['2x'], $picture['pictureSources'][0]['srcsetCandidates'][1]['descriptors']);
        $t->same('image', $image['embeddedResource']);
        $t->same('hero.jpg', $image['src']);
        $t->same('Hero & Source', $image['alt']);
        $t->same('hero-small.jpg', $image['srcsetCandidates'][0]['url']);
        $t->same('1200w', $image['srcsetCandidates'][1]['descriptor']);
        $t->same('100vw', $image['sizes']);
        $t->same('lazy', $image['loading']);
        $t->same('async', $image['decoding']);

        $t->same('video', $video['embeddedResource']);
        $t->same(true, $video['controls']);
        $t->same('poster.jpg', $video['poster']);
        $t->same('metadata', $video['preload']);
        $t->same('clip.webm', $video['mediaSources'][0]['src']);
        $t->same('video/mp4', $video['mediaSources'][1]['type']);
        $t->same('screen', $video['mediaSources'][1]['media']);
        $t->same('captions', $video['tracks'][0]['kind']);
        $t->same('en', $video['tracks'][0]['srclang']);
        $t->same('English', $video['tracks'][0]['label']);
        $t->same('captions.vtt', $video['tracks'][0]['src']);
        $t->same(true, $video['tracks'][0]['default']);

        $t->same('audio', $audio['embeddedResource']);
        $t->same('chapter.mp3', $audio['src']);
        $t->same(true, $audio['controls']);
        $t->same('chapter.ogg', $audio['mediaSources'][0]['src']);
        $t->same('audio/ogg', $audio['mediaSources'][0]['type']);

        $t->same('iframe', $iframe['embeddedResource']);
        $t->same('frame.html', $iframe['src']);
        $t->same('<p>Preview</p>', $iframe['srcdoc']);
        $t->same(['allow-scripts', 'allow-forms'], $iframe['sandboxTokens']);
        $t->same(true, $iframe['allowFullscreen']);
        $t->same('Legacy frame fallback', $iframe['fallbackText']);

        $t->same('embed', $embed['embeddedResource']);
        $t->same('plugin.swf', $embed['src']);
        $t->same('application/x-shockwave-flash', $embed['mimeType']);
        $t->same('320', $embed['width']);

        $t->same('object', $object['embeddedResource']);
        $t->same('diagram.svg', $object['data']);
        $t->same('image/svg+xml', $object['mimeType']);
        $t->same('diagram', $object['nameAttribute']);
        $t->same([
            ['paramName' => 'quality', 'value' => 'high', 'valueType' => null, 'mimeType' => null],
            ['paramName' => 'review-url', 'value' => 'packet.html', 'valueType' => 'ref', 'mimeType' => 'text/html'],
        ], $object['params']);
        $t->same('param', $object['children'][0]['embeddedResource']);
        $t->same('quality', $object['children'][0]['paramName']);
        $t->same('Object fallback', $object['fallbackText']);
        $t->same('<picture><source media="(min-width: 60em)" srcset="hero.avif 1x, hero@2x.avif 2x" type="image/avif"><source srcset="hero.webp 800w" type="image/webp"><img alt="Hero &amp; Source" decoding="async" loading="lazy" sizes="100vw" src="hero.jpg" srcset="hero-small.jpg 400w, hero-large.jpg 1200w"></picture><video controls poster="poster.jpg" preload="metadata"><source src="clip.webm" type="video/webm"><source media="screen" src="clip.mp4" type="video/mp4"><track default kind="captions" label="English" src="captions.vtt" srclang="en"></video><audio controls src="chapter.mp3"><source src="chapter.ogg" type="audio/ogg"></audio><iframe allowfullscreen height="360" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-forms" src="frame.html" srcdoc="&lt;p&gt;Preview&lt;/p&gt;" width="640">Legacy frame fallback</iframe><embed height="32" src="plugin.swf" type="application/x-shockwave-flash" width="320"><object data="diagram.svg" height="480" name="diagram" type="image/svg+xml" width="640"><param name="quality" value="high"></param><param name="review-url" type="text/html" value="packet.html" valuetype="ref"></param>Object fallback</object>', $html);
    },
    'summarizes html fragment resource url base provenance without fetching resources' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<base href="../assets/">'
                . '<link rel="stylesheet preload" href="css/site.css" imagesrcset="icons/app.png 1x, //cdn.example.test/app@2x.png 2x" as="style">'
                . '<img id="cover" src="../media/cover.png" srcset="../media/cover.png 1x, data:image/png;base64,AAAA 2x" alt="Cover">'
                . '<video poster="poster.jpg"><source src="video/chapter.mp4" type="video/mp4"><track kind="captions" src="#captions"></video>'
                . '<object data="objects/diagram.svg"><param name="movie" value="clips/diagram.swf" valuetype="ref" type="application/x-shockwave-flash"></object>'
                . '<a href="#note">note</a><form action="/submit"></form>',
            'resource url provenance fragment'
        );
        $packet = XmlHtmlDom::summarizeHtmlFragmentResourceUrls($dom, 'https://example.test/book/chapter.xhtml');
        $find = static function (array $packet, string $element, string $attribute, ?string $role = null, ?int $candidateIndex = null) use ($t): array {
            foreach ($packet['resources'] as $resource) {
                if (($resource['element'] ?? null) !== $element || ($resource['attribute'] ?? null) !== $attribute) {
                    continue;
                }
                if ($role !== null && ($resource['role'] ?? null) !== $role) {
                    continue;
                }
                if ($candidateIndex !== null && ($resource['candidateIndex'] ?? null) !== $candidateIndex) {
                    continue;
                }

                return $resource;
            }

            $t->true(false, 'Expected resource URL record was not found');

            return [];
        };

        $t->same('html-fragment-resource-url-base-provenance', $packet['resourceUrlReviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same(['html-fragment-resource-url-review-only'], $packet['directReaderDiagnosticCodes']);
        $t->same('https://example.test/book/chapter.xhtml', $packet['providedBaseUrl']);
        $t->same('active-base-href', $packet['effectiveBaseSource']);
        $t->same('https://example.test/assets/', $packet['effectiveBaseUrl']);
        $t->same('../assets/', $packet['activeBaseHref']);
        $t->same('relative', $packet['activeBaseHrefKind']);
        $t->same(true, $packet['activeBaseHrefUsable']);
        $t->same([], $packet['baseIssueCodes']);
        $t->same(14, $packet['resourceCount']);
        $t->same(13, $packet['resolvedResourceCount']);
        $t->same(1, $packet['unsafeResourceCount']);
        $t->same(['base', 'link', 'img', 'video', 'source', 'track', 'object', 'param', 'a', 'form'], $packet['resourceElements']);
        $t->same(['href', 'imagesrcset', 'src', 'srcset', 'poster', 'data', 'value', 'action'], $packet['resourceAttributes']);
        $t->same(['unsafe-resource-url'], $packet['resourceIssueCodes']);

        $base = $find($packet, 'base', 'href');
        $link = $find($packet, 'link', 'href');
        $image = $find($packet, 'img', 'src');
        $cdnCandidate = $find($packet, 'link', 'imagesrcset', 'image-srcset-candidate', 1);
        $unsafeCandidate = $find($packet, 'img', 'srcset', 'srcset-candidate', 1);
        $track = $find($packet, 'track', 'src');
        $param = $find($packet, 'param', 'value');
        $form = $find($packet, 'form', 'action');

        $t->same('https://example.test/assets/', $base['resolvedUrl']);
        $t->same('https://example.test/assets/css/site.css', $link['resolvedUrl']);
        $t->same('https://example.test/media/cover.png', $image['resolvedUrl']);
        $t->same('cover', $image['elementId']);
        $t->same('img[1]', $image['elementPath']);
        $t->same('https://cdn.example.test/app@2x.png', $cdnCandidate['resolvedUrl']);
        $t->same('scheme-relative', $cdnCandidate['urlKind']);
        $t->same('data', $unsafeCandidate['urlScheme']);
        $t->same(true, $unsafeCandidate['urlUnsafe']);
        $t->same(false, $unsafeCandidate['resolved']);
        $t->same(['unsafe-resource-url'], $unsafeCandidate['issueCodes']);
        $t->same('https://example.test/assets/#captions', $track['resolvedUrl']);
        $t->same('https://example.test/assets/clips/diagram.swf', $param['resolvedUrl']);
        $t->same('https://example.test/submit', $form['resolvedUrl']);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes unresolved html fragment resources when no usable base is present' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<img src="cover.png" alt="Cover"><a href="#local">local</a><base href="javascript:alert(1)">',
            'unbased resource url provenance fragment'
        );
        $packet = XmlHtmlDom::summarizeHtmlFragmentResourceUrls($dom);
        $resources = $packet['resources'];

        $t->same(null, $packet['effectiveBaseUrl']);
        $t->same('none', $packet['effectiveBaseSource']);
        $t->same(['unusable-active-base-href'], $packet['baseIssueCodes']);
        $t->same(3, $packet['resourceCount']);
        $t->same(1, $packet['resolvedResourceCount']);
        $t->same(2, $packet['unresolvedResourceCount']);
        $t->same(1, $packet['unsafeResourceCount']);
        $t->same(['missing-resource-base-url', 'unsafe-resource-url'], $packet['resourceIssueCodes']);
        $t->same('cover.png', $resources[0]['value']);
        $t->same(false, $resources[0]['resolved']);
        $t->same(['missing-resource-base-url'], $resources[0]['issueCodes']);
        $t->same('#local', $resources[1]['resolvedUrl']);
        $t->same('javascript', $resources[2]['urlScheme']);
        $t->same(['unsafe-resource-url'], $resources[2]['issueCodes']);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'summarizes html image loading policy metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<img src="hero.avif" alt="Hero" loading="lazy" decoding="async" fetchpriority="high" crossorigin="use-credentials" referrerpolicy="no-referrer">'
                . '<img src="fallback.png" alt="Fallback" loading="Soon" decoding="fast" fetchpriority="urgent" crossorigin="credentialed" referrerpolicy="never">',
            'image loading policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);

        $validImage = $summary[0];
        $invalidImage = $summary[1];

        $t->same('image-loading-metadata-review', $validImage['imageLoadingReviewPolicy']);
        $t->same('lazy', $validImage['imageLoadingState']);
        $t->same(true, $validImage['imageLoadingValid']);
        $t->same('async', $validImage['imageDecodingState']);
        $t->same('high', $validImage['imageFetchPriority']);
        $t->same('use-credentials', $validImage['imageCrossoriginState']);
        $t->same('no-referrer', $validImage['imageReferrerPolicy']);
        $t->same([], $validImage['imageLoadingIssueCodes']);

        $t->same(null, $invalidImage['imageLoadingState']);
        $t->same(false, $invalidImage['imageLoadingValid']);
        $t->same(null, $invalidImage['imageDecodingState']);
        $t->same(null, $invalidImage['imageFetchPriority']);
        $t->same(null, $invalidImage['imageCrossoriginState']);
        $t->same(null, $invalidImage['imageReferrerPolicy']);
        $t->same([
            'invalid-image-loading',
            'invalid-image-decoding',
            'invalid-image-fetchpriority',
            'invalid-image-crossorigin',
            'invalid-image-referrerpolicy',
        ], $invalidImage['imageLoadingIssueCodes']);
        $t->same(5, $invalidImage['imageLoadingIssueCount']);
    },
    'summarizes html media playback policy metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="inline" controls controlslist="nodownload nofullscreen nodownload" playsinline disablepictureinpicture disableremoteplayback poster="poster.jpg">Trailer fallback</video>'
                . '<audio id="remote" controls controlslist="noremoteplayback bad-token" disableremoteplayback>Audio fallback</audio>'
                . '<video id="empty" controlslist="  ">Empty controls</video>',
            'media playback policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/media-playback-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $video = $summary[0];
        $audio = $summary[1];
        $empty = $summary[2];

        $t->same('media-playback-policy-metadata-review', $video['mediaPlaybackPolicyReview']);
        $t->same(true, $video['playsInline']);
        $t->same(true, $video['mediaPlaysInline']);
        $t->same(true, $video['mediaDisablePictureInPicture']);
        $t->same(true, $video['mediaDisableRemotePlayback']);
        $t->same('nodownload nofullscreen nodownload', $video['mediaControlsListRaw']);
        $t->same(['nodownload', 'nofullscreen', 'nodownload'], $video['mediaControlsListRawTokens']);
        $t->same(['nodownload', 'nofullscreen'], $video['mediaControlsListTokens']);
        $t->same(['nodownload' => 2, 'nofullscreen' => 1], $video['mediaControlsListTokenCounts']);
        $t->same(['nodownload'], $video['duplicateMediaControlsListTokens']);
        $t->same([], $video['invalidMediaControlsListTokens']);
        $t->same(true, $video['mediaControlsListValid']);
        $t->same(['duplicate-media-controlslist-token'], $video['mediaPlaybackIssueCodes']);
        $t->same([
            ['code' => 'duplicate-media-controlslist-token', 'token' => 'nodownload', 'count' => 2],
        ], $video['mediaPlaybackIssues']);

        $t->same('media-playback-policy-metadata-review', $audio['mediaPlaybackPolicyReview']);
        $t->same(['noremoteplayback', 'bad-token'], $audio['mediaControlsListRawTokens']);
        $t->same(['noremoteplayback'], $audio['mediaControlsListTokens']);
        $t->same(['bad-token'], $audio['invalidMediaControlsListTokens']);
        $t->same(false, $audio['mediaControlsListValid']);
        $t->same(true, $audio['mediaDisableRemotePlayback']);
        $t->same(false, $audio['mediaPlaysInline']);
        $t->same(false, $audio['mediaDisablePictureInPicture']);
        $t->same(['invalid-media-controlslist-token'], $audio['mediaPlaybackIssueCodes']);

        $t->same([], $empty['mediaControlsListRawTokens']);
        $t->same(false, $empty['mediaControlsListValid']);
        $t->same(['empty-media-controlslist'], $empty['mediaPlaybackIssueCodes']);

        $t->same('<video controls controlslist="nodownload nofullscreen nodownload" disablepictureinpicture disableremoteplayback id="inline" playsinline poster="poster.jpg">Trailer fallback</video><audio controls controlslist="noremoteplayback bad-token" disableremoteplayback id="remote">Audio fallback</audio><video controlslist="  " id="empty">Empty controls</video>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/media-playback-policy-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html server side image map provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><a id="chart-map" href="/map?packet=42"><img id="server" src="chart.png" alt="Chart" ismap></a></p>'
                . '<p><a id="missing-link"><img id="missing-href" src="missing.png" alt="Missing" ismap></a></p>'
                . '<p><img id="orphan" src="orphan.png" alt="Orphan" ismap></p>'
                . '<p><a href="/hybrid"><img id="hybrid" src="hybrid.png" alt="Hybrid" ismap usemap="#figures"></a></p>'
                . '<map name="figures"><area href="/client" alt="Client map"></map>',
            'server-side image map review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/server-image-map-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $server = $summary[0]['children'][0]['children'][0];
        $missingHref = $summary[1]['children'][0]['children'][0];
        $orphan = $summary[2]['children'][0];
        $hybrid = $summary[3]['children'][0]['children'][0];

        $t->same('html-server-side-image-map-review', $server['serverImageMapReviewPolicy']);
        $t->same(true, $server['serverImageMap']);
        $t->same('ismap', $server['serverImageMapRaw']);
        $t->same(true, $server['serverImageMapAnchorFound']);
        $t->same('/map?packet=42', $server['serverImageMapAnchorHref']);
        $t->same(true, $server['serverImageMapAnchorHrefUsable']);
        $t->same('chart-map', $server['serverImageMapAnchorId']);
        $t->same('', $server['serverImageMapAnchorText']);
        $t->same(false, $server['serverImageMapHasClientMap']);
        $t->same(true, $server['serverImageMapUsable']);
        $t->same([], $server['serverImageMapIssueCodes']);

        $t->same(true, $missingHref['serverImageMapAnchorFound']);
        $t->same(null, $missingHref['serverImageMapAnchorHref']);
        $t->same(false, $missingHref['serverImageMapAnchorHrefUsable']);
        $t->same(false, $missingHref['serverImageMapUsable']);
        $t->same(['server-image-map-anchor-missing-href'], $missingHref['serverImageMapIssueCodes']);

        $t->same(false, $orphan['serverImageMapAnchorFound']);
        $t->same(null, $orphan['serverImageMapAnchorHref']);
        $t->same(false, $orphan['serverImageMapUsable']);
        $t->same(['server-image-map-without-anchor'], $orphan['serverImageMapIssueCodes']);

        $t->same('/hybrid', $hybrid['serverImageMapAnchorHref']);
        $t->same(true, $hybrid['serverImageMapHasClientMap']);
        $t->same(true, $hybrid['serverImageMapUsable']);
        $t->same(['server-image-map-has-client-usemap'], $hybrid['serverImageMapIssueCodes']);
        $t->same([[
            'code' => 'server-image-map-has-client-usemap',
            'useMapRaw' => '#figures',
        ]], $hybrid['serverImageMapIssues']);
        $t->same('resolved', $hybrid['useMapAssociationState']);
        $t->same(['/client'], $hybrid['useMapAreaHrefs']);
        $t->contains('<img alt="Chart" id="server" ismap src="chart.png">', $html);
        $t->contains('<img alt="Hybrid" id="hybrid" ismap src="hybrid.png" usemap="#figures">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/server-image-map-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html object param review provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<object id="player" data="player.swf" type="application/x-shockwave-flash">'
                . '<param name="Movie" value="movie.swf" valuetype="ref" type="application/x-shockwave-flash">'
                . '<param name="movie" value="override.swf" valuetype="REF">'
                . '<param name="controller" value="control-panel" valuetype="object">'
                . '<param value="loose"><param name=" " value="blank">'
                . '<param name="bad&lt;tag" value="bad" valuetype="bogus">Fallback</object>',
            'object param review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/object-param-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $object = $summary[0];
        $firstParam = $object['paramDetails'][0];
        $implicitParam = $object['paramDetails'][3];
        $invalidParam = $object['paramDetails'][5];
        $childParam = $object['children'][0];

        $t->same('object', $object['embeddedResource']);
        $t->same(6, $object['paramCount']);
        $t->same(['Movie', 'controller'], $object['paramNames']);
        $t->same(['Movie'], $object['duplicateParamNames']);
        $t->same(2, $object['unnamedParamCount']);
        $t->same(1, $object['invalidParamNameCount']);
        $t->same(1, $object['invalidParamValueTypeCount']);
        $t->same(2, $object['refParamCount']);
        $t->same(1, $object['objectReferenceParamCount']);
        $t->same([
            ['index' => 0, 'paramName' => 'Movie', 'value' => 'movie.swf', 'mimeType' => 'application/x-shockwave-flash', 'valueType' => 'ref'],
            ['index' => 1, 'paramName' => 'movie', 'value' => 'override.swf', 'mimeType' => null, 'valueType' => 'ref'],
        ], $object['refParams']);
        $t->same([
            ['index' => 2, 'paramName' => 'controller', 'value' => 'control-panel', 'mimeType' => null, 'valueType' => 'object'],
        ], $object['objectReferenceParams']);
        $t->same([
            ['code' => 'duplicate-param-name', 'paramName' => 'Movie', 'paramNameKey' => 'movie'],
            ['code' => 'missing-param-name', 'paramIndex' => 3, 'value' => 'loose'],
            ['code' => 'missing-param-name', 'paramIndex' => 4, 'value' => 'blank'],
            ['code' => 'invalid-param-name', 'paramIndex' => 5, 'paramNameRaw' => 'bad<tag'],
            ['code' => 'invalid-param-valuetype', 'paramIndex' => 5, 'paramName' => 'bad<tag', 'valueTypeRaw' => 'bogus'],
        ], $object['paramIssues']);

        $t->same('Movie', $firstParam['paramNameRaw']);
        $t->same('Movie', $firstParam['paramNameNormalized']);
        $t->same('movie', $firstParam['paramNameKey']);
        $t->same(true, $firstParam['paramNameValid']);
        $t->same('movie.swf', $firstParam['valueRaw']);
        $t->same('ref', $firstParam['valueTypeRaw']);
        $t->same('ref', $firstParam['valueTypeState']);
        $t->same(true, $firstParam['valueTypeExplicit']);
        $t->same(true, $firstParam['valueTypeValid']);
        $t->same('application/x-shockwave-flash', $firstParam['mimeType']);

        $t->same(null, $implicitParam['paramNameNormalized']);
        $t->same(null, $implicitParam['paramNameKey']);
        $t->same(false, $implicitParam['paramNameValid']);
        $t->same('data', $implicitParam['valueTypeState']);
        $t->same(false, $implicitParam['valueTypeExplicit']);
        $t->same(true, $implicitParam['valueTypeValid']);

        $t->same('bad<tag', $invalidParam['paramNameNormalized']);
        $t->same(false, $invalidParam['paramNameValid']);
        $t->same('bogus', $invalidParam['valueTypeRaw']);
        $t->same('data', $invalidParam['valueTypeState']);
        $t->same(true, $invalidParam['valueTypeExplicit']);
        $t->same(false, $invalidParam['valueTypeValid']);

        $t->same('param', $childParam['embeddedResource']);
        $t->same('Movie', $childParam['paramNameNormalized']);
        $t->same('ref', $childParam['valueTypeState']);
        $t->same('Fallback', $object['fallbackText']);
        $t->same('<object data="player.swf" id="player" type="application/x-shockwave-flash"><param name="Movie" type="application/x-shockwave-flash" value="movie.swf" valuetype="ref"></param><param name="movie" value="override.swf" valuetype="REF"></param><param name="controller" value="control-panel" valuetype="object"></param><param value="loose"></param><param name=" " value="blank"></param><param name="bad&lt;tag" value="bad" valuetype="bogus"></param>Fallback</object>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/object-param-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html object form and image-map associations for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="review-form" action="/review" method="post"></form>'
                . '<object id="packet-diagram" data="diagram.svg" type="image/svg+xml" name="diagram" form="review-form" usemap="#diagram-map" typemustmatch>Diagram fallback</object>'
                . '<object id="missing-form-object" data="fallback.bin" form="missing-form" usemap="bad target"></object>'
                . '<map name="diagram-map"><area alt="Detail" href="#detail" shape="rect" coords="0,0,10,10"></map>',
            'object association review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/object-association-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $object = $summary[1];
        $missingFormObject = $summary[2];
        $map = $summary[3];

        $t->same('object', $object['embeddedResource']);
        $t->same('diagram.svg', $object['data']);
        $t->same('image/svg+xml', $object['mimeType']);
        $t->same(true, $object['typeMustMatch']);
        $t->same('review-form', $object['formOwnerRaw']);
        $t->same('review-form', $object['formOwnerTargetId']);
        $t->same('review-form', $object['formOwnerId']);
        $t->same('form-attribute', $object['formOwnerSource']);
        $t->same(true, $object['formOwnerFound']);
        $t->same('/review', $object['formOwnerAction']);
        $t->same('post', $object['formOwnerMethod']);
        $t->same('#diagram-map', $object['useMapRaw']);
        $t->same('diagram-map', $object['useMapName']);
        $t->same(true, $object['useMapValid']);
        $t->same('Diagram fallback', $object['fallbackText']);

        $t->same('object', $missingFormObject['embeddedResource']);
        $t->same(false, $missingFormObject['typeMustMatch']);
        $t->same('missing-form', $missingFormObject['formOwnerTargetId']);
        $t->same('missing-form-attribute', $missingFormObject['formOwnerSource']);
        $t->same(false, $missingFormObject['formOwnerFound']);
        $t->same('bad target', $missingFormObject['useMapRaw']);
        $t->same('bad target', $missingFormObject['useMapName']);
        $t->same(false, $missingFormObject['useMapValid']);

        $t->same('map', $map['imageMap']);
        $t->same('diagram-map', $map['mapName']);
        $t->same(true, $map['mapNameValid']);
        $t->same(['#detail'], $map['areaHrefs']);
        $t->same(['Detail'], $map['areaLabels']);
        $t->same('<form action="/review" id="review-form" method="post"></form><object data="diagram.svg" form="review-form" id="packet-diagram" name="diagram" type="image/svg+xml" typemustmatch usemap="#diagram-map">Diagram fallback</object><object data="fallback.bin" form="missing-form" id="missing-form-object" usemap="bad target"></object><map name="diagram-map"><area alt="Detail" coords="0,0,10,10" href="#detail" shape="rect"></map>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/object-association-review.html', $document->children[0]->attr('part'));
    },
    'summarizes iframe srcdoc as inert parsed review provenance' => static function (TestRunner $t): void {
        $srcdoc = implode("\n", [
            '<article data-review="srcdoc">',
            '<h1>Preview</h1>',
            '<p>Open <a href="chapter.html#one">chapter</a><img src="cover.jpg" alt="Cover"></p>',
            '<form action="/review" method="post"><input name="q" value="ok"></form>',
            '<iframe src="nested.html">Nested</iframe>',
            '<canvas>Fallback chart</canvas>',
            '</article>',
        ]);
        $unsafeSrcdoc = '<section><!DOCTYPE html [<!ENTITY reviewer SYSTEM "file:///etc/passwd">]><p>Unsafe</p></section>';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<iframe src="frame.html" srcdoc="' . htmlspecialchars($srcdoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Legacy frame fallback</iframe>'
                . '<iframe srcdoc="' . htmlspecialchars($unsafeSrcdoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Unsafe fallback</iframe>',
            'iframe srcdoc review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $iframe = $summary[0];
        $unsafeIframe = $summary[1];

        $t->same('iframe', $iframe['embeddedResource']);
        $t->same($srcdoc, $iframe['srcdoc']);
        $t->same('iframe-srcdoc-inert-fragment-review', $iframe['srcdocReviewPolicy']);
        $t->same(strlen($srcdoc), $iframe['srcdocByteLength']);
        $t->same(hash('sha256', $srcdoc), $iframe['srcdocSha256']);
        $t->same(true, $iframe['srcdocParsed']);
        $t->same([], $iframe['srcdocDiagnostics']);
        $t->same(['article'], $iframe['srcdocTopLevelElementNames']);
        $t->same(1, $iframe['srcdocTopLevelElementCount']);
        $t->same('Preview Open chapter Nested Fallback chart', $iframe['srcdocText']);
        $t->same(strlen('Preview Open chapter Nested Fallback chart'), $iframe['srcdocTextLength']);
        $t->same(hash('sha256', 'Preview Open chapter Nested Fallback chart'), $iframe['srcdocTextSha256']);
        $t->same(['chapter.html#one'], $iframe['srcdocLinkHrefs']);
        $t->same(['cover.jpg'], $iframe['srcdocImageSources']);
        $t->same(1, $iframe['srcdocFormCount']);
        $t->same(['/review'], $iframe['srcdocFormActions']);
        $t->same([], $iframe['srcdocActiveElementNames']);
        $t->same(['iframe', 'canvas'], $iframe['srcdocEmbeddedElementNames']);
        $t->same('Legacy frame fallback', $iframe['fallbackText']);

        $t->same($unsafeSrcdoc, $unsafeIframe['srcdoc']);
        $t->same(false, $unsafeIframe['srcdocParsed']);
        $t->same(['srcdoc-unsafe-or-unparseable'], $unsafeIframe['srcdocDiagnostics']);
        $t->contains('document type', $unsafeIframe['srcdocError']);
        $t->same('Unsafe fallback', $unsafeIframe['fallbackText']);
        $t->contains('srcdoc="&lt;article data-review=&quot;srcdoc&quot;&gt;', $html);
    },
    'summarizes iframe sandbox and permissions policy for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<iframe id="trusted-frame" src="frame.html" sandbox="allow-scripts allow-same-origin allow-popups allow-scripts bad-token" allow="fullscreen *; clipboard-write &#039;self&#039;; geolocation https://maps.example.test; bad&lt;feature *; camera" referrerpolicy="Strict-Origin-When-Cross-Origin" loading="Lazy" allowfullscreen>Frame fallback</iframe>'
                . '<iframe id="bad-frame" src="bad.html" sandbox="" allow="midi &#039;none&#039;; broken&lt;directive" referrerpolicy="unsafe-policy" loading="soon"></iframe>',
            'iframe policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/iframe-policy-summary-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $trusted = $summary[0];
        $invalid = $summary[1];

        $t->same('iframe-policy-metadata-review', $trusted['iframePolicyReview']);
        $t->same([
            'allow-scripts',
            'allow-same-origin',
            'allow-popups',
            'allow-scripts',
            'bad-token',
        ], $trusted['sandboxTokens']);
        $t->same(['allow-scripts', 'allow-same-origin', 'allow-popups'], $trusted['sandboxValidTokens']);
        $t->same(['bad-token'], $trusted['invalidSandboxTokens']);
        $t->same(['allow-scripts'], $trusted['duplicateSandboxTokens']);
        $t->same(false, $trusted['sandboxAllTokensValid']);
        $t->same(true, $trusted['sandboxAllowsScripts']);
        $t->same(true, $trusted['sandboxAllowsSameOrigin']);
        $t->same(true, $trusted['sandboxAllowsScriptsAndSameOrigin']);
        $t->same([
            'invalid-iframe-sandbox-token',
            'duplicate-iframe-sandbox-token',
            'iframe-sandbox-allows-scripts-same-origin',
            'invalid-iframe-allow-directive',
        ], $trusted['iframePolicyIssueCodes']);

        $t->same(5, $trusted['allowDirectiveCount']);
        $t->same([
            'fullscreen',
            'clipboard-write',
            'geolocation',
            'camera',
        ], $trusted['allowFeatures']);
        $t->same(['bad<feature *'], $trusted['invalidAllowDirectives']);
        $t->same(false, $trusted['allowPolicyValid']);
        $t->same(['*'], $trusted['allowDirectives'][0]['allowList']);
        $t->same(["'self'"], $trusted['allowDirectives'][1]['allowList']);
        $t->same(['https://maps.example.test'], $trusted['allowDirectives'][2]['allowList']);
        $t->same(false, $trusted['allowDirectives'][3]['valid']);
        $t->same([], $trusted['allowDirectives'][4]['allowList']);
        $t->same('strict-origin-when-cross-origin', $trusted['referrerPolicy']);
        $t->same(true, $trusted['referrerPolicyValid']);
        $t->same('lazy', $trusted['loadingState']);
        $t->same(true, $trusted['loadingValid']);
        $t->same(true, $trusted['allowFullscreen']);
        $t->same('Frame fallback', $trusted['fallbackText']);

        $t->same([], $invalid['sandboxTokens']);
        $t->same([], $invalid['sandboxValidTokens']);
        $t->same(true, $invalid['sandboxAllTokensValid']);
        $t->same(2, $invalid['allowDirectiveCount']);
        $t->same(['midi'], $invalid['allowFeatures']);
        $t->same(["'none'"], $invalid['allowDirectives'][0]['allowList']);
        $t->same(['broken<directive'], $invalid['invalidAllowDirectives']);
        $t->same(null, $invalid['referrerPolicy']);
        $t->same(false, $invalid['referrerPolicyValid']);
        $t->same(null, $invalid['loadingState']);
        $t->same(false, $invalid['loadingValid']);
        $t->same(false, $invalid['allowFullscreen']);
        $t->same([
            'invalid-iframe-allow-directive',
            'invalid-iframe-referrer-policy',
            'invalid-iframe-loading-state',
        ], $invalid['iframePolicyIssueCodes']);
        $t->contains('allowfullscreen', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/iframe-policy-summary-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html hyperlinks and image-map areas for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>See <a href="chapter.html#intro" target="_blank" rel="noopener noreferrer tag" download="packet.html" hreflang="en" type="text/html" ping="/audit /log" referrerpolicy="no-referrer">Chapter <span>one</span></a></p>'
                . '<p><img src="diagram.png" alt="Diagram" usemap="#figures"><img src="bad.png" alt="Bad" usemap="bad target"></p>'
                . '<map name="figures"><area shape="rect" coords="0,0,10,10" href="diagram.png#hotspot" alt="Diagram hotspot" target="_self" rel="help external"></map>',
            'hyperlink review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $anchor = $summary[0]['children'][1];
        $image = $summary[1]['children'][0];
        $invalidImage = $summary[1]['children'][1];
        $map = $summary[2];
        $area = $map['children'][0];

        $t->same('a', $anchor['name']);
        $t->same('a', $anchor['hyperlink']);
        $t->same('chapter.html#intro', $anchor['href']);
        $t->same('_blank', $anchor['target']);
        $t->same('noopener noreferrer tag', $anchor['relRaw']);
        $t->same(['noopener', 'noreferrer', 'tag'], $anchor['relTokens']);
        $t->same('packet.html', $anchor['download']);
        $t->same('en', $anchor['hreflang']);
        $t->same('text/html', $anchor['mimeType']);
        $t->same('/audit /log', $anchor['pingRaw']);
        $t->same(['/audit', '/log'], $anchor['pingUrls']);
        $t->same('no-referrer', $anchor['referrerpolicy']);
        $t->same('Chapter one', $anchor['label']);
        $t->same('image', $image['embeddedResource']);
        $t->same('diagram.png', $image['src']);
        $t->same('Diagram', $image['alt']);
        $t->same('#figures', $image['useMapRaw']);
        $t->same('figures', $image['useMapName']);
        $t->same(true, $image['useMapValid']);
        $t->same('bad target', $invalidImage['useMapRaw']);
        $t->same('bad target', $invalidImage['useMapName']);
        $t->same(false, $invalidImage['useMapValid']);
        $t->same('map', $map['name']);
        $t->same(['name' => 'figures'], $map['attributes']);
        $t->same('map', $map['imageMap']);
        $t->same('figures', $map['mapNameRaw']);
        $t->same('figures', $map['mapName']);
        $t->same(true, $map['mapNameValid']);
        $t->same(1, $map['areaCount']);
        $t->same(['diagram.png#hotspot'], $map['areaHrefs']);
        $t->same(['Diagram hotspot'], $map['areaLabels']);
        $t->same('diagram.png#hotspot', $map['areas'][0]['href']);
        $t->same('area', $area['name']);
        $t->same('area', $area['hyperlink']);
        $t->same('diagram.png#hotspot', $area['href']);
        $t->same('Diagram hotspot', $area['label']);
        $t->same('rect', $area['shape']);
        $t->same('0,0,10,10', $area['coords']);
        $t->same(['help', 'external'], $area['relTokens']);
        $t->same('<p>See <a download="packet.html" href="chapter.html#intro" hreflang="en" ping="/audit /log" referrerpolicy="no-referrer" rel="noopener noreferrer tag" target="_blank" type="text/html">Chapter <span>one</span></a></p><p><img alt="Diagram" src="diagram.png" usemap="#figures"><img alt="Bad" src="bad.png" usemap="bad target"></p><map name="figures"><area alt="Diagram hotspot" coords="0,0,10,10" href="diagram.png#hotspot" rel="help external" shape="rect" target="_self"></map>', $html);
    },
    'summarizes html image map association and area geometry diagnostics for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>'
                . '<img src="diagram.png" alt="Diagram" usemap="#figures">'
                . '<img src="missing.png" alt="Missing" usemap="#missing">'
                . '<img src="duplicate.png" alt="Duplicate" usemap="#dupe">'
                . '<img src="invalid.png" alt="Invalid" usemap="bad target">'
                . '</p>'
                . '<map name="figures">'
                . '<area shape="rect" coords="0,0,10,10" href="diagram.png#rect" alt="Rectangle">'
                . '<area shape="circle" coords="5,5,0" href="diagram.png#circle" alt="Bad circle">'
                . '<area shape="poly" coords="0,0,10,0,10" href="diagram.png#poly" alt="Bad polygon">'
                . '<area shape="default" coords="99,99" href="diagram.png#default" alt="Default">'
                . '<area shape="rect" coords="1,two,3,4" href="diagram.png#after-default" alt="After default">'
                . '</map>'
                . '<map name="dupe"><area href="dupe-one.html" alt="Dup one"></map>'
                . '<map name="dupe"><area href="dupe-two.html" alt="Dup two"></map>'
                . '<map name="bad target"><area href="bad.html" alt="Bad"></map>'
                . '<map name="unused"><area href="unused.html" alt="Unused"></map>',
            'image map area geometry review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $resolvedImage = $summary[0]['children'][0];
        $missingImage = $summary[0]['children'][1];
        $duplicateImage = $summary[0]['children'][2];
        $invalidImage = $summary[0]['children'][3];
        $map = $summary[1];
        $rectArea = $map['areas'][0];
        $circleArea = $map['areas'][1];
        $polyArea = $map['areas'][2];
        $defaultArea = $map['areas'][3];
        $badCoordArea = $map['areas'][4];
        $firstDuplicateMap = $summary[2];
        $invalidMap = $summary[4];
        $unusedMap = $summary[5];

        $t->same('resolved', $resolvedImage['useMapAssociationState']);
        $t->same(1, $resolvedImage['useMapTargetCount']);
        $t->same(5, $resolvedImage['useMapAreaCount']);
        $t->same([
            'diagram.png#rect',
            'diagram.png#circle',
            'diagram.png#poly',
            'diagram.png#default',
            'diagram.png#after-default',
        ], $resolvedImage['useMapAreaHrefs']);
        $t->same([
            'Rectangle',
            'Bad circle',
            'Bad polygon',
            'Default',
            'After default',
        ], $resolvedImage['useMapAreaLabels']);
        $t->same([], $resolvedImage['useMapIssues']);
        $t->same('missing-map', $missingImage['useMapAssociationState']);
        $t->same([['code' => 'missing-image-map', 'mapName' => 'missing']], $missingImage['useMapIssues']);
        $t->same('duplicate-map-name', $duplicateImage['useMapAssociationState']);
        $t->same(2, $duplicateImage['useMapTargetCount']);
        $t->same(['dupe-one.html', 'dupe-two.html'], $duplicateImage['useMapAreaHrefs']);
        $t->same('invalid-reference', $invalidImage['useMapAssociationState']);
        $t->same([['code' => 'invalid-usemap-reference', 'useMapRaw' => 'bad target']], $invalidImage['useMapIssues']);

        $t->same('referenced', $map['imageMapAssociationState']);
        $t->same(1, $map['imageMapReferenceCount']);
        $t->same(['diagram.png'], $map['imageMapReferenceSources']);
        $t->same([], $map['imageMapIssues']);
        $t->same('rect', $rectArea['areaShape']);
        $t->same([0.0, 0.0, 10.0, 10.0], $rectArea['coordsNumbers']);
        $t->same(true, $rectArea['areaGeometryValid']);
        $t->same('circle', $circleArea['areaShape']);
        $t->same(false, $circleArea['areaGeometryValid']);
        $t->same([['code' => 'invalid-circle-area-radius', 'radius' => 0.0]], $circleArea['areaGeometryIssues']);
        $t->same('poly', $polyArea['areaShape']);
        $t->same([[
            'code' => 'invalid-area-coord-count',
            'shape' => 'poly',
            'expected' => 'even-number-at-least-6',
            'actual' => 5,
        ]], $polyArea['areaGeometryIssues']);
        $t->same('default', $defaultArea['areaShape']);
        $t->same(false, $defaultArea['coordsRequired']);
        $t->same(true, $defaultArea['areaGeometryValid']);
        $t->same([['code' => 'default-area-coords-ignored']], $defaultArea['areaGeometryIssues']);
        $t->same(false, $badCoordArea['coordsValid']);
        $t->same([['code' => 'invalid-area-coord-number', 'token' => 'two']], $badCoordArea['areaGeometryIssues']);
        $t->same(1, $map['defaultAreaCount']);
        $t->same(3, $map['firstDefaultAreaIndex']);
        $t->same([
            'code' => 'default-area-precedes-specific-area',
            'defaultAreaIndex' => 3,
            'coveredAreaIndexes' => [4],
        ], $map['defaultAreaPrecedenceIssue']);
        $t->same(5, $map['areaGeometryIssueCount']);
        $t->same('duplicate-map-name', $firstDuplicateMap['imageMapAssociationState']);
        $t->same(2, $firstDuplicateMap['imageMapDuplicateNameCount']);
        $t->same([['code' => 'duplicate-map-name', 'mapName' => 'dupe', 'count' => 2]], $firstDuplicateMap['imageMapIssues']);
        $t->same('invalid-map-name', $invalidMap['imageMapAssociationState']);
        $t->same([['code' => 'invalid-map-name', 'mapNameRaw' => 'bad target']], $invalidMap['imageMapIssues']);
        $t->same('unreferenced', $unusedMap['imageMapAssociationState']);
        $t->same([['code' => 'unreferenced-image-map', 'mapName' => 'unused']], $unusedMap['imageMapIssues']);
        $t->contains('<area alt="Default" coords="99,99" href="diagram.png#default" shape="default">', $html);
    },
    'summarizes html hyperlink navigation side-effect provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><a href="https://example.test/report" target="_blank" rel="external opener external bad&lt;tag" download ping="https://audit.example.test/log /relative javascript:alert(1) mailto:ops@example.test" referrerpolicy="strict-origin-when-cross-origin">External report</a></p>'
                . '<p><a href="#local" target="_top" rel="noopener noreferrer" referrerpolicy="bogus">Local target</a></p>'
                . '<map name="side-effects"><area alt="Script hotspot" href="javascript:alert(1)" target="_blank" rel="noreferrer" ping="/area-ping"></map>',
            'hyperlink navigation side-effect review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hyperlink-navigation-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $external = $summary[0]['children'][0];
        $local = $summary[1]['children'][0];
        $area = $summary[2]['children'][0];

        $t->same('a', $external['hyperlinkNavigationReview']);
        $t->same('absolute', $external['hrefKind']);
        $t->same('https', $external['hrefScheme']);
        $t->same(false, $external['hrefUnsafe']);
        $t->same('_blank', $external['targetName']);
        $t->same(true, $external['targetReserved']);
        $t->same(true, $external['targetBlank']);
        $t->same(true, $external['targetOpenerAllowed']);
        $t->same(false, $external['targetNoopener']);
        $t->same(true, $external['downloadRequested']);
        $t->same(null, $external['downloadSuggestedFilename']);
        $t->same(['external', 'opener'], $external['hyperlinkRelTokens']);
        $t->same(['external' => 2, 'opener' => 1], $external['hyperlinkRelTokenCounts']);
        $t->same(['external'], $external['duplicateHyperlinkRelTokens']);
        $t->same(['bad<tag'], $external['invalidHyperlinkRelTokens']);
        $t->same(['opener'], $external['hyperlinkSecurityRelTokens']);
        $t->same('strict-origin-when-cross-origin', $external['referrerPolicy']);
        $t->same(true, $external['referrerPolicyValid']);
        $t->same(true, $external['pingSideEffect']);
        $t->same(4, $external['pingUrlCount']);
        $t->same(['javascript:alert(1)'], $external['unsafePingUrls']);
        $t->same(['mailto:ops@example.test'], $external['nonHttpPingUrls']);
        $t->same('absolute', $external['pingUrlRecords'][0]['kind']);
        $t->same('https', $external['pingUrlRecords'][0]['scheme']);
        $t->same('relative', $external['pingUrlRecords'][1]['kind']);
        $t->same('javascript', $external['pingUrlRecords'][2]['scheme']);
        $t->same([
            ['code' => 'target-blank-explicit-opener'],
            ['code' => 'invalid-rel-token', 'relToken' => 'bad<tag'],
            ['code' => 'duplicate-rel-token', 'relToken' => 'external', 'count' => 2],
            ['code' => 'unsafe-ping-url', 'url' => 'javascript:alert(1)', 'scheme' => 'javascript'],
            ['code' => 'non-http-ping-url', 'url' => 'mailto:ops@example.test', 'scheme' => 'mailto'],
        ], $external['navigationIssues']);

        $t->same('fragment', $local['hrefKind']);
        $t->same('_top', $local['targetName']);
        $t->same(true, $local['targetReserved']);
        $t->same(false, $local['targetBlank']);
        $t->same(['noopener', 'noreferrer'], $local['hyperlinkSecurityRelTokens']);
        $t->same(null, $local['referrerPolicy']);
        $t->same(false, $local['referrerPolicyValid']);
        $t->same([
            ['code' => 'invalid-referrer-policy', 'referrerPolicyRaw' => 'bogus'],
        ], $local['navigationIssues']);

        $t->same('area', $area['hyperlinkNavigationReview']);
        $t->same('absolute', $area['hrefKind']);
        $t->same('javascript', $area['hrefScheme']);
        $t->same(true, $area['hrefUnsafe']);
        $t->same(true, $area['targetBlank']);
        $t->same(false, $area['targetOpenerAllowed']);
        $t->same(true, $area['targetNoopener']);
        $t->same(['noreferrer'], $area['hyperlinkSecurityRelTokens']);
        $t->same(['/area-ping'], $area['pingUrls']);
        $t->same([
            ['code' => 'unsafe-href', 'href' => 'javascript:alert(1)', 'scheme' => 'javascript'],
        ], $area['navigationIssues']);

        $t->same('<p><a download="" href="https://example.test/report" ping="https://audit.example.test/log /relative javascript:alert(1) mailto:ops@example.test" referrerpolicy="strict-origin-when-cross-origin" rel="external opener external bad&lt;tag" target="_blank">External report</a></p><p><a href="#local" referrerpolicy="bogus" rel="noopener noreferrer" target="_top">Local target</a></p><map name="side-effects"><area alt="Script hotspot" href="javascript:alert(1)" ping="/area-ping" rel="noreferrer" target="_blank"></map>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/hyperlink-navigation-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html hyperlink fragment target provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<h2 id="intro">Intro</h2>'
                . '<p><a href="#intro">Intro link</a><a href="#legacy">Legacy link</a><a href="#">Top</a><a href="#missing">Missing</a><a href="#bad target">Bad</a></p>'
                . '<a name="legacy">Legacy target</a>'
                . '<div id="dup">First duplicate</div><section id="dup">Second duplicate</section>'
                . '<p><a href="#dup">Duplicate</a></p>'
                . '<map name="toc"><area href="#intro" alt="Intro area"></map>',
            'hyperlink fragment target review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hyperlink-fragment-target-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $intro = $summary[1]['children'][0];
        $legacy = $summary[1]['children'][1];
        $top = $summary[1]['children'][2];
        $missing = $summary[1]['children'][3];
        $invalid = $summary[1]['children'][4];
        $duplicate = $summary[5]['children'][0];
        $area = $summary[6]['children'][0];

        $t->same('hyperlink-fragment-target-review', $intro['hrefFragmentReviewPolicy']);
        $t->same('intro', $intro['hrefFragmentRaw']);
        $t->same('intro', $intro['hrefFragmentTarget']);
        $t->same(true, $intro['hrefFragmentTargetValid']);
        $t->same(true, $intro['hrefFragmentTargetFound']);
        $t->same(1, $intro['hrefFragmentTargetCount']);
        $t->same('id', $intro['hrefFragmentTargetKind']);
        $t->same('h2', $intro['hrefFragmentTargetElement']['tag']);
        $t->same(2, $intro['hrefFragmentTargetElement']['headingLevel']);
        $t->same([], $intro['hrefFragmentIssueCodes']);

        $t->same('anchor-name', $legacy['hrefFragmentTargetKind']);
        $t->same('a', $legacy['hrefFragmentTargetElement']['tag']);
        $t->same('legacy', $legacy['hrefFragmentTargetElement']['nameAttribute']);
        $t->same('Legacy target', $legacy['hrefFragmentTargetElement']['text']);
        $t->same([], $legacy['hrefFragmentIssueCodes']);

        $t->same('', $top['hrefFragmentRaw']);
        $t->same(true, $top['hrefFragmentDocumentTop']);
        $t->same(false, $top['hrefFragmentTargetFound']);
        $t->same('document-top', $top['hrefFragmentTargetKind']);
        $t->same([], $top['hrefFragmentIssueCodes']);

        $t->same('missing-target', $missing['hrefFragmentTargetKind']);
        $t->same(['missing-hyperlink-fragment-target'], $missing['hrefFragmentIssueCodes']);
        $t->same([['code' => 'missing-hyperlink-fragment-target', 'fragmentTarget' => 'missing']], $missing['hrefFragmentIssues']);

        $t->same(false, $invalid['hrefFragmentTargetValid']);
        $t->same('invalid-reference', $invalid['hrefFragmentTargetKind']);
        $t->same(['invalid-hyperlink-fragment-target'], $invalid['hrefFragmentIssueCodes']);

        $t->same('duplicate-id', $duplicate['hrefFragmentTargetKind']);
        $t->same(2, $duplicate['hrefFragmentTargetCount']);
        $t->same(['duplicate-hyperlink-fragment-target'], $duplicate['hrefFragmentIssueCodes']);
        $t->same(['div', 'section'], array_map(static fn (array $target): string => (string) $target['tag'], $duplicate['hrefFragmentTargetElements']));
        $t->same([[
            'code' => 'duplicate-hyperlink-fragment-target',
            'fragmentTarget' => 'dup',
            'targetType' => 'id',
            'count' => 2,
        ]], $duplicate['hrefFragmentIssues']);

        $t->same('id', $area['hrefFragmentTargetKind']);
        $t->same('h2', $area['hrefFragmentTargetElement']['tag']);
        $t->contains('<a href="#bad target">Bad</a>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/hyperlink-fragment-target-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html base link and meta metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<base href="https://example.test/docs/" target="_blank">'
                . '<link rel="preload stylesheet modulepreload" href="review.css" as="style" type="text/css" media="screen and (min-width: 40em)" hreflang="en" crossorigin="anonymous" integrity="sha384-review" referrerpolicy="no-referrer" sizes="any" imagesrcset="cover.avif 1x, cover@2x.avif 2x" imagesizes="100vw" fetchpriority="high">'
                . '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta property="og:title" content="Review Packet"><meta http-equiv="refresh" content="5; url=https://example.test/next?stage=review"><p>Body</p>',
            'document metadata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/document-metadata-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $base = $summary[0];
        $link = $summary[1];
        $charsetMeta = $summary[2];
        $viewportMeta = $summary[3];
        $propertyMeta = $summary[4];
        $refreshMeta = $summary[5];
        $paragraph = $summary[6];

        $t->same('base', $base['documentMetadata']);
        $t->same('https://example.test/docs/', $base['href']);
        $t->same('_blank', $base['target']);
        $t->same('link', $link['documentMetadata']);
        $t->same('review.css', $link['href']);
        $t->same('preload stylesheet modulepreload', $link['relRaw']);
        $t->same(['preload', 'stylesheet', 'modulepreload'], $link['relTokens']);
        $t->same('style', $link['as']);
        $t->same('screen and (min-width: 40em)', $link['media']);
        $t->same('en', $link['hreflang']);
        $t->same('text/css', $link['mimeType']);
        $t->same('anonymous', $link['crossorigin']);
        $t->same('sha384-review', $link['integrity']);
        $t->same('no-referrer', $link['referrerpolicy']);
        $t->same('any', $link['sizes']);
        $t->same('cover.avif 1x, cover@2x.avif 2x', $link['imageSrcset']);
        $t->same('cover.avif', $link['imageSrcsetCandidates'][0]['url']);
        $t->same(['2x'], $link['imageSrcsetCandidates'][1]['descriptors']);
        $t->same('100vw', $link['imageSizes']);
        $t->same('high', $link['fetchpriority']);
        $t->same('meta', $charsetMeta['documentMetadata']);
        $t->same('UTF-8', $charsetMeta['charset']);
        $t->same('viewport', $viewportMeta['nameAttribute']);
        $t->same('width=device-width, initial-scale=1', $viewportMeta['content']);
        $t->same('og:title', $propertyMeta['property']);
        $t->same('Review Packet', $propertyMeta['content']);
        $t->same('refresh', $refreshMeta['httpEquivRaw']);
        $t->same('refresh', $refreshMeta['httpEquiv']);
        $t->same('5; url=https://example.test/next?stage=review', $refreshMeta['content']);
        $t->same([
            'contentRaw' => '5; url=https://example.test/next?stage=review',
            'delayRaw' => '5',
            'delay' => 5.0,
            'urlRaw' => 'https://example.test/next?stage=review',
            'url' => 'https://example.test/next?stage=review',
        ], $refreshMeta['refresh']);
        $t->same('Body', $paragraph['text']);
        $t->same('<base href="https://example.test/docs/" target="_blank"><link as="style" crossorigin="anonymous" fetchpriority="high" href="review.css" hreflang="en" imagesizes="100vw" imagesrcset="cover.avif 1x, cover@2x.avif 2x" integrity="sha384-review" media="screen and (min-width: 40em)" referrerpolicy="no-referrer" rel="preload stylesheet modulepreload" sizes="any" type="text/css"><meta charset="UTF-8"><meta content="width=device-width, initial-scale=1" name="viewport"><meta content="Review Packet" property="og:title"><meta content="5; url=https://example.test/next?stage=review" http-equiv="refresh"><p>Body</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/document-metadata-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html link resource hint and preload provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<link rel="preload modulepreload dns-prefetch preload custom-rel bad&lt;tag" href="app.js" as="Script" crossorigin="anonymous" integrity="sha384-app" fetchpriority="High">'
                . '<link rel="preconnect preload" as="bogus">'
                . '<link rel="stylesheet icon canonical" href="/site.css"><p>Body</p>',
            'link resource review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/link-resource-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $preload = $summary[0];
        $missingHref = $summary[1];
        $stylesheet = $summary[2];

        $t->same('link', $preload['linkResourceReview']);
        $t->same(['preload', 'modulepreload', 'dns-prefetch', 'custom-rel'], $preload['linkRelTokens']);
        $t->same(['preload' => 2, 'modulepreload' => 1, 'dns-prefetch' => 1, 'custom-rel' => 1], $preload['linkRelTokenCounts']);
        $t->same(['preload'], $preload['duplicateLinkRelTokens']);
        $t->same(['bad<tag'], $preload['invalidLinkRelTokens']);
        $t->same(['custom-rel'], $preload['customLinkRelTokens']);
        $t->same(['preload', 'modulepreload', 'dns-prefetch'], $preload['linkResourceRelTokens']);
        $t->same(['preload', 'modulepreload', 'resource-hint'], $preload['linkResourceKinds']);
        $t->same('preload', $preload['linkPrimaryResourceKind']);
        $t->same(['dns-prefetch'], $preload['linkResourceHintTokens']);
        $t->same(true, $preload['linkHrefRequired']);
        $t->same(true, $preload['linkHrefPresent']);
        $t->same('Script', $preload['preloadAsRaw']);
        $t->same('script', $preload['preloadAs']);
        $t->same(true, $preload['preloadAsRequired']);
        $t->same(true, $preload['preloadAsValid']);
        $t->same([
            ['code' => 'invalid-link-rel-token', 'relToken' => 'bad<tag'],
            ['code' => 'duplicate-link-rel-token', 'relToken' => 'preload', 'count' => 2],
        ], $preload['linkIssues']);
        $t->same('anonymous', $preload['crossorigin']);
        $t->same('sha384-app', $preload['integrity']);
        $t->same('High', $preload['fetchpriority']);

        $t->same(['preconnect', 'preload'], $missingHref['linkResourceRelTokens']);
        $t->same(['resource-hint', 'preload'], $missingHref['linkResourceKinds']);
        $t->same('resource-hint', $missingHref['linkPrimaryResourceKind']);
        $t->same(true, $missingHref['linkHrefRequired']);
        $t->same(false, $missingHref['linkHrefPresent']);
        $t->same('bogus', $missingHref['preloadAs']);
        $t->same(false, $missingHref['preloadAsValid']);
        $t->same([
            ['code' => 'missing-link-href', 'relTokens' => ['preconnect', 'preload']],
            ['code' => 'invalid-preload-as', 'asRaw' => 'bogus'],
        ], $missingHref['linkIssues']);

        $t->same(['stylesheet', 'icon', 'canonical'], $stylesheet['linkResourceRelTokens']);
        $t->same(['stylesheet', 'icon', 'canonical'], $stylesheet['linkResourceKinds']);
        $t->same('stylesheet', $stylesheet['linkPrimaryResourceKind']);
        $t->same(true, $stylesheet['linkHrefRequired']);
        $t->same([], $stylesheet['linkIssues']);
        $t->same(false, $stylesheet['preloadAsRequired']);
        $t->same(true, $stylesheet['preloadAsValid']);
        $t->same('<link as="Script" crossorigin="anonymous" fetchpriority="High" href="app.js" integrity="sha384-app" rel="preload modulepreload dns-prefetch preload custom-rel bad&lt;tag"><link as="bogus" rel="preconnect preload"><link href="/site.css" rel="stylesheet icon canonical"><p>Body</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/link-resource-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html link blocking token provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<link rel="stylesheet preload" href="/critical.css" as="style" blocking="render render custom">'
                . '<link rel="preconnect" href="https://fonts.example" blocking="layout">'
                . '<link rel="author" href="/about" blocking>'
                . '<link rel="stylesheet" href="/plain.css">',
            'link blocking review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/link-blocking-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $critical = $summary[0];
        $preconnect = $summary[1];
        $emptyBlocking = $summary[2];
        $plain = $summary[3];

        $t->same('render render custom', $critical['blockingRaw']);
        $t->same(['render', 'render', 'custom'], $critical['blockingTokens']);
        $t->same('link-render-blocking-token-review', $critical['linkBlockingReviewPolicy']);
        $t->same(true, $critical['linkBlockingAttributePresent']);
        $t->same(['render', 'render', 'custom'], $critical['linkBlockingTokens']);
        $t->same(['render' => 2, 'custom' => 1], $critical['linkBlockingTokenCounts']);
        $t->same(['render'], $critical['duplicateLinkBlockingTokens']);
        $t->same(['custom'], $critical['invalidLinkBlockingTokens']);
        $t->same(true, $critical['linkRenderBlockingTokenPresent']);
        $t->same(true, $critical['linkRenderBlockingResourceCandidate']);
        $t->same('declared-render-blocking-resource', $critical['linkBlockingReviewKind']);
        $t->same([
            ['code' => 'invalid-link-blocking-token', 'blockingToken' => 'custom', 'count' => 1],
            ['code' => 'duplicate-link-blocking-token', 'blockingToken' => 'render', 'count' => 2],
        ], $critical['linkIssues']);
        $t->same(['stylesheet', 'preload'], $critical['linkResourceKinds']);

        $t->same(['layout' => 1], $preconnect['linkBlockingTokenCounts']);
        $t->same(['layout'], $preconnect['invalidLinkBlockingTokens']);
        $t->same([], $preconnect['duplicateLinkBlockingTokens']);
        $t->same(false, $preconnect['linkRenderBlockingTokenPresent']);
        $t->same(false, $preconnect['linkRenderBlockingResourceCandidate']);
        $t->same('declared-non-render-token', $preconnect['linkBlockingReviewKind']);
        $t->same([
            ['code' => 'invalid-link-blocking-token', 'blockingToken' => 'layout', 'count' => 1],
        ], $preconnect['linkIssues']);

        $t->same(false, $emptyBlocking['linkHrefRequired']);
        $t->same('', $emptyBlocking['blockingRaw']);
        $t->same([], $emptyBlocking['linkBlockingTokens']);
        $t->same([], $emptyBlocking['linkBlockingTokenCounts']);
        $t->same(true, $emptyBlocking['linkBlockingAttributePresent']);
        $t->same('empty-blocking-attribute', $emptyBlocking['linkBlockingReviewKind']);
        $t->same([], $emptyBlocking['linkIssues']);

        $t->same(null, $plain['blockingRaw']);
        $t->same(false, $plain['linkBlockingAttributePresent']);
        $t->same('not-declared', $plain['linkBlockingReviewKind']);
        $t->same(true, $plain['linkRenderBlockingResourceCandidate']);
        $t->same([], $plain['linkIssues']);

        $t->same('<link as="style" blocking="render render custom" href="/critical.css" rel="stylesheet preload"><link blocking="layout" href="https://fonts.example" rel="preconnect"><link blocking="" href="/about" rel="author"><link href="/plain.css" rel="stylesheet">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/link-blocking-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html link and style loading policy metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<link rel="preload stylesheet" href="app.css" as="style" blocking="render render bad-token" crossorigin="credentialed" fetchpriority="urgent" referrerpolicy="bogus">'
                . '<style media="screen" blocking="render bad-token render">body{color:blue}</style>'
                . '<link rel="modulepreload" href="app.mjs" blocking="render" crossorigin="use-credentials" fetchpriority="low" referrerpolicy="strict-origin">',
            'link and style loading policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/link-style-loading-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $invalidLink = $summary[0];
        $style = $summary[1];
        $validLink = $summary[2];

        $t->same('link-loading-policy-metadata-review', $invalidLink['linkLoadingPolicyReview']);
        $t->same('render render bad-token', $invalidLink['blockingRaw']);
        $t->same(['render', 'render', 'bad-token'], $invalidLink['blockingTokens']);
        $t->same(['render' => 2, 'bad-token' => 1], $invalidLink['linkBlockingTokenCounts']);
        $t->same(['render'], $invalidLink['duplicateLinkBlockingTokens']);
        $t->same(['bad-token'], $invalidLink['invalidLinkBlockingTokens']);
        $t->same(false, $invalidLink['linkBlockingAllTokensValid']);
        $t->same(null, $invalidLink['linkCrossoriginState']);
        $t->same(false, $invalidLink['linkCrossoriginValid']);
        $t->same(null, $invalidLink['linkFetchPriority']);
        $t->same(false, $invalidLink['linkFetchPriorityValid']);
        $t->same(null, $invalidLink['linkReferrerPolicy']);
        $t->same(false, $invalidLink['linkReferrerPolicyValid']);
        $t->same([
            'invalid-link-crossorigin',
            'invalid-link-fetchpriority',
            'invalid-link-referrerpolicy',
            'invalid-link-blocking-token',
            'duplicate-link-blocking-token',
        ], $invalidLink['linkLoadingIssueCodes']);

        $t->same('style-loading-policy-metadata-review', $style['styleLoadingPolicyReview']);
        $t->same(['render' => 2, 'bad-token' => 1], $style['styleBlockingTokenCounts']);
        $t->same(['render'], $style['duplicateStyleBlockingTokens']);
        $t->same(['bad-token'], $style['invalidStyleBlockingTokens']);
        $t->same(false, $style['styleBlockingAllTokensValid']);
        $t->same([
            'invalid-style-blocking-token',
            'duplicate-style-blocking-token',
        ], $style['styleLoadingIssueCodes']);

        $t->same('use-credentials', $validLink['linkCrossoriginState']);
        $t->same(true, $validLink['linkCrossoriginValid']);
        $t->same('low', $validLink['linkFetchPriority']);
        $t->same(true, $validLink['linkFetchPriorityValid']);
        $t->same('strict-origin', $validLink['linkReferrerPolicy']);
        $t->same(true, $validLink['linkReferrerPolicyValid']);
        $t->same(['render' => 1], $validLink['linkBlockingTokenCounts']);
        $t->same([], $validLink['linkLoadingIssues']);

        $t->same('<link as="style" blocking="render render bad-token" crossorigin="credentialed" fetchpriority="urgent" href="app.css" referrerpolicy="bogus" rel="preload stylesheet"><style blocking="render bad-token render" media="screen">body{color:blue}</style><link blocking="render" crossorigin="use-credentials" fetchpriority="low" href="app.mjs" referrerpolicy="strict-origin" rel="modulepreload">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/link-style-loading-policy-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html figure caption state for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<figure id="fig-review"><img src="chart.png" alt="Quarterly chart"><figcaption>Figure <strong>one</strong>: imports</figcaption><p>Fallback note</p><figcaption>Extra caption</figcaption></figure>'
                . '<figcaption data-review="orphan">Orphan caption</figcaption>',
            'figure caption review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $figure = $summary[0];
        $image = $figure['children'][0];
        $caption = $figure['children'][1];
        $extraCaption = $figure['children'][3];
        $orphanCaption = $summary[1];

        $t->same('figure', $figure['name']);
        $t->same('figure', $figure['figurePart']);
        $t->same('Figure one: imports', $figure['captionText']);
        $t->same(2, $figure['captionCount']);
        $t->same('image', $image['embeddedResource']);
        $t->same('chart.png', $image['src']);
        $t->same('Quarterly chart', $image['alt']);
        $t->same('figcaption', $caption['name']);
        $t->same('caption', $caption['figurePart']);
        $t->same('Figure one: imports', $caption['captionText']);
        $t->same('Extra caption', $extraCaption['captionText']);
        $t->same('figcaption', $orphanCaption['name']);
        $t->same('caption', $orphanCaption['figurePart']);
        $t->same('Orphan caption', $orphanCaption['captionText']);
        $t->same(['review' => 'orphan'], $orphanCaption['dataset']);
        $t->same('<figure id="fig-review"><img alt="Quarterly chart" src="chart.png"><figcaption>Figure <strong>one</strong>: imports</figcaption><p>Fallback note</p><figcaption>Extra caption</figcaption></figure><figcaption data-review="orphan">Orphan caption</figcaption>', $html);
    },
    'summarizes html table structure spans and header references for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<table id="review"><caption>Quarterly <strong>review</strong></caption><colgroup span="2"><col span="3"><col span="0"></colgroup><thead><tr><th id="h1" scope="col" abbr="Q1">Quarter</th><th id="h2" scope="bad" colspan="2">Status</th></tr></thead><tbody><tr><th id="r1" scope="row">Batch A</th><td headers="h1 r1" rowspan="0" colspan="3">Ready</td><td colspan="2000" rowspan="-1">Overflow</td></tr></tbody></table>',
            'table structure review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $table = $summary[0];
        $caption = $table['children'][0];
        $colgroup = $table['children'][1];
        $firstColumn = $colgroup['children'][0];
        $invalidColumn = $colgroup['children'][1];
        $thead = $table['children'][2];
        $headRow = $thead['children'][0];
        $quarterHeader = $headRow['children'][0];
        $statusHeader = $headRow['children'][1];
        $tbody = $table['children'][3];
        $bodyRow = $tbody['children'][0];
        $rowHeader = $bodyRow['children'][0];
        $readyCell = $bodyRow['children'][1];
        $overflowCell = $bodyRow['children'][2];

        $t->same('table', $table['tablePart']);
        $t->same('Quarterly review', $table['captionText']);
        $t->same(1, $table['captionCount']);
        $t->same('caption', $caption['tablePart']);
        $t->same('Quarterly review', $caption['captionText']);
        $t->same('column-group', $colgroup['tablePart']);
        $t->same('2', $colgroup['spanRaw']);
        $t->same(2, $colgroup['span']);
        $t->same('column', $firstColumn['tablePart']);
        $t->same('3', $firstColumn['spanRaw']);
        $t->same(3, $firstColumn['span']);
        $t->same('0', $invalidColumn['spanRaw']);
        $t->same(1, $invalidColumn['span']);

        $t->same('header-group', $thead['tablePart']);
        $t->same('body-group', $tbody['tablePart']);
        $t->same('row', $headRow['tablePart']);
        $t->same('row', $bodyRow['tablePart']);

        $t->same('cell', $quarterHeader['tablePart']);
        $t->same('header', $quarterHeader['tableCell']);
        $t->same(1, $quarterHeader['colSpan']);
        $t->same(1, $quarterHeader['rowSpan']);
        $t->same('col', $quarterHeader['scopeRaw']);
        $t->same('col', $quarterHeader['scope']);
        $t->same('Q1', $quarterHeader['abbr']);
        $t->same([], $quarterHeader['headers']);
        $t->same('bad', $statusHeader['scopeRaw']);
        $t->same(null, $statusHeader['scope']);
        $t->same('2', $statusHeader['colSpanRaw']);
        $t->same(2, $statusHeader['colSpan']);

        $t->same('header', $rowHeader['tableCell']);
        $t->same('row', $rowHeader['scope']);
        $t->same('data', $readyCell['tableCell']);
        $t->same('h1 r1', $readyCell['headersRaw']);
        $t->same(['h1', 'r1'], $readyCell['headers']);
        $t->same('3', $readyCell['colSpanRaw']);
        $t->same(3, $readyCell['colSpan']);
        $t->same('0', $readyCell['rowSpanRaw']);
        $t->same(0, $readyCell['rowSpan']);
        $t->same('2000', $overflowCell['colSpanRaw']);
        $t->same(1000, $overflowCell['colSpan']);
        $t->same('-1', $overflowCell['rowSpanRaw']);
        $t->same(1, $overflowCell['rowSpan']);
        $t->same('<table id="review"><caption>Quarterly <strong>review</strong></caption><colgroup span="2"><col span="3"><col span="0"></colgroup><thead><tr><th abbr="Q1" id="h1" scope="col">Quarter</th><th colspan="2" id="h2" scope="bad">Status</th></tr></thead><tbody><tr><th id="r1" scope="row">Batch A</th><td colspan="3" headers="h1 r1" rowspan="0">Ready</td><td colspan="2000" rowspan="-1">Overflow</td></tr></tbody></table>', $html);
    },
    'resolves html table header abbr and scope provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<table id="review-grid" summary="Legacy summary"><caption>Import matrix</caption><thead><tr>'
                . '<th id="region" scope="col" abbr="Reg" colspan="2">Region</th>'
                . '<th id="period" scope="col" abbr="Q2">Period</th>'
                . '<th id="dup" scope="col" abbr="First">Duplicate One</th>'
                . '<th id="dup" scope="colgroup" abbr="Second">Duplicate Two</th>'
                . '<td id="note">Not a header</td>'
                . '</tr></thead><tbody><tr><th id="row-a" scope="row" abbr="A" rowspan="2">Batch A</th>'
                . '<td headers="region row-a dup dup missing note bad<tag period outer" colspan="2" rowspan="2">Ready</td>'
                . '</tr></tbody></table>'
                . '<table><tr><th id="outer" scope="col" abbr="Out">Outer table</th></tr></table>',
            'table header abbr scope reference review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $table = $summary[0];
        $headRow = $table['children'][1]['children'][0];
        $regionHeader = $headRow['children'][0];
        $duplicateHeader = $headRow['children'][2];
        $duplicateHeaderSecond = $headRow['children'][3];
        $bodyRow = $table['children'][2]['children'][0];
        $rowHeader = $bodyRow['children'][0];
        $readyCell = $bodyRow['children'][1];
        $references = $readyCell['headerReferences'];

        $t->same('table', $table['tablePart']);
        $t->same(['id' => 'review-grid', 'summary' => 'Legacy summary'], $table['attributes']);
        $t->same('Import matrix', $table['captionText']);
        $t->same(5, $table['tableHeaderCellCount']);
        $t->same(['region', 'period', 'dup', 'row-a'], $table['tableHeaderIds']);
        $t->same(['dup'], $table['duplicateTableHeaderIds']);
        $t->same(['duplicate-table-header-id'], $table['tableHeaderIssueCodes']);
        $t->same('dup', $table['tableHeaderIssues'][0]['headerId'] ?? null);
        $t->same(2, $table['tableHeaderIssues'][0]['count'] ?? null);
        $t->same(['Duplicate One', 'Duplicate Two'], $table['tableHeaderIssues'][0]['texts'] ?? null);
        $t->same(2, $table['tableDataCellCount']);
        $t->same(['dup'], $table['tableDuplicateHeaderIds']);
        $t->same(1, $table['explicitHeaderReferenceCellCount']);
        $t->same(['region', 'row-a', 'dup', 'missing', 'note', 'period', 'outer'], $table['explicitHeaderReferenceIds']);
        $t->same(['dup'], $table['duplicateHeaderReferenceIds']);
        $t->same(['bad<tag'], $table['invalidHeaderReferenceTokens']);
        $t->same(['region', 'row-a', 'period'], $table['resolvedHeaderReferenceIds']);
        $t->same(['missing', 'outer'], $table['missingHeaderReferenceIds']);
        $t->same(['note'], $table['nonHeaderReferenceIds']);
        $t->same(['dup'], $table['duplicateHeaderTargetIds']);
        $t->same(8, $table['tableHeaderReferenceIssueCount']);
        $t->same([
            'duplicate-table-header-id',
            'duplicate-table-header-reference-token',
            'missing-table-header-target',
            'non-header-table-header-target',
            'invalid-table-header-reference-token',
        ], $table['tableHeaderReferenceIssueCodes']);
        $t->same(false, $table['tableHeaderReferencesResolved']);
        $t->same('Reg', $regionHeader['abbr']);
        $t->same('col', $regionHeader['scope']);
        $t->same('First', $duplicateHeader['abbr']);
        $t->same('Second', $duplicateHeaderSecond['abbr']);
        $t->same('A', $rowHeader['abbr']);
        $t->same('row', $rowHeader['scope']);
        $t->same('2', $readyCell['colSpanRaw']);
        $t->same(2, $readyCell['colSpan']);
        $t->same('2', $readyCell['rowSpanRaw']);
        $t->same(2, $readyCell['rowSpan']);

        $t->same('nearest-table-th-idref-review', $readyCell['headerReferenceReviewPolicy']);
        $t->same(['region', 'row-a', 'dup', 'missing', 'note', 'period', 'outer'], $readyCell['headerReferenceIds']);
        $t->same(['bad<tag'], $readyCell['invalidHeaderReferenceTokens']);
        $t->same(['dup'], $readyCell['duplicateHeaderReferenceIds']);
        $t->same(['region', 'row-a', 'period'], $readyCell['resolvedHeaderReferenceIds']);
        $t->same(['missing', 'outer'], $readyCell['missingHeaderReferenceIds']);
        $t->same(['note'], $readyCell['nonHeaderReferenceIds']);
        $t->same(['dup'], $readyCell['duplicateHeaderTargetIds']);
        $t->same([
            'Region',
            'Batch A',
            'Duplicate One',
            'Duplicate Two',
            'Duplicate One',
            'Duplicate Two',
            'Period',
        ], $readyCell['resolvedHeaderTexts']);
        $t->same(['region', 'row-a', 'dup', 'dup', 'dup', 'dup', 'period'], $readyCell['resolvedHeaderIds']);
        $t->same([
            [
                'code' => 'duplicate-table-header-id',
                'token' => 'dup',
                'index' => 2,
                'targetCount' => 2,
                'targetTexts' => ['Duplicate One', 'Duplicate Two'],
            ],
            [
                'code' => 'duplicate-table-header-reference-token',
                'token' => 'dup',
                'index' => 3,
                'firstIndex' => 2,
            ],
            [
                'code' => 'duplicate-table-header-id',
                'token' => 'dup',
                'index' => 3,
                'targetCount' => 2,
                'targetTexts' => ['Duplicate One', 'Duplicate Two'],
            ],
            ['code' => 'missing-table-header-target', 'token' => 'missing', 'index' => 4],
            [
                'code' => 'non-header-table-header-target',
                'token' => 'note',
                'index' => 5,
                'targetNames' => ['td'],
                'targetTexts' => ['Not a header'],
            ],
            ['code' => 'invalid-table-header-reference-token', 'token' => 'bad<tag', 'index' => 6],
            ['code' => 'missing-table-header-target', 'token' => 'outer', 'index' => 8],
        ], $readyCell['headerReferenceIssues']);
        $t->same([
            'duplicate-table-header-id',
            'duplicate-table-header-reference-token',
            'missing-table-header-target',
            'non-header-table-header-target',
            'invalid-table-header-reference-token',
        ], $readyCell['headerReferenceIssueCodes']);
        $t->same([2, 3, 3, 4, 5, 6, 8], array_map(
            static fn (array $issue): int => (int) $issue['index'],
            $readyCell['headerReferenceIssues']
        ));
        $t->same(false, $readyCell['headerReferencesResolved']);

        $t->same('resolved', $references[0]['targetState']);
        $t->same('Region', $references[0]['headerTargets'][0]['text']);
        $t->same('col', $references[0]['headerTargets'][0]['scope']);
        $t->same('Reg', $references[0]['headerTargets'][0]['abbr']);
        $t->same('2', $references[0]['headerTargets'][0]['colSpanRaw']);
        $t->same(2, $references[0]['headerTargets'][0]['colSpan']);
        $t->same('resolved', $references[1]['targetState']);
        $t->same('Batch A', $references[1]['headerTargets'][0]['text']);
        $t->same('row', $references[1]['headerTargets'][0]['scope']);
        $t->same('A', $references[1]['headerTargets'][0]['abbr']);
        $t->same('2', $references[1]['headerTargets'][0]['rowSpanRaw']);
        $t->same(2, $references[1]['headerTargets'][0]['rowSpan']);
        $t->same('duplicate-header-target-id', $references[2]['targetState']);
        $t->same(2, $references[2]['headerTargetCount']);
        $t->same(['Duplicate One', 'Duplicate Two'], array_map(
            static fn (array $target): string => $target['text'],
            $references[2]['headerTargets']
        ));
        $t->same(['First', 'Second'], array_map(
            static fn (array $target): string => $target['abbr'],
            $references[2]['headerTargets']
        ));
        $t->same(true, $references[3]['duplicateToken']);
        $t->same(2, $references[3]['firstIndex']);
        $t->same('duplicate-header-target-id', $references[3]['targetState']);
        $t->same('missing', $references[4]['targetState']);
        $t->same('non-header-target', $references[5]['targetState']);
        $t->same([['name' => 'td', 'id' => 'note', 'text' => 'Not a header']], $references[5]['nonHeaderTargets']);
        $t->same('invalid-token', $references[6]['state']);
        $t->same('resolved', $references[7]['targetState']);
        $t->same('Period', $references[7]['headerTargets'][0]['text']);
        $t->same('Q2', $references[7]['headerTargets'][0]['abbr']);
        $t->same('missing', $references[8]['targetState']);
        $t->same('<table id="review-grid" summary="Legacy summary"><caption>Import matrix</caption><thead><tr><th abbr="Reg" colspan="2" id="region" scope="col">Region</th><th abbr="Q2" id="period" scope="col">Period</th><th abbr="First" id="dup" scope="col">Duplicate One</th><th abbr="Second" id="dup" scope="colgroup">Duplicate Two</th><td id="note">Not a header</td></tr></thead><tbody><tr><th abbr="A" id="row-a" rowspan="2" scope="row">Batch A</th><td colspan="2" headers="region row-a dup dup missing note bad&lt;tag period outer" rowspan="2">Ready</td></tr></tbody></table><table><tr><th abbr="Out" id="outer" scope="col">Outer table</th></tr></table>', $html);
    },
    'serializes detached dom nodes and children for reader handoff' => static function (TestRunner $t): void {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $fragment = $dom->createDocumentFragment();
        $section = $dom->createElement('section');
        $section->setAttribute('hidden', 'hidden');
        $paragraph = $dom->createElement('p');
        $paragraph->appendChild($dom->createTextNode('Detached <text> & notes'));
        $section->appendChild($paragraph);
        $section->appendChild($dom->createElement('br'));
        $section->appendChild($dom->createComment('review -- source'));
        $fragment->appendChild($section);

        $t->same('<section hidden><p>Detached &lt;text&gt; &amp; notes</p><br><!--review - - source--></section>', XmlHtmlDom::serializeHtmlNode($fragment));
        $t->same('<p>Detached &lt;text&gt; &amp; notes</p><br><!--review - - source-->', XmlHtmlDom::serializeHtmlChildren($section));
        $t->same('<section hidden><p>Detached &lt;text&gt; &amp; notes</p><br><!--review - - source--></section>', XmlHtmlDom::serializeHtmlNode($section));
        $t->same('<!--detached- -->', XmlHtmlDom::serializeHtmlNode($dom->createComment('detached-')));
        $t->same('<!--detached- - -tail- -->', XmlHtmlDom::serializeHtmlNode($dom->createComment('detached---tail-')));
    },
    'preserves svg and mathml foreign content names in deterministic html serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg>'
                . '<math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>',
            'foreign content HTML fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('svg', $summary[0]['name']);
        $t->same([
            'preserveAspectRatio' => 'xMidYMid meet',
            'viewBox' => '0 0 10 10',
        ], $summary[0]['attributes']);
        $t->same('linearGradient', $summary[0]['children'][0]['name']);
        $t->same('textPath', $summary[0]['children'][1]['name']);
        $t->same('math', $summary[1]['name']);
        $t->same('definitionURL', array_key_first($summary[1]['children'][0]['attributes']));
        $t->same('<svg preserveAspectRatio="xMidYMid meet" viewBox="0 0 10 10"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg><math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>', $html);
    },
    'preserves svg stitchTiles filter attribute casing before raw handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><filter id="noise"><feTurbulence baseFrequency="0.8" numOctaves="2" stitchTiles="stitch"></feTurbulence><feDisplacementMap scale="2" xChannelSelector="R" yChannelSelector="G"></feDisplacementMap></filter></svg>',
            'svg filter stitchTiles fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $filter = $summary[0]['children'][0];
        $turbulence = $filter['children'][0];
        $displacement = $filter['children'][1];

        $t->same('svg', $summary[0]['name']);
        $t->same('filter', $filter['name']);
        $t->same('feTurbulence', $turbulence['name']);
        $t->same([
            'baseFrequency' => '0.8',
            'numOctaves' => '2',
            'stitchTiles' => 'stitch',
        ], $turbulence['attributes']);
        $t->same('feDisplacementMap', $displacement['name']);
        $t->same([
            'scale' => '2',
            'xChannelSelector' => 'R',
            'yChannelSelector' => 'G',
        ], $displacement['attributes']);
        $t->same(
            '<svg><filter id="noise"><feTurbulence baseFrequency="0.8" numOctaves="2" stitchTiles="stitch"></feTurbulence><feDisplacementMap scale="2" xChannelSelector="R" yChannelSelector="G"></feDisplacementMap></filter></svg>',
            $html
        );
        $t->true(!str_contains($html, 'stitchtiles='), 'Expected SVG stitchTiles attribute to serialize with HTML5 foreign-content casing');
    },
    'keeps svg element-name casing scoped to svg foreign content' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<math><lineargradient data-review="math">m</lineargradient><mtext><linearGradient viewBox="html">html</linearGradient></mtext><svg><linearGradient id="g"></linearGradient></svg></math>',
            'mixed MathML and SVG foreign content fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $mathUnknown = $summary[0]['children'][0];
        $mathHtmlText = $summary[0]['children'][1]['children'][0];
        $nestedSvg = $summary[0]['children'][2];

        $t->same('math', $summary[0]['name']);
        $t->same('lineargradient', $mathUnknown['name']);
        $t->same(['data-review' => 'math'], $mathUnknown['attributes']);
        $t->same('lineargradient', $mathHtmlText['name']);
        $t->same(['viewbox' => 'html'], $mathHtmlText['attributes']);
        $t->same('svg', $nestedSvg['name']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('<math><lineargradient data-review="math">m</lineargradient><mtext><lineargradient viewbox="html">html</lineargradient></mtext><svg><linearGradient id="g"></linearGradient></svg></math>', $html);
        $t->true(!str_contains($html, '<math><linearGradient'), 'Expected MathML non-SVG descendants to keep their parsed names');
    },
    'keeps html integration point descendants out of foreign-content casing' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><foreignObject><div viewBox="html attr"><linearGradient data-review="html child">HTML child</linearGradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject></svg>'
                . '<math><annotation-xml encoding="text/html"><div viewBox="math html"><textPath>HTML text</textPath></div></annotation-xml><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math>',
            'foreign content integration-point fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $foreignObject = $summary[0]['children'][0];
        $foreignDiv = $foreignObject['children'][0];
        $nestedSvg = $foreignDiv['children'][1];
        $mathHtmlAnnotation = $summary[1]['children'][0];
        $mathHtmlDiv = $mathHtmlAnnotation['children'][0];
        $mathContentAnnotation = $summary[1]['children'][1];

        $t->same('foreignObject', $foreignObject['name']);
        $t->same('div', $foreignDiv['name']);
        $t->same(['viewbox' => 'html attr'], $foreignDiv['attributes']);
        $t->same('lineargradient', $foreignDiv['children'][0]['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('annotation-xml', $mathHtmlAnnotation['name']);
        $t->same(['encoding' => 'text/html'], $mathHtmlAnnotation['attributes']);
        $t->same('div', $mathHtmlDiv['name']);
        $t->same(['viewbox' => 'math html'], $mathHtmlDiv['attributes']);
        $t->same('textpath', $mathHtmlDiv['children'][0]['name']);
        $t->same(['definitionURL' => '#x'], $mathContentAnnotation['children'][0]['attributes']);
        $t->same('<svg><foreignObject><div viewbox="html attr"><lineargradient data-review="html child">HTML child</lineargradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject></svg><math><annotation-xml encoding="text/html"><div viewbox="math html"><textpath>HTML text</textpath></div></annotation-xml><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math>', $html);
    },
    'treats svg desc descendants as html integration point content' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><desc><p viewBox="html attr"><textPath>HTML fallback</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></desc></svg>',
            'svg desc integration-point fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $svg = $summary[0];
        $desc = $svg['children'][0];
        $paragraph = $desc['children'][0];
        $textPath = $paragraph['children'][0];
        $nestedSvg = $paragraph['children'][1];

        $t->same('svg', $svg['name']);
        $t->same('desc', $desc['name']);
        $t->same('p', $paragraph['name']);
        $t->same(['viewbox' => 'html attr'], $paragraph['attributes']);
        $t->same('textpath', $textPath['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same(['viewBox' => '0 0 1 1'], $nestedSvg['attributes']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('<svg><desc><p viewbox="html attr"><textpath>HTML fallback</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></desc></svg>', $html);
    },
    'treats svg title descendants as html integration point content' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><title><p viewBox="html attr"><textPath>Title fallback</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></title></svg>',
            'svg title integration-point fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $svg = $summary[0];
        $title = $svg['children'][0];
        $paragraph = $title['children'][0];
        $textPath = $paragraph['children'][0];
        $nestedSvg = $paragraph['children'][1];

        $t->same('svg', $svg['name']);
        $t->same('title', $title['name']);
        $t->same('p', $paragraph['name']);
        $t->same(['viewbox' => 'html attr'], $paragraph['attributes']);
        $t->same('textpath', $textPath['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same(['viewBox' => '0 0 1 1'], $nestedSvg['attributes']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('<svg><title><p viewbox="html attr"><textpath>Title fallback</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></title></svg>', $html);
        $t->true(!str_contains($html, '&lt;p viewBox'), 'Expected SVG title fallback markup to stay parsed instead of escaped as RCDATA');
    },
    'keeps mathml token text integration descendants in html casing' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<math><mtext><span viewBox="html attr"><textPath>HTML text</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></span></mtext>'
                . '<mi><a href="/review">link</a></mi><mo><mglyph src="glyph.png"></mglyph></mo></math>',
            'mathml text integration-point fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $math = $summary[0];
        $mtext = $math['children'][0];
        $span = $mtext['children'][0];
        $nestedSvg = $span['children'][1];
        $mi = $math['children'][1];
        $mo = $math['children'][2];

        $t->same('math', $math['name']);
        $t->same('mtext', $mtext['name']);
        $t->same(['viewbox' => 'html attr'], $span['attributes']);
        $t->same('textpath', $span['children'][0]['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same(['viewBox' => '0 0 1 1'], $nestedSvg['attributes']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same('a', $mi['children'][0]['name']);
        $t->same(['href' => '/review'], $mi['children'][0]['attributes']);
        $t->same('mglyph', $mo['children'][0]['name']);
        $t->same('<math><mtext><span viewbox="html attr"><textpath>HTML text</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></span></mtext><mi><a href="/review">link</a></mi><mo><mglyph src="glyph.png"></mglyph></mo></math>', $html);
    },
    'keeps mathml mglyph and malignmark exceptions in foreign casing' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<math><mi><malignmark definitionURL="#mark"><svg viewBox="0 0 1 1"><linearGradient id="g"></linearGradient></svg></malignmark><mglyph definitionURL="#glyph"></mglyph><span definitionURL="#html">HTML</span></mi></math>',
            'mathml text integration-point exception fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $mi = $summary[0]['children'][0];
        $malignmark = $mi['children'][0];
        $mglyph = $mi['children'][1];
        $span = $mi['children'][2];

        $t->same(['definitionURL' => '#mark'], $malignmark['attributes']);
        $t->same('svg', $malignmark['children'][0]['name']);
        $t->same('linearGradient', $malignmark['children'][0]['children'][0]['name']);
        $t->same(['definitionURL' => '#glyph'], $mglyph['attributes']);
        $t->same(['definitionurl' => '#html'], $span['attributes']);
        $t->same('<math><mi><malignmark definitionURL="#mark"><svg viewBox="0 0 1 1"><linearGradient id="g"></linearGradient></svg></malignmark><mglyph definitionURL="#glyph"></mglyph><span definitionurl="#html">HTML</span></mi></math>', $html);
    },
    'preserves html foreign-content cdata sections as escaped text' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<svg><desc><![CDATA[Reviewer <source> & notes]]></desc><text><![CDATA[A < B & C]]></text></svg>'
                . '<math><annotation encoding="application/x-tex"><![CDATA[x < y & z]]></annotation></math>',
            'foreign content CDATA fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('svg', $summary[0]['name']);
        $t->same('desc', $summary[0]['children'][0]['name']);
        $t->same('Reviewer <source> & notes', $summary[0]['children'][0]['text']);
        $t->same('text', $summary[0]['children'][1]['name']);
        $t->same('A < B & C', $summary[0]['children'][1]['text']);
        $t->same('math', $summary[1]['name']);
        $t->same('annotation', $summary[1]['children'][0]['name']);
        $t->same(['encoding' => 'application/x-tex'], $summary[1]['children'][0]['attributes']);
        $t->same('x < y & z', $summary[1]['children'][0]['text']);
        $t->same('<svg><desc>Reviewer &lt;source&gt; &amp; notes</desc><text>A &lt; B &amp; C</text></svg><math><annotation encoding="application/x-tex">x &lt; y &amp; z</annotation></math>', $html);
        $t->true(!str_contains($html, '<![CDATA['), 'Expected CDATA delimiters to be normalized away before HTML handoff');
        $t->true(!str_contains($html, '<source>'), 'Expected CDATA tag-looking text to stay escaped');
    },
    'serializes html rcdata elements as escaped text not parsed child markup' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<textarea data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></textarea>'
                . '<title>Packet <em>literal</em> &amp; title</title>',
            'rcdata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('textarea', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>', $summary[0]['children'][0]['text']);
        $t->same('title', $summary[1]['name']);
        $t->same('Packet <em>literal</em> & title', $summary[1]['text']);
        $t->same('text', $summary[1]['children'][0]['type']);
        $t->same(
            '<textarea data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;</textarea><title>Packet &lt;em&gt;literal&lt;/em&gt; &amp; title</title>',
            $html
        );
    },
    'keeps unterminated html rcdata source as escaped text through fragment end' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<textarea data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b><p>after</p>',
            'unterminated rcdata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $expectedText = 'Reviewer <script>alert(1)</script> & <b>note</b><p>after</p>';
        $expectedHtml = '<textarea data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;&lt;p&gt;after&lt;/p&gt;</textarea>';

        $t->same(1, count($summary));
        $t->same('textarea', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same($expectedText, $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same($expectedText, $summary[0]['children'][0]['text']);
        $t->same($expectedHtml, $html);
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected unterminated textarea script-looking source to stay escaped');
        $t->true(!str_contains($html, '<p>after</p>'), 'Expected unterminated textarea following source to stay escaped');
    },
    'serializes obsolete html raw text fallback elements as escaped source text' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<xmp data-source="legacy">Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea></xmp>'
                . '<noembed>Fallback <img src=x> & source</noembed>'
                . '<noframes>Frame fallback <a href="/edit">edit</a></noframes><p>after</p>',
            'obsolete raw text review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('xmp', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>', $summary[0]['children'][0]['text']);
        $t->same('noembed', $summary[1]['name']);
        $t->same('Fallback <img src=x> & source', $summary[1]['text']);
        $t->same('noframes', $summary[2]['name']);
        $t->same('Frame fallback <a href="/edit">edit</a>', $summary[2]['text']);
        $t->same('<xmp data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;textarea&gt;&lt;b&gt;note&lt;/b&gt;&lt;/textarea&gt;</xmp><noembed>Fallback &lt;img src=x&gt; &amp; source</noembed><noframes>Frame fallback &lt;a href="/edit"&gt;edit&lt;/a&gt;</noframes><p>after</p>', $html);
        $t->true(!str_contains($html, '<textarea>'), 'Expected raw text textarea-looking source to stay escaped');
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected raw text script-looking source to stay escaped');
    },
    'treats html noscript fallback as escaped raw text for raw handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<noscript data-source="legacy">Fallback <script>alert(1)</script> & source <img src=x></noscript><p>after</p>',
            'noscript raw text review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(2, count($summary));
        $t->same('noscript', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('Fallback <script>alert(1)</script> & source <img src=x>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('Fallback <script>alert(1)</script> & source <img src=x>', $summary[0]['children'][0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('after', $summary[1]['text']);
        $t->same('<noscript data-source="legacy">Fallback &lt;script&gt;alert(1)&lt;/script&gt; &amp; source &lt;img src=x&gt;</noscript><p>after</p>', $html);
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected noscript script-looking source to stay escaped');
        $t->true(!str_contains($html, '<img src=x>'), 'Expected noscript image-looking source to stay escaped');
    },
    'treats html iframe fallback as escaped raw text for raw handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<iframe data-source="legacy"><p>Fallback <script>alert(1)</script> &amp; note</p></iframe><p>after</p>',
            'iframe raw text review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same(2, count($summary));
        $t->same('iframe', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same('<p>Fallback <script>alert(1)</script> &amp; note</p>', $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same('<p>Fallback <script>alert(1)</script> &amp; note</p>', $summary[0]['children'][0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('<iframe data-source="legacy">&lt;p&gt;Fallback &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; note&lt;/p&gt;</iframe><p>after</p>', $html);
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected iframe fallback script-looking source to stay escaped');
        $t->true(!str_contains($html, '<p>Fallback'), 'Expected iframe fallback paragraph markup to stay escaped');
    },
    'treats html plaintext as escaped source text through end of fragment' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<plaintext data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>after</p>',
            'plaintext review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $expectedText = 'Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>after</p>';

        $t->same(1, count($summary));
        $t->same('plaintext', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same($expectedText, $summary[0]['text']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same($expectedText, $summary[0]['children'][0]['text']);
        $t->same('<plaintext data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;b&gt;note&lt;/b&gt;&lt;/plaintext&gt;&lt;p&gt;after&lt;/p&gt;</plaintext>', $html);
        $t->true(!str_contains($html, '<script>alert(1)</script>'), 'Expected plaintext script-looking source to stay escaped');
        $t->true(!str_contains($html, '<p>after</p>'), 'Expected following paragraph source to stay plaintext text');
    },
    'treats html template contents as inert escaped source text for raw handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<template data-source="legacy"><p>Template <script>drop()</script> &amp; <b>note</b></p></template><p>after</p>',
            'template review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', ['source' => 'xml-html5-dom'], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/template-source-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expectedTemplateText = '<p>Template <script>drop()</script> &amp; <b>note</b></p>';
        $expectedHtml = '<template data-source="legacy">&lt;p&gt;Template &lt;script&gt;drop()&lt;/script&gt; &amp;amp; &lt;b&gt;note&lt;/b&gt;&lt;/p&gt;</template><p>after</p>';

        $t->same(2, count($summary));
        $t->same('template', $summary[0]['name']);
        $t->same(['data-source' => 'legacy'], $summary[0]['attributes']);
        $t->same($expectedTemplateText, $summary[0]['text']);
        $t->same('inert-source', $summary[0]['template']);
        $t->same($expectedTemplateText, $summary[0]['templateText']);
        $t->same(strlen($expectedTemplateText), $summary[0]['templateTextLength']);
        $t->same(hash('sha256', $expectedTemplateText), $summary[0]['templateTextSha256']);
        $t->same(true, $summary[0]['templateContainsMarkupLikeText']);
        $t->same(true, $summary[0]['templateContainsActiveLikeText']);
        $t->same('template-inert-escaped-source', $summary[0]['templateReviewPolicy']);
        $t->same('text', $summary[0]['children'][0]['type']);
        $t->same($expectedTemplateText, $summary[0]['children'][0]['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('after', $summary[1]['text']);
        $t->same($expectedHtml, $html);
        $t->contains($expectedHtml, $blocks);
        $t->same('/migration/template-source-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<script>drop()</script>'), 'Expected script-looking template source to stay escaped');
        $t->true(!str_contains($html, '<b>note</b>'), 'Expected inline tag-looking template source to stay escaped');
    },
    'summarizes html template content review provenance for reviewer handoff' => static function (TestRunner $t): void {
        $templateSource = '<article id="card"><h2>Title</h2><a href="/more">More</a><img src="cover.png" alt="Cover"><form action="/submit"><input name="q" value="search"></form><script>ignored()</script><iframe src="frame.html"></iframe></article>';
        $unsafeSource = '<!doctype html><p>Blocked</p>';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<template id="card-template">' . $templateSource . '</template>'
                . '<template id="unsafe-template">' . $unsafeSource . '</template>',
            'template content review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $card = $summary[0];
        $unsafe = $summary[1];

        $t->same(2, count($summary));
        $t->same('template', $card['name']);
        $t->same('template-content-inert-fragment-review', $card['templateContentReviewPolicy']);
        $t->same(strlen($templateSource), $card['templateContentByteLength']);
        $t->same(hash('sha256', $templateSource), $card['templateContentSha256']);
        $t->same(true, $card['templateContentParsed']);
        $t->same([], $card['templateContentDiagnostics']);
        $t->same(['article'], $card['templateContentTopLevelElementNames']);
        $t->same(1, $card['templateContentTopLevelElementCount']);
        $t->same('TitleMoreignored()', $card['templateContentText']);
        $t->same(strlen('TitleMoreignored()'), $card['templateContentTextLength']);
        $t->same(hash('sha256', 'TitleMoreignored()'), $card['templateContentTextSha256']);
        $t->same(['/more'], $card['templateContentLinkHrefs']);
        $t->same(['cover.png'], $card['templateContentImageSources']);
        $t->same(1, $card['templateContentFormCount']);
        $t->same(['/submit'], $card['templateContentFormActions']);
        $t->same(['script'], $card['templateContentActiveElementNames']);
        $t->same(['iframe'], $card['templateContentEmbeddedElementNames']);
        $t->true(!str_contains($html, '<script>ignored()</script>'), 'Expected template script source to stay escaped in raw handoff');

        $t->same('template', $unsafe['name']);
        $t->same($unsafeSource, $unsafe['templateText']);
        $t->same('template-content-inert-fragment-review', $unsafe['templateContentReviewPolicy']);
        $t->same(false, $unsafe['templateContentParsed']);
        $t->same(['template-content-unsafe-or-unparseable'], $unsafe['templateContentDiagnostics']);
        $t->contains('document type', $unsafe['templateContentError']);
    },
    'summarizes declarative shadow root slot metadata for reviewer handoff' => static function (TestRunner $t): void {
        $templateSource = '<style>:host{display:block}</style>'
            . '<h2><slot name="title"><span>Untitled</span></slot></h2>'
            . '<slot><p>Body fallback</p></slot>';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="card" part="review-card">'
                . '<template shadowrootmode="open" shadowrootdelegatesfocus shadowrootclonable>' . $templateSource . '</template>'
                . '<h2 slot="title">Review title</h2><p>Light DOM body</p>'
                . '</article>',
            'declarative shadow root review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/declarative-shadow-root-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $template = $article['children'][0];
        $lightTitle = $article['children'][1];
        $namedSlot = $template['shadowRootSlots'][0];
        $defaultSlot = $template['shadowRootSlots'][1];

        $t->same('article', $article['name']);
        $t->same(['id' => 'card', 'part' => 'review-card'], $article['attributes']);
        $t->same(['review-card'], $article['partNames']);
        $t->same('template', $template['name']);
        $t->same($templateSource, $template['templateText']);
        $t->same(true, $template['templateContentParsed']);
        $t->same(['style', 'h2', 'slot'], $template['templateContentTopLevelElementNames']);
        $t->same(['style'], $template['templateContentActiveElementNames']);
        $t->same(true, $template['declarativeShadowRoot']);
        $t->same('declarative-shadow-root-template-review', $template['shadowRootReviewPolicy']);
        $t->same('open', $template['shadowRootModeRaw']);
        $t->same('open', $template['shadowRootMode']);
        $t->same(true, $template['shadowRootModeValid']);
        $t->same(true, $template['shadowRootDelegatesFocus']);
        $t->same(true, $template['shadowRootClonable']);
        $t->same(false, $template['shadowRootSerializable']);
        $t->same(false, $template['shadowRootCustomElementRegistry']);
        $t->same('article', $template['shadowRootHostTag']);
        $t->same('card', $template['shadowRootHostId']);
        $t->same(2, $template['shadowRootSlotCount']);
        $t->same(1, $template['shadowRootDefaultSlotCount']);
        $t->same(1, $template['shadowRootNamedSlotCount']);
        $t->same(['title', ''], $template['shadowRootSlotNames']);
        $t->same(['title'], $template['shadowRootNamedSlotNames']);
        $t->same([], $template['shadowRootDuplicateSlotNames']);
        $t->same(['Untitled', 'Body fallback'], $template['shadowRootSlotFallbackTexts']);
        $t->same([], $template['shadowRootDiagnostics']);

        $t->same(0, $namedSlot['index']);
        $t->same('slot', $namedSlot['slotElement']);
        $t->same('title', $namedSlot['slotNameRaw']);
        $t->same('title', $namedSlot['slotName']);
        $t->same(false, $namedSlot['slotDefault']);
        $t->same(true, $namedSlot['slotNameValid']);
        $t->same('Untitled', $namedSlot['slotFallbackText']);
        $t->same(['span'], $namedSlot['slotFallbackElementNames']);

        $t->same(1, $defaultSlot['index']);
        $t->same(null, $defaultSlot['slotNameRaw']);
        $t->same('', $defaultSlot['slotName']);
        $t->same(true, $defaultSlot['slotDefault']);
        $t->same(true, $defaultSlot['slotNameValid']);
        $t->same('Body fallback', $defaultSlot['slotFallbackText']);
        $t->same(['p'], $defaultSlot['slotFallbackElementNames']);

        $t->same('title', $lightTitle['slotRaw']);
        $t->same('title', $lightTitle['slotName']);
        $t->same(true, $lightTitle['slotValid']);
        $t->contains('<template shadowrootclonable shadowrootdelegatesfocus shadowrootmode="open">', $html);
        $t->true(!str_contains($html, '<slot name="title">'), 'Expected shadow-root slot source to stay escaped in raw handoff');
        $t->contains($html, $blocks);
        $t->same('/migration/declarative-shadow-root-review.html', $document->children[0]->attr('part'));
    },
    'summarizes html declarative shadow root template metadata for reviewer handoff' => static function (TestRunner $t): void {
        $shadowSource = '<slot name="title">Fallback title</slot><slot name="title">Duplicate title</slot>';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="host"><template shadowrootmode="open" shadowrootcustomelementregistry>' . $shadowSource . '</template></article>'
                . '<template shadowrootcustomelementregistry><slot>Missing mode</slot></template>',
            'template declarative shadow root metadata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);

        $shadow = $summary[0]['children'][0];
        $missingMode = $summary[1];

        $t->same(true, $shadow['declarativeShadowRoot']);
        $t->same(true, $shadow['shadowRootCustomElementRegistry']);
        $t->same(['title'], $shadow['shadowRootDuplicateSlotNames']);
        $t->same(['duplicate-shadow-root-slot-name:title'], $shadow['shadowRootDiagnostics']);
        $t->same(['Fallback title', 'Duplicate title'], $shadow['shadowRootSlotFallbackTexts']);

        $t->same(true, $missingMode['declarativeShadowRoot']);
        $t->same(true, $missingMode['shadowRootCustomElementRegistry']);
        $t->same(null, $missingMode['shadowRootModeRaw']);
        $t->same(false, $missingMode['shadowRootModeValid']);
        $t->same(['invalid-shadowroot-mode'], $missingMode['shadowRootDiagnosticCodes']);
    },
    'serializes declarative shadow root custom element registry as a boolean attribute' => static function (TestRunner $t): void {
        $shadowSource = '<slot name="title">Fallback</slot>';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="registry-host"><template shadowrootmode="closed" shadowrootcustomelementregistry shadowrootserializable>' . $shadowSource . '</template><h2 slot="title">Custom title</h2></article>',
            'shadow root registry boolean review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $article = $summary[0];
        $template = $article['children'][0];
        $title = $article['children'][1];

        $t->same('article', $article['name']);
        $t->same('template', $template['name']);
        $t->same(true, $template['declarativeShadowRoot']);
        $t->same('closed', $template['shadowRootModeRaw']);
        $t->same('closed', $template['shadowRootMode']);
        $t->same(true, $template['shadowRootModeValid']);
        $t->same(true, $template['shadowRootCustomElementRegistry']);
        $t->same(true, $template['shadowRootSerializable']);
        $t->same(false, $template['shadowRootClonable']);
        $t->same(['title'], $template['shadowRootNamedSlotNames']);
        $t->same(['Fallback'], $template['shadowRootSlotFallbackTexts']);
        $t->same([], $template['shadowRootDiagnostics']);
        $t->same('title', $title['slotName']);
        $t->same('<article id="registry-host"><template shadowrootcustomelementregistry shadowrootmode="closed" shadowrootserializable>&lt;slot name="title"&gt;Fallback&lt;/slot&gt;</template><h2 slot="title">Custom title</h2></article>', $html);
        $t->true(!str_contains($html, 'shadowrootcustomelementregistry=""'), 'Expected shadowrootcustomelementregistry to serialize as an HTML boolean attribute');
    },
    'summarizes html template content across nested template and raw text sentinels' => static function (TestRunner $t): void {
        $templateSource = '<template data-inner="1"><p>Inner</p></template>'
            . '<noscript><script>const fallback = "</template>";</script><p>Fallback</p></noscript>'
            . '<script>const sentinel = "</template>";</script><p><a href="/tail">Tail</a><img src="tail.png" alt="Tail"></p>';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<template id="outer">' . $templateSource . '</template><p>after</p>',
            'template nested boundary review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $expectedEscaped = '&lt;template data-inner="1"&gt;&lt;p&gt;Inner&lt;/p&gt;&lt;/template&gt;'
            . '&lt;noscript&gt;&lt;script&gt;const fallback = "&lt;/template&gt;";&lt;/script&gt;&lt;p&gt;Fallback&lt;/p&gt;&lt;/noscript&gt;'
            . '&lt;script&gt;const sentinel = "&lt;/template&gt;";&lt;/script&gt;&lt;p&gt;&lt;a href="/tail"&gt;Tail&lt;/a&gt;&lt;img src="tail.png" alt="Tail"&gt;&lt;/p&gt;';

        $template = $summary[0];

        $t->same(2, count($summary));
        $t->same('template', $template['name']);
        $t->same(['id' => 'outer'], $template['attributes']);
        $t->same($templateSource, $template['templateText']);
        $t->same('template-content-inert-fragment-review', $template['templateContentReviewPolicy']);
        $t->same(true, $template['templateContentParsed']);
        $t->same([], $template['templateContentDiagnostics']);
        $t->same(['template', 'noscript', 'script', 'p'], $template['templateContentTopLevelElementNames']);
        $t->same(4, $template['templateContentTopLevelElementCount']);
        $t->contains('const sentinel = "</template>";', $template['templateContentText']);
        $t->contains('Tail', $template['templateContentText']);
        $t->same(['/tail'], $template['templateContentLinkHrefs']);
        $t->same(['tail.png'], $template['templateContentImageSources']);
        $t->same(0, $template['templateContentFormCount']);
        $t->same(['script'], $template['templateContentActiveElementNames']);
        $t->same([], $template['templateContentEmbeddedElementNames']);
        $t->same('p', $summary[1]['name']);
        $t->same('after', $summary[1]['text']);
        $t->same('<template id="outer">' . $expectedEscaped . '</template><p>after</p>', $html);
        $t->true(!str_contains($html, '<a href="/tail">Tail</a>'), 'Expected parsed template content links to remain escaped in raw handoff');
    },
    'summarizes html noscript fallback review provenance for reviewer handoff' => static function (TestRunner $t): void {
        $noscriptSource = '<article id="fallback"><h2>Fallback</h2><a href="/static">Static</a><img src="fallback.png" alt="Fallback"><form action="/offline"><input name="q" value="term"></form><script>blocked()</script><iframe src="fallback-frame.html"></iframe></article>';
        $unsafeSource = '<!doctype html><p>Blocked</p>';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<noscript id="fallback-source">' . $noscriptSource . '</noscript>'
                . '<noscript id="unsafe-source">' . $unsafeSource . '</noscript>',
            'noscript fallback review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', ['source' => 'xml-html5-dom'], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/noscript-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $fallback = $summary[0];
        $unsafe = $summary[1];

        $t->same(2, count($summary));
        $t->same('noscript', $fallback['name']);
        $t->same('fallback-source', $fallback['noscript']);
        $t->same($noscriptSource, $fallback['noscriptText']);
        $t->same(strlen($noscriptSource), $fallback['noscriptTextLength']);
        $t->same(hash('sha256', $noscriptSource), $fallback['noscriptTextSha256']);
        $t->same(true, $fallback['noscriptContainsMarkupLikeText']);
        $t->same(true, $fallback['noscriptContainsActiveLikeText']);
        $t->same('noscript-inert-escaped-source', $fallback['noscriptReviewPolicy']);
        $t->same('noscript-content-inert-fragment-review', $fallback['noscriptContentReviewPolicy']);
        $t->same(strlen($noscriptSource), $fallback['noscriptContentByteLength']);
        $t->same(hash('sha256', $noscriptSource), $fallback['noscriptContentSha256']);
        $t->same(true, $fallback['noscriptContentParsed']);
        $t->same([], $fallback['noscriptContentDiagnostics']);
        $t->same(['article'], $fallback['noscriptContentTopLevelElementNames']);
        $t->same(1, $fallback['noscriptContentTopLevelElementCount']);
        $t->same('FallbackStaticblocked()', $fallback['noscriptContentText']);
        $t->same(strlen('FallbackStaticblocked()'), $fallback['noscriptContentTextLength']);
        $t->same(hash('sha256', 'FallbackStaticblocked()'), $fallback['noscriptContentTextSha256']);
        $t->same(['/static'], $fallback['noscriptContentLinkHrefs']);
        $t->same(['fallback.png'], $fallback['noscriptContentImageSources']);
        $t->same(1, $fallback['noscriptContentFormCount']);
        $t->same(['/offline'], $fallback['noscriptContentFormActions']);
        $t->same(['script'], $fallback['noscriptContentActiveElementNames']);
        $t->same(['iframe'], $fallback['noscriptContentEmbeddedElementNames']);

        $t->same('noscript', $unsafe['name']);
        $t->same($unsafeSource, $unsafe['noscriptText']);
        $t->same('noscript-content-inert-fragment-review', $unsafe['noscriptContentReviewPolicy']);
        $t->same(false, $unsafe['noscriptContentParsed']);
        $t->same(['noscript-content-unsafe-or-unparseable'], $unsafe['noscriptContentDiagnostics']);
        $t->contains('document type', $unsafe['noscriptContentError']);

        $t->contains('&lt;article id="fallback"&gt;', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/noscript-fallback-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<script>blocked()</script>'), 'Expected noscript script source to stay escaped in raw handoff');
        $t->true(!str_contains($html, '<iframe src="fallback-frame.html">'), 'Expected noscript iframe source to stay escaped in raw handoff');
    },
    'foster-parents invalid table children before deterministic html serialization' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<table class="legacy"><caption>Review rows</caption><p>Loose note</p><tr><td>A</td></tr>orphan text<tr><td>B</td></tr></table><p>after</p>',
            'table foster-parenting review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('p', $summary[0]['name']);
        $t->same('Loose note', $summary[0]['text']);
        $t->same('text', $summary[1]['type']);
        $t->same('orphan text', $summary[1]['text']);
        $t->same('table', $summary[2]['name']);
        $t->same(['class' => 'legacy'], $summary[2]['attributes']);
        $t->same('caption', $summary[2]['children'][0]['name']);
        $t->same('tr', $summary[2]['children'][1]['name']);
        $t->same('tr', $summary[2]['children'][2]['name']);
        $t->same('<p>Loose note</p>orphan text<table class="legacy"><caption>Review rows</caption><tr><td>A</td></tr><tr><td>B</td></tr></table><p>after</p>', $html);
    },
    'foster-parents nested table row-group phrasing before raw handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<table id="ledger"><caption>Ledger</caption><tbody><tr><td>A</td><em>row note</em><td>B</td></tr><span>group note</span><tr><td>C</td></tr></tbody></table><p>after</p>',
            'nested table foster-parenting review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/table-foster-parenting-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $fosteredRowNote = $summary[0];
        $fosteredGroupNote = $summary[1];
        $table = $summary[2];
        $tbody = $table['children'][1];
        $rows = $tbody['children'];
        $firstRowCells = $rows[0]['children'];
        $secondRowCells = $rows[1]['children'];

        $t->same('em', $fosteredRowNote['name']);
        $t->same('row note', $fosteredRowNote['text']);
        $t->same('stress-emphasis', $fosteredRowNote['textSemantic']);
        $t->same('span', $fosteredGroupNote['name']);
        $t->same('group note', $fosteredGroupNote['text']);
        $t->same('table', $table['name']);
        $t->same(['id' => 'ledger'], $table['attributes']);
        $t->same('LedgerABC', $table['text']);
        $t->same('caption', $table['children'][0]['name']);
        $t->same('tbody', $tbody['name']);
        $t->same('body-group', $tbody['tablePart']);
        $t->same(['tr', 'tr'], array_map(static fn (array $row): string => $row['name'], $rows));
        $t->same(['td', 'td'], array_map(static fn (array $cell): string => $cell['name'], $firstRowCells));
        $t->same('A', $firstRowCells[0]['text']);
        $t->same('B', $firstRowCells[1]['text']);
        $t->same('C', $secondRowCells[0]['text']);
        $t->same('<em>row note</em><span>group note</span><table id="ledger"><caption>Ledger</caption><tbody><tr><td>A</td><td>B</td></tr><tr><td>C</td></tr></tbody></table><p>after</p>', $html);
        $t->true(!str_contains($html, '<td>A</td><em>row note</em><td>B</td>'), 'Expected row-level phrasing content to move outside table structure');
        $t->contains($html, $blocks);
        $t->same('/migration/table-foster-parenting-review.html', $document->children[0]->attr('part'));
    },
    'wraps orphan table fragment structure before raw handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<td data-col="a">A</td><th scope="col">B</th><tr><td>C</td></tr><col span="2"><tbody><tr><td>D</td></tr></tbody><p>after</p>',
            'orphan table fragment recovery review'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/table-orphan-structure-recovery.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $table = $summary[0];
        $generatedRow = $table['children'][0];
        $sourceRow = $table['children'][1];
        $generatedColgroup = $table['children'][2];
        $sourceBody = $table['children'][3];

        $t->same('table', $table['name']);
        $t->same('table', $table['tablePart']);
        $t->same('ABCD', $table['text']);
        $t->same(['tr', 'tr', 'colgroup', 'tbody'], array_map(static fn (array $node): string => $node['name'], $table['children']));
        $t->same('row', $generatedRow['tablePart']);
        $t->same(['td', 'th'], array_map(static fn (array $node): string => $node['name'], $generatedRow['children']));
        $t->same(['data-col' => 'a'], $generatedRow['children'][0]['attributes']);
        $t->same('cell', $generatedRow['children'][0]['tablePart']);
        $t->same('header', $generatedRow['children'][1]['tableCell']);
        $t->same('col', $generatedRow['children'][1]['scope']);
        $t->same('row', $sourceRow['tablePart']);
        $t->same('C', $sourceRow['text']);
        $t->same('column-group', $generatedColgroup['tablePart']);
        $t->same('col', $generatedColgroup['children'][0]['name']);
        $t->same(2, $generatedColgroup['children'][0]['span']);
        $t->same('body-group', $sourceBody['tablePart']);
        $t->same('D', $sourceBody['text']);
        $t->same('p', $summary[1]['name']);
        $t->same('after', $summary[1]['text']);
        $t->same(
            '<table><tr><td data-col="a">A</td><th scope="col">B</th></tr><tr><td>C</td></tr><colgroup><col span="2"></colgroup><tbody><tr><td>D</td></tr></tbody></table><p>after</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/table-orphan-structure-recovery.html', $document->children[0]->attr('part'));
        $t->true(!str_starts_with($html, '<td'), 'Expected orphan cells to be wrapped before raw handoff');
        $t->true(!str_contains($html, '</tr><col '), 'Expected orphan column to be wrapped in a generated colgroup');
    },
    'hands serialized HTML fragments to WordPress raw HTML blocks' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<aside data-review="source"><p>Imported<br>line &amp; reviewer notes</p></aside>',
            'WordPress review fragment'
        );
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html]),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('<aside data-review="source"><p>Imported<br>line &amp; reviewer notes</p></aside>', $html);
        $t->contains('<!-- wp:html -->', $blocks);
        $t->contains('<aside data-review="source">', $blocks);
        $t->contains('Imported<br>line &amp; reviewer notes', $blocks);
        $t->contains('<!-- /wp:html -->', $blocks);
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment("<p>bad\0html</p>", 'unsafe HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<!DOCTYPE html><p>bad</p>', 'unsafe HTML fragment'));
    },
    'rejects unsafe HTML fragment declarations before serialization handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment('<p data-review="ok">Safe</p>', 'safe HTML fragment');

        $t->same('<p data-review="ok">Safe</p>', XmlHtmlDom::serializeHtmlFragment($dom));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<!ENTITY reviewer SYSTEM "file:///etc/passwd"><p>&reviewer;</p>', 'unsafe HTML fragment'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => XmlHtmlDom::loadHtmlFragment('<?xml-stylesheet href="https://example.invalid/review.xsl"?><p>bad</p>', 'unsafe HTML fragment'));
    },
];
