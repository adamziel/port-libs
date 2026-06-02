<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserNameArrayCommentBoundaryPdf = static function (): string {
    $content = '/OC/Hidden#4Cayer BDC BT /F1 12 Tf 72 720 Td (Hidden comment-array leak) Tj ET EMC '
        . '/OC/Visible#4Cayer BDC BT /F1 12 Tf 72 700 Td (Visible comment-array layer) Tj ET EMC';

    return "%PDF-1.4\n"
        . "1 0 obj\n"
        . "<< /Type /Catalog /Pages 2 0 R /OCProperties<</OCGs[20 0 R 21 0 R]/D<</BaseState/OFF/ON[ % 20 0 R /Hidden#4Cayer is only parser-comment review text\n 21 0 R]/Order[20 0 R % /Hidden#4Cayer comment-only name\n 21 0 R]>>>> >>\n"
        . "endobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources<</Font<</F1 4 0 R>>/Properties<</Hidden#4Cayer 20 0 R/Visible#4Cayer 21 0 R>>>> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /OCG /Name (Hidden migration review layer) >>\nendobj\n"
        . "21 0 obj\n<< /Type /OCG /Name (Visible import layer) >>\nendobj\n"
        . "%%EOF";
};

return [
    'ignores commented object references and names inside optional-content arrays before WordPress text extraction' => static function (TestRunner $t) use ($parserNameArrayCommentBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserNameArrayCommentBoundaryPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Visible comment-array layer'], $extractor->extractTextLines($pdf));
        $t->same(['Visible comment-array layer'], $extractor->extractTextRuns($pdf));
        $t->same('Visible comment-array layer', $plainText);
        $t->same("Visible comment-array layer\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Hidden comment-array leak'));
        $t->true(!str_contains($plainText, 'Hidden migration review layer'));
        $t->true(!str_contains($plainText, 'Hidden#4Cayer'));
        $t->true(!str_contains($plainText, '20 0 R'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
