<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
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

    'pdf reader conserves multicolumn front matter while reporting remaining semantic loss' => static function (TestRunner $t): void {
        $path = dirname(__DIR__, 3) . '/pandoc-showcase/samples/pdf-layout-unstructured-multicolumn-multi-column-2p.pdf';
        $document = (new PdfReader([
            'pdfGeometryTables' => true,
            'pdfRepairProseText' => true,
        ]))->read((string) file_get_contents($path));
        $meta = $document->attr('meta');
        $ledger = $meta['pdfTextFidelity'];
        $plain = PandocConverter::write($document, 'plain');
        $wordpress = PandocConverter::write($document, 'wordpress');
        $summaryPosition = strpos($plain, 'Open-domain question answering relies');
        $introductionPosition = strpos($plain, '1 Introduction');

        $t->same(true, $meta['pdfTextComplete']);
        $t->same(false, $meta['pdfSemanticTextComplete']);
        $t->true($meta['pdfFrontMatterRecords'] >= 20);
        $t->contains('Dense Passage Retrieval for Open-Domain Question Answering', $plain);
        $t->contains('Vladimir Karpukhin', $plain);
        $t->contains('sewoncs.washington.edu', $plain);
        $t->contains('danqiccs.princeton.edu', $plain);
        $t->contains('Abstract', $plain);
        $t->contains('benchmarks.1', $plain);
        $t->true(!str_contains($wordpress, '<p>1</p>'), 'A detached superscript marker must not become a standalone paragraph.');
        $t->true($meta['pdfInlineMarkerRecords'] >= 1);
        $t->true(is_int($summaryPosition) && is_int($introductionPosition) && $summaryPosition < $introductionPosition);
        $t->true($ledger['tokenCoverage'] > 0.855);
        $t->true($ledger['unresolvedTokenCount'] < 225);
        $t->true($ledger['unresolvedTokenCount'] > 0, 'Remaining body and diagram loss must stay visible in the ledger.');
    },

    'pdf reader conserves a split formula through exact source and geometry reconciliation' => static function (TestRunner $t): void {
        $path = dirname(__DIR__, 3) . '/pandoc-showcase/samples/pdf-layout-docling-code-formula-code_and_formula.pdf';
        $document = (new PdfReader([
            'pdfGeometryTables' => true,
            'pdfRepairProseText' => true,
        ]))->read((string) file_get_contents($path));
        $meta = $document->attr('meta');
        $ledger = $meta['pdfTextFidelity'];
        $unresolvedCharacters = array_column($ledger['unresolvedCharacterSample'], 'character');
        $changedFormulaStages = [];
        foreach ($meta['pdfSemanticPipeline'] as $run) {
            foreach ($run['stages'] as $stage) {
                if ($stage['processor'] === 'formula-regions' && $stage['changed']) {
                    $changedFormulaStages[] = $stage;
                }
            }
        }

        $t->same(true, $meta['pdfTextComplete']);
        $t->same(false, $meta['pdfSemanticTextComplete']);
        $t->same(1, $meta['pdfFormulaRegions']);
        $t->contains('a2 + 8 = 12', PandocConverter::write($document, 'plain'));
        $t->true($ledger['significantCharacterCoverage'] > 0.98);
        $t->same(false, in_array('=', $unresolvedCharacters, true));
        $t->same(1, count($changedFormulaStages));
    },
];
