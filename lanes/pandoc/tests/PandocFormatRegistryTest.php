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
];
