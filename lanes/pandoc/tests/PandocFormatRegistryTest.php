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
    'builds wiki-family output token taxonomy without writer parity claims' => static function (TestRunner $t): void {
        $directions = PandocFormatRegistry::wikiFormatDirections();
        $surfaces = PandocFormatRegistry::wikiOutputUnsupportedSurfaces();
        $summary = PandocFormatRegistry::wikiUnsupportedFormatSummary();
        $parity = PandocFormatRegistry::wikiFormatParitySummary();
        $packet = PandocFormatRegistry::wikiFormatReviewPacket();

        $t->same(['creole', 'dokuwiki', 'jira', 'mediawiki', 'tikiwiki', 'twiki', 'vimwiki', 'xwiki', 'zimwiki'], array_keys($directions));
        $t->same('input-output', $directions['dokuwiki']['direction']);
        $t->same('input-output', $directions['jira']['direction']);
        $t->same('output-only', $directions['xwiki']['direction']);
        $t->same('unsupported', $directions['mediawiki']['outputStatus']);
        $t->same('not-applicable', $directions['zimwiki']['inputStatus']);

        $t->same(['creole', 'dokuwiki', 'mediawiki', 'tikiwiki', 'twiki', 'vimwiki'], PandocFormatRegistry::unsupportedWikiInputFormats());
        $t->same(['dokuwiki', 'jira', 'mediawiki', 'xwiki', 'zimwiki'], PandocFormatRegistry::unsupportedWikiOutputFormats());
        $t->same(['dokuwiki', 'jira', 'mediawiki', 'xwiki', 'zimwiki'], array_keys($surfaces));
        $t->same('wiki-writer-not-implemented', $surfaces['mediawiki']['unsupportedReason']);
        $t->same(['writer-component-missing', 'external-wiki-converter-disallowed'], $surfaces['mediawiki']['diagnostics']);
        $t->same('', $surfaces['mediawiki']['outputImplementation']);
        $t->same(false, $surfaces['mediawiki']['directWriterParitySupported']);
        $t->same(true, $surfaces['mediawiki']['externalToolFree']);
        $t->same(['wiki'], $surfaces['mediawiki']['extensionInferences']);
        $t->same([], $surfaces['xwiki']['extensionInferences']);
        $t->same('output-only', $surfaces['xwiki']['direction']);
        $t->same('', $surfaces['xwiki']['inputImplementation']);

        $t->same([
            'anyUnsupported' => ['creole', 'dokuwiki', 'jira', 'mediawiki', 'tikiwiki', 'twiki', 'vimwiki', 'xwiki', 'zimwiki'],
            'unsupportedBoth' => ['dokuwiki', 'mediawiki'],
            'unsupportedInputOnly' => ['creole', 'tikiwiki', 'twiki', 'vimwiki'],
            'unsupportedOutputOnly' => ['xwiki', 'zimwiki'],
            'unsupportedOutputTokens' => ['dokuwiki', 'jira', 'mediawiki', 'xwiki', 'zimwiki'],
            'noNativeReader' => ['creole', 'dokuwiki', 'mediawiki', 'tikiwiki', 'twiki', 'vimwiki'],
            'noNativeWriter' => ['dokuwiki', 'jira', 'mediawiki', 'xwiki', 'zimwiki'],
        ], $summary);

        $t->same(9, $parity['uniqueFormatCount']);
        $t->same(7, $parity['inputFormatCount']);
        $t->same(5, $parity['outputFormatCount']);
        $t->same(['inputOutput' => 3, 'inputOnly' => 4, 'outputOnly' => 2], $parity['directionCounts']);
        $t->same(['partial' => 1, 'unsupported' => 6], $parity['inputSupportStatusCounts']);
        $t->same(['unsupported' => 5], $parity['outputSupportStatusCounts']);
        $t->same(2, $parity['extensionInferenceMappings']);
        $t->same(5, $parity['unsupportedOutputSurfaceMappings']);
        $t->same(1, $parity['registeredInputImplementations']);
        $t->same(0, $parity['registeredOutputImplementations']);
        $t->same(true, $parity['externalToolFree']);
        $t->same(false, $parity['directWriterParitySupported']);
        $t->same('unsupported', $parity['directParityStatus']);
        $t->contains('no PHP wiki writer', $parity['reviewNote']);

        $t->same('2026-06-03', $packet['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $packet['upstreamManualUrl']);
        $t->same(true, $packet['externalToolFree']);
        $t->same(['dokuwiki', 'jira', 'mediawiki'], $packet['directionBuckets']['inputOutput']);
        $t->same(['creole', 'tikiwiki', 'twiki', 'vimwiki'], $packet['directionBuckets']['inputOnly']);
        $t->same(['xwiki', 'zimwiki'], $packet['directionBuckets']['outputOnly']);
        $t->same($summary, $packet['unsupportedFormatSummary']);
        $t->same($parity, $packet['paritySummary']);
        $t->same($surfaces, $packet['unsupportedOutputSurfaces']);
        $t->same([
            'input' => true,
            'output' => true,
            'direction' => 'input-output',
            'inputStatus' => 'unsupported',
            'outputStatus' => 'unsupported',
            'extensionInferred' => true,
            'extensions' => ['wiki'],
            'inputImplementation' => '',
            'outputImplementation' => '',
        ], $packet['formats']['mediawiki']);
        $t->same(false, $packet['formats']['zimwiki']['extensionInferred']);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
    'tracks roff manual reader writer and extension inference registry metadata' => static function (TestRunner $t): void {
        $t->same(['man', 'mdoc'], PandocFormatRegistry::roffManualInputFormats());
        $t->same(['man', 'ms'], PandocFormatRegistry::roffManualOutputFormats());
        $t->same(PandocFormatRegistry::roffManualInputFormats(), PandocFormatRegistry::roffInputFormats());
        $t->same(PandocFormatRegistry::roffManualOutputFormats(), PandocFormatRegistry::roffOutputFormats());
        $t->same([
            '.ms' => 'ms',
            '.roff' => 'ms',
            '.[1-9]' => 'man',
            '.[1-9][a-z]+' => 'man',
        ], PandocFormatRegistry::roffManualExtensionInference());
        $t->same(PandocFormatRegistry::roffManualExtensionInference(), PandocFormatRegistry::roffExtensionInference());

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
        $t->same(18, count(PandocFormatRegistry::unsupportedInputFormats()));
        $t->same(58, count(PandocFormatRegistry::unsupportedOutputFormats()));
    },
    'tracks roff manual direction buckets without direct parity claims' => static function (TestRunner $t): void {
        $registry = PandocFormatRegistry::roffManualFormatRegistry();
        $directions = PandocFormatRegistry::roffManualFormatDirections();
        $summary = PandocFormatRegistry::roffManualFormatRegistrySummary();

        $t->same(PandocFormatRegistry::roffManualFormatRegistry(), PandocFormatRegistry::roffFormatRegistry());
        $t->same(PandocFormatRegistry::roffManualFormatRegistrySummary(), PandocFormatRegistry::roffFormatRegistrySummary());
        $t->same(['man', 'mdoc', 'ms'], array_keys($registry));
        $t->same(['man', 'mdoc', 'ms'], array_keys($directions));
        $t->same('input-output', $registry['man']['direction']);
        $t->same('input-only', $registry['mdoc']['direction']);
        $t->same('output-only', $registry['ms']['direction']);
        $t->same('input-output', $directions['man']['direction']);
        $t->same('input-only', $directions['mdoc']['direction']);
        $t->same('output-only', $directions['ms']['direction']);
        $t->same(['man'], $summary['directionBuckets']['input-output']);
        $t->same(['mdoc'], $summary['directionBuckets']['input-only']);
        $t->same(['ms'], $summary['directionBuckets']['output-only']);
        $t->same(['man', 'mdoc'], $summary['inputStatusBuckets']['unsupported']);
        $t->same(['man', 'ms'], $summary['outputStatusBuckets']['unsupported']);
        $t->same(['.[1-9]', '.[1-9][a-z]+'], $registry['man']['extensionInferences']);
        $t->same([], $registry['mdoc']['extensionInferences']);
        $t->same(['.ms', '.roff'], $registry['ms']['extensionInferences']);

        foreach (['man', 'mdoc'] as $format) {
            $t->same('unsupported', $registry[$format]['input']['status']);
            $t->same('', $registry[$format]['input']['implementation']);
            $t->same(false, $registry[$format]['directReaderParityClaimed']);
        }
        foreach (['man', 'ms'] as $format) {
            $t->same('unsupported', $registry[$format]['output']['status']);
            $t->same('', $registry[$format]['output']['implementation']);
            $t->same(false, $registry[$format]['directWriterParityClaimed']);
        }

        $t->same('not-applicable', $registry['mdoc']['output']['status']);
        $t->same(false, $registry['mdoc']['directWriterParityClaimed']);
        $t->same('not-applicable', $registry['ms']['input']['status']);
        $t->same(false, $registry['ms']['directReaderParityClaimed']);
        $t->same(false, $summary['directReaderParityClaimed']);
        $t->same(false, $summary['directWriterParityClaimed']);
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
            $t->same(PandocFormatRegistry::roffManualExtensionInference()[$pattern], $entry['format']);
        }

        $t->same('ms', PandocFormatRegistry::inferRoffManualFormatFromExtension('.ms'));
        $t->same('ms', PandocFormatRegistry::inferRoffManualFormatFromExtension('MS'));
        $t->same('ms', PandocFormatRegistry::inferRoffManualFormatFromExtension('.roff'));
        $t->same('ms', PandocFormatRegistry::inferRoffManualFormatFromExtension('ROFF'));
        $t->same('man', PandocFormatRegistry::inferRoffManualFormatFromExtension('.1'));
        $t->same('man', PandocFormatRegistry::inferRoffManualFormatFromExtension('9'));
        $t->same('man', PandocFormatRegistry::inferRoffManualFormatFromExtension('.3p'));
        $t->same('man', PandocFormatRegistry::inferRoffManualFormatFromExtension('5SSL'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension(''));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.0'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.10'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.10ssl'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.3-p'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.3.1'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.3_foo'));
        $t->same(null, PandocFormatRegistry::inferRoffManualFormatFromExtension('.mdoc'));
        $t->same(PandocFormatRegistry::inferRoffManualFormatFromExtension('.3p'), PandocFormatRegistry::inferRoffFormatFromExtension('.3p'));

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

        $t->same(['ms', 'man'], PandocFormatRegistry::roffManualFormatsWithExtensionInference());
        $t->same(['mdoc'], PandocFormatRegistry::roffManualFormatsWithoutExtensionInference());
    },
    'builds roff manual review packets with unsupported extension surfaces' => static function (TestRunner $t): void {
        $packet = PandocFormatRegistry::roffManualFormatReviewPacket();
        $surface = PandocFormatRegistry::roffManualUnsupportedFormatForExtension('3P');
        $surfaces = PandocFormatRegistry::roffManualUnsupportedExtensionSurfaces();
        $summary = PandocFormatRegistry::roffManualUnsupportedFormatSummary();
        $parity = PandocFormatRegistry::roffManualFormatParitySummary();

        $t->same($packet, PandocFormatRegistry::roffFormatReviewPacket());
        $t->same('2026-06-03', $packet['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $packet['upstreamManualUrl']);
        $t->same(PandocFormatRegistry::UPSTREAM_SOURCE_COMMIT, $packet['upstreamSourceCommit']);
        $t->same([
            'inputOutput' => ['man'],
            'inputOnly' => ['mdoc'],
            'outputOnly' => ['ms'],
        ], $packet['directionBuckets']);
        $t->same(['ms', 'man'], $packet['extensionInferredFormats']);
        $t->same(['mdoc'], $packet['nonExtensionInferredFormats']);
        $t->same(['man', 'mdoc'], $packet['unsupportedInputFormats']);
        $t->same(['man', 'ms'], $packet['unsupportedOutputFormats']);
        $t->same($summary, $packet['unsupportedFormatSummary']);
        $t->same($parity, $packet['paritySummary']);
        $t->same(['man', 'mdoc', 'ms'], array_keys($packet['formats']));
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
        $t->same(false, $packet['formats']['mdoc']['extensionInferred']);
        $t->same(['.ms', '.roff'], $packet['formats']['ms']['extensions']);

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
        ], $surface);
        $t->same(['.ms', '.roff', '.[1-9]', '.[1-9][a-z]+'], array_keys($surfaces));
        $t->same('generic-roff-source', $surfaces['.roff']['kind']);
        $t->same('output-only', $surfaces['.roff']['direction']);
        $t->same(true, $surfaces['.roff']['unsupportedOutput']);
        $t->same(null, PandocFormatRegistry::roffManualUnsupportedFormatForExtension('.mdoc'));
        $t->same(null, PandocFormatRegistry::roffManualUnsupportedFormatForExtension('.10ssl'));
        $t->same([
            'anyUnsupported' => ['man', 'mdoc', 'ms'],
            'unsupportedBoth' => ['man'],
            'unsupportedInputOnly' => ['mdoc'],
            'unsupportedOutputOnly' => ['ms'],
            'noNativeReader' => ['man', 'mdoc'],
            'noNativeWriter' => ['man', 'ms'],
        ], $summary);
        $t->same(3, $parity['uniqueFormatCount']);
        $t->same(['unsupported' => 2], $parity['inputSupportStatusCounts']);
        $t->same(['unsupported' => 2], $parity['outputSupportStatusCounts']);
        $t->same(4, $parity['extensionInferenceMappings']);
        $t->same(2, $parity['manualSectionExtensionMappings']);
        $t->same(2, $parity['literalExtensionMappings']);
        $t->same(4, $parity['unsupportedExtensionSurfaceMappings']);
        $t->same(0, $parity['registeredInputImplementations']);
        $t->same(0, $parity['registeredOutputImplementations']);
        $t->same(false, $parity['directReaderParitySupported']);
        $t->same(false, $parity['directWriterParitySupported']);
        $t->same(false, $parity['directParityClaimed']);
        $t->same('unsupported', $parity['directParityStatus']);
        $t->contains('no native PHP roff/manual reader or writer is registered', $parity['reviewNote']);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
];
