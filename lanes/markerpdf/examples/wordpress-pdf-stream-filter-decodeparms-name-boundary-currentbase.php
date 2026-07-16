<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$compress = static function (string $content): string {
    $compressed = gzcompress($content);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress stream-filter DecodeParms /Name smoke content.');
    }

    return $compressed;
};

$unsafeContent = 'BT /F1 12 Tf 72 720 Td (DecodeParms Name Flate Smoke Leak) Tj ET';
$unsafeCompressed = $compress($unsafeContent);
$safeCryptContent = 'BT /F1 12 Tf 72 704 Td (Crypt Identity DecodeParms Smoke Preserved) Tj ET';
$safeCryptCompressed = $compress($safeCryptContent);
$visibleAfter = 'BT /F1 12 Tf 72 688 Td (Visible After DecodeParms Name Smoke) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Name /Identity >> /Length " . strlen($unsafeCompressed) . " >>\nstream\n{$unsafeCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name /Identity >> null ] /Length " . strlen($safeCryptCompressed) . " >>\nstream\n{$safeCryptCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expected = [
    'Crypt Identity DecodeParms Smoke Preserved',
    'Visible After DecodeParms Name Smoke',
];

if (
    $lines !== $expected
    || str_contains($plainText, 'DecodeParms Name Flate Smoke Leak')
    || str_contains($plainText, 'FlateDecode')
    || str_contains($plainText, "\0")
) {
    throw new RuntimeException('Expected non-Crypt DecodeParms /Name stream to fail closed before WordPress paragraph import.');
}

echo '<!-- markerpdf:stream-filter-decodeparms-name-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter-decodeparms-name-boundary',
    'native_boundary' => 'DecodeParms /Name is accepted for Identity Crypt filters and rejected on ordinary Flate page content streams',
    'paragraphs' => $lines,
    'crypt_identity_decodeparms_name_preserved' => true,
    'non_crypt_decodeparms_name_failed_closed' => !str_contains($plainText, 'DecodeParms Name Flate Smoke Leak'),
    'raw_filter_dictionary_hidden' => !str_contains($plainText, 'FlateDecode'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
