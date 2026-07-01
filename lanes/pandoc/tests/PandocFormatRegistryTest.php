<?php

declare(strict_types=1);

use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\BibliographyReader;
use PortLibs\Pandoc\DelimitedTextReader;
use PortLibs\Pandoc\DocBookReader;
use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\EpubReader;
use PortLibs\Pandoc\EpubWriter;
use PortLibs\Pandoc\Fb2Reader;
use PortLibs\Pandoc\HtmlReader;
use PortLibs\Pandoc\IpynbReader;
use PortLibs\Pandoc\JiraReader;
use PortLibs\Pandoc\JsonReader;
use PortLibs\Pandoc\JsonWriter;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\LegacyDocReader;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\OdtReader;
use PortLibs\Pandoc\OpmlReader;
use PortLibs\Pandoc\OpmlWriter;
use PortLibs\Pandoc\PandocFormatRegistry;
use PortLibs\Pandoc\PdfReader;
use PortLibs\Pandoc\PlainWriter;
use PortLibs\Pandoc\PptxReader;
use PortLibs\Pandoc\RichPackageUnsupportedFormatRegistry;
use PortLibs\Pandoc\RtfReader;
use PortLibs\Pandoc\XmlReader;
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

        $t->same(['pdf', 'doc'], PandocFormatRegistry::localInputFormats());
        $t->same(['pdf', 'doc'], array_keys($support));
        $t->same('partial', $support['pdf']['status']);
        $t->same(PdfReader::class, $support['pdf']['implementation']);
        $t->same('partial', $support['doc']['status']);
        $t->same(LegacyDocReader::class, $support['doc']['implementation']);
        $t->true(!in_array('pdf', PandocFormatRegistry::upstreamInputFormats(), true));
        $t->true(!in_array('doc', PandocFormatRegistry::upstreamInputFormats(), true));
    },
    'maps current php input support against every upstream input token' => static function (TestRunner $t): void {
        $support = PandocFormatRegistry::phpInputSupport();

        $t->same(PandocFormatRegistry::upstreamInputFormats(), array_keys($support));
        $t->same('partial', $support['markdown']['status']);
        $t->same(MarkdownReader::class, $support['markdown']['implementation']);
        foreach (['bibtex', 'biblatex', 'csljson', 'endnotexml', 'ris'] as $format) {
            $t->same('partial', $support[$format]['status']);
            $t->same(BibliographyReader::class, $support[$format]['implementation']);
        }
        foreach (['bits', 'jats', 'xml'] as $format) {
            $t->same('partial', $support[$format]['status']);
            $t->same(XmlReader::class, $support[$format]['implementation']);
        }
        $t->same('partial', $support['native']['status']);
        $t->same(NativeReader::class, $support['native']['implementation']);
        $t->same('partial', $support['html']['status']);
        $t->same(HtmlReader::class, $support['html']['implementation']);
        $t->same('partial', $support['docbook']['status']);
        $t->same(DocBookReader::class, $support['docbook']['implementation']);
        $t->same('partial', $support['ipynb']['status']);
        $t->same(IpynbReader::class, $support['ipynb']['implementation']);
        $t->same('partial', $support['json']['status']);
        $t->same(JsonReader::class, $support['json']['implementation']);
        $t->same('partial', $support['jira']['status']);
        $t->same(JiraReader::class, $support['jira']['implementation']);
        $t->same('partial', $support['csv']['status']);
        $t->same(DelimitedTextReader::class, $support['csv']['implementation']);
        $t->same('partial', $support['tsv']['status']);
        $t->same(DelimitedTextReader::class, $support['tsv']['implementation']);
        $t->same('partial', $support['docx']['status']);
        $t->same(DocxReader::class, $support['docx']['implementation']);
        $t->same('partial', $support['epub']['status']);
        $t->same(EpubReader::class, $support['epub']['implementation']);
        $t->same('partial', $support['fb2']['status']);
        $t->same(Fb2Reader::class, $support['fb2']['implementation']);
        $t->same('partial', $support['odt']['status']);
        $t->same(OdtReader::class, $support['odt']['implementation']);
        $t->same('partial', $support['opml']['status']);
        $t->same(OpmlReader::class, $support['opml']['implementation']);
        $t->same('partial', $support['pptx']['status']);
        $t->same(PptxReader::class, $support['pptx']['implementation']);
        $t->same('partial', $support['rtf']['status']);
        $t->same(RtfReader::class, $support['rtf']['implementation']);
        $t->same('partial', $support['xlsx']['status']);
        $t->same(XlsxReader::class, $support['xlsx']['implementation']);
        $t->same(18, count(PandocFormatRegistry::unsupportedInputFormats()));
    },
    'maps current php output support against every upstream output token' => static function (TestRunner $t): void {
        $support = PandocFormatRegistry::phpOutputSupport();

        $t->same(PandocFormatRegistry::upstreamOutputFormats(), array_keys($support));
        $t->same('partial', $support['markdown']['status']);
        $t->same(MarkdownWriter::class, $support['markdown']['implementation']);
        $t->same('partial', $support['html']['status']);
        $t->same(HtmlWriter::class, $support['html']['implementation']);
        $t->same('partial', $support['epub']['status']);
        $t->same(EpubWriter::class, $support['epub']['implementation']);
        $t->same('partial', $support['epub3']['status']);
        $t->same(EpubWriter::class, $support['epub3']['implementation']);
        $t->same('partial', $support['json']['status']);
        $t->same(JsonWriter::class, $support['json']['implementation']);
        $t->same('partial', $support['latex']['status']);
        $t->same(LatexWriter::class, $support['latex']['implementation']);
        $t->same('partial', $support['native']['status']);
        $t->same(NativeWriter::class, $support['native']['implementation']);
        $t->same('partial', $support['opml']['status']);
        $t->same(OpmlWriter::class, $support['opml']['implementation']);
        $t->same('partial', $support['plain']['status']);
        $t->same(PlainWriter::class, $support['plain']['implementation']);
        $t->same('unsupported', $support['docx']['status']);
        $t->same('unsupported', $support['epub2']['status']);
        $t->same('unsupported', $support['odt']['status']);
        $t->same('unsupported', $support['pdf']['status']);
        $t->same(58, count(PandocFormatRegistry::unsupportedOutputFormats()));
    },
    'tracks wiki format registry status without claiming direct parity' => static function (TestRunner $t): void {
        $registry = PandocFormatRegistry::wikiFormatRegistry();

        $t->same(['creole', 'dokuwiki', 'jira', 'mediawiki', 'tikiwiki', 'twiki', 'vimwiki'], PandocFormatRegistry::wikiInputFormats());
        $t->same(['dokuwiki', 'jira', 'mediawiki', 'xwiki', 'zimwiki'], PandocFormatRegistry::wikiOutputFormats());
        $t->same(['creole', 'dokuwiki', 'jira', 'mediawiki', 'tikiwiki', 'twiki', 'vimwiki', 'xwiki', 'zimwiki'], array_keys($registry));
        $t->same(['dokuwiki' => 'dokuwiki', 'wiki' => 'mediawiki'], PandocFormatRegistry::wikiExtensionInference());
        $t->same('dokuwiki', PandocFormatRegistry::inferWikiFormatFromExtension('.DOKUWIKI'));
        $t->same('mediawiki', PandocFormatRegistry::inferWikiFormatFromExtension(' wiki '));
        $t->same(null, PandocFormatRegistry::inferWikiFormatFromExtension('vimwiki'));

        foreach (['dokuwiki', 'jira', 'mediawiki'] as $format) {
            $t->same('input-output', $registry[$format]['direction'], "{$format} should be tracked in both wiki directions");
        }

        foreach (['creole', 'tikiwiki', 'twiki', 'vimwiki'] as $format) {
            $t->same('input-only', $registry[$format]['direction'], "{$format} should be an input-only wiki token");
            $t->same('not-applicable', $registry[$format]['output']['status']);
            $t->same(false, $registry[$format]['directWriterParityClaimed']);
        }

        foreach (['xwiki', 'zimwiki'] as $format) {
            $t->same('output-only', $registry[$format]['direction'], "{$format} should be an output-only wiki token");
            $t->same('not-applicable', $registry[$format]['input']['status']);
            $t->same(false, $registry[$format]['directReaderParityClaimed']);
        }

        foreach (['creole', 'dokuwiki', 'mediawiki', 'tikiwiki', 'twiki', 'vimwiki'] as $format) {
            $t->same('unsupported', $registry[$format]['input']['status'], "{$format} reader remains unsupported");
            $t->same('', $registry[$format]['input']['implementation']);
            $t->same(false, $registry[$format]['directReaderParityClaimed']);
        }

        $t->same('partial', $registry['jira']['input']['status']);
        $t->same(JiraReader::class, $registry['jira']['input']['implementation']);
        $t->same(false, $registry['jira']['directReaderParityClaimed']);

        foreach (['dokuwiki', 'jira', 'mediawiki', 'xwiki', 'zimwiki'] as $format) {
            $t->same('unsupported', $registry[$format]['output']['status'], "{$format} writer remains unsupported");
            $t->same('', $registry[$format]['output']['implementation']);
            $t->same(false, $registry[$format]['directWriterParityClaimed']);
        }

        $t->same(['dokuwiki'], $registry['dokuwiki']['extensionInferences']);
        $t->same(['wiki'], $registry['mediawiki']['extensionInferences']);
        $t->same([], $registry['vimwiki']['extensionInferences']);
    },
    'summarizes wiki registry direction and support buckets' => static function (TestRunner $t): void {
        $summary = PandocFormatRegistry::wikiFormatRegistrySummary();

        $t->same(7, count($summary['inputFormats']));
        $t->same(5, count($summary['outputFormats']));
        $t->same(9, count($summary['uniqueFormats']));
        $t->same(['dokuwiki', 'jira', 'mediawiki'], $summary['directionBuckets']['input-output']);
        $t->same(['creole', 'tikiwiki', 'twiki', 'vimwiki'], $summary['directionBuckets']['input-only']);
        $t->same(['xwiki', 'zimwiki'], $summary['directionBuckets']['output-only']);
        $t->same(['jira'], $summary['inputStatusBuckets']['partial']);
        $t->same(['creole', 'dokuwiki', 'mediawiki', 'tikiwiki', 'twiki', 'vimwiki'], $summary['inputStatusBuckets']['unsupported']);
        $t->same(['dokuwiki', 'jira', 'mediawiki', 'xwiki', 'zimwiki'], $summary['outputStatusBuckets']['unsupported']);
        $t->same(['dokuwiki' => 'dokuwiki', 'wiki' => 'mediawiki'], $summary['extensionInference']);
        $t->same(false, $summary['directReaderParityClaimed']);
        $t->same(false, $summary['directWriterParityClaimed']);
    },
    'builds a compact wiki format review packet from registry status' => static function (TestRunner $t): void {
        $packet = PandocFormatRegistry::wikiFormatReviewPacket();

        $t->same(PandocFormatRegistry::wikiInputFormats(), $packet['inputFormats']);
        $t->same(PandocFormatRegistry::wikiOutputFormats(), $packet['outputFormats']);
        $t->same(['creole', 'dokuwiki', 'jira', 'mediawiki', 'tikiwiki', 'twiki', 'vimwiki', 'xwiki', 'zimwiki'], $packet['uniqueFormats']);
        $t->same(['dokuwiki', 'jira', 'mediawiki'], $packet['directionBuckets']['input-output']);
        $t->same(['creole', 'tikiwiki', 'twiki', 'vimwiki'], $packet['directionBuckets']['input-only']);
        $t->same(['xwiki', 'zimwiki'], $packet['directionBuckets']['output-only']);
        $t->same(['dokuwiki' => 'dokuwiki', 'wiki' => 'mediawiki'], $packet['extensionInference']);

        $t->same(['creole', 'dokuwiki', 'mediawiki', 'tikiwiki', 'twiki', 'vimwiki'], $packet['unsupportedInputs']);
        $t->same(['dokuwiki', 'jira', 'mediawiki', 'xwiki', 'zimwiki'], $packet['unsupportedOutputs']);
        $t->same(['jira'], $packet['partialInputs']);
        $t->same([], $packet['partialOutputs']);
        $t->same(false, $packet['directReaderParityClaimed']);
        $t->same(false, $packet['directWriterParityClaimed']);

        $t->same('Jira wiki markup', $packet['formats']['jira']['label']);
        $t->same('input-output', $packet['formats']['jira']['direction']);
        $t->same('partial', $packet['formats']['jira']['inputStatus']);
        $t->same(JiraReader::class, $packet['formats']['jira']['inputImplementation']);
        $t->same('unsupported', $packet['formats']['jira']['outputStatus']);
        $t->same('', $packet['formats']['jira']['outputImplementation']);
        $t->same(false, $packet['formats']['jira']['directReaderParityClaimed']);
        $t->same(false, $packet['formats']['jira']['directWriterParityClaimed']);

        $t->same(['wiki'], $packet['formats']['mediawiki']['extensionInferences']);
        $t->same('unsupported', $packet['formats']['mediawiki']['inputStatus']);
        $t->same('unsupported', $packet['formats']['mediawiki']['outputStatus']);
        $t->same('', $packet['formats']['mediawiki']['inputImplementation']);
        $t->same('', $packet['formats']['mediawiki']['outputImplementation']);
        $t->same('not-applicable', $packet['formats']['xwiki']['inputStatus']);
        $t->same('unsupported', $packet['formats']['xwiki']['outputStatus']);
        $t->same([], $packet['formats']['vimwiki']['extensionInferences']);
    },
    'builds roff manual registry review packet without claiming direct parity' => static function (TestRunner $t): void {
        $registry = PandocFormatRegistry::roffManualFormatRegistry();
        $summary = PandocFormatRegistry::roffManualFormatRegistrySummary();
        $packet = PandocFormatRegistry::roffManualFormatReviewPacket();

        $t->same(['man', 'mdoc'], PandocFormatRegistry::roffManualInputFormats());
        $t->same(['man', 'ms'], PandocFormatRegistry::roffManualOutputFormats());
        $t->same([
            '.ms' => 'ms',
            '.roff' => 'ms',
            '.[1-9]' => 'man',
        ], PandocFormatRegistry::roffManualExtensionInference());
        $t->same('ms', PandocFormatRegistry::inferRoffManualFormatFromExtension('.ms'));
        $t->same('ms', PandocFormatRegistry::inferRoffManualFormatFromExtension('MS'));
        $t->same('ms', PandocFormatRegistry::inferRoffManualFormatFromExtension(' roff '));
        $t->same('man', PandocFormatRegistry::inferRoffManualFormatFromExtension('.1'));
        $t->same('man', PandocFormatRegistry::inferRoffManualFormatFromExtension('9'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension(''));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.0'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.10'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.mdoc'));

        $t->same(['man', 'mdoc', 'ms'], array_keys($registry));
        $t->same('input-output', $registry['man']['direction']);
        $t->same('input-only', $registry['mdoc']['direction']);
        $t->same('output-only', $registry['ms']['direction']);
        $t->same('unsupported', $registry['man']['input']['status']);
        $t->same('unsupported', $registry['man']['output']['status']);
        $t->same('unsupported', $registry['mdoc']['input']['status']);
        $t->same('not-applicable', $registry['mdoc']['output']['status']);
        $t->same('not-applicable', $registry['ms']['input']['status']);
        $t->same('unsupported', $registry['ms']['output']['status']);
        $t->same('', $registry['man']['input']['implementation']);
        $t->same('', $registry['man']['output']['implementation']);
        $t->same('', $registry['mdoc']['input']['implementation']);
        $t->same('', $registry['ms']['output']['implementation']);
        $t->contains('upstream man reader token', $registry['man']['input']['notes']);
        $t->contains('manual-family input token', $registry['mdoc']['input']['notes']);
        $t->contains('.ms/.roff extension inference', $registry['ms']['output']['notes']);
        $t->same(['.[1-9]'], $registry['man']['extensionInferences']);
        $t->same([], $registry['mdoc']['extensionInferences']);
        $t->same(['.ms', '.roff'], $registry['ms']['extensionInferences']);
        $t->same(false, $registry['man']['directReaderParityClaimed']);
        $t->same(false, $registry['man']['directWriterParityClaimed']);
        $t->same(false, $registry['mdoc']['directWriterParityClaimed']);
        $t->same(false, $registry['ms']['directReaderParityClaimed']);

        $t->same(['man'], $summary['directionBuckets']['input-output']);
        $t->same(['mdoc'], $summary['directionBuckets']['input-only']);
        $t->same(['ms'], $summary['directionBuckets']['output-only']);
        $t->same(['man', 'mdoc'], $summary['inputStatusBuckets']['unsupported']);
        $t->same(['man', 'ms'], $summary['outputStatusBuckets']['unsupported']);
        $t->same(false, $summary['directReaderParityClaimed']);
        $t->same(false, $summary['directWriterParityClaimed']);

        $t->same('2026-06-03', $packet['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $packet['upstreamManualUrl']);
        $t->same('912bfa5e2e3f5c74eb125dfc19404f67c61ca58b', $packet['upstreamSourceCommit']);
        $t->same(PandocFormatRegistry::roffManualInputFormats(), $packet['inputFormats']);
        $t->same(PandocFormatRegistry::roffManualOutputFormats(), $packet['outputFormats']);
        $t->same(['man', 'mdoc', 'ms'], $packet['uniqueFormats']);
        $t->same(['man', 'mdoc'], $packet['unsupportedInputs']);
        $t->same(['man', 'ms'], $packet['unsupportedOutputs']);
        $t->same([], $packet['partialInputs']);
        $t->same([], $packet['partialOutputs']);
        $t->same(false, $packet['directReaderParityClaimed']);
        $t->same(false, $packet['directWriterParityClaimed']);
        $t->same('roff man manual page', $packet['formats']['man']['label']);
        $t->same('input-output', $packet['formats']['man']['direction']);
        $t->same('unsupported', $packet['formats']['man']['inputStatus']);
        $t->same('unsupported', $packet['formats']['man']['outputStatus']);
        $t->same(['.[1-9]'], $packet['formats']['man']['extensionInferences']);
        $t->same('not-applicable', $packet['formats']['mdoc']['outputStatus']);
        $t->same('not-applicable', $packet['formats']['ms']['inputStatus']);
        $t->same(['.ms', '.roff'], $packet['formats']['ms']['extensionInferences']);

        foreach ($packet['formats'] as $format => $review) {
            $t->same('', $review['inputImplementation'], "Roff/manual {$format} must not register an input implementation");
            $t->same('', $review['outputImplementation'], "Roff/manual {$format} must not register an output implementation");
        }
    },
    'reports wiki direct format ship gate blockers from registry status' => static function (TestRunner $t): void {
        $gate = PandocFormatRegistry::wikiFormatShipGate();

        $t->same('wiki', $gate['family']);
        $t->same('PandocFormatRegistry::wikiFormatReviewPacket', $gate['source']);
        $t->same(false, $gate['shippable']);
        $t->same('blocked', $gate['directParityStatus']);
        $t->same('blocked', $gate['readerStatus']);
        $t->same('blocked', $gate['writerStatus']);
        $t->same(7, $gate['acceptedInputFormatCount']);
        $t->same(5, $gate['acceptedOutputFormatCount']);
        $t->same(9, $gate['uniqueFormatCount']);
        $t->same(false, $gate['directReaderParityClaimed']);
        $t->same(false, $gate['directWriterParityClaimed']);
        $t->same([], $gate['directReaderCompleteFormats']);
        $t->same([], $gate['directWriterCompleteFormats']);
        $t->same(['creole', 'dokuwiki', 'jira', 'mediawiki', 'tikiwiki', 'twiki', 'vimwiki'], $gate['readerBlockingFormats']);
        $t->same(['dokuwiki', 'jira', 'mediawiki', 'xwiki', 'zimwiki'], $gate['writerBlockingFormats']);
        $t->same(7, $gate['readerBlockingFormatCount']);
        $t->same(5, $gate['writerBlockingFormatCount']);
        $t->same(['creole', 'dokuwiki', 'mediawiki', 'tikiwiki', 'twiki', 'vimwiki'], $gate['unsupportedInputs']);
        $t->same(['jira'], $gate['partialInputs']);
        $t->same(['dokuwiki', 'jira', 'mediawiki', 'xwiki', 'zimwiki'], $gate['unsupportedOutputs']);
        $t->same([], $gate['partialOutputs']);
        $t->same([
            'native PHP wiki readers for every accepted upstream wiki input format',
            'native PHP wiki writers for every accepted upstream wiki output format',
            'focused direct-format fixtures without invoking Pandoc or external wiki renderers',
        ], $gate['activationRequirements']);
        $t->same([
            'wiki-reader-parity-incomplete',
            'wiki-writer-parity-incomplete',
            'wiki-format-registry-accounting-only',
        ], $gate['diagnostics']);
    },
    'summarizes rich package unsupported format buckets without converter claims' => static function (TestRunner $t): void {
        $summary = PandocFormatRegistry::richPackageUnsupportedFormatSummary();

        $t->same(RichPackageUnsupportedFormatRegistry::richPackageFormats(), $summary['uniqueFormats']);
        $t->same(['docx', 'odt', 'epub', 'ipynb', 'pptx', 'xlsx'], $summary['inputFormats']);
        $t->same([
            'docx',
            'odt',
            'opendocument',
            'epub',
            'epub2',
            'epub3',
            'ipynb',
            'pptx',
            'chunkedhtml',
            'icml',
            'pdf',
        ], $summary['outputFormats']);
        $t->same(['docx', 'odt', 'epub', 'ipynb', 'pptx', 'xlsx'], $summary['directInputFormats']);
        $t->same(['epub', 'epub3'], $summary['directOutputFormats']);
        $t->same([], $summary['unsupportedInputFormats']);
        $t->same([
            'docx',
            'odt',
            'opendocument',
            'epub2',
            'ipynb',
            'pptx',
            'chunkedhtml',
            'icml',
            'pdf',
        ], $summary['unsupportedOutputFormats']);
        $t->same([], $summary['unsupportedBothFormats']);
        $t->same(['docx', 'odt', 'ipynb', 'pptx'], $summary['partialInputUnsupportedOutputFormats']);
        $t->same([], $summary['inputOnlyUnsupportedFormats']);
        $t->same(['opendocument', 'epub2', 'chunkedhtml', 'icml', 'pdf'], $summary['outputOnlyUnsupportedFormats']);
        $t->same(['opendocument', 'epub2', 'chunkedhtml', 'icml', 'pdf'], $summary['noNativeReaderWriterFormats']);
        $t->same(['doc', 'ods', 'odp', 'zip'], $summary['sourceAliasUnsupportedExtensions']);
        $t->same(['output'], $summary['extensionUnsupportedDirections']['.docx']);
        $t->same(['output'], $summary['extensionUnsupportedDirections']['.epub']);
        $t->same(['output'], $summary['extensionUnsupportedDirections']['.pdf']);
        $t->same(false, array_key_exists('.xlsx', $summary['extensionUnsupportedDirections']));
        $t->same(false, $summary['directReaderParityClaimed']);
        $t->same(false, $summary['directWriterParityClaimed']);
        $t->same(true, $summary['externalToolFree']);
    },
    'builds rich package review packet from direct format support maps' => static function (TestRunner $t): void {
        $packet = PandocFormatRegistry::richPackageFormatReviewPacket();

        $t->same(RichPackageUnsupportedFormatRegistry::UPSTREAM_COMMIT, $packet['upstreamCommit']);
        $t->same(PandocFormatRegistry::UPSTREAM_MANUAL_DATE, $packet['upstreamManualDate']);
        $t->same(PandocFormatRegistry::UPSTREAM_SOURCE_COMMIT, $packet['upstreamSourceCommit']);
        $t->same($packet['summary'], PandocFormatRegistry::richPackageUnsupportedFormatSummary());
        $t->same(12, $packet['registryReport']['denominators']['richPackageFormats']);
        $t->same(['supported' => 6, 'unsupported' => 0, 'total' => 6], $packet['registryReport']['directSupport']['input']);
        $t->same(['supported' => 2, 'unsupported' => 9, 'total' => 11], $packet['registryReport']['directSupport']['output']);
        $t->same(['docx', 'odt', 'epub', 'ipynb', 'pptx', 'xlsx'], array_keys($packet['phpInputSupport']));
        $t->same(DocxReader::class, $packet['phpInputSupport']['docx']['implementation']);
        $t->same(IpynbReader::class, $packet['phpInputSupport']['ipynb']['implementation']);
        $t->same(XlsxReader::class, $packet['phpInputSupport']['xlsx']['implementation']);
        $t->same('unsupported', $packet['phpOutputSupport']['docx']['status']);
        $t->same('partial', $packet['phpOutputSupport']['epub3']['status']);
        $t->same(EpubWriter::class, $packet['phpOutputSupport']['epub3']['implementation']);
        $t->same('unsupported', $packet['phpOutputSupport']['pdf']['status']);
        $t->contains('No native PHP reader or writer is registered', $packet['phpOutputSupport']['pdf']['notes']);
        $t->same(['doc', 'ods', 'odp', 'zip'], array_column($packet['sourceAliasDiagnostics'], 'extension'));
        $t->contains('container-preflight-only', implode(',', $packet['sourceAliasDiagnostics'][3]['diagnostics']));
        $t->same(['.docx', '.epub', '.fodt', '.icml', '.ipynb', '.odt', '.pdf', '.pptx'], array_column($packet['extensionDiagnostics'], 'extension'));
        $t->same(['output'], $packet['extensionDiagnostics'][6]['unsupportedDirections']);
        $t->same(true, $packet['externalToolFree']);
    },
];
