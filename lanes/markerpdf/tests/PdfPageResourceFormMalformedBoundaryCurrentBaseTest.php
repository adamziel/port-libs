<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceFormMalformedBoundaryPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Page before malformed form) Tj ET\n"
        . "q /BrokenForm Do Q\n"
        . "BT /F1 12 Tf 72 700 Td (Page after malformed form) Tj ET";
    $brokenForm = 'q /PrivateForm Do Q';
    $privateForm = 'BT /F1 12 Tf 12 24 Td (Private malformed form resource leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> /XObject << /BrokenForm 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources 99 0 R /XObject << /PrivateForm 9 0 R >> /Length " . strlen($brokenForm) . " >>\nstream\n{$brokenForm}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources << /Font << /F1 7 0 R >> >> /Length " . strlen($privateForm) . " >>\nstream\n{$privateForm}\nendstream\nendobj\n"
        . "%%EOF";
};

$pageResourceFormMalformedLiteralBoundaryPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Page before unresolved form) Tj ET\n"
        . "q /BrokenLiteralForm Do Q\n"
        . "BT /F1 12 Tf 72 700 Td (Page after unresolved form) Tj ET";
    $brokenForm = 'BT /F1 12 Tf 12 24 Td (Malformed form literal text leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> /XObject << /BrokenLiteralForm 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources 99 0 R /Length " . strlen($brokenForm) . " >>\nstream\n{$brokenForm}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'form XObject malformed Resources do not promote top-level decoy resource keys' => static function (TestRunner $t) use ($pageResourceFormMalformedBoundaryPdf): void {
        $pdf = $pageResourceFormMalformedBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same([
            'Page before malformed form',
            'Page after malformed form',
        ], $extractor->extractTextLines($pdf));
        $t->same("Page before malformed form\nPage after malformed form", $plainText);
        $t->same(false, str_contains($plainText, 'Private malformed form resource leak'));
    },
    'form XObject malformed Resources block literal form text before WordPress paragraphs' => static function (TestRunner $t) use ($pageResourceFormMalformedLiteralBoundaryPdf): void {
        $pdf = $pageResourceFormMalformedLiteralBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same([
            'Page before unresolved form',
            'Page after unresolved form',
        ], $extractor->extractTextLines($pdf));
        $t->same("Page before unresolved form\nPage after unresolved form", $plainText);
        $t->same(false, str_contains($plainText, 'Malformed form literal text leak'));
    },
];
