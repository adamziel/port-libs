<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocFormatRegistry;
use PortLibs\Pandoc\XmlHtmlDom;
use PortLibs\Pandoc\XmlReader;

return [
    'bounds XML namespace collision packets while preserving total counts' => static function (TestRunner $t): void {
        $items = [];
        for ($index = 0; $index < 30; ++$index) {
            $suffix = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $items[] = '<a:collision' . $suffix . ' attrA:code' . $suffix . '="A"/>'
                . '<b:collision' . $suffix . ' attrB:code' . $suffix . '="B"/>'
                . '<scope' . $suffix . ' xmlns="urn:scope-' . $suffix . '">'
                . '<inner' . $suffix . ' xmlns="">Reset</inner' . $suffix . '>'
                . '</scope' . $suffix . '>';
        }

        $xml = '<doc xmlns:a="urn:element-a" xmlns:b="urn:element-b"'
            . ' xmlns:attrA="urn:attribute-a" xmlns:attrB="urn:attribute-b">'
            . implode('', $items)
            . '</doc>';
        $dom = XmlHtmlDom::loadXmlDocument($xml, 'bounded namespace collision XML', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeXmlNamespaceUsage($dom, $xml);
        $diagnostics = [];
        foreach ($packet['directReaderDiagnostics'] as $diagnostic) {
            $diagnostics[$diagnostic['code']] = $diagnostic;
        }

        $t->same(false, $packet['directReaderParity']);
        $t->same('xml-namespace-usage-diagnostics-review-only', $packet['reviewPolicy']);
        $t->same(25, $packet['namespaceReviewLimit']);

        $t->same(30, $packet['elementNamespaceCollisionCount']);
        $t->same(25, $packet['elementNamespaceCollisionSummaryCount']);
        $t->same(25, $packet['elementNamespaceCollisionLimit']);
        $t->same(true, $packet['elementNamespaceCollisionTruncated']);
        $t->same(25, count($packet['elementNamespaceCollisions']));
        $t->same('collision00', $packet['elementNamespaceCollisions'][0]['localName'] ?? null);
        $t->same(['urn:element-a', 'urn:element-b'], $packet['elementNamespaceCollisions'][0]['namespaceUris'] ?? null);

        $t->same(30, $packet['attributeNamespaceCollisionCount']);
        $t->same(25, $packet['attributeNamespaceCollisionSummaryCount']);
        $t->same(25, $packet['attributeNamespaceCollisionLimit']);
        $t->same(true, $packet['attributeNamespaceCollisionTruncated']);
        $t->same(25, count($packet['attributeNamespaceCollisions']));
        $t->same('code00', $packet['attributeNamespaceCollisions'][0]['localName'] ?? null);
        $t->same(['urn:attribute-a', 'urn:attribute-b'], $packet['attributeNamespaceCollisions'][0]['namespaceUris'] ?? null);

        $t->same(60, $packet['defaultNamespaceTransitionCount']);
        $t->same(25, $packet['defaultNamespaceTransitionSummaryCount']);
        $t->same(25, $packet['defaultNamespaceTransitionLimit']);
        $t->same(true, $packet['defaultNamespaceTransitionTruncated']);
        $t->same(25, count($packet['defaultNamespaceTransitions']));

        $t->same(
            30,
            $diagnostics['element-local-name-namespace-collisions']['details']['collisionCount'] ?? null
        );
        $t->same(
            30,
            $diagnostics['attribute-local-name-namespace-collisions']['details']['collisionCount'] ?? null
        );
        $t->same(
            60,
            $diagnostics['default-namespace-transitions']['details']['transitionCount'] ?? null
        );
        $t->same(false, $diagnostics['direct-reader-unsupported']['directReaderParity'] ?? null);
        $t->same(true, $diagnostics['element-local-name-namespace-collisions']['coveredByPacket'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'keeps XML family registry entries partial while namespace packets stay review only' => static function (TestRunner $t): void {
        $support = PandocFormatRegistry::phpInputSupport();

        foreach (['bits', 'jats', 'xml'] as $format) {
            $t->same('partial', $support[$format]['status']);
            $t->same(XmlReader::class, $support[$format]['implementation']);
            $t->same(false, $support[$format]['status'] === 'complete');
        }

        $packet = XmlHtmlDom::summarizeXmlNamespaceSourceUsage(
            '<doc xmlns="urn:root"><missing:item missing:code="1"/></doc>'
        );

        $t->same(false, $packet['directReaderParity']);
        $t->same('xml-namespace-source-usage-review-only', $packet['reviewPolicy']);
        $t->same(2, $packet['unboundNamespacePrefixUseCount']);
        $t->same(['missing'], $packet['unboundNamespacePrefixes']);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
];
