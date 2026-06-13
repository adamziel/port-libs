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
        $t->same('partial', $inputSupport['csv']['status']);
        $t->same(DelimitedTextReader::class, $inputSupport['csv']['implementation']);
        $t->same('partial', $inputSupport['tsv']['status']);
        $t->same(DelimitedTextReader::class, $inputSupport['tsv']['implementation']);
        $t->same(EpubReader::class, $inputSupport['epub']['implementation']);
        $t->same(OdtReader::class, $inputSupport['odt']['implementation']);
        $t->same(RtfReader::class, $inputSupport['rtf']['implementation']);
        $t->same(PandocJsonReader::class, $inputSupport['json']['implementation']);
        $t->same(MarkdownWriter::class, $outputSupport['markdown']['implementation']);
        $t->same(PandocJsonWriter::class, $outputSupport['json']['implementation']);
        $t->same(PlainWriter::class, $outputSupport['plain']['implementation']);
        $t->contains('wrapping diagnostics', $outputSupport['plain']['notes']);

        $t->same(31, count(PandocFormatRegistry::unsupportedInputFormats()));
        $t->same(61, count(PandocFormatRegistry::unsupportedOutputFormats()));
    },
    'tracks xml jats bits direct reader capabilities without parity claims' => static function (TestRunner $t): void {
        $inputSupport = PandocFormatRegistry::xmlJatsBitsInputSupport();
        $directions = PandocFormatRegistry::xmlJatsBitsFormatDirections();
        $packet = PandocFormatRegistry::xmlJatsBitsDirectReaderCapabilityPacket();

        $t->same(['xml', 'jats', 'bits'], PandocFormatRegistry::xmlJatsBitsInputFormats());
        $t->same(['xml', 'jats', 'bits'], array_keys($inputSupport));
        $t->same(['xml', 'jats', 'bits'], PandocFormatRegistry::unsupportedXmlJatsBitsInputFormats());
        $t->same(['xml', 'jats', 'bits'], array_keys($directions));

        foreach (['xml', 'jats', 'bits'] as $format) {
            $t->same('unsupported', $inputSupport[$format]['status'], "XML/JATS/BITS input {$format} must not claim direct reader parity");
            $t->same('', $inputSupport[$format]['implementation'], "XML/JATS/BITS input {$format} must not register a direct reader implementation");
            $t->same(true, $directions[$format]['input']);
            $t->same(false, $directions[$format]['output']);
            $t->same('input-only', $directions[$format]['direction']);
            $t->same('unsupported', $directions[$format]['inputStatus']);
            $t->same('not-applicable', $directions[$format]['outputStatus']);
            $t->same(false, $packet['formats'][$format]['directReaderParity']);
            $t->same(XmlHtmlDom::class, $packet['formats'][$format]['diagnosticImplementation']);
            $t->same('', $packet['formats'][$format]['inputImplementation']);
        }

        $t->same('2026-06-03', $packet['upstreamManualDate']);
        $t->contains('pandoc.org/demo/example2.html', $packet['upstreamManualUrl']);
        $t->same(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $packet['upstreamSourceCommit']);
        $t->same(['xml', 'jats', 'bits'], $packet['inputFormats']);
        $t->same(['xml', 'jats', 'bits'], $packet['unsupportedInputFormats']);
        $t->same(3, $packet['unsupportedInputCount']);
        $t->same(['unsupported' => 3], $packet['inputSupportStatusCounts']);
        $t->same(false, $packet['directReaderParitySupported']);
        $t->same(0, $packet['registeredDirectReaderImplementations']);
        $t->same(3, $packet['boundedDiagnosticSurfaceCount']);
        $t->same(true, $packet['explicitUnsupportedVerdict']);
        $t->contains('no full direct reader parity is registered', $packet['reviewNote']);

        $t->same('loadXmlDocument', $packet['formats']['xml']['reviewMethod']);
        $t->same('safe-xml-dom-primitives-only', $packet['formats']['xml']['reviewPolicy']);
        $t->same(null, $packet['formats']['xml']['aliasedTo']);
        $t->contains('safe XML loading', implode('; ', $packet['formats']['xml']['boundedDiagnostics']));
        $t->contains('full Pandoc XML input mapping', implode('; ', $packet['formats']['xml']['remainingReaderGaps']));
        $t->contains('no full native PHP XML direct reader is registered yet', $packet['formats']['xml']['inputNotes']);

        $t->same('summarizeJatsFrontMatter', $packet['formats']['jats']['reviewMethod']);
        $t->same('jats-bits-front-matter-review-only', $packet['formats']['jats']['reviewPolicy']);
        $t->same(null, $packet['formats']['jats']['aliasedTo']);
        $t->contains('article front-matter identifiers', implode('; ', $packet['formats']['jats']['boundedDiagnostics']));
        $t->contains('full JATS body and back-matter mapping', implode('; ', $packet['formats']['jats']['remainingReaderGaps']));

        $t->same('summarizeJatsFrontMatter', $packet['formats']['bits']['reviewMethod']);
        $t->same('jats-bits-front-matter-review-only', $packet['formats']['bits']['reviewPolicy']);
        $t->same('jats', $packet['formats']['bits']['aliasedTo']);
        $t->contains('book and book-part metadata identifiers', implode('; ', $packet['formats']['bits']['boundedDiagnostics']));
        $t->contains('full BITS book body and book-part mapping', implode('; ', $packet['formats']['bits']['remainingReaderGaps']));
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

        $t->same(31, count(PandocFormatRegistry::unsupportedInputFormats()));
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

        $t->same(31, count(PandocFormatRegistry::unsupportedInputFormats()));
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
];
