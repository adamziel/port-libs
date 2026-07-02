<?php

declare(strict_types=1);

use PortLibs\Pandoc\RichPackageUnsupportedFormatRegistry;
use PortLibs\Pandoc\PandocFormatRegistry;

return [
    'reports rich package format denominators and direct support boundaries' => static function (TestRunner $t): void {
        $report = RichPackageUnsupportedFormatRegistry::statusReport();

        $t->same('0640c4c9859aa5a3ede082c190fcd5883c24ac83', $report['upstreamCommit']);
        $t->same([
            'docx',
            'odt',
            'opendocument',
            'epub',
            'epub2',
            'epub3',
            'ipynb',
            'pptx',
            'xlsx',
            'chunkedhtml',
            'icml',
            'pdf',
        ], RichPackageUnsupportedFormatRegistry::richPackageFormats());
        $t->same(12, $report['denominators']['richPackageFormats']);
        $t->same(6, $report['denominators']['upstreamRichPackageInputs']);
        $t->same(11, $report['denominators']['upstreamRichPackageOutputs']);
        $t->same(9, $report['denominators']['sourceAliasExtensions']);
        $t->same(9, $report['denominators']['richPackageExtensions']);
        $t->same(['supported' => 6, 'unsupported' => 0, 'total' => 6], $report['directSupport']['input']);
        $t->same(['supported' => 3, 'unsupported' => 8, 'total' => 11], $report['directSupport']['output']);
        $t->same(0, count($report['unsupportedDiagnostics']['input']));
        $t->same(8, count($report['unsupportedDiagnostics']['output']));
        $t->same(7, count($report['extensionDiagnostics']));
    },

    'summarizes rich package unsupported format review buckets' => static function (TestRunner $t): void {
        $summary = RichPackageUnsupportedFormatRegistry::unsupportedFormatSummary();
        $packet = RichPackageUnsupportedFormatRegistry::reviewPacket();

        $t->same($summary, PandocFormatRegistry::richPackageUnsupportedFormatSummary());
        $t->same($packet, PandocFormatRegistry::richPackageFormatReviewPacket());
        $t->same('0640c4c9859aa5a3ede082c190fcd5883c24ac83', $summary['upstreamCommit']);
        $t->same(true, $summary['externalToolFree']);
        $t->same(['docx', 'odt', 'epub', 'ipynb', 'pptx'], $summary['directionBuckets']['input-output']);
        $t->same(['xlsx'], $summary['directionBuckets']['input-only']);
        $t->same(['opendocument', 'epub2', 'epub3', 'chunkedhtml', 'icml', 'pdf'], $summary['directionBuckets']['output-only']);
        $t->same(['epub'], $summary['supportBuckets']['boundedNativeInputOutput']);
        $t->same(['xlsx'], $summary['supportBuckets']['boundedNativeInputOnly']);
        $t->same(['epub3'], $summary['supportBuckets']['boundedNativeOutputOnly']);
        $t->same(['docx', 'odt', 'ipynb', 'pptx'], $summary['supportBuckets']['nativeInputUnsupportedOutput']);
        $t->same([], $summary['supportBuckets']['unsupportedInputNativeOutput']);
        $t->same([], $summary['supportBuckets']['unsupportedInputOnly']);
        $t->same(['opendocument', 'epub2', 'chunkedhtml', 'icml', 'pdf'], $summary['supportBuckets']['unsupportedOutputOnly']);
        $t->same([], $summary['supportBuckets']['unsupportedInputOutput']);
        $t->same([], $summary['unsupportedFormats']['input']);
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
        ], $summary['unsupportedFormats']['output']);
        $t->same([], $summary['noNativeReaderFormats']);
        $t->same($summary['unsupportedFormats']['output'], $summary['noNativeWriterFormats']);
        $t->same(9, $summary['unsupportedDiagnosticCounts']['writer-component-missing']);
        $t->same(6, $summary['unsupportedDiagnosticCounts']['package-assembly-not-implemented']);
        $t->same(1, $summary['unsupportedDiagnosticCounts']['external-notebook-tooling-disallowed']);
        $t->same(1, $summary['unsupportedDiagnosticCounts']['renderer-engine-disallowed']);
        $t->same(5, $summary['unsupportedGateCounts']['shared-zip-package-core']);
        $t->same(['doc', 'ods', 'odp', 'zip'], $summary['unsupportedSourceAliasExtensions']);
        $t->same(['.docx', '.epub', '.fodt', '.icml', '.ipynb', '.odt', '.pdf', '.pptx'], $summary['unsupportedExtensionNames']);

        $t->same('rich-package-unsupported-format', $packet['registry']);
        $t->same(true, $packet['externalToolFree']);
        $t->same($summary, $packet['summary']);
        $t->same([], $packet['unsupportedDiagnostics']['input']);
        $t->same(9, count($packet['unsupportedDiagnostics']['output']));
        $t->same(4, count($packet['sourceAliasDiagnostics']));
        $t->same(8, count($packet['extensionDiagnostics']));
    },

    'keeps bounded native rich package readers distinct from unsupported writers' => static function (TestRunner $t): void {
        $docxInput = RichPackageUnsupportedFormatRegistry::formatStatus('docx', 'input');
        $docxOutput = RichPackageUnsupportedFormatRegistry::formatStatus('docx', 'output');
        $odtInput = RichPackageUnsupportedFormatRegistry::formatStatus('odt', 'input');
        $epubInput = RichPackageUnsupportedFormatRegistry::formatStatus('epub', 'input');
        $ipynbInput = RichPackageUnsupportedFormatRegistry::formatStatus('ipynb', 'input');
        $pptxInput = RichPackageUnsupportedFormatRegistry::formatStatus('pptx', 'input');
        $xlsxInput = RichPackageUnsupportedFormatRegistry::formatStatus('xlsx', 'input');

        $t->same('bounded-native-rich-package-input', $docxInput['state']);
        $t->same('pandoc.rich-package.input.bounded-native', $docxInput['code']);
        $t->same(true, $docxInput['countsAsDirectSupport']);
        $t->same('DocxReader', $docxInput['component']);
        $t->same(['shared-zip-package-core', 'opc-xml-relationships-core', 'docx-openxml-core'], $docxInput['gates']);
        $t->same([], $docxInput['diagnostics']);

        $t->same('bounded-native-rich-package-output', $docxOutput['state']);
        $t->same('pandoc.rich-package.output.bounded-native', $docxOutput['code']);
        $t->same(true, $docxOutput['countsAsDirectSupport']);
        $t->same('DocxWriter', $docxOutput['component']);
        $t->same([], $docxOutput['diagnostics']);
        $t->contains('docx-openxml-writer-core', implode(',', $docxOutput['gates']));

        $t->same('OdtReader', $odtInput['component']);
        $t->same(['shared-zip-package-core', 'odf-open-document-core'], $odtInput['gates']);
        $t->same('EpubReader', $epubInput['component']);
        $t->same(['shared-zip-package-core', 'epub3-package-core', 'xml-html5-dom-core'], $epubInput['gates']);
        $t->same('IpynbReader', $ipynbInput['component']);
        $t->same(['ipynb-reader-core'], $ipynbInput['gates']);
        $t->same(true, $ipynbInput['countsAsDirectSupport']);
        $t->same('bounded-native-rich-package-input', $pptxInput['state']);
        $t->same('pandoc.rich-package.input.bounded-native', $pptxInput['code']);
        $t->same(true, $pptxInput['countsAsDirectSupport']);
        $t->same('PptxReader', $pptxInput['component']);
        $t->same(['shared-zip-package-core', 'opc-xml-relationships-core', 'pptx-openxml-core'], $pptxInput['gates']);
        $t->same([], $pptxInput['diagnostics']);
        $t->same('bounded-native-rich-package-input', $xlsxInput['state']);
        $t->same('pandoc.rich-package.input.bounded-native', $xlsxInput['code']);
        $t->same(true, $xlsxInput['countsAsDirectSupport']);
        $t->same('XlsxReader', $xlsxInput['component']);
        $t->same(['shared-zip-package-core', 'opc-xml-relationships-core', 'xlsx-openxml-core'], $xlsxInput['gates']);
        $t->same([], $xlsxInput['diagnostics']);
    },

    'reports unsupported package inputs and outputs without external conversion claims' => static function (TestRunner $t): void {
        $pptxInput = RichPackageUnsupportedFormatRegistry::formatStatus('pptx', 'input');
        $xlsxInput = RichPackageUnsupportedFormatRegistry::formatStatus('xlsx', 'input');
        $ipynbOutput = RichPackageUnsupportedFormatRegistry::formatStatus('ipynb', 'output');
        $epub3Output = RichPackageUnsupportedFormatRegistry::formatStatus('epub3', 'output');
        $chunkedHtmlOutput = RichPackageUnsupportedFormatRegistry::formatStatus('chunkedhtml', 'output');
        $icmlOutput = RichPackageUnsupportedFormatRegistry::formatStatus('icml', 'output');
        $pdfOutput = RichPackageUnsupportedFormatRegistry::formatStatus('pdf', 'output');
        $unsupportedInputFormats = array_column(
            RichPackageUnsupportedFormatRegistry::unsupportedDiagnostics('input'),
            'format'
        );
        $unsupportedOutputFormats = array_column(
            RichPackageUnsupportedFormatRegistry::unsupportedDiagnostics('output'),
            'format'
        );

        $t->same('bounded-native-rich-package-input', $pptxInput['state']);
        $t->same('pandoc.rich-package.input.bounded-native', $pptxInput['code']);
        $t->same(true, $pptxInput['countsAsDirectSupport']);
        $t->same('PptxReader', $pptxInput['component']);
        $t->same(['shared-zip-package-core', 'opc-xml-relationships-core', 'pptx-openxml-core'], $pptxInput['gates']);
        $t->same([], $pptxInput['diagnostics']);

        $t->same('bounded-native-rich-package-input', $xlsxInput['state']);
        $t->same(['shared-zip-package-core', 'opc-xml-relationships-core', 'xlsx-openxml-core'], $xlsxInput['gates']);
        $t->same([], $xlsxInput['diagnostics']);
        $t->same([], $unsupportedInputFormats);

        $t->same('unsupported-rich-package-output', $ipynbOutput['state']);
        $t->same(['ipynb-notebook-writer-core'], $ipynbOutput['gates']);
        $t->contains('external-notebook-tooling-disallowed', implode(',', $ipynbOutput['diagnostics']));
        $t->same('bounded-native-rich-package-output', $epub3Output['state']);
        $t->same(['shared-zip-package-core', 'epub3-package-writer-core', 'xml-html5-dom-core'], $epub3Output['gates']);
        $t->same('EpubWriter', $epub3Output['component']);
        $t->same(true, $epub3Output['countsAsDirectSupport']);
        $t->same('unsupported-rich-package-output', $chunkedHtmlOutput['state']);
        $t->same(['shared-zip-package-core', 'xml-html5-dom-core', 'chunked-html-package-writer-core'], $chunkedHtmlOutput['gates']);
        $t->same(['doctemplate-core', 'icml-writer-core'], $icmlOutput['gates']);
        $t->same(['pdf-engine-handoff-core'], $pdfOutput['gates']);
        $t->contains('renderer-engine-disallowed', implode(',', $pdfOutput['diagnostics']));
        $t->same([
            'odt',
            'opendocument',
            'epub2',
            'ipynb',
            'pptx',
            'chunkedhtml',
            'icml',
            'pdf',
        ], $unsupportedOutputFormats);
    },

    'separates rich package source aliases from direct format support' => static function (TestRunner $t): void {
        $docxAlias = RichPackageUnsupportedFormatRegistry::sourceAliasStatus('.docx');
        $docAlias = RichPackageUnsupportedFormatRegistry::sourceAliasStatus('doc');
        $odsAlias = RichPackageUnsupportedFormatRegistry::sourceAliasStatus('ods');
        $zipAlias = RichPackageUnsupportedFormatRegistry::sourceAliasStatus('zip');
        $diagnosticExtensions = array_column(
            RichPackageUnsupportedFormatRegistry::sourceAliasDiagnostics(),
            'extension'
        );

        $t->same('docx', RichPackageUnsupportedFormatRegistry::sourceFormatForExtension('.docx'));
        $t->same('doc', RichPackageUnsupportedFormatRegistry::sourceFormatForExtension('doc'));
        $t->same('pptx', RichPackageUnsupportedFormatRegistry::sourceFormatForExtension('pptx'));
        $t->same('xlsx', RichPackageUnsupportedFormatRegistry::sourceFormatForExtension('xlsx'));
        $t->same(null, RichPackageUnsupportedFormatRegistry::sourceFormatForExtension('pdf'));

        $t->same('direct-rich-package-input', $docxAlias['state']);
        $t->same(true, $docxAlias['countsAsDirectSupport']);
        $t->same('DocxReader', $docxAlias['component']);
        $t->same('bounded-native-rich-package-input', $docxAlias['directInputState']);

        $t->same('unsupported-rich-package-source-alias', $docAlias['state']);
        $t->same(false, $docAlias['countsAsDirectSupport']);
        $t->same('LegacyDocReader', $docAlias['component']);
        $t->same(null, $docAlias['directInputState']);
        $t->contains('legacy-cfb-handoff-only', implode(',', $docAlias['diagnostics']));

        $t->same('unsupported-rich-package-source-alias', $odsAlias['state']);
        $t->same(['shared-zip-package-core', 'odf-spreadsheet-reader-core'], $odsAlias['gates']);
        $t->same('direct-rich-package-input', RichPackageUnsupportedFormatRegistry::sourceAliasStatus('xlsx')['state']);
        $t->same('XlsxReader', RichPackageUnsupportedFormatRegistry::sourceAliasStatus('xlsx')['component']);
        $t->same('direct-rich-package-input', RichPackageUnsupportedFormatRegistry::sourceAliasStatus('pptx')['state']);
        $t->same('PptxReader', RichPackageUnsupportedFormatRegistry::sourceAliasStatus('pptx')['component']);
        $t->same('unsupported-rich-package-source-alias', $zipAlias['state']);
        $t->same(['shared-zip-package-core'], $zipAlias['gates']);
        $t->contains('container-preflight-only', implode(',', $zipAlias['diagnostics']));
        $t->same(['doc', 'ods', 'odp', 'zip'], $diagnosticExtensions);
    },

    'reports rich package extension diagnostics without converter claims' => static function (TestRunner $t): void {
        $t->same([
            '.docx',
            '.epub',
            '.fodt',
            '.icml',
            '.ipynb',
            '.odt',
            '.pdf',
            '.pptx',
            '.xlsx',
        ], RichPackageUnsupportedFormatRegistry::richPackageExtensions());

        $epub = RichPackageUnsupportedFormatRegistry::extensionStatus('EPUB');
        $ipynb = RichPackageUnsupportedFormatRegistry::extensionStatus('.ipynb');
        $pdf = RichPackageUnsupportedFormatRegistry::extensionStatus('pdf');
        $fodt = RichPackageUnsupportedFormatRegistry::extensionStatus('.fodt');
        $pptx = RichPackageUnsupportedFormatRegistry::extensionStatus('pptx');
        $xlsx = RichPackageUnsupportedFormatRegistry::extensionStatus('xlsx');
        $diagnosticExtensions = array_column(
            RichPackageUnsupportedFormatRegistry::extensionDiagnostics(),
            'extension'
        );

        $t->same('.epub', $epub['extension']);
        $t->same('epub', $epub['format']);
        $t->same(['epub', 'epub2', 'epub3'], $epub['formats']);
        $t->same(['epub'], $epub['inputFormats']);
        $t->same(['epub', 'epub2', 'epub3'], $epub['outputFormats']);
        $t->same(['epub'], $epub['directInputFormats']);
        $t->same(['epub', 'epub3'], $epub['directOutputFormats']);
        $t->same(['epub2'], $epub['unsupportedOutputFormats']);
        $t->same(['output'], $epub['unsupportedDirections']);
        $t->same(true, $epub['externalToolFree']);

        $t->same('notebook-json-package', $ipynb['kind']);
        $t->same(['ipynb'], $ipynb['directInputFormats']);
        $t->same([], $ipynb['unsupportedInputFormats']);
        $t->same(['ipynb'], $ipynb['unsupportedOutputFormats']);
        $t->same(['output'], $ipynb['unsupportedDirections']);
        $t->contains('external-notebook-tooling-disallowed', implode(',', $ipynb['diagnostics']));

        $t->same('pdf-rendered-artifact', $pdf['kind']);
        $t->same([], $pdf['inputFormats']);
        $t->same(['pdf'], $pdf['outputFormats']);
        $t->same(['pdf'], $pdf['unsupportedOutputFormats']);
        $t->same(['output'], $pdf['unsupportedDirections']);
        $t->contains('renderer-engine-disallowed', implode(',', $pdf['diagnostics']));

        $t->same('opendocument', $fodt['format']);
        $t->same(['opendocument'], $fodt['unsupportedOutputFormats']);
        $t->same(['output'], $fodt['unsupportedDirections']);

        $t->same(['pptx'], $pptx['inputFormats']);
        $t->same(['pptx'], $pptx['directInputFormats']);
        $t->same(['output'], $pptx['unsupportedDirections']);
        $t->same([], $pptx['unsupportedInputFormats']);
        $t->same(['pptx'], $pptx['unsupportedOutputFormats']);
        $t->same(['xlsx'], $xlsx['inputFormats']);
        $t->same(['xlsx'], $xlsx['directInputFormats']);
        $t->same([], $xlsx['unsupportedInputFormats']);
        $t->same([], $xlsx['unsupportedOutputFormats']);
        $t->same([], $xlsx['unsupportedDirections']);
        $t->same([
            '.epub',
            '.fodt',
            '.icml',
            '.ipynb',
            '.odt',
            '.pdf',
            '.pptx',
        ], $diagnosticExtensions);
    },
];
