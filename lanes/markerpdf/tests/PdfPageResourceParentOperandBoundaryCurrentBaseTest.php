<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceParentOperandBoundaryCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode parent operand boundary CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceParentOperandBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceParentOperandBoundaryPdf = static function () use ($pageResourceParentOperandBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /ParentOperandForm Do Q';
    $parentForm = 'BT /F1 12 Tf 12 24 Td (Malformed parent operand form leak) Tj ET';
    $decoyForm = 'BT /F1 12 Tf 12 24 Td (Malformed trailing parent decoy form leak) Tj ET';
    $parentCMap = $pageResourceParentOperandBoundaryCMap([
        '41' => 'Malformed parent operand font leak',
    ]);
    $decoyCMap = $pageResourceParentOperandBoundaryCMap([
        '41' => 'Malformed trailing parent decoy font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 1 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 20 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 10 0 R 99 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MalformedParentOperand /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($parentCMap) . " >>\nstream\n{$parentCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 260 80] /Length " . strlen($parentForm) . " >>\nstream\n{$parentForm}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /ParentOperandForm 7 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MalformedTrailingParentDecoy /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($decoyCMap) . " >>\nstream\n{$decoyCMap}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 280 80] /Length " . strlen($decoyForm) . " >>\nstream\n{$decoyForm}\nendstream\nendobj\n"
        . "99 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 8 0 R >> /XObject << /ParentOperandForm 11 0 R >> >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects page Parent references with trailing top-level operands before inherited resource lookup' => static function (
        TestRunner $t
    ) use ($pageResourceParentOperandBoundaryPdf): void {
        $pdf = $pageResourceParentOperandBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['A'], $extractor->extractTextLines($pdf));
        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->same('A', $plainText);
        $t->same("A\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same([], (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf));
        $t->same(false, str_contains($plainText, 'Malformed parent operand font leak'));
        $t->same(false, str_contains($plainText, 'Malformed parent operand form leak'));
        $t->same(false, str_contains($plainText, 'Malformed trailing parent decoy font leak'));
        $t->same(false, str_contains($plainText, 'Malformed trailing parent decoy form leak'));
        $t->same(false, str_contains($plainText, 'ParentOperandForm'));
    },
];
