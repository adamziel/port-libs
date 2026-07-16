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

    return $encoded;
};

$content = static fn (string $text, int $y): string => "BT /F1 12 Tf 72 {$y} Td ({$text}) Tj ET";

$fullTupleLeak = ' ' . $content('ASCII85 Overflow Full Tuple Leak', 720);
$fullTupleEncoded = 'uuuuu' . $ascii85($fullTupleLeak) . '~>';
$partialTupleLeak = $content('ASCII85 Overflow Partial Tuple Leak', 700);
while (strlen($partialTupleLeak) % 4 !== 0) {
    $partialTupleLeak .= ' ';
}
$partialTupleEncoded = $ascii85($partialTupleLeak) . 'uu~>';
$visibleAfter = $content('Visible After ASCII85 Overflow Boundary', 680);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter /ASCII85Decode /Length " . strlen($fullTupleEncoded) . " >>\nstream\n{$fullTupleEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter /ASCII85Decode /Length " . strlen($partialTupleEncoded) . " >>\nstream\n{$partialTupleEncoded}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$metadata = [
    'scenario' => 'wordpress-pdf-stream-filter-stack-ascii85-overflow-currentbase',
    'support_component' => 'native-pdf-ascii85-stream-filter-decoder',
    'native_boundary' => 'ASCII85 groups must stay within the 32-bit tuple range before WordPress paragraph extraction',
    'full_tuple_overflow_rejected' => !str_contains($plainText, 'ASCII85 Overflow Full Tuple Leak'),
    'partial_tuple_overflow_rejected' => !str_contains($plainText, 'ASCII85 Overflow Partial Tuple Leak'),
    'visible_after_preserved' => in_array('Visible After ASCII85 Overflow Boundary', $lines, true),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:stream-filter-stack-ascii85-overflow-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
