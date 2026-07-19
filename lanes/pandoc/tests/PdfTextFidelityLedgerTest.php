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

    'pdf fidelity ledger subtracts repeated and asymmetric emitted inventories exactly once' => static function (
        TestRunner $t
    ): void {
        $ledger = PdfTextFidelityLedger::fromText(
            'alpha beta alpha gamma',
            'alpha gamma alpha delta'
        );

        $t->same(4, $ledger['sourceTokenCount']);
        $t->same(4, $ledger['emittedTokenCount']);
        $t->same(1, $ledger['unresolvedTokenCount']);
        $t->same(1, $ledger['addedTokenCount']);
        $t->same([['text' => 'beta', 'count' => 1]], $ledger['unresolvedTokenSample']);
        $t->same([['text' => 'delta', 'count' => 1]], $ledger['addedTokenSample']);
        $t->same(2, $ledger['unresolvedTokenAdjacencyCount']);
        $t->same(1 / 3, $ledger['tokenAdjacencyCoverage']);
        $t->same(1, $ledger['unresolvedSignificantCharacterCount']);
        $t->same(2, $ledger['addedSignificantCharacterCount']);
        $t->same(
            '4aceebefde7e195328848f90b2603ac8ff4b820877d385ca8d64d0f887cbf60d',
            $ledger['sourceTokenDigest']
        );
        $t->same(
            '768bc41afcdcc2b55b1b8afa1ad05c5317e5cb4260cc5830bf4b38fe62a65558',
            $ledger['emittedTokenDigest']
        );
        $t->same(false, $ledger['sourceAccounted']);
        $t->same(false, $ledger['exactProjection']);
    },

    'pdf fidelity ledger streams source lines and nested AST text without changing its audit result' => static function (TestRunner $t): void {
        $blocks = [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Café Alpha']),
                new AstNode('strong', [], [new AstNode('text', ['text' => 'Beta'])]),
                new AstNode('text', ['text' => 'Your article']),
            ]),
        ];

        $streamed = PdfTextFidelityLedger::fromSourceLineItems([
            ['page' => 1, 'stream' => 1, 'text' => 'Café Alpha'],
            ['page' => 1, 'stream' => 1, 'text' => 'Beta Your article'],
        ], $blocks);
        $joined = PdfTextFidelityLedger::fromText(
            "Café Alpha\nBeta Your article",
            'Café Alpha Beta Your article'
        );

        $t->same($joined, $streamed);
        $t->same(true, $streamed['exactProjection']);
        $t->same(5, $streamed['sourceTokenCount']);
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
        $t->same(2, $meta['pdfSourceDisposition']['version']);
        $t->same(1, $meta['pdfSourceDisposition']['sourceOccurrenceCount']);
        $t->same(true, $meta['pdfSourceDisposition']['allOccurrencesDispositioned']);
        $t->same(true, $meta['pdfSourceDisposition']['allOccurrencesResolved']);
        $t->same(true, $meta['pdfSourceDisposition']['orderedSignificantCharactersPreserved']);
        $t->same('source-order-exact', $meta['pdfSourceDisposition']['orderProofStrength']);
        $t->same(true, $meta['pdfSourceDisposition']['sourceEdgeMappingComplete']);
        $t->same(1, $meta['pdfSourceDisposition']['sourceEdgeCount']);
    },

    'pdf reader proves multicolumn front matter despite conservative legacy token residuals' => static function (TestRunner $t): void {
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
        $t->same(true, $meta['pdfDocumentComplete'] ?? null);
        $t->same(true, $meta['pdfTextVisibilityRawComplete'] ?? null);
        $t->same(true, $meta['pdfTextVisibilityComplete'] ?? null);
        $t->same(true, $meta['pdfSemanticTextComplete']);
        $t->same(0, $meta['pdfSourceDisposition']['unresolvedOccurrenceCount'] ?? null);
        $t->same(true, $meta['pdfSourceDisposition']['allOccurrencesResolved'] ?? null);
        $t->same(true, $meta['pdfSourceDisposition']['sourceEdgeMappingComplete'] ?? null);
        $t->same(true, $meta['pdfSourceDisposition']['orderedSignificantCharactersPreserved'] ?? null);
        $t->same([1, 2], $meta['pdfRepresentedPageNumbers'] ?? null);
        $t->same(true, $meta['pdfPageRepresentationComplete'] ?? null);
        $t->true($meta['pdfFrontMatterRecords'] >= 20);
        $t->contains('Dense Passage Retrieval for Open-Domain Question Answering', $plain);
        $t->contains('Vladimir Karpukhin', $plain);
        $t->contains('sewoncs.washington.edu', $plain);
        $t->contains('danqiccs.princeton.edu', $plain);
        $t->contains('Abstract', $plain);
        $t->contains('benchmarks.1', $plain);
        $t->contains(
            'w(i)s,w(i)s+1,···,w(i)e',
            preg_replace('/\s+/u', '', $plain) ?? '',
            'Exact inline math markers retain every source punctuation occurrence.'
        );
        $t->true(!str_contains($wordpress, '<p>1</p>'), 'A detached superscript marker must not become a standalone paragraph.');
        $t->true(is_int($summaryPosition) && is_int($introductionPosition) && $summaryPosition < $introductionPosition);
        $t->true($ledger['tokenCoverage'] > 0.855);
        $t->true($ledger['unresolvedTokenCount'] < 225);
        $t->true(
            $ledger['unresolvedTokenCount'] > 0,
            'The older token-overlap diagnostic may remain conservative without overriding exact occurrence and edge proof.'
        );
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
        $t->same(true, $meta['pdfDocumentComplete'] ?? null);
        $t->same(true, $meta['pdfTextVisibilityRawComplete'] ?? null);
        $t->same(true, $meta['pdfTextVisibilityComplete'] ?? null);
        $t->same(true, $meta['pdfSemanticTextComplete']);
        $t->same(0, $meta['pdfSourceDisposition']['unresolvedOccurrenceCount'] ?? null);
        $t->same(true, $meta['pdfSourceDisposition']['sourceEdgeMappingComplete'] ?? null);
        $t->same(true, $meta['pdfSourceDisposition']['orderedSignificantCharactersPreserved'] ?? null);
        $t->same([1, 2], $meta['pdfRepresentedPageNumbers'] ?? null);
        $t->same(true, $meta['pdfPageRepresentationComplete'] ?? null);
        $t->same(1, $meta['pdfFormulaRegions']);
        $t->contains('a2 + 8 = 12', PandocConverter::write($document, 'plain'));
        $t->true($ledger['significantCharacterCoverage'] > 0.98);
        $t->same(false, in_array('=', $unresolvedCharacters, true));
        $t->same(1, count($changedFormulaStages));
    },
];
