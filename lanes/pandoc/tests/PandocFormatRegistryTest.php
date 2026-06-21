<?php

declare(strict_types=1);

use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\EpubReader;
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
        $t->same(35, count(PandocFormatRegistry::unsupportedInputFormats()));
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
        $t->same('partial', $support['latex']['status']);
        $t->same(LatexWriter::class, $support['latex']['implementation']);
        $t->same('partial', $support['native']['status']);
        $t->same(NativeWriter::class, $support['native']['implementation']);
        $t->same('partial', $support['plain']['status']);
        $t->same('unsupported', $support['docx']['status']);
        $t->same('unsupported', $support['epub']['status']);
        $t->same('unsupported', $support['odt']['status']);
        $t->same('unsupported', $support['pdf']['status']);
        $t->same(61, count(PandocFormatRegistry::unsupportedOutputFormats()));
    },
];
