<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85Encode = static function (string $bytes): string {
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

    return $encoded;
};

$before = "BT /F1 12 Tf 72 720 Td (Before ASCII85 Stack Boundary) Tj ET\n";
while (strlen($before) % 4 !== 0) {
    $before .= ' ';
}

$after = "\nBT /F1 12 Tf 72 704 Td (After ASCII85 Stack Boundary) Tj ET";
$encoded = $ascii85Encode($before)
    . "\nendstream\n!"
    . $ascii85Encode($after)
    . '~>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ null /ASCII85Decode ] >>\nstream\n{$encoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$joined = implode("\n", $lines);

echo '<!-- markerpdf:pdf-stream-filter-stack-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter-stack-boundary',
    'stream_filters' => [null, 'ASCII85Decode'],
    'missing_length_stream_payload' => true,
    'requires_ascii85_eod_before_endstream_boundary' => true,
    'fake_endstream_payload_excluded' => !str_contains($joined, 'endstream'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'paragraphs' => $lines,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
