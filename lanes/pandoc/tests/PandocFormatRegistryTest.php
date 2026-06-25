<?php

declare(strict_types=1);

use PortLibs\Pandoc\BibTexReader;
use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\EpubReader;
use PortLibs\Pandoc\EpubWriter;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\JsonReader;
use PortLibs\Pandoc\JsonWriter;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\OdtReader;
use PortLibs\Pandoc\PandocFormatRegistry;
use PortLibs\Pandoc\PdfReader;
use PortLibs\Pandoc\PptxReader;
use PortLibs\Pandoc\XmlHtmlDom;
use PortLibs\Pandoc\XlsxReader;

return [
    'tracks the current upstream pandoc input format denominator' => static function (TestRunner $t): void {
        $formats = PandocFormatRegistry::upstreamInputFormats();

        $t->same(51, count($formats));
        $t->same($formats, array_values(array_unique($formats)));
        $t->same('2026-06-03', PandocFormatRegistry::UPSTREAM_MANUAL_DATE);
        $t->same('912bfa5e2e3f5c74eb125dfc19404f67c61ca58b', PandocFormatRegistry::UPSTREAM_SOURCE_COMMIT);
        $t->contains('pandoc.org/demo/example2.html', PandocFormatRegistry::UPSTREAM_MANUAL_URL);
        foreach ([
            'asciidoc',
            'bits',
            'commonmark_x',
            'csv',
            'docx',
            'epub',
            'gfm',
            'html',
            'ipynb',
            'json',
            'markdown',
            'native',
            'odt',
            'pptx',
            'typst',
            'xlsx',
            'xml',
        ] as $format) {
            $t->true(in_array($format, $formats, true), "Missing upstream input format {$format}");
        }
        $t->true(!in_array('pdf', $formats, true), 'PDF must not be counted as an upstream Pandoc input format');
    },
    'tracks the current upstream pandoc output format denominator' => static function (TestRunner $t): void {
        $formats = PandocFormatRegistry::upstreamOutputFormats();

        $t->same(75, count($formats));
        $t->same($formats, array_values(array_unique($formats)));
        foreach ([
            'ansi',
            'asciidoctor',
            'bbcode_xenforo',
            'beamer',
            'chunkedhtml',
            'docbook4',
            'docbook5',
            'docx',
            'epub2',
            'epub3',
            'html4',
            'html5',
            'jats_archiving',
            'jats_articleauthoring',
            'jats_publishing',
            'json',
            'markua',
            'opendocument',
            'pdf',
            'plain',
            'pptx',
            'revealjs',
            'tei',
            'texinfo',
            'vimdoc',
            'xwiki',
            'zimwiki',
        ] as $format) {
            $t->true(in_array($format, $formats, true), "Missing upstream output format {$format}");
        }
    },
    'records aliases without removing accepted upstream format tokens' => static function (TestRunner $t): void {
        $inputAliases = PandocFormatRegistry::inputAliases();
        $outputAliases = PandocFormatRegistry::outputAliases();

        $t->same('jats', $inputAliases['bits']);
        $t->same('gfm', $inputAliases['markdown_github']);
        $t->same('asciidoc', $outputAliases['asciidoctor']);
        $t->same('docbook5', $outputAliases['docbook']);
        $t->same('epub3', $outputAliases['epub']);
        $t->same('html', $outputAliases['html5']);
        $t->same('jats_archiving', $outputAliases['jats']);
        foreach ($inputAliases as $alias => $canonical) {
            $t->true(in_array($alias, PandocFormatRegistry::upstreamInputFormats(), true), "Input alias {$alias} must remain a tracked format");
            $t->true(in_array($canonical, PandocFormatRegistry::upstreamInputFormats(), true), "Input canonical {$canonical} must be tracked");
        }
        foreach ($outputAliases as $alias => $canonical) {
            $t->true(in_array($alias, PandocFormatRegistry::upstreamOutputFormats(), true), "Output alias {$alias} must remain a tracked format");
            $t->true(in_array($canonical, PandocFormatRegistry::upstreamOutputFormats(), true), "Output canonical {$canonical} must be tracked");
        }
    },
    'records project local input support separately from upstream pandoc inputs' => static function (TestRunner $t): void {
        $support = PandocFormatRegistry::phpLocalInputSupport();

        $t->same(['pdf'], PandocFormatRegistry::localInputFormats());
        $t->same(['pdf'], array_keys($support));
        $t->same('partial', $support['pdf']['status']);
        $t->same(PdfReader::class, $support['pdf']['implementation']);
        $t->true(!in_array('pdf', PandocFormatRegistry::upstreamInputFormats(), true));
    },
    'maps current php input support against every upstream input token' => static function (TestRunner $t): void {
        $support = PandocFormatRegistry::phpInputSupport();

        $t->same(PandocFormatRegistry::upstreamInputFormats(), array_keys($support));
        $t->same('partial', $support['bibtex']['status']);
        $t->same(BibTexReader::class, $support['bibtex']['implementation']);
        $t->same('partial', $support['biblatex']['status']);
        $t->same(BibTexReader::class, $support['biblatex']['implementation']);
        $t->same('partial', $support['markdown']['status']);
        $t->same(MarkdownReader::class, $support['markdown']['implementation']);
        $t->same('partial', $support['native']['status']);
        $t->same(NativeReader::class, $support['native']['implementation']);
        $t->same('partial', $support['html']['status']);
        $t->same('partial', $support['json']['status']);
        $t->same(JsonReader::class, $support['json']['implementation']);
        $t->same('partial', $support['docx']['status']);
        $t->same(DocxReader::class, $support['docx']['implementation']);
        $t->same('partial', $support['epub']['status']);
        $t->same(EpubReader::class, $support['epub']['implementation']);
        $t->same('partial', $support['odt']['status']);
        $t->same(OdtReader::class, $support['odt']['implementation']);
        $t->same('partial', $support['pptx']['status']);
        $t->same(PptxReader::class, $support['pptx']['implementation']);
        $t->same('partial', $support['xlsx']['status']);
        $t->same(XlsxReader::class, $support['xlsx']['implementation']);
        $t->same(31, count(PandocFormatRegistry::unsupportedInputFormats()));
    },
    'maps current php output support against every upstream output token' => static function (TestRunner $t): void {
        $support = PandocFormatRegistry::phpOutputSupport();

        $t->same(PandocFormatRegistry::upstreamOutputFormats(), array_keys($support));
        $t->same('partial', $support['markdown']['status']);
        $t->same(MarkdownWriter::class, $support['markdown']['implementation']);
        $t->same('partial', $support['html']['status']);
        $t->same(HtmlWriter::class, $support['html']['implementation']);
        $t->same('partial', $support['json']['status']);
        $t->same(JsonWriter::class, $support['json']['implementation']);
        $t->same('partial', $support['epub']['status']);
        $t->same(EpubWriter::class, $support['epub']['implementation']);
        $t->same('partial', $support['epub3']['status']);
        $t->same(EpubWriter::class, $support['epub3']['implementation']);
        $t->same('partial', $support['epub2']['status']);
        $t->same(EpubWriter::class, $support['epub2']['implementation']);
        $t->same('partial', $support['latex']['status']);
        $t->same(LatexWriter::class, $support['latex']['implementation']);
        $t->same('partial', $support['native']['status']);
        $t->same(NativeWriter::class, $support['native']['implementation']);
        $t->same('partial', $support['plain']['status']);
        $t->same('unsupported', $support['docx']['status']);
        $t->same('unsupported', $support['odt']['status']);
        $t->same('unsupported', $support['pdf']['status']);
        $t->same(58, count(PandocFormatRegistry::unsupportedOutputFormats()));
    },
    'tracks xml jats bits review diagnostics without direct reader parity claims' => static function (TestRunner $t): void {
        $support = PandocFormatRegistry::xmlJatsBitsInputSupport();
        $directions = PandocFormatRegistry::xmlJatsBitsFormatDirections();
        $packet = PandocFormatRegistry::xmlJatsBitsDirectReaderCapabilityPacket();
        $expectedFormats = ['xml', 'jats', 'bits'];

        $t->same($expectedFormats, PandocFormatRegistry::xmlJatsBitsInputFormats());
        $t->same($expectedFormats, array_keys($support));
        $t->same($expectedFormats, PandocFormatRegistry::unsupportedXmlJatsBitsInputFormats());
        $t->same($expectedFormats, array_keys($directions));
        $t->same($expectedFormats, $packet['unsupportedDirectReaderFormats']);
        $t->same(3, $packet['unsupportedDirectReaderCount']);
        $t->same(false, $packet['directReaderParitySupported']);
        $t->same(0, $packet['registeredDirectReaderImplementations']);
        $t->same(0, $packet['registeredDirectReaderRecords']);
        $t->same([], $packet['registeredDirectReaderRecordFormats']);
        $t->same(1, $packet['registeredDiagnosticImplementations']);
        $t->same(1, $packet['boundedDiagnosticSurfaceCount']);
        $t->same(['unsupported' => 3], $packet['inputSupportStatusCounts']);
        $t->same(PandocFormatRegistry::xmlJatsBitsLocalEvidenceCounters(), $packet['localEvidenceCounters']);
        $t->contains('no native PHP direct reader parity registered', $packet['reviewNote']);

        foreach ($expectedFormats as $format) {
            $t->same('unsupported', $support[$format]['status']);
            $t->same('', $support[$format]['implementation']);
            $t->contains('No native PHP reader or writer is registered', $support[$format]['notes']);
            $t->same(true, $directions[$format]['input']);
            $t->same(false, $directions[$format]['output']);
            $t->same('input-only', $directions[$format]['direction']);
            $t->same('unsupported', $directions[$format]['inputStatus']);
            $t->same('not-applicable', $directions[$format]['outputStatus']);
            $t->same(false, $packet['formats'][$format]['directReaderParity']);
            $t->same(false, $packet['formats'][$format]['registeredDirectReaderRecord']);
            $t->same('full-direct-reader-missing', $packet['formats'][$format]['unsupportedDirectReaderReason']['code']);
            $t->same('unsupported', $packet['formats'][$format]['unsupportedDirectReaderReason']['status']);
            $t->same(false, $packet['formats'][$format]['unsupportedDirectReaderReason']['directReaderParity']);
        }

        $t->same(XmlHtmlDom::class, $packet['formats']['xml']['diagnosticImplementation']);
        $t->same('summarizeXmlNamespaceUsage', $packet['formats']['xml']['reviewMethod']);
        $t->same('xml-namespace-usage-diagnostics-review-only', $packet['formats']['xml']['reviewPolicy']);
        $t->contains('namespace collision summaries', implode('; ', $packet['formats']['xml']['boundedDiagnostics']));
        foreach ([
            'elementNamespaceCollisionCount',
            'elementNamespaceCollisions',
            'attributeNamespaceCollisionCount',
            'attributeNamespaceCollisions',
            'defaultNamespaceUseCount',
            'defaultNamespaceUris',
            'defaultNamespaceUriCount',
            'defaultNamespaceTransitionCount',
            'defaultNamespaceTransitions',
        ] as $field) {
            $t->true(in_array($field, $packet['formats']['xml']['reviewPacketFields'], true), "XML review packet fields should include {$field}");
        }
        $t->same('', $packet['formats']['jats']['diagnosticImplementation']);
        $t->same('', $packet['formats']['bits']['diagnosticImplementation']);
        $t->same('jats', $packet['formats']['bits']['aliasedTo']);
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
        $t->same('unsupported', $packet['inputStatus']);
        $t->same('', $packet['inputImplementation']);
        $t->same(XmlHtmlDom::class, $packet['diagnosticImplementation']);
        $t->same('summarizeXmlNamespaceUsage', $packet['reviewMethod']);
        $t->same(false, $packet['directReaderParity']);
        $t->same('unsupported', $packet['directReaderParityStatus']);
        $t->same('full-direct-reader-missing', $packet['unsupportedDirectReaderReason']);
        $t->same(false, $packet['unsupportedDirectReaderDiagnostic']['directReaderParity']);
        $t->same(0, $packet['registeredDirectReaderImplementations']);
        $t->same(0, $packet['registeredDirectReaderRecords']);
        $t->same([], $packet['registeredDirectReaderRecordFormats']);
        $t->same(1, $packet['registeredDiagnosticImplementations']);
        $t->same($packet['namespacePrefixFrequencies'], $packet['namespacePrefixFrequencyRows']);
        $t->same($packet['namespaceUriFrequencies'], $packet['namespaceUriFrequencyRows']);

        foreach ([
            'elementNamespaceCollisionCount',
            'elementNamespaceCollisions',
            'attributeNamespaceCollisionCount',
            'attributeNamespaceCollisions',
            'defaultNamespaceTransitionCount',
            'defaultNamespaceTransitions',
        ] as $field) {
            $t->true(in_array($field, $packet['reviewPacketFields'], true), "XML registry review packet fields should include {$field}");
        }

        $t->same(1, $packet['elementNamespaceCollisionCount']);
        $t->same(1, $packet['attributeNamespaceCollisionCount']);
        $t->same(3, $packet['defaultNamespaceTransitionCount']);
        $t->same(5, $packet['defaultNamespaceUseCount']);
        $t->same(['urn:group', 'urn:root'], $packet['defaultNamespaceUris']);
        $t->same(2, $packet['sameUriMultiplePrefixCount']);
        $t->same(['a', 'b'], $sameUriAliases['urn:item-b']['prefixes'] ?? null);
        $t->same(['default', 'rootAlias'], $sameUriAliases['urn:root']['prefixes'] ?? null);
        $t->same(2, $packet['samePrefixMultipleUriCount']);
        $t->same(['urn:item-a', 'urn:item-b'], $samePrefixAliases['a']['namespaceUris'] ?? null);
        $t->same(['urn:group', 'urn:root'], $samePrefixAliases['default']['namespaceUris'] ?? null);
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
];
