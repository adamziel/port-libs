<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$simpleEncodingIndirectWidthCurrentBasePdf = static function (): string {
    $content = 'BT /Fwide 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'BT /Fthin 12 Tf 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 98 704 Tm <58595A5B> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fwide 2 0 R /Fthin 7 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+WideSimple /Encoding 6 0 R /FirstChar 65 /LastChar 73 /Widths [20 0 R 20 0 R 20 0 R 20 0 R 20 0 R 20 0 R 20 0 R 20 0 R 20 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /BaseEncoding 8 0 R /Differences 9 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+ThinSimple /Encoding 6 0 R /FirstChar 84 /LastChar 91 /Widths 22 0 R >>\nendobj\n"
        . "8 0 obj\n/WinAnsiEncoding\nendobj\n"
        . "9 0 obj\n[65 /W /i /d /e /B /l /o /c /k 84 /T /h /i /n /T /e /x /t]\nendobj\n"
        . "20 0 obj\n1000\nendobj\n"
        . "21 0 obj\n250\nendobj\n"
        . "22 0 obj\n[21 0 R 21 0 R 21 0 R 21 0 R 21 0 R 21 0 R 21 0 R 21 0 R]\nendobj\n%%EOF";
};

return [
    'resolves indirect simple-font encoding and width operands before WordPress text gaps' => static function (TestRunner $t) use ($simpleEncodingIndirectWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $simpleEncodingIndirectWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'ABCD'));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
