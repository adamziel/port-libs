<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$objectStreamFilterOwnerBoundaryPdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current filtered fallback page) Tj T* (Object stream carrier excluded) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale filtered fallback page) Tj ET';
    $carrierMember = '<< /Producer (WordPress import) >> BT /F1 12 Tf 72 680 Td (Filtered object stream carrier leak) Tj ET';

    $currentCompressed = gzcompress($currentContent);
    $staleCompressed = gzcompress($staleContent);
    $carrierHeader = '12 0';
    $carrierPlain = $carrierHeader . "\n" . $carrierMember . "\n";
    $carrierCompressed = gzcompress($carrierPlain);
    if (!is_string($currentCompressed) || !is_string($staleCompressed) || !is_string($carrierCompressed)) {
        throw new RuntimeException('Unable to compress focused object-stream owner-boundary fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, 0, "<< /Filter /FlateDecode /Length " . strlen($currentCompressed) . " >>\nstream\n{$currentCompressed}\nendstream");
    $addObject(2, 0, "<< /Type /ObjStm /N 1 /First " . (strlen($carrierHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($carrierCompressed) . " >>\nstream\n{$carrierCompressed}\nendstream");
    $addObject(3, 0, "<< /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream");
    $addObject(4, 0, '<< /Type /Catalog /NeedsRendering false >>');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 5\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow($offsets[2])
        . $xrefRow(0, 1, 'f')
        . $xrefRow($offsets[4])
        . "trailer\n<< /Size 5 /Root 4 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'keeps filtered object stream carrier payload out of fallback WordPress text extraction' => static function (TestRunner $t) use ($objectStreamFilterOwnerBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $objectStreamFilterOwnerBoundaryPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Current filtered fallback page', 'Object stream carrier excluded'], $extractor->extractTextLines($pdf));
        $t->same(['Current filtered fallback page', 'Object stream carrier excluded'], $extractor->extractTextRuns($pdf));
        $t->same("Current filtered fallback page\nObject stream carrier excluded", $plainText);
        $t->same("Current filtered fallback page\nObject stream carrier excluded\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Filtered object stream carrier leak'));
        $t->true(!str_contains($plainText, 'Stale filtered fallback page'));
        $t->true(!str_contains($plainText, 'WordPress import'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
