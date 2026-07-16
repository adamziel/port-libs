<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineDctBoundaryPdf = static function (): string {
    $before = 'BT /F1 12 Tf 72 720 Td (Before inline JPEG) Tj ET';
    $between = 'BT /F1 12 Tf 72 700 Td (Between inline JPEGs) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After inline JPEGs) Tj ET';

    $dctPayload = "\xff\xd8\xff\xe0JFIF\0 raw bytes EI BT /F1 12 Tf 72 710 Td (DCTDecode inline raster leak) Tj ET \xff\xd9";
    $dctAliasPayload = "\xff\xd8\xff\xdb quant bytes EI BT /F1 12 Tf 72 690 Td (DCT alias inline raster leak) Tj ET \xff\xd9";
    $hexWrappedPayload = strtoupper(bin2hex("\xff\xd8 ASCIIHex wrapped bytes EI BT /F1 12 Tf 72 690 Td (ASCIIHex DCT inline raster leak) Tj ET \xff\xd9")) . '>';

    $content = $before . "\n"
        . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /DCTDecode ID\n{$dctPayload}\nEI\n"
        . $between . "\n"
        . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /DCT ID\n{$dctAliasPayload}\nEI\n"
        . "BT /F1 12 Tf 72 690 Td (After DCT alias) Tj ET\n"
        . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F [/AHx /DCTDecode] ID\n{$hexWrappedPayload}\nEI\n"
        . $after;

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$inlineDctSegmentBoundaryPdf = static function (): string {
    $before = 'BT /F1 12 Tf 72 720 Td (Before inline APP JPEG) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After inline APP JPEG) Tj ET';
    $segmentLeak = 'BT /F1 12 Tf 72 700 Td (Inline APP DCT segment leak) Tj ET';
    $segmentPayload = "APP segment bytes before false EOI \xff\xd9 EI {$segmentLeak} still inside segment";
    $jpegPayload = "\xff\xd8"
        . "\xff\xe1" . pack('n', strlen($segmentPayload) + 2) . $segmentPayload
        . "\xff\xd9";

    $content = $before . "\n"
        . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /DCTDecode ID\n{$jpegPayload}\nEI\n"
        . $after;

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

return [
    'keeps inline DCTDecode image payloads inside JPEG EOI boundaries before WordPress text extraction' => static function (TestRunner $t) use ($inlineDctBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineDctBoundaryPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $expected = [
            'Before inline JPEG',
            'Between inline JPEGs',
            'After DCT alias',
            'After inline JPEGs',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Before inline JPEG\nBetween inline JPEGs\nAfter DCT alias\nAfter inline JPEGs", $plainText);
        $t->same("Before inline JPEG\nBetween inline JPEGs\nAfter DCT alias\nAfter inline JPEGs\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'DCTDecode inline raster leak'));
        $t->true(!str_contains($plainText, 'DCT alias inline raster leak'));
        $t->true(!str_contains($plainText, 'ASCIIHex DCT inline raster leak'));
        $t->true(!str_contains($plainText, 'JFIF'));
    },
    'ignores false inline DCTDecode EOI markers inside length-coded APP segments' => static function (TestRunner $t) use ($inlineDctSegmentBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineDctSegmentBoundaryPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before inline APP JPEG',
            'After inline APP JPEG',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Before inline APP JPEG\nAfter inline APP JPEG", $plainText);
        $t->same("Before inline APP JPEG\nAfter inline APP JPEG\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Inline APP DCT segment leak'));
        $t->true(!str_contains($plainText, 'APP segment bytes'));
        $t->true(!str_contains($plainText, 'still inside segment'));
    },
];
