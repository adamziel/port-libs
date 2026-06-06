<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageTokenizerBoundaryPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Tokenizer Boundary) Tj ET\n"
        . "BI BT /F1 12 Tf 72 704 Td (Stray BI Text Survives) Tj ET\n"
        . "BT /F1 12 Tf 72 688 Td (After Tokenizer Boundary) Tj ET\n"
        . "BI /W 1 /H 1 /CS /G /BPC 8 ID\n"
        . "BT /F1 12 Tf 72 660 Td (Inline Image Payload Noise) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 672 Td (After Real Inline Image) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerEarlyEiPdf = static function (): string {
    $payload = 'abc EI BT /F1 12 Tf 72 660 Td (Early EI Inline Payload Noise) Tj ET rawtail';
    $content = "BT /F1 12 Tf 72 720 Td (Before Early EI Boundary) Tj ET\n"
        . "BI /W 16 /H 1 /CS /G /BPC 8 ID\n"
        . $payload . "\nEI\n"
        . "BT /F1 12 Tf 72 704 Td (After Early EI Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerTightIdSeparatorPdf = static function (): string {
    $payload = 'abc EI BT /F1 12 Tf 72 660 Td (Tight ID Inline Payload Noise) Tj ET rawtail';
    $content = "BT /F1 12 Tf 72 720 Td (Before Tight ID Boundary) Tj ET\n"
        . "BI /W 16 /H 1 /CS /G /BPC 8 ID"
        . $payload . "\nEI\n"
        . "BT /F1 12 Tf 72 704 Td (After Tight ID Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerCommentAfterIdPdf = static function (): string {
    $payload = 'abc EI BT /F1 12 Tf 72 660 Td (Comment ID Inline Payload Noise) Tj ET rawtail';
    $content = "BT /F1 12 Tf 72 720 Td (Before Comment ID Boundary) Tj ET\n"
        . "BI /W 16 /H 1 /CS /G /BPC 8 ID% comment after ID token\n"
        . $payload . "\nEI\n"
        . "BT /F1 12 Tf 72 704 Td (After Comment ID Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerCommentAfterIdWhitespaceSamplePdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Comment Whitespace Boundary) Tj ET\n"
        . "BI /W 4 /H 1 /CS /G /BPC 8 ID% comment after ID token\n"
        . " abcEI\n"
        . "BT /F1 12 Tf 72 704 Td (After Comment Whitespace Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerTightEiTerminatorPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Tight EI Boundary) Tj ET\n"
        . "BI /W 1 /H 1 /CS /G /BPC 8 IDxEI\n"
        . "BT /F1 12 Tf 72 704 Td (After Tight EI Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerNullWhitespacePdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before NUL Whitespace Boundary) Tj ET\n"
        . "BI\0/W 1\0/H 1\0/CS /G\0/BPC 8\0ID\0"
        . "x\0BT /F1 12 Tf 72 660 Td (NUL Inline Payload Noise) Tj ET rawtail\0EI\0"
        . "BT /F1 12 Tf 72 704 Td (After NUL Whitespace Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerCompactDelimiterPdf = static function (): string {
    $payload = 'abc EI BT /F1 12 Tf 72 660 Td (Compact Delimiter Inline Payload Noise) Tj ET rawtail';
    $content = "BT /F1 12 Tf 72 720 Td (Before Compact Delimiter Boundary) Tj ET\n"
        . "BI/W 16/H 1/CS/G/BPC 8 ID\n"
        . $payload . "\nEI\n"
        . "BT /F1 12 Tf 72 704 Td (After Compact Delimiter Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerNestedDictionaryDecoyPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Nested Dictionary Decoy) Tj ET\n"
        . "BI /DP << /Width 1 /Filter /FlateDecode /BitsPerComponent 8 >> ID\n"
        . "BT /F1 12 Tf 72 704 Td (Nested Dictionary Decoy Text Survives) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (After Nested Dictionary Decoy) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerTextObjectBiDecoyPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Text Object BI) Tj\n"
        . "0 -16 Td BI /W 1 /H 1 /CS /G /BPC 8 ID\n"
        . "(Text Object BI Survives) Tj\n"
        . "0 -16 Td EI\n"
        . "(After Text Object BI) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerJbig2Pdf = static function (): string {
    $payload = "\x97JB2\r\n\x1a\n EI BT /F1 12 Tf 72 660 Td (JBIG2 Inline Payload Noise) Tj ET rawtail\nEI";
    $content = "BT /F1 12 Tf 72 720 Td (Before JBIG2 Boundary) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . $payload . "\n"
        . "BT /F1 12 Tf 72 704 Td (After JBIG2 Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerRawJbig2Pdf = static function (): string {
    $payload = "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Raw JBIG2 Inline Payload Noise) Tj ET rawtail\nEI";
    $content = "BT /F1 12 Tf 72 720 Td (Before Raw JBIG2 Boundary) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . $payload . "\n"
        . "BT /F1 12 Tf 72 704 Td (After Raw JBIG2 Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerPreviewVisibleEiTextPdf = static function (): string {
    $payload = "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Preview Payload EI Noise) Tj ET rawtail\nEI";
    $content = "BT /F1 12 Tf 72 720 Td (Before Visible EI Text) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . $payload . "\n"
        . "BT /F1 12 Tf 72 704 Td (Visible EI Marker Text) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerPreviewVisibleEiTextArrayPdf = static function (): string {
    $payload = "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (TJ Array Payload EI Noise) Tj ET rawtail\nEI";
    $content = "BT /F1 12 Tf 72 720 Td (Before TJ Array EI Text) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . $payload . "\n"
        . "BT /F1 12 Tf 72 704 Td [(Visible EI Array Text)] TJ ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerMarkedActualTextEiPdf = static function (): string {
    $payload = "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Marked ActualText Payload Noise) Tj ET rawtail\nEI";
    $content = "BT /F1 12 Tf 72 720 Td (Before Marked ActualText EI) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . $payload . "\n"
        . "/Span << /ActualText (Visible EI ActualText) >> BDC BT /F1 12 Tf 72 704 Td (Hidden ActualText Source) Tj ET EMC\n"
        . "BT /F1 12 Tf 72 688 Td (After Marked ActualText EI) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerSampleFloorMarkedActualTextPdf = static function (): string {
    $payload = "\x80 EI\n/Span << /ActualText (Visible Sample Floor ActualText) >> BDC BT /F1 12 Tf 72 704 Td (Hidden Sample Floor Text) Tj ET EMC\nEI";
    $content = "BT /F1 12 Tf 72 720 Td (Before Sample Floor ActualText) Tj ET\n"
        . "BI /W 8 /H 1 /IM true /F /JBIG2Decode ID\n"
        . $payload . "\n"
        . "BT /F1 12 Tf 72 688 Td (After Sample Floor ActualText) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerCommentEiPdf = static function (): string {
    $payload = "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Comment EI Payload Noise) Tj ET rawtail\nEI";
    $content = "BT /F1 12 Tf 72 720 Td (Before Comment EI Boundary) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . $payload . "\n"
        . "% comment EI BT /F1 12 Tf 72 640 Td (Comment After Inline Noise) Tj ET\n"
        . "BT /F1 12 Tf 72 704 Td (After Comment EI Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerPreviewFilterChainPdf = static function (): string {
    $runLengthEncode = static function (string $bytes): string {
        $encoded = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 128) {
            $chunk = substr($bytes, $offset, 128);
            $encoded .= chr(strlen($chunk) - 1) . $chunk;
        }

        return $encoded . chr(128);
    };

    $jpxPayload = $runLengthEncode(
        "\xff\x4f wrapped JPX bytes EI BT /F1 12 Tf 72 660 Td (Wrapped JPX Inline Payload Noise) Tj ET \xff\xd9"
    );
    $jbig2Payload = $runLengthEncode(
        "\x97JB2\r\n\x1a\n wrapped JBIG2 bytes EI BT /F1 12 Tf 72 640 Td (Wrapped JBIG2 Inline Payload Noise) Tj ET rawtail"
    );
    $content = "BT /F1 12 Tf 72 720 Td (Before Wrapped Preview Filter) Tj ET\n"
        . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F [/RL /JPXDecode] ID\n"
        . $jpxPayload . "\nEI\n"
        . "BT /F1 12 Tf 72 704 Td (Between Wrapped Preview Filters) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F [/RunLengthDecode /JBIG2Decode] ID\n"
        . $jbig2Payload . "\nEI\n"
        . "BT /F1 12 Tf 72 688 Td (After Wrapped Preview Filters) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerTightPreviewFilterTerminatorPdf = static function (): string {
    $dctPayload = "\xff\xd8\xff\xd9";
    $jpxPayload = "\xff\x4f\xff\xd9";
    $content = "BT /F1 12 Tf 72 720 Td (Before Tight Preview Filter) Tj ET\n"
        . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /DCTDecode ID\n"
        . $dctPayload . "EI\n"
        . "BT /F1 12 Tf 72 704 Td (Between Tight Preview Filters) Tj ET\n"
        . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /JPXDecode ID\n"
        . $jpxPayload . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (After Tight Preview Filters) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerCcittPdf = static function (string $filter, string $prefix): string {
    $payload = "\x00\x10\x04 EI BT /F1 12 Tf 72 660 Td ({$prefix} Inline Payload Noise) Tj ET rawtail\nEI";
    $content = "BT /F1 12 Tf 72 720 Td (Before {$prefix} Inline) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /{$filter} ID\n"
        . $payload . "\n"
        . "BT /F1 12 Tf 72 704 Td (After {$prefix} Inline) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerMultipleCcittPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before First CCITT) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /CCITTFaxDecode ID\n"
        . "\x00\x10\x04 EI BT /F1 12 Tf 72 660 Td (First CCITT Inline Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 704 Td (Between CCITT Images) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /CCF ID\n"
        . "\x00\x10\x04 EI BT /F1 12 Tf 72 640 Td (Second CCF Inline Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (After Second CCITT) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerUnsupportedFilterPdf = static function (): string {
    $payload = 'abc EI BT /F1 12 Tf 72 660 Td (Unsupported Inline Payload Noise) Tj ET rawtail';
    $content = "BT /F1 12 Tf 72 720 Td (Before Unsupported Inline) Tj ET\n"
        . "BI /W 8 /H 1 /CS /G /BPC 8 /F /Crypt ID\n"
        . $payload . "\nEI\n"
        . "BT /F1 12 Tf 72 704 Td (After Unsupported Inline) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerWrappedUnsupportedFilterPdf = static function (): string {
    $runLengthEncode = static function (string $bytes): string {
        $encoded = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 128) {
            $chunk = substr($bytes, $offset, 128);
            $encoded .= chr(strlen($chunk) - 1) . $chunk;
        }

        return $encoded . chr(128);
    };

    $payload = $runLengthEncode('wrapped EI BT /F1 12 Tf 72 660 Td (Wrapped Unsupported Inline Payload Noise) Tj ET rawtail');
    $content = "BT /F1 12 Tf 72 720 Td (Before Wrapped Unsupported Inline) Tj ET\n"
        . "BI /W 8 /H 1 /CS /G /BPC 8 /F [/RL /Crypt] ID\n"
        . $payload . "\nEI\n"
        . "BT /F1 12 Tf 72 704 Td (After Wrapped Unsupported Inline) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerNamedColorSpacePdf = static function (): string {
    $payload = 'abc EI BT /F1 12 Tf 72 660 Td (Named ColorSpace Inline Payload Noise) Tj ET rawtail';
    $content = "BT /F1 12 Tf 72 720 Td (Before Named ColorSpace Inline) Tj ET\n"
        . "BI /W 16 /H 1 /CS /CSWordPress /BPC 8 ID\n"
        . $payload . "\nEI\n"
        . "BT /F1 12 Tf 72 704 Td (After Named ColorSpace Inline) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> /ColorSpace << /CSWordPress /DeviceRGB >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerSlashDelimiterAfterEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Slash EI Boundary) Tj ET\n"
        . "BI /W 1 /H 1 /CS /G /BPC 8 ID\n"
        . "x\n"
        . "EI/Decorative Do\n"
        . "BT /F1 12 Tf 72 704 Td (After Slash EI Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> /XObject << /Decorative 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length 1 >>\nstream\ny\nendstream\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerSlashMarkedContentActualTextPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Slash Marked EI) Tj ET\n"
        . "BI /W 1 /H 1 /CS /G /BPC 8 ID\n"
        . "x\n"
        . "EI/Span << /ActualText (Slash EI ActualText) >> BDC BT /F1 12 Tf 72 700 Td (Hidden Slash EI Text) Tj ET EMC\n"
        . "BT /F1 12 Tf 72 704 Td (After Slash Marked EI) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerNamedPropertyActualTextPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Named Property EI) Tj ET\n"
        . "BI /W 1 /H 1 /CS /G /BPC 8 ID\n"
        . "x\n"
        . "EI/Span /PActual BDC BT /F1 12 Tf 72 700 Td (Hidden Named Property Text) Tj ET EMC\n"
        . "BT /F1 12 Tf 72 704 Td (After Named Property EI) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> /Properties << /PActual << /ActualText (Named Property EI ActualText) >> >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerStrayEiAfterTextPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Stray Operator) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Payload Noise Token) Tj ET rawtail\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 704 Td (Visible Before Stray Operator) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Stray Operator) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerSameLineStrayEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Same Line Stray) Tj ET\n"
        . "BI /W 8 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x80 EI BT /F1 12 Tf 72 704 Td (Visible Same Line Before Stray) Tj ET EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Same Line Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerGraphicsStateStrayEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Q Wrapped Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Q Wrapped Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "q\n"
        . "BT /F1 12 Tf 72 704 Td (Visible Q Wrapped Before Stray) Tj ET\n"
        . "Q\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Q Wrapped Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerCmGraphicsStateStrayEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before CM Wrapped Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (CM Wrapped Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "q\n"
        . "1 0 0 1 24 0 cm\n"
        . "BT /F1 12 Tf 48 704 Td (Visible CM Wrapped Before Stray) Tj ET\n"
        . "Q\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After CM Wrapped Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerClipPathStrayEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Clip Path Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Clip Path Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "q\n"
        . "60 680 260 60 re W n\n"
        . "BT /F1 12 Tf 72 704 Td (Visible Clip Path Before Stray) Tj ET\n"
        . "Q\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Clip Path Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerXObjectDoStrayEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before XObject Do Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (XObject Do Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "/Decorative Do\n"
        . "BT /F1 12 Tf 72 704 Td (Visible XObject Do Before Stray) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After XObject Do Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> /XObject << /Decorative 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length 1 >>\nstream\ny\nendstream\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerMarkedContentPointStrayEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Marked Point Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Marked Point Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "/Artifact MP\n"
        . "BT /F1 12 Tf 72 704 Td (Visible MP Before Stray) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After MP Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Marked Point DP Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "/Span << /MCID 7 >> DP\n"
        . "BT /F1 12 Tf 72 672 Td (Visible DP Before Stray) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 656 Td (Visible After DP Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerColorStateStrayEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Color State Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Color State Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "0 0 1 rg\n"
        . "BT /F1 12 Tf 72 704 Td (Visible RGB Color Before Stray) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After RGB Color Stray) Tj ET\n"
        . "BT /F1 12 Tf 72 672 Td (Before Named Color State Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 650 Td (Named Color State Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "/DeviceRGB cs\n"
        . "0.2 0.3 0.4 scn\n"
        . "BT /F1 12 Tf 72 656 Td (Visible SCN Color Before Stray) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 640 Td (Visible After SCN Color Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerPatternColorStateStrayEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Pattern Color Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Pattern Color Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "/Pattern cs\n"
        . "0.5 /P1 scn\n"
        . "BT /F1 12 Tf 72 704 Td (Visible Pattern Color Before Stray) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Pattern Color Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> /Pattern << /P1 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 2 /TilingType 1 /BBox [0 0 8 8] /XStep 8 /YStep 8 /Resources << >> /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerShadingStrayEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Shading Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Shading Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "/Shade1 sh\n"
        . "BT /F1 12 Tf 72 704 Td (Visible Shading Before Stray) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Shading Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> /Shading << /Shade1 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /ShadingType 2 /ColorSpace /DeviceRGB /Coords [0 0 100 0] /Function << /FunctionType 2 /Domain [0 1] /C0 [0 0 0] /C1 [1 1 1] /N 1 >> /Extend [true true] >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerDashPatternStrayEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Dash Pattern Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Dash Pattern Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "[3 1] 0 d\n"
        . "BT /F1 12 Tf 72 704 Td (Visible Dash Pattern Before Stray) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Dash Pattern Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerCompatibilitySectionStrayEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Compatibility Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Compatibility Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "BX\n"
        . "/FutureOperand FutureOp\n"
        . "BT /F1 12 Tf 72 704 Td (Visible Compatibility Before Stray) Tj ET\n"
        . "EX\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Compatibility Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerTextStateStrayEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Text State Stray) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Text State Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "/F1 10 Tf\n"
        . "14 TL\n"
        . "1.5 Tc\n"
        . "2 Tw\n"
        . "95 Tz\n"
        . "0 Tr\n"
        . "1 Ts\n"
        . "BT 72 704 Td (Visible Text State Before Stray) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Text State Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerSameLineCompatibilitySectionStrayEiPdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Same Line Compatibility Stray) Tj ET\n"
        . "BI /W 8 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x80 EI BX /FutureOperand FutureOp BT /F1 12 Tf 72 704 Td (Visible Same Line Compatibility Before Stray) Tj ET EX EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Same Line Compatibility Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerOuterMarkedContentClosePdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Outer Marked Inline) Tj ET\n"
        . "/Span BMC\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Outer Marked Payload Noise) Tj ET rawtail\n"
        . "EI EMC\n"
        . "BT /F1 12 Tf 72 704 Td (Visible After Outer Marked Inline) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Outer Marked Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerOuterGraphicsStateClosePdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Outer Graphics Inline) Tj ET\n"
        . "q\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Outer Graphics Payload Noise) Tj ET rawtail\n"
        . "EI Q\n"
        . "BT /F1 12 Tf 72 704 Td (Visible After Outer Graphics Inline) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Outer Graphics Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$inlineImageTokenizerOuterCompatibilityClosePdf = static function (): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before Outer Compatibility Inline) Tj ET\n"
        . "BX\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Outer Compatibility Payload Noise) Tj ET rawtail\n"
        . "EI EX\n"
        . "BT /F1 12 Tf 72 704 Td (Visible After Outer Compatibility Inline) Tj ET\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (Visible After Outer Compatibility Stray) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'keeps malformed BI tokenizer boundary from swallowing later WordPress text' => static function (TestRunner $t) use ($inlineImageTokenizerBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerBoundaryPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Tokenizer Boundary',
            'Stray BI Text Survives',
            'After Tokenizer Boundary',
            'After Real Inline Image',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Stray BI Text Survives'));
        $t->true(!str_contains($plainText, 'Inline Image Payload Noise'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'keeps early EI bytes inside unfiltered inline image payload until sample boundary is satisfied' => static function (TestRunner $t) use ($inlineImageTokenizerEarlyEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerEarlyEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Early EI Boundary',
            'After Early EI Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Early EI Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'recovers tight ID inline image data boundaries before WordPress text extraction' => static function (TestRunner $t) use ($inlineImageTokenizerTightIdSeparatorPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerTightIdSeparatorPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Tight ID Boundary',
            'After Tight ID Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Tight ID Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'IDabc'));
    },
    'treats immediate PDF comments after inline image ID as token separators before WordPress text extraction' => static function (TestRunner $t) use ($inlineImageTokenizerCommentAfterIdPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerCommentAfterIdPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Comment ID Boundary',
            'After Comment ID Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Comment ID Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'comment after ID token'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'preserves leading whitespace image samples after comment-bounded inline image ID separators' => static function (TestRunner $t) use ($inlineImageTokenizerCommentAfterIdWhitespaceSamplePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerCommentAfterIdWhitespaceSamplePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Comment Whitespace Boundary',
            'After Comment Whitespace Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'After Comment Whitespace Boundary'));
        $t->true(!str_contains($plainText, 'comment after ID token'));
        $t->true(!str_contains($plainText, 'abcEI'));
    },
    'recovers tight EI inline image terminators after exact sample floors before WordPress text extraction' => static function (TestRunner $t) use ($inlineImageTokenizerTightEiTerminatorPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerTightEiTerminatorPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Tight EI Boundary',
            'After Tight EI Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'IDxEI'));
        $t->true(!str_contains($plainText, 'xEI'));
    },
    'treats PDF NUL whitespace as inline image tokenizer separators before WordPress text extraction' => static function (TestRunner $t) use ($inlineImageTokenizerNullWhitespacePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerNullWhitespacePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before NUL Whitespace Boundary',
            'After NUL Whitespace Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'NUL Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, "\0"));
        $t->true(!str_contains($plainText, '/W 1'));
        $t->true(!str_contains($plainText, 'ID'));
        $t->true(!str_contains($plainText, 'EI'));
    },
    'treats slash-delimited compact BI dictionaries as inline images before WordPress text extraction' => static function (TestRunner $t) use ($inlineImageTokenizerCompactDelimiterPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerCompactDelimiterPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Compact Delimiter Boundary',
            'After Compact Delimiter Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Compact Delimiter Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'BI/W'));
    },
    'keeps malformed BI nested dictionary decoys from becoming inline image boundaries' => static function (TestRunner $t) use ($inlineImageTokenizerNestedDictionaryDecoyPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerNestedDictionaryDecoyPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Nested Dictionary Decoy',
            'Nested Dictionary Decoy Text Survives',
            'After Nested Dictionary Decoy',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Nested Dictionary Decoy Text Survives'));
        $t->true(!str_contains($plainText, 'FlateDecode'));
        $t->true(!str_contains($plainText, 'BitsPerComponent'));
    },
    'keeps BI text-object decoys from becoming inline image boundaries' => static function (TestRunner $t) use ($inlineImageTokenizerTextObjectBiDecoyPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerTextObjectBiDecoyPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Text Object BI',
            'Text Object BI Survives',
            'After Text Object BI',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Text Object BI Survives'));
        $t->true(!str_contains($plainText, 'BitsPerComponent'));
        $t->true(!str_contains($plainText, '/W 1'));
    },
    'keeps JBIG2 preview-only inline image payload closed across delimiter-looking EI bytes' => static function (TestRunner $t) use ($inlineImageTokenizerJbig2Pdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerJbig2Pdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before JBIG2 Boundary',
            'After JBIG2 Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'JBIG2 Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, "\x97JB2"));
    },
    'keeps raw JBIG2 segment inline image payload closed across delimiter-looking EI bytes' => static function (TestRunner $t) use ($inlineImageTokenizerRawJbig2Pdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerRawJbig2Pdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Raw JBIG2 Boundary',
            'After Raw JBIG2 Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Raw JBIG2 Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'closes preview-only inline image fallback before visible text containing EI bytes' => static function (TestRunner $t) use ($inlineImageTokenizerPreviewVisibleEiTextPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerPreviewVisibleEiTextPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Visible EI Text',
            'Visible EI Marker Text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible EI Marker Text'));
        $t->true(!str_contains($plainText, 'Preview Payload EI Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'closes preview-only inline image fallback before TJ array text containing EI bytes' => static function (TestRunner $t) use ($inlineImageTokenizerPreviewVisibleEiTextArrayPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerPreviewVisibleEiTextArrayPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before TJ Array EI Text',
            'Visible EI Array Text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible EI Array Text'));
        $t->true(!str_contains($plainText, 'TJ Array Payload EI Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'closes preview-only inline image fallback before marked ActualText containing EI bytes' => static function (TestRunner $t) use ($inlineImageTokenizerMarkedActualTextEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerMarkedActualTextEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Marked ActualText EI',
            'Visible EI ActualText',
            'After Marked ActualText EI',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible EI ActualText'));
        $t->true(!str_contains($plainText, 'Hidden ActualText Source'));
        $t->true(!str_contains($plainText, 'Marked ActualText Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'closes sample-floor preview fallback before marked ActualText spans after EI' => static function (TestRunner $t) use ($inlineImageTokenizerSampleFloorMarkedActualTextPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerSampleFloorMarkedActualTextPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Sample Floor ActualText',
            'Visible Sample Floor ActualText',
            'After Sample Floor ActualText',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Sample Floor ActualText'));
        $t->true(!str_contains($plainText, 'Hidden Sample Floor Text'));
        $t->true(!str_contains($plainText, 'Sample Floor Text) Tj'));
        $t->true(!str_contains($plainText, "\x80 EI"));
    },
    'ignores EI bytes inside comments after preview-only inline image terminators' => static function (TestRunner $t) use ($inlineImageTokenizerCommentEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerCommentEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Comment EI Boundary',
            'After Comment EI Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Comment EI Payload Noise'));
        $t->true(!str_contains($plainText, 'Comment After Inline Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'keeps preview-only inline image filter chains closed before WordPress text extraction' => static function (TestRunner $t) use ($inlineImageTokenizerPreviewFilterChainPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerPreviewFilterChainPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Wrapped Preview Filter',
            'Between Wrapped Preview Filters',
            'After Wrapped Preview Filters',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Wrapped JPX Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'Wrapped JBIG2 Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, "\x97JB2"));
    },
    'recovers tight DCT and JPX preview-filter terminators before WordPress text extraction' => static function (TestRunner $t) use ($inlineImageTokenizerTightPreviewFilterTerminatorPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerTightPreviewFilterTerminatorPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Tight Preview Filter',
            'Between Tight Preview Filters',
            'After Tight Preview Filters',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Between Tight Preview Filters'));
        $t->true(str_contains($plainText, 'After Tight Preview Filters'));
        $t->true(!str_contains($plainText, 'DCTDecode'));
        $t->true(!str_contains($plainText, 'JPXDecode'));
        $t->true(!str_contains($plainText, 'EI'));
    },
    'keeps CCITTFax preview-only inline image payload closed across delimiter-looking EI bytes' => static function (TestRunner $t) use ($inlineImageTokenizerCcittPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerCcittPdf('CCITTFaxDecode', 'CCITT');
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before CCITT Inline',
            'After CCITT Inline',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'CCITT Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'CCITTFaxDecode'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'keeps CCF abbreviated inline image payload closed across delimiter-looking EI bytes' => static function (TestRunner $t) use ($inlineImageTokenizerCcittPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerCcittPdf('CCF', 'CCF');
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before CCF Inline',
            'After CCF Inline',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'CCF Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'preserves text between multiple CCITT inline image tokenizer fallbacks' => static function (TestRunner $t) use ($inlineImageTokenizerMultipleCcittPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerMultipleCcittPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before First CCITT',
            'Between CCITT Images',
            'After Second CCITT',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Between CCITT Images'));
        $t->true(!str_contains($plainText, 'First CCITT Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'Second CCF Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'keeps unsupported inline image filter payload closed across delimiter-looking EI bytes' => static function (TestRunner $t) use ($inlineImageTokenizerUnsupportedFilterPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerUnsupportedFilterPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Unsupported Inline',
            'After Unsupported Inline',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Unsupported Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'Crypt'));
    },
    'keeps wrapped unsupported inline image filter chains closed before WordPress text extraction' => static function (TestRunner $t) use ($inlineImageTokenizerWrappedUnsupportedFilterPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerWrappedUnsupportedFilterPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Wrapped Unsupported Inline',
            'After Wrapped Unsupported Inline',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Wrapped Unsupported Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'Crypt'));
    },
    'keeps named ColorSpace inline image payload closed before WordPress text extraction' => static function (TestRunner $t) use ($inlineImageTokenizerNamedColorSpacePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerNamedColorSpacePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Named ColorSpace Inline',
            'After Named ColorSpace Inline',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($plainText, 'Named ColorSpace Inline Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'CSWordPress'));
    },
    'treats slash after inline image EI as a delimiter before WordPress text extraction' => static function (TestRunner $t) use ($inlineImageTokenizerSlashDelimiterAfterEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerSlashDelimiterAfterEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Slash EI Boundary',
            'After Slash EI Boundary',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'After Slash EI Boundary'));
        $t->true(!str_contains($plainText, 'Decorative'));
        $t->true(!str_contains($plainText, "\0"));
    },
    'preserves slash-delimited marked ActualText immediately after inline image EI' => static function (TestRunner $t) use ($inlineImageTokenizerSlashMarkedContentActualTextPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerSlashMarkedContentActualTextPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Slash Marked EI',
            'Slash EI ActualText',
            'After Slash Marked EI',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Slash EI ActualText'));
        $t->true(!str_contains($plainText, 'Hidden Slash EI Text'));
        $t->true(!str_contains($plainText, 'EI/Span'));
    },
    'preserves named marked-content ActualText property immediately after inline image EI' => static function (TestRunner $t) use ($inlineImageTokenizerNamedPropertyActualTextPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerNamedPropertyActualTextPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Named Property EI',
            'Named Property EI ActualText',
            'After Named Property EI',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Named Property EI ActualText'));
        $t->true(!str_contains($plainText, 'Hidden Named Property Text'));
        $t->true(!str_contains($plainText, '/PActual'));
        $t->true(!str_contains($plainText, 'EI/Span'));
    },
    'closes preview-only fallback before line-separated text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerStrayEiAfterTextPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerStrayEiAfterTextPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Stray Operator',
            'Visible Before Stray Operator',
            'Visible After Stray Operator',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Before Stray Operator'));
        $t->true(str_contains($plainText, 'Visible After Stray Operator'));
        $t->true(!str_contains($plainText, 'Payload Noise Token'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'closes preview-only fallback before same-line text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerSameLineStrayEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerSameLineStrayEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Same Line Stray',
            'Visible Same Line Before Stray',
            'Visible After Same Line Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Same Line Before Stray'));
        $t->true(str_contains($plainText, 'Visible After Same Line Stray'));
        $t->true(!str_contains($plainText, "\x80 EI"));
    },
    'closes preview-only fallback before graphics-state wrapped text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerGraphicsStateStrayEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerGraphicsStateStrayEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Q Wrapped Stray',
            'Visible Q Wrapped Before Stray',
            'Visible After Q Wrapped Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Q Wrapped Before Stray'));
        $t->true(str_contains($plainText, 'Visible After Q Wrapped Stray'));
        $t->true(!str_contains($plainText, 'Q Wrapped Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'closes preview-only fallback before cm-transformed graphics-state text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerCmGraphicsStateStrayEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerCmGraphicsStateStrayEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before CM Wrapped Stray',
            'Visible CM Wrapped Before Stray',
            'Visible After CM Wrapped Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible CM Wrapped Before Stray'));
        $t->true(str_contains($plainText, 'Visible After CM Wrapped Stray'));
        $t->true(!str_contains($plainText, 'CM Wrapped Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'closes preview-only fallback before clipping-path text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerClipPathStrayEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerClipPathStrayEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Clip Path Stray',
            'Visible Clip Path Before Stray',
            'Visible After Clip Path Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Clip Path Before Stray'));
        $t->true(str_contains($plainText, 'Visible After Clip Path Stray'));
        $t->true(!str_contains($plainText, 'Clip Path Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'closes preview-only fallback before XObject Do text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerXObjectDoStrayEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerXObjectDoStrayEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before XObject Do Stray',
            'Visible XObject Do Before Stray',
            'Visible After XObject Do Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible XObject Do Before Stray'));
        $t->true(str_contains($plainText, 'Visible After XObject Do Stray'));
        $t->true(!str_contains($plainText, 'XObject Do Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'Decorative'));
    },
    'closes preview-only fallback before marked-content point text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerMarkedContentPointStrayEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerMarkedContentPointStrayEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Marked Point Stray',
            'Visible MP Before Stray',
            'Visible After MP Stray',
            'Visible DP Before Stray',
            'Visible After DP Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible MP Before Stray'));
        $t->true(str_contains($plainText, 'Visible After MP Stray'));
        $t->true(str_contains($plainText, 'Visible DP Before Stray'));
        $t->true(str_contains($plainText, 'Visible After DP Stray'));
        $t->true(!str_contains($plainText, 'Marked Point Payload Noise'));
        $t->true(!str_contains($plainText, 'Marked Point DP Payload Noise'));
        $t->true(!str_contains($plainText, '/Artifact MP'));
        $t->true(!str_contains($plainText, '/MCID 7'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'closes preview-only fallback before color-state text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerColorStateStrayEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerColorStateStrayEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Color State Stray',
            'Visible RGB Color Before Stray',
            'Visible After RGB Color Stray',
            'Before Named Color State Stray',
            'Visible SCN Color Before Stray',
            'Visible After SCN Color Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible RGB Color Before Stray'));
        $t->true(str_contains($plainText, 'Visible SCN Color Before Stray'));
        $t->true(str_contains($plainText, 'Visible After SCN Color Stray'));
        $t->true(!str_contains($plainText, 'Color State Payload Noise'));
        $t->true(!str_contains($plainText, 'Named Color State Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'closes preview-only fallback before pattern color-state text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerPatternColorStateStrayEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerPatternColorStateStrayEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Pattern Color Stray',
            'Visible Pattern Color Before Stray',
            'Visible After Pattern Color Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Pattern Color Before Stray'));
        $t->true(str_contains($plainText, 'Visible After Pattern Color Stray'));
        $t->true(!str_contains($plainText, 'Pattern Color Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, '/P1 scn'));
    },
    'closes preview-only fallback before shading paint text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerShadingStrayEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerShadingStrayEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Shading Stray',
            'Visible Shading Before Stray',
            'Visible After Shading Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Shading Before Stray'));
        $t->true(str_contains($plainText, 'Visible After Shading Stray'));
        $t->true(!str_contains($plainText, 'Shading Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'Shade1'));
    },
    'closes preview-only fallback before dash-pattern text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerDashPatternStrayEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerDashPatternStrayEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Dash Pattern Stray',
            'Visible Dash Pattern Before Stray',
            'Visible After Dash Pattern Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Dash Pattern Before Stray'));
        $t->true(str_contains($plainText, 'Visible After Dash Pattern Stray'));
        $t->true(!str_contains($plainText, 'Dash Pattern Payload Noise'));
        $t->true(!str_contains($plainText, '[3 1] 0 d'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'closes preview-only fallback before text-state operators and text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerTextStateStrayEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerTextStateStrayEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Text State Stray',
            'Visible Text State Before Stray',
            'Visible After Text State Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Text State Before Stray'));
        $t->true(str_contains($plainText, 'Visible After Text State Stray'));
        $t->true(!str_contains($plainText, 'Text State Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, '/F1 10 Tf'));
    },
    'closes preview-only fallback before compatibility-section text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerCompatibilitySectionStrayEiPdf, $inlineImageTokenizerSameLineCompatibilitySectionStrayEiPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerCompatibilitySectionStrayEiPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Compatibility Stray',
            'Visible Compatibility Before Stray',
            'Visible After Compatibility Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible Compatibility Before Stray'));
        $t->true(str_contains($plainText, 'Visible After Compatibility Stray'));
        $t->true(!str_contains($plainText, 'Compatibility Payload Noise'));
        $t->true(!str_contains($plainText, '/FutureOperand'));
        $t->true(!str_contains($plainText, 'FutureOp'));
        $t->true(!str_contains($plainText, 'rawtail'));

        $sameLinePdf = $inlineImageTokenizerSameLineCompatibilitySectionStrayEiPdf();
        $sameLinePlainText = $extractor->extractPlainText($sameLinePdf);
        $sameLineExpected = [
            'Before Same Line Compatibility Stray',
            'Visible Same Line Compatibility Before Stray',
            'Visible After Same Line Compatibility Stray',
        ];

        $t->same($sameLineExpected, $extractor->extractTextLines($sameLinePdf));
        $t->same($sameLineExpected, $extractor->extractTextRuns($sameLinePdf));
        $t->same(implode("\n", $sameLineExpected), $sameLinePlainText);
        $t->same(implode("\n", $sameLineExpected) . "\n", $extractor->naiveGetText($sameLinePdf));
        $t->same(['1'], $extractor->extractPageLabels($sameLinePdf));
        $t->same(1, $extractor->extractOutlineMetadata($sameLinePdf)['pages']);
        $t->true(str_contains($sameLinePlainText, 'Visible Same Line Compatibility Before Stray'));
        $t->true(str_contains($sameLinePlainText, 'Visible After Same Line Compatibility Stray'));
        $t->true(!str_contains($sameLinePlainText, '/FutureOperand'));
        $t->true(!str_contains($sameLinePlainText, 'FutureOp'));
        $t->true(!str_contains($sameLinePlainText, "\x80 EI BX"));
    },
    'closes preview-only fallback before externally closed marked-content text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerOuterMarkedContentClosePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerOuterMarkedContentClosePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Outer Marked Inline',
            'Visible After Outer Marked Inline',
            'Visible After Outer Marked Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible After Outer Marked Inline'));
        $t->true(str_contains($plainText, 'Visible After Outer Marked Stray'));
        $t->true(!str_contains($plainText, 'Outer Marked Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, '/Span BMC'));
        $t->true(!str_contains($plainText, 'EMC'));
    },
    'closes preview-only fallback before externally closed graphics-state text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerOuterGraphicsStateClosePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerOuterGraphicsStateClosePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Outer Graphics Inline',
            'Visible After Outer Graphics Inline',
            'Visible After Outer Graphics Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible After Outer Graphics Inline'));
        $t->true(str_contains($plainText, 'Visible After Outer Graphics Stray'));
        $t->true(!str_contains($plainText, 'Outer Graphics Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
    },
    'closes preview-only fallback before externally closed compatibility text followed by stray EI operator' => static function (TestRunner $t) use ($inlineImageTokenizerOuterCompatibilityClosePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $inlineImageTokenizerOuterCompatibilityClosePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Before Outer Compatibility Inline',
            'Visible After Outer Compatibility Inline',
            'Visible After Outer Compatibility Stray',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(str_contains($plainText, 'Visible After Outer Compatibility Inline'));
        $t->true(str_contains($plainText, 'Visible After Outer Compatibility Stray'));
        $t->true(!str_contains($plainText, 'Outer Compatibility Payload Noise'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->true(!str_contains($plainText, 'BX'));
        $t->true(!str_contains($plainText, 'EX'));
    },
];
