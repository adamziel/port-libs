<?php

declare(strict_types=1);

use PortLibs\Pandoc\DelimitedTextReader;
use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\EpubReader;
use PortLibs\Pandoc\IpynbReader;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\OdtReader;
use PortLibs\Pandoc\PandocFormatRegistry;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\PlainWriter;
use PortLibs\Pandoc\RtfReader;
use PortLibs\Pandoc\UpstreamRunnerDependencyAudit;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'tracks upstream wiki formats and php support status in the pandoc registry' => static function (TestRunner $t): void {
        $inputFormats = PandocFormatRegistry::upstreamInputFormats();
        $outputFormats = PandocFormatRegistry::upstreamOutputFormats();
        $inputSupport = PandocFormatRegistry::phpInputSupport();
        $outputSupport = PandocFormatRegistry::phpOutputSupport();
        $wikiInputSupport = PandocFormatRegistry::wikiInputSupport();
        $wikiOutputSupport = PandocFormatRegistry::wikiOutputSupport();

        $t->same(51, count($inputFormats));
        $t->same(75, count($outputFormats));
        $t->same($inputFormats, array_values(array_unique($inputFormats)));
        $t->same($outputFormats, array_values(array_unique($outputFormats)));
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, PandocFormatRegistry::UPSTREAM_SOURCE_COMMIT);
        $t->same('2026-06-03', PandocFormatRegistry::UPSTREAM_MANUAL_DATE);
        $t->contains('pandoc.org/demo/example2.html', PandocFormatRegistry::UPSTREAM_MANUAL_URL);

        $t->same([
            'creole',
            'dokuwiki',
            'jira',
            'mediawiki',
            'tikiwiki',
            'twiki',
            'vimwiki',
        ], PandocFormatRegistry::wikiInputFormats());
        $t->same([
            'dokuwiki',
            'jira',
            'mediawiki',
            'xwiki',
            'zimwiki',
        ], PandocFormatRegistry::wikiOutputFormats());

        $t->same(array_keys($inputSupport), $inputFormats);
        $t->same(array_keys($outputSupport), $outputFormats);
        $t->same(PandocFormatRegistry::wikiInputFormats(), array_keys($wikiInputSupport));
        $t->same(PandocFormatRegistry::wikiOutputFormats(), array_keys($wikiOutputSupport));

        foreach ($wikiInputSupport as $format => $support) {
            $t->same('unsupported', $support['status'], "Wiki input {$format} should not be claimed as direct native parity");
            $t->same('', $support['implementation']);
            $t->contains('No native PHP reader or writer is registered', $support['notes']);
        }
        foreach ($wikiOutputSupport as $format => $support) {
            $t->same('unsupported', $support['status'], "Wiki output {$format} should not be claimed as direct native parity");
            $t->same('', $support['implementation']);
            $t->contains('No native PHP reader or writer is registered', $support['notes']);
        }

        $t->same('jats', PandocFormatRegistry::inputAliases()['bits']);
        $t->same('gfm', PandocFormatRegistry::inputAliases()['markdown_github']);
        $t->same('docbook5', PandocFormatRegistry::outputAliases()['docbook']);
        $t->same('epub3', PandocFormatRegistry::outputAliases()['epub']);
        $t->same('html', PandocFormatRegistry::outputAliases()['html5']);

        $t->same('partial', $inputSupport['markdown']['status']);
        $t->same(MarkdownReader::class, $inputSupport['markdown']['implementation']);
        $t->same('partial', $inputSupport['docx']['status']);
        $t->same(DocxReader::class, $inputSupport['docx']['implementation']);
        $t->contains('section/title metadata packets', $inputSupport['docbook']['notes']);
        $t->contains('xref/link target diagnostics', $inputSupport['docbook']['notes']);
        $t->same('partial', $inputSupport['csv']['status']);
        $t->same(DelimitedTextReader::class, $inputSupport['csv']['implementation']);
        $t->same('partial', $inputSupport['tsv']['status']);
        $t->same(DelimitedTextReader::class, $inputSupport['tsv']['implementation']);
        $t->same(EpubReader::class, $inputSupport['epub']['implementation']);
        $t->same(OdtReader::class, $inputSupport['odt']['implementation']);
        $t->same(RtfReader::class, $inputSupport['rtf']['implementation']);
        $t->same(PandocJsonReader::class, $inputSupport['json']['implementation']);
        $t->same('partial', $inputSupport['xml']['status']);
        $t->same(XmlHtmlDom::class, $inputSupport['xml']['implementation']);
        $t->contains('full Pandoc XML reader parity remains open', $inputSupport['xml']['notes']);
        $t->same('partial', $inputSupport['jats']['status']);
        $t->same(XmlHtmlDom::class, $inputSupport['jats']['implementation']);
        $t->contains('unsupported direct-reader parity reasons', $inputSupport['jats']['notes']);
        $t->same('partial', $inputSupport['bits']['status']);
        $t->same(XmlHtmlDom::class, $inputSupport['bits']['implementation']);
        $t->contains('full Pandoc BITS reader parity remains open', $inputSupport['bits']['notes']);
        $t->same(MarkdownWriter::class, $outputSupport['markdown']['implementation']);
        $t->same(PandocJsonWriter::class, $outputSupport['json']['implementation']);
        $t->same(PlainWriter::class, $outputSupport['plain']['implementation']);
        $t->contains('wrapping diagnostics', $outputSupport['plain']['notes']);

        $t->same(28, count(PandocFormatRegistry::unsupportedInputFormats()));
        $t->same(false, in_array('xml', PandocFormatRegistry::unsupportedInputFormats(), true));
        $t->same(false, in_array('jats', PandocFormatRegistry::unsupportedInputFormats(), true));
        $t->same(false, in_array('bits', PandocFormatRegistry::unsupportedInputFormats(), true));
        $t->same(61, count(PandocFormatRegistry::unsupportedOutputFormats()));
    },
    'tracks xml jats bits direct reader capabilities without parity claims' => static function (TestRunner $t): void {
        $inputSupport = PandocFormatRegistry::xmlJatsBitsInputSupport();
        $directions = PandocFormatRegistry::xmlJatsBitsFormatDirections();
        $packet = PandocFormatRegistry::xmlJatsBitsDirectReaderCapabilityPacket();
        $expectedFormats = ['xml', 'jats', 'bits'];
        $expectedCounters = [
            'phpPass' => 3369,
            'phpFail' => 0,
            'mappedUpstreamCases' => 3329,
            'mappedPandocXmlDirectInputRegistryCases' => 1,
            'pandocXmlDirectInputRegistryAssertions' => 17,
            'mappedXmlHtmlDomJatsFrontMatterReviewCases' => 2,
            'xmlHtmlDomJatsFrontMatterReviewAssertions' => 69,
            'mappedXmlHtmlDomJatsBodyDiagnosticsCases' => 2,
            'xmlHtmlDomJatsBodyDiagnosticsAssertions' => 60,
            'mappedXmlHtmlDomJatsBackMatterReferenceCases' => 1,
            'xmlHtmlDomJatsBackMatterReferenceAssertions' => 39,
            'mappedXmlHtmlDomJatsRelationshipDiagnosticCases' => 1,
            'xmlHtmlDomJatsRelationshipDiagnosticAssertions' => 28,
            'mappedXmlHtmlDomDirectReaderCapabilityCases' => 1,
            'xmlHtmlDomDirectReaderCapabilityAssertions' => 91,
        ];

        $t->same($expectedFormats, PandocFormatRegistry::xmlJatsBitsInputFormats());
        $t->same($expectedFormats, array_keys($inputSupport));
        $t->same([], PandocFormatRegistry::unsupportedXmlJatsBitsInputFormats());
        $t->same($expectedFormats, array_keys($directions));
        $t->same($expectedCounters, PandocFormatRegistry::xmlJatsBitsLocalEvidenceCounters());
        $t->same($expectedCounters, $packet['localEvidenceCounters']);
        $t->same($expectedFormats, $packet['unsupportedDirectReaderFormats']);
        $t->same(3, $packet['unsupportedDirectReaderCount']);

        foreach ($expectedFormats as $format) {
            $t->same('partial', $inputSupport[$format]['status'], "XML/JATS/BITS input {$format} should expose bounded direct input routing");
            $t->same(XmlHtmlDom::class, $inputSupport[$format]['implementation']);
            $t->contains('full Pandoc', $inputSupport[$format]['notes']);
            $t->same(true, $directions[$format]['input']);
            $t->same(false, $directions[$format]['output']);
            $t->same('input-only', $directions[$format]['direction']);
            $t->same('partial', $directions[$format]['inputStatus']);
            $t->same('not-applicable', $directions[$format]['outputStatus']);
            $t->same(false, $packet['formats'][$format]['directReaderParity']);
            $t->same(XmlHtmlDom::class, $packet['formats'][$format]['diagnosticImplementation']);
            $t->same(XmlHtmlDom::class, $packet['formats'][$format]['inputImplementation']);
            $t->contains('full Pandoc', $packet['formats'][$format]['inputNotes']);
            $t->same(false, $packet['formats'][$format]['registeredDirectReaderRecord']);
            $t->same('full-direct-reader-missing', $packet['formats'][$format]['unsupportedDirectReaderReason']['code']);
            $t->same('unsupported', $packet['formats'][$format]['unsupportedDirectReaderReason']['status']);
            $t->same(false, $packet['formats'][$format]['unsupportedDirectReaderReason']['directReaderParity']);
            $t->contains('full Pandoc', $packet['formats'][$format]['unsupportedDirectReaderReason']['message']);
        }

        $t->same('2026-06-03', $packet['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $packet['upstreamManualUrl']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $packet['upstreamSourceCommit']);
        $t->same($expectedFormats, $packet['inputFormats']);
        $t->same([], $packet['unsupportedInputFormats']);
        $t->same(0, $packet['unsupportedInputCount']);
        $t->same(['partial' => 3], $packet['inputSupportStatusCounts']);
        $t->same(false, $packet['directReaderParitySupported']);
        $t->same(0, $packet['registeredDirectReaderImplementations']);
        $t->same(0, $packet['registeredDirectReaderRecords']);
        $t->same([], $packet['registeredDirectReaderRecordFormats']);
        $t->same(3, $packet['registeredDiagnosticImplementations']);
        $t->same(3, $packet['boundedDiagnosticSurfaceCount']);
        $t->same(true, $packet['explicitUnsupportedVerdict']);
        $t->contains('no full direct reader parity is registered', $packet['reviewNote']);

        $t->same('summarizeXmlNamespaceUsage', $packet['formats']['xml']['reviewMethod']);
        $t->same('xml-namespace-usage-diagnostics-review-only', $packet['formats']['xml']['reviewPolicy']);
        $t->same(null, $packet['formats']['xml']['aliasedTo']);
        $t->contains('safe XML loading', implode('; ', $packet['formats']['xml']['boundedDiagnostics']));
        $t->contains('root, element, language, id, and attribute provenance', implode('; ', $packet['formats']['xml']['boundedDiagnostics']));
        $t->contains('namespace declaration provenance', implode('; ', $packet['formats']['xml']['boundedDiagnostics']));
        $t->contains('bounded namespace declaration scope', implode('; ', $packet['formats']['xml']['boundedDiagnostics']));
        $t->true(in_array('namespaceScopes', $packet['formats']['xml']['reviewPacketFields'], true));
        $t->true(in_array('prefixRedefinitions', $packet['formats']['xml']['reviewPacketFields'], true));
        $t->true(in_array('duplicateUriSummaries', $packet['formats']['xml']['reviewPacketFields'], true));
        $t->true(in_array('namespaceDiagnosticCodes', $packet['formats']['xml']['reviewPacketFields'], true));
        $t->contains('namespace collision summaries', implode('; ', $packet['formats']['xml']['boundedDiagnostics']));
        $t->contains('prefix and URI frequency summaries', implode('; ', $packet['formats']['xml']['boundedDiagnostics']));
        $t->contains('default namespace transition diagnostics', implode('; ', $packet['formats']['xml']['boundedDiagnostics']));
        $t->contains('full Pandoc XML input mapping', implode('; ', $packet['formats']['xml']['remainingReaderGaps']));
        $t->contains('full Pandoc XML reader parity remains open', $packet['formats']['xml']['inputNotes']);

        $t->same('summarizeJatsFrontMatter', $packet['formats']['jats']['reviewMethod']);
        $t->same('jats-bits-front-matter-and-body-diagnostics-review-only', $packet['formats']['jats']['reviewPolicy']);
        $t->same(null, $packet['formats']['jats']['aliasedTo']);
        $t->contains('root element qualified name', implode('; ', $packet['formats']['jats']['boundedDiagnostics']));
        $t->contains('article front-matter identifiers', implode('; ', $packet['formats']['jats']['boundedDiagnostics']));
        $t->true(in_array('namespaceSummary', $packet['formats']['jats']['reviewPacketFields'], true));
        $t->true(in_array('directReaderDiagnostics', $packet['formats']['jats']['reviewPacketFields'], true));
        $t->contains('full JATS body and back-matter mapping', implode('; ', $packet['formats']['jats']['remainingReaderGaps']));

        $t->same('summarizeJatsFrontMatter', $packet['formats']['bits']['reviewMethod']);
        $t->same('jats-bits-front-matter-and-body-diagnostics-review-only', $packet['formats']['bits']['reviewPolicy']);
        $t->same('jats', $packet['formats']['bits']['aliasedTo']);
        $t->contains('root element qualified name', implode('; ', $packet['formats']['bits']['boundedDiagnostics']));
        $t->contains('book and book-part metadata identifiers', implode('; ', $packet['formats']['bits']['boundedDiagnostics']));
        $t->true(in_array('namespaceSummary', $packet['formats']['bits']['reviewPacketFields'], true));
        $t->true(in_array('directReaderDiagnostics', $packet['formats']['bits']['reviewPacketFields'], true));
        $t->contains('full BITS book body and book-part mapping', implode('; ', $packet['formats']['bits']['remainingReaderGaps']));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'builds xml namespace registry review packets without direct reader records' => static function (TestRunner $t): void {
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
XML, 'registry XML namespace packet', preserveWhiteSpace: false);
        $packet = PandocFormatRegistry::xmlNamespaceUsageReviewPacket($dom);
        $prefixRows = [];
        foreach ($packet['namespacePrefixFrequencyRows'] as $row) {
            $prefixRows[$row['prefix']] = $row;
        }
        $uriRows = [];
        foreach ($packet['namespaceUriFrequencyRows'] as $row) {
            $uriRows[$row['namespaceUri']] = $row;
        }
        $sameUriAliases = [];
        foreach ($packet['sameUriMultiplePrefixes'] as $alias) {
            $sameUriAliases[$alias['namespaceUri']] = $alias;
        }
        $samePrefixAliases = [];
        foreach ($packet['samePrefixMultipleUris'] as $alias) {
            $samePrefixAliases[$alias['prefix']] = $alias;
        }

        $t->same('xml', $packet['format']);
        $t->same('xml', $packet['inputFormat']);
        $t->same('partial', $packet['inputStatus']);
        $t->same(XmlHtmlDom::class, $packet['inputImplementation']);
        $t->same(XmlHtmlDom::class, $packet['diagnosticImplementation']);
        $t->same('summarizeXmlNamespaceUsage', $packet['reviewMethod']);
        $t->same('xml-namespace-usage-diagnostics-review-only', $packet['reviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same('unsupported', $packet['directReaderParityStatus']);
        $t->same('full-direct-reader-missing', $packet['unsupportedDirectReaderReason']);
        $t->same('full-direct-reader-missing', $packet['unsupportedDirectReaderDiagnostic']['code']);
        $t->same(false, $packet['unsupportedDirectReaderDiagnostic']['directReaderParity']);
        $t->same(0, $packet['registeredDirectReaderImplementations']);
        $t->same(0, $packet['registeredDirectReaderRecords']);
        $t->same([], $packet['registeredDirectReaderRecordFormats']);
        $t->same(1, $packet['registeredDiagnosticImplementations']);
        $t->contains('prefix and URI frequency summaries', implode('; ', $packet['boundedDiagnostics']));
        $t->contains('full Pandoc XML input mapping', implode('; ', $packet['remainingReaderGaps']));

        $t->same(7, $packet['namespacePrefixFrequencyRowCount']);
        $t->same($packet['namespacePrefixFrequencies'], $packet['namespacePrefixFrequencyRows']);
        $t->same(['urn:item-a', 'urn:item-b'], $prefixRows['a']['namespaceUris'] ?? null);
        $t->same(2, $prefixRows['a']['useCount'] ?? null);
        $t->same(['', 'urn:group', 'urn:root'], $prefixRows['default']['namespaceUris'] ?? null);
        $t->same(6, $prefixRows['default']['useCount'] ?? null);
        $t->same(['urn:attr-a'], $prefixRows['attrA']['namespaceUris'] ?? null);
        $t->same(4, $prefixRows['attrA']['attributeUseCount'] ?? null);

        $t->same(7, $packet['namespaceUriFrequencyRowCount']);
        $t->same($packet['namespaceUriFrequencies'], $packet['namespaceUriFrequencyRows']);
        $t->same(['default', 'rootAlias'], $uriRows['urn:root']['prefixes'] ?? null);
        $t->same(3, $uriRows['urn:root']['useCount'] ?? null);
        $t->same(['a', 'b'], $uriRows['urn:item-b']['prefixes'] ?? null);
        $t->same(['default', 'none'], $uriRows['']['prefixes'] ?? null);

        $t->same(5, $packet['defaultNamespaceUseCount']);
        $t->same(['urn:group', 'urn:root'], $packet['defaultNamespaceUris']);
        $t->same(2, $packet['defaultNamespaceUriCount']);
        $t->same(2, $packet['sameUriMultiplePrefixCount']);
        $t->same(['a', 'b'], $sameUriAliases['urn:item-b']['prefixes'] ?? null);
        $t->same(['default', 'rootAlias'], $sameUriAliases['urn:root']['prefixes'] ?? null);
        $t->same(2, $packet['samePrefixMultipleUriCount']);
        $t->same(['urn:item-a', 'urn:item-b'], $samePrefixAliases['a']['namespaceUris'] ?? null);
        $t->same(['', 'urn:group', 'urn:root'], $samePrefixAliases['default']['namespaceUris'] ?? null);

        $t->same(1, $packet['elementNamespaceCollisionCount']);
        $t->same(1, $packet['attributeNamespaceCollisionCount']);
        $t->same(3, $packet['defaultNamespaceTransitionCount']);
        $t->same([
            'direct-reader-unsupported',
            'element-local-name-namespace-collisions',
            'attribute-local-name-namespace-collisions',
            'default-namespace-transitions',
            'default-namespace-usage',
            'namespace-uri-multiple-prefixes',
            'namespace-prefix-multiple-uris',
        ], $packet['directReaderDiagnosticCodes']);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'tracks roff manual reader writer and extension inference registry metadata' => static function (TestRunner $t): void {
        $t->same(['man', 'mdoc'], PandocFormatRegistry::roffManualInputFormats());
        $t->same(['man', 'ms'], PandocFormatRegistry::roffManualOutputFormats());
        $t->same([
            '.ms' => 'ms',
            '.roff' => 'ms',
            '.[1-9]' => 'man',
            '.[1-9][a-z]+' => 'man',
        ], PandocFormatRegistry::roffManualExtensionInference());

        $inputSupport = PandocFormatRegistry::roffManualInputSupport();
        $outputSupport = PandocFormatRegistry::roffManualOutputSupport();

        $t->same(['man', 'mdoc'], array_keys($inputSupport));
        $t->same(['man', 'ms'], array_keys($outputSupport));

        $t->same('unsupported', $inputSupport['man']['status']);
        $t->same('', $inputSupport['man']['implementation']);
        $t->contains('upstream man reader source semantics', $inputSupport['man']['notes']);
        $t->same('unsupported', $inputSupport['mdoc']['status']);
        $t->same('', $inputSupport['mdoc']['implementation']);
        $t->contains('manual-family input', $inputSupport['mdoc']['notes']);

        $t->same('unsupported', $outputSupport['man']['status']);
        $t->same('', $outputSupport['man']['implementation']);
        $t->contains('upstream man writer source semantics', $outputSupport['man']['notes']);
        $t->same('unsupported', $outputSupport['ms']['status']);
        $t->same('', $outputSupport['ms']['implementation']);
        $t->contains('.ms/.roff extension inference', $outputSupport['ms']['notes']);

        $t->same(28, count(PandocFormatRegistry::unsupportedInputFormats()));
        $t->same(61, count(PandocFormatRegistry::unsupportedOutputFormats()));
    },
    'tracks roff manual input output direction buckets without direct parity claims' => static function (TestRunner $t): void {
        $directions = PandocFormatRegistry::roffManualFormatDirections();
        $inputFormats = PandocFormatRegistry::roffManualInputFormats();
        $outputFormats = PandocFormatRegistry::roffManualOutputFormats();

        $t->same([
            'man',
            'mdoc',
            'ms',
        ], array_keys($directions));
        $t->same(['man'], PandocFormatRegistry::roffManualBidirectionalFormats());
        $t->same(['mdoc'], PandocFormatRegistry::roffManualInputOnlyFormats());
        $t->same(['ms'], PandocFormatRegistry::roffManualOutputOnlyFormats());
        $t->same(3, count($directions));

        foreach ($directions as $format => $direction) {
            $hasInput = in_array($format, $inputFormats, true);
            $hasOutput = in_array($format, $outputFormats, true);
            $expectedDirection = $hasInput && $hasOutput ? 'input-output' : ($hasInput ? 'input-only' : 'output-only');

            $t->same($hasInput, $direction['input'], "Roff manual format {$format} input direction mismatch");
            $t->same($hasOutput, $direction['output'], "Roff manual format {$format} output direction mismatch");
            $t->same($expectedDirection, $direction['direction'], "Roff manual format {$format} direction bucket mismatch");
            $t->same($hasInput ? 'unsupported' : 'not-applicable', $direction['inputStatus'], "Roff manual format {$format} input status mismatch");
            $t->same($hasOutput ? 'unsupported' : 'not-applicable', $direction['outputStatus'], "Roff manual format {$format} output status mismatch");
        }
    },
    'tracks roff manual extension inference buckets without direct parity claims' => static function (TestRunner $t): void {
        $directions = PandocFormatRegistry::roffManualExtensionDirections();

        $t->same(['.ms', '.roff', '.[1-9]', '.[1-9][a-z]+'], array_keys($directions));

        $t->same('ms', $directions['.ms']['format']);
        $t->same(false, $directions['.ms']['input']);
        $t->same(true, $directions['.ms']['output']);
        $t->same('output-only', $directions['.ms']['direction']);
        $t->same('not-applicable', $directions['.ms']['inputStatus']);
        $t->same('unsupported', $directions['.ms']['outputStatus']);

        $t->same('ms', $directions['.roff']['format']);
        $t->same(false, $directions['.roff']['input']);
        $t->same(true, $directions['.roff']['output']);
        $t->same('output-only', $directions['.roff']['direction']);
        $t->same('not-applicable', $directions['.roff']['inputStatus']);
        $t->same('unsupported', $directions['.roff']['outputStatus']);

        foreach (['.[1-9]', '.[1-9][a-z]+'] as $pattern) {
            $t->same('man', $directions[$pattern]['format']);
            $t->same(true, $directions[$pattern]['input']);
            $t->same(true, $directions[$pattern]['output']);
            $t->same('input-output', $directions[$pattern]['direction']);
            $t->same('unsupported', $directions[$pattern]['inputStatus']);
            $t->same('unsupported', $directions[$pattern]['outputStatus']);
        }

        $t->same([], array_keys(array_filter($directions, static fn (array $direction): bool => $direction['format'] === 'mdoc')));
    },
    'builds roff manual review packets with extension inference and unsupported parity' => static function (TestRunner $t): void {
        $t->same('ms', PandocFormatRegistry::inferRoffManualFormatFromExtension('.ms'));
        $t->same('ms', PandocFormatRegistry::inferRoffManualFormatFromExtension('MS'));
        $t->same('ms', PandocFormatRegistry::inferRoffManualFormatFromExtension('.roff'));
        $t->same('ms', PandocFormatRegistry::inferRoffManualFormatFromExtension('ROFF'));
        $t->same('man', PandocFormatRegistry::inferRoffManualFormatFromExtension('.1'));
        $t->same('man', PandocFormatRegistry::inferRoffManualFormatFromExtension('9'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension(''));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.0'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.10'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.mdoc'));

        $packet = PandocFormatRegistry::roffManualFormatReviewPacket();

        $t->same('2026-06-03', $packet['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $packet['upstreamManualUrl']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $packet['upstreamSourceCommit']);
        $t->same(PandocFormatRegistry::roffManualInputFormats(), $packet['inputFormats']);
        $t->same(PandocFormatRegistry::roffManualOutputFormats(), $packet['outputFormats']);
        $t->same([
            'inputOutput' => ['man'],
            'inputOnly' => ['mdoc'],
            'outputOnly' => ['ms'],
        ], $packet['directionBuckets']);
        $t->same([
            '.ms' => 'ms',
            '.roff' => 'ms',
            '.[1-9]' => 'man',
            '.[1-9][a-z]+' => 'man',
        ], $packet['extensionInference']);
        $t->same(['ms', 'man'], $packet['extensionInferredFormats']);
        $t->same(['mdoc'], $packet['nonExtensionInferredFormats']);
        $t->same(PandocFormatRegistry::roffManualInputFormats(), $packet['unsupportedInputFormats']);
        $t->same(PandocFormatRegistry::roffManualOutputFormats(), $packet['unsupportedOutputFormats']);
        $t->same([
            'man',
            'mdoc',
            'ms',
        ], array_keys($packet['formats']));

        $t->same([
            'input' => true,
            'output' => true,
            'direction' => 'input-output',
            'inputStatus' => 'unsupported',
            'outputStatus' => 'unsupported',
            'extensionInferred' => true,
            'extensions' => ['.[1-9]', '.[1-9][a-z]+'],
            'inputImplementation' => '',
            'outputImplementation' => '',
        ], $packet['formats']['man']);
        $t->same([
            'input' => true,
            'output' => false,
            'direction' => 'input-only',
            'inputStatus' => 'unsupported',
            'outputStatus' => 'not-applicable',
            'extensionInferred' => false,
            'extensions' => [],
            'inputImplementation' => '',
            'outputImplementation' => '',
        ], $packet['formats']['mdoc']);
        $t->same([
            'input' => false,
            'output' => true,
            'direction' => 'output-only',
            'inputStatus' => 'not-applicable',
            'outputStatus' => 'unsupported',
            'extensionInferred' => true,
            'extensions' => ['.ms', '.roff'],
            'inputImplementation' => '',
            'outputImplementation' => '',
        ], $packet['formats']['ms']);

        foreach ($packet['formats'] as $format => $review) {
            $t->same('', $review['inputImplementation'], "Roff manual review packet {$format} must not register an input implementation");
            $t->same('', $review['outputImplementation'], "Roff manual review packet {$format} must not register an output implementation");
        }
    },
    'tracks roff manual section suffix extension inference without reader parity' => static function (TestRunner $t): void {
        $t->same('man', PandocFormatRegistry::inferRoffManualFormatFromExtension('.3p'));
        $t->same('man', PandocFormatRegistry::inferRoffManualFormatFromExtension('3P'));
        $t->same('man', PandocFormatRegistry::inferRoffManualFormatFromExtension('.5ssl'));
        $t->same('man', PandocFormatRegistry::inferRoffManualFormatFromExtension('7tcl'));
        $t->same('man', PandocFormatRegistry::inferRoffManualFormatFromExtension('.9x'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.3-p'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.3.1'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.3_foo'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.10ssl'));

        $t->same(['ms', 'man'], PandocFormatRegistry::roffManualFormatsWithExtensionInference());
        $t->same(['mdoc'], PandocFormatRegistry::roffManualFormatsWithoutExtensionInference());

        $packet = PandocFormatRegistry::roffManualFormatReviewPacket();
        $t->same(['.[1-9]', '.[1-9][a-z]+'], $packet['formats']['man']['extensions']);
        $t->same('unsupported', $packet['formats']['man']['inputStatus']);
        $t->same('unsupported', $packet['formats']['man']['outputStatus']);
        $t->same('', $packet['formats']['man']['inputImplementation']);
        $t->same('', $packet['formats']['man']['outputImplementation']);
        $t->same(false, $packet['formats']['mdoc']['extensionInferred']);
    },
    'classifies roff manual extension evidence without converter parity' => static function (TestRunner $t): void {
        $metadata = PandocFormatRegistry::roffManualExtensionPatternMetadata();

        $t->same([
            '.ms' => [
                'format' => 'ms',
                'kind' => 'ms-macro-package',
                'manualSection' => false,
            ],
            '.roff' => [
                'format' => 'ms',
                'kind' => 'generic-roff-source',
                'manualSection' => false,
            ],
            '.[1-9]' => [
                'format' => 'man',
                'kind' => 'manual-section',
                'manualSection' => true,
            ],
            '.[1-9][a-z]+' => [
                'format' => 'man',
                'kind' => 'manual-section-suffix',
                'manualSection' => true,
            ],
        ], $metadata);

        foreach ($metadata as $pattern => $entry) {
            $t->same(PandocFormatRegistry::roffManualExtensionInference()[$pattern], $entry['format'], "Roff manual extension metadata {$pattern} must match format inference");
        }

        $t->same([
            'format' => 'ms',
            'normalizedExtension' => '.ms',
            'pattern' => '.ms',
            'kind' => 'ms-macro-package',
            'manualSection' => null,
            'manualSectionNumber' => null,
            'manualSectionSuffix' => null,
        ], PandocFormatRegistry::classifyRoffManualExtension('MS'));
        $t->same([
            'format' => 'ms',
            'normalizedExtension' => '.roff',
            'pattern' => '.roff',
            'kind' => 'generic-roff-source',
            'manualSection' => null,
            'manualSectionNumber' => null,
            'manualSectionSuffix' => null,
        ], PandocFormatRegistry::classifyRoffManualExtension('.ROFF'));
        $t->same([
            'format' => 'man',
            'normalizedExtension' => '.1',
            'pattern' => '.[1-9]',
            'kind' => 'manual-section',
            'manualSection' => '1',
            'manualSectionNumber' => '1',
            'manualSectionSuffix' => '',
        ], PandocFormatRegistry::classifyRoffManualExtension('1'));
        $t->same([
            'format' => 'man',
            'normalizedExtension' => '.3p',
            'pattern' => '.[1-9][a-z]+',
            'kind' => 'manual-section-suffix',
            'manualSection' => '3p',
            'manualSectionNumber' => '3',
            'manualSectionSuffix' => 'p',
        ], PandocFormatRegistry::classifyRoffManualExtension('.3P'));
        $t->same([
            'format' => null,
            'normalizedExtension' => '.10ssl',
            'pattern' => null,
            'kind' => 'unknown',
            'manualSection' => null,
            'manualSectionNumber' => null,
            'manualSectionSuffix' => null,
        ], PandocFormatRegistry::classifyRoffManualExtension('.10ssl'));

        foreach (['.ms', '.roff', '.1', '.3p', '.5ssl', '.10ssl', '.mdoc', ''] as $extension) {
            $classification = PandocFormatRegistry::classifyRoffManualExtension($extension);
            $t->same($classification['format'], PandocFormatRegistry::inferRoffManualFormatFromExtension($extension), "Roff manual classification {$extension} should drive format inference");
        }

        $packet = PandocFormatRegistry::roffManualFormatReviewPacket();
        $t->same($metadata, $packet['extensionPatternMetadata']);
        $t->same('unsupported', $packet['formats']['man']['inputStatus']);
        $t->same('unsupported', $packet['formats']['man']['outputStatus']);
        $t->same('unsupported', $packet['formats']['ms']['outputStatus']);
        $t->same('', $packet['formats']['man']['inputImplementation']);
        $t->same('', $packet['formats']['ms']['outputImplementation']);
    },
    'classifies roff manual unsupported surfaces from extensions without converter claims' => static function (TestRunner $t): void {
        $man = PandocFormatRegistry::roffManualUnsupportedFormatForExtension('3P');
        $t->same([
            'extension' => '.3p',
            'format' => 'man',
            'pattern' => '.[1-9][a-z]+',
            'kind' => 'manual-section-suffix',
            'manualSection' => '3p',
            'manualSectionNumber' => '3',
            'manualSectionSuffix' => 'p',
            'input' => true,
            'output' => true,
            'direction' => 'input-output',
            'inputStatus' => 'unsupported',
            'outputStatus' => 'unsupported',
            'unsupportedInput' => true,
            'unsupportedOutput' => true,
            'inputImplementation' => '',
            'outputImplementation' => '',
        ], $man);

        $literalMan = PandocFormatRegistry::roffManualUnsupportedFormatForExtension('.1');
        $t->same('manual-section', $literalMan['kind']);
        $t->same('1', $literalMan['manualSection']);
        $t->same(true, $literalMan['unsupportedInput']);
        $t->same(true, $literalMan['unsupportedOutput']);

        $ms = PandocFormatRegistry::roffManualUnsupportedFormatForExtension('.ms');
        $t->same('ms', $ms['format']);
        $t->same('ms-macro-package', $ms['kind']);
        $t->same('output-only', $ms['direction']);
        $t->same(false, $ms['input']);
        $t->same(true, $ms['output']);
        $t->same(false, $ms['unsupportedInput']);
        $t->same(true, $ms['unsupportedOutput']);
        $t->same('not-applicable', $ms['inputStatus']);
        $t->same('unsupported', $ms['outputStatus']);
        $t->same('', $ms['inputImplementation']);
        $t->same('', $ms['outputImplementation']);

        $roff = PandocFormatRegistry::roffManualUnsupportedFormatForExtension('ROFF');
        $t->same('.roff', $roff['extension']);
        $t->same('generic-roff-source', $roff['kind']);
        $t->same('ms', $roff['format']);
        $t->same(true, $roff['unsupportedOutput']);

        $t->same(null, PandocFormatRegistry::roffManualUnsupportedFormatForExtension('.mdoc'));
        $t->same(null, PandocFormatRegistry::roffManualUnsupportedFormatForExtension('.10ssl'));

        $surfaces = PandocFormatRegistry::roffManualUnsupportedExtensionSurfaces();
        $packet = PandocFormatRegistry::roffManualFormatReviewPacket();
        $summary = PandocFormatRegistry::roffManualFormatParitySummary();

        $t->same([
            '.ms',
            '.roff',
            '.[1-9]',
            '.[1-9][a-z]+',
        ], array_keys($surfaces));
        $t->same($surfaces, $packet['unsupportedExtensionSurfaces']);
        $t->same(4, $summary['unsupportedExtensionSurfaceMappings']);
        $t->same('ms', $surfaces['.ms']['format']);
        $t->same('output-only', $surfaces['.roff']['direction']);
        $t->same('man', $surfaces['.[1-9]']['format']);
        $t->same('input-output', $surfaces['.[1-9][a-z]+']['direction']);

        foreach ($surfaces as $extension => $surface) {
            $t->same('', $surface['inputImplementation'], "Roff manual extension {$extension} must not register an input implementation");
            $t->same('', $surface['outputImplementation'], "Roff manual extension {$extension} must not register an output implementation");
            $t->same(true, $surface['unsupportedInput'] || $surface['unsupportedOutput'], "Roff manual extension {$extension} must expose an unsupported surface");
        }
    },
    'summarizes roff manual unsupported format surfaces without converter claims' => static function (TestRunner $t): void {
        $summary = PandocFormatRegistry::roffManualUnsupportedFormatSummary();
        $packet = PandocFormatRegistry::roffManualFormatReviewPacket();
        $directions = PandocFormatRegistry::roffManualFormatDirections();

        $t->same($summary, $packet['unsupportedFormatSummary']);
        $t->same([
            'man',
            'mdoc',
            'ms',
        ], $summary['anyUnsupported']);
        $t->same(['man'], $summary['unsupportedBoth']);
        $t->same(['mdoc'], $summary['unsupportedInputOnly']);
        $t->same(['ms'], $summary['unsupportedOutputOnly']);
        $t->same(PandocFormatRegistry::unsupportedRoffManualInputFormats(), $summary['noNativeReader']);
        $t->same(PandocFormatRegistry::unsupportedRoffManualOutputFormats(), $summary['noNativeWriter']);
        $t->same(['man', 'mdoc'], $summary['noNativeReader']);
        $t->same(['man', 'ms'], $summary['noNativeWriter']);

        foreach ($summary['anyUnsupported'] as $format) {
            $direction = $directions[$format];
            $t->same(true, $direction['inputStatus'] === 'unsupported' || $direction['outputStatus'] === 'unsupported', "Roff manual {$format} should have an unsupported surface");
        }

        $man = $packet['formats']['man'];
        $t->same('input-output', $man['direction']);
        $t->same('unsupported', $man['inputStatus']);
        $t->same('unsupported', $man['outputStatus']);
        $t->same(['.[1-9]', '.[1-9][a-z]+'], $man['extensions']);
        $t->same('', $man['inputImplementation']);
        $t->same('', $man['outputImplementation']);

        $mdoc = $packet['formats']['mdoc'];
        $t->same('input-only', $mdoc['direction']);
        $t->same('unsupported', $mdoc['inputStatus']);
        $t->same('not-applicable', $mdoc['outputStatus']);
        $t->same([], $mdoc['extensions']);
        $t->same('', $mdoc['inputImplementation']);

        $ms = $packet['formats']['ms'];
        $t->same('output-only', $ms['direction']);
        $t->same('not-applicable', $ms['inputStatus']);
        $t->same('unsupported', $ms['outputStatus']);
        $t->same(['.ms', '.roff'], $ms['extensions']);
        $t->same('', $ms['outputImplementation']);
    },
    'summarizes roff manual registry parity counts without registering converters' => static function (TestRunner $t): void {
        $summary = PandocFormatRegistry::roffManualFormatParitySummary();
        $packet = PandocFormatRegistry::roffManualFormatReviewPacket();

        $coreSummary = [
            'totalFormats' => $summary['totalFormats'],
            'inputFormats' => $summary['inputFormats'],
            'outputFormats' => $summary['outputFormats'],
            'inputOutputFormats' => $summary['inputOutputFormats'],
            'inputOnlyFormats' => $summary['inputOnlyFormats'],
            'outputOnlyFormats' => $summary['outputOnlyFormats'],
            'extensionInferenceMappings' => $summary['extensionInferenceMappings'],
            'extensionInferredFormats' => $summary['extensionInferredFormats'],
            'nonExtensionInferredFormats' => $summary['nonExtensionInferredFormats'],
            'manualSectionExtensionMappings' => $summary['manualSectionExtensionMappings'],
            'literalExtensionMappings' => $summary['literalExtensionMappings'],
            'unsupportedInputFormats' => $summary['unsupportedInputFormats'],
            'unsupportedOutputFormats' => $summary['unsupportedOutputFormats'],
            'registeredInputImplementations' => $summary['registeredInputImplementations'],
            'registeredOutputImplementations' => $summary['registeredOutputImplementations'],
            'directParityClaimed' => $summary['directParityClaimed'],
        ];
        $t->same([
            'totalFormats' => 3,
            'inputFormats' => 2,
            'outputFormats' => 2,
            'inputOutputFormats' => 1,
            'inputOnlyFormats' => 1,
            'outputOnlyFormats' => 1,
            'extensionInferenceMappings' => 4,
            'extensionInferredFormats' => 2,
            'nonExtensionInferredFormats' => 1,
            'manualSectionExtensionMappings' => 2,
            'literalExtensionMappings' => 2,
            'unsupportedInputFormats' => 2,
            'unsupportedOutputFormats' => 2,
            'registeredInputImplementations' => 0,
            'registeredOutputImplementations' => 0,
            'directParityClaimed' => false,
        ], $coreSummary);

        $t->same($summary, $packet['paritySummary']);
        $t->same('2026-06-03', $summary['upstreamManualDate']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $summary['upstreamSourceCommit']);
        $t->same(3, $summary['uniqueFormatCount']);
        $t->same([
            'inputOutput' => 1,
            'inputOnly' => 1,
            'outputOnly' => 1,
        ], $summary['directionCounts']);
        $t->same(['unsupported' => 2], $summary['inputSupportStatusCounts']);
        $t->same(['unsupported' => 2], $summary['outputSupportStatusCounts']);
        $t->same(2, $summary['inputFormatCount']);
        $t->same(2, $summary['outputFormatCount']);
        $t->same(2, $summary['extensionInferredFormatCount']);
        $t->same(1, $summary['nonExtensionInferredFormatCount']);
        $t->same(true, $summary['sectionSuffixExtensionInference']);
        $t->same(2, $summary['unsupportedInputCount']);
        $t->same(2, $summary['unsupportedOutputCount']);
        $t->same(false, $summary['directReaderParitySupported']);
        $t->same(false, $summary['directWriterParitySupported']);
        $t->same('unsupported', $summary['directParityStatus']);
        $t->contains('no native PHP roff/manual reader or writer is registered', $summary['reviewNote']);

        $t->same(count($packet['formats']), $summary['totalFormats']);
        $t->same(count($packet['directionBuckets']['inputOutput']), $summary['inputOutputFormats']);
        $t->same(count($packet['directionBuckets']['inputOnly']), $summary['inputOnlyFormats']);
        $t->same(count($packet['directionBuckets']['outputOnly']), $summary['outputOnlyFormats']);
        $t->same(count($packet['extensionInference']), $summary['extensionInferenceMappings']);
        $t->same(count($packet['unsupportedInputFormats']), $summary['unsupportedInputFormats']);
        $t->same(count($packet['unsupportedOutputFormats']), $summary['unsupportedOutputFormats']);
    },
    'tracks rich package formats and unsupported direct writer parity' => static function (TestRunner $t): void {
        $t->same([
            'docx',
            'epub',
            'ipynb',
            'odt',
            'pptx',
            'xlsx',
        ], PandocFormatRegistry::richPackageInputFormats());
        $t->same([
            'chunkedhtml',
            'docx',
            'epub',
            'epub2',
            'epub3',
            'icml',
            'ipynb',
            'odt',
            'opendocument',
            'pdf',
            'pptx',
        ], PandocFormatRegistry::richPackageOutputFormats());

        $inputSupport = PandocFormatRegistry::richPackageInputSupport();
        $outputSupport = PandocFormatRegistry::richPackageOutputSupport();

        $t->same(PandocFormatRegistry::richPackageInputFormats(), array_keys($inputSupport));
        $t->same(PandocFormatRegistry::richPackageOutputFormats(), array_keys($outputSupport));

        $t->same('partial', $inputSupport['docx']['status']);
        $t->same(DocxReader::class, $inputSupport['docx']['implementation']);
        $t->same('partial', $inputSupport['epub']['status']);
        $t->same(EpubReader::class, $inputSupport['epub']['implementation']);
        $t->same('partial', $inputSupport['ipynb']['status']);
        $t->same(IpynbReader::class, $inputSupport['ipynb']['implementation']);
        $t->contains('notebook reader maps Markdown/code/raw cells', $inputSupport['ipynb']['notes']);
        $t->same('partial', $inputSupport['odt']['status']);
        $t->same(OdtReader::class, $inputSupport['odt']['implementation']);

        $t->same([
            'pptx',
            'xlsx',
        ], PandocFormatRegistry::unsupportedRichPackageInputFormats());
        foreach (PandocFormatRegistry::unsupportedRichPackageInputFormats() as $format) {
            $t->same('unsupported', $inputSupport[$format]['status'], "Rich package input {$format} should not claim direct native parity");
            $t->same('', $inputSupport[$format]['implementation']);
            $t->contains('No native PHP reader or writer is registered', $inputSupport[$format]['notes']);
        }

        $t->same([
            'chunkedhtml',
            'docx',
            'epub',
            'epub2',
            'epub3',
            'icml',
            'ipynb',
            'odt',
            'opendocument',
            'pdf',
            'pptx',
        ], PandocFormatRegistry::unsupportedRichPackageOutputFormats());
        foreach (PandocFormatRegistry::unsupportedRichPackageOutputFormats() as $format) {
            $t->same('unsupported', $outputSupport[$format]['status'], "Rich package output {$format} should not claim direct native writer parity");
            $t->same('', $outputSupport[$format]['implementation']);
            $t->contains('No native PHP reader or writer is registered', $outputSupport[$format]['notes']);
        }

        $t->same(28, count(PandocFormatRegistry::unsupportedInputFormats()));
        $t->same(61, count(PandocFormatRegistry::unsupportedOutputFormats()));
    },
    'tracks rich package input output direction buckets without direct writer parity claims' => static function (TestRunner $t): void {
        $directions = PandocFormatRegistry::richPackageFormatDirections();
        $inputFormats = PandocFormatRegistry::richPackageInputFormats();
        $outputFormats = PandocFormatRegistry::richPackageOutputFormats();

        $t->same([
            'docx',
            'epub',
            'ipynb',
            'odt',
            'pptx',
            'xlsx',
            'chunkedhtml',
            'epub2',
            'epub3',
            'icml',
            'opendocument',
            'pdf',
        ], array_keys($directions));
        $t->same([
            'docx',
            'epub',
            'ipynb',
            'odt',
            'pptx',
        ], PandocFormatRegistry::richPackageBidirectionalFormats());
        $t->same(['xlsx'], PandocFormatRegistry::richPackageInputOnlyFormats());
        $t->same([
            'chunkedhtml',
            'epub2',
            'epub3',
            'icml',
            'opendocument',
            'pdf',
        ], PandocFormatRegistry::richPackageOutputOnlyFormats());

        foreach ($directions as $format => $direction) {
            $hasInput = in_array($format, $inputFormats, true);
            $hasOutput = in_array($format, $outputFormats, true);
            $expectedDirection = $hasInput && $hasOutput ? 'input-output' : ($hasInput ? 'input-only' : 'output-only');
            $expectedInputStatus = in_array($format, ['docx', 'epub', 'ipynb', 'odt'], true) ? 'partial' : ($hasInput ? 'unsupported' : 'not-applicable');

            $t->same($hasInput, $direction['input'], "Rich package format {$format} input direction mismatch");
            $t->same($hasOutput, $direction['output'], "Rich package format {$format} output direction mismatch");
            $t->same($expectedDirection, $direction['direction'], "Rich package format {$format} direction bucket mismatch");
            $t->same($expectedInputStatus, $direction['inputStatus'], "Rich package format {$format} input status mismatch");
            $t->same($hasOutput ? 'unsupported' : 'not-applicable', $direction['outputStatus'], "Rich package format {$format} output status mismatch");
        }
    },
    'tracks rich package extension evidence without converter parity' => static function (TestRunner $t): void {
        $t->same([
            '.docx' => 'docx',
            '.epub' => 'epub',
            '.fodt' => 'opendocument',
            '.icml' => 'icml',
            '.ipynb' => 'ipynb',
            '.odt' => 'odt',
            '.pdf' => 'pdf',
            '.pptx' => 'pptx',
            '.xlsx' => 'xlsx',
        ], PandocFormatRegistry::richPackageExtensionInference());

        $t->same('docx', PandocFormatRegistry::inferRichPackageFormatFromExtension('.docx'));
        $t->same('docx', PandocFormatRegistry::inferRichPackageFormatFromExtension('DOCX'));
        $t->same('epub', PandocFormatRegistry::inferRichPackageFormatFromExtension('.EPUB'));
        $t->same('opendocument', PandocFormatRegistry::inferRichPackageFormatFromExtension('fodt'));
        $t->same('ipynb', PandocFormatRegistry::inferRichPackageFormatFromExtension('.ipynb'));
        $t->same('pdf', PandocFormatRegistry::inferRichPackageFormatFromExtension('PDF'));
        $t->same(null, PandocFormatRegistry::inferRichPackageFormatFromExtension(''));
        $t->same(null, PandocFormatRegistry::inferRichPackageFormatFromExtension('.zip'));

        $t->same([
            'format' => 'epub',
            'normalizedExtension' => '.epub',
            'kind' => 'epub-publication-package',
            'formats' => ['epub', 'epub2', 'epub3'],
            'inputFormats' => ['epub'],
            'outputFormats' => ['epub', 'epub2', 'epub3'],
        ], PandocFormatRegistry::classifyRichPackageExtension('EPUB'));
        $t->same([
            'format' => 'opendocument',
            'normalizedExtension' => '.fodt',
            'kind' => 'flat-open-document-text',
            'formats' => ['opendocument'],
            'inputFormats' => [],
            'outputFormats' => ['opendocument'],
        ], PandocFormatRegistry::classifyRichPackageExtension('.FODT'));
        $t->same([
            'format' => 'xlsx',
            'normalizedExtension' => '.xlsx',
            'kind' => 'office-open-xml-spreadsheet-package',
            'formats' => ['xlsx'],
            'inputFormats' => ['xlsx'],
            'outputFormats' => [],
        ], PandocFormatRegistry::classifyRichPackageExtension('xlsx'));
        $t->same([
            'format' => null,
            'normalizedExtension' => '.zip',
            'kind' => 'unknown',
            'formats' => [],
            'inputFormats' => [],
            'outputFormats' => [],
        ], PandocFormatRegistry::classifyRichPackageExtension('.zip'));

        $t->same([
            'docx',
            'epub',
            'epub2',
            'epub3',
            'opendocument',
            'icml',
            'ipynb',
            'odt',
            'pdf',
            'pptx',
            'xlsx',
        ], PandocFormatRegistry::richPackageFormatsWithExtensionInference());
        $t->same(['chunkedhtml'], PandocFormatRegistry::richPackageFormatsWithoutExtensionInference());

        $directions = PandocFormatRegistry::richPackageFormatDirections();
        foreach (PandocFormatRegistry::richPackageFormatsWithExtensionInference() as $format) {
            $t->same(true, array_key_exists($format, $directions), "Rich package extension-inferred format {$format} must remain in direction accounting");
            $t->same(true, $directions[$format]['inputStatus'] === 'partial' || $directions[$format]['inputStatus'] === 'unsupported' || $directions[$format]['inputStatus'] === 'not-applicable', "Rich package extension-inferred format {$format} must keep explicit input accounting");
            $t->same(true, $directions[$format]['outputStatus'] === 'unsupported' || $directions[$format]['outputStatus'] === 'not-applicable', "Rich package extension-inferred format {$format} must not claim output parity");
        }
    },
    'classifies rich package unsupported formats from file extensions without converter claims' => static function (TestRunner $t): void {
        $docx = PandocFormatRegistry::richPackageUnsupportedFormatForExtension('DOCX');
        $t->same([
            'extension' => '.docx',
            'format' => 'docx',
            'input' => true,
            'output' => true,
            'direction' => 'input-output',
            'inputStatus' => 'partial',
            'outputStatus' => 'unsupported',
            'unsupportedInput' => false,
            'unsupportedOutput' => true,
            'partialInput' => true,
            'partialOutput' => false,
            'inputImplementation' => DocxReader::class,
            'outputImplementation' => '',
        ], $docx);

        $ipynb = PandocFormatRegistry::richPackageUnsupportedFormatForExtension('.ipynb');
        $t->same('partial', $ipynb['inputStatus']);
        $t->same('unsupported', $ipynb['outputStatus']);
        $t->same(false, $ipynb['unsupportedInput']);
        $t->same(true, $ipynb['unsupportedOutput']);
        $t->same(true, $ipynb['partialInput']);
        $t->same(IpynbReader::class, $ipynb['inputImplementation']);
        $t->same('', $ipynb['outputImplementation']);

        $xlsx = PandocFormatRegistry::richPackageUnsupportedFormatForExtension('.xlsx');
        $t->same('input-only', $xlsx['direction']);
        $t->same('unsupported', $xlsx['inputStatus']);
        $t->same('not-applicable', $xlsx['outputStatus']);
        $t->same(true, $xlsx['unsupportedInput']);
        $t->same(false, $xlsx['unsupportedOutput']);

        $pdf = PandocFormatRegistry::richPackageUnsupportedFormatForExtension('pdf');
        $t->same('output-only', $pdf['direction']);
        $t->same('not-applicable', $pdf['inputStatus']);
        $t->same('unsupported', $pdf['outputStatus']);
        $t->same(false, $pdf['unsupportedInput']);
        $t->same(true, $pdf['unsupportedOutput']);

        $opendocument = PandocFormatRegistry::richPackageUnsupportedFormatForExtension('.fodt');
        $t->same('opendocument', $opendocument['format']);
        $t->same('output-only', $opendocument['direction']);
        $t->same('unsupported', $opendocument['outputStatus']);
        $t->same(null, PandocFormatRegistry::richPackageUnsupportedFormatForExtension('.zip'));
    },
    'builds rich package review packets without direct converter parity claims' => static function (TestRunner $t): void {
        $packet = PandocFormatRegistry::richPackageFormatReviewPacket();

        $t->same('2026-06-03', $packet['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $packet['upstreamManualUrl']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $packet['upstreamSourceCommit']);
        $t->same(PandocFormatRegistry::richPackageInputFormats(), $packet['inputFormats']);
        $t->same(PandocFormatRegistry::richPackageOutputFormats(), $packet['outputFormats']);
        $t->same([
            'inputOutput' => ['docx', 'epub', 'ipynb', 'odt', 'pptx'],
            'inputOnly' => ['xlsx'],
            'outputOnly' => ['chunkedhtml', 'epub2', 'epub3', 'icml', 'opendocument', 'pdf'],
        ], $packet['directionBuckets']);
        $t->same(PandocFormatRegistry::richPackageExtensionInference(), $packet['extensionInference']);
        $t->same(PandocFormatRegistry::richPackageExtensionMetadata(), $packet['extensionMetadata']);
        $t->same([
            'docx',
            'epub',
            'epub2',
            'epub3',
            'opendocument',
            'icml',
            'ipynb',
            'odt',
            'pdf',
            'pptx',
            'xlsx',
        ], $packet['extensionInferredFormats']);
        $t->same(['chunkedhtml'], $packet['nonExtensionInferredFormats']);
        $t->same(['docx', 'epub', 'ipynb', 'odt', 'pptx'], PandocFormatRegistry::richPackageBidirectionalFormats());
        $t->same(['xlsx'], PandocFormatRegistry::richPackageInputOnlyFormats());
        $t->same(['chunkedhtml', 'epub2', 'epub3', 'icml', 'opendocument', 'pdf'], PandocFormatRegistry::richPackageOutputOnlyFormats());
        $t->same(['docx', 'epub', 'ipynb', 'odt'], $packet['partialInputFormats']);
        $t->same([], $packet['partialOutputFormats']);
        $t->same(['pptx', 'xlsx'], $packet['unsupportedInputFormats']);
        $t->same([
            'chunkedhtml',
            'docx',
            'epub',
            'epub2',
            'epub3',
            'icml',
            'ipynb',
            'odt',
            'opendocument',
            'pdf',
            'pptx',
        ], $packet['unsupportedOutputFormats']);
        $t->same([
            'docx',
            'epub',
            'ipynb',
            'odt',
            'pptx',
            'xlsx',
            'chunkedhtml',
            'epub2',
            'epub3',
            'icml',
            'opendocument',
            'pdf',
        ], array_keys($packet['formats']));

        $t->same([
            'input' => true,
            'output' => true,
            'direction' => 'input-output',
            'inputStatus' => 'partial',
            'outputStatus' => 'unsupported',
            'extensionInferred' => true,
            'extensions' => ['.docx'],
            'inputImplementation' => DocxReader::class,
            'outputImplementation' => '',
        ], $packet['formats']['docx']);
        $t->same(['.epub'], $packet['formats']['epub']['extensions']);
        $t->same(['.epub'], $packet['formats']['epub2']['extensions']);
        $t->same(['.epub'], $packet['formats']['epub3']['extensions']);
        $t->same('partial', $packet['formats']['epub']['inputStatus']);
        $t->same(EpubReader::class, $packet['formats']['epub']['inputImplementation']);
        $t->same('partial', $packet['formats']['odt']['inputStatus']);
        $t->same(OdtReader::class, $packet['formats']['odt']['inputImplementation']);
        $t->same(true, $packet['formats']['opendocument']['extensionInferred']);
        $t->same(['.fodt'], $packet['formats']['opendocument']['extensions']);
        $t->same(false, $packet['formats']['chunkedhtml']['extensionInferred']);
        $t->same([], $packet['formats']['chunkedhtml']['extensions']);
        $t->same('partial', $packet['formats']['ipynb']['inputStatus']);
        $t->same('unsupported', $packet['formats']['ipynb']['outputStatus']);
        $t->same(IpynbReader::class, $packet['formats']['ipynb']['inputImplementation']);
        $t->same('', $packet['formats']['ipynb']['outputImplementation']);
        $t->same('input-only', $packet['formats']['xlsx']['direction']);
        $t->same('not-applicable', $packet['formats']['xlsx']['outputStatus']);
        $t->same('output-only', $packet['formats']['pdf']['direction']);
        $t->same('not-applicable', $packet['formats']['pdf']['inputStatus']);
        $t->same('unsupported', $packet['formats']['pdf']['outputStatus']);
    },
    'summarizes rich package unsupported format surfaces without converter claims' => static function (TestRunner $t): void {
        $summary = PandocFormatRegistry::richPackageUnsupportedFormatSummary();
        $packet = PandocFormatRegistry::richPackageFormatReviewPacket();
        $directions = PandocFormatRegistry::richPackageFormatDirections();

        $t->same($summary, $packet['unsupportedFormatSummary']);
        $t->same([
            'docx',
            'epub',
            'ipynb',
            'odt',
            'pptx',
            'xlsx',
            'chunkedhtml',
            'epub2',
            'epub3',
            'icml',
            'opendocument',
            'pdf',
        ], $summary['anyUnsupported']);
        $t->same(['pptx'], $summary['unsupportedBoth']);
        $t->same(['docx', 'epub', 'ipynb', 'odt'], $summary['partialInputUnsupportedOutput']);
        $t->same(['xlsx'], $summary['unsupportedInputOnly']);
        $t->same(['chunkedhtml', 'epub2', 'epub3', 'icml', 'opendocument', 'pdf'], $summary['unsupportedOutputOnly']);
        $t->same(PandocFormatRegistry::unsupportedRichPackageInputFormats(), $summary['noNativeReader']);
        $t->same(PandocFormatRegistry::unsupportedRichPackageOutputFormats(), $summary['noNativeWriter']);

        foreach ($summary['anyUnsupported'] as $format) {
            $direction = $directions[$format];
            $t->same(true, $direction['inputStatus'] === 'unsupported' || $direction['outputStatus'] === 'unsupported', "Rich package {$format} should have an unsupported surface");
        }

        foreach ($summary['partialInputUnsupportedOutput'] as $format) {
            $review = $packet['formats'][$format];
            $t->same('partial', $review['inputStatus'], "Rich package {$format} should keep partial native input accounting");
            $t->same('unsupported', $review['outputStatus'], "Rich package {$format} should keep unsupported output accounting");
            $t->same(false, $review['inputImplementation'] === '', "Rich package {$format} should keep its existing partial reader class");
            $t->same('', $review['outputImplementation'], "Rich package {$format} must not register a native writer");
        }

        foreach ($summary['unsupportedBoth'] as $format) {
            $review = $packet['formats'][$format];
            $t->same('unsupported', $review['inputStatus'], "Rich package {$format} should not claim native reader parity");
            $t->same('unsupported', $review['outputStatus'], "Rich package {$format} should not claim native writer parity");
            $t->same('', $review['inputImplementation']);
            $t->same('', $review['outputImplementation']);
        }

        $xlsx = $packet['formats']['xlsx'];
        $t->same('input-only', $xlsx['direction']);
        $t->same('unsupported', $xlsx['inputStatus']);
        $t->same('not-applicable', $xlsx['outputStatus']);
        $t->same('', $xlsx['inputImplementation']);

        foreach ($summary['unsupportedOutputOnly'] as $format) {
            $review = $packet['formats'][$format];
            $t->same('output-only', $review['direction'], "Rich package {$format} should remain output-only");
            $t->same('not-applicable', $review['inputStatus'], "Rich package {$format} should not appear as an input token");
            $t->same('unsupported', $review['outputStatus'], "Rich package {$format} should keep unsupported output accounting");
            $t->same('', $review['outputImplementation'], "Rich package {$format} must not register a native writer");
        }
    },
    'builds rich package unsupported-format packets without external converter claims' => static function (TestRunner $t): void {
        $packet = PandocFormatRegistry::richPackageUnsupportedFormatReviewPacket();

        $t->same('2026-06-03', $packet['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $packet['upstreamManualUrl']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $packet['upstreamSourceCommit']);
        $t->same(PandocFormatRegistry::richPackageUnsupportedFormatSummary(), $packet['unsupportedFormatSummary']);
        $t->same([
            'pptx',
            'xlsx',
        ], $packet['unsupportedInputFormats']);
        $t->same([
            'chunkedhtml',
            'docx',
            'epub',
            'epub2',
            'epub3',
            'icml',
            'ipynb',
            'odt',
            'opendocument',
            'pdf',
            'pptx',
        ], $packet['unsupportedOutputFormats']);
        $t->same([
            'docx',
            'epub',
            'ipynb',
            'odt',
            'pptx',
            'xlsx',
            'chunkedhtml',
            'epub2',
            'epub3',
            'icml',
            'opendocument',
            'pdf',
        ], $packet['unsupportedFormats']);
        $t->same(12, $packet['unsupportedFormatCount']);
        $t->same(2, $packet['inputUnsupportedCount']);
        $t->same(11, $packet['outputUnsupportedCount']);

        $docx = $packet['formats']['docx'];
        $t->same(true, $docx['input']);
        $t->same(true, $docx['output']);
        $t->same('input-output', $docx['direction']);
        $t->same(['output'], $docx['unsupportedDirections']);
        $t->same('partial', $docx['inputStatus']);
        $t->same('unsupported', $docx['outputStatus']);
        $t->same(DocxReader::class, $docx['inputImplementation']);
        $t->same('', $docx['outputImplementation']);
        $t->contains('DOCX package import slices', $docx['inputNotes']);
        $t->contains('No native PHP reader or writer is registered', $docx['outputNotes']);

        $ipynb = $packet['formats']['ipynb'];
        $t->same(['output'], $ipynb['unsupportedDirections']);
        $t->same('partial', $ipynb['inputStatus']);
        $t->same('unsupported', $ipynb['outputStatus']);
        $t->same(IpynbReader::class, $ipynb['inputImplementation']);
        $t->same('', $ipynb['outputImplementation']);
        $t->contains('notebook reader maps Markdown/code/raw cells', $ipynb['inputNotes']);
        $t->contains('No native PHP reader or writer is registered', $ipynb['outputNotes']);

        $xlsx = $packet['formats']['xlsx'];
        $t->same('input-only', $xlsx['direction']);
        $t->same(['input'], $xlsx['unsupportedDirections']);
        $t->same('unsupported', $xlsx['inputStatus']);
        $t->same('not-applicable', $xlsx['outputStatus']);
        $t->same('', $xlsx['outputNotes']);

        $pdf = $packet['formats']['pdf'];
        $t->same('output-only', $pdf['direction']);
        $t->same(['output'], $pdf['unsupportedDirections']);
        $t->same('not-applicable', $pdf['inputStatus']);
        $t->same('unsupported', $pdf['outputStatus']);
        $t->same('', $pdf['inputNotes']);
        $t->contains('No native PHP reader or writer is registered', $pdf['outputNotes']);

        foreach ($packet['formats'] as $format => $review) {
            $t->same(true, $review['externalToolFree'], "Rich package unsupported-format packet {$format} must stay native PHP only");
            $t->same(false, $review['unsupportedDirections'] === [], "Rich package unsupported-format packet {$format} must expose an unsupported direction");
            if (in_array('input', $review['unsupportedDirections'], true)) {
                $t->same('', $review['inputImplementation'], "Rich package unsupported input {$format} must not register a reader implementation");
            }
            if (in_array('output', $review['unsupportedDirections'], true)) {
                $t->same('', $review['outputImplementation'], "Rich package unsupported output {$format} must not register a writer implementation");
            }
        }
    },
    'tracks tabular data reader option registry metadata without full reader parity claims' => static function (TestRunner $t): void {
        $t->same(['csv', 'tsv'], PandocFormatRegistry::tabularDataInputFormats());
        $t->same([], PandocFormatRegistry::tabularDataOutputFormats());
        $t->same([
            '.csv' => 'csv',
            '.tsv' => 'tsv',
        ], PandocFormatRegistry::tabularDataExtensionInference());

        $t->same('csv', PandocFormatRegistry::inferTabularDataFormatFromExtension('.CSV'));
        $t->same('tsv', PandocFormatRegistry::inferTabularDataFormatFromExtension('tsv'));
        $t->same(null, PandocFormatRegistry::inferTabularDataFormatFromExtension(''));
        $t->same(null, PandocFormatRegistry::inferTabularDataFormatFromExtension('.ods'));
        $t->same(['csv', 'tsv'], PandocFormatRegistry::tabularDataFormatsWithExtensionInference());
        $t->same([], PandocFormatRegistry::tabularDataFormatsWithoutExtensionInference());
        $t->same([
            'delimiter',
            'delimiterName',
            'quote',
            'keepSpace',
            'escape',
            'firstRowHeader',
            'emptyInputPolicy',
            'multilineCellPolicy',
            'readerOptionsUsed',
        ], PandocFormatRegistry::tabularDataReaderOptionProfileOrder());

        $directions = PandocFormatRegistry::tabularDataFormatDirections();
        $t->same(['csv', 'tsv'], array_keys($directions));
        $t->same([], PandocFormatRegistry::tabularDataBidirectionalFormats());
        $t->same(['csv', 'tsv'], PandocFormatRegistry::tabularDataInputOnlyFormats());
        $t->same([], PandocFormatRegistry::tabularDataOutputOnlyFormats());
        $t->same([
            'inputOutput' => 0,
            'inputOnly' => 2,
            'outputOnly' => 0,
        ], PandocFormatRegistry::tabularDataDirectionBucketCounts());
        foreach ($directions as $format => $direction) {
            $t->same(true, $direction['input'], "Tabular data {$format} should remain an input token");
            $t->same(false, $direction['output'], "Tabular data {$format} should not be reported as an output token");
            $t->same('input-only', $direction['direction']);
            $t->same('partial', $direction['inputStatus']);
            $t->same('not-applicable', $direction['outputStatus']);
        }

        $profiles = PandocFormatRegistry::tabularDataReaderOptionProfiles();
        $t->same([
            'delimiter' => ',',
            'delimiterName' => 'comma',
            'quote' => '"',
            'keepSpace' => false,
            'escape' => null,
            'firstRowHeader' => true,
            'emptyInputPolicy' => 'empty-document',
            'multilineCellPolicy' => 'linebreak-separated-plain-blocks',
            'readerOptionsUsed' => false,
        ], $profiles['csv']);
        $t->same([
            'delimiter' => "\t",
            'delimiterName' => 'tab',
            'quote' => null,
            'keepSpace' => false,
            'escape' => null,
            'firstRowHeader' => true,
            'emptyInputPolicy' => 'empty-document',
            'multilineCellPolicy' => 'linebreak-separated-plain-blocks',
            'readerOptionsUsed' => false,
        ], $profiles['tsv']);

        $inputSupport = PandocFormatRegistry::tabularDataInputSupport();
        foreach ($inputSupport as $format => $support) {
            $t->same('partial', $support['status'], "Tabular data {$format} should expose the bounded native reader without full parity");
            $t->same(DelimitedTextReader::class, $support['implementation']);
            $t->contains('full Pandoc', $support['notes']);
        }

        $provenance = PandocFormatRegistry::tabularDataInputSourceProvenance();
        $t->same([
            'module' => 'Text.Pandoc.Readers.CSV',
            'function' => 'readCSV',
            'registry' => '("csv"          , TextReader readCSV)',
            'csvOptions' => 'defaultCSVOptions',
            'readerOptions' => 'ignored by readCSV',
        ], $provenance['csv']);
        $t->same([
            'module' => 'Text.Pandoc.Readers.CSV',
            'function' => 'readTSV',
            'registry' => '("tsv"          , TextReader readTSV)',
            'csvOptions' => 'CSVOptions with tab delimiter, no quote, no escape, keepSpace false',
            'readerOptions' => 'ignored by readTSV',
        ], $provenance['tsv']);
        $t->same([
            'explicit-format' => [
                'priority' => 0,
                'sourceFields' => ['format'],
                'selectedFormatPolicy' => 'honor-explicit-format-token',
                'conflictPolicy' => 'wins-over-extension-and-content-row-profile',
                'diagnosticCode' => 'tabular-data-explicit-format-extension-conflict',
            ],
            'extension' => [
                'priority' => 1,
                'sourceFields' => ['extension', 'sourcePath'],
                'selectedFormatPolicy' => 'infer-from-normalized-extension',
                'conflictPolicy' => 'loses-when-explicit-format-conflicts',
                'diagnosticCode' => null,
            ],
            'content-row-profile' => [
                'priority' => 2,
                'sourceFields' => ['content'],
                'selectedFormatPolicy' => 'infer-from-row-delimiter-scores',
                'conflictPolicy' => 'loses-when-explicit-format-or-extension-is-selected',
                'diagnosticCode' => null,
            ],
        ], PandocFormatRegistry::tabularDataFormatInferenceBuckets());

        $t->same([], PandocFormatRegistry::unsupportedTabularDataInputFormats());
        $t->same([], PandocFormatRegistry::unsupportedTabularDataOutputFormats());
        $t->same(28, count(PandocFormatRegistry::unsupportedInputFormats()));
        $t->same(61, count(PandocFormatRegistry::unsupportedOutputFormats()));
    },
    'builds tabular data review packets from option profiles without full converter claims' => static function (TestRunner $t): void {
        $packet = PandocFormatRegistry::tabularDataFormatReviewPacket();

        $t->same(2, $packet['reviewPacketVersion']);
        $t->same('pandoc-tabular-data-registry-options', $packet['reviewPacketKind']);
        $t->same(false, $packet['fullReaderParity']);
        $t->same(true, $packet['externalToolFree']);
        $t->same('2026-06-03', $packet['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $packet['upstreamManualUrl']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $packet['upstreamSourceCommit']);
        $t->same(['csv', 'tsv'], $packet['inputFormats']);
        $t->same([], $packet['outputFormats']);
        $t->same([
            'inputOutput' => [],
            'inputOnly' => ['csv', 'tsv'],
            'outputOnly' => [],
        ], $packet['directionBuckets']);
        $t->same([
            'inputOutput' => 0,
            'inputOnly' => 2,
            'outputOnly' => 0,
        ], $packet['directionBucketCounts']);
        $t->same([
            '.csv' => 'csv',
            '.tsv' => 'tsv',
        ], $packet['extensionInference']);
        $t->same(['csv', 'tsv'], $packet['extensionInferredFormats']);
        $t->same([], $packet['nonExtensionInferredFormats']);
        $t->same([
            'inputOutput' => 0,
            'inputOnly' => 2,
            'outputOnly' => 0,
            'readerOnly' => 2,
            'writerOnly' => 0,
            'registeredReaders' => 2,
            'registeredWriters' => 0,
        ], $packet['directionCounts']);
        $t->same(PandocFormatRegistry::tabularDataReaderOptionProfileOrder(), $packet['readerOptionProfileOrder']);
        $t->same(PandocFormatRegistry::tabularDataReaderOptionProfiles(), $packet['readerOptionProfiles']);
        $t->same(PandocFormatRegistry::tabularDataInputSourceProvenance(), $packet['inputSourceProvenance']);
        $t->same(PandocFormatRegistry::tabularDataFormatInferenceBuckets(), $packet['formatInferenceBuckets']);
        $t->same(PandocFormatRegistry::tabularDataSourceProvenanceBuckets(), $packet['sourceProvenanceBuckets']);
        $t->same(PandocFormatRegistry::tabularDataOptionConflictProfiles(), $packet['optionConflictProfiles']);
        $t->same(PandocFormatRegistry::tabularDataInputSourceProvenanceBuckets(), $packet['inputSourceProvenanceBuckets']);
        $t->same(PandocFormatRegistry::tabularDataExplicitExtensionConflictMatrix(), $packet['explicitExtensionConflictMatrix']);
        $t->same(PandocFormatRegistry::tabularDataUnsupportedWriterReasons(), $packet['unsupportedWriterReasons']);
        $t->same(PandocFormatRegistry::tabularDataDialectProfileReviewPackets(), $packet['dialectProfileReviewPackets']);
        $t->same([], $packet['unsupportedInputFormats']);
        $t->same([], $packet['unsupportedOutputFormats']);
        $t->same([
            'anyUnsupported' => [],
            'unsupportedBoth' => [],
            'unsupportedInputOnly' => [],
            'unsupportedOutputOnly' => [],
            'noNativeReader' => [],
            'noNativeWriter' => [],
        ], $packet['unsupportedFormatSummary']);

        $t->same(['csv', 'tsv'], array_keys($packet['formats']));
        $t->same([
            'input' => true,
            'output' => false,
            'direction' => 'input-only',
            'directionBucket' => 'input-only',
            'inputStatus' => 'partial',
            'outputStatus' => 'not-applicable',
            'extensionInferred' => true,
            'extensions' => ['.csv'],
            'explicitFormatToken' => 'csv',
            'inputImplementation' => DelimitedTextReader::class,
            'outputImplementation' => '',
            'readerOptionProfileOrder' => $packet['readerOptionProfileOrder'],
            'readerOptions' => $packet['readerOptionProfiles']['csv'],
            'sourceProvenance' => $packet['inputSourceProvenance']['csv'],
            'sourceProvenanceBucket' => $packet['inputSourceProvenanceBuckets']['csv'],
            'fullReaderParity' => false,
            'externalToolFree' => true,
            'unsupportedWriterReason' => $packet['unsupportedWriterReasons']['csv'],
            'extensionConflictProbes' => $packet['explicitExtensionConflictMatrix']['csv'],
        ], $packet['formats']['csv']);
        $t->same(['.tsv'], $packet['formats']['tsv']['extensions']);
        $t->same("\t", $packet['formats']['tsv']['readerOptions']['delimiter']);
        $t->same(null, $packet['formats']['tsv']['readerOptions']['quote']);
        $t->same(false, $packet['formats']['tsv']['readerOptions']['readerOptionsUsed']);

        foreach ($packet['formats'] as $format => $review) {
            $t->same(DelimitedTextReader::class, $review['inputImplementation'], "Tabular data review packet {$format} must expose the bounded input implementation");
            $t->same('', $review['outputImplementation'], "Tabular data review packet {$format} must not register an output implementation");
            $t->same(true, $review['readerOptions']['firstRowHeader'], "Tabular data {$format} should expose first-row header behavior");
            $t->same('Text.Pandoc.Readers.CSV', $review['sourceProvenance']['module']);
            $t->same($packet['inputSourceProvenanceBuckets'][$format]['bucket'], $review['sourceProvenanceBucket']['bucket']);
            $t->same(false, $review['fullReaderParity']);
            $t->same(true, $review['externalToolFree']);
            $t->same('no-upstream-tabular-writer-token', $review['unsupportedWriterReason']['reasonCode']);
        }
    },
    'builds generated tabular dialect profile review packets with stable conflict provenance' => static function (TestRunner $t): void {
        $profiles = PandocFormatRegistry::tabularDataReaderOptionProfiles();
        $buckets = PandocFormatRegistry::tabularDataInputSourceProvenanceBuckets();
        $writerReasons = PandocFormatRegistry::tabularDataUnsupportedWriterReasons();
        $conflicts = PandocFormatRegistry::tabularDataExplicitExtensionConflictMatrix();
        $packets = PandocFormatRegistry::tabularDataDialectProfileReviewPackets();

        $t->same([
            'csv' => [
                'bucket' => 'Text.Pandoc.Readers.CSV::readCSV',
                'module' => 'Text.Pandoc.Readers.CSV',
                'function' => 'readCSV',
                'registry' => '("csv"          , TextReader readCSV)',
                'formats' => ['csv'],
            ],
            'tsv' => [
                'bucket' => 'Text.Pandoc.Readers.CSV::readTSV',
                'module' => 'Text.Pandoc.Readers.CSV',
                'function' => 'readTSV',
                'registry' => '("tsv"          , TextReader readTSV)',
                'formats' => ['tsv'],
            ],
        ], $buckets);
        $t->same([
            'inputOutput' => 0,
            'inputOnly' => 2,
            'outputOnly' => 0,
            'readerOnly' => 2,
            'writerOnly' => 0,
            'registeredReaders' => 2,
            'registeredWriters' => 0,
        ], PandocFormatRegistry::tabularDataDirectionCounts());
        $t->same([
            'status' => 'unsupported',
            'reasonCode' => 'no-upstream-tabular-writer-token',
            'reason' => 'Pandoc does not list csv as an output format; the native registry keeps csv reader-only.',
            'upstreamOutputToken' => false,
            'nativeWriterRegistered' => false,
            'implementation' => '',
            'outputStatus' => 'not-applicable',
        ], $writerReasons['csv']);
        $t->same('no-upstream-tabular-writer-token', $writerReasons['tsv']['reasonCode']);
        $t->contains('keeps tsv reader-only', $writerReasons['tsv']['reason']);

        $t->same(['.csv', '.tsv'], array_keys($conflicts['csv']));
        $t->same([
            'extension' => '.csv',
            'explicitFormat' => 'csv',
            'extensionInferredFormat' => 'csv',
            'conflict' => false,
            'selectedFormat' => 'csv',
            'rejectedInferredFormat' => null,
            'resolution' => 'explicit-format-matches-extension',
            'selectedSourceBucket' => 'Text.Pandoc.Readers.CSV::readCSV',
            'inferredSourceBucket' => 'Text.Pandoc.Readers.CSV::readCSV',
            'selectedDelimiterName' => 'comma',
        ], $conflicts['csv']['.csv']);
        $t->same([
            'extension' => '.tsv',
            'explicitFormat' => 'csv',
            'extensionInferredFormat' => 'tsv',
            'conflict' => true,
            'selectedFormat' => 'csv',
            'rejectedInferredFormat' => 'tsv',
            'resolution' => 'explicit-format-wins',
            'selectedSourceBucket' => 'Text.Pandoc.Readers.CSV::readCSV',
            'inferredSourceBucket' => 'Text.Pandoc.Readers.CSV::readTSV',
            'selectedDelimiterName' => 'comma',
        ], $conflicts['csv']['.tsv']);
        $t->same('explicit-format-wins', $conflicts['tsv']['.csv']['resolution']);
        $t->same('tab', $conflicts['tsv']['.csv']['selectedDelimiterName']);

        $t->same(['csv', 'tsv'], array_keys($packets));
        $t->same('pandoc-csv-reader-default', $packets['csv']['dialectProfileId']);
        $t->same('csv', $packets['csv']['explicitFormat']);
        $t->same(true, $packets['csv']['inputOnly']);
        $t->same(true, $packets['csv']['readerOnly']);
        $t->same(['.csv'], $packets['csv']['extensions']);
        $t->same(PandocFormatRegistry::tabularDataReaderOptionProfileOrder(), $packets['csv']['readerOptionProfileOrder']);
        $t->same($profiles['csv'], $packets['csv']['readerOptions']);
        $t->same($buckets['csv'], $packets['csv']['sourceProvenanceBucket']);
        $t->same($writerReasons['csv'], $packets['csv']['unsupportedWriterReason']);
        $t->same($conflicts['csv'], $packets['csv']['extensionConflictProbes']);
        $t->same('pandoc-tsv-reader-default', $packets['tsv']['dialectProfileId']);
        $t->same("\t", $packets['tsv']['readerOptions']['delimiter']);
        $t->same(null, $packets['tsv']['readerOptions']['quote']);

        $packet = PandocFormatRegistry::tabularDataFormatReviewPacket();
        $t->same($packets, $packet['dialectProfileReviewPackets']);
        $t->same($conflicts, $packet['explicitExtensionConflictMatrix']);
        $t->same($writerReasons, $packet['unsupportedWriterReasons']);
        foreach ($packets as $format => $review) {
            $t->same($packet['formats'][$format]['sourceProvenanceBucket']['bucket'], $review['sourceProvenanceBucket']['bucket']);
            $t->same($packet['formats'][$format]['extensionConflictProbes'], $review['extensionConflictProbes']);
            $t->same('', $review['unsupportedWriterReason']['implementation']);
            $t->same(false, $review['unsupportedWriterReason']['nativeWriterRegistered']);
        }
    },
    'buckets tabular data source provenance by upstream reader module and direction' => static function (TestRunner $t): void {
        $buckets = PandocFormatRegistry::tabularDataSourceProvenanceBuckets();

        $t->same(['Text.Pandoc.Readers.CSV'], array_keys($buckets));
        $t->same([
            'module' => 'Text.Pandoc.Readers.CSV',
            'formats' => ['csv', 'tsv'],
            'functions' => ['readCSV', 'readTSV'],
            'registryEntries' => [
                '("csv"          , TextReader readCSV)',
                '("tsv"          , TextReader readTSV)',
            ],
            'csvOptions' => [
                'defaultCSVOptions',
                'CSVOptions with tab delimiter, no quote, no escape, keepSpace false',
            ],
            'readerOptions' => ['ignored by readCSV', 'ignored by readTSV'],
            'directionBuckets' => [
                'inputOutput' => [],
                'inputOnly' => ['csv', 'tsv'],
                'outputOnly' => [],
            ],
            'inputImplementations' => [DelimitedTextReader::class],
            'outputImplementations' => [],
        ], $buckets['Text.Pandoc.Readers.CSV']);
    },
    'tracks csv tsv explicit extension conflict provenance without parser changes' => static function (TestRunner $t): void {
        $conflicts = PandocFormatRegistry::tabularDataOptionConflictProfiles();

        $t->same(['explicit-csv-extension-tsv', 'explicit-tsv-extension-csv'], array_keys($conflicts));
        $csvOverTsv = $conflicts['explicit-csv-extension-tsv'];
        $t->same('csv', $csvOverTsv['explicitFormat']);
        $t->same('.tsv', $csvOverTsv['extension']);
        $t->same('tsv', $csvOverTsv['extensionFormat']);
        $t->same('csv', $csvOverTsv['selectedFormat']);
        $t->same('tsv', $csvOverTsv['rejectedFormat']);
        $t->same('explicit-format', $csvOverTsv['selectedSourceBucket']);
        $t->same('extension', $csvOverTsv['rejectedSourceBucket']);
        $t->same('explicit-format-wins', $csvOverTsv['resolution']);
        $t->same('tabular-data-explicit-format-extension-conflict', $csvOverTsv['diagnosticCode']);
        $t->same(['delimiter', 'delimiterName', 'quote'], $csvOverTsv['optionConflictFields']);
        $t->same(',', $csvOverTsv['selectedReaderOptions']['delimiter']);
        $t->same('"', $csvOverTsv['selectedReaderOptions']['quote']);
        $t->same("\t", $csvOverTsv['rejectedReaderOptions']['delimiter']);
        $t->same(null, $csvOverTsv['rejectedReaderOptions']['quote']);
        $t->same('readCSV', $csvOverTsv['selectedSourceProvenance']['function']);
        $t->same('readTSV', $csvOverTsv['rejectedSourceProvenance']['function']);
        $t->same(false, $csvOverTsv['parserBehaviorChanged']);
        $t->same(true, $csvOverTsv['externalToolFree']);

        $tsvOverCsv = $conflicts['explicit-tsv-extension-csv'];
        $t->same('tsv', $tsvOverCsv['selectedFormat']);
        $t->same('csv', $tsvOverCsv['rejectedFormat']);
        $t->same("\t", $tsvOverCsv['selectedReaderOptions']['delimiter']);
        $t->same(',', $tsvOverCsv['rejectedReaderOptions']['delimiter']);
    },
    'classifies tabular data extension surfaces from option profiles without output claims' => static function (TestRunner $t): void {
        $csv = PandocFormatRegistry::tabularDataUnsupportedFormatForExtension('CSV');
        $t->same([
            'extension' => '.csv',
            'format' => 'csv',
            'input' => true,
            'output' => false,
            'direction' => 'input-only',
            'inputStatus' => 'partial',
            'outputStatus' => 'not-applicable',
            'unsupportedInput' => false,
            'unsupportedOutput' => false,
            'inputImplementation' => DelimitedTextReader::class,
            'outputImplementation' => '',
            'delimiter' => ',',
            'delimiterName' => 'comma',
            'quote' => '"',
            'keepSpace' => false,
            'escape' => null,
            'firstRowHeader' => true,
            'emptyInputPolicy' => 'empty-document',
            'multilineCellPolicy' => 'linebreak-separated-plain-blocks',
            'readerOptionsUsed' => false,
            'sourceModule' => 'Text.Pandoc.Readers.CSV',
            'sourceFunction' => 'readCSV',
        ], $csv);

        $tsv = PandocFormatRegistry::tabularDataUnsupportedFormatForExtension('.tsv');
        $t->same('.tsv', $tsv['extension']);
        $t->same('tsv', $tsv['format']);
        $t->same("\t", $tsv['delimiter']);
        $t->same('tab', $tsv['delimiterName']);
        $t->same(null, $tsv['quote']);
        $t->same('readTSV', $tsv['sourceFunction']);
        $t->same(false, $tsv['unsupportedInput']);
        $t->same(false, $tsv['unsupportedOutput']);
        $t->same(null, PandocFormatRegistry::tabularDataUnsupportedFormatForExtension(''));
        $t->same(null, PandocFormatRegistry::tabularDataUnsupportedFormatForExtension('.xlsx'));
    },
    'tracks wiki format input output direction buckets without direct parity claims' => static function (TestRunner $t): void {
        $directions = PandocFormatRegistry::wikiFormatDirections();
        $inputFormats = PandocFormatRegistry::wikiInputFormats();
        $outputFormats = PandocFormatRegistry::wikiOutputFormats();

        $t->same([
            'creole',
            'dokuwiki',
            'jira',
            'mediawiki',
            'tikiwiki',
            'twiki',
            'vimwiki',
            'xwiki',
            'zimwiki',
        ], array_keys($directions));
        $t->same([
            'dokuwiki',
            'jira',
            'mediawiki',
        ], PandocFormatRegistry::wikiBidirectionalFormats());
        $t->same([
            'creole',
            'tikiwiki',
            'twiki',
            'vimwiki',
        ], PandocFormatRegistry::wikiInputOnlyFormats());
        $t->same([
            'xwiki',
            'zimwiki',
        ], PandocFormatRegistry::wikiOutputOnlyFormats());
        $t->same(9, count($directions));

        foreach ($directions as $format => $direction) {
            $hasInput = in_array($format, $inputFormats, true);
            $hasOutput = in_array($format, $outputFormats, true);
            $expectedDirection = $hasInput && $hasOutput ? 'input-output' : ($hasInput ? 'input-only' : 'output-only');

            $t->same($hasInput, $direction['input'], "Wiki format {$format} input direction mismatch");
            $t->same($hasOutput, $direction['output'], "Wiki format {$format} output direction mismatch");
            $t->same($expectedDirection, $direction['direction'], "Wiki format {$format} direction bucket mismatch");
            $t->same($hasInput ? 'unsupported' : 'not-applicable', $direction['inputStatus'], "Wiki format {$format} input status mismatch");
            $t->same($hasOutput ? 'unsupported' : 'not-applicable', $direction['outputStatus'], "Wiki format {$format} output status mismatch");
        }
    },
    'tracks wiki file extension inference without direct parity claims' => static function (TestRunner $t): void {
        $t->same([
            '.dokuwiki' => 'dokuwiki',
            '.wiki' => 'mediawiki',
        ], PandocFormatRegistry::wikiExtensionInference());

        $t->same('dokuwiki', PandocFormatRegistry::inferWikiFormatFromExtension('.dokuwiki'));
        $t->same('dokuwiki', PandocFormatRegistry::inferWikiFormatFromExtension('DOKUWIKI'));
        $t->same('mediawiki', PandocFormatRegistry::inferWikiFormatFromExtension('.wiki'));
        $t->same('mediawiki', PandocFormatRegistry::inferWikiFormatFromExtension('WIKI'));
        $t->same(null, PandocFormatRegistry::inferWikiFormatFromExtension(''));
        $t->same(null, PandocFormatRegistry::inferWikiFormatFromExtension('.xwiki'));
        $t->same(null, PandocFormatRegistry::inferWikiFormatFromExtension('.zimwiki'));

        $t->same([
            'dokuwiki',
            'mediawiki',
        ], PandocFormatRegistry::wikiFormatsWithExtensionInference());
        $t->same([
            'creole',
            'jira',
            'tikiwiki',
            'twiki',
            'vimwiki',
            'xwiki',
            'zimwiki',
        ], PandocFormatRegistry::wikiFormatsWithoutExtensionInference());

        $directions = PandocFormatRegistry::wikiFormatDirections();
        foreach (PandocFormatRegistry::wikiFormatsWithExtensionInference() as $format) {
            $t->same(true, array_key_exists($format, $directions), "Wiki extension-inferred format {$format} must remain in direction accounting");
            $t->same('unsupported', $directions[$format]['inputStatus'], "Wiki extension-inferred format {$format} must not claim input parity");
            $t->same('unsupported', $directions[$format]['outputStatus'], "Wiki extension-inferred format {$format} must not claim output parity");
        }

        $extensionInference = array_flip(PandocFormatRegistry::wikiExtensionInference());
        foreach (PandocFormatRegistry::wikiFormatsWithoutExtensionInference() as $format) {
            $t->same(false, array_key_exists($format, $extensionInference), "Wiki format {$format} should not be file-extension inferred");
        }
    },
    'records remaining wiki input extension alias statuses without parser claims' => static function (TestRunner $t): void {
        $expectedAliases = [
            '.creole' => 'creole',
            '.jira' => 'jira',
            '.mediawiki' => 'mediawiki',
            '.tikiwiki' => 'tikiwiki',
            '.twiki' => 'twiki',
            '.vimwiki' => 'vimwiki',
        ];
        $reason = 'Upstream wiki reader coverage is inventoried, but no native PHP wiki reader is registered for this format.';
        $packet = PandocFormatRegistry::wikiInputExtensionAliasStatusPacket();
        $mediawiki = PandocFormatRegistry::wikiInputExtensionAliasStatus('MEDIAWIKI');
        $vimwiki = PandocFormatRegistry::wikiInputExtensionAliasStatus('.VimWiki');

        $t->same($expectedAliases, PandocFormatRegistry::wikiInputExtensionStatusAliases());
        $t->same(false, array_key_exists('.dokuwiki', $packet['extensionAliases']));
        $t->same(false, array_key_exists('.wiki', $packet['extensionAliases']));
        $t->same([
            '.dokuwiki' => 'dokuwiki',
            '.wiki' => 'mediawiki',
        ], $packet['upstreamExtensionInference']);
        $t->same('wiki-input-extension-alias-status', $packet['family']);
        $t->same(PandocFormatRegistry::wikiInputFormats(), $packet['inputFormats']);
        $t->same($expectedAliases, $packet['extensionAliases']);
        $t->same('unsupported', $packet['unsupportedVerdict']);
        $t->same(array_keys($expectedAliases), $packet['unsupportedExtensionAliases']);
        $t->same(6, $packet['unsupportedAliasCount']);
        $t->same(false, $packet['directReaderParitySupported']);
        $t->same(true, $packet['externalToolFree']);
        $t->same(0, $packet['registeredNativeImplementationCount']);
        $t->same([], $packet['nativeImplementationRecords']);
        $t->same(array_keys($expectedAliases), array_keys($packet['aliases']));

        foreach ($expectedAliases as $alias => $format) {
            $status = $packet['aliases'][$alias];
            $t->same($alias, $status['extension']);
            $t->same($format, $status['format']);
            $t->same('wiki-input-extension-status-alias', $status['aliasKind']);
            $t->same(false, $status['upstreamExtensionInferred'], "Wiki alias {$alias} must not change upstream extension inference");
            $t->same('unsupported', $status['inputStatus']);
            $t->same('unsupported', $status['verdict']);
            $t->same('wiki-reader-not-ported', $status['reasonCode']);
            $t->same($reason, $status['reason']);
            $t->same('', $status['inputImplementation'], "Wiki alias {$alias} must not register a native reader");
            $t->same('', $status['outputImplementation'], "Wiki alias {$alias} must not register a native writer");
            $t->same([], $status['nativeImplementationRecords']);
            $t->same(false, $status['directReaderParitySupported']);
            $t->same(true, $status['externalToolFree']);
        }

        $t->same([
            'extension' => '.mediawiki',
            'format' => 'mediawiki',
            'label' => 'MediaWiki',
            'family' => 'wiki',
            'aliasKind' => 'wiki-input-extension-status-alias',
            'upstreamExtensionInferred' => false,
            'input' => true,
            'output' => true,
            'direction' => 'input-output',
            'inputStatus' => 'unsupported',
            'outputStatus' => 'unsupported',
            'verdict' => 'unsupported',
            'reasonCode' => 'wiki-reader-not-ported',
            'reason' => $reason,
            'unsupportedReason' => [
                'family' => 'wiki',
                'format' => 'mediawiki',
                'alias' => '.mediawiki',
                'direction' => 'input',
                'status' => 'unsupported',
                'reasonCode' => 'wiki-reader-not-ported',
                'reason' => $reason,
            ],
            'serializedReason' => '{"family":"wiki","format":"mediawiki","alias":".mediawiki","direction":"input","status":"unsupported","reasonCode":"wiki-reader-not-ported","reason":"Upstream wiki reader coverage is inventoried, but no native PHP wiki reader is registered for this format."}',
            'inputImplementation' => '',
            'outputImplementation' => '',
            'nativeImplementationRecords' => [],
            'directReaderParitySupported' => false,
            'externalToolFree' => true,
        ], $mediawiki);
        $t->same([
            'family' => 'wiki',
            'format' => 'mediawiki',
            'alias' => '.mediawiki',
            'direction' => 'input',
            'status' => 'unsupported',
            'reasonCode' => 'wiki-reader-not-ported',
            'reason' => $reason,
        ], json_decode($mediawiki['serializedReason'], true, 512, JSON_THROW_ON_ERROR));

        $t->same('vimwiki', $vimwiki['format']);
        $t->same('input-only', $vimwiki['direction']);
        $t->same(false, $vimwiki['output']);
        $t->same('not-applicable', $vimwiki['outputStatus']);
        $t->same(null, PandocFormatRegistry::wikiInputExtensionAliasStatus('.wiki'));
        $t->same(null, PandocFormatRegistry::wikiInputExtensionAliasStatus('.dokuwiki'));
        $t->same(null, PandocFormatRegistry::wikiInputExtensionAliasStatus('.xwiki'));

        foreach (array_keys($expectedAliases) as $alias) {
            $t->same(null, PandocFormatRegistry::inferWikiFormatFromExtension($alias), "Wiki status alias {$alias} must not become upstream file inference");
        }
    },
    'checks wiki input token status gates with stable unsupported reasons' => static function (TestRunner $t): void {
        $mediaWikiGate = PandocFormatRegistry::wikiInputTokenStatusGate('.wiki');

        $t->same([
            'token' => '.wiki',
            'normalizedToken' => '.wiki',
            'kind' => 'extension-alias',
            'format' => 'mediawiki',
            'input' => true,
            'output' => true,
            'direction' => 'input-output',
            'inputStatus' => 'unsupported',
            'outputStatus' => 'unsupported',
            'verdict' => 'unsupported',
            'reasonCode' => 'wiki-native-reader-unregistered',
            'reason' => 'No native PHP reader or writer is registered for this upstream Pandoc format yet.',
            'serializedReason' => 'format=mediawiki;kind=extension-alias;normalizedToken=.wiki;inputStatus=unsupported;reasonCode=wiki-native-reader-unregistered',
            'unsupported' => true,
            'partial' => false,
            'inputImplementation' => '',
            'directReaderParitySupported' => false,
        ], $mediaWikiGate);

        $dokuWikiGate = PandocFormatRegistry::wikiInputTokenStatusGate('.dokuwiki');
        $t->same('extension-alias', $dokuWikiGate['kind']);
        $t->same('dokuwiki', $dokuWikiGate['format']);
        $t->same('unsupported', $dokuWikiGate['verdict']);
        $t->same('wiki-native-reader-unregistered', $dokuWikiGate['reasonCode']);
        $t->same('format=dokuwiki;kind=extension-alias;normalizedToken=.dokuwiki;inputStatus=unsupported;reasonCode=wiki-native-reader-unregistered', $dokuWikiGate['serializedReason']);
        $t->same('', $dokuWikiGate['inputImplementation']);
        $t->same(false, $dokuWikiGate['directReaderParitySupported']);
        $t->contains('No native PHP reader or writer is registered', $dokuWikiGate['reason']);

        $tokenGate = PandocFormatRegistry::wikiInputTokenStatusGate('dokuwiki');
        $t->same('input-token', $tokenGate['kind']);
        $t->same('dokuwiki', $tokenGate['normalizedToken']);
        $t->same('dokuwiki', $tokenGate['format']);
        $t->same('unsupported', $tokenGate['verdict']);
        $t->same('format=dokuwiki;kind=input-token;normalizedToken=dokuwiki;inputStatus=unsupported;reasonCode=wiki-native-reader-unregistered', $tokenGate['serializedReason']);
        $t->contains('No native PHP reader or writer is registered', $tokenGate['reason']);
        $t->same(false, $tokenGate['directReaderParitySupported']);

        $t->same(null, PandocFormatRegistry::wikiInputTokenStatusGate(''));
        $t->same(null, PandocFormatRegistry::wikiInputTokenStatusGate('xwiki'));
        $t->same(null, PandocFormatRegistry::wikiInputTokenStatusGate('.xwiki'));
    },
    'records twiki wiki input token status gate without native parser claims' => static function (TestRunner $t): void {
        $gate = PandocFormatRegistry::wikiInputTokenStatusGate();
        $twikiToken = PandocFormatRegistry::wikiInputTokenStatus('TWiki+smart');
        $twikiAlias = PandocFormatRegistry::wikiInputTokenStatus('.TWiki');

        $t->same([
            '.dokuwiki' => 'dokuwiki',
            '.wiki' => 'mediawiki',
            '.twiki' => 'twiki',
        ], PandocFormatRegistry::wikiInputStatusAliases());
        $t->same('wiki-input-token-status', $gate['family']);
        $t->same('unsupported', $gate['unsupportedVerdict']);
        $t->same(PandocFormatRegistry::wikiInputFormats(), $gate['unsupportedInputFormats']);
        $t->same(false, $gate['directReaderParitySupported']);
        $t->same(true, $gate['externalToolFree']);
        $t->same(PandocFormatRegistry::wikiInputStatusAliases(), $gate['statusAliases']);
        $t->same('twiki', $gate['statusAliases']['.twiki']);
        $t->same('twiki', $gate['aliasStatuses']['.twiki']['format']);
        $t->same('', $gate['formats']['twiki']['outputImplementation']);
        $t->same(false, array_key_exists('.twiki', PandocFormatRegistry::wikiExtensionInference()));

        $t->same([
            'query' => 'TWiki+smart',
            'normalizedToken' => 'twiki+smart',
            'format' => 'twiki',
            'label' => 'TWiki',
            'family' => 'wiki',
            'alias' => false,
            'aliasKind' => 'wiki-input-token',
            'input' => true,
            'output' => false,
            'direction' => 'input-only',
            'inputStatus' => 'unsupported',
            'outputStatus' => 'not-applicable',
            'verdict' => 'unsupported',
            'reasonCode' => 'wiki-reader-not-ported',
            'reason' => 'Upstream wiki reader coverage is inventoried, but no native PHP wiki reader is registered for this format.',
            'unsupportedReason' => [
                'family' => 'wiki',
                'format' => 'twiki',
                'direction' => 'input',
                'status' => 'unsupported',
                'reasonCode' => 'wiki-reader-not-ported',
                'reason' => 'Upstream wiki reader coverage is inventoried, but no native PHP wiki reader is registered for this format.',
            ],
            'serializedReason' => '{"family":"wiki","format":"twiki","direction":"input","status":"unsupported","reasonCode":"wiki-reader-not-ported","reason":"Upstream wiki reader coverage is inventoried, but no native PHP wiki reader is registered for this format."}',
            'inputImplementation' => '',
            'outputImplementation' => '',
            'directReaderParitySupported' => false,
            'externalToolFree' => true,
        ], $twikiToken);
        $t->same([
            'family' => 'wiki',
            'format' => 'twiki',
            'direction' => 'input',
            'status' => 'unsupported',
            'reasonCode' => 'wiki-reader-not-ported',
            'reason' => 'Upstream wiki reader coverage is inventoried, but no native PHP wiki reader is registered for this format.',
        ], json_decode($twikiToken['serializedReason'], true, 512, JSON_THROW_ON_ERROR));

        $t->same('twiki', $twikiAlias['format']);
        $t->same('.twiki', $twikiAlias['normalizedToken']);
        $t->same(true, $twikiAlias['alias']);
        $t->same('wiki-input-status-extension', $twikiAlias['aliasKind']);
        $t->same('unsupported', $twikiAlias['verdict']);
        $t->same('', $twikiAlias['inputImplementation']);
        $t->same(false, $twikiAlias['directReaderParitySupported']);
        $t->same($twikiToken['serializedReason'], $twikiAlias['serializedReason']);
        $t->same(null, PandocFormatRegistry::wikiInputTokenStatus('.xwiki'));
    },
    'summarizes wiki registry parity counts without registering converters' => static function (TestRunner $t): void {
        $summary = PandocFormatRegistry::wikiFormatParitySummary();

        $coreSummary = [
            'totalFormats' => $summary['totalFormats'],
            'inputFormats' => $summary['inputFormats'],
            'outputFormats' => $summary['outputFormats'],
            'inputOutputFormats' => $summary['inputOutputFormats'],
            'inputOnlyFormats' => $summary['inputOnlyFormats'],
            'outputOnlyFormats' => $summary['outputOnlyFormats'],
            'extensionInferenceMappings' => $summary['extensionInferenceMappings'],
            'extensionInferredFormats' => $summary['extensionInferredFormats'],
            'nonExtensionInferredFormats' => $summary['nonExtensionInferredFormats'],
            'unsupportedInputFormats' => $summary['unsupportedInputFormats'],
            'unsupportedOutputFormats' => $summary['unsupportedOutputFormats'],
            'registeredInputImplementations' => $summary['registeredInputImplementations'],
            'registeredOutputImplementations' => $summary['registeredOutputImplementations'],
            'directParityClaimed' => $summary['directParityClaimed'],
        ];
        $t->same([
            'totalFormats' => 9,
            'inputFormats' => 7,
            'outputFormats' => 5,
            'inputOutputFormats' => 3,
            'inputOnlyFormats' => 4,
            'outputOnlyFormats' => 2,
            'extensionInferenceMappings' => 2,
            'extensionInferredFormats' => 2,
            'nonExtensionInferredFormats' => 7,
            'unsupportedInputFormats' => 7,
            'unsupportedOutputFormats' => 5,
            'registeredInputImplementations' => 0,
            'registeredOutputImplementations' => 0,
            'directParityClaimed' => false,
        ], $coreSummary);

        $packet = PandocFormatRegistry::wikiFormatReviewPacket();
        $t->same(count($packet['formats']), $summary['totalFormats']);
        $t->same(count($packet['directionBuckets']['inputOutput']), $summary['inputOutputFormats']);
        $t->same(count($packet['directionBuckets']['inputOnly']), $summary['inputOnlyFormats']);
        $t->same(count($packet['directionBuckets']['outputOnly']), $summary['outputOnlyFormats']);
        $t->same(count($packet['extensionInference']), $summary['extensionInferenceMappings']);
        $t->same(count($packet['unsupportedInputFormats']), $summary['unsupportedInputFormats']);
        $t->same(count($packet['unsupportedOutputFormats']), $summary['unsupportedOutputFormats']);
        $t->same(false, $summary['directParityClaimed']);
    },
    'builds compact wiki parity summary without direct reader writer claims' => static function (TestRunner $t): void {
        $summary = PandocFormatRegistry::wikiFormatParitySummary();

        $t->same('2026-06-03', $summary['upstreamManualDate']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $summary['upstreamSourceCommit']);
        $t->same(7, $summary['inputFormatCount']);
        $t->same(5, $summary['outputFormatCount']);
        $t->same(9, $summary['uniqueFormatCount']);
        $t->same([
            'inputOutput' => 3,
            'inputOnly' => 4,
            'outputOnly' => 2,
        ], $summary['directionCounts']);
        $t->same(['unsupported' => 7], $summary['inputSupportStatusCounts']);
        $t->same(['unsupported' => 5], $summary['outputSupportStatusCounts']);
        $t->same(2, $summary['extensionInferredFormatCount']);
        $t->same(7, $summary['nonExtensionInferredFormatCount']);
        $t->same(7, $summary['unsupportedInputCount']);
        $t->same(5, $summary['unsupportedOutputCount']);
        $t->same(false, $summary['directReaderParitySupported']);
        $t->same(false, $summary['directWriterParitySupported']);
        $t->same('unsupported', $summary['directParityStatus']);
        $t->contains('no native PHP wiki reader or writer is registered', $summary['reviewNote']);
    },
    'builds wiki format review packets without direct parity claims' => static function (TestRunner $t): void {
        $packet = PandocFormatRegistry::wikiFormatReviewPacket();

        $t->same('2026-06-03', $packet['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $packet['upstreamManualUrl']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $packet['upstreamSourceCommit']);
        $t->same(PandocFormatRegistry::wikiInputFormats(), $packet['inputFormats']);
        $t->same(PandocFormatRegistry::wikiOutputFormats(), $packet['outputFormats']);
        $t->same([
            'inputOutput' => ['dokuwiki', 'jira', 'mediawiki'],
            'inputOnly' => ['creole', 'tikiwiki', 'twiki', 'vimwiki'],
            'outputOnly' => ['xwiki', 'zimwiki'],
        ], $packet['directionBuckets']);
        $t->same([
            '.dokuwiki' => 'dokuwiki',
            '.wiki' => 'mediawiki',
        ], $packet['extensionInference']);
        $t->same(['dokuwiki', 'mediawiki'], $packet['extensionInferredFormats']);
        $t->same(['creole', 'jira', 'tikiwiki', 'twiki', 'vimwiki', 'xwiki', 'zimwiki'], $packet['nonExtensionInferredFormats']);
        $t->same(PandocFormatRegistry::wikiInputFormats(), $packet['unsupportedInputFormats']);
        $t->same(PandocFormatRegistry::wikiOutputFormats(), $packet['unsupportedOutputFormats']);
        $t->same([
            'creole',
            'dokuwiki',
            'jira',
            'mediawiki',
            'tikiwiki',
            'twiki',
            'vimwiki',
            'xwiki',
            'zimwiki',
        ], array_keys($packet['formats']));

        $t->same([
            'input' => true,
            'output' => true,
            'direction' => 'input-output',
            'inputStatus' => 'unsupported',
            'outputStatus' => 'unsupported',
            'extensionInferred' => true,
            'extensions' => ['.dokuwiki'],
            'inputImplementation' => '',
            'outputImplementation' => '',
        ], $packet['formats']['dokuwiki']);
        $t->same(['.wiki'], $packet['formats']['mediawiki']['extensions']);
        $t->same('input-only', $packet['formats']['creole']['direction']);
        $t->same('not-applicable', $packet['formats']['creole']['outputStatus']);
        $t->same([], $packet['formats']['creole']['extensions']);
        $t->same('output-only', $packet['formats']['xwiki']['direction']);
        $t->same('not-applicable', $packet['formats']['xwiki']['inputStatus']);
        $t->same('unsupported', $packet['formats']['xwiki']['outputStatus']);

        foreach ($packet['formats'] as $format => $review) {
            $t->same('', $review['inputImplementation'], "Wiki review packet {$format} must not register an input implementation");
            $t->same('', $review['outputImplementation'], "Wiki review packet {$format} must not register an output implementation");
        }
    },
    'summarizes wiki unsupported format surfaces without converter claims' => static function (TestRunner $t): void {
        $summary = PandocFormatRegistry::wikiUnsupportedFormatSummary();
        $packet = PandocFormatRegistry::wikiFormatReviewPacket();
        $directions = PandocFormatRegistry::wikiFormatDirections();

        $t->same($summary, $packet['unsupportedFormatSummary']);
        $t->same([
            'creole',
            'dokuwiki',
            'jira',
            'mediawiki',
            'tikiwiki',
            'twiki',
            'vimwiki',
            'xwiki',
            'zimwiki',
        ], $summary['anyUnsupported']);
        $t->same(['dokuwiki', 'jira', 'mediawiki'], $summary['unsupportedBoth']);
        $t->same(['creole', 'tikiwiki', 'twiki', 'vimwiki'], $summary['unsupportedInputOnly']);
        $t->same(['xwiki', 'zimwiki'], $summary['unsupportedOutputOnly']);
        $t->same(PandocFormatRegistry::unsupportedWikiInputFormats(), $summary['noNativeReader']);
        $t->same(PandocFormatRegistry::unsupportedWikiOutputFormats(), $summary['noNativeWriter']);
        $t->same(PandocFormatRegistry::wikiInputFormats(), $summary['noNativeReader']);
        $t->same(PandocFormatRegistry::wikiOutputFormats(), $summary['noNativeWriter']);

        foreach ($summary['anyUnsupported'] as $format) {
            $direction = $directions[$format];
            $t->same(true, $direction['inputStatus'] === 'unsupported' || $direction['outputStatus'] === 'unsupported', "Wiki {$format} should have an unsupported surface");
        }

        foreach ($summary['unsupportedBoth'] as $format) {
            $review = $packet['formats'][$format];
            $t->same('input-output', $review['direction'], "Wiki {$format} should remain bidirectional in upstream accounting");
            $t->same('unsupported', $review['inputStatus'], "Wiki {$format} should not claim native reader parity");
            $t->same('unsupported', $review['outputStatus'], "Wiki {$format} should not claim native writer parity");
            $t->same('', $review['inputImplementation']);
            $t->same('', $review['outputImplementation']);
        }

        foreach ($summary['unsupportedInputOnly'] as $format) {
            $review = $packet['formats'][$format];
            $t->same('input-only', $review['direction'], "Wiki {$format} should remain input-only");
            $t->same('unsupported', $review['inputStatus'], "Wiki {$format} should keep unsupported input accounting");
            $t->same('not-applicable', $review['outputStatus'], "Wiki {$format} should not appear as an output token");
            $t->same('', $review['inputImplementation']);
        }

        foreach ($summary['unsupportedOutputOnly'] as $format) {
            $review = $packet['formats'][$format];
            $t->same('output-only', $review['direction'], "Wiki {$format} should remain output-only");
            $t->same('not-applicable', $review['inputStatus'], "Wiki {$format} should not appear as an input token");
            $t->same('unsupported', $review['outputStatus'], "Wiki {$format} should keep unsupported output accounting");
            $t->same('', $review['outputImplementation']);
        }
    },
    'builds wiki input unsupported reason registry matrix without parser claims' => static function (TestRunner $t): void {
        $inputTokens = [
            'creole',
            'dokuwiki',
            'jira',
            'mediawiki',
            'tikiwiki',
            'twiki',
            'vimwiki',
        ];
        $extensionAliasesByToken = [
            'creole' => [],
            'dokuwiki' => ['.dokuwiki'],
            'jira' => [],
            'mediawiki' => ['.wiki'],
            'tikiwiki' => [],
            'twiki' => [],
            'vimwiki' => [],
        ];
        $diagnostics = PandocFormatRegistry::textMarkupUnsupportedFormatDiagnostics();
        $matrix = PandocFormatRegistry::wikiInputUnsupportedReasonRegistryMatrix();

        $t->same('2026-06-03', $matrix['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $matrix['upstreamManualUrl']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $matrix['upstreamSourceCommit']);
        $t->same($inputTokens, $matrix['inputTokens']);
        $t->same($inputTokens, $matrix['unsupportedInputTokens']);
        $t->same(7, $matrix['unsupportedInputCount']);
        $t->same(false, $matrix['directReaderParitySupported']);
        $t->same(true, $matrix['externalToolFree']);
        $t->same(true, $matrix['nativeImplementationRecordsEmpty']);
        $t->same([
            '.dokuwiki' => 'dokuwiki',
            '.wiki' => 'mediawiki',
        ], $matrix['extensionAliases']);
        $t->same($matrix['extensionAliases'], PandocFormatRegistry::wikiExtensionInference());
        $t->same(['dokuwiki', 'mediawiki'], PandocFormatRegistry::wikiFormatsWithExtensionInference());
        $t->same(['creole', 'jira', 'tikiwiki', 'twiki', 'vimwiki', 'xwiki', 'zimwiki'], PandocFormatRegistry::wikiFormatsWithoutExtensionInference());
        $t->same($inputTokens, array_keys($matrix['rows']));
        $t->same($inputTokens, array_keys($matrix['nativeImplementationRecords']));

        foreach ($inputTokens as $format) {
            $diagnostic = $diagnostics[$format];
            $expectedReasonPayload = [
                'format' => $format,
                'family' => 'wiki',
                'reasonCode' => 'wiki-reader-not-ported',
                'reason' => $diagnostic['reason'],
                'inputStatus' => 'unsupported',
                'outputStatus' => $diagnostic['outputStatus'],
                'unsupportedDirections' => $diagnostic['unsupportedDirections'],
                'inputNotes' => $diagnostic['inputNotes'],
            ];
            $expectedImplementationRecord = [
                'inputImplementation' => '',
                'outputImplementation' => '',
            ];
            $row = $matrix['rows'][$format];

            $t->same($format, $row['inputToken']);
            $t->same('wiki', $row['family']);
            $t->same($extensionAliasesByToken[$format], $row['extensionAliases']);
            $t->same($expectedReasonPayload, $row['unsupportedReasonPayload']);
            $t->same(json_encode($expectedReasonPayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $row['serializedUnsupportedReasonPayload']);
            $t->same(false, $row['directReaderParity']);
            $t->same(true, $row['externalToolFree']);
            $t->same($expectedImplementationRecord, $row['nativeImplementationRecord']);
            $t->same($expectedImplementationRecord, $matrix['nativeImplementationRecords'][$format]);
        }
    },
    'builds text markup unsupported diagnostics without reader or writer claims' => static function (TestRunner $t): void {
        $formats = [
            'asciidoc',
            'creole',
            'djot',
            'dokuwiki',
            'fb2',
            'haddock',
            'jira',
            'man',
            'mdoc',
            'mediawiki',
            'muse',
            'opml',
            'org',
            'pod',
            'rst',
            't2t',
            'textile',
            'tikiwiki',
            'twiki',
            'vimwiki',
        ];
        $packet = PandocFormatRegistry::textMarkupUnsupportedFormatReviewPacket();
        $diagnostics = PandocFormatRegistry::textMarkupUnsupportedFormatDiagnostics();

        $t->same($formats, PandocFormatRegistry::textMarkupInputFormats());
        $t->same($formats, array_keys(PandocFormatRegistry::textMarkupInputSupport()));
        $t->same($formats, PandocFormatRegistry::unsupportedTextMarkupInputFormats());
        $t->same($formats, $packet['inputFormats']);
        $t->same($formats, $packet['unsupportedInputFormats']);
        $t->same($formats, array_keys($diagnostics));
        $t->same($diagnostics, $packet['diagnostics']);
        $t->same(20, $packet['upstreamInputDenominator']);
        $t->same(0, $packet['localNativeReaderPasses']);
        $t->same(20, $packet['unsupportedInputCount']);
        $t->same(true, $packet['allInputUnsupported']);
        $t->same([], $packet['readerCapableFormats']);
        $t->same([], $packet['writerCapableFormats']);
        $t->same('2026-06-03', $packet['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $packet['upstreamManualUrl']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $packet['upstreamSourceCommit']);

        $t->same([
            'lightweight-markup' => ['asciidoc', 'djot', 'fb2', 'haddock', 'muse', 'opml', 'org', 'pod', 'rst', 't2t', 'textile'],
            'wiki' => ['creole', 'dokuwiki', 'jira', 'mediawiki', 'tikiwiki', 'twiki', 'vimwiki'],
            'roff-manual' => ['man', 'mdoc'],
        ], $packet['familyBuckets']);
        $t->same([
            'roff-manual-reader-not-ported' => 2,
            'text-markup-reader-not-ported' => 11,
            'wiki-reader-not-ported' => 7,
        ], $packet['reasonCodeCounts']);

        foreach ($diagnostics as $format => $diagnostic) {
            $t->same($format, $diagnostic['format']);
            $t->same('unsupported', $diagnostic['inputStatus'], "Text markup {$format} should keep explicit unsupported input status");
            $t->same(false, $diagnostic['readerCapable'], "Text markup {$format} must not claim a native reader");
            $t->same('', $diagnostic['inputImplementation'], "Text markup {$format} must not register a native reader implementation");
            $t->same(true, in_array('input', $diagnostic['unsupportedDirections'], true), "Text markup {$format} must expose an unsupported input direction");
            $t->same(true, $diagnostic['externalToolFree'], "Text markup {$format} diagnostics must remain external-tool free");
            $t->contains('no native PHP', $diagnostic['reason']);
        }

        $asciidoc = $diagnostics['asciidoc'];
        $t->same('lightweight-markup', $asciidoc['family']);
        $t->same('text-markup-reader-not-ported', $asciidoc['reasonCode']);
        $t->same('unsupported', $asciidoc['outputStatus']);
        $t->same(['input', 'output'], $asciidoc['unsupportedDirections']);
        $t->same(false, $asciidoc['writerCapable']);
        $t->same('', $asciidoc['outputImplementation']);

        $dokuwiki = $diagnostics['dokuwiki'];
        $t->same('wiki', $dokuwiki['family']);
        $t->same('wiki-reader-not-ported', $dokuwiki['reasonCode']);
        $t->same('unsupported', $dokuwiki['outputStatus']);
        $t->same(['input', 'output'], $dokuwiki['unsupportedDirections']);
        $t->contains('No native PHP reader or writer is registered', $dokuwiki['inputNotes']);

        $man = $diagnostics['man'];
        $t->same('roff-manual', $man['family']);
        $t->same('roff-manual-reader-not-ported', $man['reasonCode']);
        $t->same('unsupported', $man['outputStatus']);
        $t->same(['input', 'output'], $man['unsupportedDirections']);
        $t->contains('upstream man reader source semantics', $man['inputNotes']);
        $t->contains('upstream man writer source semantics', $man['outputNotes']);

        $mdoc = $diagnostics['mdoc'];
        $t->same('roff-manual', $mdoc['family']);
        $t->same('not-applicable', $mdoc['outputStatus']);
        $t->same(['input'], $mdoc['unsupportedDirections']);
        $t->same(false, $mdoc['writerCapable']);
        $t->contains('manual-family input', $mdoc['inputNotes']);
        $t->contains('No upstream Pandoc writer token', $mdoc['outputNotes']);
    },
    'builds wiki format upstream evidence packets without direct parity claims' => static function (TestRunner $t): void {
        $templates = PandocFormatRegistry::wikiTemplateResources();
        $fixtures = PandocFormatRegistry::wikiFixtureSources();
        $packet = PandocFormatRegistry::wikiFormatEvidencePacket();

        $t->same([
            'dokuwiki' => 'data/templates/default.dokuwiki',
            'jira' => 'data/templates/default.jira',
            'mediawiki' => 'data/templates/default.mediawiki',
            'xwiki' => 'data/templates/default.xwiki',
            'zimwiki' => 'data/templates/default.zimwiki',
        ], $templates);
        $t->same($templates, PandocFormatRegistry::wikiOutputTemplateResources());
        $t->same(PandocFormatRegistry::wikiOutputFormats(), PandocFormatRegistry::wikiOutputFormatsWithDefaultTemplates());
        $t->same([], PandocFormatRegistry::wikiOutputFormatsWithoutDefaultTemplates());
        foreach ($templates as $format => $resource) {
            $t->same($resource, PandocFormatRegistry::templateResourceForWikiOutputFormat($format));
            $t->same($resource, PandocFormatRegistry::templateResourceForWikiOutputFormat($format . '+smart'));
        }
        foreach (PandocFormatRegistry::wikiInputOnlyFormats() as $format) {
            $t->same(null, PandocFormatRegistry::templateResourceForWikiOutputFormat($format));
        }
        $t->same(null, PandocFormatRegistry::templateResourceForWikiOutputFormat(''));
        $t->same(null, PandocFormatRegistry::templateResourceForWikiOutputFormat('vimwiki+smart'));
        $t->same([
            'creole' => [
                'reader' => ['test/creole-reader.txt'],
                'writer' => [],
            ],
            'dokuwiki' => [
                'reader' => [
                    'test/dokuwiki_inline_formatting.dokuwiki',
                    'test/dokuwiki_external_images.dokuwiki',
                    'test/dokuwiki_multiblock_table.dokuwiki',
                ],
                'writer' => [
                    'test/tables.dokuwiki',
                    'test/writer.dokuwiki',
                ],
            ],
            'jira' => [
                'reader' => ['test/jira-reader.jira'],
                'writer' => [
                    'test/tables.jira',
                    'test/writer.jira',
                ],
            ],
            'mediawiki' => [
                'reader' => ['test/mediawiki-reader.wiki'],
                'writer' => [
                    'test/tables.mediawiki',
                    'test/tables/*.mediawiki',
                    'test/writer.mediawiki',
                ],
            ],
            'tikiwiki' => [
                'reader' => ['test/tikiwiki-reader.tikiwiki'],
                'writer' => [],
            ],
            'twiki' => [
                'reader' => ['test/twiki-reader.twiki'],
                'writer' => [],
            ],
            'vimwiki' => [
                'reader' => ['test/vimwiki-reader.wiki'],
                'writer' => [],
            ],
            'xwiki' => [
                'reader' => [],
                'writer' => [
                    'test/tables.xwiki',
                    'test/writer.xwiki',
                ],
            ],
            'zimwiki' => [
                'reader' => [],
                'writer' => [
                    'test/tables.zimwiki',
                    'test/writer.zimwiki',
                ],
            ],
        ], $fixtures);

        $t->same('2026-06-03', $packet['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $packet['upstreamManualUrl']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $packet['upstreamSourceCommit']);
        $t->same($templates, $packet['templateResources']);
        $t->same($fixtures, $packet['fixtureSources']);
        $t->same([
            'creole',
            'dokuwiki',
            'jira',
            'mediawiki',
            'tikiwiki',
            'twiki',
            'vimwiki',
            'xwiki',
            'zimwiki',
        ], array_keys($packet['formats']));

        $t->same([
            'test/dokuwiki_inline_formatting.dokuwiki',
            'test/dokuwiki_external_images.dokuwiki',
            'test/dokuwiki_multiblock_table.dokuwiki',
        ], $packet['formats']['dokuwiki']['readerFixtures']);
        $t->same([
            'test/tables.dokuwiki',
            'test/writer.dokuwiki',
        ], $packet['formats']['dokuwiki']['writerFixtures']);
        $t->same('data/templates/default.dokuwiki', $packet['formats']['dokuwiki']['templateResource']);
        $t->same(true, $packet['formats']['dokuwiki']['hasTemplateResource']);
        $t->same('input-output', $packet['formats']['mediawiki']['direction']);
        $t->same(['test/mediawiki-reader.wiki'], $packet['formats']['mediawiki']['readerFixtures']);
        $t->same([
            'test/tables.mediawiki',
            'test/tables/*.mediawiki',
            'test/writer.mediawiki',
        ], $packet['formats']['mediawiki']['writerFixtures']);
        $t->same('input-only', $packet['formats']['vimwiki']['direction']);
        $t->same(['test/vimwiki-reader.wiki'], $packet['formats']['vimwiki']['readerFixtures']);
        $t->same([], $packet['formats']['vimwiki']['writerFixtures']);
        $t->same('', $packet['formats']['vimwiki']['templateResource']);
        $t->same(false, $packet['formats']['vimwiki']['hasTemplateResource']);
        $t->same('output-only', $packet['formats']['xwiki']['direction']);
        $t->same([], $packet['formats']['xwiki']['readerFixtures']);
        $t->same([
            'test/tables.xwiki',
            'test/writer.xwiki',
        ], $packet['formats']['xwiki']['writerFixtures']);
        $t->same('not-applicable', $packet['formats']['xwiki']['inputStatus']);
        $t->same('unsupported', $packet['formats']['xwiki']['outputStatus']);

        foreach ($packet['formats'] as $format => $evidence) {
            $t->same('', $evidence['inputImplementation'], "Wiki evidence packet {$format} must not register an input implementation");
            $t->same('', $evidence['outputImplementation'], "Wiki evidence packet {$format} must not register an output implementation");
        }
    },
    'builds compact wiki registry metadata from audited evidence' => static function (TestRunner $t): void {
        $metadata = PandocFormatRegistry::wikiFormatRegistryMetadata();
        $labels = [];
        $directions = [];
        foreach ($metadata as $format => $entry) {
            $labels[$format] = $entry['label'];
            $directions[$format] = [
                'input' => $entry['input'],
                'output' => $entry['output'],
                'direction' => $entry['direction'],
                'inputStatus' => $entry['inputStatus'],
                'outputStatus' => $entry['outputStatus'],
            ];
        }

        $t->same([
            'creole',
            'dokuwiki',
            'jira',
            'mediawiki',
            'tikiwiki',
            'twiki',
            'vimwiki',
            'xwiki',
            'zimwiki',
        ], array_keys($metadata));
        $t->same([
            'creole' => 'Creole',
            'dokuwiki' => 'DokuWiki',
            'jira' => 'Jira wiki',
            'mediawiki' => 'MediaWiki',
            'tikiwiki' => 'TikiWiki',
            'twiki' => 'TWiki',
            'vimwiki' => 'Vimwiki',
            'xwiki' => 'XWiki',
            'zimwiki' => 'ZimWiki',
        ], $labels);
        $t->same(PandocFormatRegistry::wikiFormatDirections(), $directions);
        $t->same([
            'format' => 'creole',
            'label' => 'Creole',
            'input' => true,
            'output' => false,
            'direction' => 'input-only',
            'inputStatus' => 'unsupported',
            'outputStatus' => 'not-applicable',
            'readerFixturePaths' => ['test/creole-reader.txt'],
            'writerFixturePaths' => [],
            'upstreamFixturePaths' => ['test/creole-reader.txt'],
            'upstreamTemplatePath' => null,
        ], $metadata['creole']);
        $t->same([
            'format' => 'dokuwiki',
            'label' => 'DokuWiki',
            'input' => true,
            'output' => true,
            'direction' => 'input-output',
            'inputStatus' => 'unsupported',
            'outputStatus' => 'unsupported',
            'readerFixturePaths' => [
                'test/dokuwiki_inline_formatting.dokuwiki',
                'test/dokuwiki_external_images.dokuwiki',
                'test/dokuwiki_multiblock_table.dokuwiki',
            ],
            'writerFixturePaths' => [
                'test/tables.dokuwiki',
                'test/writer.dokuwiki',
            ],
            'upstreamFixturePaths' => [
                'test/dokuwiki_inline_formatting.dokuwiki',
                'test/dokuwiki_external_images.dokuwiki',
                'test/dokuwiki_multiblock_table.dokuwiki',
                'test/tables.dokuwiki',
                'test/writer.dokuwiki',
            ],
            'upstreamTemplatePath' => 'data/templates/default.dokuwiki',
        ], $metadata['dokuwiki']);
        $t->same([
            'format' => 'xwiki',
            'label' => 'XWiki',
            'input' => false,
            'output' => true,
            'direction' => 'output-only',
            'inputStatus' => 'not-applicable',
            'outputStatus' => 'unsupported',
            'readerFixturePaths' => [],
            'writerFixturePaths' => [
                'test/tables.xwiki',
                'test/writer.xwiki',
            ],
            'upstreamFixturePaths' => [
                'test/tables.xwiki',
                'test/writer.xwiki',
            ],
            'upstreamTemplatePath' => 'data/templates/default.xwiki',
        ], $metadata['xwiki']);
    },
    'tracks wiki upstream reader writer source provenance without direct parity claims' => static function (TestRunner $t): void {
        $inputProvenance = PandocFormatRegistry::wikiInputSourceProvenance();
        $outputProvenance = PandocFormatRegistry::wikiOutputSourceProvenance();
        $combinedProvenance = PandocFormatRegistry::wikiFormatSourceProvenance();
        $directions = PandocFormatRegistry::wikiFormatDirections();
        $inputSupport = PandocFormatRegistry::wikiInputSupport();
        $outputSupport = PandocFormatRegistry::wikiOutputSupport();

        $t->same([
            'creole',
            'dokuwiki',
            'jira',
            'mediawiki',
            'tikiwiki',
            'twiki',
            'vimwiki',
        ], array_keys($inputProvenance));
        $t->same([
            'dokuwiki',
            'jira',
            'mediawiki',
            'xwiki',
            'zimwiki',
        ], array_keys($outputProvenance));
        $t->same([
            'creole',
            'dokuwiki',
            'jira',
            'mediawiki',
            'tikiwiki',
            'twiki',
            'vimwiki',
            'xwiki',
            'zimwiki',
        ], array_keys($combinedProvenance));

        $t->same([
            'module' => 'Text.Pandoc.Readers.Creole',
            'function' => 'readCreole',
            'registry' => '("creole"       , TextReader readCreole)',
        ], $inputProvenance['creole']);
        $t->same([
            'module' => 'Text.Pandoc.Readers.DokuWiki',
            'function' => 'readDokuWiki',
            'registry' => '("dokuwiki"     , TextReader readDokuWiki)',
        ], $inputProvenance['dokuwiki']);
        $t->same([
            'module' => 'Text.Pandoc.Readers.Jira',
            'function' => 'readJira',
            'registry' => '("jira"         , TextReader readJira)',
        ], $inputProvenance['jira']);
        $t->same([
            'module' => 'Text.Pandoc.Readers.MediaWiki',
            'function' => 'readMediaWiki',
            'registry' => '("mediawiki"    , TextReader readMediaWiki)',
        ], $inputProvenance['mediawiki']);
        $t->same([
            'module' => 'Text.Pandoc.Readers.TikiWiki',
            'function' => 'readTikiWiki',
            'registry' => '("tikiwiki"     , TextReader readTikiWiki)',
        ], $inputProvenance['tikiwiki']);
        $t->same([
            'module' => 'Text.Pandoc.Readers.TWiki',
            'function' => 'readTWiki',
            'registry' => '("twiki"        , TextReader readTWiki)',
        ], $inputProvenance['twiki']);
        $t->same([
            'module' => 'Text.Pandoc.Readers.Vimwiki',
            'function' => 'readVimwiki',
            'registry' => '("vimwiki"      , TextReader readVimwiki)',
        ], $inputProvenance['vimwiki']);

        $t->same([
            'module' => 'Text.Pandoc.Writers.DokuWiki',
            'function' => 'writeDokuWiki',
            'registry' => '("dokuwiki"     , TextWriter writeDokuWiki)',
        ], $outputProvenance['dokuwiki']);
        $t->same([
            'module' => 'Text.Pandoc.Writers.Jira',
            'function' => 'writeJira',
            'registry' => '("jira"         , TextWriter writeJira)',
        ], $outputProvenance['jira']);
        $t->same([
            'module' => 'Text.Pandoc.Writers.MediaWiki',
            'function' => 'writeMediaWiki',
            'registry' => '("mediawiki"    , TextWriter writeMediaWiki)',
        ], $outputProvenance['mediawiki']);
        $t->same([
            'module' => 'Text.Pandoc.Writers.XWiki',
            'function' => 'writeXWiki',
            'registry' => '("xwiki"        , TextWriter writeXWiki)',
        ], $outputProvenance['xwiki']);
        $t->same([
            'module' => 'Text.Pandoc.Writers.ZimWiki',
            'function' => 'writeZimWiki',
            'registry' => '("zimwiki"      , TextWriter writeZimWiki)',
        ], $outputProvenance['zimwiki']);

        foreach ($combinedProvenance as $format => $source) {
            $direction = $directions[$format];

            $t->same($direction['input'], $source['input'] !== null, "Wiki source provenance {$format} input presence mismatch");
            $t->same($direction['output'], $source['output'] !== null, "Wiki source provenance {$format} output presence mismatch");

            if ($source['input'] !== null) {
                $t->same('unsupported', $inputSupport[$format]['status'], "Wiki source provenance {$format} input must not claim PHP reader parity");
                $t->contains('Text.Pandoc.Readers.', $source['input']['module']);
                $t->contains('TextReader', $source['input']['registry']);
                $t->same(true, str_starts_with($source['input']['function'], 'read'));
            }

            if ($source['output'] !== null) {
                $t->same('unsupported', $outputSupport[$format]['status'], "Wiki source provenance {$format} output must not claim PHP writer parity");
                $t->contains('Text.Pandoc.Writers.', $source['output']['module']);
                $t->contains('TextWriter', $source['output']['registry']);
                $t->same(true, str_starts_with($source['output']['function'], 'write'));
            }
        }

        $t->same(null, $combinedProvenance['creole']['output']);
        $t->same(null, $combinedProvenance['xwiki']['input']);
        $t->same(null, $combinedProvenance['zimwiki']['input']);
    },
    'records wiki roff man text markup reader ship gate unsupported verdict' => static function (TestRunner $t): void {
        $expectedFormats = [
            'asciidoc',
            'creole',
            'djot',
            'dokuwiki',
            'fb2',
            'haddock',
            'jira',
            'man',
            'mdoc',
            'mediawiki',
            'muse',
            'opml',
            'org',
            'pod',
            'rst',
            't2t',
            'textile',
            'tikiwiki',
            'twiki',
            'vimwiki',
        ];

        $support = PandocFormatRegistry::textMarkupReaderSupport();
        $gate = PandocFormatRegistry::textMarkupReaderShipGate();

        $t->same($expectedFormats, PandocFormatRegistry::textMarkupReaderFormats());
        $t->same($expectedFormats, array_keys($support));
        $t->same($expectedFormats, PandocFormatRegistry::unsupportedTextMarkupReaderFormats());
        $t->same('2026-06-03', $gate['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $gate['upstreamManualUrl']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $gate['upstreamSourceCommit']);
        $t->same('wiki-roff-man-text-markup-readers', $gate['family']);
        $t->same($expectedFormats, $gate['inputFormats']);
        $t->same(20, $gate['upstreamDenominator']);
        $t->same(0, $gate['localPassingNumerator']);
        $t->same('unsupported', $gate['unsupportedVerdict']);
        $t->same($expectedFormats, $gate['unsupportedFormats']);
        $t->same(20, $gate['unsupportedCount']);
        $t->same([], $gate['partialFormats']);
        $t->same([], $gate['implementedFormats']);
        $t->same([
            'roff-manual' => 2,
            'text-markup' => 11,
            'wiki' => 7,
        ], $gate['familyCounts']);
        $t->same(['unsupported' => 20], $gate['supportStatusCounts']);
        $t->same([
            'roff-manual-native-reader-not-implemented' => [
                'family' => 'roff-manual',
                'status' => 'unsupported',
                'readerParityStatus' => 'not-implemented',
                'reviewPolicy' => 'registry-diagnostics-only',
                'externalToolFree' => true,
                'message' => 'Pandoc roff/manual input is registered upstream, but no native PHP roff/manual reader parity is implemented.',
                'formats' => ['man', 'mdoc'],
                'formatCount' => 2,
            ],
        ], $gate['unsupportedReasonTaxonomy']);
        $t->same(['roff-manual-native-reader-not-implemented' => 2], $gate['unsupportedReasonCounts']);
        $t->same(false, $gate['directReaderParitySupported']);
        $t->same(true, $gate['externalToolFree']);
        $t->same($expectedFormats, array_keys($gate['formats']));

        $t->same('wiki', $gate['formats']['mediawiki']['family']);
        $t->same('roff-manual', $gate['formats']['man']['family']);
        $t->same('text-markup', $gate['formats']['org']['family']);
        $t->contains('upstream man reader source semantics', $gate['formats']['man']['inputNotes']);
        $t->contains('No native PHP reader or writer is registered', $gate['formats']['org']['inputNotes']);
        $t->same(null, $gate['formats']['org']['unsupportedReason']);

        foreach (['man', 'mdoc'] as $format) {
            $reason = $gate['formats'][$format]['unsupportedReason'];
            $t->same('roff-manual-native-reader-not-implemented', $reason['code'], "Roff/manual {$format} should share a stable unsupported reason code");
            $t->same('roff-manual', $reason['family']);
            $t->same('unsupported', $reason['status']);
            $t->same('not-implemented', $reason['readerParityStatus']);
            $t->same('registry-diagnostics-only', $reason['reviewPolicy']);
            $t->same(false, $reason['directReaderParity']);
            $t->same(true, $reason['externalToolFree']);
            $t->contains($format . ': Pandoc roff/manual input is registered upstream', $reason['message']);
        }

        foreach ($gate['formats'] as $format => $entry) {
            $t->same('unsupported', $entry['inputStatus'], "Text markup reader {$format} must keep explicit unsupported accounting");
            $t->same('', $entry['inputImplementation'], "Text markup reader {$format} must not register a native reader implementation");
            $t->same(true, $entry['unsupported'], "Text markup reader {$format} should be part of the unsupported verdict");
        }
    },
    'tracks rare text format direction buckets and extension inference without direct parity claims' => static function (TestRunner $t): void {
        $t->same([
            'asciidoc',
            'djot',
            'fb2',
            'haddock',
            'muse',
            'opml',
            'org',
            'pod',
            'rst',
            't2t',
            'textile',
        ], PandocFormatRegistry::rareTextInputFormats());
        $t->same([
            'asciidoc',
            'asciidoc_legacy',
            'asciidoctor',
            'djot',
            'fb2',
            'haddock',
            'markua',
            'muse',
            'opml',
            'org',
            'rst',
            'texinfo',
            'textile',
            'vimdoc',
        ], PandocFormatRegistry::rareTextOutputFormats());
        $t->same([
            'asciidoc',
            'djot',
            'fb2',
            'haddock',
            'muse',
            'opml',
            'org',
            'pod',
            'rst',
            't2t',
            'textile',
            'asciidoc_legacy',
            'asciidoctor',
            'markua',
            'texinfo',
            'vimdoc',
        ], array_keys(PandocFormatRegistry::rareTextFormatDirections()));
        $t->same([
            'asciidoc',
            'djot',
            'fb2',
            'haddock',
            'muse',
            'opml',
            'org',
            'rst',
            'textile',
        ], PandocFormatRegistry::rareTextBidirectionalFormats());
        $t->same(['pod', 't2t'], PandocFormatRegistry::rareTextInputOnlyFormats());
        $t->same([
            'asciidoc_legacy',
            'asciidoctor',
            'markua',
            'texinfo',
            'vimdoc',
        ], PandocFormatRegistry::rareTextOutputOnlyFormats());

        $t->same([
            '.adoc' => 'asciidoc',
            '.asciidoc' => 'asciidoc',
            '.asc' => 'asciidoc',
            '.dj' => 'djot',
            '.djot' => 'djot',
            '.fb2' => 'fb2',
            '.haddock' => 'haddock',
            '.markua' => 'markua',
            '.muse' => 'muse',
            '.opml' => 'opml',
            '.org' => 'org',
            '.pod' => 'pod',
            '.rst' => 'rst',
            '.t2t' => 't2t',
            '.texi' => 'texinfo',
            '.texinfo' => 'texinfo',
            '.textile' => 'textile',
            '.vimdoc' => 'vimdoc',
        ], PandocFormatRegistry::rareTextExtensionInference());
        $t->same('org', PandocFormatRegistry::inferRareTextFormatFromExtension('ORG'));
        $t->same('rst', PandocFormatRegistry::inferRareTextFormatFromExtension('.rst'));
        $t->same('textile', PandocFormatRegistry::inferRareTextFormatFromExtension('textile'));
        $t->same('muse', PandocFormatRegistry::inferRareTextFormatFromExtension('.MUSE'));
        $t->same('asciidoc', PandocFormatRegistry::inferRareTextFormatFromExtension('adoc'));
        $t->same(null, PandocFormatRegistry::inferRareTextFormatFromExtension(''));
        $t->same(null, PandocFormatRegistry::inferRareTextFormatFromExtension('.wiki'));

        $t->same([
            'asciidoc',
            'djot',
            'fb2',
            'haddock',
            'markua',
            'muse',
            'opml',
            'org',
            'pod',
            'rst',
            't2t',
            'texinfo',
            'textile',
            'vimdoc',
        ], PandocFormatRegistry::rareTextFormatsWithExtensionInference());
        $t->same(['asciidoc_legacy', 'asciidoctor'], PandocFormatRegistry::rareTextFormatsWithoutExtensionInference());
        $t->same([
            'asciidoc_legacy' => 'asciidoc',
            'asciidoctor' => 'asciidoc',
        ], PandocFormatRegistry::rareTextOutputAliases());

        foreach (PandocFormatRegistry::rareTextFormatDirections() as $format => $direction) {
            if ($direction['input']) {
                $t->same('unsupported', $direction['inputStatus'], "Rare text input {$format} should not claim native reader parity");
            } else {
                $t->same('not-applicable', $direction['inputStatus'], "Rare text output-only {$format} should not appear as an input token");
            }
            if ($direction['output']) {
                $t->same('unsupported', $direction['outputStatus'], "Rare text output {$format} should not claim native writer parity");
            } else {
                $t->same('not-applicable', $direction['outputStatus'], "Rare text input-only {$format} should not appear as an output token");
            }
        }
    },
    'builds rare text review packets with explicit unsupported diagnostics' => static function (TestRunner $t): void {
        $packet = PandocFormatRegistry::rareTextFormatReviewPacket();
        $summary = PandocFormatRegistry::rareTextUnsupportedFormatSummary();
        $parity = PandocFormatRegistry::rareTextFormatParitySummary();

        $t->same('2026-06-03', $packet['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $packet['upstreamManualUrl']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $packet['upstreamSourceCommit']);
        $t->same(PandocFormatRegistry::rareTextInputFormats(), $packet['inputFormats']);
        $t->same(PandocFormatRegistry::rareTextOutputFormats(), $packet['outputFormats']);
        $t->same([
            'inputOutput' => ['asciidoc', 'djot', 'fb2', 'haddock', 'muse', 'opml', 'org', 'rst', 'textile'],
            'inputOnly' => ['pod', 't2t'],
            'outputOnly' => ['asciidoc_legacy', 'asciidoctor', 'markua', 'texinfo', 'vimdoc'],
        ], $packet['directionBuckets']);
        $t->same(PandocFormatRegistry::rareTextExtensionInference(), $packet['extensionInference']);
        $t->same(PandocFormatRegistry::rareTextOutputAliases(), $packet['outputAliases']);
        $t->same(PandocFormatRegistry::rareTextInputFormats(), $packet['unsupportedInputFormats']);
        $t->same(PandocFormatRegistry::rareTextOutputFormats(), $packet['unsupportedOutputFormats']);
        $t->same($summary, $packet['unsupportedFormatSummary']);
        $t->same($parity, $packet['paritySummary']);

        $t->same([
            'asciidoc',
            'djot',
            'fb2',
            'haddock',
            'muse',
            'opml',
            'org',
            'pod',
            'rst',
            't2t',
            'textile',
            'asciidoc_legacy',
            'asciidoctor',
            'markua',
            'texinfo',
            'vimdoc',
        ], array_keys($packet['formats']));
        $t->same([
            'label' => 'Org mode',
            'input' => true,
            'output' => true,
            'direction' => 'input-output',
            'inputStatus' => 'unsupported',
            'outputStatus' => 'unsupported',
            'extensionInferred' => true,
            'extensions' => ['.org'],
            'inputImplementation' => '',
            'outputImplementation' => '',
        ], $packet['formats']['org']);
        $t->same('reStructuredText', $packet['formats']['rst']['label']);
        $t->same(['.rst'], $packet['formats']['rst']['extensions']);
        $t->same('Textile', $packet['formats']['textile']['label']);
        $t->same(['.textile'], $packet['formats']['textile']['extensions']);
        $t->same('input-only', $packet['formats']['pod']['direction']);
        $t->same('not-applicable', $packet['formats']['pod']['outputStatus']);
        $t->same('output-only', $packet['formats']['markua']['direction']);
        $t->same('not-applicable', $packet['formats']['markua']['inputStatus']);
        $t->same(false, $packet['formats']['asciidoc_legacy']['extensionInferred']);
        $t->same([], $packet['formats']['asciidoc_legacy']['extensions']);

        foreach ($packet['formats'] as $format => $review) {
            $t->same('', $review['inputImplementation'], "Rare text review packet {$format} must not register an input implementation");
            $t->same('', $review['outputImplementation'], "Rare text review packet {$format} must not register an output implementation");
        }
    },
    'summarizes rare text unsupported surfaces without parser claims' => static function (TestRunner $t): void {
        $summary = PandocFormatRegistry::rareTextUnsupportedFormatSummary();
        $directions = PandocFormatRegistry::rareTextFormatDirections();

        $t->same([
            'asciidoc',
            'djot',
            'fb2',
            'haddock',
            'muse',
            'opml',
            'org',
            'pod',
            'rst',
            't2t',
            'textile',
            'asciidoc_legacy',
            'asciidoctor',
            'markua',
            'texinfo',
            'vimdoc',
        ], $summary['anyUnsupported']);
        $t->same(['asciidoc', 'djot', 'fb2', 'haddock', 'muse', 'opml', 'org', 'rst', 'textile'], $summary['unsupportedBoth']);
        $t->same(['pod', 't2t'], $summary['unsupportedInputOnly']);
        $t->same(['asciidoc_legacy', 'asciidoctor', 'markua', 'texinfo', 'vimdoc'], $summary['unsupportedOutputOnly']);
        $t->same(PandocFormatRegistry::unsupportedRareTextInputFormats(), $summary['noNativeReader']);
        $t->same(PandocFormatRegistry::unsupportedRareTextOutputFormats(), $summary['noNativeWriter']);

        foreach ($summary['anyUnsupported'] as $format) {
            $direction = $directions[$format];
            $t->same(true, $direction['inputStatus'] === 'unsupported' || $direction['outputStatus'] === 'unsupported', "Rare text {$format} should expose an unsupported surface");
        }

        $org = PandocFormatRegistry::rareTextUnsupportedFormatForExtension('.ORG');
        $t->same([
            'extension' => '.org',
            'format' => 'org',
            'input' => true,
            'output' => true,
            'direction' => 'input-output',
            'inputStatus' => 'unsupported',
            'outputStatus' => 'unsupported',
            'unsupportedInput' => true,
            'unsupportedOutput' => true,
            'inputImplementation' => '',
            'outputImplementation' => '',
        ], $org);

        $pod = PandocFormatRegistry::rareTextUnsupportedFormatForExtension('pod');
        $t->same('input-only', $pod['direction']);
        $t->same(true, $pod['unsupportedInput']);
        $t->same(false, $pod['unsupportedOutput']);
        $t->same('not-applicable', $pod['outputStatus']);

        $markua = PandocFormatRegistry::rareTextUnsupportedFormatForExtension('.markua');
        $t->same('output-only', $markua['direction']);
        $t->same(false, $markua['unsupportedInput']);
        $t->same(true, $markua['unsupportedOutput']);
        $t->same('not-applicable', $markua['inputStatus']);
        $t->same(null, PandocFormatRegistry::rareTextUnsupportedFormatForExtension('.wiki'));
    },
    'summarizes rare text registry parity counts without registering converters' => static function (TestRunner $t): void {
        $summary = PandocFormatRegistry::rareTextFormatParitySummary();
        $packet = PandocFormatRegistry::rareTextFormatReviewPacket();

        $coreSummary = [
            'totalFormats' => $summary['totalFormats'],
            'inputFormats' => $summary['inputFormats'],
            'outputFormats' => $summary['outputFormats'],
            'inputOutputFormats' => $summary['inputOutputFormats'],
            'inputOnlyFormats' => $summary['inputOnlyFormats'],
            'outputOnlyFormats' => $summary['outputOnlyFormats'],
            'extensionInferenceMappings' => $summary['extensionInferenceMappings'],
            'extensionInferredFormats' => $summary['extensionInferredFormats'],
            'nonExtensionInferredFormats' => $summary['nonExtensionInferredFormats'],
            'outputAliasMappings' => $summary['outputAliasMappings'],
            'unsupportedInputFormats' => $summary['unsupportedInputFormats'],
            'unsupportedOutputFormats' => $summary['unsupportedOutputFormats'],
            'registeredInputImplementations' => $summary['registeredInputImplementations'],
            'registeredOutputImplementations' => $summary['registeredOutputImplementations'],
            'directParityClaimed' => $summary['directParityClaimed'],
        ];
        $t->same([
            'totalFormats' => 16,
            'inputFormats' => 11,
            'outputFormats' => 14,
            'inputOutputFormats' => 9,
            'inputOnlyFormats' => 2,
            'outputOnlyFormats' => 5,
            'extensionInferenceMappings' => 18,
            'extensionInferredFormats' => 14,
            'nonExtensionInferredFormats' => 2,
            'outputAliasMappings' => 2,
            'unsupportedInputFormats' => 11,
            'unsupportedOutputFormats' => 14,
            'registeredInputImplementations' => 0,
            'registeredOutputImplementations' => 0,
            'directParityClaimed' => false,
        ], $coreSummary);
        $t->same(['unsupported' => 11], $summary['inputSupportStatusCounts']);
        $t->same(['unsupported' => 14], $summary['outputSupportStatusCounts']);
        $t->same(false, $summary['directReaderParitySupported']);
        $t->same(false, $summary['directWriterParitySupported']);
        $t->same('unsupported', $summary['directParityStatus']);
        $t->contains('no native PHP rare text reader or writer is registered', $summary['reviewNote']);
        $t->same(count($packet['formats']), $summary['totalFormats']);
        $t->same(count($packet['directionBuckets']['inputOutput']), $summary['inputOutputFormats']);
        $t->same(count($packet['directionBuckets']['inputOnly']), $summary['inputOnlyFormats']);
        $t->same(count($packet['directionBuckets']['outputOnly']), $summary['outputOnlyFormats']);
        $t->same(count($packet['extensionInference']), $summary['extensionInferenceMappings']);
        $t->same(count($packet['unsupportedInputFormats']), $summary['unsupportedInputFormats']);
        $t->same(count($packet['unsupportedOutputFormats']), $summary['unsupportedOutputFormats']);
    },
];
