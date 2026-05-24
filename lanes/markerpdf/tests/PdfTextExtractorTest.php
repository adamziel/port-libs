<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfWithStreams = static function (array $streams): string {
    $pdf = "%PDF-1.4\n";
    foreach (array_values($streams) as $index => $content) {
        $objectNumber = $index + 1;
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf . "%%EOF";
};

$ascii85Encode = static function (string $bytes): string {
    $encoded = '<~';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($value === 0 && $chunkLength === 4) {
            $encoded .= 'z';
            continue;
        }

        $chars = '';
        for ($index = 0; $index < 5; $index++) {
            $chars = chr(($value % 85) + 33) . $chars;
            $value = intdiv($value, 85);
        }
        $encoded .= substr($chars, 0, $chunkLength + 1);
    }

    return $encoded . '~>';
};

return [
    'extracts literal and array text operators from content streams' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = "BT /F1 12 Tf 72 720 Td (Hello \\(WP\\)) Tj [(Data) 120 ( Liberation)] TJ ET";
        $runs = (new PdfTextExtractor())->extractTextRuns($pdfWithContent($content));
        $t->same(['Hello (WP)', 'Data Liberation'], $runs);
    },
    'extracts flate encoded content streams' => static function (TestRunner $t): void {
        $content = 'BT <48656c6c6f> Tj ET';
        $compressed = gzcompress($content);
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n%%EOF";
        $t->same('Hello', (new PdfTextExtractor())->extractPlainText($pdf));
    },
    'extracts ASCIIHex stream filters before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (ASCII Hex Import) Tj ET';
        $encoded = chunk_split(strtoupper(bin2hex($content)), 16, "\n") . '>';
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /ASCIIHexDecode /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $compressed = gzcompress('BT /F1 12 Tf 72 720 Td (Stacked Filter Import) Tj ET');
        $stacked = strtoupper(bin2hex($compressed)) . '>';
        $stackedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter [ /ASCIIHexDecode /FlateDecode ] /Length " . strlen($stacked) . " >>\nstream\n{$stacked}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same('ASCII Hex Import', $extractor->extractPlainText($pdf));
        $t->same('Stacked Filter Import', $extractor->extractPlainText($stackedPdf));
    },
    'extracts ASCII85 stream filters before WordPress paragraph rendering' => static function (TestRunner $t) use ($ascii85Encode): void {
        $content = 'BT /F1 12 Tf 72 720 Td (ASCII85 Import) Tj T* (Clean Blocks) Tj ET';
        $encoded = chunk_split($ascii85Encode($content), 22, "\n");
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /ASCII85Decode /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $compressed = gzcompress('BT /F1 12 Tf 72 720 Td (A85 Flate Import) Tj ET');
        $stacked = $ascii85Encode($compressed);
        $stackedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter [ /A85 /FlateDecode ] /Length " . strlen($stacked) . " >>\nstream\n{$stacked}\nendstream\nendobj\n%%EOF";

        $zeroContent = "BT /F1 12 Tf 72 720 Td (Zero\0\0\0\0Group) Tj ET";
        $zeroEncoded = str_replace('!!!!', 'z', $ascii85Encode($zeroContent));
        $zeroPdf = "%PDF-1.4\n1 0 obj\n<< /Filter /A85 /Length " . strlen($zeroEncoded) . " >>\nstream\n{$zeroEncoded}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same("ASCII85 Import\nClean Blocks", $extractor->extractPlainText($pdf));
        $t->same('A85 Flate Import', $extractor->extractPlainText($stackedPdf));
        $t->same("Zero\0\0\0\0Group", $extractor->extractPlainText($zeroPdf));
        $t->same('', $extractor->extractPlainText("%PDF-1.4\n1 0 obj\n<< /Filter /A85 >>\nstream\n!~>\nendstream\nendobj\n%%EOF"));
    },
    'resolves indirect stream filters and benign DecodeParms for WordPress extraction' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Indirect Filter Import) Tj T* (DecodeParms Predictor One) Tj ET';
        $compressed = gzcompress($content);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter 2 0 R /DecodeParms 3 0 R /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
            . "2 0 obj\n/FlateDecode\nendobj\n"
            . "3 0 obj\n<< /Predictor 1 >>\nendobj\n"
            . "%%EOF";

        $stackedContent = 'BT /F1 12 Tf 72 720 Td (Indirect Filter Array) Tj ET';
        $stackedCompressed = gzcompress($stackedContent);
        $stackedEncoded = strtoupper(bin2hex($stackedCompressed)) . '>';
        $stackedPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter 2 0 R /DecodeParms [ null 3 0 R ] /Length " . strlen($stackedEncoded) . " >>\nstream\n{$stackedEncoded}\nendstream\nendobj\n"
            . "2 0 obj\n[ /ASCIIHexDecode /FlateDecode ]\nendobj\n"
            . "3 0 obj\n<< /Predictor 1 /Columns 8 >>\nendobj\n"
            . "%%EOF";

        $unsupportedPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns 8 >> /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
            . "%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same("Indirect Filter Import\nDecodeParms Predictor One", $extractor->extractPlainText($pdf));
        $t->same('Indirect Filter Array', $extractor->extractPlainText($stackedPdf));
        $t->same('', $extractor->extractPlainText($unsupportedPdf));
    },
    'uses ToUnicode CMap codespacerange widths for variable-length WordPress text' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <8141208142> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "2 begincodespacerange\n"
            . "<20> <20>\n"
            . "<8000> <FFFF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<8141> <0057006F0072006400500072006500730073>\n"
            . "<8142> <0042006C006F0063006B0073>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VariableSubset /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same('WordPress Blocks', $extractor->extractPlainText($pdf));
        $t->same(['WordPress Blocks'], $extractor->extractTextRuns($pdf));
    },
    'groups adjacent text operators on the same PDF text line' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Heading) Tj T* (First ) Tj (paragraph) Tj 0 -16 Td (Second line) Tj ET';
        $lines = (new PdfTextExtractor())->extractTextLines($pdfWithContent($content));
        $t->same(['Heading', 'First paragraph', 'Second line'], $lines);
    },
    'uses text advance before same-line Tm gap decisions for WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm (Data) Tj 1 0 0 1 98 720 Tm (base) Tj 1 0 0 1 146 720 Tm (Import) Tj 1 0 0 1 186 720 Tm (er) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));

        $t->same(['Database Importer'], $lines);
        $t->true(!str_contains($extractor->extractPlainText($pdfWithContent($content)), 'Data base'));
    },
    'uses PDF text-state spacing for same-line Tm gap decisions before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 2 Tc 120 Tz 1 0 0 1 72 720 Tm (Data) Tj 1 0 0 1 112 720 Tm (base) Tj ET '
            . 'BT /F1 12 Tf 100 Tz 16 TL 72 720 Td (Intro) Tj 18 2 (Import Profile) " 1 0 0 1 182 704 Tm (s) Tj ET '
            . 'BT /F1 12 Tf 18 Tw 1 0 0 1 72 688 Tm (Media Import) Tj 1 0 0 1 170 688 Tm (er) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Database', 'Intro', 'Import Profiles', 'Media Importer'], $lines);
        $t->true(!str_contains($plainText, 'Data base'));
        $t->true(!str_contains($plainText, 'Profile s'));
        $t->true(!str_contains($plainText, 'Import er'));
    },
    'keeps q Q scoped text state from leaking into later positioned WordPress text' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm q 20 Tc (Data) Tj Q 1 0 0 1 180 720 Tm (Import) Tj 1 0 0 1 235 720 Tm (Tool) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Data Import Tool'], $lines);
        $t->true(!str_contains($plainText, 'ImportTool'));
    },
    'applies TJ numeric positioning adjustments before WordPress Tm gap decisions' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm [(Import ) -1000 (Profile)] TJ 1 0 0 1 178 720 Tm (s) Tj '
            . '1 0 0 1 72 704 Tm [(Site) 1000 (Map)] TJ 1 0 0 1 124 704 Tm (Index) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Import Profiles', 'SiteMap Index'], $lines);
        $t->true(!str_contains($plainText, 'Profile s'));
        $t->true(str_contains($plainText, 'SiteMap Index'));
    },
    'applies Tm horizontal scaling before WordPress Tm gap decisions' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 1.5 0 0 1 72 720 Tm (Import Profile) Tj 1 0 0 1 204 720 Tm (s) Tj '
            . '0.5 0 0 1 72 704 Tm (SiteMap) Tj 1 0 0 1 106 704 Tm (Index) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Import Profiles', 'SiteMap Index'], $lines);
        $t->true(!str_contains($plainText, 'Profile s'));
        $t->true(str_contains($plainText, 'SiteMap Index'));
    },
    'decodes literal continuations and UTF-16BE hex strings' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = "BT (WordPress \\\nimport) Tj T* <FEFF00440061007400610020004C0069006200650072006100740069006F006E> Tj ET";
        $lines = (new PdfTextExtractor())->extractTextLines($pdfWithContent($content));
        $t->same(['WordPress import', 'Data Liberation'], $lines);
    },
    'decodes PDF literal escapes in Tj and TJ WordPress text' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = "BT (Editor\\'s \\(PDF\\) import\\040notes) Tj T* [(Clean\\053blocks) 120 ( keep nested \\(review\\) text)] TJ T* (Line\\\r\ncontinued and slash\\qkept) Tj ET";
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $runs = $extractor->extractTextRuns($pdfWithContent($content));

        $t->same([
            "Editor's (PDF) import notes",
            'Clean+blocks keep nested (review) text',
            'Linecontinued and slashqkept',
        ], $lines);
        $t->same([
            "Editor's (PDF) import notes",
            'Clean+blocks keep nested (review) text',
            'Linecontinued and slashqkept',
        ], $runs);
        $t->true(!str_contains($extractor->extractPlainText($pdfWithContent($content)), '\\q'));
    },
    'extracts block-ready lines from a WordPress import fixture' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-import-content.pdf');
        $t->true(is_string($fixture), 'Fixture should be readable');

        $lines = (new PdfTextExtractor())->extractTextLines($fixture);
        $t->same(['WP Migration', 'Clean blocks from PDF imports', 'Media library captions'], $lines);
    },
    'replays upstream naive_get_text page suffix and get_length_of_text trim boundary' => static function (TestRunner $t) use ($pdfWithStreams): void {
        $pdf = $pdfWithStreams([
            'BT (First page) Tj T* (Second line) Tj ET',
            'BT (Second page) Tj ET',
        ]);
        $extractor = new PdfTextExtractor();
        $expectedText = "First page\nSecond line\nSecond page\n";

        $t->same($expectedText, $extractor->naiveGetText($pdf));

        $path = sys_get_temp_dir() . '/markerpdf-text-length-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, $pdf);
        try {
            $t->same(strlen(trim($expectedText)), $extractor->getLengthOfText($path));
        } finally {
            @unlink($path);
        }
    },
];
