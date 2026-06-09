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
    'tracks roff manual reader writer and extension inference registry metadata' => static function (TestRunner $t): void {
        $t->same(['man', 'mdoc'], PandocFormatRegistry::roffManualInputFormats());
        $t->same(['man', 'ms'], PandocFormatRegistry::roffManualOutputFormats());
        $t->same([
            '.ms' => 'ms',
            '.roff' => 'ms',
            '.[1-9]' => 'man',
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

        $t->same(34, count(PandocFormatRegistry::unsupportedInputFormats()));
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
        $t->same('partial', $inputSupport['odt']['status']);
        $t->same(OdtReader::class, $inputSupport['odt']['implementation']);

        $t->same([
            'ipynb',
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

        $t->same(34, count(PandocFormatRegistry::unsupportedInputFormats()));
        $t->same(61, count(PandocFormatRegistry::unsupportedOutputFormats()));
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
];
