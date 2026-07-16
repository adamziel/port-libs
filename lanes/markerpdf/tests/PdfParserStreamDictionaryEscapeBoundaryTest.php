<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$streamDictionaryEscapeBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Escaped dictionary boundary) Tj T* (WordPress parser safe) Tj ET';
    $compressed = gzcompress($content);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress stream dictionary escape-boundary fixture.');
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n"
        . "<< /Producer (Ignore /Filter /DCTDecode /Length 1 /DecodeParms << /Predictor 12 >>) "
        . "% /Filter /DCTDecode /Length 1\n"
        . "/Fil#74er /FlateDecode /Len#67th " . strlen($compressed) . " >>\n"
        . "stream\n{$compressed}\nendstream\n"
        . "endobj\n%%EOF";
};

$nestedStreamDictionaryNamePdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Nested dictionary names ignored) Tj T* (Top level stream wins) Tj ET';
    $compressed = gzcompress($content);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress nested stream dictionary fixture.');
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n"
        . "<< /Metadata << /Filter /DCTDecode /Length 1 /DecodeParms << /Predictor 12 >> >> "
        . "/Filter /FlateDecode /Length " . strlen($compressed) . " >>\n"
        . "stream\n{$compressed}\nendstream\n"
        . "endobj\n%%EOF";
};

return [
    'uses escaped top-level stream dictionary names and ignores literal/comment noise before WordPress text extraction' => static function (TestRunner $t) use ($streamDictionaryEscapeBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $streamDictionaryEscapeBoundaryPdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Escaped dictionary boundary', 'WordPress parser safe'], $extractor->extractTextLines($pdf));
        $t->same(['Escaped dictionary boundary', 'WordPress parser safe'], $extractor->extractTextRuns($pdf));
        $t->same("Escaped dictionary boundary\nWordPress parser safe", $plainText);
        $t->same("Escaped dictionary boundary\nWordPress parser safe\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, 'DCTDecode'));
        $t->true(!str_contains($plainText, 'Predictor'));
    },

    'ignores nested stream dictionary names before the real top-level filter and length entries' => static function (TestRunner $t) use ($nestedStreamDictionaryNamePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $nestedStreamDictionaryNamePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Nested dictionary names ignored', 'Top level stream wins'], $extractor->extractTextLines($pdf));
        $t->same(['Nested dictionary names ignored', 'Top level stream wins'], $extractor->extractTextRuns($pdf));
        $t->same("Nested dictionary names ignored\nTop level stream wins", $plainText);
        $t->same("Nested dictionary names ignored\nTop level stream wins\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, 'DCTDecode'));
        $t->true(!str_contains($plainText, 'Metadata'));
    },
];
