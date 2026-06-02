<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pngSubPredictorEncode = static function (string $bytes, int $columns): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        if (strlen($row) !== $columns) {
            throw new RuntimeException('DecodeParms owner smoke rows must be fixed-width.');
        }

        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
    }

    return $encoded;
};

$rowOne = 'BT /F1 12 Tf 72 720 Td (Current DecodeParms Owner) Tj T* ';
$rowTwo = str_pad('(Indirect Length Skips Fake) Tj ET', strlen($rowOne));
$compressed = gzcompress($pngSubPredictorEncode($rowOne . $rowTwo, strlen($rowOne)));
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress DecodeParms owner smoke fixture.');
}

$visibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After Owner Boundary) Tj ET';
$carrierPayload = "BT /F1 12 Tf 72 640 Td (Carrier stream text leak) Tj ET\n"
    . "endstream\nendobj\n"
    . "20 0 obj\n<< /Predictor /Twelve /Columns 1 >>\nendobj\n"
    . 'BT /F1 12 Tf 72 620 Td (Post fake decodeparms leak) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "20 0 obj\n<< /Predictor 12 /Columns " . strlen($rowOne) . " >>\nendobj\n"
    . "30 0 obj\n" . strlen($carrierPayload) . "\nendobj\n"
    . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms 20 0 R /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length 30 0 R >>\nstream\n{$carrierPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf-parser-stream-decodeparms-owner-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF stream owner scanning resolves simple indirect Length before accepting embedded fake DecodeParms object headers',
    'uses_current_decodeparms_object' => str_contains($plainText, 'Current DecodeParms Owner')
        && str_contains($plainText, 'Indirect Length Skips Fake'),
    'visible_after_boundary' => str_contains($plainText, 'Visible After Owner Boundary'),
    'fake_decodeparms_object_excluded' => !str_contains($plainText, 'Twelve'),
    'carrier_stream_payload_excluded' => !str_contains($plainText, 'Carrier stream text leak')
        && !str_contains($plainText, 'Post fake decodeparms leak'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
