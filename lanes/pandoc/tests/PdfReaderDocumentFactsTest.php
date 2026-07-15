<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\NativePdfFactsProvider;
use PortLibs\MarkerPDF\PdfDocumentFactsMerger;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;

$readerFactsPdf = static function (): string {
    $first = 'BT /F1 12 Tf 72 720 Td (A complete first-page sentence.) Tj ET';
    $second = 'BT /F1 12 Tf 72 720 Td (A complete second-page sentence.) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($first) . " >>\nstream\n{$first}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($second) . " >>\nstream\n{$second}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'runs one unchanged document semantic pass from merged durable page facts' => static function (
        TestRunner $t
    ) use ($readerFactsPdf): void {
        $pdf = $readerFactsPdf();
        $provider = new NativePdfFactsProvider();
        $facts = (new PdfDocumentFactsMerger())->mergeComplete([
            $provider->extract($pdf, ['startPage' => 1, 'maxPages' => 1]),
            $provider->extract($pdf, ['startPage' => 2, 'maxPages' => 1]),
        ]);
        $options = [
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => true,
            'pdfCollectImagePlacements' => true,
            'pdfCollectFormXObjectPlacements' => true,
        ];
        $baseline = (new PdfReader($options))->read($pdf);
        $fromFacts = (new PdfReader($options + ['pdfDocumentFacts' => $facts->toArray()]))->read($pdf);

        $t->same(
            PandocConverter::write($baseline, 'wordpress'),
            PandocConverter::write($fromFacts, 'wordpress')
        );
        $metadata = $fromFacts->attr('meta', []);
        $t->same(true, $metadata['pdfDocumentComplete'] ?? null);
        $t->same([1, 2], $metadata['pdfProcessedPageNumbers'] ?? null);
        $t->same('native-php-v1', $metadata['pdfFactsProvider'] ?? null);
        $t->same(hash('sha256', $pdf), $metadata['pdfFactsSourceSha256'] ?? null);
        $t->same(
            $baseline->attr('meta', [])['pdfTextFidelity']['sourceTokenCount'] ?? null,
            $metadata['pdfTextFidelity']['sourceTokenCount'] ?? null
        );
    },
    'rejects durable facts when their source digest does not match' => static function (
        TestRunner $t
    ) use ($readerFactsPdf): void {
        $pdf = $readerFactsPdf();
        $facts = (new NativePdfFactsProvider())->extract($pdf);

        $t->throws(
            RuntimeException::class,
            static fn () => (new PdfReader(['pdfDocumentFacts' => $facts]))->read($pdf . "\n% changed")
        );
    },
    'keeps global output stable for multicolumn theatre and formula corpus facts' => static function (TestRunner $t): void {
        $root = dirname(__DIR__, 3);
        $fixtures = [
            $root . '/pandoc-showcase/samples/pdf-layout-unstructured-multicolumn-multi-column-2p.pdf',
            $root . '/pandoc-showcase/samples/pdf-layout-vdl-theatre-script-ASC_script_format_example.pdf',
            $root . '/pandoc-showcase/samples/pdf-layout-docling-code-formula-code_and_formula.pdf',
        ];
        $readerOptions = [
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => true,
            'pdfCollectImagePlacements' => true,
            'pdfCollectFormXObjectPlacements' => true,
        ];
        foreach ($fixtures as $fixture) {
            $pdf = file_get_contents($fixture);
            $t->true(is_string($pdf) && $pdf !== '', 'Expected PDF corpus fixture ' . basename($fixture));
            $provider = new NativePdfFactsProvider();
            $inventory = $provider->extract($pdf, ['maxPages' => 1])->inventory();
            $ranges = [];
            for ($page = 1; $page <= $inventory['totalPages']; $page += 2) {
                $ranges[] = $provider->extract($pdf, [
                    'startPage' => $page,
                    'maxPages' => min(2, $inventory['totalPages'] - $page + 1),
                    'pdfMaxPositionedTextRuns' => 250_000,
                ]);
            }
            $facts = (new PdfDocumentFactsMerger())->mergeComplete($ranges);
            $baseline = (new PdfReader($readerOptions))->read($pdf);
            $fromFacts = (new PdfReader($readerOptions + ['pdfDocumentFacts' => $facts]))->read($pdf);

            $t->same(
                PandocConverter::write($baseline, 'wordpress'),
                PandocConverter::write($fromFacts, 'wordpress'),
                'Global facts output changed for ' . basename($fixture)
            );
        }
    },
];
