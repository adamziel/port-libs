<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$buildPdf = static function (string $content, string $streamDictionary): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n{$streamDictionary}\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$content = "BT /F1 12 Tf 72 720 Td (Before Text BI Endstream) Tj\n"
    . "BI /W 1 /H 1 /CS /G /BPC 8 ID\n"
    . "(Text Object BI With EI Survives) Tj\n"
    . "EI\n"
    . "endstream\n"
    . "(After Fake Endstream Token Survives) Tj ET\n"
    . "BT /F1 12 Tf 72 704 Td (After Text BI Endstream) Tj ET\n";
$expectedLines = [
    'Before Text BI EndstreamText Object BI With EI SurvivesAfter Fake Endstream Token Survives',
    'After Text BI Endstream',
];
$streamDictionaries = [
    'missing_length_text_object_bi_survives_fake_endstream' => '<< >>',
    'short_length_text_object_bi_survives_fake_endstream' => '<< /Length 50 >>',
    'overdeclared_length_text_object_bi_survives_fake_endstream' => '<< /Length 9999 >>',
];

$extractor = new PdfTextExtractor();
$flags = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];
$wordpressLines = [];
foreach ($streamDictionaries as $flag => $streamDictionary) {
    $pdf = $buildPdf($content, $streamDictionary);
    $lines = $extractor->extractTextLines($pdf);
    $plainText = $extractor->extractPlainText($pdf);
    $flags[$flag] = $lines === $expectedLines;
    $flags[$flag . '_excludes_fake_endstream_operator_text'] = !str_contains($plainText, 'endstream');

    if ($wordpressLines === []) {
        $wordpressLines = $lines;
    }
}

foreach ($flags as $flag => $passed) {
    if ($passed !== true && $passed !== false) {
        throw new RuntimeException('Unexpected non-boolean smoke flag: ' . $flag);
    }

    if ($flag !== 'executes_python_or_models' && $flag !== 'executes_external_pdf_tools' && !$passed) {
        throw new RuntimeException('Inline-image text-object stream boundary smoke failed: ' . $flag);
    }
}

echo '<!-- markerpdf-inline-image-text-object-stream-boundary-currentbase '
    . json_encode($flags, JSON_UNESCAPED_SLASHES)
    . " -->\n";

foreach ($wordpressLines as $line) {
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "</p>\n";
}
