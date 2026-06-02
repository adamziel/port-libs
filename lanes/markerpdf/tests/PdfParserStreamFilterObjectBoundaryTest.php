<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$currentBaseFilteredFallbackPdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale filtered fallback leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current filtered object boundary) Tj T* (Current base fallback) Tj ET';

    $staleCompressed = gzcompress($staleContent);
    $currentCompressed = gzcompress($currentContent);
    if (!is_string($staleCompressed) || !is_string($currentCompressed)) {
        throw new RuntimeException('Unable to compress focused parser stream-filter fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, 0, '<< /Type /Catalog /NeedsRendering false >>');
    $addObject(2, 0, "<< /Filter /FlateDecode /Length " . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream");
    $addObject(3, 0, "<< /Filter /FlateDecode /Length " . strlen($currentCompressed) . " >>\nstream\n{$currentCompressed}\nendstream");

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 4\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets[1])
        . $xrefRow(0, 1, 'f')
        . $xrefRow($offsets[3])
        . "trailer\n<< /Size 4 /Root 1 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$inlineImageNestedStreamTokenPdf = static function (): string {
    $nestedPayload = 'BT /F1 12 Tf 72 680 Td (Nested fake stream leak) Tj ET';
    $inlineImagePayload = "<< /Length " . strlen($nestedPayload) . " >>\n"
        . "stream\n{$nestedPayload}\nendstream\n";
    $content = "BI /W 1 /H 1 /CS /RGB /BPC 8 ID\n{$inlineImagePayload}EI\n"
        . 'BT /F1 12 Tf 72 720 Td (Visible parser boundary) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'uses current xref direct stream objects before filtered fallback text extraction' => static function (TestRunner $t) use ($currentBaseFilteredFallbackPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $currentBaseFilteredFallbackPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Current filtered object boundary', 'Current base fallback'], $extractor->extractTextLines($pdf));
        $t->same(['Current filtered object boundary', 'Current base fallback'], $extractor->extractTextRuns($pdf));
        $t->same("Current filtered object boundary\nCurrent base fallback", $plainText);
        $t->same("Current filtered object boundary\nCurrent base fallback\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, 'Stale filtered fallback leak'));
        $t->true(!str_contains($plainText, "\0"));
    },

    'ignores nested stream-looking tokens inside current stream payload boundaries' => static function (TestRunner $t) use ($inlineImageNestedStreamTokenPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageNestedStreamTokenPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible parser boundary'], $extractor->extractTextLines($pdf));
        $t->same(['Visible parser boundary'], $extractor->extractTextRuns($pdf));
        $t->same('Visible parser boundary', $plainText);
        $t->same("Visible parser boundary\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, 'Nested fake stream leak'));
        $t->true(!str_contains($plainText, 'stream'));
    },
];
