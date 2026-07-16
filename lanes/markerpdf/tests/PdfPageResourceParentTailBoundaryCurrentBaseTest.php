<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceParentTailBoundaryCMap = static function (array $entries): string {
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', (string) $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode page Parent tail-boundary CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceParentTailBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceParentTailBoundaryPdf = static function () use ($pageResourceParentTailBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /ParentTailForm Do Q';
    $form = 'BT /F1 12 Tf 12 24 Td (Parent tail inherited form leak) Tj ET';
    $cMap = $pageResourceParentTailBoundaryCMap([
        '41' => 'Parent tail inherited font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R 99 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentTailInherited /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /ParentTailForm 7 0 R >> >>\nendobj\n"
        . "99 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 5 0 R >> /XObject << /ParentTailForm 7 0 R >> >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects tailed page Parent references before inherited resource lookup' => static function (TestRunner $t) use ($pageResourceParentTailBoundaryPdf): void {
        $pdf = $pageResourceParentTailBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);

        $t->same(['A'], $extractor->extractTextLines($pdf));
        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->same('A', $plainText);
        $t->same("A\n", $extractor->naiveGetText($pdf));
        $t->same([], $boundary);
        $t->same(false, str_contains($plainText, 'Parent tail inherited font leak'));
        $t->same(false, str_contains($plainText, 'Parent tail inherited form leak'));
        $t->same(false, str_contains($plainText, 'ParentTailForm'));
    },
];
