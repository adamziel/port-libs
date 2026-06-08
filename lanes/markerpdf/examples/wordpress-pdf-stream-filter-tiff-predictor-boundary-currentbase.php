<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$packSamples = static function (array $samples, int $bitsPerComponent): string {
    $bitLength = count($samples) * $bitsPerComponent;
    $bytes = str_repeat("\0", intdiv($bitLength + 7, 8));
    $maxSample = (1 << $bitsPerComponent) - 1;
    foreach ($samples as $sampleIndex => $sample) {
        if (!is_int($sample) || $sample < 0 || $sample > $maxSample) {
            throw new RuntimeException('Focused TIFF predictor smoke uses an invalid sample value.');
        }

        for ($bit = 0; $bit < $bitsPerComponent; $bit++) {
            $sourceShift = $bitsPerComponent - 1 - $bit;
            if ((($sample >> $sourceShift) & 1) === 0) {
                continue;
            }

            $absoluteBit = ($sampleIndex * $bitsPerComponent) + $bit;
            $byteIndex = intdiv($absoluteBit, 8);
            $targetShift = 7 - ($absoluteBit % 8);
            $bytes[$byteIndex] = chr(ord($bytes[$byteIndex]) | (1 << $targetShift));
        }
    }

    return $bytes;
};

$tiffPredictorFourBit = static function (string $bytes) use ($packSamples): string {
    $samples = [];
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset++) {
        $value = ord($bytes[$offset]);
        $samples[] = ($value >> 4) & 0x0f;
        $samples[] = $value & 0x0f;
    }

    $encodedSamples = [];
    foreach ($samples as $index => $sample) {
        $encodedSamples[] = $index === 0 ? $sample : ($sample - $samples[$index - 1]) & 0x0f;
    }

    return $packSamples($encodedSamples, 4);
};

$content = 'BT /F1 12 Tf 72 720 Td (WP TIFF Predictor Four Bit) Tj T* (Packed Stream Rows) Tj ET';
$columns = strlen($content) * 2;
$encoded = $tiffPredictorFourBit($content);
$compressed = gzcompress($encoded);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress WordPress packed TIFF predictor fixture.');
}
$visibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After TIFF Predictor Boundary) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 2 /Colors 1 /BitsPerComponent 4 /Columns {$columns} >> /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expectedLines = [
    'WP TIFF Predictor Four Bit',
    'Packed Stream Rows',
    'Visible After TIFF Predictor Boundary',
];

$evidence = [
    'scenario' => 'wordpress_pdf_stream_filter_tiff_predictor_boundary',
    'stream_filters' => ['FlateDecode'],
    'decode_parms' => [
        'Predictor' => 2,
        'Colors' => 1,
        'BitsPerComponent' => 4,
        'Columns' => $columns,
    ],
    'paragraphs' => $lines,
    'packed_tiff_predictor_decoded' => $lines === $expectedLines && $plainText === implode("\n", $expectedLines),
    'dictionary_tokens_not_leaked' => !str_contains($plainText, 'BitsPerComponent')
        && !str_contains($plainText, 'FlateDecode')
        && !str_contains($plainText, 'Predictor 2'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (in_array('--self-test', $argv, true)) {
    foreach (['packed_tiff_predictor_decoded', 'dictionary_tokens_not_leaked'] as $flag) {
        if (($evidence[$flag] ?? false) !== true) {
            throw new RuntimeException('Failed markerPDF packed TIFF predictor smoke: ' . $flag);
        }
    }

    echo json_encode(['self_test_passed' => true] + $evidence, JSON_UNESCAPED_SLASHES) . "\n";
    return;
}

echo "<!-- wp:comment {\"markerpdf_stream_filter_tiff_predictor_boundary\":"
    . htmlspecialchars(json_encode($evidence, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "} -->\n";
echo "<!-- /wp:comment -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
