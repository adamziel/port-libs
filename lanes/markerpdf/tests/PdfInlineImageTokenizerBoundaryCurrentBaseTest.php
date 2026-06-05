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
];
