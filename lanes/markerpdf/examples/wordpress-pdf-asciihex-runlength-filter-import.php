<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runLengthEncode = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length;) {
        $repeatLength = 1;
        while (
            $offset + $repeatLength < $length
            && $bytes[$offset + $repeatLength] === $bytes[$offset]
            && $repeatLength < 128
        ) {
            $repeatLength++;
        }

        if ($repeatLength >= 4) {
            $encoded .= chr(257 - $repeatLength) . $bytes[$offset];
            $offset += $repeatLength;
            continue;
        }

        $literalStart = $offset;
        $literalLength = 0;
        while ($offset < $length && $literalLength < 128) {
            $lookaheadRepeat = 1;
            while (
                $offset + $lookaheadRepeat < $length
                && $bytes[$offset + $lookaheadRepeat] === $bytes[$offset]
                && $lookaheadRepeat < 128
            ) {
                $lookaheadRepeat++;
            }
            if ($lookaheadRepeat >= 4) {
                break;
            }

            $offset++;
            $literalLength++;
        }

        $encoded .= chr($literalLength - 1) . substr($bytes, $literalStart, $literalLength);
    }

    return $encoded . chr(128);
};

$content = 'BT /F1 12 Tf 72 720 Td (ASCIIHex RunLength Import) Tj T* (Block Ready Content) Tj ET';
$encoded = chunk_split(strtoupper(bin2hex($runLengthEncode($content))), 32, "\n");
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Filter [ /ASCIIHexDecode /RunLengthDecode ] /Length " . strlen($encoded) . " >>\n"
    . "stream\n{$encoded}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-asciihex-runlength-filter ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter',
    'stream_filters' => ['ASCIIHexDecode', 'RunLengthDecode'],
    'length_bounded_stream_payload' => true,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
