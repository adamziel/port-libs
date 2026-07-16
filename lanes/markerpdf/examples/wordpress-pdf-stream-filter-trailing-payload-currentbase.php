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

$directLeak = $content('Unbounded ASCIIHex Content Leak', 720);
$directEncoded = strtoupper(bin2hex($directLeak))
    . '>BT /F1 12 Tf 72 700 Td (Raw Trailing Hex Decoy) Tj ET';

$stackLeak = $content('Unbounded ASCII85 Stack Leak', 684);
$stackCompressed = gzcompress($stackLeak, 0);
if (!is_string($stackCompressed)) {
    throw new RuntimeException('Unable to compress unbounded ASCII85 stack smoke stream.');
}
$stackEncoded = $ascii85($stackCompressed)
    . '~>BT /F1 12 Tf 72 664 Td (Raw Trailing Stack Decoy) Tj ET';

$boundedStack = $content('Bounded Stack Content Import', 648);
$boundedCompressed = gzcompress($boundedStack, 0);
if (!is_string($boundedCompressed)) {
    throw new RuntimeException('Unable to compress bounded ASCII85 stack smoke stream.');
}
$boundedEncoded = $ascii85($boundedCompressed) . "~>\n  \t";

$visibleAfter = $content('Visible After Trailing Payload Boundary', 628);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 7 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter /ASCIIHexDecode /Length " . strlen($directEncoded) . " >>\nstream\n{$directEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /Length " . strlen($stackEncoded) . " >>\nstream\n{$stackEncoded}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /Length " . strlen($boundedEncoded) . " >>\nstream\n{$boundedEncoded}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$metadata = [
    'scenario' => 'wordpress-pdf-stream-filter-trailing-payload-currentbase',
    'support_component' => 'native-pdf-visible-content-stream-filter-boundary',
    'native_boundary' => 'page-visible filtered streams require whitespace-only bytes after explicit filter EOD markers before WordPress paragraph extraction',
    'direct_asciihex_unbounded_rejected' => !str_contains($plainText, 'Unbounded ASCIIHex Content Leak'),
    'stacked_ascii85_unbounded_rejected' => !str_contains($plainText, 'Unbounded ASCII85 Stack Leak'),
    'bounded_stack_preserved' => in_array('Bounded Stack Content Import', $lines, true),
    'visible_after_preserved' => in_array('Visible After Trailing Payload Boundary', $lines, true),
    'raw_trailing_decoys_excluded' => !str_contains($plainText, 'Raw Trailing Hex Decoy')
        && !str_contains($plainText, 'Raw Trailing Stack Decoy'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:stream-filter-trailing-payload-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
