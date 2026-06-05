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
];
