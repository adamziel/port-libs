<?php

declare(strict_types=1);

use PortLibs\Pandoc\RichPackageUnsupportedFormatRegistry;

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
            'pptx',
            'xlsx',
            'chunkedhtml',
        ], RichPackageUnsupportedFormatRegistry::richPackageFormats());
        $t->same(9, $report['denominators']['richPackageFormats']);
        $t->same(5, $report['denominators']['upstreamRichPackageInputs']);
        $t->same(8, $report['denominators']['upstreamRichPackageOutputs']);
        $t->same(9, $report['denominators']['sourceAliasExtensions']);
        $t->same(['supported' => 3, 'unsupported' => 2, 'total' => 5], $report['directSupport']['input']);
        $t->same(['supported' => 0, 'unsupported' => 8, 'total' => 8], $report['directSupport']['output']);
        $t->same(2, count($report['unsupportedDiagnostics']['input']));
        $t->same(8, count($report['unsupportedDiagnostics']['output']));
    },

    'keeps bounded native rich package readers distinct from unsupported writers' => static function (TestRunner $t): void {
        $docxInput = RichPackageUnsupportedFormatRegistry::formatStatus('docx', 'input');
        $docxOutput = RichPackageUnsupportedFormatRegistry::formatStatus('docx', 'output');
        $odtInput = RichPackageUnsupportedFormatRegistry::formatStatus('odt', 'input');
        $epubInput = RichPackageUnsupportedFormatRegistry::formatStatus('epub', 'input');

        $t->same('bounded-native-rich-package-input', $docxInput['state']);
        $t->same('pandoc.rich-package.input.bounded-native', $docxInput['code']);
        $t->same(true, $docxInput['countsAsDirectSupport']);
        $t->same('DocxReader', $docxInput['component']);
        $t->same(['shared-zip-package-core', 'opc-xml-relationships-core', 'docx-openxml-core'], $docxInput['gates']);
        $t->same([], $docxInput['diagnostics']);

        $t->same('unsupported-rich-package-output', $docxOutput['state']);
        $t->same('pandoc.rich-package.output.unsupported-format', $docxOutput['code']);
        $t->same(false, $docxOutput['countsAsDirectSupport']);
        $t->same(null, $docxOutput['component']);
        $t->contains('writer-component-missing', implode(',', $docxOutput['diagnostics']));
        $t->contains('docx-openxml-writer-core', implode(',', $docxOutput['gates']));

        $t->same('OdtReader', $odtInput['component']);
        $t->same(['shared-zip-package-core', 'odf-open-document-core'], $odtInput['gates']);
        $t->same('EpubReader', $epubInput['component']);
        $t->same(['shared-zip-package-core', 'epub3-package-core', 'xml-html5-dom-core'], $epubInput['gates']);
    },

    'reports unsupported package inputs and outputs without external conversion claims' => static function (TestRunner $t): void {
        $pptxInput = RichPackageUnsupportedFormatRegistry::formatStatus('pptx', 'input');
        $xlsxInput = RichPackageUnsupportedFormatRegistry::formatStatus('xlsx', 'input');
        $epub3Output = RichPackageUnsupportedFormatRegistry::formatStatus('epub3', 'output');
        $chunkedHtmlOutput = RichPackageUnsupportedFormatRegistry::formatStatus('chunkedhtml', 'output');
        $unsupportedInputFormats = array_column(
            RichPackageUnsupportedFormatRegistry::unsupportedDiagnostics('input'),
            'format'
        );
        $unsupportedOutputFormats = array_column(
            RichPackageUnsupportedFormatRegistry::unsupportedDiagnostics('output'),
            'format'
        );

        $t->same('unsupported-rich-package-input', $pptxInput['state']);
        $t->same('pandoc.rich-package.input.unsupported-format', $pptxInput['code']);
        $t->same(false, $pptxInput['countsAsDirectSupport']);
        $t->same(null, $pptxInput['component']);
        $t->same(['shared-zip-package-core', 'opc-xml-relationships-core', 'pptx-openxml-core'], $pptxInput['gates']);
        $t->contains('reader-component-missing', implode(',', $pptxInput['diagnostics']));
        $t->contains('external-office-conversion-disallowed', implode(',', $pptxInput['diagnostics']));

        $t->same('unsupported-rich-package-input', $xlsxInput['state']);
        $t->same(['shared-zip-package-core', 'opc-xml-relationships-core', 'xlsx-openxml-core'], $xlsxInput['gates']);
        $t->same(['pptx', 'xlsx'], $unsupportedInputFormats);

        $t->same('unsupported-rich-package-output', $epub3Output['state']);
        $t->same(['shared-zip-package-core', 'epub3-package-writer-core', 'xml-html5-dom-core'], $epub3Output['gates']);
        $t->same('unsupported-rich-package-output', $chunkedHtmlOutput['state']);
        $t->same(['shared-zip-package-core', 'xml-html5-dom-core', 'chunked-html-package-writer-core'], $chunkedHtmlOutput['gates']);
        $t->same([
            'docx',
            'odt',
            'opendocument',
            'epub',
            'epub2',
            'epub3',
            'pptx',
            'chunkedhtml',
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
        $t->same('unsupported-rich-package-source-alias', $zipAlias['state']);
        $t->same(['shared-zip-package-core'], $zipAlias['gates']);
        $t->contains('container-preflight-only', implode(',', $zipAlias['diagnostics']));
        $t->same(['doc', 'ods', 'odp', 'pptx', 'xlsx', 'zip'], $diagnosticExtensions);
    },
];
