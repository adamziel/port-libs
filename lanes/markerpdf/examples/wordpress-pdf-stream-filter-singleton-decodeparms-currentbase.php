<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85 = static function (string $bytes): string {
    $encoded = '';
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

$ambiguous = 'BT /F1 12 Tf 72 720 Td (WP Ambiguous Singleton DecodeParms Leak) Tj ET';
$aligned = 'BT /F1 12 Tf 72 700 Td (WP Aligned Stream Stack Import) Tj ET';
$visibleAfter = 'BT /F1 12 Tf 72 680 Td (WP Visible After Singleton DecodeParms) Tj ET';

$ambiguousCompressed = gzcompress($ambiguous);
$alignedCompressed = gzcompress($aligned);
if (!is_string($ambiguousCompressed) || !is_string($alignedCompressed)) {
    throw new RuntimeException('Unable to compress WordPress singleton DecodeParms fixture.');
}

$ambiguousEncoded = $ascii85($ambiguousCompressed);
$alignedEncoded = $ascii85($alignedCompressed);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms << /Predictor 1 >> /Length " . strlen($ambiguousEncoded) . " >>\nstream\n{$ambiguousEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Length " . strlen($alignedEncoded) . " >>\nstream\n{$alignedEncoded}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expectedLines = [
    'WP Aligned Stream Stack Import',
    'WP Visible After Singleton DecodeParms',
];

$summary = [
    'scenario' => 'wordpress_pdf_stream_filter_singleton_decodeparms_currentbase',
    'native_boundary' => 'PDF stream filter arrays reject singleton non-null DecodeParms dictionaries unless parameters are explicitly aligned to filter slots',
    'singleton_decodeparms_rejected' => !str_contains($plainText, 'WP Ambiguous Singleton DecodeParms Leak'),
    'aligned_null_decodeparms_imported' => $lines === $expectedLines,
    'filter_tokens_excluded' => !str_contains($plainText, 'ASCII85Decode') && !str_contains($plainText, 'FlateDecode'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'self_test_passed' => $lines === $expectedLines
        && !str_contains($plainText, 'WP Ambiguous Singleton DecodeParms Leak')
        && !str_contains($plainText, 'Predictor'),
];

foreach (['singleton_decodeparms_rejected', 'aligned_null_decodeparms_imported', 'filter_tokens_excluded', 'self_test_passed'] as $flag) {
    if (($summary[$flag] ?? false) !== true) {
        throw new RuntimeException('Failed markerPDF singleton DecodeParms smoke: ' . $flag);
    }
}

echo '<!-- markerpdf:stream-filter-singleton-decodeparms-boundary ' . htmlspecialchars(
    json_encode($summary, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
