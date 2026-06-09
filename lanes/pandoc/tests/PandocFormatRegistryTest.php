<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\EpubReader;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\OdtReader;
use PortLibs\Pandoc\PandocFormatRegistry;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;
use PortLibs\Pandoc\PlainWriter;
use PortLibs\Pandoc\RtfReader;
use PortLibs\Pandoc\UpstreamRunnerDependencyAudit;

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
        $t->same(EpubReader::class, $inputSupport['epub']['implementation']);
        $t->same(OdtReader::class, $inputSupport['odt']['implementation']);
        $t->same(RtfReader::class, $inputSupport['rtf']['implementation']);
        $t->same(PandocJsonReader::class, $inputSupport['json']['implementation']);
        $t->same(MarkdownWriter::class, $outputSupport['markdown']['implementation']);
        $t->same(PandocJsonWriter::class, $outputSupport['json']['implementation']);
        $t->same(PlainWriter::class, $outputSupport['plain']['implementation']);
        $t->contains('wrapping diagnostics', $outputSupport['plain']['notes']);

        $t->same(34, count(PandocFormatRegistry::unsupportedInputFormats()));
        $t->same(61, count(PandocFormatRegistry::unsupportedOutputFormats()));
    },
];
