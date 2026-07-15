<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PdfReader;
use PortLibs\Pandoc\PdfTextFidelityLedger;

return [
    'pdf fidelity ledger distinguishes content conservation from reading order' => static function (TestRunner $t): void {
        $ledger = PdfTextFidelityLedger::fromText(
            'Title Alpha Beta Gamma',
            'Gamma Title Alpha Beta'
        );

        $t->same(1.0, $ledger['tokenCoverage']);
        $t->same(1.0, $ledger['significantCharacterCoverage']);
        $t->true($ledger['tokenAdjacencyCoverage'] < 1.0);
        $t->same(true, $ledger['sourceAccounted']);
        $t->same(false, $ledger['exactProjection']);
    },

    'pdf fidelity ledger reports missing words and formula symbols without word special cases' => static function (TestRunner $t): void {
        $ledger = PdfTextFidelityLedger::fromText(
            "Abstract\nVladimir Karpukhin\na² + 8 = 12",
            'Vladimir Karpukhin'
        );
        $missingTokens = array_column($ledger['unresolvedTokenSample'], 'text');
        $missingCharacters = array_column($ledger['unresolvedCharacterSample'], 'character');

        $t->same(false, $ledger['sourceAccounted']);
        $t->true($ledger['tokenCoverage'] < 1.0);
        $t->true($ledger['significantCharacterCoverage'] < 1.0);
        $t->true(in_array('abstract', $missingTokens, true));
        $t->true(in_array('+', $missingCharacters, true));
        $t->true(in_array('=', $missingCharacters, true));
    },

    'pdf reader exposes semantic fidelity separately from stream completeness' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Complete source sentence.) Tj ET';
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . ">>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $document = (new PdfReader())->read($pdf);
        $meta = $document->attr('meta');

        $t->same(true, $meta['pdfTextComplete']);
        $t->same(true, $meta['pdfSemanticTextComplete']);
        $t->same(1, $meta['pdfTextFidelity']['version']);
        $t->same(1.0, $meta['pdfTextFidelity']['tokenCoverage']);
        $t->same(true, $meta['pdfTextFidelity']['sourceAccounted']);
    },

    'pdf fidelity ledger makes the current multicolumn semantic loss machine readable' => static function (TestRunner $t): void {
        $path = dirname(__DIR__, 3) . '/pandoc-showcase/samples/pdf-layout-unstructured-multicolumn-multi-column-2p.pdf';
        $document = (new PdfReader([
            'pdfGeometryTables' => true,
            'pdfRepairProseText' => true,
        ]))->read((string) file_get_contents($path));
        $meta = $document->attr('meta');
        $ledger = $meta['pdfTextFidelity'];

        $t->same(true, $meta['pdfTextComplete']);
        $t->same(false, $meta['pdfSemanticTextComplete']);
        $t->true($ledger['tokenCoverage'] < 0.90, 'The known abstract/author loss must not remain invisible behind extraction completeness.');
        $t->true($ledger['unresolvedTokenCount'] > 100);
    },

    'pdf fidelity ledger makes the current formula loss machine readable' => static function (TestRunner $t): void {
        $path = dirname(__DIR__, 3) . '/pandoc-showcase/samples/pdf-layout-docling-code-formula-code_and_formula.pdf';
        $document = (new PdfReader([
            'pdfGeometryTables' => true,
            'pdfRepairProseText' => true,
        ]))->read((string) file_get_contents($path));
        $meta = $document->attr('meta');
        $ledger = $meta['pdfTextFidelity'];

        $t->same(true, $meta['pdfTextComplete']);
        $t->same(false, $meta['pdfSemanticTextComplete']);
        $t->true($ledger['unresolvedSignificantCharacterCount'] > 0);
        $t->true($ledger['significantCharacterCoverage'] < 1.0);
    },
];
