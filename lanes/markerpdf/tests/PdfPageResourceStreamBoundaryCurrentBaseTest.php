<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceStreamBoundaryCMap = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
    if ($encoded === false) {
        throw new RuntimeException('Unable to encode focused resource-stream CMap text.');
    }

    return "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<41> <" . strtoupper(bin2hex($encoded)) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceStreamBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceStreamBoundaryPdf = static function () use ($pageResourceStreamBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /StreamForm Do Q q /ParentForm Do Q';
    $streamForm = 'BT /F1 12 Tf 12 24 Td (Stream resource form leak) Tj ET';
    $parentForm = 'BT /F1 12 Tf 12 24 Td (Parent resource form leak) Tj ET';
    $streamCMap = $pageResourceStreamBoundaryCMap('Stream resource font leak');
    $parentCMap = $pageResourceStreamBoundaryCMap('Parent resource font leak');
    $resourcePayload = 'BT /F1 12 Tf 1 1 Td (resource-stream payload leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 12 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StreamResourceFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($streamForm) . " >>\nstream\n{$streamForm}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentResourceFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($streamCMap) . " >>\nstream\n{$streamCMap}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($parentCMap) . " >>\nstream\n{$parentCMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /ParentForm 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($parentForm) . " >>\nstream\n{$parentForm}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Length " . strlen($resourcePayload) . " /Font << /F1 5 0 R >> /XObject << /StreamForm 6 0 R >> >>\nstream\n{$resourcePayload}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'fails closed on page Resources stream objects instead of inheriting parent or promoting stream dictionaries' => static function (TestRunner $t) use ($pageResourceStreamBoundaryPdf): void {
        $pdf = $pageResourceStreamBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];

        $t->same(['A'], $extractor->extractTextLines($pdf));
        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->same('A', $plainText);
        $t->same("A\n", $extractor->naiveGetText($pdf));
        $t->same('unresolved_or_malformed', $resourceMetadata['status'] ?? null);
        $t->same(false, $resourceMetadata['resolved'] ?? null);
        $t->same(3, $resourceMetadata['resource_owner_object'] ?? null);
        $t->same(12, $resourceMetadata['resource_object'] ?? null);
        $t->same(false, $resourceMetadata['inherited'] ?? null);
        $t->same([], $resourceMetadata['categories'] ?? null);
        $t->same(false, str_contains($plainText, 'Stream resource font leak'));
        $t->same(false, str_contains($plainText, 'Stream resource form leak'));
        $t->same(false, str_contains($plainText, 'Parent resource font leak'));
        $t->same(false, str_contains($plainText, 'Parent resource form leak'));
        $t->same(false, str_contains($plainText, 'resource-stream payload leak'));
    },
];
