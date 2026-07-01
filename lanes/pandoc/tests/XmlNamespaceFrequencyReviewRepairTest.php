<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocFormatRegistry;
use PortLibs\Pandoc\XmlHtmlDom;
use PortLibs\Pandoc\XmlReader;

return [
    'records mapped xml namespace frequency repair assertion count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedXmlNamespaceFrequencyReviewRepairCases'] ?? null);
        $t->same(34, $manifest['xmlNamespaceFrequencyReviewRepairAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedXmlNamespaceFrequencyReviewRepairCases'] ?? null);
        $t->same(34, $manifest['benchmarkDenominator']['breakdown']['xmlNamespaceFrequencyReviewRepairAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedXmlNamespaceFrequencyReviewRepairCases'] ?? null);
        $t->same(34, $manifest['benchmarkDenominator']['inventory']['xmlNamespaceFrequencyReviewRepairAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedXmlNamespaceFrequencyReviewRepairCases'] ?? null);
        $t->same(34, $manifest['inventory']['xmlNamespaceFrequencyReviewRepairAssertions'] ?? null);
    },

    'pins xml namespace frequency review packet fields on current main' => static function (TestRunner $t): void {
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
        $dom = XmlHtmlDom::loadXmlDocument($xml, 'XML namespace frequency repair packet', preserveWhiteSpace: false);
        $packet = XmlHtmlDom::summarizeXmlNamespaceUsage($dom, $xml);
        $prefixFrequencies = [];
        foreach ($packet['namespacePrefixFrequencies'] as $frequency) {
            $prefixFrequencies[$frequency['prefix']] = $frequency;
        }
        $uriFrequencies = [];
        foreach ($packet['namespaceUriFrequencies'] as $frequency) {
            $uriFrequencies[$frequency['namespaceUri']] = $frequency;
        }
        $inputSupport = PandocFormatRegistry::phpInputSupport();
        $unsupportedInputs = PandocFormatRegistry::unsupportedInputFormats();

        $t->same('xml-namespace-usage-diagnostics-review-only', $packet['reviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same('xml-namespace-usage', $packet['namespaceReview']);
        $t->same('provided-source', $packet['namespaceUsageSourceMode']);
        $t->same(8, $packet['namespacePrefixFrequencySummaryCount']);
        $t->same(8, $packet['namespaceUriFrequencySummaryCount']);
        $t->same(5, $packet['defaultNamespaceUseCount']);
        $t->same(['urn:group', 'urn:root'], $packet['defaultNamespaceUris']);
        $t->same(2, $packet['sameUriMultiplePrefixCount']);
        $t->same(2, $packet['samePrefixMultipleUriCount']);
        $t->same(6, $prefixFrequencies['default']['useCount'] ?? null);
        $t->same(['urn:item-a', 'urn:item-b'], $prefixFrequencies['a']['namespaceUris'] ?? null);
        $t->same(['default', 'rootAlias'], $uriFrequencies['urn:root']['prefixes'] ?? null);
        $t->same(3, $uriFrequencies['urn:root']['useCount'] ?? null);
        $t->contains('direct-reader-unsupported', implode(',', $packet['directReaderDiagnosticCodes']));
        $t->contains('namespace-uri-multiple-prefixes', implode(',', $packet['directReaderDiagnosticCodes']));
        $t->contains('namespace-prefix-multiple-uris', implode(',', $packet['directReaderDiagnosticCodes']));
        $t->contains('unused-namespace-declarations', implode(',', $packet['directReaderDiagnosticCodes']));
        $t->same(false, $packet['directReaderDiagnostics'][0]['directReaderParity'] ?? null);
        $t->same(1, $packet['unusedNamespaceDeclarationCount']);
        $t->same('partial', $inputSupport['xml']['status'] ?? null);
        $t->same(XmlReader::class, $inputSupport['xml']['implementation'] ?? null);
        $t->same('partial', $inputSupport['jats']['status'] ?? null);
        $t->same('partial', $inputSupport['bits']['status'] ?? null);
        $t->true(!in_array('xml', $unsupportedInputs, true));
        $t->true(!in_array('jats', $unsupportedInputs, true));
    },
];
